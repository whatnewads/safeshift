<?php
/**
 * Input Sanitizer
 * 
 * Provides secure input sanitization for HIPAA-compliant data handling
 * This class is responsible for cleaning and formatting input data
 * Used by ViewModels for format cleaning and HTML escaping
 */

namespace Core\Helpers;

class InputSanitizer
{
    /**
     * Sanitize general input
     * 
     * @param string|null $input Raw input string
     * @param int $flags Optional filter flags
     * @return string Sanitized string
     */
    public static function sanitizeInput(?string $input, int $flags = FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH): string
    {
        if ($input === null || $input === false) {
            return '';
        }
        
        // Convert to string if needed
        $input = (string) $input;
        
        // Remove whitespace from beginning and end
        $input = trim($input);
        
        // Remove backslashes
        $input = stripslashes($input);
        
        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Since FILTER_SANITIZE_STRING is deprecated in PHP 8.1+,
        // we'll use htmlspecialchars which already handles the escaping
        
        return $input;
    }
    
    /**
     * Sanitize email address
     * 
     * @param string $email Raw email input
     * @return string Sanitized email
     */
    public static function sanitizeEmail(string $email): string
    {
        $email = trim($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return strtolower($email);
    }
    
    /**
     * Sanitize username
     * 
     * @param string $username Raw username input
     * @return string Sanitized username
     */
    public static function sanitizeUsername(string $username): string
    {
        $username = trim($username);
        // Allow only alphanumeric, underscore, dash, and dot
        $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
        return substr($username, 0, 50); // Limit length
    }
    
    /**
     * Sanitize numeric input
     * 
     * @param mixed $number Raw numeric input
     * @param bool $allowDecimal Allow decimal numbers
     * @return mixed Sanitized number or 0
     */
    public static function sanitizeNumber($number, bool $allowDecimal = false)
    {
        if ($allowDecimal) {
            return filter_var($number, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }
        return filter_var($number, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * Sanitize phone number
     * 
     * @param string $phone Raw phone input
     * @return string Sanitized phone number
     */
    public static function sanitizePhone(string $phone): string
    {
        // Remove everything except digits, plus sign, parentheses, dash, space
        $phone = preg_replace('/[^0-9+\(\)\-\s]/', '', $phone);
        // Trim whitespace and limit length
        $phone = trim($phone);
        return substr($phone, 0, 20); // Limit length
    }
    
    /**
     * Sanitize text for database storage
     * 
     * @param string|null $text Raw text input
     * @param int $maxLength Maximum allowed length
     * @return string Sanitized text
     */
    public static function sanitizeText(?string $text, int $maxLength = 0): string
    {
        if ($text === null || $text === false) {
            return '';
        }
        
        // Convert to string
        $text = (string) $text;
        
        // Remove NULL bytes
        $text = str_replace("\0", "", $text);
        
        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Trim whitespace
        $text = trim($text);
        
        // Limit length if specified
        if ($maxLength > 0) {
            $text = substr($text, 0, $maxLength);
        }
        
        return $text;
    }
    
    /**
     * Sanitize HTML content (for rich text editors)
     * 
     * @param string $html Raw HTML input
     * @param array $allowedTags Array of allowed HTML tags
     * @return string Sanitized HTML
     */
    public static function sanitizeHtml(string $html, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            // Default safe tags for basic formatting
            $allowedTags = ['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'a', 'span'];
        }
        
        $allowedTagsString = '<' . implode('><', $allowedTags) . '>';
        $html = strip_tags($html, $allowedTagsString);
        
        // Remove dangerous attributes
        $html = preg_replace('/on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        
        return $html;
    }
    
    /**
     * Sanitize file name
     * 
     * @param string $filename Raw filename input
     * @return string Sanitized filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path information
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        
        // Remove multiple dots (prevent directory traversal)
        $filename = preg_replace('/\.+/', '.', $filename);
        
        // Ensure it doesn't start with a dot
        $filename = ltrim($filename, '.');
        
        return $filename;
    }
    
    /**
     * Sanitize URL
     * 
     * @param string $url Raw URL input
     * @return string Sanitized URL
     */
    public static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        // Validate the URL structure
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        // Ensure it's HTTP or HTTPS
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            return '';
        }
        
        return $url;
    }
    
    /**
     * Sanitize array recursively
     * 
     * @param array $array Input array
     * @param string $method Sanitization method to apply
     * @return array Sanitized array
     */
    public static function sanitizeArray(array $array, string $method = 'sanitizeInput'): array
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            $cleanKey = self::sanitizeInput($key);
            
            if (is_array($value)) {
                $sanitized[$cleanKey] = self::sanitizeArray($value, $method);
            } else {
                if (method_exists(self::class, $method)) {
                    $sanitized[$cleanKey] = self::$method($value);
                } else {
                    $sanitized[$cleanKey] = self::sanitizeInput($value);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize JSON string
     * 
     * @param string $json Raw JSON input
     * @return string Sanitized JSON or empty string
     */
    public static function sanitizeJson(string $json): string
    {
        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }
        
        // Sanitize the decoded data
        $sanitized = self::sanitizeArray($decoded);
        
        return json_encode($sanitized);
    }
    
    /**
     * Sanitize for SQL LIKE queries
     * 
     * @param string $string Input string
     * @return string Escaped string for LIKE queries
     */
    public static function sanitizeLike(string $string): string
    {
        $string = self::sanitizeInput($string);
        // Escape SQL LIKE wildcards
        $string = str_replace(['%', '_'], ['\%', '\_'], $string);
        return $string;
    }
    
    /**
     * Remove XSS attempts from input
     * 
     * @param string $input Raw input
     * @return string Cleaned input
     */
    public static function removeXss(string $input): string
    {
        // Remove any NULL bytes
        $input = str_replace("\0", '', $input);
        
        // Remove suspicious strings
        $patterns = [
            '/<script[^>]*?>.*?<\/script>/si',   // Strip out javascript
            '/<iframe[^>]*?>.*?<\/iframe>/si',   // Strip out iframes
            '/<object[^>]*?>.*?<\/object>/si',   // Strip out objects
            '/<embed[^>]*?>.*?<\/embed>/si',     // Strip out embeds
            '/on\w+\s*=\s*["\'][^"\']*["\']/i',  // Strip out event handlers
            '/javascript\s*:/i',                  // Strip javascript protocol
            '/vbscript\s*:/i',                    // Strip vbscript protocol
        ];
        
        foreach ($patterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        return $input;
    }
    
    /**
     * Sanitize OTP code
     * 
     * @param string $otp Raw OTP input
     * @return string Sanitized OTP code
     */
    public static function sanitizeOtp(string $otp): string
    {
        // Remove all non-alphanumeric characters
        $otp = preg_replace('/[^A-Za-z0-9]/', '', $otp);
        // Convert to uppercase
        $otp = strtoupper($otp);
        // Limit to 6 characters
        return substr($otp, 0, 6);
    }
    
    /**
     * Sanitize and escape output for safe HTML display
     * 
     * @param string|null $string String to escape
     * @param int $flags ENT_* flags for htmlspecialchars
     * @param string $encoding Character encoding
     * @return string Escaped string safe for HTML output
     */
    public static function escape(?string $string, int $flags = ENT_QUOTES | ENT_HTML5, string $encoding = 'UTF-8'): string
    {
        if ($string === null) {
            return '';
        }
        
        return htmlspecialchars($string, $flags, $encoding);
    }
    
    /**
     * Sanitize data for CSV output
     * 
     * @param string $data Data to sanitize
     * @return string Sanitized data safe for CSV
     */
    public static function sanitizeCsv(string $data): string
    {
        // Remove any formula injection attempts
        if (in_array(substr($data, 0, 1), ['=', '+', '-', '@'])) {
            $data = "'" . $data;
        }
        
        // Escape quotes
        $data = str_replace('"', '""', $data);
        
        return $data;
    }
}