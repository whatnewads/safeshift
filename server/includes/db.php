<?php
// includes/db.php
namespace App\db;

use PDO;
use PDOException;

/**
 * Get PDO connection instance
 * @return PDO
 * @throws PDOException
 */
function pdo(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // Log the error without exposing sensitive information
            if (defined('LOG_ERRORS') && LOG_ERRORS) {
                error_log("Database connection failed: " . $e->getMessage());
            }
            
            // In production, show generic error
            if (defined('APP_ENV') && APP_ENV === 'production') {
                throw new PDOException("Database connection failed. Please try again later.");
            } else {
                throw $e;
            }
        }
    }
    
    return $pdo;
}

/**
 * Check database connection
 * @return bool
 */
function checkConnection(): bool {
    try {
        $pdo = pdo();
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get last insert ID
 * @return string|false
 */
function lastInsertId() {
    try {
        return pdo()->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}
?>