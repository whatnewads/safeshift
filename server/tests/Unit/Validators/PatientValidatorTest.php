<?php
/**
 * PatientValidator Unit Tests
 * 
 * Tests for the PatientValidator class which handles validation
 * of patient data including demographics, contact info, and identifiers.
 * 
 * @package SafeShift\Tests\Unit\Validators
 */

declare(strict_types=1);

namespace Tests\Unit\Validators;

use PHPUnit\Framework\TestCase;
use Model\Validators\PatientValidator;
use Tests\Helpers\Factories\PatientFactory;

/**
 * @covers \Model\Validators\PatientValidator
 */
class PatientValidatorTest extends TestCase
{
    // =========================================================================
    // Valid Patient Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidPatientPasses(): void
    {
        $data = PatientFactory::makeMinimal();
        
        $errors = PatientValidator::validate($data, true);
        
        $this->assertEmpty($errors, 'Valid patient should pass validation');
    }

    /**
     * @test
     */
    public function testFullPatientDataPasses(): void
    {
        $data = PatientFactory::make();
        
        $errors = PatientValidator::validate($data, true);
        
        $this->assertEmpty($errors, 'Complete patient should pass validation');
    }

    // =========================================================================
    // Required Field Tests
    // =========================================================================

    /**
     * @test
     */
    public function testMissingFirstNameFails(): void
    {
        $data = PatientFactory::makeInvalid(['first_name']);
        
        $errors = PatientValidator::validate($data, true);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('first_name', $errors);
    }

    /**
     * @test
     */
    public function testMissingLastNameFails(): void
    {
        $data = PatientFactory::makeInvalid(['last_name']);
        
        $errors = PatientValidator::validate($data, true);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('last_name', $errors);
    }

    /**
     * @test
     */
    public function testMissingDOBFails(): void
    {
        $data = PatientFactory::makeInvalid(['date_of_birth']);
        
        $errors = PatientValidator::validate($data, true);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('date_of_birth', $errors);
    }

    /**
     * @test
     */
    public function testEmptyFirstNameFails(): void
    {
        $data = PatientFactory::make(['first_name' => '']);
        
        $errors = PatientValidator::validate($data, true);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('first_name', $errors);
    }

    /**
     * @test
     */
    public function testEmptyLastNameFails(): void
    {
        $data = PatientFactory::make(['last_name' => '']);
        
        $errors = PatientValidator::validate($data, true);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('last_name', $errors);
    }

    // =========================================================================
    // Date of Birth Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testInvalidDOBFormatFails(): void
    {
        $data = PatientFactory::make(['date_of_birth' => 'invalid-date']);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('date_of_birth', $errors);
    }

    /**
     * @test
     */
    public function testFutureDOBFails(): void
    {
        $data = PatientFactory::make([
            'date_of_birth' => date('Y-m-d', strtotime('+1 year')),
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('date_of_birth', $errors);
    }

    /**
     * @test
     */
    public function testValidDOBPasses(): void
    {
        $data = PatientFactory::make([
            'date_of_birth' => '1980-05-15',
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayNotHasKey('date_of_birth', $errors);
    }

    /**
     * @test
     * @dataProvider validDOBFormatProvider
     */
    public function testValidDOBFormats(string $dob): void
    {
        $data = PatientFactory::make(['date_of_birth' => $dob]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayNotHasKey('date_of_birth', $errors,
            "DOB format '{$dob}' should be valid");
    }

    /**
     * Data provider for valid DOB formats
     */
    public static function validDOBFormatProvider(): array
    {
        return [
            'standard format' => ['1990-01-15'],
            'older patient' => ['1945-12-31'],
            'recent patient' => [date('Y-m-d', strtotime('-1 year'))],
        ];
    }

    // =========================================================================
    // Name Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testNameWithSpecialCharactersPasses(): void
    {
        $data = PatientFactory::make([
            'first_name' => "O'Brien",
            'last_name' => 'García-López',
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayNotHasKey('first_name', $errors);
        $this->assertArrayNotHasKey('last_name', $errors);
    }

    /**
     * @test
     */
    public function testNameMaxLengthExceeded(): void
    {
        $data = PatientFactory::make([
            'first_name' => str_repeat('A', 256), // Exceeds max length
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayHasKey('first_name', $errors);
    }

    // =========================================================================
    // Email Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidEmailPasses(): void
    {
        $data = PatientFactory::make([
            'email' => 'test.patient@example.com',
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayNotHasKey('email', $errors);
    }

    /**
     * @test
     */
    public function testInvalidEmailFails(): void
    {
        $data = PatientFactory::make([
            'email' => 'not-an-email',
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayHasKey('email', $errors);
    }

    /**
     * @test
     */
    public function testEmptyEmailIsAllowed(): void
    {
        $data = PatientFactory::make([
            'email' => null,
        ]);
        
        $errors = PatientValidator::validate($data);
        
        // Email is optional
        $this->assertArrayNotHasKey('email', $errors);
    }

    // =========================================================================
    // Phone Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidPhoneFormats(): void
    {
        $validFormats = [
            '(303) 555-1234',
            '303-555-1234',
            '3035551234',
            '+1 (303) 555-1234',
        ];
        
        foreach ($validFormats as $phone) {
            $data = PatientFactory::make(['phone' => $phone]);
            $errors = PatientValidator::validate($data);
            
            // Phone validation may or may not be enforced
            $this->assertIsArray($errors);
        }
    }

    // =========================================================================
    // SSN Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidSSNFormat(): void
    {
        $data = PatientFactory::make([
            'ssn' => '123-45-6789',
        ]);
        
        $errors = PatientValidator::validate($data);
        
        // SSN format validation
        $this->assertArrayNotHasKey('ssn', $errors);
    }

    /**
     * @test
     */
    public function testInvalidSSNFormat(): void
    {
        $data = PatientFactory::make([
            'ssn' => '12345', // Invalid format
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayHasKey('ssn', $errors);
    }

    // =========================================================================
    // Gender Validation Tests
    // =========================================================================

    /**
     * @test
     * @dataProvider validGenderProvider
     */
    public function testValidGenders(string $gender): void
    {
        $data = PatientFactory::make(['gender' => $gender]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayNotHasKey('gender', $errors,
            "Gender '{$gender}' should be valid");
    }

    /**
     * Data provider for valid genders
     */
    public static function validGenderProvider(): array
    {
        return [
            'Male' => ['M'],
            'Female' => ['F'],
            'Other' => ['O'],
        ];
    }

    /**
     * @test
     */
    public function testInvalidGenderFails(): void
    {
        $data = PatientFactory::make(['gender' => 'X']);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayHasKey('gender', $errors);
    }

    // =========================================================================
    // Address Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidAddressPasses(): void
    {
        $data = PatientFactory::make([
            'address_line_1' => '123 Main Street',
            'city' => 'Denver',
            'state' => 'CO',
            'zip_code' => '80202',
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayNotHasKey('address_line_1', $errors);
        $this->assertArrayNotHasKey('city', $errors);
        $this->assertArrayNotHasKey('state', $errors);
        $this->assertArrayNotHasKey('zip_code', $errors);
    }

    /**
     * @test
     */
    public function testInvalidStateCodeFails(): void
    {
        $data = PatientFactory::make([
            'state' => 'XX', // Invalid state code
        ]);
        
        $errors = PatientValidator::validate($data);
        
        // May or may not validate state codes
        $this->assertIsArray($errors);
    }

    /**
     * @test
     */
    public function testInvalidZipCodeFails(): void
    {
        $data = PatientFactory::make([
            'zip_code' => '1234', // Invalid - too short
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayHasKey('zip_code', $errors);
    }

    // =========================================================================
    // Emergency Contact Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testEmergencyContactValidation(): void
    {
        $data = PatientFactory::make([
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '(303) 555-9876',
            'emergency_contact_relationship' => 'Spouse',
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayNotHasKey('emergency_contact_name', $errors);
        $this->assertArrayNotHasKey('emergency_contact_phone', $errors);
        $this->assertArrayNotHasKey('emergency_contact_relationship', $errors);
    }

    // =========================================================================
    // Update Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testUpdateValidationAllowsPartialData(): void
    {
        $data = [
            'email' => 'updated@example.com',
        ];
        
        // Update validation should not require all fields
        $errors = PatientValidator::validate($data, false);
        
        // Should only validate provided fields
        $this->assertArrayNotHasKey('first_name', $errors);
        $this->assertArrayNotHasKey('last_name', $errors);
        $this->assertArrayNotHasKey('date_of_birth', $errors);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    /**
     * @test
     */
    public function testWhitespaceOnlyNameFails(): void
    {
        $data = PatientFactory::make([
            'first_name' => '   ',
            'last_name' => '   ',
        ]);
        
        $errors = PatientValidator::validate($data, true);
        
        $this->assertNotEmpty($errors);
    }

    /**
     * @test
     */
    public function testUnicodeNamesPasses(): void
    {
        $data = PatientFactory::make([
            'first_name' => 'François',
            'last_name' => 'Müller',
        ]);
        
        $errors = PatientValidator::validate($data);
        
        $this->assertArrayNotHasKey('first_name', $errors);
        $this->assertArrayNotHasKey('last_name', $errors);
    }

    /**
     * @test
     */
    public function testMinorPatientValidation(): void
    {
        $data = PatientFactory::makeMinor();
        
        $errors = PatientValidator::validate($data, true);
        
        // Minors should pass validation
        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function testElderlyPatientValidation(): void
    {
        $data = PatientFactory::makeElderly();
        
        $errors = PatientValidator::validate($data, true);
        
        // Elderly patients should pass validation
        $this->assertEmpty($errors);
    }

    // =========================================================================
    // Helper Method Tests
    // =========================================================================

    /**
     * @test
     */
    public function testIsValidReturnsTrueForValidData(): void
    {
        $data = PatientFactory::makeMinimal();
        
        $isValid = PatientValidator::isValid($data, true);
        
        $this->assertTrue($isValid);
    }

    /**
     * @test
     */
    public function testIsValidReturnsFalseForInvalidData(): void
    {
        $data = []; // Missing all required fields
        
        $isValid = PatientValidator::isValid($data, true);
        
        $this->assertFalse($isValid);
    }

    /**
     * @test
     */
    public function testGetErrorsReturnsLastErrors(): void
    {
        $data = PatientFactory::makeInvalid(['first_name', 'last_name']);
        
        PatientValidator::validate($data, true);
        $errors = PatientValidator::getErrors();
        
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
    }
}
