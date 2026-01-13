<?php
/**
 * Run migration for tables without foreign keys
 */

require_once __DIR__ . '/../includes/bootstrap.php';

echo "Creating SafeShift EHR Tables (No Foreign Keys)\n";
echo "==============================================\n\n";

try {
    $db = \App\db\pdo();
    
    // Read the SQL file
    $sql_file = __DIR__ . '/migrations/safeshift_schema_no_fk.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Split by semicolons
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql_content)));
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $index => $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        try {
            // Extract table name if it's a CREATE TABLE statement
            if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $statement, $matches)) {
                echo "Creating table " . $matches[1] . "... ";
            } else {
                echo "Executing statement " . ($index + 1) . "... ";
            }
            
            $db->exec($statement);
            $success_count++;
            echo "✓\n";
            
        } catch (PDOException $e) {
            $error_count++;
            echo "✗ " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n==============================================\n";
    echo "Summary: $success_count successful, $error_count failed\n\n";
    
    // Check which tables were created
    echo "Checking created tables:\n";
    $stmt = $db->query("SHOW TABLES LIKE '%_log'");
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "✓ $table\n";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE '%template%'");
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "✓ $table\n";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE '%flag%'");
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "✓ $table\n";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE '%training%'");
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "✓ $table\n";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE '%qa_%'");
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "✓ $table\n";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE 'appointments'");
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "✓ $table\n";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE 'orders'");
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "✓ $table\n";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE 'dot_tests'");
    while ($table = $stmt->fetch(PDO::FETCH_COLUMN)) {
        echo "✓ $table\n";
    }
    
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration complete!\n";