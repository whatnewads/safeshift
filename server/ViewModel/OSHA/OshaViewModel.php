<?php
/**
 * OshaViewModel - Business logic layer for OSHA recordkeeping
 * 
 * Handles: OSHA 300/300A/301 logs, injury recording, rate calculations, ITA submission
 * Compliance: 29 CFR 1904 recordability rules, multi-establishment reporting
 * 
 * @package SafeShift\ViewModel\OSHA
 */

declare(strict_types=1);

namespace ViewModel\OSHA;

use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;
use Core\Services\AuditService;
use Exception;
use PDO;

/**
 * OSHA ViewModel
 * 
 * Manages OSHA recordkeeping per 29 CFR 1904 requirements.
 * Calculates TRIR, DART rates and generates 300/300A/301 logs.
 */
class OshaViewModel extends BaseViewModel
{
    /** OSHA injury/illness categories (29 CFR 1904.7) */
    public const CATEGORY_INJURY = 'injury';
    public const CATEGORY_SKIN_DISORDER = 'skin_disorder';
    public const CATEGORY_RESPIRATORY = 'respiratory';
    public const CATEGORY_POISONING = 'poisoning';
    public const CATEGORY_HEARING_LOSS = 'hearing_loss';
    public const CATEGORY_OTHER_ILLNESS = 'other_illness';

    /** Case classification (29 CFR 1904.7) */
    public const CLASS_DEATH = 'death';
    public const CLASS_DAYS_AWAY = 'days_away';
    public const CLASS_JOB_RESTRICTION = 'job_restriction';
    public const CLASS_OTHER_RECORDABLE = 'other_recordable';

    /** Standard work hours for rate calculations */
    private const STANDARD_HOURS_BASE = 200000;

    /**
     * Constructor
     * 
     * @param AuditService|null $auditService
     * @param PDO|null $pdo
     */
    public function __construct(
        ?AuditService $auditService = null,
        ?PDO $pdo = null
    ) {
        parent::__construct($auditService, $pdo);
    }

    /**
     * Get injuries with filters
     * 
     * @param array $filters Optional filters (year, establishment_id, category, etc.)
     * @return array API response
     */
    public function getInjuries(array $filters = []): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_compliance');
            
            [$page, $perPage] = $this->getPaginationParams($filters);
            
            $year = (int)($filters['year'] ?? date('Y'));
            
            $sql = "SELECT 
                        oi.*,
                        p.legal_first_name, p.legal_last_name, p.mrn,
                        est.establishment_name
                    FROM osha_injuries oi
                    LEFT JOIN patients p ON oi.employee_patient_id = p.patient_id
                    LEFT JOIN establishments est ON oi.establishment_id = est.establishment_id
                    WHERE YEAR(oi.injury_date) = :year";
            
            $params = ['year' => $year];
            
            if (!empty($filters['establishment_id'])) {
                $sql .= " AND oi.establishment_id = :establishment_id";
                $params['establishment_id'] = $filters['establishment_id'];
            }
            
            if (!empty($filters['category'])) {
                $sql .= " AND oi.injury_category = :category";
                $params['category'] = $filters['category'];
            }
            
            if (!empty($filters['classification'])) {
                $sql .= " AND oi.case_classification = :classification";
                $params['classification'] = $filters['classification'];
            }
            
            if (isset($filters['recordable'])) {
                $sql .= " AND oi.is_recordable = :recordable";
                $params['recordable'] = $filters['recordable'] ? 1 : 0;
            }
            
            $sql .= " ORDER BY oi.injury_date DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $injuries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format results
            $formatted = array_map(function($injury) {
                return $this->formatInjuryForList($injury);
            }, $injuries);
            
            // Log access
            $this->audit('VIEW', 'osha_injuries', null, [
                'year' => $year,
                'count' => count($formatted)
            ]);
            
            $result = $this->paginate($formatted, $page, $perPage);
            
            return ApiResponse::success($result, 'OSHA injuries retrieved successfully');
            
        } catch (Exception $e) {
            $this->logError('getInjuries', $e, ['filters' => $filters]);
            return $this->handleException($e, 'Failed to retrieve OSHA injuries');
        }
    }

    /**
     * Record new injury/illness
     * 
     * @param array $data Injury data
     * @return array API response
     */
    public function recordInjury(array $data): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            // Validate injury data
            $errors = $this->validateInjuryData($data);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            // Determine recordability per 29 CFR 1904.7
            $recordability = $this->determineRecordability($data);
            
            $injuryId = $this->generateUuid();
            $caseNumber = $this->generateCaseNumber($data['establishment_id'] ?? null);
            
            $sql = "INSERT INTO osha_injuries (
                        injury_id, case_number, establishment_id, employer_id,
                        employee_patient_id, encounter_id,
                        injury_date, injury_time, injury_location,
                        injury_description, body_part_affected,
                        injury_category, case_classification,
                        is_recordable, recordability_reason,
                        days_away_from_work, days_restricted,
                        is_privacy_case, reported_by, reported_at,
                        created_at
                    ) VALUES (
                        :injury_id, :case_number, :establishment_id, :employer_id,
                        :employee_patient_id, :encounter_id,
                        :injury_date, :injury_time, :injury_location,
                        :injury_description, :body_part_affected,
                        :injury_category, :case_classification,
                        :is_recordable, :recordability_reason,
                        :days_away_from_work, :days_restricted,
                        :is_privacy_case, :reported_by, :reported_at,
                        :created_at
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'injury_id' => $injuryId,
                'case_number' => $caseNumber,
                'establishment_id' => $data['establishment_id'] ?? null,
                'employer_id' => $data['employer_id'] ?? null,
                'employee_patient_id' => $data['patient_id'] ?? null,
                'encounter_id' => $data['encounter_id'] ?? null,
                'injury_date' => $data['injury_date'],
                'injury_time' => $data['injury_time'] ?? null,
                'injury_location' => $data['injury_location'] ?? null,
                'injury_description' => $data['injury_description'],
                'body_part_affected' => $data['body_part_affected'] ?? null,
                'injury_category' => $data['injury_category'] ?? self::CATEGORY_INJURY,
                'case_classification' => $data['case_classification'] ?? self::CLASS_OTHER_RECORDABLE,
                'is_recordable' => $recordability['is_recordable'] ? 1 : 0,
                'recordability_reason' => $recordability['reason'],
                'days_away_from_work' => (int)($data['days_away_from_work'] ?? 0),
                'days_restricted' => (int)($data['days_restricted'] ?? 0),
                'is_privacy_case' => isset($data['is_privacy_case']) ? ($data['is_privacy_case'] ? 1 : 0) : 0,
                'reported_by' => $this->getCurrentUserId(),
                'reported_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log audit
            $this->audit('CREATE', 'osha_injury', $injuryId, [
                'is_recordable' => $recordability['is_recordable'],
                'case_classification' => $data['case_classification'] ?? null
            ]);
            
            return ApiResponse::success([
                'injury_id' => $injuryId,
                'case_number' => $caseNumber,
                'is_recordable' => $recordability['is_recordable'],
                'recordability_reason' => $recordability['reason']
            ], 'Injury recorded successfully');
            
        } catch (Exception $e) {
            $this->logError('recordInjury', $e, ['data_keys' => array_keys($data)]);
            return $this->handleException($e, 'Failed to record injury');
        }
    }

    /**
     * Update injury record
     * 
     * @param string $id Injury ID
     * @param array $data Update data
     * @return array API response
     */
    public function updateInjury(string $id, array $data): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid injury ID format']]);
            }
            
            $injury = $this->findInjuryById($id);
            
            if (!$injury) {
                return ApiResponse::notFound('Injury record not found');
            }
            
            // Validate update data
            $errors = $this->validateInjuryUpdate($data);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            // Re-determine recordability if relevant fields changed
            if (isset($data['case_classification']) || isset($data['days_away_from_work']) || 
                isset($data['days_restricted']) || isset($data['injury_category'])) {
                $mergedData = array_merge($injury, $data);
                $recordability = $this->determineRecordability($mergedData);
            } else {
                $recordability = [
                    'is_recordable' => $injury['is_recordable'],
                    'reason' => $injury['recordability_reason']
                ];
            }
            
            $sql = "UPDATE osha_injuries SET
                        injury_date = COALESCE(:injury_date, injury_date),
                        injury_time = COALESCE(:injury_time, injury_time),
                        injury_location = COALESCE(:injury_location, injury_location),
                        injury_description = COALESCE(:injury_description, injury_description),
                        body_part_affected = COALESCE(:body_part_affected, body_part_affected),
                        injury_category = COALESCE(:injury_category, injury_category),
                        case_classification = COALESCE(:case_classification, case_classification),
                        is_recordable = :is_recordable,
                        recordability_reason = :recordability_reason,
                        days_away_from_work = COALESCE(:days_away_from_work, days_away_from_work),
                        days_restricted = COALESCE(:days_restricted, days_restricted),
                        is_privacy_case = COALESCE(:is_privacy_case, is_privacy_case),
                        updated_at = :updated_at
                    WHERE injury_id = :injury_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'injury_id' => $id,
                'injury_date' => $data['injury_date'] ?? null,
                'injury_time' => $data['injury_time'] ?? null,
                'injury_location' => $data['injury_location'] ?? null,
                'injury_description' => $data['injury_description'] ?? null,
                'body_part_affected' => $data['body_part_affected'] ?? null,
                'injury_category' => $data['injury_category'] ?? null,
                'case_classification' => $data['case_classification'] ?? null,
                'is_recordable' => $recordability['is_recordable'] ? 1 : 0,
                'recordability_reason' => $recordability['reason'],
                'days_away_from_work' => $data['days_away_from_work'] ?? null,
                'days_restricted' => $data['days_restricted'] ?? null,
                'is_privacy_case' => isset($data['is_privacy_case']) ? ($data['is_privacy_case'] ? 1 : 0) : null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log audit
            $this->audit('UPDATE', 'osha_injury', $id, [
                'fields_updated' => array_keys($data)
            ]);
            
            return ApiResponse::success([
                'injury_id' => $id,
                'is_recordable' => $recordability['is_recordable']
            ], 'Injury updated successfully');
            
        } catch (Exception $e) {
            $this->logError('updateInjury', $e, ['injury_id' => $id]);
            return $this->handleException($e, 'Failed to update injury');
        }
    }

    /**
     * Delete injury record
     * 
     * @param string $id Injury ID
     * @return array API response
     */
    public function deleteInjury(string $id): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            // Only admins can delete OSHA records
            $role = $this->getCurrentUserRole();
            if (!in_array($role, ['tadmin', 'cadmin', 'Admin'])) {
                return ApiResponse::forbidden('Only administrators can delete OSHA records');
            }
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid injury ID format']]);
            }
            
            $injury = $this->findInjuryById($id);
            
            if (!$injury) {
                return ApiResponse::notFound('Injury record not found');
            }
            
            // Soft delete
            $sql = "UPDATE osha_injuries SET 
                        deleted_at = :deleted_at,
                        deleted_by = :deleted_by
                    WHERE injury_id = :injury_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'injury_id' => $id,
                'deleted_at' => date('Y-m-d H:i:s'),
                'deleted_by' => $this->getCurrentUserId()
            ]);
            
            // Log audit
            $this->audit('DELETE', 'osha_injury', $id);
            
            return ApiResponse::success(null, 'Injury record deleted');
            
        } catch (Exception $e) {
            $this->logError('deleteInjury', $e, ['injury_id' => $id]);
            return $this->handleException($e, 'Failed to delete injury');
        }
    }

    /**
     * Get OSHA 300 Log data
     * 
     * @param int $year Year for the log
     * @param string|null $establishmentId Optional establishment ID
     * @return array API response
     */
    public function get300Log(int $year, ?string $establishmentId = null): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_compliance');
            
            $sql = "SELECT 
                        oi.*,
                        p.legal_first_name, p.legal_last_name, p.job_title,
                        est.establishment_name
                    FROM osha_injuries oi
                    LEFT JOIN patients p ON oi.employee_patient_id = p.patient_id
                    LEFT JOIN establishments est ON oi.establishment_id = est.establishment_id
                    WHERE YEAR(oi.injury_date) = :year
                    AND oi.is_recordable = 1
                    AND oi.deleted_at IS NULL";
            
            $params = ['year' => $year];
            
            if ($establishmentId) {
                $sql .= " AND oi.establishment_id = :establishment_id";
                $params['establishment_id'] = $establishmentId;
            }
            
            $sql .= " ORDER BY oi.case_number ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $injuries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format for 300 Log
            $logEntries = array_map(function($injury) {
                return $this->format300LogEntry($injury);
            }, $injuries);
            
            // Get establishment info
            $establishmentInfo = $this->getEstablishmentInfo($establishmentId);
            
            // Log access
            $this->audit('VIEW', 'osha_300_log', null, [
                'year' => $year,
                'establishment_id' => $establishmentId
            ]);
            
            return ApiResponse::success([
                'year' => $year,
                'establishment' => $establishmentInfo,
                'entries' => $logEntries,
                'total_cases' => count($logEntries),
                'summary' => $this->calculate300Summary($injuries)
            ], 'OSHA 300 Log retrieved');
            
        } catch (Exception $e) {
            $this->logError('get300Log', $e, ['year' => $year]);
            return $this->handleException($e, 'Failed to retrieve 300 Log');
        }
    }

    /**
     * Calculate TRIR, DART and other rates
     * 
     * @param int $year Year for calculation
     * @param string|null $establishmentId Optional establishment ID
     * @return array API response
     */
    public function calculateRates(int $year, ?string $establishmentId = null): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_compliance');
            
            // Get injury counts
            $sql = "SELECT 
                        COUNT(*) as total_recordable,
                        SUM(CASE WHEN case_classification = 'death' THEN 1 ELSE 0 END) as deaths,
                        SUM(CASE WHEN case_classification = 'days_away' THEN 1 ELSE 0 END) as days_away_cases,
                        SUM(CASE WHEN case_classification = 'job_restriction' THEN 1 ELSE 0 END) as restricted_cases,
                        SUM(CASE WHEN case_classification = 'other_recordable' THEN 1 ELSE 0 END) as other_recordable,
                        SUM(days_away_from_work) as total_days_away,
                        SUM(days_restricted) as total_days_restricted
                    FROM osha_injuries
                    WHERE YEAR(injury_date) = :year
                    AND is_recordable = 1
                    AND deleted_at IS NULL";
            
            $params = ['year' => $year];
            
            if ($establishmentId) {
                $sql .= " AND establishment_id = :establishment_id";
                $params['establishment_id'] = $establishmentId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get hours worked (from establishments or default)
            $hoursWorked = $this->getHoursWorked($year, $establishmentId);
            
            // Calculate rates per OSHA formula: (N/EH) x 200,000
            $trir = $hoursWorked > 0 
                ? ($counts['total_recordable'] / $hoursWorked) * self::STANDARD_HOURS_BASE 
                : 0;
            
            $dartCases = $counts['days_away_cases'] + $counts['restricted_cases'];
            $dart = $hoursWorked > 0 
                ? ($dartCases / $hoursWorked) * self::STANDARD_HOURS_BASE 
                : 0;
            
            $deathRate = $hoursWorked > 0 
                ? ($counts['deaths'] / $hoursWorked) * self::STANDARD_HOURS_BASE 
                : 0;
            
            // Log access
            $this->audit('VIEW', 'osha_rates', null, [
                'year' => $year,
                'establishment_id' => $establishmentId
            ]);
            
            return ApiResponse::success([
                'year' => $year,
                'establishment_id' => $establishmentId,
                'hours_worked' => $hoursWorked,
                'counts' => [
                    'total_recordable' => (int)$counts['total_recordable'],
                    'deaths' => (int)$counts['deaths'],
                    'days_away_cases' => (int)$counts['days_away_cases'],
                    'restricted_cases' => (int)$counts['restricted_cases'],
                    'other_recordable' => (int)$counts['other_recordable'],
                    'total_days_away' => (int)$counts['total_days_away'],
                    'total_days_restricted' => (int)$counts['total_days_restricted'],
                ],
                'rates' => [
                    'trir' => round($trir, 2),
                    'dart' => round($dart, 2),
                    'death_rate' => round($deathRate, 4),
                    'ltir' => round($dart, 2), // Lost Time Incident Rate = DART
                ],
                'calculation_base' => self::STANDARD_HOURS_BASE
            ], 'OSHA rates calculated');
            
        } catch (Exception $e) {
            $this->logError('calculateRates', $e, ['year' => $year]);
            return $this->handleException($e, 'Failed to calculate rates');
        }
    }

    /**
     * Submit data to OSHA ITA (Injury Tracking Application)
     * 
     * @param array $data Submission data
     * @return array API response
     */
    public function submitToIta(array $data): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_compliance');
            
            // Only admins can submit to ITA
            $role = $this->getCurrentUserRole();
            if (!in_array($role, ['tadmin', 'cadmin', 'Admin'])) {
                return ApiResponse::forbidden('Only administrators can submit to OSHA ITA');
            }
            
            // Validate required fields
            if (empty($data['year'])) {
                return ApiResponse::validationError(['year' => ['Year is required']]);
            }
            
            $year = (int)$data['year'];
            $establishmentId = $data['establishment_id'] ?? null;
            
            // Get 300A summary data
            $summaryResult = $this->calculateRates($year, $establishmentId);
            
            if (!$summaryResult['success']) {
                return $summaryResult;
            }
            
            // Create ITA submission record
            $submissionId = $this->generateUuid();
            
            $sql = "INSERT INTO osha_ita_submissions (
                        submission_id, year, establishment_id,
                        submission_data, submitted_by, submitted_at,
                        status, created_at
                    ) VALUES (
                        :submission_id, :year, :establishment_id,
                        :submission_data, :submitted_by, :submitted_at,
                        :status, :created_at
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'submission_id' => $submissionId,
                'year' => $year,
                'establishment_id' => $establishmentId,
                'submission_data' => json_encode($summaryResult['data']),
                'submitted_by' => $this->getCurrentUserId(),
                'submitted_at' => date('Y-m-d H:i:s'),
                'status' => 'pending', // Would be 'submitted' after actual API call
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log audit
            $this->audit('SUBMIT', 'osha_ita', $submissionId, [
                'year' => $year,
                'establishment_id' => $establishmentId
            ]);
            
            // Note: Actual OSHA ITA API integration would happen here
            // This is a placeholder for the submission record
            
            return ApiResponse::success([
                'submission_id' => $submissionId,
                'year' => $year,
                'status' => 'pending',
                'data' => $summaryResult['data'],
                'message' => 'ITA submission record created. Manual submission to OSHA ITA portal may be required.'
            ], 'ITA submission initiated');
            
        } catch (Exception $e) {
            $this->logError('submitToIta', $e, ['data' => $data]);
            return $this->handleException($e, 'Failed to submit to ITA');
        }
    }

    /**
     * Find injury by ID
     * 
     * @param string $id Injury ID
     * @return array|null
     */
    private function findInjuryById(string $id): ?array
    {
        $sql = "SELECT * FROM osha_injuries WHERE injury_id = :injury_id AND deleted_at IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['injury_id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Determine recordability per 29 CFR 1904.7
     * 
     * @param array $data Injury data
     * @return array ['is_recordable' => bool, 'reason' => string]
     */
    private function determineRecordability(array $data): array
    {
        // Death is always recordable
        if (($data['case_classification'] ?? '') === self::CLASS_DEATH) {
            return ['is_recordable' => true, 'reason' => 'Fatality - always recordable'];
        }
        
        // Days away from work
        if (($data['days_away_from_work'] ?? 0) > 0) {
            return ['is_recordable' => true, 'reason' => 'Days away from work'];
        }
        
        // Restricted work/job transfer
        if (($data['days_restricted'] ?? 0) > 0) {
            return ['is_recordable' => true, 'reason' => 'Restricted work or job transfer'];
        }
        
        // Medical treatment beyond first aid
        if (isset($data['medical_treatment']) && $data['medical_treatment']) {
            return ['is_recordable' => true, 'reason' => 'Medical treatment beyond first aid'];
        }
        
        // Loss of consciousness
        if (isset($data['loss_of_consciousness']) && $data['loss_of_consciousness']) {
            return ['is_recordable' => true, 'reason' => 'Loss of consciousness'];
        }
        
        // Significant injury/illness diagnosed by physician
        if (isset($data['significant_injury']) && $data['significant_injury']) {
            return ['is_recordable' => true, 'reason' => 'Significant injury diagnosed by physician'];
        }
        
        // Check for specific recordable conditions (hearing loss, TB, etc.)
        $category = $data['injury_category'] ?? self::CATEGORY_INJURY;
        if (in_array($category, [self::CATEGORY_HEARING_LOSS, self::CATEGORY_RESPIRATORY])) {
            return ['is_recordable' => true, 'reason' => 'Occupational illness - ' . $category];
        }
        
        return ['is_recordable' => false, 'reason' => 'Does not meet recordability criteria'];
    }

    /**
     * Validate injury data
     * 
     * @param array $data
     * @return array Errors
     */
    private function validateInjuryData(array $data): array
    {
        $errors = [];
        
        if (empty($data['injury_date'])) {
            $errors['injury_date'] = ['Injury date is required'];
        } elseif (!strtotime($data['injury_date'])) {
            $errors['injury_date'] = ['Invalid injury date format'];
        }
        
        if (empty($data['injury_description'])) {
            $errors['injury_description'] = ['Injury description is required'];
        }
        
        if (!empty($data['injury_category'])) {
            $validCategories = [
                self::CATEGORY_INJURY,
                self::CATEGORY_SKIN_DISORDER,
                self::CATEGORY_RESPIRATORY,
                self::CATEGORY_POISONING,
                self::CATEGORY_HEARING_LOSS,
                self::CATEGORY_OTHER_ILLNESS
            ];
            
            if (!in_array($data['injury_category'], $validCategories)) {
                $errors['injury_category'] = ['Invalid injury category'];
            }
        }
        
        if (!empty($data['case_classification'])) {
            $validClassifications = [
                self::CLASS_DEATH,
                self::CLASS_DAYS_AWAY,
                self::CLASS_JOB_RESTRICTION,
                self::CLASS_OTHER_RECORDABLE
            ];
            
            if (!in_array($data['case_classification'], $validClassifications)) {
                $errors['case_classification'] = ['Invalid case classification'];
            }
        }
        
        return $errors;
    }

    /**
     * Validate injury update data
     * 
     * @param array $data
     * @return array Errors
     */
    private function validateInjuryUpdate(array $data): array
    {
        $errors = [];
        
        if (isset($data['injury_date']) && !strtotime($data['injury_date'])) {
            $errors['injury_date'] = ['Invalid injury date format'];
        }
        
        if (isset($data['days_away_from_work']) && $data['days_away_from_work'] < 0) {
            $errors['days_away_from_work'] = ['Days away cannot be negative'];
        }
        
        if (isset($data['days_restricted']) && $data['days_restricted'] < 0) {
            $errors['days_restricted'] = ['Days restricted cannot be negative'];
        }
        
        return $errors;
    }

    /**
     * Format injury for list display
     * 
     * @param array $injury
     * @return array
     */
    private function formatInjuryForList(array $injury): array
    {
        $employeeName = ($injury['is_privacy_case'] ?? false)
            ? 'Privacy Case'
            : (($injury['legal_first_name'] ?? '') . ' ' . ($injury['legal_last_name'] ?? ''));
        
        return [
            'injury_id' => $injury['injury_id'],
            'case_number' => $injury['case_number'],
            'establishment_name' => $injury['establishment_name'] ?? null,
            'employee_name' => $employeeName,
            'injury_date' => $injury['injury_date'],
            'injury_description' => substr($injury['injury_description'] ?? '', 0, 100),
            'body_part_affected' => $injury['body_part_affected'] ?? null,
            'injury_category' => $injury['injury_category'],
            'case_classification' => $injury['case_classification'],
            'is_recordable' => (bool)$injury['is_recordable'],
            'days_away_from_work' => (int)($injury['days_away_from_work'] ?? 0),
            'days_restricted' => (int)($injury['days_restricted'] ?? 0),
        ];
    }

    /**
     * Format for 300 Log entry
     * 
     * @param array $injury
     * @return array
     */
    private function format300LogEntry(array $injury): array
    {
        $employeeName = ($injury['is_privacy_case'] ?? false)
            ? 'Privacy Case'
            : (($injury['legal_first_name'] ?? '') . ' ' . ($injury['legal_last_name'] ?? ''));
        
        return [
            'case_number' => $injury['case_number'],
            'employee_name' => $employeeName,
            'job_title' => ($injury['is_privacy_case'] ?? false) ? '' : ($injury['job_title'] ?? ''),
            'date_of_injury' => $injury['injury_date'],
            'where_event_occurred' => $injury['injury_location'] ?? '',
            'description' => $injury['injury_description'] ?? '',
            'classification' => [
                'death' => $injury['case_classification'] === self::CLASS_DEATH,
                'days_away' => $injury['case_classification'] === self::CLASS_DAYS_AWAY,
                'restricted' => $injury['case_classification'] === self::CLASS_JOB_RESTRICTION,
                'other_recordable' => $injury['case_classification'] === self::CLASS_OTHER_RECORDABLE,
            ],
            'days_away_from_work' => (int)($injury['days_away_from_work'] ?? 0),
            'days_restricted' => (int)($injury['days_restricted'] ?? 0),
            'injury_type' => $injury['injury_category'],
        ];
    }

    /**
     * Calculate 300 Log summary
     * 
     * @param array $injuries
     * @return array
     */
    private function calculate300Summary(array $injuries): array
    {
        $summary = [
            'total_cases' => count($injuries),
            'deaths' => 0,
            'days_away_cases' => 0,
            'restricted_cases' => 0,
            'other_recordable' => 0,
            'total_days_away' => 0,
            'total_days_restricted' => 0,
            'injury_count' => 0,
            'skin_disorder_count' => 0,
            'respiratory_count' => 0,
            'poisoning_count' => 0,
            'hearing_loss_count' => 0,
            'other_illness_count' => 0,
        ];
        
        foreach ($injuries as $injury) {
            $classification = $injury['case_classification'] ?? '';
            $category = $injury['injury_category'] ?? '';
            
            if ($classification === self::CLASS_DEATH) $summary['deaths']++;
            if ($classification === self::CLASS_DAYS_AWAY) $summary['days_away_cases']++;
            if ($classification === self::CLASS_JOB_RESTRICTION) $summary['restricted_cases']++;
            if ($classification === self::CLASS_OTHER_RECORDABLE) $summary['other_recordable']++;
            
            $summary['total_days_away'] += (int)($injury['days_away_from_work'] ?? 0);
            $summary['total_days_restricted'] += (int)($injury['days_restricted'] ?? 0);
            
            if ($category === self::CATEGORY_INJURY) $summary['injury_count']++;
            if ($category === self::CATEGORY_SKIN_DISORDER) $summary['skin_disorder_count']++;
            if ($category === self::CATEGORY_RESPIRATORY) $summary['respiratory_count']++;
            if ($category === self::CATEGORY_POISONING) $summary['poisoning_count']++;
            if ($category === self::CATEGORY_HEARING_LOSS) $summary['hearing_loss_count']++;
            if ($category === self::CATEGORY_OTHER_ILLNESS) $summary['other_illness_count']++;
        }
        
        return $summary;
    }

    /**
     * Generate case number
     * 
     * @param string|null $establishmentId
     * @return string
     */
    private function generateCaseNumber(?string $establishmentId): string
    {
        $year = date('Y');
        
        // Get next case number for this year/establishment
        $sql = "SELECT COUNT(*) + 1 as next_number 
                FROM osha_injuries 
                WHERE YEAR(injury_date) = :year";
        
        $params = ['year' => $year];
        
        if ($establishmentId) {
            $sql .= " AND establishment_id = :establishment_id";
            $params['establishment_id'] = $establishmentId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $number = str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
        
        return "{$year}-{$number}";
    }

    /**
     * Get establishment info
     * 
     * @param string|null $establishmentId
     * @return array|null
     */
    private function getEstablishmentInfo(?string $establishmentId): ?array
    {
        if (!$establishmentId) {
            return null;
        }
        
        $sql = "SELECT * FROM establishments WHERE establishment_id = :establishment_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['establishment_id' => $establishmentId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get hours worked for rate calculation
     * 
     * @param int $year
     * @param string|null $establishmentId
     * @return int
     */
    private function getHoursWorked(int $year, ?string $establishmentId): int
    {
        // Try to get from establishment records
        $sql = "SELECT total_hours_worked 
                FROM establishment_hours 
                WHERE year = :year";
        
        $params = ['year' => $year];
        
        if ($establishmentId) {
            $sql .= " AND establishment_id = :establishment_id";
            $params['establishment_id'] = $establishmentId;
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total_hours_worked'] > 0) {
                return (int)$result['total_hours_worked'];
            }
        } catch (Exception $e) {
            // Table may not exist, use default
        }
        
        // Return default based on employee count (2000 hours per employee per year)
        return self::STANDARD_HOURS_BASE; // Default to calculation base
    }
}
