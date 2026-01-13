<?php
/**
 * Validation Service
 * 
 * Provides input validation functionality
 */

namespace Core\Services;

class ValidationService extends BaseService
{
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return bool
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate username
     * 
     * @param string $username Username to validate
     * @return bool
     */
    public function validateUsername(string $username): bool
    {
        // Username must be 3-50 characters, alphanumeric with underscore, dash, dot
        return preg_match('/^[a-zA-Z0-9_\-\.]{3,50}$/', $username);
    }
    
    /**
     * Validate password strength
     * 
     * @param string $password Password to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validatePassword(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate phone number
     * 
     * @param string $phone Phone number to validate
     * @return bool
     */
    public function validatePhone(string $phone): bool
    {
        // Remove common formatting characters
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
        // Check if it's a valid US phone number (10 digits)
        return preg_match('/^[+]?1?[0-9]{10}$/', $phone);
    }
    
    /**
     * Validate date
     * 
     * @param string $date Date string
     * @param string $format Expected format
     * @return bool
     */
    public function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validate OTP code
     * 
     * @param string $otp OTP code to validate
     * @return bool
     */
    public function validateOtp(string $otp): bool
    {
        // OTP must be exactly 6 alphanumeric characters
        return preg_match('/^[A-Z0-9]{6}$/', strtoupper($otp));
    }
    
    /**
     * Validate UUID
     * 
     * @param string $uuid UUID to validate
     * @return bool
     */
    public function validateUuid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL to validate
     * @return bool
     */
    public function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate IP address
     * 
     * @param string $ip IP address to validate
     * @param bool $allowPrivate Allow private IP ranges
     * @return bool
     */
    public function validateIp(string $ip, bool $allowPrivate = false): bool
    {
        $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        if (!$allowPrivate) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Provided token
     * @param string $sessionToken Token from session
     * @return bool
     */
    public function validateCsrfToken(string $token, string $sessionToken): bool
    {
        if (empty($token) || empty($sessionToken)) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file $_FILES array element
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return array ['valid' => bool, 'error' => string]
     */
    public function validateFileUpload(array $file, array $allowedTypes = [], int $maxSize = 10485760): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['valid' => false, 'error' => 'No file uploaded'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['valid' => false, 'error' => 'File size exceeds limit'];
            default:
                return ['valid' => false, 'error' => 'Unknown upload error'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds maximum allowed'];
        }
        
        if (!empty($allowedTypes)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ['valid' => false, 'error' => 'File type not allowed'];
            }
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate input against regex pattern
     * 
     * @param string $input Input to validate
     * @param string $pattern Regex pattern
     * @return bool
     */
    public function validatePattern(string $input, string $pattern): bool
    {
        return preg_match($pattern, $input);
    }
    
    /**
     * Validate alphanumeric string
     * 
     * @param string $input Input to validate
     * @param bool $allowSpaces Allow spaces
     * @return bool
     */
    public function validateAlphanumeric(string $input, bool $allowSpaces = false): bool
    {
        $pattern = $allowSpaces ? '/^[a-zA-Z0-9\s]+$/' : '/^[a-zA-Z0-9]+$/';
        return preg_match($pattern, $input);
    }
    
    /**
     * Validate numeric input
     * 
     * @param mixed $input Input to validate
     * @param float|null $min Minimum value
     * @param float|null $max Maximum value
     * @return bool
     */
    public function validateNumber($input, ?float $min = null, ?float $max = null): bool
    {
        if (!is_numeric($input)) {
            return false;
        }
        
        $num = (float) $input;
        
        if ($min !== null && $num < $min) {
            return false;
        }
        
        if ($max !== null && $num > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate required field
     * 
     * @param mixed $input Input to validate
     * @return bool
     */
    public function validateRequired($input): bool
    {
        if (is_null($input)) {
            return false;
        }
        
        if (is_string($input) && trim($input) === '') {
            return false;
        }
        
        if (is_array($input) && empty($input)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate multiple fields with rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            // Convert string rules to array
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }
            
            foreach ($fieldRules as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
                
                if (!$this->checkRule($value, $ruleName, $ruleParams)) {
                    if (!isset($errors[$field])) {
                        $errors[$field] = [];
                    }
                    $errors[$field][] = $this->getErrorMessage($field, $ruleName, $ruleParams);
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check a single validation rule
     * 
     * @param mixed $value Value to check
     * @param string $rule Rule name
     * @param array $params Rule parameters
     * @return bool
     */
    private function checkRule($value, string $rule, array $params = []): bool
    {
        switch ($rule) {
            case 'required':
                return $this->validateRequired($value);
                
            case 'email':
                return $value === null || $this->validateEmail($value);
                
            case 'username':
                return $value === null || $this->validateUsername($value);
                
            case 'phone':
                return $value === null || $this->validatePhone($value);
                
            case 'date':
                $format = $params[0] ?? 'Y-m-d';
                return $value === null || $this->validateDate($value, $format);
                
            case 'uuid':
                return $value === null || $this->validateUuid($value);
                
            case 'url':
                return $value === null || $this->validateUrl($value);
                
            case 'numeric':
                return $value === null || is_numeric($value);
                
            case 'min':
                $min = (float) ($params[0] ?? 0);
                return $value === null || (is_numeric($value) && (float) $value >= $min);
                
            case 'max':
                $max = (float) ($params[0] ?? PHP_FLOAT_MAX);
                return $value === null || (is_numeric($value) && (float) $value <= $max);
                
            case 'minlength':
                $min = (int) ($params[0] ?? 0);
                return $value === null || (is_string($value) && strlen($value) >= $min);
                
            case 'maxlength':
                $max = (int) ($params[0] ?? PHP_INT_MAX);
                return $value === null || (is_string($value) && strlen($value) <= $max);
                
            case 'in':
                return $value === null || in_array($value, $params, true);
                
            case 'regex':
                $pattern = $params[0] ?? '';
                return $value === null || $this->validatePattern($value, $pattern);
                
            default:
                return true;
        }
    }
    
    /**
     * Get error message for validation rule
     * 
     * @param string $field Field name
     * @param string $rule Rule name
     * @param array $params Rule parameters
     * @return string
     */
    private function getErrorMessage(string $field, string $rule, array $params = []): string
    {
        $fieldLabel = ucfirst(str_replace('_', ' ', $field));
        
        switch ($rule) {
            case 'required':
                return "{$fieldLabel} is required";
                
            case 'email':
                return "{$fieldLabel} must be a valid email address";
                
            case 'username':
                return "{$fieldLabel} must be 3-50 characters and contain only letters, numbers, underscore, dash, or dot";
                
            case 'phone':
                return "{$fieldLabel} must be a valid phone number";
                
            case 'date':
                $format = $params[0] ?? 'Y-m-d';
                return "{$fieldLabel} must be a valid date in format {$format}";
                
            case 'uuid':
                return "{$fieldLabel} must be a valid UUID";
                
            case 'url':
                return "{$fieldLabel} must be a valid URL";
                
            case 'numeric':
                return "{$fieldLabel} must be a number";
                
            case 'min':
                return "{$fieldLabel} must be at least {$params[0]}";
                
            case 'max':
                return "{$fieldLabel} must be no more than {$params[0]}";
                
            case 'minlength':
                return "{$fieldLabel} must be at least {$params[0]} characters long";
                
            case 'maxlength':
                return "{$fieldLabel} must be no more than {$params[0]} characters long";
                
            case 'in':
                return "{$fieldLabel} must be one of: " . implode(', ', $params);
                
            case 'regex':
                return "{$fieldLabel} format is invalid";
                
            default:
                return "{$fieldLabel} is invalid";
        }
    }
}