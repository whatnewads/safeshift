<?php
/**
 * Logger Class
 * 
 * Handles file-based logging with rotation and cleanup
 */

namespace App\Core;

use Exception;

class Logger
{
    private string $logDir;
    private array $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    private int $currentLogLevel;
    
    public function __construct(?string $logDir = null)
    {
        $this->logDir = $logDir ?? dirname(__DIR__, 2) . '/logs';
        $this->currentLogLevel = $this->logLevels[defined('LOG_LEVEL') ? LOG_LEVEL : 'ERROR'];
        
        // Ensure log directory exists
        $this->createLogDirectory();
    }
    
    /**
     * Create log directory if it doesn't exist
     */
    private function createLogDirectory(): void
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0750, true);
        }
        
        // Protect log directory with .htaccess
        $htaccessFile = $this->logDir . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all\n");
        }
    }
    
    /**
     * Log a message to file
     * 
     * @param string $type Log type/category
     * @param mixed $data Data to log
     * @param string $level Log level
     * @return bool
     */
    public function log(string $type, $data, string $level = 'INFO'): bool
    {
        // Check if we should log based on level
        if (!$this->shouldLog($level)) {
            return true;
        }
        
        try {
            $logFile = $this->getLogFile($type);
            
            // Prepare log entry
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s.u'),
                'level' => $level,
                'type' => $type,
                'session_id' => session_id() ?: null,
                'user_id' => $_SESSION['user']['user_id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'data' => $data
            ];
            
            // Write to log file
            $logLine = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            
            // Use file locking to prevent corruption
            $result = file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
            
            // Rotate logs if they get too large (10MB)
            if (filesize($logFile) > 10485760) {
                $this->rotateLog($logFile);
            }
            
            return $result !== false;
            
        } catch (Exception $e) {
            // Last resort - log to PHP error log
            error_log('Logger failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if message should be logged based on level
     * 
     * @param string $level
     * @return bool
     */
    private function shouldLog(string $level): bool
    {
        $levelValue = $this->logLevels[$level] ?? 1;
        return $levelValue >= $this->currentLogLevel;
    }
    
    /**
     * Get log file path based on type and date
     * 
     * @param string $type
     * @return string
     */
    private function getLogFile(string $type): string
    {
        // Determine log file based on type
        switch ($type) {
            case 'error':
            case 'exception':
                $filename = 'error_' . date('Y-m-d') . '.log';
                break;
            case 'audit':
            case 'audit_error':
                $filename = 'audit_' . date('Y-m-d') . '.log';
                break;
            case 'access':
            case 'request':
                $filename = 'access_' . date('Y-m-d') . '.log';
                break;
            case '404':
                $filename = '404_' . date('Y-m-d') . '.log';
                break;
            case 'security':
                $filename = 'security_' . date('Y-m-d') . '.log';
                break;
            case 'phi':
            case 'phi_access':
                $filename = 'phi_' . date('Y-m-d') . '.log';
                break;
            case 'system':
                $filename = 'system_' . date('Y-m-d') . '.log';
                break;
            default:
                $filename = 'general_' . date('Y-m-d') . '.log';
        }
        
        return $this->logDir . '/' . $filename;
    }
    
    /**
     * Rotate a log file when it gets too large
     * 
     * @param string $logFile
     * @return bool
     */
    private function rotateLog(string $logFile): bool
    {
        try {
            $timestamp = date('YmdHis');
            $rotatedFile = $logFile . '.' . $timestamp;
            
            // Rename current log file
            if (rename($logFile, $rotatedFile)) {
                // Compress rotated file
                $gz = gzopen($rotatedFile . '.gz', 'w9');
                if ($gz) {
                    gzwrite($gz, file_get_contents($rotatedFile));
                    gzclose($gz);
                    
                    // Remove uncompressed rotated file
                    unlink($rotatedFile);
                }
                
                // Clean up old logs
                $this->cleanupOldLogs();
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Log rotation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old log files
     * 
     * @param int $daysToKeep
     */
    private function cleanupOldLogs(int $daysToKeep = 90): void
    {
        try {
            $cutoffTime = time() - ($daysToKeep * 86400);
            
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
     * Log debug message
     * 
     * @param string $type
     * @param mixed $data
     * @return bool
     */
    public function debug(string $type, $data): bool
    {
        return $this->log($type, $data, 'DEBUG');
    }
    
    /**
     * Log info message
     * 
     * @param string $type
     * @param mixed $data
     * @return bool
     */
    public function info(string $type, $data): bool
    {
        return $this->log($type, $data, 'INFO');
    }
    
    /**
     * Log warning message
     * 
     * @param string $type
     * @param mixed $data
     * @return bool
     */
    public function warning(string $type, $data): bool
    {
        return $this->log($type, $data, 'WARNING');
    }
    
    /**
     * Log error message
     * 
     * @param string $type
     * @param mixed $data
     * @return bool
     */
    public function error(string $type, $data): bool
    {
        return $this->log($type, $data, 'ERROR');
    }
    
    /**
     * Log critical message
     * 
     * @param string $type
     * @param mixed $data
     * @return bool
     */
    public function critical(string $type, $data): bool
    {
        return $this->log($type, $data, 'CRITICAL');
    }
    
    /**
     * Log exception
     * 
     * @param Exception $exception
     * @param array $context
     * @return bool
     */
    public function logException(Exception $exception, array $context = []): bool
    {
        $data = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context
        ];
        
        return $this->error('exception', $data);
    }
    
    /**
     * Read log file
     * 
     * @param string $type
     * @param string $date Y-m-d format
     * @param int $limit
     * @return array
     */
    public function readLog(string $type, string $date, int $limit = 1000): array
    {
        $logFile = $this->logDir . '/' . $type . '_' . $date . '.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $logs = [];
        $handle = fopen($logFile, 'r');
        
        if ($handle) {
            // Read file backwards for recent entries first
            $lines = [];
            while (($line = fgets($handle)) !== false) {
                $lines[] = $line;
                if (count($lines) > $limit) {
                    array_shift($lines);
                }
            }
            fclose($handle);
            
            // Parse JSON lines
            foreach (array_reverse($lines) as $line) {
                $entry = json_decode(trim($line), true);
                if ($entry) {
                    $logs[] = $entry;
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Get available log files
     * 
     * @return array
     */
    public function getAvailableLogs(): array
    {
        $logs = [];
        $files = glob($this->logDir . '/*.log');
        
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^(.+?)_(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
                $logs[] = [
                    'type' => $matches[1],
                    'date' => $matches[2],
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }
        
        return $logs;
    }
}