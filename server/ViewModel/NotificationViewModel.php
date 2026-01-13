<?php

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\NotificationRepository;
use Model\Entities\Notification;
use ViewModel\Core\ApiResponse;
use PDO;

/**
 * Notification ViewModel
 *
 * Coordinates between the View (API) and Model (Repository/Entity) layers.
 * Handles business logic for notification operations.
 *
 * @package ViewModel
 */
class NotificationViewModel
{
    private NotificationRepository $repository;
    private ?string $currentUserId = null;

    public function __construct(PDO $pdo)
    {
        $this->repository = new NotificationRepository($pdo);
    }

    /**
     * Set the current user context
     */
    public function setCurrentUser(string $userId): self
    {
        $this->currentUserId = $userId;
        return $this;
    }

    /**
     * Get notifications for the current user
     *
     * @param array $filters Optional filters (type, read, priority, search)
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array API response data
     */
    public function getNotifications(
        array $filters = [],
        int $page = 1,
        int $perPage = 50
    ): array {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $type = $filters['type'] ?? null;
            $isRead = isset($filters['read']) ? (bool) $filters['read'] : null;
            $priority = $filters['priority'] ?? null;
            $search = $filters['search'] ?? null;

            $offset = ($page - 1) * $perPage;

            // Get notifications
            $notifications = $this->repository->findByUserId(
                $this->currentUserId,
                $type,
                $isRead,
                $priority,
                $perPage,
                $offset
            );

            // Apply search filter in memory (for title/message search)
            if ($search) {
                $searchLower = strtolower($search);
                $notifications = array_filter($notifications, function (Notification $n) use ($searchLower) {
                    return str_contains(strtolower($n->getTitle()), $searchLower) ||
                           str_contains(strtolower($n->getMessage() ?? ''), $searchLower);
                });
                $notifications = array_values($notifications);
            }

            // Get counts for badges
            $unreadCounts = $this->repository->countUnreadByType($this->currentUserId);
            $totalCount = $this->repository->countByUserId($this->currentUserId, $type, $isRead);

            // Convert to array format
            $notificationData = array_map(
                fn(Notification $n) => $n->toArray(),
                $notifications
            );

            return ApiResponse::success([
                'notifications' => $notificationData,
                'unread_counts' => $unreadCounts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $perPage),
                ],
                'filters' => [
                    'type' => $type,
                    'read' => $isRead,
                    'priority' => $priority,
                    'search' => $search,
                ],
            ]);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::getNotifications error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve notifications', 500);
        }
    }

    /**
     * Get a single notification by ID
     */
    public function getNotification(string $notificationId): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $notification = $this->repository->findById($notificationId);

            if (!$notification) {
                return ApiResponse::error('Notification not found', 404);
            }

            // Verify ownership
            if ($notification->getUserId() !== $this->currentUserId) {
                return ApiResponse::error('Access denied', 403);
            }

            return ApiResponse::success([
                'notification' => $notification->toArray(),
            ]);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::getNotification error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve notification', 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $counts = $this->repository->countUnreadByType($this->currentUserId);
            $hasUnread = $this->repository->hasUnread($this->currentUserId);

            return ApiResponse::success([
                'unread_counts' => $counts,
                'has_unread' => $hasUnread,
                'total_unread' => $counts['all'],
            ]);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::getUnreadCount error: " . $e->getMessage());
            return ApiResponse::error('Failed to retrieve unread count', 500);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(string $notificationId): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            // Verify notification exists and belongs to user
            $notification = $this->repository->findById($notificationId);
            
            if (!$notification) {
                return ApiResponse::error('Notification not found', 404);
            }

            if ($notification->getUserId() !== $this->currentUserId) {
                return ApiResponse::error('Access denied', 403);
            }

            $success = $this->repository->markAsRead($notificationId, $this->currentUserId);

            if ($success) {
                // Return updated notification and counts
                $updated = $this->repository->findById($notificationId);
                $counts = $this->repository->countUnreadByType($this->currentUserId);

                return ApiResponse::success([
                    'notification' => $updated?->toArray(),
                    'unread_counts' => $counts,
                    'message' => 'Notification marked as read',
                ]);
            }

            return ApiResponse::error('Failed to mark notification as read', 500);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::markAsRead error: " . $e->getMessage());
            return ApiResponse::error('Failed to mark notification as read', 500);
        }
    }

    /**
     * Mark a notification as unread
     */
    public function markAsUnread(string $notificationId): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $notification = $this->repository->findById($notificationId);
            
            if (!$notification) {
                return ApiResponse::error('Notification not found', 404);
            }

            if ($notification->getUserId() !== $this->currentUserId) {
                return ApiResponse::error('Access denied', 403);
            }

            $success = $this->repository->markAsUnread($notificationId, $this->currentUserId);

            if ($success) {
                $updated = $this->repository->findById($notificationId);
                $counts = $this->repository->countUnreadByType($this->currentUserId);

                return ApiResponse::success([
                    'notification' => $updated?->toArray(),
                    'unread_counts' => $counts,
                    'message' => 'Notification marked as unread',
                ]);
            }

            return ApiResponse::error('Failed to mark notification as unread', 500);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::markAsUnread error: " . $e->getMessage());
            return ApiResponse::error('Failed to mark notification as unread', 500);
        }
    }

    /**
     * Mark all notifications as read (optionally filtered by type)
     */
    public function markAllAsRead(?string $type = null): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $count = $this->repository->markAllAsRead($this->currentUserId, $type);
            $counts = $this->repository->countUnreadByType($this->currentUserId);

            return ApiResponse::success([
                'marked_count' => $count,
                'unread_counts' => $counts,
                'message' => "Marked {$count} notification(s) as read",
            ]);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::markAllAsRead error: " . $e->getMessage());
            return ApiResponse::error('Failed to mark all notifications as read', 500);
        }
    }

    /**
     * Delete a notification
     */
    public function deleteNotification(string $notificationId): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $notification = $this->repository->findById($notificationId);
            
            if (!$notification) {
                return ApiResponse::error('Notification not found', 404);
            }

            if ($notification->getUserId() !== $this->currentUserId) {
                return ApiResponse::error('Access denied', 403);
            }

            $success = $this->repository->deleteForUser($notificationId, $this->currentUserId);

            if ($success) {
                $counts = $this->repository->countUnreadByType($this->currentUserId);

                return ApiResponse::success([
                    'unread_counts' => $counts,
                    'message' => 'Notification deleted',
                ]);
            }

            return ApiResponse::error('Failed to delete notification', 500);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::deleteNotification error: " . $e->getMessage());
            return ApiResponse::error('Failed to delete notification', 500);
        }
    }

    /**
     * Delete all notifications for the current user
     */
    public function deleteAllNotifications(): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $count = $this->repository->deleteAllForUser($this->currentUserId);

            return ApiResponse::success([
                'deleted_count' => $count,
                'unread_counts' => [
                    'all' => 0,
                    'training' => 0,
                    'licensure' => 0,
                    'registrar' => 0,
                    'case' => 0,
                    'message' => 0,
                    'system' => 0,
                ],
                'message' => "Deleted {$count} notification(s)",
            ]);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::deleteAllNotifications error: " . $e->getMessage());
            return ApiResponse::error('Failed to delete notifications', 500);
        }
    }

    /**
     * Create a new notification (for system use)
     */
    public function createNotification(
        string $userId,
        string $type,
        string $title,
        ?string $message = null,
        string $priority = 'normal',
        ?array $data = null,
        ?string $expiresAt = null
    ): array {
        try {
            // Validate type
            $validTypes = ['training', 'licensure', 'registrar', 'case', 'message', 'system'];
            if (!in_array($type, $validTypes)) {
                return ApiResponse::error("Invalid notification type: {$type}", 400);
            }

            // Validate priority
            $validPriorities = ['high', 'medium', 'low', 'normal'];
            if (!in_array($priority, $validPriorities)) {
                $priority = 'normal';
            }

            $expiresAtDate = $expiresAt ? new \DateTimeImmutable($expiresAt) : null;

            $notification = $this->repository->createNotification(
                $userId,
                $type,
                $title,
                $message,
                $priority,
                $data,
                $expiresAtDate
            );

            if ($notification) {
                return ApiResponse::success([
                    'notification' => $notification->toArray(),
                    'message' => 'Notification created',
                ], 201);
            }

            return ApiResponse::error('Failed to create notification', 500);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::createNotification error: " . $e->getMessage());
            return ApiResponse::error('Failed to create notification', 500);
        }
    }

    /**
     * Broadcast notification to multiple users (for admin use)
     */
    public function broadcastNotification(
        array $userIds,
        string $type,
        string $title,
        ?string $message = null,
        string $priority = 'normal',
        ?array $data = null,
        ?string $expiresAt = null
    ): array {
        try {
            $expiresAtDate = $expiresAt ? new \DateTimeImmutable($expiresAt) : null;

            $count = $this->repository->broadcast(
                $userIds,
                $type,
                $title,
                $message,
                $priority,
                $data,
                $expiresAtDate
            );

            return ApiResponse::success([
                'sent_count' => $count,
                'total_users' => count($userIds),
                'message' => "Notification sent to {$count} user(s)",
            ]);
        } catch (\Exception $e) {
            error_log("NotificationViewModel::broadcastNotification error: " . $e->getMessage());
            return ApiResponse::error('Failed to broadcast notification', 500);
        }
    }
}
