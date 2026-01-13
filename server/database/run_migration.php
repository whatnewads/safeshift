<?php
/**
 * Database Migration Runner
 * Adds session_id column to AuditEvent table
 * 
 * Run from command line: php database/run_migration.php
 */

// Load configuration
require_once __DIR__ . '/../includes/config.php';

echo "SafeShift EHR - Database Migration\n";
echo "===================================\n\n";

try {
    // Connect to database
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "✓ Connected to database: " . DB_NAME . "\n\n";
    
    // Check if session_id column already exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
        AND TABLE_NAME = 'AuditEvent' 
        AND COLUMN_NAME = 'session_id'
    ");
    $stmt->execute(['dbname' => DB_NAME]);
    $result = $stmt->fetch();
    
    if ($result['cnt'] > 0) {
        echo "ℹ session_id column already exists in AuditEvent table.\n";
        echo "No migration needed.\n";
    } else {
        echo "Adding session_id column to AuditEvent table...\n";
        
        // Add the session_id column
        $pdo->exec("ALTER TABLE AuditEvent ADD COLUMN session_id VARCHAR(128) NULL AFTER user_agent");
        echo "✓ session_id column added successfully.\n";
        
        // Check if index exists
        $indexStmt = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = :dbname
            AND TABLE_NAME = 'AuditEvent'
            AND INDEX_NAME = 'idx_audit_session_id'
        ");
        $indexStmt->execute(['dbname' => DB_NAME]);
        $indexResult = $indexStmt->fetch();
        
        if ($indexResult['cnt'] == 0) {
            $pdo->exec("CREATE INDEX idx_audit_session_id ON AuditEvent(session_id)");
            echo "✓ Index idx_audit_session_id created successfully.\n";
        } else {
            echo "ℹ Index idx_audit_session_id already exists.\n";
        }
    }
    
    // Verify the column was added
    echo "\nVerifying migration...\n";
    $verifyStmt = $pdo->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
        AND TABLE_NAME = 'AuditEvent' 
        AND COLUMN_NAME = 'session_id'
    ");
    $verifyStmt->execute(['dbname' => DB_NAME]);
    $columnInfo = $verifyStmt->fetch();
    
    if ($columnInfo) {
        echo "✓ Migration verified:\n";
        echo "  - Column: {$columnInfo['COLUMN_NAME']}\n";
        echo "  - Type: {$columnInfo['DATA_TYPE']}({$columnInfo['CHARACTER_MAXIMUM_LENGTH']})\n";
        echo "  - Nullable: {$columnInfo['IS_NULLABLE']}\n";
    } else {
        echo "✗ Migration verification failed - column not found!\n";
        exit(1);
    }
    
    echo "\n===================================\n";
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}