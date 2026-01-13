<?php
/**
 * EncounterFactory - Test Data Factory for Encounters
 * 
 * Creates mock encounter data for testing purposes.
 * 
 * @package SafeShift\Tests\Helpers\Factories
 */

declare(strict_types=1);

namespace Tests\Helpers\Factories;

/**
 * Factory for generating test encounter data
 */
class EncounterFactory
{
    /** @var array<string> Valid encounter types */
    private const ENCOUNTER_TYPES = [
        'office_visit',
        'dot_physical',
        'drug_screen',
        'osha_injury',
        'workers_comp',
        'pre_employment',
        'urgent',
        'telehealth',
        'follow_up',
    ];

    /** @var array<string> Valid encounter statuses */
    private const STATUSES = [
        'scheduled',
        'checked_in',
        'in_progress',
        'pending_review',
        'complete',
        'cancelled',
        'no_show',
    ];

    /** @var array<string> Chief complaint examples */
    private const CHIEF_COMPLAINTS = [
        'Annual physical examination',
        'DOT physical certification renewal',
        'Pre-employment screening',
        'Workplace injury - right hand laceration',
        'Drug screen for employment',
        'Follow-up for previous injury',
        'Urgent care - back pain',
        'Workers compensation evaluation',
    ];

    /**
     * Create a basic encounter data array
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function make(array $overrides = []): array
    {
        $defaults = [
            'encounter_id' => self::generateUuid(),
            'patient_id' => self::generateUuid(),
            'provider_id' => self::generateUuid(),
            'clinic_id' => self::generateUuid(),
            'encounter_type' => self::ENCOUNTER_TYPES[array_rand(self::ENCOUNTER_TYPES)],
            'status' => 'in_progress',
            'encounter_date' => date('Y-m-d H:i:s'),
            'chief_complaint' => self::CHIEF_COMPLAINTS[array_rand(self::CHIEF_COMPLAINTS)],
            'hpi' => 'Patient presents with the stated chief complaint. No previous similar episodes.',
            'ros' => 'General: No fever, chills, or weight changes.',
            'physical_exam' => 'General: Alert and oriented. Vitals stable.',
            'assessment' => 'Patient evaluation complete.',
            'plan' => 'Continue current management. Follow up as needed.',
            'vitals' => self::makeVitals(),
            'icd_codes' => [],
            'cpt_codes' => [],
            'clinical_data' => [],
            'is_locked' => false,
            'is_amended' => false,
            'locked_at' => null,
            'locked_by' => null,
            'amendment_reason' => null,
            'amended_at' => null,
            'amended_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by' => null,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create encounter for specific type
     * 
     * @param string $type
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeOfType(string $type, array $overrides = []): array
    {
        return self::make(array_merge(['encounter_type' => $type], $overrides));
    }

    /**
     * Create DOT physical encounter
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeDotPhysical(array $overrides = []): array
    {
        return self::make(array_merge([
            'encounter_type' => 'dot_physical',
            'chief_complaint' => 'DOT physical examination for commercial driver license',
            'hpi' => 'Commercial driver presents for DOT physical renewal. No current medical conditions.',
            'physical_exam' => 'HEENT: Pupils equal and reactive. TMs clear. Vision 20/20 both eyes.',
            'assessment' => 'Driver meets DOT physical requirements.',
            'cpt_codes' => ['99455'],
        ], $overrides));
    }

    /**
     * Create drug screen encounter
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeDrugScreen(array $overrides = []): array
    {
        return self::make(array_merge([
            'encounter_type' => 'drug_screen',
            'chief_complaint' => 'Pre-employment drug screening',
            'hpi' => 'Patient presents for pre-employment drug screening.',
            'physical_exam' => null,
            'assessment' => 'Drug screen specimen collected.',
            'clinical_data' => [
                'specimen_type' => 'urine',
                'collection_time' => date('H:i:s'),
                'chain_of_custody' => true,
            ],
            'cpt_codes' => ['80305'],
        ], $overrides));
    }

    /**
     * Create OSHA injury encounter
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeOshaInjury(array $overrides = []): array
    {
        return self::make(array_merge([
            'encounter_type' => 'osha_injury',
            'chief_complaint' => 'Workplace injury - reported to supervisor',
            'hpi' => 'Patient reports injury occurred during work activities. Mechanism of injury documented.',
            'physical_exam' => 'Examination of affected area. Range of motion assessed.',
            'assessment' => 'Work-related injury requiring medical treatment.',
            'icd_codes' => ['S61.401A'],
            'cpt_codes' => ['99213'],
            'clinical_data' => [
                'work_related' => true,
                'injury_date' => date('Y-m-d'),
                'employer_notified' => true,
                'osha_recordable' => true,
            ],
        ], $overrides));
    }

    /**
     * Create workers comp encounter
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeWorkersComp(array $overrides = []): array
    {
        return self::make(array_merge([
            'encounter_type' => 'workers_comp',
            'chief_complaint' => 'Workers compensation follow-up',
            'hpi' => 'Follow-up for work-related injury. Patient reports improvement.',
            'clinical_data' => [
                'work_related' => true,
                'claim_number' => 'WC-' . date('Y') . '-' . random_int(10000, 99999),
                'work_status' => 'modified_duty',
            ],
        ], $overrides));
    }

    /**
     * Create encounter with specific status
     * 
     * @param string $status
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeWithStatus(string $status, array $overrides = []): array
    {
        $data = ['status' => $status];
        
        if ($status === 'complete') {
            $data['is_locked'] = true;
            $data['locked_at'] = date('Y-m-d H:i:s');
            $data['locked_by'] = self::generateUuid();
        }
        
        return self::make(array_merge($data, $overrides));
    }

    /**
     * Create locked (signed) encounter
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeLocked(array $overrides = []): array
    {
        return self::make(array_merge([
            'status' => 'complete',
            'is_locked' => true,
            'locked_at' => date('Y-m-d H:i:s'),
            'locked_by' => self::generateUuid(),
        ], $overrides));
    }

    /**
     * Create amended encounter
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeAmended(array $overrides = []): array
    {
        return self::makeLocked(array_merge([
            'is_amended' => true,
            'amendment_reason' => 'Correction to clinical documentation per clinical review.',
            'amended_at' => date('Y-m-d H:i:s'),
            'amended_by' => self::generateUuid(),
        ], $overrides));
    }

    /**
     * Create incomplete encounter (missing required fields)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeIncomplete(array $overrides = []): array
    {
        return self::make(array_merge([
            'status' => 'in_progress',
            'chief_complaint' => null,
            'vitals' => [],
            'assessment' => null,
            'plan' => null,
        ], $overrides));
    }

    /**
     * Create vitals data
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeVitals(array $overrides = []): array
    {
        $defaults = [
            'systolic_bp' => random_int(110, 140),
            'diastolic_bp' => random_int(60, 90),
            'pulse' => random_int(60, 100),
            'temperature' => round(98.6 + (random_int(-10, 10) / 10), 1),
            'respiratory_rate' => random_int(12, 20),
            'oxygen_saturation' => random_int(95, 100),
            'weight' => random_int(120, 220),
            'height' => random_int(60, 76),
            'recorded_at' => date('Y-m-d H:i:s'),
            'recorded_by' => self::generateUuid(),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create abnormal vitals (for edge case testing)
     * 
     * @return array<string, mixed>
     */
    public static function makeAbnormalVitals(): array
    {
        return self::makeVitals([
            'systolic_bp' => 180,
            'diastolic_bp' => 110,
            'pulse' => 120,
            'temperature' => 102.5,
            'respiratory_rate' => 28,
            'oxygen_saturation' => 88,
        ]);
    }

    /**
     * Create assessment data
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeAssessment(array $overrides = []): array
    {
        $defaults = [
            'assessment' => 'Clinical evaluation complete. Findings documented.',
            'icd_codes' => ['Z00.00'],
            'diagnosis' => 'General medical examination without abnormal findings',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create treatment plan data
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function makeTreatment(array $overrides = []): array
    {
        $defaults = [
            'plan' => 'Continue current medications. Follow up in 30 days.',
            'cpt_codes' => ['99213'],
            'medications' => [],
            'procedures' => [],
            'follow_up' => date('Y-m-d', strtotime('+30 days')),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Create multiple encounters
     * 
     * @param int $count
     * @param array<string, mixed> $overrides
     * @return array<int, array<string, mixed>>
     */
    public static function makeMany(int $count, array $overrides = []): array
    {
        $encounters = [];
        for ($i = 0; $i < $count; $i++) {
            $encounters[] = self::make($overrides);
        }
        return $encounters;
    }

    /**
     * Create encounters with different statuses
     * 
     * @return array<string, array<string, mixed>>
     */
    public static function makeAllStatuses(): array
    {
        $encounters = [];
        foreach (self::STATUSES as $status) {
            $encounters[$status] = self::makeWithStatus($status);
        }
        return $encounters;
    }

    /**
     * Create encounters with different types
     * 
     * @return array<string, array<string, mixed>>
     */
    public static function makeAllTypes(): array
    {
        $encounters = [];
        foreach (self::ENCOUNTER_TYPES as $type) {
            $encounters[$type] = self::makeOfType($type);
        }
        return $encounters;
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
