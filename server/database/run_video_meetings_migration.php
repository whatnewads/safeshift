<?php
/**
 * run_video_meetings_migration.php - Video Meetings Database Migration Runner
 * 
 * Executes the video meetings schema migration to create necessary tables
 * for WebRTC video meeting functionality.
 * 
 * Usage: php database/run_video_meetings_migration.php
 * 
 * @package    SafeShift\Database\Migrations
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Include configuration
require_once BASE_PATH . '/includes/config.php';

// Include database connection
require_once BASE_PATH . '/includes/db.php';

use function App\db\pdo;

/**
 * Run the video meetings migration
 */
function runVideoMeetingsMigration(): void
{
    $migrationFile = BASE_PATH . '/database/migrations/video_meetings_schema.sql';
    $logFile = BASE_PATH . '/logs/video_meetings_migration.log';
    
    // Ensure logs directory exists
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    // Log function
    $log = function(string $message, string $level = 'INFO') use ($logFile): void {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        echo $logEntry;
    };
    
    $log('=== Video Meetings Migration Started ===');
    $log("Migration file: {$migrationFile}");
    
    try {
        // Check if migration file exists
        if (!file_exists($migrationFile)) {
            throw new RuntimeException("Migration file not found: {$migrationFile}");
        }
        
        // Read SQL file
        $sql = file_get_contents($migrationFile);
        if ($sql === false) {
            throw new RuntimeException("Failed to read migration file: {$migrationFile}");
        }
        
        $log("Migration file loaded successfully (" . strlen($sql) . " bytes)");
        
        // Get database connection
        $pdo = pdo();
        $log("Database connection established");
        
        // Check current state - which tables exist
        $existingTables = [];
        $tablesToCreate = ['video_meetings', 'meeting_participants', 'meeting_chat_messages', 'video_meeting_logs'];
        
        foreach ($tablesToCreate as $table) {
            // Use direct query for SHOW TABLES LIKE (doesn't support prepared statements in MariaDB)
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table); // Sanitize table name
            $checkSql = "SHOW TABLES LIKE '{$safeTable}'";
            $stmt = $pdo->query($checkSql);
            if ($stmt->fetch()) {
                $existingTables[] = $table;
            }
        }
        
        if (!empty($existingTables)) {
            $log("Warning: The following tables already exist: " . implode(', ', $existingTables), 'WARNING');
            $log("Migration will use CREATE TABLE IF NOT EXISTS to avoid errors", 'WARNING');
        }
        
        // Note: DDL statements (CREATE TABLE) cause implicit commits in MySQL/MariaDB
        // so we cannot use transactions for schema migrations
        $log("Executing migration statements (DDL auto-commits)...");
        
        // Split SQL into individual statements
        // Remove comments and split by semicolon
        $rawStatements = explode(';', $sql);
        $statements = [];
        
        foreach ($rawStatements as $stmt) {
            // Trim whitespace
            $stmt = trim($stmt);
            if (empty($stmt)) continue;
            
            // Remove leading comment lines (-- style)
            $lines = explode("\n", $stmt);
            $cleanedLines = [];
            $foundSql = false;
            
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                // Skip leading comment lines
                if (!$foundSql && (strpos($trimmedLine, '--') === 0 || empty($trimmedLine))) {
                    continue;
                }
                $foundSql = true;
                $cleanedLines[] = $line;
            }
            
            $cleanedStmt = trim(implode("\n", $cleanedLines));
            
            // Skip if statement is now empty or is just a comment block
            if (empty($cleanedStmt)) continue;
            if (preg_match('/^\/\*.*\*\/$/s', $cleanedStmt)) continue;
            
            $statements[] = $cleanedStmt;
        }
        
        $log("Found " . count($statements) . " SQL statements to execute");
        
        $executed = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($statements as $index => $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            
            // Skip pure comment blocks
            if (preg_match('/^--/', $statement) || preg_match('/^\/\*/', $statement)) {
                $skipped++;
                continue;
            }
            
            // Get statement type for logging
            $statementType = 'UNKNOWN';
            if (preg_match('/^(CREATE|ALTER|INSERT|UPDATE|DELETE|SET)\s+/i', $statement, $matches)) {
                $statementType = strtoupper($matches[1]);
            }
            
            // Extract table name if it's a CREATE TABLE statement
            $tableName = '';
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
            }
            
            try {
                $pdo->exec($statement);
                $executed++;
                
                if ($tableName) {
                    $log("Executed {$statementType} TABLE: {$tableName}");
                } else {
                    $log("Executed statement #{$index}: {$statementType}");
                }
            } catch (PDOException $e) {
                // Log error but continue for non-critical errors
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    $log("Table already exists, skipping: {$tableName}", 'WARNING');
                    $skipped++;
                } else {
                    $log("Error executing statement: " . $e->getMessage(), 'ERROR');
                    $errors[] = $e->getMessage();
                }
            }
        }
        
        $log("Migration completed: {$executed} statements executed, {$skipped} skipped");
        
        if (!empty($errors)) {
            $log("Errors encountered during migration:", 'WARNING');
            foreach ($errors as $error) {
                $log("  - {$error}", 'WARNING');
            }
        }
        
        // Verify tables were created
        $log("Verifying table creation...");
        $verifyErrors = [];
        
        foreach ($tablesToCreate as $table) {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table); // Sanitize table name
            $checkSql = "SHOW TABLES LIKE '{$safeTable}'";
            $stmt = $pdo->query($checkSql);
            
            if (!$stmt->fetch()) {
                $verifyErrors[] = $table;
            } else {
                // Count columns
                $colSql = "SHOW COLUMNS FROM `{$table}`";
                $colStmt = $pdo->query($colSql);
                $colCount = $colStmt->rowCount();
                $log("Verified table '{$table}' with {$colCount} columns", 'SUCCESS');
            }
        }
        
        if (!empty($verifyErrors)) {
            throw new RuntimeException("Failed to create tables: " . implode(', ', $verifyErrors));
        }
        
        // Log table structures for verification
        $log("=== Table Structures ===");
        foreach ($tablesToCreate as $table) {
            $structSql = "DESCRIBE `{$table}`";
            $stmt = $pdo->query($structSql);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $log("Table: {$table}");
            foreach ($columns as $col) {
                $log("  - {$col['Field']}: {$col['Type']}" . 
                     ($col['Null'] === 'NO' ? ' NOT NULL' : '') .
                     ($col['Key'] === 'PRI' ? ' PRIMARY KEY' : '') .
                     ($col['Key'] === 'UNI' ? ' UNIQUE' : '') .
                     ($col['Key'] === 'MUL' ? ' INDEX' : ''));
            }
        }
        
        $log('=== Video Meetings Migration Completed Successfully ===', 'SUCCESS');
        
    } catch (PDOException $e) {
        $log("Database error: " . $e->getMessage(), 'ERROR');
        $log("SQL State: " . $e->getCode(), 'ERROR');
        exit(1);
    } catch (RuntimeException $e) {
        $log("Runtime error: " . $e->getMessage(), 'ERROR');
        exit(1);
    } catch (Exception $e) {
        $log("Unexpected error: " . $e->getMessage(), 'ERROR');
        exit(1);
    }
}

// Check if running from CLI
if (php_sapi_name() === 'cli') {
    // Parse command line arguments
    $options = getopt('h', ['help', 'dry-run']);
    
    if (isset($options['h']) || isset($options['help'])) {
        echo <<<HELP
Video Meetings Migration Runner
================================
Usage: php database/run_video_meetings_migration.php [options]

Options:
  -h, --help     Show this help message
  --dry-run      Show what would be executed without making changes

This script creates the following tables:
  - video_meetings          Core meeting records
  - meeting_participants    Participant tracking  
  - meeting_chat_messages   In-meeting chat history
  - video_meeting_logs      Audit logging

HELP;
        exit(0);
    }
    
    if (isset($options['dry-run'])) {
        echo "Dry run mode - showing migration SQL:\n";
        echo "=====================================\n\n";
        $migrationFile = BASE_PATH . '/database/migrations/video_meetings_schema.sql';
        if (file_exists($migrationFile)) {
            echo file_get_contents($migrationFile);
        } else {
            echo "Migration file not found: {$migrationFile}\n";
        }
        exit(0);
    }
    
    // Run the migration
    runVideoMeetingsMigration();
} else {
    // Running from web - check for admin authentication
    echo "This script should be run from the command line.\n";
    echo "Usage: php database/run_video_meetings_migration.php\n";
    exit(1);
}
