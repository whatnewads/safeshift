<?php
/**
 * Patient Vitals ViewModel
 * 
 * Handles API request/response formatting for patient vitals
 * Validates input and transforms Model data for API consumption
 */

namespace ViewModel;

use Core\Services\PatientVitalsService;
use Exception;

class PatientVitalsViewModel
{
    private PatientVitalsService $vitalsService;
    
    /**
     * Constructor
     */
    public function __construct(PatientVitalsService $vitalsService)
    {
        $this->vitalsService = $vitalsService;
    }
    
    /**
     * Get patient vitals data
     */
    public function getVitalsData(array $input): array
    {
        try {
            // Input validation
            $errors = $this->validateInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Get patient ID from encounter if needed
            $patientId = $this->resolvePatientId($input);
            if (!$patientId) {
                return [
                    'success' => false,
                    'error' => 'Unable to resolve patient ID'
                ];
            }
            
            // Call Model service
            $vitals = $this->vitalsService->getPatientVitalTrends(
                $patientId,
                $input['days'] ?? 30
            );
            
            // Transform for API response
            return [
                'success' => true,
                'data' => $vitals,
                'period' => [
                    'days' => $input['days'] ?? 30,
                    'from' => date('Y-m-d', strtotime('-' . ($input['days'] ?? 30) . ' days')),
                    'to' => date('Y-m-d')
                ],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Vitals fetch error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve vitals',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Get latest vitals only
     */
    public function getLatestVitals(array $input): array
    {
        try {
            // Input validation
            $errors = $this->validatePatientIdInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Call Model service
            $vitals = $this->vitalsService->getLatestVitals($input['patient_id']);
            
            // Transform for API response
            return [
                'success' => true,
                'data' => [
                    'patient_id' => $input['patient_id'],
                    'vitals' => $vitals
                ],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Latest vitals fetch error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve latest vitals',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Get abnormal vitals
     */
    public function getAbnormalVitals(array $input): array
    {
        try {
            // Input validation
            $errors = $this->validatePatientIdInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            $days = isset($input['days']) ? (int)$input['days'] : 7;
            
            // Call Model service
            $abnormalVitals = $this->vitalsService->getAbnormalVitals(
                $input['patient_id'],
                $days
            );
            
            // Transform for API response
            return [
                'success' => true,
                'data' => [
                    'patient_id' => $input['patient_id'],
                    'abnormal_vitals' => $abnormalVitals,
                    'count' => count($abnormalVitals)
                ],
                'period' => [
                    'days' => $days,
                    'from' => date('Y-m-d', strtotime("-$days days")),
                    'to' => date('Y-m-d')
                ],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Abnormal vitals fetch error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve abnormal vitals',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Record new vitals
     */
    public function recordVitals(array $input): array
    {
        try {
            // Input validation
            $errors = $this->validateRecordVitalsInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Call Model service
            $recorded = $this->vitalsService->recordVitals(
                $input['encounter_id'],
                $input['patient_id'],
                $input['vitals'],
                $input['user_id']
            );
            
            // Transform for API response
            return [
                'success' => true,
                'data' => [
                    'recorded_count' => count($recorded),
                    'vitals' => $recorded
                ],
                'message' => 'Vitals recorded successfully',
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Vitals recording error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to record vitals',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Get vital trend for specific type
     */
    public function getVitalTrend(array $input): array
    {
        try {
            // Input validation
            $errors = $this->validateTrendInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            $days = isset($input['days']) ? (int)$input['days'] : 30;
            
            // Call Model service
            $trend = $this->vitalsService->getVitalTrend(
                $input['patient_id'],
                $input['vital_code'],
                $days
            );
            
            // Transform for API response
            return [
                'success' => true,
                'data' => $trend,
                'period' => [
                    'days' => $days,
                    'from' => date('Y-m-d', strtotime("-$days days")),
                    'to' => date('Y-m-d')
                ],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Vital trend fetch error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve vital trend',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Validate input for getting vitals
     */
    private function validateInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        // Either patient_id or encounter_id is required
        if (empty($input['patient_id']) && empty($input['encounter_id'])) {
            $errors['patient_id'] = 'Either patient_id or encounter_id is required';
        }
        
        if (!empty($input['patient_id']) && !preg_match($uuidPattern, $input['patient_id'])) {
            $errors['patient_id'] = 'Invalid patient ID format';
        }
        
        if (!empty($input['encounter_id']) && !preg_match($uuidPattern, $input['encounter_id'])) {
            $errors['encounter_id'] = 'Invalid encounter ID format';
        }
        
        if (isset($input['days'])) {
            if (!is_numeric($input['days']) || $input['days'] < 1 || $input['days'] > 365) {
                $errors['days'] = 'Days must be between 1 and 365';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate patient ID input
     */
    private function validatePatientIdInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['patient_id'])) {
            $errors['patient_id'] = 'Patient ID is required';
        } elseif (!preg_match($uuidPattern, $input['patient_id'])) {
            $errors['patient_id'] = 'Invalid patient ID format';
        }
        
        return $errors;
    }
    
    /**
     * Validate record vitals input
     */
    private function validateRecordVitalsInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['encounter_id'])) {
            $errors['encounter_id'] = 'Encounter ID is required';
        } elseif (!preg_match($uuidPattern, $input['encounter_id'])) {
            $errors['encounter_id'] = 'Invalid encounter ID format';
        }
        
        if (empty($input['patient_id'])) {
            $errors['patient_id'] = 'Patient ID is required';
        } elseif (!preg_match($uuidPattern, $input['patient_id'])) {
            $errors['patient_id'] = 'Invalid patient ID format';
        }
        
        if (empty($input['user_id'])) {
            $errors['user_id'] = 'User ID is required';
        }
        
        if (empty($input['vitals']) || !is_array($input['vitals'])) {
            $errors['vitals'] = 'Vitals array is required';
        } else {
            // Validate each vital
            foreach ($input['vitals'] as $index => $vital) {
                if (empty($vital['code'])) {
                    $errors["vitals.$index.code"] = 'Vital code is required';
                }
                if (empty($vital['value'])) {
                    $errors["vitals.$index.value"] = 'Vital value is required';
                }
                if (empty($vital['units'])) {
                    $errors["vitals.$index.units"] = 'Vital units is required';
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate trend input
     */
    private function validateTrendInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['patient_id'])) {
            $errors['patient_id'] = 'Patient ID is required';
        } elseif (!preg_match($uuidPattern, $input['patient_id'])) {
            $errors['patient_id'] = 'Invalid patient ID format';
        }
        
        if (empty($input['vital_code'])) {
            $errors['vital_code'] = 'Vital code is required';
        }
        
        if (isset($input['days'])) {
            if (!is_numeric($input['days']) || $input['days'] < 1 || $input['days'] > 365) {
                $errors['days'] = 'Days must be between 1 and 365';
            }
        }
        
        return $errors;
    }
    
    /**
     * Resolve patient ID from input
     */
    private function resolvePatientId(array $input): ?string
    {
        if (!empty($input['patient_id'])) {
            return $input['patient_id'];
        }
        
        if (!empty($input['encounter_id'])) {
            // In production, would look up patient ID from encounter
            // For now, return null to indicate need for implementation
            return null;
        }
        
        return null;
    }
}