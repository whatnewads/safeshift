<?php

declare(strict_types=1);

namespace Core\Services;

use Exception;
use RuntimeException;
use InvalidArgumentException;

/**
 * BedrockService - Amazon Bedrock API Integration
 *
 * Provides integration with Amazon Bedrock API using Claude models
 * for generating EMS clinical narratives from encounter data.
 *
 * @package Core\Services
 * @author SafeShift EHR Development Team
 */
class BedrockService extends BaseService
{
    /**
     * AWS Bedrock API Bearer Token
     * @var string|null
     */
    private ?string $apiKey = null;

    /**
     * AWS Bedrock Region
     * @var string
     */
    private string $region = 'us-east-1';

    /**
     * Model ID for Bedrock
     * Use cross-region inference profile format for API Keys
     * DeepSeek-R1 - excellent reasoning model for text generation
     * @var string
     */
    private string $modelId = 'us.deepseek.r1-v1:0';

    /**
     * API Base URL Template
     * @var string
     */
    private string $apiBaseUrl = 'https://bedrock-runtime.{region}.amazonaws.com';

    /**
     * Request timeout in seconds
     * @var int
     */
    private int $timeout = 60;

    /**
     * Maximum retry attempts for rate limit errors
     * @var int
     */
    private int $maxRetries = 3;

    /**
     * Base delay in seconds for exponential backoff
     * @var int
     */
    private int $retryBaseDelay = 5;

    /**
     * Maximum tokens for response
     * @var int
     */
    private int $maxTokens = 2048;

    /**
     * Temperature for response generation (lower = more deterministic)
     * @var float
     */
    private float $temperature = 0.3;

    /**
     * Constructor - Initialize BedrockService with API key from environment
     *
     * @throws RuntimeException If API key is not configured
     */
    public function __construct()
    {
        parent::__construct();
        $this->initializeApiKey();
        $this->initializeConfiguration();
    }

    /**
     * Initialize API key from environment variable
     *
     * @throws RuntimeException If API key is not set
     */
    private function initializeApiKey(): void
    {
        // Try environment variable first
        $apiKey = getenv('AWS_BEARER_TOKEN_BEDROCK');
        
        // Fallback to $_ENV if getenv fails
        if ($apiKey === false || empty($apiKey)) {
            $apiKey = $_ENV['AWS_BEARER_TOKEN_BEDROCK'] ?? null;
        }
        
        // Fallback to defined constant
        if (empty($apiKey) && defined('AWS_BEARER_TOKEN_BEDROCK')) {
            $apiKey = AWS_BEARER_TOKEN_BEDROCK;
        }

        if (empty($apiKey)) {
            $this->logError('Bedrock API key not configured', [
                'hint' => 'Set AWS_BEARER_TOKEN_BEDROCK environment variable'
            ]);
            throw new RuntimeException(
                'Amazon Bedrock API key is not configured. Please set the AWS_BEARER_TOKEN_BEDROCK environment variable.'
            );
        }

        $this->apiKey = $apiKey;
        $this->logInfo('BedrockService initialized successfully');
    }

    /**
     * Initialize additional configuration from environment
     */
    private function initializeConfiguration(): void
    {
        // Allow region override from environment
        $region = getenv('AWS_BEDROCK_REGION');
        if ($region !== false && !empty($region)) {
            $this->region = $region;
        }

        // Allow model ID override from environment
        $modelId = getenv('AWS_BEDROCK_MODEL_ID');
        if ($modelId !== false && !empty($modelId)) {
            $this->modelId = $modelId;
        }

        // Allow timeout override
        $timeout = getenv('AWS_BEDROCK_TIMEOUT');
        if ($timeout !== false && is_numeric($timeout)) {
            $this->timeout = (int)$timeout;
        }
    }

    /**
     * Generate a clinical narrative using Amazon Bedrock Claude API
     *
     * @param string $prompt The complete prompt including system instructions and encounter data
     * @return string The generated narrative text
     * @throws InvalidArgumentException If prompt is empty
     * @throws RuntimeException If API call fails
     */
    public function generateNarrative(string $prompt): string
    {
        if (empty(trim($prompt))) {
            throw new InvalidArgumentException('Prompt cannot be empty');
        }

        $this->logInfo('Starting narrative generation request');

        try {
            $response = $this->callBedrockApi($prompt);
            $narrative = $this->parseResponse($response);
            
            $this->logInfo('Narrative generation completed successfully', [
                'narrative_length' => strlen($narrative)
            ]);

            return $narrative;
        } catch (Exception $e) {
            $this->logError('Narrative generation failed', [
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            throw $e;
        }
    }

    /**
     * Call the Amazon Bedrock Converse API with retry logic
     *
     * @param string $prompt The prompt to send to the API
     * @return array The decoded API response
     * @throws RuntimeException If the API call fails after all retries
     */
    private function callBedrockApi(string $prompt): array
    {
        $url = $this->buildApiUrl();
        $requestBody = $this->buildRequestBody($prompt);
        $headers = $this->buildHeaders();

        $this->logInfo('Making Bedrock API request', [
            'url' => $url,
            'model' => $this->modelId,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature
        ]);

        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->executeHttpRequest($url, $requestBody, $headers);
                return $response;
            } catch (RuntimeException $e) {
                $lastException = $e;
                
                // Only retry on rate limit (429) or server errors (5xx)
                if ($e->getCode() !== 429 && ($e->getCode() < 500 || $e->getCode() > 599)) {
                    throw $e;
                }
                
                if ($attempt < $this->maxRetries) {
                    // Calculate exponential backoff delay with jitter
                    $delay = $this->retryBaseDelay * pow(2, $attempt - 1);
                    $jitter = rand(0, 1000) / 1000; // 0-1 second jitter
                    $totalDelay = $delay + $jitter;
                    
                    $this->logInfo("Rate limited. Retrying in {$totalDelay}s (attempt {$attempt}/{$this->maxRetries})", [
                        'error_code' => $e->getCode(),
                        'delay' => $totalDelay
                    ]);
                    
                    sleep((int)$totalDelay);
                }
            }
        }

        // All retries exhausted
        throw $lastException ?? new RuntimeException('API call failed after all retries');
    }

    /**
     * Build the Bedrock API URL
     *
     * @return string The full API URL
     */
    private function buildApiUrl(): string
    {
        $baseUrl = str_replace('{region}', $this->region, $this->apiBaseUrl);
        $encodedModelId = urlencode($this->modelId);
        
        return "{$baseUrl}/model/{$encodedModelId}/converse";
    }

    /**
     * Build the request body for the Converse API
     *
     * @param string $prompt The user prompt
     * @return array The request body array
     */
    private function buildRequestBody(string $prompt): array
    {
        return [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'inferenceConfig' => [
                'temperature' => $this->temperature,
                'maxTokens' => $this->maxTokens
            ]
        ];
    }

    /**
     * Build HTTP headers for the API request
     *
     * @return array The headers array
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
    }

    /**
     * Execute HTTP request using cURL
     *
     * @param string $url The API URL
     * @param array $body The request body
     * @param array $headers The request headers
     * @return array The decoded response
     * @throws RuntimeException If the request fails
     */
    private function executeHttpRequest(string $url, array $body, array $headers): array
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        try {
            $curlOptions = [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
            ];

            // Add CA certificate bundle for SSL verification (fixes Windows SSL issues)
            $caCertPath = $this->getCaCertPath();
            if ($caCertPath !== null) {
                $curlOptions[CURLOPT_CAINFO] = $caCertPath;
            }

            curl_setopt_array($ch, $curlOptions);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);

            // Handle cURL errors
            if ($curlErrno !== 0) {
                throw new RuntimeException(
                    $this->formatCurlError($curlErrno, $curlError),
                    $curlErrno
                );
            }

            // Handle HTTP errors
            if ($httpCode < 200 || $httpCode >= 300) {
                $this->handleHttpError($httpCode, $responseBody);
            }

            // Parse response
            $decoded = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    'Failed to parse API response: ' . json_last_error_msg()
                );
            }

            return $decoded;
        } finally {
            curl_close($ch);
        }
    }

    /**
     * Format cURL error message
     *
     * @param int $errno The cURL error number
     * @param string $error The cURL error message
     * @return string Formatted error message
     */
    private function formatCurlError(int $errno, string $error): string
    {
        $commonErrors = [
            CURLE_COULDNT_CONNECT => 'Could not connect to Bedrock API. Please check network connectivity.',
            CURLE_COULDNT_RESOLVE_HOST => 'Could not resolve Bedrock API hostname. Please check DNS configuration.',
            CURLE_OPERATION_TIMEDOUT => 'Request to Bedrock API timed out. The server may be experiencing high load.',
            CURLE_SSL_CONNECT_ERROR => 'SSL connection to Bedrock API failed. Please check SSL/TLS configuration.',
            CURLE_SSL_CERTPROBLEM => 'SSL certificate problem. Please verify certificate chain.',
        ];

        if (isset($commonErrors[$errno])) {
            return $commonErrors[$errno];
        }

        return "Network error communicating with Bedrock API: {$error} (code: {$errno})";
    }

    /**
     * Handle HTTP error responses
     *
     * @param int $httpCode The HTTP status code
     * @param string $responseBody The response body
     * @throws RuntimeException With appropriate error message
     */
    private function handleHttpError(int $httpCode, string $responseBody): void
    {
        // Try to extract error message from response
        $errorMessage = $this->extractErrorMessage($responseBody);

        // Map common HTTP error codes to user-friendly messages
        switch ($httpCode) {
            case 400:
                throw new RuntimeException(
                    "Bad request to Bedrock API: {$errorMessage}",
                    $httpCode
                );

            case 401:
                // Never log the actual API key - just note authentication failed
                $this->logError('Authentication failed - API key may be invalid or expired');
                throw new RuntimeException(
                    'Authentication failed. Please verify your Bedrock API credentials.',
                    $httpCode
                );

            case 403:
                throw new RuntimeException(
                    'Access denied. Please verify your AWS permissions for Bedrock.',
                    $httpCode
                );

            case 404:
                throw new RuntimeException(
                    "Model not found: {$this->modelId}. Please verify the model ID.",
                    $httpCode
                );

            case 429:
                throw new RuntimeException(
                    'Rate limit exceeded. Please try again in a few moments.',
                    $httpCode
                );

            case 500:
            case 502:
            case 503:
            case 504:
                throw new RuntimeException(
                    'Bedrock API service is temporarily unavailable. Please try again later.',
                    $httpCode
                );

            default:
                throw new RuntimeException(
                    "Bedrock API error (HTTP {$httpCode}): {$errorMessage}",
                    $httpCode
                );
        }
    }

    /**
     * Extract error message from API response body
     *
     * @param string $responseBody The raw response body
     * @return string The extracted error message
     */
    private function extractErrorMessage(string $responseBody): string
    {
        if (empty($responseBody)) {
            return 'No error details available';
        }

        $decoded = json_decode($responseBody, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Check common error response formats
            if (isset($decoded['message'])) {
                return $decoded['message'];
            }
            if (isset($decoded['error']['message'])) {
                return $decoded['error']['message'];
            }
            if (isset($decoded['error'])) {
                return is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
            }
        }

        // Return truncated raw response if not JSON
        $maxLength = 200;
        if (strlen($responseBody) > $maxLength) {
            return substr($responseBody, 0, $maxLength) . '...';
        }

        return $responseBody;
    }

    /**
     * Parse the Bedrock API response to extract the narrative text
     *
     * Response format:
     * {
     *   "output": {
     *     "message": {
     *       "role": "assistant",
     *       "content": [
     *         { "text": "The generated narrative..." }
     *       ]
     *     }
     *   }
     * }
     *
     * @param array $response The decoded API response
     * @return string The extracted narrative text
     * @throws RuntimeException If response format is unexpected
     */
    private function parseResponse(array $response): string
    {
        // Navigate the response structure
        if (!isset($response['output'])) {
            $this->logError('Invalid response structure: missing output field', [
                'response_keys' => array_keys($response)
            ]);
            throw new RuntimeException('Invalid API response: missing output field');
        }

        if (!isset($response['output']['message'])) {
            throw new RuntimeException('Invalid API response: missing message field');
        }

        if (!isset($response['output']['message']['content'])) {
            throw new RuntimeException('Invalid API response: missing content field');
        }

        $content = $response['output']['message']['content'];
        
        if (!is_array($content) || empty($content)) {
            throw new RuntimeException('Invalid API response: content is empty');
        }

        // Extract text from the first content block
        $firstContent = $content[0];
        
        if (!isset($firstContent['text'])) {
            throw new RuntimeException('Invalid API response: missing text in content');
        }

        $narrative = trim($firstContent['text']);

        if (empty($narrative)) {
            throw new RuntimeException('API returned empty narrative');
        }

        return $narrative;
    }

    /**
     * Set custom temperature for narrative generation
     *
     * @param float $temperature Value between 0.0 and 1.0
     * @return self
     * @throws InvalidArgumentException If temperature is out of range
     */
    public function setTemperature(float $temperature): self
    {
        if ($temperature < 0.0 || $temperature > 1.0) {
            throw new InvalidArgumentException('Temperature must be between 0.0 and 1.0');
        }
        $this->temperature = $temperature;
        return $this;
    }

    /**
     * Set custom max tokens for narrative generation
     *
     * @param int $maxTokens Maximum tokens (1-4096)
     * @return self
     * @throws InvalidArgumentException If maxTokens is out of range
     */
    public function setMaxTokens(int $maxTokens): self
    {
        if ($maxTokens < 1 || $maxTokens > 4096) {
            throw new InvalidArgumentException('Max tokens must be between 1 and 4096');
        }
        $this->maxTokens = $maxTokens;
        return $this;
    }

    /**
     * Set custom timeout for API requests
     *
     * @param int $timeout Timeout in seconds (5-300)
     * @return self
     * @throws InvalidArgumentException If timeout is out of range
     */
    public function setTimeout(int $timeout): self
    {
        if ($timeout < 5 || $timeout > 300) {
            throw new InvalidArgumentException('Timeout must be between 5 and 300 seconds');
        }
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Get the current model ID
     *
     * @return string
     */
    public function getModelId(): string
    {
        return $this->modelId;
    }

    /**
     * Get the current region
     *
     * @return string
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Check if the service is properly configured
     *
     * @return bool True if API key is set
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get the path to CA certificate bundle for SSL verification
     *
     * This fixes SSL certificate verification issues on Windows
     * where PHP's cURL doesn't have a CA bundle configured.
     *
     * @return string|null Path to CA cert file, or null if not found
     */
    private function getCaCertPath(): ?string
    {
        // Check for project-local CA bundle first
        $projectCaCert = dirname(__DIR__, 2) . '/cache/cacert.pem';
        if (file_exists($projectCaCert)) {
            return $projectCaCert;
        }

        // Check php.ini curl.cainfo setting
        $phpIniCaInfo = ini_get('curl.cainfo');
        if (!empty($phpIniCaInfo) && file_exists($phpIniCaInfo)) {
            return $phpIniCaInfo;
        }

        // Check for common CA bundle locations
        $commonLocations = [
            '/etc/ssl/certs/ca-certificates.crt', // Debian/Ubuntu
            '/etc/pki/tls/certs/ca-bundle.crt',   // CentOS/RHEL
            '/etc/ssl/ca-bundle.pem',             // OpenSUSE
            '/usr/local/share/certs/ca-root-nss.crt', // FreeBSD
            'C:\\Windows\\System32\\curl-ca-bundle.crt', // Windows curl
        ];

        foreach ($commonLocations as $location) {
            if (file_exists($location)) {
                return $location;
            }
        }

        // No CA bundle found - cURL will use system defaults
        return null;
    }

    /**
     * Test the API connection with a simple request
     *
     * @return array Status information
     */
    public function testConnection(): array
    {
        try {
            $startTime = microtime(true);
            
            // Send a simple test prompt
            $response = $this->generateNarrative('Respond with: OK');
            
            $duration = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'model' => $this->modelId,
                'region' => $this->region,
                'response_time_ms' => $duration
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'model' => $this->modelId,
                'region' => $this->region
            ];
        }
    }
}
