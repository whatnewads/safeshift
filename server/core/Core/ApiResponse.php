<?php
/**
 * API Response Class
 * 
 * Provides standardized JSON responses for all API endpoints
 */

namespace App\Core;

class ApiResponse
{
    /**
     * Send JSON response
     * 
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     */
    public static function json($data, int $statusCode = 200, array $headers = []): void
    {
        // Set default headers
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        
        // Set custom headers
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }
        
        // Set HTTP status code
        http_response_code($statusCode);
        
        // Output JSON
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // End script execution
        exit;
    }
    
    /**
     * Send success response
     * 
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     */
    public static function success($data = null, ?string $message = null, int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'data' => $data
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        self::json($response, $statusCode);
    }
    
    /**
     * Send error response
     * 
     * @param string $message
     * @param int $statusCode
     * @param array $errors
     * @param mixed $data
     */
    public static function error(string $message, int $statusCode = 400, array $errors = [], $data = null): void
    {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        self::json($response, $statusCode);
    }
    
    /**
     * Send validation error response
     * 
     * @param array $errors
     * @param string $message
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error($message, 422, $errors);
    }
    
    /**
     * Send unauthorized response
     * 
     * @param string $message
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }
    
    /**
     * Send forbidden response
     * 
     * @param string $message
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }
    
    /**
     * Send not found response
     * 
     * @param string $message
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }
    
    /**
     * Send method not allowed response
     * 
     * @param array $allowedMethods
     * @param string $message
     */
    public static function methodNotAllowed(array $allowedMethods = [], string $message = 'Method not allowed'): void
    {
        $headers = [];
        if (!empty($allowedMethods)) {
            $headers['Allow'] = implode(', ', $allowedMethods);
        }
        
        self::error($message, 405, [], null);
    }
    
    /**
     * Send conflict response
     * 
     * @param string $message
     * @param array $errors
     */
    public static function conflict(string $message = 'Conflict', array $errors = []): void
    {
        self::error($message, 409, $errors);
    }
    
    /**
     * Send too many requests response (rate limiting)
     * 
     * @param string $message
     * @param int $retryAfter Seconds until retry
     */
    public static function tooManyRequests(string $message = 'Too many requests', int $retryAfter = 60): void
    {
        header("Retry-After: $retryAfter");
        self::error($message, 429);
    }
    
    /**
     * Send internal server error response
     * 
     * @param string $message
     * @param bool $includeDebugInfo Include debug info in development
     */
    public static function serverError(string $message = 'Internal server error', bool $includeDebugInfo = true): void
    {
        $data = null;
        
        // Include debug info in development mode
        if ($includeDebugInfo && defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $data = [
                'debug' => [
                    'file' => debug_backtrace()[0]['file'] ?? 'unknown',
                    'line' => debug_backtrace()[0]['line'] ?? 0,
                    'trace' => array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 0, 5)
                ]
            ];
        }
        
        self::error($message, 500, [], $data);
    }
    
    /**
     * Send service unavailable response
     * 
     * @param string $message
     * @param int $retryAfter Seconds until service is available
     */
    public static function serviceUnavailable(string $message = 'Service unavailable', int $retryAfter = 300): void
    {
        header("Retry-After: $retryAfter");
        self::error($message, 503);
    }
    
    /**
     * Send created response
     * 
     * @param mixed $data
     * @param string|null $location Resource location URL
     * @param string|null $message
     */
    public static function created($data = null, ?string $location = null, ?string $message = null): void
    {
        if ($location !== null) {
            header("Location: $location");
        }
        
        self::success($data, $message, 201);
    }
    
    /**
     * Send accepted response (for async operations)
     * 
     * @param mixed $data
     * @param string|null $message
     */
    public static function accepted($data = null, ?string $message = null): void
    {
        self::success($data, $message, 202);
    }
    
    /**
     * Send no content response
     */
    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
    
    /**
     * Send partial content response
     * 
     * @param mixed $data
     * @param int $start
     * @param int $end
     * @param int $total
     */
    public static function partialContent($data, int $start, int $end, int $total): void
    {
        header("Content-Range: items $start-$end/$total");
        self::json($data, 206);
    }
    
    /**
     * Send paginated response
     * 
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @param string|null $message
     */
    public static function paginated(array $items, int $total, int $page, int $perPage, ?string $message = null): void
    {
        $totalPages = ceil($total / $perPage);
        
        $response = [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ]
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        self::json($response);
    }
    
    /**
     * Send file download response
     * 
     * @param string $content File content
     * @param string $filename
     * @param string $mimeType
     */
    public static function download(string $content, string $filename, string $mimeType = 'application/octet-stream'): void
    {
        header("Content-Type: $mimeType");
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Length: " . strlen($content));
        header("Cache-Control: private, max-age=0, must-revalidate");
        header("Pragma: public");
        
        echo $content;
        exit;
    }
    
    /**
     * Send CSV response
     * 
     * @param array $data
     * @param string $filename
     * @param array $headers
     */
    public static function csv(array $data, string $filename, array $headers = []): void
    {
        $output = fopen('php://memory', 'w');
        
        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers if provided
        if (!empty($headers)) {
            fputcsv($output, $headers);
        } elseif (!empty($data)) {
            // Use first row keys as headers
            fputcsv($output, array_keys($data[0]));
        }
        
        // Add data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        self::download($csv, $filename, 'text/csv; charset=UTF-8');
    }
    
    /**
     * Send redirect response
     * 
     * @param string $url
     * @param int $statusCode 301 (permanent) or 302 (temporary)
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: $url", true, $statusCode);
        exit;
    }
    
    /**
     * Send maintenance mode response
     * 
     * @param string $message
     * @param int $retryAfter Seconds until service is available
     */
    public static function maintenance(string $message = 'System is under maintenance', int $retryAfter = 3600): void
    {
        self::serviceUnavailable($message, $retryAfter);
    }
}