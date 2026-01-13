<?php
/**
 * Patient.php - Patient Entity for SafeShift EHR
 * 
 * Represents a patient with PHI (Protected Health Information) handling.
 * SSN is always encrypted at rest and masked in display.
 * 
 * @package    SafeShift\Model\Entities
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Entities;

use Model\Interfaces\EntityInterface;
use Model\ValueObjects\Email;
use Model\ValueObjects\PhoneNumber;
use Model\ValueObjects\SSN;
use Model\ValueObjects\UUID;
use DateTimeInterface;
use DateTimeImmutable;

/**
 * Patient entity
 * 
 * Represents a patient in the SafeShift EHR system.
 * Implements HIPAA-compliant PHI handling with SSN encryption
 * and data masking capabilities.
 */
class Patient implements EntityInterface
{
    /** @var string|null Patient unique identifier (UUID) */
    protected ?string $patientId;
    
    /** @var string Patient's first name */
    protected string $firstName;
    
    /** @var string Patient's last name */
    protected string $lastName;
    
    /** @var string|null Patient's middle name */
    protected ?string $middleName;
    
    /** @var DateTimeInterface Date of birth */
    protected DateTimeInterface $dateOfBirth;
    
    /** @var string Gender (M, F, O, U) */
    protected string $gender;
    
    /** @var SSN|null Social Security Number (encrypted) */
    protected ?SSN $ssn;
    
    /** @var string|null Medical Record Number */
    protected ?string $mrn;
    
    /** @var Email|null Patient email */
    protected ?Email $email;
    
    /** @var PhoneNumber|null Primary phone */
    protected ?PhoneNumber $primaryPhone;
    
    /** @var PhoneNumber|null Secondary phone */
    protected ?PhoneNumber $secondaryPhone;
    
    /** @var string|null Street address line 1 */
    protected ?string $addressLine1;
    
    /** @var string|null Street address line 2 */
    protected ?string $addressLine2;
    
    /** @var string|null City */
    protected ?string $city;
    
    /** @var string|null State (2-letter code) */
    protected ?string $state;
    
    /** @var string|null ZIP code */
    protected ?string $zipCode;
    
    /** @var string|null Country */
    protected ?string $country;
    
    /** @var string|null Employer ID */
    protected ?string $employerId;
    
    /** @var string|null Employer name */
    protected ?string $employerName;
    
    /** @var string|null Clinic ID this patient belongs to */
    protected ?string $clinicId;
    
    /** @var string|null Emergency contact name */
    protected ?string $emergencyContactName;
    
    /** @var PhoneNumber|null Emergency contact phone */
    protected ?PhoneNumber $emergencyContactPhone;
    
    /** @var string|null Emergency contact relationship */
    protected ?string $emergencyContactRelation;
    
    /** @var array<string, mixed> Insurance information */
    protected array $insuranceInfo;
    
    /** @var bool Patient active status */
    protected bool $activeStatus;
    
    /** @var DateTimeInterface|null Entity creation timestamp */
    protected ?DateTimeInterface $createdAt;
    
    /** @var DateTimeInterface|null Entity update timestamp */
    protected ?DateTimeInterface $updatedAt;
    
    /** @var string|null Created by user ID */
    protected ?string $createdBy;

    /** Gender constants */
    public const GENDER_MALE = 'M';
    public const GENDER_FEMALE = 'F';
    public const GENDER_OTHER = 'O';
    public const GENDER_UNKNOWN = 'U';

    /**
     * Create a new Patient instance
     * 
     * @param string $firstName First name
     * @param string $lastName Last name
     * @param DateTimeInterface $dateOfBirth Date of birth
     * @param string $gender Gender
     */
    public function __construct(
        string $firstName,
        string $lastName,
        DateTimeInterface $dateOfBirth,
        string $gender = self::GENDER_UNKNOWN
    ) {
        $this->patientId = null;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->middleName = null;
        $this->dateOfBirth = $dateOfBirth;
        $this->gender = $this->validateGender($gender);
        $this->ssn = null;
        $this->mrn = null;
        $this->email = null;
        $this->primaryPhone = null;
        $this->secondaryPhone = null;
        $this->addressLine1 = null;
        $this->addressLine2 = null;
        $this->city = null;
        $this->state = null;
        $this->zipCode = null;
        $this->country = 'US';
        $this->employerId = null;
        $this->employerName = null;
        $this->clinicId = null;
        $this->emergencyContactName = null;
        $this->emergencyContactPhone = null;
        $this->emergencyContactRelation = null;
        $this->insuranceInfo = [];
        $this->activeStatus = true;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->createdBy = null;
    }

    /**
     * Validate gender value
     * 
     * @param string $gender
     * @return string
     */
    private function validateGender(string $gender): string
    {
        $validGenders = [
            self::GENDER_MALE,
            self::GENDER_FEMALE,
            self::GENDER_OTHER,
            self::GENDER_UNKNOWN,
        ];
        
        $gender = strtoupper($gender);
        return in_array($gender, $validGenders, true) ? $gender : self::GENDER_UNKNOWN;
    }

    /**
     * Get the patient ID
     * 
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->patientId;
    }

    /**
     * Set the patient ID
     * 
     * @param string $patientId
     * @return self
     */
    public function setId(string $patientId): self
    {
        $this->patientId = $patientId;
        return $this;
    }

    /**
     * Get first name
     * 
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * Set first name
     * 
     * @param string $firstName
     * @return self
     */
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get last name
     * 
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * Set last name
     * 
     * @param string $lastName
     * @return self
     */
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get middle name
     * 
     * @return string|null
     */
    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    /**
     * Set middle name
     * 
     * @param string|null $middleName
     * @return self
     */
    public function setMiddleName(?string $middleName): self
    {
        $this->middleName = $middleName;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get full name
     * 
     * @return string
     */
    public function getFullName(): string
    {
        $parts = [$this->firstName];
        
        if ($this->middleName) {
            $parts[] = $this->middleName;
        }
        
        $parts[] = $this->lastName;
        
        return implode(' ', $parts);
    }

    /**
     * Get date of birth
     * 
     * @return DateTimeInterface
     */
    public function getDateOfBirth(): DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    /**
     * Set date of birth
     * 
     * @param DateTimeInterface $dateOfBirth
     * @return self
     */
    public function setDateOfBirth(DateTimeInterface $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get age in years
     * 
     * @return int
     */
    public function getAge(): int
    {
        $now = new DateTimeImmutable();
        return $this->dateOfBirth->diff($now)->y;
    }

    /**
     * Get gender
     * 
     * @return string
     */
    public function getGender(): string
    {
        return $this->gender;
    }

    /**
     * Set gender
     * 
     * @param string $gender
     * @return self
     */
    public function setGender(string $gender): self
    {
        $this->gender = $this->validateGender($gender);
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get gender display text
     * 
     * @return string
     */
    public function getGenderDisplay(): string
    {
        return match ($this->gender) {
            self::GENDER_MALE => 'Male',
            self::GENDER_FEMALE => 'Female',
            self::GENDER_OTHER => 'Other',
            default => 'Unknown',
        };
    }

    /**
     * Get SSN
     * 
     * @return SSN|null
     */
    public function getSsn(): ?SSN
    {
        return $this->ssn;
    }

    /**
     * Set SSN
     * 
     * @param SSN|null $ssn
     * @return self
     */
    public function setSsn(?SSN $ssn): self
    {
        $this->ssn = $ssn;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get masked SSN for display
     * 
     * @return string|null
     */
    public function getMaskedSsn(): ?string
    {
        return $this->ssn?->getMasked();
    }

    /**
     * Get SSN last four digits
     * 
     * @return string|null
     */
    public function getSsnLastFour(): ?string
    {
        return $this->ssn?->getLastFour();
    }

    /**
     * Get Medical Record Number
     * 
     * @return string|null
     */
    public function getMrn(): ?string
    {
        return $this->mrn;
    }

    /**
     * Set Medical Record Number
     * 
     * @param string|null $mrn
     * @return self
     */
    public function setMrn(?string $mrn): self
    {
        $this->mrn = $mrn;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get email
     * 
     * @return Email|null
     */
    public function getEmail(): ?Email
    {
        return $this->email;
    }

    /**
     * Set email
     * 
     * @param Email|null $email
     * @return self
     */
    public function setEmail(?Email $email): self
    {
        $this->email = $email;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get primary phone
     * 
     * @return PhoneNumber|null
     */
    public function getPrimaryPhone(): ?PhoneNumber
    {
        return $this->primaryPhone;
    }

    /**
     * Set primary phone
     * 
     * @param PhoneNumber|null $phone
     * @return self
     */
    public function setPrimaryPhone(?PhoneNumber $phone): self
    {
        $this->primaryPhone = $phone;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get secondary phone
     * 
     * @return PhoneNumber|null
     */
    public function getSecondaryPhone(): ?PhoneNumber
    {
        return $this->secondaryPhone;
    }

    /**
     * Set secondary phone
     * 
     * @param PhoneNumber|null $phone
     * @return self
     */
    public function setSecondaryPhone(?PhoneNumber $phone): self
    {
        $this->secondaryPhone = $phone;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Set full address
     * 
     * @param string|null $line1
     * @param string|null $line2
     * @param string|null $city
     * @param string|null $state
     * @param string|null $zipCode
     * @param string|null $country
     * @return self
     */
    public function setAddress(
        ?string $line1,
        ?string $line2,
        ?string $city,
        ?string $state,
        ?string $zipCode,
        ?string $country = 'US'
    ): self {
        $this->addressLine1 = $line1;
        $this->addressLine2 = $line2;
        $this->city = $city;
        $this->state = $state;
        $this->zipCode = $zipCode;
        $this->country = $country;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get formatted address
     * 
     * @return string
     */
    public function getFormattedAddress(): string
    {
        $parts = [];
        
        if ($this->addressLine1) {
            $parts[] = $this->addressLine1;
        }
        
        if ($this->addressLine2) {
            $parts[] = $this->addressLine2;
        }
        
        $cityLine = array_filter([$this->city, $this->state, $this->zipCode]);
        if (!empty($cityLine)) {
            $parts[] = implode(', ', array_slice($cityLine, 0, 2)) . ' ' . ($this->zipCode ?? '');
        }
        
        return implode("\n", $parts);
    }

    /**
     * Get employer ID
     * 
     * @return string|null
     */
    public function getEmployerId(): ?string
    {
        return $this->employerId;
    }

    /**
     * Set employer
     * 
     * @param string|null $employerId
     * @param string|null $employerName
     * @return self
     */
    public function setEmployer(?string $employerId, ?string $employerName = null): self
    {
        $this->employerId = $employerId;
        $this->employerName = $employerName;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get employer name
     * 
     * @return string|null
     */
    public function getEmployerName(): ?string
    {
        return $this->employerName;
    }

    /**
     * Get clinic ID
     * 
     * @return string|null
     */
    public function getClinicId(): ?string
    {
        return $this->clinicId;
    }

    /**
     * Set clinic ID
     * 
     * @param string|null $clinicId
     * @return self
     */
    public function setClinicId(?string $clinicId): self
    {
        $this->clinicId = $clinicId;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Set emergency contact
     * 
     * @param string|null $name
     * @param PhoneNumber|null $phone
     * @param string|null $relation
     * @return self
     */
    public function setEmergencyContact(
        ?string $name,
        ?PhoneNumber $phone,
        ?string $relation
    ): self {
        $this->emergencyContactName = $name;
        $this->emergencyContactPhone = $phone;
        $this->emergencyContactRelation = $relation;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get insurance information
     * 
     * @return array<string, mixed>
     */
    public function getInsuranceInfo(): array
    {
        return $this->insuranceInfo;
    }

    /**
     * Set insurance information
     * 
     * @param array<string, mixed> $info
     * @return self
     */
    public function setInsuranceInfo(array $info): self
    {
        $this->insuranceInfo = $info;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if patient is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->activeStatus;
    }

    /**
     * Activate patient
     * 
     * @return self
     */
    public function activate(): self
    {
        $this->activeStatus = true;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Deactivate patient
     * 
     * @return self
     */
    public function deactivate(): self
    {
        $this->activeStatus = false;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if entity has been persisted
     * 
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->patientId !== null;
    }

    /**
     * Get creation timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Get update timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Validate entity data
     * 
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->firstName)) {
            $errors['first_name'] = 'First name is required';
        }
        
        if (empty($this->lastName)) {
            $errors['last_name'] = 'Last name is required';
        }
        
        if ($this->dateOfBirth > new DateTimeImmutable()) {
            $errors['date_of_birth'] = 'Date of birth cannot be in the future';
        }
        
        return $errors;
    }

    /**
     * Convert to array (includes PHI but masks SSN)
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patientId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'middle_name' => $this->middleName,
            'full_name' => $this->getFullName(),
            'date_of_birth' => $this->dateOfBirth->format('Y-m-d'),
            'age' => $this->getAge(),
            'gender' => $this->gender,
            'gender_display' => $this->getGenderDisplay(),
            'ssn_masked' => $this->getMaskedSsn(),
            'ssn_last_four' => $this->getSsnLastFour(),
            'mrn' => $this->mrn,
            'email' => $this->email?->getValue(),
            'primary_phone' => $this->primaryPhone?->getFormatted(),
            'secondary_phone' => $this->secondaryPhone?->getFormatted(),
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'country' => $this->country,
            'employer_id' => $this->employerId,
            'employer_name' => $this->employerName,
            'clinic_id' => $this->clinicId,
            'emergency_contact_name' => $this->emergencyContactName,
            'emergency_contact_phone' => $this->emergencyContactPhone?->getFormatted(),
            'emergency_contact_relation' => $this->emergencyContactRelation,
            'insurance_info' => $this->insuranceInfo,
            'active_status' => $this->activeStatus,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert to safe array (minimal PHI for lists/search)
     * 
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'patient_id' => $this->patientId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'date_of_birth' => $this->dateOfBirth->format('Y-m-d'),
            'age' => $this->getAge(),
            'gender' => $this->gender,
            'mrn' => $this->mrn,
            'ssn_last_four' => $this->getSsnLastFour(),
            'employer_name' => $this->employerName,
            'active_status' => $this->activeStatus,
        ];
    }

    /**
     * Create from array data
     * 
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        // Handle different column names from database: 'dob' or 'date_of_birth'
        // Also handle 'legal_first_name'/'legal_last_name' vs 'first_name'/'last_name'
        $dobValue = $data['date_of_birth'] ?? $data['dob'] ?? null;
        
        if ($dobValue instanceof DateTimeInterface) {
            $dob = $dobValue;
        } elseif ($dobValue !== null) {
            $dob = new DateTimeImmutable($dobValue);
        } else {
            // Default to a placeholder date if not provided
            $dob = new DateTimeImmutable('1900-01-01');
        }
        
        // Get first/last name - support both database column names and entity names
        $firstName = $data['first_name'] ?? $data['legal_first_name'] ?? '';
        $lastName = $data['last_name'] ?? $data['legal_last_name'] ?? '';
        
        // Get gender - support both 'gender' and 'sex_assigned_at_birth'
        $gender = $data['gender'] ?? $data['sex_assigned_at_birth'] ?? self::GENDER_UNKNOWN;
        
        $patient = new static(
            $firstName,
            $lastName,
            $dob,
            $gender
        );
        
        if (isset($data['patient_id']) || isset($data['patient_uuid'])) {
            $patient->patientId = $data['patient_id'] ?? $data['patient_uuid'];
        }
        
        if (isset($data['middle_name'])) {
            $patient->middleName = $data['middle_name'];
        }
        
        if (isset($data['ssn']) && $data['ssn'] instanceof SSN) {
            $patient->ssn = $data['ssn'];
        } elseif (isset($data['ssn_encrypted']) && isset($data['ssn_last_four'])) {
            $patient->ssn = SSN::fromEncrypted($data['ssn_encrypted'], $data['ssn_last_four']);
        }
        
        if (isset($data['mrn'])) {
            $patient->mrn = $data['mrn'];
        }
        
        if (isset($data['email'])) {
            $patient->email = $data['email'] instanceof Email 
                ? $data['email'] 
                : new Email($data['email']);
        }
        
        if (isset($data['primary_phone'])) {
            $patient->primaryPhone = $data['primary_phone'] instanceof PhoneNumber
                ? $data['primary_phone']
                : new PhoneNumber($data['primary_phone']);
        }
        
        if (isset($data['secondary_phone'])) {
            $patient->secondaryPhone = $data['secondary_phone'] instanceof PhoneNumber
                ? $data['secondary_phone']
                : new PhoneNumber($data['secondary_phone']);
        }
        
        $patient->addressLine1 = $data['address_line_1'] ?? null;
        $patient->addressLine2 = $data['address_line_2'] ?? null;
        $patient->city = $data['city'] ?? null;
        $patient->state = $data['state'] ?? null;
        $patient->zipCode = $data['zip_code'] ?? null;
        $patient->country = $data['country'] ?? 'US';
        
        $patient->employerId = $data['employer_id'] ?? null;
        $patient->employerName = $data['employer_name'] ?? null;
        $patient->clinicId = $data['clinic_id'] ?? null;
        
        $patient->emergencyContactName = $data['emergency_contact_name'] ?? null;
        $patient->emergencyContactRelation = $data['emergency_contact_relation'] ?? null;
        
        if (isset($data['emergency_contact_phone'])) {
            $patient->emergencyContactPhone = $data['emergency_contact_phone'] instanceof PhoneNumber
                ? $data['emergency_contact_phone']
                : new PhoneNumber($data['emergency_contact_phone']);
        }
        
        if (isset($data['insurance_info'])) {
            $patient->insuranceInfo = is_array($data['insurance_info']) 
                ? $data['insurance_info'] 
                : json_decode($data['insurance_info'], true) ?? [];
        }
        
        if (isset($data['active_status'])) {
            $patient->activeStatus = (bool) $data['active_status'];
        }
        
        if (isset($data['created_at'])) {
            $patient->createdAt = new DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $patient->updatedAt = new DateTimeImmutable($data['updated_at']);
        }
        
        if (isset($data['created_by'])) {
            $patient->createdBy = $data['created_by'];
        }
        
        return $patient;
    }
}
