<?php
/**
 * Database Connection Manager
 * 
 * Provides centralized database connection management using PDO
 * Implements singleton pattern to ensure single connection instance
 */

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    
    /**
     * Get PDO connection instance
     * 
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::createConnection();
        }
        
        return self::$instance;
    }
    
    /**
     * Create new database connection
     * 
     * @throws PDOException
     */
    private static function createConnection(): void
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_PORT ?? '3306',
                DB_NAME,
                DB_CHARSET ?? 'utf8mb4'
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . (DB_CHARSET ?? 'utf8mb4') . " COLLATE " . (DB_CHARSET ?? 'utf8mb4') . "_unicode_ci",
                PDO::ATTR_PERSISTENT => false
            ];
            
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Sync timezone with PHP
            self::syncTimezone();
            
        } catch (PDOException $e) {
            // Log error without exposing sensitive information
            error_log("Database connection failed: " . $e->getMessage());
            
            // In production, show generic error
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                throw new PDOException("Database connection failed. Please try again later.");
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * Sync MySQL timezone with PHP timezone
     */
    private static function syncTimezone(): void
    {
        try {
            $dt = new \DateTime('now', new \DateTimeZone(date_default_timezone_get()));
            $offset = $dt->format('P'); // Format: +00:00 or -06:00
            
            self::$instance->exec("SET time_zone = '$offset'");
            
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log("[DATABASE] MySQL session timezone set to: $offset (matching PHP: " . date_default_timezone_get() . ")");
            }
        } catch (\Exception $e) {
            error_log("[DATABASE] Warning: Could not sync MySQL timezone: " . $e->getMessage());
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
     * Close database connection
     */
    public static function close(): void
    {
        self::$instance = null;
    }
    
    /**
     * Begin transaction
     * 
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     * 
     * @return bool
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     * 
     * @return bool
     */
    public static function rollback(): bool
    {
        return self::getConnection()->rollBack();
    }
    
    /**
     * Get last insert ID
     * 
     * @return string|false
     */
    public static function lastInsertId()
    {
        return self::getConnection()->lastInsertId();
    }
}