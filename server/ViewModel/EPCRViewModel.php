<?php
/**
 * EPCR ViewModel
 * 
 * Handles API request/response formatting for EMS Electronic Patient Care Reports
 * Validates input and transforms Model data for API consumption
 */

namespace ViewModel;

use Core\Services\EPCRService;
use Exception;

class EPCRViewModel
{
    private EPCRService $epcrService;
    
    /**
     * Constructor
     */
    public function __construct(EPCRService $epcrService)
    {
        $this->epcrService = $epcrService;
    }
    
    /**
     * Save EPCR (draft or complete)
     */
    public function saveEPCR(array $input): array
    {
        try {
            // Basic input validation (format only)
            $errors = $this->validateSaveInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Extract user ID from session or input
            $userId = $input['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
            if (!$userId) {
                return [
                    'success' => false,
                    'error' => 'User ID not found'
                ];
            }
            
            // Call Model service
            $result = $this->epcrService->saveEPCR($input, $userId);
            
            // Transform for API response
            return [
                'success' => $result['success'],
                'encounter_id' => $result['encounter_id'],
                'patient_id' => $result['patient_id'],
                'epcr_id' => $result['epcr_id'],
                'message' => $result['message'],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("EPCR save error: " . $e->getMessage());
            
            // Parse validation errors if present
            if (strpos($e->getMessage(), 'Validation failed:') !== false) {
                $errors = json_decode(str_replace('Validation failed: ', '', $e->getMessage()), true);
                return [
                    'success' => false,
                    'errors' => $errors,
                    'message' => 'Validation failed',
                    'timestamp' => date('c')
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to save ePCR: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Submit EPCR for review
     */
    public function submitEPCR(array $input): array
    {
        try {
            // Validate input
            $errors = $this->validateSubmitInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Extract user ID
            $userId = $input['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
            if (!$userId) {
                return [
                    'success' => false,
                    'error' => 'User ID not found'
                ];
            }
            
            // Call Model service
            $result = $this->epcrService->submitEPCR($input['epcr_id'], $userId);
            
            // Transform for API response
            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("EPCR submit error: " . $e->getMessage());
            
            // Parse validation errors if present
            if (strpos($e->getMessage(), 'Validation failed:') !== false) {
                $errors = json_decode(str_replace('Validation failed: ', '', $e->getMessage()), true);
                return [
                    'success' => false,
                    'errors' => $errors,
                    'message' => 'ePCR is incomplete and cannot be submitted',
                    'timestamp' => date('c')
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to submit ePCR',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Validate EPCR without saving
     */
    public function validateEPCR(array $input): array
    {
        try {
            // Remove any system fields before validation
            unset($input['csrf_token']);
            unset($input['user_id']);
            
            // Call Model service
            $result = $this->epcrService->validateEPCR($input);
            
            // Transform for API response
            return [
                'success' => $result['valid'],
                'valid' => $result['valid'],
                'errors' => $result['errors'],
                'completion_percentage' => $result['completion_percentage'],
                'message' => $result['valid'] ? 'ePCR is valid' : 'ePCR has validation errors',
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("EPCR validation error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to validate ePCR',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Get EPCR by ID
     */
    public function getEPCR(array $input): array
    {
        try {
            // Validate input
            $errors = $this->validateGetInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Call Model service
            $epcr = $this->epcrService->getEPCR($input['epcr_id']);
            
            if (!$epcr) {
                return [
                    'success' => false,
                    'error' => 'ePCR not found',
                    'timestamp' => date('c')
                ];
            }
            
            // Transform for API response
            return [
                'success' => true,
                'data' => $this->formatEPCR($epcr->toArray()),
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("EPCR fetch error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve ePCR',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Get incomplete EPCRs for current user
     */
    public function getIncompleteEPCRs(array $input): array
    {
        try {
            // Extract user ID
            $userId = $input['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
            if (!$userId) {
                return [
                    'success' => false,
                    'error' => 'User ID not found'
                ];
            }
            
            // Call Model service
            $epcrs = $this->epcrService->getIncompleteEPCRs($userId);
            
            // Transform for API response
            $formatted = [];
            foreach ($epcrs as $epcr) {
                $formatted[] = $this->formatEPCRSummary($epcr->toArray());
            }
            
            return [
                'success' => true,
                'data' => $formatted,
                'count' => count($formatted),
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Incomplete EPCRs fetch error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve incomplete ePCRs',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Lock EPCR after review
     */
    public function lockEPCR(array $input): array
    {
        try {
            // Validate input
            $errors = $this->validateLockInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Extract user ID
            $userId = $input['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
            if (!$userId) {
                return [
                    'success' => false,
                    'error' => 'User ID not found'
                ];
            }
            
            // Call Model service
            $success = $this->epcrService->lockEPCR($input['epcr_id'], $userId);
            
            // Transform for API response
            return [
                'success' => $success,
                'message' => $success ? 'ePCR locked successfully' : 'Failed to lock ePCR',
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("EPCR lock error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to lock ePCR',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Validate save input (basic format validation only)
     */
    private function validateSaveInput(array $input): array
    {
        $errors = [];
        
        // Validate blood pressure format if provided
        if (!empty($input['blood_pressure']) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $input['blood_pressure'])) {
            $errors['blood_pressure'] = 'Invalid blood pressure format. Use format: 120/80';
        }
        
        // Validate email if provided
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        // Validate phone if provided
        if (!empty($input['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $input['phone']);
            if (strlen($phone) < 10) {
                $errors['phone'] = 'Phone number must be at least 10 digits';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate submit input
     */
    private function validateSubmitInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['epcr_id'])) {
            $errors['epcr_id'] = 'ePCR ID is required';
        } elseif (!preg_match($uuidPattern, $input['epcr_id'])) {
            $errors['epcr_id'] = 'Invalid ePCR ID format';
        }
        
        return $errors;
    }
    
    /**
     * Validate get input
     */
    private function validateGetInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['epcr_id'])) {
            $errors['epcr_id'] = 'ePCR ID is required';
        } elseif (!preg_match($uuidPattern, $input['epcr_id'])) {
            $errors['epcr_id'] = 'Invalid ePCR ID format';
        }
        
        return $errors;
    }
    
    /**
     * Validate lock input
     */
    private function validateLockInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['epcr_id'])) {
            $errors['epcr_id'] = 'ePCR ID is required';
        } elseif (!preg_match($uuidPattern, $input['epcr_id'])) {
            $errors['epcr_id'] = 'Invalid ePCR ID format';
        }
        
        return $errors;
    }
    
    /**
     * Format EPCR for API response
     */
    private function formatEPCR(array $epcr): array
    {
        // Ensure all expected fields are present
        return array_merge([
            'epcr_id' => null,
            'encounter_id' => null,
            'patient_id' => null,
            'incident_number' => null,
            'unit_number' => null,
            'dispatch_time' => null,
            'chief_complaint' => null,
            'transport_disposition' => null,
            'narrative' => null,
            'is_submitted' => false,
            'is_locked' => false,
            'created_at' => null,
            'updated_at' => null
        ], $epcr);
    }
    
    /**
     * Format EPCR summary for list views
     */
    private function formatEPCRSummary(array $epcr): array
    {
        return [
            'epcr_id' => $epcr['epcr_id'],
            'incident_number' => $epcr['incident_number'],
            'unit_number' => $epcr['unit_number'],
            'dispatch_time' => $epcr['dispatch_time'],
            'chief_complaint' => $epcr['chief_complaint'],
            'transport_disposition' => $epcr['transport_disposition'],
            'created_at' => $epcr['created_at']
        ];
    }
}