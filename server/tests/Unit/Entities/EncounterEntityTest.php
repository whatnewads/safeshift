<?php
/**
 * EncounterEntityTest.php - Unit Tests for Encounter Entity
 * 
 * Tests the Encounter entity class including construction,
 * status transitions, locking, amendments, and data integrity.
 * 
 * @package    SafeShift\Tests\Unit\Entities
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Tests\Unit\Entities;

use PHPUnit\Framework\TestCase;
use Model\Entities\Encounter;
use DateTimeImmutable;

/**
 * Test cases for Encounter entity
 */
class EncounterEntityTest extends TestCase
{
    /**
     * Test encounter creation with required fields
     */
    public function testCreateEncounterWithRequiredFields(): void
    {
        $patientId = 'patient-uuid-123';
        $encounter = new Encounter($patientId);
        
        $this->assertEquals($patientId, $encounter->getPatientId());
        $this->assertEquals(Encounter::TYPE_OFFICE_VISIT, $encounter->getEncounterType());
        $this->assertEquals(Encounter::STATUS_SCHEDULED, $encounter->getStatus());
        $this->assertNull($encounter->getId());
        $this->assertNull($encounter->getProviderId());
        $this->assertFalse($encounter->isLocked());
        $this->assertFalse($encounter->isPersisted());
    }

    /**
     * Test encounter creation with all parameters
     */
    public function testCreateEncounterWithAllParameters(): void
    {
        $patientId = 'patient-uuid-123';
        $providerId = 'provider-uuid-456';
        $type = Encounter::TYPE_DOT_PHYSICAL;
        
        $encounter = new Encounter($patientId, $type, $providerId);
        
        $this->assertEquals($patientId, $encounter->getPatientId());
        $this->assertEquals($providerId, $encounter->getProviderId());
        $this->assertEquals($type, $encounter->getEncounterType());
    }

    /**
     * Test setting encounter ID
     */
    public function testSetEncounterId(): void
    {
        $encounter = new Encounter('patient-123');
        $encounterId = 'encounter-uuid-789';
        
        $encounter->setId($encounterId);
        
        $this->assertEquals($encounterId, $encounter->getId());
        $this->assertTrue($encounter->isPersisted());
    }

    /**
     * Test valid encounter types
     */
    public function testValidEncounterTypes(): void
    {
        $validTypes = [
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
        
        foreach ($validTypes as $type) {
            $encounter = new Encounter('patient-123', $type);
            $this->assertEquals($type, $encounter->getEncounterType());
        }
    }

    /**
     * Test invalid encounter type defaults to office visit
     */
    public function testInvalidEncounterTypeDefaultsToOfficeVisit(): void
    {
        $encounter = new Encounter('patient-123', 'invalid_type');
        
        $this->assertEquals(Encounter::TYPE_OFFICE_VISIT, $encounter->getEncounterType());
    }

    /**
     * Test valid status values
     */
    public function testValidStatusValues(): void
    {
        $validStatuses = [
            Encounter::STATUS_SCHEDULED,
            Encounter::STATUS_CHECKED_IN,
            Encounter::STATUS_IN_PROGRESS,
            Encounter::STATUS_PENDING_REVIEW,
            Encounter::STATUS_COMPLETE,
            Encounter::STATUS_CANCELLED,
            Encounter::STATUS_NO_SHOW,
        ];
        
        $encounter = new Encounter('patient-123');
        
        foreach ($validStatuses as $status) {
            $encounter->setStatus($status);
            $this->assertEquals($status, $encounter->getStatus());
        }
    }

    /**
     * Test invalid status defaults to scheduled
     */
    public function testInvalidStatusDefaultsToScheduled(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->setStatus('invalid_status');
        
        $this->assertEquals(Encounter::STATUS_SCHEDULED, $encounter->getStatus());
    }

    /**
     * Test setting chief complaint
     */
    public function testSetChiefComplaint(): void
    {
        $encounter = new Encounter('patient-123');
        $complaint = 'Headache and dizziness for 3 days';
        
        $encounter->setChiefComplaint($complaint);
        
        $this->assertEquals($complaint, $encounter->getChiefComplaint());
    }

    /**
     * Test setting HPI (History of Present Illness)
     */
    public function testSetHpi(): void
    {
        $encounter = new Encounter('patient-123');
        $hpi = 'Patient reports headache started 3 days ago...';
        
        $encounter->setHpi($hpi);
        
        $this->assertEquals($hpi, $encounter->getHpi());
    }

    /**
     * Test setting Review of Systems
     */
    public function testSetRos(): void
    {
        $encounter = new Encounter('patient-123');
        $ros = 'Constitutional: Denies fever, chills...';
        
        $encounter->setRos($ros);
        
        $this->assertEquals($ros, $encounter->getRos());
    }

    /**
     * Test setting physical exam
     */
    public function testSetPhysicalExam(): void
    {
        $encounter = new Encounter('patient-123');
        $exam = 'General: Alert and oriented...';
        
        $encounter->setPhysicalExam($exam);
        
        $this->assertEquals($exam, $encounter->getPhysicalExam());
    }

    /**
     * Test setting assessment
     */
    public function testSetAssessment(): void
    {
        $encounter = new Encounter('patient-123');
        $assessment = 'Tension headache, likely stress-related';
        
        $encounter->setAssessment($assessment);
        
        $this->assertEquals($assessment, $encounter->getAssessment());
    }

    /**
     * Test setting plan
     */
    public function testSetPlan(): void
    {
        $encounter = new Encounter('patient-123');
        $plan = '1. OTC ibuprofen\n2. Rest\n3. Follow-up in 1 week if not improved';
        
        $encounter->setPlan($plan);
        
        $this->assertEquals($plan, $encounter->getPlan());
    }

    /**
     * Test setting vitals array
     */
    public function testSetVitalsArray(): void
    {
        $encounter = new Encounter('patient-123');
        $vitals = [
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'heart_rate' => 72,
            'temperature' => 98.6,
            'respiratory_rate' => 16,
            'oxygen_saturation' => 98,
        ];
        
        $encounter->setVitals($vitals);
        
        $this->assertEquals($vitals, $encounter->getVitals());
    }

    /**
     * Test setting individual vital
     */
    public function testSetIndividualVital(): void
    {
        $encounter = new Encounter('patient-123');
        
        $encounter->setVital('blood_pressure_systolic', 130);
        $encounter->setVital('blood_pressure_diastolic', 85);
        
        $this->assertEquals(130, $encounter->getVital('blood_pressure_systolic'));
        $this->assertEquals(85, $encounter->getVital('blood_pressure_diastolic'));
        $this->assertNull($encounter->getVital('nonexistent'));
    }

    /**
     * Test setting clinical data
     */
    public function testSetClinicalData(): void
    {
        $encounter = new Encounter('patient-123');
        $clinicalData = [
            'allergies' => ['Penicillin', 'Sulfa'],
            'current_medications' => ['Lisinopril 10mg', 'Metformin 500mg'],
        ];
        
        $encounter->setClinicalData($clinicalData);
        
        $this->assertEquals($clinicalData, $encounter->getClinicalData());
    }

    /**
     * Test setting encounter date
     */
    public function testSetEncounterDate(): void
    {
        $encounter = new Encounter('patient-123');
        $date = new DateTimeImmutable('2025-01-15 10:30:00');
        
        $encounter->setEncounterDate($date);
        
        $this->assertEquals($date, $encounter->getEncounterDate());
    }

    /**
     * Test setting ICD codes
     */
    public function testSetIcdCodes(): void
    {
        $encounter = new Encounter('patient-123');
        $codes = ['G44.209', 'R51.9'];
        
        $encounter->setIcdCodes($codes);
        
        $this->assertEquals($codes, $encounter->getIcdCodes());
    }

    /**
     * Test setting CPT codes
     */
    public function testSetCptCodes(): void
    {
        $encounter = new Encounter('patient-123');
        $codes = ['99213', '99214'];
        
        $encounter->setCptCodes($codes);
        
        $this->assertEquals($codes, $encounter->getCptCodes());
    }

    /**
     * Test locking an encounter
     */
    public function testLockEncounter(): void
    {
        $encounter = new Encounter('patient-123');
        $userId = 'user-uuid-456';
        
        $encounter->lock($userId);
        
        $this->assertTrue($encounter->isLocked());
        $this->assertNotNull($encounter->getLockedAt());
        $this->assertEquals(Encounter::STATUS_COMPLETE, $encounter->getStatus());
    }

    /**
     * Test cannot lock already locked encounter
     */
    public function testCannotLockAlreadyLockedEncounter(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->lock('user-1');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encounter is already locked');
        
        $encounter->lock('user-2');
    }

    /**
     * Test cannot modify locked encounter
     */
    public function testCannotModifyLockedEncounter(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->lock('user-123');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot modify locked encounter');
        
        $encounter->setChiefComplaint('New complaint');
    }

    /**
     * Test encounter can be amended after locking
     */
    public function testCanAmendLockedEncounter(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->lock('user-123');
        
        $this->assertTrue($encounter->canAmend());
    }

    /**
     * Test cannot amend unlocked encounter
     */
    public function testCannotAmendUnlockedEncounter(): void
    {
        $encounter = new Encounter('patient-123');
        
        $this->assertFalse($encounter->canAmend());
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encounter must be locked before it can be amended');
        
        $encounter->startAmendment('Correction needed', 'user-456');
    }

    /**
     * Test starting amendment process
     */
    public function testStartAmendment(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->lock('user-123');
        
        $encounter->startAmendment('Correcting medication dosage', 'user-456');
        
        $this->assertTrue($encounter->isBeingAmended());
        $this->assertEquals('Correcting medication dosage', $encounter->getAmendmentReason());
    }

    /**
     * Test amendment requires reason
     */
    public function testAmendmentRequiresReason(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->lock('user-123');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Amendment reason is required');
        
        $encounter->startAmendment('', 'user-456');
    }

    /**
     * Test can modify encounter during amendment
     */
    public function testCanModifyDuringAmendment(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->setChiefComplaint('Original complaint');
        $encounter->lock('user-123');
        $encounter->startAmendment('Correction needed', 'user-456');
        
        // Should not throw exception
        $encounter->setChiefComplaint('Amended complaint');
        
        $this->assertEquals('Amended complaint', $encounter->getChiefComplaint());
    }

    /**
     * Test validation on new encounter
     */
    public function testValidationOnNewEncounter(): void
    {
        $encounter = new Encounter('patient-123');
        
        $errors = $encounter->validate();
        
        $this->assertEmpty($errors);
    }

    /**
     * Test validation requires chief complaint for complete status
     */
    public function testValidationRequiresChiefComplaintForComplete(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->setStatus(Encounter::STATUS_COMPLETE);
        
        $errors = $encounter->validate();
        
        $this->assertArrayHasKey('chief_complaint', $errors);
    }

    /**
     * Test toArray includes all fields
     */
    public function testToArrayIncludesAllFields(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_DOT_PHYSICAL, 'provider-456');
        $encounter->setId('encounter-789');
        $encounter->setChiefComplaint('DOT physical exam');
        $encounter->setVitals(['blood_pressure_systolic' => 120]);
        $encounter->setIcdCodes(['Z02.4']);
        
        $array = $encounter->toArray();
        
        $this->assertEquals('encounter-789', $array['encounter_id']);
        $this->assertEquals('patient-123', $array['patient_id']);
        $this->assertEquals('provider-456', $array['provider_id']);
        $this->assertEquals(Encounter::TYPE_DOT_PHYSICAL, $array['encounter_type']);
        $this->assertEquals('DOT physical exam', $array['chief_complaint']);
        $this->assertEquals(['blood_pressure_systolic' => 120], $array['vitals']);
        $this->assertEquals(['Z02.4'], $array['icd_codes']);
        $this->assertFalse($array['is_locked']);
    }

    /**
     * Test toSafeArray excludes sensitive data
     */
    public function testToSafeArrayExcludesSensitiveData(): void
    {
        $encounter = new Encounter('patient-123');
        $encounter->setId('encounter-789');
        $encounter->setChiefComplaint('Sensitive medical information');
        $encounter->setAssessment('Confidential assessment');
        
        $array = $encounter->toSafeArray();
        
        $this->assertArrayHasKey('encounter_id', $array);
        $this->assertArrayHasKey('patient_id', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayNotHasKey('chief_complaint', $array);
        $this->assertArrayNotHasKey('assessment', $array);
        $this->assertArrayNotHasKey('plan', $array);
    }

    /**
     * Test fromArray creates encounter correctly
     */
    public function testFromArrayCreatesEncounterCorrectly(): void
    {
        $data = [
            'encounter_id' => 'encounter-uuid-123',
            'patient_id' => 'patient-uuid-456',
            'provider_id' => 'provider-uuid-789',
            'clinic_id' => 'clinic-uuid-001',
            'encounter_type' => Encounter::TYPE_OSHA_INJURY,
            'status' => Encounter::STATUS_IN_PROGRESS,
            'chief_complaint' => 'Work injury - hand laceration',
            'hpi' => 'Patient cut hand on machinery...',
            'vitals' => ['blood_pressure_systolic' => 125, 'blood_pressure_diastolic' => 82],
            'icd_codes' => ['S61.401A'],
            'cpt_codes' => ['99213'],
            'encounter_date' => '2025-01-15 14:30:00',
            'created_at' => '2025-01-15 14:00:00',
        ];
        
        $encounter = Encounter::fromArray($data);
        
        $this->assertEquals('encounter-uuid-123', $encounter->getId());
        $this->assertEquals('patient-uuid-456', $encounter->getPatientId());
        $this->assertEquals('provider-uuid-789', $encounter->getProviderId());
        $this->assertEquals('clinic-uuid-001', $encounter->getClinicId());
        $this->assertEquals(Encounter::TYPE_OSHA_INJURY, $encounter->getEncounterType());
        $this->assertEquals(Encounter::STATUS_IN_PROGRESS, $encounter->getStatus());
        $this->assertEquals('Work injury - hand laceration', $encounter->getChiefComplaint());
        $this->assertEquals(['blood_pressure_systolic' => 125, 'blood_pressure_diastolic' => 82], $encounter->getVitals());
        $this->assertEquals(['S61.401A'], $encounter->getIcdCodes());
    }

    /**
     * Test fromArray handles locked encounters
     */
    public function testFromArrayHandlesLockedEncounters(): void
    {
        $data = [
            'patient_id' => 'patient-123',
            'locked_at' => '2025-01-15 16:00:00',
            'locked_by' => 'user-789',
        ];
        
        $encounter = Encounter::fromArray($data);
        
        $this->assertTrue($encounter->isLocked());
    }

    /**
     * Test fromArray handles amendments
     */
    public function testFromArrayHandlesAmendments(): void
    {
        $data = [
            'patient_id' => 'patient-123',
            'locked_at' => '2025-01-15 16:00:00',
            'locked_by' => 'user-789',
            'is_amended' => true,
            'amendment_reason' => 'Corrected diagnosis',
            'amended_at' => '2025-01-15 17:00:00',
            'amended_by' => 'user-456',
        ];
        
        $encounter = Encounter::fromArray($data);
        
        $this->assertTrue($encounter->isBeingAmended());
        $this->assertEquals('Corrected diagnosis', $encounter->getAmendmentReason());
    }

    /**
     * Test encounter timestamps are updated on modification
     */
    public function testTimestampsUpdatedOnModification(): void
    {
        $encounter = new Encounter('patient-123');
        $originalUpdatedAt = $encounter->getUpdatedAt();
        
        usleep(1000); // Small delay to ensure timestamp difference
        
        $encounter->setChiefComplaint('Updated complaint');
        
        $this->assertGreaterThanOrEqual($originalUpdatedAt, $encounter->getUpdatedAt());
    }

    /**
     * Test setting provider ID
     */
    public function testSetProviderId(): void
    {
        $encounter = new Encounter('patient-123');
        
        $encounter->setProviderId('new-provider-456');
        
        $this->assertEquals('new-provider-456', $encounter->getProviderId());
    }

    /**
     * Test setting clinic ID
     */
    public function testSetClinicId(): void
    {
        $encounter = new Encounter('patient-123');
        
        $encounter->setClinicId('clinic-uuid-789');
        
        $this->assertEquals('clinic-uuid-789', $encounter->getClinicId());
    }

    /**
     * Test setting encounter type after creation
     */
    public function testSetEncounterTypeAfterCreation(): void
    {
        $encounter = new Encounter('patient-123', Encounter::TYPE_OFFICE_VISIT);
        
        $encounter->setEncounterType(Encounter::TYPE_TELEHEALTH);
        
        $this->assertEquals(Encounter::TYPE_TELEHEALTH, $encounter->getEncounterType());
    }

    /**
     * Test empty ICD codes returns empty array
     */
    public function testEmptyIcdCodesReturnsEmptyArray(): void
    {
        $encounter = new Encounter('patient-123');
        
        $this->assertEquals([], $encounter->getIcdCodes());
    }

    /**
     * Test empty CPT codes returns empty array
     */
    public function testEmptyCptCodesReturnsEmptyArray(): void
    {
        $encounter = new Encounter('patient-123');
        
        $this->assertEquals([], $encounter->getCptCodes());
    }
}
