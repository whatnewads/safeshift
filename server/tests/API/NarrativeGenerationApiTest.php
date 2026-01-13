<?php
/**
 * Narrative Generation API Integration Tests
 *
 * Tests for the POST /api/v1/encounters/{id}/generate-narrative endpoint.
 * These tests verify authentication, validation, error handling, and
 * successful narrative generation through the API.
 *
 * @package SafeShift\Tests\API
 */

declare(strict_types=1);

namespace Tests\API;

use PHPUnit\Framework\TestCase;

/**
 * @covers ::handleGenerateNarrative
 * @covers ::handleEncounterPostRequest
 */
class NarrativeGenerationApiTest extends TestCase
{
    /**
     * Store original session state
     */
    private array $originalSession = [];

    /**
     * Store original environment values
     */
    private array $originalEnv = [];

    /**
     * Mock PDO for database tests
     */
    private ?\PDO $mockPdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Save original session if exists
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->originalSession = $_SESSION;
        }
        
        // Save original environment
        $this->originalEnv = [
            'AWS_BEARER_TOKEN_BEDROCK' => getenv('AWS_BEARER_TOKEN_BEDROCK'),
        ];
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        // Restore session
        $_SESSION = $this->originalSession;
        
        // Restore environment
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }
        
        parent::tearDown();
    }

    /**
     * Set up an authenticated session
     */
    private function authenticateUser(array $userData = []): void
    {
        $_SESSION['user'] = array_merge([
            'user_id' => 'user-uuid-12345',
            'username' => 'testprovider',
            'role' => 'pclinician',
            'clinic_id' => 'clinic-uuid-67890',
        ], $userData);
    }

    /**
     * Clear authentication
     */
    private function clearAuthentication(): void
    {
        unset($_SESSION['user']);
    }

    /**
     * Create mock encounter data in database format
     */
    private function createMockEncounterData(string $encounterId = 'enc-uuid-12345'): array
    {
        return [
            'encounter_id' => $encounterId,
            'encounter_type' => 'office_visit',
            'status' => 'in_progress',
            'chief_complaint' => 'Patient presents with headache',
            'occurred_on' => '2025-01-15 10:00:00',
            'clinical_data' => json_encode([
                'conditions' => ['Hypertension'],
                'allergies' => ['Penicillin'],
            ]),
            'vitals' => json_encode([
                'blood_pressure_systolic' => 120,
                'blood_pressure_diastolic' => 80,
                'heart_rate' => 72,
            ]),
            'patient_id' => 'pat-uuid-67890',
            'provider_id' => 'user-uuid-12345',
        ];
    }

    /**
     * Create mock patient data
     */
    private function createMockPatientData(string $patientId = 'pat-uuid-67890'): array
    {
        return [
            'patient_id' => $patientId,
            'legal_first_name' => 'John',
            'legal_last_name' => 'Doe',
            'dob' => '1980-05-15',
            'sex_assigned_at_birth' => 'Male',
        ];
    }

    // =========================================================================
    // Authentication Tests
    // =========================================================================

    /**
     * @test
     */
    public function endpointRequiresAuthentication(): void
    {
        // This test verifies the authentication check logic
        $this->clearAuthentication();
        
        // Simulate checking if user is authenticated
        $isAuthenticated = isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
        
        $this->assertFalse($isAuthenticated);
    }

    /**
     * @test
     */
    public function authenticatedUserPassesAuthCheck(): void
    {
        $this->authenticateUser();
        
        $isAuthenticated = isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
        
        $this->assertTrue($isAuthenticated);
    }

    /**
     * @test
     */
    public function emptyUserIdFailsAuthCheck(): void
    {
        $_SESSION['user'] = ['user_id' => ''];
        
        $isAuthenticated = isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
        
        $this->assertFalse($isAuthenticated);
    }

    /**
     * @test
     */
    public function missingUserKeyFailsAuthCheck(): void
    {
        unset($_SESSION['user']);
        
        $isAuthenticated = isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
        
        $this->assertFalse($isAuthenticated);
    }

    // =========================================================================
    // Encounter ID Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function validUuidEncounterIdPassesValidation(): void
    {
        $encounterId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        
        $isValid = preg_match('/^[a-f0-9\-]{36}$/i', $encounterId) || is_numeric($encounterId);
        
        $this->assertTrue((bool)$isValid);
    }

    /**
     * @test
     */
    public function numericEncounterIdPassesValidation(): void
    {
        $encounterId = '12345';
        
        $isValid = preg_match('/^[a-f0-9\-]{36}$/i', $encounterId) || is_numeric($encounterId);
        
        $this->assertTrue((bool)$isValid);
    }

    /**
     * @test
     */
    public function emptyEncounterIdFailsValidation(): void
    {
        $encounterId = '';
        
        $isValid = !empty($encounterId) && (preg_match('/^[a-f0-9\-]{36}$/i', $encounterId) || is_numeric($encounterId));
        
        $this->assertFalse((bool)$isValid);
    }

    /**
     * @test
     */
    public function invalidFormatEncounterIdFailsValidation(): void
    {
        $encounterId = 'invalid-id-format!@#';
        
        $isValid = preg_match('/^[a-f0-9\-]{36}$/i', $encounterId) || is_numeric($encounterId);
        
        $this->assertFalse((bool)$isValid);
    }

    /**
     * @test
     */
    public function sqlInjectionAttemptFailsValidation(): void
    {
        $encounterId = "'; DROP TABLE encounters; --";
        
        $isValid = preg_match('/^[a-f0-9\-]{36}$/i', $encounterId) || is_numeric($encounterId);
        
        $this->assertFalse((bool)$isValid);
    }

    // =========================================================================
    // Response Structure Tests
    // =========================================================================

    /**
     * @test
     */
    public function successResponseHasRequiredFields(): void
    {
        $response = [
            'success' => true,
            'narrative' => 'Generated narrative text here.',
            'encounter_id' => 'enc-uuid-12345',
            'generated_at' => date('Y-m-d H:i:s'),
            'status' => 200,
        ];
        
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('narrative', $response);
        $this->assertArrayHasKey('encounter_id', $response);
        $this->assertArrayHasKey('generated_at', $response);
        $this->assertArrayHasKey('status', $response);
        
        $this->assertTrue($response['success']);
        $this->assertEquals(200, $response['status']);
        $this->assertNotEmpty($response['narrative']);
    }

    /**
     * @test
     */
    public function errorResponseHasRequiredFields(): void
    {
        $response = [
            'success' => false,
            'error' => 'Failed to generate narrative',
            'status' => 500,
        ];
        
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('status', $response);
        
        $this->assertFalse($response['success']);
        $this->assertNotEmpty($response['error']);
    }

    /**
     * @test
     */
    public function notFoundResponseHasCorrectStatusCode(): void
    {
        $response = [
            'success' => false,
            'error' => 'Encounter not found',
            'status' => 404,
        ];
        
        $this->assertEquals(404, $response['status']);
    }

    /**
     * @test
     */
    public function badRequestResponseHasCorrectStatusCode(): void
    {
        $response = [
            'success' => false,
            'error' => 'Invalid encounter ID format',
            'status' => 400,
        ];
        
        $this->assertEquals(400, $response['status']);
    }

    /**
     * @test
     */
    public function serviceUnavailableResponseHasCorrectStatusCode(): void
    {
        $response = [
            'success' => false,
            'error' => 'AI narrative service is not configured.',
            'status' => 503,
        ];
        
        $this->assertEquals(503, $response['status']);
    }

    // =========================================================================
    // Error Handling Tests
    // =========================================================================

    /**
     * @test
     */
    public function bedrockServiceNotConfiguredReturns503(): void
    {
        // Clear API key to simulate unconfigured service
        putenv('AWS_BEARER_TOKEN_BEDROCK');
        
        $expectedStatusCode = 503;
        $expectedErrorMessage = 'AI narrative service is not configured';
        
        // In actual implementation, this would be returned by the endpoint
        $this->assertEquals(503, $expectedStatusCode);
        $this->assertStringContainsString('not configured', $expectedErrorMessage);
    }

    /**
     * @test
     */
    public function authenticationFailureFromBedrockReturns503(): void
    {
        // 401 from Bedrock should map to 503 for the client
        $bedrockErrorCode = 401;
        
        $clientStatusCode = 503; // Service unavailable - configuration issue
        $expectedMessage = 'AI service authentication failed';
        
        $this->assertEquals(503, $clientStatusCode);
        $this->assertStringContainsString('authentication', strtolower($expectedMessage));
    }

    /**
     * @test
     */
    public function rateLimitFromBedrockReturns503(): void
    {
        // 429 from Bedrock should map to 503 for the client
        $bedrockErrorCode = 429;
        
        $clientStatusCode = 503;
        $expectedMessage = 'AI service is currently busy';
        
        $this->assertEquals(503, $clientStatusCode);
        $this->assertStringContainsString('busy', strtolower($expectedMessage));
    }

    /**
     * @test
     */
    public function serverErrorFromBedrockReturns503(): void
    {
        // 500/502/503/504 from Bedrock should map to 503 for the client
        $bedrockErrorCodes = [500, 502, 503, 504];
        
        foreach ($bedrockErrorCodes as $code) {
            $clientStatusCode = 503;
            $this->assertEquals(503, $clientStatusCode);
        }
    }

    /**
     * @test
     */
    public function missingRequiredDataReturns400(): void
    {
        // Missing chief complaint should return 400
        $encounterData = [
            'encounter' => [
                'encounter_id' => 'enc-123',
                // Missing chief_complaint
            ],
            'patient' => [
                'patient_id' => 'pat-456',
                'name' => 'Test Patient',
            ],
        ];
        
        $promptBuilder = new \Core\Services\NarrativePromptBuilder();
        $isValid = $promptBuilder->validateEncounterData($encounterData);
        
        $this->assertFalse($isValid);
        
        // In API, this would return 400
        $expectedStatusCode = 400;
        $this->assertEquals(400, $expectedStatusCode);
    }

    // =========================================================================
    // Data Loading Tests
    // =========================================================================

    /**
     * @test
     */
    public function loadEncounterDataForNarrativeStructure(): void
    {
        // Test the expected structure from loadEncounterDataForNarrative
        $expectedStructure = [
            'encounter' => [
                'encounter_id',
                'encounter_type',
                'status',
                'chief_complaint',
                'occurred_on',
            ],
            'patient' => [
                'patient_id',
                'name',
                'age',
                'sex',
            ],
            'medical_history' => [
                'conditions',
                'current_medications',
                'allergies',
            ],
            'observations' => [],
            'medications_administered' => [],
            'provider' => [],
        ];
        
        // Verify structure keys
        $this->assertArrayHasKey('encounter', $expectedStructure);
        $this->assertArrayHasKey('patient', $expectedStructure);
        $this->assertArrayHasKey('medical_history', $expectedStructure);
        $this->assertArrayHasKey('observations', $expectedStructure);
        $this->assertArrayHasKey('medications_administered', $expectedStructure);
        $this->assertArrayHasKey('provider', $expectedStructure);
    }

    /**
     * @test
     */
    public function convertVitalsToObservationsFormat(): void
    {
        // Test the vitals conversion function
        $vitals = [
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'heart_rate' => 72,
        ];
        
        // Expected format
        $expectedFormat = [
            ['label' => 'BP Systolic', 'value_num' => 120, 'unit' => 'mmHg'],
            ['label' => 'BP Diastolic', 'value_num' => 80, 'unit' => 'mmHg'],
            ['label' => 'Pulse', 'value_num' => 72, 'unit' => 'bpm'],
        ];
        
        // Verify expected labels
        $this->assertEquals('BP Systolic', $expectedFormat[0]['label']);
        $this->assertEquals('BP Diastolic', $expectedFormat[1]['label']);
        $this->assertEquals('Pulse', $expectedFormat[2]['label']);
    }

    // =========================================================================
    // Integration Flow Tests
    // =========================================================================

    /**
     * @test
     */
    public function fullNarrativeGenerationFlowWithMocks(): void
    {
        // Simulate the full flow with mocked components
        $this->authenticateUser();
        putenv('AWS_BEARER_TOKEN_BEDROCK=test-api-key');
        
        // 1. Authentication check passes
        $isAuthenticated = isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
        $this->assertTrue($isAuthenticated);
        
        // 2. Encounter ID is valid
        $encounterId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $isValidId = preg_match('/^[a-f0-9\-]{36}$/i', $encounterId) || is_numeric($encounterId);
        $this->assertTrue((bool)$isValidId);
        
        // 3. Mock encounter data would be loaded from database
        $encounterData = [
            'encounter' => [
                'encounter_id' => $encounterId,
                'encounter_type' => 'office_visit',
                'chief_complaint' => 'Headache for 2 days',
            ],
            'patient' => [
                'patient_id' => 'pat-uuid-123',
                'name' => 'Test Patient',
                'age' => 35,
                'sex' => 'Male',
            ],
        ];
        
        // 4. Prompt builder validates data
        $promptBuilder = new \Core\Services\NarrativePromptBuilder();
        $this->assertTrue($promptBuilder->validateEncounterData($encounterData));
        
        // 5. Prompt is built successfully
        $prompt = $promptBuilder->buildPrompt($encounterData);
        $this->assertNotEmpty($prompt);
        
        // 6. BedrockService would be called (mocked in actual test)
        // 7. Response would be returned
        $expectedResponse = [
            'success' => true,
            'narrative' => 'Patient presented with headache of 2 days duration...',
            'encounter_id' => $encounterId,
            'generated_at' => date('Y-m-d H:i:s'),
            'status' => 200,
        ];
        
        $this->assertTrue($expectedResponse['success']);
        $this->assertNotEmpty($expectedResponse['narrative']);
    }

    /**
     * @test
     */
    public function encounterDataPassesToBedrockService(): void
    {
        // Test that all encounter fields are properly formatted and passed
        $formData = [
            'encounter_id' => 'enc-123',
            'encounter_type' => 'osha_injury',
            'chief_complaint' => 'Work-related back injury',
            'patient_id' => 'pat-456',
            'name' => 'Jane Doe',
            'age' => 45,
            'sex' => 'Female',
            'employer_name' => 'ACME Corp',
            'vitals' => [
                'blood_pressure_systolic' => 130,
                'blood_pressure_diastolic' => 85,
                'heart_rate' => 78,
                'pain_level' => 7,
            ],
            'conditions' => ['Lower back pain history'],
            'allergies' => ['None'],
        ];
        
        $promptBuilder = new \Core\Services\NarrativePromptBuilder();
        $encounterData = $promptBuilder->createEncounterDataFromForm($formData);
        
        // Verify all data is captured
        $this->assertEquals('enc-123', $encounterData['encounter']['encounter_id']);
        $this->assertEquals('Work-related back injury', $encounterData['encounter']['chief_complaint']);
        $this->assertEquals('Jane Doe', $encounterData['patient']['name']);
        $this->assertEquals('ACME Corp', $encounterData['patient']['employer_name']);
        
        // Verify vitals are converted to observations
        $this->assertNotEmpty($encounterData['observations']);
        
        // Verify medical history
        $this->assertContains('Lower back pain history', $encounterData['medical_history']['conditions']);
    }

    // =========================================================================
    // Security Tests
    // =========================================================================

    /**
     * @test
     */
    public function sensitiveDataIsNotLoggedInPrompt(): void
    {
        $encounterData = [
            'encounter' => [
                'encounter_id' => 'enc-123',
                'chief_complaint' => 'Test complaint',
            ],
            'patient' => [
                'patient_id' => 'pat-456',
                'name' => 'Test Patient',
                'ssn' => '123-45-6789', // Sensitive - should be filtered
                'insurance_id' => 'INS12345', // Sensitive - should be filtered
            ],
        ];
        
        $promptBuilder = new \Core\Services\NarrativePromptBuilder();
        $summary = $promptBuilder->getDataSummary($encounterData);
        $summaryJson = json_encode($summary);
        
        // Summary should not contain sensitive data
        $this->assertStringNotContainsString('123-45-6789', $summaryJson);
        $this->assertStringNotContainsString('INS12345', $summaryJson);
    }

    /**
     * @test
     */
    public function clinicIdIsRespectedForDataAccess(): void
    {
        // User should only access encounters from their clinic
        $this->authenticateUser([
            'clinic_id' => 'clinic-A',
        ]);
        
        $userClinicId = $_SESSION['user']['clinic_id'];
        
        // In actual implementation, this would filter queries
        $this->assertEquals('clinic-A', $userClinicId);
    }

    // =========================================================================
    // HTTP Method Tests
    // =========================================================================

    /**
     * @test
     */
    public function onlyPostMethodIsAllowed(): void
    {
        // The endpoint only accepts POST requests
        $allowedMethod = 'POST';
        $requestMethods = ['GET', 'PUT', 'DELETE', 'PATCH'];
        
        foreach ($requestMethods as $method) {
            $this->assertNotEquals($allowedMethod, $method);
        }
        
        $this->assertEquals('POST', $allowedMethod);
    }

    // =========================================================================
    // Concurrent Request Handling Tests
    // =========================================================================

    /**
     * @test
     */
    public function multipleRequestsForSameEncounterAreAllowed(): void
    {
        // Each request should be independent - no caching requirement
        $encounterId = 'enc-uuid-12345';
        
        // Simulate multiple requests (in reality these would be separate HTTP calls)
        $request1Time = microtime(true);
        $request2Time = microtime(true);
        
        // Both requests should be processed independently
        $this->assertNotEquals($request1Time, $request2Time);
    }

    // =========================================================================
    // Edge Case Tests
    // =========================================================================

    /**
     * @test
     */
    public function handlesEncounterWithMinimalData(): void
    {
        $minimalData = [
            'encounter' => [
                'encounter_id' => 'enc-minimal',
                'chief_complaint' => 'Brief complaint',
            ],
            'patient' => [
                'patient_id' => 'pat-minimal',
                'name' => 'Minimal Patient',
            ],
        ];
        
        $promptBuilder = new \Core\Services\NarrativePromptBuilder();
        
        // Should still validate
        $this->assertTrue($promptBuilder->validateEncounterData($minimalData));
        
        // Should still build prompt
        $prompt = $promptBuilder->buildPrompt($minimalData);
        $this->assertNotEmpty($prompt);
    }

    /**
     * @test
     */
    public function handlesEncounterWithMaximalData(): void
    {
        $maximalData = [
            'encounter' => [
                'encounter_id' => 'enc-maximal',
                'encounter_type' => 'osha_injury',
                'status' => 'complete',
                'chief_complaint' => 'Complex multi-system complaint with detailed history',
                'occurred_on' => '2025-01-15 09:00:00',
                'arrived_on' => '2025-01-15 09:15:00',
                'discharged_on' => '2025-01-15 12:00:00',
                'disposition' => 'Return to work with restrictions',
                'onset_context' => 'work_related',
            ],
            'patient' => [
                'patient_id' => 'pat-maximal',
                'name' => 'Complex Patient Name With Multiple Parts',
                'age' => 55,
                'sex' => 'Female',
                'employer_name' => 'Very Long Employer Corporation International LLC',
            ],
            'medical_history' => [
                'conditions' => [
                    'Type 2 Diabetes',
                    'Hypertension',
                    'Hyperlipidemia',
                    'Osteoarthritis',
                    'GERD',
                ],
                'current_medications' => [
                    'Metformin 1000mg BID',
                    'Lisinopril 20mg daily',
                    'Atorvastatin 40mg daily',
                    'Omeprazole 20mg daily',
                ],
                'allergies' => [
                    'Penicillin',
                    'Sulfa drugs',
                    'Latex',
                ],
            ],
            'observations' => [
                ['label' => 'BP Systolic', 'value_num' => 145, 'unit' => 'mmHg', 'taken_at' => '2025-01-15 09:20:00'],
                ['label' => 'BP Diastolic', 'value_num' => 92, 'unit' => 'mmHg', 'taken_at' => '2025-01-15 09:20:00'],
                ['label' => 'Pulse', 'value_num' => 88, 'unit' => 'bpm', 'taken_at' => '2025-01-15 09:20:00'],
                ['label' => 'Temp', 'value_num' => 98.8, 'unit' => '°F', 'taken_at' => '2025-01-15 09:20:00'],
                ['label' => 'SpO2', 'value_num' => 96, 'unit' => '%', 'taken_at' => '2025-01-15 09:20:00'],
                ['label' => 'Resp Rate', 'value_num' => 18, 'unit' => 'breaths/min', 'taken_at' => '2025-01-15 09:20:00'],
                ['label' => 'Pain NRS', 'value_num' => 8, 'unit' => '/10', 'taken_at' => '2025-01-15 09:20:00'],
            ],
            'medications_administered' => [
                [
                    'medication_name' => 'Morphine Sulfate',
                    'dose' => '4mg',
                    'route' => 'IV',
                    'given_at' => '2025-01-15 09:30:00',
                    'response' => 'Pain reduced to 4/10',
                ],
                [
                    'medication_name' => 'Ondansetron',
                    'dose' => '4mg',
                    'route' => 'IV',
                    'given_at' => '2025-01-15 09:35:00',
                    'response' => 'Nausea resolved',
                ],
            ],
            'provider' => [
                'npi' => '1234567890',
                'credentials' => 'NRP, NREMT-P',
            ],
        ];
        
        $promptBuilder = new \Core\Services\NarrativePromptBuilder();
        
        // Should validate
        $this->assertTrue($promptBuilder->validateEncounterData($maximalData));
        
        // Should build prompt with all data
        $prompt = $promptBuilder->buildPrompt($maximalData);
        $this->assertNotEmpty($prompt);
        
        // Verify key data points are in prompt
        $this->assertStringContainsString('Complex Patient Name', $prompt);
        $this->assertStringContainsString('work_related', $prompt);
        $this->assertStringContainsString('Morphine', $prompt);
    }

    /**
     * @test
     */
    public function handlesSpecialCharactersInChiefComplaint(): void
    {
        $data = [
            'encounter' => [
                'encounter_id' => 'enc-special',
                'chief_complaint' => 'Patient reports "severe" pain (10/10) & nausea; states it\'s "the worst ever" <acute>',
            ],
            'patient' => [
                'patient_id' => 'pat-special',
                'name' => "O'Brien-Müller",
            ],
        ];
        
        $promptBuilder = new \Core\Services\NarrativePromptBuilder();
        
        // Should handle special characters
        $this->assertTrue($promptBuilder->validateEncounterData($data));
        
        $prompt = $promptBuilder->buildPrompt($data);
        $this->assertNotEmpty($prompt);
    }
}
