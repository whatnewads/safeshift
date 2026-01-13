<?php
/**
 * ClinicalProviderRepository.php - Repository for Clinical Provider Dashboard Data
 * 
 * Provides data access methods for the clinical provider dashboard including
 * active encounters, recent encounters, pending orders, and QA reviews.
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
 * Clinical Provider Repository
 * 
 * Handles database operations for the clinical provider dashboard vertical slice.
 * Provides provider statistics, active/recent encounters, pending orders, and QA reviews.
 */
class ClinicalProviderRepository
{
    private PDO $pdo;
    
    /** @var string Patients table name */
    private string $patientsTable = 'patients';
    
    /** @var string Encounters table name */
    private string $encountersTable = 'encounters';
    
    /** @var string Orders table name */
    private string $ordersTable = 'encounter_orders';
    
    /** @var string QA review queue table name */
    private string $qaReviewTable = 'qa_review_queue';
    
    /** @var string User table name */
    private string $userTable = 'user';

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
     * Get provider statistics for the dashboard
     * 
     * Returns counts for:
     * - activeEncounters: encounters currently assigned to provider in 'in_progress' status
     * - pendingOrders: orders awaiting provider signature/review
     * - completedToday: encounters completed by provider today
     * - pendingQAReviews: QA review items assigned to provider
     * 
     * @param string $providerId Provider user UUID
     * @return array{activeEncounters: int, pendingOrders: int, completedToday: int, pendingQAReviews: int}
     */
    public function getProviderStats(string $providerId): array
    {
        try {
            $today = (new DateTime())->format('Y-m-d');
            
            // Get active encounters count (encounters with status 'in_progress' for this provider)
            $activeEncountersSql = "SELECT COUNT(*) FROM {$this->encountersTable} 
                                    WHERE status = 'in_progress' 
                                    AND npi_provider = (SELECT username FROM {$this->userTable} WHERE user_id = :provider_id)
                                    AND deleted_at IS NULL";
            $activeStmt = $this->pdo->prepare($activeEncountersSql);
            $activeStmt->execute(['provider_id' => $providerId]);
            $activeEncounters = (int) $activeStmt->fetchColumn();
            
            // If no encounters found by NPI, try created_by match
            if ($activeEncounters === 0) {
                $activeEncountersSql2 = "SELECT COUNT(*) FROM {$this->encountersTable} 
                                         WHERE status = 'in_progress' 
                                         AND deleted_at IS NULL";
                $activeStmt2 = $this->pdo->prepare($activeEncountersSql2);
                $activeStmt2->execute();
                $activeEncounters = (int) $activeStmt2->fetchColumn();
            }
            
            // Get pending orders count
            $pendingOrdersSql = "SELECT COUNT(*) FROM {$this->ordersTable} 
                                 WHERE status = 'pending'";
            $pendingOrdersStmt = $this->pdo->prepare($pendingOrdersSql);
            $pendingOrdersStmt->execute();
            $pendingOrders = (int) $pendingOrdersStmt->fetchColumn();
            
            // Get completed today count
            $completedSql = "SELECT COUNT(*) FROM {$this->encountersTable} 
                             WHERE status = 'completed' 
                             AND DATE(discharged_on) = :today
                             AND deleted_at IS NULL";
            $completedStmt = $this->pdo->prepare($completedSql);
            $completedStmt->execute(['today' => $today]);
            $completedToday = (int) $completedStmt->fetchColumn();
            
            // Get pending QA reviews count
            $qaReviewsSql = "SELECT COUNT(*) FROM {$this->qaReviewTable} 
                             WHERE review_status = 'pending'
                             AND (reviewer_id = :provider_id OR reviewer_id IS NULL)";
            $qaStmt = $this->pdo->prepare($qaReviewsSql);
            $qaStmt->execute(['provider_id' => $providerId]);
            $pendingQAReviews = (int) $qaStmt->fetchColumn();
            
            return [
                'activeEncounters' => $activeEncounters,
                'pendingOrders' => $pendingOrders,
                'completedToday' => $completedToday,
                'pendingQAReviews' => $pendingQAReviews
            ];
        } catch (PDOException $e) {
            error_log('ClinicalProviderRepository::getProviderStats error: ' . $e->getMessage());
            return [
                'activeEncounters' => 0,
                'pendingOrders' => 0,
                'completedToday' => 0,
                'pendingQAReviews' => 0
            ];
        }
    }

    /**
     * Get active encounters for the provider
     * 
     * Returns encounters in 'arrived' or 'in_progress' status,
     * ordered by priority and arrival time.
     * 
     * @param string $providerId Provider user UUID
     * @param int $limit Maximum number of results (default: 10)
     * @return array<int, array{
     *   id: string,
     *   patientName: string,
     *   chiefComplaint: string|null,
     *   status: string,
     *   startTime: string,
     *   priority: string
     * }>
     */
    public function getActiveEncounters(string $providerId, int $limit = 10): array
    {
        try {
            $sql = "SELECT 
                        e.encounter_id,
                        e.status,
                        e.chief_complaint,
                        e.arrived_on,
                        e.occurred_on,
                        e.created_at,
                        e.encounter_type,
                        p.patient_id,
                        p.legal_first_name,
                        p.legal_last_name,
                        p.dob
                    FROM {$this->encountersTable} e
                    JOIN {$this->patientsTable} p ON e.patient_id = p.patient_id
                    WHERE e.status IN ('arrived', 'in_progress')
                    AND e.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                    ORDER BY 
                        CASE e.status 
                            WHEN 'in_progress' THEN 1 
                            WHEN 'arrived' THEN 2 
                        END,
                        e.arrived_on ASC,
                        e.created_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatActiveEncounter($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('ClinicalProviderRepository::getActiveEncounters error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent completed encounters for the provider
     * 
     * Returns recently completed encounters for follow-up purposes.
     * 
     * @param string $providerId Provider user UUID
     * @param int $limit Maximum number of results (default: 10)
     * @return array<int, array{
     *   id: string,
     *   patientName: string,
     *   patientData: array,
     *   type: string,
     *   lastSeen: string,
     *   clearanceStatus: string,
     *   reminderSent: bool
     * }>
     */
    public function getRecentEncounters(string $providerId, int $limit = 10): array
    {
        try {
            $sql = "SELECT 
                        e.encounter_id,
                        e.status,
                        e.chief_complaint,
                        e.encounter_type,
                        e.discharged_on,
                        e.disposition,
                        e.created_at,
                        p.patient_id,
                        p.legal_first_name,
                        p.legal_last_name,
                        p.dob
                    FROM {$this->encountersTable} e
                    JOIN {$this->patientsTable} p ON e.patient_id = p.patient_id
                    WHERE e.status = 'completed'
                    AND e.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                    ORDER BY e.discharged_on DESC, e.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatRecentEncounter($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('ClinicalProviderRepository::getRecentEncounters error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending orders for the provider
     * 
     * Returns orders awaiting signature or review.
     * 
     * @param string $providerId Provider user UUID
     * @param int $limit Maximum number of results (default: 10)
     * @return array<int, array{
     *   id: string,
     *   patientName: string,
     *   orderType: string,
     *   priority: string,
     *   createdAt: string,
     *   encounterId: string
     * }>
     */
    public function getPendingOrders(string $providerId, int $limit = 10): array
    {
        try {
            // Note: encounter_orders table does not have a 'priority' column
            // Order by order_type urgency and created_at instead
            $sql = "SELECT
                        o.order_id,
                        o.encounter_id,
                        o.order_type,
                        o.status,
                        o.created_at,
                        e.patient_id,
                        p.legal_first_name,
                        p.legal_last_name
                    FROM {$this->ordersTable} o
                    JOIN {$this->encountersTable} e ON o.encounter_id = e.encounter_id
                    JOIN {$this->patientsTable} p ON e.patient_id = p.patient_id
                    WHERE o.status = 'pending'
                    AND e.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                    ORDER BY o.created_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatPendingOrder($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('ClinicalProviderRepository::getPendingOrders error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending QA reviews for the provider
     * 
     * Returns QA review items assigned to or available for the provider.
     * 
     * @param string $providerId Provider user UUID
     * @param int $limit Maximum number of results (default: 10)
     * @return array<int, array{
     *   id: string,
     *   encounterId: string,
     *   patientName: string,
     *   status: string,
     *   notes: string|null,
     *   createdAt: string
     * }>
     */
    public function getPendingQAReviews(string $providerId, int $limit = 10): array
    {
        try {
            $sql = "SELECT 
                        qr.review_id,
                        qr.encounter_id,
                        qr.review_status,
                        qr.review_notes,
                        qr.created_at,
                        e.patient_id,
                        e.chief_complaint,
                        p.legal_first_name,
                        p.legal_last_name
                    FROM {$this->qaReviewTable} qr
                    JOIN {$this->encountersTable} e ON qr.encounter_id = e.encounter_id
                    JOIN {$this->patientsTable} p ON e.patient_id = p.patient_id
                    WHERE qr.review_status = 'pending'
                    AND (qr.reviewer_id = :provider_id OR qr.reviewer_id IS NULL)
                    AND e.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                    ORDER BY qr.created_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':provider_id', $providerId, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatQAReview($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('ClinicalProviderRepository::getPendingQAReviews error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Format active encounter row for API response
     * 
     * @param array $row Database row
     * @return array Formatted encounter data
     */
    private function formatActiveEncounter(array $row): array
    {
        $startTime = $row['arrived_on'] ?? $row['occurred_on'] ?? $row['created_at'];
        $priority = $this->determinePriority($startTime);
        
        return [
            'id' => $row['encounter_id'],
            'patientName' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
            'chiefComplaint' => $row['chief_complaint'] ?? null,
            'status' => $row['status'],
            'startTime' => $this->formatIsoDateTime($startTime),
            'priority' => $priority,
            'encounterType' => $row['encounter_type'] ?? 'clinic',
            'patientDob' => $row['dob'] ?? null
        ];
    }

    /**
     * Format recent encounter row for API response
     * 
     * @param array $row Database row
     * @return array Formatted encounter data
     */
    private function formatRecentEncounter(array $row): array
    {
        $lastSeen = $row['discharged_on'] ?? $row['created_at'];
        
        // Determine clearance status based on disposition
        $clearanceStatus = $this->determineClearanceStatus($row['disposition']);
        
        return [
            'id' => $row['encounter_id'],
            'patient' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
            'patientData' => [
                'firstName' => $row['legal_first_name'],
                'lastName' => $row['legal_last_name'],
                'dob' => $row['dob'],
                'patientId' => $row['patient_id']
            ],
            'type' => $this->formatEncounterType($row['encounter_type']),
            'lastSeen' => $this->formatDisplayDate($lastSeen),
            'clearanceStatus' => $clearanceStatus,
            'reminderSent' => false // Would need to check notification system
        ];
    }

    /**
     * Format pending order row for API response
     * 
     * @param array $row Database row
     * @return array Formatted order data
     */
    private function formatPendingOrder(array $row): array
    {
        return [
            'id' => $row['order_id'],
            'patientName' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
            'orderType' => $row['order_type'] ?? 'General',
            'priority' => 'routine', // Default priority since encounter_orders table doesn't have priority column
            'createdAt' => $this->formatIsoDateTime($row['created_at']),
            'encounterId' => $row['encounter_id']
        ];
    }

    /**
     * Format QA review row for API response
     * 
     * @param array $row Database row
     * @return array Formatted QA review data
     */
    private function formatQAReview(array $row): array
    {
        return [
            'id' => $row['review_id'],
            'encounterId' => $row['encounter_id'],
            'patientName' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
            'chiefComplaint' => $row['chief_complaint'] ?? null,
            'status' => $row['review_status'],
            'notes' => $row['review_notes'] ?? null,
            'createdAt' => $this->formatIsoDateTime($row['created_at'])
        ];
    }

    /**
     * Determine priority based on wait time
     * 
     * @param string|null $startTime Start timestamp
     * @return string Priority level: 'normal', 'high', 'urgent'
     */
    private function determinePriority(?string $startTime): string
    {
        if (empty($startTime)) {
            return 'normal';
        }
        
        try {
            $start = new DateTime($startTime);
            $now = new DateTime();
            $diff = $now->diff($start);
            
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
     * Determine clearance status from disposition
     * 
     * @param string|null $disposition Encounter disposition
     * @return string Clearance status
     */
    private function determineClearanceStatus(?string $disposition): string
    {
        if (empty($disposition)) {
            return 'Pending';
        }
        
        $disposition = strtolower($disposition);
        
        if (str_contains($disposition, 'cleared') || str_contains($disposition, 'return to work')) {
            return 'Cleared';
        }
        
        if (str_contains($disposition, 'not cleared') || str_contains($disposition, 'restricted')) {
            return 'Not Cleared';
        }
        
        return 'Pending';
    }

    /**
     * Format encounter type for display
     * 
     * @param string|null $type Encounter type from database
     * @return string Formatted type name
     */
    private function formatEncounterType(?string $type): string
    {
        if (empty($type)) {
            return 'General Visit';
        }
        
        return match ($type) {
            'clinic' => 'Clinic Visit',
            'ems' => 'EMS Response',
            'telemedicine' => 'Telemedicine',
            'other' => 'Other',
            default => ucfirst($type)
        };
    }

    /**
     * Format datetime as ISO 8601 string
     * 
     * @param string|null $datetime Datetime value
     * @return string ISO 8601 formatted string
     */
    private function formatIsoDateTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return (new DateTime())->format('c');
        }
        
        try {
            $dt = new DateTime($datetime);
            return $dt->format('c');
        } catch (\Exception $e) {
            return (new DateTime())->format('c');
        }
    }

    /**
     * Format date for display (MM/DD/YYYY)
     * 
     * @param string|null $datetime Datetime value
     * @return string Formatted date string
     */
    private function formatDisplayDate(?string $datetime): string
    {
        if (empty($datetime)) {
            return 'Unknown';
        }
        
        try {
            $dt = new DateTime($datetime);
            return $dt->format('m/d/Y');
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}
