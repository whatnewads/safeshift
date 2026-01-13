<?php
/**
 * Rate Limiting Middleware
 * 
 * Implements rate limiting for API endpoints
 * Uses file-based storage (Redis recommended for production)
 */

namespace Api\Middleware;

class RateLimit
{
    private string $cacheDir;
    private int $windowSeconds;
    private int $maxRequests;
    
    /**
     * Constructor
     */
    public function __construct(int $windowSeconds = 60, int $maxRequests = 60)
    {
        $this->cacheDir = __DIR__ . '/../../cache/rate_limits/';
        $this->windowSeconds = $windowSeconds;
        $this->maxRequests = $maxRequests;
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Check if request is allowed
     */
    public function checkLimit(string $identifier, int $maxRequests = null): array
    {
        $maxRequests = $maxRequests ?? $this->maxRequests;
        $key = 'rate_limit_' . md5($identifier);
        $file = $this->cacheDir . $key . '.json';
        $currentTime = time();
        
        // Load existing data
        $data = $this->loadData($file);
        
        // Check if window has expired
        if ($data['window_start'] + $this->windowSeconds < $currentTime) {
            // Reset for new window
            $data = [
                'window_start' => $currentTime,
                'count' => 1
            ];
            $this->saveData($file, $data);
            
            return [
                'allowed' => true,
                'remaining' => $maxRequests - 1,
                'reset_at' => $currentTime + $this->windowSeconds
            ];
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxRequests) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => $data['window_start'] + $this->windowSeconds,
                'retry_after' => ($data['window_start'] + $this->windowSeconds) - $currentTime
            ];
        }
        
        // Increment counter
        $data['count']++;
        $this->saveData($file, $data);
        
        return [
            'allowed' => true,
            'remaining' => $maxRequests - $data['count'],
            'reset_at' => $data['window_start'] + $this->windowSeconds
        ];
    }
    
    /**
     * Apply rate limit headers
     */
    public function applyHeaders(array $result): void
    {
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset_at']);
        
        if (!$result['allowed']) {
            header('Retry-After: ' . $result['retry_after']);
        }
    }
    
    /**
     * Load rate limit data
     */
    private function loadData(string $file): array
    {
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['window_start']) && isset($data['count'])) {
                return $data;
            }
        }
        
        return [
            'window_start' => time(),
            'count' => 0
        ];
    }
    
    /**
     * Save rate limit data
     */
    private function saveData(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Clean old rate limit files
     */
    public function cleanup(): int
    {
        $deleted = 0;
        $files = glob($this->cacheDir . '*.json');
        $expireTime = time() - ($this->windowSeconds * 2);
        
        foreach ($files as $file) {
            if (filemtime($file) < $expireTime) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Create middleware for specific endpoint
     */
    public static function forEndpoint(string $endpoint, int $requests = 60, int $window = 60): self
    {
        $limits = [
            'dashboard-stats' => ['requests' => 30, 'window' => 60],
            'recent-patients' => ['requests' => 60, 'window' => 60],
            'patient-vitals' => ['requests' => 100, 'window' => 60],
            'notifications' => ['requests' => 120, 'window' => 60],
            'ems/*' => ['requests' => 200, 'window' => 60],
        ];
        
        // Check for specific endpoint config
        if (isset($limits[$endpoint])) {
            return new self($limits[$endpoint]['window'], $limits[$endpoint]['requests']);
        }
        
        // Check for wildcard matches
        foreach ($limits as $pattern => $config) {
            if (strpos($pattern, '*') !== false) {
                $regex = str_replace('*', '.*', $pattern);
                if (preg_match('/^' . $regex . '$/i', $endpoint)) {
                    return new self($config['window'], $config['requests']);
                }
            }
        }
        
        // Use provided or default values
        return new self($window, $requests);
    }
}