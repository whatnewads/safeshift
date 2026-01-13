<?php
/**
 * ClinicianViewModel - Business logic layer for clinician module
 *
 * Handles: clinical notes, ePCR forms, patient records, patient search
 * Security: CSRF validation, permission checks, input sanitization, PHI audit logging
 *
 * @package SafeShift\ViewModel
 */

namespace ViewModel;

use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;
use Core\Repositories\PatientRepository;
use Core\Repositories\EncounterRepository;
use Core\Services\AuditService;
use Exception;
use PDO;

class ClinicianViewModel extends BaseViewModel
{
    /**
     * @var array Allowed clinical roles for clinician module access
     */
    private const ALLOWED_ROLES = ['1clinician', 'dclinician', 'pclinician', 'cadmin', 'tadmin'];
    
    /** @var PatientRepository Patient repository */
    private PatientRepository $patientRepo;
    
    /** @var EncounterRepository Encounter repository */
    private EncounterRepository $encounterRepo;
    
    /**
     * Constructor with dependency injection
     *
     * @param PatientRepository|null $patientRepo
     * @param EncounterRepository|null $encounterRepo
     * @param AuditService|null $auditService
     * @param PDO|null $pdo
     * @param string|null $logPath Custom log path (defaults to LOG_PATH constant)
     */
    public function __construct(
        ?PatientRepository $patientRepo = null,
        ?EncounterRepository $encounterRepo = null,
        ?AuditService $auditService = null,
        ?PDO $pdo = null,
        ?string $logPath = null
    ) {
        parent::__construct($auditService, $pdo, $logPath);
        
        $this->patientRepo = $patientRepo ?? new PatientRepository($this->pdo);
        $this->encounterRepo = $encounterRepo ?? new EncounterRepository($this->pdo);
    }
    
    // ============================================
    // AUTHENTICATION & AUTHORIZATION METHODS
    // ============================================
    
    /**
     * Validate the current user session
     * 
     * @return bool True if session is valid
     */
    public function validateSession(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity'])) {
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;
            if ((time() - $_SESSION['last_activity']) > $timeout) {
                $this->logAuditEvent('session_timeout', [
                    'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
                ]);
                return false;
            }
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Check if current user has a specific permission
     * 
     * @param string $permission Permission to check
     * @return bool True if user has permission
     */
    public function checkPermission(string $permission): bool
    {
        if (!$this->validateSession()) {
            return false;
        }
        
        $permission = $this->sanitizeString($permission);
        $userRole = $_SESSION['user']['role'] ?? $_SESSION['user']['primary_role'] ?? null;
        
        if (!$userRole) {
            return false;
        }
        
        // Admin roles have full access
        if (in_array($userRole, ['tadmin', 'cadmin'])) {
            return true;
        }
        
        // Check role-based permissions
        $rolePermissions = $this->getRolePermissions($userRole);
        
        return in_array($permission, $rolePermissions);
    }
    
    /**
     * Validate a CSRF token
     * 
     * @param string $token Token to validate
     * @return bool True if token is valid
     */
    public function validateCSRFToken(string $token): bool
    {
        $token = $this->sanitizeString($token);
        $tokenName = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        
        if (!isset($_SESSION[$tokenName])) {
            $this->logAuditEvent('csrf_validation_failed', [
                'reason' => 'no_session_token'
            ]);
            return false;
        }
        
        $isValid = hash_equals($_SESSION[$tokenName], $token);
        
        if (!$isValid) {
            $this->logAuditEvent('csrf_validation_failed', [
                'reason' => 'token_mismatch',
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
        }
        
        return $isValid;
    }
    
    /**
     * Generate a new CSRF token
     * 
     * @return string The generated token
     */
    public function generateCSRFToken(): string
    {
        $tokenName = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        $token = bin2hex(random_bytes(32));
        $_SESSION[$tokenName] = $token;
        
        return $token;
    }
    
    // ============================================
    // CLINICAL NOTES METHODS (from clinical-notes.php)
    // ============================================
    
    /**
     * Get a clinical note by ID
     * 
     * @param int $noteId The note ID to retrieve
     * @return array|null Note data or null if not found
     */
    public function getClinicalNote(int $noteId): ?array
    {
        if (!$this->validateSession()) {
            return null;
        }
        
        if (!$this->checkPermission('view_clinical_notes')) {
            $this->logAuditEvent('unauthorized_access', [
                'action' => 'get_clinical_note',
                'note_id' => $noteId,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            return null;
        }
        
        try {
            // TODO: Replace with actual repository call when ClinicalNoteRepository is available
            // Example: return $this->clinicalNoteRepository->findById($noteId);
            
            $this->logAuditEvent('clinical_note_accessed', [
                'note_id' => $noteId,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            
            // Return mock data structure for development
            return [
                'note_id' => $noteId,
                'patient_id' => 0,
                'encounter_id' => 0,
                'subjective' => '',
                'objective' => '',
                'assessment' => '',
                'plan' => '',
                'provider_id' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'status' => 'draft',
                'signature_status' => 'unsigned'
            ];
            
        } catch (Exception $e) {
            $this->logError('getClinicalNote', $e, ['note_id' => $noteId]);
            return null;
        }
    }
    
    /**
     * Save a clinical note (create or update)
     * 
     * @param array $data Note data to save
     * @return array Result with success status, errors, and noteId
     */
    public function saveClinicalNote(array $data): array
    {
        if (!$this->validateSession()) {
            return ['success' => false, 'errors' => ['session' => 'Invalid session'], 'noteId' => null];
        }
        
        // Validate CSRF token if present
        if (isset($data['csrf_token']) && !$this->validateCSRFToken($data['csrf_token'])) {
            return ['success' => false, 'errors' => ['csrf' => 'Invalid security token'], 'noteId' => null];
        }
        
        if (!$this->checkPermission('edit_clinical_notes')) {
            $this->logAuditEvent('unauthorized_access', [
                'action' => 'save_clinical_note',
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            return ['success' => false, 'errors' => ['permission' => 'Insufficient permissions'], 'noteId' => null];
        }
        
        // Validate input data
        $validationErrors = $this->validateClinicalNoteData($data);
        if (!empty($validationErrors)) {
            return ['success' => false, 'errors' => $validationErrors, 'noteId' => null];
        }
        
        // Sanitize input
        $sanitizedData = $this->sanitizeClinicalNoteData($data);
        
        try {
            // TODO: Replace with actual repository call when ClinicalNoteRepository is available
            // Example: $noteId = $this->clinicalNoteRepository->save($sanitizedData);
            
            $noteId = $sanitizedData['note_id'] ?? random_int(1000, 9999);
            
            $this->logAuditEvent('clinical_note_saved', [
                'note_id' => $noteId,
                'patient_id' => $sanitizedData['patient_id'] ?? null,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown',
                'action' => isset($data['note_id']) ? 'update' : 'create'
            ]);
            
            return [
                'success' => true,
                'errors' => [],
                'noteId' => $noteId
            ];
            
        } catch (Exception $e) {
            $this->logError('saveClinicalNote', $e, ['patient_id' => $data['patient_id'] ?? null]);
            return ['success' => false, 'errors' => ['system' => 'Unable to save clinical note'], 'noteId' => null];
        }
    }
    
    /**
     * Validate clinical note data
     * 
     * @param array $data Data to validate
     * @return array Validation errors (empty if valid)
     */
    public function validateClinicalNoteData(array $data): array
    {
        $errors = [];
        
        // Patient ID is required
        if (empty($data['patient_id'])) {
            $errors['patient_id'] = 'Patient ID is required';
        } elseif (!is_numeric($data['patient_id']) || $data['patient_id'] < 1) {
            $errors['patient_id'] = 'Invalid patient ID';
        }
        
        // Encounter ID is required
        if (empty($data['encounter_id'])) {
            $errors['encounter_id'] = 'Encounter ID is required';
        } elseif (!is_numeric($data['encounter_id']) || $data['encounter_id'] < 1) {
            $errors['encounter_id'] = 'Invalid encounter ID';
        }
        
        // SOAP fields - at least one section should have content
        $soapFields = ['subjective', 'objective', 'assessment', 'plan'];
        $hasContent = false;
        foreach ($soapFields as $field) {
            if (!empty(trim($data[$field] ?? ''))) {
                $hasContent = true;
                break;
            }
        }
        
        if (!$hasContent) {
            $errors['content'] = 'At least one SOAP section must have content';
        }
        
        // Validate field lengths
        foreach ($soapFields as $field) {
            if (!empty($data[$field]) && strlen($data[$field]) > 65535) {
                $errors[$field] = ucfirst($field) . ' exceeds maximum length';
            }
        }
        
        return $errors;
    }
    
    /**
     * Format a clinical note for display
     * 
     * @param array $note Raw note data
     * @return array Formatted note data
     */
    public function formatClinicalNoteForDisplay(array $note): array
    {
        return [
            'note_id' => (int)($note['note_id'] ?? 0),
            'patient_id' => (int)($note['patient_id'] ?? 0),
            'encounter_id' => (int)($note['encounter_id'] ?? 0),
            'subjective' => htmlspecialchars($note['subjective'] ?? '', ENT_QUOTES, 'UTF-8'),
            'objective' => htmlspecialchars($note['objective'] ?? '', ENT_QUOTES, 'UTF-8'),
            'assessment' => htmlspecialchars($note['assessment'] ?? '', ENT_QUOTES, 'UTF-8'),
            'plan' => htmlspecialchars($note['plan'] ?? '', ENT_QUOTES, 'UTF-8'),
            'provider_name' => htmlspecialchars($note['provider_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'),
            'provider_id' => (int)($note['provider_id'] ?? 0),
            'created_at' => $this->formatDateTime($note['created_at'] ?? null),
            'updated_at' => $this->formatDateTime($note['updated_at'] ?? null),
            'status' => htmlspecialchars($note['status'] ?? 'draft', ENT_QUOTES, 'UTF-8'),
            'signature_status' => htmlspecialchars($note['signature_status'] ?? 'unsigned', ENT_QUOTES, 'UTF-8'),
            'is_editable' => ($note['status'] ?? 'draft') === 'draft',
            'is_signed' => ($note['signature_status'] ?? 'unsigned') === 'signed'
        ];
    }
    
    /**
     * Get patient sidebar data for clinical notes view
     *
     * @param int $patientId Patient ID
     * @return array Patient sidebar data
     */
    public function getPatientSidebarData(int $patientId): array
    {
        if (!$this->validateSession() || !$this->checkPermission('view_patient_records')) {
            return [];
        }
        
        try {
            // Fetch patient from repository
            $patient = $this->patientRepo->findById((string)$patientId);
            
            if (!$patient) {
                return [];
            }
            
            $this->logAuditEvent('patient_sidebar_accessed', [
                'patient_id' => $patientId,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            
            // Log PHI access via AuditService
            if ($this->auditService) {
                $this->auditService->log('VIEW', 'patient_sidebar', (string)$patientId);
            }
            
            $patientData = $patient->toSafeArray();
            
            // Get recent encounters for this patient to extract vitals
            $recentEncounters = $this->encounterRepo->findByPatientId((string)$patientId, 1, 5);
            $recentVitals = [];
            
            foreach ($recentEncounters as $encounter) {
                $encounterData = $encounter instanceof \Model\Entities\Encounter
                    ? $encounter->toArray()
                    : $encounter;
                if (!empty($encounterData['vitals'])) {
                    $recentVitals[] = $encounterData['vitals'];
                }
            }
            
            return [
                'patient_id' => $patientId,
                'name' => trim(($patientData['first_name'] ?? '') . ' ' . ($patientData['last_name'] ?? '')),
                'dob' => $patientData['date_of_birth'] ?? '',
                'age' => $this->calculateAge($patientData['date_of_birth'] ?? null),
                'gender' => $patientData['gender'] ?? '',
                'mrn' => $patientData['mrn'] ?? '',
                'allergies' => $patientData['allergies'] ?? [],
                'active_medications' => $patientData['medications'] ?? [],
                'active_problems' => $patientData['medical_history'] ?? [],
                'recent_vitals' => $recentVitals,
                'alerts' => $this->generatePatientAlerts($patientData)
            ];
            
        } catch (Exception $e) {
            $this->logError('getPatientSidebarData', $e, ['patient_id' => $patientId]);
            return [];
        }
    }
    
    /**
     * Generate patient alerts based on their data
     *
     * @param array $patientData Patient data
     * @return array List of alerts
     */
    private function generatePatientAlerts(array $patientData): array
    {
        $alerts = [];
        
        // Check for allergies
        if (!empty($patientData['allergies'])) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'Patient has documented allergies',
                'priority' => 'high'
            ];
        }
        
        // Check for missing emergency contact
        if (empty($patientData['emergency_contact_name'])) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'No emergency contact on file',
                'priority' => 'low'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Get available SOAP note templates
     * 
     * @return array Array of templates
     */
    public function getSOAPTemplates(): array
    {
        try {
            // TODO: Replace with actual repository call when TemplateRepository is available
            // Example: return $this->templateRepository->findByType('soap_note');
            
            return [
                [
                    'template_id' => 1,
                    'name' => 'General Visit',
                    'description' => 'Standard template for general clinic visits',
                    'subjective' => 'Chief Complaint:\n\nHistory of Present Illness:\n\nReview of Systems:\n',
                    'objective' => 'Vital Signs:\n\nPhysical Exam:\n',
                    'assessment' => 'Assessment:\n\nDiagnosis:\n',
                    'plan' => 'Plan:\n\nFollow-up:\n'
                ],
                [
                    'template_id' => 2,
                    'name' => 'Work Injury',
                    'description' => 'Template for occupational injury evaluations',
                    'subjective' => 'Mechanism of Injury:\n\nDate/Time of Injury:\n\nBody Part Affected:\n\nSymptoms:\n',
                    'objective' => 'Vital Signs:\n\nPhysical Exam:\n\nFunctional Assessment:\n',
                    'assessment' => 'Diagnosis:\n\nWork-Relatedness:\n\nOSHA Recordability:\n',
                    'plan' => 'Treatment:\n\nWork Restrictions:\n\nFollow-up:\n'
                ],
                [
                    'template_id' => 3,
                    'name' => 'DOT Physical',
                    'description' => 'Template for DOT physical examinations',
                    'subjective' => 'Medical History:\n\nCurrent Medications:\n\nOccupational History:\n',
                    'objective' => 'Vision:\nHearing:\nBlood Pressure:\nPulse:\nUrinalysis:\n\nPhysical Exam:\n',
                    'assessment' => 'Medical Certification Status:\n\nConditions Requiring Monitoring:\n',
                    'plan' => 'Certification Period:\n\nRestrictions:\n\nFollow-up Requirements:\n'
                ]
            ];
            
        } catch (Exception $e) {
            $this->logError('getSOAPTemplates', $e);
            return [];
        }
    }
    
    // ============================================
    // ePCR METHODS (from ems-epcr.php)
    // ============================================
    
    /**
     * Get an ePCR by ID
     * 
     * @param int $epcrId The ePCR ID to retrieve
     * @return array|null ePCR data or null if not found
     */
    public function getEPCR(int $epcrId): ?array
    {
        if (!$this->validateSession()) {
            return null;
        }
        
        if (!$this->checkPermission('view_epcr')) {
            $this->logAuditEvent('unauthorized_access', [
                'action' => 'get_epcr',
                'epcr_id' => $epcrId,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            return null;
        }
        
        try {
            // TODO: Replace with actual repository call when EPCRRepository is available
            // Example: return $this->epcrRepository->findById($epcrId);
            
            $this->logAuditEvent('epcr_accessed', [
                'epcr_id' => $epcrId,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            
            return [
                'epcr_id' => $epcrId,
                'encounter_id' => 0,
                'patient_id' => 0,
                'incident_number' => '',
                'unit_number' => '',
                'dispatch_time' => null,
                'en_route_time' => null,
                'on_scene_time' => null,
                'at_patient_time' => null,
                'transport_time' => null,
                'at_destination_time' => null,
                'in_service_time' => null,
                'chief_complaint' => '',
                'dispatch_reason' => '',
                'scene_address' => '',
                'transport_disposition' => '',
                'destination_facility' => '',
                'narrative' => '',
                'medications_administered' => [],
                'procedures_performed' => [],
                'vitals' => [],
                'crew_members' => [],
                'is_submitted' => false,
                'is_locked' => false,
                'status' => 'draft',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->logError('getEPCR', $e, ['epcr_id' => $epcrId]);
            return null;
        }
    }
    
    /**
     * Save an ePCR (create or update)
     * 
     * @param array $data ePCR data to save
     * @return array Result with success status, errors, and epcrId
     */
    public function saveEPCR(array $data): array
    {
        if (!$this->validateSession()) {
            return ['success' => false, 'errors' => ['session' => 'Invalid session'], 'epcrId' => null];
        }
        
        if (isset($data['csrf_token']) && !$this->validateCSRFToken($data['csrf_token'])) {
            return ['success' => false, 'errors' => ['csrf' => 'Invalid security token'], 'epcrId' => null];
        }
        
        if (!$this->checkPermission('edit_epcr')) {
            $this->logAuditEvent('unauthorized_access', [
                'action' => 'save_epcr',
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            return ['success' => false, 'errors' => ['permission' => 'Insufficient permissions'], 'epcrId' => null];
        }
        
        // Validate input data
        $validationErrors = $this->validateEPCRData($data);
        if (!empty($validationErrors)) {
            return ['success' => false, 'errors' => $validationErrors, 'epcrId' => null];
        }
        
        // Sanitize input
        $sanitizedData = $this->sanitizeEPCRData($data);
        
        try {
            // TODO: Replace with actual repository call when EPCRRepository is available
            // Example: $epcrId = $this->epcrRepository->save($sanitizedData);
            
            $epcrId = $sanitizedData['epcr_id'] ?? random_int(1000, 9999);
            
            $this->logAuditEvent('epcr_saved', [
                'epcr_id' => $epcrId,
                'patient_id' => $sanitizedData['patient_id'] ?? null,
                'incident_number' => $sanitizedData['incident_number'] ?? null,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown',
                'action' => isset($data['epcr_id']) ? 'update' : 'create'
            ]);
            
            return [
                'success' => true,
                'errors' => [],
                'epcrId' => $epcrId
            ];
            
        } catch (Exception $e) {
            $this->logError('saveEPCR', $e, ['patient_id' => $data['patient_id'] ?? null]);
            return ['success' => false, 'errors' => ['system' => 'Unable to save ePCR'], 'epcrId' => null];
        }
    }
    
    /**
     * Validate ePCR data
     * 
     * @param array $data Data to validate
     * @return array Validation errors (empty if valid)
     */
    public function validateEPCRData(array $data): array
    {
        $errors = [];
        
        // Validate incident number format if provided
        if (!empty($data['incident_number'])) {
            if (strlen($data['incident_number']) > 50) {
                $errors['incident_number'] = 'Incident number exceeds maximum length';
            }
        }
        
        // Validate unit number
        if (!empty($data['unit_number']) && strlen($data['unit_number']) > 20) {
            $errors['unit_number'] = 'Unit number exceeds maximum length';
        }
        
        // Validate blood pressure format if provided
        if (!empty($data['blood_pressure']) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $data['blood_pressure'])) {
            $errors['blood_pressure'] = 'Invalid blood pressure format. Use format: 120/80';
        }
        
        // Validate times are in proper sequence
        $timeFields = ['dispatch_time', 'en_route_time', 'on_scene_time', 'at_patient_time', 'transport_time', 'at_destination_time'];
        $previousTime = null;
        foreach ($timeFields as $field) {
            if (!empty($data[$field])) {
                $currentTime = strtotime($data[$field]);
                if ($currentTime === false) {
                    $errors[$field] = 'Invalid date/time format';
                } elseif ($previousTime !== null && $currentTime < $previousTime) {
                    $errors[$field] = 'Time must be after previous time in sequence';
                }
                $previousTime = $currentTime;
            }
        }
        
        // Validate narrative length
        if (!empty($data['narrative']) && strlen($data['narrative']) > 65535) {
            $errors['narrative'] = 'Narrative exceeds maximum length';
        }
        
        return $errors;
    }
    
    /**
     * Format an ePCR for display
     * 
     * @param array $epcr Raw ePCR data
     * @return array Formatted ePCR data
     */
    public function formatEPCRForDisplay(array $epcr): array
    {
        return [
            'epcr_id' => (int)($epcr['epcr_id'] ?? 0),
            'encounter_id' => (int)($epcr['encounter_id'] ?? 0),
            'patient_id' => (int)($epcr['patient_id'] ?? 0),
            'incident_number' => htmlspecialchars($epcr['incident_number'] ?? '', ENT_QUOTES, 'UTF-8'),
            'unit_number' => htmlspecialchars($epcr['unit_number'] ?? '', ENT_QUOTES, 'UTF-8'),
            'dispatch_time' => $this->formatDateTime($epcr['dispatch_time'] ?? null),
            'en_route_time' => $this->formatDateTime($epcr['en_route_time'] ?? null),
            'on_scene_time' => $this->formatDateTime($epcr['on_scene_time'] ?? null),
            'at_patient_time' => $this->formatDateTime($epcr['at_patient_time'] ?? null),
            'transport_time' => $this->formatDateTime($epcr['transport_time'] ?? null),
            'at_destination_time' => $this->formatDateTime($epcr['at_destination_time'] ?? null),
            'chief_complaint' => htmlspecialchars($epcr['chief_complaint'] ?? '', ENT_QUOTES, 'UTF-8'),
            'dispatch_reason' => htmlspecialchars($epcr['dispatch_reason'] ?? '', ENT_QUOTES, 'UTF-8'),
            'scene_address' => htmlspecialchars($epcr['scene_address'] ?? '', ENT_QUOTES, 'UTF-8'),
            'transport_disposition' => htmlspecialchars($epcr['transport_disposition'] ?? '', ENT_QUOTES, 'UTF-8'),
            'destination_facility' => htmlspecialchars($epcr['destination_facility'] ?? '', ENT_QUOTES, 'UTF-8'),
            'narrative' => htmlspecialchars($epcr['narrative'] ?? '', ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars($epcr['status'] ?? 'draft', ENT_QUOTES, 'UTF-8'),
            'is_submitted' => (bool)($epcr['is_submitted'] ?? false),
            'is_locked' => (bool)($epcr['is_locked'] ?? false),
            'created_at' => $this->formatDateTime($epcr['created_at'] ?? null),
            'updated_at' => $this->formatDateTime($epcr['updated_at'] ?? null),
            'is_editable' => !($epcr['is_locked'] ?? false)
        ];
    }
    
    /**
     * Log an audit event
     * 
     * @param string $action The action being logged
     * @param array $context Additional context data
     * @return void
     */
    public function logAuditEvent(string $action, array $context): void
    {
        try {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => $this->sanitizeString($action),
                'user_id' => $_SESSION['user']['user_id'] ?? 'anonymous',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $this->sanitizeString($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
                'context' => array_map([$this, 'sanitizeForLog'], $context)
            ];
            
            $logFile = $this->logPath . 'audit_' . date('Y-m-d') . '.log';
            $logLine = date('Y-m-d H:i:s') . ' | ' . json_encode($logEntry) . PHP_EOL;
            
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
            
        } catch (Exception $e) {
            error_log('Failed to write audit log: ' . $e->getMessage());
        }
    }
    
    // ============================================
    // PATIENT RECORDS METHODS (from patient-records.php)
    // ============================================
    
    /**
     * Get a patient record by ID
     *
     * @param int $patientId Patient ID
     * @return array|null Patient record or null if not found
     */
    public function getPatientRecord(int $patientId): ?array
    {
        if (!$this->validateSession()) {
            return null;
        }
        
        if (!$this->checkPermission('view_patient_records')) {
            $this->logAuditEvent('unauthorized_access', [
                'action' => 'get_patient_record',
                'patient_id' => $patientId,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            return null;
        }
        
        try {
            // Use PatientRepository to fetch actual patient data
            $patient = $this->patientRepo->findById((string)$patientId);
            
            if (!$patient) {
                return null;
            }
            
            // Log PHI access for HIPAA compliance
            $this->logAuditEvent('patient_record_accessed', [
                'patient_id' => $patientId,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            
            // Also log via AuditService if available
            if ($this->auditService) {
                $this->auditService->log('VIEW', 'patient', (string)$patientId);
            }
            
            // Return patient data with SSN masked for security
            return $patient->toSafeArray();
            
        } catch (Exception $e) {
            $this->logError('getPatientRecord', $e, ['patient_id' => $patientId]);
            return null;
        }
    }
    
    /**
     * Get patient encounters
     *
     * @param int $patientId Patient ID
     * @return array List of encounters
     */
    public function getPatientEncounters(int $patientId): array
    {
        if (!$this->validateSession() || !$this->checkPermission('view_patient_records')) {
            return [];
        }
        
        try {
            // Use EncounterRepository to fetch actual encounter data
            $encounters = $this->encounterRepo->findByPatientId((string)$patientId);
            
            $this->logAuditEvent('patient_encounters_accessed', [
                'patient_id' => $patientId,
                'encounter_count' => count($encounters),
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            
            // Log PHI access via AuditService
            if ($this->auditService) {
                $this->auditService->log('VIEW', 'patient_encounters', (string)$patientId);
            }
            
            // Convert encounter entities to arrays
            return array_map(function($encounter) {
                return $encounter instanceof \Model\Entities\Encounter
                    ? $encounter->toArray()
                    : $encounter;
            }, $encounters);
            
        } catch (Exception $e) {
            $this->logError('getPatientEncounters', $e, ['patient_id' => $patientId]);
            return [];
        }
    }
    
    /**
     * Get patient vitals
     * 
     * @param int $patientId Patient ID
     * @return array List of vital records
     */
    public function getPatientVitals(int $patientId): array
    {
        if (!$this->validateSession() || !$this->checkPermission('view_patient_records')) {
            return [];
        }
        
        try {
            // TODO: Replace with actual repository call when VitalsRepository is available
            // Example: return $this->vitalsRepository->findByPatientId($patientId);
            
            return [];
            
        } catch (Exception $e) {
            $this->logError('getPatientVitals', $e, ['patient_id' => $patientId]);
            return [];
        }
    }
    
    /**
     * Get patient medications
     * 
     * @param int $patientId Patient ID
     * @return array List of medications
     */
    public function getPatientMedications(int $patientId): array
    {
        if (!$this->validateSession() || !$this->checkPermission('view_patient_records')) {
            return [];
        }
        
        try {
            // TODO: Replace with actual repository call when MedicationRepository is available
            // Example: return $this->medicationRepository->findByPatientId($patientId);
            
            return [];
            
        } catch (Exception $e) {
            $this->logError('getPatientMedications', $e, ['patient_id' => $patientId]);
            return [];
        }
    }
    
    /**
     * Get patient allergies
     * 
     * @param int $patientId Patient ID
     * @return array List of allergies
     */
    public function getPatientAllergies(int $patientId): array
    {
        if (!$this->validateSession() || !$this->checkPermission('view_patient_records')) {
            return [];
        }
        
        try {
            // TODO: Replace with actual repository call when AllergyRepository is available
            // Example: return $this->allergyRepository->findByPatientId($patientId);
            
            return [];
            
        } catch (Exception $e) {
            $this->logError('getPatientAllergies', $e, ['patient_id' => $patientId]);
            return [];
        }
    }
    
    /**
     * Format a patient record for display
     * 
     * @param array $record Raw patient record
     * @return array Formatted patient record
     */
    public function formatPatientRecordForDisplay(array $record): array
    {
        return [
            'patient_id' => (int)($record['patient_id'] ?? 0),
            'mrn' => htmlspecialchars($record['mrn'] ?? '', ENT_QUOTES, 'UTF-8'),
            'full_name' => htmlspecialchars(
                trim(($record['first_name'] ?? '') . ' ' . ($record['middle_name'] ?? '') . ' ' . ($record['last_name'] ?? '')),
                ENT_QUOTES,
                'UTF-8'
            ),
            'first_name' => htmlspecialchars($record['first_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'last_name' => htmlspecialchars($record['last_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'middle_name' => htmlspecialchars($record['middle_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'date_of_birth' => $this->formatDate($record['date_of_birth'] ?? null),
            'age' => $this->calculateAge($record['date_of_birth'] ?? null),
            'gender' => htmlspecialchars($record['gender'] ?? '', ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($record['email'] ?? '', ENT_QUOTES, 'UTF-8'),
            'phone' => $this->formatPhoneNumber($record['phone'] ?? ''),
            'address' => htmlspecialchars($record['address'] ?? '', ENT_QUOTES, 'UTF-8'),
            'city' => htmlspecialchars($record['city'] ?? '', ENT_QUOTES, 'UTF-8'),
            'state' => htmlspecialchars($record['state'] ?? '', ENT_QUOTES, 'UTF-8'),
            'zip_code' => htmlspecialchars($record['zip_code'] ?? '', ENT_QUOTES, 'UTF-8'),
            'full_address' => $this->formatFullAddress($record),
            'employer_name' => htmlspecialchars($record['employer_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'job_title' => htmlspecialchars($record['job_title'] ?? '', ENT_QUOTES, 'UTF-8'),
            'department' => htmlspecialchars($record['department'] ?? '', ENT_QUOTES, 'UTF-8'),
            'emergency_contact_name' => htmlspecialchars($record['emergency_contact_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'emergency_contact_phone' => $this->formatPhoneNumber($record['emergency_contact_phone'] ?? ''),
            'insurance_provider' => htmlspecialchars($record['insurance_provider'] ?? '', ENT_QUOTES, 'UTF-8'),
            'created_at' => $this->formatDateTime($record['created_at'] ?? null),
            'updated_at' => $this->formatDateTime($record['updated_at'] ?? null)
        ];
    }
    
    // ============================================
    // PATIENT SEARCH METHODS (from patient-search.php)
    // ============================================
    
    /**
     * Search for patients
     *
     * @param array $criteria Search criteria
     * @return array Search results with success status
     */
    public function searchPatients(array $criteria): array
    {
        if (!$this->validateSession()) {
            return ['success' => false, 'error' => 'Invalid session', 'results' => [], 'count' => 0];
        }
        
        if (!$this->checkPermission('search_patients')) {
            $this->logAuditEvent('unauthorized_access', [
                'action' => 'search_patients',
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            return ['success' => false, 'error' => 'Insufficient permissions', 'results' => [], 'count' => 0];
        }
        
        // Validate search criteria
        $validationErrors = $this->validateSearchCriteria($criteria);
        if (!empty($validationErrors)) {
            return ['success' => false, 'errors' => $validationErrors, 'results' => [], 'count' => 0];
        }
        
        // Sanitize search criteria
        $sanitizedCriteria = $this->sanitizeSearchCriteria($criteria);
        
        try {
            // Use PatientRepository to search actual patient data
            $searchQuery = $sanitizedCriteria['query'] ?? '';
            $results = [];
            
            if (!empty($searchQuery)) {
                // Use the search method from PatientRepository
                $results = $this->patientRepo->search($searchQuery, 50);
            } else {
                // Build filters from specific criteria
                $filters = [];
                if (!empty($sanitizedCriteria['mrn'])) {
                    $filters['mrn'] = $sanitizedCriteria['mrn'];
                }
                if (!empty($sanitizedCriteria['first_name'])) {
                    $filters['first_name'] = $sanitizedCriteria['first_name'];
                }
                if (!empty($sanitizedCriteria['last_name'])) {
                    $filters['last_name'] = $sanitizedCriteria['last_name'];
                }
                if (!empty($sanitizedCriteria['dob'])) {
                    $filters['date_of_birth'] = $sanitizedCriteria['dob'];
                }
                if (!empty($sanitizedCriteria['employer_id'])) {
                    $filters['employer_id'] = $sanitizedCriteria['employer_id'];
                }
                
                $results = $this->patientRepo->findAll($filters, 1, 50);
            }
            
            $this->logAuditEvent('patient_search', [
                'criteria' => $sanitizedCriteria,
                'result_count' => count($results),
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
            
            // Log PHI search via AuditService
            if ($this->auditService) {
                $this->auditService->log('SEARCH', 'patient', null, ['criteria' => $sanitizedCriteria]);
            }
            
            // Convert patient entities to safe arrays (SSN masked)
            $safeResults = array_map(function($patient) {
                return $patient instanceof \Model\Entities\Patient
                    ? $patient->toSafeArray()
                    : $patient;
            }, $results);
            
            return [
                'success' => true,
                'results' => $safeResults,
                'count' => count($safeResults)
            ];
            
        } catch (Exception $e) {
            $this->logError('searchPatients', $e, ['criteria' => $sanitizedCriteria]);
            return ['success' => false, 'error' => 'Search failed', 'results' => [], 'count' => 0];
        }
    }
    
    /**
     * Validate search criteria
     * 
     * @param array $criteria Criteria to validate
     * @return array Validation errors (empty if valid)
     */
    public function validateSearchCriteria(array $criteria): array
    {
        $errors = [];
        
        // Check if at least one search criterion is provided
        $searchFields = ['query', 'mrn', 'first_name', 'last_name', 'dob', 'email', 'phone', 'employer_id'];
        $hasValidCriteria = false;
        
        foreach ($searchFields as $field) {
            if (!empty(trim($criteria[$field] ?? ''))) {
                $hasValidCriteria = true;
                break;
            }
        }
        
        if (!$hasValidCriteria) {
            $errors['criteria'] = 'At least one search criterion is required';
        }
        
        // Validate date of birth format if provided
        if (!empty($criteria['dob'])) {
            $dob = strtotime($criteria['dob']);
            if ($dob === false) {
                $errors['dob'] = 'Invalid date format for date of birth';
            } elseif ($dob > time()) {
                $errors['dob'] = 'Date of birth cannot be in the future';
            }
        }
        
        // Validate email format if provided
        if (!empty($criteria['email']) && !filter_var($criteria['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        // Validate phone format if provided
        if (!empty($criteria['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $criteria['phone']);
            if (strlen($phone) < 10) {
                $errors['phone'] = 'Phone number must be at least 10 digits';
            }
        }
        
        // Validate MRN format if provided
        if (!empty($criteria['mrn']) && strlen($criteria['mrn']) > 50) {
            $errors['mrn'] = 'MRN exceeds maximum length';
        }
        
        return $errors;
    }
    
    /**
     * Format search results for display
     * 
     * @param array $results Raw search results
     * @return array Formatted search results
     */
    public function formatSearchResultsForDisplay(array $results): array
    {
        $formatted = [];
        
        foreach ($results as $result) {
            $formatted[] = [
                'patient_id' => (int)($result['patient_id'] ?? 0),
                'mrn' => htmlspecialchars($result['mrn'] ?? '', ENT_QUOTES, 'UTF-8'),
                'full_name' => htmlspecialchars(
                    trim(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? '')),
                    ENT_QUOTES,
                    'UTF-8'
                ),
                'date_of_birth' => $this->formatDate($result['date_of_birth'] ?? null),
                'age' => $this->calculateAge($result['date_of_birth'] ?? null),
                'gender' => htmlspecialchars($result['gender'] ?? '', ENT_QUOTES, 'UTF-8'),
                'phone' => $this->formatPhoneNumber($result['phone'] ?? ''),
                'employer_name' => htmlspecialchars($result['employer_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                'last_visit' => $this->formatDate($result['last_visit'] ?? null)
            ];
        }
        
        return $formatted;
    }
    
    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================
    
    /**
     * Get role-based permissions
     * 
     * @param string $role User role
     * @return array List of permissions for the role
     */
    private function getRolePermissions(string $role): array
    {
        $permissions = [
            'tadmin' => [
                'view_clinical_notes', 'edit_clinical_notes', 'delete_clinical_notes',
                'view_epcr', 'edit_epcr', 'delete_epcr',
                'view_patient_records', 'edit_patient_records',
                'search_patients', 'manage_users', 'view_audit_logs'
            ],
            'cadmin' => [
                'view_clinical_notes', 'edit_clinical_notes',
                'view_epcr', 'edit_epcr',
                'view_patient_records', 'edit_patient_records',
                'search_patients', 'view_audit_logs'
            ],
            '1clinician' => [
                'view_clinical_notes', 'edit_clinical_notes',
                'view_epcr', 'edit_epcr',
                'view_patient_records',
                'search_patients'
            ],
            'dclinician' => [
                'view_clinical_notes', 'edit_clinical_notes',
                'view_epcr', 'edit_epcr',
                'view_patient_records',
                'search_patients'
            ],
            'pclinician' => [
                'view_clinical_notes', 'edit_clinical_notes',
                'view_epcr', 'edit_epcr',
                'view_patient_records',
                'search_patients'
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * Sanitize a string for safe storage/display
     * 
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    private function sanitizeString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitize a value for log output
     * 
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    private function sanitizeForLog($value)
    {
        if (is_string($value)) {
            return $this->sanitizeString($value);
        }
        if (is_array($value)) {
            return array_map([$this, 'sanitizeForLog'], $value);
        }
        return $value;
    }
    
    /**
     * Sanitize clinical note data
     * 
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private function sanitizeClinicalNoteData(array $data): array
    {
        return [
            'note_id' => isset($data['note_id']) ? (int)$data['note_id'] : null,
            'patient_id' => (int)($data['patient_id'] ?? 0),
            'encounter_id' => (int)($data['encounter_id'] ?? 0),
            'subjective' => trim($data['subjective'] ?? ''),
            'objective' => trim($data['objective'] ?? ''),
            'assessment' => trim($data['assessment'] ?? ''),
            'plan' => trim($data['plan'] ?? ''),
            'status' => $this->sanitizeString($data['status'] ?? 'draft'),
            'provider_id' => $_SESSION['user']['user_id'] ?? 0
        ];
    }
    
    /**
     * Sanitize ePCR data
     * 
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private function sanitizeEPCRData(array $data): array
    {
        return [
            'epcr_id' => isset($data['epcr_id']) ? (int)$data['epcr_id'] : null,
            'patient_id' => (int)($data['patient_id'] ?? 0),
            'encounter_id' => (int)($data['encounter_id'] ?? 0),
            'incident_number' => $this->sanitizeString($data['incident_number'] ?? ''),
            'unit_number' => $this->sanitizeString($data['unit_number'] ?? ''),
            'dispatch_time' => !empty($data['dispatch_time']) ? $data['dispatch_time'] : null,
            'en_route_time' => !empty($data['en_route_time']) ? $data['en_route_time'] : null,
            'on_scene_time' => !empty($data['on_scene_time']) ? $data['on_scene_time'] : null,
            'at_patient_time' => !empty($data['at_patient_time']) ? $data['at_patient_time'] : null,
            'transport_time' => !empty($data['transport_time']) ? $data['transport_time'] : null,
            'at_destination_time' => !empty($data['at_destination_time']) ? $data['at_destination_time'] : null,
            'chief_complaint' => trim($data['chief_complaint'] ?? ''),
            'dispatch_reason' => trim($data['dispatch_reason'] ?? ''),
            'scene_address' => trim($data['scene_address'] ?? ''),
            'transport_disposition' => $this->sanitizeString($data['transport_disposition'] ?? ''),
            'destination_facility' => trim($data['destination_facility'] ?? ''),
            'narrative' => trim($data['narrative'] ?? ''),
            'status' => $this->sanitizeString($data['status'] ?? 'draft')
        ];
    }
    
    /**
     * Sanitize search criteria
     * 
     * @param array $criteria Criteria to sanitize
     * @return array Sanitized criteria
     */
    private function sanitizeSearchCriteria(array $criteria): array
    {
        $sanitized = [];
        
        if (!empty($criteria['query'])) {
            $sanitized['query'] = $this->sanitizeString($criteria['query']);
        }
        if (!empty($criteria['mrn'])) {
            $sanitized['mrn'] = $this->sanitizeString($criteria['mrn']);
        }
        if (!empty($criteria['first_name'])) {
            $sanitized['first_name'] = $this->sanitizeString($criteria['first_name']);
        }
        if (!empty($criteria['last_name'])) {
            $sanitized['last_name'] = $this->sanitizeString($criteria['last_name']);
        }
        if (!empty($criteria['dob'])) {
            $sanitized['dob'] = $criteria['dob'];
        }
        if (!empty($criteria['email'])) {
            $sanitized['email'] = filter_var(trim($criteria['email']), FILTER_SANITIZE_EMAIL);
        }
        if (!empty($criteria['phone'])) {
            $sanitized['phone'] = preg_replace('/[^0-9]/', '', $criteria['phone']);
        }
        if (!empty($criteria['employer_id'])) {
            $sanitized['employer_id'] = (int)$criteria['employer_id'];
        }
        
        return $sanitized;
    }
    
    /**
     * Format a date/time value for display
     * 
     * @param string|null $datetime Date/time value
     * @return string Formatted date/time
     */
    private function formatDateTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return '';
        }
        
        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '';
        }
        
        return date('M j, Y g:i A', $timestamp);
    }
    
    /**
     * Format a date value for display
     * 
     * @param string|null $date Date value
     * @return string Formatted date
     */
    private function formatDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        
        return date('M j, Y', $timestamp);
    }
    
    /**
     * Calculate age from date of birth
     * 
     * @param string|null $dob Date of birth
     * @return int|null Age in years or null if invalid
     */
    private function calculateAge(?string $dob): ?int
    {
        if (empty($dob)) {
            return null;
        }
        
        $birthDate = strtotime($dob);
        if ($birthDate === false) {
            return null;
        }
        
        $today = time();
        $age = date('Y', $today) - date('Y', $birthDate);
        
        // Adjust if birthday hasn't occurred yet this year
        if (date('md', $today) < date('md', $birthDate)) {
            $age--;
        }
        
        return $age;
    }
    
    /**
     * Format a phone number for display
     * 
     * @param string $phone Raw phone number
     * @return string Formatted phone number
     */
    private function formatPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6)
            );
        }
        
        if (strlen($digits) === 11 && $digits[0] === '1') {
            return sprintf('+1 (%s) %s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7)
            );
        }
        
        return htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Format a full address from record components
     * 
     * @param array $record Record containing address fields
     * @return string Formatted full address
     */
    private function formatFullAddress(array $record): string
    {
        $parts = [];
        
        if (!empty($record['address'])) {
            $parts[] = htmlspecialchars($record['address'], ENT_QUOTES, 'UTF-8');
        }
        
        $cityStateZip = [];
        if (!empty($record['city'])) {
            $cityStateZip[] = htmlspecialchars($record['city'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($record['state'])) {
            $cityStateZip[] = htmlspecialchars($record['state'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($record['zip_code'])) {
            $cityStateZip[] = htmlspecialchars($record['zip_code'], ENT_QUOTES, 'UTF-8');
        }
        
        if (!empty($cityStateZip)) {
            $parts[] = implode(', ', array_slice($cityStateZip, 0, 2)) . 
                       (count($cityStateZip) > 2 ? ' ' . $cityStateZip[2] : '');
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Log an error with context
     * 
     * @param string $method Method name where error occurred
     * @param Exception $e The exception
     * @param array $context Additional context
     * @return void
     */
    private function logError(string $method, Exception $e, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'class' => 'ClinicianViewModel',
            'method' => $method,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => $_SESSION['user']['user_id'] ?? 'unknown',
            'context' => $context
        ];
        
        error_log('ClinicianViewModel Error: ' . json_encode($logEntry));
        
        try {
            $logFile = $this->logPath . 'error_' . date('Y-m-d') . '.log';
            $logLine = date('Y-m-d H:i:s') . ' | ERROR | ' . json_encode($logEntry) . PHP_EOL;
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (Exception $logException) {
            error_log('Failed to write to error log: ' . $logException->getMessage());
        }
    }
}