<?php
/**
 * Migration Runner Script
 * Run the 2FA and mail tables migration
 */

// Load environment variables
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv("$key=$value");
        }
    }
}

// Database config
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'safeshift_ehr_001_0';
$user = getenv('DB_USER') ?: 'safeshift_admin';
$pass = getenv('DB_PASS') ?: '';

echo "Connecting to database: $dbname on $host:$port as $user\n";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "Connected successfully!\n";
    
    // Read migration file
    $migrationFile = dirname(__DIR__) . '/database/migrations/2025_12_31_add_2fa_and_mail_tables.sql';
    
    if (!file_exists($migrationFile)) {
        die("Migration file not found: $migrationFile\n");
    }
    
    $sql = file_get_contents($migrationFile);
    echo "Loaded migration file: " . basename($migrationFile) . "\n";
    
    // Remove SQL comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Split into individual statements by semicolon
    $rawStatements = explode(';', $sql);
    $statements = [];
    
    foreach ($rawStatements as $stmt) {
        $stmt = trim($stmt);
        if (!empty($stmt) && strlen($stmt) > 5) {
            $statements[] = $stmt;
        }
    }
    
    echo "Found " . count($statements) . " SQL statements to execute\n";
    
    $executed = 0;
    $errors = [];
    
    foreach ($statements as $stmt) {
        // Skip comments-only statements
        if (empty(trim(preg_replace('/--.*$/m', '', $stmt)))) {
            continue;
        }
        
        try {
            $pdo->exec($stmt);
            $executed++;
            
            // Show progress
            if (preg_match('/CREATE TABLE.+`(\w+)`/i', $stmt, $matches)) {
                echo "  Created table: {$matches[1]}\n";
            } elseif (preg_match('/ALTER TABLE\s+`(\w+)`/i', $stmt, $matches)) {
                echo "  Altered table: {$matches[1]}\n";
            } elseif (preg_match('/CREATE.+VIEW\s+`(\w+)`/i', $stmt, $matches)) {
                echo "  Created view: {$matches[1]}\n";
            }
            
        } catch (PDOException $e) {
            // Check for "already exists" errors
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  Skipped (already exists): " . substr($stmt, 0, 50) . "...\n";
            } else {
                $errors[] = [
                    'statement' => substr($stmt, 0, 100),
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Executed: $executed statements\n";
    
    if (!empty($errors)) {
        echo "Errors: " . count($errors) . "\n";
        foreach ($errors as $err) {
            echo "  - " . $err['error'] . "\n";
        }
    }
    
    // Verify tables were created
    echo "\n=== Verifying Tables ===\n";
    $tables = ['two_factor_codes', 'mail_log', 'email_rate_limits'];
    
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            echo "  ✓ Table '$table' exists\n";
        } else {
            echo "  ✗ Table '$table' NOT found\n";
        }
    }
    
    // Check user table columns
    echo "\n=== Verifying User Table Columns ===\n";
    $columns = ['last_reminder_sent_at', 'email_opt_in_reminders', 'email_opt_in_security'];
    
    foreach ($columns as $col) {
        $result = $pdo->query("SHOW COLUMNS FROM `user` LIKE '$col'")->fetch();
        if ($result) {
            echo "  ✓ Column 'user.$col' exists\n";
        } else {
            echo "  ✗ Column 'user.$col' NOT found\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}
