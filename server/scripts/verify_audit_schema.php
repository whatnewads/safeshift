<?php
/**
 * Verify Audit Log Schema
 * Tests the database schema for audit logging
 */

require_once __DIR__ . '/../includes/config.php';

echo "=== Audit Log Schema Verification ===\n\n";

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected to database: " . DB_NAME . "\n\n";
    
    // Check AuditEvent table columns
    echo "=== AuditEvent Table Columns ===\n";
    $stmt = $pdo->query('DESCRIBE AuditEvent');
    $columns = [];
    while ($row = $stmt->fetch()) {
        echo sprintf("  %-20s %-20s %s\n", 
            $row['Field'], 
            $row['Type'], 
            ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL')
        );
        $columns[] = $row['Field'];
    }
    
    // Verify required columns exist
    echo "\n=== Column Verification ===\n";
    $requiredColumns = [
        'user_name' => 'VARCHAR(255)',
        'user_role' => 'VARCHAR(50)',
        'patient_id' => 'INT',
        'modified_fields' => 'JSON',
        'old_values' => 'JSON',
        'new_values' => 'JSON',
        'success' => 'BOOLEAN/TINYINT',
        'error_message' => 'TEXT'
    ];
    
    foreach ($requiredColumns as $col => $type) {
        if (in_array($col, $columns)) {
            echo "  ✓ $col - EXISTS\n";
        } else {
            echo "  ✗ $col - MISSING\n";
        }
    }
    
    // Check indexes
    echo "\n=== AuditEvent Indexes ===\n";
    $stmt = $pdo->query('SHOW INDEX FROM AuditEvent');
    $indexes = [];
    while ($row = $stmt->fetch()) {
        if (!in_array($row['Key_name'], $indexes)) {
            echo sprintf("  %-25s on %s\n", $row['Key_name'], $row['Column_name']);
            $indexes[] = $row['Key_name'];
        }
    }
    
    // Verify required indexes
    echo "\n=== Index Verification ===\n";
    $requiredIndexes = [
        'idx_ae_patient_id',
        'idx_ae_success',
        'idx_ae_user_name'
    ];
    
    foreach ($requiredIndexes as $idx) {
        if (in_array($idx, $indexes)) {
            echo "  ✓ $idx - EXISTS\n";
        } else {
            echo "  ✗ $idx - MISSING\n";
        }
    }
    
    // Also check audit_log table if it exists
    echo "\n=== audit_log Table Columns ===\n";
    $stmt = $pdo->query('DESCRIBE audit_log');
    $auditLogColumns = [];
    while ($row = $stmt->fetch()) {
        echo sprintf("  %-20s %-20s %s\n", 
            $row['Field'], 
            $row['Type'], 
            ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL')
        );
        $auditLogColumns[] = $row['Field'];
    }
    
    echo "\n=== Schema Verification Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
