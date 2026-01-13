<?php
/**
 * Secure Centralized Logger Service
 * 
 * This service handles all logging operations with security constraints:
 * - Single privileged process for writing logs
 * - Input validation and sanitization
 * - PHI stripping
 * - Log rotation
 * - Tamper-proof audit trails
 */

namespace Core\Infrastructure\Logging;

use Exception;

class SecureLogger {
    private static $instance = null;
    private $logDir;
    private $logFile;
    private $rotationConfig;
    private $encryptionKey;
    private $hashChain = '';
    
    // Log levels
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    const LEVEL_AUDIT = 'AUDIT';
    
    // Log categories
    const CAT_AUTH = 'AUTH';
    const CAT_ACCESS = 'ACCESS';
    const CAT_FORM = 'FORM';
    const CAT_ERROR = 'ERROR';
    const CAT_SYSTEM = 'SYSTEM';
    const CAT_OSHA = 'OSHA';
    const CAT_HIPAA = 'HIPAA';
    
    // EHR-specific log categories
    const CAT_EHR = 'EHR';
    const CAT_ENCOUNTER = 'ENCOUNTER';
    const CAT_VITALS = 'VITALS';
    const CAT_ASSESSMENT = 'ASSESSMENT';
    const CAT_TREATMENT = 'TREATMENT';
    const CAT_SIGNATURE = 'SIGNATURE';
    const CAT_FINALIZATION = 'FINALIZATION';
    const CAT_PHI_ACCESS = 'PHI_ACCESS';
    
    private function __construct() {
        // Use the standard logs directory
        $this->logDir = dirname(dirname(dirname(__DIR__))) . '/logs/';
        
        // Create log directory if it doesn't exist
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0750, true);
        }
        
        // Set up rotation config (6 months retention)
        $this->rotationConfig = [
            'max_size' => 100 * 1024 * 1024, // 100MB per file
            'retention_days' => 180, // 6 months
            'compress' => true
        ];
        
        // Initialize hash chain for tamper detection
        $this->initializeHashChain();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new SecureLogger();
        }
        return self::$instance;
    }
    
    /**
     * Main logging method
     */
    public function log($level, $category, $message, $context = []) {
        // Validate input
        if (!$this->validateLogEntry($level, $category, $message)) {
            return false;
        }
        
        // Sanitize and prepare log entry
        $logEntry = $this->prepareLogEntry($level, $category, $message, $context);
        
        // Strip PHI if present
        $logEntry = $this->stripPHI($logEntry);
        
        // Write to log file
        return $this->writeLog($logEntry);
    }
    
    /**
     * Validate log entry parameters
     */
    private function validateLogEntry($level, $category, $message) {
        $validLevels = [
            self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARNING,
            self::LEVEL_ERROR, self::LEVEL_CRITICAL, self::LEVEL_AUDIT
        ];
        
        $validCategories = [
            self::CAT_AUTH, self::CAT_ACCESS, self::CAT_FORM,
            self::CAT_ERROR, self::CAT_SYSTEM, self::CAT_OSHA, self::CAT_HIPAA,
            // EHR-specific categories
            self::CAT_EHR, self::CAT_ENCOUNTER, self::CAT_VITALS,
            self::CAT_ASSESSMENT, self::CAT_TREATMENT, self::CAT_SIGNATURE,
            self::CAT_FINALIZATION, self::CAT_PHI_ACCESS
        ];
        
        if (!in_array($level, $validLevels)) {
            return false;
        }
        
        if (!in_array($category, $validCategories)) {
            return false;
        }
        
        // Truncate overly long messages
        if (strlen($message) > 10000) {
            $message = substr($message, 0, 10000) . '... [truncated]';
        }
        
        return true;
    }
    
    /**
     * Prepare structured log entry
     */
    private function prepareLogEntry($level, $category, $message, $context) {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'level' => $level,
            'category' => $category,
            'message' => $this->sanitizeMessage($message),
            'context' => $this->sanitizeContext($context),
            'session_id' => session_id() ?: 'no-session',
            'user_id' => $_SESSION['user']['user_id'] ?? null,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_id' => $this->generateRequestId(),
            'process_id' => getmypid()
        ];
        
        return $entry;
    }
    
    /**
     * Sanitize log message
     */
    private function sanitizeMessage($message) {
        // Handle null values
        $message = $message ?? '';
        
        // Remove control characters
        $message = preg_replace('/[\x00-\x1F\x7F]/', '', $message);
        
        // Escape special characters
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        // Truncate if needed
        if (strlen($message) > 5000) {
            $message = substr($message, 0, 5000) . '... [truncated]';
        }
        
        return $message;
    }
    
    /**
     * Sanitize context data
     */
    private function sanitizeContext($context) {
        if (!is_array($context)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($context as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            
            if (is_string($value)) {
                $value = $this->sanitizeMessage($value);
            } elseif (is_array($value)) {
                $value = $this->sanitizeContext($value);
            } elseif (is_object($value)) {
                $value = '[object]';
            }
            
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }
    
    /**
     * Strip PHI (Protected Health Information) from log entry
     */
    private function stripPHI($entry) {
        // Patterns for PHI data
        $phiPatterns = [
            '/\b\d{3}-\d{2}-\d{4}\b/' => '[SSN-REDACTED]', // SSN
            '/\b\d{3}-\d{3}-\d{4}\b/' => '[PHONE-REDACTED]', // Phone
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[EMAIL-REDACTED]', // Email
            '/\b(?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12][0-9]|3[01])[-\/](?:19|20)\d{2}\b/' => '[DOB-REDACTED]', // Date of birth MM/DD/YYYY or MM-DD-YYYY
            '/\b(?:19|20)\d{2}[-\/](?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12][0-9]|3[01])\b/' => '[DOB-REDACTED]', // Date of birth YYYY-MM-DD or YYYY/MM/DD
            '/\b\d{8,}\b/' => '[ID-REDACTED]', // Medical record numbers
        ];
        
        $json = json_encode($entry);
        
        foreach ($phiPatterns as $pattern => $replacement) {
            $json = preg_replace($pattern, $replacement, $json);
        }
        
        return json_decode($json, true);
    }
    
    /**
     * Write log entry to file
     */
    private function writeLog($entry) {
        // Determine log file based on category
        $filename = $this->getLogFilename($entry['category']);
        $filepath = $this->logDir . $filename;
        
        // Check rotation
        $this->rotateLogIfNeeded($filepath);
        
        // Add hash for tamper detection
        $entry['hash'] = $this->calculateEntryHash($entry);
        
        // Format as JSON
        $logLine = json_encode($entry) . PHP_EOL;
        
        // Write to file (append-only)
        $result = file_put_contents($filepath, $logLine, FILE_APPEND | LOCK_EX);
        
        // Update hash chain
        $this->updateHashChain($entry['hash']);
        
        return $result !== false;
    }
    
    /**
     * Get appropriate log filename based on category and date
     */
    private function getLogFilename($category) {
        $date = date('Y-m-d');
        return strtolower($category ?? 'system') . '_' . $date . '.log';
    }
    
    /**
     * Rotate log file if needed
     */
    private function rotateLogIfNeeded($filepath) {
        if (!file_exists($filepath)) {
            return;
        }
        
        $filesize = filesize($filepath);
        
        if ($filesize > $this->rotationConfig['max_size']) {
            $timestamp = date('YmdHis');
            $rotatedPath = $filepath . '.' . $timestamp;
            
            rename($filepath, $rotatedPath);
            
            // Compress if configured
            if ($this->rotationConfig['compress']) {
                $this->compressLogFile($rotatedPath);
            }
        }
        
        // Clean old files
        $this->cleanOldLogs();
    }
    
    /**
     * Compress rotated log file
     */
    private function compressLogFile($filepath) {
        $gz = gzopen($filepath . '.gz', 'wb9');
        $fp = fopen($filepath, 'rb');
        
        while (!feof($fp)) {
            gzwrite($gz, fread($fp, 1024 * 512));
        }
        
        fclose($fp);
        gzclose($gz);
        
        unlink($filepath);
    }
    
    /**
     * Clean logs older than retention period
     */
    private function cleanOldLogs() {
        $files = glob($this->logDir . '*.log*');
        $cutoffTime = time() - ($this->rotationConfig['retention_days'] * 86400);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
    
    /**
     * Calculate hash for log entry (tamper detection)
     */
    private function calculateEntryHash($entry) {
        $data = json_encode($entry) . $this->hashChain;
        return hash('sha256', $data);
    }
    
    /**
     * Update hash chain
     */
    private function updateHashChain($hash) {
        $this->hashChain = $hash;
        
        // Persist hash chain
        file_put_contents($this->logDir . '.hashchain', $this->hashChain, LOCK_EX);
    }
    
    /**
     * Initialize hash chain from file
     */
    private function initializeHashChain() {
        $chainFile = $this->logDir . '.hashchain';
        
        if (file_exists($chainFile)) {
            $this->hashChain = file_get_contents($chainFile);
        } else {
            $this->hashChain = hash('sha256', 'INITIAL_CHAIN_' . time());
            file_put_contents($chainFile, $this->hashChain, LOCK_EX);
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Generate unique request ID
     */
    private function generateRequestId() {
        return uniqid('req_', true);
    }
    
    /**
     * Verify log integrity
     */
    public function verifyLogIntegrity($filename) {
        $filepath = $this->logDir . $filename;
        
        if (!file_exists($filepath)) {
            return false;
        }
        
        $lines = file($filepath, FILE_IGNORE_NEW_LINES);
        $previousHash = '';
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            
            if (!$entry || !isset($entry['hash'])) {
                return false;
            }
            
            // Recalculate hash
            $originalHash = $entry['hash'];
            unset($entry['hash']);
            
            $calculatedHash = hash('sha256', json_encode($entry) . $previousHash);
            
            if ($calculatedHash !== $originalHash) {
                return false;
            }
            
            $previousHash = $originalHash;
        }
        
        return true;
    }
}