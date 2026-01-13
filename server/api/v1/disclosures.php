<?php

declare(strict_types=1);

/**
 * Disclosures API Endpoint
 *
 * Handles disclosure templates and acknowledgment records for patient signatures.
 * Routes:
 *   GET    /api/v1/disclosures/templates           - Get all active disclosure templates
 *   GET    /api/v1/disclosures/templates/{type}    - Get specific template by type
 *   GET    /api/v1/encounters/{id}/disclosures     - Get acknowledgments for an encounter
 *   POST   /api/v1/encounters/{id}/disclosures     - Record disclosure acknowledgment
 *
 * @package API\v1
 */

require_once __DIR__ . '/../../model/Core/Database.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../includes/ehr_logger.php';

use Model\Core\Database;
use ViewModel\Core\ApiResponse;

/**
 * Route handler called by api/v1/index.php
 * 
 * @param string $subPath The path after /api/v1/disclosures/
 * @param string $method The HTTP method
 */
function handleDisclosuresRoute(string $subPath, string $method): void
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
        
        // Route the request
        switch ($method) {
            case 'GET':
                handleDisclosureGetRequest($pdo, $segments);
                break;
                
            case 'POST':
                handleDisclosurePostRequest($pdo, $segments, $userId);
                break;
                
            default:
                ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        }
    } catch (\Exception $e) {
        error_log("Disclosures API error: " . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Internal server error'), 500);
    }
}

/**
 * Handle GET requests
 * 
 * @param PDO $pdo Database connection
 * @param array $segments Path segments
 */
function handleDisclosureGetRequest(\PDO $pdo, array $segments): void
{
    $action = $segments[0] ?? '';
    
    // GET /disclosures/templates - List all active templates
    if ($action === 'templates') {
        $type = $segments[1] ?? null;
        
        if ($type) {
            // GET /disclosures/templates/{type} - Get specific template
            getDisclosureTemplateByType($pdo, $type);
        } else {
            // GET /disclosures/templates - Get all active templates
            getAllDisclosureTemplates($pdo);
        }
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle POST requests
 * 
 * @param PDO $pdo Database connection
 * @param array $segments Path segments
 * @param string|null $userId Current user ID
 */
function handleDisclosurePostRequest(\PDO $pdo, array $segments, ?string $userId): void
{
    // POST requires authentication
    if (!$userId) {
        ApiResponse::send(ApiResponse::error('Authentication required'), 401);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Get all active disclosure templates
 * 
 * @param PDO $pdo Database connection
 */
function getAllDisclosureTemplates(\PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                disclosure_type,
                title,
                content,
                version,
                requires_work_related,
                display_order
            FROM disclosure_templates
            WHERE is_active = TRUE
            ORDER BY display_order ASC, id ASC
        ");
        $stmt->execute();
        $templates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Convert boolean fields
        $templates = array_map(function($template) {
            $template['requires_work_related'] = (bool) $template['requires_work_related'];
            $template['id'] = (int) $template['id'];
            $template['display_order'] = (int) $template['display_order'];
            return $template;
        }, $templates);
        
        ApiResponse::send(ApiResponse::success($templates, 'Disclosure templates retrieved'), 200);
    } catch (\PDOException $e) {
        error_log("Error fetching disclosure templates: " . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Failed to fetch disclosure templates'), 500);
    }
}

/**
 * Get a specific disclosure template by type
 * 
 * @param PDO $pdo Database connection
 * @param string $type Disclosure type
 */
function getDisclosureTemplateByType(\PDO $pdo, string $type): void
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                disclosure_type,
                title,
                content,
                version,
                requires_work_related,
                display_order
            FROM disclosure_templates
            WHERE disclosure_type = :type AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['type' => $type]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$template) {
            ApiResponse::send(ApiResponse::notFound("Disclosure template '{$type}' not found"), 404);
            return;
        }
        
        // Convert boolean fields
        $template['requires_work_related'] = (bool) $template['requires_work_related'];
        $template['id'] = (int) $template['id'];
        $template['display_order'] = (int) $template['display_order'];
        
        ApiResponse::send(ApiResponse::success($template, 'Disclosure template retrieved'), 200);
    } catch (\PDOException $e) {
        error_log("Error fetching disclosure template: " . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Failed to fetch disclosure template'), 500);
    }
}

/**
 * Get encounter disclosures (acknowledgments)
 * Called from encounters.php for GET /api/v1/encounters/{id}/disclosures
 * 
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter ID
 */
function getEncounterDisclosures(\PDO $pdo, string $encounterId): void
{
    try {
        // Validate encounter exists
        $checkStmt = $pdo->prepare("SELECT encounter_id FROM encounters WHERE encounter_id = :id");
        $checkStmt->execute(['id' => $encounterId]);
        if (!$checkStmt->fetch()) {
            ApiResponse::send(ApiResponse::notFound('Encounter not found'), 404);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                encounter_id,
                disclosure_type,
                disclosure_version,
                disclosure_text,
                acknowledged_at,
                acknowledged_by_patient,
                ip_address
            FROM encounter_disclosures
            WHERE encounter_id = :encounter_id
            ORDER BY acknowledged_at ASC
        ");
        $stmt->execute(['encounter_id' => $encounterId]);
        $disclosures = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Convert types
        $disclosures = array_map(function($disclosure) {
            $disclosure['id'] = (int) $disclosure['id'];
            $disclosure['acknowledged_by_patient'] = (bool) $disclosure['acknowledged_by_patient'];
            return $disclosure;
        }, $disclosures);
        
        ApiResponse::send(ApiResponse::success($disclosures, 'Encounter disclosures retrieved'), 200);
    } catch (\PDOException $e) {
        error_log("Error fetching encounter disclosures: " . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Failed to fetch encounter disclosures'), 500);
    }
}

/**
 * Record a disclosure acknowledgment
 * Called from encounters.php for POST /api/v1/encounters/{id}/disclosures
 *
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter ID
 * @param array $data Request data
 */
function recordDisclosureAcknowledgment(\PDO $pdo, string $encounterId, array $data): void
{
    $userId = $_SESSION['user']['user_id'] ?? null;
    
    try {
        // Validate required fields
        $requiredFields = ['disclosure_type', 'disclosure_text', 'disclosure_version'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                // Log validation failure
                logEhrError($encounterId, $userId, 'DISCLOSURE_ACK', "Missing required field: {$field}");
                ApiResponse::send(ApiResponse::error("Missing required field: {$field}"), 400);
                return;
            }
        }
        
        // Validate encounter exists
        $checkStmt = $pdo->prepare("SELECT encounter_id FROM encounters WHERE encounter_id = :id");
        $checkStmt->execute(['id' => $encounterId]);
        if (!$checkStmt->fetch()) {
            logEhrError($encounterId, $userId, 'DISCLOSURE_ACK', 'Encounter not found');
            ApiResponse::send(ApiResponse::notFound('Encounter not found'), 404);
            return;
        }
        
        // Validate disclosure type
        $validTypes = ['general_consent', 'privacy_practices', 'work_related_auth', 'hipaa_acknowledgment'];
        if (!in_array($data['disclosure_type'], $validTypes)) {
            logEhrError($encounterId, $userId, 'DISCLOSURE_ACK', "Invalid disclosure type: {$data['disclosure_type']}");
            ApiResponse::send(ApiResponse::error('Invalid disclosure type'), 400);
            return;
        }
        
        // Check if already acknowledged
        $existingStmt = $pdo->prepare("
            SELECT id FROM encounter_disclosures
            WHERE encounter_id = :encounter_id AND disclosure_type = :disclosure_type
        ");
        $existingStmt->execute([
            'encounter_id' => $encounterId,
            'disclosure_type' => $data['disclosure_type']
        ]);
        
        if ($existingStmt->fetch()) {
            logEhrSubmission($encounterId, $userId, 'DISCLOSURE_ACK', 'WARNING',
                "Disclosure already acknowledged: {$data['disclosure_type']}");
            ApiResponse::send(ApiResponse::error('Disclosure already acknowledged for this encounter'), 409);
            return;
        }
        
        // Get client info for audit trail
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Record acknowledgment
        $stmt = $pdo->prepare("
            INSERT INTO encounter_disclosures (
                encounter_id,
                disclosure_type,
                disclosure_version,
                disclosure_text,
                acknowledged_at,
                acknowledged_by_patient,
                ip_address,
                user_agent
            ) VALUES (
                :encounter_id,
                :disclosure_type,
                :disclosure_version,
                :disclosure_text,
                NOW(),
                :acknowledged_by_patient,
                :ip_address,
                :user_agent
            )
        ");
        
        $stmt->execute([
            'encounter_id' => $encounterId,
            'disclosure_type' => $data['disclosure_type'],
            'disclosure_version' => $data['disclosure_version'],
            'disclosure_text' => $data['disclosure_text'],
            'acknowledged_by_patient' => $data['acknowledged_by_patient'] ?? true,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
        
        $acknowledgmentId = $pdo->lastInsertId();
        
        // Log the acknowledgment using EHR logger
        logDisclosureAcknowledgment($encounterId, $userId, $data['disclosure_type'], 'SUCCESS');
        
        ApiResponse::send(ApiResponse::success([
            'id' => (int) $acknowledgmentId,
            'encounter_id' => $encounterId,
            'disclosure_type' => $data['disclosure_type'],
            'acknowledged_at' => date('Y-m-d H:i:s')
        ], 'Disclosure acknowledgment recorded'), 201);
        
    } catch (\PDOException $e) {
        // Log the error
        logEhrError($encounterId, $userId, 'DISCLOSURE_ACK', $e->getMessage(), $e->getTraceAsString());
        ApiResponse::send(ApiResponse::serverError('Failed to record disclosure acknowledgment'), 500);
    }
}

/**
 * Record multiple disclosure acknowledgments at once
 * Called from encounters.php for POST /api/v1/encounters/{id}/disclosures/batch
 *
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter ID
 * @param array $disclosures Array of disclosure acknowledgments
 */
function recordBatchDisclosureAcknowledgments(\PDO $pdo, string $encounterId, array $disclosures): void
{
    $userId = $_SESSION['user']['user_id'] ?? null;
    
    try {
        // Validate encounter exists
        $checkStmt = $pdo->prepare("SELECT encounter_id FROM encounters WHERE encounter_id = :id");
        $checkStmt->execute(['id' => $encounterId]);
        if (!$checkStmt->fetch()) {
            logEhrError($encounterId, $userId, 'BATCH_DISCLOSURE_ACK', 'Encounter not found');
            ApiResponse::send(ApiResponse::notFound('Encounter not found'), 404);
            return;
        }
        
        if (empty($disclosures)) {
            logEhrError($encounterId, $userId, 'BATCH_DISCLOSURE_ACK', 'No disclosures provided');
            ApiResponse::send(ApiResponse::error('No disclosures provided'), 400);
            return;
        }
        
        // Get client info for audit trail
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $pdo->beginTransaction();
        
        $recorded = [];
        $validTypes = ['general_consent', 'privacy_practices', 'work_related_auth', 'hipaa_acknowledgment'];
        
        $insertStmt = $pdo->prepare("
            INSERT INTO encounter_disclosures (
                encounter_id,
                disclosure_type,
                disclosure_version,
                disclosure_text,
                acknowledged_at,
                acknowledged_by_patient,
                ip_address,
                user_agent
            ) VALUES (
                :encounter_id,
                :disclosure_type,
                :disclosure_version,
                :disclosure_text,
                NOW(),
                :acknowledged_by_patient,
                :ip_address,
                :user_agent
            )
        ");
        
        foreach ($disclosures as $disclosure) {
            // Validate required fields
            if (empty($disclosure['disclosure_type']) ||
                empty($disclosure['disclosure_text']) ||
                empty($disclosure['disclosure_version'])) {
                $pdo->rollBack();
                logEhrError($encounterId, $userId, 'BATCH_DISCLOSURE_ACK',
                    'Each disclosure must have type, text, and version');
                ApiResponse::send(ApiResponse::error('Each disclosure must have type, text, and version'), 400);
                return;
            }
            
            // Validate disclosure type
            if (!in_array($disclosure['disclosure_type'], $validTypes)) {
                $pdo->rollBack();
                logEhrError($encounterId, $userId, 'BATCH_DISCLOSURE_ACK',
                    "Invalid disclosure type: {$disclosure['disclosure_type']}");
                ApiResponse::send(ApiResponse::error("Invalid disclosure type: {$disclosure['disclosure_type']}"), 400);
                return;
            }
            
            $insertStmt->execute([
                'encounter_id' => $encounterId,
                'disclosure_type' => $disclosure['disclosure_type'],
                'disclosure_version' => $disclosure['disclosure_version'],
                'disclosure_text' => $disclosure['disclosure_text'],
                'acknowledged_by_patient' => $disclosure['acknowledged_by_patient'] ?? true,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent
            ]);
            
            $recorded[] = [
                'id' => (int) $pdo->lastInsertId(),
                'disclosure_type' => $disclosure['disclosure_type']
            ];
        }
        
        $pdo->commit();
        
        // Log each disclosure acknowledgment using EHR logger
        foreach ($recorded as $record) {
            logDisclosureAcknowledgment($encounterId, $userId, $record['disclosure_type'], 'SUCCESS');
        }
        
        // Log batch summary
        $types = implode(', ', array_column($recorded, 'disclosure_type'));
        logEhrSubmission($encounterId, $userId, 'BATCH_DISCLOSURE_ACK', 'SUCCESS', [
            'disclosure_types' => $types,
            'count' => count($recorded),
        ]);
        
        ApiResponse::send(ApiResponse::success([
            'recorded' => $recorded,
            'count' => count($recorded),
            'encounter_id' => $encounterId
        ], 'Disclosure acknowledgments recorded'), 201);
        
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logEhrError($encounterId, $userId, 'BATCH_DISCLOSURE_ACK', $e->getMessage(), $e->getTraceAsString());
        ApiResponse::send(ApiResponse::serverError('Failed to record disclosure acknowledgments'), 500);
    }
}
