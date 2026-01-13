<?php

declare(strict_types=1);

/**
 * SMS Send Reminder API Endpoint
 *
 * POST /api/v1/sms/send-reminder
 * Sends an appointment reminder SMS to a patient
 *
 * Request:
 * {
 *   patient_id: int,
 *   encounter_id: int (optional),
 *   phone_number: string,
 *   appointment_date: string,
 *   appointment_time: string,
 *   clinic_name: string
 * }
 *
 * Response:
 * Success: { success: true, message_id: string }
 * Failure: { success: false, error: string }
 *
 * @package SafeShift\API\v1\SMS
 */

// Bootstrap if not already loaded
if (!defined('BOOTSTRAP_LOADED')) {
    require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
}

use ViewModel\Core\ApiResponse;

// Set JSON content type
header('Content-Type: application/json; charset=utf-8');

// CORS Headers
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:3000',
    'http://127.0.0.1:3000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-XSRF-Token, Authorization');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    exit;
}

// Check authentication
if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
    ApiResponse::send(ApiResponse::error('Authentication required'), 401);
    exit;
}

$userId = $_SESSION['user']['user_id'];

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    ApiResponse::send(ApiResponse::error('Invalid JSON request body'), 400);
    exit;
}

// Validate required fields
$requiredFields = ['patient_id', 'phone_number', 'appointment_date', 'appointment_time', 'clinic_name'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    ApiResponse::send(ApiResponse::error('Missing required fields: ' . implode(', ', $missingFields)), 400);
    exit;
}

// Validate phone number format
$phoneNumber = preg_replace('/[^0-9+]/', '', $input['phone_number']);
if (strlen($phoneNumber) < 10) {
    ApiResponse::send(ApiResponse::error('Invalid phone number format'), 400);
    exit;
}

// Format the phone number for SMS (add +1 if US number without country code)
if (strlen($phoneNumber) === 10) {
    $phoneNumber = '+1' . $phoneNumber;
} elseif (strpos($phoneNumber, '+') !== 0) {
    $phoneNumber = '+' . $phoneNumber;
}

// Build the message content
$appointmentDate = date('l, F j, Y', strtotime($input['appointment_date']));
$appointmentTime = date('g:i A', strtotime($input['appointment_time']));
$clinicName = htmlspecialchars($input['clinic_name'], ENT_QUOTES, 'UTF-8');

$messageContent = "SafeShift Reminder: You have a follow-up appointment on {$appointmentDate} at {$appointmentTime} at {$clinicName}. Reply STOP to opt out.";

// Get PDO instance
try {
    $pdo = \App\db\pdo();
} catch (Exception $e) {
    error_log('SMS API - Database connection error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::error('Database connection error'), 500);
    exit;
}

try {
    $pdo->beginTransaction();

    // Generate a unique message ID
    $messageId = 'sms_' . bin2hex(random_bytes(16));
    
    // Insert SMS log record
    $sql = "INSERT INTO sms_logs (
        patient_id,
        encounter_id,
        phone_number,
        message_content,
        message_type,
        status,
        provider,
        provider_message_id,
        created_by
    ) VALUES (
        :patient_id,
        :encounter_id,
        :phone_number,
        :message_content,
        'appointment_reminder',
        'pending',
        'twilio',
        :provider_message_id,
        :created_by
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':patient_id' => (int) $input['patient_id'],
        ':encounter_id' => !empty($input['encounter_id']) ? (int) $input['encounter_id'] : null,
        ':phone_number' => $phoneNumber,
        ':message_content' => $messageContent,
        ':provider_message_id' => $messageId,
        ':created_by' => $userId
    ]);

    $smsLogId = $pdo->lastInsertId();

    // Attempt to send via Twilio (or stub)
    $sendResult = sendViaTwilio($phoneNumber, $messageContent, $messageId);

    // Update SMS log with result
    if ($sendResult['success']) {
        $updateSql = "UPDATE sms_logs SET 
            status = 'sent',
            provider_message_id = :provider_message_id,
            sent_at = NOW()
        WHERE id = :id";
        
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':provider_message_id' => $sendResult['message_id'] ?? $messageId,
            ':id' => $smsLogId
        ]);
    } else {
        $updateSql = "UPDATE sms_logs SET 
            status = 'failed',
            error_message = :error_message
        WHERE id = :id";
        
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':error_message' => $sendResult['error'] ?? 'Unknown error',
            ':id' => $smsLogId
        ]);
    }

    // Log to audit trail
    logSmsAudit($pdo, $userId, $input['patient_id'], $smsLogId, $sendResult['success'] ? 'sent' : 'failed');

    $pdo->commit();

    if ($sendResult['success']) {
        ApiResponse::send(ApiResponse::success([
            'message_id' => $sendResult['message_id'] ?? $messageId,
            'sms_log_id' => $smsLogId
        ], 'SMS reminder sent successfully'), 200);
    } else {
        ApiResponse::send(ApiResponse::error('Failed to send SMS: ' . ($sendResult['error'] ?? 'Unknown error')), 500);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('SMS API - Database error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::error('Database error while sending SMS'), 500);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('SMS API - Error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::error('Error sending SMS reminder'), 500);
}

/**
 * Send SMS via Twilio
 * 
 * This function is stubbed for development. In production, uncomment the Twilio integration.
 *
 * @param string $phoneNumber The recipient phone number
 * @param string $message The message content
 * @param string $messageId Internal message ID
 * @return array Result with success status and message_id or error
 */
function sendViaTwilio(string $phoneNumber, string $message, string $messageId): array
{
    // Check if Twilio credentials are configured
    $twilioSid = getenv('TWILIO_ACCOUNT_SID') ?: ($_ENV['TWILIO_ACCOUNT_SID'] ?? '');
    $twilioToken = getenv('TWILIO_AUTH_TOKEN') ?: ($_ENV['TWILIO_AUTH_TOKEN'] ?? '');
    $twilioFrom = getenv('TWILIO_PHONE_NUMBER') ?: ($_ENV['TWILIO_PHONE_NUMBER'] ?? '');

    // If Twilio is not configured, use stub mode
    if (empty($twilioSid) || empty($twilioToken) || empty($twilioFrom)) {
        // Stub mode - simulate successful send
        error_log("SMS STUB MODE: Would send to {$phoneNumber}: {$message}");
        
        return [
            'success' => true,
            'message_id' => $messageId,
            'stub_mode' => true
        ];
    }

    // Production Twilio integration
    try {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json";
        
        $postData = [
            'To' => $phoneNumber,
            'From' => $twilioFrom,
            'Body' => $message
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$twilioSid}:{$twilioToken}",
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'error' => 'Twilio API connection error: ' . $curlError
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['sid'])) {
            return [
                'success' => true,
                'message_id' => $responseData['sid']
            ];
        }

        return [
            'success' => false,
            'error' => $responseData['message'] ?? 'Twilio API error (HTTP ' . $httpCode . ')'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Twilio exception: ' . $e->getMessage()
        ];
    }
}

/**
 * Log SMS action to audit trail
 *
 * @param PDO $pdo Database connection
 * @param int $userId User who initiated the SMS
 * @param int $patientId Target patient
 * @param int $smsLogId SMS log record ID
 * @param string $status Send status
 */
function logSmsAudit(PDO $pdo, int $userId, int $patientId, int $smsLogId, string $status): void
{
    try {
        // Check if audit_logs table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
        if ($tableCheck->rowCount() === 0) {
            // Table doesn't exist, skip audit logging
            return;
        }

        $sql = "INSERT INTO audit_logs (
            user_id,
            action,
            entity_type,
            entity_id,
            details,
            ip_address,
            user_agent,
            created_at
        ) VALUES (
            :user_id,
            :action,
            'sms_log',
            :entity_id,
            :details,
            :ip_address,
            :user_agent,
            NOW()
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => 'SMS_REMINDER_' . strtoupper($status),
            ':entity_id' => $smsLogId,
            ':details' => json_encode([
                'patient_id' => $patientId,
                'status' => $status
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the SMS operation
        error_log('SMS Audit logging error: ' . $e->getMessage());
    }
}
