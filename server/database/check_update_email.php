<?php
/**
 * Check and update user email address
 */

require_once __DIR__ . '/../includes/bootstrap.php';

try {
    echo "Checking user email addresses...\n\n";
    
    // Check current email for admin user
    $stmt = $db->prepare("SELECT user_id, username, email FROM User WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Current admin user details:\n";
        echo "User ID: " . $user['user_id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Email: " . $user['email'] . "\n\n";
        
        // Update to a valid email address
        $new_email = 'admin@safeshift.local'; // Local development email
        
        echo "Updating email to: $new_email\n";
        
        $update_stmt = $db->prepare("UPDATE User SET email = :email WHERE user_id = :user_id");
        $update_stmt->execute([
            'email' => $new_email,
            'user_id' => $user['user_id']
        ]);
        
        echo "✓ Email updated successfully!\n\n";
        
        // Verify the update
        $verify_stmt = $db->prepare("SELECT email FROM User WHERE user_id = :user_id");
        $verify_stmt->execute(['user_id' => $user['user_id']]);
        $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Verified new email: " . $result['email'] . "\n";
    } else {
        echo "Admin user not found!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}