<?php
/**
 * Validation Functions - REFACTORED VERSION
 * 
 * This file provides backward compatibility while using the new ValidationService
 * All functions now delegate to the ValidationService in Core layer
 */

namespace App\validation;

use Core\Services\ValidationService;
use Exception;

// Initialize service (singleton pattern for backward compatibility)
$validationService = null;

/**
 * Get ValidationService instance
 */
function getValidationService(): ValidationService {
    global $validationService;
    if ($validationService === null) {
        $validationService = new ValidationService();
    }
    return $validationService;
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool
 */
function validate_email($email) {
    return getValidationService()->validateEmail($email);
}

/**
 * Validate username
 * @param string $username Username to validate
 * @return bool
 */
function validate_username($username) {
    return getValidationService()->validateUsername($username);
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_password($password) {
    return getValidationService()->validatePassword($password);
}

/**
 * Validate phone number
 * @param string $phone Phone number to validate
 * @return bool
 */
function validate_phone($phone) {
    return getValidationService()->validatePhone($phone);
}

/**
 * Validate date
 * @param string $date Date string
 * @param string $format Expected format
 * @return bool
 */
function validate_date($date, $format = 'Y-m-d') {
    return getValidationService()->validateDate($date, $format);
}

/**
 * Validate OTP code
 * @param string $otp OTP code to validate
 * @return bool
 */
function validate_otp($otp) {
    return getValidationService()->validateOtp($otp);
}

/**
 * Validate UUID
 * @param string $uuid UUID to validate
 * @return bool
 */
function validate_uuid($uuid) {
    return getValidationService()->validateUuid($uuid);
}

/**
 * Validate URL
 * @param string $url URL to validate
 * @return bool
 */
function validate_url($url) {
    return getValidationService()->validateUrl($url);
}

/**
 * Validate IP address
 * @param string $ip IP address to validate
 * @param bool $allow_private Allow private IP ranges
 * @return bool
 */
function validate_ip($ip, $allow_private = false) {
    return getValidationService()->validateIp($ip, $allow_private);
}

/**
 * Validate CSRF token
 * @param string $token Provided token
 * @param string $session_token Token from session
 * @return bool
 */
function validate_csrf_token($token, $session_token) {
    return getValidationService()->validateCsrfToken($token, $session_token);
}

/**
 * Validate file upload
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return array ['valid' => bool, 'error' => string]
 */
function validate_file_upload($file, $allowed_types = [], $max_size = 10485760) {
    return getValidationService()->validateFileUpload($file, $allowed_types, $max_size);
}

/**
 * Validate input against regex pattern
 * @param string $input Input to validate
 * @param string $pattern Regex pattern
 * @return bool
 */
function validate_pattern($input, $pattern) {
    return getValidationService()->validatePattern($input, $pattern);
}

/**
 * Validate alphanumeric string
 * @param string $input Input to validate
 * @param bool $allow_spaces Allow spaces
 * @return bool
 */
function validate_alphanumeric($input, $allow_spaces = false) {
    return getValidationService()->validateAlphanumeric($input, $allow_spaces);
}

/**
 * Validate numeric input
 * @param mixed $input Input to validate
 * @param float $min Minimum value
 * @param float $max Maximum value
 * @return bool
 */
function validate_number($input, $min = null, $max = null) {
    return getValidationService()->validateNumber($input, $min, $max);
}

/**
 * Validate required field
 * @param mixed $input Input to validate
 * @return bool
 */
function validate_required($input) {
    return getValidationService()->validateRequired($input);
}

/**
 * Validate input
 * General validation function
 * @param mixed $input Input to validate
 * @param array $rules Validation rules
 * @return bool
 */
function validate_input($input, $rules = []) {
    // If no rules provided, just check if required
    if (empty($rules)) {
        return validate_required($input);
    }
    
    // If rules are provided, use the full validation service
    // Convert single field validation to the expected format
    $data = ['field' => $input];
    $validationRules = ['field' => $rules];
    
    $result = getValidationService()->validate($data, $validationRules);
    
    return $result['valid'];
}

/**
 * Validate multiple fields with rules (new function for extended validation)
 * @param array $data Data to validate
 * @param array $rules Validation rules
 * @return array ['valid' => bool, 'errors' => array]
 */
function validate_fields($data, $rules) {
    return getValidationService()->validate($data, $rules);
}