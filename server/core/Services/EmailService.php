<?php
/**
 * Email Service
 *
 * Handles all email communications via Amazon SES SMTP
 *
 * Configuration is loaded from environment variables via Api\Config\MailConfig
 * Required env vars: SES_SMTP_HOST, SES_SMTP_PORT, SES_SMTP_USER, SES_SMTP_PASS,
 *                    SES_SMTP_FROM_EMAIL, SES_SMTP_FROM_NAME
 */

namespace Core\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include the mail config
require_once dirname(__DIR__, 2) . '/api/config/mail.php';

use Api\Config\MailConfig;

class EmailService extends BaseService
{
    private ?PHPMailer $mailer = null;
    
    /**
     * Initialize PHPMailer with Amazon SES SMTP settings
     *
     * Configuration is loaded from environment variables via MailConfig
     *
     * @return PHPMailer|null Configured mailer instance
     */
    private function getMailer(): ?PHPMailer
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }
        
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->logError('PHPMailer not installed. Run: composer install');
            return null;
        }
        
        try {
            // Load SES SMTP configuration from environment variables
            $mailConfig = MailConfig::get();
            
            $mail = new PHPMailer(true);
            
            // Server settings - Amazon SES SMTP
            $mail->isSMTP();
            $mail->Host       = $mailConfig['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailConfig['user'];
            $mail->Password   = $mailConfig['pass'];
            $mail->Port       = $mailConfig['port'];
            
            // Prevent SMTP blocking from causing request timeouts
            $mail->Timeout = $mailConfig['timeout'] ?? 10;
            // Don't keep SMTP connection alive between sends
            $mail->SMTPKeepAlive = false;
            
            // Encryption - Amazon SES uses STARTTLS on port 587
            if ($mailConfig['port'] == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Amazon SES requires proper SSL verification (unlike GoDaddy)
            // For production, keep verify_peer true. Only disable for local testing.
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false
                ]
            ];
            
            // Debug output - ALWAYS OFF for HTTP responses to prevent corrupting JSON
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            
            // Enable SMTP debugging to file in development
            if ($this->isDevelopment() && getenv('SMTP_DEBUG') === 'true') {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function($str, $level) {
                    $logFile = dirname(__DIR__, 2) . '/logs/email_smtp.log';
                    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $str . "\n", FILE_APPEND | LOCK_EX);
                };
            }
            
            // Set default sender from SES config
            $mail->setFrom(
                $mailConfig['from_email'],
                $mailConfig['from_name']
            );
            $mail->addReplyTo(
                $mailConfig['from_email'],
                $mailConfig['from_name'] . ' Support'
            );
            
            // Set email format
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            
            $this->mailer = $mail;
            
            $this->log('Mailer initialized with SES SMTP', [
                'host' => $mailConfig['host'],
                'port' => $mailConfig['port'],
                'from' => $mailConfig['from_email']
            ]);
            
            return $mail;
            
        } catch (\RuntimeException $e) {
            // Config validation failed (missing env vars)
            $this->logError('Mail configuration error', ['error' => $e->getMessage()]);
            return null;
        } catch (Exception $e) {
            $this->logError('Failed to initialize mailer', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Send OTP email to user
     * 
     * @param string $email Recipient email
     * @param string $otpCode The OTP code
     * @param string $username Username for personalization
     * @return array Result with success status and message
     */
    public function sendOtp(string $email, string $otpCode, string $username = ''): array
    {
        try {
            $mail = $this->getMailer();
            
            if (!$mail) {
                throw new Exception('Mail system unavailable');
            }
            
            // Clear any previous recipients
            $mail->clearAddresses();
            
            // Recipients
            $mail->addAddress($email, $username);
            
            // Content
            $mail->Subject = 'Your SafeShift Login Verification Code';
            
            // HTML body
            $mail->Body = $this->getOtpEmailTemplate($otpCode, $username);
            
            // Plain text alternative
            $mail->AltBody = $this->getOtpEmailPlainText($otpCode);
            
            // Send email
            $mail->send();
            
            // Log success
            $this->log('OTP email sent', [
                'recipient' => substr($email, 0, 3) . '***',
                'username' => $username
            ]);
            
            return $this->formatResponse(true, [], 'Verification code sent successfully');
            
        } catch (Exception $e) {
            $this->logError('Failed to send OTP', [
                'error' => $e->getMessage(),
                'recipient' => substr($email, 0, 3) . '***'
            ]);
            
            return $this->formatResponse(false, [], 'Failed to send verification code', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send password reset email
     * 
     * @param string $email Recipient email
     * @param string $resetToken Reset token
     * @param string $username Username
     * @return array Result with success status
     */
    public function sendPasswordReset(string $email, string $resetToken, string $username = ''): array
    {
        try {
            $mail = $this->getMailer();
            
            if (!$mail) {
                throw new Exception('Mail system unavailable');
            }
            
            // Clear any previous recipients
            $mail->clearAddresses();
            
            // Recipients
            $mail->addAddress($email, $username);
            
            // Content
            $mail->Subject = 'Password Reset Request - SafeShift EHR';
            
            $resetUrl = $this->getConfig('APP_URL', 'https://1stresponse.safeshift.ai') . 
                       '/reset-password?token=' . $resetToken;
            
            // HTML body
            $mail->Body = $this->getPasswordResetEmailTemplate($resetUrl, $username);
            
            // Plain text alternative
            $mail->AltBody = $this->getPasswordResetEmailPlainText($resetUrl);
            
            // Send email
            $mail->send();
            
            $this->log('Password reset email sent', [
                'recipient' => substr($email, 0, 3) . '***',
                'username' => $username
            ]);
            
            return $this->formatResponse(true, [], 'Password reset email sent');
            
        } catch (Exception $e) {
            $this->logError('Failed to send password reset', [
                'error' => $e->getMessage(),
                'recipient' => substr($email, 0, 3) . '***'
            ]);
            
            return $this->formatResponse(false, [], 'Failed to send password reset email', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send general notification email
     * 
     * @param string $email Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML body content
     * @param string $plainBody Plain text body
     * @param string $recipientName Recipient name
     * @return array
     */
    public function sendNotification(
        string $email, 
        string $subject, 
        string $htmlBody, 
        string $plainBody = '',
        string $recipientName = ''
    ): array {
        try {
            $mail = $this->getMailer();
            
            if (!$mail) {
                throw new Exception('Mail system unavailable');
            }
            
            // Clear any previous recipients
            $mail->clearAddresses();
            
            // Recipients
            $mail->addAddress($email, $recipientName);
            
            // Content
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody ?: strip_tags($htmlBody);
            
            // Send email
            $mail->send();
            
            $this->log('Notification email sent', [
                'recipient' => substr($email, 0, 3) . '***',
                'subject' => $subject
            ]);
            
            return $this->formatResponse(true, [], 'Email sent successfully');
            
        } catch (Exception $e) {
            $this->logError('Failed to send notification', [
                'error' => $e->getMessage(),
                'recipient' => substr($email, 0, 3) . '***',
                'subject' => $subject
            ]);
            
            return $this->formatResponse(false, [], 'Failed to send email', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Test email configuration
     * 
     * @param string $testEmail Email address to send test to
     * @return array Test results
     */
    public function testEmailConfiguration(string $testEmail = 'test@safeshift.ai'): array
    {
        try {
            $mail = $this->getMailer();
            
            if (!$mail) {
                return $this->formatResponse(false, [], 'PHPMailer not installed');
            }
            
            // Enable debug for testing
            $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
            
            // Clear any previous recipients
            $mail->clearAddresses();
            
            // Test recipient
            $mail->addAddress($testEmail, 'Test User');
            $mail->Subject = 'Test Email - SafeShift EHR';
            $mail->Body = '<h1>Test Email</h1><p>This is a test email from SafeShift EHR.</p>' .
                         '<p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
            $mail->AltBody = 'This is a test email from SafeShift EHR. Sent at: ' . date('Y-m-d H:i:s');
            
            if ($mail->send()) {
                return $this->formatResponse(true, [
                    'recipient' => $testEmail,
                    'sent_at' => date('Y-m-d H:i:s')
                ], 'Test email sent successfully');
            } else {
                return $this->formatResponse(false, [
                    'error' => $mail->ErrorInfo
                ], 'Failed to send test email');
            }
            
        } catch (Exception $e) {
            return $this->formatResponse(false, [
                'error' => $e->getMessage(),
                'trace' => $this->isDevelopment() ? $e->getTraceAsString() : null
            ], 'Email test failed');
        }
    }
    
    /**
     * Get OTP email HTML template
     * 
     * @param string $otpCode
     * @param string $username
     * @return string
     */
    private function getOtpEmailTemplate(string $otpCode, string $username = ''): string
    {
        $appName = $this->getConfig('APP_NAME', 'SafeShift EHR');
        
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a73e8; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 30px; margin: 20px 0; }
        .otp-code { 
            background: #fff; 
            border: 2px solid #1a73e8; 
            padding: 15px; 
            font-size: 24px; 
            text-align: center; 
            letter-spacing: 5px; 
            margin: 20px 0;
            font-weight: bold;
        }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        .warning { color: #d93025; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($appName) . '</h1>
        </div>
        <div class="content">
            <h2>Login Verification Required</h2>
            <p>Hello' . ($username ? ' ' . htmlspecialchars($username) : '') . ',</p>
            <p>Your verification code for ' . htmlspecialchars($appName) . ' login is:</p>
            <div class="otp-code">' . htmlspecialchars($otpCode) . '</div>
            <p>This code will expire in <strong>10 minutes</strong>.</p>
            <p class="warning">⚠️ Do not share this code with anyone. SafeShift staff will never ask for your verification code.</p>
            <p>If you did not request this code, please ignore this email and ensure your account is secure.</p>
        </div>
        <div class="footer">
            <p>This is an automated message from ' . htmlspecialchars($appName) . ' System.<br>
            Please do not reply to this email.</p>
            <p>&copy; ' . date('Y') . ' SafeShift, LLC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get OTP email plain text
     * 
     * @param string $otpCode
     * @return string
     */
    private function getOtpEmailPlainText(string $otpCode): string
    {
        $appName = $this->getConfig('APP_NAME', 'SafeShift EHR');
        
        return "Your {$appName} login verification code is: {$otpCode}\n\n" .
               "This code will expire in 10 minutes.\n\n" .
               "Do not share this code with anyone.\n\n" .
               "If you did not request this code, please ignore this email.";
    }
    
    /**
     * Get password reset email HTML template
     * 
     * @param string $resetUrl
     * @param string $username
     * @return string
     */
    private function getPasswordResetEmailTemplate(string $resetUrl, string $username = ''): string
    {
        $appName = $this->getConfig('APP_NAME', 'SafeShift EHR');
        
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a73e8; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 30px; margin: 20px 0; }
        .button { 
            display: inline-block; 
            padding: 12px 30px; 
            background: #1a73e8; 
            color: white; 
            text-decoration: none; 
            border-radius: 5px; 
            margin: 20px 0;
        }
        .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($appName) . '</h1>
        </div>
        <div class="content">
            <h2>Password Reset Request</h2>
            <p>Hello' . ($username ? ' ' . htmlspecialchars($username) : '') . ',</p>
            <p>We received a request to reset your password. Click the button below to create a new password:</p>
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($resetUrl) . '" class="button">Reset Password</a>
            </p>
            <p>This link will expire in <strong>1 hour</strong>.</p>
            <p>If you did not request this reset, please ignore this email. Your password will remain unchanged.</p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' SafeShift, LLC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get password reset email plain text
     *
     * @param string $resetUrl
     * @return string
     */
    private function getPasswordResetEmailPlainText(string $resetUrl): string
    {
        return "Password Reset Request\n\n" .
               "Click the following link to reset your password:\n" .
               $resetUrl . "\n\n" .
               "This link will expire in 1 hour.\n\n" .
               "If you did not request this reset, please ignore this email.";
    }
    
    // ============================================================================
    // Work-Related Incident Notification Methods
    // ============================================================================
    
    /**
     * Send work-related incident notification emails to configured recipients
     *
     * @param int $encounterId The encounter ID
     * @param int $clinicId The clinic ID
     * @param array $incidentData Data about the incident (HIPAA-safe only)
     * @return array Result with success status and sent count
     */
    public function sendWorkRelatedIncidentNotification(
        int $encounterId,
        int $clinicId,
        array $incidentData
    ): array {
        try {
            // Get configured recipients for this clinic
            $recipients = $this->getClinicEmailRecipients($clinicId);
            
            if (empty($recipients)) {
                $this->log('No email recipients configured for clinic', ['clinic_id' => $clinicId]);
                return $this->formatResponse(false, [], 'No email recipients configured for this clinic');
            }
            
            // Build email content (HIPAA-safe, non-sensitive)
            $emailContent = $this->buildWorkRelatedEmailContent($incidentData);
            
            // Send to all recipients and collect results
            $results = [];
            $successCount = 0;
            $failedCount = 0;
            $sentEmails = [];
            
            foreach ($recipients as $recipient) {
                $sendResult = $this->sendWorkRelatedEmail(
                    $recipient['email_address'],
                    $recipient['recipient_name'] ?? '',
                    $emailContent['subject'],
                    $emailContent['html_body'],
                    $emailContent['plain_body']
                );
                
                // Log the email send attempt
                $this->logEmailSend(
                    $encounterId,
                    $recipient['email_address'],
                    $emailContent['subject'],
                    substr($emailContent['plain_body'], 0, 500),
                    $sendResult['success'] ? 'sent' : 'failed',
                    $sendResult['error'] ?? null
                );
                
                if ($sendResult['success']) {
                    $successCount++;
                    $sentEmails[] = $this->maskEmail($recipient['email_address']);
                } else {
                    $failedCount++;
                }
                
                $results[] = [
                    'email' => $this->maskEmail($recipient['email_address']),
                    'success' => $sendResult['success'],
                    'error' => $sendResult['error'] ?? null
                ];
            }
            
            $this->log('Work-related notification emails sent', [
                'encounter_id' => $encounterId,
                'clinic_id' => $clinicId,
                'success_count' => $successCount,
                'failed_count' => $failedCount
            ]);
            
            return $this->formatResponse(true, [
                'sent_count' => $successCount,
                'failed_count' => $failedCount,
                'total_recipients' => count($recipients),
                'sent_to' => $sentEmails,
                'results' => $results
            ], "Sent $successCount of " . count($recipients) . " notification emails");
            
        } catch (Exception $e) {
            $this->logError('Failed to send work-related notifications', [
                'encounter_id' => $encounterId,
                'clinic_id' => $clinicId,
                'error' => $e->getMessage()
            ]);
            
            return $this->formatResponse(false, [], 'Failed to send notifications', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get email recipients configured for a clinic
     *
     * @param int $clinicId
     * @return array Array of recipient records
     */
    private function getClinicEmailRecipients(int $clinicId): array
    {
        try {
            $sql = "SELECT id, email_address, recipient_name, recipient_type
                    FROM clinic_email_recipients
                    WHERE clinic_id = :clinic_id
                    AND is_active = 1
                    AND recipient_type IN ('work_related', 'all')
                    ORDER BY recipient_name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['clinic_id' => $clinicId]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->logError('Failed to get clinic email recipients', [
                'clinic_id' => $clinicId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Build HIPAA-compliant email content for work-related incident
     *
     * IMPORTANT: This method MUST NOT include any PHI (Protected Health Information):
     * - NO patient names or identifiers
     * - NO specific injury details
     * - NO medical information
     * - NO social security numbers
     * - NO contact information
     *
     * @param array $data Incident data
     * @return array Array with subject, html_body, and plain_body
     */
    private function buildWorkRelatedEmailContent(array $data): array
    {
        $clinicName = htmlspecialchars($data['clinic_name'] ?? 'Unknown Clinic');
        $date = $data['date'] ?? date('Y-m-d');
        $formattedDate = date('F j, Y', strtotime($date));
        $city = htmlspecialchars($data['city'] ?? '');
        $state = htmlspecialchars($data['state'] ?? '');
        $locationDescription = htmlspecialchars($data['location_description'] ?? 'Not specified');
        
        // Truncate narrative to first 200 chars for HIPAA safety
        $narrative = $data['narrative'] ?? '';
        $narrativeTruncated = mb_substr(strip_tags($narrative), 0, 200);
        if (strlen($narrative) > 200) {
            $narrativeTruncated .= '...';
        }
        $narrativeTruncated = htmlspecialchars($narrativeTruncated);
        
        $locationString = $city;
        if ($state) {
            $locationString .= $locationString ? ", $state" : $state;
        }
        
        // Email subject
        $subject = "Work-Related Incident Report - {$clinicName} - {$formattedDate}";
        
        // HTML body
        $htmlBody = $this->getWorkRelatedEmailTemplate([
            'clinic_name' => $clinicName,
            'date' => $formattedDate,
            'location' => $locationString,
            'location_description' => $locationDescription,
            'narrative' => $narrativeTruncated
        ]);
        
        // Plain text body
        $plainBody = $this->getWorkRelatedEmailPlainText([
            'clinic_name' => $clinicName,
            'date' => $formattedDate,
            'location' => $locationString,
            'location_description' => $locationDescription,
            'narrative' => $narrativeTruncated
        ]);
        
        return [
            'subject' => $subject,
            'html_body' => $htmlBody,
            'plain_body' => $plainBody
        ];
    }
    
    /**
     * Get work-related incident email HTML template
     *
     * @param array $data Template data
     * @return string HTML email body
     */
    private function getWorkRelatedEmailTemplate(array $data): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #003366; color: white; padding: 20px; text-align: center; }
        .header h2 { margin: 0; font-size: 20px; }
        .content { padding: 20px; background: #ffffff; }
        .content p { margin: 0 0 15px 0; }
        .info-row { margin-bottom: 12px; padding: 10px; background: #f8f9fa; border-left: 3px solid #003366; }
        .label { font-weight: bold; color: #003366; display: block; margin-bottom: 3px; }
        .value { color: #333; }
        .footer { background: #f5f5f5; padding: 15px 20px; font-size: 12px; color: #666; text-align: center; }
        .footer p { margin: 5px 0; }
        .action-notice { background: #e8f4f8; border: 1px solid #b8daff; padding: 15px; border-radius: 4px; margin-top: 20px; }
        .action-notice strong { color: #004085; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>SafeShift EHR - Work-Related Incident Report</h2>
        </div>
        <div class="content">
            <p>A work-related incident report has been filed and finalized.</p>
            
            <div class="info-row">
                <span class="label">Date of Incident:</span>
                <span class="value">' . $data['date'] . '</span>
            </div>
            
            <div class="info-row">
                <span class="label">Location:</span>
                <span class="value">' . $data['clinic_name'] . ($data['location'] ? ', ' . $data['location'] : '') . '</span>
            </div>
            
            <div class="info-row">
                <span class="label">General Location of Incident:</span>
                <span class="value">' . $data['location_description'] . '</span>
            </div>
            
            <div class="info-row">
                <span class="label">Brief Summary:</span>
                <span class="value">' . $data['narrative'] . '</span>
            </div>
            
            <div class="action-notice">
                <strong>For full details, please log into the SafeShift EHR system.</strong>
                <p style="margin: 10px 0 0 0; font-size: 13px;">This notification contains limited information for privacy compliance. Complete incident details are available in the system.</p>
            </div>
        </div>
        <div class="footer">
            <p>This is an automated notification from SafeShift EHR. Do not reply to this email.</p>
            <p>&copy; ' . date('Y') . ' SafeShift EHR. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get work-related incident email plain text version
     *
     * @param array $data Template data
     * @return string Plain text email body
     */
    private function getWorkRelatedEmailPlainText(array $data): string
    {
        return "SafeShift EHR - Work-Related Incident Report\n" .
               "============================================\n\n" .
               "A work-related incident report has been filed and finalized.\n\n" .
               "Date of Incident: " . $data['date'] . "\n" .
               "Location: " . $data['clinic_name'] . ($data['location'] ? ', ' . $data['location'] : '') . "\n" .
               "General Location of Incident: " . $data['location_description'] . "\n" .
               "Brief Summary: " . $data['narrative'] . "\n\n" .
               "For full details, please log into the SafeShift EHR system.\n\n" .
               "---\n" .
               "This is an automated notification from SafeShift EHR.\n" .
               "Do not reply to this email.\n" .
               "(c) " . date('Y') . " SafeShift EHR. All rights reserved.";
    }
    
    /**
     * Send a single work-related email
     *
     * @param string $email Recipient email
     * @param string $recipientName Recipient name
     * @param string $subject Email subject
     * @param string $htmlBody HTML body
     * @param string $plainBody Plain text body
     * @return array Result with success status
     */
    private function sendWorkRelatedEmail(
        string $email,
        string $recipientName,
        string $subject,
        string $htmlBody,
        string $plainBody
    ): array {
        try {
            $mail = $this->getMailer();
            
            if (!$mail) {
                throw new Exception('Mail system unavailable');
            }
            
            // Clear any previous recipients
            $mail->clearAddresses();
            
            // Recipients
            $mail->addAddress($email, $recipientName);
            
            // Content
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody;
            
            // Send email
            $mail->send();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->logError('Failed to send work-related email', [
                'recipient' => $this->maskEmail($email),
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Log email send attempt to database
     *
     * @param int $encounterId
     * @param string $recipientEmail
     * @param string $subject
     * @param string $bodyPreview
     * @param string $status
     * @param string|null $errorMessage
     */
    private function logEmailSend(
        int $encounterId,
        string $recipientEmail,
        string $subject,
        string $bodyPreview,
        string $status,
        ?string $errorMessage = null
    ): void {
        try {
            $sql = "INSERT INTO email_logs (
                        encounter_id, recipient_email, email_type, subject,
                        body_preview, status, error_message, sent_at, created_by
                    ) VALUES (
                        :encounter_id, :recipient_email, 'work_related_notification', :subject,
                        :body_preview, :status, :error_message, :sent_at, :created_by
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'encounter_id' => $encounterId,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'body_preview' => $bodyPreview,
                'status' => $status,
                'error_message' => $errorMessage,
                'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
                'created_by' => $this->getCurrentUserId()
            ]);
            
        } catch (Exception $e) {
            $this->logError('Failed to log email send', [
                'encounter_id' => $encounterId,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get email logs for an encounter
     *
     * @param int $encounterId
     * @return array
     */
    public function getEmailLogs(int $encounterId): array
    {
        try {
            $sql = "SELECT id, recipient_email, email_type, subject, status,
                           error_message, sent_at, created_at
                    FROM email_logs
                    WHERE encounter_id = :encounter_id
                    ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['encounter_id' => $encounterId]);
            
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Mask emails in response
            foreach ($logs as &$log) {
                $log['recipient_email'] = $this->maskEmail($log['recipient_email']);
            }
            
            return $this->formatResponse(true, ['logs' => $logs]);
            
        } catch (Exception $e) {
            $this->logError('Failed to get email logs', [
                'encounter_id' => $encounterId,
                'error' => $e->getMessage()
            ]);
            
            return $this->formatResponse(false, [], 'Failed to retrieve email logs');
        }
    }
    
    /**
     * Mask email address for privacy
     *
     * @param string $email
     * @return string Masked email (e.g., "joh***@example.com")
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        $maskedName = strlen($name) > 3
            ? substr($name, 0, 3) . '***'
            : $name[0] . '***';
        
        return $maskedName . '@' . $domain;
    }
}