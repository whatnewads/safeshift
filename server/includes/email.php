<?php
/**
 * Email Functions - REFACTORED VERSION
 * 
 * This file provides backward compatibility while using the new EmailService
 * All functions now delegate to the EmailService in Core layer
 */

namespace App\email;

use Core\Services\EmailService;
use Exception;

// Initialize service (singleton pattern for backward compatibility)
$emailService = null;

/**
 * Get EmailService instance
 */
function getEmailService(): EmailService {
    global $emailService;
    if ($emailService === null) {
        $emailService = new EmailService();
    }
    return $emailService;
}

/**
 * Get mailer instance (deprecated - for backward compatibility only)
 * @return null
 */
function get_mailer() {
    // This function is deprecated
    // EmailService handles mailer initialization internally
    return null;
}

/**
 * Send OTP email to user
 * @param string $email Recipient email
 * @param string $otp_code The OTP code
 * @param string $username Username for personalization
 * @return array Result with success status and message
 */
function send_otp_email($email, $otp_code, $username = '') {
    try {
        $result = getEmailService()->sendOtp($email, $otp_code, $username);
        
        // Convert to legacy format for backward compatibility
        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'error' => $result['error'] ?? null
        ];
    } catch (Exception $e) {
        error_log("[EMAIL] Error in send_otp_email wrapper: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send verification code',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send password reset email
 * @param string $email Recipient email
 * @param string $reset_token Reset token
 * @param string $username Username
 * @return array Result with success status
 */
function send_password_reset($email, $reset_token, $username = '') {
    try {
        $result = getEmailService()->sendPasswordReset($email, $reset_token, $username);
        
        // Convert to legacy format
        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'error' => $result['error'] ?? null
        ];
    } catch (Exception $e) {
        error_log("[EMAIL] Error in send_password_reset wrapper: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send password reset email',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Test email configuration
 * @return array Test results
 */
function test_email() {
    try {
        return getEmailService()->testEmailConfiguration();
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Email test failed',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send general notification email (new wrapper function)
 * @param string $email Recipient email
 * @param string $subject Email subject
 * @param string $html_body HTML body content
 * @param string $plain_body Plain text body
 * @param string $recipient_name Recipient name
 * @return array
 */
function send_notification($email, $subject, $html_body, $plain_body = '', $recipient_name = '') {
    try {
        return getEmailService()->sendNotification($email, $subject, $html_body, $plain_body, $recipient_name);
    } catch (Exception $e) {
        error_log("[EMAIL] Error in send_notification wrapper: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send email',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send work-related incident notification emails
 * @param int $encounter_id Encounter ID
 * @param int $clinic_id Clinic ID
 * @param array $incident_data Incident data (HIPAA-safe only)
 * @return array Result with success status and sent count
 */
function send_work_related_notification($encounter_id, $clinic_id, $incident_data) {
    try {
        return getEmailService()->sendWorkRelatedIncidentNotification(
            $encounter_id,
            $clinic_id,
            $incident_data
        );
    } catch (Exception $e) {
        error_log("[EMAIL] Error in send_work_related_notification wrapper: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send work-related notifications',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get email logs for an encounter
 * @param int $encounter_id Encounter ID
 * @return array
 */
function get_email_logs($encounter_id) {
    try {
        return getEmailService()->getEmailLogs($encounter_id);
    } catch (Exception $e) {
        error_log("[EMAIL] Error in get_email_logs wrapper: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to get email logs',
            'error' => $e->getMessage()
        ];
    }
}