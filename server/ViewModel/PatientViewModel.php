<?php

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\PatientRepository;
use Model\Entities\Patient;
use Model\ValueObjects\Email;
use Model\ValueObjects\PhoneNumber;
use Model\ValueObjects\SSN;
use Model\Validators\PatientValidator;
use ViewModel\Core\ApiResponse;
use Core\Traits\Auditable;
use PDO;
use DateTimeImmutable;

/**
 * Patient ViewModel
 *
 * Coordinates between the View (API) and Model (Repository/Entity) layers.
 * Handles business logic for patient operations including registration,
 * search, and demographics management.
 *
 * Includes HIPAA-compliant audit logging via the Auditable trait.
 *
 * @package ViewModel
 */
class PatientViewModel
{
    use Auditable;
    
    private PatientRepository $repository;
    private ?string $currentUserId = null;
    private ?string $currentClinicId = null;

    public function __construct(PDO $pdo)
    {
        $this->repository = new PatientRepository($pdo);
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
     * Get patients with filtering, searching, and pagination
     *
     * @param array $filters Optional filters (search, employerId, active, sortBy, sortOrder)
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array API response data
     */
    public function getPatients(
        array $filters = [],
        int $page = 1,
        int $perPage = 50
    ): array {
        try {
            $search = $filters['search'] ?? null;
            $employerId = $filters['employerId'] ?? $filters['employer_id'] ?? null;
            $active = isset($filters['active']) ? (bool) $filters['active'] : true;
            $sortBy = $filters['sortBy'] ?? $filters['sort_by'] ?? 'last_name';
            $sortOrder = $filters['sortOrder'] ?? $filters['sort_order'] ?? 'ASC';

            $offset = ($page - 1) * $perPage;

            // Get patients
            $patients = $this->repository->search(
                $search,
                $employerId,
                $active,
                $perPage,
                $offset,
                $sortBy,
                $sortOrder
            );

            // Get total count
            $totalCount = $this->repository->count($search, $employerId, $active);

            // Convert to safe array format (minimal PHI for lists)
            $patientData = array_map(
                fn(Patient $p) => $this->formatPatientForList($p),
                $patients
            );

            return ApiResponse::success([
                'patients' => $patientData,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $perPage),
                ],
                'filters' => [
                    'search' => $search,
                    'employer_id' => $employerId,
                    'active' => $active,
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                ],
            ]);
        } catch (\Exception $e) {
            error_log("PatientViewModel::getPatients error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve patients', 500);
        }
    }

    /**
     * Get a single patient by ID (full details)
     */
    public function getPatient(string $patientId): array
    {
        try {
            $patient = $this->repository->findById($patientId);

            if (!$patient) {
                // Log access attempt to non-existent patient
                $this->logFailure('read', 'patient', $patientId, 'Patient not found');
                return ApiResponse::error('Patient not found', 404);
            }

            // Log PHI access - this is a HIPAA requirement
            $this->logRead(
                resourceType: 'patient',
                resourceId: $patientId,
                patientId: $patientId,
                description: "Accessed patient record: {$patient->getFullName()}"
            );

            return ApiResponse::success([
                'patient' => $this->formatPatientForDetail($patient),
            ]);
        } catch (\Exception $e) {
            error_log("PatientViewModel::getPatient error: " . $e->getMessage());
            $this->logFailure('read', 'patient', $patientId, $e->getMessage());
            return ApiResponse::error('Failed to retrieve patient', 500);
        }
    }

    /**
     * Create a new patient (registration)
     */
    public function createPatient(array $data): array
    {
        try {
            // Validate required fields
            $errors = $this->validatePatientData($data);
            if (!empty($errors)) {
                $this->logFailure('create', 'patient', null, 'Validation failed', null, ['errors' => $errors]);
                return ApiResponse::error('Validation failed', 422, $errors);
            }

            // Check for duplicate patient (by SSN + DOB or email)
            $duplicate = $this->checkForDuplicate($data);
            if ($duplicate) {
                $this->logFailure('create', 'patient', null, 'Duplicate patient detected', $duplicate->getId());
                return ApiResponse::error('A patient with this information already exists', 409, [
                    'duplicate_id' => $duplicate->getId(),
                    'duplicate_name' => $duplicate->getFullName(),
                ]);
            }

            // Create Patient entity
            $patient = $this->hydratePatientFromData($data);

            // Set clinic if available
            if ($this->currentClinicId) {
                $patient->setClinicId($this->currentClinicId);
            }

            // Save to database
            $success = $this->repository->save($patient);

            if ($success) {
                // Log patient creation with sanitized data
                $this->logCreate(
                    resourceType: 'patient',
                    resourceId: $patient->getId(),
                    newValues: $this->formatPatientForDetail($patient),
                    patientId: $patient->getId(),
                    description: "Created new patient: {$patient->getFullName()}"
                );

                return ApiResponse::success([
                    'patient' => $this->formatPatientForDetail($patient),
                    'message' => 'Patient created successfully',
                ], 201);
            }

            $this->logFailure('create', 'patient', null, 'Database save failed');
            return ApiResponse::error('Failed to create patient', 500);
        } catch (\Exception $e) {
            error_log("PatientViewModel::createPatient error: " . $e->getMessage());
            $this->logFailure('create', 'patient', null, $e->getMessage());
            return ApiResponse::error('Failed to create patient: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing patient
     */
    public function updatePatient(string $patientId, array $data): array
    {
        try {
            // Find existing patient
            $patient = $this->repository->findById($patientId);
            if (!$patient) {
                $this->logFailure('update', 'patient', $patientId, 'Patient not found');
                return ApiResponse::error('Patient not found', 404);
            }

            // Capture old values BEFORE update for audit trail
            $oldValues = $this->formatPatientForDetail($patient);

            // Validate data
            $errors = $this->validatePatientData($data, false);
            if (!empty($errors)) {
                $this->logFailure('update', 'patient', $patientId, 'Validation failed', $patientId, ['errors' => $errors]);
                return ApiResponse::error('Validation failed', 422, $errors);
            }

            // Update patient fields
            $patient = $this->updatePatientFromData($patient, $data);

            // Save to database
            $success = $this->repository->save($patient);

            if ($success) {
                // Capture new values AFTER update
                $newValues = $this->formatPatientForDetail($patient);

                // Log patient update with old and new values
                $this->logUpdate(
                    resourceType: 'patient',
                    resourceId: $patientId,
                    oldValues: $oldValues,
                    newValues: $newValues,
                    modifiedFields: null, // Auto-calculated by trait
                    patientId: $patientId,
                    description: "Updated patient: {$patient->getFullName()}"
                );

                return ApiResponse::success([
                    'patient' => $newValues,
                    'message' => 'Patient updated successfully',
                ]);
            }

            $this->logFailure('update', 'patient', $patientId, 'Database save failed', $patientId);
            return ApiResponse::error('Failed to update patient', 500);
        } catch (\Exception $e) {
            error_log("PatientViewModel::updatePatient error: " . $e->getMessage());
            $this->logFailure('update', 'patient', $patientId, $e->getMessage(), $patientId);
            return ApiResponse::error('Failed to update patient', 500);
        }
    }

    /**
     * Delete (soft delete) a patient
     */
    public function deletePatient(string $patientId): array
    {
        try {
            $patient = $this->repository->findById($patientId);
            if (!$patient) {
                $this->logFailure('delete', 'patient', $patientId, 'Patient not found');
                return ApiResponse::error('Patient not found', 404);
            }

            // Capture patient data before deletion for audit trail
            $oldValues = $this->formatPatientForDetail($patient);

            $success = $this->repository->delete($patientId);

            if ($success) {
                // Log patient deletion with preserved record state
                $this->logDelete(
                    resourceType: 'patient',
                    resourceId: $patientId,
                    oldValues: $oldValues,
                    patientId: $patientId,
                    description: "Deactivated patient: {$patient->getFullName()}"
                );

                return ApiResponse::success([
                    'message' => 'Patient deactivated successfully',
                ]);
            }

            $this->logFailure('delete', 'patient', $patientId, 'Database delete failed', $patientId);
            return ApiResponse::error('Failed to delete patient', 500);
        } catch (\Exception $e) {
            error_log("PatientViewModel::deletePatient error: " . $e->getMessage());
            $this->logFailure('delete', 'patient', $patientId, $e->getMessage(), $patientId);
            return ApiResponse::error('Failed to delete patient', 500);
        }
    }

    /**
     * Search patients (for autocomplete/quick search)
     */
    public function searchPatients(string $query, int $limit = 20): array
    {
        try {
            if (strlen($query) < 2) {
                return ApiResponse::error('Search query must be at least 2 characters', 400);
            }

            $patients = $this->repository->search($query, null, true, $limit, 0);

            $patientData = array_map(
                fn(Patient $p) => $this->formatPatientForList($p),
                $patients
            );

            // Log search operation for audit trail
            $this->logSearch(
                resourceType: 'patient',
                searchCriteria: ['query' => $query, 'limit' => $limit],
                resultCount: count($patientData),
                description: "Searched patients with query: {$query}"
            );

            return ApiResponse::success([
                'patients' => $patientData,
                'count' => count($patientData),
                'query' => $query,
            ]);
        } catch (\Exception $e) {
            error_log("PatientViewModel::searchPatients error: " . $e->getMessage());
            $this->logFailure('search', 'patient', null, $e->getMessage());
            return ApiResponse::error('Search failed', 500);
        }
    }

    /**
     * Find patient by MRN
     */
    public function getPatientByMrn(string $mrn): array
    {
        try {
            $patient = $this->repository->findByMrn($mrn);

            if (!$patient) {
                $this->logFailure('read', 'patient', null, "Patient not found by MRN: {$mrn}");
                return ApiResponse::error('Patient not found', 404);
            }

            // Log PHI access
            $this->logRead(
                resourceType: 'patient',
                resourceId: $patient->getId(),
                patientId: $patient->getId(),
                description: "Accessed patient by MRN: {$mrn}"
            );

            return ApiResponse::success([
                'patient' => $this->formatPatientForDetail($patient),
            ]);
        } catch (\Exception $e) {
            error_log("PatientViewModel::getPatientByMrn error: " . $e->getMessage());
            $this->logFailure('read', 'patient', null, $e->getMessage());
            return ApiResponse::error('Failed to retrieve patient', 500);
        }
    }

    /**
     * Get patients by employer
     */
    public function getPatientsByEmployer(string $employerId, int $page = 1, int $perPage = 50): array
    {
        return $this->getPatients(['employer_id' => $employerId], $page, $perPage);
    }

    /**
     * Reactivate a deactivated patient
     */
    public function reactivatePatient(string $patientId): array
    {
        try {
            $success = $this->repository->activate($patientId);

            if ($success) {
                $patient = $this->repository->findById($patientId);
                
                // Log reactivation as an update operation
                $this->logUpdate(
                    resourceType: 'patient',
                    resourceId: $patientId,
                    oldValues: ['active' => false],
                    newValues: ['active' => true],
                    modifiedFields: ['active'],
                    patientId: $patientId,
                    description: "Reactivated patient: {$patient->getFullName()}"
                );

                return ApiResponse::success([
                    'patient' => $this->formatPatientForDetail($patient),
                    'message' => 'Patient reactivated successfully',
                ]);
            }

            $this->logFailure('update', 'patient', $patientId, 'Failed to reactivate patient', $patientId);
            return ApiResponse::error('Failed to reactivate patient', 500);
        } catch (\Exception $e) {
            error_log("PatientViewModel::reactivatePatient error: " . $e->getMessage());
            $this->logFailure('update', 'patient', $patientId, $e->getMessage(), $patientId);
            return ApiResponse::error('Failed to reactivate patient', 500);
        }
    }

    /**
     * Get recent patients
     */
    public function getRecentPatients(int $days = 7, int $limit = 50): array
    {
        try {
            $patients = $this->repository->getRecentPatients($days, $limit);

            $patientData = array_map(
                fn(Patient $p) => $this->formatPatientForList($p),
                $patients
            );

            return ApiResponse::success([
                'patients' => $patientData,
                'count' => count($patientData),
                'period_days' => $days,
            ]);
        } catch (\Exception $e) {
            error_log("PatientViewModel::getRecentPatients error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve recent patients', 500);
        }
    }

    /**
     * Validate patient data
     */
    private function validatePatientData(array $data, bool $isCreate = true): array
    {
        $errors = [];

        // Required fields for creation
        if ($isCreate) {
            if (empty($data['first_name']) && empty($data['firstName'])) {
                $errors['first_name'] = 'First name is required';
            }
            if (empty($data['last_name']) && empty($data['lastName'])) {
                $errors['last_name'] = 'Last name is required';
            }
            if (empty($data['date_of_birth']) && empty($data['dateOfBirth'])) {
                $errors['date_of_birth'] = 'Date of birth is required';
            }
        }

        // Validate email if provided
        $email = $data['email'] ?? null;
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        // Validate date of birth
        $dob = $data['date_of_birth'] ?? $data['dateOfBirth'] ?? null;
        if ($dob) {
            try {
                $dobDate = new DateTimeImmutable($dob);
                if ($dobDate > new DateTimeImmutable()) {
                    $errors['date_of_birth'] = 'Date of birth cannot be in the future';
                }
            } catch (\Exception $e) {
                $errors['date_of_birth'] = 'Invalid date format';
            }
        }

        // Validate gender
        $gender = $data['gender'] ?? null;
        if ($gender && !in_array(strtoupper($gender), ['M', 'F', 'O', 'U'])) {
            $errors['gender'] = 'Invalid gender value (must be M, F, O, or U)';
        }

        // Validate SSN if provided
        $ssn = $data['ssn'] ?? null;
        if ($ssn) {
            $ssnClean = preg_replace('/[^0-9]/', '', $ssn);
            if (strlen($ssnClean) !== 9) {
                $errors['ssn'] = 'SSN must be 9 digits';
            }
        }

        return $errors;
    }

    /**
     * Check for duplicate patient
     */
    private function checkForDuplicate(array $data): ?Patient
    {
        // Check by SSN last four + DOB
        $ssn = $data['ssn'] ?? null;
        $dob = $data['date_of_birth'] ?? $data['dateOfBirth'] ?? null;
        
        if ($ssn && $dob) {
            $ssnClean = preg_replace('/[^0-9]/', '', $ssn);
            $ssnLastFour = substr($ssnClean, -4);
            $dobDate = (new DateTimeImmutable($dob))->format('Y-m-d');
            
            $existing = $this->repository->findBySsnAndDob($ssnLastFour, $dobDate);
            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * Create Patient entity from input data
     */
    private function hydratePatientFromData(array $data): Patient
    {
        $firstName = $data['first_name'] ?? $data['firstName'] ?? '';
        $lastName = $data['last_name'] ?? $data['lastName'] ?? '';
        $dob = $data['date_of_birth'] ?? $data['dateOfBirth'] ?? '';
        $gender = $data['gender'] ?? 'U';

        $patient = new Patient(
            $firstName,
            $lastName,
            new DateTimeImmutable($dob),
            $gender
        );

        return $this->updatePatientFromData($patient, $data);
    }

    /**
     * Update Patient entity from input data
     */
    private function updatePatientFromData(Patient $patient, array $data): Patient
    {
        // Basic info
        if (isset($data['first_name']) || isset($data['firstName'])) {
            $patient->setFirstName($data['first_name'] ?? $data['firstName']);
        }
        if (isset($data['last_name']) || isset($data['lastName'])) {
            $patient->setLastName($data['last_name'] ?? $data['lastName']);
        }
        if (isset($data['middle_name']) || isset($data['middleName'])) {
            $patient->setMiddleName($data['middle_name'] ?? $data['middleName']);
        }
        if (isset($data['date_of_birth']) || isset($data['dateOfBirth'])) {
            $dob = $data['date_of_birth'] ?? $data['dateOfBirth'];
            $patient->setDateOfBirth(new DateTimeImmutable($dob));
        }
        if (isset($data['gender'])) {
            $patient->setGender($data['gender']);
        }

        // SSN
        if (!empty($data['ssn'])) {
            $ssnClean = preg_replace('/[^0-9]/', '', $data['ssn']);
            $patient->setSsn(new SSN($ssnClean));
        }

        // Contact info
        if (isset($data['email'])) {
            $patient->setEmail($data['email'] ? new Email($data['email']) : null);
        }
        if (isset($data['primary_phone']) || isset($data['primaryPhone']) || isset($data['phone'])) {
            $phone = $data['primary_phone'] ?? $data['primaryPhone'] ?? $data['phone'];
            $patient->setPrimaryPhone($phone ? new PhoneNumber($phone) : null);
        }
        if (isset($data['secondary_phone']) || isset($data['secondaryPhone'])) {
            $phone = $data['secondary_phone'] ?? $data['secondaryPhone'];
            $patient->setSecondaryPhone($phone ? new PhoneNumber($phone) : null);
        }

        // Address
        $patient->setAddress(
            $data['address_line_1'] ?? $data['addressLine1'] ?? $data['address'] ?? null,
            $data['address_line_2'] ?? $data['addressLine2'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['zip_code'] ?? $data['zipCode'] ?? $data['zip'] ?? null,
            $data['country'] ?? 'US'
        );

        // Employer
        if (isset($data['employer_id']) || isset($data['employerId'])) {
            $patient->setEmployer(
                $data['employer_id'] ?? $data['employerId'],
                $data['employer_name'] ?? $data['employerName'] ?? null
            );
        }

        // Emergency contact
        if (isset($data['emergency_contact_name']) || isset($data['emergencyContactName'])) {
            $ecPhone = $data['emergency_contact_phone'] ?? $data['emergencyContactPhone'] ?? null;
            $patient->setEmergencyContact(
                $data['emergency_contact_name'] ?? $data['emergencyContactName'],
                $ecPhone ? new PhoneNumber($ecPhone) : null,
                $data['emergency_contact_relation'] ?? $data['emergencyContactRelation'] ?? null
            );
        }

        // Insurance
        if (isset($data['insurance_info']) || isset($data['insuranceInfo'])) {
            $insurance = $data['insurance_info'] ?? $data['insuranceInfo'];
            if (is_string($insurance)) {
                $insurance = json_decode($insurance, true) ?? [];
            }
            $patient->setInsuranceInfo($insurance);
        }

        return $patient;
    }

    /**
     * Format patient for list view (minimal PHI)
     */
    private function formatPatientForList(Patient $patient): array
    {
        return [
            'id' => $patient->getId(),
            'patient_id' => $patient->getId(),
            'first_name' => $patient->getFirstName(),
            'firstName' => $patient->getFirstName(),
            'last_name' => $patient->getLastName(),
            'lastName' => $patient->getLastName(),
            'full_name' => $patient->getFullName(),
            'date_of_birth' => $patient->getDateOfBirth()->format('Y-m-d'),
            'dateOfBirth' => $patient->getDateOfBirth()->format('Y-m-d'),
            'age' => $patient->getAge(),
            'gender' => $patient->getGender(),
            'gender_display' => $patient->getGenderDisplay(),
            'mrn' => $patient->getMrn(),
            'ssn_last_four' => $patient->getSsnLastFour(),
            'employer_id' => $patient->getEmployerId(),
            'employerId' => $patient->getEmployerId(),
            'employer_name' => $patient->getEmployerName(),
            'employerName' => $patient->getEmployerName(),
            'active' => $patient->isActive(),
            'created_at' => $patient->getCreatedAt()?->format('Y-m-d\TH:i:s.000\Z'),
            'createdAt' => $patient->getCreatedAt()?->format('Y-m-d\TH:i:s.000\Z'),
        ];
    }

    /**
     * Format patient for detail view (full data, masked SSN)
     */
    private function formatPatientForDetail(Patient $patient): array
    {
        $data = $patient->toArray();
        
        // Add aliases for frontend compatibility
        return array_merge($data, [
            'id' => $data['patient_id'],
            'firstName' => $data['first_name'],
            'lastName' => $data['last_name'],
            'middleName' => $data['middle_name'],
            'fullName' => $data['full_name'],
            'dateOfBirth' => $data['date_of_birth'],
            'genderDisplay' => $data['gender_display'],
            'ssnMasked' => $data['ssn_masked'],
            'ssnLastFour' => $data['ssn_last_four'],
            'primaryPhone' => $data['primary_phone'],
            'secondaryPhone' => $data['secondary_phone'],
            'addressLine1' => $data['address_line_1'],
            'addressLine2' => $data['address_line_2'],
            'zipCode' => $data['zip_code'],
            'employerId' => $data['employer_id'],
            'employerName' => $data['employer_name'],
            'clinicId' => $data['clinic_id'],
            'emergencyContactName' => $data['emergency_contact_name'],
            'emergencyContactPhone' => $data['emergency_contact_phone'],
            'emergencyContactRelation' => $data['emergency_contact_relation'],
            'insuranceInfo' => $data['insurance_info'],
            'activeStatus' => $data['active_status'],
            'active' => $data['active_status'],
            'createdAt' => $data['created_at'],
            'updatedAt' => $data['updated_at'],
        ]);
    }
}
