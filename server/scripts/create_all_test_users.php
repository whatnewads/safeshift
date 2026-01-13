<?php
/**
 * Create ALL Test Users Script
 * Creates test users for all roles
 * 
 * Users created:
 * - employee / hash123 (Employee role)
 * - admin / hash123 (Admin role)  
 * - clinician / hash123 (Clinician role)
 * - employer / hash123 (EmployerPortal role)
 * 
 * Usage: Upload to root and access via browser or run via CLI
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

// Test users configuration
$test_users = [
    [
        'username' => 'employee',
        'password' => 'hash123',
        'email' => 'employee@test.local',
        'role' => 'Employee',
        'dashboard' => '/employee/dashboard_employee'
    ],
    [
        'username' => 'admin',
        'password' => 'hash123',
        'email' => 'admin@test.local',
        'role' => 'Admin',
        'dashboard' => '/admin/dashboard_admin'
    ],
    [
        'username' => 'clinician',
        'password' => 'hash123',
        'email' => 'clinician@test.local',
        'role' => 'Clinician',
        'dashboard' => '/clinician/dashboard'
    ],
    [
        'username' => 'employer',
        'password' => 'hash123',
        'email' => 'employer@test.local',
        'role' => 'EmployerPortal',
        'dashboard' => '/employer/dashboard_employer'
    ]
];

try {
    echo "<h2>Creating Test Users for All Roles</h2>\n";
    echo "<pre>\n";
    echo "=====================================\n\n";
    
    // First, ensure all roles exist
    echo "Ensuring all roles exist in Role table...\n";
    echo "-----------------------------------------\n";
    
    $roles_to_create = [
        'Employee' => 'Employee user with access to personal health records',
        'Admin' => 'System administrator with full access',
        'Clinician' => 'Medical professional with patient access',
        'EmployerPortal' => 'Employer representative with employee health access'
    ];
    
    foreach ($roles_to_create as $role_name => $description) {
        $check_role = $db->prepare("SELECT role_id FROM Role WHERE name = :name");
        $check_role->execute(['name' => $role_name]);
        
        if ($check_role->fetch()) {
            echo "✓ Role '$role_name' exists\n";
        } else {
            $role_id = generate_uuid();
            $create_role = $db->prepare("
                INSERT INTO Role (role_id, name, description, created_at, updated_at)
                VALUES (:role_id, :name, :description, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            
            $create_role->execute([
                'role_id' => $role_id,
                'name' => $role_name,
                'description' => $description
            ]);
            
            echo "✓ Created role '$role_name'\n";
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
        
        // Check if user exists
        $check_user = $db->prepare("SELECT user_id FROM User WHERE username = :username");
        $check_user->execute(['username' => $username]);
        $existing_user = $check_user->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            // Update existing user
            $user_id = $existing_user['user_id'];
            
            $update_user = $db->prepare("
                UPDATE User 
                SET password_hash = :password_hash,
                    email = :email,
                    mfa_enabled = 0,
                    status = 'active',
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
            
            $create_user = $db->prepare("
                INSERT INTO User (user_id, username, password_hash, email, mfa_enabled, status, created_at, updated_at)
                VALUES (:user_id, :username, :password_hash, :email, 0, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
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
        $get_role = $db->prepare("SELECT role_id FROM Role WHERE name = :name");
        $get_role->execute(['name' => $role_name]);
        $role = $get_role->fetch(PDO::FETCH_ASSOC);
        $role_id = $role['role_id'];
        
        // Remove any existing role assignments for this user
        $delete_roles = $db->prepare("DELETE FROM UserRole WHERE user_id = :user_id");
        $delete_roles->execute(['user_id' => $user_id]);
        
        // Assign role to user
        $user_role_id = generate_uuid();
        $assign_role = $db->prepare("
            INSERT INTO UserRole (user_role_id, user_id, role_id, created_at)
            VALUES (:user_role_id, :user_id, :role_id, CURRENT_TIMESTAMP)
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
        echo $test_user['role'] . " User:\n";
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
    echo "\nMake sure these database tables exist:\n";
    echo "  - User (user_id, username, password_hash, email, mfa_enabled, status)\n";
    echo "  - Role (role_id, name, description)\n";
    echo "  - UserRole (user_role_id, user_id, role_id, created_at)\n";
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