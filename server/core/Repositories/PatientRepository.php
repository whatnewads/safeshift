<?php
/**
 * Patient Repository
 * 
 * Handles all patient-related database operations
 * HIPAA-compliant data access layer
 */

namespace Core\Repositories;

use Core\Entities\Patient;
use PDO;
use Exception;

class PatientRepository
{
    private PDO $pdo;
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Find patient by ID
     */
    public function findById(string $patientId): ?Patient
    {
        $sql = "SELECT 
                    patient_id,
                    mrn,
                    legal_first_name,
                    legal_last_name,
                    preferred_name,
                    date_of_birth,
                    gender,
                    email,
                    phone,
                    address_line_1,
                    address_line_2,
                    city,
                    state,
                    zip,
                    country,
                    emergency_contact_name,
                    emergency_contact_phone,
                    emergency_contact_relationship,
                    insurance_provider,
                    insurance_policy_number,
                    employer_name,
                    employer_id,
                    is_active,
                    created_at,
                    updated_at,
                    deleted_at
                FROM patients
                WHERE patient_id = :patient_id
                AND deleted_at IS NULL";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['patient_id' => $patientId]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return new Patient($data);
    }
    
    /**
     * Find patient by MRN
     */
    public function findByMrn(string $mrn): ?Patient
    {
        $sql = "SELECT * FROM patients 
                WHERE mrn = :mrn 
                AND deleted_at IS NULL";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['mrn' => $mrn]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return new Patient($data);
    }
    
    /**
     * Search patients by name
     */
    public function searchByName(string $searchTerm, int $limit = 50): array
    {
        $sql = "SELECT * FROM patients 
                WHERE (legal_first_name LIKE :search 
                    OR legal_last_name LIKE :search 
                    OR preferred_name LIKE :search
                    OR CONCAT(legal_first_name, ' ', legal_last_name) LIKE :search)
                AND deleted_at IS NULL
                ORDER BY legal_last_name, legal_first_name
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $searchTerm . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Patient($data);
        }
        
        return $results;
    }
    
    /**
     * Get patients by employer
     */
    public function findByEmployerId(string $employerId, int $limit = 100): array
    {
        $sql = "SELECT * FROM patients 
                WHERE employer_id = :employer_id
                AND deleted_at IS NULL
                ORDER BY legal_last_name, legal_first_name
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':employer_id', $employerId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Patient($data);
        }
        
        return $results;
    }
    
    /**
     * Create new patient
     */
    public function create(Patient $patient, string $createdBy): Patient
    {
        $patientId = $this->generateUuid();
        $mrn = $this->generateMrn();
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO patients (
                    patient_id, mrn, legal_first_name, legal_last_name,
                    preferred_name, date_of_birth, gender, email, phone,
                    address_line_1, address_line_2, city, state, zip, country,
                    emergency_contact_name, emergency_contact_phone,
                    emergency_contact_relationship, insurance_provider,
                    insurance_policy_number, employer_name, employer_id,
                    is_active, created_at, created_by
                ) VALUES (
                    :patient_id, :mrn, :legal_first_name, :legal_last_name,
                    :preferred_name, :date_of_birth, :gender, :email, :phone,
                    :address_line_1, :address_line_2, :city, :state, :zip, :country,
                    :emergency_contact_name, :emergency_contact_phone,
                    :emergency_contact_relationship, :insurance_provider,
                    :insurance_policy_number, :employer_name, :employer_id,
                    :is_active, :created_at, :created_by
                )";
        
        $patient->setPatientId($patientId);
        $patient->setMrn($mrn);
        $patient->setCreatedAt($now);
        $patient->setIsActive(true);
        
        $data = $patient->toArray();
        $data['created_by'] = $createdBy;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $patient;
    }
    
    /**
     * Update patient
     */
    public function update(Patient $patient, string $updatedBy): bool
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "UPDATE patients SET
                    legal_first_name = :legal_first_name,
                    legal_last_name = :legal_last_name,
                    preferred_name = :preferred_name,
                    date_of_birth = :date_of_birth,
                    gender = :gender,
                    email = :email,
                    phone = :phone,
                    address_line_1 = :address_line_1,
                    address_line_2 = :address_line_2,
                    city = :city,
                    state = :state,
                    zip = :zip,
                    country = :country,
                    emergency_contact_name = :emergency_contact_name,
                    emergency_contact_phone = :emergency_contact_phone,
                    emergency_contact_relationship = :emergency_contact_relationship,
                    insurance_provider = :insurance_provider,
                    insurance_policy_number = :insurance_policy_number,
                    employer_name = :employer_name,
                    employer_id = :employer_id,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE patient_id = :patient_id
                AND deleted_at IS NULL";
        
        $patient->setUpdatedAt($now);
        
        $data = $patient->toArray();
        $data['updated_by'] = $updatedBy;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * Soft delete patient
     */
    public function delete(string $patientId, string $deletedBy): bool
    {
        $sql = "UPDATE patients 
                SET deleted_at = :deleted_at,
                    updated_by = :updated_by
                WHERE patient_id = :patient_id
                AND deleted_at IS NULL";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'patient_id' => $patientId,
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_by' => $deletedBy
        ]);
    }
    
    /**
     * Get recent patients accessed by user
     */
    public function getRecentlyAccessedByUser(string $userId, int $limit = 10): array
    {
        $sql = "SELECT DISTINCT
                    p.patient_id,
                    p.mrn,
                    p.legal_first_name,
                    p.legal_last_name,
                    p.preferred_name,
                    p.date_of_birth,
                    p.employer_name,
                    pal.accessed_at,
                    pal.access_type,
                    -- Get last encounter date
                    (SELECT MAX(e.created_at) 
                     FROM encounters e 
                     WHERE e.patient_id = p.patient_id
                     AND e.status != 'voided') as last_encounter_date
                FROM patient_access_log pal
                INNER JOIN patients p ON pal.patient_id = p.patient_id
                WHERE pal.user_id = :user_id
                AND p.deleted_at IS NULL
                ORDER BY pal.accessed_at DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $data;
        }
        
        return $results;
    }
    
    /**
     * Upsert patient (for ePCR save)
     */
    public function upsert(array $data, string $userId): string
    {
        // Check if patient exists by matching criteria
        $existingPatientId = $this->findExistingPatient($data);
        
        if ($existingPatientId) {
            // Update existing patient
            $patient = $this->findById($existingPatientId);
            
            // Update only non-empty fields
            foreach ($data as $key => $value) {
                if (!empty($value) && method_exists($patient, 'set' . $this->toCamelCase($key))) {
                    $setter = 'set' . $this->toCamelCase($key);
                    $patient->$setter($value);
                }
            }
            
            $this->update($patient, $userId);
            return $existingPatientId;
        } else {
            // Create new patient
            $patient = new Patient($data);
            $patient = $this->create($patient, $userId);
            return $patient->getPatientId();
        }
    }
    
    /**
     * Find existing patient by matching criteria
     */
    private function findExistingPatient(array $data): ?string
    {
        // Try to match by SSN if provided
        if (!empty($data['ssn'])) {
            $sql = "SELECT patient_id FROM patients 
                    WHERE ssn = :ssn 
                    AND deleted_at IS NULL 
                    LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['ssn' => $data['ssn']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['patient_id'];
            }
        }
        
        // Try to match by name and DOB
        if (!empty($data['legal_first_name']) && 
            !empty($data['legal_last_name']) && 
            !empty($data['date_of_birth'])) {
            
            $sql = "SELECT patient_id FROM patients 
                    WHERE legal_first_name = :first_name
                    AND legal_last_name = :last_name
                    AND date_of_birth = :dob
                    AND deleted_at IS NULL 
                    LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'first_name' => $data['legal_first_name'],
                'last_name' => $data['legal_last_name'],
                'dob' => $data['date_of_birth']
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['patient_id'];
            }
        }
        
        return null;
    }
    
    /**
     * Log patient access
     */
    public function logAccess(string $patientId, string $userId, string $accessType): void
    {
        $sql = "INSERT INTO patient_access_log (
                    access_id, patient_id, user_id, access_type, accessed_at
                ) VALUES (
                    :access_id, :patient_id, :user_id, :access_type, :accessed_at
                )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'access_id' => $this->generateUuid(),
            'patient_id' => $patientId,
            'user_id' => $userId,
            'access_type' => $accessType,
            'accessed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Generate MRN
     */
    private function generateMrn(): string
    {
        // Get the last MRN
        $sql = "SELECT mrn FROM patients 
                WHERE mrn LIKE 'MRN-%' 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && preg_match('/MRN-(\d+)/', $result['mrn'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 100000; // Start from 100000
        }
        
        return 'MRN-' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }
    
    /**
     * Convert snake_case to CamelCase
     */
    private function toCamelCase(string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }
}