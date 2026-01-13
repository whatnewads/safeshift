<?php

declare(strict_types=1);

/**
 * Document Upload API Endpoint
 *
 * POST /api/v1/encounters/upload-document
 * Uploads an appointment document/photo for an encounter
 *
 * Request (multipart/form-data):
 * - file: The uploaded file (image or PDF)
 * - encounter_id: int (required)
 * - document_type: string (optional) - 'appointment_card', 'referral', 'prescription', 'other'
 * - notes: string (optional)
 *
 * Response:
 * Success: { success: true, data: { document_id, file_name, file_path, ... } }
 * Failure: { success: false, error: string }
 *
 * @package SafeShift\API\v1\Encounters
 */

// Bootstrap if not already loaded
if (!defined('BOOTSTRAP_LOADED')) {
    require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
}

use ViewModel\Core\ApiResponse;

// Set JSON content type (will be changed for file download if needed)
header('Content-Type: application/json; charset=utf-8');

// CORS Headers
$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:3000',
    'http://127.0.0.1:3000'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-XSRF-Token, Authorization');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST for upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    exit;
}

// Check authentication
if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
    ApiResponse::send(ApiResponse::error('Authentication required'), 401);
    exit;
}

$userId = $_SESSION['user']['user_id'];

// Validate required fields
if (empty($_POST['encounter_id'])) {
    ApiResponse::send(ApiResponse::error('Encounter ID is required'), 400);
    exit;
}

$encounterId = (int) $_POST['encounter_id'];
$documentType = $_POST['document_type'] ?? 'other';
$notes = $_POST['notes'] ?? null;

// Validate document type
$validDocumentTypes = ['appointment_card', 'referral', 'prescription', 'other'];
if (!in_array($documentType, $validDocumentTypes)) {
    $documentType = 'other';
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    ApiResponse::send(ApiResponse::error('No file uploaded'), 400);
    exit;
}

$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
    ];
    $errorMessage = $uploadErrors[$file['error']] ?? 'Unknown upload error';
    ApiResponse::send(ApiResponse::error($errorMessage), 400);
    exit;
}

// Validate file type
$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!isset($allowedMimeTypes[$mimeType])) {
    ApiResponse::send(ApiResponse::error('Invalid file type. Allowed: JPEG, PNG, GIF, WebP, PDF'), 400);
    exit;
}

// Validate file size (max 10MB)
$maxFileSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxFileSize) {
    ApiResponse::send(ApiResponse::error('File size exceeds maximum allowed (10MB)'), 400);
    exit;
}

// Generate secure filename
$extension = $allowedMimeTypes[$mimeType];
$secureFileName = bin2hex(random_bytes(16)) . '.' . $extension;
$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$originalName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
$originalName = substr($originalName, 0, 100) . '.' . $extension;

// Create upload directory structure
$baseUploadDir = dirname(__DIR__, 3) . '/uploads/documents/encounters';
$encounterDir = $baseUploadDir . '/' . $encounterId;

if (!is_dir($encounterDir)) {
    if (!mkdir($encounterDir, 0755, true)) {
        error_log('Document Upload - Failed to create directory: ' . $encounterDir);
        ApiResponse::send(ApiResponse::error('Failed to create upload directory'), 500);
        exit;
    }
}

// Move uploaded file
$destinationPath = $encounterDir . '/' . $secureFileName;
if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
    error_log('Document Upload - Failed to move file to: ' . $destinationPath);
    ApiResponse::send(ApiResponse::error('Failed to save uploaded file'), 500);
    exit;
}

// Store relative path for database
$relativePath = 'uploads/documents/encounters/' . $encounterId . '/' . $secureFileName;

// Get PDO instance
try {
    $pdo = \App\db\pdo();
} catch (Exception $e) {
    // Clean up uploaded file on error
    @unlink($destinationPath);
    error_log('Document Upload - Database connection error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::error('Database connection error'), 500);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insert document record
    $sql = "INSERT INTO appointment_documents (
        encounter_id,
        file_name,
        original_name,
        file_path,
        file_type,
        file_size,
        document_type,
        notes,
        uploaded_by
    ) VALUES (
        :encounter_id,
        :file_name,
        :original_name,
        :file_path,
        :file_type,
        :file_size,
        :document_type,
        :notes,
        :uploaded_by
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':encounter_id' => $encounterId,
        ':file_name' => $secureFileName,
        ':original_name' => $originalName,
        ':file_path' => $relativePath,
        ':file_type' => $mimeType,
        ':file_size' => $file['size'],
        ':document_type' => $documentType,
        ':notes' => $notes,
        ':uploaded_by' => $userId
    ]);

    $documentId = $pdo->lastInsertId();

    // Log to audit trail
    logDocumentAudit($pdo, $userId, $encounterId, $documentId, 'uploaded');

    $pdo->commit();

    ApiResponse::send(ApiResponse::success([
        'document_id' => (int) $documentId,
        'encounter_id' => $encounterId,
        'file_name' => $secureFileName,
        'original_name' => $originalName,
        'file_type' => $mimeType,
        'file_size' => $file['size'],
        'document_type' => $documentType,
        'uploaded_at' => date('Y-m-d H:i:s')
    ], 'Document uploaded successfully'), 201);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Clean up uploaded file on error
    @unlink($destinationPath);
    error_log('Document Upload - Database error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::error('Database error while saving document'), 500);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Clean up uploaded file on error
    @unlink($destinationPath);
    error_log('Document Upload - Error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::error('Error uploading document'), 500);
}

/**
 * Log document action to audit trail
 *
 * @param PDO $pdo Database connection
 * @param int $userId User who performed the action
 * @param int $encounterId Associated encounter
 * @param int $documentId Document record ID
 * @param string $action Action performed
 */
function logDocumentAudit(PDO $pdo, int $userId, int $encounterId, int $documentId, string $action): void
{
    try {
        // Check if audit_logs table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
        if ($tableCheck->rowCount() === 0) {
            // Table doesn't exist, skip audit logging
            return;
        }

        $sql = "INSERT INTO audit_logs (
            user_id,
            action,
            entity_type,
            entity_id,
            details,
            ip_address,
            user_agent,
            created_at
        ) VALUES (
            :user_id,
            :action,
            'appointment_document',
            :entity_id,
            :details,
            :ip_address,
            :user_agent,
            NOW()
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => 'DOCUMENT_' . strtoupper($action),
            ':entity_id' => $documentId,
            ':details' => json_encode([
                'encounter_id' => $encounterId,
                'action' => $action
            ]),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the document operation
        error_log('Document Audit logging error: ' . $e->getMessage());
    }
}
