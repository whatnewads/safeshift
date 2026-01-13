<?php
/**
 * EPCR Repository
 * 
 * Handles all EPCR (Electronic Patient Care Report) database operations
 * NEMSIS-compliant EMS data access layer
 */

namespace Core\Repositories;

use Core\Entities\EPCR;
use PDO;
use Exception;

class EPCRRepository
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
     * Find EPCR by ID
     */
    public function findById(string $epcrId): ?EPCR
    {
        $sql = "SELECT * FROM epcr WHERE epcr_id = :epcr_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['epcr_id' => $epcrId]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return new EPCR($data);
    }
    
    /**
     * Find EPCR by encounter ID
     */
    public function findByEncounterId(string $encounterId): ?EPCR
    {
        $sql = "SELECT * FROM epcr WHERE encounter_id = :encounter_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return new EPCR($data);
    }
    
    /**
     * Find EPCRs by incident number
     */
    public function findByIncidentNumber(string $incidentNumber): array
    {
        $sql = "SELECT * FROM epcr 
                WHERE incident_number = :incident_number
                ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['incident_number' => $incidentNumber]);
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new EPCR($data);
        }
        
        return $results;
    }
    
    /**
     * Create EPCR
     */
    public function create(EPCR $epcr, string $createdBy): EPCR
    {
        $epcrId = $this->generateUuid();
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO epcr (
                    epcr_id, encounter_id, patient_id, incident_number, unit_number,
                    dispatch_time, enroute_time, onscene_time, patient_contact_time,
                    depart_scene_time, arrive_destination_time, transfer_care_time,
                    cleared_time, response_priority, transport_priority,
                    transport_disposition, destination_facility_id, destination_facility_name,
                    chief_complaint, primary_impression, secondary_impression,
                    mechanism_of_injury, location_type, incident_address,
                    incident_city, incident_state, incident_zip, incident_county,
                    incident_latitude, incident_longitude, narrative,
                    provider_signature, patient_signature, patient_refusal_reason,
                    is_submitted, is_locked, created_at, created_by
                ) VALUES (
                    :epcr_id, :encounter_id, :patient_id, :incident_number, :unit_number,
                    :dispatch_time, :enroute_time, :onscene_time, :patient_contact_time,
                    :depart_scene_time, :arrive_destination_time, :transfer_care_time,
                    :cleared_time, :response_priority, :transport_priority,
                    :transport_disposition, :destination_facility_id, :destination_facility_name,
                    :chief_complaint, :primary_impression, :secondary_impression,
                    :mechanism_of_injury, :location_type, :incident_address,
                    :incident_city, :incident_state, :incident_zip, :incident_county,
                    :incident_latitude, :incident_longitude, :narrative,
                    :provider_signature, :patient_signature, :patient_refusal_reason,
                    :is_submitted, :is_locked, :created_at, :created_by
                )";
        
        $epcr->setEpcrId($epcrId);
        $epcr->setCreatedAt($now);
        
        $data = $epcr->toArray();
        $data['created_by'] = $createdBy;
        
        // Convert arrays to JSON for storage
        $jsonFields = ['crew_members', 'vital_signs', 'procedures', 'medications'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }
        
        // Convert booleans for MySQL
        $data['is_submitted'] = $data['is_submitted'] ? 1 : 0;
        $data['is_locked'] = $data['is_locked'] ? 1 : 0;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $epcr;
    }
    
    /**
     * Update EPCR
     */
    public function update(EPCR $epcr, string $updatedBy): bool
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "UPDATE epcr SET
                    incident_number = :incident_number,
                    unit_number = :unit_number,
                    dispatch_time = :dispatch_time,
                    enroute_time = :enroute_time,
                    onscene_time = :onscene_time,
                    patient_contact_time = :patient_contact_time,
                    depart_scene_time = :depart_scene_time,
                    arrive_destination_time = :arrive_destination_time,
                    transfer_care_time = :transfer_care_time,
                    cleared_time = :cleared_time,
                    response_priority = :response_priority,
                    transport_priority = :transport_priority,
                    transport_disposition = :transport_disposition,
                    destination_facility_id = :destination_facility_id,
                    destination_facility_name = :destination_facility_name,
                    chief_complaint = :chief_complaint,
                    primary_impression = :primary_impression,
                    secondary_impression = :secondary_impression,
                    mechanism_of_injury = :mechanism_of_injury,
                    location_type = :location_type,
                    incident_address = :incident_address,
                    incident_city = :incident_city,
                    incident_state = :incident_state,
                    incident_zip = :incident_zip,
                    incident_county = :incident_county,
                    incident_latitude = :incident_latitude,
                    incident_longitude = :incident_longitude,
                    narrative = :narrative,
                    provider_signature = :provider_signature,
                    patient_signature = :patient_signature,
                    patient_refusal_reason = :patient_refusal_reason,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE epcr_id = :epcr_id
                AND is_locked = 0";
        
        $epcr->setUpdatedAt($now);
        
        $data = $epcr->toArray();
        $data['updated_by'] = $updatedBy;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * Submit EPCR
     */
    public function submit(string $epcrId, string $submittedBy): bool
    {
        $sql = "UPDATE epcr SET
                    is_submitted = 1,
                    submitted_at = :submitted_at,
                    submitted_by = :submitted_by,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE epcr_id = :epcr_id
                AND is_locked = 0";
        
        $now = date('Y-m-d H:i:s');
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'epcr_id' => $epcrId,
            'submitted_at' => $now,
            'submitted_by' => $submittedBy,
            'updated_at' => $now,
            'updated_by' => $submittedBy
        ]);
    }
    
    /**
     * Lock EPCR
     */
    public function lock(string $epcrId, string $lockedBy): bool
    {
        $sql = "UPDATE epcr SET
                    is_locked = 1,
                    locked_at = :locked_at,
                    locked_by = :locked_by,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE epcr_id = :epcr_id
                AND is_submitted = 1";
        
        $now = date('Y-m-d H:i:s');
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'epcr_id' => $epcrId,
            'locked_at' => $now,
            'locked_by' => $lockedBy,
            'updated_at' => $now,
            'updated_by' => $lockedBy
        ]);
    }
    
    /**
     * Create encounter response
     */
    public function createEncounterResponse(string $encounterId, array $data, string $userId): void
    {
        $sql = "INSERT INTO encounter_response (
                    response_id, encounter_id, unit_number, dispatch_number,
                    response_priority, transport_priority, transport_disposition,
                    destination_facility, times_data, crew_data,
                    created_at, created_by
                ) VALUES (
                    :response_id, :encounter_id, :unit_number, :dispatch_number,
                    :response_priority, :transport_priority, :transport_disposition,
                    :destination_facility, :times_data, :crew_data,
                    :created_at, :created_by
                )";
        
        // Prepare times data
        $timesData = [
            'dispatch' => $data['dispatch_time'] ?? null,
            'enroute' => $data['enroute_time'] ?? null,
            'onscene' => $data['onscene_time'] ?? null,
            'patient_contact' => $data['patient_contact_time'] ?? null,
            'depart_scene' => $data['depart_scene_time'] ?? null,
            'arrive_destination' => $data['arrive_destination_time'] ?? null,
            'transfer_care' => $data['transfer_care_time'] ?? null,
            'cleared' => $data['cleared_time'] ?? null
        ];
        
        // Prepare crew data
        $crewData = [];
        if (isset($data['crew_members']) && is_array($data['crew_members'])) {
            $crewData = $data['crew_members'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'response_id' => $this->generateUuid(),
            'encounter_id' => $encounterId,
            'unit_number' => $data['unit_number'] ?? null,
            'dispatch_number' => $data['incident_number'] ?? null,
            'response_priority' => $data['response_priority'] ?? null,
            'transport_priority' => $data['transport_priority'] ?? null,
            'transport_disposition' => $data['transport_disposition'] ?? null,
            'destination_facility' => $data['destination_facility_name'] ?? null,
            'times_data' => json_encode($timesData),
            'crew_data' => json_encode($crewData),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $userId
        ]);
    }
    
    /**
     * Insert crew members
     */
    public function insertCrewMembers(string $encounterId, array $crewMembers, string $userId): void
    {
        $sql = "INSERT INTO encounter_crew (
                    crew_id, encounter_id, user_id, role, certification_level,
                    created_at, created_by
                ) VALUES (
                    :crew_id, :encounter_id, :user_id, :role, :certification_level,
                    :created_at, :created_by
                )";
        
        $now = date('Y-m-d H:i:s');
        
        foreach ($crewMembers as $member) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'crew_id' => $this->generateUuid(),
                'encounter_id' => $encounterId,
                'user_id' => $member['user_id'] ?? null,
                'role' => $member['role'] ?? null,
                'certification_level' => $member['certification_level'] ?? null,
                'created_at' => $now,
                'created_by' => $userId
            ]);
        }
    }
    
    /**
     * Get EPCRs by date range
     */
    public function getByDateRange(string $startDate, string $endDate, int $limit = 100): array
    {
        $sql = "SELECT * FROM epcr
                WHERE dispatch_time BETWEEN :start_date AND :end_date
                ORDER BY dispatch_time DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end_date', $endDate, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new EPCR($data);
        }
        
        return $results;
    }
    
    /**
     * Get incomplete EPCRs
     */
    public function getIncomplete(string $userId = null, int $limit = 50): array
    {
        $sql = "SELECT * FROM epcr
                WHERE is_submitted = 0
                AND is_locked = 0";
        
        if ($userId) {
            $sql .= " AND created_by = :user_id";
        }
        
        $sql .= " ORDER BY created_at DESC
                 LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        if ($userId) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new EPCR($data);
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