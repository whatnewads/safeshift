<?php
/**
 * App\log Namespace Functions
 * Located at: /includes/log_functions.php
 * 
 * Backward compatibility functions for \App\log namespace.
 * These wrap the new LogService to maintain compatibility with existing code.
 * 
 * Usage:
 *   \App\log\file_log('channel', ['key' => 'value']);
 *   \App\log\audit('ACTION', 'SubjectType', $subjectId, ['details']);
 *   \App\log\security_event('EVENT_TYPE', ['details']);
 */

namespace App\log;

/**
 * Log a message to file with channel support
 * 
 * @param string $channel Log channel/type (e.g., 'request', 'error', 'redirect')
 * @param array $context Additional context data
 * @return bool Success status
 */
function file_log($channel, array $context = []) {
    $logger = $GLOBALS['logger'] ?? null;
    if ($logger) {
        return $logger->info($channel, $context, $channel);
    }
    // Fallback to error_log if logger not available
    error_log("[{$channel}] " . json_encode($context));
    return true;
}

/**
 * Log an audit event for HIPAA compliance
 * 
 * @param string $action Action performed (e.g., 'LOGIN_REDIRECT', 'UNAUTHORIZED_ACCESS')
 * @param string $subjectType Type of subject (e.g., 'User', 'Patient', 'Encounter')
 * @param string|int|null $subjectId ID of the subject
 * @param array $details Additional details
 * @return bool Success status
 */
function audit($action, $subjectType, $subjectId = null, array $details = []) {
    $logger = $GLOBALS['logger'] ?? null;
    if ($logger) {
        return $logger->audit($action, $subjectType, $subjectId, $details);
    }
    // Fallback to error_log if logger not available
    error_log("[AUDIT] {$action} on {$subjectType}/{$subjectId}: " . json_encode($details));
    return true;
}

/**
 * Log a security event
 * 
 * @param string $eventType Type of security event (e.g., 'CSRF_ATTACK', 'BRUTE_FORCE')
 * @param array $details Event details
 * @return bool Success status
 */
function security_event($eventType, array $details = []) {
    $logger = $GLOBALS['logger'] ?? null;
    if ($logger) {
        return $logger->security($eventType, "Security event: {$eventType}", $details);
    }
    // Fallback to error_log if logger not available
    error_log("[SECURITY] {$eventType}: " . json_encode($details));
    return true;
}