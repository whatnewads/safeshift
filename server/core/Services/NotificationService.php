<?php
/**
 * Notification Service
 * 
 * Handles business logic for user notifications
 * Manages notification creation, delivery, and status
 */

namespace Core\Services;

use Core\Repositories\NotificationRepository;
use Exception;

class NotificationService
{
    private NotificationRepository $notificationRepo;
    
    /**
     * Constructor
     */
    public function __construct(NotificationRepository $notificationRepo)
    {
        $this->notificationRepo = $notificationRepo;
    }
    
    /**
     * Get notifications for user
     */
    public function getUserNotifications(string $userId, bool $unreadOnly = false, int $limit = 20, int $offset = 0): array
    {
        // Ensure table exists
        $this->notificationRepo->createTableIfNotExists();
        
        // Get notifications
        $notifications = $this->notificationRepo->getForUser($userId, $unreadOnly, $limit, $offset);
        
        // Format notifications
        $formatted = [];
        foreach ($notifications as $notif) {
            $formatted[] = $this->formatNotification($notif);
        }
        
        // Get counts
        $counts = $this->notificationRepo->getCounts($userId);
        
        return [
            'notifications' => $formatted,
            'total' => (int)$counts['total'],
            'unread' => (int)$counts['unread'],
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $counts['total']
        ];
    }
    
    /**
     * Mark notifications as read
     */
    public function markAsRead(string $userId, array $notificationIds): int
    {
        return $this->notificationRepo->markAsRead($userId, $notificationIds);
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(string $userId): int
    {
        return $this->notificationRepo->markAllAsRead($userId);
    }
    
    /**
     * Create notification for user
     */
    public function createNotification(array $notification): string
    {
        // Validate required fields
        if (empty($notification['user_id'])) {
            throw new Exception('User ID is required');
        }
        if (empty($notification['type'])) {
            throw new Exception('Notification type is required');
        }
        if (empty($notification['title'])) {
            throw new Exception('Notification title is required');
        }
        
        // Set defaults
        $notification['priority'] = $notification['priority'] ?? 'normal';
        
        // Set expiration based on type if not provided
        if (!isset($notification['expires_at'])) {
            $notification['expires_at'] = $this->getDefaultExpiration($notification['type']);
        }
        
        return $this->notificationRepo->create($notification);
    }
    
    /**
     * Create critical notification
     */
    public function createCriticalNotification(string $userId, string $title, string $message, array $data = []): string
    {
        return $this->createNotification([
            'user_id' => $userId,
            'type' => 'critical_alert',
            'priority' => 'critical',
            'title' => $title,
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * Create lab result notification
     */
    public function createLabResultNotification(string $userId, string $patientId, array $labData): string
    {
        $title = isset($labData['is_critical']) && $labData['is_critical'] 
            ? 'Critical Lab Result' 
            : 'New Lab Result Available';
        
        return $this->createNotification([
            'user_id' => $userId,
            'type' => 'lab_result',
            'priority' => isset($labData['is_critical']) && $labData['is_critical'] ? 'high' : 'normal',
            'title' => $title,
            'message' => $labData['message'] ?? 'Lab results are ready for review',
            'data' => [
                'patient_id' => $patientId,
                'lab_id' => $labData['lab_id'] ?? null,
                'test_name' => $labData['test_name'] ?? null
            ]
        ]);
    }
    
    /**
     * Create appointment reminder
     */
    public function createAppointmentReminder(string $userId, array $appointmentData): string
    {
        return $this->createNotification([
            'user_id' => $userId,
            'type' => 'appointment_reminder',
            'priority' => 'normal',
            'title' => 'Upcoming Appointment',
            'message' => $appointmentData['message'] ?? 'You have an upcoming appointment',
            'data' => [
                'appointment_id' => $appointmentData['appointment_id'] ?? null,
                'appointment_time' => $appointmentData['appointment_time'] ?? null,
                'patient_name' => $appointmentData['patient_name'] ?? null
            ]
        ]);
    }
    
    /**
     * Create prescription alert
     */
    public function createPrescriptionAlert(string $userId, array $prescriptionData): string
    {
        $priority = isset($prescriptionData['is_urgent']) && $prescriptionData['is_urgent'] ? 'high' : 'normal';
        
        return $this->createNotification([
            'user_id' => $userId,
            'type' => 'prescription_alert',
            'priority' => $priority,
            'title' => $prescriptionData['title'] ?? 'Prescription Action Required',
            'message' => $prescriptionData['message'],
            'data' => [
                'prescription_ids' => $prescriptionData['prescription_ids'] ?? [],
                'patient_count' => $prescriptionData['patient_count'] ?? 0
            ]
        ]);
    }
    
    /**
     * Create system notification for role
     */
    public function createSystemNotificationForRole(string $role, string $title, string $message, string $priority = 'normal'): int
    {
        return $this->notificationRepo->createForRole($role, [
            'type' => 'system_alert',
            'priority' => $priority,
            'title' => $title,
            'message' => $message
        ]);
    }
    
    /**
     * Delete expired notifications
     */
    public function cleanupExpiredNotifications(): int
    {
        return $this->notificationRepo->deleteExpired();
    }
    
    /**
     * Get notifications by type
     */
    public function getNotificationsByType(string $userId, string $type, int $limit = 10): array
    {
        $notifications = $this->notificationRepo->getByType($userId, $type, $limit);
        
        $formatted = [];
        foreach ($notifications as $notif) {
            $formatted[] = $this->formatNotification($notif);
        }
        
        return $formatted;
    }
    
    /**
     * Check if user has any unread notifications
     */
    public function hasUnreadNotifications(string $userId): bool
    {
        $counts = $this->notificationRepo->getCounts($userId);
        return $counts['unread'] > 0;
    }
    
    /**
     * Format notification for response
     */
    private function formatNotification(array $notification): array
    {
        return [
            'id' => $notification['notification_id'],
            'type' => $notification['type'],
            'priority' => $notification['priority'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'data' => json_decode($notification['data'], true) ?? [],
            'is_read' => (bool)$notification['is_read'],
            'read_at' => $notification['read_at'],
            'created_at' => $notification['created_at'],
            'time_ago' => $this->getTimeAgo(strtotime($notification['created_at'])),
            'expires_at' => $notification['expires_at']
        ];
    }
    
    /**
     * Get default expiration time based on notification type
     */
    private function getDefaultExpiration(string $type): ?string
    {
        $expirationDays = [
            'system_alert' => 30,
            'appointment_reminder' => 7,
            'prescription_alert' => 14,
            'lab_result' => 90,
            'patient_update' => 30,
            'critical_alert' => 7,
            'maintenance' => 7
        ];
        
        $days = $expirationDays[$type] ?? 30;
        return date('Y-m-d H:i:s', strtotime("+$days days"));
    }
    
    /**
     * Convert timestamp to human-readable time ago format
     */
    private function getTimeAgo(int $timestamp): string
    {
        $timeAgo = time() - $timestamp;
        
        if ($timeAgo < 60) {
            return 'Just now';
        } elseif ($timeAgo < 3600) {
            $minutes = floor($timeAgo / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($timeAgo < 86400) {
            $hours = floor($timeAgo / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($timeAgo < 604800) {
            $days = floor($timeAgo / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
    
    /**
     * Create sample notifications for testing
     */
    public function createSampleNotifications(string $userId): void
    {
        // Only create if user has no notifications
        if ($this->notificationRepo->hasNotifications($userId)) {
            return;
        }
        
        $sampleNotifications = [
            [
                'user_id' => $userId,
                'type' => 'lab_result',
                'priority' => 'high',
                'title' => 'Critical Lab Result',
                'message' => 'Patient John Doe has critical lab values requiring immediate attention.',
                'data' => ['patient_id' => 'sample-patient-1', 'lab_id' => 'lab-001']
            ],
            [
                'user_id' => $userId,
                'type' => 'appointment_reminder',
                'priority' => 'normal',
                'title' => 'Upcoming Appointments',
                'message' => 'You have 3 appointments scheduled for tomorrow.',
                'data' => ['count' => 3, 'date' => date('Y-m-d', strtotime('+1 day'))]
            ],
            [
                'user_id' => $userId,
                'type' => 'system_alert',
                'priority' => 'low',
                'title' => 'System Maintenance',
                'message' => 'Scheduled maintenance will occur this weekend from 2 AM to 4 AM.',
                'data' => ['maintenance_window' => '2025-12-15 02:00:00']
            ],
            [
                'user_id' => $userId,
                'type' => 'patient_update',
                'priority' => 'normal',
                'title' => 'Patient Status Update',
                'message' => 'Patient Jane Smith has been discharged from the ER.',
                'data' => ['patient_id' => 'sample-patient-2', 'status' => 'discharged']
            ],
            [
                'user_id' => $userId,
                'type' => 'prescription_alert',
                'priority' => 'high',
                'title' => 'Prescription Renewal Required',
                'message' => '5 patients have prescriptions expiring within the next 7 days.',
                'data' => ['count' => 5, 'urgent' => true]
            ]
        ];
        
        $this->notificationRepo->createBatch($sampleNotifications);
    }
}