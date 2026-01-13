<?php
/**
 * Database Connection Class
 * Located at: core/Infrastructure/Database/Connection.php
 *
 * Singleton pattern database connection manager
 * Provides PDO instance with proper configuration and error handling
 */

namespace Core\Infrastructure\Database;

use PDO;
use PDOException;
use DateTime;
use DateTimeZone;
use Exception;

class DatabaseConnection
{
    /**
     * @var PDO|null The singleton PDO instance
     */
    private static ?PDO $instance = null;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        // Prevent direct instantiation
    }
    
    /**
     * Private clone to prevent cloning of the instance
     */
    private function __clone()
    {
        // Prevent cloning
    }
    
    /**
     * Get the singleton PDO instance
     * 
     * @return PDO
     * @throws PDOException
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::createInstance();
        }
        
        return self::$instance;
    }
    
    /**
     * Create the PDO instance with proper configuration
     * 
     * @throws PDOException
     */
    private static function createInstance(): void
    {
        try {
            // Validate required constants
            self::validateConfiguration();
            
            // Build DSN
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            // PDO options matching the original configuration
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci",
                PDO::ATTR_PERSISTENT => false
            ];
            
            // Create PDO instance
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Synchronize timezone with PHP
            self::synchronizeTimezone();
            
            // Log successful connection in development
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log('[DatabaseConnection] Successfully connected to database');
            }
            
        } catch (PDOException $e) {
            // Log the error without exposing sensitive information
            if (defined('LOG_ERRORS') && LOG_ERRORS) {
                error_log('[DatabaseConnection] Connection failed: ' . $e->getMessage());
            }
            
            // In production, show generic error
            if (defined('APP_ENV') && APP_ENV === 'production') {
                throw new PDOException("Database connection failed. Please try again later.");
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * Validate database configuration constants
     * 
     * @throws Exception
     */
    private static function validateConfiguration(): void
    {
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'];
        $missing = [];
        
        foreach ($required as $constant) {
            if (!defined($constant)) {
                $missing[] = $constant;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception(
                'Missing required database configuration: ' . implode(', ', $missing) . 
                '. Please check config.php'
            );
        }
    }
    
    /**
     * Synchronize MySQL session timezone with PHP timezone
     */
    private static function synchronizeTimezone(): void
    {
        try {
            // Get PHP timezone offset
            $dt = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
            $offset = $dt->format('P'); // Format: +00:00 or -06:00
            
            // Set MySQL session timezone to match PHP
            self::$instance->exec("SET time_zone = '$offset'");
            
            // Log successful sync in development
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log(
                    "[DatabaseConnection] MySQL session timezone set to: $offset " .
                    "(matching PHP: " . date_default_timezone_get() . ")"
                );
            }
            
        } catch (Exception $e) {
            // Log error but don't fail - the app can still work
            error_log("[DatabaseConnection] Warning: Could not sync MySQL timezone: " . $e->getMessage());
        }
    }
    
    /**
     * Check if database connection is active
     * 
     * @return bool
     */
    public static function isConnected(): bool
    {
        try {
            if (self::$instance === null) {
                return false;
            }
            
            self::$instance->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get last insert ID
     * 
     * @return string|false
     */
    public static function lastInsertId()
    {
        try {
            return self::getInstance()->lastInsertId();
        } catch (PDOException $e) {
            error_log('[DatabaseConnection] Failed to get last insert ID: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset the connection (useful for testing)
     * Note: This should only be used in testing environments
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}