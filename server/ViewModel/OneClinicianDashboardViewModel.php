<?php
/**
 * 1Clinician Dashboard ViewModel
 * 
 * Handles data preparation and business logic for the 1clinician dashboard
 * Follows MVVM pattern - no direct HTML rendering
 */

namespace ViewModel;

use Core\Services\AuthService;
use Core\Services\AuditService;
use Core\Services\DashboardStatsService;
use Core\Services\PatientService;
use Core\Services\NotificationService;
use Core\Repositories\EncounterRepository;
use Core\Repositories\NotificationRepository;
use Core\Exceptions\AuthorizationException;

class OneClinicianDashboardViewModel
{
    private AuthService $authService;
    private AuditService $auditService;
    private DashboardStatsService $statsService;
    private PatientService $patientService;
    private NotificationService $notificationService;
    
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->auditService = new AuditService();
        
        // Proper dependency injection: ViewModel → Repository → PDO
        $encounterRepo = new EncounterRepository($GLOBALS['db']);
        $this->statsService = new DashboardStatsService($encounterRepo);
        
        $this->patientService = new PatientService();
        
        // Proper dependency injection: ViewModel → Service → Repository → PDO
        $notificationRepo = new NotificationRepository($GLOBALS['db']);
        $this->notificationService = new NotificationService($notificationRepo);
    }
    
    /**
     * Get dashboard data for the view
     * 
     * @return array View data including user info, stats, and UI configuration
     * @throws AuthorizationException If user doesn't have required role
     */
    public function getDashboardData(): array
    {
        // Validate user authentication and authorization
        if (!$this->authService->isLoggedIn()) {
            throw new AuthorizationException('User not authenticated');
        }
        
        $user = $this->authService->getCurrentUser();
        if (!$user) {
            throw new AuthorizationException('Unable to retrieve user information');
        }
        
        $userId = $user['user_id'];
        $userRole = $this->authService->getPrimaryRole();
        
        // Validate user has appropriate role
        $allowedRoles = ['1clinician', 'pclinician', 'cadmin', 'tadmin'];
        if (!$userRole || !in_array($userRole['slug'], $allowedRoles)) {
            $this->auditService->logUnauthorizedAccess('clinician-dashboard', $userId, [
                'attempted_role' => '1clinician',
                'actual_role' => $userRole ? $userRole['slug'] : 'none'
            ]);
            throw new AuthorizationException('Insufficient privileges to access this dashboard');
        }
        
        // Log dashboard access
        $this->auditService->logDashboardAccess('1clinician-dashboard', $userId);
        
        // Get clinic information
        $clinicName = $this->getClinicName($userId);
        
        // Prepare view data with standard structure
        return [
            // Standard view data for header/footer
            'currentUser' => $user,
            'csrf_token' => $_SESSION[CSRF_TOKEN_NAME] ?? '',
            'pageTitle' => 'Clinician Dashboard - SafeShift EHR',
            'pageDescription' => 'SafeShift EHR Clinician Dashboard for occupational health management',
            'bodyClass' => 'dashboard-clinician',
            'additionalCSS' => [
                '/View/assets/css/normalize.css',
                '/View/assets/css/common.css',
                '/View/assets/css/dashboard-clinician.css',
                '/View/assets/css/recent-patients.css'
            ],
            'additionalJS' => [
                '/View/assets/js/common.js',
                '/View/assets/js/dashboard-clinician.js',
                '/View/assets/js/recent-patients.js'
            ],
            
            // Dashboard-specific data
            'username' => $user['username'],
            'userId' => $userId,
            'userRole' => $userRole['slug'],
            'clinicName' => $clinicName,
            
            // API endpoints configuration
            'apiEndpoints' => [
                'dashboardStats' => '/api/dashboard-stats.php',
                'recentPatients' => '/api/recent-patients.php',
                'patientVitals' => '/api/patient-vitals.php',
                'notifications' => '/api/notifications.php',
                'logPatientAccess' => '/api/log-patient-access.php'
            ],
            
            // Feature flags
            'showTimeline' => true,
            'showRecentPatients' => true,
            'showNotifications' => true
        ];
    }

    private function logDashboardAccess($auditData)
    {
        // In production, this would fetch from database based on user's clinic association
        // For now, return a default value
    }

    /**
     * Get clinic name for the user
     *
     * @param int|string $userId User ID (can be UUID string or integer)
     * @return string
     */
    private function getClinicName(int|string $userId): string
    {
        // In production, this would fetch from database based on user's clinic association
        // For now, return a default value
        return "SafeShift Medical Center";
    }
    
    /**
     * Handle quick patient registration
     * 
     * @param array $data Registration form data
     * @return array Result with success status and patient ID or error
     */
    public function handleQuickRegistration(array $data): array
    {
        try {
            // Validate required fields
            $required = ['first_name', 'last_name', 'dob', 'sex'];
            $missing = [];
            
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                return [
                    'success' => false,
                    'error' => 'Missing required fields: ' . implode(', ', $missing)
                ];
            }
            
            // Create patient record
            $patientId = $this->patientService->createPatient($data);
            
            if ($patientId) {
                // Log the registration
                $this->auditService->logPatientRegistration($patientId, $data['registration_type'] ?? 'quick');
                
                return [
                    'success' => true,
                    'patient_id' => $patientId,
                    'redirect' => "/clinician/patient-records.php?patient_id={$patientId}"
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to create patient record'
                ];
            }
            
        } catch (\Exception $e) {
            error_log("Quick registration error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An error occurred during registration'
            ];
        }
    }
}