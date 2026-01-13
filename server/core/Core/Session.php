<?php
/**
 * Session Management Class
 * 
 * Provides secure session handling with CSRF protection
 */

namespace App\Core;

class Session
{
    private static bool $initialized = false;
    
    /**
     * Initialize session with secure settings
     */
    public static function init(): void
    {
        if (self::$initialized || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Session save path
        $sessionPath = dirname(__DIR__, 2) . '/sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0700, true);
        }
        
        // Determine if we should use secure cookies
        // On localhost/development HTTP, secure cookies won't work
        $isSecure = self::shouldUseSecureCookies();
        
        // Configure session before starting
        ini_set('session.save_path', $sessionPath);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $isSecure ? '1' : '0');
        ini_set('session.cookie_samesite', $isSecure ? 'Strict' : 'Lax');
        ini_set('session.gc_maxlifetime', defined('SESSION_LIFETIME') ? SESSION_LIFETIME : '3600');
        ini_set('session.cookie_lifetime', '0'); // Session cookie
        
        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => defined('SESSION_DOMAIN') ? SESSION_DOMAIN : '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => $isSecure ? 'Strict' : 'Lax'
        ]);
        
        // Start session
        session_start();
        
        // Initialize CSRF token if not exists
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        
        self::$initialized = true;
    }
    
    /**
     * Determine if secure cookies should be used
     *
     * Returns false for localhost/development HTTP environments
     *
     * @return bool
     */
    private static function shouldUseSecureCookies(): bool
    {
        // If explicitly set via constant, use that
        if (defined('SESSION_SECURE')) {
            return (bool) SESSION_SECURE;
        }
        
        // Check if HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        
        // If on HTTPS, use secure cookies
        if ($isHttps) {
            return true;
        }
        
        // Check if localhost/development
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'])
            || strpos($host, 'localhost:') === 0
            || strpos($host, '127.0.0.1:') === 0;
        
        // On localhost HTTP, don't use secure cookies (they won't work)
        if ($isLocalhost) {
            return false;
        }
        
        // Default to secure for non-localhost HTTP (though this is not recommended)
        return false;
    }
    
    /**
     * Set session value
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        self::init();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::init();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session key exists
     * 
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::init();
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session value
     * 
     * @param string $key
     */
    public static function remove(string $key): void
    {
        self::init();
        unset($_SESSION[$key]);
    }
    
    /**
     * Clear all session data
     */
    public static function clear(): void
    {
        self::init();
        $_SESSION = [];
    }
    
    /**
     * Regenerate session ID
     * 
     * @param bool $deleteOldSession
     */
    public static function regenerate(bool $deleteOldSession = true): void
    {
        self::init();
        session_regenerate_id($deleteOldSession);
    }
    
    /**
     * Get CSRF token
     * 
     * @return string
     */
    public static function getCsrfToken(): string
    {
        self::init();
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token
     * @return bool
     */
    public static function validateCsrfToken(string $token): bool
    {
        self::init();
        if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Set user session data
     * 
     * @param array $userData
     */
    public static function setUser(array $userData): void
    {
        self::set('user', [
            'user_id' => $userData['user_id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'logged_in_at' => time()
        ]);
        
        // Clear any pending 2FA
        self::remove('pending_2fa');
        
        // Regenerate session ID for security
        self::regenerate();
    }
    
    /**
     * Get current user data
     * 
     * @return array|null
     */
    public static function getUser(): ?array
    {
        return self::get('user');
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        $user = self::getUser();
        return !empty($user['user_id']);
    }
    
    /**
     * Set pending 2FA data
     * 
     * @param array $userData
     */
    public static function setPending2FA(array $userData): void
    {
        self::set('pending_2fa', [
            'user_id' => $userData['user_id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'expires' => time() + 600 // 10 minutes
        ]);
    }
    
    /**
     * Get pending 2FA data
     *
     * @return array|null
     */
    public static function getPending2FA(): ?array
    {
        $pending = self::get('pending_2fa');
        
        // Check if expired
        if ($pending && isset($pending['expires']) && $pending['expires'] < time()) {
            self::remove('pending_2fa');
            return null;
        }
        
        return $pending;
    }
    
    /**
     * Clear pending 2FA data
     * Used when 2FA expires or is cancelled
     */
    public static function clearPending2FA(): void
    {
        self::remove('pending_2fa');
    }
    
    /**
     * Destroy session completely
     */
    public static function destroy(): void
    {
        self::init();
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        self::$initialized = false;
    }
    
    /**
     * Get session ID
     * 
     * @return string
     */
    public static function getId(): string
    {
        self::init();
        return session_id();
    }
    
    /**
     * Flash message support
     * 
     * @param string $key
     * @param string $message
     */
    public static function flash(string $key, string $message): void
    {
        self::set('flash_' . $key, $message);
    }
    
    /**
     * Get and remove flash message
     * 
     * @param string $key
     * @return string|null
     */
    public static function getFlash(string $key): ?string
    {
        $message = self::get('flash_' . $key);
        self::remove('flash_' . $key);
        return $message;
    }
    
    /**
     * Check session timeout
     * 
     * @param int $timeout Timeout in seconds
     * @return bool Returns true if session is still valid
     */
    public static function checkTimeout(int $timeout = 1200): bool
    {
        $user = self::getUser();
        if (!$user || !isset($user['logged_in_at'])) {
            return false;
        }
        
        if (time() - $user['logged_in_at'] > $timeout) {
            self::destroy();
            return false;
        }
        
        return true;
    }
}