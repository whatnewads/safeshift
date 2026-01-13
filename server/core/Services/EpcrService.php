<?php
/**
 * EPCR Service
 * 
 * Handles business logic for EMS Electronic Patient Care Reports
 * Coordinates EPCR operations across multiple repositories
 */

namespace Core\Services;

use Core\Repositories\EPCRRepository;
use Core\Repositories\PatientRepository;
use Core\Repositories\EncounterRepository;
use Core\Repositories\ObservationRepository;
use Core\Validators\EPCRValidator;
use Core\Entities\EPCR;
use Core\Entities\Patient;
use Core\Entities\Encounter;
use PDO;
use Exception;

class EPCRService
{
    private EPCRRepository $epcrRepo;
    private PatientRepository $patientRepo;
    private EncounterRepository $encounterRepo;
    private ObservationRepository $observationRepo;
    private EPCRValidator $validator;
    private PDO $pdo;
    
    /**
     * Constructor
     */
    public function __construct(
        EPCRRepository $epcrRepo,
        PatientRepository $patientRepo,
        EncounterRepository $encounterRepo,
        ObservationRepository $observationRepo,
        EPCRValidator $validator,
        PDO $pdo
    ) {
        $this->epcrRepo = $epcrRepo;
        $this->patientRepo = $patientRepo;
        $this->encounterRepo = $encounterRepo;
        $this->observationRepo = $observationRepo;
        $this->validator = $validator;
        $this->pdo = $pdo;
    }
    
    /**
     * Save EPCR (draft or complete)
     */
    public function saveEPCR(array $data, string $userId): array
    {
        // Validate draft
        $errors = $this->validator->validateDraft($data);
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . json_encode($errors));
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            // 1. Create or update patient record
            $patientData = $this->extractPatientData($data);
            $patientId = $this->patientRepo->upsert($patientData, $userId);
            
            // 2. Create encounter record
            $encounterData = $this->extractEncounterData($data, $patientId);
            $encounter = new Encounter($encounterData);
            $encounter = $this->encounterRepo->create($encounter, $userId);
            $encounterId = $encounter->getEncounterId();
            
            // 3. Create or update EPCR record
            $epcr = $this->epcrRepo->findByEncounterId($encounterId);
            if ($epcr) {
                // Update existing EPCR
                $this->updateEPCRData($epcr, $data);
                $this->epcrRepo->update($epcr, $userId);
            } else {
                // Create new EPCR
                $epcrData = $this->extractEPCRData($data, $encounterId, $patientId);
                $epcr = new EPCR($epcrData);
                $epcr = $this->epcrRepo->create($epcr, $userId);
            }
            
            // 4. Create encounter response (EMS-specific)
            $this->epcrRepo->createEncounterResponse($encounterId, $data, $userId);
            
            // 5. Insert crew members (if any)
            if (!empty($data['crew_members'])) {
                $this->epcrRepo->insertCrewMembers($encounterId, $data['crew_members'], $userId);
            }
            
            // 6. Insert vitals
            if (!empty($data['vitals']) || $this->hasVitalData($data)) {
                $this->observationRepo->insertEpcrVitals($encounterId, $patientId, $data, $userId);
            }
            
            // 7. Handle signatures
            if (!empty($data['provider_signature'])) {
                $this->saveSignature($encounterId, $patientId, 'provider_signature', 
                                    $data['provider_signature'], $userId);
            }
            
            if (!empty($data['patient_signature'])) {
                $this->saveSignature($encounterId, $patientId, 'patient_consent',
                                    $data['patient_signature'], $userId);
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            return [
                'success' => true,
                'epcr_id' => $epcr->getEpcrId(),
                'encounter_id' => $encounterId,
                'patient_id' => $patientId,
                'message' => 'ePCR saved successfully'
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Submit EPCR
     */
    public function submitEPCR(string $epcrId, string $userId): array
    {
        $epcr = $this->epcrRepo->findById($epcrId);
        if (!$epcr) {
            throw new Exception('EPCR not found');
        }
        
        // Convert to array for validation
        $epcrData = $epcr->toArray();
        
        // Validate for submission
        $errors = $this->validator->validateSubmission($epcrData);
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . json_encode($errors));
        }
        
        // Submit the EPCR
        if ($this->epcrRepo->submit($epcrId, $userId)) {
            return [
                'success' => true,
                'message' => 'ePCR submitted successfully'
            ];
        } else {
            throw new Exception('Failed to submit ePCR');
        }
    }
    
    /**
     * Validate EPCR without saving
     */
    public function validateEPCR(array $data): array
    {
        $errors = $this->validator->validateSubmission($data);
        $completionPercentage = $this->validator->getCompletionPercentage($data);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'completion_percentage' => $completionPercentage
        ];
    }
    
    /**
     * Get EPCR by ID
     */
    public function getEPCR(string $epcrId): ?EPCR
    {
        return $this->epcrRepo->findById($epcrId);
    }
    
    /**
     * Get incomplete EPCRs for user
     */
    public function getIncompleteEPCRs(string $userId): array
    {
        return $this->epcrRepo->getIncomplete($userId);
    }
    
    /**
     * Lock EPCR after review
     */
    public function lockEPCR(string $epcrId, string $userId): bool
    {
        return $this->epcrRepo->lock($epcrId, $userId);
    }
    
    /**
     * Extract patient data from form
     */
    private function extractPatientData(array $data): array
    {
        return [
            'legal_first_name' => $data['patient_first_name'] ?? null,
            'legal_last_name' => $data['patient_last_name'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address_line_1' => $data['address_line_1'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip' => $data['zip'] ?? null,
            'ssn' => $data['ssn'] ?? null,
            'insurance_provider' => $data['insurance_provider'] ?? null,
            'insurance_policy_number' => $data['insurance_policy_number'] ?? null
        ];
    }
    
    /**
     * Extract encounter data from form
     */
    private function extractEncounterData(array $data, string $patientId): array
    {
        return [
            'patient_id' => $patientId,
            'encounter_type' => 'emergency',
            'status' => 'in-progress',
            'priority' => $data['response_priority'] ?? 'urgent',
            'chief_complaint' => $data['chief_complaint'] ?? null,
            'started_at' => $data['patient_contact_time'] ?? date('Y-m-d H:i:s'),
            'mechanism_of_injury' => $data['mechanism_of_injury'] ?? null,
            'injury_description' => $data['injury_description'] ?? null,
            'injury_location' => $data['incident_address'] ?? null
        ];
    }
    
    /**
     * Extract EPCR data from form
     */
    private function extractEPCRData(array $data, string $encounterId, string $patientId): array
    {
        return [
            'encounter_id' => $encounterId,
            'patient_id' => $patientId,
            'incident_number' => $data['incident_number'] ?? null,
            'unit_number' => $data['unit_number'] ?? null,
            'dispatch_time' => $data['dispatch_time'] ?? null,
            'enroute_time' => $data['enroute_time'] ?? null,
            'onscene_time' => $data['onscene_time'] ?? null,
            'patient_contact_time' => $data['patient_contact_time'] ?? null,
            'depart_scene_time' => $data['depart_scene_time'] ?? null,
            'arrive_destination_time' => $data['arrive_destination_time'] ?? null,
            'transfer_care_time' => $data['transfer_care_time'] ?? null,
            'cleared_time' => $data['cleared_time'] ?? null,
            'response_priority' => $data['response_priority'] ?? null,
            'transport_priority' => $data['transport_priority'] ?? null,
            'transport_disposition' => $data['transport_disposition'] ?? null,
            'destination_facility_id' => $data['destination_facility_id'] ?? null,
            'destination_facility_name' => $data['destination_facility_name'] ?? null,
            'chief_complaint' => $data['chief_complaint'] ?? null,
            'primary_impression' => $data['primary_impression'] ?? null,
            'secondary_impression' => $data['secondary_impression'] ?? null,
            'mechanism_of_injury' => $data['mechanism_of_injury'] ?? null,
            'location_type' => $data['location_type'] ?? null,
            'incident_address' => $data['incident_address'] ?? null,
            'incident_city' => $data['incident_city'] ?? null,
            'incident_state' => $data['incident_state'] ?? null,
            'incident_zip' => $data['incident_zip'] ?? null,
            'incident_county' => $data['incident_county'] ?? null,
            'narrative' => $data['narrative'] ?? null,
            'crew_members' => $data['crew_members'] ?? [],
            'vital_signs' => $data['vital_signs'] ?? [],
            'procedures' => $data['procedures'] ?? [],
            'medications' => $data['medications'] ?? []
        ];
    }
    
    /**
     * Update EPCR data from form
     */
    private function updateEPCRData(EPCR $epcr, array $data): void
    {
        // Update only provided fields
        $fields = [
            'incident_number', 'unit_number', 'dispatch_time', 'enroute_time',
            'onscene_time', 'patient_contact_time', 'depart_scene_time',
            'arrive_destination_time', 'transfer_care_time', 'cleared_time',
            'response_priority', 'transport_priority', 'transport_disposition',
            'destination_facility_name', 'chief_complaint', 'primary_impression',
            'secondary_impression', 'mechanism_of_injury', 'narrative'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $setter = 'set' . $this->toCamelCase($field);
                if (method_exists($epcr, $setter)) {
                    $epcr->$setter($data[$field]);
                }
            }
        }
    }
    
    /**
     * Check if form has vital data
     */
    private function hasVitalData(array $data): bool
    {
        $vitalFields = ['blood_pressure', 'pulse', 'respiration', 'temperature', 'spo2', 'pain_scale'];
        foreach ($vitalFields as $field) {
            if (!empty($data[$field])) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Save signature
     */
    private function saveSignature(string $encounterId, string $patientId, string $type, 
                                  string $signature, string $userId): void
    {
        // This would save the signature to a signature table
        // Implementation depends on signature storage strategy
    }
    
    /**
     * Convert snake_case to CamelCase
     */
    private function toCamelCase(string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }
}