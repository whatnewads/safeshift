<?php
/**
 * AppConfig.php - Application Configuration for SafeShift EHR
 * 
 * Centralizes application-wide configuration including session settings,
 * encryption keys reference, and application constants.
 * 
 * @package    SafeShift\Model\Config
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Config;

/**
 * Application configuration class
 * 
 * Provides centralized application configuration management with
 * environment-based settings and secure defaults.
 */
final class AppConfig
{
    /** @var string Application environment */
    private string $environment;
    
    /** @var bool Debug mode flag */
    private bool $debug;
    
    /** @var string Application name */
    private string $appName;
    
    /** @var string Application version */
    private string $appVersion;
    
    /** @var string Base URL */
    private string $baseUrl;
    
    /** @var string Encryption key reference (never store actual key) */
    private string $encryptionKeyPath;
    
    /** @var array<string, mixed> Session configuration */
    private array $sessionConfig;
    
    /** @var array<string, mixed> Security configuration */
    private array $securityConfig;
    
    /** @var array<string, mixed> Custom configuration values */
    private array $customConfig = [];
    
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /**
     * Application environments
     */
    public const ENV_PRODUCTION = 'production';
    public const ENV_STAGING = 'staging';
    public const ENV_DEVELOPMENT = 'development';
    public const ENV_TESTING = 'testing';

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->loadFromEnvironment();
        $this->setSessionDefaults();
        $this->setSecurityDefaults();
    }

    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load configuration from environment variables
     */
    private function loadFromEnvironment(): void
    {
        $this->environment = $this->getEnv('APP_ENV', self::ENV_PRODUCTION);
        $this->debug = filter_var(
            $this->getEnv('APP_DEBUG', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
        $this->appName = $this->getEnv('APP_NAME', 'SafeShift EHR');
        $this->appVersion = $this->getEnv('APP_VERSION', '1.0.0');
        $this->baseUrl = rtrim($this->getEnv('APP_URL', ''), '/');
        $this->encryptionKeyPath = $this->getEnv('ENCRYPTION_KEY_PATH', '');
    }

    /**
     * Set session configuration defaults
     */
    private function setSessionDefaults(): void
    {
        $isProduction = $this->environment === self::ENV_PRODUCTION;
        
        $this->sessionConfig = [
            // Session lifetime in seconds (30 minutes default)
            'lifetime' => (int) $this->getEnv('SESSION_LIFETIME', '1800'),
            
            // Idle timeout in seconds (15 minutes)
            'idle_timeout' => (int) $this->getEnv('SESSION_IDLE_TIMEOUT', '900'),
            
            // Session name
            'name' => $this->getEnv('SESSION_NAME', 'SAFESHIFT_SESSID'),
            
            // Session path
            'path' => $this->getEnv('SESSION_PATH', '/'),
            
            // Session domain
            'domain' => $this->getEnv('SESSION_DOMAIN', ''),
            
            // Secure cookie (HTTPS only)
            'secure' => $isProduction || filter_var(
                $this->getEnv('SESSION_SECURE', 'true'),
                FILTER_VALIDATE_BOOLEAN
            ),
            
            // HTTP only (no JavaScript access)
            'httponly' => true,
            
            // SameSite attribute (Strict, Lax, or None)
            'samesite' => $this->getEnv('SESSION_SAMESITE', 'Strict'),
            
            // Regenerate session ID interval (5 minutes)
            'regenerate_interval' => (int) $this->getEnv('SESSION_REGEN_INTERVAL', '300'),
            
            // Session handler (files, database, redis)
            'handler' => $this->getEnv('SESSION_HANDLER', 'files'),
            
            // Session save path (for file handler)
            'save_path' => $this->getEnv('SESSION_SAVE_PATH', ''),
        ];
    }

    /**
     * Set security configuration defaults
     */
    private function setSecurityDefaults(): void
    {
        $this->securityConfig = [
            // CSRF token name
            'csrf_token_name' => 'csrf_token',
            
            // CSRF token lifetime in seconds (1 hour)
            'csrf_token_lifetime' => 3600,
            
            // Password minimum length
            'password_min_length' => 12,
            
            // Password requires uppercase
            'password_require_uppercase' => true,
            
            // Password requires lowercase
            'password_require_lowercase' => true,
            
            // Password requires number
            'password_require_number' => true,
            
            // Password requires special character
            'password_require_special' => true,
            
            // Maximum login attempts before lockout
            'max_login_attempts' => 5,
            
            // Lockout duration in seconds (15 minutes)
            'lockout_duration' => 900,
            
            // Two-factor authentication enabled
            'two_factor_enabled' => filter_var(
                $this->getEnv('TWO_FACTOR_ENABLED', 'true'),
                FILTER_VALIDATE_BOOLEAN
            ),
            
            // OTP expiration in seconds (5 minutes)
            'otp_expiration' => 300,
            
            // Bcrypt cost factor
            'bcrypt_cost' => 12,
            
            // Allowed origins for CORS
            'allowed_origins' => array_filter(
                explode(',', $this->getEnv('ALLOWED_ORIGINS', ''))
            ),
            
            // Content Security Policy
            'csp_enabled' => true,
            
            // HIPAA audit logging enabled
            'audit_logging_enabled' => true,
        ];
    }

    /**
     * Get environment variable with fallback
     * 
     * @param string $key Environment variable name
     * @param string $default Default value if not set
     * @return string
     */
    private function getEnv(string $key, string $default = ''): string
    {
        // Check for defined constants first (legacy support)
        if (defined($key)) {
            return (string) constant($key);
        }
        
        // Then check environment variables
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Check $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Check $_SERVER (for Apache SetEnv)
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        return $default;
    }

    /**
     * Get configuration value by key
     * 
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = match ($parts[0]) {
            'session' => $this->sessionConfig,
            'security' => $this->securityConfig,
            'custom' => $this->customConfig,
            'environment' => $this->environment,
            'debug' => $this->debug,
            'app_name' => $this->appName,
            'app_version' => $this->appVersion,
            'base_url' => $this->baseUrl,
            default => $this->customConfig[$parts[0]] ?? $default,
        };
        
        // Navigate nested keys
        array_shift($parts);
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        
        return $value;
    }

    /**
     * Set custom configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return self
     */
    public function set(string $key, mixed $value): self
    {
        $this->customConfig[$key] = $value;
        return $this;
    }

    /**
     * Get session configuration
     * 
     * @return array<string, mixed>
     */
    public function getSessionConfig(): array
    {
        return $this->sessionConfig;
    }

    /**
     * Get security configuration
     * 
     * @return array<string, mixed>
     */
    public function getSecurityConfig(): array
    {
        return $this->securityConfig;
    }

    /**
     * Get application environment
     * 
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Check if running in production
     * 
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->environment === self::ENV_PRODUCTION;
    }

    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Get application name
     * 
     * @return string
     */
    public function getAppName(): string
    {
        return $this->appName;
    }

    /**
     * Get application version
     * 
     * @return string
     */
    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    /**
     * Get base URL
     * 
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get encryption key path (not the actual key)
     * 
     * @return string
     */
    public function getEncryptionKeyPath(): string
    {
        return $this->encryptionKeyPath;
    }

    /**
     * Get all configuration as array (safe for logging)
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'environment' => $this->environment,
            'debug' => $this->debug,
            'app_name' => $this->appName,
            'app_version' => $this->appVersion,
            'base_url' => $this->baseUrl,
            'session' => $this->sessionConfig,
            // Security config partially exposed (no sensitive values)
            'security' => [
                'password_min_length' => $this->securityConfig['password_min_length'],
                'max_login_attempts' => $this->securityConfig['max_login_attempts'],
                'two_factor_enabled' => $this->securityConfig['two_factor_enabled'],
                'audit_logging_enabled' => $this->securityConfig['audit_logging_enabled'],
            ],
        ];
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone(): void
    {
    }

    /**
     * Prevent unserialization of singleton
     * 
     * @throws \RuntimeException
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}
