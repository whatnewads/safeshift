<?php
/**
 * Create Test Users for New 6-Role System
 * Creates test users for: tadmin, cadmin, pclinician, dclinician, 1clinician, custom
 * 
 * All test users will have password: SafeShift2024!
 * 
 * Usage: Run via browser or CLI
 * DELETE THIS FILE AFTER RUNNING IN PRODUCTION
 */

// Load configuration
require_once __DIR__ . '/includes/config.php';

// Create database connection
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Generate UUID function
function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Test users configuration for new 6-role system
// pclinician will be privacy officer
// dclinician will be the manager/supervisor role
// tadmin will be the security officer
// 1clinician will be the employee/team member/provider
$test_users = [
    [
        'username' => 'tadmin',
        'password' => 'SafeShift2024!',
        'email' => 'tadmin@safeshift.local',
        'role' => 'tadmin',
        'dashboard' => '/dashboards/tadmin/'
    ],
    [
        'username' => 'pclinician',
        'password' => 'SafeShift2024!',
        'email' => 'pclinician@safeshift.local',
        'role' => 'pclinician',
        'dashboard' => '/dashboards/pclinician/'
    ],
    [
        'username' => 'dclinician',
        'password' => 'SafeShift2024!',
        'email' => 'dclinician@safeshift.local',
        'role' => 'dclinician',
        'dashboard' => '/dashboards/dclinician/'
    ],
    [
        'username' => '1clinician',
        'password' => 'SafeShift2024!',
        'email' => '1clinician@safeshift.local',
        'role' => '1clinician',
        'dashboard' => '/dashboards/1clinician/'
    ]
];

try {
    echo "<h2>Creating Test Users for New 6-Role System</h2>\n";
    echo "<pre>\n";
    echo "=============================================\n\n";
    
    // First, verify all roles exist
    echo "Verifying roles exist in database...\n";
    echo "-----------------------------------------\n";
    
    $expected_roles = ['tadmin', 'cadmin', 'pclinician', 'dclinician', '1clinician', 'custom'];
    
    foreach ($expected_roles as $role_name) {
        // Note: Using lowercase 'role' table name to match actual database
        $check_role = $db->prepare("SELECT role_id, description FROM role WHERE name = :name");
        $check_role->execute(['name' => $role_name]);
        $role = $check_role->fetch();
        
        if ($role) {
            echo "✓ Role '$role_name' exists (ID: " . $role['role_id'] . ")\n";
        } else {
            echo "❌ Role '$role_name' NOT FOUND - Please check database!\n";
            die("\nError: Required role '$role_name' does not exist in the database.\n");
        }
    }
    
    echo "\n";
    
    // Create each test user
    foreach ($test_users as $test_user) {
        echo "=====================================\n";
        echo "Creating user: " . $test_user['username'] . "\n";
        echo "-------------------------------------\n";
        
        $username = $test_user['username'];
        $password = $test_user['password'];
        $email = $test_user['email'];
        $role_name = $test_user['role'];
        $dashboard = $test_user['dashboard'];
        
        // Hash the password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if user exists - Note: using lowercase 'user' table
        $check_user = $db->prepare("SELECT user_id FROM user WHERE username = :username");
        $check_user->execute(['username' => $username]);
        $existing_user = $check_user->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            // Update existing user
            $user_id = $existing_user['user_id'];
            
            $update_user = $db->prepare("
                UPDATE user 
                SET password_hash = :password_hash,
                    email = :email,
                    mfa_enabled = 0,
                    status = 'active',
                    login_attempts = 0,
                    lockout_until = NULL,
                    account_locked_until = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE username = :username
            ");
            
            $update_user->execute([
                'password_hash' => $password_hash,
                'email' => $email,
                'username' => $username
            ]);
            
            echo "✓ Updated existing user '$username'\n";
        } else {
            // Create new user
            $user_id = generate_uuid();
            
            // Include all required fields based on the table structure
            $create_user = $db->prepare("
                INSERT INTO user (
                    user_id, username, password_hash, email, 
                    mfa_enabled, status, is_active,
                    login_attempts, lockout_until, account_locked_until,
                    created_at, updated_at
                ) VALUES (
                    :user_id, :username, :password_hash, :email, 
                    0, 'active', 1,
                    0, NULL, NULL,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )
            ");
            
            $create_user->execute([
                'user_id' => $user_id,
                'username' => $username,
                'password_hash' => $password_hash,
                'email' => $email
            ]);
            
            echo "✓ Created new user '$username'\n";
        }
        
        // Get role_id
        $get_role = $db->prepare("SELECT role_id FROM role WHERE name = :name");
        $get_role->execute(['name' => $role_name]);
        $role = $get_role->fetch(PDO::FETCH_ASSOC);
        $role_id = $role['role_id'];
        
        // Remove any existing role assignments for this user
        $delete_roles = $db->prepare("DELETE FROM userrole WHERE user_id = :user_id");
        $delete_roles->execute(['user_id' => $user_id]);
        
        // Assign role to user
        $user_role_id = generate_uuid();
        $assign_role = $db->prepare("
            INSERT INTO userrole (user_role_id, user_id, role_id, assigned_by, created_at)
            VALUES (:user_role_id, :user_id, :role_id, 'system', CURRENT_TIMESTAMP)
        ");
        
        $assign_role->execute([
            'user_role_id' => $user_role_id,
            'user_id' => $user_id,
            'role_id' => $role_id
        ]);
        
        echo "✓ Assigned role '$role_name' to user\n";
        echo "\n";
        echo "Login credentials:\n";
        echo "  Username: $username\n";
        echo "  Password: $password\n";
        echo "  Expected redirect: $dashboard\n";
        echo "\n";
    }
    
    echo "=====================================\n";
    echo "✓✓✓ ALL TEST USERS CREATED ✓✓✓\n";
    echo "=====================================\n\n";
    
    echo "Summary of Test Users:\n";
    echo "----------------------\n\n";
    
    foreach ($test_users as $test_user) {
        echo strtoupper($test_user['role']) . " User:\n";
        echo "  Username: " . $test_user['username'] . "\n";
        echo "  Password: " . $test_user['password'] . "\n";
        echo "  Dashboard: " . $test_user['dashboard'] . "\n\n";
    }
    
    echo "Test the login at: /login (or just /)\n\n";
    
    echo "⚠️  SECURITY WARNING:\n";
    echo "--------------------\n";
    echo "1. These are TEST credentials only\n";
    echo "2. DELETE this script after use\n";
    echo "3. Change passwords before production\n";
    echo "4. Enable MFA for production users\n";
    
    echo "</pre>\n";
    
} catch (PDOException $e) {
    echo "<pre>\n";
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
    echo "\nDebug info:\n";
    echo "  Error Code: " . $e->getCode() . "\n";
    echo "  SQL State: " . $e->errorInfo[0] . "\n";
    echo "\nMake sure these database tables exist (lowercase):\n";
    echo "  - user\n";
    echo "  - role\n";
    echo "  - userrole\n";
    echo "</pre>\n";
} catch (Exception $e) {
    echo "<pre>\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "</pre>\n";
}

// If running via CLI, strip HTML tags
if (php_sapi_name() === 'cli') {
    $output = ob_get_clean();
    $output = strip_tags($output);
    echo $output;
}
?>