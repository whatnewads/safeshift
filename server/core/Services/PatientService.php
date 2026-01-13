<?php
/**
 * Patient Service
 * 
 * Business logic layer for patient operations
 * Follows MVVM pattern: ViewModel → Service → Repository → PDO
 * HIPAA-compliant patient data handling
 */

namespace Core\Services;

use Core\Repositories\PatientRepository;
use Core\Entities\Patient;
use PDO;
use Exception;

class PatientService extends BaseService
{
    private PatientRepository $patientRepo;
    
    /**
     * Constructor - initializes with PatientRepository
     */
    public function __construct(?PatientRepository $patientRepo = null)
    {
        parent::__construct();
        
        // If no repository provided, create one with our PDO connection
        $this->patientRepo = $patientRepo ?? new PatientRepository($this->db);
    }
    
    /**
     * Get patient by ID
     * 
     * @param int|string $patientId Patient ID
     * @return array|null Patient data or null if not found
     */
    public function getPatientById($patientId): ?array
    {
        try {
            $patient = $this->patientRepo->findById((string) $patientId);
            
            if (!$patient) {
                return null;
            }
            
            // Convert Patient entity to array for view consumption
            return $patient->toArray();
            
        } catch (Exception $e) {
            $this->logError("Error fetching patient by ID: " . $e->getMessage(), [
                'patient_id' => $patientId
            ]);
            return null;
        }
    }
    
    /**
     * Get patient by MRN
     * 
     * @param string $mrn Medical Record Number
     * @return array|null Patient data or null if not found
     */
    public function getPatientByMrn(string $mrn): ?array
    {
        try {
            $patient = $this->patientRepo->findByMrn($mrn);
            
            if (!$patient) {
                return null;
            }
            
            return $patient->toArray();
            
        } catch (Exception $e) {
            $this->logError("Error fetching patient by MRN: " . $e->getMessage(), [
                'mrn' => $mrn
            ]);
            return null;
        }
    }
    
    /**
     * Search patients by various criteria
     * 
     * @param array $criteria Search criteria (query, mrn, first_name, last_name, dob, employer_id)
     * @return array Search results
     */
    public function searchPatients(array $criteria): array
    {
        try {
            $results = [];
            
            // Handle general query search (could be name or MRN)
            if (!empty($criteria['query'])) {
                $query = $criteria['query'];
                
                // Check if query looks like an MRN
                if (preg_match('/^MRN-/i', $query)) {
                    $patient = $this->patientRepo->findByMrn($query);
                    if ($patient) {
                        return [$patient->toArray()];
                    }
                }
                
                // Search by name
                $patients = $this->patientRepo->searchByName($query, 50);
                foreach ($patients as $patient) {
                    $results[] = $patient->toArray();
                }
                
                return $results;
            }
            
            // Search by specific MRN
            if (!empty($criteria['mrn'])) {
                $patient = $this->patientRepo->findByMrn($criteria['mrn']);
                if ($patient) {
                    return [$patient->toArray()];
                }
                return [];
            }
            
            // Search by name fields
            if (!empty($criteria['first_name']) || !empty($criteria['last_name'])) {
                $searchTerm = trim(
                    ($criteria['first_name'] ?? '') . ' ' . ($criteria['last_name'] ?? '')
                );
                
                $patients = $this->patientRepo->searchByName($searchTerm, 50);
                
                // Filter by DOB if provided
                if (!empty($criteria['dob'])) {
                    $patients = array_filter($patients, function($patient) use ($criteria) {
                        return $patient->getDateOfBirth() === $criteria['dob'];
                    });
                }
                
                foreach ($patients as $patient) {
                    $results[] = $patient->toArray();
                }
                
                return $results;
            }
            
            // Search by employer
            if (!empty($criteria['employer_id'])) {
                $patients = $this->patientRepo->findByEmployerId($criteria['employer_id'], 100);
                foreach ($patients as $patient) {
                    $results[] = $patient->toArray();
                }
                return $results;
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logError("Error searching patients: " . $e->getMessage(), [
                'criteria' => $criteria
            ]);
            return [];
        }
    }
    
    /**
     * Create a new patient
     * 
     * @param array $data Patient data
     * @return string|null Patient ID on success, null on failure
     */
    public function createPatient(array $data): ?string
    {
        try {
            // Validate required fields
            $required = ['first_name', 'last_name', 'dob'];
            $missing = $this->validateRequiredFields($data, $required);
            
            if (!empty($missing)) {
                $this->logError("Missing required fields for patient creation", [
                    'missing' => $missing
                ]);
                return null;
            }
            
            // Map form fields to entity fields
            $patientData = [
                'legal_first_name' => $data['first_name'] ?? $data['legal_first_name'] ?? '',
                'legal_last_name' => $data['last_name'] ?? $data['legal_last_name'] ?? '',
                'preferred_name' => $data['preferred_name'] ?? null,
                'date_of_birth' => $this->formatDateForDb($data['dob'] ?? $data['date_of_birth'] ?? ''),
                'gender' => $this->mapGenderToSex($data['sex'] ?? $data['gender'] ?? ''),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address_line_1' => $data['address_line_1'] ?? $data['address'] ?? null,
                'address_line_2' => $data['address_line_2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip' => $data['zip'] ?? $data['zip_code'] ?? null,
                'country' => $data['country'] ?? 'USA',
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,
                'insurance_provider' => $data['insurance_provider'] ?? null,
                'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
                'employer_name' => $data['employer_name'] ?? null,
                'employer_id' => $data['employer_id'] ?? null,
            ];
            
            // Create Patient entity
            $patient = new Patient($patientData);
            
            // Get current user ID for audit
            $userId = $this->getCurrentUserId() ?? 'system';
            
            // Create patient via repository
            $createdPatient = $this->patientRepo->create($patient, $userId);
            
            return $createdPatient->getPatientId();
            
        } catch (Exception $e) {
            $this->logError("Error creating patient: " . $e->getMessage(), [
                'data' => array_keys($data) // Log only keys for HIPAA compliance
            ]);
            return null;
        }
    }
    
    /**
     * Update patient information
     * 
     * @param string $patientId Patient ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function updatePatient(string $patientId, array $data): bool
    {
        try {
            $patient = $this->patientRepo->findById($patientId);
            
            if (!$patient) {
                $this->logError("Patient not found for update", ['patient_id' => $patientId]);
                return false;
            }
            
            // Update fields
            if (isset($data['first_name'])) {
                $patient->setLegalFirstName($data['first_name']);
            }
            if (isset($data['last_name'])) {
                $patient->setLegalLastName($data['last_name']);
            }
            if (isset($data['preferred_name'])) {
                $patient->setPreferredName($data['preferred_name']);
            }
            if (isset($data['dob'])) {
                $patient->setDateOfBirth($this->formatDateForDb($data['dob']));
            }
            if (isset($data['gender']) || isset($data['sex'])) {
                $patient->setGender($this->mapGenderToSex($data['sex'] ?? $data['gender']));
            }
            if (isset($data['email'])) {
                $patient->setEmail($data['email']);
            }
            if (isset($data['phone'])) {
                $patient->setPhone($data['phone']);
            }
            
            $userId = $this->getCurrentUserId() ?? 'system';
            
            return $this->patientRepo->update($patient, $userId);
            
        } catch (Exception $e) {
            $this->logError("Error updating patient: " . $e->getMessage(), [
                'patient_id' => $patientId
            ]);
            return false;
        }
    }
    
    /**
     * Get list of employers for dropdowns
     * 
     * @return array List of employers
     */
    public function getEmployersList(): array
    {
        try {
            $sql = "SELECT employer_id, company_name, company_code 
                    FROM employers 
                    WHERE is_active = 1 
                    ORDER BY company_name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->logError("Error fetching employers list: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recently accessed patients for a user
     * 
     * @param string $userId User ID
     * @param int $limit Number of recent patients to return
     * @return array Recent patients
     */
    public function getRecentlyAccessedPatients(string $userId, int $limit = 10): array
    {
        try {
            return $this->patientRepo->getRecentlyAccessedByUser($userId, $limit);
            
        } catch (Exception $e) {
            $this->logError("Error fetching recently accessed patients: " . $e->getMessage(), [
                'user_id' => $userId
            ]);
            return [];
        }
    }
    
    /**
     * Log patient access for HIPAA compliance
     * 
     * @param string $patientId Patient ID
     * @param string $userId User ID
     * @param string $accessType Type of access (view, edit, etc.)
     * @return void
     */
    public function logPatientAccess(string $patientId, string $userId, string $accessType): void
    {
        try {
            $this->patientRepo->logAccess($patientId, $userId, $accessType);
        } catch (Exception $e) {
            $this->logError("Error logging patient access: " . $e->getMessage(), [
                'patient_id' => $patientId,
                'user_id' => $userId,
                'access_type' => $accessType
            ]);
        }
    }
    
    /**
     * Map gender value to standardized sex value
     * Used for ePCR and NEMSIS compliance
     * 
     * @param string $gender Gender from form
     * @return string Standardized sex value
     */
    public static function mapGenderToSex(string $gender): string
    {
        $gender = strtolower(trim($gender));
        
        $genderMap = [
            'male' => 'M',
            'm' => 'M',
            'man' => 'M',
            'female' => 'F',
            'f' => 'F',
            'woman' => 'F',
            'other' => 'O',
            'o' => 'O',
            'unknown' => 'U',
            'u' => 'U',
            '' => 'U'
        ];
        
        return $genderMap[$gender] ?? 'U';
    }
    
    /**
     * Parse patient name from "Last, First" format
     * 
     * @param string $fullName Full name in "Last, First" format
     * @return array Array with 'first' and 'last' keys
     */
    public static function parsePatientName(string $fullName): array
    {
        $parts = array_map('trim', explode(',', $fullName, 2));
        
        return [
            'last' => $parts[0] ?? '',
            'first' => $parts[1] ?? ''
        ];
    }
    
    /**
     * Upsert patient (for ePCR save)
     * 
     * @param array $data Patient data
     * @return string Patient ID
     */
    public function upsertPatient(array $data): string
    {
        try {
            $userId = $this->getCurrentUserId() ?? 'system';
            return $this->patientRepo->upsert($data, $userId);
            
        } catch (Exception $e) {
            $this->logError("Error upserting patient: " . $e->getMessage());
            throw $e;
        }
    }
}