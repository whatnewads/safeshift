<?php

declare(strict_types=1);

namespace Model\Entities;

use Model\Interfaces\EntityInterface;

/**
 * Notification Entity
 *
 * Represents a user notification from the user_notification table.
 * Maps notification types to frontend categories (training, licensure, registrar, case, message).
 *
 * @package Model\Entities
 */
class Notification implements EntityInterface
{
    private string $notificationId;
    private string $userId;
    private string $type;
    private string $priority;
    private string $title;
    private ?string $message;
    private ?array $data;
    private bool $isRead;
    private ?\DateTimeImmutable $readAt;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $expiresAt;

    /**
     * Notification types that map to the frontend categories
     */
    public const TYPE_TRAINING = 'training';
    public const TYPE_LICENSURE = 'licensure';
    public const TYPE_REGISTRAR = 'registrar';
    public const TYPE_CASE = 'case';
    public const TYPE_MESSAGE = 'message';
    public const TYPE_SYSTEM = 'system';

    /**
     * Priority levels
     */
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';

    /**
     * Valid notification types
     */
    private const VALID_TYPES = [
        self::TYPE_TRAINING,
        self::TYPE_LICENSURE,
        self::TYPE_REGISTRAR,
        self::TYPE_CASE,
        self::TYPE_MESSAGE,
        self::TYPE_SYSTEM,
    ];

    /**
     * Valid priorities
     */
    private const VALID_PRIORITIES = [
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
    ];

    public function __construct(
        string $notificationId,
        string $userId,
        string $type,
        string $title,
        ?string $message = null,
        string $priority = self::PRIORITY_NORMAL,
        ?array $data = null,
        bool $isRead = false,
        ?\DateTimeImmutable $readAt = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $expiresAt = null
    ) {
        $this->notificationId = $notificationId;
        $this->userId = $userId;
        $this->setType($type);
        $this->title = $title;
        $this->message = $message;
        $this->setPriority($priority);
        $this->data = $data;
        $this->isRead = $isRead;
        $this->readAt = $readAt;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
    }

    /**
     * Create a new notification with a generated UUID
     */
    public static function create(
        string $userId,
        string $type,
        string $title,
        ?string $message = null,
        string $priority = self::PRIORITY_NORMAL,
        ?array $data = null,
        ?\DateTimeImmutable $expiresAt = null
    ): self {
        return new self(
            self::generateUuid(),
            $userId,
            $type,
            $title,
            $message,
            $priority,
            $data,
            false,
            null,
            new \DateTimeImmutable(),
            $expiresAt
        );
    }

    /**
     * Create notification from database row
     *
     * @param array<string, mixed> $data Entity data
     * @return static New entity instance
     */
    public static function fromArray(array $data): static
    {
        $jsonData = null;
        if (!empty($data['data'])) {
            $jsonData = is_string($data['data']) ? json_decode($data['data'], true) : $data['data'];
        }

        return new static(
            $data['notification_id'],
            $data['user_id'],
            $data['type'],
            $data['title'],
            $data['message'] ?? null,
            $data['priority'] ?? self::PRIORITY_NORMAL,
            $jsonData,
            (bool)($data['is_read'] ?? false),
            !empty($data['read_at']) ? new \DateTimeImmutable($data['read_at']) : null,
            !empty($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            !empty($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null
        );
    }

    /**
     * Generate UUID for new notifications
     */
    private static function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    // Getters

    /**
     * Get the entity's unique identifier
     *
     * @return string|null The entity ID (UUID) or null if not yet persisted
     */
    public function getId(): ?string
    {
        return $this->notificationId;
    }

    public function getNotificationId(): string
    {
        return $this->notificationId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    /**
     * Get entity creation timestamp
     *
     * @return \DateTimeInterface|null Creation timestamp or null if not set
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Get entity last update timestamp
     *
     * @return \DateTimeInterface|null Update timestamp or null if not set
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        // Notifications are immutable after creation, so updated_at equals created_at
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Check if entity has been persisted
     *
     * @return bool True if entity has an ID (persisted), false otherwise
     */
    public function isPersisted(): bool
    {
        return !empty($this->notificationId);
    }

    /**
     * Check if notification is expired
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * Check if notification requires action
     */
    public function isActionRequired(): bool
    {
        return isset($this->data['action_required']) && $this->data['action_required'] === true;
    }

    // Setters (immutable-style returns new instance or mutates)

    private function setType(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            // Allow unknown types but log warning
            error_log("Unknown notification type: {$type}");
        }
        $this->type = $type;
    }

    private function setPriority(string $priority): void
    {
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            $priority = self::PRIORITY_NORMAL;
        }
        $this->priority = $priority;
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): self
    {
        $this->isRead = true;
        $this->readAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): self
    {
        $this->isRead = false;
        $this->readAt = null;
        return $this;
    }

    /**
     * Convert entity to array representation
     *
     * Returns all entity properties as an associative array.
     *
     * @return array<string, mixed> Entity data as array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->notificationId,
            'notification_id' => $this->notificationId,
            'user_id' => $this->userId,
            'type' => $this->type,
            'priority' => $this->priority,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'is_read' => $this->isRead,
            'read' => $this->isRead, // Alias for frontend compatibility
            'read_at' => $this->readAt?->format('Y-m-d\TH:i:s.u\Z'),
            'created_at' => $this->createdAt->format('Y-m-d\TH:i:s.u\Z'),
            'expires_at' => $this->expiresAt?->format('Y-m-d\TH:i:s.u\Z'),
            'timestamp' => $this->getRelativeTimestamp(),
            'action_required' => $this->isActionRequired(),
            'metadata' => $this->data, // Alias for frontend compatibility
        ];
    }

    /**
     * Convert entity to safe array representation
     *
     * Returns entity properties safe for external exposure.
     *
     * @return array<string, mixed> Safe entity data as array
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->notificationId,
            'type' => $this->type,
            'priority' => $this->priority,
            'title' => $this->title,
            'message' => $this->message,
            'is_read' => $this->isRead,
            'timestamp' => $this->getRelativeTimestamp(),
            'action_required' => $this->isActionRequired(),
        ];
    }

    /**
     * Get human-readable relative timestamp (e.g., "5 minutes ago")
     */
    public function getRelativeTimestamp(): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->createdAt);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }
        return 'just now';
    }

    /**
     * Validate entity data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->notificationId)) {
            $errors[] = 'Notification ID is required';
        }
        if (empty($this->userId)) {
            $errors[] = 'User ID is required';
        }
        if (empty($this->type)) {
            $errors[] = 'Type is required';
        }
        if (empty($this->title)) {
            $errors[] = 'Title is required';
        }

        return $errors;
    }
}
