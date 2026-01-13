<?php
/**
 * Test 2FA Flow
 * 
 * Tests the request and verify 2FA endpoints
 */

echo "=== 2FA Flow Test ===\n\n";

// Bootstrap
require_once dirname(__DIR__) . '/includes/bootstrap.php';

use Core\Services\TwoFactorService;

// Test email - use your verified SES email
$testEmail = getenv('SES_SMTP_FROM_EMAIL') ?: 'wes.yielding@newadsoriginals.com';

// Get a user from the database
echo "1. Finding a test user...\n";

try {
    $db = new PDO(
        "mysql:host=" . (getenv('DB_HOST') ?: '127.0.0.1') . 
        ";port=" . (getenv('DB_PORT') ?: '3306') . 
        ";dbname=" . (getenv('DB_NAME') ?: 'safeshift_ehr_001_0') .
        ";charset=utf8mb4",
        getenv('DB_USER') ?: 'safeshift_admin',
        getenv('DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get first active user
    $stmt = $db->query("SELECT user_id, username, email FROM user WHERE is_active = 1 LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("   No active users found in database\n");
    }
    
    echo "   Found user: {$user['username']} (ID: {$user['user_id']})\n";
    echo "   User email: {$user['email']}\n";
    echo "   Will send to: $testEmail (verified SES email)\n";
    
} catch (PDOException $e) {
    die("   Database error: " . $e->getMessage() . "\n");
}

// Test TwoFactorService
echo "\n2. Testing TwoFactorService::requestCode()...\n";

$twoFactorService = new TwoFactorService();

// Request a 2FA code
$result = $twoFactorService->requestCode(
    $user['user_id'],
    $testEmail,  // Use verified SES email
    $user['username'],
    'login'
);

echo "   Result:\n";
print_r($result);

if ($result['success']) {
    echo "\n   ✓ 2FA code requested successfully!\n";
    echo "   Check your email at: $testEmail\n";
    
    // Check if code was stored in database
    echo "\n3. Verifying code stored in database...\n";
    
    $stmt = $db->prepare("
        SELECT id, code_hash, purpose, expires_at, attempts 
        FROM two_factor_codes 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $user['user_id']]);
    $code = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($code) {
        echo "   ✓ Code found in database\n";
        echo "   ID: {$code['id']}\n";
        echo "   Purpose: {$code['purpose']}\n";
        echo "   Expires: {$code['expires_at']}\n";
        echo "   Attempts: {$code['attempts']}\n";
        echo "   Hash length: " . strlen($code['code_hash']) . " chars\n";
    } else {
        echo "   ✗ No code found in database\n";
    }
    
    // Test verification (with wrong code to test failure)
    echo "\n4. Testing TwoFactorService::verifyCode() with wrong code...\n";
    
    $verifyResult = $twoFactorService->verifyCode($user['user_id'], '000000', 'login');
    echo "   Result:\n";
    print_r($verifyResult);
    
    if (!$verifyResult['success']) {
        echo "   ✓ Correctly rejected invalid code\n";
    }
    
} else {
    echo "\n   ✗ 2FA code request failed\n";
}

// Check mail_log
echo "\n5. Checking mail_log table...\n";

try {
    $stmt = $db->query("SELECT * FROM mail_log ORDER BY created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($logs) > 0) {
        echo "   Found " . count($logs) . " mail log entries:\n";
        foreach ($logs as $log) {
            echo "   - ID: {$log['id']}, Type: {$log['email_type']}, Status: {$log['status']}, Time: {$log['created_at']}\n";
        }
    } else {
        echo "   No mail_log entries found\n";
    }
} catch (PDOException $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// Check rate limits
echo "\n6. Checking email_rate_limits table...\n";

try {
    $stmt = $db->query("SELECT * FROM email_rate_limits ORDER BY last_sent_at DESC LIMIT 5");
    $limits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($limits) > 0) {
        echo "   Found " . count($limits) . " rate limit entries:\n";
        foreach ($limits as $limit) {
            echo "   - User: {$limit['user_id']}, Type: {$limit['email_type']}, Count: {$limit['count']}\n";
        }
    } else {
        echo "   No rate_limits entries found\n";
    }
} catch (PDOException $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
