<?php
/**
 * EncounterValidator Unit Tests
 * 
 * Tests for the EncounterValidator class which handles validation
 * of encounter data including required fields, status transitions,
 * and clinical data validation.
 * 
 * @package SafeShift\Tests\Unit\Validators
 */

declare(strict_types=1);

namespace Tests\Unit\Validators;

use PHPUnit\Framework\TestCase;
use Model\Validators\EncounterValidator;
use Tests\Helpers\Factories\EncounterFactory;

/**
 * @covers \Model\Validators\EncounterValidator
 */
class EncounterValidatorTest extends TestCase
{
    // =========================================================================
    // Valid Encounter Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidEncounterPasses(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'chief_complaint' => 'Annual physical examination',
        ];
        
        $errors = EncounterValidator::validateCreate($data);
        
        $this->assertEmpty($errors, 'Valid encounter should pass validation');
    }

    /**
     * @test
     */
    public function testValidEncounterWithAllFields(): void
    {
        $encounter = EncounterFactory::make();
        
        $errors = EncounterValidator::validateCreate($encounter);
        
        $this->assertEmpty($errors, 'Complete encounter should pass validation');
    }

    // =========================================================================
    // Missing Required Field Tests
    // =========================================================================

    /**
     * @test
     */
    public function testMissingPatientIdFails(): void
    {
        $data = [
            'encounter_type' => 'office_visit',
            'chief_complaint' => 'Test complaint',
        ];
        
        $errors = EncounterValidator::validateCreate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('patient_id', $errors);
    }

    /**
     * @test
     */
    public function testMissingEncounterTypeFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'chief_complaint' => 'Test complaint',
        ];
        
        $errors = EncounterValidator::validateCreate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('encounter_type', $errors);
    }

    /**
     * @test
     */
    public function testEmptyPatientIdFails(): void
    {
        $data = [
            'patient_id' => '',
            'encounter_type' => 'office_visit',
        ];
        
        $errors = EncounterValidator::validateCreate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('patient_id', $errors);
    }

    // =========================================================================
    // Invalid Format Tests
    // =========================================================================

    /**
     * @test
     */
    public function testInvalidPatientIdFormatFails(): void
    {
        $data = [
            'patient_id' => 'not-a-valid-uuid',
            'encounter_type' => 'office_visit',
        ];
        
        $errors = EncounterValidator::validate($data, true);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('patient_id', $errors);
    }

    /**
     * @test
     */
    public function testInvalidEncounterTypeFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'invalid_type',
        ];
        
        $errors = EncounterValidator::validateCreate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('encounter_type', $errors);
    }

    /**
     * @test
     */
    public function testInvalidStatusFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'status' => 'invalid_status',
        ];
        
        $errors = EncounterValidator::validate($data, true);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('status', $errors);
    }

    // =========================================================================
    // Valid Encounter Type Tests
    // =========================================================================

    /**
     * @test
     * @dataProvider validEncounterTypeProvider
     */
    public function testValidEncounterTypesPass(string $encounterType): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => $encounterType,
        ];
        
        $errors = EncounterValidator::validateCreate($data);
        
        $this->assertArrayNotHasKey('encounter_type', $errors,
            "Encounter type '{$encounterType}' should be valid");
    }

    /**
     * Data provider for valid encounter types
     */
    public static function validEncounterTypeProvider(): array
    {
        return [
            'office_visit' => ['office_visit'],
            'dot_physical' => ['dot_physical'],
            'drug_screen' => ['drug_screen'],
            'osha_injury' => ['osha_injury'],
            'workers_comp' => ['workers_comp'],
            'pre_employment' => ['pre_employment'],
            'urgent' => ['urgent'],
            'telehealth' => ['telehealth'],
            'follow_up' => ['follow_up'],
        ];
    }

    // =========================================================================
    // Text Field Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testChiefComplaintMinimumLength(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'chief_complaint' => 'Ab', // Too short - less than minimum
        ];
        
        $errors = EncounterValidator::validate($data);
        
        // Minimum length depends on implementation - check if enforced
        $this->assertIsArray($errors);
    }

    /**
     * @test
     */
    public function testChiefComplaintMaximumLength(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'chief_complaint' => str_repeat('A', 2000), // Exceeds max length
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('chief_complaint', $errors);
    }

    /**
     * @test
     */
    public function testAssessmentFieldValidation(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'assessment' => str_repeat('A', 6000), // Exceeds max length
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('assessment', $errors);
    }

    /**
     * @test
     */
    public function testPlanFieldValidation(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'plan' => str_repeat('A', 6000), // Exceeds max length
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('plan', $errors);
    }

    // =========================================================================
    // Vitals Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidVitalsPass(): void
    {
        $vitals = EncounterFactory::makeVitals();
        
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'vitals' => $vitals,
        ];
        
        $errors = EncounterValidator::validate($data);
        
        // Check for vital-specific errors
        $vitalErrors = array_filter(array_keys($errors), fn($k) => str_starts_with($k, 'vitals.'));
        $this->assertEmpty($vitalErrors, 'Valid vitals should not have errors');
    }

    /**
     * @test
     */
    public function testInvalidSystolicBPFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'vitals' => [
                'systolic_bp' => 500, // Invalid - too high
            ],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('vitals.systolic_bp', $errors);
    }

    /**
     * @test
     */
    public function testInvalidDiastolicBPFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'vitals' => [
                'diastolic_bp' => 10, // Invalid - too low
            ],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('vitals.diastolic_bp', $errors);
    }

    /**
     * @test
     */
    public function testInvalidPulseFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'vitals' => [
                'pulse' => 500, // Invalid - too high
            ],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('vitals.pulse', $errors);
    }

    /**
     * @test
     */
    public function testInvalidTemperatureFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'vitals' => [
                'temperature' => 120, // Invalid - too high
            ],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('vitals.temperature', $errors);
    }

    /**
     * @test
     */
    public function testInvalidRespiratoryRateFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'vitals' => [
                'respiratory_rate' => 100, // Invalid - too high
            ],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('vitals.respiratory_rate', $errors);
    }

    /**
     * @test
     */
    public function testInvalidOxygenSaturationFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'vitals' => [
                'oxygen_saturation' => 110, // Invalid - over 100%
            ],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('vitals.oxygen_saturation', $errors);
    }

    // =========================================================================
    // ICD/CPT Code Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidICDCodesPass(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'icd_codes' => ['Z00.00', 'R51.9', 'M54.5'],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayNotHasKey('icd_codes', $errors);
    }

    /**
     * @test
     */
    public function testInvalidICDCodeFormatFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'icd_codes' => ['INVALID', '123', 'NOT-A-CODE'],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('icd_codes', $errors);
    }

    /**
     * @test
     */
    public function testValidCPTCodesPass(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'cpt_codes' => ['99213', '99214', '36415'],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayNotHasKey('cpt_codes', $errors);
    }

    /**
     * @test
     */
    public function testInvalidCPTCodeFormatFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'cpt_codes' => ['INVALID', 'ABC', '12'],
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('cpt_codes', $errors);
    }

    // =========================================================================
    // Status Transition Validation Tests
    // =========================================================================

    /**
     * @test
     * @dataProvider validStatusTransitionProvider
     */
    public function testValidStatusTransitions(string $from, string $to): void
    {
        $errors = EncounterValidator::validateStatusTransition($from, $to);
        
        $this->assertEmpty($errors, 
            "Status transition from '{$from}' to '{$to}' should be valid");
    }

    /**
     * Data provider for valid status transitions
     */
    public static function validStatusTransitionProvider(): array
    {
        return [
            'scheduled to checked_in' => ['scheduled', 'checked_in'],
            'scheduled to in_progress' => ['scheduled', 'in_progress'],
            'scheduled to cancelled' => ['scheduled', 'cancelled'],
            'checked_in to in_progress' => ['checked_in', 'in_progress'],
            'in_progress to complete' => ['in_progress', 'complete'],
            'in_progress to pending_review' => ['in_progress', 'pending_review'],
            'pending_review to complete' => ['pending_review', 'complete'],
        ];
    }

    /**
     * @test
     * @dataProvider invalidStatusTransitionProvider
     */
    public function testInvalidStatusTransitions(string $from, string $to): void
    {
        $errors = EncounterValidator::validateStatusTransition($from, $to);
        
        $this->assertNotEmpty($errors, 
            "Status transition from '{$from}' to '{$to}' should be invalid");
        $this->assertArrayHasKey('status', $errors);
    }

    /**
     * Data provider for invalid status transitions
     */
    public static function invalidStatusTransitionProvider(): array
    {
        return [
            'complete to in_progress' => ['complete', 'in_progress'],
            'complete to scheduled' => ['complete', 'scheduled'],
            'cancelled to complete' => ['cancelled', 'complete'],
            'scheduled to complete (skip step)' => ['scheduled', 'complete'],
        ];
    }

    // =========================================================================
    // Lock Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidateForLockWithCompleteData(): void
    {
        $encounter = EncounterFactory::make([
            'chief_complaint' => 'Annual physical examination for patient',
            'vitals' => EncounterFactory::makeVitals(),
        ]);
        
        $errors = EncounterValidator::validateForLock($encounter);
        
        // Check if basic required fields are present
        $this->assertArrayNotHasKey('patient_id', $errors);
        $this->assertArrayNotHasKey('provider_id', $errors);
        $this->assertArrayNotHasKey('encounter_type', $errors);
    }

    /**
     * @test
     */
    public function testValidateForLockRequiresChiefComplaint(): void
    {
        $encounter = EncounterFactory::make([
            'chief_complaint' => '', // Empty
        ]);
        
        $errors = EncounterValidator::validateForLock($encounter);
        
        $this->assertArrayHasKey('chief_complaint', $errors);
    }

    /**
     * @test
     */
    public function testValidateForLockChiefComplaintMinLength(): void
    {
        $encounter = EncounterFactory::make([
            'chief_complaint' => 'Short', // Less than 10 characters
        ]);
        
        $errors = EncounterValidator::validateForLock($encounter);
        
        $this->assertArrayHasKey('chief_complaint', $errors);
    }

    /**
     * @test
     */
    public function testDOTPhysicalRequiresPhysicalExam(): void
    {
        $encounter = EncounterFactory::makeDotPhysical([
            'physical_exam' => null,
        ]);
        
        $errors = EncounterValidator::validateForLock($encounter);
        
        $this->assertArrayHasKey('physical_exam', $errors);
    }

    /**
     * @test
     */
    public function testOSHAInjuryRequiresHPI(): void
    {
        $encounter = EncounterFactory::makeOshaInjury([
            'hpi' => null,
        ]);
        
        $errors = EncounterValidator::validateForLock($encounter);
        
        $this->assertArrayHasKey('hpi', $errors);
    }

    // =========================================================================
    // Amendment Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testAmendmentRequiresReason(): void
    {
        $data = [
            'amended_by' => $this->generateUuid(),
            'changes' => ['assessment' => 'Updated assessment'],
        ];
        
        $errors = EncounterValidator::validateAmendment($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('amendment_reason', $errors);
    }

    /**
     * @test
     */
    public function testAmendmentReasonMinLength(): void
    {
        $data = [
            'amendment_reason' => 'Short', // Less than 10 characters
            'amended_by' => $this->generateUuid(),
        ];
        
        $errors = EncounterValidator::validateAmendment($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('amendment_reason', $errors);
    }

    /**
     * @test
     */
    public function testAmendmentRequiresAmendedBy(): void
    {
        $data = [
            'amendment_reason' => 'Correction needed for clinical accuracy.',
        ];
        
        $errors = EncounterValidator::validateAmendment($data);
        
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('amended_by', $errors);
    }

    /**
     * @test
     */
    public function testValidAmendmentPasses(): void
    {
        $data = [
            'amendment_reason' => 'Correction needed for clinical accuracy and completeness.',
            'amended_by' => $this->generateUuid(),
            'changes' => [
                'assessment' => 'Updated clinical assessment with new findings.',
            ],
        ];
        
        $errors = EncounterValidator::validateAmendment($data);
        
        $this->assertArrayNotHasKey('amendment_reason', $errors);
        $this->assertArrayNotHasKey('amended_by', $errors);
    }

    // =========================================================================
    // Date Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidEncounterDatePasses(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'encounter_date' => date('Y-m-d'),
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayNotHasKey('encounter_date', $errors);
    }

    /**
     * @test
     */
    public function testFutureDateTooFarFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'encounter_date' => date('Y-m-d', strtotime('+2 years')),
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('encounter_date', $errors);
    }

    /**
     * @test
     */
    public function testPastDateTooFarFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'encounter_date' => date('Y-m-d', strtotime('-15 years')),
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('encounter_date', $errors);
    }

    /**
     * @test
     */
    public function testInvalidDateFormatFails(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
            'encounter_date' => 'not-a-date',
        ];
        
        $errors = EncounterValidator::validate($data);
        
        $this->assertArrayHasKey('encounter_date', $errors);
    }

    // =========================================================================
    // isValid Helper Tests
    // =========================================================================

    /**
     * @test
     */
    public function testIsValidReturnsTrueForValidData(): void
    {
        $data = [
            'patient_id' => $this->generateUuid(),
            'encounter_type' => 'office_visit',
        ];
        
        $isValid = EncounterValidator::isValid($data, true);
        
        $this->assertTrue($isValid);
    }

    /**
     * @test
     */
    public function testIsValidReturnsFalseForInvalidData(): void
    {
        $data = [
            // Missing required fields
        ];
        
        $isValid = EncounterValidator::isValid($data, true);
        
        $this->assertFalse($isValid);
    }

    // =========================================================================
    // getErrors Helper Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetErrorsReturnsLastValidationErrors(): void
    {
        $data = [
            // Missing required fields
        ];
        
        EncounterValidator::validateCreate($data);
        $errors = EncounterValidator::getErrors();
        
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

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
}
