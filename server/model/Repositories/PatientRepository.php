<?php

declare(strict_types=1);

namespace Model\Repositories;

use Model\Entities\Patient;
use Model\Interfaces\RepositoryInterface;
use Model\ValueObjects\Email;
use Model\ValueObjects\PhoneNumber;
use Model\ValueObjects\SSN;
use PDO;
use DateTimeImmutable;

/**
 * Patient Repository
 *
 * Data access layer for patient records.
 * Handles all database operations for the patient table.
 * Implements HIPAA-compliant data handling with SSN encryption.
 *
 * @package Model\Repositories
 */
class PatientRepository implements RepositoryInterface
{
    private PDO $pdo;
    private string $table = 'patients';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find patient by ID
     */
    public function findById(string $id): ?Patient
    {
        $sql = "SELECT * FROM {$this->table} WHERE patient_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydratePatient($row);
    }

    /**
     * Find patient by MRN (Medical Record Number)
     */
    public function findByMrn(string $mrn): ?Patient
    {
        $sql = "SELECT * FROM {$this->table} WHERE mrn = :mrn";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['mrn' => $mrn]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydratePatient($row);
    }

    /**
     * Find patient by SSN last four and DOB (for matching)
     */
    public function findBySsnAndDob(string $ssnLastFour, string $dateOfBirth): ?Patient
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE ssn_last_four = :ssn_last_four 
                AND DATE(date_of_birth) = :dob";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'ssn_last_four' => $ssnLastFour,
            'dob' => $dateOfBirth,
        ]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return $this->hydratePatient($row);
    }

    /**
     * Find all patients matching criteria
     *
     * @param array<string, mixed> $criteria Search criteria (e.g., ['employer_id' => '...', 'active_status' => true])
     * @param array<string, string> $orderBy Order by fields ['field' => 'ASC|DESC']
     * @param int|null $limit Maximum number of results (default: 50)
     * @param int|null $offset Number of results to skip (default: 0)
     * @return array<int, Patient> Array of matching patients
     */
    public function findAll(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): array {
        // Build WHERE clause from criteria
        $conditions = [];
        $params = [];
        
        // Default to active patients if not specified in criteria
        if (!array_key_exists('active_status', $criteria)) {
            $conditions[] = 'active_status = 1';
        }
        
        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $conditions[] = "{$field} IS NULL";
            } elseif (is_bool($value)) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value ? 1 : 0;
            } else {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Build ORDER BY clause
        $orderClauses = [];
        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderClauses[] = "{$field} {$direction}";
            }
        }
        // Default ordering if none specified
        if (empty($orderClauses)) {
            $orderClauses[] = 'last_name ASC';
            $orderClauses[] = 'first_name ASC';
        }
        $orderByClause = 'ORDER BY ' . implode(', ', $orderClauses);
        
        // Apply defaults for limit and offset
        $limitValue = $limit ?? 50;
        $offsetValue = $offset ?? 0;
        
        $sql = "SELECT * FROM {$this->table}
                {$whereClause}
                {$orderByClause}
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind criteria parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->bindValue(':limit', $limitValue, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offsetValue, PDO::PARAM_INT);
        $stmt->execute();

        $patients = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $patients[] = $this->hydratePatient($row);
        }

        return $patients;
    }

    /**
     * Search patients by name, MRN, or SSN last four
     */
    public function search(
        ?string $query = null,
        ?string $employerId = null,
        ?bool $active = true,
        int $limit = 50,
        int $offset = 0,
        string $sortBy = 'last_name',
        string $sortOrder = 'ASC'
    ): array {
        $conditions = [];
        $params = [];

        // Active status filter
        if ($active !== null) {
            $conditions[] = 'active_status = :active';
            $params['active'] = $active ? 1 : 0;
        }

        // Employer filter
        if ($employerId !== null) {
            $conditions[] = 'employer_id = :employer_id';
            $params['employer_id'] = $employerId;
        }

        // Search query (name, MRN, SSN last four)
        if ($query !== null && $query !== '') {
            $searchTerm = '%' . $query . '%';
            $conditions[] = '(
                first_name LIKE :search_term 
                OR last_name LIKE :search_term 
                OR CONCAT(first_name, " ", last_name) LIKE :search_term
                OR mrn LIKE :search_term 
                OR ssn_last_four LIKE :search_term
                OR email LIKE :search_term
            )';
            $params['search_term'] = $searchTerm;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Validate sort column
        $validSortColumns = ['last_name', 'first_name', 'date_of_birth', 'created_at', 'mrn'];
        $sortBy = in_array($sortBy, $validSortColumns) ? $sortBy : 'last_name';
        $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM {$this->table} 
                {$whereClause}
                ORDER BY {$sortBy} {$sortOrder}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $patients = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $patients[] = $this->hydratePatient($row);
        }

        return $patients;
    }

    /**
     * Count patients matching criteria (interface method)
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return int Number of matching patients
     */
    public function count(array $criteria = []): int
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $conditions[] = "{$field} IS NULL";
            } elseif (is_bool($value)) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value ? 1 : 0;
            } else {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count patients with extended filters (legacy method)
     */
    public function countWithFilters(
        ?string $query = null,
        ?string $employerId = null,
        ?bool $active = true
    ): int {
        $conditions = [];
        $params = [];

        if ($active !== null) {
            $conditions[] = 'active_status = :active';
            $params['active'] = $active ? 1 : 0;
        }

        if ($employerId !== null) {
            $conditions[] = 'employer_id = :employer_id';
            $params['employer_id'] = $employerId;
        }

        if ($query !== null && $query !== '') {
            $searchTerm = '%' . $query . '%';
            $conditions[] = '(
                first_name LIKE :search_term
                OR last_name LIKE :search_term
                OR CONCAT(first_name, " ", last_name) LIKE :search_term
                OR mrn LIKE :search_term
                OR ssn_last_four LIKE :search_term
            )';
            $params['search_term'] = $searchTerm;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Find patients by employer
     */
    public function findByEmployer(string $employerId, int $limit = 100, int $offset = 0): array
    {
        return $this->search(null, $employerId, true, $limit, $offset);
    }

    /**
     * Find patients by clinic
     */
    public function findByClinic(string $clinicId, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE clinic_id = :clinic_id AND active_status = 1
                ORDER BY last_name, first_name 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':clinic_id', $clinicId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $patients = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $patients[] = $this->hydratePatient($row);
        }

        return $patients;
    }

    /**
     * Find single patient matching criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return Patient|null
     */
    public function findOneBy(array $criteria): ?Patient
    {
        $results = $this->findAll($criteria, [], 1, 0);
        return $results[0] ?? null;
    }

    /**
     * Create a new patient from array data
     *
     * @param array<string, mixed> $data Patient data
     * @return Patient Created patient
     * @throws \InvalidArgumentException If required data is missing
     */
    public function create(array $data): Patient
    {
        $requiredFields = ['first_name', 'last_name', 'date_of_birth'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $patient = Patient::fromArray($data);
        
        if (!$this->insert($patient)) {
            throw new \RuntimeException('Failed to create patient');
        }

        return $this->findById($patient->getId());
    }

    /**
     * Update an existing patient (interface method)
     *
     * @param string $id Patient ID to update
     * @param array<string, mixed> $data Updated patient data
     * @return Patient Updated patient
     * @throws \RuntimeException If patient not found
     */
    public function update(string $id, array $data): Patient
    {
        $patient = $this->findById($id);
        if (!$patient) {
            throw new \RuntimeException("Patient not found: {$id}");
        }

        // Build update SQL dynamically based on provided data
        $allowedFields = [
            'first_name', 'last_name', 'middle_name', 'date_of_birth', 'gender',
            'email', 'primary_phone', 'secondary_phone',
            'address_line_1', 'address_line_2', 'city', 'state', 'zip_code', 'country',
            'employer_id', 'employer_name', 'clinic_id',
            'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
            'insurance_info', 'active_status'
        ];
        $setClauses = [];
        $params = ['patient_id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'insurance_info' && is_array($data[$field])) {
                    $setClauses[] = "{$field} = :{$field}";
                    $params[$field] = json_encode($data[$field]);
                } elseif ($field === 'active_status') {
                    $setClauses[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field] ? 1 : 0;
                } else {
                    $setClauses[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                }
            }
        }

        if (empty($setClauses)) {
            return $patient; // Nothing to update
        }

        $setClauses[] = 'updated_at = NOW()';

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE patient_id = :patient_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Return fresh entity
        return $this->findById($id);
    }

    /**
     * Check if patient exists (interface method)
     *
     * @param string $id Patient ID
     * @return bool True if exists
     */
    public function exists(string $id): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE patient_id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Check if patient exists by various identifiers (extended method)
     */
    public function existsByIdentifier(
        ?string $id = null,
        ?string $mrn = null,
        ?string $ssnLastFour = null,
        ?string $email = null
    ): bool {
        if ($id) {
            return $this->findById($id) !== null;
        }
        
        if ($mrn) {
            return $this->findByMrn($mrn) !== null;
        }
        
        if ($ssnLastFour) {
            $sql = "SELECT 1 FROM {$this->table} WHERE ssn_last_four = :ssn LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['ssn' => $ssnLastFour]);
            return $stmt->fetch() !== false;
        }
        
        if ($email) {
            $sql = "SELECT 1 FROM {$this->table} WHERE email = :email LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['email' => $email]);
            return $stmt->fetch() !== false;
        }
        
        return false;
    }

    /**
     * Save (insert or update) a patient
     *
     * @param \Model\Interfaces\EntityInterface $entity Patient entity to save
     * @return Patient Saved patient
     */
    public function save($entity): Patient
    {
        if (!$entity instanceof Patient) {
            throw new \InvalidArgumentException('Entity must be a Patient');
        }

        if ($entity->getId()) {
            $existing = $this->findById($entity->getId());
            if ($existing) {
                $this->updateEntity($entity);
                return $this->findById($entity->getId());
            }
        }

        $this->insert($entity);
        return $this->findById($entity->getId());
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Get the entity class this repository manages
     */
    public function getEntityClass(): string
    {
        return Patient::class;
    }

    /**
     * Get the database table name
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Insert a new patient
     */
    private function insert(Patient $patient): bool
    {
        // Generate UUID if not set
        $patientId = $patient->getId() ?? $this->generateUuid();
        
        // Generate MRN if not set
        $mrn = $patient->getMrn() ?? $this->generateMrn();

        $sql = "INSERT INTO {$this->table} (
                    patient_id, first_name, last_name, middle_name, date_of_birth,
                    gender, ssn_encrypted, ssn_last_four, mrn, email,
                    primary_phone, secondary_phone, address_line_1, address_line_2,
                    city, state, zip_code, country, employer_id, employer_name,
                    clinic_id, emergency_contact_name, emergency_contact_phone,
                    emergency_contact_relation, insurance_info, active_status,
                    created_at, updated_at
                ) VALUES (
                    :patient_id, :first_name, :last_name, :middle_name, :date_of_birth,
                    :gender, :ssn_encrypted, :ssn_last_four, :mrn, :email,
                    :primary_phone, :secondary_phone, :address_line_1, :address_line_2,
                    :city, :state, :zip_code, :country, :employer_id, :employer_name,
                    :clinic_id, :emergency_contact_name, :emergency_contact_phone,
                    :emergency_contact_relation, :insurance_info, :active_status,
                    NOW(), NOW()
                )";

        $stmt = $this->pdo->prepare($sql);
        
        $ssn = $patient->getSsn();
        
        $success = $stmt->execute([
            'patient_id' => $patientId,
            'first_name' => $patient->getFirstName(),
            'last_name' => $patient->getLastName(),
            'middle_name' => $patient->getMiddleName(),
            'date_of_birth' => $patient->getDateOfBirth()->format('Y-m-d'),
            'gender' => $patient->getGender(),
            'ssn_encrypted' => $ssn?->getEncrypted(),
            'ssn_last_four' => $ssn?->getLastFour(),
            'mrn' => $mrn,
            'email' => $patient->getEmail()?->getValue(),
            'primary_phone' => $patient->getPrimaryPhone()?->getValue(),
            'secondary_phone' => $patient->getSecondaryPhone()?->getValue(),
            'address_line_1' => $patient->toArray()['address_line_1'] ?? null,
            'address_line_2' => $patient->toArray()['address_line_2'] ?? null,
            'city' => $patient->toArray()['city'] ?? null,
            'state' => $patient->toArray()['state'] ?? null,
            'zip_code' => $patient->toArray()['zip_code'] ?? null,
            'country' => $patient->toArray()['country'] ?? 'US',
            'employer_id' => $patient->getEmployerId(),
            'employer_name' => $patient->getEmployerName(),
            'clinic_id' => $patient->getClinicId(),
            'emergency_contact_name' => $patient->toArray()['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $patient->toArray()['emergency_contact_phone'] ?? null,
            'emergency_contact_relation' => $patient->toArray()['emergency_contact_relation'] ?? null,
            'insurance_info' => json_encode($patient->getInsuranceInfo()),
            'active_status' => $patient->isActive() ? 1 : 0,
        ]);

        if ($success) {
            $patient->setId($patientId);
            if (!$patient->getMrn()) {
                $patient->setMrn($mrn);
            }
        }

        return $success;
    }

    /**
     * Update an existing patient entity (internal use)
     */
    private function updateEntity(Patient $patient): bool
    {
        $sql = "UPDATE {$this->table} SET
                    first_name = :first_name,
                    last_name = :last_name,
                    middle_name = :middle_name,
                    date_of_birth = :date_of_birth,
                    gender = :gender,
                    ssn_encrypted = :ssn_encrypted,
                    ssn_last_four = :ssn_last_four,
                    email = :email,
                    primary_phone = :primary_phone,
                    secondary_phone = :secondary_phone,
                    address_line_1 = :address_line_1,
                    address_line_2 = :address_line_2,
                    city = :city,
                    state = :state,
                    zip_code = :zip_code,
                    country = :country,
                    employer_id = :employer_id,
                    employer_name = :employer_name,
                    clinic_id = :clinic_id,
                    emergency_contact_name = :emergency_contact_name,
                    emergency_contact_phone = :emergency_contact_phone,
                    emergency_contact_relation = :emergency_contact_relation,
                    insurance_info = :insurance_info,
                    active_status = :active_status,
                    updated_at = NOW()
                WHERE patient_id = :patient_id";

        $stmt = $this->pdo->prepare($sql);
        
        $ssn = $patient->getSsn();
        $data = $patient->toArray();
        
        return $stmt->execute([
            'patient_id' => $patient->getId(),
            'first_name' => $patient->getFirstName(),
            'last_name' => $patient->getLastName(),
            'middle_name' => $patient->getMiddleName(),
            'date_of_birth' => $patient->getDateOfBirth()->format('Y-m-d'),
            'gender' => $patient->getGender(),
            'ssn_encrypted' => $ssn?->getEncrypted(),
            'ssn_last_four' => $ssn?->getLastFour(),
            'email' => $patient->getEmail()?->getValue(),
            'primary_phone' => $patient->getPrimaryPhone()?->getValue(),
            'secondary_phone' => $patient->getSecondaryPhone()?->getValue(),
            'address_line_1' => $data['address_line_1'] ?? null,
            'address_line_2' => $data['address_line_2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'country' => $data['country'] ?? 'US',
            'employer_id' => $patient->getEmployerId(),
            'employer_name' => $patient->getEmployerName(),
            'clinic_id' => $patient->getClinicId(),
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'emergency_contact_relation' => $data['emergency_contact_relation'] ?? null,
            'insurance_info' => json_encode($patient->getInsuranceInfo()),
            'active_status' => $patient->isActive() ? 1 : 0,
        ]);
    }

    /**
     * Soft delete a patient (deactivate)
     */
    public function delete(string $id): bool
    {
        $sql = "UPDATE {$this->table} 
                SET active_status = 0, updated_at = NOW()
                WHERE patient_id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Hard delete a patient (for GDPR right to erasure)
     * Use with caution - this permanently removes the record
     */
    public function hardDelete(string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE patient_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Activate a patient
     */
    public function activate(string $id): bool
    {
        $sql = "UPDATE {$this->table} 
                SET active_status = 1, updated_at = NOW()
                WHERE patient_id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }


    /**
     * Get recent patients (created in last N days)
     */
    public function getRecentPatients(int $days = 7, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND active_status = 1
                ORDER BY created_at DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $patients = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $patients[] = $this->hydratePatient($row);
        }

        return $patients;
    }

    /**
     * Hydrate a Patient entity from database row
     */
    private function hydratePatient(array $row): Patient
    {
        // Use array_key_exists to avoid PHP 8+ undefined key warnings
        // Check actual DB column names first (legal_first_name, legal_last_name, dob)
        $firstName = '';
        if (array_key_exists('legal_first_name', $row) && $row['legal_first_name'] !== null) {
            $firstName = $row['legal_first_name'];
        } elseif (array_key_exists('first_name', $row) && $row['first_name'] !== null) {
            $firstName = $row['first_name'];
        }
        
        $lastName = '';
        if (array_key_exists('legal_last_name', $row) && $row['legal_last_name'] !== null) {
            $lastName = $row['legal_last_name'];
        } elseif (array_key_exists('last_name', $row) && $row['last_name'] !== null) {
            $lastName = $row['last_name'];
        }
        
        $middleName = null;
        if (array_key_exists('legal_middle_name', $row) && $row['legal_middle_name'] !== null) {
            $middleName = $row['legal_middle_name'];
        } elseif (array_key_exists('middle_name', $row) && $row['middle_name'] !== null) {
            $middleName = $row['middle_name'];
        }
        
        $dateOfBirth = null;
        if (array_key_exists('dob', $row) && $row['dob'] !== null) {
            $dateOfBirth = $row['dob'];
        } elseif (array_key_exists('date_of_birth', $row) && $row['date_of_birth'] !== null) {
            $dateOfBirth = $row['date_of_birth'];
        }
        
        $gender = 'U';
        if (array_key_exists('sex_assigned_at_birth', $row) && $row['sex_assigned_at_birth'] !== null) {
            $gender = $row['sex_assigned_at_birth'];
        } elseif (array_key_exists('gender', $row) && $row['gender'] !== null) {
            $gender = $row['gender'];
        }
        
        $data = [
            'patient_id' => $row['patient_id'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => $middleName,
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            'mrn' => $row['mrn'] ?? null,
            'email' => $row['email'] ?? null,
            'primary_phone' => $row['primary_phone'] ?? null,
            'secondary_phone' => $row['secondary_phone'] ?? null,
            'address_line_1' => $row['address_line_1'] ?? null,
            'address_line_2' => $row['address_line_2'] ?? null,
            'city' => $row['city'] ?? null,
            'state' => $row['state'] ?? null,
            'zip_code' => $row['zip_code'] ?? null,
            'country' => $row['country'] ?? 'US',
            'employer_id' => $row['employer_id'] ?? null,
            'employer_name' => $row['employer_name'] ?? null,
            'clinic_id' => $row['clinic_id'] ?? null,
            'emergency_contact_name' => $row['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $row['emergency_contact_phone'] ?? null,
            'emergency_contact_relation' => $row['emergency_contact_relation'] ?? null,
            'active_status' => $row['active_status'] ?? 1,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        // Handle SSN
        if (!empty($row['ssn_encrypted']) && !empty($row['ssn_last_four'])) {
            $data['ssn_encrypted'] = $row['ssn_encrypted'];
            $data['ssn_last_four'] = $row['ssn_last_four'];
        }

        // Handle insurance info
        if (!empty($row['insurance_info'])) {
            $data['insurance_info'] = is_string($row['insurance_info'])
                ? json_decode($row['insurance_info'], true)
                : $row['insurance_info'];
        }

        return Patient::fromArray($data);
    }

    /**
     * Generate a new UUID
     */
    private function generateUuid(): string
    {
        return sprintf(
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
    }

    /**
     * Generate a new MRN (Medical Record Number)
     */
    private function generateMrn(): string
    {
        // Format: SS-YYYYMMDD-XXXX (SS = site prefix, XXXX = sequence)
        $date = date('Ymd');
        $sequence = mt_rand(1000, 9999);
        return "SS-{$date}-{$sequence}";
    }
}
