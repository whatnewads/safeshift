<?php
/**
 * Encounter Save Hook
 * Called after encounter is saved to evaluate flags
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Services/FlagEngine.php';

use Core\Services\FlagEngine;

/**
 * Evaluate encounter for flags after save
 * 
 * @param string $encounter_id
 * @return array Flags created
 */
function evaluateEncounterFlags($encounter_id) {
    try {
        $db = \App\db\pdo();
        $flag_engine = new FlagEngine($db);
        
        // Evaluate encounter
        $flags = $flag_engine->evaluateEncounter($encounter_id);
        
        // Log flag creation
        if (!empty($flags)) {
            \App\log\file_log('audit', [
                'action' => 'encounter_flags_evaluated',
                'encounter_id' => $encounter_id,
                'flags_created' => count($flags),
                'flags' => $flags
            ]);
        }
        
        return $flags;
        
    } catch (Exception $e) {
        \App\log\file_log('error', [
            'message' => 'Failed to evaluate encounter flags',
            'encounter_id' => $encounter_id,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

/**
 * Send notification for critical flags
 * 
 * @param array $flags
 * @param string $encounter_id
 */
function notifyCriticalFlags($flags, $encounter_id) {
    $critical_flags = array_filter($flags, function($flag) {
        return $flag['severity'] === 'critical';
    });
    
    if (empty($critical_flags)) {
        return;
    }
    
    try {
        $db = \App\db\pdo();
        
        // Get encounter details
        $stmt = $db->prepare("
            SELECT e.*, p.first_name, p.last_name, p.mrn, 
                   u.username as provider_name
            FROM encounters e
            JOIN patients p ON e.patient_id = p.patient_id
            JOIN user u ON e.provider_id = u.user_id
            WHERE e.encounter_id = ?
        ");
        $stmt->execute([$encounter_id]);
        $encounter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$encounter) {
            return;
        }
        
        // Get managers to notify
        $stmt = $db->query("
            SELECT email, username 
            FROM user 
            WHERE role IN ('tadmin', 'cadmin', 'pclinician') 
            AND status = 'active'
        ");
        $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Send notifications
        foreach ($managers as $manager) {
            $subject = 'CRITICAL: Encounter Flagged for Review';
            $body = "Critical flag(s) detected on encounter:\n\n";
            $body .= "Patient: {$encounter['first_name']} {$encounter['last_name']} (MRN: {$encounter['mrn']})\n";
            $body .= "Provider: {$encounter['provider_name']}\n";
            $body .= "Date: " . date('Y-m-d H:i', strtotime($encounter['created_at'])) . "\n\n";
            $body .= "Flags:\n";
            
            foreach ($critical_flags as $flag) {
                $body .= "- {$flag['flag_reason']}\n";
            }
            
            $body .= "\nPlease review immediately: " . SITE_URL . "/encounter/review/{$encounter_id}";
            
            // Use the email service to send notification
            // For now, log the notification
            \App\log\file_log('audit', [
                'action' => 'critical_flag_notification',
                'recipient' => $manager['email'],
                'encounter_id' => $encounter_id
            ]);
        }
        
    } catch (Exception $e) {
        \App\log\file_log('error', [
            'message' => 'Failed to send critical flag notifications',
            'error' => $e->getMessage()
        ]);
    }
}

// If called directly with encounter_id parameter
if (isset($_POST['encounter_id'])) {
    header('Content-Type: application/json');
    
    $encounter_id = $_POST['encounter_id'];
    $flags = evaluateEncounterFlags($encounter_id);
    
    // Send notifications for critical flags
    if (!empty($flags)) {
        notifyCriticalFlags($flags, $encounter_id);
    }
    
    echo json_encode([
        'success' => true,
        'flags_created' => count($flags),
        'flags' => $flags
    ]);
}