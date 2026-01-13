<?php
/**
 * Session.php - Secure Session Handler for SafeShift EHR
 * 
 * Provides secure session management with features:
 * - CSRF token generation and validation
 * - Session fingerprinting
 * - Timeout management
 * - Secure cookie parameters (HttpOnly, Secure, SameSite)
 * - Session regeneration on privilege changes
 * 
 * @package    SafeShift\Model\Core
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Core;

use Model\Config\AppConfig;

/**
 * Secure session handler
 * 
 * Implements secure session management with HIPAA-compliant
 * security features and protection mechanisms.
 */
final class Session
{
    /** @var AppConfig Configuration instance */
    private AppConfig $config;
    
    /** @var array<string, mixed> Session configuration */
    private array $sessionConfig;
    
    /** @var bool Session started flag */
    private bool $started = false;
    
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** Session fingerprint key */
    private const FINGERPRINT_KEY = '_session_fingerprint';
    
    /** Last activity timestamp key */
    private const LAST_ACTIVITY_KEY = '_last_activity';
    
    /** Session creation timestamp key */
    private const CREATED_KEY = '_session_created';
    
    /** CSRF token key */
    private const CSRF_KEY = '_csrf_token';
    
    /** CSRF token timestamp key */
    private const CSRF_TIMESTAMP_KEY = '_csrf_timestamp';
    
    /** Last regeneration timestamp key */
    private const REGEN_KEY = '_last_regeneration';
    
    /** User ID key */
    private const USER_ID_KEY = 'user_id';
    
    /** User role key */
    private const USER_ROLE_KEY = 'user_role';

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->config = AppConfig::getInstance();
        $this->sessionConfig = $this->config->getSessionConfig();
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
     * Start session with secure configuration
     * 
     * @return bool True if session started successfully
     */
    public function start(): bool
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return true;
        }

        // Configure session before starting
        $this->configureSession();

        // Start session
        if (!session_start()) {
            return false;
        }

        $this->started = true;

        // Initialize session if new
        if (!isset($_SESSION[self::CREATED_KEY])) {
            $this->initializeSession();
        }

        // Validate session integrity
        if (!$this->validateSession()) {
            $this->destroy();
            return false;
        }

        // Check for session timeout
        if ($this->isTimedOut()) {
            $this->destroy();
            return false;
        }

        // Update last activity
        $_SESSION[self::LAST_ACTIVITY_KEY] = time();

        // Regenerate session ID periodically
        $this->periodicRegenerate();

        return true;
    }

    /**
     * Configure session settings
     */
    private function configureSession(): void
    {
        // Set session name
        session_name($this->sessionConfig['name']);

        // Set cookie parameters
        session_set_cookie_params([
            'lifetime' => $this->sessionConfig['lifetime'],
            'path' => $this->sessionConfig['path'],
            'domain' => $this->sessionConfig['domain'],
            'secure' => $this->sessionConfig['secure'],
            'httponly' => $this->sessionConfig['httponly'],
            'samesite' => $this->sessionConfig['samesite'],
        ]);

        // Configure session handler if specified
        if (!empty($this->sessionConfig['save_path'])) {
            session_save_path($this->sessionConfig['save_path']);
        }

        // Use strict mode
        ini_set('session.use_strict_mode', '1');
        
        // Use cookies only (no URL session IDs)
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        
        // Prevent JavaScript access to session cookie
        ini_set('session.cookie_httponly', '1');
        
        // Secure entropy
        ini_set('session.entropy_length', '32');
        ini_set('session.hash_function', 'sha256');
    }

    /**
     * Initialize a new session
     */
    private function initializeSession(): void
    {
        $_SESSION[self::CREATED_KEY] = time();
        $_SESSION[self::LAST_ACTIVITY_KEY] = time();
        $_SESSION[self::FINGERPRINT_KEY] = $this->generateFingerprint();
        $_SESSION[self::REGEN_KEY] = time();
        
        // Generate initial CSRF token
        $this->regenerateCsrfToken();
    }

    /**
     * Validate session integrity
     * 
     * @return bool True if session is valid
     */
    private function validateSession(): bool
    {
        // Check fingerprint
        if (!isset($_SESSION[self::FINGERPRINT_KEY])) {
            return false;
        }

        $currentFingerprint = $this->generateFingerprint();
        
        if (!hash_equals($_SESSION[self::FINGERPRINT_KEY], $currentFingerprint)) {
            // Potential session hijacking attempt
            $this->logSecurityEvent('Session fingerprint mismatch');
            return false;
        }

        return true;
    }

    /**
     * Check if session has timed out
     * 
     * @return bool True if session timed out
     */
    private function isTimedOut(): bool
    {
        if (!isset($_SESSION[self::LAST_ACTIVITY_KEY])) {
            return true;
        }

        $idleTime = time() - $_SESSION[self::LAST_ACTIVITY_KEY];
        
        if ($idleTime > $this->sessionConfig['idle_timeout']) {
            $this->logSecurityEvent('Session idle timeout');
            return true;
        }

        // Check absolute session lifetime
        if (isset($_SESSION[self::CREATED_KEY])) {
            $sessionAge = time() - $_SESSION[self::CREATED_KEY];
            
            if ($sessionAge > $this->sessionConfig['lifetime']) {
                $this->logSecurityEvent('Session lifetime exceeded');
                return true;
            }
        }

        return false;
    }

    /**
     * Periodically regenerate session ID
     */
    private function periodicRegenerate(): void
    {
        if (!isset($_SESSION[self::REGEN_KEY])) {
            $_SESSION[self::REGEN_KEY] = time();
            return;
        }

        $timeSinceRegen = time() - $_SESSION[self::REGEN_KEY];
        
        if ($timeSinceRegen > $this->sessionConfig['regenerate_interval']) {
            $this->regenerate();
        }
    }

    /**
     * Regenerate session ID
     * 
     * @param bool $deleteOldSession Whether to delete old session data
     * @return bool
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        if (!$this->started) {
            return false;
        }

        if (session_regenerate_id($deleteOldSession)) {
            $_SESSION[self::REGEN_KEY] = time();
            $_SESSION[self::FINGERPRINT_KEY] = $this->generateFingerprint();
            return true;
        }

        return false;
    }

    /**
     * Generate session fingerprint
     * 
     * @return string Fingerprint hash
     */
    private function generateFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            // Note: Do NOT include IP address as it may change for mobile users
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Generate CSRF token
     * 
     * @return string CSRF token
     */
    public function generateCsrfToken(): string
    {
        if (!$this->started) {
            $this->start();
        }

        // Return existing token if still valid
        if ($this->isCsrfTokenValid()) {
            return $_SESSION[self::CSRF_KEY];
        }

        return $this->regenerateCsrfToken();
    }

    /**
     * Regenerate CSRF token
     * 
     * @return string New CSRF token
     */
    public function regenerateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_KEY] = $token;
        $_SESSION[self::CSRF_TIMESTAMP_KEY] = time();
        
        return $token;
    }

    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool True if token is valid
     */
    public function validateCsrfToken(string $token): bool
    {
        if (!$this->started) {
            return false;
        }

        if (!isset($_SESSION[self::CSRF_KEY])) {
            return false;
        }

        // Check token expiration
        if (!$this->isCsrfTokenValid()) {
            $this->logSecurityEvent('CSRF token expired');
            return false;
        }

        // Use timing-safe comparison
        if (!hash_equals($_SESSION[self::CSRF_KEY], $token)) {
            $this->logSecurityEvent('CSRF token mismatch');
            return false;
        }

        return true;
    }

    /**
     * Check if CSRF token is still valid
     * 
     * @return bool
     */
    private function isCsrfTokenValid(): bool
    {
        if (!isset($_SESSION[self::CSRF_KEY]) || !isset($_SESSION[self::CSRF_TIMESTAMP_KEY])) {
            return false;
        }

        $securityConfig = $this->config->getSecurityConfig();
        $tokenAge = time() - $_SESSION[self::CSRF_TIMESTAMP_KEY];
        
        return $tokenAge < $securityConfig['csrf_token_lifetime'];
    }

    /**
     * Set session value
     * 
     * @param string $key Session key
     * @param mixed $value Session value
     */
    public function set(string $key, mixed $value): void
    {
        if (!$this->started) {
            $this->start();
        }
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->started) {
            $this->start();
        }
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     * 
     * @param string $key Session key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (!$this->started) {
            $this->start();
        }
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     * 
     * @param string $key Session key
     */
    public function remove(string $key): void
    {
        if (!$this->started) {
            return;
        }
        unset($_SESSION[$key]);
    }

    /**
     * Set authenticated user
     * 
     * @param string $userId User ID
     * @param string $role User role
     */
    public function setUser(string $userId, string $role): void
    {
        // Regenerate session on privilege change
        $this->regenerate();
        
        $this->set(self::USER_ID_KEY, $userId);
        $this->set(self::USER_ROLE_KEY, $role);
        
        // Regenerate CSRF token on login
        $this->regenerateCsrfToken();
    }

    /**
     * Get authenticated user ID
     * 
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $this->get(self::USER_ID_KEY);
    }

    /**
     * Get authenticated user role
     * 
     * @return string|null
     */
    public function getUserRole(): ?string
    {
        return $this->get(self::USER_ROLE_KEY);
    }

    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->getUserId() !== null;
    }

    /**
     * Clear user session (logout)
     */
    public function clearUser(): void
    {
        $this->remove(self::USER_ID_KEY);
        $this->remove(self::USER_ROLE_KEY);
        
        // Regenerate session on logout
        $this->regenerate();
        $this->regenerateCsrfToken();
    }

    /**
     * Destroy session completely
     */
    public function destroy(): void
    {
        if (!$this->started && session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // Clear session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy session
        session_destroy();
        $this->started = false;
    }

    /**
     * Get session ID
     * 
     * @return string
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Flash message - store for next request only
     * 
     * @param string $key Message key
     * @param mixed $value Message value
     */
    public function flash(string $key, mixed $value): void
    {
        if (!$this->started) {
            $this->start();
        }
        
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get and remove flash message
     * 
     * @param string $key Message key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        if (!$this->started) {
            $this->start();
        }
        
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        
        return $default;
    }

    /**
     * Log security event
     * 
     * @param string $message Event message
     */
    private function logSecurityEvent(string $message): void
    {
        $context = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id(),
            'user_id' => $_SESSION[self::USER_ID_KEY] ?? 'anonymous',
        ];

        error_log(sprintf(
            '[Session Security] %s | Context: %s',
            $message,
            json_encode($context)
        ));
    }

    /**
     * Get time until session expires
     * 
     * @return int Seconds until expiration (-1 if expired or not started)
     */
    public function getTimeUntilExpiry(): int
    {
        if (!$this->started || !isset($_SESSION[self::LAST_ACTIVITY_KEY])) {
            return -1;
        }

        $idleTime = time() - $_SESSION[self::LAST_ACTIVITY_KEY];
        $remaining = $this->sessionConfig['idle_timeout'] - $idleTime;
        
        return max(-1, $remaining);
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
