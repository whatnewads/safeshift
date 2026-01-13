<?php
/**
 * Database Connection Test Script
 * 
 * Run from command line:
 * php scripts/test_db_connection.php
 * 
 * Tests the database connection using the application's configuration
 */

echo "========================================\n";
echo "SafeShift EHR - Database Connection Test\n";
echo "========================================\n\n";

// Load application configuration
require_once __DIR__ . '/../includes/config.php';

echo "Configuration loaded successfully.\n\n";

// Display connection parameters (hide password)
echo "Connection Parameters:\n";
echo "  Host:     " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
echo "  Port:     " . (defined('DB_PORT') ? DB_PORT : 'NOT DEFINED') . "\n";
echo "  Database: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
echo "  User:     " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
echo "  Charset:  " . (defined('DB_CHARSET') ? DB_CHARSET : 'NOT DEFINED') . "\n";
echo "  Password: " . (defined('DB_PASS') ? '********' : 'NOT DEFINED') . "\n\n";

// Check if all required constants are defined
$missing = [];
if (!defined('DB_HOST')) $missing[] = 'DB_HOST';
if (!defined('DB_PORT')) $missing[] = 'DB_PORT';
if (!defined('DB_NAME')) $missing[] = 'DB_NAME';
if (!defined('DB_USER')) $missing[] = 'DB_USER';
if (!defined('DB_PASS')) $missing[] = 'DB_PASS';

if (!empty($missing)) {
    echo "ERROR: Missing configuration constants: " . implode(', ', $missing) . "\n";
    exit(1);
}

echo "Testing database connection...\n\n";

try {
    // Build DSN
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET ?? 'utf8mb4'
    );
    
    echo "DSN: mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . (DB_CHARSET ?? 'utf8mb4') . "\n\n";
    
    // PDO options
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5, // 5 second timeout
    ];
    
    // Attempt connection
    $startTime = microtime(true);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $connectTime = (microtime(true) - $startTime) * 1000;
    
    echo "✓ SUCCESS: Database connection established!\n";
    echo "  Connection time: " . number_format($connectTime, 2) . " ms\n\n";
    
    // Test simple query
    $result = $pdo->query("SELECT 1 as connected");
    $row = $result->fetch();
    if ($row && $row['connected'] == 1) {
        echo "✓ SUCCESS: Simple query (SELECT 1) executed successfully.\n\n";
    }
    
    // Get server version
    $version = $pdo->query("SELECT VERSION() as version")->fetch();
    echo "Database Server Info:\n";
    echo "  Version: " . ($version['version'] ?? 'Unknown') . "\n\n";
    
    // List tables
    echo "Tables in database '" . DB_NAME . "':\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) === 0) {
        echo "  (No tables found - database may be empty)\n";
    } else {
        echo "  Total tables: " . count($tables) . "\n\n";
        
        // Display first 20 tables
        $displayTables = array_slice($tables, 0, 20);
        foreach ($displayTables as $i => $table) {
            echo "  " . ($i + 1) . ". " . $table . "\n";
        }
        
        if (count($tables) > 20) {
            echo "  ... and " . (count($tables) - 20) . " more tables.\n";
        }
    }
    
    echo "\n========================================\n";
    echo "RESULT: Connection test PASSED ✓\n";
    echo "========================================\n";
    
    exit(0);
    
} catch (PDOException $e) {
    echo "✗ ERROR: Connection failed!\n\n";
    echo "Error Details:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Code:    " . $e->getCode() . "\n";
    
    // Provide troubleshooting hints based on error
    echo "\nTroubleshooting:\n";
    
    $errorMsg = $e->getMessage();
    
    if (strpos($errorMsg, 'Connection refused') !== false) {
        echo "  - Check if MySQL/MariaDB is running\n";
        echo "  - Verify the port number is correct\n";
        echo "  - Check firewall settings\n";
    } elseif (strpos($errorMsg, 'Access denied') !== false) {
        echo "  - Verify username and password are correct\n";
        echo "  - Check if user has permissions to access the database\n";
        echo "  - Verify the user can connect from the specified host\n";
    } elseif (strpos($errorMsg, 'Unknown database') !== false) {
        echo "  - The database '" . DB_NAME . "' does not exist\n";
        echo "  - Create the database or check the DB_NAME in .env\n";
    } elseif (strpos($errorMsg, 'timed out') !== false) {
        echo "  - The connection timed out\n";
        echo "  - Check if the host is reachable\n";
        echo "  - Verify network connectivity\n";
    } elseif (strpos($errorMsg, 'Name or service not known') !== false || strpos($errorMsg, 'getaddrinfo') !== false) {
        echo "  - The hostname could not be resolved\n";
        echo "  - Check the DB_HOST value in .env\n";
    }
    
    echo "\n========================================\n";
    echo "RESULT: Connection test FAILED ✗\n";
    echo "========================================\n";
    
    exit(1);
}
