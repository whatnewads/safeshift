<?php
/**
 * Verify 2FA Code Endpoint
 * 
 * POST /api/v1/auth/verify_2fa.php
 * 
 * Verifies a 2FA code entered by the user
 * 
 * Request body:
 * {
 *   "user_id": "uuid",    // Required
 *   "code": "123456",     // Required - 6 digit code
 *   "purpose": "login"    // Optional: login, password_reset, email_change, security
 * }
 * 
 * Response (success):
 * {
 *   "success": true,
 *   "message": "Verification successful",
 *   "data": {
 *     "verified_at": "2025-12-31 12:05:00",
 *     "purpose": "login"
 *   }
 * }
 * 
 * Response (failure):
 * {
 *   "success": false,
 *   "message": "Invalid verification code. 4 attempts remaining.",
 *   "data": {
 *     "remaining_attempts": 4
 *   }
 * }
 */

declare(strict_types=1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Bootstrap
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

use Core\Services\TwoFactorService;

try {
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request body'
        ]);
        exit;
    }
    
    // Validate required fields
    $userId = trim($input['user_id'] ?? '');
    $code = trim($input['code'] ?? '');
    
    if (empty($userId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required'
        ]);
        exit;
    }
    
    if (empty($code)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Verification code is required'
        ]);
        exit;
    }
    
    // Validate code format (6 digits)
    if (!preg_match('/^\d{6}$/', $code)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid code format. Please enter a 6-digit code.'
        ]);
        exit;
    }
    
    // Get purpose (default: login)
    $purpose = $input['purpose'] ?? 'login';
    $validPurposes = ['login', 'password_reset', 'email_change', 'security'];
    if (!in_array($purpose, $validPurposes)) {
        $purpose = 'login';
    }
    
    // Verify the code
    $twoFactorService = new TwoFactorService();
    $result = $twoFactorService->verifyCode($userId, $code, $purpose);
    
    // Set appropriate status code
    if ($result['success']) {
        http_response_code(200);
    } else {
        // 401 for invalid code, but not rate limited
        http_response_code(401);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('2FA Verify Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during verification'
    ]);
}
