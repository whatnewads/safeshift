<?php
/**
 * Recent Patients ViewModel
 * 
 * Handles API request/response formatting for recent patients feature
 * Validates input and transforms Model data for API consumption
 */

namespace ViewModel;

use Core\Services\PatientAccessService;
use Exception;

class RecentPatientsViewModel
{
    private PatientAccessService $patientAccessService;
    
    /**
     * Constructor
     */
    public function __construct(PatientAccessService $patientAccessService)
    {
        $this->patientAccessService = $patientAccessService;
    }
    
    /**
     * Get recent patients for user
     */
    public function getRecentPatients(string $userId, array $input = []): array
    {
        try {
            // Validate input
            $errors = $this->validateInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Get limit from input or use default
            $limit = isset($input['limit']) ? (int)$input['limit'] : 10;
            
            // Call Model service
            $recentPatients = $this->patientAccessService->getRecentPatients($userId, $limit);
            
            // Log patient access for audit
            $this->logBulkAccess($recentPatients, $userId);
            
            // Transform for API response
            return [
                'success' => true,
                'data' => $this->formatPatients($recentPatients),
                'count' => count($recentPatients),
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Recent patients fetch error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve recent patients',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Log patient access
     */
    public function logPatientAccess(string $userId, array $input): array
    {
        try {
            // Validate input
            $errors = $this->validateAccessLogInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Log the access
            $success = $this->patientAccessService->logAccess(
                $input['patient_id'],
                $userId,
                $input['access_type'] ?? 'view'
            );
            
            return [
                'success' => $success,
                'message' => $success ? 'Access logged successfully' : 'Failed to log access',
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Patient access logging error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to log patient access',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Get patient access history
     */
    public function getPatientAccessHistory(array $input): array
    {
        try {
            // Validate input
            $errors = $this->validateHistoryInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Get limit from input or use default
            $limit = isset($input['limit']) ? (int)$input['limit'] : 50;
            
            // Call Model service
            $history = $this->patientAccessService->getPatientAccessHistory(
                $input['patient_id'],
                $limit
            );
            
            // Transform for API response
            return [
                'success' => true,
                'data' => $history,
                'count' => count($history),
                'patient_id' => $input['patient_id'],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Patient access history error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve patient access history',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Validate input
     */
    private function validateInput(array $input): array
    {
        $errors = [];
        
        if (isset($input['limit'])) {
            if (!is_numeric($input['limit']) || $input['limit'] < 1 || $input['limit'] > 100) {
                $errors['limit'] = 'Limit must be between 1 and 100';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate access log input
     */
    private function validateAccessLogInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['patient_id'])) {
            $errors['patient_id'] = 'Patient ID is required';
        } elseif (!preg_match($uuidPattern, $input['patient_id'])) {
            $errors['patient_id'] = 'Invalid patient ID format';
        }
        
        if (isset($input['access_type']) && !in_array($input['access_type'], ['view', 'edit'])) {
            $errors['access_type'] = 'Access type must be either "view" or "edit"';
        }
        
        return $errors;
    }
    
    /**
     * Validate history input
     */
    private function validateHistoryInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['patient_id'])) {
            $errors['patient_id'] = 'Patient ID is required';
        } elseif (!preg_match($uuidPattern, $input['patient_id'])) {
            $errors['patient_id'] = 'Invalid patient ID format';
        }
        
        if (isset($input['limit'])) {
            if (!is_numeric($input['limit']) || $input['limit'] < 1 || $input['limit'] > 500) {
                $errors['limit'] = 'Limit must be between 1 and 500';
            }
        }
        
        return $errors;
    }
    
    /**
     * Format patients for API response
     */
    private function formatPatients(array $patients): array
    {
        $formatted = [];
        
        foreach ($patients as $patient) {
            // Determine display name
            $displayName = trim($patient['preferred_name'] ?? '') ?: 
                          trim(($patient['legal_first_name'] ?? '') . ' ' . ($patient['legal_last_name'] ?? ''));
            
            $formatted[] = [
                'patient_uuid' => $patient['patient_id'],
                'full_name' => $displayName,
                'mrn' => $patient['mrn'] ?? 'N/A',
                'last_encounter_date' => $patient['last_encounter_date'],
                'employer_name' => $patient['employer_name'] ?? 'N/A',
                'accessed_at' => $patient['accessed_at'],
                'access_type' => $patient['access_type']
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Log bulk access for audit trail
     */
    private function logBulkAccess(array $patients, string $userId): void
    {
        try {
            // Log that user accessed recent patients list
            error_log(json_encode([
                'event' => 'recent_patients_accessed',
                'user_id' => $userId,
                'patient_count' => count($patients),
                'timestamp' => date('c')
            ]));
        } catch (Exception $e) {
            // Don't let logging errors break the main flow
            error_log("Failed to log bulk access: " . $e->getMessage());
        }
    }
}