<?php
/**
 * Vital Entity
 * 
 * Represents a vital sign observation
 * HIPAA-compliant vital signs data structure
 */

namespace Core\Entities;

class Vital
{
    private string $observation_id;
    private string $encounter_id;
    private string $patient_id;
    private string $code;
    private string $value;
    private string $units;
    private ?string $reference_range;
    private ?string $abnormal_flag;
    private string $observed_at;
    private ?string $observed_by;
    private ?string $method;
    private ?string $body_site;
    private ?string $device_id;
    private ?string $notes;
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
            'observation_id' => $this->observation_id,
            'encounter_id' => $this->encounter_id,
            'patient_id' => $this->patient_id,
            'code' => $this->code,
            'value' => $this->value,
            'units' => $this->units,
            'reference_range' => $this->reference_range,
            'abnormal_flag' => $this->abnormal_flag,
            'observed_at' => $this->observed_at,
            'observed_by' => $this->observed_by,
            'method' => $this->method,
            'body_site' => $this->body_site,
            'device_id' => $this->device_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by
        ];
    }
    
    /**
     * Get vital display name
     */
    public function getDisplayName(): string
    {
        $displayNames = [
            'bp_systolic' => 'Blood Pressure (Systolic)',
            'bp_diastolic' => 'Blood Pressure (Diastolic)',
            'pulse' => 'Pulse',
            'temperature' => 'Temperature',
            'spo2' => 'SpO2',
            'respiration' => 'Respiration Rate',
            'blood_sugar' => 'Blood Sugar',
            'pain_scale' => 'Pain Scale',
            'weight' => 'Weight',
            'height' => 'Height',
            'bmi' => 'BMI'
        ];
        
        return $displayNames[$this->code] ?? ucfirst(str_replace('_', ' ', $this->code));
    }
    
    /**
     * Check if vital is abnormal
     */
    public function isAbnormal(): bool
    {
        return !empty($this->abnormal_flag) && 
               in_array(strtoupper($this->abnormal_flag), ['H', 'L', 'HH', 'LL', 'A']);
    }
    
    /**
     * Get formatted value with units
     */
    public function getFormattedValue(): string
    {
        return $this->value . (!empty($this->units) ? ' ' . $this->units : '');
    }
    
    // Getters
    public function getObservationId(): string { return $this->observation_id; }
    public function getEncounterId(): string { return $this->encounter_id; }
    public function getPatientId(): string { return $this->patient_id; }
    public function getCode(): string { return $this->code; }
    public function getValue(): string { return $this->value; }
    public function getUnits(): string { return $this->units; }
    public function getReferenceRange(): ?string { return $this->reference_range; }
    public function getAbnormalFlag(): ?string { return $this->abnormal_flag; }
    public function getObservedAt(): string { return $this->observed_at; }
    public function getObservedBy(): ?string { return $this->observed_by; }
    public function getMethod(): ?string { return $this->method; }
    public function getBodySite(): ?string { return $this->body_site; }
    public function getDeviceId(): ?string { return $this->device_id; }
    public function getNotes(): ?string { return $this->notes; }
    public function getCreatedAt(): string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }
    public function getCreatedBy(): string { return $this->created_by; }
    public function getUpdatedBy(): ?string { return $this->updated_by; }
    
    // Setters
    public function setObservationId(string $observation_id): void { $this->observation_id = $observation_id; }
    public function setEncounterId(string $encounter_id): void { $this->encounter_id = $encounter_id; }
    public function setPatientId(string $patient_id): void { $this->patient_id = $patient_id; }
    public function setCode(string $code): void { $this->code = $code; }
    public function setValue(string $value): void { $this->value = $value; }
    public function setUnits(string $units): void { $this->units = $units; }
    public function setReferenceRange(?string $reference_range): void { $this->reference_range = $reference_range; }
    public function setAbnormalFlag(?string $abnormal_flag): void { $this->abnormal_flag = $abnormal_flag; }
    public function setObservedAt(string $observed_at): void { $this->observed_at = $observed_at; }
    public function setObservedBy(?string $observed_by): void { $this->observed_by = $observed_by; }
    public function setMethod(?string $method): void { $this->method = $method; }
    public function setBodySite(?string $body_site): void { $this->body_site = $body_site; }
    public function setDeviceId(?string $device_id): void { $this->device_id = $device_id; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }
    public function setCreatedAt(string $created_at): void { $this->created_at = $created_at; }
    public function setUpdatedAt(?string $updated_at): void { $this->updated_at = $updated_at; }
    public function setCreatedBy(string $created_by): void { $this->created_by = $created_by; }
    public function setUpdatedBy(?string $updated_by): void { $this->updated_by = $updated_by; }
}