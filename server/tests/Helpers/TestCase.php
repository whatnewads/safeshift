<?php
/**
 * Base TestCase for SafeShift EHR Tests
 * 
 * Provides common testing utilities including:
 * - Database transaction wrapping
 * - Mock user authentication
 * - Test data factories
 * - Helper assertions
 * 
 * @package SafeShift\Tests\Helpers
 */

declare(strict_types=1);

namespace Tests\Helpers;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use PDO;

/**
 * Base test case class with common utilities
 */
abstract class TestCase extends PHPUnitTestCase
{
    /** @var PDO|null Database connection */
    protected ?PDO $pdo = null;
    
    /** @var bool Whether to use database transactions */
    protected bool $useTransaction = true;
    
    /** @var array<string, mixed> Mock session data */
    protected array $mockSession = [];
    
    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize mock session
        $this->mockSession = [];
        
        // Start database transaction if configured
        if ($this->useTransaction) {
            $this->beginTransaction();
        }
    }
    
    /**
     * Tear down test environment
     */
    protected function tearDown(): void
    {
        // Rollback database transaction if started
        if ($this->useTransaction && $this->pdo !== null) {
            $this->rollbackTransaction();
        }
        
        // Clear mock session
        $this->mockSession = [];
        
        parent::tearDown();
    }
    
    /**
     * Get database connection
     */
    protected function getDatabase(): ?PDO
    {
        if ($this->pdo === null) {
            $this->pdo = getTestDatabase();
        }
        return $this->pdo;
    }
    
    /**
     * Begin database transaction
     */
    protected function beginTransaction(): void
    {
        $pdo = $this->getDatabase();
        if ($pdo !== null && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }
    
    /**
     * Rollback database transaction
     */
    protected function rollbackTransaction(): void
    {
        if ($this->pdo !== null && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
    
    /**
     * Commit database transaction (use sparingly)
     */
    protected function commitTransaction(): void
    {
        if ($this->pdo !== null && $this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }
    
    // =========================================================================
    // Mock Authentication Methods
    // =========================================================================
    
    /**
     * Set up mock authenticated user
     * 
     * @param string $role User role
     * @param array<string, mixed> $overrides Additional user data
     */
    protected function actAsUser(string $role = 'pclinician', array $overrides = []): array
    {
        $user = createTestUser($role, $overrides);
        $this->mockSession = [
            'user' => $user,
            'last_activity' => time(),
            'csrf_token' => bin2hex(random_bytes(32)),
        ];
        
        // Also set in $_SESSION for components that use it directly
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array_merge($_SESSION, $this->mockSession);
        }
        
        return $user;
    }
    
    /**
     * Set up mock admin user
     */
    protected function actAsAdmin(): array
    {
        return $this->actAsUser('Admin', [
            'username' => 'admin_test',
            'email' => 'admin@test.safeshift.com',
        ]);
    }
    
    /**
     * Set up mock manager user
     */
    protected function actAsManager(): array
    {
        return $this->actAsUser('Manager', [
            'username' => 'manager_test',
            'email' => 'manager@test.safeshift.com',
        ]);
    }
    
    /**
     * Set up mock clinician user
     */
    protected function actAsClinician(): array
    {
        return $this->actAsUser('pclinician', [
            'username' => 'clinician_test',
            'email' => 'clinician@test.safeshift.com',
        ]);
    }
    
    /**
     * Clear mock authentication
     */
    protected function logout(): void
    {
        $this->mockSession = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }
    
    /**
     * Get mock CSRF token
     */
    protected function getCsrfToken(): string
    {
        return $this->mockSession['csrf_token'] ?? 'test_csrf_token';
    }
    
    /**
     * Get current mock user
     */
    protected function getCurrentUser(): ?array
    {
        return $this->mockSession['user'] ?? null;
    }
    
    // =========================================================================
    // Test Data Factory Methods
    // =========================================================================
    
    /**
     * Create test patient data
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function makePatient(array $overrides = []): array
    {
        return createTestPatient($overrides);
    }
    
    /**
     * Create test encounter data
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function makeEncounter(array $overrides = []): array
    {
        return createTestEncounter($overrides);
    }
    
    /**
     * Create test user data
     * 
     * @param string $role
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function makeUser(string $role = 'pclinician', array $overrides = []): array
    {
        return createTestUser($role, $overrides);
    }
    
    // =========================================================================
    // Custom Assertions
    // =========================================================================
    
    /**
     * Assert that array has expected structure
     * 
     * @param array<string> $expectedKeys
     * @param array<string, mixed> $actual
     * @param string $message
     */
    protected function assertArrayStructure(array $expectedKeys, array $actual, string $message = ''): void
    {
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $actual, $message ?: "Missing expected key: {$key}");
        }
    }
    
    /**
     * Assert that response indicates success
     * 
     * @param array<string, mixed> $response
     */
    protected function assertSuccessResponse(array $response): void
    {
        $this->assertTrue($response['success'] ?? false, 'Expected successful response');
        $this->assertArrayHasKey('data', $response, 'Success response should have data key');
    }
    
    /**
     * Assert that response indicates error
     * 
     * @param array<string, mixed> $response
     * @param int|null $expectedCode
     */
    protected function assertErrorResponse(array $response, ?int $expectedCode = null): void
    {
        $this->assertFalse($response['success'] ?? true, 'Expected error response');
        $this->assertTrue(
            isset($response['errors']) || isset($response['error']),
            'Error response should have errors or error key'
        );
        
        if ($expectedCode !== null && isset($response['status'])) {
            $this->assertEquals($expectedCode, $response['status'], "Expected HTTP status {$expectedCode}");
        }
    }
    
    /**
     * Assert that response has validation error for field
     * 
     * @param array<string, mixed> $response
     * @param string $field
     */
    protected function assertValidationError(array $response, string $field): void
    {
        $this->assertErrorResponse($response);
        $errors = $response['errors'] ?? [];
        $this->assertArrayHasKey($field, $errors, "Expected validation error for field: {$field}");
    }
    
    /**
     * Assert that two dates are equal (ignoring time)
     * 
     * @param string $expected
     * @param string $actual
     * @param string $message
     */
    protected function assertDatesEqual(string $expected, string $actual, string $message = ''): void
    {
        $expectedDate = date('Y-m-d', strtotime($expected));
        $actualDate = date('Y-m-d', strtotime($actual));
        $this->assertEquals($expectedDate, $actualDate, $message ?: 'Dates should be equal');
    }
    
    /**
     * Assert that a value is a valid UUID
     * 
     * @param mixed $value
     * @param string $message
     */
    protected function assertValidUuid($value, string $message = ''): void
    {
        $this->assertIsString($value, $message ?: 'UUID should be a string');
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($pattern, $value, $message ?: 'Value should be a valid UUID');
    }
    
    /**
     * Assert that execution time is within limit
     * 
     * @param callable $callback
     * @param float $maxSeconds
     * @param string $message
     */
    protected function assertExecutionTime(callable $callback, float $maxSeconds, string $message = ''): void
    {
        $start = microtime(true);
        $callback();
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(
            $maxSeconds, 
            $duration, 
            $message ?: "Execution should take less than {$maxSeconds} seconds"
        );
    }
    
    // =========================================================================
    // Helper Methods
    // =========================================================================
    
    /**
     * Generate unique test ID
     */
    protected function generateTestId(): string
    {
        return 'test_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }
    
    /**
     * Generate valid UUID for testing
     */
    protected function generateUuid(): string
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
    
    /**
     * Create a mock PDO for testing
     * 
     * @return \PHPUnit\Framework\MockObject\MockObject&PDO
     */
    protected function createMockPdo(): PDO
    {
        return $this->createMock(PDO::class);
    }
    
    /**
     * Disable tests that require database when not available
     */
    protected function skipIfNoDatabaseConnection(): void
    {
        if ($this->getDatabase() === null) {
            $this->markTestSkipped('Database connection not available');
        }
    }
    
    /**
     * Mark test as integration test
     */
    protected function markAsIntegrationTest(): void
    {
        if (getenv('SKIP_INTEGRATION_TESTS') === 'true') {
            $this->markTestSkipped('Integration tests are disabled');
        }
    }
}
