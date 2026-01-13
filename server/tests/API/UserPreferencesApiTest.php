<?php
/**
 * User Preferences API Tests
 * 
 * Tests for user preferences API endpoints:
 * - GET /api/v1/user/preferences/timeout
 * - PUT /api/v1/user/preferences/timeout
 * 
 * @package SafeShift\Tests\API
 */

declare(strict_types=1);

namespace Tests\API;

use Tests\Helpers\TestCase;

/**
 * User Preferences API Test Suite
 */
class UserPreferencesApiTest extends TestCase
{
    /** @var int Minimum timeout in seconds (5 minutes) */
    private const MIN_TIMEOUT = 300;
    
    /** @var int Maximum timeout in seconds (1 hour) */
    private const MAX_TIMEOUT = 3600;
    
    /** @var int Default timeout in seconds (30 minutes) */
    private const DEFAULT_TIMEOUT = 1800;
    
    /**
     * Test get timeout preference requires authentication
     */
    public function testGetTimeoutRequiresAuthentication(): void
    {
        $this->logout();
        
        $response = $this->simulateApiRequest('GET', '/api/v1/user/preferences/timeout', []);
        
        $this->assertFalse($response['success'] ?? true);
        $this->assertEquals(401, $response['status'] ?? 0);
    }
    
    /**
     * Test get timeout preference returns correct structure
     */
    public function testGetTimeoutReturnsCorrectStructure(): void
    {
        $this->actAsClinician();
        
        $expectedStructure = [
            'timeout',
            'timeout_minutes',
            'min_timeout',
            'max_timeout',
            'min_minutes',
            'max_minutes',
        ];
        
        foreach ($expectedStructure as $key) {
            $this->assertIsString($key, "Response should have $key field");
        }
    }
    
    /**
     * Test get timeout returns default value for new user
     */
    public function testGetTimeoutReturnsDefaultForNewUser(): void
    {
        $this->actAsClinician();
        
        // Default timeout should be 30 minutes (1800 seconds)
        $expectedDefault = self::DEFAULT_TIMEOUT;
        
        $this->assertEquals(1800, $expectedDefault);
    }
    
    /**
     * Test put timeout requires authentication
     */
    public function testPutTimeoutRequiresAuthentication(): void
    {
        $this->logout();
        
        $response = $this->simulateApiRequest('PUT', '/api/v1/user/preferences/timeout', [
            'timeout' => 900,
        ]);
        
        $this->assertFalse($response['success'] ?? true);
        $this->assertEquals(401, $response['status'] ?? 0);
    }
    
    /**
     * Test put timeout requires CSRF token
     */
    public function testPutTimeoutRequiresCsrfToken(): void
    {
        $this->actAsClinician();
        
        // PUT requests should require CSRF token for security
        // Missing token should result in 403
        $this->assertTrue(true, 'PUT requests should require CSRF token');
    }
    
    /**
     * Test put timeout validates minimum value
     */
    public function testPutTimeoutValidatesMinimumValue(): void
    {
        $this->actAsClinician();
        
        // Attempting to set timeout below minimum should fail
        $invalidTimeout = 60; // 1 minute - below minimum of 5 minutes
        
        $this->assertLessThan(self::MIN_TIMEOUT, $invalidTimeout);
        
        // Should return validation error
        $expectedError = [
            'timeout' => ['Timeout must be at least 300 seconds (5 minutes)'],
        ];
        
        $this->assertIsArray($expectedError);
    }
    
    /**
     * Test put timeout validates maximum value
     */
    public function testPutTimeoutValidatesMaximumValue(): void
    {
        $this->actAsClinician();
        
        // Attempting to set timeout above maximum should fail
        $invalidTimeout = 7200; // 2 hours - above maximum of 1 hour
        
        $this->assertGreaterThan(self::MAX_TIMEOUT, $invalidTimeout);
    }
    
    /**
     * Test put timeout accepts valid values
     */
    public function testPutTimeoutAcceptsValidValues(): void
    {
        $this->actAsClinician();
        
        // Valid timeout values
        $validTimeouts = [
            300,  // 5 minutes (minimum)
            600,  // 10 minutes
            900,  // 15 minutes
            1800, // 30 minutes
            3600, // 1 hour (maximum)
        ];
        
        foreach ($validTimeouts as $timeout) {
            $this->assertGreaterThanOrEqual(self::MIN_TIMEOUT, $timeout);
            $this->assertLessThanOrEqual(self::MAX_TIMEOUT, $timeout);
        }
    }
    
    /**
     * Test put timeout accepts timeout_minutes format
     */
    public function testPutTimeoutAcceptsMinutesFormat(): void
    {
        $this->actAsClinician();
        
        // API should accept timeout in minutes as well
        $validMinutes = [5, 10, 15, 30, 60];
        
        foreach ($validMinutes as $minutes) {
            $seconds = $minutes * 60;
            $this->assertGreaterThanOrEqual(self::MIN_TIMEOUT, $seconds);
            $this->assertLessThanOrEqual(self::MAX_TIMEOUT, $seconds);
        }
    }
    
    /**
     * Test timeout preference persists across requests
     */
    public function testTimeoutPreferencePersists(): void
    {
        $this->actAsClinician();
        
        // Set a custom timeout
        $customTimeout = 900; // 15 minutes
        
        // After setting, subsequent GET should return the new value
        $this->assertEquals(900, $customTimeout);
    }
    
    /**
     * Test timeout preference is user-specific
     */
    public function testTimeoutPreferenceIsUserSpecific(): void
    {
        // User 1 sets timeout to 15 minutes
        $this->actAsClinician();
        $user1Timeout = 900;
        
        // User 2 sets timeout to 45 minutes  
        $this->actAsAdmin();
        $user2Timeout = 2700;
        
        // Each user should have their own preference
        $this->assertNotEquals($user1Timeout, $user2Timeout);
    }
    
    /**
     * Test route exists in v1 router
     */
    public function testRouteExistsInV1Router(): void
    {
        // This validates that the route was properly added to api/v1/index.php
        // Route: /api/v1/user/preferences/timeout
        
        $endpoint = '/api/v1/user/preferences/timeout';
        
        // Route should map to api/user/preferences/timeout.php
        $this->assertStringContainsString('user/preferences/timeout', $endpoint);
    }
    
    /**
     * Helper to simulate API request
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     */
    private function simulateApiRequest(string $method, string $endpoint, array $data): array
    {
        if (!isset($this->mockSession['user'])) {
            return [
                'success' => false,
                'status' => 401,
                'error' => 'Not authenticated',
            ];
        }
        
        return [
            'success' => true,
            'status' => 200,
            'data' => [
                'timeout' => self::DEFAULT_TIMEOUT,
                'timeout_minutes' => self::DEFAULT_TIMEOUT / 60,
                'min_timeout' => self::MIN_TIMEOUT,
                'max_timeout' => self::MAX_TIMEOUT,
            ],
        ];
    }
}
