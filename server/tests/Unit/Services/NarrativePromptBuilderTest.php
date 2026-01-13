<?php
/**
 * NarrativePromptBuilder Unit Tests
 *
 * Tests for the NarrativePromptBuilder class which constructs prompts
 * for AI-based clinical narrative generation.
 *
 * @package SafeShift\Tests\Unit\Services
 */

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Core\Services\NarrativePromptBuilder;
use InvalidArgumentException;

/**
 * @covers \Core\Services\NarrativePromptBuilder
 */
class NarrativePromptBuilderTest extends TestCase
{
    private NarrativePromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new NarrativePromptBuilder();
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create valid minimal encounter data for testing
     */
    private function createValidEncounterData(): array
    {
        return [
            'encounter' => [
                'encounter_id' => 'enc-uuid-12345',
                'encounter_type' => 'office_visit',
                'status' => 'in_progress',
                'chief_complaint' => 'Patient presents with headache',
            ],
            'patient' => [
                'patient_id' => 'pat-uuid-67890',
                'name' => 'John Doe',
                'age' => 35,
                'sex' => 'Male',
            ],
        ];
    }

    /**
     * Create full encounter data with all optional fields
     */
    private function createFullEncounterData(): array
    {
        return [
            'encounter' => [
                'encounter_id' => 'enc-uuid-12345',
                'encounter_type' => 'osha_injury',
                'status' => 'in_progress',
                'chief_complaint' => 'Work-related hand laceration',
                'occurred_on' => '2025-01-15 09:30:00',
                'arrived_on' => '2025-01-15 09:45:00',
                'discharged_on' => '2025-01-15 11:00:00',
                'disposition' => 'Return to work with restrictions',
                'onset_context' => 'work_related',
            ],
            'patient' => [
                'patient_id' => 'pat-uuid-67890',
                'name' => 'Jane Smith',
                'age' => 42,
                'sex' => 'Female',
                'employer_name' => 'ACME Industries',
            ],
            'medical_history' => [
                'conditions' => ['Hypertension', 'Type 2 Diabetes'],
                'current_medications' => ['Lisinopril 10mg', 'Metformin 500mg'],
                'allergies' => ['Penicillin', 'Sulfa'],
            ],
            'observations' => [
                [
                    'label' => 'BP Systolic',
                    'value_num' => 128,
                    'unit' => 'mmHg',
                    'taken_at' => '2025-01-15 09:50:00',
                ],
                [
                    'label' => 'BP Diastolic',
                    'value_num' => 82,
                    'unit' => 'mmHg',
                    'taken_at' => '2025-01-15 09:50:00',
                ],
                [
                    'label' => 'Pulse',
                    'value_num' => 76,
                    'unit' => 'bpm',
                    'taken_at' => '2025-01-15 09:50:00',
                ],
                [
                    'label' => 'Pain NRS',
                    'value_num' => 6,
                    'unit' => '/10',
                    'taken_at' => '2025-01-15 09:50:00',
                ],
            ],
            'medications_administered' => [
                [
                    'medication_name' => 'Ibuprofen',
                    'dose' => '400mg',
                    'route' => 'PO',
                    'given_at' => '2025-01-15 10:00:00',
                    'response' => 'Pain reduced to 3/10',
                ],
            ],
            'provider' => [
                'npi' => '1234567890',
                'credentials' => 'NRP, NREMT-P',
            ],
        ];
    }

    // =========================================================================
    // buildPrompt Tests
    // =========================================================================

    /**
     * @test
     */
    public function buildPromptCombinesSystemPromptAndEncounterData(): void
    {
        $encounterData = $this->createValidEncounterData();
        
        $prompt = $this->builder->buildPrompt($encounterData);
        
        // Verify system prompt content is present
        $this->assertStringContainsString('EMS NARRATIVE GENERATION', $prompt);
        $this->assertStringContainsString('CORE PRINCIPLES', $prompt);
        $this->assertStringContainsString('SOAP', $prompt);
        
        // Verify clinical data section marker
        $this->assertStringContainsString('## CLINICAL DATA', $prompt);
        
        // Verify encounter data is present as JSON
        $this->assertStringContainsString('enc-uuid-12345', $prompt);
        $this->assertStringContainsString('Patient presents with headache', $prompt);
        $this->assertStringContainsString('John Doe', $prompt);
    }

    /**
     * @test
     */
    public function buildPromptThrowsExceptionForInvalidData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encounter data');
        
        $this->builder->buildPrompt([]);
    }

    /**
     * @test
     */
    public function buildPromptThrowsExceptionWhenMissingEncounterSection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $this->builder->buildPrompt([
            'patient' => [
                'patient_id' => '123',
                'name' => 'John Doe',
            ],
        ]);
    }

    /**
     * @test
     */
    public function buildPromptThrowsExceptionWhenMissingPatientSection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $this->builder->buildPrompt([
            'encounter' => [
                'encounter_id' => '123',
                'chief_complaint' => 'Headache',
            ],
        ]);
    }

    /**
     * @test
     */
    public function buildPromptIncludesAllOptionalSections(): void
    {
        $encounterData = $this->createFullEncounterData();
        
        $prompt = $this->builder->buildPrompt($encounterData);
        
        // Verify all sections appear in the JSON data
        $this->assertStringContainsString('medical_history', $prompt);
        $this->assertStringContainsString('observations', $prompt);
        $this->assertStringContainsString('medications_administered', $prompt);
        $this->assertStringContainsString('provider', $prompt);
        
        // Verify specific data
        $this->assertStringContainsString('Hypertension', $prompt);
        $this->assertStringContainsString('BP Systolic', $prompt);
        $this->assertStringContainsString('Ibuprofen', $prompt);
        $this->assertStringContainsString('1234567890', $prompt);
    }

    // =========================================================================
    // validateEncounterData Tests
    // =========================================================================

    /**
     * @test
     */
    public function validateEncounterDataReturnsTrueForValidData(): void
    {
        $data = $this->createValidEncounterData();
        
        $result = $this->builder->validateEncounterData($data);
        
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseForEmptyArray(): void
    {
        $result = $this->builder->validateEncounterData([]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenMissingEncounterKey(): void
    {
        $result = $this->builder->validateEncounterData([
            'patient' => [
                'patient_id' => '123',
                'name' => 'Test',
            ],
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenMissingPatientKey(): void
    {
        $result = $this->builder->validateEncounterData([
            'encounter' => [
                'encounter_id' => '123',
                'chief_complaint' => 'Test',
            ],
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenEncounterNotArray(): void
    {
        $result = $this->builder->validateEncounterData([
            'encounter' => 'not an array',
            'patient' => [
                'patient_id' => '123',
                'name' => 'Test',
            ],
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenPatientNotArray(): void
    {
        $result = $this->builder->validateEncounterData([
            'encounter' => [
                'encounter_id' => '123',
                'chief_complaint' => 'Test',
            ],
            'patient' => 'not an array',
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenMissingEncounterId(): void
    {
        $result = $this->builder->validateEncounterData([
            'encounter' => [
                'chief_complaint' => 'Test complaint',
            ],
            'patient' => [
                'patient_id' => '123',
                'name' => 'Test',
            ],
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenMissingChiefComplaint(): void
    {
        $result = $this->builder->validateEncounterData([
            'encounter' => [
                'encounter_id' => '123',
            ],
            'patient' => [
                'patient_id' => '456',
                'name' => 'Test',
            ],
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenMissingPatientId(): void
    {
        $result = $this->builder->validateEncounterData([
            'encounter' => [
                'encounter_id' => '123',
                'chief_complaint' => 'Test complaint',
            ],
            'patient' => [
                'name' => 'Test',
            ],
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenMissingPatientName(): void
    {
        $result = $this->builder->validateEncounterData([
            'encounter' => [
                'encounter_id' => '123',
                'chief_complaint' => 'Test complaint',
            ],
            'patient' => [
                'patient_id' => '456',
            ],
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataReturnsFalseWhenEncounterIdIsEmpty(): void
    {
        $result = $this->builder->validateEncounterData([
            'encounter' => [
                'encounter_id' => '',
                'chief_complaint' => 'Test',
            ],
            'patient' => [
                'patient_id' => '123',
                'name' => 'Test',
            ],
        ]);
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function validateEncounterDataAllowsMissingOptionalFields(): void
    {
        // Only required fields, no optional sections
        $data = [
            'encounter' => [
                'encounter_id' => 'enc-123',
                'chief_complaint' => 'Headache',
            ],
            'patient' => [
                'patient_id' => 'pat-456',
                'name' => 'John Doe',
            ],
        ];
        
        $result = $this->builder->validateEncounterData($data);
        
        $this->assertTrue($result);
    }

    // =========================================================================
    // createEncounterDataFromForm Tests
    // =========================================================================

    /**
     * @test
     */
    public function createEncounterDataFromFormTransformsBasicFields(): void
    {
        $formData = [
            'encounter_id' => 'enc-form-123',
            'encounter_type' => 'office_visit',
            'status' => 'in_progress',
            'chief_complaint' => 'Chest pain',
            'patient_id' => 'pat-form-456',
            'name' => 'Jane Doe',
            'age' => 45,
            'sex' => 'Female',
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertArrayHasKey('encounter', $result);
        $this->assertArrayHasKey('patient', $result);
        
        $this->assertEquals('enc-form-123', $result['encounter']['encounter_id']);
        $this->assertEquals('Chest pain', $result['encounter']['chief_complaint']);
        $this->assertEquals('pat-form-456', $result['patient']['patient_id']);
        $this->assertEquals('Jane Doe', $result['patient']['name']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormHandlesCamelCaseFields(): void
    {
        $formData = [
            'encounterId' => 'enc-camel-123',
            'encounterType' => 'dot_physical',
            'chiefComplaint' => 'DOT physical exam',
            'patientId' => 'pat-camel-456',
            'patientName' => 'Bob Smith',
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertEquals('enc-camel-123', $result['encounter']['encounter_id']);
        $this->assertEquals('dot_physical', $result['encounter']['encounter_type']);
        $this->assertEquals('DOT physical exam', $result['encounter']['chief_complaint']);
        $this->assertEquals('pat-camel-456', $result['patient']['patient_id']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormCombinesFirstAndLastName(): void
    {
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Test',
            'patient_id' => 'pat-456',
            'first_name' => 'John',
            'last_name' => 'Smith',
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertEquals('John Smith', $result['patient']['name']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormHandlesCamelCaseNames(): void
    {
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Test',
            'patient_id' => 'pat-456',
            'firstName' => 'Mary',
            'lastName' => 'Johnson',
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertEquals('Mary Johnson', $result['patient']['name']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormHandlesMedicalHistoryStrings(): void
    {
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Test',
            'patient_id' => 'pat-456',
            'name' => 'Test Patient',
            'conditions' => 'Diabetes, Hypertension, COPD',
            'allergies' => 'Penicillin, Sulfa',
            'currentMedications' => 'Metformin, Lisinopril',
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertIsArray($result['medical_history']['conditions']);
        $this->assertContains('Diabetes', $result['medical_history']['conditions']);
        $this->assertContains('Hypertension', $result['medical_history']['conditions']);
        
        $this->assertIsArray($result['medical_history']['allergies']);
        $this->assertContains('Penicillin', $result['medical_history']['allergies']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormHandlesMedicalHistoryArrays(): void
    {
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Test',
            'patient_id' => 'pat-456',
            'name' => 'Test Patient',
            'conditions' => ['Diabetes', 'Hypertension'],
            'allergies' => ['Penicillin'],
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertEquals(['Diabetes', 'Hypertension'], $result['medical_history']['conditions']);
        $this->assertEquals(['Penicillin'], $result['medical_history']['allergies']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormTransformsVitalsToObservations(): void
    {
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Test',
            'patient_id' => 'pat-456',
            'name' => 'Test Patient',
            'vitals' => [
                'blood_pressure_systolic' => 120,
                'blood_pressure_diastolic' => 80,
                'heart_rate' => 72,
                'temperature' => 98.6,
                'oxygen_saturation' => 98,
            ],
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertArrayHasKey('observations', $result);
        $this->assertIsArray($result['observations']);
        $this->assertGreaterThan(0, count($result['observations']));
        
        // Find BP Systolic observation
        $bpSystolic = null;
        foreach ($result['observations'] as $obs) {
            if ($obs['label'] === 'BP Systolic') {
                $bpSystolic = $obs;
                break;
            }
        }
        
        $this->assertNotNull($bpSystolic);
        $this->assertEquals(120, $bpSystolic['value_num']);
        $this->assertEquals('mmHg', $bpSystolic['unit']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormPassesThroughObservationsFormat(): void
    {
        $observations = [
            ['label' => 'BP Systolic', 'value_num' => 130, 'unit' => 'mmHg'],
            ['label' => 'Pulse', 'value_num' => 80, 'unit' => 'bpm'],
        ];
        
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Test',
            'patient_id' => 'pat-456',
            'name' => 'Test Patient',
            'vitals' => $observations,
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        // Should pass through unchanged when already in observation format
        $this->assertEquals($observations, $result['observations']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormTransformsMedications(): void
    {
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Test',
            'patient_id' => 'pat-456',
            'name' => 'Test Patient',
            'medications' => [
                [
                    'medication_name' => 'Ibuprofen',
                    'dose' => '400mg',
                    'route' => 'PO',
                ],
            ],
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertArrayHasKey('medications_administered', $result);
        $this->assertCount(1, $result['medications_administered']);
        $this->assertEquals('Ibuprofen', $result['medications_administered'][0]['medication_name']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormMapsProviderInfo(): void
    {
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Test',
            'patient_id' => 'pat-456',
            'name' => 'Test Patient',
            'provider_npi' => '1234567890',
            'provider_credentials' => 'MD, FACEP',
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('1234567890', $result['provider']['npi']);
        $this->assertEquals('MD, FACEP', $result['provider']['credentials']);
    }

    /**
     * @test
     */
    public function createEncounterDataFromFormMapsOnsetContextFromInjuryClassification(): void
    {
        $formData = [
            'encounter_id' => 'enc-123',
            'chief_complaint' => 'Work injury',
            'patient_id' => 'pat-456',
            'name' => 'Test Patient',
            'injuryClassification' => 'work_related',
        ];
        
        $result = $this->builder->createEncounterDataFromForm($formData);
        
        $this->assertEquals('work_related', $result['encounter']['onset_context']);
    }

    // =========================================================================
    // PHI Handling Tests
    // =========================================================================

    /**
     * @test
     */
    public function buildPromptIncludesPatientNameInOutput(): void
    {
        $data = $this->createValidEncounterData();
        $data['patient']['name'] = 'Robert Johnson III';
        
        $prompt = $this->builder->buildPrompt($data);
        
        // Patient name should be included (needed for narrative context)
        $this->assertStringContainsString('Robert Johnson III', $prompt);
    }

    /**
     * @test
     */
    public function buildPromptIncludesPatientAgeInOutput(): void
    {
        $data = $this->createValidEncounterData();
        $data['patient']['age'] = 67;
        
        $prompt = $this->builder->buildPrompt($data);
        
        // Age should be included for clinical context
        $this->assertStringContainsString('67', $prompt);
    }

    /**
     * @test
     */
    public function buildPromptIncludesSexInOutput(): void
    {
        $data = $this->createValidEncounterData();
        $data['patient']['sex'] = 'Female';
        
        $prompt = $this->builder->buildPrompt($data);
        
        // Sex should be included for clinical context
        $this->assertStringContainsString('Female', $prompt);
    }

    /**
     * @test
     */
    public function buildPromptIncludesEmployerNameInOutput(): void
    {
        $data = $this->createFullEncounterData();
        
        $prompt = $this->builder->buildPrompt($data);
        
        // Employer name should be included for occupational health narratives
        $this->assertStringContainsString('ACME Industries', $prompt);
    }

    /**
     * @test
     */
    public function buildPromptSanitizesDataToOnlyAllowedFields(): void
    {
        $data = $this->createValidEncounterData();
        
        // Add fields that should NOT appear in the output
        $data['patient']['ssn'] = '123-45-6789';
        $data['patient']['social_security_number'] = '987-65-4321';
        $data['patient']['insurance_number'] = 'INS123456';
        $data['encounter']['billing_code'] = 'BILL999';
        
        $prompt = $this->builder->buildPrompt($data);
        
        // These sensitive fields should be filtered out
        $this->assertStringNotContainsString('123-45-6789', $prompt);
        $this->assertStringNotContainsString('987-65-4321', $prompt);
        $this->assertStringNotContainsString('INS123456', $prompt);
        $this->assertStringNotContainsString('BILL999', $prompt);
    }

    // =========================================================================
    // getSystemPrompt Tests
    // =========================================================================

    /**
     * @test
     */
    public function getSystemPromptReturnsNonEmptyString(): void
    {
        $prompt = $this->builder->getSystemPrompt();
        
        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
    }

    /**
     * @test
     */
    public function getSystemPromptContainsEmsContext(): void
    {
        $prompt = $this->builder->getSystemPrompt();
        
        $this->assertStringContainsString('EMS', $prompt);
        $this->assertStringContainsString('clinical', $prompt);
        $this->assertStringContainsString('narrative', $prompt);
    }

    /**
     * @test
     */
    public function getSystemPromptContainsSoapStructure(): void
    {
        $prompt = $this->builder->getSystemPrompt();
        
        $this->assertStringContainsString('Subjective', $prompt);
        $this->assertStringContainsString('Objective', $prompt);
        $this->assertStringContainsString('Plan', $prompt);
    }

    /**
     * @test
     */
    public function getSystemPromptContainsParamedicScopeGuidance(): void
    {
        $prompt = $this->builder->getSystemPrompt();
        
        $this->assertStringContainsString('paramedic', $prompt);
        $this->assertStringContainsString('NEVER diagnose', $prompt);
    }

    // =========================================================================
    // formatEncounterDataAsJson Tests
    // =========================================================================

    /**
     * @test
     */
    public function formatEncounterDataAsJsonReturnsValidJson(): void
    {
        $data = $this->createValidEncounterData();
        
        $json = $this->builder->formatEncounterDataAsJson($data);
        
        $this->assertIsString($json);
        
        // Should be valid JSON
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    /**
     * @test
     */
    public function formatEncounterDataAsJsonPreservesSanitizedData(): void
    {
        $data = $this->createValidEncounterData();
        
        $json = $this->builder->formatEncounterDataAsJson($data);
        $decoded = json_decode($json, true);
        
        $this->assertArrayHasKey('encounter', $decoded);
        $this->assertArrayHasKey('patient', $decoded);
        $this->assertEquals('enc-uuid-12345', $decoded['encounter']['encounter_id']);
    }

    /**
     * @test
     */
    public function formatEncounterDataAsJsonHandlesSpecialCharacters(): void
    {
        $data = $this->createValidEncounterData();
        $data['encounter']['chief_complaint'] = 'Patient complains of "severe" pain & nausea <acute onset>';
        
        $json = $this->builder->formatEncounterDataAsJson($data);
        
        // Should be valid JSON even with special characters
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
    }

    /**
     * @test
     */
    public function formatEncounterDataAsJsonHandlesUnicodeCharacters(): void
    {
        $data = $this->createValidEncounterData();
        $data['patient']['name'] = 'José García-Müller';
        
        $json = $this->builder->formatEncounterDataAsJson($data);
        
        // Unicode should be preserved (not escaped)
        $this->assertStringContainsString('José', $json);
        $this->assertStringContainsString('García', $json);
        $this->assertStringContainsString('Müller', $json);
    }

    // =========================================================================
    // getDataSummary Tests
    // =========================================================================

    /**
     * @test
     */
    public function getDataSummaryReturnsStructuredSummary(): void
    {
        $data = $this->createFullEncounterData();
        
        $summary = $this->builder->getDataSummary($data);
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('has_encounter', $summary);
        $this->assertArrayHasKey('has_patient', $summary);
        $this->assertArrayHasKey('has_medical_history', $summary);
        $this->assertArrayHasKey('observation_count', $summary);
        $this->assertArrayHasKey('medication_count', $summary);
        $this->assertArrayHasKey('has_provider', $summary);
    }

    /**
     * @test
     */
    public function getDataSummaryReturnsTrueForPresentSections(): void
    {
        $data = $this->createFullEncounterData();
        
        $summary = $this->builder->getDataSummary($data);
        
        $this->assertTrue($summary['has_encounter']);
        $this->assertTrue($summary['has_patient']);
        $this->assertTrue($summary['has_medical_history']);
        $this->assertTrue($summary['has_provider']);
    }

    /**
     * @test
     */
    public function getDataSummaryCountsObservationsCorrectly(): void
    {
        $data = $this->createFullEncounterData();
        
        $summary = $this->builder->getDataSummary($data);
        
        $this->assertEquals(4, $summary['observation_count']);
    }

    /**
     * @test
     */
    public function getDataSummaryCountsMedicationsCorrectly(): void
    {
        $data = $this->createFullEncounterData();
        
        $summary = $this->builder->getDataSummary($data);
        
        $this->assertEquals(1, $summary['medication_count']);
    }

    /**
     * @test
     */
    public function getDataSummaryDoesNotContainPhi(): void
    {
        $data = $this->createFullEncounterData();
        
        $summary = $this->builder->getDataSummary($data);
        $summaryJson = json_encode($summary);
        
        // Should not contain actual PHI values
        $this->assertStringNotContainsString('Jane Smith', $summaryJson);
        $this->assertStringNotContainsString('pat-uuid-67890', $summaryJson);
        $this->assertStringNotContainsString('hand laceration', $summaryJson);
    }

    /**
     * @test
     */
    public function getDataSummaryIncludesEncounterTypeWithoutPhi(): void
    {
        $data = $this->createFullEncounterData();
        
        $summary = $this->builder->getDataSummary($data);
        
        $this->assertArrayHasKey('encounter_type', $summary);
        $this->assertEquals('osha_injury', $summary['encounter_type']);
    }

    /**
     * @test
     */
    public function getDataSummaryIndicatesChiefComplaintPresence(): void
    {
        $data = $this->createFullEncounterData();
        
        $summary = $this->builder->getDataSummary($data);
        
        $this->assertArrayHasKey('has_chief_complaint', $summary);
        $this->assertTrue($summary['has_chief_complaint']);
    }

    /**
     * @test
     */
    public function getDataSummaryHandlesMinimalData(): void
    {
        $data = $this->createValidEncounterData();
        
        $summary = $this->builder->getDataSummary($data);
        
        $this->assertTrue($summary['has_encounter']);
        $this->assertTrue($summary['has_patient']);
        $this->assertFalse($summary['has_medical_history']);
        $this->assertEquals(0, $summary['observation_count']);
        $this->assertEquals(0, $summary['medication_count']);
        $this->assertFalse($summary['has_provider']);
    }
}
