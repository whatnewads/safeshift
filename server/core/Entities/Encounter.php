<?php
/**
 * Encounter Entity
 * 
 * Represents a medical encounter/visit in the system
 * HIPAA-compliant encounter data structure
 */

namespace Core\Entities;

class Encounter
{
    private string $encounter_id;
    private string $patient_id;
    private string $encounter_type;
    private string $status;
    private string $priority;
    private ?string $chief_complaint;
    private ?string $reason_for_visit;
    private ?string $provider_id;
    private ?string $location_id;
    private ?string $department_id;
    private ?string $employer_id;
    private ?string $employer_name;
    private ?string $injury_date;
    private ?string $injury_time;
    private ?string $injury_location;
    private ?string $injury_description;
    private bool $is_work_related = false;
    private bool $is_osha_recordable = false;
    private bool $is_dot_related = false;
    private ?string $disposition;
    private ?string $discharge_instructions;
    private ?string $follow_up_date;
    private ?string $started_at;
    private ?string $ended_at;
    private ?string $created_at;
    private ?string $updated_at;
    private ?string $created_by;
    private ?string $updated_by;
    
    /**
     * Constructor
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    /**
     * Hydrate entity from array
     */
    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Convert entity to array
     */
    public function toArray(): array
    {
        return [
            'encounter_id' => $this->encounter_id,
            'patient_id' => $this->patient_id,
            'encounter_type' => $this->encounter_type,
            'status' => $this->status,
            'priority' => $this->priority,
            'chief_complaint' => $this->chief_complaint,
            'reason_for_visit' => $this->reason_for_visit,
            'provider_id' => $this->provider_id,
            'location_id' => $this->location_id,
            'department_id' => $this->department_id,
            'employer_id' => $this->employer_id,
            'employer_name' => $this->employer_name,
            'injury_date' => $this->injury_date,
            'injury_time' => $this->injury_time,
            'injury_location' => $this->injury_location,
            'injury_description' => $this->injury_description,
            'is_work_related' => $this->is_work_related,
            'is_osha_recordable' => $this->is_osha_recordable,
            'is_dot_related' => $this->is_dot_related,
            'disposition' => $this->disposition,
            'discharge_instructions' => $this->discharge_instructions,
            'follow_up_date' => $this->follow_up_date,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by
        ];
    }
    
    /**
     * Check if encounter is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'discharged', 'closed']);
    }
    
    /**
     * Check if encounter is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['in-progress', 'active', 'open']);
    }
    
    /**
     * Get duration in minutes
     */
    public function getDurationMinutes(): ?int
    {
        if (empty($this->started_at) || empty($this->ended_at)) {
            return null;
        }
        
        $start = strtotime($this->started_at);
        $end = strtotime($this->ended_at);
        
        if ($start === false || $end === false) {
            return null;
        }
        
        return round(($end - $start) / 60);
    }
    
    // Getters
    public function getEncounterId(): string { return $this->encounter_id; }
    public function getPatientId(): string { return $this->patient_id; }
    public function getEncounterType(): string { return $this->encounter_type; }
    public function getStatus(): string { return $this->status; }
    public function getPriority(): string { return $this->priority; }
    public function getChiefComplaint(): ?string { return $this->chief_complaint; }
    public function getReasonForVisit(): ?string { return $this->reason_for_visit; }
    public function getProviderId(): ?string { return $this->provider_id; }
    public function getLocationId(): ?string { return $this->location_id; }
    public function getDepartmentId(): ?string { return $this->department_id; }
    public function getEmployerId(): ?string { return $this->employer_id; }
    public function getEmployerName(): ?string { return $this->employer_name; }
    public function getInjuryDate(): ?string { return $this->injury_date; }
    public function getInjuryTime(): ?string { return $this->injury_time; }
    public function getInjuryLocation(): ?string { return $this->injury_location; }
    public function getInjuryDescription(): ?string { return $this->injury_description; }
    public function isWorkRelated(): bool { return $this->is_work_related; }
    public function isOshaRecordable(): bool { return $this->is_osha_recordable; }
    public function isDotRelated(): bool { return $this->is_dot_related; }
    public function getDisposition(): ?string { return $this->disposition; }
    public function getDischargeInstructions(): ?string { return $this->discharge_instructions; }
    public function getFollowUpDate(): ?string { return $this->follow_up_date; }
    public function getStartedAt(): ?string { return $this->started_at; }
    public function getEndedAt(): ?string { return $this->ended_at; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }
    public function getCreatedBy(): ?string { return $this->created_by; }
    public function getUpdatedBy(): ?string { return $this->updated_by; }
    
    // Setters
    public function setEncounterId(string $encounter_id): void { $this->encounter_id = $encounter_id; }
    public function setPatientId(string $patient_id): void { $this->patient_id = $patient_id; }
    public function setEncounterType(string $encounter_type): void { $this->encounter_type = $encounter_type; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function setPriority(string $priority): void { $this->priority = $priority; }
    public function setChiefComplaint(?string $chief_complaint): void { $this->chief_complaint = $chief_complaint; }
    public function setReasonForVisit(?string $reason_for_visit): void { $this->reason_for_visit = $reason_for_visit; }
    public function setProviderId(?string $provider_id): void { $this->provider_id = $provider_id; }
    public function setLocationId(?string $location_id): void { $this->location_id = $location_id; }
    public function setDepartmentId(?string $department_id): void { $this->department_id = $department_id; }
    public function setEmployerId(?string $employer_id): void { $this->employer_id = $employer_id; }
    public function setEmployerName(?string $employer_name): void { $this->employer_name = $employer_name; }
    public function setInjuryDate(?string $injury_date): void { $this->injury_date = $injury_date; }
    public function setInjuryTime(?string $injury_time): void { $this->injury_time = $injury_time; }
    public function setInjuryLocation(?string $injury_location): void { $this->injury_location = $injury_location; }
    public function setInjuryDescription(?string $injury_description): void { $this->injury_description = $injury_description; }
    public function setIsWorkRelated(bool $is_work_related): void { $this->is_work_related = $is_work_related; }
    public function setIsOshaRecordable(bool $is_osha_recordable): void { $this->is_osha_recordable = $is_osha_recordable; }
    public function setIsDotRelated(bool $is_dot_related): void { $this->is_dot_related = $is_dot_related; }
    public function setDisposition(?string $disposition): void { $this->disposition = $disposition; }
    public function setDischargeInstructions(?string $discharge_instructions): void { $this->discharge_instructions = $discharge_instructions; }
    public function setFollowUpDate(?string $follow_up_date): void { $this->follow_up_date = $follow_up_date; }
    public function setStartedAt(?string $started_at): void { $this->started_at = $started_at; }
    public function setEndedAt(?string $ended_at): void { $this->ended_at = $ended_at; }
    public function setCreatedAt(?string $created_at): void { $this->created_at = $created_at; }
    public function setUpdatedAt(?string $updated_at): void { $this->updated_at = $updated_at; }
    public function setCreatedBy(?string $created_by): void { $this->created_by = $created_by; }
    public function setUpdatedBy(?string $updated_by): void { $this->updated_by = $updated_by; }
}