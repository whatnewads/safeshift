<?php
/**
 * PatientValidator.php - Patient Data Validator for SafeShift EHR
 * 
 * Provides comprehensive validation for patient data including
 * required fields, format validation, and data integrity checks.
 * 
 * @package    SafeShift\Model\Validators
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Validators;

use DateTimeImmutable;

/**
 * Patient data validator
 * 
 * Validates patient data for creation, updates, and data integrity.
 */
class PatientValidator
{
    /** @var array<string, string|array<string>> Validation errors */
    private static array $errors = [];

    /** Valid gender values */
    private const VALID_GENDERS = ['M', 'F', 'O'];

    /** Valid US state codes */
    private const VALID_STATES = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'DC'
    ];

    /**
     * Validate patient data
     * 
     * @param array<string, mixed> $data Patient data to validate
     * @param bool $isCreate Whether this is a create operation
     * @return array<string, string|array<string>> Validation errors
     */
    public static function validate(array $data, bool $isCreate = false): array
    {
        self::$errors = [];

        // Required field validation for create
        if ($isCreate) {
            self::validateRequiredFields($data);
        }

        // Field-specific validation
        if (isset($data['first_name'])) {
            self::validateName($data['first_name'], 'first_name');
        }

        if (isset($data['last_name'])) {
            self::validateName($data['last_name'], 'last_name');
        }

        if (isset($data['middle_name']) && !empty($data['middle_name'])) {
            self::validateName($data['middle_name'], 'middle_name');
        }

        if (isset($data['date_of_birth'])) {
            self::validateDateOfBirth($data['date_of_birth']);
        }

        if (isset($data['gender'])) {
            self::validateGender($data['gender']);
        }

        if (isset($data['email']) && !empty($data['email'])) {
            self::validateEmail($data['email']);
        }

        if (isset($data['phone']) && !empty($data['phone'])) {
            self::validatePhone($data['phone'], 'phone');
        }

        if (isset($data['mobile_phone']) && !empty($data['mobile_phone'])) {
            self::validatePhone($data['mobile_phone'], 'mobile_phone');
        }

        if (isset($data['ssn']) && !empty($data['ssn'])) {
            self::validateSSN($data['ssn']);
        }

        if (isset($data['zip_code']) && !empty($data['zip_code'])) {
            self::validateZipCode($data['zip_code']);
        }

        if (isset($data['state']) && !empty($data['state'])) {
            self::validateState($data['state']);
        }

        return self::$errors;
    }

    /**
     * Validate required fields for create operation
     * 
     * @param array<string, mixed> $data Patient data
     */
    private static function validateRequiredFields(array $data): void
    {
        $requiredFields = ['first_name', 'last_name', 'date_of_birth'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || self::isEmpty($data[$field])) {
                self::addError($field, self::getFieldLabel($field) . ' is required');
            }
        }
    }

    /**
     * Validate name field
     * 
     * @param mixed $value Name value
     * @param string $field Field name
     */
    private static function validateName(mixed $value, string $field): void
    {
        if (!is_string($value)) {
            self::addError($field, self::getFieldLabel($field) . ' must be a string');
            return;
        }

        $trimmed = trim($value);

        if (empty($trimmed)) {
            self::addError($field, self::getFieldLabel($field) . ' is required');
            return;
        }

        if (strlen($trimmed) > 255) {
            self::addError($field, self::getFieldLabel($field) . ' cannot exceed 255 characters');
        }

        // Check for invalid characters (allow letters, spaces, hyphens, apostrophes)
        if (!preg_match("/^[\p{L}\s\-'\.]+$/u", $trimmed)) {
            self::addError($field, self::getFieldLabel($field) . ' contains invalid characters');
        }
    }

    /**
     * Validate date of birth
     * 
     * @param mixed $value Date value
     */
    private static function validateDateOfBirth(mixed $value): void
    {
        if ($value instanceof \DateTimeInterface) {
            $date = $value;
        } else {
            if (!is_string($value) || empty(trim($value))) {
                self::addError('date_of_birth', 'Date of birth is required');
                return;
            }

            try {
                $date = new DateTimeImmutable($value);
            } catch (\Exception $e) {
                self::addError('date_of_birth', 'Invalid date format');
                return;
            }
        }

        $now = new DateTimeImmutable();

        // Cannot be in the future
        if ($date > $now) {
            self::addError('date_of_birth', 'Date of birth cannot be in the future');
        }

        // Cannot be more than 150 years ago
        $maxAge = $now->modify('-150 years');
        if ($date < $maxAge) {
            self::addError('date_of_birth', 'Invalid date of birth');
        }
    }

    /**
     * Validate gender
     * 
     * @param mixed $value Gender value
     */
    private static function validateGender(mixed $value): void
    {
        if (!is_string($value)) {
            self::addError('gender', 'Gender must be a string');
            return;
        }

        if (!in_array($value, self::VALID_GENDERS, true)) {
            self::addError('gender', 'Gender must be one of: ' . implode(', ', self::VALID_GENDERS));
        }
    }

    /**
     * Validate email address
     * 
     * @param mixed $value Email value
     */
    private static function validateEmail(mixed $value): void
    {
        if (!is_string($value)) {
            self::addError('email', 'Email must be a string');
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            self::addError('email', 'Invalid email address');
        }

        if (strlen($value) > 255) {
            self::addError('email', 'Email cannot exceed 255 characters');
        }
    }

    /**
     * Validate phone number
     * 
     * @param mixed $value Phone value
     * @param string $field Field name
     */
    private static function validatePhone(mixed $value, string $field): void
    {
        if (!is_string($value)) {
            self::addError($field, self::getFieldLabel($field) . ' must be a string');
            return;
        }

        // Remove common formatting characters
        $cleaned = preg_replace('/[\s\-\(\)\.]/', '', $value);

        // Check if it's a valid phone number (at least 10 digits)
        if (!preg_match('/^\+?\d{10,15}$/', $cleaned)) {
            self::addError($field, 'Invalid phone number format');
        }
    }

    /**
     * Validate SSN format
     * 
     * @param mixed $value SSN value
     */
    private static function validateSSN(mixed $value): void
    {
        if (!is_string($value)) {
            self::addError('ssn', 'SSN must be a string');
            return;
        }

        // Remove dashes for validation
        $cleaned = str_replace('-', '', $value);

        // SSN should be 9 digits
        if (!preg_match('/^\d{9}$/', $cleaned)) {
            self::addError('ssn', 'SSN must be a valid 9-digit number');
        }

        // Check for common invalid SSNs
        $invalidPrefixes = ['000', '666', '900', '901', '902', '903', '904', '905', '906', '907', '908', '909', '999'];
        if (in_array(substr($cleaned, 0, 3), $invalidPrefixes, true)) {
            self::addError('ssn', 'Invalid SSN');
        }
    }

    /**
     * Validate ZIP code
     * 
     * @param mixed $value ZIP value
     */
    private static function validateZipCode(mixed $value): void
    {
        if (!is_string($value)) {
            self::addError('zip_code', 'ZIP code must be a string');
            return;
        }

        // US ZIP codes: 5 digits or 5+4 format
        if (!preg_match('/^\d{5}(-\d{4})?$/', $value)) {
            self::addError('zip_code', 'Invalid ZIP code format');
        }
    }

    /**
     * Validate US state code
     * 
     * @param mixed $value State value
     */
    private static function validateState(mixed $value): void
    {
        if (!is_string($value)) {
            self::addError('state', 'State must be a string');
            return;
        }

        $upper = strtoupper($value);
        if (!in_array($upper, self::VALID_STATES, true)) {
            self::addError('state', 'Invalid state code');
        }
    }

    /**
     * Check if value is empty
     * 
     * @param mixed $value Value to check
     * @return bool
     */
    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }

    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     */
    private static function addError(string $field, string $message): void
    {
        if (isset(self::$errors[$field])) {
            if (is_array(self::$errors[$field])) {
                self::$errors[$field][] = $message;
            } else {
                self::$errors[$field] = [self::$errors[$field], $message];
            }
        } else {
            self::$errors[$field] = $message;
        }
    }

    /**
     * Get human-readable field label
     * 
     * @param string $field Field name
     * @return string
     */
    private static function getFieldLabel(string $field): string
    {
        return match ($field) {
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'middle_name' => 'Middle name',
            'date_of_birth' => 'Date of birth',
            'gender' => 'Gender',
            'email' => 'Email',
            'phone' => 'Phone number',
            'mobile_phone' => 'Mobile phone',
            'ssn' => 'Social Security Number',
            'address_line_1' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'zip_code' => 'ZIP code',
            default => ucwords(str_replace('_', ' ', $field)),
        };
    }

    /**
     * Get last validation errors
     * 
     * @return array<string, string|array<string>>
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Check if validation passed
     * 
     * @param array<string, mixed> $data Data to validate
     * @param bool $isCreate Whether this is create operation
     * @return bool
     */
    public static function isValid(array $data, bool $isCreate = false): bool
    {
        return count(self::validate($data, $isCreate)) === 0;
    }
}
