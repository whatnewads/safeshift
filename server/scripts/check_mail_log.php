<?php
// Quick check of mail_log table

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

$pdo = new PDO(
    "mysql:host=" . (getenv('DB_HOST') ?: '127.0.0.1') . 
    ";dbname=" . (getenv('DB_NAME') ?: 'safeshift_ehr_001_0'),
    getenv('DB_USER') ?: 'safeshift_admin',
    getenv('DB_PASS') ?: ''
);

echo "=== Mail Log (Last 10) ===\n";
$rows = $pdo->query('SELECT * FROM mail_log ORDER BY created_at DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo sprintf(
        "ID: %d | Type: %s | Status: %s | Email: %s | Time: %s\n",
        $row['id'],
        $row['email_type'],
        $row['status'],
        $row['recipient_email'],
        $row['created_at']
    );
}

echo "\n=== Two Factor Codes (Last 5) ===\n";
$rows = $pdo->query('SELECT id, user_id, purpose, expires_at, attempts, consumed_at FROM two_factor_codes ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo sprintf(
        "ID: %d | Purpose: %s | Expires: %s | Attempts: %d | Consumed: %s\n",
        $row['id'],
        $row['purpose'],
        $row['expires_at'],
        $row['attempts'],
        $row['consumed_at'] ?: 'No'
    );
}

echo "\n=== User Last Reminder Timestamps ===\n";
$rows = $pdo->query('SELECT user_id, username, last_reminder_sent_at FROM user WHERE last_reminder_sent_at IS NOT NULL ORDER BY last_reminder_sent_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo sprintf(
        "User: %s | Last Reminder: %s\n",
        $row['username'],
        $row['last_reminder_sent_at']
    );
}
