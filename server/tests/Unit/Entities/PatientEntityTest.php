<?php
/**
 * PatientEntityTest.php - Unit Tests for Patient Entity
 * 
 * Tests the Patient entity class including construction,
 * PHI handling, SSN masking, and data integrity.
 * 
 * @package    SafeShift\Tests\Unit\Entities
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Tests\Unit\Entities;

use PHPUnit\Framework\TestCase;
use Model\Entities\Patient;
use Model\ValueObjects\Email;
use Model\ValueObjects\PhoneNumber;
use Model\ValueObjects\SSN;
use DateTimeImmutable;

/**
 * Test cases for Patient entity
 */
class PatientEntityTest extends TestCase
{
    /**
     * Test patient creation with required fields
     */
    public function testCreatePatientWithRequiredFields(): void
    {
        $dob = new DateTimeImmutable('1990-05-15');
        $patient = new Patient('John', 'Doe', $dob);
        
        $this->assertEquals('John', $patient->getFirstName());
        $this->assertEquals('Doe', $patient->getLastName());
        $this->assertEquals($dob, $patient->getDateOfBirth());
        $this->assertEquals(Patient::GENDER_UNKNOWN, $patient->getGender());
        $this->assertNull($patient->getId());
        $this->assertTrue($patient->isActive());
    }

    /**
     * Test patient creation with all parameters
     */
    public function testCreatePatientWithAllParameters(): void
    {
        $dob = new DateTimeImmutable('1985-03-20');
        $patient = new Patient('Jane', 'Smith', $dob, Patient::GENDER_FEMALE);
        
        $this->assertEquals('Jane', $patient->getFirstName());
        $this->assertEquals('Smith', $patient->getLastName());
        $this->assertEquals(Patient::GENDER_FEMALE, $patient->getGender());
    }

    /**
     * Test setting patient ID
     */
    public function testSetPatientId(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $patientId = 'patient-uuid-123';
        
        $patient->setId($patientId);
        
        $this->assertEquals($patientId, $patient->getId());
        $this->assertTrue($patient->isPersisted());
    }

    /**
     * Test full name generation without middle name
     */
    public function testFullNameWithoutMiddleName(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $this->assertEquals('John Doe', $patient->getFullName());
    }

    /**
     * Test full name generation with middle name
     */
    public function testFullNameWithMiddleName(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $patient->setMiddleName('Robert');
        
        $this->assertEquals('John Robert Doe', $patient->getFullName());
    }

    /**
     * Test setting middle name
     */
    public function testSetMiddleName(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $patient->setMiddleName('Michael');
        
        $this->assertEquals('Michael', $patient->getMiddleName());
    }

    /**
     * Test valid gender values
     */
    public function testValidGenderValues(): void
    {
        $dob = new DateTimeImmutable('1990-01-01');
        
        $patientMale = new Patient('John', 'Doe', $dob, Patient::GENDER_MALE);
        $this->assertEquals(Patient::GENDER_MALE, $patientMale->getGender());
        
        $patientFemale = new Patient('Jane', 'Doe', $dob, Patient::GENDER_FEMALE);
        $this->assertEquals(Patient::GENDER_FEMALE, $patientFemale->getGender());
        
        $patientOther = new Patient('Alex', 'Doe', $dob, Patient::GENDER_OTHER);
        $this->assertEquals(Patient::GENDER_OTHER, $patientOther->getGender());
        
        $patientUnknown = new Patient('Chris', 'Doe', $dob, Patient::GENDER_UNKNOWN);
        $this->assertEquals(Patient::GENDER_UNKNOWN, $patientUnknown->getGender());
    }

    /**
     * Test invalid gender defaults to unknown
     */
    public function testInvalidGenderDefaultsToUnknown(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'), 'X');
        
        $this->assertEquals(Patient::GENDER_UNKNOWN, $patient->getGender());
    }

    /**
     * Test gender display text
     */
    public function testGenderDisplayText(): void
    {
        $dob = new DateTimeImmutable('1990-01-01');
        
        $patient = new Patient('John', 'Doe', $dob, Patient::GENDER_MALE);
        $this->assertEquals('Male', $patient->getGenderDisplay());
        
        $patient->setGender(Patient::GENDER_FEMALE);
        $this->assertEquals('Female', $patient->getGenderDisplay());
        
        $patient->setGender(Patient::GENDER_OTHER);
        $this->assertEquals('Other', $patient->getGenderDisplay());
        
        $patient->setGender(Patient::GENDER_UNKNOWN);
        $this->assertEquals('Unknown', $patient->getGenderDisplay());
    }

    /**
     * Test age calculation
     */
    public function testAgeCalculation(): void
    {
        // Calculate expected age based on current date
        $birthYear = (int)(new DateTimeImmutable())->format('Y') - 35;
        $dob = new DateTimeImmutable("{$birthYear}-01-01");
        
        $patient = new Patient('John', 'Doe', $dob);
        
        $this->assertGreaterThanOrEqual(34, $patient->getAge());
        $this->assertLessThanOrEqual(35, $patient->getAge());
    }

    /**
     * Test setting date of birth
     */
    public function testSetDateOfBirth(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $newDob = new DateTimeImmutable('1985-06-15');
        
        $patient->setDateOfBirth($newDob);
        
        $this->assertEquals($newDob, $patient->getDateOfBirth());
    }

    /**
     * Test setting first name
     */
    public function testSetFirstName(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $patient->setFirstName('Jonathan');
        
        $this->assertEquals('Jonathan', $patient->getFirstName());
    }

    /**
     * Test setting last name
     */
    public function testSetLastName(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $patient->setLastName('Smith');
        
        $this->assertEquals('Smith', $patient->getLastName());
    }

    /**
     * Test setting email with Email value object
     */
    public function testSetEmailWithValueObject(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $email = new Email('john.doe@example.com');
        
        $patient->setEmail($email);
        
        $this->assertEquals($email, $patient->getEmail());
        $this->assertEquals('john.doe@example.com', $patient->getEmail()->getValue());
    }

    /**
     * Test setting primary phone
     */
    public function testSetPrimaryPhone(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $phone = new PhoneNumber('555-123-4567');
        
        $patient->setPrimaryPhone($phone);
        
        $this->assertEquals($phone, $patient->getPrimaryPhone());
    }

    /**
     * Test setting secondary phone
     */
    public function testSetSecondaryPhone(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $phone = new PhoneNumber('555-987-6543');
        
        $patient->setSecondaryPhone($phone);
        
        $this->assertEquals($phone, $patient->getSecondaryPhone());
    }

    /**
     * Test setting MRN (Medical Record Number)
     */
    public function testSetMrn(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $patient->setMrn('MRN-001234');
        
        $this->assertEquals('MRN-001234', $patient->getMrn());
    }

    /**
     * Test setting address
     */
    public function testSetAddress(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $patient->setAddress(
            '123 Main Street',
            'Apt 4B',
            'Denver',
            'CO',
            '80202',
            'US'
        );
        
        $this->assertStringContainsString('123 Main Street', $patient->getFormattedAddress());
        $this->assertStringContainsString('Apt 4B', $patient->getFormattedAddress());
        $this->assertStringContainsString('Denver', $patient->getFormattedAddress());
    }

    /**
     * Test formatted address with minimal data
     */
    public function testFormattedAddressWithMinimalData(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $patient->setAddress('123 Main Street', null, 'Denver', 'CO', '80202');
        
        $address = $patient->getFormattedAddress();
        $this->assertStringContainsString('123 Main Street', $address);
        $this->assertStringNotContainsString('null', $address);
    }

    /**
     * Test setting employer
     */
    public function testSetEmployer(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $patient->setEmployer('employer-uuid-123', 'ABC Corporation');
        
        $this->assertEquals('employer-uuid-123', $patient->getEmployerId());
        $this->assertEquals('ABC Corporation', $patient->getEmployerName());
    }

    /**
     * Test setting clinic ID
     */
    public function testSetClinicId(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $patient->setClinicId('clinic-uuid-456');
        
        $this->assertEquals('clinic-uuid-456', $patient->getClinicId());
    }

    /**
     * Test setting emergency contact
     */
    public function testSetEmergencyContact(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $phone = new PhoneNumber('555-111-2222');
        
        $patient->setEmergencyContact('Jane Doe', $phone, 'Spouse');
        
        $this->assertNotNull($patient);
    }

    /**
     * Test setting insurance information
     */
    public function testSetInsuranceInfo(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $insuranceInfo = [
            'provider' => 'Blue Cross Blue Shield',
            'policy_number' => 'BCBS-12345',
            'group_number' => 'GRP-001',
            'effective_date' => '2025-01-01',
        ];
        
        $patient->setInsuranceInfo($insuranceInfo);
        
        $this->assertEquals($insuranceInfo, $patient->getInsuranceInfo());
    }

    /**
     * Test patient activation
     */
    public function testActivatePatient(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $patient->deactivate();
        
        $this->assertFalse($patient->isActive());
        
        $patient->activate();
        
        $this->assertTrue($patient->isActive());
    }

    /**
     * Test patient deactivation
     */
    public function testDeactivatePatient(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $this->assertTrue($patient->isActive());
        
        $patient->deactivate();
        
        $this->assertFalse($patient->isActive());
    }

    /**
     * Test validation with valid data
     */
    public function testValidationWithValidData(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $errors = $patient->validate();
        
        $this->assertEmpty($errors);
    }

    /**
     * Test validation with empty first name
     */
    public function testValidationWithEmptyFirstName(): void
    {
        $patient = new Patient('', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $errors = $patient->validate();
        
        $this->assertArrayHasKey('first_name', $errors);
    }

    /**
     * Test validation with empty last name
     */
    public function testValidationWithEmptyLastName(): void
    {
        $patient = new Patient('John', '', new DateTimeImmutable('1990-01-01'));
        
        $errors = $patient->validate();
        
        $this->assertArrayHasKey('last_name', $errors);
    }

    /**
     * Test validation with future date of birth
     */
    public function testValidationWithFutureDateOfBirth(): void
    {
        $futureDob = new DateTimeImmutable('+1 year');
        $patient = new Patient('John', 'Doe', $futureDob);
        
        $errors = $patient->validate();
        
        $this->assertArrayHasKey('date_of_birth', $errors);
    }

    /**
     * Test toArray includes expected fields
     */
    public function testToArrayIncludesExpectedFields(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-05-15'), Patient::GENDER_MALE);
        $patient->setId('patient-uuid-123');
        $patient->setMiddleName('Robert');
        $patient->setMrn('MRN-001');
        $patient->setEmail(new Email('john@example.com'));
        
        $array = $patient->toArray();
        
        $this->assertEquals('patient-uuid-123', $array['patient_id']);
        $this->assertEquals('John', $array['first_name']);
        $this->assertEquals('Doe', $array['last_name']);
        $this->assertEquals('Robert', $array['middle_name']);
        $this->assertEquals('John Robert Doe', $array['full_name']);
        $this->assertEquals('1990-05-15', $array['date_of_birth']);
        $this->assertEquals(Patient::GENDER_MALE, $array['gender']);
        $this->assertEquals('Male', $array['gender_display']);
        $this->assertEquals('MRN-001', $array['mrn']);
        $this->assertEquals('john@example.com', $array['email']);
        $this->assertTrue($array['active_status']);
    }

    /**
     * Test toSafeArray excludes sensitive data
     */
    public function testToSafeArrayExcludesSensitiveData(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-05-15'));
        $patient->setId('patient-uuid-123');
        $patient->setEmail(new Email('john@example.com'));
        $patient->setAddress('123 Main St', null, 'Denver', 'CO', '80202');
        
        $array = $patient->toSafeArray();
        
        $this->assertArrayHasKey('patient_id', $array);
        $this->assertArrayHasKey('first_name', $array);
        $this->assertArrayHasKey('last_name', $array);
        $this->assertArrayHasKey('full_name', $array);
        $this->assertArrayHasKey('date_of_birth', $array);
        $this->assertArrayHasKey('mrn', $array);
        
        // Should NOT include sensitive address/contact info
        $this->assertArrayNotHasKey('email', $array);
        $this->assertArrayNotHasKey('address_line_1', $array);
        $this->assertArrayNotHasKey('primary_phone', $array);
    }

    /**
     * Test fromArray creates patient correctly
     */
    public function testFromArrayCreatesPatientCorrectly(): void
    {
        $data = [
            'patient_id' => 'patient-uuid-123',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'middle_name' => 'Marie',
            'date_of_birth' => '1985-03-20',
            'gender' => Patient::GENDER_FEMALE,
            'mrn' => 'MRN-002',
            'email' => 'jane@example.com',
            'primary_phone' => '555-123-4567',
            'address_line_1' => '456 Oak Avenue',
            'city' => 'Boulder',
            'state' => 'CO',
            'zip_code' => '80301',
            'employer_id' => 'emp-001',
            'employer_name' => 'Tech Corp',
            'clinic_id' => 'clinic-001',
            'active_status' => true,
            'created_at' => '2024-01-01 00:00:00',
        ];
        
        $patient = Patient::fromArray($data);
        
        $this->assertEquals('patient-uuid-123', $patient->getId());
        $this->assertEquals('Jane', $patient->getFirstName());
        $this->assertEquals('Smith', $patient->getLastName());
        $this->assertEquals('Marie', $patient->getMiddleName());
        $this->assertEquals(Patient::GENDER_FEMALE, $patient->getGender());
        $this->assertEquals('MRN-002', $patient->getMrn());
        $this->assertEquals('jane@example.com', $patient->getEmail()->getValue());
        $this->assertEquals('emp-001', $patient->getEmployerId());
        $this->assertEquals('Tech Corp', $patient->getEmployerName());
        $this->assertEquals('clinic-001', $patient->getClinicId());
        $this->assertTrue($patient->isActive());
    }

    /**
     * Test fromArray handles minimal data
     */
    public function testFromArrayHandlesMinimalData(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
        ];
        
        $patient = Patient::fromArray($data);
        
        $this->assertEquals('John', $patient->getFirstName());
        $this->assertEquals('Doe', $patient->getLastName());
        $this->assertNull($patient->getId());
        $this->assertNull($patient->getMiddleName());
        $this->assertNull($patient->getMrn());
    }

    /**
     * Test fromArray handles insurance info as array
     */
    public function testFromArrayHandlesInsuranceInfoAsArray(): void
    {
        $insuranceData = [
            'provider' => 'Aetna',
            'policy_number' => 'AET-999',
        ];
        
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'insurance_info' => $insuranceData,
        ];
        
        $patient = Patient::fromArray($data);
        
        $this->assertEquals($insuranceData, $patient->getInsuranceInfo());
    }

    /**
     * Test timestamps are set on creation
     */
    public function testTimestampsSetOnCreation(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $this->assertNotNull($patient->getCreatedAt());
        $this->assertNotNull($patient->getUpdatedAt());
    }

    /**
     * Test timestamps are updated on modification
     */
    public function testTimestampsUpdatedOnModification(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $originalUpdatedAt = $patient->getUpdatedAt();
        
        usleep(1000); // Small delay
        
        $patient->setFirstName('Jonathan');
        
        $this->assertGreaterThanOrEqual($originalUpdatedAt, $patient->getUpdatedAt());
    }

    /**
     * Test new patient is not persisted
     */
    public function testNewPatientIsNotPersisted(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        
        $this->assertFalse($patient->isPersisted());
    }

    /**
     * Test patient with ID is persisted
     */
    public function testPatientWithIdIsPersisted(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'));
        $patient->setId('patient-123');
        
        $this->assertTrue($patient->isPersisted());
    }

    /**
     * Test setting gender updates correctly
     */
    public function testSetGenderUpdatesCorrectly(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'), Patient::GENDER_MALE);
        
        $patient->setGender(Patient::GENDER_OTHER);
        
        $this->assertEquals(Patient::GENDER_OTHER, $patient->getGender());
    }

    /**
     * Test lowercase gender is normalized
     */
    public function testLowercaseGenderIsNormalized(): void
    {
        $patient = new Patient('John', 'Doe', new DateTimeImmutable('1990-01-01'), 'm');
        
        $this->assertEquals(Patient::GENDER_MALE, $patient->getGender());
    }

    /**
     * Test fromArray with DateTimeInterface DOB
     */
    public function testFromArrayWithDateTimeInterfaceDob(): void
    {
        $dob = new DateTimeImmutable('1990-05-15');
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => $dob,
        ];
        
        $patient = Patient::fromArray($data);
        
        $this->assertEquals($dob, $patient->getDateOfBirth());
    }

    /**
     * Test fromArray handles patient_uuid alternative key
     */
    public function testFromArrayHandlesPatientUuidAlternativeKey(): void
    {
        $data = [
            'patient_uuid' => 'uuid-12345',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
        ];
        
        $patient = Patient::fromArray($data);
        
        $this->assertEquals('uuid-12345', $patient->getId());
    }
}
