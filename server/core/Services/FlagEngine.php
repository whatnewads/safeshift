<?php
/**
 * Flag Engine Service
 * 
 * Evaluates encounters against rules to automatically flag high-risk cases
 * Feature 2.1: High-Risk Call Flagging
 */

namespace Core\Services;

use PDO;
use Exception;

class FlagEngine extends BaseService
{    private $rules_cache = null;
    
    /**
     * Constructor
     * @param PDO $db Database connection
     */
    public function __construct(\PDO $db = null)
    {
        parent::__construct();
        $this->db = $db ?: \App\db\pdo();
    }
    
    /**
     * Evaluate an encounter for flags
     * 
     * @param string $encounter_id
     * @return array Flags created
     */
    public function evaluateEncounter(string $encounter_id): array
    {
        try {
            // Get encounter data with patient info
            $encounter = $this->fetchEncounterData($encounter_id);
            if (!$encounter) {
                throw new Exception("Encounter not found: $encounter_id");
            }
            
            // Get active flagging rules
            $rules = $this->getActiveRules();
            
            $flags_created = [];
            
            // Evaluate each rule
            foreach ($rules as $rule) {
                if ($this->matchesCondition($encounter, $rule['rule_condition'])) {
                    $flag_id = $this->createFlag([
                        'encounter_id' => $encounter_id,
                        'flag_type' => $rule['rule_type'],
                        'severity' => $rule['flag_severity'],
                        'flag_reason' => $rule['rule_name'],
                        'auto_flagged' => true
                    ]);
                    
                    if ($flag_id) {
                        $flags_created[] = [
                            'flag_id' => $flag_id,
                            'type' => $rule['rule_type'],
                            'severity' => $rule['flag_severity']
                        ];
                    }
                }
            }
            
            // Check for hard-coded critical conditions
            $critical_flags = $this->checkCriticalConditions($encounter);
            foreach ($critical_flags as $flag) {
                $flag_id = $this->createFlag($flag);
                if ($flag_id) {
                    $flags_created[] = [
                        'flag_id' => $flag_id,
                        'type' => $flag['flag_type'],
                        'severity' => $flag['severity']
                    ];
                }
            }
            
            return $flags_created;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to evaluate encounter for flags',
                'encounter_id' => $encounter_id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get active flagging rules
     * 
     * @return array
     */
    private function getActiveRules(): array
    {
        if ($this->rules_cache !== null) {
            return $this->rules_cache;
        }
        
        try {
            $stmt = $this->db->query("
                SELECT rule_id, rule_name, rule_type, rule_condition, flag_severity
                FROM flag_rules
                WHERE is_active = TRUE
                ORDER BY flag_severity DESC, created_at DESC
            ");
            
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON conditions
            foreach ($rules as &$rule) {
                $rule['rule_condition'] = json_decode($rule['rule_condition'], true);
            }
            
            $this->rules_cache = $rules;
            return $rules;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get active rules',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Check if encounter matches rule condition
     * 
     * @param array $encounter
     * @param array $condition
     * @return bool
     */
    private function matchesCondition(array $encounter, array $condition): bool
    {
        if (!is_array($condition)) {
            return false;
        }
        
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? null;
        
        // Get field value from encounter data
        $field_value = $this->getFieldValue($encounter, $field);
        
        // Evaluate condition based on operator
        switch ($operator) {
            case '>':
                return is_numeric($field_value) && $field_value > $value;
                
            case '>=':
                return is_numeric($field_value) && $field_value >= $value;
                
            case '<':
                return is_numeric($field_value) && $field_value < $value;
                
            case '<=':
                return is_numeric($field_value) && $field_value <= $value;
                
            case '==':
            case '=':
                return $field_value == $value;
                
            case '!=':
                return $field_value != $value;
                
            case 'contains':
                return stripos((string)$field_value, (string)$value) !== false;
                
            case 'not_contains':
                return stripos((string)$field_value, (string)$value) === false;
                
            case 'missing':
            case 'empty':
                return empty($field_value);
                
            case 'exists':
            case 'not_empty':
                return !empty($field_value);
                
            case 'in':
                return is_array($value) && in_array($field_value, $value);
                
            case 'not_in':
                return is_array($value) && !in_array($field_value, $value);
                
            case 'regex':
                return preg_match($value, (string)$field_value) === 1;
                
            case 'and':
                // Recursive AND conditions
                if (isset($condition['conditions']) && is_array($condition['conditions'])) {
                    foreach ($condition['conditions'] as $sub_condition) {
                        if (!$this->matchesCondition($encounter, $sub_condition)) {
                            return false;
                        }
                    }
                    return true;
                }
                return false;
                
            case 'or':
                // Recursive OR conditions
                if (isset($condition['conditions']) && is_array($condition['conditions'])) {
                    foreach ($condition['conditions'] as $sub_condition) {
                        if ($this->matchesCondition($encounter, $sub_condition)) {
                            return true;
                        }
                    }
                }
                return false;
                
            default:
                return false;
        }
    }
    
    /**
     * Get field value from encounter data (supports nested fields)
     * 
     * @param array $encounter
     * @param string $field
     * @return mixed
     */
    private function getFieldValue(array $encounter, string $field)
    {
        // Support dot notation for nested fields
        $keys = explode('.', $field);
        $value = $encounter;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Check for hard-coded critical conditions
     * 
     * @param array $encounter
     * @return array
     */
    private function checkCriticalConditions(array $encounter): array
    {
        $flags = [];
        
        // Critical vital signs
        $systolic_bp = $encounter['systolic_bp'] ?? 0;
        $diastolic_bp = $encounter['diastolic_bp'] ?? 0;
        $heart_rate = $encounter['heart_rate'] ?? 0;
        $spo2 = $encounter['spo2'] ?? 100;
        
        // Hypertensive crisis
        if ($systolic_bp >= 180 || $diastolic_bp >= 120) {
            $flags[] = [
                'encounter_id' => $encounter['encounter_id'],
                'flag_type' => 'vital_threshold',
                'severity' => 'critical',
                'flag_reason' => 'Hypertensive crisis: BP ' . $systolic_bp . '/' . $diastolic_bp,
                'auto_flagged' => true
            ];
        }
        
        // Hypotension
        if ($systolic_bp > 0 && $systolic_bp < 90) {
            $flags[] = [
                'encounter_id' => $encounter['encounter_id'],
                'flag_type' => 'vital_threshold',
                'severity' => 'high',
                'flag_reason' => 'Hypotension: Systolic BP ' . $systolic_bp,
                'auto_flagged' => true
            ];
        }
        
        // Tachycardia
        if ($heart_rate > 120) {
            $flags[] = [
                'encounter_id' => $encounter['encounter_id'],
                'flag_type' => 'vital_threshold',
                'severity' => 'high',
                'flag_reason' => 'Tachycardia: HR ' . $heart_rate,
                'auto_flagged' => true
            ];
        }
        
        // Bradycardia
        if ($heart_rate > 0 && $heart_rate < 50) {
            $flags[] = [
                'encounter_id' => $encounter['encounter_id'],
                'flag_type' => 'vital_threshold',
                'severity' => 'high',
                'flag_reason' => 'Bradycardia: HR ' . $heart_rate,
                'auto_flagged' => true
            ];
        }
        
        // Hypoxia
        if ($spo2 > 0 && $spo2 < 92) {
            $flags[] = [
                'encounter_id' => $encounter['encounter_id'],
                'flag_type' => 'vital_threshold',
                'severity' => 'critical',
                'flag_reason' => 'Hypoxia: SpO2 ' . $spo2 . '%',
                'auto_flagged' => true
            ];
        }
        
        // OSHA recordable injury indicators
        if ($this->isOSHARecordable($encounter)) {
            $flags[] = [
                'encounter_id' => $encounter['encounter_id'],
                'flag_type' => 'osha_recordable',
                'severity' => 'high',
                'flag_reason' => 'Potential OSHA recordable injury',
                'auto_flagged' => true
            ];
        }
        
        // Missing critical documentation
        if ($this->hasMissingDocumentation($encounter)) {
            $flags[] = [
                'encounter_id' => $encounter['encounter_id'],
                'flag_type' => 'protocol_deviation',
                'severity' => 'medium',
                'flag_reason' => 'Missing required documentation',
                'auto_flagged' => true
            ];
        }
        
        return $flags;
    }
    
    /**
     * Check if injury is OSHA recordable
     * 
     * @param array $encounter
     * @return bool
     */
    private function isOSHARecordable(array $encounter): bool
    {
        // Check for indicators of OSHA recordability
        $chief_complaint = strtolower($encounter['chief_complaint'] ?? '');
        $diagnosis = strtolower($encounter['diagnosis'] ?? '');
        $treatment = strtolower($encounter['treatment_notes'] ?? '');
        
        $recordable_keywords = [
            'hospitalization',
            'lost time',
            'restricted work',
            'loss of consciousness',
            'fracture',
            'amputation',
            'significant injury',
            'needle stick',
            'blood exposure'
        ];
        
        foreach ($recordable_keywords as $keyword) {
            if (stripos($chief_complaint, $keyword) !== false ||
                stripos($diagnosis, $keyword) !== false ||
                stripos($treatment, $keyword) !== false) {
                return true;
            }
        }
        
        // Check if sent to hospital
        if (!empty($encounter['referral_type']) && 
            stripos($encounter['referral_type'], 'hospital') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check for missing critical documentation
     * 
     * @param array $encounter
     * @return bool
     */
    private function hasMissingDocumentation(array $encounter): bool
    {
        $required_fields = [
            'chief_complaint',
            'vital_signs_recorded',
            'physical_exam_complete',
            'treatment_provided',
            'provider_signature'
        ];
        
        foreach ($required_fields as $field) {
            if (empty($encounter[$field])) {
                return true;
            }
        }
        
        // Check vital signs completeness
        if ($encounter['vital_signs_recorded'] ?? false) {
            $vital_fields = ['systolic_bp', 'diastolic_bp', 'heart_rate', 'respiratory_rate'];
            foreach ($vital_fields as $vital) {
                if (empty($encounter[$vital]) || $encounter[$vital] <= 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Create a flag
     * 
     * @param array $flag_data
     * @return string|null Flag ID
     */
    private function createFlag(array $flag_data): ?string
    {
        try {
            $flag_id = $this->generateUuid();
            
            $stmt = $this->db->prepare("
                INSERT INTO encounter_flags
                (flag_id, encounter_id, flag_type, severity, flag_reason,
                 auto_flagged, flagged_by, status, created_at, due_date)
                VALUES (:flag_id, :encounter_id, :flag_type, :severity, :flag_reason,
                        :auto_flagged, :flagged_by, 'pending', NOW(), :due_date)
            ");
            
            // Set due date based on severity
            $due_hours = [
                'critical' => 24,
                'high' => 48,
                'medium' => 72,
                'low' => 168  // 7 days
            ];
            
            $severity = $flag_data['severity'] ?? 'medium';
            $hours = $due_hours[$severity] ?? 72;
            $due_date = date('Y-m-d H:i:s', strtotime("+$hours hours"));
            
            $result = $stmt->execute([
                'flag_id' => $flag_id,
                'encounter_id' => $flag_data['encounter_id'],
                'flag_type' => $flag_data['flag_type'],
                'severity' => $severity,
                'flag_reason' => $flag_data['flag_reason'],
                'auto_flagged' => $flag_data['auto_flagged'] ?? false,
                'flagged_by' => $flag_data['flagged_by'] ?? null,
                'due_date' => $due_date
            ]);
            
            if ($result) {
                // Log flag creation
                \App\log\file_log('audit', [
                    'action' => 'flag_created',
                    'flag_id' => $flag_id,
                    'encounter_id' => $flag_data['encounter_id'],
                    'severity' => $severity,
                    'auto_flagged' => $flag_data['auto_flagged'] ?? false
                ]);
                
                return $flag_id;
            }
            
            return null;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to create flag',
                'error' => $e->getMessage(),
                'flag_data' => $flag_data
            ]);
            return null;
        }
    }
    
    /**
     * Fetch encounter data with related information
     * 
     * @param string $encounter_id
     * @return array|null
     */
    private function fetchEncounterData(string $encounter_id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, p.legal_first_name, p.legal_last_name, p.dob, p.sex_assigned_at_birth
                FROM encounters e
                LEFT JOIN patients p ON e.patient_id = p.patient_id
                WHERE e.encounter_id = :encounter_id
            ");
            
            $stmt->execute(['encounter_id' => $encounter_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to fetch encounter data',
                'encounter_id' => $encounter_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Generate UUID
     * 
     * @return string
     */
/**
     * Create a manual flag
     * 
     * @param string $encounter_id
     * @param array $flag_data
     * @param string $flagged_by User ID
     * @return string|null Flag ID
     */
    public function createManualFlag(string $encounter_id, array $flag_data, string $flagged_by): ?string
    {
        $flag_data['encounter_id'] = $encounter_id;
        $flag_data['flagged_by'] = $flagged_by;
        $flag_data['auto_flagged'] = false;
        
        return $this->createFlag($flag_data);
    }
    
    /**
     * Get flag statistics for dashboard
     * 
     * @return array
     */
    public function getFlagStatistics(): array
    {
        try {
            $stats = [];
            
            // Count by status
            $stmt = $this->db->query("
                SELECT status, COUNT(*) as count
                FROM encounter_flags
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY status
            ");
            $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Count by severity
            $stmt = $this->db->query("
                SELECT severity, COUNT(*) as count
                FROM encounter_flags
                WHERE status = 'pending'
                GROUP BY severity
            ");
            $stats['by_severity'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Average resolution time
            $stmt = $this->db->query("
                SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours
                FROM encounter_flags
                WHERE status = 'resolved'
                AND resolved_at IS NOT NULL
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stats['avg_resolution_hours'] = round($stmt->fetchColumn() ?: 0, 1);
            
            // Overdue flags
            $stmt = $this->db->query("
                SELECT COUNT(*) as count
                FROM encounter_flags
                WHERE status = 'pending'
                AND due_date < NOW()
            ");
            $stats['overdue_count'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            \App\log\file_log('error', [
                'message' => 'Failed to get flag statistics',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}