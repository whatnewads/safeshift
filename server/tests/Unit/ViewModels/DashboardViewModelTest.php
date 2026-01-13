<?php
/**
 * DashboardViewModel Unit Tests
 * 
 * Tests for the DashboardViewModel class which handles general
 * dashboard operations and data retrieval.
 * 
 * @package SafeShift\Tests\Unit\ViewModels
 */

declare(strict_types=1);

namespace Tests\Unit\ViewModels;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ViewModel\DashboardViewModel;
use PDO;
use Tests\Helpers\Factories\UserFactory;

/**
 * @covers \ViewModel\DashboardViewModel
 */
class DashboardViewModelTest extends TestCase
{
    private DashboardViewModel $viewModel;
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
        
        // Create ViewModel
        $this->viewModel = new DashboardViewModel($this->mockPdo);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    // =========================================================================
    // Session Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testDashboardRequiresValidSession(): void
    {
        // No session set
        $result = $this->viewModel->getDashboardData();
        
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
    }

    /**
     * @test
     */
    public function testDashboardWithValidSession(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getDashboardData();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // =========================================================================
    // Role-Based Data Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetDashboardDataForClinician(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getDashboardData();
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function testGetDashboardDataForManager(): void
    {
        $user = UserFactory::makeManager();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getDashboardData();
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function testGetDashboardDataForAdmin(): void
    {
        $user = UserFactory::makeAdmin();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getDashboardData();
        
        $this->assertIsArray($result);
    }

    // =========================================================================
    // Encounter Summary Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetEncounterSummary(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getEncounterSummary();
        
        $this->assertIsArray($result);
        // Should contain summary stats
        if ($result['success'] ?? false) {
            $this->assertArrayHasKey('data', $result);
        }
    }

    /**
     * @test
     */
    public function testGetTodayEncounters(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getTodayEncounters();
        
        $this->assertIsArray($result);
    }

    // =========================================================================
    // Patient Summary Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetPatientSummary(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getPatientSummary();
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function testGetRecentPatients(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getRecentPatients(10);
        
        $this->assertIsArray($result);
    }

    // =========================================================================
    // Notification Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetNotifications(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getNotifications();
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function testGetUnreadNotificationCount(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getUnreadNotificationCount();
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    // =========================================================================
    // Widget Data Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetWidgetData(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getWidgetData('encounters_today');
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function testGetMultipleWidgets(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $widgets = ['encounters_today', 'pending_reviews', 'recent_patients'];
        $result = $this->viewModel->getWidgets($widgets);
        
        $this->assertIsArray($result);
    }

    // =========================================================================
    // Quick Stats Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetQuickStats(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getQuickStats();
        
        $this->assertIsArray($result);
    }

    // =========================================================================
    // User Preferences Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetUserDashboardPreferences(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $result = $this->viewModel->getUserDashboardPreferences();
        
        $this->assertIsArray($result);
    }

    /**
     * @test
     */
    public function testUpdateUserDashboardPreferences(): void
    {
        $user = UserFactory::makeClinician();
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $preferences = [
            'widgets' => ['encounters_today', 'recent_patients'],
            'theme' => 'light',
        ];
        
        $result = $this->viewModel->updateUserDashboardPreferences($preferences);
        
        $this->assertIsArray($result);
    }
}
