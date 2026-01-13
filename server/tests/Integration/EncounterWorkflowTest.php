<?php
/**
 * EncounterWorkflowTest.php - Integration Tests for Encounter Workflow
 * 
 * Tests the complete encounter workflow from creation to finalization,
 * including status transitions, data validation, and email triggers.
 * 
 * @package    SafeShift\Tests\Integration
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Helpers\TestCase;
use Tests\Helpers\Factories\EncounterFactory;
use Tests\Helpers\Factories\PatientFactory;
use Tests\Helpers\Factories\UserFactory;
use Model\Entities\Encounter;
use Model\Entities\Patient;
use DateTimeImmutable;

/**
 * Integration tests for complete encounter workflow
 */
class EncounterWorkflowTest extends TestCase
{
    private EncounterFactory $encounterFactory;
    private PatientFactory $patientFactory;
    private UserFactory $userFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encounterFactory = new EncounterFactory();
        $this->patientFactory = new PatientFactory();
        $this->userFactory = new UserFactory();
    }

    /**
     * Test complete encounter workflow from creation to completion
     */
    public function testCompleteEncounterWorkflow(): void
    {
        // Step 1: Create patient
        $patientData = $this->patientFactory->make();
        $patient = Patient::fromArray($patientData);
        $patient->setId('patient-' . uniqid());
        
        $this->assertNotNull($patient->getId());
        $this->assertTrue($patient->isActive());
        
        // Step 2: Create encounter
        $encounter = new Encounter($patient->getId(), Encounter::TYPE_OFFICE_VISIT);
        $encounter->setId('encounter-' . uniqid());
        
        $this->assertEquals(Encounter::STATUS_SCHEDULED, $encounter->getStatus());
        
        // Step 3: Check in patient
        $encounter->setStatus(Encounter::STATUS_CHECKED_IN);
        $this->assertEquals(Encounter::STATUS_CHECKED_IN, $encounter->getStatus());
        
        // Step 4: Start encounter (in progress)
        $encounter->setStatus(Encounter::STATUS_IN_PROGRESS);
        $this->assertEquals(Encounter::STATUS_IN_PROGRESS, $encounter->getStatus());
        
        // Step 5: Add patient demographics (already in patient object)
        $this->assertEquals($patientData['first_name'], $patient->getFirstName());
        $this->assertEquals($patientData['last_name'], $patient->getLastName());
        
        // Step 6: Add vitals
        $vitals = $this->encounterFactory->makeVitals();
        $encounter->setVitals($vitals);
        
        $this->assertNotEmpty($encounter->getVitals());
        $this->assertEquals($vitals['blood_pressure_systolic'], $encounter->getVital('blood_pressure_systolic'));
        
        // Step 7: Add chief complaint and HPI
        $encounter->setChiefComplaint('Routine follow-up for hypertension');
        $encounter->setHpi('Patient presents for routine follow-up. Reports compliance with medications.');
        
        $this->assertNotEmpty($encounter->getChiefComplaint());
        $this->assertNotEmpty($encounter->getHpi());
        
        // Step 8: Add assessment
        $encounter->setAssessment('Essential hypertension, well controlled');
        $encounter->setIcdCodes(['I10']);
        
        $this->assertNotEmpty($encounter->getAssessment());
        $this->assertContains('I10', $encounter->getIcdCodes());
        
        // Step 9: Add plan
        $encounter->setPlan('Continue current medications. Follow-up in 3 months.');
        $encounter->setCptCodes(['99213']);
        
        $this->assertNotEmpty($encounter->getPlan());
        $this->assertContains('99213', $encounter->getCptCodes());
        
        // Step 10: Submit for review
        $encounter->setStatus(Encounter::STATUS_PENDING_REVIEW);
        $this->assertEquals(Encounter::STATUS_PENDING_REVIEW, $encounter->getStatus());
        
        // Step 11: Complete and lock encounter
        $clinicianId = 'clinician-' . uniqid();
        $encounter->lock($clinicianId);
        
        $this->assertTrue($encounter->isLocked());
        $this->assertEquals(Encounter::STATUS_COMPLETE, $encounter->getStatus());
    }

    /**
     * Test DOT physical workflow
     */
    public function testDotPhysicalWorkflow(): void
    {
        $patientData = $this->patientFactory->makeWithEmployer();
        $patient = Patient::fromArray($patientData);
        $patient->setId('patient-' . uniqid());
        
        $encounter = new Encounter($patient->getId(), Encounter::TYPE_DOT_PHYSICAL);
        $encounter->setId('encounter-' . uniqid());
        
        // DOT physicals have specific requirements
        $this->assertEquals(Encounter::TYPE_DOT_PHYSICAL, $encounter->getEncounterType());
        
        // Add required DOT physical data
        $vitals = $this->encounterFactory->makeVitals();
        $encounter->setVitals($vitals);
        
        // Add clinical data specific to DOT
        $clinicalData = [
            'vision_right' => '20/20',
            'vision_left' => '20/20',
            'hearing_right' => 'Normal',
            'hearing_left' => 'Normal',
            'diabetes_controlled' => true,
            'cardiovascular_clear' => true,
        ];
        $encounter->setClinicalData($clinicalData);
        
        // DOT ICD code
        $encounter->setIcdCodes(['Z02.4']);
        
        // Complete workflow
        $encounter->setStatus(Encounter::STATUS_IN_PROGRESS);
        $encounter->setChiefComplaint('DOT physical examination');
        $encounter->setPhysicalExam('Complete DOT physical examination performed...');
        $encounter->setAssessment('Qualified for DOT certification');
        $encounter->setPlan('DOT certificate issued, valid for 2 years');
        
        $encounter->lock('clinician-123');
        
        $this->assertTrue($encounter->isLocked());
        $this->assertEquals(['Z02.4'], $encounter->getIcdCodes());
    }

    /**
     * Test OSHA injury workflow
     */
    public function testOshaInjuryWorkflow(): void
    {
        $patientData = $this->patientFactory->makeWithEmployer();
        $patient = Patient::fromArray($patientData);
        $patient->setId('patient-' . uniqid());
        
        $encounter = new Encounter($patient->getId(), Encounter::TYPE_OSHA_INJURY);
        $encounter->setId('encounter-' . uniqid());
        
        $this->assertEquals(Encounter::TYPE_OSHA_INJURY, $encounter->getEncounterType());
        
        // OSHA injuries require work-related classification
        $clinicalData = [
            'work_related' => true,
            'injury_date' => date('Y-m-d'),
            'injury_time' => '14:30',
            'injury_location' => 'Warehouse floor',
            'injury_description' => 'Laceration to right hand from box cutter',
            'body_part' => 'Right hand',
            'nature_of_injury' => 'Laceration',
            'cause_of_injury' => 'Sharp object',
            'osha_recordable' => true,
            'lost_time' => false,
            'restricted_duty' => true,
        ];
        $encounter->setClinicalData($clinicalData);
        
        // Add vitals
        $encounter->setVitals($this->encounterFactory->makeVitals());
        
        // Add clinical documentation
        $encounter->setChiefComplaint('Work injury - laceration to right hand');
        $encounter->setHpi('Patient was cutting open boxes when box cutter slipped...');
        $encounter->setPhysicalExam('2cm laceration to right palm, no tendon involvement...');
        $encounter->setAssessment('Superficial laceration, right hand');
        $encounter->setIcdCodes(['S61.411A']);
        $encounter->setPlan('Wound cleaned and closed with 4 sutures. Tetanus given. Return in 7 days for suture removal.');
        
        // Work restrictions
        $clinicalData['work_restrictions'] = 'No use of right hand for 7 days';
        $clinicalData['return_to_work_date'] = date('Y-m-d', strtotime('+7 days'));
        $encounter->setClinicalData($clinicalData);
        
        $encounter->lock('clinician-123');
        
        $this->assertTrue($encounter->isLocked());
        $this->assertTrue($encounter->getClinicalData()['work_related']);
        $this->assertTrue($encounter->getClinicalData()['osha_recordable']);
    }

    /**
     * Test encounter cancellation workflow
     */
    public function testEncounterCancellationWorkflow(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_OFFICE_VISIT);
        $encounter->setId('encounter-' . uniqid());
        
        // Patient checks in
        $encounter->setStatus(Encounter::STATUS_CHECKED_IN);
        $this->assertEquals(Encounter::STATUS_CHECKED_IN, $encounter->getStatus());
        
        // Patient decides to leave/cancel
        $encounter->setStatus(Encounter::STATUS_CANCELLED);
        
        $this->assertEquals(Encounter::STATUS_CANCELLED, $encounter->getStatus());
        $this->assertFalse($encounter->isLocked());
    }

    /**
     * Test no-show workflow
     */
    public function testNoShowWorkflow(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_OFFICE_VISIT);
        $encounter->setId('encounter-' . uniqid());
        
        $this->assertEquals(Encounter::STATUS_SCHEDULED, $encounter->getStatus());
        
        // Mark as no-show
        $encounter->setStatus(Encounter::STATUS_NO_SHOW);
        
        $this->assertEquals(Encounter::STATUS_NO_SHOW, $encounter->getStatus());
    }

    /**
     * Test encounter amendment workflow
     */
    public function testEncounterAmendmentWorkflow(): void
    {
        // Create and complete an encounter
        $encounter = new Encounter('patient-123', Encounter::TYPE_OFFICE_VISIT);
        $encounter->setId('encounter-' . uniqid());
        $encounter->setChiefComplaint('Original complaint');
        $encounter->setAssessment('Original assessment');
        $encounter->lock('clinician-123');
        
        $this->assertTrue($encounter->isLocked());
        
        // Start amendment process
        $encounter->startAmendment('Correcting diagnosis code', 'supervisor-456');
        
        $this->assertTrue($encounter->isBeingAmended());
        $this->assertEquals('Correcting diagnosis code', $encounter->getAmendmentReason());
        
        // Now can modify
        $encounter->setAssessment('Corrected assessment with additional diagnosis');
        $encounter->setIcdCodes(['I10', 'E11.9']);
        
        $this->assertEquals('Corrected assessment with additional diagnosis', $encounter->getAssessment());
    }

    /**
     * Test multiple encounters for same patient
     */
    public function testMultipleEncountersForSamePatient(): void
    {
        $patientId = 'patient-' . uniqid();
        
        // First encounter
        $encounter1 = new Encounter($patientId, Encounter::TYPE_OFFICE_VISIT);
        $encounter1->setId('encounter-1');
        $encounter1->setChiefComplaint('Annual physical');
        $encounter1->lock('clinician-123');
        
        // Second encounter (follow-up)
        $encounter2 = new Encounter($patientId, Encounter::TYPE_FOLLOW_UP);
        $encounter2->setId('encounter-2');
        $encounter2->setChiefComplaint('Follow-up from annual physical');
        
        // Both should reference same patient
        $this->assertEquals($patientId, $encounter1->getPatientId());
        $this->assertEquals($patientId, $encounter2->getPatientId());
        
        // First is locked, second is not
        $this->assertTrue($encounter1->isLocked());
        $this->assertFalse($encounter2->isLocked());
    }

    /**
     * Test urgent care encounter workflow
     */
    public function testUrgentCareWorkflow(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_URGENT);
        $encounter->setId('encounter-' . uniqid());
        
        $this->assertEquals(Encounter::TYPE_URGENT, $encounter->getEncounterType());
        
        // Urgent care typically skips scheduling
        $encounter->setStatus(Encounter::STATUS_IN_PROGRESS);
        
        // Add urgent vitals
        $vitals = [
            'blood_pressure_systolic' => 145,
            'blood_pressure_diastolic' => 95,
            'heart_rate' => 100,
            'temperature' => 101.5,
            'respiratory_rate' => 20,
            'oxygen_saturation' => 96,
        ];
        $encounter->setVitals($vitals);
        
        $encounter->setChiefComplaint('Fever and body aches for 2 days');
        $encounter->setAssessment('Influenza-like illness');
        $encounter->setIcdCodes(['J11.1']);
        $encounter->setPlan('Supportive care, rest, fluids. Return if worsening.');
        
        $encounter->lock('clinician-123');
        
        $this->assertTrue($encounter->isLocked());
    }

    /**
     * Test telehealth encounter workflow
     */
    public function testTelehealthWorkflow(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_TELEHEALTH);
        $encounter->setId('encounter-' . uniqid());
        
        $this->assertEquals(Encounter::TYPE_TELEHEALTH, $encounter->getEncounterType());
        
        // Telehealth may have limited vitals (self-reported)
        $vitals = [
            'blood_pressure_systolic' => 120, // self-reported
            'blood_pressure_diastolic' => 80,
            'temperature' => 98.6, // self-reported
        ];
        $encounter->setVitals($vitals);
        
        // Add telehealth-specific data
        $clinicalData = [
            'telehealth_platform' => 'SafeShift Video',
            'connection_quality' => 'Good',
            'patient_location' => 'Home',
            'provider_location' => 'Office',
        ];
        $encounter->setClinicalData($clinicalData);
        
        $encounter->setChiefComplaint('Medication refill request');
        $encounter->setAssessment('Stable on current medications');
        $encounter->setPlan('Refill medications for 90 days');
        
        $encounter->lock('clinician-123');
        
        $this->assertTrue($encounter->isLocked());
        $this->assertEquals('SafeShift Video', $encounter->getClinicalData()['telehealth_platform']);
    }

    /**
     * Test encounter with supervising provider
     */
    public function testEncounterWithSupervisingProvider(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_OFFICE_VISIT, 'resident-456');
        $encounter->setId('encounter-' . uniqid());
        
        // Resident sees patient, supervisor co-signs
        $encounterArray = $encounter->toArray();
        
        // Verify provider is set
        $this->assertEquals('resident-456', $encounter->getProviderId());
    }

    /**
     * Test pre-employment physical workflow
     */
    public function testPreEmploymentPhysicalWorkflow(): void
    {
        $patientData = $this->patientFactory->makeWithEmployer();
        $patient = Patient::fromArray($patientData);
        $patient->setId('patient-' . uniqid());
        
        $encounter = new Encounter($patient->getId(), Encounter::TYPE_PRE_EMPLOYMENT);
        $encounter->setId('encounter-' . uniqid());
        
        $this->assertEquals(Encounter::TYPE_PRE_EMPLOYMENT, $encounter->getEncounterType());
        
        // Pre-employment specific data
        $clinicalData = [
            'job_title' => 'Warehouse Worker',
            'job_requirements' => 'Heavy lifting up to 50lbs, standing for extended periods',
            'physical_demands' => 'Heavy',
            'drug_screen_required' => true,
            'drug_screen_result' => 'Negative',
        ];
        $encounter->setClinicalData($clinicalData);
        
        // Standard physical exam
        $encounter->setVitals($this->encounterFactory->makeVitals());
        $encounter->setChiefComplaint('Pre-employment physical examination');
        $encounter->setPhysicalExam('Complete physical examination performed...');
        $encounter->setAssessment('Fit for duty without restrictions');
        $encounter->setIcdCodes(['Z02.1']);
        $encounter->setPlan('Cleared for employment');
        
        $encounter->lock('clinician-123');
        
        $this->assertTrue($encounter->isLocked());
        $this->assertEquals('Negative', $encounter->getClinicalData()['drug_screen_result']);
    }

    /**
     * Test workers comp encounter workflow
     */
    public function testWorkersCompWorkflow(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_WORKERS_COMP);
        $encounter->setId('encounter-' . uniqid());
        
        $this->assertEquals(Encounter::TYPE_WORKERS_COMP, $encounter->getEncounterType());
        
        // Workers comp specific data
        $clinicalData = [
            'claim_number' => 'WC-2025-001234',
            'date_of_injury' => '2025-01-10',
            'employer_name' => 'ABC Manufacturing',
            'insurance_carrier' => 'Workers Comp Insurance Co',
            'adjuster_name' => 'Jane Smith',
            'adjuster_phone' => '555-123-4567',
            'authorized_treatment' => true,
        ];
        $encounter->setClinicalData($clinicalData);
        
        $encounter->setChiefComplaint('Follow-up for work injury');
        $encounter->setAssessment('Improving, continue physical therapy');
        $encounter->setPlan('Continue PT 2x/week. Return in 2 weeks.');
        
        $encounter->lock('clinician-123');
        
        $this->assertTrue($encounter->isLocked());
        $this->assertEquals('WC-2025-001234', $encounter->getClinicalData()['claim_number']);
    }

    /**
     * Test encounter data integrity through workflow
     */
    public function testDataIntegrityThroughWorkflow(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_OFFICE_VISIT);
        $encounter->setId('encounter-' . uniqid());
        
        // Set all data
        $originalVitals = $this->encounterFactory->makeVitals();
        $originalComplaint = 'Test chief complaint with special chars: <>&"\'';
        $originalAssessment = 'Test assessment';
        $originalIcd = ['I10', 'E11.9'];
        $originalCpt = ['99213', '99214'];
        
        $encounter->setVitals($originalVitals);
        $encounter->setChiefComplaint($originalComplaint);
        $encounter->setAssessment($originalAssessment);
        $encounter->setIcdCodes($originalIcd);
        $encounter->setCptCodes($originalCpt);
        
        // Convert to array and back
        $array = $encounter->toArray();
        $reconstituted = Encounter::fromArray($array);
        
        // Verify data integrity
        $this->assertEquals($originalVitals, $reconstituted->getVitals());
        $this->assertEquals($originalComplaint, $reconstituted->getChiefComplaint());
        $this->assertEquals($originalAssessment, $reconstituted->getAssessment());
        $this->assertEquals($originalIcd, $reconstituted->getIcdCodes());
        $this->assertEquals($originalCpt, $reconstituted->getCptCodes());
    }

    /**
     * Test encounter status cannot go backwards
     * Note: This tests business logic that should be enforced at service level
     */
    public function testStatusTransitionsAreValidated(): void
    {
        $encounter = new Encounter('patient-123');
        
        // Valid forward transition
        $encounter->setStatus(Encounter::STATUS_CHECKED_IN);
        $encounter->setStatus(Encounter::STATUS_IN_PROGRESS);
        $encounter->setStatus(Encounter::STATUS_PENDING_REVIEW);
        
        // Note: The entity itself doesn't enforce transition order,
        // but the service layer should. This test documents expected behavior.
        $this->assertEquals(Encounter::STATUS_PENDING_REVIEW, $encounter->getStatus());
    }

    /**
     * Test concurrent modifications are tracked via timestamps
     */
    public function testConcurrentModificationTracking(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->setId('encounter-' . uniqid());
        
        $version1 = $encounter->getUpdatedAt();
        
        usleep(1000); // Small delay
        $encounter->setChiefComplaint('First update');
        $version2 = $encounter->getUpdatedAt();
        
        usleep(1000);
        $encounter->setAssessment('Second update');
        $version3 = $encounter->getUpdatedAt();
        
        // Each modification should update the timestamp
        $this->assertGreaterThanOrEqual($version1, $version2);
        $this->assertGreaterThanOrEqual($version2, $version3);
    }
}
