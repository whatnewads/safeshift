<?php
/**
 * RegistrationRepository.php - Repository for Registration Dashboard Data
 * 
 * Provides data access methods for the registration dashboard including
 * queue statistics, pending registrations, and patient search.
 * 
 * @package    SafeShift\Model\Repositories
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Repositories;

use PDO;
use PDOException;
use DateTime;

/**
 * Registration Repository
 * 
 * Handles database operations for the registration dashboard vertical slice.
 * Provides queue statistics, pending registration lists, and patient search.
 */
class RegistrationRepository
{
    private PDO $pdo;
    
    /** @var string Patients table name */
    private string $patientsTable = 'patients';
    
    /** @var string Encounters table name */
    private string $encountersTable = 'encounters';
    
    /** @var string Appointments table name */
    private string $appointmentsTable = 'appointments';

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get queue statistics for the registration dashboard
     * 
     * Returns counts for:
     * - waiting: patients with status 'arrived' waiting to be processed
     * - inProgress: patients currently being registered (status 'in_progress')
     * - completedToday: registrations completed today
     * 
     * @return array{waiting: int, inProgress: int, completedToday: int}
     */
    public function getQueueStats(): array
    {
        try {
            $today = (new DateTime())->format('Y-m-d');
            
            // Get waiting count (encounters with status 'arrived')
            $waitingSql = "SELECT COUNT(*) FROM {$this->encountersTable} 
                           WHERE status = 'arrived' 
                           AND deleted_at IS NULL";
            $waitingStmt = $this->pdo->prepare($waitingSql);
            $waitingStmt->execute();
            $waiting = (int) $waitingStmt->fetchColumn();
            
            // Get in-progress count (encounters with status 'in_progress')
            $inProgressSql = "SELECT COUNT(*) FROM {$this->encountersTable} 
                              WHERE status = 'in_progress' 
                              AND deleted_at IS NULL";
            $inProgressStmt = $this->pdo->prepare($inProgressSql);
            $inProgressStmt->execute();
            $inProgress = (int) $inProgressStmt->fetchColumn();
            
            // Get completed today count
            $completedSql = "SELECT COUNT(*) FROM {$this->encountersTable} 
                             WHERE status = 'completed' 
                             AND DATE(created_at) = :today
                             AND deleted_at IS NULL";
            $completedStmt = $this->pdo->prepare($completedSql);
            $completedStmt->execute(['today' => $today]);
            $completedToday = (int) $completedStmt->fetchColumn();
            
            return [
                'waiting' => $waiting,
                'inProgress' => $inProgress,
                'completedToday' => $completedToday
            ];
        } catch (PDOException $e) {
            error_log('RegistrationRepository::getQueueStats error: ' . $e->getMessage());
            return [
                'waiting' => 0,
                'inProgress' => 0,
                'completedToday' => 0
            ];
        }
    }

    /**
     * Get pending registrations
     * 
     * Returns patients with encounters in 'arrived' status,
     * ordered by arrival time (oldest first).
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   firstName: string,
     *   lastName: string,
     *   dateOfBirth: string,
     *   arrivedAt: string,
     *   encounterId: string,
     *   priority: string
     * }>
     */
    public function getPendingRegistrations(int $limit = 20): array
    {
        try {
            $sql = "SELECT 
                        p.patient_id,
                        p.legal_first_name,
                        p.legal_last_name,
                        p.dob,
                        p.phone,
                        e.encounter_id,
                        e.arrived_on,
                        e.created_at as encounter_created,
                        e.chief_complaint
                    FROM {$this->encountersTable} e
                    JOIN {$this->patientsTable} p ON e.patient_id = p.patient_id
                    WHERE e.status = 'arrived'
                    AND e.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                    ORDER BY e.arrived_on ASC, e.created_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatPendingRegistration($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('RegistrationRepository::getPendingRegistrations error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search patients by name, phone, or date of birth
     * 
     * @param string $query Search query
     * @param int $limit Maximum number of results (default: 50)
     * @return array<int, array{
     *   id: string,
     *   firstName: string,
     *   lastName: string,
     *   dateOfBirth: string,
     *   phone: string|null,
     *   email: string|null
     * }>
     */
    public function searchPatients(string $query, int $limit = 50): array
    {
        try {
            $query = trim($query);
            
            if (empty($query)) {
                return [];
            }
            
            $searchTerm = '%' . $query . '%';
            
            $sql = "SELECT 
                        patient_id,
                        legal_first_name,
                        legal_last_name,
                        dob,
                        phone,
                        email,
                        preferred_name
                    FROM {$this->patientsTable}
                    WHERE deleted_at IS NULL
                    AND (
                        legal_first_name LIKE :search_term
                        OR legal_last_name LIKE :search_term
                        OR CONCAT(legal_first_name, ' ', legal_last_name) LIKE :search_term
                        OR phone LIKE :search_term
                        OR email LIKE :search_term
                        OR DATE_FORMAT(dob, '%Y-%m-%d') LIKE :search_term
                    )
                    ORDER BY legal_last_name ASC, legal_first_name ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':search_term', $searchTerm, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatPatientSearchResult($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('RegistrationRepository::searchPatients error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get patient by ID
     * 
     * @param string $patientId Patient UUID
     * @return array|null Patient data or null if not found
     */
    public function findPatientById(string $patientId): ?array
    {
        try {
            $sql = "SELECT 
                        patient_id,
                        legal_first_name,
                        legal_last_name,
                        preferred_name,
                        dob,
                        sex_assigned_at_birth,
                        gender_identity,
                        phone,
                        email,
                        county,
                        zip_code
                    FROM {$this->patientsTable}
                    WHERE patient_id = :patient_id
                    AND deleted_at IS NULL";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['patient_id' => $patientId]);
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                return null;
            }
            
            return $this->formatPatientSearchResult($row);
        } catch (PDOException $e) {
            error_log('RegistrationRepository::findPatientById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format pending registration row for API response
     * 
     * @param array $row Database row
     * @return array Formatted registration data
     */
    private function formatPendingRegistration(array $row): array
    {
        $arrivedAt = $row['arrived_on'] ?? $row['encounter_created'];
        $waitTime = $this->calculateWaitTime($arrivedAt);
        
        // Determine priority based on wait time
        $priority = $this->determinePriority($arrivedAt);
        
        return [
            'id' => $row['patient_id'],
            'firstName' => $row['legal_first_name'],
            'lastName' => $row['legal_last_name'],
            'dateOfBirth' => $row['dob'],
            'waitTime' => $waitTime,
            'priority' => $priority,
            'encounterId' => $row['encounter_id'],
            'phone' => $row['phone'] ?? null,
            'chiefComplaint' => $row['chief_complaint'] ?? null
        ];
    }

    /**
     * Format patient row for search result
     * 
     * @param array $row Database row
     * @return array Formatted patient data
     */
    private function formatPatientSearchResult(array $row): array
    {
        return [
            'id' => $row['patient_id'],
            'firstName' => $row['legal_first_name'],
            'lastName' => $row['legal_last_name'],
            'preferredName' => $row['preferred_name'] ?? null,
            'dateOfBirth' => $row['dob'],
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null
        ];
    }

    /**
     * Calculate wait time as a human-readable string
     * 
     * @param string|null $arrivedAt Arrival timestamp
     * @return string Human-readable wait time (e.g., "12 min", "1 hr 5 min")
     */
    private function calculateWaitTime(?string $arrivedAt): string
    {
        if (empty($arrivedAt)) {
            return 'Unknown';
        }
        
        try {
            $arrived = new DateTime($arrivedAt);
            $now = new DateTime();
            $diff = $now->diff($arrived);
            
            $hours = $diff->h + ($diff->days * 24);
            $minutes = $diff->i;
            
            if ($hours > 0) {
                return "{$hours} hr " . ($minutes > 0 ? "{$minutes} min" : '');
            }
            
            return "{$minutes} min";
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Determine priority based on wait time
     * 
     * @param string|null $arrivedAt Arrival timestamp
     * @return string Priority level: 'normal', 'high', 'urgent'
     */
    private function determinePriority(?string $arrivedAt): string
    {
        if (empty($arrivedAt)) {
            return 'normal';
        }
        
        try {
            $arrived = new DateTime($arrivedAt);
            $now = new DateTime();
            $diff = $now->diff($arrived);
            
            $totalMinutes = ($diff->h * 60) + $diff->i + ($diff->days * 24 * 60);
            
            // Priority thresholds
            if ($totalMinutes >= 60) {
                return 'urgent';
            } elseif ($totalMinutes >= 30) {
                return 'high';
            }
            
            return 'normal';
        } catch (\Exception $e) {
            return 'normal';
        }
    }

    /**
     * Get today's appointment count
     * 
     * @return int Number of appointments scheduled for today
     */
    public function getTodayAppointmentCount(): int
    {
        try {
            $today = (new DateTime())->format('Y-m-d');
            
            $sql = "SELECT COUNT(*) FROM {$this->appointmentsTable} 
                    WHERE DATE(scheduled_at) = :today";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['today' => $today]);
            
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('RegistrationRepository::getTodayAppointmentCount error: ' . $e->getMessage());
            return 0;
        }
    }
}
