<?php
/**
 * Two-Factor Authentication ViewModel
 * 
 * Coordinates 2FA/OTP workflow and prepares data for view
 * Delegates business logic to AuthService
 */

namespace ViewModel;

use Core\Services\AuthService;
use Core\Services\AuditService;
use Core\Validators\OTPValidator;
use App\Core\Session;

class TwoFactorViewModel
{
    private AuthService $authService;
    private AuditService $auditService;
    private OTPValidator $validator;
    
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->auditService = new AuditService();
        $this->validator = new OTPValidator();
    }
    
    /**
     * Handle OTP verification
     * 
     * @param array $input Sanitized input from router (otp_code/code, csrf_token)
     * @return array Response with success status and redirect info
     */
    public function handleOTPVerification(array $input): array
    {
        // Validate OTP format
        $validationErrors = $this->validator->validateOTPInput($input);
        
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'errors' => $validationErrors,
                'error' => 'Please enter a valid 6-digit verification code'
            ];
        }
        
        // Get sanitized OTP code
        $otpCode = $this->validator->sanitizeOTP($input['otp_code'] ?? $input['code'] ?? '');
        
        // Attempt OTP verification
        $verifyResult = $this->authService->verify2FA($otpCode);
        
        if ($verifyResult['success']) {
            return [
                'success' => true,
                'message' => 'Verification successful',
                'redirect' => $verifyResult['data']['dashboard_url'] ?? $this->getDefaultRedirectUrl()
            ];
        } else {
            return [
                'success' => false,
                'error' => $verifyResult['message'] ?? 'Invalid or expired verification code',
                'can_resend' => true
            ];
        }
    }
    
    /**
     * Get 2FA page data
     * 
     * @return array Data for 2FA view
     */
    public function get2FAPageData(): array
    {
        // Get pending 2FA session data
        $pending2FA = Session::getPending2FA();
        
        if (!$pending2FA) {
            // No pending 2FA session
            return [
                'error' => 'No active verification session. Please login again.',
                'redirect_to_login' => true,
                'csrf_token' => $this->authService->getCsrfToken()
            ];
        }
        
        // Mask email for privacy
        $maskedEmail = $this->maskEmail($pending2FA['email'] ?? '');
        
        // Get any OTP message from session (for development/testing)
        $otpMessage = Session::get('otp_message');
        Session::remove('otp_message'); // Clear after reading
        
        return [
            'csrf_token' => $this->authService->getCsrfToken(),
            'page_title' => 'Two-Factor Authentication - SafeShift EHR',
            'masked_email' => $maskedEmail,
            'username' => $pending2FA['username'] ?? '',
            'otp_message' => $otpMessage,
            'can_resend' => true,
            'resend_cooldown' => 60, // seconds
            'otp_expiry_minutes' => 15,
            'max_attempts' => 3 // Not enforced here, just for UI
        ];
    }
    
    /**
     * Handle OTP resend request
     * 
     * @return array Response with success status
     */
    public function resendOTP(): array
    {
        $result = $this->authService->resendOtp();
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => $result['data']['message'] ?? 'A new verification code has been sent to your email'
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['message'] ?? 'Failed to resend verification code. Please try again.'
            ];
        }
    }
    
    /**
     * Mask email address for privacy
     * 
     * @param string $email
     * @return string Masked email (e.g., j***n@example.com)
     */
    private function maskEmail(string $email): string
    {
        if (empty($email)) {
            return 'your registered email';
        }
        
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return 'your registered email';
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        if (strlen($username) <= 2) {
            // If username is too short, mask everything
            $masked = str_repeat('*', strlen($username));
        } else {
            // Show first and last character, mask the rest
            $masked = $username[0] . str_repeat('*', strlen($username) - 2) . $username[strlen($username) - 1];
        }
        
        return $masked . '@' . $domain;
    }
    
    /**
     * Get default redirect URL after successful 2FA
     * 
     * @return string
     */
    private function getDefaultRedirectUrl(): string
    {
        return '/dashboard';
    }
    
    /**
     * Check if there's an active 2FA session
     * 
     * @return bool
     */
    public function hasActive2FASession(): bool
    {
        $pending2FA = Session::getPending2FA();
        return !empty($pending2FA);
    }
    
    /**
     * Get remaining time for current OTP session
     * 
     * @return int Seconds remaining, or 0 if expired/no session
     */
    public function getOTPTimeRemaining(): int
    {
        $pending2FA = Session::getPending2FA();
        
        if (!$pending2FA || !isset($pending2FA['created_at'])) {
            return 0;
        }
        
        $expiryTime = $pending2FA['created_at'] + (15 * 60); // 15 minutes
        $remaining = $expiryTime - time();
        
        return max(0, $remaining);
    }
}