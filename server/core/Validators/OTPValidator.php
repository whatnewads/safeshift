<?php
/**
 * OTP Validator
 * 
 * Validates OTP/2FA input format (not business rules)
 * Business rules like OTP expiry are handled in OTPService
 */

namespace Core\Validators;

class OTPValidator
{
    /**
     * Validate OTP input
     * 
     * @param array $input
     * @return array Validation errors (empty if valid)
     */
    public function validateOTPInput(array $input): array
    {
        $errors = [];
        
        // Get OTP code from input
        $otpCode = trim($input['otp_code'] ?? $input['code'] ?? '');
        
        if (empty($otpCode)) {
            $errors['otp_code'] = 'Verification code is required';
        } elseif (!$this->validateOTPFormat($otpCode)) {
            $errors['otp_code'] = 'Verification code must be exactly 6 digits';
        }
        
        return $errors;
    }
    
    /**
     * Validate OTP format
     * 
     * @param string $otp
     * @return bool
     */
    public function validateOTPFormat(string $otp): bool
    {
        // OTP must be exactly 6 digits
        return preg_match('/^[0-9]{6}$/', $otp) === 1;
    }
    
    /**
     * Sanitize OTP code
     * 
     * @param string $otp
     * @return string
     */
    public function sanitizeOTP(string $otp): string
    {
        // Remove any non-numeric characters
        $otp = preg_replace('/[^0-9]/', '', $otp);
        
        // Limit to 6 digits
        return substr($otp, 0, 6);
    }
    
    /**
     * Check if resend request is valid
     * 
     * @param array $input
     * @return array Validation errors
     */
    public function validateResendRequest(array $input): array
    {
        $errors = [];
        
        // For resend, we just need to ensure there's a valid session
        // The actual rate limiting is done in the service layer
        
        // Optional: validate any additional resend parameters
        
        return $errors;
    }
}