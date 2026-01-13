<?php
/**
 * Notifications ViewModel
 * 
 * Handles API request/response formatting for user notifications
 * Validates input and transforms Model data for API consumption
 */

namespace ViewModel;

use Core\Services\NotificationService;
use Exception;

class NotificationsViewModel
{
    private NotificationService $notificationService;
    
    /**
     * Constructor
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Get notifications for user
     */
    public function getNotifications(string $userId, array $input): array
    {
        try {
            // Input validation
            $errors = $this->validateGetInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Extract parameters
            $unreadOnly = isset($input['unread_only']) && $input['unread_only'] === 'true';
            $limit = isset($input['limit']) ? (int)$input['limit'] : 20;
            $offset = isset($input['offset']) ? (int)$input['offset'] : 0;
            
            // Call Model service
            $result = $this->notificationService->getUserNotifications(
                $userId,
                $unreadOnly,
                $limit,
                $offset
            );
            
            // Create sample notifications if none exist
            if ($result['total'] === 0) {
                $this->notificationService->createSampleNotifications($userId);
                // Re-fetch after creating samples
                $result = $this->notificationService->getUserNotifications(
                    $userId,
                    $unreadOnly,
                    $limit,
                    $offset
                );
            }
            
            // Transform for API response
            return [
                'success' => true,
                'data' => $result,
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Notifications fetch error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve notifications',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Mark notifications as read
     */
    public function markAsRead(string $userId, array $input): array
    {
        try {
            // Input validation
            $errors = $this->validateMarkAsReadInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Call Model service
            $updatedCount = $this->notificationService->markAsRead(
                $userId,
                $input['notification_ids']
            );
            
            // Transform for API response
            return [
                'success' => true,
                'data' => [
                    'updated' => $updatedCount,
                    'requested' => count($input['notification_ids'])
                ],
                'message' => "$updatedCount notification(s) marked as read",
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Mark notifications as read error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to mark notifications as read',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(string $userId): array
    {
        try {
            // Call Model service
            $updatedCount = $this->notificationService->markAllAsRead($userId);
            
            // Transform for API response
            return [
                'success' => true,
                'data' => [
                    'updated' => $updatedCount
                ],
                'message' => "All notifications marked as read",
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Mark all notifications as read error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to mark all notifications as read',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Create a new notification
     */
    public function createNotification(array $input): array
    {
        try {
            // Input validation
            $errors = $this->validateCreateInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Call Model service
            $notificationId = $this->notificationService->createNotification($input);
            
            // Transform for API response
            return [
                'success' => true,
                'data' => [
                    'notification_id' => $notificationId
                ],
                'message' => 'Notification created successfully',
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Create notification error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to create notification',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Get notifications by type
     */
    public function getByType(string $userId, array $input): array
    {
        try {
            // Input validation
            $errors = $this->validateTypeInput($input);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            $limit = isset($input['limit']) ? (int)$input['limit'] : 10;
            
            // Call Model service
            $notifications = $this->notificationService->getNotificationsByType(
                $userId,
                $input['type'],
                $limit
            );
            
            // Transform for API response
            return [
                'success' => true,
                'data' => [
                    'notifications' => $notifications,
                    'type' => $input['type'],
                    'count' => count($notifications)
                ],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Get notifications by type error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to retrieve notifications by type',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Check for unread notifications
     */
    public function hasUnread(string $userId): array
    {
        try {
            // Call Model service
            $hasUnread = $this->notificationService->hasUnreadNotifications($userId);
            
            // Transform for API response
            return [
                'success' => true,
                'data' => [
                    'has_unread' => $hasUnread
                ],
                'timestamp' => date('c')
            ];
            
        } catch (Exception $e) {
            error_log("Check unread notifications error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Failed to check for unread notifications',
                'timestamp' => date('c')
            ];
        }
    }
    
    /**
     * Validate GET input
     */
    private function validateGetInput(array $input): array
    {
        $errors = [];
        
        if (isset($input['limit'])) {
            if (!is_numeric($input['limit']) || $input['limit'] < 1 || $input['limit'] > 100) {
                $errors['limit'] = 'Limit must be between 1 and 100';
            }
        }
        
        if (isset($input['offset'])) {
            if (!is_numeric($input['offset']) || $input['offset'] < 0) {
                $errors['offset'] = 'Offset must be a non-negative number';
            }
        }
        
        if (isset($input['unread_only']) && !in_array($input['unread_only'], ['true', 'false'])) {
            $errors['unread_only'] = 'Unread only must be true or false';
        }
        
        return $errors;
    }
    
    /**
     * Validate mark as read input
     */
    private function validateMarkAsReadInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['notification_ids']) || !is_array($input['notification_ids'])) {
            $errors['notification_ids'] = 'Notification IDs array is required';
        } else {
            foreach ($input['notification_ids'] as $index => $id) {
                if (!preg_match($uuidPattern, $id)) {
                    $errors["notification_ids.$index"] = 'Invalid notification ID format';
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate create input
     */
    private function validateCreateInput(array $input): array
    {
        $errors = [];
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        
        if (empty($input['user_id'])) {
            $errors['user_id'] = 'User ID is required';
        } elseif (!preg_match($uuidPattern, $input['user_id'])) {
            $errors['user_id'] = 'Invalid user ID format';
        }
        
        if (empty($input['type'])) {
            $errors['type'] = 'Notification type is required';
        }
        
        if (empty($input['title'])) {
            $errors['title'] = 'Notification title is required';
        }
        
        $validPriorities = ['low', 'normal', 'high', 'critical'];
        if (isset($input['priority']) && !in_array($input['priority'], $validPriorities)) {
            $errors['priority'] = 'Priority must be one of: ' . implode(', ', $validPriorities);
        }
        
        return $errors;
    }
    
    /**
     * Validate type input
     */
    private function validateTypeInput(array $input): array
    {
        $errors = [];
        
        if (empty($input['type'])) {
            $errors['type'] = 'Notification type is required';
        }
        
        if (isset($input['limit'])) {
            if (!is_numeric($input['limit']) || $input['limit'] < 1 || $input['limit'] > 100) {
                $errors['limit'] = 'Limit must be between 1 and 100';
            }
        }
        
        return $errors;
    }
}