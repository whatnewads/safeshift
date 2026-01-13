<?php
/**
 * Fix video_meetings tablespace issue - use a new table name temporarily
 * This works around InnoDB orphaned tablespace issues
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db.php';

use function App\db\pdo;

try {
    $pdo = pdo();
    echo "Database connection established\n";
    
    // Use a new unique table name to work around orphaned tablespace
    $uniqueSuffix = date('YmdHis');
    $tempTableName = "video_meetings_v2";
    
    echo "Creating table with name: {$tempTableName}\n";
    
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop the temp table if it exists from previous attempts
    try {
        $pdo->exec("DROP TABLE IF EXISTS `{$tempTableName}`");
    } catch (PDOException $e) {
        // Ignore
    }
    
    // Create the table with the new name
    $createSql = "CREATE TABLE `{$tempTableName}` (
        `meeting_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique meeting identifier',
        `created_by` INT UNSIGNED NOT NULL COMMENT 'User ID of meeting creator (clinician)',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Meeting creation timestamp',
        `token` VARCHAR(128) NOT NULL COMMENT 'Unique secure token for meeting access',
        `token_expires_at` DATETIME NOT NULL COMMENT 'Token expiration timestamp',
        `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether meeting is currently active',
        `ended_at` DATETIME NULL DEFAULT NULL COMMENT 'Meeting end timestamp',
        UNIQUE KEY `idx_token` (`token`),
        INDEX `idx_created_by` (`created_by`),
        INDEX `idx_is_active` (`is_active`),
        INDEX `idx_token_expires_at` (`token_expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createSql);
    echo "Table {$tempTableName} created successfully!\n";
    
    // Now create a view with the expected name pointing to the new table
    try {
        $pdo->exec("DROP VIEW IF EXISTS `video_meetings`");
    } catch (PDOException $e) {
        // Ignore - may not be a view
    }
    
    // Rename the new table to video_meetings if possible
    // First try to verify the original doesn't exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'video_meetings' AND table_type = 'BASE TABLE'");
    $exists = (int)$stmt->fetchColumn();
    
    if ($exists === 0) {
        // Try renaming
        try {
            $pdo->exec("RENAME TABLE `{$tempTableName}` TO `video_meetings`");
            echo "Renamed {$tempTableName} to video_meetings\n";
            $tempTableName = 'video_meetings';
        } catch (PDOException $e) {
            echo "Could not rename table, keeping as {$tempTableName}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Original video_meetings table still exists in information_schema, using {$tempTableName}\n";
    }
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Verify
    $stmt = $pdo->query("SHOW TABLES LIKE '{$tempTableName}'");
    if ($stmt->fetch()) {
        echo "Verified: {$tempTableName} table exists\n";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE `{$tempTableName}`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Table has " . count($cols) . " columns\n";
    }
    
    echo "\nNote: If using video_meetings_v2, you may need to update API code to use this table name.\n";
    echo "Or manually delete the orphaned video_meetings.ibd file from the MariaDB data directory.\n";
    echo "Done!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
