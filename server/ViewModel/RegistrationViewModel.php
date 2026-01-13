<?php
/**
 * RegistrationViewModel.php - ViewModel for Registration Dashboard
 * 
 * Coordinates between the View (API) and Model (Repository) layers
 * for registration dashboard operations including queue management,
 * patient lookup, and check-in workflows.
 * 
 * @package SafeShift\ViewModel
 */

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\RegistrationRepository;
use ViewModel\Core\ApiResponse;
use ViewModel\Core\BaseViewModel;
use PDO;
use Exception;

/**
 * Registration ViewModel
 * 
 * Handles business logic for the registration dashboard vertical slice.
 * Provides methods for dashboard data, queue stats, pending registrations,
 * and patient search functionality.
 */
class RegistrationViewModel extends BaseViewModel
{
    /** @var RegistrationRepository Repository for registration data */
    private RegistrationRepository $registrationRepository;

    /**
     * Constructor
     * 
     * @param PDO|null $pdo Database connection
     */
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(null, $pdo);
        
        if ($this->pdo) {
            $this->registrationRepository = new RegistrationRepository($this->pdo);
        }
    }

    /**
     * Get complete dashboard data
     * 
     * Combines queue statistics with pending registrations for the
     * full registration dashboard view.
     * 
     * @return array API response with dashboard data
     */
    public function getDashboardData(): array
    {
        try {
            $this->requireAuth();
            
            // Verify repository is initialized
            if (!isset($this->registrationRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Get queue statistics
            $queueStats = $this->registrationRepository->getQueueStats();
            
            // Get pending registrations (waiting patients)
            $pendingRegistrations = $this->registrationRepository->getPendingRegistrations(20);
            
            // Log dashboard access for audit
            $this->audit('VIEW', 'registration_dashboard', null, [
                'pending_count' => count($pendingRegistrations),
                'queue_stats' => $queueStats
            ]);
            
            return ApiResponse::success([
                'queueStats' => $queueStats,
                'pendingRegistrations' => $pendingRegistrations
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve dashboard data');
        }
    }

    /**
     * Get queue statistics only
     * 
     * Returns just the queue counts without pending registrations list.
     * Useful for quick status updates or polling.
     * 
     * @return array API response with queue stats
     */
    public function getQueueStats(): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->registrationRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $queueStats = $this->registrationRepository->getQueueStats();
            
            return ApiResponse::success([
                'queueStats' => $queueStats
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve queue statistics');
        }
    }

    /**
     * Get pending registrations
     * 
     * Returns list of patients waiting for registration, with
     * wait time calculations and priority assignments.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with pending registrations
     */
    public function getPendingRegistrations(int $limit = 20): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->registrationRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $pendingRegistrations = $this->registrationRepository->getPendingRegistrations($limit);
            
            return ApiResponse::success([
                'pendingRegistrations' => $pendingRegistrations,
                'count' => count($pendingRegistrations)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve pending registrations');
        }
    }

    /**
     * Search patients
     * 
     * Search for patients by name, phone, email, or date of birth.
     * Returns formatted patient data suitable for display in
     * registration workflows.
     * 
     * @param string $query Search query
     * @return array API response with search results
     */
    public function searchPatients(string $query): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->registrationRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate query
            $query = trim($query);
            if (strlen($query) < 2) {
                return ApiResponse::validationError([
                    'query' => ['Search query must be at least 2 characters']
                ]);
            }
            
            // Sanitize query
            $query = $this->sanitizeValue($query);
            
            $patients = $this->registrationRepository->searchPatients($query, 50);
            
            // Log patient search for audit/HIPAA compliance
            $this->audit('SEARCH', 'patients', null, [
                'query_length' => strlen($query),
                'results_count' => count($patients)
            ]);
            
            return ApiResponse::success([
                'patients' => $patients,
                'count' => count($patients),
                'query' => $query
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to search patients');
        }
    }

    /**
     * Get patient by ID
     * 
     * Retrieve a single patient's details for registration.
     * 
     * @param string $patientId Patient UUID
     * @return array API response with patient data
     */
    public function getPatient(string $patientId): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->registrationRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate UUID format
            if (!$this->isValidUuid($patientId)) {
                return ApiResponse::validationError([
                    'patientId' => ['Invalid patient ID format']
                ]);
            }
            
            $patient = $this->registrationRepository->findPatientById($patientId);
            
            if ($patient === null) {
                return ApiResponse::notFound('Patient not found');
            }
            
            // Log PHI access for HIPAA compliance
            $this->logPhiAccess('patient', $patientId, 'view');
            
            return ApiResponse::success([
                'patient' => $patient
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve patient');
        }
    }

    /**
     * Format patient data for API response
     * 
     * Transforms raw patient data from the database into the
     * format expected by the frontend API.
     * 
     * @param array $patient Raw patient data
     * @return array Formatted patient data
     */
    public function formatPatientForResponse(array $patient): array
    {
        return [
            'id' => $patient['id'] ?? $patient['patient_id'] ?? null,
            'firstName' => $patient['firstName'] ?? $patient['legal_first_name'] ?? '',
            'lastName' => $patient['lastName'] ?? $patient['legal_last_name'] ?? '',
            'preferredName' => $patient['preferredName'] ?? $patient['preferred_name'] ?? null,
            'dateOfBirth' => $patient['dateOfBirth'] ?? $patient['dob'] ?? '',
            'phone' => $patient['phone'] ?? null,
            'email' => $patient['email'] ?? null
        ];
    }

    /**
     * Validate required fields for new registration
     * 
     * @param array $data Registration data
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateRegistrationData(array $data): array
    {
        $errors = [];
        
        // Required fields
        $required = ['firstName', 'lastName', 'dateOfBirth'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ["{$field} is required"];
            }
        }
        
        // Validate date of birth format
        if (!empty($data['dateOfBirth'])) {
            $dob = strtotime($data['dateOfBirth']);
            if ($dob === false) {
                $errors['dateOfBirth'] = ['Invalid date format'];
            } elseif ($dob > time()) {
                $errors['dateOfBirth'] = ['Date of birth cannot be in the future'];
            }
        }
        
        // Validate email format if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Invalid email format'];
        }
        
        // Validate phone format if provided
        if (!empty($data['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $data['phone']);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $errors['phone'] = ['Invalid phone number format'];
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
