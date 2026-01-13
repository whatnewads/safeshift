<?php
/**
 * Run session_id migration for AuditEvent table
 * 
 * This script adds the session_id column to the AuditEvent table if it doesn't exist
 */

// Include configuration
require_once __DIR__ . '/../includes/config.php';

echo "=== Running session_id Migration for AuditEvent ===\n\n";

try {
    // Create PDO connection
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected to database: " . DB_NAME . "\n";
    
    // Check if session_id column already exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
        AND TABLE_NAME = 'AuditEvent' 
        AND COLUMN_NAME = 'session_id'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo "✓ Column 'session_id' already exists in AuditEvent table.\n";
    } else {
        echo "Adding 'session_id' column to AuditEvent table...\n";
        
        // Add the column
        $pdo->exec("ALTER TABLE AuditEvent ADD COLUMN session_id VARCHAR(128) NULL AFTER user_agent");
        echo "✓ Column 'session_id' added successfully.\n";
    }
    
    // Check if index exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = :dbname 
        AND TABLE_NAME = 'AuditEvent' 
        AND INDEX_NAME = 'idx_audit_session_id'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo "✓ Index 'idx_audit_session_id' already exists.\n";
    } else {
        echo "Adding index 'idx_audit_session_id'...\n";
        $pdo->exec("CREATE INDEX idx_audit_session_id ON AuditEvent(session_id)");
        echo "✓ Index 'idx_audit_session_id' added successfully.\n";
    }
    
    echo "\n=== Migration Complete ===\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}