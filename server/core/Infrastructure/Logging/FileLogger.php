<?php
/**
 * File-based Logger with rotation support
 * 
 * Handles file-based logging with automatic rotation, compression,
 * and cleanup of old log files.
 */

namespace Core\Infrastructure\Logging;

use Exception;

class FileLogger {
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    const LEVEL_SECURITY = 'SECURITY';
    const LEVEL_AUDIT = 'AUDIT';
    
    /**
     * @var string Log directory path
     */
    private $logDir;
    
    /**
     * @var int Maximum log file size in bytes (default 10MB)
     */
    private $maxFileSize = 10485760;
    
    /**
     * @var int Days to keep log files (default 90 days for HIPAA compliance)
     */
    private $retentionDays = 90;
    
    /**
     * @var bool Whether to compress rotated files
     */
    private $compressRotated = true;
    
    /**
     * @var array Log level hierarchy for filtering
     */
    private $levelHierarchy = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
        self::LEVEL_CRITICAL => 4,
        self::LEVEL_SECURITY => 5,
        self::LEVEL_AUDIT => 6
    ];
    
    /**
     * @var string Minimum log level to write
     */
    private $minLevel = self::LEVEL_DEBUG;
    
    /**
     * Constructor
     * 
     * @param string $logDir Log directory path
     * @param array $config Configuration options
     */
    public function __construct($logDir = null, array $config = []) {
        $this->logDir = $logDir ?: dirname(dirname(dirname(__DIR__))) . '/logs';
        
        // Apply configuration
        if (isset($config['max_file_size'])) {
            $this->maxFileSize = $config['max_file_size'];
        }
        if (isset($config['retention_days'])) {
            $this->retentionDays = $config['retention_days'];
        }
        if (isset($config['compress_rotated'])) {
            $this->compressRotated = $config['compress_rotated'];
        }
        if (isset($config['min_level'])) {
            $this->minLevel = $config['min_level'];
        }
        
        $this->ensureLogDirectory();
    }
    
    /**
     * Log a message
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @param string $channel Log channel/type
     * @return bool Success status
     */
    public function log($level, $message, array $context = [], $channel = 'general') {
        // Check if we should log this level
        if (!$this->shouldLog($level)) {
            return true;
        }
        
        try {
            $logFile = $this->getLogFile($channel);
            
            // Check if rotation is needed
            $this->rotateIfNeeded($logFile);
            
            // Prepare log entry
            $entry = $this->prepareLogEntry($level, $message, $context);
            
            // Write to file with locking
            $result = file_put_contents(
                $logFile,
                json_encode($entry) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
            
            return $result !== false;
            
        } catch (Exception $e) {
            // Last resort - log to PHP error log
            error_log('FileLogger error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log debug message
     */
    public function debug($message, array $context = [], $channel = 'general') {
        return $this->log(self::LEVEL_DEBUG, $message, $context, $channel);
    }
    
    /**
     * Log info message
     */
    public function info($message, array $context = [], $channel = 'general') {
        return $this->log(self::LEVEL_INFO, $message, $context, $channel);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, array $context = [], $channel = 'general') {
        return $this->log(self::LEVEL_WARNING, $message, $context, $channel);
    }
    
    /**
     * Log error message
     */
    public function error($message, array $context = [], $channel = 'error') {
        return $this->log(self::LEVEL_ERROR, $message, $context, $channel);
    }
    
    /**
     * Log critical message
     */
    public function critical($message, array $context = [], $channel = 'error') {
        return $this->log(self::LEVEL_CRITICAL, $message, $context, $channel);
    }
    
    /**
     * Log security event
     */
    public function security($message, array $context = [], $channel = 'security') {
        return $this->log(self::LEVEL_SECURITY, $message, $context, $channel);
    }
    
    /**
     * Log audit event
     */
    public function audit($message, array $context = [], $channel = 'audit') {
        return $this->log(self::LEVEL_AUDIT, $message, $context, $channel);
    }
    
    /**
     * Ensure log directory exists and is protected
     */
    private function ensureLogDirectory() {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0750, true);
        }
        
        // Protect with .htaccess on Apache
        $htaccessFile = $this->logDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all\n");
        }
    }
    
    /**
     * Get log file path for a channel
     * 
     * @param string $channel Log channel
     * @return string Log file path
     */
    private function getLogFile($channel) {
        $date = date('Y-m-d');
        return $this->logDir . '/' . $channel . '_' . $date . '.log';
    }
    
    /**
     * Check if a log level should be logged
     * 
     * @param string $level Log level
     * @return bool
     */
    private function shouldLog($level) {
        if (!isset($this->levelHierarchy[$level])) {
            return false;
        }
        
        return $this->levelHierarchy[$level] >= $this->levelHierarchy[$this->minLevel];
    }
    
    /**
     * Prepare log entry
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return array Log entry
     */
    private function prepareLogEntry($level, $message, array $context) {
        return [
            'timestamp' => date('Y-m-d H:i:s.u'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'session_id' => session_id() ?: null,
            'user_id' => $_SESSION['user']['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'pid' => getmypid()
        ];
    }
    
    /**
     * Rotate log file if needed
     * 
     * @param string $logFile Log file path
     */
    private function rotateIfNeeded($logFile) {
        if (!file_exists($logFile)) {
            return;
        }
        
        if (filesize($logFile) > $this->maxFileSize) {
            $this->rotateLog($logFile);
        }
    }
    
    /**
     * Rotate a log file
     * 
     * @param string $logFile Log file path
     * @return bool Success status
     */
    private function rotateLog($logFile) {
        try {
            $timestamp = date('YmdHis');
            $rotatedFile = $logFile . '.' . $timestamp;
            
            // Rename current log file
            if (rename($logFile, $rotatedFile)) {
                // Compress if enabled
                if ($this->compressRotated) {
                    $this->compressFile($rotatedFile);
                }
                
                // Clean up old logs
                $this->cleanOldLogs();
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Log rotation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Compress a file
     * 
     * @param string $file File path
     * @return bool Success status
     */
    private function compressFile($file) {
        try {
            $gz = gzopen($file . '.gz', 'w9');
            if ($gz) {
                $fp = fopen($file, 'rb');
                while (!feof($fp)) {
                    gzwrite($gz, fread($fp, 1024 * 512));
                }
                fclose($fp);
                gzclose($gz);
                
                // Remove uncompressed file
                unlink($file);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('File compression failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old log files
     */
    private function cleanOldLogs() {
        try {
            $cutoffTime = time() - ($this->retentionDays * 86400);
            
            // Get all log files
            $logFiles = glob($this->logDir . '/*.log*');
            
            foreach ($logFiles as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    unlink($file);
                }
            }
            
        } catch (Exception $e) {
            error_log('Log cleanup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get log statistics
     * 
     * @return array Statistics about log files
     */
    public function getStatistics() {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'oldest_file' => null,
            'newest_file' => null,
            'channels' => []
        ];
        
        $files = glob($this->logDir . '/*.log*');
        
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            $stats['total_files']++;
            $stats['total_size'] += filesize($file);
            
            $mtime = filemtime($file);
            if (!$stats['oldest_file'] || $mtime < filemtime($stats['oldest_file'])) {
                $stats['oldest_file'] = $file;
            }
            if (!$stats['newest_file'] || $mtime > filemtime($stats['newest_file'])) {
                $stats['newest_file'] = $file;
            }
            
            // Extract channel name
            $basename = basename($file);
            if (preg_match('/^([^_]+)_/', $basename, $matches)) {
                $channel = $matches[1];
                if (!isset($stats['channels'][$channel])) {
                    $stats['channels'][$channel] = 0;
                }
                $stats['channels'][$channel]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Search logs for specific content
     * 
     * @param string $pattern Search pattern (regex)
     * @param array $channels Channels to search (empty for all)
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Matching log entries
     */
    public function search($pattern, array $channels = [], $startDate = null, $endDate = null) {
        $results = [];
        $files = [];
        
        // Build file list based on criteria
        if (empty($channels)) {
            $files = glob($this->logDir . '/*.log');
        } else {
            foreach ($channels as $channel) {
                $files = array_merge($files, glob($this->logDir . '/' . $channel . '_*.log'));
            }
        }
        
        foreach ($files as $file) {
            // Check date range
            if ($startDate || $endDate) {
                if (preg_match('/_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                    $fileDate = $matches[1];
                    if ($startDate && $fileDate < $startDate) {
                        continue;
                    }
                    if ($endDate && $fileDate > $endDate) {
                        continue;
                    }
                }
            }
            
            // Search file content
            $handle = fopen($file, 'r');
            if ($handle) {
                $lineNumber = 0;
                while (($line = fgets($handle)) !== false) {
                    $lineNumber++;
                    
                    $entry = json_decode($line, true);
                    if (!$entry) {
                        continue;
                    }
                    
                    // Search in message and context
                    $searchText = json_encode($entry);
                    if (preg_match($pattern, $searchText)) {
                        $results[] = [
                            'file' => $file,
                            'line' => $lineNumber,
                            'entry' => $entry
                        ];
                    }
                }
                fclose($handle);
            }
        }
        
        return $results;
    }
}