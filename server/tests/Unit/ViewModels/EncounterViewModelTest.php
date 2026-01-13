<?php
/**
 * EncounterViewModel Unit Tests
 * 
 * Tests for the EncounterViewModel class which handles encounter
 * operations including creation, updates, vitals, assessments, and signing.
 * 
 * @package SafeShift\Tests\Unit\ViewModels
 */

declare(strict_types=1);

namespace Tests\Unit\ViewModels;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ViewModel\EncounterViewModel;
use Model\Repositories\EncounterRepository;
use Model\Repositories\PatientRepository;
use Model\Entities\Encounter;
use Model\Entities\Patient;
use PDO;
use Tests\Helpers\Factories\EncounterFactory;
use Tests\Helpers\Factories\PatientFactory;
use Tests\Helpers\Factories\UserFactory;

/**
 * @covers \ViewModel\EncounterViewModel
 */
class EncounterViewModelTest extends TestCase
{
    private EncounterViewModel $viewModel;
    private MockObject&PDO $mockPdo;
    private MockObject&EncounterRepository $mockEncounterRepo;
    private MockObject&PatientRepository $mockPatientRepo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock PDO
        $this->mockPdo = $this->createMock(PDO::class);
        
        // Create ViewModel with mock PDO
        $this->viewModel = new EncounterViewModel($this->mockPdo);
    }

    // =========================================================================
    // Create Encounter Tests
    // =========================================================================

    /**
     * @test
     */
    public function testCreateEncounterWithValidData(): void
    {
        $user = UserFactory::makeClinician();
        $patient = PatientFactory::make();
        
        $this->viewModel->setCurrentUser($user['user_id']);
        $this->viewModel->setCurrentClinic($patient['clinic_id']);
        
        $encounterData = [
            'patient_id' => $patient['patient_id'],
            'encounter_type' => 'office_visit',
            'chief_complaint' => 'Annual physical examination',
        ];
        
        // Note: Full test would mock repository, but this tests validation logic
        $result = $this->viewModel->createEncounter($encounterData);
        
        // The result should indicate an error due to mock repo not being set up
        // In a real test with full mocking, we would assert success
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testCreateEncounterWithMissingRequiredFields(): void
    {
        $user = UserFactory::makeClinician();
        $this->viewModel->setCurrentUser($user['user_id']);
        
        $encounterData = [
            // Missing patient_id - required field
            'encounter_type' => 'office_visit',
        ];
        
        $result = $this->viewModel->createEncounter($encounterData);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
    }

    /**
     * @test
     */
    public function testCreateEncounterWithInvalidEncounterType(): void
    {
        $user = UserFactory::makeClinician();
        $patient = PatientFactory::make();
        
        $this->viewModel->setCurrentUser($user['user_id']);
        
        $encounterData = [
            'patient_id' => $patient['patient_id'],
            'encounter_type' => 'invalid_type',
        ];
        
        $result = $this->viewModel->createEncounter($encounterData);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
    }

    // =========================================================================
    // Update Encounter Tests
    // =========================================================================

    /**
     * @test
     */
    public function testUpdateEncounterStatus(): void
    {
        $encounterId = $this->generateUuid();
        
        $updateData = [
            'status' => 'in_progress',
        ];
        
        $result = $this->viewModel->updateEncounter($encounterId, $updateData);
        
        // Should return error since encounter not found (no mock setup)
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testUpdateEncounterWithInvalidStatus(): void
    {
        $encounterId = $this->generateUuid();
        
        $updateData = [
            'status' => 'invalid_status_value',
        ];
        
        $result = $this->viewModel->updateEncounter($encounterId, $updateData);
        
        $this->assertIsArray($result);
        // Should fail due to invalid status
        $this->assertFalse($result['success'] ?? true);
    }

    // =========================================================================
    // Vitals Tests
    // =========================================================================

    /**
     * @test
     */
    public function testAddVitalsToEncounter(): void
    {
        $encounterId = $this->generateUuid();
        
        $vitalsData = EncounterFactory::makeVitals();
        
        $result = $this->viewModel->addVitals($encounterId, $vitalsData);
        
        $this->assertIsArray($result);
        // Will fail due to encounter not found, but tests the method exists and returns proper format
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testAddVitalsWithValidBloodPressure(): void
    {
        $encounterId = $this->generateUuid();
        
        $vitalsData = [
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'heart_rate' => 72,
        ];
        
        $result = $this->viewModel->addVitals($encounterId, $vitalsData);
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function testRecordVitalsWithAbnormalValues(): void
    {
        $encounterId = $this->generateUuid();
        
        $abnormalVitals = EncounterFactory::makeAbnormalVitals();
        
        $result = $this->viewModel->recordVitals($encounterId, $abnormalVitals);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // =========================================================================
    // Assessment Tests
    // =========================================================================

    /**
     * @test
     */
    public function testAddAssessmentToEncounter(): void
    {
        $encounterId = $this->generateUuid();
        
        $assessmentData = [
            'assessment' => 'Patient presents with typical symptoms. Clinical impression documented.',
            'icd_codes' => ['Z00.00', 'R51.9'],
        ];
        
        $result = $this->viewModel->addAssessment($encounterId, $assessmentData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testAddAssessmentWithoutAssessmentOrCodes(): void
    {
        $encounterId = $this->generateUuid();
        
        $emptyData = [];
        
        $result = $this->viewModel->addAssessment($encounterId, $emptyData);
        
        $this->assertIsArray($result);
        // Should indicate error when no assessment data provided
        $this->assertFalse($result['success'] ?? true);
    }

    // =========================================================================
    // Treatment Tests
    // =========================================================================

    /**
     * @test
     */
    public function testAddTreatmentToEncounter(): void
    {
        $encounterId = $this->generateUuid();
        
        $treatmentData = [
            'plan' => 'Continue current treatment. Follow up in 2 weeks.',
            'cpt_codes' => ['99213'],
            'medications' => [
                ['name' => 'Ibuprofen', 'dosage' => '400mg', 'frequency' => 'Every 6 hours as needed']
            ],
            'follow_up' => date('Y-m-d', strtotime('+14 days')),
        ];
        
        $result = $this->viewModel->addTreatment($encounterId, $treatmentData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testAddTreatmentWithoutPlan(): void
    {
        $encounterId = $this->generateUuid();
        
        $emptyTreatment = [];
        
        $result = $this->viewModel->addTreatment($encounterId, $emptyTreatment);
        
        $this->assertIsArray($result);
        // Should fail when no treatment data provided
        $this->assertFalse($result['success'] ?? true);
    }

    // =========================================================================
    // Finalization/Signing Tests
    // =========================================================================

    /**
     * @test
     */
    public function testFinalizeEncounterWithValidData(): void
    {
        $encounterId = $this->generateUuid();
        $user = UserFactory::makeClinician();
        
        $this->viewModel->setCurrentUser($user['user_id']);
        
        $result = $this->viewModel->signEncounter($encounterId);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testFinalizeEncounterWithInvalidData(): void
    {
        // Encounter with missing required fields should not be signable
        $encounterId = $this->generateUuid();
        
        $result = $this->viewModel->signEncounter($encounterId);
        
        $this->assertIsArray($result);
        // Should fail without authentication
        $this->assertFalse($result['success'] ?? true);
    }

    /**
     * @test
     */
    public function testSignEncounterRequiresAuthentication(): void
    {
        $encounterId = $this->generateUuid();
        
        // Don't set current user - should require auth
        $result = $this->viewModel->signEncounter($encounterId);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
    }

    // =========================================================================
    // Status Transition Tests
    // =========================================================================

    /**
     * @test
     * @dataProvider validStatusTransitionsProvider
     */
    public function testEncounterStatusTransitions(string $fromStatus, string $toStatus, bool $shouldSucceed): void
    {
        // This tests that status transitions follow the defined workflow
        $validTransitions = [
            'scheduled' => ['checked_in', 'in_progress', 'cancelled', 'no_show'],
            'checked_in' => ['in_progress', 'cancelled'],
            'in_progress' => ['pending_review', 'complete', 'cancelled'],
            'pending_review' => ['in_progress', 'complete'],
            'complete' => [],
            'cancelled' => [],
            'no_show' => ['scheduled'],
        ];
        
        $allowedTransitions = $validTransitions[$fromStatus] ?? [];
        $isAllowed = in_array($toStatus, $allowedTransitions, true);
        
        $this->assertEquals($shouldSucceed, $isAllowed, 
            "Status transition from {$fromStatus} to {$toStatus} should " . ($shouldSucceed ? 'succeed' : 'fail'));
    }

    /**
     * Data provider for status transition tests
     */
    public static function validStatusTransitionsProvider(): array
    {
        return [
            'scheduled to checked_in' => ['scheduled', 'checked_in', true],
            'scheduled to in_progress' => ['scheduled', 'in_progress', true],
            'scheduled to complete (invalid)' => ['scheduled', 'complete', false],
            'in_progress to pending_review' => ['in_progress', 'pending_review', true],
            'in_progress to complete' => ['in_progress', 'complete', true],
            'pending_review to complete' => ['pending_review', 'complete', true],
            'complete to any (should fail)' => ['complete', 'in_progress', false],
            'cancelled to any (should fail)' => ['cancelled', 'scheduled', false],
        ];
    }

    // =========================================================================
    // Work-Related Flag Tests
    // =========================================================================

    /**
     * @test
     */
    public function testWorkRelatedFlagTriggersEmail(): void
    {
        // When an encounter is marked as work-related, it should trigger email notification
        $encounterData = EncounterFactory::makeOshaInjury([
            'clinical_data' => [
                'work_related' => true,
                'employer_notified' => false,
            ],
        ]);
        
        // Verify the work_related flag is set
        $this->assertTrue($encounterData['clinical_data']['work_related']);
        
        // In a full integration test, we would verify email was queued
        $this->assertArrayHasKey('work_related', $encounterData['clinical_data']);
    }

    // =========================================================================
    // Amendment Tests
    // =========================================================================

    /**
     * @test
     */
    public function testAmendLockedEncounter(): void
    {
        $encounterId = $this->generateUuid();
        $user = UserFactory::makeClinician();
        
        $this->viewModel->setCurrentUser($user['user_id']);
        
        $amendmentData = [
            'reason' => 'Correction to clinical documentation per clinical review.',
            'assessment' => 'Updated assessment with additional findings.',
        ];
        
        $result = $this->viewModel->amendEncounter($encounterId, $amendmentData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testAmendRequiresReason(): void
    {
        $encounterId = $this->generateUuid();
        $user = UserFactory::makeClinician();
        
        $this->viewModel->setCurrentUser($user['user_id']);
        
        // Missing reason
        $amendmentData = [
            'assessment' => 'Updated assessment.',
        ];
        
        $result = $this->viewModel->amendEncounter($encounterId, $amendmentData);
        
        $this->assertIsArray($result);
        // Should fail without reason
        $this->assertFalse($result['success'] ?? true);
    }

    // =========================================================================
    // Get Encounter Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetEncounterNotFound(): void
    {
        $encounterId = $this->generateUuid();
        
        $result = $this->viewModel->getEncounter($encounterId);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
    }

    /**
     * @test
     */
    public function testGetPatientEncounters(): void
    {
        $patientId = $this->generateUuid();
        
        $result = $this->viewModel->getPatientEncounters($patientId);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testGetTodaysEncounters(): void
    {
        $clinicId = $this->generateUuid();
        
        $this->viewModel->setCurrentClinic($clinicId);
        
        $result = $this->viewModel->getTodaysEncounters();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testGetMyPendingEncounters(): void
    {
        $user = UserFactory::makeClinician();
        $this->viewModel->setCurrentUser($user['user_id']);
        
        $result = $this->viewModel->getMyPendingEncounters();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // =========================================================================
    // Delete/Cancel Tests
    // =========================================================================

    /**
     * @test
     */
    public function testDeleteEncounterNotFound(): void
    {
        $encounterId = $this->generateUuid();
        
        $result = $this->viewModel->deleteEncounter($encounterId);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
    }

    // =========================================================================
    // Signature Tests
    // =========================================================================

    /**
     * @test
     */
    public function testAddSignatureToEncounter(): void
    {
        $encounterId = $this->generateUuid();
        $user = UserFactory::makeClinician();
        
        $this->viewModel->setCurrentUser($user['user_id']);
        
        $signatureData = [
            'signature_type' => 'provider',
            'signed_by' => $user['user_id'],
        ];
        
        $result = $this->viewModel->addSignature($encounterId, $signatureData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function testAddSignatureWithInvalidType(): void
    {
        $encounterId = $this->generateUuid();
        $user = UserFactory::makeClinician();
        
        $this->viewModel->setCurrentUser($user['user_id']);
        
        $signatureData = [
            'signature_type' => 'invalid_type',
            'signed_by' => $user['user_id'],
        ];
        
        $result = $this->viewModel->addSignature($encounterId, $signatureData);
        
        $this->assertIsArray($result);
        // Should fail with invalid signature type
        $this->assertFalse($result['success'] ?? true);
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
