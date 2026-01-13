<?php
/**
 * PatientViewModel - Business logic layer for patient operations
 * 
 * Handles: Patient CRUD, search, encounters retrieval
 * Security: HIPAA-compliant PHI access logging, role-based access
 * 
 * @package SafeShift\ViewModel\Patient
 */

declare(strict_types=1);

namespace ViewModel\Patient;

use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;
use Core\Repositories\PatientRepository;
use Core\Services\AuditService;
use Model\Validators\PatientValidator;
use Model\Entities\Patient;
use Exception;
use PDO;

/**
 * Patient ViewModel
 * 
 * CRUD operations for patients with HIPAA compliance.
 * All PHI access is logged for audit trail.
 */
class PatientViewModel extends BaseViewModel
{
    /** @var PatientRepository Patient repository */
    private PatientRepository $patientRepo;

    /**
     * Constructor
     * 
     * @param PatientRepository|null $patientRepo
     * @param AuditService|null $auditService
     * @param PDO|null $pdo
     */
    public function __construct(
        ?PatientRepository $patientRepo = null,
        ?AuditService $auditService = null,
        ?PDO $pdo = null
    ) {
        parent::__construct($auditService, $pdo);
        
        $this->patientRepo = $patientRepo ?? new PatientRepository($this->pdo);
    }

    /**
     * List patients with pagination and filters
     * 
     * @param array $filters Optional filters (employer_id, active_only, etc.)
     * @return array API response
     */
    public function index(array $filters = []): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_patients');
            
            [$page, $perPage] = $this->getPaginationParams($filters);
            
            // Build search criteria from filters
            $patients = [];
            
            if (!empty($filters['employer_id'])) {
                $patients = $this->patientRepo->findByEmployerId(
                    (string)$filters['employer_id'],
                    $perPage * 10 // Get more for filtering
                );
            } elseif (!empty($filters['search']) || !empty($filters['query'])) {
                $searchTerm = $filters['search'] ?? $filters['query'];
                $patients = $this->patientRepo->searchByName(
                    (string)$searchTerm,
                    $perPage * 10
                );
            } else {
                // Get recently accessed patients for current user
                $recentPatients = $this->patientRepo->getRecentlyAccessedByUser(
                    $this->getCurrentUserId(),
                    $perPage * 10
                );
                
                // Convert to Patient objects if needed
                foreach ($recentPatients as $data) {
                    if (isset($data['patient_id'])) {
                        $patient = $this->patientRepo->findById($data['patient_id']);
                        if ($patient) {
                            $patients[] = $patient;
                        }
                    }
                }
            }
            
            // Filter by active status if requested
            if (isset($filters['active_only']) && $filters['active_only']) {
                $patients = array_filter($patients, function($p) {
                    return $p instanceof Patient ? $p->isActive() : ($p['is_active'] ?? true);
                });
            }
            
            // Convert Patient objects to safe arrays
            $safePatients = array_map(function($patient) {
                if ($patient instanceof Patient) {
                    return $patient->toSafeArray();
                }
                // Already an array from recent access
                return [
                    'patient_id' => $patient['patient_id'] ?? null,
                    'mrn' => $patient['mrn'] ?? null,
                    'first_name' => $patient['legal_first_name'] ?? $patient['first_name'] ?? null,
                    'last_name' => $patient['legal_last_name'] ?? $patient['last_name'] ?? null,
                    'full_name' => ($patient['legal_first_name'] ?? '') . ' ' . ($patient['legal_last_name'] ?? ''),
                    'date_of_birth' => $patient['date_of_birth'] ?? null,
                    'employer_name' => $patient['employer_name'] ?? null,
                    'active_status' => $patient['is_active'] ?? true,
                ];
            }, array_values($patients));
            
            // Log PHI access
            $this->audit('VIEW', 'patient_list', null, [
                'count' => count($safePatients),
                'filters' => array_keys($filters)
            ]);
            
            // Paginate
            $result = $this->paginate($safePatients, $page, $perPage);
            
            return ApiResponse::success($result, 'Patients retrieved successfully');
            
        } catch (Exception $e) {
            $this->logError('index', $e, ['filters' => $filters]);
            return $this->handleException($e, 'Failed to retrieve patients');
        }
    }

    /**
     * Get single patient by ID
     * 
     * @param string $id Patient ID
     * @return array API response
     */
    public function show(string $id): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_patients');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid patient ID format']]);
            }
            
            $patient = $this->patientRepo->findById($id);
            
            if (!$patient) {
                return ApiResponse::notFound('Patient not found');
            }
            
            // Log PHI access (HIPAA requirement)
            $this->logPhiAccess('patient', $id, 'view');
            
            // Log access in patient access log
            $this->patientRepo->logAccess($id, $this->getCurrentUserId(), 'view');
            
            // Return full array (with masked SSN)
            return ApiResponse::success($patient->toArray(), 'Patient retrieved successfully');
            
        } catch (Exception $e) {
            $this->logError('show', $e, ['patient_id' => $id]);
            return $this->handleException($e, 'Failed to retrieve patient');
        }
    }

    /**
     * Create new patient
     * 
     * @param array $data Patient data
     * @return array API response
     */
    public function store(array $data): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_patients');
            
            // Validate input
            $errors = PatientValidator::validateCreate($data);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            // Map input fields to repository format
            $patientData = $this->mapInputToPatientData($data);
            
            // Check for duplicate
            if (!empty($patientData['legal_first_name']) && 
                !empty($patientData['legal_last_name']) && 
                !empty($patientData['date_of_birth'])) {
                $existingId = $this->patientRepo->upsert($patientData, $this->getCurrentUserId());
                
                if ($existingId) {
                    $patient = $this->patientRepo->findById($existingId);
                    
                    $this->audit('CREATE', 'patient', $existingId, [
                        'action' => 'created_or_updated'
                    ]);
                    
                    return ApiResponse::success(
                        $patient->toSafeArray(), 
                        'Patient created successfully'
                    );
                }
            }
            
            // Create new patient
            $patient = new \Core\Entities\Patient($patientData);
            $patient = $this->patientRepo->create($patient, $this->getCurrentUserId());
            
            // Log audit event
            $this->audit('CREATE', 'patient', $patient->getPatientId());
            $this->auditService->logPatientRegistration($patient->getPatientId(), 'standard');
            
            return ApiResponse::success(
                $patient->toSafeArray(),
                'Patient created successfully'
            );
            
        } catch (Exception $e) {
            $this->logError('store', $e, ['data_keys' => array_keys($data)]);
            return $this->handleException($e, 'Failed to create patient');
        }
    }

    /**
     * Update patient
     * 
     * @param string $id Patient ID
     * @param array $data Update data
     * @return array API response
     */
    public function update(string $id, array $data): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_patients');
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid patient ID format']]);
            }
            
            // Get existing patient
            $patient = $this->patientRepo->findById($id);
            
            if (!$patient) {
                return ApiResponse::notFound('Patient not found');
            }
            
            // Validate update data
            $errors = PatientValidator::validateUpdate($data);
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            
            // Map and apply updates
            $patientData = $this->mapInputToPatientData($data);
            
            // Update patient fields
            foreach ($patientData as $key => $value) {
                if ($value !== null) {
                    $setter = 'set' . $this->toCamelCase($key);
                    if (method_exists($patient, $setter)) {
                        $patient->$setter($value);
                    }
                }
            }
            
            // Save changes
            $success = $this->patientRepo->update($patient, $this->getCurrentUserId());
            
            if (!$success) {
                return ApiResponse::serverError('Failed to update patient');
            }
            
            // Log PHI modification
            $this->audit('UPDATE', 'patient', $id, [
                'fields_updated' => array_keys($data)
            ]);
            
            // Refresh and return
            $patient = $this->patientRepo->findById($id);
            
            return ApiResponse::success($patient->toArray(), 'Patient updated successfully');
            
        } catch (Exception $e) {
            $this->logError('update', $e, ['patient_id' => $id]);
            return $this->handleException($e, 'Failed to update patient');
        }
    }

    /**
     * Soft delete patient
     * 
     * @param string $id Patient ID
     * @return array API response
     */
    public function destroy(string $id): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('edit_patients');
            
            // Only admins can delete patients
            $role = $this->getCurrentUserRole();
            if (!in_array($role, ['tadmin', 'cadmin', 'Admin'])) {
                return ApiResponse::forbidden('Only administrators can delete patients');
            }
            
            if (!$this->isValidUuid($id)) {
                return ApiResponse::validationError(['id' => ['Invalid patient ID format']]);
            }
            
            $patient = $this->patientRepo->findById($id);
            
            if (!$patient) {
                return ApiResponse::notFound('Patient not found');
            }
            
            // Soft delete
            $success = $this->patientRepo->delete($id, $this->getCurrentUserId());
            
            if (!$success) {
                return ApiResponse::serverError('Failed to delete patient');
            }
            
            // Log deletion
            $this->audit('DELETE', 'patient', $id);
            
            return ApiResponse::success(null, 'Patient deleted successfully');
            
        } catch (Exception $e) {
            $this->logError('destroy', $e, ['patient_id' => $id]);
            return $this->handleException($e, 'Failed to delete patient');
        }
    }

    /**
     * Quick search for patients
     * 
     * @param string $query Search query
     * @return array API response
     */
    public function search(string $query): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_patients');
            
            $query = trim($query);
            
            if (strlen($query) < 2) {
                return ApiResponse::validationError([
                    'query' => ['Search query must be at least 2 characters']
                ]);
            }
            
            // Search by name
            $patients = $this->patientRepo->searchByName($query, 50);
            
            // If looks like MRN, also search by MRN
            if (preg_match('/^MRN-?\d+$/i', $query)) {
                $mrnPatient = $this->patientRepo->findByMrn($query);
                if ($mrnPatient) {
                    array_unshift($patients, $mrnPatient);
                }
            }
            
            // Convert to safe arrays
            $results = array_map(function($patient) {
                return $patient->toSafeArray();
            }, $patients);
            
            // Log search
            $this->audit('SEARCH', 'patient', null, [
                'query' => substr($query, 0, 50), // Limit logged query length
                'result_count' => count($results)
            ]);
            
            return ApiResponse::success([
                'items' => $results,
                'count' => count($results)
            ], 'Search completed');
            
        } catch (Exception $e) {
            $this->logError('search', $e, ['query' => $query]);
            return $this->handleException($e, 'Search failed');
        }
    }

    /**
     * Get patient encounters
     * 
     * @param string $patientId Patient ID
     * @return array API response
     */
    public function getEncounters(string $patientId): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_encounters');
            
            if (!$this->isValidUuid($patientId)) {
                return ApiResponse::validationError(['patient_id' => ['Invalid patient ID format']]);
            }
            
            // Verify patient exists
            $patient = $this->patientRepo->findById($patientId);
            
            if (!$patient) {
                return ApiResponse::notFound('Patient not found');
            }
            
            // Get encounters from encounter repository
            $encounterRepo = new \Core\Repositories\EncounterRepository($this->pdo);
            $encounters = $encounterRepo->findByPatientId($patientId);
            
            // Convert to arrays
            $encounterData = array_map(function($encounter) {
                return $encounter->toArray();
            }, $encounters);
            
            // Log PHI access
            $this->logPhiAccess('patient_encounters', $patientId, 'view');
            
            return ApiResponse::success([
                'patient_id' => $patientId,
                'patient_name' => $patient->getFullName(),
                'encounters' => $encounterData,
                'count' => count($encounterData)
            ], 'Patient encounters retrieved');
            
        } catch (Exception $e) {
            $this->logError('getEncounters', $e, ['patient_id' => $patientId]);
            return $this->handleException($e, 'Failed to retrieve patient encounters');
        }
    }

    /**
     * Get recently accessed patients for current user
     * 
     * @param int $limit Number of patients to return
     * @return array API response
     */
    public function getRecentPatients(int $limit = 10): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_patients');
            
            $limit = min(50, max(1, $limit));
            
            $recentPatients = $this->patientRepo->getRecentlyAccessedByUser(
                $this->getCurrentUserId(),
                $limit
            );
            
            // Format results
            $formatted = array_map(function($data) {
                return [
                    'patient_id' => $data['patient_id'],
                    'mrn' => $data['mrn'],
                    'full_name' => ($data['legal_first_name'] ?? '') . ' ' . ($data['legal_last_name'] ?? ''),
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'employer_name' => $data['employer_name'] ?? null,
                    'last_accessed' => $data['accessed_at'] ?? null,
                    'access_type' => $data['access_type'] ?? null,
                    'last_encounter_date' => $data['last_encounter_date'] ?? null,
                ];
            }, $recentPatients);
            
            return ApiResponse::success([
                'items' => $formatted,
                'count' => count($formatted)
            ], 'Recent patients retrieved');
            
        } catch (Exception $e) {
            $this->logError('getRecentPatients', $e);
            return $this->handleException($e, 'Failed to retrieve recent patients');
        }
    }

    /**
     * Map input data to patient repository format
     * 
     * @param array $data Input data
     * @return array Mapped data
     */
    private function mapInputToPatientData(array $data): array
    {
        $mapped = [];
        
        // Field mapping
        $fieldMap = [
            'first_name' => 'legal_first_name',
            'last_name' => 'legal_last_name',
            'middle_name' => 'preferred_name',
            'date_of_birth' => 'date_of_birth',
            'gender' => 'gender',
            'email' => 'email',
            'phone' => 'phone',
            'primary_phone' => 'phone',
            'address' => 'address_line_1',
            'address_line_1' => 'address_line_1',
            'address_line_2' => 'address_line_2',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'zip',
            'zip_code' => 'zip',
            'country' => 'country',
            'emergency_contact_name' => 'emergency_contact_name',
            'emergency_contact_phone' => 'emergency_contact_phone',
            'emergency_contact_relationship' => 'emergency_contact_relationship',
            'insurance_provider' => 'insurance_provider',
            'insurance_policy_number' => 'insurance_policy_number',
            'employer_name' => 'employer_name',
            'employer_id' => 'employer_id',
            'ssn' => 'ssn',
        ];
        
        foreach ($fieldMap as $inputKey => $repoKey) {
            if (isset($data[$inputKey])) {
                $mapped[$repoKey] = $data[$inputKey];
            }
        }
        
        return $mapped;
    }

    /**
     * Convert snake_case to CamelCase
     * 
     * @param string $str String to convert
     * @return string
     */
    private function toCamelCase(string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }
}
