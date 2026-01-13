<?php
/**
 * Observation Repository
 * 
 * Handles all observation/vital signs database operations
 * HIPAA-compliant vital signs data access layer
 */

namespace Core\Repositories;

use Core\Entities\Vital;
use PDO;
use Exception;

class ObservationRepository
{
    private PDO $pdo;
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Find observation by ID
     */
    public function findById(string $observationId): ?Vital
    {
        $sql = "SELECT 
                    observation_id,
                    encounter_id,
                    patient_id,
                    code,
                    value,
                    units,
                    reference_range,
                    abnormal_flag,
                    observed_at,
                    observed_by,
                    method,
                    body_site,
                    device_id,
                    notes,
                    created_at,
                    updated_at,
                    created_by,
                    updated_by
                FROM observation
                WHERE observation_id = :observation_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['observation_id' => $observationId]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return new Vital($data);
    }
    
    /**
     * Get vitals by patient ID
     */
    public function getVitalsByPatientId(string $patientId, int $days = 30): array
    {
        $sql = "SELECT 
                    o.observation_id,
                    o.encounter_id,
                    o.patient_id,
                    o.code,
                    o.value,
                    o.units,
                    o.reference_range,
                    o.abnormal_flag,
                    o.observed_at,
                    o.observed_by,
                    o.method,
                    o.body_site,
                    o.device_id,
                    o.notes,
                    o.created_at,
                    o.updated_at,
                    o.created_by,
                    o.updated_by,
                    e.started_at as encounter_date,
                    e.encounter_type
                FROM observation o
                INNER JOIN encounters e ON o.encounter_id = e.encounter_id
                WHERE e.patient_id = :patient_id
                AND o.code IN ('bp_systolic', 'bp_diastolic', 'pulse', 'temperature', 
                              'spo2', 'respiration', 'blood_sugar', 'pain_scale', 
                              'weight', 'height')
                AND o.observed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY o.observed_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $data;
        }
        
        return $results;
    }
    
    /**
     * Get vitals by encounter ID
     */
    public function getVitalsByEncounterId(string $encounterId): array
    {
        $sql = "SELECT * FROM observation
                WHERE encounter_id = :encounter_id
                ORDER BY observed_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Vital($data);
        }
        
        return $results;
    }
    
    /**
     * Get latest vitals for patient
     */
    public function getLatestVitals(string $patientId): array
    {
        $vitalCodes = [
            'bp_systolic', 'bp_diastolic', 'pulse', 'temperature',
            'spo2', 'respiration', 'blood_sugar', 'pain_scale',
            'weight', 'height'
        ];
        
        $results = [];
        
        foreach ($vitalCodes as $code) {
            $sql = "SELECT * FROM observation o
                    INNER JOIN encounters e ON o.encounter_id = e.encounter_id
                    WHERE e.patient_id = :patient_id
                    AND o.code = :code
                    ORDER BY o.observed_at DESC
                    LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'patient_id' => $patientId,
                'code' => $code
            ]);
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                $results[$code] = new Vital($data);
            }
        }
        
        return $results;
    }
    
    /**
     * Create new observation
     */
    public function create(Vital $vital, string $createdBy): Vital
    {
        $observationId = $this->generateUuid();
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO observation (
                    observation_id, encounter_id, patient_id, code,
                    value, units, reference_range, abnormal_flag,
                    observed_at, observed_by, method, body_site,
                    device_id, notes, created_at, created_by
                ) VALUES (
                    :observation_id, :encounter_id, :patient_id, :code,
                    :value, :units, :reference_range, :abnormal_flag,
                    :observed_at, :observed_by, :method, :body_site,
                    :device_id, :notes, :created_at, :created_by
                )";
        
        $vital->setObservationId($observationId);
        $vital->setCreatedAt($now);
        if (!$vital->getObservedAt()) {
            $vital->setObservedAt($now);
        }
        
        $data = $vital->toArray();
        $data['created_by'] = $createdBy;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $vital;
    }
    
    /**
     * Create multiple observations
     */
    public function createBatch(array $vitals, string $encounterId, string $patientId, string $createdBy): array
    {
        $created = [];
        
        foreach ($vitals as $vitalData) {
            $vital = new Vital($vitalData);
            $vital->setEncounterId($encounterId);
            $vital->setPatientId($patientId);
            
            $created[] = $this->create($vital, $createdBy);
        }
        
        return $created;
    }
    
    /**
     * Update observation
     */
    public function update(Vital $vital, string $updatedBy): bool
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "UPDATE observation SET
                    code = :code,
                    value = :value,
                    units = :units,
                    reference_range = :reference_range,
                    abnormal_flag = :abnormal_flag,
                    observed_at = :observed_at,
                    observed_by = :observed_by,
                    method = :method,
                    body_site = :body_site,
                    device_id = :device_id,
                    notes = :notes,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE observation_id = :observation_id";
        
        $vital->setUpdatedAt($now);
        
        $data = $vital->toArray();
        $data['updated_by'] = $updatedBy;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * Delete observation
     */
    public function delete(string $observationId): bool
    {
        $sql = "DELETE FROM observation WHERE observation_id = :observation_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['observation_id' => $observationId]);
    }
    
    /**
     * Get vital trends for specific vital type
     */
    public function getVitalTrend(string $patientId, string $vitalCode, int $days = 30, int $limit = 50): array
    {
        $sql = "SELECT 
                    o.value,
                    o.units,
                    o.observed_at,
                    o.abnormal_flag,
                    e.encounter_type
                FROM observation o
                INNER JOIN encounters e ON o.encounter_id = e.encounter_id
                WHERE e.patient_id = :patient_id
                AND o.code = :code
                AND o.observed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY o.observed_at ASC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $stmt->bindValue(':code', $vitalCode, PDO::PARAM_STR);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert vitals from ePCR save
     */
    public function insertEpcrVitals(string $encounterId, string $patientId, array $vitalData, string $userId): void
    {
        $now = date('Y-m-d H:i:s');
        
        // Handle blood pressure
        if (!empty($vitalData['blood_pressure']) && preg_match('/^(\d+)\/(\d+)$/', $vitalData['blood_pressure'], $matches)) {
            $this->insertVital($encounterId, $patientId, 'bp_systolic', $matches[1], 'mmHg', $now, $userId);
            $this->insertVital($encounterId, $patientId, 'bp_diastolic', $matches[2], 'mmHg', $now, $userId);
        }
        
        // Handle other vitals
        $vitalMappings = [
            'pulse' => ['code' => 'pulse', 'units' => 'bpm'],
            'respiration' => ['code' => 'respiration', 'units' => 'breaths/min'],
            'temperature' => ['code' => 'temperature', 'units' => 'F'],
            'spo2' => ['code' => 'spo2', 'units' => '%'],
            'pain_scale' => ['code' => 'pain_scale', 'units' => ''],
            'blood_sugar' => ['code' => 'blood_sugar', 'units' => 'mg/dL']
        ];
        
        foreach ($vitalMappings as $field => $mapping) {
            if (!empty($vitalData[$field])) {
                $this->insertVital(
                    $encounterId, 
                    $patientId, 
                    $mapping['code'], 
                    $vitalData[$field], 
                    $mapping['units'], 
                    $now, 
                    $userId
                );
            }
        }
    }
    
    /**
     * Insert single vital
     */
    private function insertVital(string $encounterId, string $patientId, string $code, 
                                string $value, string $units, string $observedAt, string $userId): void
    {
        $vital = new Vital([
            'encounter_id' => $encounterId,
            'patient_id' => $patientId,
            'code' => $code,
            'value' => $value,
            'units' => $units,
            'observed_at' => $observedAt,
            'observed_by' => $userId
        ]);
        
        $this->create($vital, $userId);
    }
    
    /**
     * Get abnormal vitals
     */
    public function getAbnormalVitals(string $patientId, int $days = 7): array
    {
        $sql = "SELECT 
                    o.*,
                    e.encounter_type,
                    e.started_at as encounter_date
                FROM observation o
                INNER JOIN encounters e ON o.encounter_id = e.encounter_id
                WHERE e.patient_id = :patient_id
                AND o.abnormal_flag IN ('H', 'L', 'HH', 'LL', 'A')
                AND o.observed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY o.observed_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Vital($data);
        }
        
        return $results;
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}