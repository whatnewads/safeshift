<?php
/**
 * EPCR (Electronic Patient Care Report) Entity
 * 
 * Represents an EMS patient care report
 * NEMSIS-compliant data structure for EMS documentation
 */

namespace Core\Entities;

class EPCR
{
    private string $epcr_id;
    private string $encounter_id;
    private string $patient_id;
    private string $incident_number;
    private string $unit_number;
    private string $dispatch_time;
    private ?string $enroute_time;
    private ?string $onscene_time;
    private ?string $patient_contact_time;
    private ?string $depart_scene_time;
    private ?string $arrive_destination_time;
    private ?string $transfer_care_time;
    private ?string $cleared_time;
    private string $response_priority;
    private string $transport_priority;
    private ?string $transport_disposition;
    private ?string $destination_facility_id;
    private ?string $destination_facility_name;
    private string $chief_complaint;
    private ?string $primary_impression;
    private ?string $secondary_impression;
    private ?string $mechanism_of_injury;
    private ?string $location_type;
    private ?string $incident_address;
    private ?string $incident_city;
    private ?string $incident_state;
    private ?string $incident_zip;
    private ?string $incident_county;
    private ?float $incident_latitude;
    private ?float $incident_longitude;
    private ?array $crew_members;
    private ?array $vital_signs;
    private ?array $procedures;
    private ?array $medications;
    private ?string $narrative;
    private ?string $provider_signature;
    private ?string $patient_signature;
    private ?string $patient_refusal_reason;
    private bool $is_submitted = false;
    private ?string $submitted_at;
    private ?string $submitted_by;
    private bool $is_locked = false;
    private ?string $locked_at;
    private ?string $locked_by;
    private string $created_at;
    private ?string $updated_at;
    private string $created_by;
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
                // Handle JSON fields
                if (in_array($key, ['crew_members', 'vital_signs', 'procedures', 'medications']) && is_string($value)) {
                    $this->$key = json_decode($value, true);
                } else {
                    $this->$key = $value;
                }
            }
        }
    }
    
    /**
     * Convert entity to array
     */
    public function toArray(): array
    {
        return [
            'epcr_id' => $this->epcr_id,
            'encounter_id' => $this->encounter_id,
            'patient_id' => $this->patient_id,
            'incident_number' => $this->incident_number,
            'unit_number' => $this->unit_number,
            'dispatch_time' => $this->dispatch_time,
            'enroute_time' => $this->enroute_time,
            'onscene_time' => $this->onscene_time,
            'patient_contact_time' => $this->patient_contact_time,
            'depart_scene_time' => $this->depart_scene_time,
            'arrive_destination_time' => $this->arrive_destination_time,
            'transfer_care_time' => $this->transfer_care_time,
            'cleared_time' => $this->cleared_time,
            'response_priority' => $this->response_priority,
            'transport_priority' => $this->transport_priority,
            'transport_disposition' => $this->transport_disposition,
            'destination_facility_id' => $this->destination_facility_id,
            'destination_facility_name' => $this->destination_facility_name,
            'chief_complaint' => $this->chief_complaint,
            'primary_impression' => $this->primary_impression,
            'secondary_impression' => $this->secondary_impression,
            'mechanism_of_injury' => $this->mechanism_of_injury,
            'location_type' => $this->location_type,
            'incident_address' => $this->incident_address,
            'incident_city' => $this->incident_city,
            'incident_state' => $this->incident_state,
            'incident_zip' => $this->incident_zip,
            'incident_county' => $this->incident_county,
            'incident_latitude' => $this->incident_latitude,
            'incident_longitude' => $this->incident_longitude,
            'crew_members' => $this->crew_members,
            'vital_signs' => $this->vital_signs,
            'procedures' => $this->procedures,
            'medications' => $this->medications,
            'narrative' => $this->narrative,
            'provider_signature' => $this->provider_signature,
            'patient_signature' => $this->patient_signature,
            'patient_refusal_reason' => $this->patient_refusal_reason,
            'is_submitted' => $this->is_submitted,
            'submitted_at' => $this->submitted_at,
            'submitted_by' => $this->submitted_by,
            'is_locked' => $this->is_locked,
            'locked_at' => $this->locked_at,
            'locked_by' => $this->locked_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by
        ];
    }
    
    /**
     * Calculate response time in minutes
     */
    public function getResponseTimeMinutes(): ?int
    {
        if (empty($this->dispatch_time) || empty($this->patient_contact_time)) {
            return null;
        }
        
        $dispatch = strtotime($this->dispatch_time);
        $contact = strtotime($this->patient_contact_time);
        
        if ($dispatch === false || $contact === false) {
            return null;
        }
        
        return round(($contact - $dispatch) / 60);
    }
    
    /**
     * Calculate transport time in minutes
     */
    public function getTransportTimeMinutes(): ?int
    {
        if (empty($this->depart_scene_time) || empty($this->arrive_destination_time)) {
            return null;
        }
        
        $depart = strtotime($this->depart_scene_time);
        $arrive = strtotime($this->arrive_destination_time);
        
        if ($depart === false || $arrive === false) {
            return null;
        }
        
        return round(($arrive - $depart) / 60);
    }
    
    /**
     * Check if EPCR is complete
     */
    public function isComplete(): bool
    {
        $requiredFields = [
            'incident_number',
            'unit_number',
            'dispatch_time',
            'chief_complaint',
            'transport_disposition',
            'narrative'
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }
        
        return true;
    }
    
    // Getters
    public function getEpcrId(): string { return $this->epcr_id; }
    public function getEncounterId(): string { return $this->encounter_id; }
    public function getPatientId(): string { return $this->patient_id; }
    public function getIncidentNumber(): string { return $this->incident_number; }
    public function getUnitNumber(): string { return $this->unit_number; }
    public function getDispatchTime(): string { return $this->dispatch_time; }
    public function getEnrouteTime(): ?string { return $this->enroute_time; }
    public function getOnsceneTime(): ?string { return $this->onscene_time; }
    public function getPatientContactTime(): ?string { return $this->patient_contact_time; }
    public function getDepartSceneTime(): ?string { return $this->depart_scene_time; }
    public function getArriveDestinationTime(): ?string { return $this->arrive_destination_time; }
    public function getTransferCareTime(): ?string { return $this->transfer_care_time; }
    public function getClearedTime(): ?string { return $this->cleared_time; }
    public function getResponsePriority(): string { return $this->response_priority; }
    public function getTransportPriority(): string { return $this->transport_priority; }
    public function getTransportDisposition(): ?string { return $this->transport_disposition; }
    public function getDestinationFacilityId(): ?string { return $this->destination_facility_id; }
    public function getDestinationFacilityName(): ?string { return $this->destination_facility_name; }
    public function getChiefComplaint(): string { return $this->chief_complaint; }
    public function getPrimaryImpression(): ?string { return $this->primary_impression; }
    public function getSecondaryImpression(): ?string { return $this->secondary_impression; }
    public function getMechanismOfInjury(): ?string { return $this->mechanism_of_injury; }
    public function getLocationType(): ?string { return $this->location_type; }
    public function getIncidentAddress(): ?string { return $this->incident_address; }
    public function getIncidentCity(): ?string { return $this->incident_city; }
    public function getIncidentState(): ?string { return $this->incident_state; }
    public function getIncidentZip(): ?string { return $this->incident_zip; }
    public function getIncidentCounty(): ?string { return $this->incident_county; }
    public function getIncidentLatitude(): ?float { return $this->incident_latitude; }
    public function getIncidentLongitude(): ?float { return $this->incident_longitude; }
    public function getCrewMembers(): ?array { return $this->crew_members; }
    public function getVitalSigns(): ?array { return $this->vital_signs; }
    public function getProcedures(): ?array { return $this->procedures; }
    public function getMedications(): ?array { return $this->medications; }
    public function getNarrative(): ?string { return $this->narrative; }
    public function getProviderSignature(): ?string { return $this->provider_signature; }
    public function getPatientSignature(): ?string { return $this->patient_signature; }
    public function getPatientRefusalReason(): ?string { return $this->patient_refusal_reason; }
    public function isSubmitted(): bool { return $this->is_submitted; }
    public function getSubmittedAt(): ?string { return $this->submitted_at; }
    public function getSubmittedBy(): ?string { return $this->submitted_by; }
    public function isLocked(): bool { return $this->is_locked; }
    public function getLockedAt(): ?string { return $this->locked_at; }
    public function getLockedBy(): ?string { return $this->locked_by; }
    public function getCreatedAt(): string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }
    public function getCreatedBy(): string { return $this->created_by; }
    public function getUpdatedBy(): ?string { return $this->updated_by; }
    
    // Setters
    public function setEpcrId(string $epcr_id): void { $this->epcr_id = $epcr_id; }
    public function setEncounterId(string $encounter_id): void { $this->encounter_id = $encounter_id; }
    public function setPatientId(string $patient_id): void { $this->patient_id = $patient_id; }
    public function setIncidentNumber(string $incident_number): void { $this->incident_number = $incident_number; }
    public function setUnitNumber(string $unit_number): void { $this->unit_number = $unit_number; }
    public function setDispatchTime(string $dispatch_time): void { $this->dispatch_time = $dispatch_time; }
    public function setEnrouteTime(?string $enroute_time): void { $this->enroute_time = $enroute_time; }
    public function setOnsceneTime(?string $onscene_time): void { $this->onscene_time = $onscene_time; }
    public function setPatientContactTime(?string $patient_contact_time): void { $this->patient_contact_time = $patient_contact_time; }
    public function setDepartSceneTime(?string $depart_scene_time): void { $this->depart_scene_time = $depart_scene_time; }
    public function setArriveDestinationTime(?string $arrive_destination_time): void { $this->arrive_destination_time = $arrive_destination_time; }
    public function setTransferCareTime(?string $transfer_care_time): void { $this->transfer_care_time = $transfer_care_time; }
    public function setClearedTime(?string $cleared_time): void { $this->cleared_time = $cleared_time; }
    public function setResponsePriority(string $response_priority): void { $this->response_priority = $response_priority; }
    public function setTransportPriority(string $transport_priority): void { $this->transport_priority = $transport_priority; }
    public function setTransportDisposition(?string $transport_disposition): void { $this->transport_disposition = $transport_disposition; }
    public function setDestinationFacilityId(?string $destination_facility_id): void { $this->destination_facility_id = $destination_facility_id; }
    public function setDestinationFacilityName(?string $destination_facility_name): void { $this->destination_facility_name = $destination_facility_name; }
    public function setChiefComplaint(string $chief_complaint): void { $this->chief_complaint = $chief_complaint; }
    public function setPrimaryImpression(?string $primary_impression): void { $this->primary_impression = $primary_impression; }
    public function setSecondaryImpression(?string $secondary_impression): void { $this->secondary_impression = $secondary_impression; }
    public function setMechanismOfInjury(?string $mechanism_of_injury): void { $this->mechanism_of_injury = $mechanism_of_injury; }
    public function setLocationType(?string $location_type): void { $this->location_type = $location_type; }
    public function setIncidentAddress(?string $incident_address): void { $this->incident_address = $incident_address; }
    public function setIncidentCity(?string $incident_city): void { $this->incident_city = $incident_city; }
    public function setIncidentState(?string $incident_state): void { $this->incident_state = $incident_state; }
    public function setIncidentZip(?string $incident_zip): void { $this->incident_zip = $incident_zip; }
    public function setIncidentCounty(?string $incident_county): void { $this->incident_county = $incident_county; }
    public function setIncidentLatitude(?float $incident_latitude): void { $this->incident_latitude = $incident_latitude; }
    public function setIncidentLongitude(?float $incident_longitude): void { $this->incident_longitude = $incident_longitude; }
    public function setCrewMembers(?array $crew_members): void { $this->crew_members = $crew_members; }
    public function setVitalSigns(?array $vital_signs): void { $this->vital_signs = $vital_signs; }
    public function setProcedures(?array $procedures): void { $this->procedures = $procedures; }
    public function setMedications(?array $medications): void { $this->medications = $medications; }
    public function setNarrative(?string $narrative): void { $this->narrative = $narrative; }
    public function setProviderSignature(?string $provider_signature): void { $this->provider_signature = $provider_signature; }
    public function setPatientSignature(?string $patient_signature): void { $this->patient_signature = $patient_signature; }
    public function setPatientRefusalReason(?string $patient_refusal_reason): void { $this->patient_refusal_reason = $patient_refusal_reason; }
    public function setIsSubmitted(bool $is_submitted): void { $this->is_submitted = $is_submitted; }
    public function setSubmittedAt(?string $submitted_at): void { $this->submitted_at = $submitted_at; }
    public function setSubmittedBy(?string $submitted_by): void { $this->submitted_by = $submitted_by; }
    public function setIsLocked(bool $is_locked): void { $this->is_locked = $is_locked; }
    public function setLockedAt(?string $locked_at): void { $this->locked_at = $locked_at; }
    public function setLockedBy(?string $locked_by): void { $this->locked_by = $locked_by; }
    public function setCreatedAt(string $created_at): void { $this->created_at = $created_at; }
    public function setUpdatedAt(?string $updated_at): void { $this->updated_at = $updated_at; }
    public function setCreatedBy(string $created_by): void { $this->created_by = $created_by; }
    public function setUpdatedBy(?string $updated_by): void { $this->updated_by = $updated_by; }
}