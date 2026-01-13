<?php
/**
 * Patient Search ViewModel
 * 
 * Handles patient search functionality following MVVM pattern
 * Separates business logic from presentation
 */

namespace ViewModel;

use Core\Services\AuthService;
use Core\Services\PatientService;
use Core\Services\AuditService;
use Core\Exceptions\AuthorizationException;

class PatientSearchViewModel
{
    private AuthService $authService;
    private PatientService $patientService;
    private AuditService $auditService;
    
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->patientService = new PatientService();
        $this->auditService = new AuditService();
    }
    
    /**
     * Get patient search page data
     * 
     * @return array View data for patient search page
     * @throws AuthorizationException If user lacks permission
     */
    public function getSearchPageData(): array
    {
        // Validate authentication
        if (!$this->authService->isLoggedIn()) {
            throw new AuthorizationException('User not authenticated');
        }
        
        $user = $this->authService->getCurrentUser();
        $userRole = $this->authService->getPrimaryRole();
        
        // Check permissions - all clinical roles can search patients
        $allowedRoles = ['1clinician', 'dclinician', 'pclinician', 'cadmin', 'tadmin'];
        if (!$userRole || !in_array($userRole['slug'], $allowedRoles)) {
            $this->auditService->logUnauthorizedAccess('patient-search', $user['user_id'], [
                'actual_role' => $userRole ? $userRole['slug'] : 'none'
            ]);
            throw new AuthorizationException('Insufficient privileges to access patient search');
        }
        
        // Log page access
        $this->auditService->logPageAccess('patient-search', $user['user_id']);
        
        // Return view data
        return [
            // Standard view data
            'currentUser' => $user,
            'csrf_token' => $_SESSION[CSRF_TOKEN_NAME] ?? '',
            'pageTitle' => 'Patient Search - SafeShift EHR',
            'pageDescription' => 'Search for patients in SafeShift EHR',
            'bodyClass' => 'patient-search-page',
            'additionalCSS' => [
                '/View/assets/css/normalize.css',
                '/View/assets/css/common.css',
                '/View/assets/css/patient-search.css'
            ],
            'additionalJS' => [
                '/View/assets/js/common.js',
                '/View/assets/js/patient-search.js'
            ],
            
            // Page-specific data
            'searchType' => $_GET['type'] ?? 'all',
            'recentSearches' => $this->getRecentSearches($user['user_id']),
            'employersList' => $this->patientService->getEmployersList()
        ];
    }
    
    /**
     * Handle patient search
     * 
     * @param array $searchData Search parameters
     * @return array Search results
     */
    public function searchPatients(array $searchData): array
    {
        // Validate user permissions
        if (!$this->authService->isLoggedIn()) {
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        $user = $this->authService->getCurrentUser();
        
        // Validate search parameters
        if (empty($searchData['query']) && empty($searchData['mrn']) && 
            empty($searchData['first_name']) && empty($searchData['last_name']) &&
            empty($searchData['dob'])) {
            return [
                'success' => false,
                'error' => 'Please provide at least one search criterion'
            ];
        }
        
        try {
            // Build search criteria
            $criteria = [];
            
            if (!empty($searchData['query'])) {
                // General search - could be name, MRN, etc.
                $criteria['query'] = $searchData['query'];
            }
            
            if (!empty($searchData['mrn'])) {
                $criteria['mrn'] = $searchData['mrn'];
            }
            
            if (!empty($searchData['first_name'])) {
                $criteria['first_name'] = $searchData['first_name'];
            }
            
            if (!empty($searchData['last_name'])) {
                $criteria['last_name'] = $searchData['last_name'];
            }
            
            if (!empty($searchData['dob'])) {
                $criteria['dob'] = $searchData['dob'];
            }
            
            if (!empty($searchData['employer_id'])) {
                $criteria['employer_id'] = $searchData['employer_id'];
            }
            
            // Perform search
            $results = $this->patientService->searchPatients($criteria);
            
            // Log the search
            $this->auditService->logPatientSearch($user['user_id'], $criteria, count($results));
            
            // Save to recent searches
            $this->saveRecentSearch($user['user_id'], $criteria);
            
            return [
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ];
            
        } catch (\Exception $e) {
            error_log("Patient search error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An error occurred during search'
            ];
        }
    }
    
    /**
     * Get recent patient searches for user
     * 
     * @param int $userId
     * @return array
     */
    private function getRecentSearches(int $userId): array
    {
        // In production, this would fetch from database
        // For now, return from session
        return $_SESSION['recent_patient_searches'][$userId] ?? [];
    }
    
    /**
     * Save recent search to history
     * 
     * @param int $userId
     * @param array $criteria
     */
    private function saveRecentSearch(int $userId, array $criteria): void
    {
        // Initialize if not exists
        if (!isset($_SESSION['recent_patient_searches'])) {
            $_SESSION['recent_patient_searches'] = [];
        }
        
        if (!isset($_SESSION['recent_patient_searches'][$userId])) {
            $_SESSION['recent_patient_searches'][$userId] = [];
        }
        
        // Add to beginning of array
        array_unshift($_SESSION['recent_patient_searches'][$userId], [
            'criteria' => $criteria,
            'timestamp' => time()
        ]);
        
        // Keep only last 10 searches
        $_SESSION['recent_patient_searches'][$userId] = array_slice(
            $_SESSION['recent_patient_searches'][$userId], 
            0, 
            10
        );
    }
    
    /**
     * Get patient details for quick view
     * 
     * @param int $patientId
     * @return array
     */
    public function getPatientQuickView(int $patientId): array
    {
        try {
            // Verify user can access this patient
            if (!$this->authService->isLoggedIn()) {
                return [
                    'success' => false,
                    'error' => 'Authentication required'
                ];
            }
            
            $user = $this->authService->getCurrentUser();
            
            // Get patient data
            $patient = $this->patientService->getPatientById($patientId);
            
            if (!$patient) {
                return [
                    'success' => false,
                    'error' => 'Patient not found'
                ];
            }
            
            // Log access
            $this->auditService->logPatientAccess($user['user_id'], $patientId, 'quick-view');
            
            return [
                'success' => true,
                'patient' => $patient
            ];
            
        } catch (\Exception $e) {
            error_log("Patient quick view error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to retrieve patient information'
            ];
        }
    }
}