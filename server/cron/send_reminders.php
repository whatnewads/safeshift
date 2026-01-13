<?php
/**
 * Inactivity Reminder Cron Job
 *
 * Sends reminder emails to users who:
 * - Haven't logged in for 3+ days
 * - Have unread notifications
 * - Haven't received a reminder in the last 3 days
 * - Have opted into reminder emails
 *
 * Schedule: Run every 6 hours via crontab
 * Example crontab entry (runs at 0:00, 6:00, 12:00, 18:00):
 *   0 0,6,12,18 * * * php /path/to/cron/send_reminders.php
 *
 * Amazon SES is used for email delivery via EmailService
 */

declare(strict_types=1);

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// Set error reporting for cron context
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

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
            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }
}

// Bootstrap the application
require_once dirname(__DIR__) . '/includes/bootstrap.php';

use Core\Services\EmailService;

/**
 * Main cron job class
 */
class ReminderCronJob
{
    private \PDO $db;
    private EmailService $emailService;
    private string $logFile;
    
    // Configuration
    private const INACTIVITY_DAYS = 3;
    private const REMINDER_COOLDOWN_DAYS = 3;
    private const MAX_EMAILS_PER_RUN = 100; // Prevent SES rate limiting
    private const BATCH_SIZE = 10;
    
    public function __construct()
    {
        // Create direct PDO connection for cron context
        $this->db = new \PDO(
            sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                getenv('DB_HOST') ?: '127.0.0.1',
                getenv('DB_PORT') ?: '3306',
                getenv('DB_NAME') ?: 'safeshift_ehr_001_0'
            ),
            getenv('DB_USER') ?: 'safeshift_admin',
            getenv('DB_PASS') ?: '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
        $this->emailService = new EmailService();
        $this->logFile = dirname(__DIR__) . '/logs/cron_reminders.log';
    }
    
    /**
     * Run the reminder job
     */
    public function run(): void
    {
        $startTime = microtime(true);
        $this->log('=== Starting Reminder Cron Job ===');
        
        try {
            // Get users needing reminders
            $users = $this->getUsersNeedingReminders();
            
            $this->log(sprintf('Found %d users needing reminders', count($users)));
            
            if (empty($users)) {
                $this->log('No reminders to send. Exiting.');
                return;
            }
            
            // Process in batches
            $sent = 0;
            $failed = 0;
            
            foreach (array_chunk($users, self::BATCH_SIZE) as $batch) {
                foreach ($batch as $user) {
                    if ($sent >= self::MAX_EMAILS_PER_RUN) {
                        $this->log('Reached max emails per run limit. Stopping.');
                        break 2;
                    }
                    
                    $result = $this->sendReminderEmail($user);
                    
                    if ($result) {
                        $sent++;
                        $this->updateLastReminderSent($user['user_id']);
                    } else {
                        $failed++;
                    }
                    
                    // Small delay to respect SES rate limits
                    usleep(100000); // 100ms
                }
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            $this->log(sprintf(
                'Completed: %d sent, %d failed, %.2fs duration',
                $sent,
                $failed,
                $duration
            ));
            
        } catch (\Exception $e) {
            $this->logError('Cron job failed: ' . $e->getMessage());
        }
        
        $this->log('=== Reminder Cron Job Finished ===');
    }
    
    /**
     * Get users who need reminder emails
     */
    private function getUsersNeedingReminders(): array
    {
        $sql = "
            SELECT 
                u.user_id,
                u.username,
                u.email,
                u.last_login,
                u.last_reminder_sent_at,
                COUNT(CASE WHEN n.is_read = 0 THEN 1 END) as unread_count
            FROM user u
            LEFT JOIN user_notification n ON u.user_id = n.user_id
            WHERE 
                u.is_active = 1
                AND u.status = 'active'
                AND u.email IS NOT NULL
                AND u.email != ''
                AND u.email_opt_in_reminders = 1
                AND (
                    u.last_login IS NULL 
                    OR u.last_login <= DATE_SUB(NOW(), INTERVAL :inactivity_days DAY)
                )
                AND (
                    u.last_reminder_sent_at IS NULL 
                    OR u.last_reminder_sent_at <= DATE_SUB(NOW(), INTERVAL :cooldown_days DAY)
                )
            GROUP BY u.user_id
            HAVING unread_count > 0
            ORDER BY u.last_login ASC
            LIMIT :max_limit
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inactivity_days', self::INACTIVITY_DAYS, \PDO::PARAM_INT);
        $stmt->bindValue(':cooldown_days', self::REMINDER_COOLDOWN_DAYS, \PDO::PARAM_INT);
        $stmt->bindValue(':max_limit', self::MAX_EMAILS_PER_RUN, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Send reminder email to a user
     */
    private function sendReminderEmail(array $user): bool
    {
        try {
            $subject = 'SafeShift EHR - You have unread notifications';
            
            $htmlBody = $this->getReminderEmailHtml($user);
            $plainBody = $this->getReminderEmailPlain($user);
            
            $result = $this->emailService->sendNotification(
                $user['email'],
                $subject,
                $htmlBody,
                $plainBody,
                $user['username']
            );
            
            // Log to mail_log
            $this->logMailSend(
                $user['user_id'],
                $user['email'],
                'reminder',
                $result['success'],
                $result['errors'] ?? null
            );
            
            if ($result['success']) {
                $this->log(sprintf('Sent reminder to user %s', $user['user_id']));
                return true;
            } else {
                $this->logError(sprintf(
                    'Failed to send reminder to user %s: %s',
                    $user['user_id'],
                    $result['message'] ?? 'Unknown error'
                ));
                return false;
            }
            
        } catch (\Exception $e) {
            $this->logError(sprintf(
                'Exception sending reminder to user %s: %s',
                $user['user_id'],
                $e->getMessage()
            ));
            return false;
        }
    }
    
    /**
     * Get HTML email body for reminder
     */
    private function getReminderEmailHtml(array $user): string
    {
        $unreadCount = (int) $user['unread_count'];
        $loginUrl = getenv('APP_BASE_URL') ?: 'https://1stresponse.safeshift.ai';
        $lastLogin = $user['last_login'] 
            ? date('F j, Y', strtotime($user['last_login']))
            : 'Never';
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: #003366; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; background: #ffffff; }
        .notification-count { 
            background: #dc3545; 
            color: white; 
            padding: 15px 25px; 
            border-radius: 8px; 
            font-size: 18px;
            text-align: center;
            margin: 20px 0;
        }
        .button { 
            display: inline-block; 
            padding: 14px 30px; 
            background: #003366; 
            color: white !important; 
            text-decoration: none; 
            border-radius: 5px; 
            margin: 20px 0;
        }
        .footer { background: #f5f5f5; padding: 15px; font-size: 12px; color: #666; text-align: center; }
        .unsubscribe { color: #666; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>SafeShift EHR</h2>
        </div>
        <div class="content">
            <p>Hello ' . htmlspecialchars($user['username'] ?: 'there') . ',</p>
            
            <div class="notification-count">
                You have <strong>' . $unreadCount . ' unread notification' . ($unreadCount > 1 ? 's' : '') . '</strong>
            </div>
            
            <p>We noticed you haven\'t logged into SafeShift EHR recently.</p>
            <p><strong>Last login:</strong> ' . $lastLogin . '</p>
            
            <p>Please log in to review your notifications and stay up to date with important information.</p>
            
            <p style="text-align: center;">
                <a href="' . htmlspecialchars($loginUrl) . '" class="button">Log In to SafeShift</a>
            </p>
        </div>
        <div class="footer">
            <p>This is an automated reminder from SafeShift EHR.</p>
            <p>
                <a href="' . htmlspecialchars($loginUrl) . '/settings/notifications" class="unsubscribe">
                    Manage email preferences
                </a>
            </p>
            <p>&copy; ' . date('Y') . ' SafeShift, LLC. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get plain text email body for reminder
     */
    private function getReminderEmailPlain(array $user): string
    {
        $unreadCount = (int) $user['unread_count'];
        $loginUrl = getenv('APP_BASE_URL') ?: 'https://1stresponse.safeshift.ai';
        $lastLogin = $user['last_login'] 
            ? date('F j, Y', strtotime($user['last_login']))
            : 'Never';
        
        return "SafeShift EHR - You have unread notifications\n\n" .
               "Hello " . ($user['username'] ?: 'there') . ",\n\n" .
               "You have {$unreadCount} unread notification" . ($unreadCount > 1 ? 's' : '') . ".\n\n" .
               "Last login: {$lastLogin}\n\n" .
               "Please log in to review your notifications:\n" .
               "{$loginUrl}\n\n" .
               "---\n" .
               "This is an automated reminder from SafeShift EHR.\n" .
               "To manage email preferences, visit: {$loginUrl}/settings/notifications\n\n" .
               "(c) " . date('Y') . " SafeShift, LLC. All rights reserved.";
    }
    
    /**
     * Update the last_reminder_sent_at timestamp
     */
    private function updateLastReminderSent(string $userId): void
    {
        try {
            $sql = "UPDATE user SET last_reminder_sent_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
        } catch (\Exception $e) {
            $this->logError('Failed to update last_reminder_sent_at: ' . $e->getMessage());
        }
    }
    
    /**
     * Log mail send to database
     */
    private function logMailSend(string $userId, string $email, string $type, bool $success, $error = null): void
    {
        try {
            $sql = "INSERT INTO mail_log 
                    (user_id, recipient_email, email_type, status, error_message, sent_at, created_at)
                    VALUES 
                    (:user_id, :recipient_email, :email_type, :status, :error_message, :sent_at, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'recipient_email' => $this->maskEmail($email),
                'email_type' => $type,
                'status' => $success ? 'sent' : 'failed',
                'error_message' => is_array($error) ? json_encode($error) : $error,
                'sent_at' => $success ? date('Y-m-d H:i:s') : null
            ]);
        } catch (\Exception $e) {
            $this->logError('Failed to log mail send: ' . $e->getMessage());
        }
    }
    
    /**
     * Mask email for logging
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***@***.***';
        
        $name = $parts[0];
        $maskedName = strlen($name) > 3 ? substr($name, 0, 3) . '***' : $name[0] . '***';
        
        return $maskedName . '@' . $parts[1];
    }
    
    /**
     * Log message
     */
    private function log(string $message): void
    {
        $formatted = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        echo $formatted;
        file_put_contents($this->logFile, $formatted, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log error message
     */
    private function logError(string $message): void
    {
        $this->log('[ERROR] ' . $message);
        error_log('[ReminderCron] ' . $message);
    }
}

// Run the cron job
$job = new ReminderCronJob();
$job->run();
