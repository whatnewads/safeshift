<?php
/**
 * Patient Entity
 * 
 * Represents a patient in the system
 * HIPAA-compliant patient data structure
 */

namespace Core\Entities;

class Patient
{
    private string $patient_id;
    private string $mrn;
    private string $legal_first_name;
    private string $legal_last_name;
    private ?string $preferred_name;
    private ?string $date_of_birth;
    private ?string $gender;
    private ?string $email;
    private ?string $phone;
    private ?string $address_line_1;
    private ?string $address_line_2;
    private ?string $city;
    private ?string $state;
    private ?string $zip;
    private ?string $country;
    private ?string $emergency_contact_name;
    private ?string $emergency_contact_phone;
    private ?string $emergency_contact_relationship;
    private ?string $insurance_provider;
    private ?string $insurance_policy_number;
    private ?string $employer_name;
    private ?string $employer_id;
    private bool $is_active = true;
    private ?string $created_at;
    private ?string $updated_at;
    private ?string $deleted_at;
    
    /**
     * Constructor
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    /**
     * Hydrate entity from array
     */
    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Convert entity to array
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patient_id,
            'mrn' => $this->mrn,
            'legal_first_name' => $this->legal_first_name,
            'legal_last_name' => $this->legal_last_name,
            'preferred_name' => $this->preferred_name,
            'date_of_birth' => $this->date_of_birth,
            'gender' => $this->gender,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country' => $this->country,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'insurance_provider' => $this->insurance_provider,
            'insurance_policy_number' => $this->insurance_policy_number,
            'employer_name' => $this->employer_name,
            'employer_id' => $this->employer_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at
        ];
    }
    
    /**
     * Get display name (preferred name if available, otherwise legal name)
     */
    public function getDisplayName(): string
    {
        if (!empty($this->preferred_name)) {
            return $this->preferred_name;
        }
        return trim($this->legal_first_name . ' ' . $this->legal_last_name);
    }
    
    /**
     * Get full legal name
     */
    public function getLegalName(): string
    {
        return trim($this->legal_first_name . ' ' . $this->legal_last_name);
    }
    
    // Getters
    public function getPatientId(): string { return $this->patient_id; }
    public function getMrn(): string { return $this->mrn; }
    public function getLegalFirstName(): string { return $this->legal_first_name; }
    public function getLegalLastName(): string { return $this->legal_last_name; }
    public function getPreferredName(): ?string { return $this->preferred_name; }
    public function getDateOfBirth(): ?string { return $this->date_of_birth; }
    public function getGender(): ?string { return $this->gender; }
    public function getEmail(): ?string { return $this->email; }
    public function getPhone(): ?string { return $this->phone; }
    public function getAddressLine1(): ?string { return $this->address_line_1; }
    public function getAddressLine2(): ?string { return $this->address_line_2; }
    public function getCity(): ?string { return $this->city; }
    public function getState(): ?string { return $this->state; }
    public function getZip(): ?string { return $this->zip; }
    public function getCountry(): ?string { return $this->country; }
    public function getEmergencyContactName(): ?string { return $this->emergency_contact_name; }
    public function getEmergencyContactPhone(): ?string { return $this->emergency_contact_phone; }
    public function getEmergencyContactRelationship(): ?string { return $this->emergency_contact_relationship; }
    public function getInsuranceProvider(): ?string { return $this->insurance_provider; }
    public function getInsurancePolicyNumber(): ?string { return $this->insurance_policy_number; }
    public function getEmployerName(): ?string { return $this->employer_name; }
    public function getEmployerId(): ?string { return $this->employer_id; }
    public function isActive(): bool { return $this->is_active; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }
    public function getDeletedAt(): ?string { return $this->deleted_at; }
    
    // Setters
    public function setPatientId(string $patient_id): void { $this->patient_id = $patient_id; }
    public function setMrn(string $mrn): void { $this->mrn = $mrn; }
    public function setLegalFirstName(string $legal_first_name): void { $this->legal_first_name = $legal_first_name; }
    public function setLegalLastName(string $legal_last_name): void { $this->legal_last_name = $legal_last_name; }
    public function setPreferredName(?string $preferred_name): void { $this->preferred_name = $preferred_name; }
    public function setDateOfBirth(?string $date_of_birth): void { $this->date_of_birth = $date_of_birth; }
    public function setGender(?string $gender): void { $this->gender = $gender; }
    public function setEmail(?string $email): void { $this->email = $email; }
    public function setPhone(?string $phone): void { $this->phone = $phone; }
    public function setAddressLine1(?string $address_line_1): void { $this->address_line_1 = $address_line_1; }
    public function setAddressLine2(?string $address_line_2): void { $this->address_line_2 = $address_line_2; }
    public function setCity(?string $city): void { $this->city = $city; }
    public function setState(?string $state): void { $this->state = $state; }
    public function setZip(?string $zip): void { $this->zip = $zip; }
    public function setCountry(?string $country): void { $this->country = $country; }
    public function setEmergencyContactName(?string $emergency_contact_name): void { $this->emergency_contact_name = $emergency_contact_name; }
    public function setEmergencyContactPhone(?string $emergency_contact_phone): void { $this->emergency_contact_phone = $emergency_contact_phone; }
    public function setEmergencyContactRelationship(?string $emergency_contact_relationship): void { $this->emergency_contact_relationship = $emergency_contact_relationship; }
    public function setInsuranceProvider(?string $insurance_provider): void { $this->insurance_provider = $insurance_provider; }
    public function setInsurancePolicyNumber(?string $insurance_policy_number): void { $this->insurance_policy_number = $insurance_policy_number; }
    public function setEmployerName(?string $employer_name): void { $this->employer_name = $employer_name; }
    public function setEmployerId(?string $employer_id): void { $this->employer_id = $employer_id; }
    public function setIsActive(bool $is_active): void { $this->is_active = $is_active; }
    public function setCreatedAt(?string $created_at): void { $this->created_at = $created_at; }
    public function setUpdatedAt(?string $updated_at): void { $this->updated_at = $updated_at; }
    public function setDeletedAt(?string $deleted_at): void { $this->deleted_at = $deleted_at; }
}