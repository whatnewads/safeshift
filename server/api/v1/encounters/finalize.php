<?php
/**
 * Encounter Finalize API Endpoint
 *
 * POST /api/v1/encounters/{id}/finalize
 *
 * Finalizes an encounter and triggers work-related notifications if applicable.
 * This endpoint:
 * 1. Validates the encounter exists and is in a valid state
 * 2. Updates encounter status to 'finalized'
 * 3. If work-related, sends email notifications to configured recipients
 * 4. Logs all email send attempts for audit trail
 *
 * @package API\v1\encounters
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../model/Core/Database.php';
require_once __DIR__ . '/../../../model/Validators/EncounterValidator.php';
require_once __DIR__ . '/../../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../../core/Services/EmailService.php';
require_once __DIR__ . '/../../../core/Services/EHRLogger.php';

use Model\Core\Database;
use Model\Validators\EncounterValidator;
use ViewModel\Core\ApiResponse;
use Core\Services\EmailService;
use Core\Services\EHRLogger;

/**
 * Handle encounter finalization request
 *
 * @param string $encounterId The encounter ID to finalize
 */
function handleFinalizeEncounter(string $encounterId): void
{
    $startTime = microtime(true);
    $ehrLogger = EHRLogger::getInstance();
    
    try {
        // Validate encounter ID
        if (empty($encounterId)) {
            $ehrLogger->logError('FINALIZE_ENCOUNTER', 'Missing encounter ID', [
                'channel' => EHRLogger::CHANNEL_FINALIZATION,
            ]);
            ApiResponse::send(ApiResponse::error('Encounter ID is required'), 400);
            return;
        }
        
        // Log finalization initiation
        $ehrLogger->logOperation(EHRLogger::OP_FINALIZE, [
            'encounter_id' => $encounterId,
            'details' => ['action' => 'finalization_initiated'],
            'result' => 'in_progress',
            'start_time' => $startTime,
        ], EHRLogger::CHANNEL_FINALIZATION);
        
        // Get database connection
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Get current user from session
        $userId = $_SESSION['user']['user_id'] ?? null;
        $clinicId = $_SESSION['user']['clinic_id'] ?? null;
        
        if (!$userId) {
            $ehrLogger->logError('FINALIZE_ENCOUNTER', 'Authentication required', [
                'channel' => EHRLogger::CHANNEL_FINALIZATION,
                'encounter_id' => $encounterId,
            ]);
            ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
            return;
        }
        
        // Get encounter with clinic info
        $encounter = getEncounterWithClinicInfo($pdo, $encounterId);
        
        if (!$encounter) {
            $ehrLogger->logError('FINALIZE_ENCOUNTER', 'Encounter not found', [
                'channel' => EHRLogger::CHANNEL_FINALIZATION,
                'encounter_id' => $encounterId,
            ]);
            ApiResponse::send(ApiResponse::notFound('Encounter not found'), 404);
            return;
        }
        
        // Check if encounter can be finalized
        $validStatuses = ['in_progress', 'pending_review', 'completed', 'signed'];
        $previousStatus = $encounter['status'];
        
        if (!in_array($encounter['status'], $validStatuses)) {
            $ehrLogger->logFinalization($encounterId, [
                'errors' => ["Invalid status for finalization: {$encounter['status']}"],
            ], false, [
                'start_time' => $startTime,
                'previous_status' => $previousStatus,
            ]);
            ApiResponse::send(
                ApiResponse::error("Cannot finalize encounter with status: {$encounter['status']}"),
                400
            );
            return;
        }
        
        // Check if already finalized
        if ($encounter['status'] === 'finalized') {
            $ehrLogger->logError('FINALIZE_ENCOUNTER', 'Encounter already finalized', [
                'channel' => EHRLogger::CHANNEL_FINALIZATION,
                'encounter_id' => $encounterId,
            ]);
            ApiResponse::send(ApiResponse::error('Encounter is already finalized'), 400);
            return;
        }
        
        // Get full encounter data for validation
        $encounterData = getFullEncounterData($pdo, $encounterId);
        
        // Validate encounter data before finalization
        $validationErrors = EncounterValidator::validateForFinalization($encounterData);
        if (!empty($validationErrors)) {
            $ehrLogger->logFinalization($encounterId, [
                'validation' => $validationErrors,
            ], false, [
                'start_time' => $startTime,
                'previous_status' => $previousStatus,
            ]);
            
            ApiResponse::send(ApiResponse::validationError(
                $validationErrors,
                'Cannot finalize: Please complete all required fields'
            ), 422);
            return;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Update encounter status to finalized
            $updateResult = updateEncounterStatus($pdo, $encounterId, 'finalized', $userId);
            
            if (!$updateResult) {
                throw new Exception('Failed to update encounter status');
            }
            
            // Log status transition
            $ehrLogger->logStatusTransition($encounterId, $previousStatus, 'finalized', 'finalization_complete');
            
            // Initialize response data
            $responseData = [
                'encounter_id' => $encounterId,
                'status' => 'finalized',
                'finalized_at' => date('c'),
                'finalized_by' => $userId,
                'emails_sent' => 0,
                'email_recipients' => []
            ];
            
            // Check if this is a work-related incident
            $isWorkRelated = $encounter['is_work_related'] == 1 ||
                             $encounter['injury_classification'] === 'work_related';
            
            if ($isWorkRelated) {
                // Get the clinic ID from encounter or session
                $encounterClinicId = $encounter['clinic_id'] ?? $clinicId;
                
                if ($encounterClinicId) {
                    // Send work-related notifications
                    $emailService = new EmailService();
                    $emailResult = $emailService->sendWorkRelatedIncidentNotification(
                        (int)$encounterId,
                        (int)$encounterClinicId,
                        [
                            'date' => $encounter['injury_date'] ?? $encounter['started_at'] ?? date('Y-m-d'),
                            'clinic_name' => $encounter['clinic_name'] ?? 'Unknown Clinic',
                            'city' => $encounter['clinic_city'] ?? '',
                            'state' => $encounter['clinic_state'] ?? '',
                            'location_description' => $encounter['injury_location'] ?? 'Not specified',
                            'narrative' => $encounter['injury_description'] ?? $encounter['chief_complaint'] ?? ''
                        ]
                    );
                    
                    // Add email results to response
                    if ($emailResult['success'] && isset($emailResult['data'])) {
                        $responseData['emails_sent'] = $emailResult['data']['sent_count'] ?? 0;
                        $responseData['email_recipients'] = $emailResult['data']['sent_to'] ?? [];
                        $responseData['email_status'] = 'sent';
                        
                        // Log successful email notification
                        $ehrLogger->logEmailNotification(
                            $encounterId,
                            $emailResult['data']['sent_to'] ?? [],
                            true
                        );
                    } else {
                        $responseData['email_status'] = 'failed';
                        $responseData['email_error'] = $emailResult['message'] ?? 'Unknown error';
                        
                        // Log failed email notification
                        $ehrLogger->logEmailNotification(
                            $encounterId,
                            [],
                            false,
                            $emailResult['message'] ?? 'Unknown error'
                        );
                    }
                } else {
                    $responseData['email_status'] = 'skipped';
                    $responseData['email_error'] = 'No clinic ID available';
                    
                    // Log skipped email
                    $ehrLogger->logEmailNotification(
                        $encounterId,
                        [],
                        false,
                        'No clinic ID available'
                    );
                }
            } else {
                $responseData['email_status'] = 'not_required';
                $responseData['email_reason'] = 'Encounter is not work-related';
            }
            
            // Log the finalization to database
            logEncounterFinalization($pdo, $encounterId, $userId, $responseData);
            
            // Commit transaction
            $pdo->commit();
            
            // Log successful finalization to EHR log
            $ehrLogger->logFinalization($encounterId, [
                'warnings' => [],
            ], true, [
                'start_time' => $startTime,
                'previous_status' => $previousStatus,
                'is_work_related' => $isWorkRelated,
            ]);
            
            // Send success response
            ApiResponse::send(ApiResponse::success($responseData, 'Encounter finalized successfully'), 200);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            
            // Log rollback
            $ehrLogger->logError('FINALIZE_ENCOUNTER', 'Transaction rolled back: ' . $e->getMessage(), [
                'channel' => EHRLogger::CHANNEL_FINALIZATION,
                'encounter_id' => $encounterId,
            ]);
            
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Finalize encounter error: " . $e->getMessage());
        
        // Log the failure
        $ehrLogger->logFinalization($encounterId ?? 'unknown', [
            'errors' => [$e->getMessage()],
        ], false, [
            'start_time' => $startTime,
        ]);
        
        ApiResponse::send(ApiResponse::serverError('Failed to finalize encounter'), 500);
    }
}

/**
 * Get encounter with clinic information
 * 
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter ID
 * @return array|null Encounter data or null if not found
 */
function getEncounterWithClinicInfo(\PDO $pdo, string $encounterId): ?array
{
    $sql = "SELECT 
                e.encounter_id,
                e.patient_id,
                e.status,
                e.is_work_related,
                e.injury_classification,
                e.injury_date,
                e.injury_location,
                e.injury_description,
                e.chief_complaint,
                e.started_at,
                e.clinic_id,
                c.name as clinic_name,
                c.city as clinic_city,
                c.state as clinic_state
            FROM encounters e
            LEFT JOIN clinics c ON e.clinic_id = c.id
            WHERE e.encounter_id = :encounter_id
            AND e.status != 'voided'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['encounter_id' => $encounterId]);
    
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    return $result ?: null;
}

/**
 * Update encounter status
 * 
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter ID
 * @param string $status New status
 * @param int $userId User making the change
 * @return bool Success status
 */
function updateEncounterStatus(\PDO $pdo, string $encounterId, string $status, int $userId): bool
{
    $sql = "UPDATE encounters 
            SET status = :status,
                ended_at = CASE WHEN :status = 'finalized' THEN NOW() ELSE ended_at END,
                updated_at = NOW(),
                updated_by = :user_id
            WHERE encounter_id = :encounter_id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'status' => $status,
        'encounter_id' => $encounterId,
        'user_id' => $userId
    ]);
}

/**
 * Get full encounter data for validation
 * Retrieves all form data in the structure expected by the validator
 *
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter ID
 * @return array Encounter data structured for validation
 */
function getFullEncounterData(\PDO $pdo, string $encounterId): array
{
    // Get main encounter data
    $sql = "SELECT * FROM encounters WHERE encounter_id = :encounter_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['encounter_id' => $encounterId]);
    $encounter = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    
    // Get patient data
    $patientId = $encounter['patient_id'] ?? null;
    $patient = [];
    if ($patientId) {
        $sql = "SELECT * FROM patients WHERE id = :patient_id OR uuid = :patient_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['patient_id' => $patientId]);
        $patient = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }
    
    // Get clinic data
    $clinicId = $encounter['clinic_id'] ?? null;
    $clinic = [];
    if ($clinicId) {
        $sql = "SELECT * FROM clinics WHERE id = :clinic_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['clinic_id' => $clinicId]);
        $clinic = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }
    
    // Get providers
    $providers = [];
    $sql = "SELECT ep.*, u.first_name, u.last_name
            FROM encounter_providers ep
            LEFT JOIN users u ON ep.provider_id = u.id
            WHERE ep.encounter_id = :encounter_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['encounter_id' => $encounterId]);
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $providers[] = [
            'id' => $row['provider_id'] ?? '',
            'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'role' => $row['role'] ?? 'secondary',
        ];
    }
    
    // Get assessments
    $assessments = [];
    $sql = "SELECT * FROM encounter_assessments WHERE encounter_id = :encounter_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['encounter_id' => $encounterId]);
    $assessments = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    
    // Get vitals
    $vitals = [];
    $sql = "SELECT * FROM encounter_vitals WHERE encounter_id = :encounter_id ORDER BY recorded_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['encounter_id' => $encounterId]);
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $vitals[] = [
            'time' => $row['recorded_time'] ?? $row['time'] ?? null,
            'date' => $row['recorded_date'] ?? $row['date'] ?? null,
            'avpu' => $row['avpu'] ?? null,
            'bp' => $row['blood_pressure'] ?? $row['bp'] ?? null,
            'bpTaken' => $row['bp_method'] ?? $row['bpTaken'] ?? null,
            'pulse' => $row['pulse'] ?? $row['heart_rate'] ?? null,
            'respiration' => $row['respiratory_rate'] ?? $row['respiration'] ?? null,
            'gcsTotal' => $row['gcs_total'] ?? $row['gcsTotal'] ?? null,
        ];
    }
    
    // Get disclosures
    $disclosures = [];
    $sql = "SELECT disclosure_type, acknowledged FROM encounter_disclosures WHERE encounter_id = :encounter_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['encounter_id' => $encounterId]);
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $disclosures[$row['disclosure_type']] = (bool)$row['acknowledged'];
    }
    
    // Return structured data matching client-side format
    return [
        'incidentForm' => [
            'clinicName' => $clinic['name'] ?? $encounter['clinic_name'] ?? null,
            'clinicStreetAddress' => $clinic['address'] ?? $clinic['street_address'] ?? $encounter['clinic_address'] ?? null,
            'clinicCity' => $clinic['city'] ?? $encounter['clinic_city'] ?? null,
            'clinicState' => $clinic['state'] ?? $encounter['clinic_state'] ?? null,
            'patientContactTime' => $encounter['patient_contact_time'] ?? $encounter['started_at'] ?? null,
            'clearedClinicTime' => $encounter['cleared_clinic_time'] ?? $encounter['ended_at'] ?? null,
            'location' => $encounter['injury_location'] ?? $encounter['location_of_injury'] ?? null,
            'injuryClassifiedByName' => $encounter['classified_by_name'] ?? $encounter['injury_classified_by_name'] ?? null,
            'injuryClassification' => $encounter['injury_classification'] ?? null,
        ],
        'patientForm' => [
            'firstName' => $patient['first_name'] ?? null,
            'lastName' => $patient['last_name'] ?? null,
            'dob' => $patient['date_of_birth'] ?? $patient['dob'] ?? null,
            'streetAddress' => $patient['address'] ?? $patient['street_address'] ?? null,
            'city' => $patient['city'] ?? null,
            'state' => $patient['state'] ?? null,
            'employer' => $patient['employer'] ?? null,
            'supervisorName' => $patient['supervisor_name'] ?? null,
            'supervisorPhone' => $patient['supervisor_phone'] ?? null,
            'medicalHistory' => $patient['medical_history'] ?? $encounter['medical_history'] ?? null,
            'allergies' => $patient['allergies'] ?? $encounter['allergies'] ?? null,
            'currentMedications' => $patient['current_medications'] ?? $encounter['current_medications'] ?? null,
        ],
        'providers' => $providers,
        'assessments' => $assessments,
        'vitals' => $vitals,
        'narrative' => $encounter['narrative'] ?? $encounter['clinical_narrative'] ?? null,
        'disclosures' => $disclosures,
    ];
}

/**
 * Log encounter finalization for audit trail
 *
 * @param PDO $pdo Database connection
 * @param string $encounterId Encounter ID
 * @param int $userId User who finalized
 * @param array $details Finalization details
 */
function logEncounterFinalization(\PDO $pdo, string $encounterId, int $userId, array $details): void
{
    try {
        $sql = "INSERT INTO audit_logs (
                    event_type,
                    table_name,
                    record_id,
                    user_id,
                    ip_address,
                    user_agent,
                    details,
                    created_at
                ) VALUES (
                    'encounter_finalized',
                    'encounters',
                    :record_id,
                    :user_id,
                    :ip_address,
                    :user_agent,
                    :details,
                    NOW()
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'record_id' => $encounterId,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => json_encode([
                'action' => 'finalize',
                'emails_sent' => $details['emails_sent'] ?? 0,
                'email_status' => $details['email_status'] ?? 'unknown'
            ])
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log("Failed to log encounter finalization: " . $e->getMessage());
    }
}

// If this file is called directly (not included), handle the request
if (basename($_SERVER['SCRIPT_FILENAME']) === 'finalize.php') {
    // Get encounter ID from URL path
    $path = $_SERVER['PATH_INFO'] ?? '';
    $segments = array_filter(explode('/', $path));
    $encounterId = reset($segments) ?: ($_GET['id'] ?? '');
    
    // Only allow POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        exit;
    }
    
    handleFinalizeEncounter($encounterId);
}
