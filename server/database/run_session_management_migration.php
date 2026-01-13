<?php
/**
 * Session Management Migration Runner
 * 
 * Executes the session_management.sql migration script.
 * 
 * Usage:
 * - CLI: php database/run_session_management_migration.php
 * - Browser: Navigate to /database/run_session_management_migration.php (requires admin auth)
 * 
 * @package SafeShift\Database\Migrations
 */

// Load bootstrap
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Check if running from CLI
$isCli = php_sapi_name() === 'cli';

// If web request, check authentication
if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is admin
    $userRole = $_SESSION['user']['role'] ?? '';
    $allowedRoles = ['admin', 'super_admin', 'superadmin', 'system_admin'];
    
    if (!isset($_SESSION['user']) || !in_array(strtolower($userRole), $allowedRoles)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Admin authentication required']);
        exit;
    }
    
    header('Content-Type: text/html; charset=utf-8');
}

/**
 * Output helper
 */
function output(string $message, bool $isError = false): void
{
    global $isCli;
    
    if ($isCli) {
        echo ($isError ? "[ERROR] " : "[INFO] ") . $message . PHP_EOL;
    } else {
        $color = $isError ? 'red' : 'green';
        echo "<p style='color: {$color}; font-family: monospace;'>" . htmlspecialchars($message) . "</p>";
    }
}

/**
 * Run a single SQL statement
 */
function runStatement(PDO $pdo, string $sql, string $description): bool
{
    try {
        $pdo->exec($sql);
        output("✓ {$description}");
        return true;
    } catch (PDOException $e) {
        // Check if error is just "already exists"
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if (strpos($errorMessage, 'already exists') !== false || 
            strpos($errorMessage, 'Duplicate') !== false) {
            output("⚠ {$description} - Already exists (skipped)");
            return true;
        }
        
        output("✗ {$description} - Error: " . $errorMessage, true);
        return false;
    }
}

// ============================================================================
// MIGRATION EXECUTION
// ============================================================================

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Session Management Migration</title></head><body>";
    echo "<h1>Session Management Migration</h1>";
    echo "<pre>";
}

output("Starting Session Management Migration...");
output("-------------------------------------------");

try {
    // Get database connection
    $pdo = \App\db\pdo();
    
    if (!$pdo) {
        throw new Exception("Failed to get database connection");
    }
    
    output("Database connection established");
    
    // Create user_sessions table
    $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(255) NOT NULL UNIQUE,
        device_info VARCHAR(255) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_sessions_user_id (user_id),
        INDEX idx_user_sessions_token (session_token),
        INDEX idx_user_sessions_active (is_active, expires_at),
        INDEX idx_user_sessions_last_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    runStatement($pdo, $sql, "Creating user_sessions table");
    
    // Create user_preferences table
    $sql = "CREATE TABLE IF NOT EXISTS user_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        idle_timeout INT DEFAULT 1800,
        theme VARCHAR(20) DEFAULT 'system',
        notifications_enabled BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_preferences_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    runStatement($pdo, $sql, "Creating user_preferences table");
    
    // Create session_activity_log table
    $sql = "CREATE TABLE IF NOT EXISTS session_activity_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        session_id INT DEFAULT NULL,
        user_id INT NOT NULL,
        event_type ENUM('login', 'logout', 'timeout', 'activity_ping', 'session_extend', 'forced_logout') NOT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        event_data JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_activity_user_id (user_id),
        INDEX idx_session_activity_created (created_at),
        INDEX idx_session_activity_type (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    runStatement($pdo, $sql, "Creating session_activity_log table");
    
    // Try to create the event (may fail if no EVENT privilege)
    try {
        // First check if event scheduler is enabled
        $result = $pdo->query("SHOW VARIABLES LIKE 'event_scheduler'")->fetch();
        $eventSchedulerStatus = $result['Value'] ?? 'UNKNOWN';
        
        output("Event scheduler status: {$eventSchedulerStatus}");
        
        // Drop existing event if any
        $pdo->exec("DROP EVENT IF EXISTS cleanup_expired_sessions");
        
        // Create the cleanup event
        $sql = "CREATE EVENT IF NOT EXISTS cleanup_expired_sessions
            ON SCHEDULE EVERY 5 MINUTE
            STARTS CURRENT_TIMESTAMP
            ENABLE
            DO
            BEGIN
                UPDATE user_sessions 
                SET is_active = FALSE 
                WHERE expires_at < NOW() AND is_active = TRUE;
                
                DELETE FROM user_sessions 
                WHERE is_active = FALSE AND last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);
                
                DELETE FROM session_activity_log 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
            END";
        
        $pdo->exec($sql);
        output("✓ Created cleanup_expired_sessions event");
        
    } catch (PDOException $e) {
        output("⚠ Could not create event (EVENT privilege may be required): " . $e->getMessage());
        output("  Note: Sessions will still work, but automatic cleanup won't occur.");
        output("  You can manually run: UPDATE user_sessions SET is_active = FALSE WHERE expires_at < NOW()");
    }
    
    // Verify tables were created
    output("-------------------------------------------");
    output("Verifying migration...");
    
    $tables = ['user_sessions', 'user_preferences', 'session_activity_log'];
    $allSuccess = true;
    
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
        if ($result) {
            output("✓ Table '{$table}' exists");
        } else {
            output("✗ Table '{$table}' NOT FOUND", true);
            $allSuccess = false;
        }
    }
    
    output("-------------------------------------------");
    
    if ($allSuccess) {
        output("Migration completed successfully!");
        output("");
        output("Next steps:");
        output("1. Test session creation on login");
        output("2. Test activity ping endpoint: POST /api/auth/ping-activity");
        output("3. Test session list: GET /api/auth/active-sessions");
        output("4. Verify sessions persist longer than 5 minutes with activity");
    } else {
        output("Migration completed with errors. Please review the output above.", true);
    }
    
} catch (Exception $e) {
    output("Migration failed: " . $e->getMessage(), true);
    output("Stack trace: " . $e->getTraceAsString(), true);
}

if (!$isCli) {
    echo "</pre>";
    echo "<p><a href='/'>← Return to Home</a></p>";
    echo "</body></html>";
}
