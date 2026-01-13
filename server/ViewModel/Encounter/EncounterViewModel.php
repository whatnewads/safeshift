<?php
/**
 * EncounterViewModel - Business logic layer for encounter operations
 * 
 * Handles: Encounter CRUD, vitals recording, amendments, signing, submission
 * Security: HIPAA-compliant PHI access logging, encounter state enforcement
 * 
 * @package SafeShift\ViewModel\Encounter
 */

declare(strict_types=1);

namespace ViewModel\Encounter;

use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;
use Core\Repositories\EncounterRepository;
use Core\Repositories\PatientRepository;
use Core\Services\AuditService;
use Model\Validators\EncounterValidator;
use Core\Entities\Encounter;
use Exception;
use PDO;

/**
 * Encounter ViewModel
 * 
 * CRUD operations for clinical encounters with state management.
 * Enforces encounter workflow rules (draft -> in-progress -> completed -> signed).
 */
class EncounterViewModel extends BaseViewModel
{
    /** @var EncounterRepository Encounter repository */
    private EncounterRepository $encounterRepo;
    
    /** @var PatientRepository Patient repository */
    private PatientRepository $patientRepo;

    /** Encounter status constants */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in-progress';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_AMENDED = 'amended';
    public const STATUS_VOIDED = 'voided';

    /** Valid status transitions */
    private const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_IN_PROGRESS, self::STATUS_VOIDED],
        self::STATUS_IN_PROGRESS => [self::STATUS_PENDING_REVIEW, self::STATUS_COMPLETED, self::STATUS_VOIDED],
        self::STATUS_PENDING_REVIEW => [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_VOIDED],
        self::STATUS_COMPLETED => [self::STATUS_SIGNED, self::STATUS_IN_PROGRESS],
        self::STATUS_SIGNED => [self::STATUS_LOCKED, self::STATUS_AMENDED],
        self::STATUS_LOCKED => [self::STATUS_AMENDED],
        self::STATUS_AMENDED => [self::STATUS_LOCKED],
    ];

    /**
     * Constructor
     * 
     * @param EncounterRepository|null $encounterRepo
     * @param PatientRepository|null $patientRepo
     * @param AuditService|null $auditService
     * @param PDO|null $pdo
     */
    public function __construct(
        ?EncounterRepository $encounterRepo = null,
        ?PatientRepository $patientRepo = null,
        ?AuditService $auditService = null,
        ?PDO $pdo = null
    ) {
        parent::__construct($auditService, $pdo);
        
        $this->encounterRepo = $encounterRepo ?? new EncounterRepository($this->pdo);
        $this->patientRepo = $patientRepo ?? new PatientRepository($this->pdo);
    }

    /**
     * List encounters with pagination and filters
     * 
     * @param array $filters Optional filters (patient_id, status, date_range, etc.)
     * @return array API response
     */
    public function index(array $filters = []): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_encounters');
            
            [$page, $perPage] = $this->getPaginationParams($filters);
            
            $encounters = [];
            
            if (!empty($filters['patient_id'])) {
                $encounters = $this->encounterRepo->findByPatientId(
                    (string)$filters['patient_id'],
                    $perPage * 10
                );
            } elseif (!empty($filters['status'])) {
                $daysBack = (int)($filters['days_back'] ?? 7);
                $encounters = $this->encounterRepo->findByStatus(
                    (string)$filters['status'],
                    $daysBack,
                    $perPage * 10
                );
            } elseif (!empty($filters['employer_id'])) {
                $daysBack = (int)($filters['days_back'] ?? 30);
                $encounters = $this->encounterRepo->getWorkRelatedEncounters(
                    (string)$filters['employer_id'],
                    $daysBack
                );
            }
            
            // Convert to arrays
            $encounterData = array_map(function($encounter) {
                return $this->formatEncounterForList($encounter);
            }, $encounters);
            
            // Log access
            $this->audit('VIEW', 'encounter_list', null, [
                'count' => count($encounterData),
                'filters' => array_keys($filters)
            ]);
            
            // Paginate
            $result = $this->paginate($encounterData, $page, $perPage);
            
            return ApiResponse::success($result, 'Encounters retrieved successfully');
            
        } catch (Exception $e) {
            $this->logError('index', $e, ['filters' => $filters]);
            return $this->handleException($e, 'Failed to retrieve encounters');
        }
    }

    /**
     * Get single encounter by ID
     * 
     * @param string $id Encounter ID
     * @return array API response
     */
    public function show(string $id): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid encounter ID format']]);
            }
            
            $encounter = $this->encounterRepo->findById($id);
            
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }
            
            // Get patient info
            $patient = $this->patientRepo->findById($encounter->getPatientId());
            
            // Log PHI access
            $this->logPhiAccess('encounter', $id, 'view');
            
            // Format with patient info
            $data = $this->formatEncounterForDetail($encounter);
            
            if ($patient) {
                $data['patient'] = $patient->toSafeArray();
            }
            
            return ApiResponse::success($data, 'Encounter retrieved successfully');
            
        } catch (Exception $e) {
            $this->logError('show', $e, ['encounter_id' => $id]);
            return $this->handleException($e, 'Failed to retrieve encounter');
        }
    }

    /**
     * Create new encounter
     * 
     * @param array $data Encounter data
     * @return array API response
     */
    public function store(array $data): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            // Validate patient exists
            if (empty($data['patient_id'])) {
                return ApiResponse::validationError(['patient_id' => ['Patient ID is required']]);
            }
            
            $patient = $this->patientRepo->findById($data['patient_id']);
            if (!$patient) {
                return ApiResponse::notFound('Patient not found');
            }
            
            // Validate encounter data
            $errors = EncounterValidator::validateCreate($data);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            // Map input to encounter data
            $encounterData = $this->mapInputToEncounterData($data);
            $encounterData['status'] = self::STATUS_DRAFT;
            
            // Set provider if not specified
            if (empty($encounterData['provider_id'])) {
                $encounterData['provider_id'] = $this->getCurrentUserId();
            }
            
            // Create encounter entity
            $encounter = new Encounter($encounterData);
            $encounter = $this->encounterRepo->create($encounter, $this->getCurrentUserId());
            
            // Log audit event
            $this->audit('CREATE', 'encounter', $encounter->getEncounterId(), [
                'patient_id' => $data['patient_id'],
                'encounter_type' => $encounterData['encounter_type'] ?? 'general'
            ]);
            
            return ApiResponse::success(
                $this->formatEncounterForDetail($encounter),
                'Encounter created successfully'
            );
            
        } catch (Exception $e) {
            $this->logError('store', $e, ['data_keys' => array_keys($data)]);
            return $this->handleException($e, 'Failed to create encounter');
        }
    }

    /**
     * Update encounter
     * 
     * @param string $id Encounter ID
     * @param array $data Update data
     * @return array API response
     */
    public function update(string $id, array $data): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid encounter ID format']]);
            }
            
            $encounter = $this->encounterRepo->findById($id);
            
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }
            
            // Check if encounter can be edited
            if (!$this->canEditEncounter($encounter)) {
                return ApiResponse::forbidden('Encounter is locked and cannot be edited');
            }
            
            // Validate update data
            $errors = EncounterValidator::validateUpdate($data);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            // Map and apply updates
            $encounterData = $this->mapInputToEncounterData($data);
            
            // Apply updates to encounter
            $this->applyUpdatesToEncounter($encounter, $encounterData);
            
            // Save changes
            $success = $this->encounterRepo->update($encounter, $this->getCurrentUserId());
            
            if (!$success) {
                return ApiResponse::serverError('Failed to update encounter');
            }
            
            // Log PHI modification
            $this->audit('UPDATE', 'encounter', $id, [
                'fields_updated' => array_keys($data)
            ]);
            
            // Refresh and return
            $encounter = $this->encounterRepo->findById($id);
            
            return ApiResponse::success(
                $this->formatEncounterForDetail($encounter),
                'Encounter updated successfully'
            );
            
        } catch (Exception $e) {
            $this->logError('update', $e, ['encounter_id' => $id]);
            return $this->handleException($e, 'Failed to update encounter');
        }
    }

    /**
     * Get vitals for encounter
     * 
     * @param string $encounterId Encounter ID
     * @return array API response
     */
    public function getVitals(string $encounterId): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_encounters');
            
            if (!$this->isValidUuid($encounterId)) {
                return ApiResponse::validationError(['encounter_id' => ['Invalid encounter ID format']]);
            }
            
            $encounter = $this->encounterRepo->findById($encounterId);
            
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }
            
            // Get vitals from database
            $sql = "SELECT * FROM vitals WHERE encounter_id = :encounter_id ORDER BY recorded_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['encounter_id' => $encounterId]);
            $vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log PHI access
            $this->logPhiAccess('encounter_vitals', $encounterId, 'view');
            
            return ApiResponse::success([
                'encounter_id' => $encounterId,
                'vitals' => $vitals,
                'count' => count($vitals)
            ], 'Vitals retrieved successfully');
            
        } catch (Exception $e) {
            $this->logError('getVitals', $e, ['encounter_id' => $encounterId]);
            return $this->handleException($e, 'Failed to retrieve vitals');
        }
    }

    /**
     * Record vitals for encounter
     * 
     * @param string $encounterId Encounter ID
     * @param array $vitals Vitals data
     * @return array API response
     */
    public function recordVitals(string $encounterId, array $vitals): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            if (!$this->isValidUuid($encounterId)) {
                return ApiResponse::validationError(['encounter_id' => ['Invalid encounter ID format']]);
            }
            
            $encounter = $this->encounterRepo->findById($encounterId);
            
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }
            
            if (!$this->canEditEncounter($encounter)) {
                return ApiResponse::forbidden('Encounter is locked - cannot record vitals');
            }
            
            // Validate vitals
            $validatedVitals = $this->validateVitals($vitals);
            if (isset($validatedVitals['errors'])) {
                return ApiResponse::validationError($validatedVitals['errors']);
            }
            
            // Insert vitals record
            $vitalId = $this->generateUuid();
            $sql = "INSERT INTO vitals (
                        vital_id, encounter_id, blood_pressure_systolic, blood_pressure_diastolic,
                        heart_rate, respiratory_rate, temperature, oxygen_saturation,
                        weight, height, pain_level, recorded_at, recorded_by
                    ) VALUES (
                        :vital_id, :encounter_id, :bp_systolic, :bp_diastolic,
                        :heart_rate, :respiratory_rate, :temperature, :oxygen_saturation,
                        :weight, :height, :pain_level, :recorded_at, :recorded_by
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'vital_id' => $vitalId,
                'encounter_id' => $encounterId,
                'bp_systolic' => $validatedVitals['blood_pressure_systolic'] ?? null,
                'bp_diastolic' => $validatedVitals['blood_pressure_diastolic'] ?? null,
                'heart_rate' => $validatedVitals['heart_rate'] ?? null,
                'respiratory_rate' => $validatedVitals['respiratory_rate'] ?? null,
                'temperature' => $validatedVitals['temperature'] ?? null,
                'oxygen_saturation' => $validatedVitals['oxygen_saturation'] ?? null,
                'weight' => $validatedVitals['weight'] ?? null,
                'height' => $validatedVitals['height'] ?? null,
                'pain_level' => $validatedVitals['pain_level'] ?? null,
                'recorded_at' => date('Y-m-d H:i:s'),
                'recorded_by' => $this->getCurrentUserId()
            ]);
            
            // Log audit
            $this->audit('CREATE', 'vitals', $vitalId, [
                'encounter_id' => $encounterId
            ]);
            
            return ApiResponse::success([
                'vital_id' => $vitalId,
                'encounter_id' => $encounterId
            ], 'Vitals recorded successfully');
            
        } catch (Exception $e) {
            $this->logError('recordVitals', $e, ['encounter_id' => $encounterId]);
            return $this->handleException($e, 'Failed to record vitals');
        }
    }

    /**
     * Amend a signed encounter
     * 
     * @param string $id Encounter ID
     * @param array $amendment Amendment data
     * @return array API response
     */
    public function amend(string $id, array $amendment): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid encounter ID format']]);
            }
            
            $encounter = $this->encounterRepo->findById($id);
            
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }
            
            // Only signed/locked encounters can be amended
            $status = $encounter->getStatus();
            if (!in_array($status, [self::STATUS_SIGNED, self::STATUS_LOCKED])) {
                return ApiResponse::badRequest('Only signed or locked encounters can be amended');
            }
            
            // Validate amendment
            if (empty($amendment['reason'])) {
                return ApiResponse::validationError(['reason' => ['Amendment reason is required']]);
            }
            
            // Create amendment record
            $amendmentId = $this->generateUuid();
            $sql = "INSERT INTO encounter_amendments (
                        amendment_id, encounter_id, reason, changes,
                        amended_by, amended_at
                    ) VALUES (
                        :amendment_id, :encounter_id, :reason, :changes,
                        :amended_by, :amended_at
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'amendment_id' => $amendmentId,
                'encounter_id' => $id,
                'reason' => $amendment['reason'],
                'changes' => json_encode($amendment['changes'] ?? []),
                'amended_by' => $this->getCurrentUserId(),
                'amended_at' => date('Y-m-d H:i:s')
            ]);
            
            // Apply changes if provided
            if (!empty($amendment['changes'])) {
                $this->applyUpdatesToEncounter($encounter, $amendment['changes']);
            }
            
            // Update status
            $encounter->setStatus(self::STATUS_AMENDED);
            $this->encounterRepo->update($encounter, $this->getCurrentUserId());
            
            // Log audit
            $this->audit('AMEND', 'encounter', $id, [
                'amendment_id' => $amendmentId,
                'reason' => $amendment['reason']
            ]);
            
            return ApiResponse::success([
                'encounter_id' => $id,
                'amendment_id' => $amendmentId,
                'status' => self::STATUS_AMENDED
            ], 'Encounter amended successfully');
            
        } catch (Exception $e) {
            $this->logError('amend', $e, ['encounter_id' => $id]);
            return $this->handleException($e, 'Failed to amend encounter');
        }
    }

    /**
     * Sign encounter
     * 
     * @param string $id Encounter ID
     * @return array API response
     */
    public function sign(string $id): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('sign_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid encounter ID format']]);
            }
            
            $encounter = $this->encounterRepo->findById($id);
            
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }
            
            // Check if encounter can be signed
            if ($encounter->getStatus() !== self::STATUS_COMPLETED) {
                return ApiResponse::badRequest('Only completed encounters can be signed');
            }
            
            // Only provider or admin can sign
            $currentUserId = $this->getCurrentUserId();
            $providerId = $encounter->getProviderId();
            
            if ($providerId !== $currentUserId && !$this->hasPermission('sign_any_encounter')) {
                return ApiResponse::forbidden('You can only sign your own encounters');
            }
            
            // Create signature record
            $signatureId = $this->generateUuid();
            $sql = "INSERT INTO encounter_signatures (
                        signature_id, encounter_id, signed_by, signed_at,
                        signature_type
                    ) VALUES (
                        :signature_id, :encounter_id, :signed_by, :signed_at,
                        :signature_type
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'signature_id' => $signatureId,
                'encounter_id' => $id,
                'signed_by' => $currentUserId,
                'signed_at' => date('Y-m-d H:i:s'),
                'signature_type' => 'provider'
            ]);
            
            // Update encounter status
            $encounter->setStatus(self::STATUS_SIGNED);
            $this->encounterRepo->update($encounter, $currentUserId);
            
            // Log audit
            $this->audit('SIGN', 'encounter', $id, [
                'signature_id' => $signatureId
            ]);
            
            return ApiResponse::success([
                'encounter_id' => $id,
                'signature_id' => $signatureId,
                'status' => self::STATUS_SIGNED,
                'signed_at' => date('Y-m-d H:i:s')
            ], 'Encounter signed successfully');
            
        } catch (Exception $e) {
            $this->logError('sign', $e, ['encounter_id' => $id]);
            return $this->handleException($e, 'Failed to sign encounter');
        }
    }

    /**
     * Submit encounter for review
     * 
     * @param string $id Encounter ID
     * @return array API response
     */
    public function submit(string $id): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_encounters');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid encounter ID format']]);
            }
            
            $encounter = $this->encounterRepo->findById($id);
            
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }
            
            // Check valid transition
            $currentStatus = $encounter->getStatus();
            if (!in_array($currentStatus, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS])) {
                return ApiResponse::badRequest('Encounter cannot be submitted from current status');
            }
            
            // Validate encounter is complete enough to submit
            $validationErrors = $this->validateForSubmission($encounter);
            if (!empty($validationErrors)) {
                return ApiResponse::validationError($validationErrors);
            }
            
            // Update status
            $encounter->setStatus(self::STATUS_COMPLETED);
            $this->encounterRepo->update($encounter, $this->getCurrentUserId());
            
            // Log audit
            $this->audit('SUBMIT', 'encounter', $id);
            
            return ApiResponse::success([
                'encounter_id' => $id,
                'status' => self::STATUS_COMPLETED
            ], 'Encounter submitted successfully');
            
        } catch (Exception $e) {
            $this->logError('submit', $e, ['encounter_id' => $id]);
            return $this->handleException($e, 'Failed to submit encounter');
        }
    }

    /**
     * Check if encounter can be edited
     * 
     * @param Encounter $encounter
     * @return bool
     */
    private function canEditEncounter(Encounter $encounter): bool
    {
        $lockedStatuses = [
            self::STATUS_SIGNED,
            self::STATUS_LOCKED,
            self::STATUS_VOIDED
        ];
        
        return !in_array($encounter->getStatus(), $lockedStatuses);
    }

    /**
     * Format encounter for list display
     * 
     * @param Encounter $encounter
     * @return array
     */
    private function formatEncounterForList(Encounter $encounter): array
    {
        return [
            'encounter_id' => $encounter->getEncounterId(),
            'patient_id' => $encounter->getPatientId(),
            'encounter_type' => $encounter->getEncounterType(),
            'status' => $encounter->getStatus(),
            'chief_complaint' => $encounter->getChiefComplaint(),
            'started_at' => $encounter->getStartedAt(),
            'ended_at' => $encounter->getEndedAt(),
            'is_work_related' => $encounter->isWorkRelated(),
            'is_osha_recordable' => $encounter->isOshaRecordable(),
            'is_dot_related' => $encounter->isDotRelated(),
        ];
    }

    /**
     * Format encounter for detail display
     * 
     * @param Encounter $encounter
     * @return array
     */
    private function formatEncounterForDetail(Encounter $encounter): array
    {
        return $encounter->toArray();
    }

    /**
     * Map input to encounter data
     * 
     * @param array $data Input data
     * @return array Mapped data
     */
    private function mapInputToEncounterData(array $data): array
    {
        $mapped = [];
        
        $fieldMap = [
            'patient_id' => 'patient_id',
            'encounter_type' => 'encounter_type',
            'status' => 'status',
            'priority' => 'priority',
            'chief_complaint' => 'chief_complaint',
            'reason_for_visit' => 'reason_for_visit',
            'provider_id' => 'provider_id',
            'location_id' => 'location_id',
            'department_id' => 'department_id',
            'employer_id' => 'employer_id',
            'employer_name' => 'employer_name',
            'injury_date' => 'injury_date',
            'injury_time' => 'injury_time',
            'injury_location' => 'injury_location',
            'injury_description' => 'injury_description',
            'is_work_related' => 'is_work_related',
            'is_osha_recordable' => 'is_osha_recordable',
            'is_dot_related' => 'is_dot_related',
            'disposition' => 'disposition',
            'discharge_instructions' => 'discharge_instructions',
            'follow_up_date' => 'follow_up_date',
        ];
        
        foreach ($fieldMap as $inputKey => $repoKey) {
            if (isset($data[$inputKey])) {
                $mapped[$repoKey] = $data[$inputKey];
            }
        }
        
        return $mapped;
    }

    /**
     * Apply updates to encounter entity
     * 
     * @param Encounter $encounter
     * @param array $data Update data
     */
    private function applyUpdatesToEncounter(Encounter $encounter, array $data): void
    {
        foreach ($data as $key => $value) {
            $setter = 'set' . $this->toCamelCase($key);
            if (method_exists($encounter, $setter)) {
                $encounter->$setter($value);
            }
        }
    }

    /**
     * Validate vitals data
     * 
     * @param array $vitals Vitals input
     * @return array Validated vitals or errors
     */
    private function validateVitals(array $vitals): array
    {
        $errors = [];
        $validated = [];
        
        // Blood pressure
        if (isset($vitals['blood_pressure'])) {
            if (preg_match('/^(\d{2,3})\/(\d{2,3})$/', $vitals['blood_pressure'], $matches)) {
                $validated['blood_pressure_systolic'] = (int)$matches[1];
                $validated['blood_pressure_diastolic'] = (int)$matches[2];
            } else {
                $errors['blood_pressure'] = ['Invalid format. Use format: 120/80'];
            }
        } elseif (isset($vitals['blood_pressure_systolic']) && isset($vitals['blood_pressure_diastolic'])) {
            $validated['blood_pressure_systolic'] = (int)$vitals['blood_pressure_systolic'];
            $validated['blood_pressure_diastolic'] = (int)$vitals['blood_pressure_diastolic'];
        }
        
        // Numeric fields with ranges
        $numericFields = [
            'heart_rate' => [30, 250],
            'respiratory_rate' => [5, 60],
            'temperature' => [90, 110],
            'oxygen_saturation' => [50, 100],
            'weight' => [0, 1000],
            'height' => [0, 300],
            'pain_level' => [0, 10],
        ];
        
        foreach ($numericFields as $field => [$min, $max]) {
            if (isset($vitals[$field]) && $vitals[$field] !== '') {
                $value = (float)$vitals[$field];
                if ($value < $min || $value > $max) {
                    $errors[$field] = ["Value must be between $min and $max"];
                } else {
                    $validated[$field] = $value;
                }
            }
        }
        
        if (!empty($errors)) {
            return ['errors' => $errors];
        }
        
        return $validated;
    }

    /**
     * Validate encounter for submission
     * 
     * @param Encounter $encounter
     * @return array Validation errors
     */
    private function validateForSubmission(Encounter $encounter): array
    {
        $errors = [];
        
        if (empty($encounter->getChiefComplaint())) {
            $errors['chief_complaint'] = ['Chief complaint is required for submission'];
        }
        
        if (empty($encounter->getPatientId())) {
            $errors['patient_id'] = ['Patient is required'];
        }
        
        return $errors;
    }

    /**
     * Convert snake_case to CamelCase
     * 
     * @param string $str
     * @return string
     */
    private function toCamelCase(string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }
}
