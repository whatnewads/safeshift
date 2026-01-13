<?php
/**
 * EHR Field-Level Logger
 * Logs each data field mapping to database with success/failure status
 * 
 * This logger provides detailed tracking of field-level operations during
 * encounter creation, updates, and submissions. It's designed to be HIPAA
 * compliant by not logging actual PHI values, only metadata about the fields.
 * 
 * @package SafeShift\Includes
 */

/**
 * Log detailed field-level operation for EHR encounters
 * 
 * @param string $operation Operation type: 'create', 'update', 'submit', 'sign', 'amend'
 * @param string $encounterId The encounter UUID
 * @param array $fieldMappings Array of field mappings: ['field_name' => ['value' => $val, 'db_column' => 'col', 'status' => 'success/failed', 'error' => null]]
 * @param string|null $userId The user performing the operation
 * @param string $overallStatus Overall status: 'pending', 'success', 'partial', 'failed'
 * @return void
 */
function logEhrFieldOperation(
    string $operation,
    string $encounterId,
    array $fieldMappings,
    ?string $userId = null,
    string $overallStatus = 'pending'
): void {
    $logDir = __DIR__ . '/../logs';
    
    // Ensure logs directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/ehr_field_mapping_' . date('Y-m-d') . '.log';
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'operation' => $operation,
        'encounter_id' => $encounterId,
        'user_id' => $userId ?? ($_SESSION['user']['user_id'] ?? 'N/A'),
        'overall_status' => $overallStatus,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 100),
        'request_id' => uniqid('ehr_', true),
        'fields' => []
    ];
    
    $successCount = 0;
    $failedCount = 0;
    $failedFields = [];
    
    foreach ($fieldMappings as $fieldName => $fieldData) {
        $fieldStatus = $fieldData['status'] ?? 'unknown';
        
        $logEntry['fields'][$fieldName] = [
            'db_column' => $fieldData['db_column'] ?? $fieldName,
            'status' => $fieldStatus,
            'error' => $fieldData['error'] ?? null,
            // Don't log actual values for HIPAA compliance - only indicate if value was present
            'has_value' => !empty($fieldData['value']),
            'data_type' => isset($fieldData['value']) ? gettype($fieldData['value']) : 'null',
        ];
        
        if ($fieldStatus === 'success') {
            $successCount++;
        } elseif ($fieldStatus === 'failed') {
            $failedCount++;
            $failedFields[] = $fieldName;
        }
    }
    
    $logEntry['summary'] = [
        'total_fields' => count($fieldMappings),
        'success_count' => $successCount,
        'failed_count' => $failedCount,
        'failed_fields' => $failedFields,
    ];
    
    $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Log a summary of an EHR operation for quick overview
 * 
 * @param string $operation Operation type
 * @param string $encounterId Encounter UUID
 * @param string $status Overall status
 * @param int $successCount Number of fields successfully processed
 * @param int $failedCount Number of fields that failed
 * @param array $failedFields List of field names that failed
 * @return void
 */
function logEhrOperationSummary(
    string $operation,
    string $encounterId,
    string $status,
    int $successCount,
    int $failedCount,
    array $failedFields = []
): void {
    $logDir = __DIR__ . '/../logs';
    
    // Ensure logs directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/ehr_operations_summary_' . date('Y-m-d') . '.log';
    
    $logLine = sprintf(
        "[%s] [%s] [%s] [%s] User: %s | Success: %d, Failed: %d%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($operation),
        $encounterId,
        strtoupper($status),
        $_SESSION['user']['user_id'] ?? 'N/A',
        $successCount,
        $failedCount,
        $failedCount > 0 ? " (" . implode(', ', $failedFields) . ")" : ""
    );
    
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Create a field mapping entry for logging
 * 
 * @param mixed $value The field value (will not be stored, only metadata)
 * @param string $dbColumn The database column name
 * @param string $status Status: 'success', 'failed', 'skipped'
 * @param string|null $error Error message if failed
 * @return array Field mapping array
 */
function createFieldMapping($value, string $dbColumn, string $status = 'success', ?string $error = null): array
{
    return [
        'value' => $value,
        'db_column' => $dbColumn,
        'status' => $status,
        'error' => $error,
    ];
}

/**
 * Log encounter creation with field details
 * 
 * @param string $encounterId The new encounter ID
 * @param array $data The encounter data (fields will be mapped)
 * @param bool $success Whether the operation succeeded
 * @param array $errors Any errors that occurred
 * @return void
 */
function logEncounterCreationFields(string $encounterId, array $data, bool $success, array $errors = []): void
{
    $fieldMappings = [];
    
    // Map encounter fields to database columns
    $fieldMap = [
        'patient_id' => 'patient_id',
        'patientId' => 'patient_id',
        'provider_id' => 'provider_id',
        'providerId' => 'provider_id',
        'clinic_id' => 'clinic_id',
        'clinicId' => 'clinic_id',
        'encounter_type' => 'encounter_type',
        'encounterType' => 'encounter_type',
        'chief_complaint' => 'chief_complaint',
        'chiefComplaint' => 'chief_complaint',
        'encounter_date' => 'encounter_date',
        'encounterDate' => 'encounter_date',
        'status' => 'status',
        'notes' => 'notes',
        'is_work_related' => 'is_work_related',
        'isWorkRelated' => 'is_work_related',
        'employer_id' => 'employer_id',
        'employerId' => 'employer_id',
    ];
    
    foreach ($data as $fieldName => $value) {
        $dbColumn = $fieldMap[$fieldName] ?? $fieldName;
        $fieldError = $errors[$fieldName] ?? null;
        $status = $fieldError ? 'failed' : ($success ? 'success' : 'unknown');
        
        $fieldMappings[$fieldName] = createFieldMapping($value, $dbColumn, $status, $fieldError);
    }
    
    $overallStatus = $success ? 'success' : (empty($errors) ? 'failed' : 'partial');
    
    logEhrFieldOperation('create', $encounterId, $fieldMappings, null, $overallStatus);
    logEhrOperationSummary(
        'create',
        $encounterId,
        $overallStatus,
        count(array_filter($fieldMappings, fn($f) => $f['status'] === 'success')),
        count(array_filter($fieldMappings, fn($f) => $f['status'] === 'failed')),
        array_keys(array_filter($fieldMappings, fn($f) => $f['status'] === 'failed'))
    );
}

/**
 * Log encounter update with field details
 * 
 * @param string $encounterId The encounter ID being updated
 * @param array $data The update data
 * @param bool $success Whether the operation succeeded
 * @param array $errors Any errors that occurred
 * @return void
 */
function logEncounterUpdateFields(string $encounterId, array $data, bool $success, array $errors = []): void
{
    $fieldMappings = [];
    
    foreach ($data as $fieldName => $value) {
        $fieldError = $errors[$fieldName] ?? null;
        $status = $fieldError ? 'failed' : ($success ? 'success' : 'unknown');
        
        $fieldMappings[$fieldName] = createFieldMapping($value, $fieldName, $status, $fieldError);
    }
    
    $overallStatus = $success ? 'success' : (empty($errors) ? 'failed' : 'partial');
    
    logEhrFieldOperation('update', $encounterId, $fieldMappings, null, $overallStatus);
    logEhrOperationSummary(
        'update',
        $encounterId,
        $overallStatus,
        count(array_filter($fieldMappings, fn($f) => $f['status'] === 'success')),
        count(array_filter($fieldMappings, fn($f) => $f['status'] === 'failed')),
        array_keys(array_filter($fieldMappings, fn($f) => $f['status'] === 'failed'))
    );
}

/**
 * Log encounter submission with validation results
 * 
 * @param string $encounterId The encounter ID being submitted
 * @param array $validationResults Validation results per field
 * @param bool $success Whether submission succeeded
 * @return void
 */
function logEncounterSubmissionFields(string $encounterId, array $validationResults, bool $success): void
{
    $fieldMappings = [];
    $failedFields = [];
    
    foreach ($validationResults as $fieldName => $result) {
        $status = is_bool($result) ? ($result ? 'success' : 'failed') : 'failed';
        $error = is_string($result) ? $result : null;
        
        $fieldMappings[$fieldName] = [
            'value' => null, // Don't store values
            'db_column' => $fieldName,
            'status' => $status,
            'error' => $error,
        ];
        
        if ($status === 'failed') {
            $failedFields[] = $fieldName;
        }
    }
    
    $overallStatus = $success ? 'success' : 'failed';
    
    logEhrFieldOperation('submit', $encounterId, $fieldMappings, null, $overallStatus);
    logEhrOperationSummary(
        'submit',
        $encounterId,
        $overallStatus,
        count($validationResults) - count($failedFields),
        count($failedFields),
        $failedFields
    );
}

/**
 * Log vitals recording with field details
 * 
 * @param string $encounterId The encounter ID
 * @param array $vitalsData The vitals data
 * @param bool $success Whether recording succeeded
 * @param array $errors Any errors
 * @return void
 */
function logVitalsFields(string $encounterId, array $vitalsData, bool $success, array $errors = []): void
{
    $fieldMappings = [];
    
    // Vitals field mapping
    $vitalsFields = [
        'blood_pressure_systolic' => 'bp_systolic',
        'blood_pressure_diastolic' => 'bp_diastolic',
        'heart_rate' => 'heart_rate',
        'respiratory_rate' => 'respiratory_rate',
        'temperature' => 'temperature',
        'oxygen_saturation' => 'o2_saturation',
        'weight' => 'weight',
        'height' => 'height',
        'bmi' => 'bmi',
        'pain_level' => 'pain_level',
    ];
    
    foreach ($vitalsData as $fieldName => $value) {
        $dbColumn = $vitalsFields[$fieldName] ?? $fieldName;
        $fieldError = $errors[$fieldName] ?? null;
        $status = $fieldError ? 'failed' : ($success ? 'success' : 'unknown');
        
        $fieldMappings[$fieldName] = createFieldMapping($value, $dbColumn, $status, $fieldError);
    }
    
    $overallStatus = $success ? 'success' : 'failed';
    
    logEhrFieldOperation('vitals', $encounterId, $fieldMappings, null, $overallStatus);
    logEhrOperationSummary(
        'vitals',
        $encounterId,
        $overallStatus,
        count(array_filter($fieldMappings, fn($f) => $f['status'] === 'success')),
        count(array_filter($fieldMappings, fn($f) => $f['status'] === 'failed')),
        array_keys(array_filter($fieldMappings, fn($f) => $f['status'] === 'failed'))
    );
}
