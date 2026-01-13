<?php
/**
 * Encounter Repository
 * 
 * Handles all encounter-related database operations
 * HIPAA-compliant encounter data access layer
 */

namespace Core\Repositories;

use Core\Entities\Encounter;
use PDO;
use Exception;

class EncounterRepository
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
     * Find encounter by ID
     */
    public function findById(string $encounterId): ?Encounter
    {
        $sql = "SELECT 
                    encounter_id,
                    patient_id,
                    encounter_type,
                    status,
                    priority,
                    chief_complaint,
                    reason_for_visit,
                    provider_id,
                    location_id,
                    department_id,
                    employer_id,
                    employer_name,
                    injury_date,
                    injury_time,
                    injury_location,
                    injury_description,
                    is_work_related,
                    is_osha_recordable,
                    is_dot_related,
                    disposition,
                    discharge_instructions,
                    follow_up_date,
                    started_at,
                    ended_at,
                    created_at,
                    updated_at,
                    created_by,
                    updated_by
                FROM encounters
                WHERE encounter_id = :encounter_id
                AND status != 'voided'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return new Encounter($data);
    }
    
    /**
     * Find encounters by patient ID
     */
    public function findByPatientId(string $patientId, int $limit = 50): array
    {
        $sql = "SELECT * FROM encounters
                WHERE patient_id = :patient_id
                AND status != 'voided'
                ORDER BY started_at DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Encounter($data);
        }
        
        return $results;
    }
    
    /**
     * Get today's patient count
     */
    public function countTodayPatients(): int
    {
        $sql = "SELECT COUNT(DISTINCT patient_id) as total
                FROM encounters
                WHERE DATE(started_at) = CURDATE()
                AND status IN ('completed', 'in-progress')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
    }
    
    /**
     * Get new patients count for today
     */
    public function countNewPatientsToday(): int
    {
        $sql = "SELECT COUNT(DISTINCT e.patient_id) as total
                FROM encounters e
                WHERE DATE(e.started_at) = CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM encounters e2
                    WHERE e2.patient_id = e.patient_id
                    AND e2.started_at < e.started_at
                )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
    }
    
    /**
     * Get encounters by status
     */
    public function findByStatus(string $status, int $daysBack = 7, int $limit = 100): array
    {
        $sql = "SELECT * FROM encounters
                WHERE status = :status
                AND DATE(started_at) >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY started_at DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':days', $daysBack, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Encounter($data);
        }
        
        return $results;
    }
    
    /**
     * Count pending reviews
     */
    public function countPendingReviews(int $daysBack = 7): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM encounters
                WHERE status = 'pending_review'
                AND DATE(started_at) >= DATE_SUB(CURDATE(), INTERVAL :days DAY)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $daysBack, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
    }
    
    /**
     * Create new encounter
     */
    public function create(Encounter $encounter, string $createdBy): Encounter
    {
        $encounterId = $this->generateUuid();
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO encounters (
                    encounter_id, patient_id, encounter_type, status, priority,
                    chief_complaint, reason_for_visit, provider_id, location_id,
                    department_id, employer_id, employer_name, injury_date,
                    injury_time, injury_location, injury_description,
                    is_work_related, is_osha_recordable, is_dot_related,
                    disposition, discharge_instructions, follow_up_date,
                    started_at, ended_at, created_at, created_by
                ) VALUES (
                    :encounter_id, :patient_id, :encounter_type, :status, :priority,
                    :chief_complaint, :reason_for_visit, :provider_id, :location_id,
                    :department_id, :employer_id, :employer_name, :injury_date,
                    :injury_time, :injury_location, :injury_description,
                    :is_work_related, :is_osha_recordable, :is_dot_related,
                    :disposition, :discharge_instructions, :follow_up_date,
                    :started_at, :ended_at, :created_at, :created_by
                )";
        
        $encounter->setEncounterId($encounterId);
        $encounter->setCreatedAt($now);
        if (!$encounter->getStartedAt()) {
            $encounter->setStartedAt($now);
        }
        
        $data = $encounter->toArray();
        $data['created_by'] = $createdBy;
        
        // Convert boolean values for MySQL
        $data['is_work_related'] = $data['is_work_related'] ? 1 : 0;
        $data['is_osha_recordable'] = $data['is_osha_recordable'] ? 1 : 0;
        $data['is_dot_related'] = $data['is_dot_related'] ? 1 : 0;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        return $encounter;
    }
    
    /**
     * Update encounter
     */
    public function update(Encounter $encounter, string $updatedBy): bool
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "UPDATE encounters SET
                    encounter_type = :encounter_type,
                    status = :status,
                    priority = :priority,
                    chief_complaint = :chief_complaint,
                    reason_for_visit = :reason_for_visit,
                    provider_id = :provider_id,
                    location_id = :location_id,
                    department_id = :department_id,
                    employer_id = :employer_id,
                    employer_name = :employer_name,
                    injury_date = :injury_date,
                    injury_time = :injury_time,
                    injury_location = :injury_location,
                    injury_description = :injury_description,
                    is_work_related = :is_work_related,
                    is_osha_recordable = :is_osha_recordable,
                    is_dot_related = :is_dot_related,
                    disposition = :disposition,
                    discharge_instructions = :discharge_instructions,
                    follow_up_date = :follow_up_date,
                    ended_at = :ended_at,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE encounter_id = :encounter_id";
        
        $encounter->setUpdatedAt($now);
        
        $data = $encounter->toArray();
        $data['updated_by'] = $updatedBy;
        
        // Convert boolean values for MySQL
        $data['is_work_related'] = $data['is_work_related'] ? 1 : 0;
        $data['is_osha_recordable'] = $data['is_osha_recordable'] ? 1 : 0;
        $data['is_dot_related'] = $data['is_dot_related'] ? 1 : 0;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }
    
    /**
     * Get upcoming appointments
     */
    public function getUpcomingAppointments(int $limit = 5): array
    {
        $sql = "SELECT 
                    a.appointment_id,
                    a.start_time,
                    a.visit_reason,
                    p.legal_first_name,
                    p.legal_last_name,
                    p.patient_id
                FROM appointments a
                INNER JOIN patients p ON a.patient_id = p.patient_id
                WHERE a.start_time >= NOW()
                AND DATE(a.start_time) = CURDATE()
                AND a.status = 'scheduled'
                ORDER BY a.start_time ASC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format appointments for response
        foreach ($appointments as &$apt) {
            $apt['patient_name'] = $apt['legal_first_name'] . ' ' . $apt['legal_last_name'];
            $apt['time'] = date('g:i A', strtotime($apt['start_time']));
            unset($apt['legal_first_name'], $apt['legal_last_name']);
        }
        
        return $appointments;
    }
    
    /**
     * Get work-related encounters
     */
    public function getWorkRelatedEncounters(string $employerId, int $daysBack = 30): array
    {
        $sql = "SELECT * FROM encounters
                WHERE employer_id = :employer_id
                AND is_work_related = 1
                AND DATE(started_at) >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY started_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':employer_id', $employerId, PDO::PARAM_STR);
        $stmt->bindValue(':days', $daysBack, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Encounter($data);
        }
        
        return $results;
    }
    
    /**
     * Get OSHA recordable encounters
     */
    public function getOshaRecordableCount(string $employerId, int $year): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM encounters
                WHERE employer_id = :employer_id
                AND is_osha_recordable = 1
                AND YEAR(started_at) = :year
                AND status != 'voided'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'employer_id' => $employerId,
            'year' => $year
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
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