<?php
namespace Core\Services;

use PDO;
use Exception;

/**
 * Base Service Class
 * Provides common functionality for all service classes
 */
abstract class BaseService
{
    protected $db;
    protected $logger;
    
    public function __construct()
    {
        // Include config if not already loaded
        if (!defined('CONFIG_LOADED')) {
            require_once __DIR__ . '/../../includes/config.php';
        }
        
        $this->db = $this->getDatabase();
        
        // Initialize logger if available
        if (class_exists('App\Core\Logger')) {
            $this->logger = new \App\Core\Logger();
        }
    }
    
    /**
     * Get database connection
     * @return PDO
     */
    protected function getDatabase()
    {
        try {
            // Use constants from config.php
            $host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $dbname = defined('DB_NAME') ? DB_NAME : 'safeshift_ehr_001_0';
            $username = defined('DB_USER') ? DB_USER : 'safeshift_admin';
            $password = defined('DB_PASS') ? DB_PASS : '';
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            
            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            return new PDO($dsn, $username, $password, $options);
            
        } catch (Exception $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Unable to connect to database");
        }
    }
    
    /**
     * Log message (general)
     * @param string $message
     * @param array $context
     */
    protected function log($message, $context = [])
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        } else {
            $this->logInfo($message . ' ' . json_encode($context));
        }
    }
    
    /**
     * Log error
     * @param string $message  
     * @param array $context
     */
    protected function logError($message, $context = [])
    {
        $logFile = __DIR__ . '/../../logs/error_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] " . get_class($this) . ": $message";
        if (!empty($context)) {
            $logMessage .= " " . json_encode($context);
        }
        $logMessage .= "\n";
        
        // Create logs directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        error_log($logMessage, 3, $logFile);
        
        // Also use logger if available
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
    
    /**
     * Log general information
     * @param string $message
     */
    protected function logInfo($message)
    {
        $logFile = __DIR__ . '/../../logs/general_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] " . get_class($this) . ": $message\n";
        
        // Create logs directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        error_log($logMessage, 3, $logFile);
    }
    
    /**
     * Generate UUID
     * @return string
     */
    protected function generateUuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Validate UUID format
     * @param string $uuid
     * @return bool
     */
    protected function isValidUuid($uuid)
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $uuid) === 1;
    }
    
    /**
     * Sanitize input
     * @param mixed $input
     * @param string $type
     * @return mixed
     */
    protected function sanitizeInput($input, $type = 'string')
    {
        if ($input === null) {
            return null;
        }
        
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
                
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
                
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
                
            case 'html':
                // Allow basic HTML tags
                $allowed_tags = '<p><br><strong><em><ul><ol><li><a>';
                return strip_tags($input, $allowed_tags);
                
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Check if user has required role
     * @param string|array $requiredRoles
     * @return bool
     */
    protected function hasRole($requiredRoles)
    {
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        $userRole = $_SESSION['role'];
        
        if (is_string($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }
        
        return in_array($userRole, $requiredRoles);
    }
    
    /**
     * Get current user ID
     * @return string|null
     */
    protected function getCurrentUserId()
    {
        return $_SESSION['id'] ?? null;
    }
    
    /**
     * Get current user role
     * @return string|null
     */
    protected function getCurrentUserRole()
    {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Begin database transaction
     */
    protected function beginTransaction()
    {
        $this->db->beginTransaction();
    }
    
    /**
     * Commit database transaction
     */
    protected function commit()
    {
        $this->db->commit();
    }
    
    /**
     * Rollback database transaction
     */
    protected function rollback()
    {
        $this->db->rollBack();
    }
    
    /**
     * Format date for database
     * @param string $date
     * @return string
     */
    protected function formatDateForDb($date)
    {
        if (empty($date)) {
            return null;
        }
        return date('Y-m-d', strtotime($date));
    }
    
    /**
     * Format datetime for database
     * @param string $datetime
     * @return string
     */
    protected function formatDatetimeForDb($datetime)
    {
        if (empty($datetime)) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($datetime));
    }
    
    /**
     * Format date for display
     * @param string $date
     * @return string
     */
    protected function formatDateForDisplay($date)
    {
        if (empty($date)) {
            return '';
        }
        return date('m/d/Y', strtotime($date));
    }
    
    /**
     * Format datetime for display
     * @param string $datetime
     * @return string
     */
    protected function formatDatetimeForDisplay($datetime)
    {
        if (empty($datetime)) {
            return '';
        }
        return date('m/d/Y h:i A', strtotime($datetime));
    }
    
    /**
     * Get pagination SQL
     * @param int $page
     * @param int $perPage
     * @return array
     */
    protected function getPaginationSql($page = 1, $perPage = 50)
    {
        $page = max(1, (int)$page);
        $perPage = max(1, min(100, (int)$perPage)); // Max 100 per page
        $offset = ($page - 1) * $perPage;
        
        return [
            'limit' => $perPage,
            'offset' => $offset,
            'page' => $page,
            'perPage' => $perPage
        ];
    }
    
    /**
     * Calculate total pages
     * @param int $totalCount
     * @param int $perPage
     * @return int
     */
    protected function calculateTotalPages($totalCount, $perPage)
    {
        return (int)ceil($totalCount / $perPage);
    }
    
    /**
     * Get standardized API response (now called formatResponse for consistency)
     *
     * NOTE: This method returns an array to be used by higher-level API handlers.
     * HTTP response codes should be set by the API layer (e.g., ApiResponse::send()),
     * not by the service layer, to avoid double-setting and conflicts.
     *
     * @param bool $success
     * @param mixed $data
     * @param string $message
     * @return array
     */
    protected function formatResponse(bool $success, $data = null, string $message = ''): array
    {
        // NOTE: Do NOT call http_response_code() here - that's the API layer's job
        
        $response = [
            'success' => $success,
            'timestamp' => date('c')
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return $response;
    }
    
    /**
     * Alias for formatResponse (backward compatibility)
     * @param bool $success
     * @param mixed $data
     * @param string $message
     * @return array
     */
    protected function apiResponse(bool $success, $data = null, string $message = ''): array
    {
        return $this->formatResponse($success, $data, $message);
    }
    
    /**
     * Validate required fields
     * @param array $data
     * @param array $requiredFields
     * @return array Missing fields
     */
    protected function validateRequiredFields($data, $requiredFields)
    {
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missingFields[] = $field;
            }
        }
        
        return $missingFields;
    }
    
    /**
     * Get database instance (for services that need direct access)
     * @return PDO
     */
    public function getDb()
    {
        return $this->db;
    }
    
    /**
     * Get config value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig($key, $default = null)
    {
        return defined($key) ? constant($key) : $default;
    }
    
    /**
     * Check if in development mode
     * @return bool
     */
    protected function isDevelopment()
    {
        return $this->getConfig('ENVIRONMENT', 'production') === 'development';
    }
}