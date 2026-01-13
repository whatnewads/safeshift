<?php
/**
 * EPCR Validator
 * 
 * Validates EMS ePCR form data
 * NEMSIS-compliant validation rules
 */

namespace Core\Validators;

class EPCRValidator
{
    /**
     * Required fields for a complete ePCR
     */
    private array $requiredFields = [
        'incident_number' => 'Incident Number',
        'unit_number' => 'Unit Number',
        'dispatch_time' => 'Dispatch Time',
        'chief_complaint' => 'Chief Complaint',
        'transport_disposition' => 'Transport Disposition',
        'response_priority' => 'Response Priority',
        'patient_first_name' => 'Patient First Name',
        'patient_last_name' => 'Patient Last Name',
        'narrative' => 'Patient Care Narrative'
    ];
    
    /**
     * Required fields by transport type
     */
    private array $conditionalFields = [
        'transported' => [
            'destination_facility_name' => 'Destination Facility',
            'transport_priority' => 'Transport Priority',
            'arrive_destination_time' => 'Arrival at Destination Time'
        ],
        'refusal' => [
            'patient_signature' => 'Patient Signature',
            'patient_refusal_reason' => 'Refusal Reason'
        ],
        'treated_released' => [
            'provider_signature' => 'Provider Signature',
            'discharge_instructions' => 'Discharge Instructions'
        ]
    ];
    
    /**
     * Time sequence validation
     */
    private array $timeSequence = [
        'dispatch_time',
        'enroute_time',
        'onscene_time',
        'patient_contact_time',
        'depart_scene_time',
        'arrive_destination_time',
        'transfer_care_time',
        'cleared_time'
    ];
    
    /**
     * Valid values for coded fields
     */
    private array $validValues = [
        'response_priority' => ['emergency', 'urgent', 'scheduled', 'standby'],
        'transport_priority' => ['red', 'yellow', 'green', 'routine'],
        'transport_disposition' => [
            'transported',
            'refusal',
            'treated_released',
            'dead_on_scene',
            'cancelled',
            'no_patient_found',
            'assist_only',
            'standby_only'
        ],
        'gender' => ['male', 'female', 'other', 'unknown'],
        'crew_role' => ['driver', 'attendant', 'supervisor', 'student', 'observer'],
        'crew_certification_level' => ['EMT-B', 'EMT-A', 'EMT-P', 'RN', 'MD', 'Student'],
        'medication_route' => ['PO', 'IV', 'IM', 'IO', 'IN', 'SQ', 'SL', 'INH', 'TOP', 'PR', 'ET'],
        'location_type' => [
            'home',
            'workplace',
            'street',
            'public_building',
            'healthcare_facility',
            'school',
            'recreational_area',
            'other'
        ]
    ];
    
    /**
     * Validate complete ePCR submission
     */
    public function validateSubmission(array $data): array
    {
        $errors = [];
        
        // Validate required fields
        foreach ($this->requiredFields as $field => $label) {
            if (empty($data[$field])) {
                $errors[$field] = "$label is required";
            }
        }
        
        // Validate conditional fields based on transport disposition
        if (!empty($data['transport_disposition'])) {
            $disposition = $data['transport_disposition'];
            if (isset($this->conditionalFields[$disposition])) {
                foreach ($this->conditionalFields[$disposition] as $field => $label) {
                    if (empty($data[$field])) {
                        $errors[$field] = "$label is required for $disposition";
                    }
                }
            }
        }
        
        // Validate times
        $timeErrors = $this->validateTimeSequence($data);
        $errors = array_merge($errors, $timeErrors);
        
        // Validate coded values
        $codeErrors = $this->validateCodedFields($data);
        $errors = array_merge($errors, $codeErrors);
        
        // Validate patient data
        $patientErrors = $this->validatePatientData($data);
        $errors = array_merge($errors, $patientErrors);
        
        // Validate vitals if present
        if (!empty($data['vitals'])) {
            $vitalErrors = $this->validateVitals($data['vitals']);
            if (!empty($vitalErrors)) {
                $errors['vitals'] = $vitalErrors;
            }
        }
        
        // Validate crew members if present
        if (!empty($data['crew_members'])) {
            $crewErrors = $this->validateCrewMembers($data['crew_members']);
            if (!empty($crewErrors)) {
                $errors['crew_members'] = $crewErrors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate save draft (less strict)
     */
    public function validateDraft(array $data): array
    {
        $errors = [];
        
        // Only validate format for fields that are present
        
        // Validate times if present
        $timeErrors = $this->validateTimeFormat($data);
        $errors = array_merge($errors, $timeErrors);
        
        // Validate coded values if present
        $codeErrors = $this->validateCodedFields($data);
        $errors = array_merge($errors, $codeErrors);
        
        // Validate blood pressure format if present
        if (!empty($data['blood_pressure'])) {
            if (!preg_match('/^\d{2,3}\/\d{2,3}$/', $data['blood_pressure'])) {
                $errors['blood_pressure'] = 'Invalid format. Use: 120/80';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate time sequence
     */
    private function validateTimeSequence(array $data): array
    {
        $errors = [];
        $previousTime = null;
        $previousField = null;
        
        foreach ($this->timeSequence as $field) {
            if (!empty($data[$field])) {
                $currentTime = strtotime($data[$field]);
                
                if ($currentTime === false) {
                    $errors[$field] = "Invalid time format";
                    continue;
                }
                
                if ($previousTime !== null && $currentTime < $previousTime) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . 
                                    " cannot be before " . 
                                    ucfirst(str_replace('_', ' ', $previousField));
                }
                
                $previousTime = $currentTime;
                $previousField = $field;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate time formats only
     */
    private function validateTimeFormat(array $data): array
    {
        $errors = [];
        $timeFields = array_merge($this->timeSequence, ['injury_date', 'injury_time']);
        
        foreach ($timeFields as $field) {
            if (!empty($data[$field]) && strtotime($data[$field]) === false) {
                $errors[$field] = "Invalid time format";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate coded fields
     */
    private function validateCodedFields(array $data): array
    {
        $errors = [];
        
        foreach ($this->validValues as $field => $validOptions) {
            if (!empty($data[$field]) && !in_array($data[$field], $validOptions)) {
                $errors[$field] = "Invalid value. Must be one of: " . implode(', ', $validOptions);
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate patient data
     */
    private function validatePatientData(array $data): array
    {
        $errors = [];
        
        // Validate date of birth
        if (!empty($data['date_of_birth'])) {
            $dob = strtotime($data['date_of_birth']);
            if ($dob === false) {
                $errors['date_of_birth'] = "Invalid date format";
            } elseif ($dob > time()) {
                $errors['date_of_birth'] = "Date of birth cannot be in the future";
            } elseif ($dob < strtotime('-150 years')) {
                $errors['date_of_birth'] = "Invalid date of birth";
            }
        }
        
        // Validate email if present
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format";
        }
        
        // Validate phone if present
        if (!empty($data['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $data['phone']);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $errors['phone'] = "Invalid phone number";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate vitals array
     */
    private function validateVitals(array $vitals): array
    {
        $errors = [];
        
        foreach ($vitals as $index => $vital) {
            $vitalErrors = [];
            
            if (empty($vital['type'])) {
                $vitalErrors['type'] = "Vital type is required";
            }
            
            if (empty($vital['value'])) {
                $vitalErrors['value'] = "Vital value is required";
            } elseif (!is_numeric($vital['value'])) {
                $vitalErrors['value'] = "Vital value must be numeric";
            }
            
            if (empty($vital['time'])) {
                $vitalErrors['time'] = "Vital time is required";
            } elseif (strtotime($vital['time']) === false) {
                $vitalErrors['time'] = "Invalid time format";
            }
            
            if (!empty($vitalErrors)) {
                $errors[$index] = $vitalErrors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate crew members array
     */
    private function validateCrewMembers(array $crewMembers): array
    {
        $errors = [];
        
        foreach ($crewMembers as $index => $member) {
            $memberErrors = [];
            
            if (empty($member['role'])) {
                $memberErrors['role'] = "Crew role is required";
            } elseif (!in_array($member['role'], $this->validValues['crew_role'])) {
                $memberErrors['role'] = "Invalid crew role";
            }
            
            if (!empty($member['certification_level']) && 
                !in_array($member['certification_level'], $this->validValues['crew_certification_level'])) {
                $memberErrors['certification_level'] = "Invalid certification level";
            }
            
            if (!empty($memberErrors)) {
                $errors[$index] = $memberErrors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if ePCR is complete for submission
     */
    public function isComplete(array $data): bool
    {
        $errors = $this->validateSubmission($data);
        return empty($errors);
    }
    
    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(array $data): int
    {
        $totalFields = count($this->requiredFields);
        $completedFields = 0;
        
        foreach ($this->requiredFields as $field => $label) {
            if (!empty($data[$field])) {
                $completedFields++;
            }
        }
        
        // Add conditional fields if applicable
        if (!empty($data['transport_disposition']) && 
            isset($this->conditionalFields[$data['transport_disposition']])) {
            $conditionalFields = $this->conditionalFields[$data['transport_disposition']];
            $totalFields += count($conditionalFields);
            
            foreach ($conditionalFields as $field => $label) {
                if (!empty($data[$field])) {
                    $completedFields++;
                }
            }
        }
        
        return $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
    }
}