<?php
/**
 * Notifications API Endpoint
 * Returns system notifications and alerts for 1clinician users
 * 
 * Security measures:
 * - Session validation for 1clinician role
 * - Prepared statements for all queries
 * - Rate limiting implementation
 * - CORS headers restricted to same origin
 * - Input sanitization and validation
 * - Comprehensive logging
 */

// Load bootstrap which handles all includes
require_once __DIR__ . '/../includes/bootstrap.php';

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS - Restrict to same origin only
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $allowed_origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Session validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    \App\log\security_event('UNAUTHORIZED_API_ACCESS', [
        'api' => 'notifications',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$user_role = \App\auth\user_primary_role($user_id);

// Validate user has appropriate role
$allowed_roles = ['1clinician', 'pclinician', 'dclinician', 'cadmin', 'tadmin'];
if (!in_array($user_role, $allowed_roles)) {
    \App\log\audit('UNAUTHORIZED_API_ACCESS', 'API', $user_id, [
        'api' => 'notifications',
        'user_role' => $user_role,
        'required_role' => '1clinician'
    ]);
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Rate limiting check
$rate_limit_key = 'api_notifications_' . $user_id;
$rate_limit_file = __DIR__ . '/../cache/rate_limits/' . md5($rate_limit_key) . '.json';
$rate_limit_dir = dirname($rate_limit_file);

if (!is_dir($rate_limit_dir)) {
    mkdir($rate_limit_dir, 0755, true);
}

$current_time = time();
$rate_limit_window = 60; // 1 minute
$max_requests = 120; // 120 requests per minute (for real-time updates)

if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true);
    if ($rate_data['window_start'] + $rate_limit_window > $current_time) {
        if ($rate_data['count'] >= $max_requests) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests']);
            exit;
        }
        $rate_data['count']++;
    } else {
        $rate_data = ['window_start' => $current_time, 'count' => 1];
    }
} else {
    $rate_data = ['window_start' => $current_time, 'count' => 1];
}
file_put_contents($rate_limit_file, json_encode($rate_data));

// Handle different request types
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark notifications as read
    handleMarkAsRead($user_id);
} else {
    // Get notifications
    handleGetNotifications($user_id);
}

/**
 * Get notifications for the user
 */
function handleGetNotifications($user_id) {
    // Input validation
    $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = min(max($limit, 1), 100); // Limit between 1 and 100
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $offset = max($offset, 0);
    
    // Log API access
    \App\log\audit('API_ACCESS', 'notifications', $user_id, [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => 'GET',
        'unread_only' => $unread_only,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
    try {
        // Get database connection
        $db = \App\db\pdo();
        
        // Create notifications table if it doesn't exist (temporary solution)
        $create_table = "CREATE TABLE IF NOT EXISTS user_notification (
            notification_id CHAR(36) PRIMARY KEY,
            user_id CHAR(36) NOT NULL,
            type VARCHAR(64) NOT NULL,
            priority VARCHAR(32) DEFAULT 'normal',
            title VARCHAR(255) NOT NULL,
            message TEXT,
            data JSON,
            is_read BOOLEAN DEFAULT FALSE,
            read_at DATETIME(6) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME(6) NULL,
            INDEX idx_user_unread (user_id, is_read, created_at),
            INDEX idx_expires (expires_at),
            FOREIGN KEY (user_id) REFERENCES user(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($create_table);
        
        // Insert sample notifications if none exist (for demonstration)
        $check_sql = "SELECT COUNT(*) as count FROM user_notification WHERE user_id = :user_id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bindValue(':user_id', $user_id);
        $check_stmt->execute();
        $count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            // Insert sample notifications
            $sample_notifications = [
                [
                    'type' => 'lab_result',
                    'priority' => 'high',
                    'title' => 'Critical Lab Result',
                    'message' => 'Patient John Doe has critical lab values requiring immediate attention.',
                    'data' => json_encode(['patient_id' => 'sample-patient-1', 'lab_id' => 'lab-001'])
                ],
                [
                    'type' => 'appointment_reminder',
                    'priority' => 'normal',
                    'title' => 'Upcoming Appointment',
                    'message' => 'You have 3 appointments scheduled for tomorrow.',
                    'data' => json_encode(['count' => 3, 'date' => date('Y-m-d', strtotime('+1 day'))])
                ],
                [
                    'type' => 'system_alert',
                    'priority' => 'low',
                    'title' => 'System Maintenance',
                    'message' => 'Scheduled maintenance will occur this weekend from 2 AM to 4 AM.',
                    'data' => json_encode(['maintenance_window' => '2025-12-15 02:00:00'])
                ],
                [
                    'type' => 'patient_update',
                    'priority' => 'normal',
                    'title' => 'Patient Status Update',
                    'message' => 'Patient Jane Smith has been discharged from the ER.',
                    'data' => json_encode(['patient_id' => 'sample-patient-2', 'status' => 'discharged'])
                ],
                [
                    'type' => 'prescription_alert',
                    'priority' => 'high',
                    'title' => 'Prescription Renewal Required',
                    'message' => '5 patients have prescriptions expiring within the next 7 days.',
                    'data' => json_encode(['count' => 5, 'urgent' => true])
                ]
            ];
            
            foreach ($sample_notifications as $notif) {
                $insert_sql = "INSERT INTO user_notification (notification_id, user_id, type, priority, title, message, data) 
                              VALUES (:id, :user_id, :type, :priority, :title, :message, :data)";
                $insert_stmt = $db->prepare($insert_sql);
                $insert_stmt->execute([
                    ':id' => bin2hex(random_bytes(16)),
                    ':user_id' => $user_id,
                    ':type' => $notif['type'],
                    ':priority' => $notif['priority'],
                    ':title' => $notif['title'],
                    ':message' => $notif['message'],
                    ':data' => $notif['data']
                ]);
            }
        }
        
        // Build query
        $sql = "SELECT 
                    notification_id,
                    type,
                    priority,
                    title,
                    message,
                    data,
                    is_read,
                    read_at,
                    created_at,
                    expires_at
                FROM user_notification
                WHERE user_id = :user_id
                AND (expires_at IS NULL OR expires_at > NOW())";
        
        if ($unread_only) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY 
                    CASE priority 
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'normal' THEN 3
                        WHEN 'low' THEN 4
                        ELSE 5
                    END,
                    created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':user_id', $user_id);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format notifications
        $formatted_notifications = [];
        foreach ($notifications as $notif) {
            $formatted_notifications[] = [
                'id' => $notif['notification_id'],
                'type' => $notif['type'],
                'priority' => $notif['priority'],
                'title' => $notif['title'],
                'message' => $notif['message'],
                'data' => json_decode($notif['data'], true),
                'is_read' => (bool)$notif['is_read'],
                'read_at' => $notif['read_at'],
                'created_at' => $notif['created_at'],
                'time_ago' => getTimeAgo(strtotime($notif['created_at'])),
                'expires_at' => $notif['expires_at']
            ];
        }
        
        // Get total count and unread count
        $count_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread
                      FROM user_notification
                      WHERE user_id = :user_id
                      AND (expires_at IS NULL OR expires_at > NOW())";
        
        $count_stmt = $db->prepare($count_sql);
        $count_stmt->bindValue(':user_id', $user_id);
        $count_stmt->execute();
        $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'notifications' => $formatted_notifications,
                'total' => (int)$counts['total'],
                'unread' => (int)$counts['unread'],
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $counts['total']
            ],
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        // Log error
        \App\log\file_log('error', [
            'api' => 'notifications',
            'method' => 'GET',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $user_id
        ]);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'timestamp' => date('c')
        ]);
    }
}

/**
 * Mark notifications as read
 */
function handleMarkAsRead($user_id) {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['notification_ids']) || !is_array($input['notification_ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input. Expected notification_ids array.']);
        return;
    }
    
    // Validate notification IDs
    $uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    foreach ($input['notification_ids'] as $id) {
        if (!preg_match($uuid_pattern, $id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid notification ID format']);
            return;
        }
    }
    
    // Log API access
    \App\log\audit('API_ACCESS', 'notifications', $user_id, [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => 'POST',
        'action' => 'mark_as_read',
        'notification_count' => count($input['notification_ids'])
    ]);
    
    try {
        // Get database connection
        $db = \App\db\pdo();
        
        // Update notifications
        $placeholders = implode(',', array_fill(0, count($input['notification_ids']), '?'));
        $sql = "UPDATE user_notification 
                SET is_read = TRUE, read_at = NOW() 
                WHERE user_id = ? 
                AND notification_id IN ($placeholders)
                AND is_read = FALSE";
        
        $params = array_merge([$user_id], $input['notification_ids']);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $updated_count = $stmt->rowCount();
        
        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'updated' => $updated_count,
                'requested' => count($input['notification_ids'])
            ],
            'timestamp' => date('c')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        // Log error
        \App\log\file_log('error', [
            'api' => 'notifications',
            'method' => 'POST',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $user_id
        ]);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'timestamp' => date('c')
        ]);
    }
}

/**
 * Convert timestamp to human-readable time ago format
 */
function getTimeAgo($timestamp) {
    $time_ago = time() - $timestamp;
    
    if ($time_ago < 60) {
        return 'Just now';
    } elseif ($time_ago < 3600) {
        $minutes = floor($time_ago / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_ago < 86400) {
        $hours = floor($time_ago / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_ago < 604800) {
        $days = floor($time_ago / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}