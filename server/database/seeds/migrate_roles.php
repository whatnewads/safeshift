<?php
/**
 * Role Migration Script
 * Migrates from 4-role system to 6-role system
 * WARNING: This will delete all existing roles and user assignments!
 */

require_once __DIR__ . '/../includes/config.php';

// Ensure script is run from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Color output helpers
function success($msg) { echo "\033[32m✓ $msg\033[0m\n"; }
function error($msg) { echo "\033[31m✗ $msg\033[0m\n"; }
function warning($msg) { echo "\033[33m⚠ $msg\033[0m\n"; }
function info($msg) { echo "\033[36m→ $msg\033[0m\n"; }

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    success("Database connection established");
} catch (PDOException $e) {
    error("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Start transaction
$pdo->beginTransaction();

try {
    // Disable foreign key checks for cleanup
    info("Disabling foreign key checks...");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    success("Foreign key checks disabled");

    // Step 1: Delete all user_role assignments
    info("Deleting all user role assignments...");
    $stmt = $pdo->prepare("DELETE FROM userrole");
    $stmt->execute();
    $count = $stmt->rowCount();
    success("Deleted $count user role assignments");

    // Step 2: Delete all audit events (to allow user deletion)
    info("Deleting all audit events...");
    $stmt = $pdo->prepare("DELETE FROM auditevent");
    $stmt->execute();
    $count = $stmt->rowCount();
    success("Deleted $count audit events");

    // Step 3: Delete all users
    info("Deleting all users...");
    $stmt = $pdo->prepare("DELETE FROM user");
    $stmt->execute();
    $count = $stmt->rowCount();
    success("Deleted $count users");

    // Step 4: Delete all roles
    info("Deleting all roles...");
    $stmt = $pdo->prepare("DELETE FROM role");
    $stmt->execute();
    $count = $stmt->rowCount();
    success("Deleted $count roles");

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    success("Foreign key checks re-enabled");

    // Step 4: Insert new roles
    info("Inserting new roles...");
    $roles = [
        ['id' => generateUUID(), 'name' => 'tadmin', 'description' => 'Technical Administrator - Full system access'],
        ['id' => generateUUID(), 'name' => 'cadmin', 'description' => 'Clinical Administrator - Clinical system management'],
        ['id' => generateUUID(), 'name' => 'pclinician', 'description' => 'Primary Clinician - Full patient care access'],
        ['id' => generateUUID(), 'name' => 'dclinician', 'description' => 'Delegated Clinician - Limited patient care access'],
        ['id' => generateUUID(), 'name' => '1clinician', 'description' => 'First Response Clinician - Urgent care and triage'],
        ['id' => generateUUID(), 'name' => 'custom', 'description' => 'Custom Role - Configurable permissions']
    ];

    $stmt = $pdo->prepare("INSERT INTO role (role_id, name, attributes) VALUES (:id, :name, :attributes)");
    
    foreach ($roles as $role) {
        $attributes = json_encode([
            'description' => $role['description'],
            'created_by' => 'system_migration',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $stmt->execute([
            ':id' => $role['id'],
            ':name' => $role['name'],
            ':attributes' => $attributes
        ]);
        success("Created role: {$role['name']}");
    }

    // Step 5: Create default admin user for each role (for testing)
    info("Creating default users for testing...");
    $defaultPassword = password_hash('SafeShift2024!', PASSWORD_BCRYPT);
    
    $users = [
        ['username' => 'tadmin', 'email' => 'tadmin@safeshift.ai', 'role' => 'tadmin'],
        ['username' => 'cadmin', 'email' => 'cadmin@safeshift.ai', 'role' => 'cadmin'],
        ['username' => 'pclinician', 'email' => 'pclinician@safeshift.ai', 'role' => 'pclinician'],
        ['username' => 'dclinician', 'email' => 'dclinician@safeshift.ai', 'role' => 'dclinician'],
        ['username' => '1clinician', 'email' => '1clinician@safeshift.ai', 'role' => '1clinician'],
        ['username' => 'custom_user', 'email' => 'custom@safeshift.ai', 'role' => 'custom']
    ];

    $userStmt = $pdo->prepare("
        INSERT INTO user (user_id, username, email, password_hash, mfa_enabled, status, is_active) 
        VALUES (:id, :username, :email, :password, 0, 'active', 1)
    ");

    $roleStmt = $pdo->prepare("
        INSERT INTO userrole (user_role_id, user_id, role_id) 
        VALUES (:id, :user_id, :role_id)
    ");

    // Get role IDs
    $roleIds = [];
    $result = $pdo->query("SELECT role_id, name FROM role");
    while ($row = $result->fetch()) {
        $roleIds[$row['name']] = $row['role_id'];
    }

    foreach ($users as $user) {
        $userId = generateUUID();
        
        // Create user
        $userStmt->execute([
            ':id' => $userId,
            ':username' => $user['username'],
            ':email' => $user['email'],
            ':password' => $defaultPassword
        ]);
        
        // Assign role
        $roleStmt->execute([
            ':id' => generateUUID(),
            ':user_id' => $userId,
            ':role_id' => $roleIds[$user['role']]
        ]);
        
        success("Created user: {$user['username']} with role: {$user['role']}");
    }

    // Commit transaction
    $pdo->commit();
    
    echo "\n";
    success("Migration completed successfully!");
    echo "\n";
    info("Default users created with password: SafeShift2024!");
    warning("Please change these passwords immediately!");
    
} catch (Exception $e) {
    $pdo->rollBack();
    error("Migration failed: " . $e->getMessage());
    exit(1);
}

/**
 * Generate UUID v4
 */
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}