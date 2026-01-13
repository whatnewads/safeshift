<?php

declare(strict_types=1);

namespace Model\Repositories;

use Model\Entities\Notification;
use Model\Interfaces\RepositoryInterface;
use PDO;

/**
 * Notification Repository
 *
 * Data access layer for user notifications.
 * Handles all database operations for the user_notification table.
 *
 * @package Model\Repositories
 */
class NotificationRepository implements RepositoryInterface
{
    private PDO $pdo;
    private string $table = 'user_notification';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find notification by ID
     */
    public function findById(string $id): ?Notification
    {
        $sql = "SELECT * FROM {$this->table} WHERE notification_id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return Notification::fromArray($row);
    }

    /**
     * Find all notifications matching criteria
     *
     * @param array<string, mixed> $criteria Search criteria (e.g., ['user_id' => '...', 'is_read' => false])
     * @param array<string, string> $orderBy Order by fields ['field' => 'ASC|DESC']
     * @param int|null $limit Maximum number of results (default: 100)
     * @param int|null $offset Number of results to skip (default: 0)
     * @return array<int, Notification> Array of matching notifications
     */
    public function findAll(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): array {
        // Build WHERE clause from criteria
        $conditions = [];
        $params = [];
        
        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $conditions[] = "{$field} IS NULL";
            } elseif (is_bool($value)) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value ? 1 : 0;
            } else {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        // Build ORDER BY clause
        $orderClauses = [];
        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderClauses[] = "{$field} {$direction}";
            }
        }
        // Default ordering if none specified
        if (empty($orderClauses)) {
            $orderClauses[] = 'created_at DESC';
        }
        $orderByClause = 'ORDER BY ' . implode(', ', $orderClauses);
        
        // Apply defaults for limit and offset
        $limitValue = $limit ?? 100;
        $offsetValue = $offset ?? 0;
        
        $sql = "SELECT * FROM {$this->table}
                {$whereClause}
                {$orderByClause}
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind criteria parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->bindValue(':limit', $limitValue, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offsetValue, PDO::PARAM_INT);
        $stmt->execute();

        $notifications = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = Notification::fromArray($row);
        }

        return $notifications;
    }

    /**
     * Find notifications for a specific user
     */
    public function findByUserId(
        string $userId,
        ?string $type = null,
        ?bool $isRead = null,
        ?string $priority = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        // Filter by type
        if ($type !== null && $type !== 'all') {
            $conditions[] = 'type = :type';
            $params['type'] = $type;
        }

        // Filter by read status
        if ($isRead !== null) {
            $conditions[] = 'is_read = :is_read';
            $params['is_read'] = $isRead ? 1 : 0;
        }

        // Filter by priority
        if ($priority !== null && $priority !== 'all') {
            $conditions[] = 'priority = :priority';
            $params['priority'] = $priority;
        }

        // Exclude expired notifications
        $conditions[] = '(expires_at IS NULL OR expires_at > NOW())';

        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE {$whereClause}
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $notifications = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $notifications[] = Notification::fromArray($row);
        }

        return $notifications;
    }

    /**
     * Count notifications for a user
     */
    public function countByUserId(
        string $userId,
        ?string $type = null,
        ?bool $isRead = null
    ): int {
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($type !== null && $type !== 'all') {
            $conditions[] = 'type = :type';
            $params['type'] = $type;
        }

        if ($isRead !== null) {
            $conditions[] = 'is_read = :is_read';
            $params['is_read'] = $isRead ? 1 : 0;
        }

        // Exclude expired
        $conditions[] = '(expires_at IS NULL OR expires_at > NOW())';

        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count unread notifications by type for a user
     */
    public function countUnreadByType(string $userId): array
    {
        $sql = "SELECT type, COUNT(*) as count 
                FROM {$this->table} 
                WHERE user_id = :user_id 
                  AND is_read = 0 
                  AND (expires_at IS NULL OR expires_at > NOW())
                GROUP BY type";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        $counts = [
            'all' => 0,
            'training' => 0,
            'licensure' => 0,
            'registrar' => 0,
            'case' => 0,
            'message' => 0,
            'system' => 0,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['type'];
            $count = (int) $row['count'];
            $counts[$type] = $count;
            $counts['all'] += $count;
        }

        return $counts;
    }

    /**
     * Check if user has unread notifications
     */
    public function hasUnread(string $userId): bool
    {
        $sql = "SELECT 1 FROM {$this->table} 
                WHERE user_id = :user_id 
                  AND is_read = 0 
                  AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Find single notification matching criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return Notification|null
     */
    public function findOneBy(array $criteria): ?Notification
    {
        $results = $this->findAll($criteria, [], 1, 0);
        return $results[0] ?? null;
    }

    /**
     * Create a new notification from array data
     *
     * @param array<string, mixed> $data Notification data
     * @return Notification Created notification
     * @throws \InvalidArgumentException If required data is missing
     */
    public function create(array $data): Notification
    {
        $requiredFields = ['user_id', 'type', 'title'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $notification = Notification::create(
            $data['user_id'],
            $data['type'],
            $data['title'],
            $data['message'] ?? null,
            $data['priority'] ?? 'normal',
            $data['data'] ?? null,
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null
        );

        if (!$this->insert($notification)) {
            throw new \RuntimeException('Failed to create notification');
        }

        return $notification;
    }

    /**
     * Update an existing notification
     *
     * @param string $id Notification ID to update
     * @param array<string, mixed> $data Updated notification data
     * @return Notification Updated notification
     * @throws \RuntimeException If notification not found
     */
    public function update(string $id, array $data): Notification
    {
        $notification = $this->findById($id);
        if (!$notification) {
            throw new \RuntimeException("Notification not found: {$id}");
        }

        // Build update SQL dynamically based on provided data
        $allowedFields = ['type', 'priority', 'title', 'message', 'data', 'is_read', 'read_at', 'expires_at'];
        $setClauses = [];
        $params = ['notification_id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'data' && is_array($data[$field])) {
                    $setClauses[] = "{$field} = :{$field}";
                    $params[$field] = json_encode($data[$field]);
                } elseif ($field === 'is_read') {
                    $setClauses[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field] ? 1 : 0;
                } else {
                    $setClauses[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                }
            }
        }

        if (empty($setClauses)) {
            return $notification; // Nothing to update
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE notification_id = :notification_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Return fresh entity
        return $this->findById($id);
    }

    /**
     * Check if notification exists
     *
     * @param string $id Notification ID
     * @return bool True if exists
     */
    public function exists(string $id): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE notification_id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Count notifications matching criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return int Number of matching notifications
     */
    public function count(array $criteria = []): int
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $conditions[] = "{$field} IS NULL";
            } elseif (is_bool($value)) {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value ? 1 : 0;
            } else {
                $conditions[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Save (insert or update) a notification
     *
     * @param \Model\Interfaces\EntityInterface $entity Notification entity to save
     * @return Notification Saved notification
     */
    public function save($entity): Notification
    {
        if (!$entity instanceof Notification) {
            throw new \InvalidArgumentException('Entity must be a Notification');
        }

        // Check if exists
        $existing = $this->findById($entity->getId());
        
        if ($existing) {
            $this->updateEntity($entity);
        } else {
            $this->insert($entity);
        }

        return $this->findById($entity->getId());
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Get the entity class this repository manages
     */
    public function getEntityClass(): string
    {
        return Notification::class;
    }

    /**
     * Get the database table name
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Insert a new notification
     */
    private function insert(Notification $notification): bool
    {
        $sql = "INSERT INTO {$this->table} 
                (notification_id, user_id, type, priority, title, message, data, is_read, read_at, created_at, expires_at)
                VALUES 
                (:notification_id, :user_id, :type, :priority, :title, :message, :data, :is_read, :read_at, :created_at, :expires_at)";

        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'notification_id' => $notification->getId(),
            'user_id' => $notification->getUserId(),
            'type' => $notification->getType(),
            'priority' => $notification->getPriority(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'data' => $notification->getData() ? json_encode($notification->getData()) : null,
            'is_read' => $notification->isRead() ? 1 : 0,
            'read_at' => $notification->getReadAt()?->format('Y-m-d H:i:s.u'),
            'created_at' => $notification->getCreatedAt()->format('Y-m-d H:i:s.u'),
            'expires_at' => $notification->getExpiresAt()?->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * Update an existing notification entity (internal use)
     */
    private function updateEntity(Notification $notification): bool
    {
        $sql = "UPDATE {$this->table} 
                SET type = :type,
                    priority = :priority,
                    title = :title,
                    message = :message,
                    data = :data,
                    is_read = :is_read,
                    read_at = :read_at,
                    expires_at = :expires_at
                WHERE notification_id = :notification_id";

        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'notification_id' => $notification->getId(),
            'type' => $notification->getType(),
            'priority' => $notification->getPriority(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'data' => $notification->getData() ? json_encode($notification->getData()) : null,
            'is_read' => $notification->isRead() ? 1 : 0,
            'read_at' => $notification->getReadAt()?->format('Y-m-d H:i:s.u'),
            'expires_at' => $notification->getExpiresAt()?->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId, string $userId): bool
    {
        $sql = "UPDATE {$this->table} 
                SET is_read = 1, read_at = NOW(6)
                WHERE notification_id = :notification_id 
                  AND user_id = :user_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'notification_id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(string $notificationId, string $userId): bool
    {
        $sql = "UPDATE {$this->table} 
                SET is_read = 0, read_at = NULL
                WHERE notification_id = :notification_id 
                  AND user_id = :user_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'notification_id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(string $userId, ?string $type = null): int
    {
        $conditions = ['user_id = :user_id', 'is_read = 0'];
        $params = ['user_id' => $userId];

        if ($type !== null && $type !== 'all') {
            $conditions[] = 'type = :type';
            $params['type'] = $type;
        }

        $whereClause = implode(' AND ', $conditions);
        
        $sql = "UPDATE {$this->table} 
                SET is_read = 1, read_at = NOW(6)
                WHERE {$whereClause}";

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Delete a notification
     */
    public function delete(string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE notification_id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Delete notification for a specific user (ensures ownership)
     */
    public function deleteForUser(string $notificationId, string $userId): bool
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE notification_id = :notification_id 
                  AND user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'notification_id' => $notificationId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Delete all notifications for a user
     */
    public function deleteAllForUser(string $userId): int
    {
        $sql = "DELETE FROM {$this->table} WHERE user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Delete expired notifications (cleanup job)
     */
    public function deleteExpired(): int
    {
        $sql = "DELETE FROM {$this->table} WHERE expires_at IS NOT NULL AND expires_at < NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Create a notification for a user
     */
    public function createNotification(
        string $userId,
        string $type,
        string $title,
        ?string $message = null,
        string $priority = 'normal',
        ?array $data = null,
        ?\DateTimeImmutable $expiresAt = null
    ): ?Notification {
        $notification = Notification::create(
            $userId,
            $type,
            $title,
            $message,
            $priority,
            $data,
            $expiresAt
        );

        if ($this->insert($notification)) {
            return $notification;
        }

        return null;
    }

    /**
     * Broadcast a notification to multiple users
     */
    public function broadcast(
        array $userIds,
        string $type,
        string $title,
        ?string $message = null,
        string $priority = 'normal',
        ?array $data = null,
        ?\DateTimeImmutable $expiresAt = null
    ): int {
        $successCount = 0;
        
        foreach ($userIds as $userId) {
            $notification = $this->createNotification(
                $userId,
                $type,
                $title,
                $message,
                $priority,
                $data,
                $expiresAt
            );
            
            if ($notification !== null) {
                $successCount++;
            }
        }

        return $successCount;
    }
}
