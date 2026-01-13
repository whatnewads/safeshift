<?php

declare(strict_types=1);

namespace Model\Repositories;

use PDO;

/**
 * Case Repository
 *
 * Data access layer for case management in the Manager Dashboard.
 * Cases are essentially encounters that require management tracking,
 * particularly those related to OSHA recordable injuries/illnesses.
 *
 * NOTE: OSHA tables (300_log, 301, 300a) are READ-ONLY per 29 CFR 1904 compliance.
 * This repository only reads from those tables, never writes.
 *
 * @package Model\Repositories
 */
class CaseRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get cases (encounters) with aggregated management data
     */
    public function getCases(
        ?string $clinicId = null,
        ?string $status = null,
        int $limit = 50,
        int $offset = 0,
        string $sortBy = 'created_at',
        string $sortOrder = 'DESC'
    ): array {
        $conditions = ["e.status != 'cancelled'"];
        $params = [];

        if ($clinicId !== null) {
            $conditions[] = 'e.clinic_id = :clinic_id';
            $params['clinic_id'] = $clinicId;
        }

        // Filter by case status interpretation
        if ($status === 'open') {
            $conditions[] = "e.status IN ('scheduled', 'checked_in', 'in_progress', 'pending_review')";
        } elseif ($status === 'follow-up-due') {
            // Cases where follow-up is due (within next 7 days or overdue)
            $conditions[] = "e.status = 'completed'";
            $conditions[] = "EXISTS (
                SELECT 1 FROM encounter_flags ef
                WHERE ef.encounter_id = e.encounter_id
                AND ef.flag_type = 'follow_up_required'
                AND ef.status != 'resolved'
            )";
        } elseif ($status === 'high-risk') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM encounter_flags ef
                WHERE ef.encounter_id = e.encounter_id
                AND ef.flag_type IN ('high_risk', 'osha_recordable', 'critical')
                AND ef.status != 'resolved'
            )";
        } elseif ($status === 'closed') {
            $conditions[] = "e.status = 'complete'";
            $conditions[] = "e.locked_at IS NOT NULL";
        }

        // Only include encounters that are work-related or have case management relevance
        $conditions[] = "(
            e.encounter_type IN ('osha_injury', 'workers_comp', 'dot_physical', 'drug_screen')
            OR EXISTS (
                SELECT 1 FROM encounter_flags ef 
                WHERE ef.encounter_id = e.encounter_id
            )
        )";

        $whereClause = implode(' AND ', $conditions);

        // Validate sort column
        $validSortColumns = ['created_at', 'encounter_date', 'status'];
        $sortBy = in_array($sortBy, $validSortColumns) ? $sortBy : 'created_at';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT 
                    e.encounter_id,
                    e.patient_id,
                    e.provider_id,
                    e.clinic_id,
                    e.encounter_type,
                    e.status,
                    e.chief_complaint,
                    e.encounter_date,
                    e.created_at,
                    e.locked_at,
                    e.locked_by,
                    p.first_name AS patient_first_name,
                    p.last_name AS patient_last_name,
                    p.mrn AS patient_mrn,
                    DATEDIFF(NOW(), e.created_at) AS days_open,
                    (
                        SELECT GROUP_CONCAT(ef.flag_type SEPARATOR ',')
                        FROM encounter_flags ef
                        WHERE ef.encounter_id = e.encounter_id
                        AND ef.status != 'resolved'
                    ) AS active_flags,
                    (
                        SELECT COUNT(*)
                        FROM encounter_flags ef
                        WHERE ef.encounter_id = e.encounter_id
                        AND ef.flag_type = 'osha_recordable'
                        AND ef.status != 'resolved'
                    ) AS is_osha_recordable
                FROM encounters e
                LEFT JOIN patients p ON e.patient_id = p.patient_id
                WHERE {$whereClause}
                ORDER BY {$sortBy} {$sortOrder}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $cases = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cases[] = $this->formatCaseRow($row);
        }

        return $cases;
    }

    /**
     * Count cases matching criteria
     */
    public function countCases(
        ?string $clinicId = null,
        ?string $status = null
    ): int {
        $conditions = ["e.status != 'cancelled'"];
        $params = [];

        if ($clinicId !== null) {
            $conditions[] = 'e.clinic_id = :clinic_id';
            $params['clinic_id'] = $clinicId;
        }

        if ($status === 'open') {
            $conditions[] = "e.status IN ('planned', 'arrived', 'in_progress')";
        } elseif ($status === 'follow-up-due') {
            $conditions[] = "e.status = 'completed'";
            $conditions[] = "EXISTS (
                SELECT 1 FROM encounter_flags ef
                WHERE ef.encounter_id = e.encounter_id
                AND ef.flag_type = 'follow_up_required'
                AND ef.status != 'resolved'
            )";
        } elseif ($status === 'high-risk') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM encounter_flags ef
                WHERE ef.encounter_id = e.encounter_id
                AND ef.flag_type IN ('high_risk', 'osha_recordable', 'critical')
                AND ef.status != 'resolved'
            )";
        } elseif ($status === 'closed') {
            $conditions[] = "e.status = 'completed'";
            $conditions[] = "e.deleted_at IS NOT NULL";
        }

        $conditions[] = "(
            e.encounter_type IN ('osha_injury', 'workers_comp', 'dot_physical', 'drug_screen')
            OR EXISTS (
                SELECT 1 FROM encounter_flags ef 
                WHERE ef.encounter_id = e.encounter_id
            )
        )";

        $whereClause = implode(' AND ', $conditions);

        $sql = "SELECT COUNT(*) FROM encounters e WHERE {$whereClause}";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get case statistics for the manager dashboard
     */
    public function getCaseStats(?string $clinicId = null): array
    {
        $clinicCondition = $clinicId ? 'AND e.clinic_id = :clinic_id' : '';

        // Open cases
        $sql = "SELECT COUNT(*) FROM encounters e
                WHERE e.status IN ('planned', 'arrived', 'in_progress')
                AND e.status != 'cancelled'
                AND (
                    e.encounter_type IN ('ems', 'clinic', 'telemedicine')
                    OR EXISTS (SELECT 1 FROM encounter_flags ef WHERE ef.encounter_id = e.encounter_id)
                )
                {$clinicCondition}";
        $stmt = $this->pdo->prepare($sql);
        if ($clinicId) {
            $stmt->bindValue(':clinic_id', $clinicId);
        }
        $stmt->execute();
        $openCases = (int) $stmt->fetchColumn();

        // Follow-ups due
        $sql = "SELECT COUNT(*) FROM encounters e
                WHERE e.status = 'completed'
                AND EXISTS (
                    SELECT 1 FROM encounter_flags ef
                    WHERE ef.encounter_id = e.encounter_id
                    AND ef.flag_type = 'follow_up_required'
                    AND ef.status != 'resolved'
                )
                {$clinicCondition}";
        $stmt = $this->pdo->prepare($sql);
        if ($clinicId) {
            $stmt->bindValue(':clinic_id', $clinicId);
        }
        $stmt->execute();
        $followUpsDue = (int) $stmt->fetchColumn();

        // High risk
        $sql = "SELECT COUNT(*) FROM encounters e
                WHERE e.status != 'cancelled'
                AND EXISTS (
                    SELECT 1 FROM encounter_flags ef
                    WHERE ef.encounter_id = e.encounter_id
                    AND ef.flag_type IN ('high_risk', 'osha_recordable', 'critical')
                    AND ef.status != 'resolved'
                )
                {$clinicCondition}";
        $stmt = $this->pdo->prepare($sql);
        if ($clinicId) {
            $stmt->bindValue(':clinic_id', $clinicId);
        }
        $stmt->execute();
        $highRisk = (int) $stmt->fetchColumn();

        // Closed this month
        $sql = "SELECT COUNT(*) FROM encounters e
                WHERE e.status = 'completed'
                AND e.discharged_on IS NOT NULL
                AND MONTH(e.discharged_on) = MONTH(NOW())
                AND YEAR(e.discharged_on) = YEAR(NOW())
                AND (
                    e.encounter_type IN ('ems', 'clinic', 'telemedicine')
                    OR EXISTS (SELECT 1 FROM encounter_flags ef WHERE ef.encounter_id = e.encounter_id)
                )
                {$clinicCondition}";
        $stmt = $this->pdo->prepare($sql);
        if ($clinicId) {
            $stmt->bindValue(':clinic_id', $clinicId);
        }
        $stmt->execute();
        $closedThisMonth = (int) $stmt->fetchColumn();

        return [
            'open_cases' => $openCases,
            'follow_ups_due' => $followUpsDue,
            'high_risk' => $highRisk,
            'closed_this_month' => $closedThisMonth,
        ];
    }

    /**
     * Get OSHA status for an encounter (READ-ONLY from OSHA tables)
     * 
     * NOTE: This reads from 300_log and 301 tables which are OSHA compliance tables
     * per 29 CFR 1904. These tables should NEVER be modified through this application.
     */
    public function getOshaStatus(string $encounterId): array
    {
        // Check if there's a 300 log entry
        $sql = "SELECT form300line_id, case_name, injury_type, is_recordable 
                FROM 300_log 
                WHERE encounter_id = :encounter_id 
                LIMIT 1";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['encounter_id' => $encounterId]);
            $form300 = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Table might not exist or have different structure
            $form300 = false;
        }

        // Check if there's a 301 entry
        $sql = "SELECT form301_id 
                FROM 301 
                WHERE encounter_id = :encounter_id 
                LIMIT 1";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['encounter_id' => $encounterId]);
            $form301 = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $form301 = false;
        }

        if ($form300 || $form301) {
            return [
                'status' => $form300 ? 'submitted' : 'pending',
                'form_300' => $form300 ? [
                    'id' => $form300['form300line_id'],
                    'case_name' => $form300['case_name'] ?? null,
                    'injury_type' => $form300['injury_type'] ?? null,
                    'is_recordable' => (bool)($form300['is_recordable'] ?? true),
                ] : null,
                'form_301' => $form301 ? [
                    'id' => $form301['form301_id'],
                ] : null,
            ];
        }

        // Check if it should be OSHA recordable based on encounter type
        $sql = "SELECT encounter_type FROM encounters WHERE encounter_id = :encounter_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        $encounter = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($encounter && in_array($encounter['encounter_type'], ['osha_injury', 'workers_comp'])) {
            return [
                'status' => 'pending',
                'form_300' => null,
                'form_301' => null,
            ];
        }

        return [
            'status' => 'not-applicable',
            'form_300' => null,
            'form_301' => null,
        ];
    }

    /**
     * Get a single case with full details
     */
    public function getCaseById(string $encounterId): ?array
    {
        $sql = "SELECT 
                    e.encounter_id,
                    e.patient_id,
                    e.provider_id,
                    e.clinic_id,
                    e.encounter_type,
                    e.status,
                    e.chief_complaint,
                    e.hpi,
                    e.assessment,
                    e.plan,
                    e.encounter_date,
                    e.created_at,
                    e.updated_at,
                    e.locked_at,
                    e.locked_by,
                    e.is_amended,
                    e.amendment_reason,
                    p.first_name AS patient_first_name,
                    p.last_name AS patient_last_name,
                    p.mrn AS patient_mrn,
                    p.date_of_birth AS patient_dob,
                    DATEDIFF(NOW(), e.created_at) AS days_open
                FROM encounters e
                LEFT JOIN patients p ON e.patient_id = p.patient_id
                WHERE e.encounter_id = :encounter_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $case = $this->formatCaseRow($row);

        // Get flags
        $sql = "SELECT flag_id, flag_type, flag_reason, severity, created_at, status, resolved_at
                FROM encounter_flags
                WHERE encounter_id = :encounter_id
                ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        $case['flags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get OSHA status
        $case['osha'] = $this->getOshaStatus($encounterId);

        return $case;
    }

    /**
     * Add a flag to an encounter
     */
    public function addFlag(
        string $encounterId,
        string $flagType,
        string $reason,
        string $severity = 'medium',
        ?string $createdBy = null
    ): bool {
        $sql = "INSERT INTO encounter_flags
                (encounter_id, flag_type, flag_reason, severity, flagged_by, created_at, status)
                VALUES (:encounter_id, :flag_type, :flag_reason, :severity, :flagged_by, NOW(), 'pending')";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'encounter_id' => $encounterId,
            'flag_type' => $flagType,
            'flag_reason' => $reason,
            'severity' => $severity,
            'flagged_by' => $createdBy,
        ]);
    }

    /**
     * Resolve a flag
     */
    public function resolveFlag(string $flagId, ?string $resolvedBy = null): bool
    {
        $sql = "UPDATE encounter_flags
                SET status = 'resolved', resolved_at = NOW(), resolved_by = :resolved_by
                WHERE flag_id = :flag_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'flag_id' => $flagId,
            'resolved_by' => $resolvedBy,
        ]);
    }

    /**
     * Format a case row for API response
     */
    private function formatCaseRow(array $row): array
    {
        $flags = !empty($row['active_flags']) ? explode(',', $row['active_flags']) : [];
        
        // Determine case status based on flags and encounter status
        $caseStatus = 'open';
        if (in_array('high_risk', $flags) || in_array('critical', $flags) || in_array('osha_recordable', $flags)) {
            $caseStatus = 'high-risk';
        } elseif (in_array('follow_up_required', $flags)) {
            $caseStatus = 'follow-up-due';
        } elseif ($row['status'] === 'complete' && !empty($row['locked_at'])) {
            $caseStatus = 'closed';
        }

        // Map encounter type to case type
        $caseType = match($row['encounter_type']) {
            'osha_injury', 'workers_comp' => 'Work Injury',
            'dot_physical' => 'DOT Physical',
            'drug_screen' => 'Drug Screen',
            'follow_up' => 'Follow-up',
            default => ucwords(str_replace('_', ' ', $row['encounter_type'])),
        };

        return [
            'id' => $row['encounter_id'],
            'encounterId' => $row['encounter_id'],
            'patient' => trim(($row['patient_first_name'] ?? '') . ' ' . ($row['patient_last_name'] ?? '')),
            'patientId' => $row['patient_id'],
            'patientMrn' => $row['patient_mrn'] ?? null,
            'patientDob' => $row['patient_dob'] ?? null,
            'type' => $caseType,
            'encounterType' => $row['encounter_type'],
            'status' => $caseStatus,
            'encounterStatus' => $row['status'],
            'chiefComplaint' => $row['chief_complaint'] ?? null,
            'hpi' => $row['hpi'] ?? null,
            'assessment' => $row['assessment'] ?? null,
            'plan' => $row['plan'] ?? null,
            'oshaStatus' => ($row['is_osha_recordable'] ?? 0) > 0 ? 'pending' : 'not-applicable',
            'days' => (int)($row['days_open'] ?? 0),
            'daysOpen' => (int)($row['days_open'] ?? 0),
            'activeFlags' => $flags,
            'encounterDate' => $row['encounter_date'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
            'lockedAt' => $row['locked_at'] ?? null,
            'lockedBy' => $row['locked_by'] ?? null,
            'isAmended' => (bool)($row['is_amended'] ?? false),
            'amendmentReason' => $row['amendment_reason'] ?? null,
        ];
    }
}
