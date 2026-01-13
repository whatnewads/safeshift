<?php
require_once __DIR__ . '/../includes/bootstrap.php';

try {
    $db = \App\db\pdo();
    
    // Check character sets and engines
    echo "=== Checking table engines and character sets ===\n";
    
    $stmt = $db->query("
        SELECT 
            TABLE_NAME,
            ENGINE,
            TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('user', 'patients', 'encounters', 'training_requirements')
    ");
    
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tables as $table) {
        echo sprintf("%s: Engine=%s, Collation=%s\n", 
            $table['TABLE_NAME'],
            $table['ENGINE'],
            $table['TABLE_COLLATION']
        );
    }
    
    // Check specific column definitions
    echo "\n=== Checking key column definitions ===\n";
    
    $columns_to_check = [
        ['user', 'user_id'],
        ['patients', 'patient_id'],
        ['encounters', 'encounter_id'],
        ['training_requirements', 'requirement_id']
    ];
    
    foreach ($columns_to_check as [$table, $column]) {
        $stmt = $db->query("
            SELECT 
                COLUMN_NAME,
                COLUMN_TYPE,
                CHARACTER_SET_NAME,
                COLLATION_NAME,
                IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '$table'
            AND COLUMN_NAME = '$column'
        ");
        
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($col) {
            echo sprintf("%s.%s: %s, charset=%s, collation=%s, nullable=%s\n",
                $table,
                $col['COLUMN_NAME'],
                $col['COLUMN_TYPE'],
                $col['CHARACTER_SET_NAME'],
                $col['COLLATION_NAME'],
                $col['IS_NULLABLE']
            );
        }
    }
    
    // Test creating a simple table with foreign key
    echo "\n=== Testing foreign key creation ===\n";
    
    try {
        $db->exec("DROP TABLE IF EXISTS test_fk_table");
        $db->exec("
            CREATE TABLE test_fk_table (
                id CHAR(36) PRIMARY KEY,
                user_id CHAR(36),
                FOREIGN KEY (user_id) REFERENCES user(user_id)
            )
        ");
        echo "✓ Successfully created test table with foreign key to user table\n";
        $db->exec("DROP TABLE test_fk_table");
    } catch (PDOException $e) {
        echo "✗ Failed to create test table: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}