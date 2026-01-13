<?php
/**
 * Training Compliance Service
 * 
 * Manages staff training requirements, tracking, and compliance
 * Feature 2.2: Training Compliance Dashboard
 */

namespace Core\Services;

use PDO;
use Exception;
use DateTime;

class TrainingComplianceService extends BaseService
{    /**
     * Constructor
     * @param PDO $db Database connection
     */
    public function __construct(\PDO $db = null)
    {
        parent::__construct();
        $this->db = $db ?: \App\db\pdo();
    }
    
    /**
     * Get compliance dashboard statistics
     * 
     * @param array $filters Optional filters (department, role, etc.)
     * @return array Statistics
     */
    public function getComplianceStats(array $filters = []): array
    {
        try {
            $stats = [];
            
            // Total staff count
            $totalStaff = $this->getTotalStaffCount($filters);
            $stats['total_staff'] = $totalStaff;
            
            // Compliant staff count
            $compliantStaff = $this->getCompliantStaffCount($filters);
            $stats['compliant_staff'] = $compliantStaff;
            $stats['compliance_percentage'] = $totalStaff > 0 ? 
                round(($compliantStaff / $totalStaff) * 100, 1) : 0;
            
            // Counts by status
            $stats['current_count'] = $this->getTrainingCountByStatus('current', $filters);
            $stats['expiring_soon_count'] = $this->getTrainingCountByStatus('expiring_soon', $filters);
            $stats['expired_count'] = $this->getTrainingCountByStatus('expired', $filters);
            
            // Staff with overdue trainings
            $stats['overdue_staff'] = $this->getOverdueStaffCount($filters);
            
            // Upcoming expirations (next 30 days)
            $stats['upcoming_expirations'] = $this->getUpcomingExpirations(30, $filters);
            
            // Most commonly overdue trainings
            $stats['common_overdue'] = $this->getMostCommonlyOverdueTrainings($filters);
            
            return $stats;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get compliance stats',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get training requirements for a specific role
     * 
     * @param string $role_id
     * @return array
     */
    public function getTrainingRequirementsByRole(string $role_id): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM training_requirements
                WHERE is_active = TRUE
                AND (
                    JSON_CONTAINS(required_roles, :role1)
                    OR JSON_CONTAINS(required_roles, '\"all\"')
                )
                ORDER BY training_category, training_name
            ");
            
            // Format role_id as JSON string for search
            $roleJson = json_encode($role_id);
            $stmt->execute(['role1' => $roleJson]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get training requirements by role',
                'role_id' => $role_id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get training records for a specific user
     * 
     * @param string $user_id
     * @return array
     */
    public function getUserTrainingRecords(string $user_id): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT str.*, tr.training_name, tr.training_category, 
                       tr.recurrence_interval, tr.training_description,
                       u.username as completed_by_name
                FROM staff_training_records str
                JOIN training_requirements tr ON str.requirement_id = tr.requirement_id
                LEFT JOIN user u ON str.completed_by = u.user_id
                WHERE str.user_id = :user_id
                ORDER BY str.expiration_date ASC, tr.training_name
            ");
            
            $stmt->execute(['user_id' => $user_id]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add calculated status if not stored
            foreach ($records as &$record) {
                if (!isset($record['status'])) {
                    $record['status'] = $this->calculateTrainingStatus(
                        $record['expiration_date']
                    );
                }
            }
            
            return $records;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get user training records',
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Record training completion
     * 
     * @param array $data Training record data
     * @return string|null Record ID
     */
    public function recordTrainingCompletion(array $data): ?string
    {
        try {
            $record_id = $this->generateUuid();
            
            // Calculate expiration date based on recurrence interval
            $expiration_date = $this->calculateExpirationDate(
                $data['completion_date'],
                $data['recurrence_interval'] ?? 365
            );
            
            $stmt = $this->db->prepare("
                INSERT INTO staff_training_records
                (record_id, user_id, requirement_id, completion_date, 
                 expiration_date, certification_number, proof_document_path,
                 completed_by, created_at)
                VALUES (:record_id, :user_id, :requirement_id, :completion_date,
                        :expiration_date, :certification_number, :proof_document_path,
                        :completed_by, NOW())
                ON DUPLICATE KEY UPDATE
                    completion_date = VALUES(completion_date),
                    expiration_date = VALUES(expiration_date),
                    certification_number = VALUES(certification_number),
                    proof_document_path = VALUES(proof_document_path),
                    completed_by = VALUES(completed_by),
                    updated_at = NOW()
            ");
            
            $result = $stmt->execute([
                'record_id' => $record_id,
                'user_id' => $data['user_id'],
                'requirement_id' => $data['requirement_id'],
                'completion_date' => $data['completion_date'],
                'expiration_date' => $expiration_date,
                'certification_number' => $data['certification_number'] ?? null,
                'proof_document_path' => $data['proof_document_path'] ?? null,
                'completed_by' => $data['completed_by']
            ]);
            
            if ($result) {
                // Clear any reminders for this training
                $this->clearReminders($data['user_id'], $data['requirement_id']);
                
                // Log the completion
                \App\log\file_log('audit', [
                    'action' => 'training_completed',
                    'record_id' => $record_id,
                    'user_id' => $data['user_id'],
                    'requirement_id' => $data['requirement_id']
                ]);
                
                return $record_id;
            }
            
            return null;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to record training completion',
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get staff members with overdue or expiring trainings
     * 
     * @param int $days_before_expiry Days to look ahead for expiring trainings
     * @param array $filters Optional filters
     * @return array
     */
    public function getStaffWithExpiringTrainings(int $days_before_expiry = 30, array $filters = []): array
    {
        try {
            $query = "
                SELECT DISTINCT u.user_id, u.username, u.email,
                       COUNT(CASE WHEN str.status = 'expired' THEN 1 END) as expired_count,
                       COUNT(CASE WHEN str.status = 'expiring_soon' THEN 1 END) as expiring_count,
                       MIN(str.expiration_date) as earliest_expiration
                FROM user u
                JOIN userrole ur ON u.user_id = ur.user_id
                LEFT JOIN staff_training_records str ON u.user_id = str.user_id
                    AND str.expiration_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                WHERE u.status = 'active'
                GROUP BY u.user_id, u.username, u.email
                HAVING expired_count > 0 OR expiring_count > 0
                ORDER BY expired_count DESC, expiring_count DESC, earliest_expiration ASC
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(['days' => $days_before_expiry]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get staff with expiring trainings',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Send training reminders
     * 
     * @return array Summary of reminders sent
     */
    public function sendTrainingReminders(): array
    {
        try {
            $summary = [
                '30_day' => 0,
                '14_day' => 0,
                '7_day' => 0,
                'overdue' => 0
            ];
            
            // Get trainings expiring at different intervals
            $intervals = [
                ['days' => 30, 'type' => '30_day'],
                ['days' => 14, 'type' => '14_day'],
                ['days' => 7, 'type' => '7_day'],
                ['days' => -1, 'type' => 'overdue'] // Negative for overdue
            ];
            
            foreach ($intervals as $interval) {
                $reminders = $this->getTrainingsNeedingReminder(
                    $interval['days'],
                    $interval['type']
                );
                
                foreach ($reminders as $reminder) {
                    if ($this->sendReminderNotification($reminder, $interval['type'])) {
                        $summary[$interval['type']]++;
                        $this->logReminder($reminder['record_id'], $interval['type']);
                    }
                }
            }
            
            return $summary;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to send training reminders',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Generate compliance report
     * 
     * @param array $filters
     * @param string $format 'array', 'csv', or 'pdf'
     * @return mixed
     */
    public function generateComplianceReport(array $filters = [], string $format = 'array')
    {
        try {
            // Get all staff with their training status
            $reportData = $this->getComplianceReportData($filters);
            
            switch ($format) {
                case 'csv':
                    return $this->formatAsCSV($reportData);
                    
                case 'pdf':
                    return $this->formatAsPDF($reportData);
                    
                default:
                    return $reportData;
            }
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to generate compliance report',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    // Private helper methods
    
    private function getTotalStaffCount(array $filters = []): int
    {
        $query = "SELECT COUNT(DISTINCT u.user_id) FROM user u WHERE u.status = 'active'";
        $stmt = $this->db->query($query);
        return (int) $stmt->fetchColumn();
    }
    
    private function getCompliantStaffCount(array $filters = []): int
    {
        $query = "
            SELECT COUNT(DISTINCT u.user_id)
            FROM user u
            WHERE u.status = 'active'
            AND NOT EXISTS (
                SELECT 1 
                FROM training_requirements tr
                JOIN userrole ur ON u.user_id = ur.user_id
                WHERE tr.is_active = TRUE
                AND (JSON_CONTAINS(tr.required_roles, JSON_QUOTE(ur.role_id))
                     OR JSON_CONTAINS(tr.required_roles, '\"all\"'))
                AND NOT EXISTS (
                    SELECT 1
                    FROM staff_training_records str
                    WHERE str.user_id = u.user_id
                    AND str.requirement_id = tr.requirement_id
                    AND str.expiration_date > CURDATE()
                )
            )
        ";
        
        $stmt = $this->db->query($query);
        return (int) $stmt->fetchColumn();
    }
    
    private function getTrainingCountByStatus(string $status, array $filters = []): int
    {
        if ($status === 'current') {
            $condition = "expiration_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($status === 'expiring_soon') {
            $condition = "expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } else { // expired
            $condition = "expiration_date < CURDATE()";
        }
        
        $query = "SELECT COUNT(*) FROM staff_training_records WHERE $condition";
        $stmt = $this->db->query($query);
        return (int) $stmt->fetchColumn();
    }
    
    private function getOverdueStaffCount(array $filters = []): int
    {
        $query = "
            SELECT COUNT(DISTINCT user_id)
            FROM staff_training_records
            WHERE expiration_date < CURDATE()
        ";
        
        $stmt = $this->db->query($query);
        return (int) $stmt->fetchColumn();
    }
    
    private function getUpcomingExpirations(int $days, array $filters = []): array
    {
        $stmt = $this->db->prepare("
            SELECT str.*, tr.training_name, u.username
            FROM staff_training_records str
            JOIN training_requirements tr ON str.requirement_id = tr.requirement_id
            JOIN user u ON str.user_id = u.user_id
            WHERE str.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY str.expiration_date ASC
            LIMIT 10
        ");
        
        $stmt->execute(['days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getMostCommonlyOverdueTrainings(array $filters = []): array
    {
        $stmt = $this->db->query("
            SELECT tr.training_name, COUNT(*) as overdue_count
            FROM staff_training_records str
            JOIN training_requirements tr ON str.requirement_id = tr.requirement_id
            WHERE str.expiration_date < CURDATE()
            GROUP BY tr.requirement_id, tr.training_name
            ORDER BY overdue_count DESC
            LIMIT 5
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function calculateTrainingStatus(string $expiration_date): string
    {
        $expiry = new DateTime($expiration_date);
        $today = new DateTime();
        $diff = $today->diff($expiry);
        
        if ($expiry < $today) {
            return 'expired';
        } elseif ($diff->days <= 30) {
            return 'expiring_soon';
        } else {
            return 'current';
        }
    }
    
    private function calculateExpirationDate(string $completion_date, int $recurrence_interval): string
    {
        $date = new DateTime($completion_date);
        $date->add(new \DateInterval("P{$recurrence_interval}D"));
        return $date->format('Y-m-d');
    }
    
    private function clearReminders(string $user_id, string $requirement_id): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM training_reminders
            WHERE record_id IN (
                SELECT record_id
                FROM staff_training_records
                WHERE user_id = :user_id AND requirement_id = :requirement_id
            )
        ");
        
        $stmt->execute([
            'user_id' => $user_id,
            'requirement_id' => $requirement_id
        ]);
    }
    
    private function getTrainingsNeedingReminder(int $days, string $reminder_type): array
    {
        $condition = $days >= 0 
            ? "str.expiration_date = DATE_ADD(CURDATE(), INTERVAL :days DAY)"
            : "str.expiration_date < CURDATE()";
            
        $stmt = $this->db->prepare("
            SELECT str.*, tr.training_name, u.username, u.email
            FROM staff_training_records str
            JOIN training_requirements tr ON str.requirement_id = tr.requirement_id
            JOIN user u ON str.user_id = u.user_id
            WHERE $condition
            AND NOT EXISTS (
                SELECT 1 FROM training_reminders rem
                WHERE rem.record_id = str.record_id
                AND rem.reminder_type = :reminder_type
                AND DATE(rem.sent_at) = CURDATE()
            )
        ");
        
        $params = ['reminder_type' => $reminder_type];
        if ($days >= 0) {
            $params['days'] = $days;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function sendReminderNotification(array $reminder, string $type): bool
    {
        // TODO: Implement actual email sending
        // For now, just log the notification
        \App\log\file_log('audit', [
            'action' => 'training_reminder_sent',
            'type' => $type,
            'user' => $reminder['email'],
            'training' => $reminder['training_name'],
            'expiration' => $reminder['expiration_date']
        ]);
        
        return true;
    }
    
    private function logReminder(string $record_id, string $reminder_type): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO training_reminders (reminder_id, record_id, reminder_type, sent_at)
            VALUES (:reminder_id, :record_id, :reminder_type, NOW())
        ");
        
        $stmt->execute([
            'reminder_id' => $this->generateUuid(),
            'record_id' => $record_id,
            'reminder_type' => $reminder_type
        ]);
    }
    
    private function getComplianceReportData(array $filters = []): array
    {
        $stmt = $this->db->query("
            SELECT u.user_id, u.username, u.email,
                   tr.training_name, tr.training_category,
                   str.completion_date, str.expiration_date, str.certification_number,
                   CASE
                       WHEN str.expiration_date IS NULL THEN 'Not Completed'
                       WHEN str.expiration_date < CURDATE() THEN 'Expired'
                       WHEN str.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
                       ELSE 'Current'
                   END as status
            FROM user u
            CROSS JOIN training_requirements tr
            LEFT JOIN staff_training_records str ON u.user_id = str.user_id 
                AND tr.requirement_id = str.requirement_id
            WHERE u.status = 'active' AND tr.is_active = TRUE
            ORDER BY u.username, tr.training_category, tr.training_name
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function formatAsCSV(array $data): string
    {
        $output = fopen('php://temp', 'w+');
        
        // Header
        fputcsv($output, [
            'Staff Name',
            'Email',
            'Training',
            'Category',
            'Completion Date',
            'Expiration Date',
            'Certification Number',
            'Status'
        ]);
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, [
                $row['username'],
                $row['email'],
                $row['training_name'],
                $row['training_category'],
                $row['completion_date'] ?? 'Not Completed',
                $row['expiration_date'] ?? 'N/A',
                $row['certification_number'] ?? '',
                $row['status']
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    private function formatAsPDF(array $data): string
    {
        // TODO: Implement PDF generation using TCPDF
        // For now, return a placeholder
        return "PDF generation not yet implemented";
    }
}