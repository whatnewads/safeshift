<?php
/**
 * Resend OTP API Endpoint
 * Located at: root/api/resend-otp.php
 * 
 * Handles OTP resend requests with rate limiting
 * Returns JSON response for AJAX calls
 */

// Include required files
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/log.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/email.php';

use function App\log\audit;
use function App\auth\generate_uuid;

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Check for XMLHttpRequest
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
}

// Check if user has pending 2FA session
if (empty($_SESSION['pending_2fa']) || empty($_SESSION['pending_2fa']['user_id'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'No active verification session'
    ]);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

// Verify CSRF token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token'
    ]);
    exit;
}

// Check rate limiting (max 3 resends per session, 60 seconds between resends)
$current_time = time();
$last_resend = $_SESSION['last_otp_resend'] ?? 0;
$resend_count = $_SESSION['otp_resend_count'] ?? 0;

if ($resend_count >= 3) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Maximum resend attempts reached. Please try logging in again.'
    ]);
    exit;
}

if (($current_time - $last_resend) < 60) {
    $wait_time = 60 - ($current_time - $last_resend);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => "Please wait $wait_time seconds before requesting another code",
        'wait_time' => $wait_time
    ]);
    exit;
}

// Get user information
$user_id = $_SESSION['pending_2fa']['user_id'];
$username = $_SESSION['pending_2fa']['username'];
$email = $_SESSION['pending_2fa']['email'];

try {
    // Connect to database
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Invalidate existing OTPs for this user
    $stmt = $db->prepare("
        UPDATE login_otp 
        SET consumed = 1 
        WHERE user_id = :user_id 
        AND consumed = 0
    ");
    $stmt->execute(['user_id' => $user_id]);
    
    // Generate new OTP code
    $otp_code = '';
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    for ($i = 0; $i < 6; $i++) {
        $otp_code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    // Store new OTP in database
    $otp_id = generate_uuid();
    $expires_at = date('Y-m-d H:i:s', time() + 900); // 15 minutes
    
    $stmt = $db->prepare("
        INSERT INTO login_otp (otp_id, user_id, code, expires_at, consumed, created_at)
        VALUES (:otp_id, :user_id, :code, :expires_at, 0, NOW())
    ");
    
    $stmt->execute([
        'otp_id' => $otp_id,
        'user_id' => $user_id,
        'code' => $otp_code,
        'expires_at' => $expires_at
    ]);
    
    // Log OTP generation
    audit('OTP_RESEND', 'User', $user_id, [
        'username' => $username,
        'email' => $email,
        'resend_count' => $resend_count + 1,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Send OTP via email
    $email_sent = \App\email\send_otp_email($email, $otp_code, $username);
    
    if ($email_sent) {
        // Update session tracking
        $_SESSION['last_otp_resend'] = $current_time;
        $_SESSION['otp_resend_count'] = $resend_count + 1;
        
        // Reset failed attempts on successful resend
        unset($_SESSION['2fa_attempts']);
        
        // Log successful resend
        audit('OTP_RESEND_SUCCESS', 'User', $user_id, [
            'username' => $username,
            'email' => $email,
            'resend_count' => $_SESSION['otp_resend_count'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'New verification code sent to your email',
            'expires_in' => 900, // 15 minutes in seconds
            'resends_remaining' => 3 - $_SESSION['otp_resend_count']
        ]);
        
    } else {
        // Email failed
        error_log("[OTP_RESEND] Failed to send email to: $email");
        
        // Remove the OTP since email failed
        $stmt = $db->prepare("
            DELETE FROM login_otp 
            WHERE otp_id = :otp_id
        ");
        $stmt->execute(['otp_id' => $otp_id]);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send verification code. Please check your email settings.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("[OTP_RESEND] Database error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred. Please try again.'
    ]);
    
} catch (Exception $e) {
    error_log("[OTP_RESEND] Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}