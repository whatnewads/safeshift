<?php

declare(strict_types=1);

/**
 * Clinics API Endpoint
 *
 * Handles clinic management and email recipients endpoints.
 * Routes:
 *   GET  /api/v1/clinics                          - Get all clinics
 *   GET  /api/v1/clinics/:id                      - Get single clinic
 *   GET  /api/v1/clinics/:id/email-recipients     - Get email recipients for clinic
 *   POST /api/v1/clinics/:id/email-recipients     - Add email recipient
 *   PUT  /api/v1/clinics/:id/email-recipients/:emailId  - Update email recipient
 *   DELETE /api/v1/clinics/:id/email-recipients/:emailId - Delete email recipient
 *
 * @package API\v1
 */

require_once __DIR__ . '/../../model/Core/Database.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';

use Model\Core\Database;
use ViewModel\Core\ApiResponse;

/**
 * Route handler called by api/v1/index.php
 * 
 * @param string $subPath The path after /api/v1/clinics/
 * @param string $method The HTTP method
 */
function handleClinicsRoute(string $subPath, string $method): void
{
    // Parse path segments from subPath
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    try {
        // Initialize database
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Get user from session
        $userId = $_SESSION['user']['user_id'] ?? null;
        $userClinicId = $_SESSION['user']['clinic_id'] ?? $_SESSION['clinic_id'] ?? null;
        
        if (!$userId) {
            ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
            return;
        }
        
        // Route the request
        switch ($method) {
            case 'GET':
                handleClinicsGetRequest($pdo, $segments, $userClinicId);
                break;
                
            case 'POST':
                handleClinicsPostRequest($pdo, $segments, $userId);
                break;
                
            case 'PUT':
                handleClinicsPutRequest($pdo, $segments, $userId);
                break;
                
            case 'DELETE':
                handleClinicsDeleteRequest($pdo, $segments, $userId);
                break;
                
            default:
                ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        }
    } catch (\Exception $e) {
        error_log("Clinics API error: " . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Internal server error'), 500);
    }
}

/**
 * Handle GET requests
 */
function handleClinicsGetRequest(\PDO $pdo, array $segments, ?int $userClinicId): void
{
    // GET /clinics - List all clinics
    if (empty($segments)) {
        $clinics = getAllClinics($pdo);
        ApiResponse::send(ApiResponse::success($clinics, 'Clinics retrieved successfully'), 200);
        return;
    }
    
    $clinicId = (int)$segments[0];
    
    // GET /clinics/:id - Get single clinic
    if (count($segments) === 1) {
        $clinic = getClinicById($pdo, $clinicId);
        if (!$clinic) {
            ApiResponse::send(ApiResponse::notFound('Clinic not found'), 404);
            return;
        }
        ApiResponse::send(ApiResponse::success($clinic, 'Clinic retrieved successfully'), 200);
        return;
    }
    
    // GET /clinics/:id/email-recipients - Get email recipients for clinic
    if (isset($segments[1]) && $segments[1] === 'email-recipients') {
        $recipients = getClinicEmailRecipients($pdo, $clinicId);
        ApiResponse::send(ApiResponse::success($recipients, 'Email recipients retrieved successfully'), 200);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle POST requests
 */
function handleClinicsPostRequest(\PDO $pdo, array $segments, int $userId): void
{
    // POST /clinics/:id/email-recipients - Add email recipient
    if (count($segments) >= 2 && $segments[1] === 'email-recipients') {
        $clinicId = (int)$segments[0];
        
        // Get request body
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['email_address']) || empty(trim($input['email_address']))) {
            ApiResponse::send(ApiResponse::validationError('Email address is required'), 400);
            return;
        }
        
        $emailAddress = trim($input['email_address']);
        $recipientName = isset($input['recipient_name']) ? trim($input['recipient_name']) : null;
        $recipientType = isset($input['recipient_type']) && in_array($input['recipient_type'], ['work_related', 'all']) 
            ? $input['recipient_type'] 
            : 'work_related';
        
        // Validate email format
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            ApiResponse::send(ApiResponse::validationError('Invalid email address format'), 400);
            return;
        }
        
        // Check if email already exists for this clinic
        if (emailExistsForClinic($pdo, $clinicId, $emailAddress)) {
            ApiResponse::send(ApiResponse::validationError('Email address already exists for this clinic'), 400);
            return;
        }
        
        // Insert the email recipient
        $recipient = addClinicEmailRecipient($pdo, $clinicId, $emailAddress, $recipientName, $recipientType, $userId);
        
        if ($recipient) {
            ApiResponse::send(ApiResponse::success($recipient, 'Email recipient added successfully'), 201);
        } else {
            ApiResponse::send(ApiResponse::serverError('Failed to add email recipient'), 500);
        }
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle PUT requests
 */
function handleClinicsPutRequest(\PDO $pdo, array $segments, int $userId): void
{
    // PUT /clinics/:id/email-recipients/:emailId - Update email recipient
    if (count($segments) >= 3 && $segments[1] === 'email-recipients') {
        $clinicId = (int)$segments[0];
        $emailId = (int)$segments[2];
        
        // Get request body
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['email_address']) || empty(trim($input['email_address']))) {
            ApiResponse::send(ApiResponse::validationError('Email address is required'), 400);
            return;
        }
        
        $emailAddress = trim($input['email_address']);
        $recipientName = isset($input['recipient_name']) ? trim($input['recipient_name']) : null;
        $recipientType = isset($input['recipient_type']) && in_array($input['recipient_type'], ['work_related', 'all']) 
            ? $input['recipient_type'] 
            : 'work_related';
        
        // Validate email format
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            ApiResponse::send(ApiResponse::validationError('Invalid email address format'), 400);
            return;
        }
        
        // Check if recipient exists
        $existing = getEmailRecipientById($pdo, $emailId, $clinicId);
        if (!$existing) {
            ApiResponse::send(ApiResponse::notFound('Email recipient not found'), 404);
            return;
        }
        
        // Check if new email conflicts with another recipient (excluding current one)
        if (emailExistsForClinic($pdo, $clinicId, $emailAddress, $emailId)) {
            ApiResponse::send(ApiResponse::validationError('Email address already exists for this clinic'), 400);
            return;
        }
        
        // Update the email recipient
        $recipient = updateClinicEmailRecipient($pdo, $emailId, $clinicId, $emailAddress, $recipientName, $recipientType);
        
        if ($recipient) {
            ApiResponse::send(ApiResponse::success($recipient, 'Email recipient updated successfully'), 200);
        } else {
            ApiResponse::send(ApiResponse::serverError('Failed to update email recipient'), 500);
        }
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle DELETE requests
 */
function handleClinicsDeleteRequest(\PDO $pdo, array $segments, int $userId): void
{
    // DELETE /clinics/:id/email-recipients/:emailId - Delete email recipient
    if (count($segments) >= 3 && $segments[1] === 'email-recipients') {
        $clinicId = (int)$segments[0];
        $emailId = (int)$segments[2];
        
        // Check if recipient exists
        $existing = getEmailRecipientById($pdo, $emailId, $clinicId);
        if (!$existing) {
            ApiResponse::send(ApiResponse::notFound('Email recipient not found'), 404);
            return;
        }
        
        // Delete the email recipient
        $success = deleteClinicEmailRecipient($pdo, $emailId, $clinicId);
        
        if ($success) {
            ApiResponse::send(ApiResponse::success(null, 'Email recipient deleted successfully'), 200);
        } else {
            ApiResponse::send(ApiResponse::serverError('Failed to delete email recipient'), 500);
        }
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

// ============================================================================
// Database Helper Functions
// ============================================================================

/**
 * Get all clinics
 */
function getAllClinics(\PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT id, name, address, city, state, zip_code, phone, email, 
               is_active, created_at, updated_at
        FROM clinics
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Get clinic by ID
 */
function getClinicById(\PDO $pdo, int $clinicId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, name, address, city, state, zip_code, phone, email, 
               is_active, created_at, updated_at
        FROM clinics
        WHERE id = ?
    ");
    $stmt->execute([$clinicId]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Get email recipients for a clinic
 */
function getClinicEmailRecipients(\PDO $pdo, int $clinicId): array
{
    $stmt = $pdo->prepare("
        SELECT id, clinic_id, email_address, recipient_type, recipient_name, 
               created_at, updated_at, is_active
        FROM clinic_email_recipients
        WHERE clinic_id = ? AND is_active = 1
        ORDER BY email_address ASC
    ");
    $stmt->execute([$clinicId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Get email recipient by ID
 */
function getEmailRecipientById(\PDO $pdo, int $emailId, int $clinicId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, clinic_id, email_address, recipient_type, recipient_name, 
               created_at, updated_at, is_active
        FROM clinic_email_recipients
        WHERE id = ? AND clinic_id = ? AND is_active = 1
    ");
    $stmt->execute([$emailId, $clinicId]);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    return $result ?: null;
}

/**
 * Check if email exists for clinic
 */
function emailExistsForClinic(\PDO $pdo, int $clinicId, string $emailAddress, ?int $excludeId = null): bool
{
    $sql = "
        SELECT COUNT(*) as count
        FROM clinic_email_recipients
        WHERE clinic_id = ? AND email_address = ? AND is_active = 1
    ";
    $params = [$clinicId, $emailAddress];
    
    if ($excludeId !== null) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    return (int)$result['count'] > 0;
}

/**
 * Add email recipient
 */
function addClinicEmailRecipient(
    \PDO $pdo, 
    int $clinicId, 
    string $emailAddress, 
    ?string $recipientName,
    string $recipientType,
    int $userId
): ?array {
    $stmt = $pdo->prepare("
        INSERT INTO clinic_email_recipients 
        (clinic_id, email_address, recipient_name, recipient_type, created_by, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    $success = $stmt->execute([
        $clinicId,
        $emailAddress,
        $recipientName,
        $recipientType,
        $userId
    ]);
    
    if ($success) {
        $newId = (int)$pdo->lastInsertId();
        return getEmailRecipientById($pdo, $newId, $clinicId);
    }
    
    return null;
}

/**
 * Update email recipient
 */
function updateClinicEmailRecipient(
    \PDO $pdo, 
    int $emailId, 
    int $clinicId,
    string $emailAddress, 
    ?string $recipientName,
    string $recipientType
): ?array {
    $stmt = $pdo->prepare("
        UPDATE clinic_email_recipients 
        SET email_address = ?, recipient_name = ?, recipient_type = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND clinic_id = ?
    ");
    
    $success = $stmt->execute([
        $emailAddress,
        $recipientName,
        $recipientType,
        $emailId,
        $clinicId
    ]);
    
    if ($success) {
        return getEmailRecipientById($pdo, $emailId, $clinicId);
    }
    
    return null;
}

/**
 * Delete email recipient (soft delete)
 */
function deleteClinicEmailRecipient(\PDO $pdo, int $emailId, int $clinicId): bool
{
    $stmt = $pdo->prepare("
        UPDATE clinic_email_recipients 
        SET is_active = 0, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND clinic_id = ?
    ");
    
    return $stmt->execute([$emailId, $clinicId]);
}
