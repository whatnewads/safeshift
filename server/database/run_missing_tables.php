<?php
require_once __DIR__ . '/../includes/bootstrap.php';

echo "Creating Missing SafeShift EHR Tables\n";
echo "=====================================\n\n";

try {
    $db = \App\db\pdo();
    
    // Read the SQL file
    $sql_file = __DIR__ . '/migrations/missing_tables.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql_content)));
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        try {
            if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $statement, $matches)) {
                echo "Creating table " . $matches[1] . "... ";
            }
            
            $db->exec($statement);
            $success_count++;
            echo "✓\n";
            
        } catch (PDOException $e) {
            $error_count++;
            echo "✗ " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=====================================\n";
    echo "Summary: $success_count successful, $error_count failed\n";
    
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration complete!\n";