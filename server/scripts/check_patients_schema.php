<?php
/**
 * Check patients table schema
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../model/Core/Database.php';

use Model\Core\Database;

echo "Checking patients table schema...\n";
echo "================================\n\n";

try {
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->query('DESCRIBE patients');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in 'patients' table:\n";
    echo str_repeat("-", 60) . "\n";
    
    foreach ($columns as $col) {
        echo sprintf("%-25s %s\n", $col['Field'], $col['Type']);
    }
    
    echo "\n";
    
    // Check for SSN-related columns
    echo "SSN-related columns:\n";
    $ssnFound = false;
    foreach ($columns as $col) {
        if (stripos($col['Field'], 'ssn') !== false) {
            echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
            $ssnFound = true;
        }
    }
    if (!$ssnFound) {
        echo "  (none found)\n";
    }
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
