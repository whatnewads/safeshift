<?php
/**
 * DashboardStatsViewModel Unit Tests
 * 
 * Tests for the DashboardStatsViewModel class which handles dashboard
 * data retrieval for admin, manager, and clinician dashboards.
 * 
 * @package SafeShift\Tests\Unit\ViewModels
 */

declare(strict_types=1);

namespace Tests\Unit\ViewModels;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ViewModel\DashboardstatsViewModel;
use PDO;
use Tests\Helpers\Factories\UserFactory;

/**
 * @covers \ViewModel\DashboardstatsViewModel
 */
class DashboardStatsViewModelTest extends TestCase
{
    private DashboardstatsViewModel $viewModel;
    private MockObject&PDO $mockPdo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        // Clear session
        $_SESSION = [];
        
        // Create mock PDO
        $this->mockPdo = $this->createMock(PDO::class);
        
        // Create ViewModel with mock dependencies
        $this->viewModel = new DashboardstatsViewModel(
            null, // statsService
            null, // patientRepo
            null, // encounterRepo
            null, // userRepo
            null, // auditLogRepo
            null, // auditService
            $this->mockPdo
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    // =========================================================================
    // Admin Dashboard Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetAdminDashboardDataRequiresValidSession(): void
    {
        // No session set - should fail
        $result = $this->viewModel->getAdminDashboardData();
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * @test
     */
    public function testGetAdminDashboardDataRequiresAdminRole(): void
    {
        // Set up non-admin user session
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getAdminDashboardData();
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('permission', $result['errors']);
    }

    /**
     * @test
     */
    public function testGetAdminDashboardDataWithValidAdminSession(): void
    {
        // Set up admin user session
        $user = UserFactory::makeAdmin();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getAdminDashboardData();
        
        $this->assertIsArray($result);
        // Even if data fetching fails, the structure should be correct
        $this->assertArrayHasKey('success', $result);
    }

    // =========================================================================
    // Manager Dashboard Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetManagerDashboardDataRequiresValidSession(): void
    {
        // No session set - should fail
        $result = $this->viewModel->getManagerDashboardData();
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * @test
     */
    public function testGetManagerDashboardDataRequiresManagerRole(): void
    {
        // Set up non-manager user session (QA can't access manager dashboard)
        $user = UserFactory::makeQA();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getManagerDashboardData();
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * @test
     */
    public function testGetManagerDashboardDataWithValidManagerSession(): void
    {
        // Set up manager user session
        $user = UserFactory::makeManager();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getManagerDashboardData();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // =========================================================================
    // Clinician Dashboard Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetClinicianDashboardDataRequiresValidSession(): void
    {
        // No session set - should fail
        $result = $this->viewModel->getClinicianDashboardData(123);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * @test
     */
    public function testGetClinicianDashboardDataWithValidSession(): void
    {
        // Set up clinician user session
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getClinicianDashboardData(123);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // =========================================================================
    // Daily Encounter Count Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetDailyEncounterCountInSystemStats(): void
    {
        // Set up admin session for system stats access
        $user = UserFactory::makeAdmin();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getSystemStats();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('encounters_today', $result);
        $this->assertIsInt($result['encounters_today']);
        $this->assertGreaterThanOrEqual(0, $result['encounters_today']);
    }

    // =========================================================================
    // Active Encounters Count Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetActiveEncountersCount(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getPatientLoadStats(123);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('current_queue', $result);
        $this->assertIsInt($result['current_queue']);
        $this->assertGreaterThanOrEqual(0, $result['current_queue']);
    }

    // =========================================================================
    // Completed Encounters Today Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetCompletedEncountersToday(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getPatientLoadStats(123);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('patients_today', $result);
        $this->assertIsInt($result['patients_today']);
        $this->assertGreaterThanOrEqual(0, $result['patients_today']);
    }

    // =========================================================================
    // Metric Calculation Accuracy Tests
    // =========================================================================

    /**
     * @test
     */
    public function testMetricCalculationAccuracy(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getKPIMetrics(123);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertArrayHasKey('documentation_completion', $result);
        $this->assertArrayHasKey('patient_satisfaction', $result);
        $this->assertArrayHasKey('reports_on_time', $result);
        
        // Each metric should have current, target, and trend
        foreach (['response_time', 'documentation_completion', 'patient_satisfaction', 'reports_on_time'] as $metric) {
            $this->assertArrayHasKey('current', $result[$metric]);
            $this->assertArrayHasKey('target', $result[$metric]);
            $this->assertArrayHasKey('trend', $result[$metric]);
        }
    }

    // =========================================================================
    // Empty Data Handling Tests
    // =========================================================================

    /**
     * @test
     */
    public function testEmptyDataHandlingForPatientLoad(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        // Use a user ID that won't have any data
        $result = $this->viewModel->getPatientLoadStats(999999);
        
        $this->assertIsArray($result);
        // Should return valid structure even with empty data
        $this->assertArrayHasKey('patients_today', $result);
        $this->assertArrayHasKey('patients_this_week', $result);
        $this->assertArrayHasKey('patients_this_month', $result);
        $this->assertArrayHasKey('current_queue', $result);
        $this->assertArrayHasKey('average_per_day', $result);
        $this->assertArrayHasKey('trend', $result);
    }

    /**
     * @test
     */
    public function testEmptyDataHandlingForEncounterStats(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getEncounterStats(999999, 'today');
        
        $this->assertIsArray($result);
        // Should return valid structure even with empty data
        $this->assertArrayHasKey('total_encounters', $result);
        $this->assertArrayHasKey('completed_encounters', $result);
        $this->assertArrayHasKey('pending_encounters', $result);
        $this->assertArrayHasKey('encounters_by_type', $result);
        $this->assertArrayHasKey('average_duration', $result);
        $this->assertArrayHasKey('date_range', $result);
    }

    // =========================================================================
    // Session Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidateSessionWithValidSession(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->validateSession();
        
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testValidateSessionWithExpiredSession(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        // Set last activity to more than session timeout
        $_SESSION['last_activity'] = time() - 3600;
        
        $result = $this->viewModel->validateSession();
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function testValidateSessionWithMissingUser(): void
    {
        // No user set
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->validateSession();
        
        $this->assertFalse($result);
    }

    // =========================================================================
    // CSRF Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidateCSRFTokenWithValidToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        $result = $this->viewModel->validateCSRFToken($token);
        
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testValidateCSRFTokenWithInvalidToken(): void
    {
        $_SESSION['csrf_token'] = 'valid_token';
        
        $result = $this->viewModel->validateCSRFToken('invalid_token');
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function testValidateCSRFTokenWithMissingSessionToken(): void
    {
        // No CSRF token in session
        $result = $this->viewModel->validateCSRFToken('some_token');
        
        $this->assertFalse($result);
    }

    // =========================================================================
    // Dashboard Permission Tests
    // =========================================================================

    /**
     * @test
     * @dataProvider dashboardPermissionProvider
     */
    public function testCheckDashboardPermission(string $dashboardType, string $role, bool $expectedResult): void
    {
        $user = UserFactory::make($role);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->checkDashboardPermission($dashboardType);
        
        $this->assertEquals($expectedResult, $result, 
            "Role '{$role}' should " . ($expectedResult ? '' : 'not ') . "have access to '{$dashboardType}' dashboard");
    }

    /**
     * Data provider for dashboard permission tests
     */
    public static function dashboardPermissionProvider(): array
    {
        return [
            'Admin can access admin dashboard' => ['admin', 'Admin', true],
            'cadmin can access admin dashboard' => ['admin', 'cadmin', true],
            'Manager cannot access admin dashboard' => ['admin', 'Manager', false],
            'Manager can access manager dashboard' => ['manager', 'Manager', true],
            'Admin can access manager dashboard' => ['manager', 'Admin', true],
            'pclinician can access clinician dashboard' => ['clinician', 'pclinician', true],
            'dclinician can access clinician dashboard' => ['clinician', 'dclinician', true],
            '1clinician can access clinician dashboard' => ['clinician', '1clinician', true],
            'QA can access qa_review dashboard' => ['qa_review', 'QA', true],
            'Admin can access compliance dashboard' => ['compliance', 'Admin', true],
            'pclinician can access compliance dashboard' => ['compliance', 'pclinician', true],
        ];
    }

    // =========================================================================
    // Role-Based Dashboard Redirect Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetRoleBasedDashboardForClinician(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getRoleBasedDashboard();
        
        $this->assertIsString($result);
        $this->assertStringContainsString('pclinician', $result);
    }

    /**
     * @test
     */
    public function testGetRoleBasedDashboardForAdmin(): void
    {
        $user = UserFactory::makeAdmin();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getRoleBasedDashboard();
        
        $this->assertIsString($result);
        $this->assertStringContainsString('admin', strtolower($result));
    }

    /**
     * @test
     */
    public function testGetRoleBasedDashboardWithoutSession(): void
    {
        $result = $this->viewModel->getRoleBasedDashboard();
        
        $this->assertEquals('/login', $result);
    }

    // =========================================================================
    // Date Range Processing Tests
    // =========================================================================

    /**
     * @test
     * @dataProvider dateRangeProvider
     */
    public function testProcessDateRange(string $range, string $expectedStartKey): void
    {
        $result = $this->viewModel->processDateRange($range);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('start', $result);
        $this->assertArrayHasKey('end', $result);
        
        // Verify dates are valid
        $start = strtotime($result['start']);
        $end = strtotime($result['end']);
        
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertLessThanOrEqual($end, $start, 'Start date should be before or equal to end date');
    }

    /**
     * Data provider for date range tests
     */
    public static function dateRangeProvider(): array
    {
        return [
            'today' => ['today', 'start'],
            'week' => ['week', 'start'],
            'month' => ['month', 'start'],
            'quarter' => ['quarter', 'start'],
            'year' => ['year', 'start'],
        ];
    }

    // =========================================================================
    // Compliance Data Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetComplianceDataRequiresValidSession(): void
    {
        $result = $this->viewModel->getComplianceData();
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    /**
     * @test
     */
    public function testGetComplianceDataWithValidSession(): void
    {
        $user = UserFactory::makeAdmin();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getComplianceData();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // =========================================================================
    // Stats Legacy Compatibility Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetStatsReturnsProperFormat(): void
    {
        $result = $this->viewModel->getStats();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('timestamp', $result);
    }

    /**
     * @test
     */
    public function testGetStatsForDateRangeValidatesInput(): void
    {
        $input = [
            'start_date' => 'invalid-date',
            'end_date' => '2024-12-31',
        ];
        
        $result = $this->viewModel->getStatsForDateRange($input);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * @test
     */
    public function testGetStatsForDateRangeWithValidDates(): void
    {
        $input = [
            'start_date' => date('Y-m-d', strtotime('-7 days')),
            'end_date' => date('Y-m-d'),
        ];
        
        $result = $this->viewModel->getStatsForDateRange($input);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
