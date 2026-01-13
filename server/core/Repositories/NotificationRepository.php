<?php
/**
 * Notification Repository
 * 
 * Handles all notification-related database operations
 * User notification data access layer
 */

namespace Core\Repositories;

use PDO;
use Exception;

class NotificationRepository
{
    private PDO $pdo;
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Create notifications table if not exists
     */
    public function createTableIfNotExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS user_notification (
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
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Get notifications for user
     */
    public function getForUser(string $userId, bool $unreadOnly = false, int $limit = 20, int $offset = 0): array
    {
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
        
        if ($unreadOnly) {
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
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get notification counts
     */
    public function getCounts(string $userId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread
                  FROM user_notification
                  WHERE user_id = :user_id
                  AND (expires_at IS NULL OR expires_at > NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'unread' => 0];
    }
    
    /**
     * Mark notifications as read
     */
    public function markAsRead(string $userId, array $notificationIds): int
    {
        if (empty($notificationIds)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $sql = "UPDATE user_notification 
                SET is_read = TRUE, read_at = NOW() 
                WHERE user_id = ? 
                AND notification_id IN ($placeholders)
                AND is_read = FALSE";
        
        $params = array_merge([$userId], $notificationIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Create notification
     */
    public function create(array $notification): string
    {
        $notificationId = $this->generateUuid();
        
        $sql = "INSERT INTO user_notification (
                    notification_id, user_id, type, priority, title, 
                    message, data, expires_at
                ) VALUES (
                    :notification_id, :user_id, :type, :priority, :title,
                    :message, :data, :expires_at
                )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'notification_id' => $notificationId,
            'user_id' => $notification['user_id'],
            'type' => $notification['type'],
            'priority' => $notification['priority'] ?? 'normal',
            'title' => $notification['title'],
            'message' => $notification['message'] ?? null,
            'data' => isset($notification['data']) ? json_encode($notification['data']) : null,
            'expires_at' => $notification['expires_at'] ?? null
        ]);
        
        return $notificationId;
    }
    
    /**
     * Create multiple notifications
     */
    public function createBatch(array $notifications): array
    {
        $createdIds = [];
        
        foreach ($notifications as $notification) {
            $createdIds[] = $this->create($notification);
        }
        
        return $createdIds;
    }
    
    /**
     * Delete expired notifications
     */
    public function deleteExpired(): int
    {
        $sql = "DELETE FROM user_notification 
                WHERE expires_at IS NOT NULL 
                AND expires_at < NOW()";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
    
    /**
     * Delete notification
     */
    public function delete(string $notificationId, string $userId): bool
    {
        $sql = "DELETE FROM user_notification 
                WHERE notification_id = :notification_id 
                AND user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'notification_id' => $notificationId,
            'user_id' => $userId
        ]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get notifications by type
     */
    public function getByType(string $userId, string $type, int $limit = 10): array
    {
        $sql = "SELECT * FROM user_notification
                WHERE user_id = :user_id
                AND type = :type
                AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY created_at DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create system notification for all users with role
     */
    public function createForRole(string $role, array $notification): int
    {
        // Get all users with the specified role
        $sql = "SELECT u.user_id 
                FROM user u
                INNER JOIN user_role ur ON u.user_id = ur.user_id
                INNER JOIN role r ON ur.role_id = r.role_id
                WHERE r.role_name = :role
                AND u.is_active = 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['role' => $role]);
        
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $count = 0;
        foreach ($userIds as $userId) {
            $notification['user_id'] = $userId;
            $this->create($notification);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Check if user has any notifications
     */
    public function hasNotifications(string $userId): bool
    {
        $sql = "SELECT 1 FROM user_notification 
                WHERE user_id = :user_id 
                AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Mark all as read for user
     */
    public function markAllAsRead(string $userId): int
    {
        $sql = "UPDATE user_notification 
                SET is_read = TRUE, read_at = NOW() 
                WHERE user_id = :user_id 
                AND is_read = FALSE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}