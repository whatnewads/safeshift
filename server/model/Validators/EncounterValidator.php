<?php
/**
 * EncounterValidator.php - Encounter Data Validator for SafeShift EHR
 * 
 * Provides comprehensive validation for encounter data including
 * required fields, status transitions, locking, and amendments.
 * 
 * @package    SafeShift\Model\Validators
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Validators;

use Model\Entities\Encounter;
use DateTimeImmutable;

/**
 * Encounter data validator
 * 
 * Validates encounter data for creation, updates, locking, and amendments.
 * Provides separate validation methods for different operations.
 */
class EncounterValidator
{
    /** @var array<string, string|array<string>> Validation errors */
    private static array $errors = [];

    /** Valid encounter types */
    private const VALID_ENCOUNTER_TYPES = [
        Encounter::TYPE_OFFICE_VISIT,
        Encounter::TYPE_DOT_PHYSICAL,
        Encounter::TYPE_DRUG_SCREEN,
        Encounter::TYPE_OSHA_INJURY,
        Encounter::TYPE_WORKERS_COMP,
        Encounter::TYPE_PRE_EMPLOYMENT,
        Encounter::TYPE_URGENT,
        Encounter::TYPE_TELEHEALTH,
        Encounter::TYPE_FOLLOW_UP,
    ];

    /** Valid statuses */
    private const VALID_STATUSES = [
        Encounter::STATUS_SCHEDULED,
        Encounter::STATUS_CHECKED_IN,
        Encounter::STATUS_IN_PROGRESS,
        Encounter::STATUS_PENDING_REVIEW,
        Encounter::STATUS_COMPLETE,
        Encounter::STATUS_CANCELLED,
        Encounter::STATUS_NO_SHOW,
    ];

    /** Valid status transitions */
    private const STATUS_TRANSITIONS = [
        Encounter::STATUS_SCHEDULED => [
            Encounter::STATUS_CHECKED_IN,
            Encounter::STATUS_IN_PROGRESS,
            Encounter::STATUS_CANCELLED,
            Encounter::STATUS_NO_SHOW,
        ],
        Encounter::STATUS_CHECKED_IN => [
            Encounter::STATUS_IN_PROGRESS,
            Encounter::STATUS_CANCELLED,
        ],
        Encounter::STATUS_IN_PROGRESS => [
            Encounter::STATUS_PENDING_REVIEW,
            Encounter::STATUS_COMPLETE,
            Encounter::STATUS_CANCELLED,
        ],
        Encounter::STATUS_PENDING_REVIEW => [
            Encounter::STATUS_IN_PROGRESS,
            Encounter::STATUS_COMPLETE,
        ],
        Encounter::STATUS_COMPLETE => [],
        Encounter::STATUS_CANCELLED => [],
        Encounter::STATUS_NO_SHOW => [
            Encounter::STATUS_SCHEDULED,
        ],
    ];

    /** Required vitals for complete encounters */
    private const REQUIRED_VITALS = [
        'systolic_bp',
        'diastolic_bp',
        'pulse',
        'temperature',
        'respiratory_rate',
    ];

    /**
     * Validate encounter data for any operation
     * 
     * @param array<string, mixed> $data Encounter data to validate
     * @param bool $isCreate Whether this is a create operation
     * @return array<string, string|array<string>> Validation errors
     */
    public static function validate(array $data, bool $isCreate = false): array
    {
        self::$errors = [];

        // Required field validation
        self::validateRequiredFields($data, $isCreate);

        // Format and value validation
        if (isset($data['patient_id'])) {
            self::validateUuid($data['patient_id'], 'patient_id');
        }

        if (isset($data['provider_id']) && !empty($data['provider_id'])) {
            self::validateUuid($data['provider_id'], 'provider_id');
        }

        if (isset($data['clinic_id']) && !empty($data['clinic_id'])) {
            self::validateUuid($data['clinic_id'], 'clinic_id');
        }

        if (isset($data['encounter_type'])) {
            self::validateEncounterType($data['encounter_type']);
        }

        if (isset($data['status'])) {
            self::validateStatus($data['status']);
        }

        if (isset($data['encounter_date'])) {
            self::validateEncounterDate($data['encounter_date']);
        }

        if (isset($data['chief_complaint'])) {
            self::validateTextField($data['chief_complaint'], 'chief_complaint', 1, 1000);
        }

        if (isset($data['hpi'])) {
            self::validateTextField($data['hpi'], 'hpi', 0, 5000);
        }

        if (isset($data['assessment'])) {
            self::validateTextField($data['assessment'], 'assessment', 0, 5000);
        }

        if (isset($data['plan'])) {
            self::validateTextField($data['plan'], 'plan', 0, 5000);
        }

        if (isset($data['vitals'])) {
            self::validateVitals($data['vitals']);
        }

        if (isset($data['icd_codes'])) {
            self::validateIcdCodes($data['icd_codes']);
        }

        if (isset($data['cpt_codes'])) {
            self::validateCptCodes($data['cpt_codes']);
        }

        return self::$errors;
    }

    /**
     * Validate encounter data for creation
     * 
     * @param array<string, mixed> $data Encounter data
     * @return array<string, string|array<string>> Validation errors
     */
    public static function validateCreate(array $data): array
    {
        return self::validate($data, true);
    }

    /**
     * Validate encounter data for update
     * 
     * @param array<string, mixed> $data Encounter data
     * @return array<string, string|array<string>> Validation errors
     */
    public static function validateUpdate(array $data): array
    {
        return self::validate($data, false);
    }

    /**
     * Validate encounter for locking (completing)
     * 
     * @param array<string, mixed> $data Encounter data
     * @return array<string, string|array<string>> Validation errors
     */
    public static function validateForLock(array $data): array
    {
        self::$errors = [];

        // Required fields for locked encounter
        $requiredFields = ['patient_id', 'provider_id', 'chief_complaint', 'encounter_type'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || self::isEmpty($data[$field])) {
                self::addError($field, self::getFieldLabel($field) . ' is required to complete encounter');
            }
        }

        // Chief complaint must have meaningful content
        if (isset($data['chief_complaint']) && strlen(trim($data['chief_complaint'])) < 10) {
            self::addError('chief_complaint', 'Chief complaint must be at least 10 characters');
        }

        // For DOT physicals, additional validation
        if (isset($data['encounter_type']) && $data['encounter_type'] === Encounter::TYPE_DOT_PHYSICAL) {
            self::validateDotPhysicalForLock($data);
        }

        // For OSHA injuries, additional validation
        if (isset($data['encounter_type']) && $data['encounter_type'] === Encounter::TYPE_OSHA_INJURY) {
            self::validateOshaInjuryForLock($data);
        }

        // Vitals should be present for most encounter types
        $requiresVitals = [
            Encounter::TYPE_OFFICE_VISIT,
            Encounter::TYPE_DOT_PHYSICAL,
            Encounter::TYPE_URGENT,
            Encounter::TYPE_PRE_EMPLOYMENT,
        ];

        if (isset($data['encounter_type']) && in_array($data['encounter_type'], $requiresVitals, true)) {
            if (!isset($data['vitals']) || empty($data['vitals'])) {
                self::addError('vitals', 'Vitals are required to complete this encounter type');
            } else {
                self::validateRequiredVitals($data['vitals']);
            }
        }

        return self::$errors;
    }

    /**
     * Validate encounter amendment
     * 
     * @param array<string, mixed> $data Amendment data
     * @return array<string, string|array<string>> Validation errors
     */
    public static function validateAmendment(array $data): array
    {
        self::$errors = [];

        // Amendment reason is required
        if (!isset($data['amendment_reason']) || self::isEmpty($data['amendment_reason'])) {
            self::addError('amendment_reason', 'Amendment reason is required');
        } elseif (strlen(trim($data['amendment_reason'])) < 10) {
            self::addError('amendment_reason', 'Amendment reason must be at least 10 characters');
        } elseif (strlen($data['amendment_reason']) > 1000) {
            self::addError('amendment_reason', 'Amendment reason cannot exceed 1000 characters');
        }

        // Amended by user is required
        if (!isset($data['amended_by']) || self::isEmpty($data['amended_by'])) {
            self::addError('amended_by', 'User ID for amendment is required');
        } else {
            self::validateUuid($data['amended_by'], 'amended_by');
        }

        // Validate the fields being amended
        if (isset($data['changes']) && is_array($data['changes'])) {
            foreach ($data['changes'] as $field => $value) {
                // Each amended field should have the standard validation applied
                self::validate([$field => $value], false);
            }
        }

        return self::$errors;
    }

    /**
     * Validate status transition
     * 
     * @param string $currentStatus Current status
     * @param string $newStatus New status
     * @return array<string, string|array<string>> Validation errors
     */
    public static function validateStatusTransition(string $currentStatus, string $newStatus): array
    {
        self::$errors = [];

        if (!isset(self::STATUS_TRANSITIONS[$currentStatus])) {
            self::addError('status', 'Invalid current status');
            return self::$errors;
        }

        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            self::addError('status', 'Invalid new status');
            return self::$errors;
        }

        $allowedTransitions = self::STATUS_TRANSITIONS[$currentStatus];
        
        if (!in_array($newStatus, $allowedTransitions, true)) {
            self::addError('status', sprintf(
                'Cannot transition from %s to %s. Allowed transitions: %s',
                $currentStatus,
                $newStatus,
                empty($allowedTransitions) ? 'none (status is final)' : implode(', ', $allowedTransitions)
            ));
        }

        return self::$errors;
    }

    /**
     * Validate required fields
     * 
     * @param array<string, mixed> $data Encounter data
     * @param bool $isCreate Whether this is a create operation
     */
    private static function validateRequiredFields(array $data, bool $isCreate): void
    {
        if ($isCreate) {
            $requiredFields = ['patient_id', 'encounter_type'];

            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || self::isEmpty($data[$field])) {
                    self::addError($field, self::getFieldLabel($field) . ' is required');
                }
            }
        }
    }

    /**
     * Validate UUID format
     * 
     * @param mixed $value UUID value
     * @param string $field Field name
     */
    private static function validateUuid(mixed $value, string $field): void
    {
        if (!is_string($value)) {
            self::addError($field, self::getFieldLabel($field) . ' must be a string');
            return;
        }

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($pattern, $value)) {
            self::addError($field, self::getFieldLabel($field) . ' must be a valid UUID');
        }
    }

    /**
     * Validate encounter type
     * 
     * @param mixed $value Encounter type value
     */
    private static function validateEncounterType(mixed $value): void
    {
        if (!is_string($value)) {
            self::addError('encounter_type', 'Encounter type must be a string');
            return;
        }

        if (!in_array($value, self::VALID_ENCOUNTER_TYPES, true)) {
            self::addError('encounter_type', 'Invalid encounter type. Must be one of: ' . implode(', ', self::VALID_ENCOUNTER_TYPES));
        }
    }

    /**
     * Validate status
     * 
     * @param mixed $value Status value
     */
    private static function validateStatus(mixed $value): void
    {
        if (!is_string($value)) {
            self::addError('status', 'Status must be a string');
            return;
        }

        if (!in_array($value, self::VALID_STATUSES, true)) {
            self::addError('status', 'Invalid status. Must be one of: ' . implode(', ', self::VALID_STATUSES));
        }
    }

    /**
     * Validate encounter date
     * 
     * @param mixed $value Date value
     */
    private static function validateEncounterDate(mixed $value): void
    {
        if ($value instanceof \DateTimeInterface) {
            $date = $value;
        } else {
            try {
                $date = new DateTimeImmutable($value);
            } catch (\Exception $e) {
                self::addError('encounter_date', 'Invalid date format');
                return;
            }
        }

        $now = new DateTimeImmutable();
        $maxFuture = $now->modify('+1 year');
        $maxPast = $now->modify('-10 years');

        // Cannot be more than 1 year in the future
        if ($date > $maxFuture) {
            self::addError('encounter_date', 'Encounter date cannot be more than 1 year in the future');
        }

        // Cannot be more than 10 years in the past
        if ($date < $maxPast) {
            self::addError('encounter_date', 'Encounter date cannot be more than 10 years in the past');
        }
    }

    /**
     * Validate text field
     * 
     * @param mixed $value Text value
     * @param string $field Field name
     * @param int $minLength Minimum length
     * @param int $maxLength Maximum length
     */
    private static function validateTextField(mixed $value, string $field, int $minLength, int $maxLength): void
    {
        if (!is_string($value)) {
            self::addError($field, self::getFieldLabel($field) . ' must be a string');
            return;
        }

        $length = strlen(trim($value));

        if ($minLength > 0 && $length < $minLength) {
            self::addError($field, self::getFieldLabel($field) . " must be at least {$minLength} characters");
        }

        if ($length > $maxLength) {
            self::addError($field, self::getFieldLabel($field) . " cannot exceed {$maxLength} characters");
        }
    }

    /**
     * Validate vitals data
     * 
     * @param mixed $vitals Vitals data
     */
    private static function validateVitals(mixed $vitals): void
    {
        if (!is_array($vitals)) {
            self::addError('vitals', 'Vitals must be an array');
            return;
        }

        // Validate individual vital signs if present
        if (isset($vitals['systolic_bp'])) {
            $value = (int) $vitals['systolic_bp'];
            if ($value < 50 || $value > 300) {
                self::addError('vitals.systolic_bp', 'Systolic BP must be between 50 and 300 mmHg');
            }
        }

        if (isset($vitals['diastolic_bp'])) {
            $value = (int) $vitals['diastolic_bp'];
            if ($value < 30 || $value > 200) {
                self::addError('vitals.diastolic_bp', 'Diastolic BP must be between 30 and 200 mmHg');
            }
        }

        if (isset($vitals['pulse'])) {
            $value = (int) $vitals['pulse'];
            if ($value < 20 || $value > 300) {
                self::addError('vitals.pulse', 'Pulse must be between 20 and 300 bpm');
            }
        }

        if (isset($vitals['temperature'])) {
            $value = (float) $vitals['temperature'];
            if ($value < 90 || $value > 110) {
                self::addError('vitals.temperature', 'Temperature must be between 90 and 110°F');
            }
        }

        if (isset($vitals['respiratory_rate'])) {
            $value = (int) $vitals['respiratory_rate'];
            if ($value < 5 || $value > 60) {
                self::addError('vitals.respiratory_rate', 'Respiratory rate must be between 5 and 60 breaths/min');
            }
        }

        if (isset($vitals['oxygen_saturation'])) {
            $value = (int) $vitals['oxygen_saturation'];
            if ($value < 50 || $value > 100) {
                self::addError('vitals.oxygen_saturation', 'Oxygen saturation must be between 50 and 100%');
            }
        }

        if (isset($vitals['weight'])) {
            $value = (float) $vitals['weight'];
            if ($value < 1 || $value > 1000) {
                self::addError('vitals.weight', 'Weight must be between 1 and 1000 lbs');
            }
        }

        if (isset($vitals['height'])) {
            $value = (int) $vitals['height'];
            if ($value < 10 || $value > 120) {
                self::addError('vitals.height', 'Height must be between 10 and 120 inches');
            }
        }
    }

    /**
     * Validate required vitals for lock
     * 
     * @param array<string, mixed> $vitals Vitals data
     */
    private static function validateRequiredVitals(array $vitals): void
    {
        foreach (self::REQUIRED_VITALS as $vital) {
            if (!isset($vitals[$vital]) || $vitals[$vital] === null || $vitals[$vital] === '') {
                self::addError("vitals.{$vital}", self::getVitalLabel($vital) . ' is required');
            }
        }
    }

    /**
     * Validate ICD-10 codes
     * 
     * @param mixed $codes ICD codes
     */
    private static function validateIcdCodes(mixed $codes): void
    {
        if (!is_array($codes)) {
            if (is_string($codes)) {
                $codes = json_decode($codes, true);
                if (!is_array($codes)) {
                    self::addError('icd_codes', 'ICD codes must be an array');
                    return;
                }
            } else {
                self::addError('icd_codes', 'ICD codes must be an array');
                return;
            }
        }

        foreach ($codes as $code) {
            // Basic ICD-10 format validation (e.g., A00.0, Z99.89)
            if (!preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/i', $code)) {
                self::addError('icd_codes', "Invalid ICD-10 code format: {$code}");
            }
        }
    }

    /**
     * Validate CPT codes
     * 
     * @param mixed $codes CPT codes
     */
    private static function validateCptCodes(mixed $codes): void
    {
        if (!is_array($codes)) {
            if (is_string($codes)) {
                $codes = json_decode($codes, true);
                if (!is_array($codes)) {
                    self::addError('cpt_codes', 'CPT codes must be an array');
                    return;
                }
            } else {
                self::addError('cpt_codes', 'CPT codes must be an array');
                return;
            }
        }

        foreach ($codes as $code) {
            // CPT codes are 5 digits (some with modifiers like -25)
            if (!preg_match('/^[0-9]{5}(-[0-9A-Z]{2})?$/i', $code)) {
                self::addError('cpt_codes', "Invalid CPT code format: {$code}");
            }
        }
    }

    /**
     * Validate DOT physical for lock
     * 
     * @param array<string, mixed> $data Encounter data
     */
    private static function validateDotPhysicalForLock(array $data): void
    {
        // DOT physicals require specific documentation
        if (!isset($data['physical_exam']) || self::isEmpty($data['physical_exam'])) {
            self::addError('physical_exam', 'Physical exam documentation is required for DOT physical');
        }

        if (!isset($data['assessment']) || self::isEmpty($data['assessment'])) {
            self::addError('assessment', 'Assessment is required for DOT physical');
        }
    }

    /**
     * Validate OSHA injury for lock
     * 
     * @param array<string, mixed> $data Encounter data
     */
    private static function validateOshaInjuryForLock(array $data): void
    {
        // OSHA injuries require specific fields
        if (!isset($data['chief_complaint']) || self::isEmpty($data['chief_complaint'])) {
            self::addError('chief_complaint', 'Injury description is required for OSHA injury');
        }

        if (!isset($data['hpi']) || self::isEmpty($data['hpi'])) {
            self::addError('hpi', 'History of how injury occurred is required');
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
            'patient_id' => 'Patient ID',
            'provider_id' => 'Provider ID',
            'clinic_id' => 'Clinic ID',
            'encounter_type' => 'Encounter type',
            'encounter_date' => 'Encounter date',
            'chief_complaint' => 'Chief complaint',
            'hpi' => 'History of present illness',
            'ros' => 'Review of systems',
            'physical_exam' => 'Physical exam',
            'assessment' => 'Assessment',
            'plan' => 'Treatment plan',
            'amendment_reason' => 'Amendment reason',
            'amended_by' => 'Amended by user',
            default => ucwords(str_replace('_', ' ', $field)),
        };
    }

    /**
     * Get human-readable vital sign label
     * 
     * @param string $vital Vital sign name
     * @return string
     */
    private static function getVitalLabel(string $vital): string
    {
        return match ($vital) {
            'systolic_bp' => 'Systolic blood pressure',
            'diastolic_bp' => 'Diastolic blood pressure',
            'pulse' => 'Pulse rate',
            'temperature' => 'Temperature',
            'respiratory_rate' => 'Respiratory rate',
            'oxygen_saturation' => 'Oxygen saturation',
            'weight' => 'Weight',
            'height' => 'Height',
            default => ucwords(str_replace('_', ' ', $vital)),
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

    /**
     * Validate encounter data for finalization/submission
     * Matches client-side validation rules from requiredFields.ts
     *
     * @param array<string, mixed> $data Encounter data to validate
     * @return array<string, string|array<string>> Validation errors
     */
    public static function validateForFinalization(array $data): array
    {
        self::$errors = [];

        // === INCIDENT TAB - Clinic Information ===
        self::validateNestedRequired($data, 'incidentForm.clinicName', 'Clinic Name');
        self::validateNestedRequired($data, 'incidentForm.clinicStreetAddress', 'Street Address');
        self::validateNestedRequired($data, 'incidentForm.clinicCity', 'City');
        self::validateNestedRequired($data, 'incidentForm.clinicState', 'State');

        // === INCIDENT TAB - Time Fields ===
        self::validateNestedRequired($data, 'incidentForm.patientContactTime', 'Patient Contact Time');
        self::validateNestedRequired($data, 'incidentForm.clearedClinicTime', 'Cleared Clinic Time');

        // === INCIDENT TAB - Incident Details ===
        self::validateNestedRequired($data, 'incidentForm.location', 'Location of Injury/Illness');
        self::validateNestedRequired($data, 'incidentForm.injuryClassifiedByName', 'Classified By (Name)');
        self::validateNestedRequired($data, 'incidentForm.injuryClassification', 'Injury/Illness Classification');

        // === INCIDENT TAB - Provider Information ===
        self::validateLeadProvider($data);

        // === PATIENT TAB - Demographics ===
        self::validateNestedRequired($data, 'patientForm.firstName', 'First Name');
        self::validateNestedRequired($data, 'patientForm.lastName', 'Last Name');
        self::validateNestedRequired($data, 'patientForm.dob', 'Date of Birth');

        // === PATIENT TAB - Home Address ===
        self::validateNestedRequired($data, 'patientForm.streetAddress', 'Patient Street Address');
        self::validateNestedRequired($data, 'patientForm.city', 'Patient City');
        self::validateNestedRequired($data, 'patientForm.state', 'Patient State');

        // === PATIENT TAB - Employment ===
        self::validateNestedRequired($data, 'patientForm.employer', 'Employer');
        self::validateNestedRequired($data, 'patientForm.supervisorName', 'Supervisor Name');
        self::validateNestedRequired($data, 'patientForm.supervisorPhone', 'Supervisor Phone');

        // === PATIENT TAB - Medical History ===
        self::validateNestedRequired($data, 'patientForm.medicalHistory', 'Medical History');
        self::validateNestedRequired($data, 'patientForm.allergies', 'Allergies');
        self::validateNestedRequired($data, 'patientForm.currentMedications', 'Current Medications');

        // === ASSESSMENTS TAB ===
        self::validateMinimumAssessments($data);

        // === VITALS TAB ===
        self::validateVitalsForFinalization($data);

        // === NARRATIVE TAB ===
        self::validateNarrativeLength($data);

        // === SIGNATURES TAB ===
        self::validateDisclosures($data);

        return self::$errors;
    }

    /**
     * Validate nested required field using dot notation
     *
     * @param array<string, mixed> $data Data array
     * @param string $path Dot notation path (e.g., 'incidentForm.clinicName')
     * @param string $label Human-readable field label
     */
    private static function validateNestedRequired(array $data, string $path, string $label): void
    {
        $value = self::getNestedValue($data, $path);
        if (self::isEmpty($value)) {
            self::addError($path, "{$label} is required");
        }
    }

    /**
     * Get nested value from array using dot notation
     *
     * @param array<string, mixed> $data Data array
     * @param string $path Dot notation path
     * @return mixed
     */
    private static function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Validate that at least one lead provider exists
     *
     * @param array<string, mixed> $data Encounter data
     */
    private static function validateLeadProvider(array $data): void
    {
        $providers = $data['providers'] ?? [];
        
        if (!is_array($providers) || empty($providers)) {
            self::addError('providers', 'At least one Lead Provider is required');
            return;
        }

        $hasLead = false;
        foreach ($providers as $provider) {
            if (isset($provider['role']) && $provider['role'] === 'lead' && !empty($provider['name'])) {
                $hasLead = true;
                break;
            }
        }

        if (!$hasLead) {
            self::addError('providers', 'At least one Lead Provider is required');
        }
    }

    /**
     * Validate minimum assessments requirement
     *
     * @param array<string, mixed> $data Encounter data
     */
    private static function validateMinimumAssessments(array $data): void
    {
        $assessments = $data['assessments'] ?? [];
        
        if (!is_array($assessments) || count($assessments) < 1) {
            self::addError('assessments', 'At least one assessment is required');
        }
    }

    /**
     * Validate vitals for finalization
     * Requires at least one complete vitals set with required fields
     *
     * @param array<string, mixed> $data Encounter data
     */
    private static function validateVitalsForFinalization(array $data): void
    {
        $vitals = $data['vitals'] ?? [];
        
        if (!is_array($vitals) || empty($vitals)) {
            self::addError('vitals', 'At least one complete vitals set is required');
            return;
        }

        $requiredVitalFields = ['time', 'date', 'avpu', 'bp', 'bpTaken', 'pulse', 'respiration', 'gcsTotal'];
        $hasCompleteSet = false;

        foreach ($vitals as $vitalSet) {
            if (!is_array($vitalSet)) {
                continue;
            }

            $isComplete = true;
            foreach ($requiredVitalFields as $field) {
                if (!isset($vitalSet[$field]) || self::isEmpty($vitalSet[$field])) {
                    $isComplete = false;
                    break;
                }
            }

            if ($isComplete) {
                $hasCompleteSet = true;
                break;
            }
        }

        if (!$hasCompleteSet) {
            self::addError('vitals', 'At least one complete vitals set is required (time, date, AVPU, BP, BP method, pulse, respiration, GCS)');
        }
    }

    /**
     * Validate narrative minimum length
     *
     * @param array<string, mixed> $data Encounter data
     */
    private static function validateNarrativeLength(array $data): void
    {
        $narrative = $data['narrative'] ?? '';
        
        if (!is_string($narrative) || strlen(trim($narrative)) < 25) {
            self::addError('narrative', 'Narrative must be at least 25 characters');
        }
    }

    /**
     * Validate that all disclosures are acknowledged
     *
     * @param array<string, mixed> $data Encounter data
     */
    private static function validateDisclosures(array $data): void
    {
        $disclosures = $data['disclosures'] ?? [];
        
        if (!is_array($disclosures) || empty($disclosures)) {
            self::addError('disclosures', 'Disclosures must be acknowledged');
            return;
        }

        $allAcknowledged = true;
        foreach ($disclosures as $key => $value) {
            if ($value !== true) {
                $allAcknowledged = false;
                break;
            }
        }

        if (!$allAcknowledged) {
            self::addError('disclosures', 'All disclosures must be acknowledged');
        }
    }

    /**
     * Check if finalization validation passes
     *
     * @param array<string, mixed> $data Data to validate
     * @return bool
     */
    public static function isValidForFinalization(array $data): bool
    {
        return count(self::validateForFinalization($data)) === 0;
    }
}
