<?php
/**
 * Mail Configuration
 * 
 * Loads SES SMTP settings from environment variables.
 * NEVER hardcode credentials in this file.
 * 
 * Required environment variables:
 * - SES_SMTP_HOST (default: email-smtp.us-east-1.amazonaws.com)
 * - SES_SMTP_PORT (default: 587)
 * - SES_SMTP_USER
 * - SES_SMTP_PASS
 * - SES_SMTP_FROM_EMAIL (must be verified in SES)
 * - SES_SMTP_FROM_NAME
 * - APP_BASE_URL (for email links)
 */

declare(strict_types=1);

namespace Api\Config;

class MailConfig
{
    private static ?array $config = null;
    
    /**
     * Get mail configuration from environment variables
     * 
     * @return array Configuration array
     * @throws \RuntimeException If required variables are missing
     */
    public static function get(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        
        // Load from .env if available
        self::loadEnvFile();
        
        // Build config from environment
        self::$config = [
            'host'      => self::getEnv('SES_SMTP_HOST', 'email-smtp.us-east-1.amazonaws.com'),
            'port'      => (int) self::getEnv('SES_SMTP_PORT', '587'),
            'user'      => self::getEnv('SES_SMTP_USER'),
            'pass'      => self::getEnv('SES_SMTP_PASS'),
            'from_email'=> self::getEnv('SES_SMTP_FROM_EMAIL'),
            'from_name' => self::getEnv('SES_SMTP_FROM_NAME', 'First Response Occupational'),
            'base_url'  => self::getEnv('APP_BASE_URL', 'https://1stresponse.safeshift.ai'),
            'encryption'=> 'tls', // STARTTLS on port 587
            'timeout'   => 10, // Connection timeout in seconds
        ];
        
        // Validate required fields
        self::validate();
        
        return self::$config;
    }
    
    /**
     * Get a single config value
     * 
     * @param string $key Config key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function getValue(string $key, $default = null)
    {
        $config = self::get();
        return $config[$key] ?? $default;
    }
    
    /**
     * Check if mail is configured
     * 
     * @return bool
     */
    public static function isConfigured(): bool
    {
        try {
            self::get();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }
    
    /**
     * Get environment variable with optional default
     * 
     * @param string $key Environment variable name
     * @param string|null $default Default value
     * @return string|null
     */
    private static function getEnv(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        
        if ($value === false || $value === '') {
            // Also check $_ENV and $_SERVER for compatibility
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        
        return $value ?: $default;
    }
    
    /**
     * Load .env file if it exists
     */
    private static function loadEnvFile(): void
    {
        $envPaths = [
            dirname(__DIR__, 2) . '/.env',
            dirname(__DIR__, 2) . '/.env.local',
        ];
        
        foreach ($envPaths as $envPath) {
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    // Skip comments
                    if (strpos(trim($line), '#') === 0) {
                        continue;
                    }
                    
                    // Parse KEY=VALUE
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value, " \t\n\r\0\x0B\"'");
                        
                        // Only set if not already set
                        if (getenv($key) === false) {
                            putenv("$key=$value");
                            $_ENV[$key] = $value;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Validate required configuration
     * 
     * @throws \RuntimeException If validation fails
     */
    private static function validate(): void
    {
        $required = ['user', 'pass', 'from_email'];
        $missing = [];
        
        foreach ($required as $key) {
            if (empty(self::$config[$key])) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            $envVars = array_map(function($k) {
                return 'SES_SMTP_' . strtoupper($k);
            }, $missing);
            
            throw new \RuntimeException(
                'Missing required mail configuration. Please set environment variables: ' . 
                implode(', ', $envVars)
            );
        }
        
        // Validate email format
        if (!filter_var(self::$config['from_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException(
                'Invalid SES_SMTP_FROM_EMAIL: must be a valid email address verified in SES'
            );
        }
    }
    
    /**
     * Get debug-safe config (credentials masked)
     * 
     * @return array
     */
    public static function getDebugConfig(): array
    {
        try {
            $config = self::get();
            return [
                'host' => $config['host'],
                'port' => $config['port'],
                'user' => substr($config['user'], 0, 4) . '***',
                'pass' => '***REDACTED***',
                'from_email' => $config['from_email'],
                'from_name' => $config['from_name'],
                'base_url' => $config['base_url'],
                'configured' => true,
            ];
        } catch (\RuntimeException $e) {
            return [
                'configured' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
