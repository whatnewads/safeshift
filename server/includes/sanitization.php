<?php
/**
 * Input Sanitization Functions - REFACTORED VERSION
 * Located at: root/includes/sanitization.php
 * 
 * This file provides backward compatibility while using the new InputSanitizer
 * All functions now delegate to the InputSanitizer in Core layer
 * 
 * @deprecated Use Core\Helpers\InputSanitizer directly
 */

namespace App\sanitization;

use Core\Helpers\InputSanitizer;

/**
 * Sanitize general input
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeInput() instead
 * @param string $input Raw input string
 * @param int $flags Optional filter flags
 * @return string Sanitized string
 */
function sanitize_input($input, $flags = FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH) {
    return InputSanitizer::sanitizeInput($input, $flags);
}

/**
 * Sanitize email address
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeEmail() instead
 * @param string $email Raw email input
 * @return string Sanitized email
 */
function sanitize_email($email) {
    return InputSanitizer::sanitizeEmail($email);
}

/**
 * Sanitize username
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeUsername() instead
 * @param string $username Raw username input
 * @return string Sanitized username
 */
function sanitize_username($username) {
    return InputSanitizer::sanitizeUsername($username);
}

/**
 * Sanitize numeric input
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeNumber() instead
 * @param mixed $number Raw numeric input
 * @param bool $allow_decimal Allow decimal numbers
 * @return mixed Sanitized number or 0
 */
function sanitize_number($number, $allow_decimal = false) {
    return InputSanitizer::sanitizeNumber($number, $allow_decimal);
}

/**
 * Sanitize phone number
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizePhone() instead
 * @param string $phone Raw phone input
 * @return string Sanitized phone number
 */
function sanitize_phone($phone) {
    return InputSanitizer::sanitizePhone($phone);
}

/**
 * Sanitize text for database storage
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeText() instead
 * @param string $text Raw text input
 * @param int $max_length Maximum allowed length
 * @return string Sanitized text
 */
function sanitize_text($text, $max_length = 0) {
    return InputSanitizer::sanitizeText($text, $max_length);
}

/**
 * Sanitize HTML content (for rich text editors)
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeHtml() instead
 * @param string $html Raw HTML input
 * @param array $allowed_tags Array of allowed HTML tags
 * @return string Sanitized HTML
 */
function sanitize_html($html, $allowed_tags = []) {
    return InputSanitizer::sanitizeHtml($html, $allowed_tags);
}

/**
 * Sanitize file name
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeFilename() instead
 * @param string $filename Raw filename input
 * @return string Sanitized filename
 */
function sanitize_filename($filename) {
    return InputSanitizer::sanitizeFilename($filename);
}

/**
 * Sanitize URL
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeUrl() instead
 * @param string $url Raw URL input
 * @return string Sanitized URL
 */
function sanitize_url($url) {
    return InputSanitizer::sanitizeUrl($url);
}

/**
 * Sanitize array recursively
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeArray() instead
 * @param array $array Input array
 * @param string $method Sanitization method to apply
 * @return array Sanitized array
 */
function sanitize_array($array, $method = 'sanitize_input') {
    // Convert legacy method names to new format
    $methodMap = [
        'sanitize_input' => 'sanitizeInput',
        'sanitize_email' => 'sanitizeEmail',
        'sanitize_username' => 'sanitizeUsername',
        'sanitize_number' => 'sanitizeNumber',
        'sanitize_phone' => 'sanitizePhone',
        'sanitize_text' => 'sanitizeText',
        'sanitize_html' => 'sanitizeHtml',
        'sanitize_filename' => 'sanitizeFilename',
        'sanitize_url' => 'sanitizeUrl'
    ];
    
    $newMethod = isset($methodMap[$method]) ? $methodMap[$method] : 'sanitizeInput';
    return InputSanitizer::sanitizeArray($array, $newMethod);
}

/**
 * Sanitize JSON string
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeJson() instead
 * @param string $json Raw JSON input
 * @return string Sanitized JSON or empty string
 */
function sanitize_json($json) {
    return InputSanitizer::sanitizeJson($json);
}

/**
 * Sanitize for SQL LIKE queries
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeLike() instead
 * @param string $string Input string
 * @return string Escaped string for LIKE queries
 */
function sanitize_like($string) {
    return InputSanitizer::sanitizeLike($string);
}

/**
 * Remove XSS attempts from input
 * @deprecated Use Core\Helpers\InputSanitizer::removeXss() instead
 * @param string $input Raw input
 * @return string Cleaned input
 */
function remove_xss($input) {
    return InputSanitizer::removeXss($input);
}

/**
 * Validate and sanitize OTP code
 * @deprecated Use Core\Helpers\InputSanitizer::sanitizeOtp() instead
 * @param string $otp Raw OTP input
 * @return string Sanitized OTP code
 */
function sanitize_otp($otp) {
    return InputSanitizer::sanitizeOtp($otp);
}