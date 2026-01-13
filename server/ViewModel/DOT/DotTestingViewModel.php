<?php
/**
 * DotTestingViewModel - Business logic layer for DOT drug/alcohol testing
 * 
 * Handles: DOT test management per 49 CFR Part 40 requirements
 * Security: MRO verification workflow, chain of custody, notification tracking
 * 
 * @package SafeShift\ViewModel\DOT
 */

declare(strict_types=1);

namespace ViewModel\DOT;

use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;
use Core\Services\AuditService;
use Exception;
use PDO;

/**
 * DOT Testing ViewModel
 * 
 * Manages DOT-regulated drug and alcohol testing with 49 CFR Part 40 compliance.
 * Tracks CCF forms, MRO verification, notification windows, and results.
 */
class DotTestingViewModel extends BaseViewModel
{
    /** DOT test types */
    public const TYPE_PRE_EMPLOYMENT = 'pre_employment';
    public const TYPE_RANDOM = 'random';
    public const TYPE_POST_ACCIDENT = 'post_accident';
    public const TYPE_REASONABLE_SUSPICION = 'reasonable_suspicion';
    public const TYPE_RETURN_TO_DUTY = 'return_to_duty';
    public const TYPE_FOLLOW_UP = 'follow_up';

    /** DOT test status */
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_COLLECTION_PENDING = 'collection_pending';
    public const STATUS_COLLECTED = 'collected';
    public const STATUS_AT_LAB = 'at_lab';
    public const STATUS_LAB_RESULTS = 'lab_results';
    public const STATUS_MRO_REVIEW = 'mro_review';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** DOT result types */
    public const RESULT_NEGATIVE = 'negative';
    public const RESULT_NEGATIVE_DILUTE = 'negative_dilute';
    public const RESULT_POSITIVE = 'positive';
    public const RESULT_REFUSAL = 'refusal';
    public const RESULT_SHY_BLADDER = 'shy_bladder';
    public const RESULT_CANCELLED = 'cancelled';

    /** 49 CFR Part 40 notification window (in hours) */
    private const NOTIFICATION_WINDOW_HOURS = 48;

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
     * List DOT tests with filters
     * 
     * @param array $filters Optional filters (status, type, employer_id, date_range)
     * @return array API response
     */
    public function index(array $filters = []): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_encounters');
            
            [$page, $perPage] = $this->getPaginationParams($filters);
            
            $sql = "SELECT 
                        dt.*,
                        p.legal_first_name, p.legal_last_name, p.mrn,
                        e.employer_name
                    FROM dot_tests dt
                    LEFT JOIN patients p ON dt.patient_id = p.patient_id
                    LEFT JOIN employers e ON dt.employer_id = e.employer_id
                    WHERE 1=1";
            
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND dt.status = :status";
                $params['status'] = $filters['status'];
            }
            
            if (!empty($filters['test_type'])) {
                $sql .= " AND dt.test_type = :test_type";
                $params['test_type'] = $filters['test_type'];
            }
            
            if (!empty($filters['employer_id'])) {
                $sql .= " AND dt.employer_id = :employer_id";
                $params['employer_id'] = $filters['employer_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND dt.ordered_at >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND dt.ordered_at <= :date_to";
                $params['date_to'] = $filters['date_to'] . ' 23:59:59';
            }
            
            $sql .= " ORDER BY dt.ordered_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format results
            $formatted = array_map(function($test) {
                return $this->formatTestForList($test);
            }, $tests);
            
            // Log access
            $this->audit('VIEW', 'dot_test_list', null, [
                'count' => count($formatted),
                'filters' => array_keys($filters)
            ]);
            
            $result = $this->paginate($formatted, $page, $perPage);
            
            return ApiResponse::success($result, 'DOT tests retrieved successfully');
            
        } catch (Exception $e) {
            $this->logError('index', $e, ['filters' => $filters]);
            return $this->handleException($e, 'Failed to retrieve DOT tests');
        }
    }

    /**
     * Get single DOT test by ID
     * 
     * @param string $id Test ID
     * @return array API response
     */
    public function show(string $id): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid test ID format']]);
            }
            
            $test = $this->findTestById($id);
            
            if (!$test) {
                return ApiResponse::notFound('DOT test not found');
            }
            
            // Log PHI access
            $this->logPhiAccess('dot_test', $id, 'view');
            
            return ApiResponse::success(
                $this->formatTestForDetail($test),
                'DOT test retrieved successfully'
            );
            
        } catch (Exception $e) {
            $this->logError('show', $e, ['test_id' => $id]);
            return $this->handleException($e, 'Failed to retrieve DOT test');
        }
    }

    /**
     * Initiate new DOT test
     * 
     * @param array $data Test data
     * @return array API response
     */
    public function initiate(array $data): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            // Validate required fields
            $errors = $this->validateTestInitiation($data);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            $testId = $this->generateUuid();
            $ccfNumber = $this->generateCcfNumber();
            $orderedAt = date('Y-m-d H:i:s');
            
            // Calculate notification deadline (49 CFR Part 40)
            $notificationDeadline = date('Y-m-d H:i:s', 
                strtotime("+{self::NOTIFICATION_WINDOW_HOURS} hours", strtotime($orderedAt))
            );
            
            $sql = "INSERT INTO dot_tests (
                        test_id, patient_id, employer_id, encounter_id,
                        test_type, test_reason, ccf_number,
                        ordered_by, ordered_at, notification_deadline,
                        status, created_at
                    ) VALUES (
                        :test_id, :patient_id, :employer_id, :encounter_id,
                        :test_type, :test_reason, :ccf_number,
                        :ordered_by, :ordered_at, :notification_deadline,
                        :status, :created_at
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'test_id' => $testId,
                'patient_id' => $data['patient_id'],
                'employer_id' => $data['employer_id'] ?? null,
                'encounter_id' => $data['encounter_id'] ?? null,
                'test_type' => $data['test_type'],
                'test_reason' => $data['test_reason'] ?? null,
                'ccf_number' => $ccfNumber,
                'ordered_by' => $this->getCurrentUserId(),
                'ordered_at' => $orderedAt,
                'notification_deadline' => $notificationDeadline,
                'status' => self::STATUS_ORDERED,
                'created_at' => $orderedAt
            ]);
            
            // Log audit
            $this->audit('CREATE', 'dot_test', $testId, [
                'test_type' => $data['test_type'],
                'patient_id' => $data['patient_id']
            ]);
            
            return ApiResponse::success([
                'test_id' => $testId,
                'ccf_number' => $ccfNumber,
                'status' => self::STATUS_ORDERED,
                'notification_deadline' => $notificationDeadline
            ], 'DOT test initiated successfully');
            
        } catch (Exception $e) {
            $this->logError('initiate', $e, ['data_keys' => array_keys($data)]);
            return $this->handleException($e, 'Failed to initiate DOT test');
        }
    }

    /**
     * Update CCF (Custody and Control Form) data
     * 
     * @param string $id Test ID
     * @param array $ccf CCF data
     * @return array API response
     */
    public function updateCcf(string $id, array $ccf): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid test ID format']]);
            }
            
            $test = $this->findTestById($id);
            
            if (!$test) {
                return ApiResponse::notFound('DOT test not found');
            }
            
            // Validate CCF can be updated
            if (in_array($test['status'], [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
                return ApiResponse::forbidden('CCF cannot be updated for completed or cancelled tests');
            }
            
            // Validate CCF data
            $errors = $this->validateCcfData($ccf);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            $sql = "UPDATE dot_tests SET
                        collection_site = :collection_site,
                        collector_name = :collector_name,
                        collected_at = :collected_at,
                        specimen_id = :specimen_id,
                        specimen_temperature = :specimen_temperature,
                        split_specimen = :split_specimen,
                        ccf_step = :ccf_step,
                        updated_at = :updated_at
                    WHERE test_id = :test_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'test_id' => $id,
                'collection_site' => $ccf['collection_site'] ?? null,
                'collector_name' => $ccf['collector_name'] ?? null,
                'collected_at' => $ccf['collected_at'] ?? null,
                'specimen_id' => $ccf['specimen_id'] ?? null,
                'specimen_temperature' => $ccf['specimen_temperature'] ?? null,
                'split_specimen' => isset($ccf['split_specimen']) ? ($ccf['split_specimen'] ? 1 : 0) : 0,
                'ccf_step' => $ccf['ccf_step'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update status if collection recorded
            if (!empty($ccf['collected_at'])) {
                $this->updateTestStatus($id, self::STATUS_COLLECTED);
            }
            
            // Log audit
            $this->audit('UPDATE', 'dot_test_ccf', $id, [
                'ccf_step' => $ccf['ccf_step'] ?? null
            ]);
            
            return ApiResponse::success([
                'test_id' => $id,
                'ccf_updated' => true
            ], 'CCF updated successfully');
            
        } catch (Exception $e) {
            $this->logError('updateCcf', $e, ['test_id' => $id]);
            return $this->handleException($e, 'Failed to update CCF');
        }
    }

    /**
     * Submit lab results
     * 
     * @param string $id Test ID
     * @param array $results Lab results
     * @return array API response
     */
    public function submitResults(string $id, array $results): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid test ID format']]);
            }
            
            $test = $this->findTestById($id);
            
            if (!$test) {
                return ApiResponse::notFound('DOT test not found');
            }
            
            // Validate results
            $errors = $this->validateLabResults($results);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            $sql = "UPDATE dot_tests SET
                        lab_name = :lab_name,
                        lab_received_at = :lab_received_at,
                        lab_result = :lab_result,
                        lab_result_date = :lab_result_date,
                        substances_detected = :substances_detected,
                        cutoff_levels = :cutoff_levels,
                        status = :status,
                        updated_at = :updated_at
                    WHERE test_id = :test_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'test_id' => $id,
                'lab_name' => $results['lab_name'] ?? null,
                'lab_received_at' => $results['lab_received_at'] ?? null,
                'lab_result' => $results['lab_result'],
                'lab_result_date' => $results['lab_result_date'] ?? date('Y-m-d'),
                'substances_detected' => json_encode($results['substances_detected'] ?? []),
                'cutoff_levels' => json_encode($results['cutoff_levels'] ?? []),
                'status' => self::STATUS_MRO_REVIEW,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log audit
            $this->audit('UPDATE', 'dot_test_results', $id, [
                'lab_result' => $results['lab_result']
            ]);
            
            return ApiResponse::success([
                'test_id' => $id,
                'status' => self::STATUS_MRO_REVIEW,
                'requires_mro_review' => true
            ], 'Lab results submitted - MRO review required');
            
        } catch (Exception $e) {
            $this->logError('submitResults', $e, ['test_id' => $id]);
            return $this->handleException($e, 'Failed to submit lab results');
        }
    }

    /**
     * MRO verification of test results
     * 
     * @param string $id Test ID
     * @param array $verification MRO verification data
     * @return array API response
     */
    public function mroVerify(string $id, array $verification): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('dot_mro_verify');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid test ID format']]);
            }
            
            $test = $this->findTestById($id);
            
            if (!$test) {
                return ApiResponse::notFound('DOT test not found');
            }
            
            // Only tests in MRO_REVIEW status can be verified
            if ($test['status'] !== self::STATUS_MRO_REVIEW) {
                return ApiResponse::badRequest('Test is not pending MRO review');
            }
            
            // Validate MRO verification
            $errors = $this->validateMroVerification($verification);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            $finalResult = $verification['final_result'];
            $verifiedAt = date('Y-m-d H:i:s');
            
            $sql = "UPDATE dot_tests SET
                        mro_id = :mro_id,
                        mro_verified_at = :mro_verified_at,
                        mro_final_result = :mro_final_result,
                        mro_notes = :mro_notes,
                        donor_interview_date = :donor_interview_date,
                        donor_explanation = :donor_explanation,
                        valid_medical_explanation = :valid_medical_explanation,
                        status = :status,
                        completed_at = :completed_at,
                        updated_at = :updated_at
                    WHERE test_id = :test_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'test_id' => $id,
                'mro_id' => $this->getCurrentUserId(),
                'mro_verified_at' => $verifiedAt,
                'mro_final_result' => $finalResult,
                'mro_notes' => $verification['notes'] ?? null,
                'donor_interview_date' => $verification['donor_interview_date'] ?? null,
                'donor_explanation' => $verification['donor_explanation'] ?? null,
                'valid_medical_explanation' => isset($verification['valid_medical_explanation']) 
                    ? ($verification['valid_medical_explanation'] ? 1 : 0) 
                    : 0,
                'status' => self::STATUS_COMPLETED,
                'completed_at' => $verifiedAt,
                'updated_at' => $verifiedAt
            ]);
            
            // Create result notification record
            $this->createResultNotification($id, $test, $finalResult);
            
            // Log audit
            $this->audit('MRO_VERIFY', 'dot_test', $id, [
                'final_result' => $finalResult
            ]);
            
            return ApiResponse::success([
                'test_id' => $id,
                'mro_final_result' => $finalResult,
                'status' => self::STATUS_COMPLETED,
                'verified_at' => $verifiedAt
            ], 'MRO verification completed');
            
        } catch (Exception $e) {
            $this->logError('mroVerify', $e, ['test_id' => $id]);
            return $this->handleException($e, 'Failed to complete MRO verification');
        }
    }

    /**
     * Get tests by status
     * 
     * @param string $status Test status
     * @return array API response
     */
    public function getByStatus(string $status): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_encounters');
            
            // Validate status
            $validStatuses = [
                self::STATUS_ORDERED,
                self::STATUS_COLLECTION_PENDING,
                self::STATUS_COLLECTED,
                self::STATUS_AT_LAB,
                self::STATUS_LAB_RESULTS,
                self::STATUS_MRO_REVIEW,
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED
            ];
            
            if (!in_array($status, $validStatuses)) {
                return ApiResponse::validationError(['status' => ['Invalid status value']]);
            }
            
            return $this->index(['status' => $status]);
            
        } catch (Exception $e) {
            $this->logError('getByStatus', $e, ['status' => $status]);
            return $this->handleException($e, 'Failed to retrieve tests by status');
        }
    }

    /**
     * Get tests approaching notification deadline
     * 
     * @return array API response
     */
    public function getApproachingDeadline(): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_encounters');
            
            $sql = "SELECT 
                        dt.*,
                        p.legal_first_name, p.legal_last_name, p.mrn,
                        e.employer_name,
                        TIMESTAMPDIFF(HOUR, NOW(), dt.notification_deadline) as hours_remaining
                    FROM dot_tests dt
                    LEFT JOIN patients p ON dt.patient_id = p.patient_id
                    LEFT JOIN employers e ON dt.employer_id = e.employer_id
                    WHERE dt.status NOT IN (:completed, :cancelled)
                    AND dt.notification_deadline IS NOT NULL
                    AND dt.notification_deadline <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                    ORDER BY dt.notification_deadline ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'completed' => self::STATUS_COMPLETED,
                'cancelled' => self::STATUS_CANCELLED
            ]);
            $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formatted = array_map(function($test) {
                $data = $this->formatTestForList($test);
                $data['hours_remaining'] = $test['hours_remaining'];
                $data['is_overdue'] = $test['hours_remaining'] < 0;
                return $data;
            }, $tests);
            
            return ApiResponse::success([
                'items' => $formatted,
                'count' => count($formatted)
            ], 'Tests approaching deadline retrieved');
            
        } catch (Exception $e) {
            $this->logError('getApproachingDeadline', $e);
            return $this->handleException($e, 'Failed to retrieve tests');
        }
    }

    /**
     * Find test by ID
     * 
     * @param string $id Test ID
     * @return array|null
     */
    private function findTestById(string $id): ?array
    {
        $sql = "SELECT 
                    dt.*,
                    p.legal_first_name, p.legal_last_name, p.mrn, p.date_of_birth,
                    e.employer_name
                FROM dot_tests dt
                LEFT JOIN patients p ON dt.patient_id = p.patient_id
                LEFT JOIN employers e ON dt.employer_id = e.employer_id
                WHERE dt.test_id = :test_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['test_id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Format test for list display
     * 
     * @param array $test
     * @return array
     */
    private function formatTestForList(array $test): array
    {
        return [
            'test_id' => $test['test_id'],
            'ccf_number' => $test['ccf_number'] ?? null,
            'patient_id' => $test['patient_id'],
            'patient_name' => ($test['legal_first_name'] ?? '') . ' ' . ($test['legal_last_name'] ?? ''),
            'mrn' => $test['mrn'] ?? null,
            'employer_name' => $test['employer_name'] ?? null,
            'test_type' => $test['test_type'],
            'status' => $test['status'],
            'ordered_at' => $test['ordered_at'],
            'notification_deadline' => $test['notification_deadline'] ?? null,
            'mro_final_result' => $test['mro_final_result'] ?? null,
        ];
    }

    /**
     * Format test for detail display
     * 
     * @param array $test
     * @return array
     */
    private function formatTestForDetail(array $test): array
    {
        return [
            'test_id' => $test['test_id'],
            'ccf_number' => $test['ccf_number'] ?? null,
            'patient_id' => $test['patient_id'],
            'patient_name' => ($test['legal_first_name'] ?? '') . ' ' . ($test['legal_last_name'] ?? ''),
            'mrn' => $test['mrn'] ?? null,
            'date_of_birth' => $test['date_of_birth'] ?? null,
            'employer_id' => $test['employer_id'],
            'employer_name' => $test['employer_name'] ?? null,
            'encounter_id' => $test['encounter_id'] ?? null,
            'test_type' => $test['test_type'],
            'test_reason' => $test['test_reason'] ?? null,
            'status' => $test['status'],
            'ordered_by' => $test['ordered_by'],
            'ordered_at' => $test['ordered_at'],
            'notification_deadline' => $test['notification_deadline'] ?? null,
            'collection_site' => $test['collection_site'] ?? null,
            'collector_name' => $test['collector_name'] ?? null,
            'collected_at' => $test['collected_at'] ?? null,
            'specimen_id' => $test['specimen_id'] ?? null,
            'specimen_temperature' => $test['specimen_temperature'] ?? null,
            'split_specimen' => (bool)($test['split_specimen'] ?? false),
            'lab_name' => $test['lab_name'] ?? null,
            'lab_received_at' => $test['lab_received_at'] ?? null,
            'lab_result' => $test['lab_result'] ?? null,
            'lab_result_date' => $test['lab_result_date'] ?? null,
            'substances_detected' => json_decode($test['substances_detected'] ?? '[]', true),
            'mro_id' => $test['mro_id'] ?? null,
            'mro_verified_at' => $test['mro_verified_at'] ?? null,
            'mro_final_result' => $test['mro_final_result'] ?? null,
            'mro_notes' => $test['mro_notes'] ?? null,
            'donor_interview_date' => $test['donor_interview_date'] ?? null,
            'completed_at' => $test['completed_at'] ?? null,
        ];
    }

    /**
     * Validate test initiation data
     * 
     * @param array $data
     * @return array Errors
     */
    private function validateTestInitiation(array $data): array
    {
        $errors = [];
        
        if (empty($data['patient_id'])) {
            $errors['patient_id'] = ['Patient ID is required'];
        }
        
        if (empty($data['test_type'])) {
            $errors['test_type'] = ['Test type is required'];
        } else {
            $validTypes = [
                self::TYPE_PRE_EMPLOYMENT,
                self::TYPE_RANDOM,
                self::TYPE_POST_ACCIDENT,
                self::TYPE_REASONABLE_SUSPICION,
                self::TYPE_RETURN_TO_DUTY,
                self::TYPE_FOLLOW_UP
            ];
            
            if (!in_array($data['test_type'], $validTypes)) {
                $errors['test_type'] = ['Invalid test type'];
            }
        }
        
        return $errors;
    }

    /**
     * Validate CCF data
     * 
     * @param array $ccf
     * @return array Errors
     */
    private function validateCcfData(array $ccf): array
    {
        $errors = [];
        
        if (isset($ccf['specimen_temperature'])) {
            $temp = (float)$ccf['specimen_temperature'];
            // Temperature must be between 90-100°F within 4 minutes of collection
            if ($temp < 90 || $temp > 100) {
                $errors['specimen_temperature'] = ['Temperature must be between 90-100°F'];
            }
        }
        
        return $errors;
    }

    /**
     * Validate lab results
     * 
     * @param array $results
     * @return array Errors
     */
    private function validateLabResults(array $results): array
    {
        $errors = [];
        
        if (empty($results['lab_result'])) {
            $errors['lab_result'] = ['Lab result is required'];
        } else {
            $validResults = [
                self::RESULT_NEGATIVE,
                self::RESULT_NEGATIVE_DILUTE,
                self::RESULT_POSITIVE,
                self::RESULT_REFUSAL,
                self::RESULT_SHY_BLADDER,
                self::RESULT_CANCELLED
            ];
            
            if (!in_array($results['lab_result'], $validResults)) {
                $errors['lab_result'] = ['Invalid lab result value'];
            }
        }
        
        return $errors;
    }

    /**
     * Validate MRO verification
     * 
     * @param array $verification
     * @return array Errors
     */
    private function validateMroVerification(array $verification): array
    {
        $errors = [];
        
        if (empty($verification['final_result'])) {
            $errors['final_result'] = ['Final result is required'];
        }
        
        return $errors;
    }

    /**
     * Update test status
     * 
     * @param string $testId
     * @param string $status
     */
    private function updateTestStatus(string $testId, string $status): void
    {
        $sql = "UPDATE dot_tests SET status = :status, updated_at = :updated_at WHERE test_id = :test_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'test_id' => $testId,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Generate CCF number
     * 
     * @return string
     */
    private function generateCcfNumber(): string
    {
        // Format: CCF-YYYYMMDD-XXXXX
        return 'CCF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    /**
     * Create result notification record
     * 
     * @param string $testId
     * @param array $test
     * @param string $result
     */
    private function createResultNotification(string $testId, array $test, string $result): void
    {
        try {
            $sql = "INSERT INTO dot_test_notifications (
                        notification_id, test_id, employer_id,
                        result, created_at, sent_at
                    ) VALUES (
                        :notification_id, :test_id, :employer_id,
                        :result, :created_at, NULL
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'notification_id' => $this->generateUuid(),
                'test_id' => $testId,
                'employer_id' => $test['employer_id'],
                'result' => $result,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Log but don't fail
            $this->logError('createResultNotification', $e, ['test_id' => $testId]);
        }
    }
}
