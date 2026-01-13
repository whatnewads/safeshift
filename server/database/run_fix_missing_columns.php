<?php
/**
 * Migration Runner: Fix Missing Columns
 * 
 * This script adds missing columns to:
 * - auditevent table (checksum column)
 * - userrole table (assigned_at column)
 * 
 * Run this script from the command line:
 *   php database/run_fix_missing_columns.php
 * 
 * Or via web browser (development only)
 */

// Prevent execution in production without CLI
if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_MIGRATION')) {
    // For web access, uncomment the next line:
    // define('ALLOW_WEB_MIGRATION', true);
    if (!defined('ALLOW_WEB_MIGRATION')) {
        die("This script should be run from CLI. For web access, edit the script to allow it.\n");
    }
}

echo "===========================================\n";
echo "Migration: Fix Missing Columns\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n\n";

// Load application configuration
require_once __DIR__ . '/../includes/config.php';

// Database configuration from includes/config.php
$config = [
    'host' => defined('DB_HOST') ? DB_HOST : '127.0.0.1',
    'port' => defined('DB_PORT') ? DB_PORT : 3306,
    'database' => defined('DB_NAME') ? DB_NAME : 'safeshift_ehr_001_0',
    'username' => defined('DB_USER') ? DB_USER : 'root',
    'password' => defined('DB_PASS') ? DB_PASS : '',
    'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
];

try {
    // Connect to database
    echo "Connecting to database '{$config['database']}'...\n";
    
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "✓ Connected successfully!\n\n";
    
    // Check current state
    echo "Checking current table structures...\n\n";
    
    // Check auditevent checksum column
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'auditevent' 
        AND COLUMN_NAME = 'checksum'
    ");
    $checksumExists = $stmt->fetch()['count'] > 0;
    
    // Check userrole assigned_at column
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'userrole' 
        AND COLUMN_NAME = 'assigned_at'
    ");
    $assignedAtExists = $stmt->fetch()['count'] > 0;
    
    echo "Current state:\n";
    echo "  - auditevent.checksum: " . ($checksumExists ? "EXISTS" : "MISSING") . "\n";
    echo "  - userrole.assigned_at: " . ($assignedAtExists ? "EXISTS" : "MISSING") . "\n\n";
    
    $changesApplied = 0;
    
    // Apply migration 1: Add checksum to auditevent
    if (!$checksumExists) {
        echo "Adding 'checksum' column to 'auditevent' table...\n";
        
        $pdo->exec("
            ALTER TABLE `auditevent`
            ADD COLUMN `checksum` CHAR(64) NULL 
            COMMENT 'SHA-256 hash for tamper detection / integrity verification'
            AFTER `flagged`
        ");
        
        echo "✓ Column 'checksum' added successfully!\n\n";
        $changesApplied++;
    } else {
        echo "ℹ Column 'checksum' already exists in 'auditevent' - skipping.\n\n";
    }
    
    // Apply migration 2: Add assigned_at to userrole
    if (!$assignedAtExists) {
        echo "Adding 'assigned_at' column to 'userrole' table...\n";
        
        $pdo->exec("
            ALTER TABLE `userrole`
            ADD COLUMN `assigned_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP
            COMMENT 'Timestamp when the role was assigned to the user'
            AFTER `role_id`
        ");
        
        echo "✓ Column 'assigned_at' added successfully!\n\n";
        $changesApplied++;
    } else {
        echo "ℹ Column 'assigned_at' already exists in 'userrole' - skipping.\n\n";
    }
    
    // Verify changes
    echo "===========================================\n";
    echo "Verification\n";
    echo "===========================================\n\n";
    
    // Verify auditevent
    echo "auditevent table structure:\n";
    $stmt = $pdo->query("DESCRIBE auditevent");
    $columns = $stmt->fetchAll();
    foreach ($columns as $col) {
        $marker = ($col['Field'] === 'checksum') ? " ← NEW" : "";
        echo "  {$col['Field']}: {$col['Type']} {$col['Null']}{$marker}\n";
    }
    echo "\n";
    
    // Verify userrole
    echo "userrole table structure:\n";
    $stmt = $pdo->query("DESCRIBE userrole");
    $columns = $stmt->fetchAll();
    foreach ($columns as $col) {
        $marker = ($col['Field'] === 'assigned_at') ? " ← NEW" : "";
        echo "  {$col['Field']}: {$col['Type']} {$col['Null']}{$marker}\n";
    }
    echo "\n";
    
    // Summary
    echo "===========================================\n";
    echo "Migration Complete!\n";
    echo "===========================================\n";
    echo "Changes applied: {$changesApplied}\n";
    
    if ($changesApplied > 0) {
        echo "\nThe following columns were added:\n";
        if (!$checksumExists) {
            echo "  ✓ auditevent.checksum (CHAR(64))\n";
        }
        if (!$assignedAtExists) {
            echo "  ✓ userrole.assigned_at (DATETIME)\n";
        }
    } else {
        echo "\nNo changes were needed - all columns already exist.\n";
    }
    
    echo "\nMigration finished successfully at " . date('Y-m-d H:i:s') . "\n";
    
} catch (PDOException $e) {
    echo "\n❌ DATABASE ERROR: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "  1. Database credentials are correct\n";
    echo "  2. MySQL/MariaDB server is running\n";
    echo "  3. Database '{$config['database']}' exists\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
