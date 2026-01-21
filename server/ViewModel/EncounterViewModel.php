<?php

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\EncounterRepository;
use Model\Repositories\PatientRepository;
use Model\Entities\Encounter;
use ViewModel\Core\ApiResponse;
use Core\Services\EHRLogger;
use PDO;
use DateTimeImmutable;

// Ensure EHRLogger is available
require_once __DIR__ . '/../core/Services/EHRLogger.php';

/**
 * Encounter ViewModel
 *
 * Coordinates between the View (API) and Model (Repository/Entity) layers.
 * Handles business logic for clinical encounter operations.
 *
 * @package ViewModel
 */
class EncounterViewModel
{
    private EncounterRepository $encounterRepository;
    private PatientRepository $patientRepository;
    private ?string $currentUserId = null;
    private ?string $currentClinicId = null;
    private EHRLogger $ehrLogger;

    public function __construct(PDO $pdo)
    {
        $this->encounterRepository = new EncounterRepository($pdo);
        $this->patientRepository = new PatientRepository($pdo);
        $this->ehrLogger = EHRLogger::getInstance();
    }

    /**
     * Set the current user context
     */
    public function setCurrentUser(string $userId): self
    {
        $this->currentUserId = $userId;
        return $this;
    }

    /**
     * Set the current clinic context
     */
    public function setCurrentClinic(string $clinicId): self
    {
        $this->currentClinicId = $clinicId;
        return $this;
    }

    /**
     * Get encounters with filtering and pagination
     */
    public function getEncounters(
        array $filters = [],
        int $page = 1,
        int $perPage = 50
    ): array {
        try {
            $patientId = $filters['patient_id'] ?? $filters['patientId'] ?? null;
            $providerId = $filters['provider_id'] ?? $filters['providerId'] ?? null;
            $clinicId = $filters['clinic_id'] ?? $filters['clinicId'] ?? $this->currentClinicId;
            $status = $filters['status'] ?? null;
            $encounterType = $filters['encounter_type'] ?? $filters['encounterType'] ?? null;
            $startDate = $filters['start_date'] ?? $filters['startDate'] ?? null;
            $endDate = $filters['end_date'] ?? $filters['endDate'] ?? null;
            $sortBy = $filters['sort_by'] ?? $filters['sortBy'] ?? 'encounter_date';
            $sortOrder = $filters['sort_order'] ?? $filters['sortOrder'] ?? 'DESC';

            $offset = ($page - 1) * $perPage;

            // Get encounters
            $encounters = $this->encounterRepository->search(
                $patientId,
                $providerId,
                $clinicId,
                $status,
                $encounterType,
                $startDate,
                $endDate,
                $perPage,
                $offset,
                $sortBy,
                $sortOrder
            );

            // Get total count
            $totalCount = $this->encounterRepository->countWithFilters(
                $patientId,
                $providerId,
                $clinicId,
                $status,
                $encounterType,
                $startDate,
                $endDate
            );

            // Convert to array format
            $encounterData = array_map(
                fn(Encounter $e) => $this->formatEncounterForList($e),
                $encounters
            );

            return ApiResponse::success([
                'encounters' => $encounterData,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $perPage),
                ],
                'filters' => $filters,
            ]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::getEncounters error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve encounters', $e);
        }
    }

    /**
     * Get a single encounter by ID
     */
    public function getEncounter(string $encounterId): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);

            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }

            // Get patient info for context
            $patient = $this->patientRepository->findById($encounter->getPatientId());

            return ApiResponse::success([
                'encounter' => $this->formatEncounterForDetail($encounter),
                'patient' => $patient ? [
                    'id' => $patient->getId(),
                    'first_name' => $patient->getFirstName(),
                    'last_name' => $patient->getLastName(),
                    'full_name' => $patient->getFullName(),
                    'date_of_birth' => $patient->getDateOfBirth()->format('Y-m-d'),
                    'mrn' => $patient->getMrn(),
                ] : null,
            ]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::getEncounter error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve encounter', $e);
        }
    }

    /**
     * Create a new encounter
     */
    public function createEncounter(array $data): array
    {
        $startTime = microtime(true);
        
        try {
            // Validate required fields
            $errors = $this->validateEncounterData($data, true);
            if (!empty($errors)) {
                $this->ehrLogger->logError('CREATE_ENCOUNTER', 'Validation failed', [
                    'channel' => EHRLogger::CHANNEL_ENCOUNTER,
                    'validation_errors' => $errors,
                ]);
                return ApiResponse::validationError($errors);
            }

            // Get or create patient
            $patientId = $data['patient_id'] ?? $data['patientId'];
            $patient = $this->findOrCreatePatient($patientId, $data);
            if (!$patient) {
                return ApiResponse::serverError('Failed to find or create patient');
            }
            // Update patient_id in data to use the real UUID
            $patientId = $patient->getId();
            $data['patient_id'] = $patientId;
            $data['patientId'] = $patientId;

            // Create encounter entity
            $encounter = $this->hydrateEncounterFromData($data);

            // Set clinic if available
            if ($this->currentClinicId && !$encounter->getClinicId()) {
                $encounter->setClinicId($this->currentClinicId);
            }

            // Set provider if not set
            if ($this->currentUserId && !$encounter->getProviderId()) {
                $encounter->setProviderId($this->currentUserId);
            }

            // Save to database
            $success = $this->encounterRepository->save($encounter);

            if ($success) {
                // Log successful creation
                $this->ehrLogger->logEncounterCreated(
                    $encounter->getId(),
                    $patientId,
                    [
                        'encounter_type' => $encounter->getEncounterType(),
                        'provider_id' => $encounter->getProviderId(),
                        'clinic_id' => $encounter->getClinicId(),
                    ]
                );
                
                // Log PHI access
                $this->ehrLogger->logPHIAccess(
                    (int)($this->currentUserId ?? 0),
                    $patientId,
                    'create_encounter',
                    ['encounter_id', 'patient_demographics']
                );
                
                return ApiResponse::success([
                    'encounter' => $this->formatEncounterForDetail($encounter),
                    'message' => 'Encounter created successfully',
                ], 'Encounter created successfully');
            }

            $this->ehrLogger->logError('CREATE_ENCOUNTER', 'Database save failed', [
                'channel' => EHRLogger::CHANNEL_ENCOUNTER,
            ]);
            return ApiResponse::serverError('Failed to create encounter');
        } catch (\Exception $e) {
            error_log("EncounterViewModel::createEncounter error: " . $e->getMessage());
            $this->ehrLogger->logError('CREATE_ENCOUNTER', $e->getMessage(), [
                'channel' => EHRLogger::CHANNEL_ENCOUNTER,
            ]);
            return ApiResponse::serverError('Failed to create encounter: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Update an existing encounter
     */
    public function updateEncounter(string $encounterId, array $data): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }

            // Check if locked
            if ($encounter->isLocked() && !($data['is_amendment'] ?? false)) {
                return ApiResponse::forbidden('Cannot modify locked encounter. Use amendment process.');
            }

            // Validate data
            $errors = $this->validateEncounterData($data, false);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }

            // Update encounter
            $encounter = $this->updateEncounterFromData($encounter, $data);

            // Save to database
            $success = $this->encounterRepository->save($encounter);

            if ($success) {
                return ApiResponse::success([
                    'encounter' => $this->formatEncounterForDetail($encounter),
                    'message' => 'Encounter updated successfully',
                ]);
            }

            return ApiResponse::serverError('Failed to update encounter');
        } catch (\Exception $e) {
            error_log("EncounterViewModel::updateEncounter error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to update encounter', $e);
        }
    }

    /**
     * Record vitals for an encounter
     */
    public function recordVitals(string $encounterId, array $vitals): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                return ApiResponse::notFound('Encounter not found');
            }

            if ($encounter->isLocked()) {
                return ApiResponse::forbidden('Cannot modify locked encounter');
            }

            // Merge with existing vitals
            $existingVitals = $encounter->getVitals();
            $newVitals = array_merge($existingVitals, $vitals);
            $encounter->setVitals($newVitals);

            $success = $this->encounterRepository->save($encounter);

            if ($success) {
                return ApiResponse::success([
                    'vitals' => $encounter->getVitals(),
                    'message' => 'Vitals recorded successfully',
                ]);
            }

            return ApiResponse::serverError('Failed to record vitals');
        } catch (\Exception $e) {
            error_log("EncounterViewModel::recordVitals error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to record vitals', $e);
        }
    }

    /**
     * Add vitals to an encounter (POST endpoint)
     */
    public function addVitals(string $encounterId, array $vitals): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                return array_merge(ApiResponse::error('Encounter not found'), ['status' => 404]);
            }

            if ($encounter->isLocked()) {
                return array_merge(ApiResponse::error('Cannot modify locked encounter'), ['status' => 403]);
            }

            // Validate vital signs
            $validVitalKeys = [
                'blood_pressure_systolic', 'blood_pressure_diastolic', 'heart_rate',
                'respiratory_rate', 'temperature', 'temperature_unit', 'oxygen_saturation',
                'weight', 'weight_unit', 'height', 'height_unit', 'bmi', 'pain_level',
                'recorded_at', 'recorded_by'
            ];

            $filteredVitals = array_intersect_key($vitals, array_flip($validVitalKeys));
            
            // Add timestamp if not provided
            if (empty($filteredVitals['recorded_at'])) {
                $filteredVitals['recorded_at'] = date('Y-m-d H:i:s');
            }
            if ($this->currentUserId && empty($filteredVitals['recorded_by'])) {
                $filteredVitals['recorded_by'] = $this->currentUserId;
            }

            // Merge with existing vitals
            $existingVitals = $encounter->getVitals();
            $newVitals = array_merge($existingVitals, $filteredVitals);
            $encounter->setVitals($newVitals);

            $success = $this->encounterRepository->save($encounter);

            if ($success) {
                return array_merge(ApiResponse::success([
                    'vitals' => $encounter->getVitals(),
                    'encounter_id' => $encounterId,
                ], 'Vitals added successfully'), ['status' => 201]);
            }

            return array_merge(ApiResponse::error('Failed to add vitals'), ['status' => 500]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::addVitals error: " . $e->getMessage());
            return array_merge(ApiResponse::error('Failed to add vitals'), ['status' => 500]);
        }
    }

    /**
     * Add assessment to an encounter
     */
    public function addAssessment(string $encounterId, array $data): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                return array_merge(ApiResponse::error('Encounter not found'), ['status' => 404]);
            }

            if ($encounter->isLocked()) {
                return array_merge(ApiResponse::error('Cannot modify locked encounter'), ['status' => 403]);
            }

            // Extract assessment data
            $assessment = $data['assessment'] ?? $data['diagnosis'] ?? $data['clinical_impression'] ?? null;
            $icdCodes = $data['icd_codes'] ?? $data['icdCodes'] ?? [];

            if (empty($assessment) && empty($icdCodes)) {
                return array_merge(ApiResponse::error('Assessment or ICD codes required'), ['status' => 400]);
            }

            // Update encounter
            if (!empty($assessment)) {
                $encounter->setAssessment($assessment);
            }
            
            if (!empty($icdCodes)) {
                // Merge with existing ICD codes
                $existingCodes = $encounter->getIcdCodes();
                $mergedCodes = array_unique(array_merge($existingCodes, $icdCodes));
                $encounter->setIcdCodes($mergedCodes);
            }

            $success = $this->encounterRepository->save($encounter);

            if ($success) {
                return array_merge(ApiResponse::success([
                    'assessment' => $encounter->getAssessment(),
                    'icd_codes' => $encounter->getIcdCodes(),
                    'encounter_id' => $encounterId,
                ], 'Assessment added successfully'), ['status' => 201]);
            }

            return array_merge(ApiResponse::error('Failed to add assessment'), ['status' => 500]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::addAssessment error: " . $e->getMessage());
            return array_merge(ApiResponse::error('Failed to add assessment'), ['status' => 500]);
        }
    }

    /**
     * Add treatment/plan to an encounter
     */
    public function addTreatment(string $encounterId, array $data): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                return array_merge(ApiResponse::error('Encounter not found'), ['status' => 404]);
            }

            if ($encounter->isLocked()) {
                return array_merge(ApiResponse::error('Cannot modify locked encounter'), ['status' => 403]);
            }

            // Extract treatment data
            $plan = $data['plan'] ?? $data['treatment_plan'] ?? $data['treatment'] ?? null;
            $cptCodes = $data['cpt_codes'] ?? $data['cptCodes'] ?? [];
            $medications = $data['medications'] ?? [];
            $procedures = $data['procedures'] ?? [];
            $followUp = $data['follow_up'] ?? $data['followUp'] ?? null;

            if (empty($plan) && empty($cptCodes) && empty($medications) && empty($procedures)) {
                return array_merge(ApiResponse::error('Treatment information required'), ['status' => 400]);
            }

            // Update encounter
            if (!empty($plan)) {
                $encounter->setPlan($plan);
            }
            
            if (!empty($cptCodes)) {
                // Merge with existing CPT codes
                $existingCodes = $encounter->getCptCodes();
                $mergedCodes = array_unique(array_merge($existingCodes, $cptCodes));
                $encounter->setCptCodes($mergedCodes);
            }

            // Store additional treatment data in clinical_data
            $clinicalData = $encounter->getClinicalData();
            if (!empty($medications)) {
                $clinicalData['medications'] = array_merge($clinicalData['medications'] ?? [], $medications);
            }
            if (!empty($procedures)) {
                $clinicalData['procedures'] = array_merge($clinicalData['procedures'] ?? [], $procedures);
            }
            if (!empty($followUp)) {
                $clinicalData['follow_up'] = $followUp;
            }
            $encounter->setClinicalData($clinicalData);

            $success = $this->encounterRepository->save($encounter);

            if ($success) {
                return array_merge(ApiResponse::success([
                    'plan' => $encounter->getPlan(),
                    'cpt_codes' => $encounter->getCptCodes(),
                    'clinical_data' => $encounter->getClinicalData(),
                    'encounter_id' => $encounterId,
                ], 'Treatment added successfully'), ['status' => 201]);
            }

            return array_merge(ApiResponse::error('Failed to add treatment'), ['status' => 500]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::addTreatment error: " . $e->getMessage());
            return array_merge(ApiResponse::error('Failed to add treatment'), ['status' => 500]);
        }
    }

    /**
     * Add signature to an encounter
     */
    public function addSignature(string $encounterId, array $data): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                return array_merge(ApiResponse::error('Encounter not found'), ['status' => 404]);
            }

            // Extract signature data
            $signatureType = $data['signature_type'] ?? $data['type'] ?? 'provider';
            $signatureData = $data['signature_data'] ?? $data['signature'] ?? null;
            $signedBy = $data['signed_by'] ?? $this->currentUserId;
            
            if (empty($signatureData) && empty($signedBy)) {
                return array_merge(ApiResponse::error('Signature information required'), ['status' => 400]);
            }

            // Valid signature types
            $validTypes = ['provider', 'patient', 'witness', 'supervising'];
            if (!in_array($signatureType, $validTypes)) {
                return array_merge(ApiResponse::error('Invalid signature type'), ['status' => 400]);
            }

            // Store signature in clinical data
            $clinicalData = $encounter->getClinicalData();
            $signatures = $clinicalData['signatures'] ?? [];
            
            $newSignature = [
                'type' => $signatureType,
                'signed_by' => $signedBy,
                'signed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ];
            
            if (!empty($signatureData)) {
                // Store base64 signature image if provided
                $newSignature['signature_data'] = $signatureData;
            }

            $signatures[] = $newSignature;
            $clinicalData['signatures'] = $signatures;
            $encounter->setClinicalData($clinicalData);

            // If this is a provider signature, also lock the encounter
            if ($signatureType === 'provider' && !$encounter->isLocked()) {
                $this->encounterRepository->lockEncounter($encounterId, $signedBy);
            }

            $success = $this->encounterRepository->save($encounter);

            if ($success) {
                // Reload to get updated lock status
                $encounter = $this->encounterRepository->findById($encounterId);
                
                return array_merge(ApiResponse::success([
                    'signature' => $newSignature,
                    'all_signatures' => $clinicalData['signatures'],
                    'is_locked' => $encounter->isLocked(),
                    'encounter_id' => $encounterId,
                ], 'Signature added successfully'), ['status' => 201]);
            }

            return array_merge(ApiResponse::error('Failed to add signature'), ['status' => 500]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::addSignature error: " . $e->getMessage());
            return array_merge(ApiResponse::error('Failed to add signature'), ['status' => 500]);
        }
    }

    /**
     * Sign/lock an encounter
     */
    public function signEncounter(string $encounterId): array
    {
        if (!$this->currentUserId) {
            $this->ehrLogger->logError('SIGN_ENCOUNTER', 'User not authenticated', [
                'encounter_id' => $encounterId,
                'channel' => EHRLogger::CHANNEL_SIGNATURE,
            ]);
            return ApiResponse::unauthorized('User not authenticated');
        }

        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                $this->ehrLogger->logError('SIGN_ENCOUNTER', 'Encounter not found', [
                    'encounter_id' => $encounterId,
                    'channel' => EHRLogger::CHANNEL_SIGNATURE,
                ]);
                return ApiResponse::notFound('Encounter not found');
            }

            if ($encounter->isLocked()) {
                $this->ehrLogger->logOperation(EHRLogger::OP_SIGN, [
                    'encounter_id' => $encounterId,
                    'details' => ['reason' => 'already_signed'],
                    'result' => 'failure',
                ], EHRLogger::CHANNEL_SIGNATURE);
                return ApiResponse::badRequest('Encounter is already signed');
            }

            // Validate encounter is complete enough to sign
            $validationErrors = $encounter->validate();
            if (!empty($validationErrors)) {
                $this->ehrLogger->logOperation(EHRLogger::OP_SIGN, [
                    'encounter_id' => $encounterId,
                    'details' => [
                        'reason' => 'validation_failed',
                        'error_count' => count($validationErrors),
                    ],
                    'result' => 'failure',
                ], EHRLogger::CHANNEL_SIGNATURE);
                return ApiResponse::validationError($validationErrors, 'Encounter cannot be signed');
            }

            // Lock the encounter
            $success = $this->encounterRepository->lockEncounter($encounterId, $this->currentUserId);

            if ($success) {
                // Reload the encounter
                $encounter = $this->encounterRepository->findById($encounterId);
                
                // Log successful signing
                $this->ehrLogger->logEncounterSigned($encounterId, (int)$this->currentUserId);
                
                return ApiResponse::success([
                    'encounter' => $this->formatEncounterForDetail($encounter),
                    'message' => 'Encounter signed successfully',
                ]);
            }

            $this->ehrLogger->logError('SIGN_ENCOUNTER', 'Database operation failed', [
                'encounter_id' => $encounterId,
                'channel' => EHRLogger::CHANNEL_SIGNATURE,
            ]);
            return ApiResponse::serverError('Failed to sign encounter');
        } catch (\Exception $e) {
            error_log("EncounterViewModel::signEncounter error: " . $e->getMessage());
            $this->ehrLogger->logError('SIGN_ENCOUNTER', $e->getMessage(), [
                'encounter_id' => $encounterId,
                'channel' => EHRLogger::CHANNEL_SIGNATURE,
            ]);
            return ApiResponse::serverError('Failed to sign encounter', $e);
        }
    }

    /**
     * Amend a signed encounter
     */
    public function amendEncounter(string $encounterId, array $data): array
    {
        if (!$this->currentUserId) {
            $this->ehrLogger->logError('AMEND_ENCOUNTER', 'User not authenticated', [
                'encounter_id' => $encounterId,
                'channel' => EHRLogger::CHANNEL_ENCOUNTER,
            ]);
            return ApiResponse::unauthorized('User not authenticated');
        }

        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                $this->ehrLogger->logError('AMEND_ENCOUNTER', 'Encounter not found', [
                    'encounter_id' => $encounterId,
                    'channel' => EHRLogger::CHANNEL_ENCOUNTER,
                ]);
                return ApiResponse::notFound('Encounter not found');
            }

            if (!$encounter->isLocked()) {
                $this->ehrLogger->logOperation(EHRLogger::OP_AMEND, [
                    'encounter_id' => $encounterId,
                    'details' => ['reason' => 'encounter_not_locked'],
                    'result' => 'failure',
                ], EHRLogger::CHANNEL_ENCOUNTER);
                return ApiResponse::badRequest('Only signed encounters can be amended');
            }

            $reason = $data['reason'] ?? $data['amendment_reason'] ?? null;
            if (empty($reason)) {
                $this->ehrLogger->logOperation(EHRLogger::OP_AMEND, [
                    'encounter_id' => $encounterId,
                    'details' => ['reason' => 'missing_amendment_reason'],
                    'result' => 'failure',
                ], EHRLogger::CHANNEL_ENCOUNTER);
                return ApiResponse::validationError(['reason' => 'Amendment reason is required']);
            }

            // Start amendment
            $this->encounterRepository->startAmendment($encounterId, $this->currentUserId, $reason);

            // Update the data
            $encounter = $this->encounterRepository->findById($encounterId);
            $encounter = $this->updateEncounterFromData($encounter, $data);
            
            $success = $this->encounterRepository->save($encounter);

            if ($success) {
                // Log successful amendment
                $this->ehrLogger->logEncounterAmended($encounterId, $reason, (int)$this->currentUserId);
                
                return ApiResponse::success([
                    'encounter' => $this->formatEncounterForDetail($encounter),
                    'message' => 'Encounter amended successfully',
                ]);
            }

            $this->ehrLogger->logError('AMEND_ENCOUNTER', 'Database operation failed', [
                'encounter_id' => $encounterId,
                'channel' => EHRLogger::CHANNEL_ENCOUNTER,
            ]);
            return ApiResponse::serverError('Failed to amend encounter');
        } catch (\Exception $e) {
            error_log("EncounterViewModel::amendEncounter error: " . $e->getMessage());
            $this->ehrLogger->logError('AMEND_ENCOUNTER', $e->getMessage(), [
                'encounter_id' => $encounterId,
                'channel' => EHRLogger::CHANNEL_ENCOUNTER,
            ]);
            return ApiResponse::serverError('Failed to amend encounter', $e);
        }
    }

    /**
     * Submit/finalize an encounter
     */
    public function submitEncounter(string $encounterId): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                // Wrap error logging in try-catch to prevent logging failures from crashing the response
                try {
                    $this->ehrLogger->logError('SUBMIT_ENCOUNTER', 'Encounter not found', [
                        'encounter_id' => $encounterId,
                        'channel' => EHRLogger::CHANNEL_ENCOUNTER,
                    ]);
                } catch (\Throwable $logError) {
                    error_log("EHRLogger failed (non-fatal): " . $logError->getMessage());
                }
                return ApiResponse::notFound('Encounter not found');
            }

            $previousStatus = $encounter->getStatus();
            
            // Update status to pending_review
            $success = $this->encounterRepository->updateStatus($encounterId, 'pending_review');

            if ($success) {
                $encounter = $this->encounterRepository->findById($encounterId);
                
                // Log status transition - wrapped in try-catch to prevent logging failures
                // from crashing the response after data has been successfully saved
                try {
                    $this->ehrLogger->logStatusTransition($encounterId, $previousStatus, 'pending_review', 'submitted_for_review');
                } catch (\Throwable $logError) {
                    // Log locally but don't crash the response - data was saved successfully
                    error_log("EHRLogger::logStatusTransition failed (non-fatal): " . $logError->getMessage());
                }
                
                return ApiResponse::success([
                    'encounter' => $this->formatEncounterForDetail($encounter),
                    'message' => 'Encounter submitted for review',
                ]);
            }

            // Wrap error logging in try-catch
            try {
                $this->ehrLogger->logError('SUBMIT_ENCOUNTER', 'Database operation failed', [
                    'encounter_id' => $encounterId,
                    'channel' => EHRLogger::CHANNEL_ENCOUNTER,
                ]);
            } catch (\Throwable $logError) {
                error_log("EHRLogger failed (non-fatal): " . $logError->getMessage());
            }
            return ApiResponse::serverError('Failed to submit encounter');
        } catch (\Exception $e) {
            error_log("EncounterViewModel::submitEncounter error: " . $e->getMessage());
            // Wrap error logging in try-catch to prevent logging failures from crashing
            try {
                $this->ehrLogger->logError('SUBMIT_ENCOUNTER', $e->getMessage(), [
                    'encounter_id' => $encounterId,
                    'channel' => EHRLogger::CHANNEL_ENCOUNTER,
                ]);
            } catch (\Throwable $logError) {
                error_log("EHRLogger failed (non-fatal): " . $logError->getMessage());
            }
            return ApiResponse::serverError('Failed to submit encounter', $e);
        }
    }

    /**
     * Delete/cancel an encounter
     */
    public function deleteEncounter(string $encounterId): array
    {
        try {
            $encounter = $this->encounterRepository->findById($encounterId);
            if (!$encounter) {
                $this->ehrLogger->logError('DELETE_ENCOUNTER', 'Encounter not found', [
                    'encounter_id' => $encounterId,
                    'channel' => EHRLogger::CHANNEL_ENCOUNTER,
                ]);
                return ApiResponse::notFound('Encounter not found');
            }

            if ($encounter->isLocked()) {
                $this->ehrLogger->logOperation(EHRLogger::OP_DELETE, [
                    'encounter_id' => $encounterId,
                    'details' => ['reason' => 'encounter_is_locked'],
                    'result' => 'failure',
                ], EHRLogger::CHANNEL_ENCOUNTER);
                return ApiResponse::forbidden('Cannot delete a signed encounter');
            }

            $success = $this->encounterRepository->delete($encounterId);

            if ($success) {
                // Log successful deletion
                $this->ehrLogger->logEncounterDeleted($encounterId, 'user_cancelled');
                
                return ApiResponse::success([
                    'message' => 'Encounter cancelled successfully',
                ]);
            }

            $this->ehrLogger->logError('DELETE_ENCOUNTER', 'Database operation failed', [
                'encounter_id' => $encounterId,
                'channel' => EHRLogger::CHANNEL_ENCOUNTER,
            ]);
            return ApiResponse::serverError('Failed to delete encounter');
        } catch (\Exception $e) {
            error_log("EncounterViewModel::deleteEncounter error: " . $e->getMessage());
            $this->ehrLogger->logError('DELETE_ENCOUNTER', $e->getMessage(), [
                'encounter_id' => $encounterId,
                'channel' => EHRLogger::CHANNEL_ENCOUNTER,
            ]);
            return ApiResponse::serverError('Failed to delete encounter', $e);
        }
    }

    /**
     * Get encounters for a specific patient
     */
    public function getPatientEncounters(string $patientId, int $limit = 50): array
    {
        try {
            $encounters = $this->encounterRepository->findByPatientId($patientId, $limit);

            $encounterData = array_map(
                fn(Encounter $e) => $this->formatEncounterForList($e),
                $encounters
            );

            return ApiResponse::success([
                'encounters' => $encounterData,
                'patient_id' => $patientId,
                'count' => count($encounterData),
            ]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::getPatientEncounters error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve patient encounters', $e);
        }
    }

    /**
     * Get today's encounters for a clinic
     */
    public function getTodaysEncounters(?string $clinicId = null): array
    {
        try {
            $clinicId = $clinicId ?? $this->currentClinicId;
            if (!$clinicId) {
                return ApiResponse::badRequest('Clinic ID is required');
            }

            $encounters = $this->encounterRepository->findTodaysEncounters($clinicId);

            $encounterData = array_map(
                fn(Encounter $e) => $this->formatEncounterForList($e),
                $encounters
            );

            return ApiResponse::success([
                'encounters' => $encounterData,
                'clinic_id' => $clinicId,
                'date' => date('Y-m-d'),
                'count' => count($encounterData),
            ]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::getTodaysEncounters error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve today\'s encounters', $e);
        }
    }

    /**
     * Get pending encounters for the current provider
     */
    public function getMyPendingEncounters(): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::unauthorized('User not authenticated');
        }

        try {
            $encounters = $this->encounterRepository->findPendingForProvider($this->currentUserId);

            $encounterData = array_map(
                fn(Encounter $e) => $this->formatEncounterForList($e),
                $encounters
            );

            return ApiResponse::success([
                'encounters' => $encounterData,
                'provider_id' => $this->currentUserId,
                'count' => count($encounterData),
            ]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::getMyPendingEncounters error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve pending encounters', $e);
        }
    }

    /**
     * Normalize encounter type to valid database enum value
     * Maps common aliases to valid types: 'ems', 'clinic', 'telemedicine', 'other'
     */
    private function normalizeEncounterType(?string $type): string
    {
        if (empty($type)) {
            return 'clinic'; // Default to clinic
        }
        
        // Map aliases to valid types
        $typeMap = [
            // Clinic aliases
            'clinical' => 'clinic',
            'office_visit' => 'clinic',
            'office' => 'clinic',
            'in_office' => 'clinic',
            'outpatient' => 'clinic',
            'ambulatory' => 'clinic',
            // EMS aliases
            'emergency' => 'ems',
            'ambulance' => 'ems',
            'field' => 'ems',
            '911' => 'ems',
            // Telemedicine aliases
            'telehealth' => 'telemedicine',
            'virtual' => 'telemedicine',
            'remote' => 'telemedicine',
            'video' => 'telemedicine',
            // Other
            'home' => 'other',
            'other' => 'other',
        ];
        
        $normalizedType = strtolower(trim($type));
        return $typeMap[$normalizedType] ?? $normalizedType;
    }
    
    /**
     * Find an existing patient by ID or create a new one from encounter data
     *
     * This handles the case where the frontend sends a temporary ID (timestamp-based)
     * and patient demographic data together.
     *
     * Note: The actual patients table schema uses:
     * - legal_first_name, legal_last_name (not first_name, last_name)
     * - dob (not date_of_birth)
     * - sex_assigned_at_birth enum('M','F','X','U')
     * - NO SSN columns exist in this schema
     *
     * @param string $patientId The patient ID from the request (may be UUID or temporary)
     * @param array $data The full encounter data including patient demographics
     * @return \Model\Entities\Patient|null The found or created patient, or null on failure
     */
    private function findOrCreatePatient(string $patientId, array $data): ?\Model\Entities\Patient
    {
        // 1. First try to find by ID directly (works for real UUIDs)
        $patient = $this->patientRepository->findById($patientId);
        if ($patient) {
            return $patient;
        }
        
        // 2. Extract patient data from request (supports both camelCase and snake_case)
        $firstName = $data['patient_first_name'] ?? $data['patientFirstName'] ?? null;
        $lastName = $data['patient_last_name'] ?? $data['patientLastName'] ?? null;
        $dob = $data['patient_dob'] ?? $data['patientDob'] ?? $data['date_of_birth'] ?? null;
        
        // Also check formData if present (frontend sends nested structure)
        $formData = $data['formData'] ?? [];
        $patientForm = $formData['patientForm'] ?? [];
        
        if (!$firstName && !empty($patientForm['firstName'])) {
            $firstName = $patientForm['firstName'];
        }
        if (!$lastName && !empty($patientForm['lastName'])) {
            $lastName = $patientForm['lastName'];
        }
        if (!$dob && !empty($patientForm['dob'])) {
            $dob = $patientForm['dob'];
        }
        
        // 3. Try to find existing patient by name + DOB (SSN columns don't exist in this schema)
        if ($firstName && $lastName && $dob) {
            $patient = $this->findPatientByNameAndDob($firstName, $lastName, $dob);
            if ($patient) {
                return $patient;
            }
        }
        
        // 4. If we have enough data, create a new patient directly
        if ($firstName && $lastName && $dob) {
            try {
                $patient = $this->createPatientDirect($firstName, $lastName, $dob, $data, $patientForm);
                
                if ($patient) {
                    $this->ehrLogger->logOperation('CREATE', [
                        'patient_id' => $patient->getId(),
                        'details' => [
                            'action' => 'patient_created_from_encounter',
                            'source' => 'encounter_form',
                        ],
                        'result' => 'success',
                    ], EHRLogger::CHANNEL_EHR);
                }
                
                return $patient;
            } catch (\Exception $e) {
                $this->ehrLogger->logError('CREATE_PATIENT', $e->getMessage(), [
                    'channel' => EHRLogger::CHANNEL_EHR,
                ]);
                error_log("Failed to create patient: " . $e->getMessage());
                return null;
            }
        }
        
        // 5. Not enough data to find or create patient
        return null;
    }
    
    /**
     * Find patient by name and DOB using actual database column names
     */
    private function findPatientByNameAndDob(string $firstName, string $lastName, string $dob): ?\Model\Entities\Patient
    {
        try {
            // Get PDO from repository (access via reflection or direct query)
            $pdo = $this->encounterRepository->getPdo();
            
            $sql = "SELECT patient_id FROM patients
                    WHERE legal_first_name = :first_name
                    AND legal_last_name = :last_name
                    AND dob = :dob
                    AND deleted_at IS NULL
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'dob' => $dob,
            ]);
            
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['patient_id'])) {
                return $this->patientRepository->findById($row['patient_id']);
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("findPatientByNameAndDob error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create patient directly using actual database column names
     *
     * Actual patients table schema:
     * - patient_id: char(36) UUID
     * - legal_first_name: varchar(100)
     * - legal_last_name: varchar(100)
     * - dob: date
     * - sex_assigned_at_birth: enum('M','F','X','U')
     * - phone: varchar(20)
     * - email: varchar(255)
     * - zip_code: char(10)
     */
    private function createPatientDirect(
        string $firstName,
        string $lastName,
        string $dob,
        array $data,
        array $patientForm
    ): ?\Model\Entities\Patient {
        $pdo = $this->encounterRepository->getPdo();
        
        // Generate UUID
        $patientId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
        
        // Map sex value to enum
        $sex = $patientForm['sex'] ?? $data['patient_sex'] ?? $data['patientSex'] ?? null;
        $sexMapped = 'U';
        if ($sex) {
            $sexLower = strtolower($sex);
            if (in_array($sexLower, ['m', 'male'])) {
                $sexMapped = 'M';
            } elseif (in_array($sexLower, ['f', 'female'])) {
                $sexMapped = 'F';
            } elseif ($sexLower === 'x') {
                $sexMapped = 'X';
            }
        }
        
        // Extract other fields
        $phone = $data['patient_phone'] ?? $data['patientPhone'] ?? $patientForm['phone'] ?? null;
        $email = $data['patient_email'] ?? $data['patientEmail'] ?? $patientForm['email'] ?? null;
        $zipCode = $patientForm['zipCode'] ?? $patientForm['zip'] ?? $data['patient_zip'] ?? null;
        
        // Get created_by from current user
        $createdBy = $this->currentUserId ? (int)$this->currentUserId : null;
        
        $sql = "INSERT INTO patients (
                    patient_id, legal_first_name, legal_last_name, dob,
                    sex_assigned_at_birth, phone, email, zip_code,
                    created_at, created_by
                ) VALUES (
                    :patient_id, :first_name, :last_name, :dob,
                    :sex, :phone, :email, :zip_code,
                    NOW(), :created_by
                )";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            'patient_id' => $patientId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'dob' => $dob,
            'sex' => $sexMapped,
            'phone' => $phone,
            'email' => $email,
            'zip_code' => $zipCode,
            'created_by' => $createdBy,
        ]);
        
        if ($success) {
            return $this->patientRepository->findById($patientId);
        }
        
        return null;
    }

    /**
     * Normalize status to valid database enum value
     * Maps common aliases to valid statuses: 'planned', 'arrived', 'in_progress', 'completed', 'cancelled', 'voided'
     */
    private function normalizeStatus(?string $status): string
    {
        if (empty($status)) {
            return 'planned'; // Default to planned
        }
        
        // Map aliases to valid statuses
        $statusMap = [
            // Planned aliases
            'draft' => 'in_progress',
            'scheduled' => 'planned',
            'pending' => 'planned',
            'new' => 'planned',
            // In progress aliases
            'active' => 'in_progress',
            'started' => 'in_progress',
            'open' => 'in_progress',
            'in-progress' => 'in_progress',
            // Completed aliases
            'done' => 'completed',
            'finished' => 'completed',
            'closed' => 'completed',
            'finalized' => 'completed',
            // Cancelled aliases
            'canceled' => 'cancelled',
            'aborted' => 'cancelled',
            // Voided aliases
            'void' => 'voided',
            'deleted' => 'voided',
        ];
        
        $normalizedStatus = strtolower(trim($status));
        return $statusMap[$normalizedStatus] ?? $normalizedStatus;
    }

    /**
     * Validate encounter data
     */
    private function validateEncounterData(array $data, bool $isCreate = true): array
    {
        $errors = [];

        if ($isCreate) {
            if (empty($data['patient_id']) && empty($data['patientId'])) {
                $errors['patient_id'] = 'Patient ID is required';
            }
        }

        // Normalize and validate encounter type if provided
        // Valid types from database: enum('ems','clinic','telemedicine','other')
        $type = $data['encounter_type'] ?? $data['encounterType'] ?? null;
        if ($type) {
            $normalizedType = $this->normalizeEncounterType($type);
            $validTypes = ['ems', 'clinic', 'telemedicine', 'other'];
            if (!in_array($normalizedType, $validTypes)) {
                $errors['encounter_type'] = 'Invalid encounter type. Must be one of: ' . implode(', ', $validTypes) . ' (received: ' . $type . ')';
            }
        }

        // Normalize and validate status if provided
        // Valid statuses from database: enum('planned','arrived','in_progress','completed','cancelled','voided')
        $status = $data['status'] ?? null;
        if ($status) {
            $normalizedStatus = $this->normalizeStatus($status);
            $validStatuses = ['planned', 'arrived', 'in_progress', 'completed', 'cancelled', 'voided'];
            if (!in_array($normalizedStatus, $validStatuses)) {
                $errors['status'] = 'Invalid status. Must be one of: ' . implode(', ', $validStatuses) . ' (received: ' . $status . ')';
            }
        }

        return $errors;
    }

    /**
     * Create Encounter entity from input data
     */
    private function hydrateEncounterFromData(array $data): Encounter
    {
        $patientId = $data['patient_id'] ?? $data['patientId'];
        $rawType = $data['encounter_type'] ?? $data['encounterType'] ?? 'clinic';
        $encounterType = $this->normalizeEncounterType($rawType);
        $providerId = $data['provider_id'] ?? $data['providerId'] ?? null;

        $encounter = new Encounter($patientId, $encounterType, $providerId);

        return $this->updateEncounterFromData($encounter, $data);
    }

    /**
     * Update Encounter entity from input data
     */
    private function updateEncounterFromData(Encounter $encounter, array $data): Encounter
    {
        // Skip clinical data updates if encounter is locked and not in amendment mode
        $isAmendment = $encounter->isBeingAmended();

        if (!$encounter->isLocked() || $isAmendment) {
            if (isset($data['clinic_id']) || isset($data['clinicId'])) {
                $encounter->setClinicId($data['clinic_id'] ?? $data['clinicId']);
            }
            if (isset($data['encounter_type']) || isset($data['encounterType'])) {
                $rawType = $data['encounter_type'] ?? $data['encounterType'];
                $encounter->setEncounterType($this->normalizeEncounterType($rawType));
            }
            if (isset($data['chief_complaint']) || isset($data['chiefComplaint'])) {
                $encounter->setChiefComplaint($data['chief_complaint'] ?? $data['chiefComplaint']);
            }
            if (isset($data['hpi'])) {
                $encounter->setHpi($data['hpi']);
            }
            if (isset($data['ros'])) {
                $encounter->setRos($data['ros']);
            }
            if (isset($data['physical_exam']) || isset($data['physicalExam'])) {
                $encounter->setPhysicalExam($data['physical_exam'] ?? $data['physicalExam']);
            }
            if (isset($data['assessment'])) {
                $encounter->setAssessment($data['assessment']);
            }
            if (isset($data['plan'])) {
                $encounter->setPlan($data['plan']);
            }
            if (isset($data['vitals'])) {
                $vitals = is_string($data['vitals']) ? json_decode($data['vitals'], true) : $data['vitals'];
                $encounter->setVitals($vitals ?? []);
            }
            if (isset($data['clinical_data']) || isset($data['clinicalData'])) {
                $clinicalData = $data['clinical_data'] ?? $data['clinicalData'];
                if (is_string($clinicalData)) {
                    $clinicalData = json_decode($clinicalData, true);
                }
                $encounter->setClinicalData($clinicalData ?? []);
            }
            if (isset($data['icd_codes']) || isset($data['icdCodes'])) {
                $codes = $data['icd_codes'] ?? $data['icdCodes'];
                $encounter->setIcdCodes(is_array($codes) ? $codes : []);
            }
            if (isset($data['cpt_codes']) || isset($data['cptCodes'])) {
                $codes = $data['cpt_codes'] ?? $data['cptCodes'];
                $encounter->setCptCodes(is_array($codes) ? $codes : []);
            }
            if (isset($data['encounter_date']) || isset($data['encounterDate'])) {
                $date = $data['encounter_date'] ?? $data['encounterDate'];
                $encounter->setEncounterDate(new DateTimeImmutable($date));
            }
        }

        // Status can always be updated
        if (isset($data['status'])) {
            $encounter->setStatus($data['status']);
        }

        return $encounter;
    }

    /**
     * Format encounter for list view
     */
    private function formatEncounterForList(Encounter $encounter): array
    {
        $data = $encounter->toSafeArray();
        
        return array_merge($data, [
            'id' => $data['encounter_id'],
            'encounterId' => $data['encounter_id'],
            'patientId' => $data['patient_id'],
            'encounterType' => $data['encounter_type'],
            'encounterDate' => $data['encounter_date'],
            'isLocked' => $data['is_locked'],
            'isAmended' => $data['is_amended'],
        ]);
    }

    /**
     * Format encounter for detail view
     */
    private function formatEncounterForDetail(Encounter $encounter): array
    {
        $data = $encounter->toArray();
        
        return array_merge($data, [
            'id' => $data['encounter_id'],
            'encounterId' => $data['encounter_id'],
            'patientId' => $data['patient_id'],
            'providerId' => $data['provider_id'],
            'clinicId' => $data['clinic_id'],
            'encounterType' => $data['encounter_type'],
            'chiefComplaint' => $data['chief_complaint'],
            'physicalExam' => $data['physical_exam'],
            'clinicalData' => $data['clinical_data'],
            'encounterDate' => $data['encounter_date'],
            'lockedAt' => $data['locked_at'],
            'lockedBy' => $data['locked_by'],
            'isLocked' => $data['is_locked'],
            'isAmended' => $data['is_amended'],
            'amendmentReason' => $data['amendment_reason'],
            'amendedAt' => $data['amended_at'],
            'amendedBy' => $data['amended_by'],
            'supervisingProviderId' => $data['supervising_provider_id'],
            'appointmentId' => $data['appointment_id'],
            'icdCodes' => $data['icd_codes'],
            'cptCodes' => $data['cpt_codes'],
            'serviceLocation' => $data['service_location'],
            'createdAt' => $data['created_at'],
            'updatedAt' => $data['updated_at'],
            'createdBy' => $data['created_by'],
        ]);
    }

    /**
     * Get draft (in_progress) encounters for the current authenticated user
     *
     * Returns saved draft reports that the user can resume editing.
     *
     * @return array API response with draft encounters
     */
    public function getDrafts(): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::unauthorized('User not authenticated');
        }

        try {
            // Get drafts from repository
            $drafts = $this->encounterRepository->getDraftsByUser($this->currentUserId, 10);

            // Format the response with patient display name logic
            $formattedDrafts = array_map(function ($draft) {
                // Use "New Patient" if patient_name is null or empty
                $patientDisplayName = !empty($draft['patient_name']) && trim($draft['patient_name']) !== ''
                    ? trim($draft['patient_name'])
                    : 'New Patient';

                return [
                    'encounter_id' => $draft['encounter_id'],
                    'patient_id' => $draft['patient_id'],
                    'patient_display_name' => $patientDisplayName,
                    'chief_complaint' => $draft['chief_complaint'],
                    'encounter_type' => $draft['encounter_type'],
                    'created_at' => $draft['created_at']
                        ? (new DateTimeImmutable($draft['created_at']))->format('Y-m-d\TH:i:s\Z')
                        : null,
                    'modified_at' => $draft['modified_at']
                        ? (new DateTimeImmutable($draft['modified_at']))->format('Y-m-d\TH:i:s\Z')
                        : null,
                ];
            }, $drafts);

            return ApiResponse::success([
                'drafts' => $formattedDrafts,
                'count' => count($formattedDrafts),
            ]);
        } catch (\Exception $e) {
            error_log("EncounterViewModel::getDrafts error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve draft encounters', $e);
        }
    }
}
