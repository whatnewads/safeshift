<?php
/**
 * EMS ePCR Helper Functions (DEPRECATED)
 * 
 * @deprecated This file is deprecated. Use Core\Services\EPCRService instead.
 * This file now serves as a backward compatibility wrapper for legacy code.
 * 
 * Common functions for ePCR form processing
 */

namespace App\EPCR;

use App\db;
use Core\Services\EPCRService;
use Core\Repositories\EPCRRepository;
use Core\Repositories\PatientRepository;
use Core\Repositories\EncounterRepository;
use Core\Repositories\ObservationRepository;
use Core\Validators\EPCRValidator;
use PDO;
use Exception;

/**
 * Get EPCRService instance
 * 
 * @return EPCRService
 */
function getEPCRService() {
    static $service = null;
    
    if ($service === null) {
        $pdo = db::connect();
        $epcrRepo = new EPCRRepository($pdo);
        $patientRepo = new PatientRepository($pdo);
        $encounterRepo = new EncounterRepository($pdo);
        $observationRepo = new ObservationRepository($pdo);
        $validator = new EPCRValidator();
        
        $service = new EPCRService(
            $epcrRepo,
            $patientRepo,
            $encounterRepo,
            $observationRepo,
            $validator,
            $pdo
        );
    }
    
    return $service;
}

/**
 * Generate UUID v4
 * 
 * @deprecated Use Core\Repositories\BaseRepository::generateUuid() instead
 * @return string UUID in standard format
 */
function generateUUID() {
    trigger_error('generateUUID() is deprecated. Use Core\Repositories\BaseRepository::generateUuid() instead.', E_USER_DEPRECATED);
    
    // Generate 16 random bytes
    $data = random_bytes(16);
    
    // Set version (4) and variant bits
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
    // Format as UUID
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Map form gender to database sex field
 * 
 * @deprecated Use Core\Services\PatientService::mapGenderToSex() instead
 * @param string $gender Gender from form
 * @return string Database sex value
 */
function mapGenderToSex($gender) {
    trigger_error('mapGenderToSex() is deprecated. Use Core\Services\PatientService::mapGenderToSex() instead.', E_USER_DEPRECATED);
    
    $mapping = [
        'Male' => 'M',
        'Female' => 'F',
        'Other' => 'X',
        'Unknown' => 'U'
    ];
    return $mapping[$gender] ?? 'U';
}

/**
 * Parse patient name from "Last, First" format
 * 
 * @deprecated Use Core\Services\PatientService::parsePatientName() instead
 * @param string $fullName Full name in "Last, First" format
 * @return array ['first' => string, 'last' => string]
 */
function parsePatientName($fullName) {
    trigger_error('parsePatientName() is deprecated. Use Core\Services\PatientService::parsePatientName() instead.', E_USER_DEPRECATED);
    
    $parts = explode(',', $fullName, 2);
    return [
        'last' => trim($parts[0] ?? ''),
        'first' => trim($parts[1] ?? '')
    ];
}

/**
 * Parse blood pressure from "120/80" format
 * 
 * @deprecated Use Core\Services\VitalsService::parseBloodPressure() instead
 * @param string $bp Blood pressure string
 * @return array ['systolic' => int, 'diastolic' => int] or null
 */
function parseBloodPressure($bp) {
    trigger_error('parseBloodPressure() is deprecated. Use Core\Services\VitalsService::parseBloodPressure() instead.', E_USER_DEPRECATED);
    
    if (!$bp || !preg_match('/^(\d{2,3})\/(\d{2,3})$/', $bp, $matches)) {
        return null;
    }
    
    return [
        'systolic' => (int)$matches[1],
        'diastolic' => (int)$matches[2]
    ];
}

/**
 * Create or update patient record
 * 
 * @deprecated Use Core\Services\EPCRService::saveEPCR() instead
 * @param PDO $pdo Database connection
 * @param array $data Patient data
 * @param int $userId Current user ID
 * @return string Patient ID (UUID)
 */
function upsertPatient($pdo, $data, $userId) {
    trigger_error('upsertPatient() is deprecated. Use Core\Services\EPCRService::saveEPCR() instead.', E_USER_DEPRECATED);
    
    try {
        // Transform data to new format
        $transformedData = [
            'patient_id' => $data['patient_id'] ?? null,
            'patient_first_name' => null,
            'patient_last_name' => null,
            'date_of_birth' => $data['patient_dob'] ?? null,
            'gender' => null,
            'phone' => $data['patient_phone'] ?? null,
            'email' => $data['patient_email'] ?? null,
            'address_line_1' => $data['patient_address'] ?? null,
            'city' => $data['patient_city'] ?? null,
            'state' => $data['patient_state'] ?? null,
            'zip' => $data['patient_zip_code'] ?? null,
            'ssn' => null,
            'insurance_provider' => null,
            'insurance_policy_number' => null
        ];
        
        // Parse patient name if provided
        if (!empty($data['patient_name'])) {
            $name = parsePatientName($data['patient_name']);
            $transformedData['patient_first_name'] = $name['first'];
            $transformedData['patient_last_name'] = $name['last'];
        }
        
        // Map gender
        if (!empty($data['patient_gender'])) {
            $genderMap = [
                'M' => 'male',
                'F' => 'female', 
                'X' => 'other',
                'U' => 'unknown'
            ];
            $sex = mapGenderToSex($data['patient_gender']);
            $transformedData['gender'] = $genderMap[$sex] ?? 'unknown';
        }
        
        // Use patient repository directly for upsert
        $patientRepo = new PatientRepository($pdo);
        return $patientRepo->upsert($transformedData, $userId);
        
    } catch (Exception $e) {
        debugLog('upsertPatient error: ' . $e->getMessage(), $data, 'error');
        throw $e;
    }
}

/**
 * Create or update patient address
 *
 * @deprecated Use Core\Services\PatientService instead
 * @param PDO $pdo Database connection
 * @param string $patientId Patient UUID
 * @param array $data Address data
 * @param int $userId Current user ID
 */
function upsertPatientAddress($pdo, $patientId, $data, $userId) {
    trigger_error('upsertPatientAddress() is deprecated. Use Core\Services\PatientService instead.', E_USER_DEPRECATED);
    
    // This functionality is now handled within the PatientRepository
    // No direct replacement needed as it's called from upsertPatient
}

/**
 * Create encounter record
 * 
 * @deprecated Use Core\Services\EPCRService::saveEPCR() instead
 * @param PDO $pdo Database connection
 * @param string $patientId Patient UUID
 * @param array $data Encounter data
 * @param int $userId Current user ID
 * @return string Encounter ID (UUID)
 */
function createEncounter($pdo, $patientId, $data, $userId) {
    trigger_error('createEncounter() is deprecated. Use Core\Services\EPCRService::saveEPCR() instead.', E_USER_DEPRECATED);
    
    try {
        // Transform data to new format
        $epcrData = [
            'patient_id' => $patientId,
            'chief_complaint' => $data['chief_complaint'] ?? $data['complaint_details'] ?? null,
            'dispatch_time' => $data['dispatch_time'] ?? null,
            'patient_contact_time' => $data['patient_contact_time'] ?? null,
            'transport_disposition' => $data['transport_mode'] ?? null,
            'work_related' => $data['work_related'] ?? 'no'
        ];
        
        // Use EPCR service to create the encounter
        $service = getEPCRService();
        $result = $service->saveEPCR($epcrData, $userId);
        
        return $result['encounter_id'];
        
    } catch (Exception $e) {
        debugLog('createEncounter error: ' . $e->getMessage(), $data, 'error');
        throw $e;
    }
}

/**
 * Create EMS response record
 * 
 * @deprecated Use Core\Services\EPCRService::saveEPCR() instead
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter UUID
 * @param array $data Response data
 * @param int $userId Current user ID
 */
function createEncounterResponse($pdo, $encounterId, $data, $userId) {
    trigger_error('createEncounterResponse() is deprecated. Use Core\Services\EPCRService::saveEPCR() instead.', E_USER_DEPRECATED);
    
    // This is now handled within EPCRService::saveEPCR()
    // No direct action needed
}

/**
 * Insert crew members
 * 
 * @deprecated Use Core\Services\EPCRService::saveEPCR() instead
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter UUID
 * @param array $crewData Array of crew member data
 * @param int $userId Current user ID
 */
function insertCrewMembers($pdo, $encounterId, $crewData, $userId) {
    trigger_error('insertCrewMembers() is deprecated. Use Core\Services\EPCRService::saveEPCR() instead.', E_USER_DEPRECATED);
    
    try {
        $epcrRepo = new EPCRRepository($pdo);
        $epcrRepo->insertCrewMembers($encounterId, $crewData, $userId);
    } catch (Exception $e) {
        debugLog('insertCrewMembers error: ' . $e->getMessage(), $crewData, 'error');
        throw $e;
    }
}

/**
 * Insert vital signs
 * 
 * @deprecated Use Core\Services\EPCRService::saveEPCR() instead
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter UUID
 * @param string $patientId Patient UUID
 * @param array $data Vitals data
 * @param int $userId Current user ID
 */
function insertVitals($pdo, $encounterId, $patientId, $data, $userId) {
    trigger_error('insertVitals() is deprecated. Use Core\Services\EPCRService::saveEPCR() instead.', E_USER_DEPRECATED);
    
    try {
        $observationRepo = new ObservationRepository($pdo);
        $observationRepo->insertEpcrVitals($encounterId, $patientId, $data, $userId);
    } catch (Exception $e) {
        debugLog('insertVitals error: ' . $e->getMessage(), $data, 'error');
        throw $e;
    }
}

/**
 * Insert assessment observations
 * 
 * @deprecated Use Core\Services\EPCRService::saveEPCR() instead
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter UUID
 * @param string $patientId Patient UUID
 * @param array $data Assessment data
 * @param int $userId Current user ID
 */
function insertAssessments($pdo, $encounterId, $patientId, $data, $userId) {
    trigger_error('insertAssessments() is deprecated. Use Core\Services\EPCRService::saveEPCR() instead.', E_USER_DEPRECATED);
    
    // Transform assessment data to vitals format
    $vitalsData = [];
    
    $assessmentMappings = [
        'airway_status' => 'Airway',
        'breathing_status' => 'Breathing Quality',
        'circulation_status' => 'Circulation',
        'skin_condition' => 'Skin',
        'pupil_response' => 'Pupil Response',
        'mental_status' => 'Mental Status',
        'pain_scale' => 'Pain NRS'
    ];
    
    foreach ($assessmentMappings as $field => $label) {
        if (!empty($data[$field])) {
            $vitalsData[$field] = $data[$field];
        }
    }
    
    if (!empty($vitalsData)) {
        try {
            $observationRepo = new ObservationRepository($pdo);
            $observationRepo->insertEpcrVitals($encounterId, $patientId, $vitalsData, $userId);
        } catch (Exception $e) {
            debugLog('insertAssessments error: ' . $e->getMessage(), $data, 'error');
            throw $e;
        }
    }
}

/**
 * Insert medications administered
 * 
 * @deprecated Use Core\Services\EPCRService::saveEPCR() instead
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter UUID
 * @param string $patientId Patient UUID
 * @param array $medData Array of medication data
 * @param int $userId Current user ID
 */
function insertMedications($pdo, $encounterId, $patientId, $medData, $userId) {
    trigger_error('insertMedications() is deprecated. Use Core\Services\EPCRService::saveEPCR() instead.', E_USER_DEPRECATED);
    
    if (!is_array($medData) || empty($medData)) {
        return;
    }
    
    try {
        // Use direct SQL for now as there's no medication repository yet
        $stmt = $pdo->prepare("
            INSERT INTO encounter_med_admin (
                encounter_id, patient_id, medication_name, dose, route,
                given_at, given_by, response, notes, created_at, created_by
            ) VALUES (
                :encounter_id, :patient_id, :med_name, :dose, :route,
                :given_at, :given_by, :response, :notes, NOW(), :user_id
            )
        ");
        
        foreach ($medData as $med) {
            if (!empty($med['name'])) {
                $stmt->execute([
                    'encounter_id' => $encounterId,
                    'patient_id' => $patientId,
                    'med_name' => $med['name'],
                    'dose' => $med['dose'] ?? '',
                    'route' => $med['route'] ?? '',
                    'given_at' => $med['time'] ?? date('Y-m-d H:i:s'),
                    'given_by' => $userId,
                    'response' => $med['response'] ?? null,
                    'notes' => $med['notes'] ?? null,
                    'user_id' => $userId
                ]);
            }
        }
    } catch (Exception $e) {
        debugLog('insertMedications error: ' . $e->getMessage(), $medData, 'error');
        throw $e;
    }
}

/**
 * Save signature to consents table
 * 
 * @deprecated Use Core\Services\ConsentService instead
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter UUID
 * @param string $patientId Patient UUID
 * @param string $consentType Type of consent/signature
 * @param string $signatureData Base64 signature data
 * @param int $userId Current user ID
 */
function saveSignature($pdo, $encounterId, $patientId, $consentType, $signatureData, $userId) {
    trigger_error('saveSignature() is deprecated. Use Core\Services\ConsentService instead.', E_USER_DEPRECATED);
    
    try {
        // Generate unique filename
        $filename = $encounterId . '_' . $consentType . '_' . time() . '.png';
        $relativePath = 'storage/signatures/' . $filename;
        $fullPath = dirname(__DIR__) . '/' . $relativePath;
        
        // Create directory if it doesn't exist
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Save signature image
        $data = str_replace('data:image/png;base64,', '', $signatureData);
        $data = str_replace(' ', '+', $data);
        $imageData = base64_decode($data);
        
        if ($imageData === false || empty($imageData)) {
            throw new Exception('Invalid signature data');
        }
        
        if (!file_put_contents($fullPath, $imageData)) {
            throw new Exception('Failed to save signature file');
        }
        
        // Generate hash
        $hash = hash('sha256', $imageData);
        
        // Insert into consents table
        $stmt = $pdo->prepare("
            INSERT INTO consents (
                patient_id, consent_type, consent_status, signed_at,
                signed_via, document_hash, document_path,
                created_at, created_by
            ) VALUES (
                :patient_id, :consent_type, 'granted', NOW(),
                'electronic', :hash, :path,
                NOW(), :user_id
            )
        ");
        
        $stmt->execute([
            'patient_id' => $patientId,
            'consent_type' => $consentType,
            'hash' => $hash,
            'path' => '/' . $relativePath,
            'user_id' => $userId
        ]);
    } catch (Exception $e) {
        debugLog('saveSignature error: ' . $e->getMessage(), ['consent_type' => $consentType], 'error');
        throw $e;
    }
}

/**
 * Add debug log entry
 * 
 * @deprecated Use Core\Services\LogService instead
 * @param string $message Log message
 * @param mixed $data Additional data to log
 * @param string $level Log level (info, warning, error)
 */
function debugLog($message, $data = null, $level = 'info') {
    // Keep this implementation for backward compatibility
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s.u'),
        'level' => $level,
        'message' => $message,
        'data' => $data,
        'user_id' => $_SESSION['user_id'] ?? null,
        'request_id' => $_SERVER['REQUEST_TIME_FLOAT'] ?? null
    ];
    
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/epcr_debug_' . date('Y-m-d') . '.log';
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

?>