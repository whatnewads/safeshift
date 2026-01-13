<?php
/**
 * Create Test User Script
 * Run this once to create a test employee user
 * 
 * Username: employee
 * Password: hash123
 * Role: Employee
 * 
 * Usage: Upload to root and access via browser or run via CLI
 * DELETE THIS FILE AFTER RUNNING IN PRODUCTION
 */

// Load configuration
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Security warning
if (php_sapi_name() !== 'cli') {
    echo "<div style='background: #ff0; padding: 10px; border: 2px solid #f00;'>";
    echo "<strong>⚠️ WARNING:</strong> Delete this file immediately after use!";
    echo "</div><br>";
}

try {
    echo "<h2>Creating Test User</h2>\n";
    echo "<pre>\n";
    
    // Test user details
    $username = 'employee';
    $password = 'hash123';
    $email = 'employee@test.local';
    
    // Generate UUID for user_id
    function generate_uuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    $user_id = generate_uuid();
    
    // Hash the password using bcrypt (PHP's default PASSWORD_DEFAULT)
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "User Details:\n";
    echo "-------------\n";
    echo "User ID: $user_id\n";
    echo "Username: $username\n";
    echo "Password: $password (plaintext - for testing only)\n";
    echo "Password Hash: $password_hash\n";
    echo "Email: $email\n\n";
    
    // Check if user already exists
    $check_stmt = $db->prepare("SELECT user_id FROM User WHERE username = :username");
    $check_stmt->execute(['username' => $username]);
    
    if ($check_stmt->fetch()) {
        // User exists, update instead
        echo "User '$username' already exists. Updating password...\n";
        
        $update_stmt = $db->prepare("
            UPDATE User 
            SET password_hash = :password_hash,
                updated_at = CURRENT_TIMESTAMP
            WHERE username = :username
        ");
        
        $update_stmt->execute([
            'password_hash' => $password_hash,
            'username' => $username
        ]);
        
        // Get the existing user_id
        $get_id_stmt = $db->prepare("SELECT user_id FROM User WHERE username = :username");
        $get_id_stmt->execute(['username' => $username]);
        $result = $get_id_stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $result['user_id'];
        
        echo "✓ Password updated for existing user\n\n";
    } else {
        // Create new user
        echo "Creating new user...\n";
        
        $insert_stmt = $db->prepare("
            INSERT INTO User (user_id, username, password_hash, email, mfa_enabled, active, created_at, updated_at)
            VALUES (:user_id, :username, :password_hash, :email, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $insert_stmt->execute([
            'user_id' => $user_id,
            'username' => $username,
            'password_hash' => $password_hash,
            'email' => $email
        ]);
        
        echo "✓ User created successfully\n\n";
    }
    
    // Get Employee role_id from Role table
    echo "Getting Employee role from Role table...\n";
    $role_stmt = $db->prepare("SELECT role_id FROM Role WHERE name = 'Employee'");
    $role_stmt->execute();
    $role = $role_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        // Create Employee role if it doesn't exist
        echo "Employee role not found. Creating role...\n";
        $role_id = generate_uuid();
        
        $create_role_stmt = $db->prepare("
            INSERT INTO Role (role_id, name, description, created_at, updated_at)
            VALUES (:role_id, 'Employee', 'Employee user with limited access', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $create_role_stmt->execute(['role_id' => $role_id]);
        echo "✓ Employee role created\n\n";
    } else {
        $role_id = $role['role_id'];
        echo "✓ Employee role found (ID: $role_id)\n\n";
    }
    
    // Check if user already has this role
    echo "Checking existing role assignment...\n";
    $check_role_stmt = $db->prepare("
        SELECT * FROM UserRole 
        WHERE user_id = :user_id AND role_id = :role_id
    ");
    
    $check_role_stmt->execute([
        'user_id' => $user_id,
        'role_id' => $role_id
    ]);
    
    if ($check_role_stmt->fetch()) {
        echo "✓ User already has Employee role\n\n";
    } else {
        // Assign Employee role to user
        echo "Assigning Employee role to user...\n";
        
        $user_role_id = generate_uuid();
        $assign_role_stmt = $db->prepare("
            INSERT INTO UserRole (user_role_id, user_id, role_id, assigned_at, assigned_by)
            VALUES (:user_role_id, :user_id, :role_id, CURRENT_TIMESTAMP, :assigned_by)
        ");
        
        $assign_role_stmt->execute([
            'user_role_id' => $user_role_id,
            'user_id' => $user_id,
            'role_id' => $role_id,
            'assigned_by' => 'system'
        ]);
        
        echo "✓ Employee role assigned to user\n\n";
    }
    
    // Verify the user can be queried correctly
    echo "Verifying user setup...\n";
    echo "-----------------------\n";
    
    $verify_stmt = $db->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.email,
            u.mfa_enabled,
            u.active,
            r.name as role_name
        FROM User u
        JOIN UserRole ur ON u.user_id = ur.user_id
        JOIN Role r ON ur.role_id = r.role_id
        WHERE u.username = :username
    ");
    
    $verify_stmt->execute(['username' => $username]);
    $user_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data) {
        echo "User verified successfully:\n";
        echo "  Username: " . $user_data['username'] . "\n";
        echo "  Email: " . $user_data['email'] . "\n";
        echo "  Role: " . $user_data['role_name'] . "\n";
        echo "  MFA Enabled: " . ($user_data['mfa_enabled'] ? 'Yes' : 'No') . "\n";
        echo "  Active: " . ($user_data['active'] ? 'Yes' : 'No') . "\n\n";
        
        echo "========================================\n";
        echo "✓ TEST USER CREATED SUCCESSFULLY\n";
        echo "========================================\n\n";
        
        echo "Login Credentials:\n";
        echo "  URL: /login (or just /)\n";
        echo "  Username: $username\n";
        echo "  Password: $password\n\n";
        
        echo "Expected redirect after login:\n";
        echo "  /employee/dashboard_employee\n\n";
        
        echo "⚠️  IMPORTANT: Delete this script after use!\n";
    } else {
        echo "❌ ERROR: Could not verify user creation\n";
    }
    
    echo "</pre>\n";
    
} catch (PDOException $e) {
    echo "<pre>\n";
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
    echo "Make sure the database tables exist:\n";
    echo "  - User (user_id, username, password_hash, email, mfa_enabled, active)\n";
    echo "  - Role (role_id, name, description)\n";
    echo "  - UserRole (user_role_id, user_id, role_id, assigned_at, assigned_by)\n";
    echo "</pre>\n";
} catch (Exception $e) {
    echo "<pre>\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "</pre>\n";
}

// If running via CLI, strip HTML tags
if (php_sapi_name() === 'cli') {
    // No output buffering needed for browser
}
?>