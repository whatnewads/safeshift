<?php

declare(strict_types=1);

namespace Model\Repositories;

use PDO;

/**
 * Admin Repository
 *
 * Data access layer for Admin dashboard functions including
 * compliance alerts, training records, and OSHA data (READ-ONLY).
 *
 * NOTE: OSHA tables (300_log, 301, 300a) are READ-ONLY per 29 CFR 1904 compliance.
 *
 * @package Model\Repositories
 */
class AdminRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ========================================================================
    // Compliance Alerts
    // ========================================================================

    /**
     * Get compliance alerts
     */
    public function getComplianceAlerts(
        ?string $status = null,
        ?string $priority = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $conditions = ['1=1'];
        $params = [];

        if ($status !== null && $status !== 'all') {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($priority !== null && $priority !== 'all') {
            $conditions[] = 'priority = :priority';
            $params['priority'] = $priority;
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT * FROM compliance_alerts 
                WHERE {$whereClause}
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("AdminRepository::getComplianceAlerts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count compliance alerts
     */
    public function countComplianceAlerts(?string $status = null): int
    {
        $condition = $status && $status !== 'all' ? "WHERE status = :status" : "";

        $sql = "SELECT COUNT(*) FROM compliance_alerts {$condition}";

        try {
            $stmt = $this->pdo->prepare($sql);
            if ($status && $status !== 'all') {
                $stmt->bindValue(':status', $status);
            }
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Acknowledge a compliance alert
     */
    public function acknowledgeComplianceAlert(string $alertId, string $userId): bool
    {
        $sql = "UPDATE compliance_alerts 
                SET status = 'acknowledged', acknowledged_by = :user_id, acknowledged_at = NOW()
                WHERE alert_id = :alert_id";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                'alert_id' => $alertId,
                'user_id' => $userId,
            ]);
        } catch (\Exception $e) {
            error_log("AdminRepository::acknowledgeComplianceAlert error: " . $e->getMessage());
            return false;
        }
    }

    // ========================================================================
    // Training Records
    // ========================================================================

    /**
     * Get training requirements
     */
    public function getTrainingRequirements(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM training_requirements
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("AdminRepository::getTrainingRequirements error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get training module summary with completion stats
     */
    public function getTrainingModuleSummary(): array
    {
        $sql = "SELECT
                    tr.requirement_id,
                    tr.training_name,
                    tr.training_description,
                    tr.recurrence_interval,
                    tr.is_active,
                    COUNT(DISTINCT str.user_id) AS assigned_count,
                    SUM(CASE WHEN str.completion_date IS NOT NULL THEN 1 ELSE 0 END) AS completed_count,
                    0 AS in_progress_count,
                    SUM(CASE WHEN str.completion_date IS NULL THEN 1 ELSE 0 END) AS not_started_count,
                    SUM(CASE WHEN str.expiration_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) AND str.completion_date IS NOT NULL THEN 1 ELSE 0 END) AS expiring_count
                FROM training_requirements tr
                LEFT JOIN staff_training_records str ON tr.requirement_id = str.requirement_id
                GROUP BY tr.requirement_id
                ORDER BY tr.is_active DESC, tr.training_name ASC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("AdminRepository::getTrainingModuleSummary error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get expiring credentials/certifications
     */
    public function getExpiringCredentials(int $daysAhead = 30): array
    {
        // This would typically come from a user_credentials or staff_credentials table
        // For now, we'll check expiring training records
        $sql = "SELECT
                    str.record_id,
                    str.user_id,
                    u.username,
                    tr.training_name AS credential_name,
                    str.expiration_date,
                    DATEDIFF(str.expiration_date, NOW()) AS days_until_expiry,
                    CASE
                        WHEN str.expiration_date <= NOW() THEN 'expired'
                        WHEN str.expiration_date <= DATE_ADD(NOW(), INTERVAL 14 DAY) THEN 'critical'
                        ELSE 'expiring-soon'
                    END AS status
                FROM staff_training_records str
                JOIN `user` u ON str.user_id = u.user_id
                JOIN training_requirements tr ON str.requirement_id = tr.requirement_id
                WHERE str.completion_date IS NOT NULL
                AND str.expiration_date <= DATE_ADD(NOW(), INTERVAL :days DAY)
                ORDER BY str.expiration_date ASC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':days', $daysAhead, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("AdminRepository::getExpiringCredentials error: " . $e->getMessage());
            return [];
        }
    }

    // ========================================================================
    // OSHA Data (READ-ONLY)
    // ========================================================================

    /**
     * Get OSHA 300 Log entries (READ-ONLY)
     * 
     * COMPLIANCE NOTE: This is a READ-ONLY operation per 29 CFR 1904.
     * The 300_log table should never be modified through this application.
     */
    public function getOsha300Log(?int $year = null, int $limit = 100): array
    {
        $year = $year ?? (int) date('Y');
        
        $sql = "SELECT * FROM `300_log`
                WHERE calendar_year = :year
                ORDER BY created_at DESC
                LIMIT :limit";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':year', $year, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("AdminRepository::getOsha300Log error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get OSHA 300A Summary (READ-ONLY)
     * 
     * COMPLIANCE NOTE: This is a READ-ONLY operation per 29 CFR 1904.
     */
    public function getOsha300ASummary(?int $year = null): ?array
    {
        $year = $year ?? (int) date('Y');
        
        $sql = "SELECT * FROM `300a` WHERE year_filing_for = :year ORDER BY Id DESC LIMIT 1";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':year', $year, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Exception $e) {
            error_log("AdminRepository::getOsha300ASummary error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get OSHA statistics summary (READ-ONLY aggregations)
     */
    public function getOshaStatistics(?int $year = null): array
    {
        $year = $year ?? (int) date('Y');

        // Note: 300_log uses boolean flags (days_away, job_transfer_restriction, death)
        // The actual day counts come from the related osha_case or 300a summary table
        $sql = "SELECT
                    COUNT(*) AS total_recordable_cases,
                    SUM(CASE WHEN days_away = 1 THEN 1 ELSE 0 END) AS days_away_cases,
                    SUM(CASE WHEN job_transfer_restriction = 1 THEN 1 ELSE 0 END) AS job_transfer_cases,
                    SUM(CASE WHEN days_away = 0 AND job_transfer_restriction = 0 AND death = 0 THEN 1 ELSE 0 END) AS other_recordable_cases,
                    SUM(CASE WHEN death = 1 THEN 1 ELSE 0 END) AS death_cases
                FROM `300_log`
                WHERE calendar_year = :year";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':year', $year, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Add placeholder values for total days (would come from 300a or osha_case)
            return $result ? array_merge($result, [
                'total_days_away' => 0,
                'total_days_restricted' => 0,
            ]) : [
                'total_recordable_cases' => 0,
                'days_away_cases' => 0,
                'job_transfer_cases' => 0,
                'other_recordable_cases' => 0,
                'death_cases' => 0,
                'total_days_away' => 0,
                'total_days_restricted' => 0,
            ];
        } catch (\Exception $e) {
            error_log("AdminRepository::getOshaStatistics error: " . $e->getMessage());
            return [
                'total_recordable_cases' => 0,
                'days_away_cases' => 0,
                'job_transfer_cases' => 0,
                'other_recordable_cases' => 0,
                'death_cases' => 0,
                'total_days_away' => 0,
                'total_days_restricted' => 0,
            ];
        }
    }

    // ========================================================================
    // Dashboard Statistics
    // ========================================================================

    /**
     * Get admin dashboard statistics
     */
    public function getAdminStats(): array
    {
        $stats = [
            'compliance_alerts' => $this->countComplianceAlerts('active'),
            'training_due' => 0,
            'expiring_credentials' => 0,
            'regulatory_updates' => 0,
        ];

        // Count overdue/due training (records where expiration_date has passed or is approaching)
        $sql = "SELECT COUNT(*) FROM staff_training_records
                WHERE expiration_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stats['training_due'] = (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }

        // Count expiring credentials (within 30 days)
        try {
            $stats['expiring_credentials'] = count($this->getExpiringCredentials(30));
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }

        // Count recent regulatory updates
        $sql = "SELECT COUNT(*) FROM regulatory_updates
                WHERE status = 'new' OR status = 'unread'";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stats['regulatory_updates'] = (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }

        return $stats;
    }

    // ========================================================================
    // Case & Encounter Statistics (Admin Dashboard)
    // ========================================================================

    /**
     * Get case statistics for Admin dashboard
     * Returns: pendingQA, openCases, pendingOrders, encountersToday
     */
    public function getCaseStats(?string $clinicId = null): array
    {
        $clinicCondition = $clinicId ? 'AND e.site_id = :clinic_id' : '';
        
        $stats = [
            'pending_qa' => 0,
            'open_cases' => 0,
            'pending_orders' => 0,
            'encounters_today' => 0,
        ];

        // Consolidated query: Get encounter stats using conditional aggregation
        // This optimizes the N+1 pattern by combining pending_qa, open_cases, and encounters_today
        $sql = "SELECT
                    COUNT(DISTINCT CASE WHEN e.status = 'pending_review' OR qr.review_status = 'pending'
                        THEN e.encounter_id END) AS pending_qa,
                    SUM(CASE WHEN e.status IN ('planned', 'arrived', 'in_progress')
                        THEN 1 ELSE 0 END) AS open_cases,
                    SUM(CASE WHEN DATE(e.occurred_on) = CURDATE()
                        THEN 1 ELSE 0 END) AS encounters_today
                FROM encounters e
                LEFT JOIN qa_review_queue qr ON e.encounter_id = qr.encounter_id
                WHERE e.deleted_at IS NULL
                {$clinicCondition}";
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($clinicId) {
                $stmt->bindValue(':clinic_id', $clinicId);
            }
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stats['pending_qa'] = (int) ($result['pending_qa'] ?? 0);
            $stats['open_cases'] = (int) ($result['open_cases'] ?? 0);
            $stats['encounters_today'] = (int) ($result['encounters_today'] ?? 0);
        } catch (\Exception $e) {
            error_log("AdminRepository::getCaseStats encounter stats error: " . $e->getMessage());
        }

        // Pending orders (separate table, cannot be consolidated)
        $sql = "SELECT COUNT(*) FROM orders o
                WHERE o.status = 'pending'";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stats['pending_orders'] = (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Try encounter_orders table
            $sql = "SELECT COUNT(*) FROM encounter_orders eo
                    WHERE eo.status = 'pending'";
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $stats['pending_orders'] = (int) $stmt->fetchColumn();
            } catch (\Exception $e2) {
                error_log("AdminRepository::getCaseStats pending_orders error: " . $e2->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Get recent cases/encounters for Admin dashboard
     */
    public function getRecentCases(int $limit = 10, ?string $clinicId = null): array
    {
        $clinicCondition = $clinicId ? 'AND e.site_id = :clinic_id' : '';
        
        $sql = "SELECT
                    e.encounter_id,
                    e.patient_id,
                    e.status,
                    e.chief_complaint,
                    e.encounter_type,
                    e.occurred_on,
                    e.arrived_on,
                    e.created_at,
                    p.legal_first_name,
                    p.legal_last_name,
                    u.username AS provider_name,
                    DATEDIFF(NOW(), e.created_at) AS days_open
                FROM encounters e
                LEFT JOIN patients p ON e.patient_id = p.patient_id
                LEFT JOIN `user` u ON e.npi_provider = u.user_id
                WHERE e.deleted_at IS NULL
                AND p.deleted_at IS NULL
                {$clinicCondition}
                ORDER BY e.created_at DESC
                LIMIT :limit";

        try {
            $stmt = $this->pdo->prepare($sql);
            if ($clinicId) {
                $stmt->bindValue(':clinic_id', $clinicId);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $cases = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cases[] = [
                    'id' => $row['encounter_id'],
                    'patientName' => trim(($row['legal_first_name'] ?? '') . ' ' . ($row['legal_last_name'] ?? '')),
                    'patientId' => $row['patient_id'],
                    'chiefComplaint' => $row['chief_complaint'] ?? 'Not specified',
                    'status' => $row['status'],
                    'encounterType' => $row['encounter_type'],
                    'assignedProvider' => $row['provider_name'] ?? 'Unassigned',
                    'daysOpen' => (int)($row['days_open'] ?? 0),
                    'createdAt' => $row['created_at'],
                    'occurredOn' => $row['occurred_on'],
                ];
            }
            return $cases;
        } catch (\Exception $e) {
            error_log("AdminRepository::getRecentCases error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get patient flow metrics (walk-ins, scheduled, emergency counts)
     */
    public function getPatientFlowMetrics(?string $clinicId = null): array
    {
        $clinicCondition = $clinicId ? 'AND e.site_id = :clinic_id' : '';
        
        $metrics = [
            'walk_ins' => 0,
            'scheduled' => 0,
            'emergency' => 0,
            'total_today' => 0,
        ];

        // Walk-ins today (encounters without prior appointment)
        $sql = "SELECT COUNT(*) FROM encounters e
                WHERE DATE(e.occurred_on) = CURDATE()
                AND e.deleted_at IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM appointments a
                    WHERE a.patient_id = e.patient_id
                    AND DATE(a.scheduled_for) = CURDATE()
                )
                {$clinicCondition}";
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($clinicId) {
                $stmt->bindValue(':clinic_id', $clinicId);
            }
            $stmt->execute();
            $metrics['walk_ins'] = (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Fallback: count encounters with 'clinic' type as walk-ins
            $sql = "SELECT COUNT(*) FROM encounters e
                    WHERE DATE(e.occurred_on) = CURDATE()
                    AND e.deleted_at IS NULL
                    AND e.encounter_type = 'clinic'
                    {$clinicCondition}";
            try {
                $stmt = $this->pdo->prepare($sql);
                if ($clinicId) {
                    $stmt->bindValue(':clinic_id', $clinicId);
                }
                $stmt->execute();
                $metrics['walk_ins'] = (int) $stmt->fetchColumn();
            } catch (\Exception $e2) {
                error_log("AdminRepository::getPatientFlowMetrics walk_ins error: " . $e2->getMessage());
            }
        }

        // Scheduled today (from appointments table)
        $sql = "SELECT COUNT(*) FROM appointments a
                WHERE DATE(a.scheduled_for) = CURDATE()
                AND a.status != 'cancelled'";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $metrics['scheduled'] = (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            // Fallback: count planned encounters
            $sql = "SELECT COUNT(*) FROM encounters e
                    WHERE DATE(e.occurred_on) = CURDATE()
                    AND e.deleted_at IS NULL
                    AND e.status = 'planned'
                    {$clinicCondition}";
            try {
                $stmt = $this->pdo->prepare($sql);
                if ($clinicId) {
                    $stmt->bindValue(':clinic_id', $clinicId);
                }
                $stmt->execute();
                $metrics['scheduled'] = (int) $stmt->fetchColumn();
            } catch (\Exception $e2) {
                error_log("AdminRepository::getPatientFlowMetrics scheduled error: " . $e2->getMessage());
            }
        }

        // Emergency encounters today
        $sql = "SELECT COUNT(*) FROM encounters e
                WHERE DATE(e.occurred_on) = CURDATE()
                AND e.deleted_at IS NULL
                AND e.encounter_type = 'ems'
                {$clinicCondition}";
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($clinicId) {
                $stmt->bindValue(':clinic_id', $clinicId);
            }
            $stmt->execute();
            $metrics['emergency'] = (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            error_log("AdminRepository::getPatientFlowMetrics emergency error: " . $e->getMessage());
        }

        // Total today
        $metrics['total_today'] = $metrics['walk_ins'] + $metrics['scheduled'] + $metrics['emergency'];

        return $metrics;
    }

    /**
     * Get site/establishment performance metrics
     */
    public function getSitePerformance(int $limit = 10): array
    {
        $sql = "SELECT
                    est.Id AS site_id,
                    est.establishment_name AS site_name,
                    est.city,
                    est.state,
                    COUNT(DISTINCT CASE WHEN DATE(e.occurred_on) = CURDATE() THEN e.encounter_id END) AS encounters_today,
                    COUNT(DISTINCT CASE WHEN DATE(e.occurred_on) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN e.encounter_id END) AS encounters_30d,
                    AVG(TIMESTAMPDIFF(MINUTE, e.arrived_on, e.discharged_on)) AS avg_treatment_time,
                    AVG(TIMESTAMPDIFF(MINUTE, e.arrived_on,
                        (SELECT MIN(eo.taken_at) FROM encounter_observations eo WHERE eo.encounter_id = e.encounter_id)
                    )) AS avg_wait_time
                FROM establishment est
                LEFT JOIN encounters e ON e.site_id = est.Id AND e.deleted_at IS NULL
                GROUP BY est.Id, est.establishment_name, est.city, est.state
                ORDER BY encounters_today DESC
                LIMIT :limit";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $sites = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sites[] = [
                    'siteId' => (string)$row['site_id'],
                    'siteName' => $row['site_name'] ?? 'Unknown Site',
                    'city' => $row['city'] ?? '',
                    'state' => $row['state'] ?? '',
                    'encountersToday' => (int)($row['encounters_today'] ?? 0),
                    'encounters30d' => (int)($row['encounters_30d'] ?? 0),
                    'avgTreatmentTime' => round((float)($row['avg_treatment_time'] ?? 0), 1),
                    'avgWaitTime' => round((float)($row['avg_wait_time'] ?? 0), 1),
                    'patientSatisfaction' => 0.0, // Would come from a satisfaction survey table
                ];
            }
            return $sites;
        } catch (\Exception $e) {
            error_log("AdminRepository::getSitePerformance error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get provider performance metrics
     */
    public function getProviderPerformance(int $limit = 10): array
    {
        $sql = "SELECT
                    u.user_id AS provider_id,
                    u.username AS provider_name,
                    r.name AS role_name,
                    COUNT(DISTINCT CASE WHEN DATE(e.occurred_on) = CURDATE() THEN e.encounter_id END) AS encounters_today,
                    COUNT(DISTINCT CASE WHEN DATE(e.occurred_on) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN e.encounter_id END) AS encounters_30d,
                    AVG(TIMESTAMPDIFF(MINUTE, e.arrived_on, e.discharged_on)) AS avg_encounter_time,
                    (
                        SELECT ROUND(AVG(CASE WHEN qr.review_status = 'approved' THEN 100 ELSE 0 END), 1)
                        FROM qa_review_queue qr
                        JOIN encounters e2 ON qr.encounter_id = e2.encounter_id
                        WHERE e2.npi_provider = u.user_id
                        AND qr.reviewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ) AS qa_score
                FROM `user` u
                JOIN userrole ur ON u.user_id = ur.user_id
                JOIN role r ON ur.role_id = r.role_id
                LEFT JOIN encounters e ON e.npi_provider = u.user_id AND e.deleted_at IS NULL
                WHERE r.slug IN ('pclinician', 'dclinician')
                AND u.is_active = 1
                GROUP BY u.user_id, u.username, r.name
                ORDER BY encounters_today DESC
                LIMIT :limit";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $providers = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $providers[] = [
                    'providerId' => $row['provider_id'],
                    'providerName' => $row['provider_name'] ?? 'Unknown Provider',
                    'role' => $row['role_name'] ?? 'Provider',
                    'encountersToday' => (int)($row['encounters_today'] ?? 0),
                    'encounters30d' => (int)($row['encounters_30d'] ?? 0),
                    'avgEncounterTime' => round((float)($row['avg_encounter_time'] ?? 0), 1),
                    'qaScore' => round((float)($row['qa_score'] ?? 0), 1),
                ];
            }
            return $providers;
        } catch (\Exception $e) {
            error_log("AdminRepository::getProviderPerformance error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get staff list with roles and status
     */
    public function getStaffList(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT
                    u.user_id,
                    u.username,
                    u.email,
                    u.status AS user_status,
                    u.is_active,
                    u.last_login,
                    u.created_at,
                    GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles,
                    GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ',') AS role_slugs
                FROM `user` u
                LEFT JOIN userrole ur ON u.user_id = ur.user_id
                LEFT JOIN role r ON ur.role_id = r.role_id
                WHERE u.is_active = 1
                GROUP BY u.user_id, u.username, u.email, u.status, u.is_active, u.last_login, u.created_at
                ORDER BY u.username
                LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $staff = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $staff[] = [
                    'id' => $row['user_id'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'status' => $row['user_status'] ?? 'active',
                    'isActive' => (bool)$row['is_active'],
                    'roles' => $row['roles'] ?? 'No Role',
                    'roleSlugs' => $row['role_slugs'] ? explode(',', $row['role_slugs']) : [],
                    'lastLogin' => $row['last_login'],
                    'createdAt' => $row['created_at'],
                ];
            }
            return $staff;
        } catch (\Exception $e) {
            error_log("AdminRepository::getStaffList error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count total staff
     */
    public function countStaff(): int
    {
        $sql = "SELECT COUNT(*) FROM `user` WHERE is_active = 1";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get clearance statistics (cleared, not cleared, pending)
     */
    public function getClearanceStats(?string $clinicId = null): array
    {
        $clinicCondition = $clinicId ? 'AND e.site_id = :clinic_id' : '';
        
        $stats = [
            'cleared' => 0,
            'not_cleared' => 0,
            'pending_review' => 0,
        ];

        // Consolidated query: Get clearance stats using conditional aggregation
        // This optimizes the N+1 pattern by combining cleared, not_cleared, and pending_review
        $sql = "SELECT
                    SUM(CASE WHEN e.status = 'completed'
                             AND e.disposition IN ('rtw', 'cleared', 'rtw_full', 'rtw_modified')
                             AND e.discharged_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        THEN 1 ELSE 0 END) AS cleared,
                    SUM(CASE WHEN e.status = 'completed'
                             AND e.disposition IN ('not_cleared', 'restricted', 'off_work', 'referral')
                             AND e.discharged_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        THEN 1 ELSE 0 END) AS not_cleared,
                    COUNT(DISTINCT CASE WHEN qr.review_status = 'pending'
                        THEN e.encounter_id END) AS pending_review
                FROM encounters e
                LEFT JOIN qa_review_queue qr ON e.encounter_id = qr.encounter_id
                WHERE e.deleted_at IS NULL
                {$clinicCondition}";
        try {
            $stmt = $this->pdo->prepare($sql);
            if ($clinicId) {
                $stmt->bindValue(':clinic_id', $clinicId);
            }
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stats['cleared'] = (int) ($result['cleared'] ?? 0);
            $stats['not_cleared'] = (int) ($result['not_cleared'] ?? 0);
            $stats['pending_review'] = (int) ($result['pending_review'] ?? 0);
        } catch (\Exception $e) {
            error_log("AdminRepository::getClearanceStats error: " . $e->getMessage());
        }

        return $stats;
    }
}
