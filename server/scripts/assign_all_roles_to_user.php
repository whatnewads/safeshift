#!/usr/bin/env php
<?php
/**
 * Assign All Roles to a User
 *
 * This script assigns all available roles to a specified user
 * so they can use the role-switching feature.
 *
 * Usage: php scripts/assign_all_roles_to_user.php [username]
 * Default: assigns roles to '1clinician' user
 */

require_once __DIR__ . '/../includes/bootstrap.php';

use Model\Core\Database;

// Get username from command line or default to 1clinician
$targetUsername = $argv[1] ?? '1clinician';

echo "========================================\n";
echo "  Assign All Roles to User\n";
echo "========================================\n\n";

try {
    $db = Database::getInstance();
    
    // Find the target user
    $user = $db->fetchOne(
        "SELECT user_id, username FROM user WHERE username = :username",
        ['username' => $targetUsername]
    );
    
    if (!$user) {
        echo "ERROR: User '{$targetUsername}' not found!\n";
        exit(1);
    }
    
    $userId = $user['user_id'];
    echo "Target user: {$user['username']} (ID: {$userId})\n\n";
    
    // Get all available roles
    $allRoles = $db->fetchAll("SELECT role_id, name, slug FROM role ORDER BY role_id");
    
    echo "Available roles:\n";
    foreach ($allRoles as $role) {
        echo "  - {$role['name']} ({$role['slug']})\n";
    }
    echo "\n";
    
    // Get current user roles
    $currentRoles = $db->fetchAll(
        "SELECT r.role_id, r.slug
         FROM userrole ur
         JOIN role r ON ur.role_id = r.role_id
         WHERE ur.user_id = :user_id",
        ['user_id' => $userId]
    );
    $currentRoleIds = array_column($currentRoles, 'role_id');
    
    echo "Current roles for {$targetUsername}:\n";
    if (empty($currentRoles)) {
        echo "  (none)\n";
    } else {
        foreach ($currentRoles as $role) {
            echo "  - {$role['slug']}\n";
        }
    }
    echo "\n";
    
    // Add missing roles
    $addedCount = 0;
    
    echo "Adding missing roles:\n";
    foreach ($allRoles as $role) {
        if (!in_array($role['role_id'], $currentRoleIds)) {
            $userRoleId = sprintf(
                '%s-%s-%s-%s-%s',
                substr(bin2hex(random_bytes(4)), 0, 8),
                substr(bin2hex(random_bytes(2)), 0, 4),
                '4' . substr(bin2hex(random_bytes(2)), 0, 3),
                dechex(8 + random_int(0, 3)) . substr(bin2hex(random_bytes(2)), 0, 3),
                substr(bin2hex(random_bytes(6)), 0, 12)
            );
            
            $db->insert('userrole', [
                'user_role_id' => $userRoleId,
                'user_id' => $userId,
                'role_id' => $role['role_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            echo "  + Added: {$role['name']} ({$role['slug']})\n";
            $addedCount++;
        } else {
            echo "  - Already has: {$role['name']} ({$role['slug']})\n";
        }
    }
    
    echo "\n========================================\n";
    echo "  Summary\n";
    echo "========================================\n";
    echo "User: {$targetUsername}\n";
    echo "Roles added: {$addedCount}\n";
    echo "Total roles: " . count($allRoles) . "\n\n";
    
    // Verify final state
    $finalRoles = $db->fetchAll(
        "SELECT r.name, r.slug
         FROM userrole ur
         JOIN role r ON ur.role_id = r.role_id
         WHERE ur.user_id = :user_id
         ORDER BY r.slug",
        ['user_id' => $userId]
    );
    
    echo "Final roles for {$targetUsername}:\n";
    foreach ($finalRoles as $role) {
        echo "  - {$role['name']} ({$role['slug']})\n";
    }
    echo "\n";
    
    echo "SUCCESS! User '{$targetUsername}' now has all " . count($finalRoles) . " roles.\n";
    echo "The 'Change Role' button should now appear in the sidebar.\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
