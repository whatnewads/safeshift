<?php
/**
 * BedrockService Unit Tests
 *
 * Tests for the BedrockService class which handles AI narrative generation
 * using Amazon Bedrock's Claude models.
 *
 * @package SafeShift\Tests\Unit\Services
 */

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Core\Services\BedrockService;
use RuntimeException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

/**
 * @covers \Core\Services\BedrockService
 */
class BedrockServiceTest extends TestCase
{
    /**
     * Store original environment values for cleanup
     */
    private array $originalEnv = [];

    /**
     * Store keys to unset after test
     */
    private array $envKeysToClean = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Save original environment values
        $this->originalEnv = [
            'AWS_BEARER_TOKEN_BEDROCK' => getenv('AWS_BEARER_TOKEN_BEDROCK'),
            'AWS_BEDROCK_REGION' => getenv('AWS_BEDROCK_REGION'),
            'AWS_BEDROCK_MODEL_ID' => getenv('AWS_BEDROCK_MODEL_ID'),
            'AWS_BEDROCK_TIMEOUT' => getenv('AWS_BEDROCK_TIMEOUT'),
        ];
    }

    protected function tearDown(): void
    {
        // Restore original environment values
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }
        
        // Clean up any keys we set during tests
        foreach ($this->envKeysToClean as $key) {
            putenv($key);
        }
        
        parent::tearDown();
    }

    /**
     * Set environment variable for testing
     */
    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $this->envKeysToClean[] = $key;
    }

    // =========================================================================
    // Initialization Tests
    // =========================================================================

    /**
     * @test
     */
    public function constructorThrowsExceptionWhenApiKeyNotConfigured(): void
    {
        // Clear all possible API key sources
        putenv('AWS_BEARER_TOKEN_BEDROCK');
        unset($_ENV['AWS_BEARER_TOKEN_BEDROCK']);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Amazon Bedrock API key is not configured');
        
        new BedrockService();
    }

    /**
     * @test
     */
    public function constructorInitializesWithEnvironmentApiKey(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key-12345');
        
        $service = new BedrockService();
        
        $this->assertTrue($service->isConfigured());
    }

    /**
     * @test
     */
    public function constructorInitializesWithEnvArrayApiKey(): void
    {
        // Clear getenv source
        putenv('AWS_BEARER_TOKEN_BEDROCK');
        
        // Set via $_ENV
        $_ENV['AWS_BEARER_TOKEN_BEDROCK'] = 'test-api-key-from-env';
        
        try {
            $service = new BedrockService();
            $this->assertTrue($service->isConfigured());
        } finally {
            unset($_ENV['AWS_BEARER_TOKEN_BEDROCK']);
        }
    }

    /**
     * @test
     */
    public function constructorUsesDefaultRegionWhenNotSpecified(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->assertEquals('us-east-1', $service->getRegion());
    }

    /**
     * @test
     */
    public function constructorUsesCustomRegionFromEnvironment(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        $this->setEnv('AWS_BEDROCK_REGION', 'us-west-2');
        
        $service = new BedrockService();
        
        $this->assertEquals('us-west-2', $service->getRegion());
    }

    /**
     * @test
     */
    public function constructorUsesDefaultModelIdWhenNotSpecified(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->assertStringContainsString('anthropic.claude', $service->getModelId());
    }

    /**
     * @test
     */
    public function constructorUsesCustomModelIdFromEnvironment(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        $this->setEnv('AWS_BEDROCK_MODEL_ID', 'anthropic.claude-v2');
        
        $service = new BedrockService();
        
        $this->assertEquals('anthropic.claude-v2', $service->getModelId());
    }

    // =========================================================================
    // generateNarrative Tests
    // =========================================================================

    /**
     * @test
     */
    public function generateNarrativeThrowsExceptionForEmptyPrompt(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt cannot be empty');
        
        $service->generateNarrative('');
    }

    /**
     * @test
     */
    public function generateNarrativeThrowsExceptionForWhitespaceOnlyPrompt(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Prompt cannot be empty');
        
        $service->generateNarrative('   ');
    }

    /**
     * @test
     */
    public function generateNarrativeCallsBedrockApiWithCorrectStructure(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        // Create a partial mock to intercept the HTTP call
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        // Mock successful response from Bedrock
        $mockResponse = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => 'Generated narrative text here.']
                    ]
                ]
            ]
        ];
        
        $service->expects($this->once())
            ->method('executeHttpRequest')
            ->willReturn($mockResponse);
        
        $result = $service->generateNarrative('Test prompt');
        
        $this->assertEquals('Generated narrative text here.', $result);
    }

    /**
     * @test
     */
    public function generateNarrativeReturnsNarrativeFromValidResponse(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $expectedNarrative = 'Patient presented with chief complaint of headache. Vitals obtained and within normal limits. Patient was treated and discharged in stable condition.';
        
        $service->method('executeHttpRequest')
            ->willReturn([
                'output' => [
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['text' => $expectedNarrative]
                        ]
                    ]
                ]
            ]);
        
        $result = $service->generateNarrative('Generate narrative for encounter');
        
        $this->assertEquals($expectedNarrative, $result);
    }

    // =========================================================================
    // Response Parsing Error Tests
    // =========================================================================

    /**
     * @test
     */
    public function generateNarrativeThrowsExceptionWhenResponseMissingOutput(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willReturn(['unexpected' => 'structure']);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API response: missing output field');
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeThrowsExceptionWhenResponseMissingMessage(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willReturn([
                'output' => ['other' => 'data']
            ]);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API response: missing message field');
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeThrowsExceptionWhenResponseMissingContent(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willReturn([
                'output' => [
                    'message' => ['role' => 'assistant']
                ]
            ]);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API response: missing content field');
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeThrowsExceptionWhenContentIsEmpty(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willReturn([
                'output' => [
                    'message' => [
                        'role' => 'assistant',
                        'content' => []
                    ]
                ]
            ]);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API response: content is empty');
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeThrowsExceptionWhenTextMissingFromContent(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willReturn([
                'output' => [
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'text']
                        ]
                    ]
                ]
            ]);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API response: missing text in content');
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeThrowsExceptionWhenNarrativeIsEmpty(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willReturn([
                'output' => [
                    'message' => [
                        'role' => 'assistant',
                        'content' => [
                            ['text' => '   ']
                        ]
                    ]
                ]
            ]);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API returned empty narrative');
        
        $service->generateNarrative('Test prompt');
    }

    // =========================================================================
    // HTTP Error Handling Tests
    // =========================================================================

    /**
     * @test
     */
    public function generateNarrativeHandles401AuthenticationError(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'invalid-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willThrowException(new RuntimeException(
                'Authentication failed. Please verify your Bedrock API credentials.',
                401
            ));
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(401);
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeHandles403AccessDenied(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willThrowException(new RuntimeException(
                'Access denied. Please verify your AWS permissions for Bedrock.',
                403
            ));
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeHandles404ModelNotFound(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willThrowException(new RuntimeException(
                'Model not found. Please verify the model ID.',
                404
            ));
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeHandles429RateLimitExceeded(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willThrowException(new RuntimeException(
                'Rate limit exceeded. Please try again in a few moments.',
                429
            ));
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);
        $this->expectExceptionMessage('Rate limit exceeded');
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeHandles500ServerError(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willThrowException(new RuntimeException(
                'Bedrock API service is temporarily unavailable.',
                500
            ));
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(500);
        
        $service->generateNarrative('Test prompt');
    }

    /**
     * @test
     */
    public function generateNarrativeHandles503ServiceUnavailable(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['executeHttpRequest'])
            ->getMock();
        
        $service->method('executeHttpRequest')
            ->willThrowException(new RuntimeException(
                'Bedrock API service is temporarily unavailable. Please try again later.',
                503
            ));
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(503);
        
        $service->generateNarrative('Test prompt');
    }

    // =========================================================================
    // Configuration Method Tests
    // =========================================================================

    /**
     * @test
     */
    public function setTemperatureAcceptsValidValues(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $result = $service->setTemperature(0.0);
        $this->assertSame($service, $result); // Test fluent interface
        
        $result = $service->setTemperature(0.5);
        $this->assertSame($service, $result);
        
        $result = $service->setTemperature(1.0);
        $this->assertSame($service, $result);
    }

    /**
     * @test
     */
    public function setTemperatureThrowsExceptionForValueBelowZero(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 1.0');
        
        $service->setTemperature(-0.1);
    }

    /**
     * @test
     */
    public function setTemperatureThrowsExceptionForValueAboveOne(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 1.0');
        
        $service->setTemperature(1.1);
    }

    /**
     * @test
     */
    public function setMaxTokensAcceptsValidValues(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $result = $service->setMaxTokens(1);
        $this->assertSame($service, $result);
        
        $result = $service->setMaxTokens(2048);
        $this->assertSame($service, $result);
        
        $result = $service->setMaxTokens(4096);
        $this->assertSame($service, $result);
    }

    /**
     * @test
     */
    public function setMaxTokensThrowsExceptionForValueBelowOne(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tokens must be between 1 and 4096');
        
        $service->setMaxTokens(0);
    }

    /**
     * @test
     */
    public function setMaxTokensThrowsExceptionForValueAbove4096(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tokens must be between 1 and 4096');
        
        $service->setMaxTokens(4097);
    }

    /**
     * @test
     */
    public function setTimeoutAcceptsValidValues(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $result = $service->setTimeout(5);
        $this->assertSame($service, $result);
        
        $result = $service->setTimeout(60);
        $this->assertSame($service, $result);
        
        $result = $service->setTimeout(300);
        $this->assertSame($service, $result);
    }

    /**
     * @test
     */
    public function setTimeoutThrowsExceptionForValueBelowFive(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be between 5 and 300 seconds');
        
        $service->setTimeout(4);
    }

    /**
     * @test
     */
    public function setTimeoutThrowsExceptionForValueAbove300(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be between 5 and 300 seconds');
        
        $service->setTimeout(301);
    }

    // =========================================================================
    // Getter Method Tests
    // =========================================================================

    /**
     * @test
     */
    public function getModelIdReturnsConfiguredModelId(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        $this->setEnv('AWS_BEDROCK_MODEL_ID', 'anthropic.claude-3-haiku');
        
        $service = new BedrockService();
        
        $this->assertEquals('anthropic.claude-3-haiku', $service->getModelId());
    }

    /**
     * @test
     */
    public function getRegionReturnsConfiguredRegion(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        $this->setEnv('AWS_BEDROCK_REGION', 'eu-west-1');
        
        $service = new BedrockService();
        
        $this->assertEquals('eu-west-1', $service->getRegion());
    }

    /**
     * @test
     */
    public function isConfiguredReturnsTrueWhenApiKeyIsSet(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        $this->assertTrue($service->isConfigured());
    }

    // =========================================================================
    // testConnection Tests
    // =========================================================================

    /**
     * @test
     */
    public function testConnectionReturnsSuccessOnValidResponse(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['generateNarrative'])
            ->getMock();
        
        $service->method('generateNarrative')
            ->willReturn('OK');
        
        $result = $service->testConnection();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Connection successful', $result['message']);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('region', $result);
        $this->assertArrayHasKey('response_time_ms', $result);
    }

    /**
     * @test
     */
    public function testConnectionReturnsFailureOnError(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = $this->getMockBuilder(BedrockService::class)
            ->onlyMethods(['generateNarrative'])
            ->getMock();
        
        $service->method('generateNarrative')
            ->willThrowException(new RuntimeException('Connection failed', 500));
        
        $result = $service->testConnection();
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Connection failed', $result['message']);
        $this->assertEquals(500, $result['error_code']);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('region', $result);
    }

    // =========================================================================
    // API URL Building Tests
    // =========================================================================

    /**
     * @test
     */
    public function buildApiUrlContainsCorrectRegion(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        $this->setEnv('AWS_BEDROCK_REGION', 'ap-northeast-1');
        
        $service = new BedrockService();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildApiUrl');
        $method->setAccessible(true);
        
        $url = $method->invoke($service);
        
        $this->assertStringContainsString('ap-northeast-1', $url);
        $this->assertStringContainsString('bedrock-runtime', $url);
        $this->assertStringContainsString('/converse', $url);
    }

    /**
     * @test
     */
    public function buildRequestBodyContainsCorrectStructure(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key');
        
        $service = new BedrockService();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildRequestBody');
        $method->setAccessible(true);
        
        $body = $method->invoke($service, 'Test prompt text');
        
        $this->assertIsArray($body);
        $this->assertArrayHasKey('messages', $body);
        $this->assertArrayHasKey('inferenceConfig', $body);
        
        $this->assertCount(1, $body['messages']);
        $this->assertEquals('user', $body['messages'][0]['role']);
        $this->assertEquals('Test prompt text', $body['messages'][0]['content'][0]['text']);
        
        $this->assertArrayHasKey('temperature', $body['inferenceConfig']);
        $this->assertArrayHasKey('maxTokens', $body['inferenceConfig']);
    }

    /**
     * @test
     */
    public function buildHeadersContainsAuthorizationBearer(): void
    {
        $this->setEnv('AWS_BEARER_TOKEN_BEDROCK', 'test-api-key-xyz');
        
        $service = new BedrockService();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildHeaders');
        $method->setAccessible(true);
        
        $headers = $method->invoke($service);
        
        $this->assertIsArray($headers);
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('Accept: application/json', $headers);
        
        // Check that Authorization header exists with Bearer prefix
        $authHeader = null;
        foreach ($headers as $header) {
            if (strpos($header, 'Authorization: Bearer') === 0) {
                $authHeader = $header;
                break;
            }
        }
        $this->assertNotNull($authHeader, 'Authorization Bearer header should exist');
    }
}
