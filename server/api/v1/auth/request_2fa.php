<?php
/**
 * Request 2FA Code Endpoint
 * 
 * POST /api/v1/auth/request_2fa.php
 * 
 * Generates and sends a 2FA verification code via email (Amazon SES)
 * 
 * Request body:
 * {
 *   "user_id": "uuid",    // Optional if already authenticated
 *   "email": "user@example.com",  // Required
 *   "purpose": "login"    // Optional: login, password_reset, email_change, security
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Verification code sent",
 *   "data": {
 *     "expires_in": 600,
 *     "expires_at": "2025-12-31 12:10:00"
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
    $email = trim($input['email'] ?? '');
    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email is required'
        ]);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }
    
    // Get user ID - either from input or session
    $userId = $input['user_id'] ?? null;
    
    // If no user_id provided, try to look up by email
    if (!$userId) {
        $userId = getUserIdByEmail($email);
        if (!$userId) {
            // Don't reveal whether email exists - return generic message
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'If this email exists in our system, a verification code has been sent.',
                'data' => [
                    'expires_in' => 600
                ]
            ]);
            exit;
        }
    }
    
    // Get purpose (default: login)
    $purpose = $input['purpose'] ?? 'login';
    $validPurposes = ['login', 'password_reset', 'email_change', 'security'];
    if (!in_array($purpose, $validPurposes)) {
        $purpose = 'login';
    }
    
    // Get username for personalization
    $username = getUsernameById($userId);
    
    // Request 2FA code
    $twoFactorService = new TwoFactorService();
    $result = $twoFactorService->requestCode($userId, $email, $username, $purpose);
    
    // Return response
    http_response_code($result['success'] ? 200 : 429);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('2FA Request Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred processing your request'
    ]);
}

/**
 * Look up user ID by email
 */
function getUserIdByEmail(string $email): ?string
{
    global $db;
    
    try {
        if (!isset($db)) {
            $db = \Core\Database::getInstance()->getConnection();
        }
        
        $sql = "SELECT user_id FROM user WHERE email = :email AND is_active = 1 LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['email' => $email]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['user_id'] : null;
        
    } catch (Exception $e) {
        error_log('getUserIdByEmail error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get username by user ID
 */
function getUsernameById(string $userId): string
{
    global $db;
    
    try {
        if (!isset($db)) {
            $db = \Core\Database::getInstance()->getConnection();
        }
        
        $sql = "SELECT username FROM user WHERE user_id = :user_id LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['username'] : '';
        
    } catch (Exception $e) {
        return '';
    }
}
