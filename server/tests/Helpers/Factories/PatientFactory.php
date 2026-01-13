<?php
/**
 * PatientFactory - Test Data Factory for Patients
 * 
 * Creates mock patient data for testing purposes.
 * 
 * @package SafeShift\Tests\Helpers\Factories
 */

declare(strict_types=1);

namespace Tests\Helpers\Factories;

/**
 * Factory for generating test patient data
 */
class PatientFactory
{
    /** @var array<string> First names for test data */
    private const FIRST_NAMES = [
        'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 
        'Robert', 'Jessica', 'William', 'Ashley', 'James', 'Amanda'
    ];

    /** @var array<string> Last names for test data */
    private const LAST_NAMES = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia',
        'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Wilson', 'Anderson'
    ];

    /** @var array<string> Valid genders */
    private const GENDERS = ['M', 'F', 'O'];

    /**
     * Create a basic patient data array
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function make(array $overrides = []): array
    {
        $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $lastName = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
        
        $defaults = [
            'patient_id' => self::generateUuid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => null,
            'date_of_birth' => self::generateBirthDate(),
            'gender' => self::GENDERS[array_rand(self::GENDERS)],
            'ssn' => self::generateSsn(),
            'email' => strtolower($firstName . '.' . $lastName . '@testpatient.com'),
            'phone' => self::generatePhone(),
            'mobile_phone' => self::generatePhone(),
            'address_line_1' => random_int(100, 9999) . ' Test Street',
            'address_line_2' => null,
            'city' => 'Test City',
            'state' => 'CO',
            'zip_code' => sprintf('%05d', random_int(10000, 99999)),
            'mrn' => 'MRN-' . sprintf('%08d', random_int(1, 99999999)),
            'clinic_id' => self::generateUuid(),
            'primary_care_provider' => null,
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => self::generatePhone(),
            'emergency_contact_relationship' => 'Spouse',
            'insurance_primary' => null,
            'insurance_secondary' => null,
            'employer_id' => null,
            'employer_name' => null,
            'occupation' => null,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by' => null,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create patient with minimal required fields
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeMinimal(array $overrides = []): array
    {
        $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
        $lastName = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
        
        return array_merge([
            'patient_id' => self::generateUuid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => self::generateBirthDate(),
            'gender' => 'M',
            'clinic_id' => self::generateUuid(),
        ], $overrides);
    }

    /**
     * Create a male patient
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeMale(array $overrides = []): array
    {
        return self::make(array_merge([
            'gender' => 'M',
            'first_name' => 'John',
        ], $overrides));
    }

    /**
     * Create a female patient
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeFemale(array $overrides = []): array
    {
        return self::make(array_merge([
            'gender' => 'F',
            'first_name' => 'Jane',
        ], $overrides));
    }

    /**
     * Create a minor patient (under 18)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeMinor(array $overrides = []): array
    {
        $age = random_int(5, 17);
        return self::make(array_merge([
            'date_of_birth' => date('Y-m-d', strtotime("-{$age} years")),
            'first_name' => 'Minor',
        ], $overrides));
    }

    /**
     * Create an elderly patient (65+)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeElderly(array $overrides = []): array
    {
        $age = random_int(65, 90);
        return self::make(array_merge([
            'date_of_birth' => date('Y-m-d', strtotime("-{$age} years")),
        ], $overrides));
    }

    /**
     * Create patient with insurance
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeWithInsurance(array $overrides = []): array
    {
        return self::make(array_merge([
            'insurance_primary' => [
                'carrier' => 'Test Insurance Co',
                'policy_number' => 'POL-' . random_int(100000, 999999),
                'group_number' => 'GRP-' . random_int(1000, 9999),
                'subscriber_name' => 'Self',
                'subscriber_relationship' => 'self',
            ],
        ], $overrides));
    }

    /**
     * Create patient with employer
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeWithEmployer(array $overrides = []): array
    {
        return self::make(array_merge([
            'employer_id' => self::generateUuid(),
            'employer_name' => 'Test Company Inc.',
            'occupation' => 'Test Worker',
        ], $overrides));
    }

    /**
     * Create inactive patient
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeInactive(array $overrides = []): array
    {
        return self::make(array_merge([
            'is_active' => false,
        ], $overrides));
    }

    /**
     * Create patient with missing required fields (for validation testing)
     * 
     * @param array<string> $missingFields Fields to omit
     * @return array<string, mixed>
     */
    public static function makeInvalid(array $missingFields = ['first_name']): array
    {
        $patient = self::make();
        foreach ($missingFields as $field) {
            unset($patient[$field]);
        }
        return $patient;
    }

    /**
     * Create patient with invalid data (for validation testing)
     * 
     * @return array<string, mixed>
     */
    public static function makeWithInvalidData(): array
    {
        return self::make([
            'date_of_birth' => 'invalid-date',
            'email' => 'not-an-email',
            'ssn' => '12345', // Invalid format
            'phone' => 'abc', // Invalid format
        ]);
    }

    /**
     * Create multiple patients
     * 
     * @param int $count
     * @param array<string, mixed> $overrides
     * @return array<int, array<string, mixed>>
     */
    public static function makeMany(int $count, array $overrides = []): array
    {
        $patients = [];
        for ($i = 0; $i < $count; $i++) {
            $patients[] = self::make($overrides);
        }
        return $patients;
    }

    /**
     * Create patient data for DOT testing
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeDotDriver(array $overrides = []): array
    {
        return self::makeWithEmployer(array_merge([
            'occupation' => 'Commercial Driver',
            'employer_name' => 'Test Trucking Company',
        ], $overrides));
    }

    /**
     * Generate random birth date (18-70 years old by default)
     * 
     * @param int $minAge
     * @param int $maxAge
     * @return string
     */
    public static function generateBirthDate(int $minAge = 18, int $maxAge = 70): string
    {
        $age = random_int($minAge, $maxAge);
        $dayOffset = random_int(0, 365);
        return date('Y-m-d', strtotime("-{$age} years -{$dayOffset} days"));
    }

    /**
     * Generate SSN for testing (fake format)
     * 
     * @return string
     */
    private static function generateSsn(): string
    {
        return sprintf(
            '%03d-%02d-%04d',
            random_int(100, 999),
            random_int(10, 99),
            random_int(1000, 9999)
        );
    }

    /**
     * Generate phone number for testing
     * 
     * @return string
     */
    private static function generatePhone(): string
    {
        return sprintf(
            '(%03d) %03d-%04d',
            random_int(200, 999),
            random_int(200, 999),
            random_int(1000, 9999)
        );
    }

    /**
     * Generate UUID
     */
    private static function generateUuid(): string
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
}
