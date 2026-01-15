#!/usr/bin/env php
<?php
/**
 * Create User wyieldi with All Roles
 *
 * Creates a new user with username 'wyieldi' and password 'Login.01'
 * and assigns ALL available roles to the user.
 *
 * Database: safeshift_ehr_001_0
 *
 * Usage: php scripts/create_user_wyieldi.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

use Model\Core\Database;

// User details
$username = 'wyieldi';
$password = 'Login.01';
$email = 'wyieldi@safeshift.local';

echo "========================================\n";
echo "  Create User: $username\n";
echo "  Database: " . DB_NAME . "\n";
echo "========================================\n\n";

// Generate UUID function
function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Define all roles that should exist in the system
$systemRoles = [
    ['name' => 'Intake Clinician', 'slug' => '1clinician', 'description' => 'Intake clinicians handling patient registration and check-in'],
    ['name' => 'Drug Screen Technician', 'slug' => 'dclinician', 'description' => 'Drug screen clinicians managing DOT testing workflows'],
    ['name' => 'Clinical Provider', 'slug' => 'pclinician', 'description' => 'Primary clinical providers (doctors, nurses, NPs, PAs)'],
    ['name' => 'Clinic Administrator', 'slug' => 'cadmin', 'description' => 'Clinic administrators managing clinic operations'],
    ['name' => 'Technical Administrator', 'slug' => 'tadmin', 'description' => 'Technical administrators handling system configuration'],
    ['name' => 'System Administrator', 'slug' => 'Admin', 'description' => 'Super administrators with full system access'],
    ['name' => 'Manager', 'slug' => 'Manager', 'description' => 'Managers overseeing operations and reports'],
    ['name' => 'Quality Assurance', 'slug' => 'QA', 'description' => 'Quality assurance staff reviewing encounters'],
    ['name' => 'Privacy Officer', 'slug' => 'PrivacyOfficer', 'description' => 'HIPAA privacy compliance officers'],
    ['name' => 'Security Officer', 'slug' => 'SecurityOfficer', 'description' => 'Security compliance officers'],
    ['name' => 'Employee', 'slug' => 'Employee', 'description' => 'Employee user with limited access'],
    ['name' => 'Employer Portal', 'slug' => 'EmployerPortal', 'description' => 'Employer representative with employee health access'],
];

try {
    $db = Database::getInstance();
    
    // First, ensure all roles exist in the database
    echo "Checking and creating system roles...\n";
    echo "-------------------------------------\n";
    
    foreach ($systemRoles as $role) {
        $existingRole = $db->fetchOne(
            "SELECT role_id FROM role WHERE slug = :slug",
            ['slug' => $role['slug']]
        );
        
        if (!$existingRole) {
            $roleId = generate_uuid();
            $db->insert('role', [
                'role_id' => $roleId,
                'name' => $role['name'],
                'slug' => $role['slug'],
                'description' => $role['description'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            echo "  + Created role: {$role['name']} ({$role['slug']})\n";
        } else {
            echo "  = Role exists: {$role['name']} ({$role['slug']})\n";
        }
    }
    echo "\n";
    
    // Hash the password using bcrypt (PASSWORD_DEFAULT)
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "Password Details:\n";
    echo "-----------------\n";
    echo "Plain Password: $password\n";
    echo "Password Hash:  $password_hash\n\n";
    
    // Check if user already exists
    $existingUser = $db->fetchOne(
        "SELECT user_id, username FROM user WHERE username = :username",
        ['username' => $username]
    );
    
    if ($existingUser) {
        $userId = $existingUser['user_id'];
        echo "User '$username' already exists (ID: $userId)\n";
        echo "Updating password...\n";
        
        $db->query(
            "UPDATE user SET password_hash = :password_hash, status = 'active', updated_at = NOW() WHERE user_id = :user_id",
            ['password_hash' => $password_hash, 'user_id' => $userId]
        );
        
        echo "✓ Password updated successfully\n\n";
    } else {
        // Create new user
        $userId = generate_uuid();
        
        echo "Creating new user...\n";
        echo "User ID: $userId\n";
        
        $db->insert('user', [
            'user_id' => $userId,
            'username' => $username,
            'password_hash' => $password_hash,
            'email' => $email,
            'mfa_enabled' => 0,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "✓ User created successfully\n\n";
    }
    
    // Get all available roles
    $allRoles = $db->fetchAll("SELECT role_id, name, slug FROM role ORDER BY role_id");
    
    echo "Available roles in system:\n";
    echo "--------------------------\n";
    foreach ($allRoles as $role) {
        echo "  - {$role['name']} ({$role['slug']})\n";
    }
    echo "\n";
    
    // Get current user roles
    $currentRoles = $db->fetchAll(
        "SELECT r.role_id, r.name, r.slug
         FROM userrole ur
         JOIN role r ON ur.role_id = r.role_id
         WHERE ur.user_id = :user_id",
        ['user_id' => $userId]
    );
    $currentRoleIds = array_column($currentRoles, 'role_id');
    
    echo "Current roles for $username:\n";
    if (empty($currentRoles)) {
        echo "  (none)\n";
    } else {
        foreach ($currentRoles as $role) {
            echo "  - {$role['name']} ({$role['slug']})\n";
        }
    }
    echo "\n";
    
    // Add all missing roles
    $addedCount = 0;
    $rolesAssigned = [];
    
    echo "Assigning ALL roles to user:\n";
    echo "----------------------------\n";
    
    foreach ($allRoles as $role) {
        if (!in_array($role['role_id'], $currentRoleIds)) {
            $userRoleId = generate_uuid();
            
            $db->insert('userrole', [
                'user_role_id' => $userRoleId,
                'user_id' => $userId,
                'role_id' => $role['role_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            echo "  + Added: {$role['name']} ({$role['slug']})\n";
            $addedCount++;
        } else {
            echo "  = Already has: {$role['name']} ({$role['slug']})\n";
        }
        $rolesAssigned[] = $role['name'] . " (" . $role['slug'] . ")";
    }
    
    echo "\n";
    
    // Verify final state
    $finalRoles = $db->fetchAll(
        "SELECT r.name, r.slug
         FROM userrole ur
         JOIN role r ON ur.role_id = r.role_id
         WHERE ur.user_id = :user_id
         ORDER BY r.slug",
        ['user_id' => $userId]
    );
    
    echo "========================================\n";
    echo "  SUMMARY\n";
    echo "========================================\n";
    echo "User ID:        $userId\n";
    echo "Username:       $username\n";
    echo "Email:          $email\n";
    echo "Password:       $password\n";
    echo "Password Hash:  $password_hash\n";
    echo "Status:         active\n";
    echo "MFA Enabled:    No\n";
    echo "Roles Added:    $addedCount\n";
    echo "Total Roles:    " . count($finalRoles) . "\n\n";
    
    echo "Final roles assigned to $username:\n";
    foreach ($finalRoles as $role) {
        echo "  ✓ {$role['name']} ({$role['slug']})\n";
    }
    echo "\n";
    
    echo "========================================\n";
    echo "  SUCCESS!\n";
    echo "========================================\n";
    echo "User '$username' has been created with all " . count($finalRoles) . " roles.\n\n";
    
    echo "Login Credentials:\n";
    echo "------------------\n";
    echo "Username: $username\n";
    echo "Password: $password\n";
    echo "\n";
    
    echo "The user can log in at the application login page.\n";
    echo "The 'Change Role' button will appear in the sidebar.\n";
    
} catch (PDOException $e) {
    echo "DATABASE ERROR: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
