<?php
/**
 * Reset User Passwords Script
 * This script resets passwords for existing users to known values
 * for testing purposes
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Define test passwords for each role
// Include both original users and *_user variants
$test_users = [
    // Original user accounts (from migration)
    ['username' => 'tadmin', 'password' => 'TAdmin123!', 'role' => 'tadmin'],
    ['username' => 'cadmin', 'password' => 'CAdmin123!', 'role' => 'cadmin'],
    ['username' => 'pclinician', 'password' => 'PClinician123!', 'role' => 'pclinician'],
    ['username' => 'dclinician', 'password' => 'DClinician123!', 'role' => 'dclinician'],
    ['username' => '1clinician', 'password' => '1Clinician123!', 'role' => '1clinician'],
    // Test user accounts (created by this script)
    ['username' => 'tadmin_user', 'password' => 'TAdmin123!', 'role' => 'tadmin'],
    ['username' => 'cadmin_user', 'password' => 'CAdmin123!', 'role' => 'cadmin'],
    ['username' => 'pclinician_user', 'password' => 'PClinician123!', 'role' => 'pclinician'],
    ['username' => 'dclinician_user', 'password' => 'DClinician123!', 'role' => 'dclinician'],
    ['username' => '1clinician_user', 'password' => '1Clinician123!', 'role' => '1clinician'],
    ['username' => 'custom_user', 'password' => 'Custom123!', 'role' => 'custom'],
];

$updated_users = [];
$created_users = [];

foreach ($test_users as $user_data) {
    try {
        // Check if user exists
        $stmt = $db->prepare("SELECT user_id, email FROM User WHERE username = :username");
        $stmt->execute(['username' => $user_data['username']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing user's password
            $password_hash = password_hash($user_data['password'], PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE User SET password_hash = :password_hash WHERE username = :username");
            $stmt->execute([
                'password_hash' => $password_hash,
                'username' => $user_data['username']
            ]);
            
            $updated_users[] = [
                'username' => $user_data['username'],
                'password' => $user_data['password'],
                'email' => $existing['email'],
                'role' => $user_data['role'],
                'status' => 'Password Updated'
            ];
            
            echo "✅ Updated password for: " . $user_data['username'] . "\n";
        } else {
            // Create new user
            $user_id = generate_uuid();
            $email = $user_data['username'] . '@test.safeshift.ai';
            $password_hash = password_hash($user_data['password'], PASSWORD_BCRYPT);
            
            // Insert user
            $stmt = $db->prepare("
                INSERT INTO User (user_id, username, email, password_hash, mfa_enabled, status, created_at) 
                VALUES (:user_id, :username, :email, :password_hash, 0, 'active', NOW())
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'username' => $user_data['username'],
                'email' => $email,
                'password_hash' => $password_hash
            ]);
            
            // Get role_id
            $stmt = $db->prepare("SELECT role_id FROM Role WHERE name = :role_name");
            $stmt->execute(['role_name' => $user_data['role']]);
            $role = $stmt->fetch();
            
            if ($role) {
                // Assign role
                $stmt = $db->prepare("
                    INSERT INTO UserRole (user_role_id, user_id, role_id) 
                    VALUES (:user_role_id, :user_id, :role_id)
                ");
                $stmt->execute([
                    'user_role_id' => generate_uuid(),
                    'user_id' => $user_id,
                    'role_id' => $role['role_id']
                ]);
            }
            
            $created_users[] = [
                'username' => $user_data['username'],
                'password' => $user_data['password'],
                'email' => $email,
                'role' => $user_data['role'],
                'status' => 'Created New'
            ];
            
            echo "✅ Created new user: " . $user_data['username'] . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Error processing " . $user_data['username'] . ": " . $e->getMessage() . "\n";
    }
}

// Get all existing users
echo "\n\n📋 Fetching all existing users...\n";
$stmt = $db->query("
    SELECT 
        u.username, 
        u.email, 
        u.status,
        u.mfa_enabled,
        GROUP_CONCAT(r.name) as roles
    FROM User u
    LEFT JOIN UserRole ur ON u.user_id = ur.user_id
    LEFT JOIN Role r ON ur.role_id = r.role_id
    GROUP BY u.user_id
    ORDER BY u.username
");
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate UUID function
function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return sprintf('%08s-%04s-%04s-%04s-%12s',
        bin2hex(substr($data, 0, 4)),
        bin2hex(substr($data, 4, 2)),
        bin2hex(substr($data, 6, 2)),
        bin2hex(substr($data, 8, 2)),
        bin2hex(substr($data, 10, 6))
    );
}

// Create markdown documentation
$markdown_content = "# SafeShift EHR - User Credentials\n\n";
$markdown_content .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";

$markdown_content .= "## Test User Accounts\n\n";
$markdown_content .= "These are the test accounts with known passwords for development/testing:\n\n";
$markdown_content .= "| Username | Password | Email | Role | Status |\n";
$markdown_content .= "|----------|----------|-------|------|--------|\n";

// Add updated users
foreach ($updated_users as $user) {
    $markdown_content .= "| {$user['username']} | {$user['password']} | {$user['email']} | {$user['role']} | {$user['status']} |\n";
}

// Add created users
foreach ($created_users as $user) {
    $markdown_content .= "| {$user['username']} | {$user['password']} | {$user['email']} | {$user['role']} | {$user['status']} |\n";
}

$markdown_content .= "\n## All Existing Users in Database\n\n";
$markdown_content .= "| Username | Email | Status | MFA Enabled | Roles |\n";
$markdown_content .= "|----------|-------|--------|-------------|-------|\n";

foreach ($all_users as $user) {
    $mfa = $user['mfa_enabled'] ? 'Yes' : 'No';
    $roles = $user['roles'] ?? 'No Role';
    $markdown_content .= "| {$user['username']} | {$user['email']} | {$user['status']} | {$mfa} | {$roles} |\n";
}

$markdown_content .= "\n## Password Policy\n\n";
$markdown_content .= "- Minimum 8 characters\n";
$markdown_content .= "- Must contain uppercase and lowercase letters\n";
$markdown_content .= "- Must contain numbers\n";
$markdown_content .= "- Must contain special characters\n\n";

$markdown_content .= "## Notes\n\n";
$markdown_content .= "- All passwords are hashed using bcrypt in the database\n";
$markdown_content .= "- MFA is disabled for test accounts to simplify testing\n";
$markdown_content .= "- These credentials are for development/testing only\n";

// Write to file
file_put_contents(__DIR__ . '/../USER_CREDENTIALS.md', $markdown_content);

echo "\n\n✅ Password reset complete!\n";
echo "📄 Credentials documented in USER_CREDENTIALS.md\n";
?>