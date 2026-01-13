<?php
/**
 * Login Validator
 * 
 * Validates login form input format (not business rules)
 * Business rules like account lockout are handled in AuthService
 */

namespace Core\Validators;

class LoginValidator
{
    /**
     * Validate login form input
     * 
     * @param array $input
     * @return array Validation errors (empty if valid)
     */
    public function validateLoginInput(array $input): array
    {
        $errors = [];
        
        // Validate username
        $username = trim($input['username'] ?? '');
        if (empty($username)) {
            $errors['username'] = 'Username is required';
        } elseif (!$this->validateUsername($username)) {
            $errors['username'] = 'Username must be 3-50 characters and contain only letters, numbers, dots, underscores, and hyphens';
        }
        
        // Validate password - don't trim password
        $password = $input['password'] ?? '';
        if (empty($password)) {
            $errors['password'] = 'Password is required';
        } elseif (!$this->validatePassword($password)) {
            $errors['password'] = 'Password cannot be empty';
        }
        
        return $errors;
    }
    
    /**
     * Validate username format
     * 
     * @param string $username
     * @return bool
     */
    public function validateUsername(string $username): bool
    {
        // Username rules:
        // - 3-50 characters
        // - Alphanumeric plus . _ -
        // - Cannot start or end with special chars
        
        $length = strlen($username);
        if ($length < 3 || $length > 50) {
            return false;
        }
        
        // Check allowed characters
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            return false;
        }
        
        // Cannot start or end with special characters
        if (preg_match('/^[._-]|[._-]$/', $username)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate password format
     * 
     * @param string $password
     * @return bool
     */
    public function validatePassword(string $password): bool
    {
        // For login, we only check that password is not empty
        // Password complexity is enforced during registration/password change
        return !empty($password);
    }
    
    /**
     * Sanitize username for safe use
     * 
     * @param string $username
     * @return string
     */
    public function sanitizeUsername(string $username): string
    {
        // Remove any whitespace
        $username = trim($username);
        
        // Remove any characters that aren't allowed
        $username = preg_replace('/[^a-zA-Z0-9._-]/', '', $username);
        
        // Limit length
        return substr($username, 0, 50);
    }
}