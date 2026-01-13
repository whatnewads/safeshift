<?php
/**
 * Test Reminder Cron Job
 * 
 * Tests the inactivity reminder functionality
 */

echo "=== Reminder Cron Test ===\n\n";

// Load env
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . "=" . trim($value, " \t\n\r\0\x0B\"'"));
        }
    }
}

// Connect to database
try {
    $db = new PDO(
        "mysql:host=" . (getenv('DB_HOST') ?: '127.0.0.1') . 
        ";port=" . (getenv('DB_PORT') ?: '3306') . 
        ";dbname=" . (getenv('DB_NAME') ?: 'safeshift_ehr_001_0') .
        ";charset=utf8mb4",
        getenv('DB_USER') ?: 'safeshift_admin',
        getenv('DB_PASS') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Database connected\n\n";
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}

// Check users_needing_reminder view
echo "1. Checking users_needing_reminder view...\n";

try {
    $stmt = $db->query("SELECT * FROM users_needing_reminder LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Found " . count($users) . " users needing reminders:\n";
    foreach ($users as $user) {
        echo "   - {$user['username']} ({$user['email']}): {$user['unread_notifications']} unread, last login: {$user['last_login']}\n";
    }
} catch (PDOException $e) {
    echo "   Error: " . $e->getMessage() . "\n";
    
    // View might not exist, check manually
    echo "\n   Trying manual query...\n";
}

// Manual check for users with old logins
echo "\n2. Checking for users with old/no logins...\n";

$stmt = $db->query("
    SELECT user_id, username, email, last_login, last_reminder_sent_at, email_opt_in_reminders
    FROM user 
    WHERE is_active = 1 
    ORDER BY last_login ASC 
    LIMIT 10
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    echo "   - {$user['username']}: last_login={$user['last_login']}, opt_in={$user['email_opt_in_reminders']}\n";
}

// Check for unread notifications
echo "\n3. Checking for unread notifications...\n";

$stmt = $db->query("
    SELECT user_id, COUNT(*) as unread_count 
    FROM user_notification 
    WHERE is_read = 0 
    GROUP BY user_id
");
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($notifications) > 0) {
    echo "   Found " . count($notifications) . " users with unread notifications:\n";
    foreach ($notifications as $n) {
        echo "   - User {$n['user_id']}: {$n['unread_count']} unread\n";
    }
} else {
    echo "   No unread notifications found. Creating test notification...\n";
    
    // Get first user
    $stmt = $db->query("SELECT user_id FROM user WHERE is_active = 1 LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $stmt = $db->prepare("
            INSERT INTO user_notification (notification_id, user_id, type, priority, title, message, is_read, created_at)
            VALUES (UUID(), :user_id, 'system', 'normal', 'Test Notification', 'This is a test notification for reminder testing', 0, NOW())
        ");
        $stmt->execute(['user_id' => $user['user_id']]);
        echo "   ✓ Created test notification for user {$user['user_id']}\n";
        
        // Set user's last_login to 4 days ago to trigger reminder
        $stmt = $db->prepare("
            UPDATE user 
            SET last_login = DATE_SUB(NOW(), INTERVAL 4 DAY),
                last_reminder_sent_at = NULL,
                email_opt_in_reminders = 1
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $user['user_id']]);
        echo "   ✓ Set user's last_login to 4 days ago\n";
    }
}

// Check the view again
echo "\n4. Re-checking users_needing_reminder view...\n";

try {
    $stmt = $db->query("SELECT * FROM users_needing_reminder LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Found " . count($users) . " users needing reminders:\n";
    foreach ($users as $user) {
        echo "   - {$user['username']} ({$user['email']}): {$user['unread_notifications']} unread\n";
    }
    
    if (count($users) > 0) {
        echo "\n5. Running reminder cron (dry run - emails will be sent!)...\n";
        echo "   Press Ctrl+C within 5 seconds to cancel...\n";
        sleep(5);
        
        // Actually run the cron
        echo "\n   Running cron/send_reminders.php...\n";
        echo "   ----------------------------------------\n";
        
        include dirname(__DIR__) . '/cron/send_reminders.php';
        
        echo "   ----------------------------------------\n";
    }
} catch (PDOException $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
