<?php
/**
 * Patient Vitals Service
 * 
 * Handles business logic for patient vital signs
 * Includes vital status determination and trend analysis
 */

namespace Core\Services;

use Core\Repositories\ObservationRepository;
use Core\Repositories\PatientRepository;
use Core\Validators\VitalRangeValidator;
use Core\Entities\Patient;
use Core\Entities\Vital;
use Exception;

class PatientVitalsService
{
    private ObservationRepository $observationRepo;
    private PatientRepository $patientRepo;
    private VitalRangeValidator $validator;
    
    /**
     * Constructor
     */
    public function __construct(
        ObservationRepository $observationRepo,
        PatientRepository $patientRepo,
        VitalRangeValidator $validator
    ) {
        $this->observationRepo = $observationRepo;
        $this->patientRepo = $patientRepo;
        $this->validator = $validator;
    }
    
    /**
     * Get patient vital trends with status determination
     */
    public function getPatientVitalTrends(string $patientId, int $days = 30): array
    {
        // Verify patient exists
        $patient = $this->patientRepo->findById($patientId);
        if (!$patient) {
            throw new Exception("Patient not found: $patientId");
        }
        
        // Get vitals from repository
        $vitals = $this->observationRepo->getVitalsByPatientId($patientId, $days);
        
        // Group vitals by type for processing
        $vitalsByType = $this->groupVitalsByType($vitals);
        
        // Process each vital type
        $processedVitals = [];
        $trends = [];
        
        foreach ($vitalsByType as $code => $vitalList) {
            if (empty($vitalList)) continue;
            
            $latest = $vitalList[0]; // Most recent reading
            $value = floatval($latest['value']);
            
            // Determine status and color using validator
            $status = $this->validator->getVitalStatus($code, $value);
            $color = $this->validator->getStatusColor($code, $value);
            $referenceRange = $this->validator->getReferenceRange($code);
            
            $processedVitals[] = [
                'type' => $code,
                'name' => $this->getVitalDisplayName($code),
                'value' => $value,
                'units' => $latest['units'],
                'status' => $status,
                'color' => $color,
                'reference_range' => $referenceRange,
                'observed_at' => $latest['observed_at'],
                'encounter_id' => $latest['encounter_id']
            ];
            
            // Prepare trend data (last 10 readings)
            $trendData = [];
            foreach (array_slice($vitalList, 0, 10) as $v) {
                $trendData[] = [
                    'value' => floatval($v['value']),
                    'date' => date('Y-m-d H:i', strtotime($v['observed_at'])),
                    'encounter_type' => $v['encounter_type'] ?? 'routine'
                ];
            }
            
            $trends[$code] = array_reverse($trendData);
        }
        
        // Add combined blood pressure if both systolic and diastolic exist
        $bloodPressure = $this->processCombinedBloodPressure($processedVitals);
        
        return [
            'patient' => [
                'patient_id' => $patient->getPatientId(),
                'name' => $patient->getDisplayName(),
                'mrn' => $patient->getMrn()
            ],
            'vitals' => $processedVitals,
            'trends' => $trends,
            'blood_pressure_combined' => $bloodPressure,
            'last_updated' => date('c')
        ];
    }
    
    /**
     * Get latest vitals for patient
     */
    public function getLatestVitals(string $patientId): array
    {
        $patient = $this->patientRepo->findById($patientId);
        if (!$patient) {
            throw new Exception("Patient not found: $patientId");
        }
        
        $latestVitals = $this->observationRepo->getLatestVitals($patientId);
        
        $result = [];
        foreach ($latestVitals as $code => $vital) {
            $value = floatval($vital->getValue());
            $result[$code] = [
                'value' => $value,
                'units' => $vital->getUnits(),
                'status' => $this->validator->getVitalStatus($code, $value),
                'color' => $this->validator->getStatusColor($code, $value),
                'observed_at' => $vital->getObservedAt(),
                'formatted_value' => $vital->getFormattedValue()
            ];
        }
        
        return $result;
    }
    
    /**
     * Get abnormal vitals for patient
     */
    public function getAbnormalVitals(string $patientId, int $days = 7): array
    {
        $patient = $this->patientRepo->findById($patientId);
        if (!$patient) {
            throw new Exception("Patient not found: $patientId");
        }
        
        $abnormalVitals = $this->observationRepo->getAbnormalVitals($patientId, $days);
        
        $result = [];
        foreach ($abnormalVitals as $vital) {
            $result[] = [
                'observation_id' => $vital->getObservationId(),
                'type' => $vital->getCode(),
                'name' => $vital->getDisplayName(),
                'value' => $vital->getFormattedValue(),
                'abnormal_flag' => $vital->getAbnormalFlag(),
                'observed_at' => $vital->getObservedAt(),
                'color' => $this->getAbnormalFlagColor($vital->getAbnormalFlag())
            ];
        }
        
        return $result;
    }
    
    /**
     * Record new vitals
     */
    public function recordVitals(string $encounterId, string $patientId, array $vitals, string $userId): array
    {
        $recorded = [];
        
        foreach ($vitals as $vitalData) {
            $vital = new Vital([
                'encounter_id' => $encounterId,
                'patient_id' => $patientId,
                'code' => $vitalData['code'],
                'value' => $vitalData['value'],
                'units' => $vitalData['units'],
                'observed_at' => $vitalData['observed_at'] ?? date('Y-m-d H:i:s'),
                'observed_by' => $userId,
                'method' => $vitalData['method'] ?? null,
                'body_site' => $vitalData['body_site'] ?? null
            ]);
            
            // Set abnormal flag
            $value = floatval($vitalData['value']);
            $abnormalFlag = $this->validator->getAbnormalFlag($vitalData['code'], $value);
            $vital->setAbnormalFlag($abnormalFlag);
            
            // Set reference range
            $referenceRange = $this->validator->getReferenceRange($vitalData['code']);
            $vital->setReferenceRange($referenceRange);
            
            $recorded[] = $this->observationRepo->create($vital, $userId);
        }
        
        return $recorded;
    }
    
    /**
     * Get vital trend for specific vital type
     */
    public function getVitalTrend(string $patientId, string $vitalCode, int $days = 30): array
    {
        $patient = $this->patientRepo->findById($patientId);
        if (!$patient) {
            throw new Exception("Patient not found: $patientId");
        }
        
        if (!$this->validator->isSupported($vitalCode)) {
            throw new Exception("Unsupported vital code: $vitalCode");
        }
        
        $trendData = $this->observationRepo->getVitalTrend($patientId, $vitalCode, $days);
        
        return [
            'vital_code' => $vitalCode,
            'vital_name' => $this->getVitalDisplayName($vitalCode),
            'reference_range' => $this->validator->getReferenceRange($vitalCode),
            'data' => $trendData
        ];
    }
    
    /**
     * Group vitals by type
     */
    private function groupVitalsByType(array $vitals): array
    {
        $grouped = [];
        foreach ($vitals as $vital) {
            $code = $vital['code'];
            if (!isset($grouped[$code])) {
                $grouped[$code] = [];
            }
            $grouped[$code][] = $vital;
        }
        return $grouped;
    }
    
    /**
     * Process combined blood pressure
     */
    private function processCombinedBloodPressure(array $vitals): ?array
    {
        $systolic = null;
        $diastolic = null;
        
        foreach ($vitals as $vital) {
            if ($vital['type'] === 'bp_systolic') {
                $systolic = $vital;
            }
            if ($vital['type'] === 'bp_diastolic') {
                $diastolic = $vital;
            }
        }
        
        if ($systolic && $diastolic) {
            $bpData = $this->validator->validateBloodPressure(
                $systolic['value'],
                $diastolic['value']
            );
            
            return [
                'value' => $bpData['display_value'],
                'status' => $bpData['overall_status'],
                'color' => $bpData['color'],
                'systolic_status' => $bpData['systolic_status'],
                'diastolic_status' => $bpData['diastolic_status']
            ];
        }
        
        return null;
    }
    
    /**
     * Get vital display name
     */
    private function getVitalDisplayName(string $code): string
    {
        $displayNames = [
            'bp_systolic' => 'Blood Pressure (Systolic)',
            'bp_diastolic' => 'Blood Pressure (Diastolic)',
            'pulse' => 'Pulse',
            'temperature' => 'Temperature',
            'spo2' => 'SpO2',
            'respiration' => 'Respiration Rate',
            'blood_sugar' => 'Blood Sugar',
            'pain_scale' => 'Pain Scale',
            'weight' => 'Weight',
            'height' => 'Height',
            'bmi' => 'BMI'
        ];
        
        return $displayNames[$code] ?? ucfirst(str_replace('_', ' ', $code));
    }
    
    /**
     * Get color for abnormal flag
     */
    private function getAbnormalFlagColor(?string $flag): string
    {
        if (!$flag) return 'green';
        
        switch ($flag) {
            case 'HH': // Critical high
            case 'LL': // Critical low
                return 'red';
            case 'H':  // High
            case 'L':  // Low
                return 'yellow';
            case 'N':  // Normal
                return 'green';
            default:
                return 'orange';
        }
    }
}