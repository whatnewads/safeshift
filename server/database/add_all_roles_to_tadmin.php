<?php
/**
 * Script to add all available roles to the tadmin user
 * This enables testing the role switcher functionality
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';

use function App\db\pdo;

echo "=== Adding All Roles to tadmin User ===\n\n";

try {
    $db = pdo();
    
    // 1. Find tadmin's user_id
    $stmt = $db->prepare("SELECT user_id, username FROM user WHERE username = :username");
    $stmt->execute(['username' => 'tadmin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("Error: User 'tadmin' not found in database.\n");
    }
    
    $user_id = $user['user_id'];
    echo "Found tadmin user: {$user_id}\n\n";
    
    // 2. Get all available roles
    $roles_stmt = $db->query("SELECT role_id, name, slug FROM role ORDER BY name");
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Available roles:\n";
    foreach ($roles as $role) {
        echo "  - {$role['name']} ({$role['slug']})\n";
    }
    echo "\n";
    
    // 3. Get tadmin's current roles
    $current_roles_stmt = $db->prepare("SELECT role_id FROM userrole WHERE user_id = :user_id");
    $current_roles_stmt->execute(['user_id' => $user_id]);
    $current_role_ids = $current_roles_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Current roles for tadmin: " . count($current_role_ids) . "\n\n";
    
    // 4. Add missing roles
    $insert_stmt = $db->prepare("
        INSERT INTO userrole (user_role_id, user_id, role_id, assigned_by, created_at)
        VALUES (:user_role_id, :user_id, :role_id, 'system', CURRENT_TIMESTAMP)
    ");
    
    $added = 0;
    $skipped = 0;
    
    foreach ($roles as $role) {
        if (in_array($role['role_id'], $current_role_ids)) {
            echo "  [SKIP] {$role['name']} - already assigned\n";
            $skipped++;
        } else {
            // Generate UUID for user_role_id
            $user_role_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $insert_stmt->execute([
                'user_role_id' => $user_role_id,
                'user_id' => $user_id,
                'role_id' => $role['role_id']
            ]);
            
            echo "  [ADDED] {$role['name']}\n";
            $added++;
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Roles added: {$added}\n";
    echo "Roles skipped (already assigned): {$skipped}\n";
    echo "Total roles for tadmin: " . ($added + count($current_role_ids)) . "\n\n";
    
    // 5. Verify final state
    $verify_stmt = $db->prepare("
        SELECT r.name, r.slug 
        FROM userrole ur 
        JOIN role r ON ur.role_id = r.role_id 
        WHERE ur.user_id = :user_id
        ORDER BY r.name
    ");
    $verify_stmt->execute(['user_id' => $user_id]);
    $final_roles = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== tadmin's Roles After Update ===\n";
    foreach ($final_roles as $role) {
        echo "  - {$role['name']} ({$role['slug']})\n";
    }
    echo "\nDone! Log out and back in to see the role switcher.\n";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
