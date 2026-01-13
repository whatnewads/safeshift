<?php
/**
 * Dashboard Logger Service
 * 
 * Provides structured JSON logging specifically for dashboard operations.
 * Tracks performance metrics, cache operations, and user access patterns.
 * 
 * Log Channels:
 * - dashboard: General dashboard operations
 * - metrics: Metric calculations and requests
 * - cache: Cache hit/miss operations
 * - performance: Query and response time tracking
 * - access: User dashboard access patterns
 * 
 * @package Core\Services
 */

declare(strict_types=1);

namespace Core\Services;

use Exception;

class DashboardLogger
{
    private static ?DashboardLogger $instance = null;
    private string $logPath;
    private float $requestStartTime;
    private array $queryLog = [];
    private int $cacheHits = 0;
    private int $cacheMisses = 0;
    
    // Operation types
    public const OP_DASHBOARD_LOAD = 'DASHBOARD_LOAD';
    public const OP_METRIC_REQUEST = 'METRIC_REQUEST';
    public const OP_METRIC_CALCULATE = 'METRIC_CALCULATE';
    public const OP_CACHE_READ = 'CACHE_READ';
    public const OP_CACHE_WRITE = 'CACHE_WRITE';
    public const OP_QUERY_EXECUTE = 'QUERY_EXECUTE';
    public const OP_DATA_AGGREGATE = 'DATA_AGGREGATE';
    public const OP_TODO_HIT = 'TODO_HIT';
    
    // Log channels
    public const CHANNEL_DASHBOARD = 'dashboard';
    public const CHANNEL_METRICS = 'metrics';
    public const CHANNEL_CACHE = 'cache';
    public const CHANNEL_PERFORMANCE = 'performance';
    public const CHANNEL_ACCESS = 'access';
    
    // Dashboard types
    public const DASH_ADMIN = 'admin';
    public const DASH_MANAGER = 'manager';
    public const DASH_CLINICAL = 'clinical_provider';
    public const DASH_TECHNICIAN = 'technician';
    public const DASH_REGISTRATION = 'registration';
    public const DASH_GENERIC = 'generic';
    
    // Performance thresholds
    public const THRESHOLD_SLOW_QUERY_MS = 100;
    public const THRESHOLD_SLOW_DASHBOARD_MS = 500;
    public const THRESHOLD_MAX_QUERIES = 10;
    public const THRESHOLD_CACHE_MISS_RATE = 0.5;
    
    // Log levels
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_PERF = 'PERF';

    private function __construct()
    {
        $this->logPath = dirname(dirname(__DIR__)) . '/logs/';
        $this->requestStartTime = microtime(true);
        
        // Ensure log directory exists
        if (!file_exists($this->logPath)) {
            mkdir($this->logPath, 0750, true);
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset request tracking for a new request
     */
    public function startRequest(): void
    {
        $this->requestStartTime = microtime(true);
        $this->queryLog = [];
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
    }

    /**
     * Log a dashboard metric request
     * 
     * @param string $dashboard Dashboard type being accessed
     * @param string $metric Specific metric being requested
     * @param int $userId User making the request
     * @param array $filters Filter parameters applied
     * @return bool Success status
     */
    public function logMetricRequest(string $dashboard, string $metric, int $userId, array $filters = []): bool
    {
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => self::LEVEL_INFO,
            'channel' => self::CHANNEL_METRICS,
            'operation' => self::OP_METRIC_REQUEST,
            'dashboard_type' => $dashboard,
            'metric' => $metric,
            'user_id' => $userId,
            'user_role' => $this->getCurrentUserRole(),
            'filters' => $this->sanitizeFilters($filters),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $this->getUserAgent(),
            'request_id' => $this->getRequestId(),
            'result' => 'initiated',
        ];
        
        return $this->writeLog(self::CHANNEL_METRICS, $logEntry);
    }

    /**
     * Log metric calculation details
     * 
     * @param string $metric Metric being calculated
     * @param array $queryInfo Information about queries executed
     * @param float $executionTime Time taken for calculation in seconds
     * @return bool Success status
     */
    public function logMetricCalculation(string $metric, array $queryInfo, float $executionTime): bool
    {
        $executionTimeMs = (int)($executionTime * 1000);
        $level = $executionTimeMs > self::THRESHOLD_SLOW_QUERY_MS ? self::LEVEL_WARNING : self::LEVEL_INFO;
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => $level,
            'channel' => self::CHANNEL_METRICS,
            'operation' => self::OP_METRIC_CALCULATE,
            'metric' => $metric,
            'user_id' => $this->getCurrentUserId(),
            'query_count' => $queryInfo['query_count'] ?? 1,
            'row_count' => $queryInfo['row_count'] ?? 0,
            'execution_time_ms' => $executionTimeMs,
            'threshold_exceeded' => $executionTimeMs > self::THRESHOLD_SLOW_QUERY_MS,
            'request_id' => $this->getRequestId(),
            'result' => 'success',
        ];
        
        // Track query for performance summary
        $this->queryLog[] = [
            'metric' => $metric,
            'execution_time_ms' => $executionTimeMs,
        ];
        
        return $this->writeLog(self::CHANNEL_METRICS, $logEntry);
    }

    /**
     * Log cache operation (hit or miss)
     * 
     * @param string $key Cache key accessed
     * @param bool $hit Whether cache was hit (true) or missed (false)
     * @param int|null $ttl Time-to-live for cache entry (on write)
     * @return bool Success status
     */
    public function logCacheOperation(string $key, bool $hit, ?int $ttl = null): bool
    {
        // Update hit/miss counters
        if ($hit) {
            $this->cacheHits++;
        } else {
            $this->cacheMisses++;
        }
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => self::LEVEL_DEBUG,
            'channel' => self::CHANNEL_CACHE,
            'operation' => $hit ? self::OP_CACHE_READ : self::OP_CACHE_WRITE,
            'cache_key' => $this->sanitizeCacheKey($key),
            'cache_hit' => $hit,
            'ttl_seconds' => $ttl,
            'user_id' => $this->getCurrentUserId(),
            'request_id' => $this->getRequestId(),
            'session_hits' => $this->cacheHits,
            'session_misses' => $this->cacheMisses,
            'session_hit_rate' => $this->calculateCacheHitRate(),
        ];
        
        // Warn if cache miss rate is high
        if ($this->shouldWarnCacheMissRate()) {
            $logEntry['level'] = self::LEVEL_WARNING;
            $logEntry['warning'] = 'High cache miss rate detected';
        }
        
        return $this->writeLog(self::CHANNEL_CACHE, $logEntry);
    }

    /**
     * Log full dashboard load event
     * 
     * @param string $dashboardType Type of dashboard being loaded
     * @param int $userId User loading the dashboard
     * @param array $metrics Metrics included in the dashboard
     * @return bool Success status
     */
    public function logDashboardLoad(string $dashboardType, int $userId, array $metrics): bool
    {
        $totalTimeMs = (int)((microtime(true) - $this->requestStartTime) * 1000);
        $totalQueryTime = array_sum(array_column($this->queryLog, 'execution_time_ms'));
        $queryCount = count($this->queryLog);
        
        $level = self::LEVEL_INFO;
        $warnings = [];
        
        // Check performance thresholds
        if ($totalTimeMs > self::THRESHOLD_SLOW_DASHBOARD_MS) {
            $level = self::LEVEL_WARNING;
            $warnings[] = "Dashboard load exceeded " . self::THRESHOLD_SLOW_DASHBOARD_MS . "ms threshold";
        }
        
        if ($queryCount > self::THRESHOLD_MAX_QUERIES) {
            $level = self::LEVEL_WARNING;
            $warnings[] = "Query count ({$queryCount}) exceeded threshold of " . self::THRESHOLD_MAX_QUERIES;
        }
        
        if ($this->shouldWarnCacheMissRate()) {
            $level = self::LEVEL_WARNING;
            $warnings[] = 'Cache miss rate exceeded 50%';
        }
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => $level,
            'channel' => self::CHANNEL_DASHBOARD,
            'operation' => self::OP_DASHBOARD_LOAD,
            'dashboard_type' => $dashboardType,
            'user_id' => $userId,
            'user_role' => $this->getCurrentUserRole(),
            'metrics_requested' => $metrics,
            'metrics_count' => count($metrics),
            'cache_status' => $this->cacheHits > 0 ? 'hit' : 'miss',
            'cache_hit_rate' => $this->calculateCacheHitRate(),
            'query_count' => $queryCount,
            'total_query_time_ms' => $totalQueryTime,
            'response_time_ms' => $totalTimeMs,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $this->getUserAgent(),
            'request_id' => $this->getRequestId(),
            'warnings' => $warnings,
            'result' => 'success',
        ];
        
        return $this->writeLog(self::CHANNEL_DASHBOARD, $logEntry);
    }

    /**
     * Log query performance (especially for slow queries)
     * 
     * @param string $query Query description (not the actual SQL for security)
     * @param float $executionTime Execution time in seconds
     * @param int $rowCount Number of rows returned
     * @return bool Success status
     */
    public function logQueryPerformance(string $query, float $executionTime, int $rowCount): bool
    {
        $executionTimeMs = (int)($executionTime * 1000);
        
        // Only log if query is slow (>100ms) or if it's a significant query
        if ($executionTimeMs < self::THRESHOLD_SLOW_QUERY_MS && $rowCount < 100) {
            // Track but don't write to log for fast queries
            $this->queryLog[] = [
                'query' => $query,
                'execution_time_ms' => $executionTimeMs,
                'row_count' => $rowCount,
            ];
            return true;
        }
        
        $level = $executionTimeMs > self::THRESHOLD_SLOW_QUERY_MS ? self::LEVEL_PERF : self::LEVEL_DEBUG;
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => $level,
            'channel' => self::CHANNEL_PERFORMANCE,
            'operation' => self::OP_QUERY_EXECUTE,
            'query_description' => $this->sanitizeQueryDescription($query),
            'execution_time_ms' => $executionTimeMs,
            'row_count' => $rowCount,
            'is_slow_query' => $executionTimeMs > self::THRESHOLD_SLOW_QUERY_MS,
            'user_id' => $this->getCurrentUserId(),
            'request_id' => $this->getRequestId(),
        ];
        
        // Track query
        $this->queryLog[] = [
            'query' => $query,
            'execution_time_ms' => $executionTimeMs,
            'row_count' => $rowCount,
        ];
        
        return $this->writeLog(self::CHANNEL_PERFORMANCE, $logEntry);
    }

    /**
     * Log user dashboard access pattern
     * 
     * @param int $userId User ID
     * @param string $dashboardType Dashboard type accessed
     * @param array $accessDetails Additional access details
     * @return bool Success status
     */
    public function logDashboardAccess(int $userId, string $dashboardType, array $accessDetails = []): bool
    {
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => self::LEVEL_INFO,
            'channel' => self::CHANNEL_ACCESS,
            'operation' => self::OP_DASHBOARD_LOAD,
            'user_id' => $userId,
            'user_role' => $this->getCurrentUserRole(),
            'dashboard_type' => $dashboardType,
            'access_details' => $this->sanitizeAccessDetails($accessDetails),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $this->getUserAgent(),
            'request_id' => $this->getRequestId(),
            'session_id' => session_id() ? substr(session_id(), 0, 8) . '...' : null,
        ];
        
        return $this->writeLog(self::CHANNEL_ACCESS, $logEntry);
    }

    /**
     * Log data aggregation operation
     * 
     * @param string $aggregationType Type of aggregation performed
     * @param array $dataInfo Information about the data aggregated
     * @param float $executionTime Time taken for aggregation
     * @return bool Success status
     */
    public function logDataAggregation(string $aggregationType, array $dataInfo, float $executionTime): bool
    {
        $executionTimeMs = (int)($executionTime * 1000);
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => $executionTimeMs > self::THRESHOLD_SLOW_QUERY_MS ? self::LEVEL_WARNING : self::LEVEL_INFO,
            'channel' => self::CHANNEL_METRICS,
            'operation' => self::OP_DATA_AGGREGATE,
            'aggregation_type' => $aggregationType,
            'data_points' => $dataInfo['data_points'] ?? 0,
            'source_tables' => $dataInfo['source_tables'] ?? [],
            'execution_time_ms' => $executionTimeMs,
            'user_id' => $this->getCurrentUserId(),
            'request_id' => $this->getRequestId(),
            'result' => 'success',
        ];
        
        return $this->writeLog(self::CHANNEL_METRICS, $logEntry);
    }

    /**
     * Log when a TODO item is hit (incomplete feature)
     * 
     * @param string $location Code location (file:line or method name)
     * @param string $description Description of the TODO
     * @param array $context Additional context
     * @return bool Success status
     */
    public function logTodoHit(string $location, string $description, array $context = []): bool
    {
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => self::LEVEL_WARNING,
            'channel' => self::CHANNEL_DASHBOARD,
            'operation' => self::OP_TODO_HIT,
            'todo_location' => $location,
            'todo_description' => $this->sanitizeString($description),
            'user_id' => $this->getCurrentUserId(),
            'user_role' => $this->getCurrentUserRole(),
            'context' => $this->sanitizeContext($context),
            'request_id' => $this->getRequestId(),
            'action_required' => true,
        ];
        
        return $this->writeLog(self::CHANNEL_DASHBOARD, $logEntry);
    }

    /**
     * Log an error during dashboard operation
     * 
     * @param string $operation Operation that failed
     * @param string $errorMessage Error message
     * @param array $context Additional context
     * @return bool Success status
     */
    public function logError(string $operation, string $errorMessage, array $context = []): bool
    {
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => self::LEVEL_ERROR,
            'channel' => self::CHANNEL_DASHBOARD,
            'operation' => $operation,
            'user_id' => $this->getCurrentUserId(),
            'user_role' => $this->getCurrentUserRole(),
            'error_message' => $this->sanitizeString($errorMessage),
            'context' => $this->sanitizeContext($context),
            'ip_address' => $this->getClientIP(),
            'request_id' => $this->getRequestId(),
            'result' => 'failure',
        ];
        
        return $this->writeLog(self::CHANNEL_DASHBOARD, $logEntry);
    }

    /**
     * Get performance summary for current request
     * 
     * @return array Performance summary data
     */
    public function getPerformanceSummary(): array
    {
        $totalTimeMs = (int)((microtime(true) - $this->requestStartTime) * 1000);
        $totalQueryTime = array_sum(array_column($this->queryLog, 'execution_time_ms'));
        $queryCount = count($this->queryLog);
        $slowQueries = array_filter($this->queryLog, function($q) {
            return ($q['execution_time_ms'] ?? 0) > self::THRESHOLD_SLOW_QUERY_MS;
        });
        
        return [
            'total_time_ms' => $totalTimeMs,
            'query_count' => $queryCount,
            'total_query_time_ms' => $totalQueryTime,
            'slow_query_count' => count($slowQueries),
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
            'cache_hit_rate' => $this->calculateCacheHitRate(),
            'thresholds_exceeded' => [
                'slow_dashboard' => $totalTimeMs > self::THRESHOLD_SLOW_DASHBOARD_MS,
                'max_queries' => $queryCount > self::THRESHOLD_MAX_QUERIES,
                'cache_miss_rate' => $this->shouldWarnCacheMissRate(),
            ],
        ];
    }

    // ========== PRIVATE HELPER METHODS ==========

    /**
     * Calculate current cache hit rate
     */
    private function calculateCacheHitRate(): float
    {
        $total = $this->cacheHits + $this->cacheMisses;
        if ($total === 0) {
            return 0.0;
        }
        return round($this->cacheHits / $total, 2);
    }

    /**
     * Check if cache miss rate exceeds threshold
     */
    private function shouldWarnCacheMissRate(): bool
    {
        $total = $this->cacheHits + $this->cacheMisses;
        if ($total < 5) {
            return false; // Not enough data
        }
        return $this->calculateCacheHitRate() < (1 - self::THRESHOLD_CACHE_MISS_RATE);
    }

    /**
     * Sanitize filter parameters for logging
     */
    private function sanitizeFilters(array $filters): array
    {
        $sanitized = [];
        foreach ($filters as $key => $value) {
            // Don't log sensitive filter values
            if (in_array(strtolower($key), ['password', 'token', 'secret', 'ssn', 'dob'])) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeFilters($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = substr($value, 0, 100);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Sanitize cache key for logging
     */
    private function sanitizeCacheKey(string $key): string
    {
        // Remove any potential sensitive data from cache key
        $key = preg_replace('/\d{3}-\d{2}-\d{4}/', '[SSN]', $key);
        return substr($key, 0, 200);
    }

    /**
     * Sanitize query description
     */
    private function sanitizeQueryDescription(string $query): string
    {
        // Remove actual values, keep structure
        $sanitized = preg_replace('/= \'[^\']*\'/', "= '[VALUE]'", $query);
        $sanitized = preg_replace('/= \d+/', '= [NUMBER]', $sanitized);
        return substr($sanitized, 0, 500);
    }

    /**
     * Sanitize access details
     */
    private function sanitizeAccessDetails(array $details): array
    {
        $sensitiveKeys = ['patient_id', 'ssn', 'dob', 'email', 'phone'];
        $sanitized = [];
        
        foreach ($details as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeAccessDetails($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize context array
     */
    private function sanitizeContext(array $context): array
    {
        return $this->sanitizeFilters($context);
    }

    /**
     * Sanitize string for logging
     */
    private function sanitizeString(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        return substr($value, 0, 2000);
    }

    /**
     * Write log entry to file
     */
    private function writeLog(string $channel, array $entry): bool
    {
        try {
            $date = date('Y-m-d');
            $filename = $this->logPath . 'dashboard_' . $date . '.log';
            
            // Add hash for tamper detection
            $entry['hash'] = hash('sha256', json_encode($entry) . ($this->getLastHash() ?? ''));
            
            $logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            
            $result = file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX);
            
            if ($result !== false) {
                $this->updateLastHash($entry['hash']);
            }
            
            return $result !== false;
        } catch (Exception $e) {
            error_log("DashboardLogger write failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get last hash for chain integrity
     */
    private function getLastHash(): ?string
    {
        $hashFile = $this->logPath . '.dashboard_hash';
        if (file_exists($hashFile)) {
            return file_get_contents($hashFile);
        }
        return null;
    }

    /**
     * Update last hash
     */
    private function updateLastHash(string $hash): void
    {
        $hashFile = $this->logPath . '.dashboard_hash';
        file_put_contents($hashFile, $hash, LOCK_EX);
    }

    /**
     * Get current user ID from session
     */
    private function getCurrentUserId(): ?int
    {
        return $_SESSION['user']['user_id'] ?? null;
    }

    /**
     * Get current user role from session
     */
    private function getCurrentUserRole(): ?string
    {
        return $_SESSION['user']['role'] ?? $_SESSION['user']['primary_role'] ?? null;
    }

    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
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
     * Get user agent string
     */
    private function getUserAgent(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return substr($ua, 0, 500);
    }

    /**
     * Get or generate request ID
     */
    private function getRequestId(): string
    {
        if (!isset($_SERVER['X_REQUEST_ID'])) {
            $_SERVER['X_REQUEST_ID'] = 'dash_' . uniqid('', true);
        }
        return $_SERVER['X_REQUEST_ID'];
    }

    /**
     * Get log statistics for dashboard logs
     * 
     * @param string $date Date to get stats for (Y-m-d format)
     * @return array Statistics array
     */
    public function getLogStatistics(string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $filename = $this->logPath . 'dashboard_' . $date . '.log';
        
        if (!file_exists($filename)) {
            return ['error' => 'Log file not found'];
        }
        
        $stats = [
            'date' => $date,
            'total_entries' => 0,
            'by_channel' => [],
            'by_operation' => [],
            'by_level' => [],
            'by_user' => [],
            'slow_queries' => 0,
            'cache_operations' => ['hits' => 0, 'misses' => 0],
            'avg_response_time_ms' => 0,
            'file_size_bytes' => filesize($filename),
        ];
        
        $totalResponseTime = 0;
        $responseTimeCount = 0;
        
        $handle = fopen($filename, 'r');
        while (($line = fgets($handle)) !== false) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            
            $stats['total_entries']++;
            
            // Count by channel
            $channel = $entry['channel'] ?? 'unknown';
            $stats['by_channel'][$channel] = ($stats['by_channel'][$channel] ?? 0) + 1;
            
            // Count by operation
            $op = $entry['operation'] ?? 'unknown';
            $stats['by_operation'][$op] = ($stats['by_operation'][$op] ?? 0) + 1;
            
            // Count by level
            $level = $entry['level'] ?? 'unknown';
            $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
            
            // Count by user
            $userId = $entry['user_id'] ?? 'anonymous';
            $stats['by_user'][$userId] = ($stats['by_user'][$userId] ?? 0) + 1;
            
            // Track slow queries
            if (($entry['is_slow_query'] ?? false) === true) {
                $stats['slow_queries']++;
            }
            
            // Track cache operations
            if (isset($entry['cache_hit'])) {
                if ($entry['cache_hit']) {
                    $stats['cache_operations']['hits']++;
                } else {
                    $stats['cache_operations']['misses']++;
                }
            }
            
            // Track response times
            if (isset($entry['response_time_ms'])) {
                $totalResponseTime += $entry['response_time_ms'];
                $responseTimeCount++;
            }
        }
        fclose($handle);
        
        if ($responseTimeCount > 0) {
            $stats['avg_response_time_ms'] = round($totalResponseTime / $responseTimeCount, 2);
        }
        
        // Calculate cache hit rate
        $totalCache = $stats['cache_operations']['hits'] + $stats['cache_operations']['misses'];
        $stats['cache_operations']['hit_rate'] = $totalCache > 0 
            ? round($stats['cache_operations']['hits'] / $totalCache * 100, 1) 
            : 0;
        
        return $stats;
    }
}
