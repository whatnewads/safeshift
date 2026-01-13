<?php
/**
 * Update user emails for testing
 * 
 * This script updates all test user emails to a target email address
 * so that OTP emails can be received during testing.
 * 
 * Usage: php database/update_user_emails.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$targetEmail = 'wesyielding1@gmail.com';

try {
    echo "Updating user emails to: $targetEmail\n\n";
    
    // Get all users
    $stmt = $db->prepare("SELECT user_id, username, email FROM User ORDER BY username");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current users:\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($users as $user) {
        echo sprintf("%-20s %-40s %s\n", $user['username'], $user['email'], $user['user_id']);
    }
    echo str_repeat('-', 80) . "\n\n";
    
    // Update all user emails to target email
    $updateStmt = $db->prepare("UPDATE User SET email = :email WHERE user_id = :user_id");
    
    $updated = 0;
    foreach ($users as $user) {
        $updateStmt->execute([
            'email' => $targetEmail,
            'user_id' => $user['user_id']
        ]);
        $updated++;
        echo "✓ Updated {$user['username']} email to: $targetEmail\n";
    }
    
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "SUCCESS: Updated $updated user(s) email to: $targetEmail\n";
    echo str_repeat('=', 80) . "\n\n";
    
    echo "Now when you log in with any user with MFA enabled, the OTP will be sent to:\n";
    echo "  $targetEmail\n\n";
    
    echo "The OTP will also be logged to: logs/otp.log\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
