<?php
/**
 * Test SES Email Sending
 * 
 * Tests the Amazon SES SMTP integration
 */

echo "=== SES Email Test ===\n\n";

// Bootstrap
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Check if MailConfig loads properly
echo "1. Testing MailConfig...\n";
require_once dirname(__DIR__) . '/api/config/mail.php';

try {
    $config = \Api\Config\MailConfig::get();
    echo "   ✓ MailConfig loaded successfully\n";
    echo "   Host: {$config['host']}\n";
    echo "   Port: {$config['port']}\n";
    echo "   From: {$config['from_email']}\n";
    echo "   From Name: {$config['from_name']}\n";
} catch (Exception $e) {
    echo "   ✗ MailConfig Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test EmailService
echo "\n2. Testing EmailService...\n";

use Core\Services\EmailService;

$emailService = new EmailService();

// Get a test email address
$testEmail = $config['from_email']; // Send to self for testing

echo "   Sending test email to: $testEmail\n";

// Test with OTP
$testCode = '123456';
$result = $emailService->sendOtp($testEmail, $testCode, 'Test User');

echo "\n3. Send Result:\n";
print_r($result);

if ($result['success']) {
    echo "\n   ✓ Email sent successfully!\n";
    
    // Check mail_log table
    echo "\n4. Checking mail_log table...\n";
    
    try {
        $db = \Core\Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT * FROM mail_log ORDER BY created_at DESC LIMIT 5");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($logs) > 0) {
            echo "   ✓ Found " . count($logs) . " mail log entries:\n";
            foreach ($logs as $log) {
                echo "     - ID: {$log['id']}, Type: {$log['email_type']}, Status: {$log['status']}, Created: {$log['created_at']}\n";
            }
        } else {
            echo "   ! No mail_log entries found (logging may not be hooked up yet)\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error checking mail_log: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n   ✗ Email send failed!\n";
    if (isset($result['errors'])) {
        echo "   Errors: " . json_encode($result['errors']) . "\n";
    }
    
    // Try to get more debug info
    echo "\n   Checking SMTP debug log...\n";
    $logFile = dirname(__DIR__) . '/logs/email_smtp.log';
    if (file_exists($logFile)) {
        echo "   Last 20 lines from email_smtp.log:\n";
        $lines = array_slice(file($logFile), -20);
        foreach ($lines as $line) {
            echo "   " . $line;
        }
    } else {
        echo "   No SMTP debug log found. Set SMTP_DEBUG=true in .env to enable.\n";
    }
}

echo "\n=== Test Complete ===\n";
