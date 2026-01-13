<?php
/**
 * Database Migration: Enhance audit_log Table
 * 
 * Adds missing fields required for HIPAA compliance:
 * - user_name: Human-readable name for audit reports
 * - patient_id: Direct link to patient records for PHI access tracking
 * - modified_fields: JSON array of changed field names
 * - old_values: Previous state before modification
 * - new_values: New state after modification
 * - success: Whether the operation succeeded
 * - error_message: Error details for failed operations
 * 
 * @package SafeShift\Database\Migrations
 * @version 1.0.0
 * @date 2026-01-12
 */

declare(strict_types=1);

namespace Database\Migrations;

use PDO;
use Exception;

/**
 * Run the migration
 * 
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function runMigration(PDO $pdo): bool
{
    $migrations = [];
    
    // Check if we should alter existing audit_log table or create new structure
    // First, check if the audit_log table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
    $auditLogExists = $stmt->rowCount() > 0;
    
    if ($auditLogExists) {
        // Get existing columns
        $existingColumns = getExistingColumns($pdo, 'audit_log');
        
        // Add user_name column if not exists
        if (!in_array('user_name', $existingColumns)) {
            $migrations[] = "ALTER TABLE audit_log ADD COLUMN user_name VARCHAR(255) NULL AFTER user_id";
        }
        
        // Add patient_id column if not exists
        if (!in_array('patient_id', $existingColumns)) {
            $migrations[] = "ALTER TABLE audit_log ADD COLUMN patient_id INT UNSIGNED NULL AFTER session_id";
        }
        
        // Add modified_fields column if not exists (after old_values or details)
        if (!in_array('modified_fields', $existingColumns)) {
            if (in_array('old_values', $existingColumns)) {
                $migrations[] = "ALTER TABLE audit_log ADD COLUMN modified_fields JSON NULL AFTER old_values";
            } elseif (in_array('details', $existingColumns)) {
                $migrations[] = "ALTER TABLE audit_log ADD COLUMN modified_fields JSON NULL AFTER details";
            } else {
                $migrations[] = "ALTER TABLE audit_log ADD COLUMN modified_fields JSON NULL";
            }
        }
        
        // Add old_values column if not exists
        if (!in_array('old_values', $existingColumns)) {
            $migrations[] = "ALTER TABLE audit_log ADD COLUMN old_values JSON NULL";
        }
        
        // Add new_values column if not exists
        if (!in_array('new_values', $existingColumns)) {
            $migrations[] = "ALTER TABLE audit_log ADD COLUMN new_values JSON NULL AFTER old_values";
        }
        
        // Add success column if not exists
        if (!in_array('success', $existingColumns)) {
            $migrations[] = "ALTER TABLE audit_log ADD COLUMN success BOOLEAN NOT NULL DEFAULT TRUE";
        }
        
        // Add error_message column if not exists
        if (!in_array('error_message', $existingColumns)) {
            $migrations[] = "ALTER TABLE audit_log ADD COLUMN error_message TEXT NULL";
        }
        
        // Add indexes (check first to avoid duplicates)
        $existingIndexes = getExistingIndexes($pdo, 'audit_log');
        
        if (!in_array('idx_patient_id', $existingIndexes) && in_array('patient_id', $existingColumns) || !in_array('patient_id', $existingColumns)) {
            // Will add index after column is created
            $migrations[] = "ALTER TABLE audit_log ADD INDEX idx_audit_patient_id (patient_id)";
        }
        
        if (!in_array('idx_success', $existingIndexes)) {
            $migrations[] = "ALTER TABLE audit_log ADD INDEX idx_audit_success (success)";
        }
    }
    
    // Also update AuditEvent table if it exists (the one used by AuditService)
    $stmt = $pdo->query("SHOW TABLES LIKE 'AuditEvent'");
    $auditEventExists = $stmt->rowCount() > 0;
    
    if ($auditEventExists) {
        $existingColumns = getExistingColumns($pdo, 'AuditEvent');
        
        // Add user_name column
        if (!in_array('user_name', $existingColumns)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD COLUMN user_name VARCHAR(255) NULL AFTER user_id";
        }
        
        // Add user_role column
        if (!in_array('user_role', $existingColumns)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD COLUMN user_role VARCHAR(50) NULL AFTER user_name";
        }
        
        // Add patient_id column
        if (!in_array('patient_id', $existingColumns)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD COLUMN patient_id INT UNSIGNED NULL AFTER session_id";
        }
        
        // Add modified_fields column
        if (!in_array('modified_fields', $existingColumns)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD COLUMN modified_fields JSON NULL";
        }
        
        // Add old_values column
        if (!in_array('old_values', $existingColumns)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD COLUMN old_values JSON NULL";
        }
        
        // Add new_values column
        if (!in_array('new_values', $existingColumns)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD COLUMN new_values JSON NULL";
        }
        
        // Add success column
        if (!in_array('success', $existingColumns)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD COLUMN success BOOLEAN NOT NULL DEFAULT TRUE";
        }
        
        // Add error_message column
        if (!in_array('error_message', $existingColumns)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD COLUMN error_message TEXT NULL";
        }
        
        // Add indexes
        $existingIndexes = getExistingIndexes($pdo, 'AuditEvent');
        
        if (!in_array('idx_ae_patient_id', $existingIndexes)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD INDEX idx_ae_patient_id (patient_id)";
        }
        
        if (!in_array('idx_ae_success', $existingIndexes)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD INDEX idx_ae_success (success)";
        }
        
        if (!in_array('idx_ae_user_name', $existingIndexes)) {
            $migrations[] = "ALTER TABLE AuditEvent ADD INDEX idx_ae_user_name (user_name)";
        }
    }
    
    // Execute migrations
    $executed = 0;
    $errors = [];
    
    foreach ($migrations as $sql) {
        try {
            $pdo->exec($sql);
            $executed++;
            echo "✓ Executed: " . substr($sql, 0, 80) . "...\n";
        } catch (Exception $e) {
            // Ignore duplicate column/index errors
            if (strpos($e->getMessage(), 'Duplicate column') === false && 
                strpos($e->getMessage(), 'Duplicate key name') === false) {
                $errors[] = "Failed: $sql - " . $e->getMessage();
                echo "✗ Failed: " . substr($sql, 0, 80) . "... - " . $e->getMessage() . "\n";
            } else {
                echo "⊘ Skipped (already exists): " . substr($sql, 0, 60) . "...\n";
            }
        }
    }
    
    echo "\n";
    echo "Migration Summary:\n";
    echo "- Executed: $executed\n";
    echo "- Errors: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
        return false;
    }
    
    return true;
}

/**
 * Get existing columns for a table
 * 
 * @param PDO $pdo
 * @param string $tableName
 * @return array
 */
function getExistingColumns(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tableName`");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $columns;
}

/**
 * Get existing indexes for a table
 * 
 * @param PDO $pdo
 * @param string $tableName
 * @return array
 */
function getExistingIndexes(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->prepare("SHOW INDEX FROM `$tableName`");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $indexes = [];
    foreach ($results as $row) {
        $indexes[] = $row['Key_name'];
    }
    
    return array_unique($indexes);
}

/**
 * Rollback migration (for development/testing)
 * 
 * @param PDO $pdo
 * @return bool
 */
function rollbackMigration(PDO $pdo): bool
{
    $dropColumns = [
        "ALTER TABLE audit_log DROP COLUMN IF EXISTS user_name",
        "ALTER TABLE audit_log DROP COLUMN IF EXISTS patient_id", 
        "ALTER TABLE audit_log DROP COLUMN IF EXISTS modified_fields",
        "ALTER TABLE audit_log DROP COLUMN IF EXISTS success",
        "ALTER TABLE audit_log DROP COLUMN IF EXISTS error_message",
        "ALTER TABLE AuditEvent DROP COLUMN IF EXISTS user_name",
        "ALTER TABLE AuditEvent DROP COLUMN IF EXISTS user_role",
        "ALTER TABLE AuditEvent DROP COLUMN IF EXISTS patient_id",
        "ALTER TABLE AuditEvent DROP COLUMN IF EXISTS modified_fields",
        "ALTER TABLE AuditEvent DROP COLUMN IF EXISTS old_values",
        "ALTER TABLE AuditEvent DROP COLUMN IF EXISTS new_values",
        "ALTER TABLE AuditEvent DROP COLUMN IF EXISTS success",
        "ALTER TABLE AuditEvent DROP COLUMN IF EXISTS error_message",
    ];
    
    foreach ($dropColumns as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ Rolled back: $sql\n";
        } catch (Exception $e) {
            // Ignore errors for non-existent columns
            if (strpos($e->getMessage(), "check that column/key exists") === false) {
                echo "✗ Rollback error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    return true;
}

// CLI execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    echo "=== SafeShift EHR Audit Log Migration ===\n\n";
    
    // Load config
    require_once __DIR__ . '/../../includes/config.php';
    
    // Get database connection
    try {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname = defined('DB_NAME') ? DB_NAME : 'safeshift_ehr_001_0';
        $username = defined('DB_USER') ? DB_USER : 'safeshift_admin';
        $password = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        echo "Connected to database: $dbname\n\n";
        
        // Check for rollback flag
        if (isset($argv[1]) && $argv[1] === '--rollback') {
            echo "Rolling back migration...\n\n";
            rollbackMigration($pdo);
        } else {
            echo "Running migration...\n\n";
            $result = runMigration($pdo);
            exit($result ? 0 : 1);
        }
        
    } catch (Exception $e) {
        echo "Database connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
