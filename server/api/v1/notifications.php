<?php

declare(strict_types=1);

/**
 * Notifications API Endpoint
 *
 * Handles all notification-related API requests.
 * Routes:
 * - GET    /api/v1/notifications                 - List notifications
 * - GET    /api/v1/notifications/unread-count    - Get unread count by type
 * - GET    /api/v1/notifications/{id}            - Get single notification
 * - POST   /api/v1/notifications/{id}/read       - Mark as read
 * - POST   /api/v1/notifications/{id}/unread     - Mark as unread
 * - POST   /api/v1/notifications/mark-all-read   - Mark all as read
 * - DELETE /api/v1/notifications/{id}            - Delete notification
 * - DELETE /api/v1/notifications                 - Delete all notifications
 *
 * @package SafeShift\API\v1
 */

use ViewModel\NotificationViewModel;
use ViewModel\Core\ApiResponse;

/**
 * Handle notifications route
 *
 * @param string $subPath The path after /notifications/
 * @param string $method HTTP method
 */
function handleNotificationsRoute(string $subPath, string $method): void
{
    // Get PDO instance using the db helper function (same pattern as working legacy endpoints)
    $pdo = \App\db\pdo();

    // Require authentication - check for user object with user_id (consistent with BaseViewModel)
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::error('Authentication required'), 401);
        return;
    }

    $userId = $_SESSION['user']['user_id'];
    
    // Initialize ViewModel
    $viewModel = new NotificationViewModel($pdo);
    $viewModel->setCurrentUser($userId);

    // Parse subpath for routing
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    $action = $segments[0] ?? '';
    $subAction = $segments[1] ?? '';

    // Route based on method and path
    switch ($method) {
        case 'GET':
            handleGetNotifications($viewModel, $action, $segments);
            break;
            
        case 'POST':
            handlePostNotifications($viewModel, $action, $subAction);
            break;
            
        case 'DELETE':
            handleDeleteNotifications($viewModel, $action);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * Handle GET requests for notifications
 */
function handleGetNotifications(NotificationViewModel $viewModel, string $action, array $segments): void
{
    // GET /notifications/unread-count
    if ($action === 'unread-count') {
        $result = $viewModel->getUnreadCount();
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // GET /notifications/{id} - Get single notification
    if (!empty($action) && isUuid($action)) {
        $result = $viewModel->getNotification($action);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // GET /notifications - List notifications with filters
    $filters = [
        'type' => $_GET['type'] ?? null,
        'read' => isset($_GET['read']) ? filter_var($_GET['read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
        'priority' => $_GET['priority'] ?? null,
        'search' => $_GET['search'] ?? null,
    ];
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
    
    $result = $viewModel->getNotifications($filters, $page, $perPage);
    ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
}

/**
 * Handle POST requests for notifications
 */
function handlePostNotifications(NotificationViewModel $viewModel, string $action, string $subAction): void
{
    // POST /notifications/mark-all-read
    if ($action === 'mark-all-read') {
        $input = getJsonInput();
        $type = $input['type'] ?? null;
        
        $result = $viewModel->markAllAsRead($type);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // POST /notifications/{id}/read
    if (isUuid($action) && $subAction === 'read') {
        $result = $viewModel->markAsRead($action);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // POST /notifications/{id}/unread
    if (isUuid($action) && $subAction === 'unread') {
        $result = $viewModel->markAsUnread($action);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // Invalid POST route
    ApiResponse::send(ApiResponse::error('Invalid endpoint'), 404);
}

/**
 * Handle DELETE requests for notifications
 */
function handleDeleteNotifications(NotificationViewModel $viewModel, string $action): void
{
    // DELETE /notifications - Delete all
    if (empty($action)) {
        $result = $viewModel->deleteAllNotifications();
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // DELETE /notifications/{id} - Delete single
    if (isUuid($action)) {
        $result = $viewModel->deleteNotification($action);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    ApiResponse::send(ApiResponse::error('Invalid endpoint'), 404);
}

/**
 * Check if string is a valid UUID
 */
function isUuid(string $value): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
}

/**
 * Get JSON input from request body
 */
function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return [];
    }
    
    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}
