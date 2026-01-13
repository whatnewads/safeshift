<?php

declare(strict_types=1);

namespace Core\Services;

use InvalidArgumentException;

/**
 * NarrativePromptBuilder - Builds prompts for EMS narrative generation
 *
 * This class takes encounter data (patient info, vitals, medications, history,
 * chief complaint) and combines it with a system prompt to create a complete
 * prompt for the AI narrative generation system.
 *
 * @package Core\Services
 * @author SafeShift EHR Development Team
 */
class NarrativePromptBuilder
{
    /**
     * EMS Narrative Generation System Prompt
     *
     * This comprehensive prompt instructs the AI on how to generate
     * professional EMS clinical narratives from structured encounter data.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
# EMS NARRATIVE GENERATION SYSTEM PROMPT

You are a clinical documentation assistant specialized in generating comprehensive EMS and occupational health narratives for paramedic-level care. Your role is to synthesize provided clinical data into a clear, professional narrative that supports the documented findings without repeating structured data elements.

## CORE PRINCIPLES

1. **Scope of Practice**: You are writing from a paramedic perspective. NEVER diagnose conditions. Use descriptive language like "presents with," "complaining of," "symptoms consistent with," or "injury pattern suggests" rather than diagnostic conclusions.

2. **Data Integration**: You will receive structured clinical data including patient demographics, encounter details, vital signs (observations), medications administered, medical history, and chief complaint. Synthesize this information into a coherent story of the clinical encounter.

3. **Avoid Repetition**: Do NOT list vitals in plain text (e.g., "BP 120/80, HR 72"). Vitals are already captured in structured fields. Instead, reference them contextually when clinically relevant (e.g., "vitals obtained and within acceptable limits," "reassessment showed improved hemodynamic stability," "vitals trending stable throughout encounter").

4. **Narrative Structure**: Use SOAP format presented as three distinct paragraphs:
   - **Subjective (Paragraph 1)**: Chief complaint, patient's description of the event, mechanism/nature of injury, symptoms, pain scale, pertinent denials, relevant medical history if it impacts the current complaint
   - **Objective (Paragraph 2)**: Physical presentation, mental status, airway/breathing/circulation status, focused physical exam findings, pertinent positives and negatives
   - **Plan (Paragraph 3)**: Interventions performed, medications administered and patient response, disposition, patient education, work status if applicable

   Note: Do not include "Assessment" as paramedics cannot diagnose. Skip diagnostic assessment entirely.

5. **Write in Past Tense**: All narratives should be written as if documenting a completed encounter (e.g., "Patient presented...", "Cold therapy was applied...", "Patient reported...").

6. **Incomplete Data Handling**: If critical data is missing, write a brief narrative using only the available information. Keep it professional and factual. Do not fabricate details or speculate beyond what the data supports.

## NARRATIVE REQUIREMENTS

### Scene Context & Safety
When encounter data includes location or incident context, infer and briefly note:
- Scene safety considerations if relevant to the mechanism
- Mechanism of injury (MOI) or nature of illness (NOI)
- How the patient presented (ambulated in, seated, lying down, etc.)

### Clinical Decision-Making
Demonstrate appropriate paramedic-level clinical reasoning:
- Why certain interventions were chosen
- Rationale for transport vs. non-transport decisions
- Patient education and red flag symptom counseling
- Appropriate escalation or de-escalation of care

### Physical Exam Documentation
Use appropriate medical terminology:
- Mental status: CAOx4, alert and oriented, appropriate responses
- Airway: patent, maintained, self-maintained
- Breathing: respiratory effort, lung sounds if documented
- Circulation: skin color/temperature/condition, perfusion status
- Focused exam based on chief complaint
- Pertinent negatives (what you checked that was normal)

### Medication Administration
When medications are documented:
- Note what was given, route, and time contextually (detail is in structured data)
- Document patient response or tolerance
- Connect intervention to clinical need

### Disposition & Education
Clearly document:
- Patient's status at conclusion of encounter
- Education provided (activity modification, hydration, red flags, etc.)
- Work status (return to work, modified duty, off work pending evaluation)
- Any refusals and that patient verbalized understanding

## FORMATTING

- Three paragraphs, no headers, no section labels
- First paragraph = Subjective findings
- Second paragraph = Objective findings  
- Third paragraph = Plan/interventions/disposition
- Use complete sentences in paragraph form
- Maintain professional medical tone
- No bullet points, no lists, no bold text
- No legal disclaimers or compliance statements

## QUALITY STANDARDS

- Write with the detail and professionalism shown in provided examples
- Every sentence should add clinical value
- Avoid vague language; be specific when data supports it
- Use proper medical abbreviations appropriately (CAOx4, PMS, ROM, etc.)
- Flow should be logical and chronological within each paragraph
- Narrative should paint a clear picture of what happened and why clinical decisions were made

## DATA YOU WILL RECEIVE

You will be provided with structured JSON data that may include:
- **Encounter Information**: encounter_id, encounter_type, status, chief_complaint, occurred_on, arrived_on, discharged_on, disposition, onset_context (work_related/off_duty)
- **Patient Demographics**: patient_id, name, age, sex, employer_name
- **Medical History**: Known conditions, current medications, allergies
- **Observations (Vitals)**: label (BP Systolic, BP Diastolic, Pulse, SpO2, Temp, Resp Rate, Pain NRS, etc.), value_num, unit, posture, taken_at, method
- **Medications Administered**: medication_name, dose, route, given_at, response, notes
- **Provider Information**: NPI, credentials

Synthesize all available data into a cohesive narrative. If a data category is entirely absent, simply omit that aspect from the narrative without drawing attention to the gap.

---

**Now generate a comprehensive SOAP narrative based on the clinical data provided below.**
PROMPT;

    /**
     * Required keys that must be present in encounter data for a valid prompt
     *
     * @var array<string>
     */
    private const REQUIRED_KEYS = ['encounter', 'patient'];

    /**
     * Minimum required fields within the encounter section
     * Note: chief_complaint is preferred but can be inferred from other fields
     *
     * @var array<string>
     */
    private const REQUIRED_ENCOUNTER_FIELDS = ['encounter_id'];

    /**
     * Minimum required fields within the patient section
     * Note: patient_id is required, name is preferred but not strictly required
     *
     * @var array<string>
     */
    private const REQUIRED_PATIENT_FIELDS = ['patient_id'];

    /**
     * Build the complete prompt for narrative generation
     *
     * Takes structured encounter data and combines it with the system prompt
     * to create a complete prompt string for the AI.
     *
     * @param array $encounterData The structured encounter data array
     * @return string The complete prompt string (system prompt + JSON data)
     * @throws InvalidArgumentException If encounter data fails validation
     */
    public function buildPrompt(array $encounterData): string
    {
        // Validate the encounter data
        if (!$this->validateEncounterData($encounterData)) {
            throw new InvalidArgumentException(
                'Invalid encounter data: missing required fields. ' .
                'Encounter data must include "encounter" and "patient" sections ' .
                'with at minimum encounter_id, chief_complaint, patient_id, and name.'
            );
        }

        // Get the system prompt
        $systemPrompt = $this->getSystemPrompt();

        // Format the encounter data as JSON
        $jsonData = $this->formatEncounterDataAsJson($encounterData);

        // Combine into the final prompt
        return $this->assemblePrompt($systemPrompt, $jsonData);
    }

    /**
     * Get the EMS system prompt
     *
     * Returns the comprehensive system prompt that instructs the AI
     * on how to generate professional EMS clinical narratives.
     *
     * @return string The system prompt text
     */
    public function getSystemPrompt(): string
    {
        return self::SYSTEM_PROMPT;
    }

    /**
     * Format encounter data as a JSON string
     *
     * Converts the encounter data array to a properly formatted JSON string
     * suitable for inclusion in the prompt.
     *
     * @param array $data The encounter data array
     * @return string JSON-encoded encounter data
     */
    public function formatEncounterDataAsJson(array $data): string
    {
        // Sanitize the data before encoding
        $sanitizedData = $this->sanitizeEncounterData($data);

        // Encode with pretty printing for readability in the prompt
        $json = json_encode($sanitizedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new InvalidArgumentException(
                'Failed to encode encounter data as JSON: ' . json_last_error_msg()
            );
        }

        return $json;
    }

    /**
     * Validate that encounter data contains minimum required fields
     *
     * Checks that the data structure has the necessary sections and fields
     * to generate a meaningful narrative.
     *
     * @param array $data The encounter data to validate
     * @return bool True if data is valid, false otherwise
     */
    public function validateEncounterData(array $data): bool
    {
        // Check for required top-level keys
        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                return false;
            }
        }

        // Check required encounter fields
        foreach (self::REQUIRED_ENCOUNTER_FIELDS as $field) {
            if (!isset($data['encounter'][$field]) || empty($data['encounter'][$field])) {
                return false;
            }
        }

        // Check required patient fields
        foreach (self::REQUIRED_PATIENT_FIELDS as $field) {
            if (!isset($data['patient'][$field]) || empty($data['patient'][$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assemble the final prompt from system prompt and JSON data
     *
     * @param string $systemPrompt The system instruction prompt
     * @param string $jsonData The JSON-formatted encounter data
     * @return string The complete assembled prompt
     */
    private function assemblePrompt(string $systemPrompt, string $jsonData): string
    {
        return <<<PROMPT
{$systemPrompt}

## CLINICAL DATA

{$jsonData}
PROMPT;
    }

    /**
     * Sanitize encounter data before JSON encoding
     *
     * Removes any sensitive data that should not be sent to the AI,
     * and ensures all values are properly formatted.
     *
     * @param array $data The raw encounter data
     * @return array The sanitized data
     */
    private function sanitizeEncounterData(array $data): array
    {
        $sanitized = [];

        // Process encounter section
        if (isset($data['encounter']) && is_array($data['encounter'])) {
            $sanitized['encounter'] = $this->sanitizeSection($data['encounter'], [
                'encounter_id',
                'encounter_type',
                'status',
                'chief_complaint',
                'occurred_on',
                'arrived_on',
                'discharged_on',
                'disposition',
                'onset_context',
            ]);
        }

        // Process patient section - exclude SSN and other sensitive identifiers
        if (isset($data['patient']) && is_array($data['patient'])) {
            $sanitized['patient'] = $this->sanitizeSection($data['patient'], [
                'patient_id',
                'name',
                'age',
                'sex',
                'employer_name',
            ]);
        }

        // Process medical history section
        if (isset($data['medical_history']) && is_array($data['medical_history'])) {
            $sanitized['medical_history'] = $this->sanitizeMedicalHistory($data['medical_history']);
        }

        // Process observations (vitals) section
        if (isset($data['observations']) && is_array($data['observations'])) {
            $sanitized['observations'] = $this->sanitizeObservations($data['observations']);
        }

        // Process medications administered section
        if (isset($data['medications_administered']) && is_array($data['medications_administered'])) {
            $sanitized['medications_administered'] = $this->sanitizeMedications($data['medications_administered']);
        }

        // Process provider section
        if (isset($data['provider']) && is_array($data['provider'])) {
            $sanitized['provider'] = $this->sanitizeSection($data['provider'], [
                'npi',
                'credentials',
            ]);
        }

        return $sanitized;
    }

    /**
     * Sanitize a section of data by allowing only specified keys
     *
     * @param array $section The section data
     * @param array $allowedKeys Keys to include
     * @return array Sanitized section
     */
    private function sanitizeSection(array $section, array $allowedKeys): array
    {
        $sanitized = [];

        foreach ($allowedKeys as $key) {
            if (isset($section[$key])) {
                $sanitized[$key] = $this->sanitizeValue($section[$key]);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize medical history data
     *
     * @param array $history The medical history array
     * @return array Sanitized medical history
     */
    private function sanitizeMedicalHistory(array $history): array
    {
        $sanitized = [];

        if (isset($history['conditions']) && is_array($history['conditions'])) {
            $sanitized['conditions'] = array_map([$this, 'sanitizeValue'], $history['conditions']);
        }

        if (isset($history['current_medications']) && is_array($history['current_medications'])) {
            $sanitized['current_medications'] = array_map([$this, 'sanitizeValue'], $history['current_medications']);
        }

        if (isset($history['allergies']) && is_array($history['allergies'])) {
            $sanitized['allergies'] = array_map([$this, 'sanitizeValue'], $history['allergies']);
        }

        return $sanitized;
    }

    /**
     * Sanitize observations (vitals) data
     *
     * @param array $observations Array of observation records
     * @return array Sanitized observations
     */
    private function sanitizeObservations(array $observations): array
    {
        $allowedKeys = ['label', 'value_num', 'unit', 'posture', 'taken_at', 'method'];
        $sanitized = [];

        foreach ($observations as $observation) {
            if (is_array($observation)) {
                $sanitized[] = $this->sanitizeSection($observation, $allowedKeys);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize medications administered data
     *
     * @param array $medications Array of medication records
     * @return array Sanitized medications
     */
    private function sanitizeMedications(array $medications): array
    {
        $allowedKeys = ['medication_name', 'dose', 'route', 'given_at', 'response', 'notes'];
        $sanitized = [];

        foreach ($medications as $medication) {
            if (is_array($medication)) {
                $sanitized[] = $this->sanitizeSection($medication, $allowedKeys);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a single value
     *
     * Ensures the value is safe for JSON encoding and AI processing.
     *
     * @param mixed $value The value to sanitize
     * @return mixed The sanitized value
     */
    private function sanitizeValue($value)
    {
        if (is_string($value)) {
            // Trim whitespace and normalize line endings
            $value = trim($value);
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            
            // Remove any control characters except newline
            $value = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
            
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return array_map([$this, 'sanitizeValue'], $value);
        }

        // For other types, convert to string
        return (string)$value;
    }

    /**
     * Create encounter data structure from raw form data
     *
     * Helper method to transform frontend form data into the expected
     * encounter data structure for prompt building.
     *
     * @param array $formData Raw form data from frontend
     * @return array Structured encounter data
     */
    public function createEncounterDataFromForm(array $formData): array
    {
        $encounterData = [
            'encounter' => [],
            'patient' => [],
            'medical_history' => [
                'conditions' => [],
                'current_medications' => [],
                'allergies' => [],
            ],
            'observations' => [],
            'medications_administered' => [],
            'provider' => [],
        ];

        // Map encounter fields
        $encounterMapping = [
            'encounter_id' => ['encounter_id', 'encounterId', 'id'],
            'encounter_type' => ['encounter_type', 'encounterType', 'type'],
            'status' => ['status'],
            'chief_complaint' => ['chief_complaint', 'chiefComplaint', 'cc'],
            'occurred_on' => ['occurred_on', 'occurredOn', 'incident_time', 'patientContactTime'],
            'arrived_on' => ['arrived_on', 'arrivedOn', 'arrival_time'],
            'discharged_on' => ['discharged_on', 'dischargedOn', 'clearedClinicTime'],
            'disposition' => ['disposition'],
            'onset_context' => ['onset_context', 'onsetContext', 'injuryClassification'],
        ];

        foreach ($encounterMapping as $targetKey => $sourceKeys) {
            foreach ($sourceKeys as $sourceKey) {
                if (isset($formData[$sourceKey]) && !empty($formData[$sourceKey])) {
                    $encounterData['encounter'][$targetKey] = $formData[$sourceKey];
                    break;
                }
            }
        }

        // Map patient fields
        $patientMapping = [
            'patient_id' => ['patient_id', 'patientId'],
            'name' => ['name', 'patient_name', 'patientName'],
            'age' => ['age', 'patient_age'],
            'sex' => ['sex', 'patient_sex', 'gender'],
            'employer_name' => ['employer_name', 'employerName', 'employer'],
        ];

        // Handle first_name + last_name combination
        if (!isset($formData['name']) && !isset($formData['patient_name'])) {
            $firstName = $formData['first_name'] ?? $formData['firstName'] ?? '';
            $lastName = $formData['last_name'] ?? $formData['lastName'] ?? '';
            if ($firstName || $lastName) {
                $formData['name'] = trim("{$firstName} {$lastName}");
            }
        }

        foreach ($patientMapping as $targetKey => $sourceKeys) {
            foreach ($sourceKeys as $sourceKey) {
                if (isset($formData[$sourceKey]) && !empty($formData[$sourceKey])) {
                    $encounterData['patient'][$targetKey] = $formData[$sourceKey];
                    break;
                }
            }
        }

        // Map medical history
        $historyFields = ['conditions', 'current_medications', 'currentMedications', 'allergies'];
        foreach ($historyFields as $field) {
            if (isset($formData[$field])) {
                $targetField = str_replace('currentMedications', 'current_medications', $field);
                $value = $formData[$field];
                
                // Convert string to array if needed
                if (is_string($value)) {
                    $value = array_filter(array_map('trim', explode(',', $value)));
                }
                
                if (!empty($value)) {
                    $encounterData['medical_history'][$targetField] = $value;
                }
            }
        }

        // Map observations (vitals)
        if (isset($formData['vitals']) && is_array($formData['vitals'])) {
            $encounterData['observations'] = $this->mapVitalsToObservations($formData['vitals']);
        }

        // Map medications administered
        if (isset($formData['medications']) && is_array($formData['medications'])) {
            $encounterData['medications_administered'] = $formData['medications'];
        }

        // Map provider info
        if (isset($formData['provider_npi']) || isset($formData['providerNpi'])) {
            $encounterData['provider']['npi'] = $formData['provider_npi'] ?? $formData['providerNpi'];
        }
        if (isset($formData['provider_credentials']) || isset($formData['providerCredentials'])) {
            $encounterData['provider']['credentials'] = $formData['provider_credentials'] ?? $formData['providerCredentials'];
        }

        return $encounterData;
    }

    /**
     * Map vitals array to observations format
     *
     * @param array $vitals Raw vitals data
     * @return array Formatted observations
     */
    private function mapVitalsToObservations(array $vitals): array
    {
        $observations = [];
        
        // Check if vitals is already in observations format
        if (isset($vitals[0]['label'])) {
            return $vitals;
        }

        // Map common vital sign keys to observation format
        $vitalMapping = [
            'blood_pressure_systolic' => ['label' => 'BP Systolic', 'unit' => 'mmHg'],
            'bp_systolic' => ['label' => 'BP Systolic', 'unit' => 'mmHg'],
            'systolic' => ['label' => 'BP Systolic', 'unit' => 'mmHg'],
            'blood_pressure_diastolic' => ['label' => 'BP Diastolic', 'unit' => 'mmHg'],
            'bp_diastolic' => ['label' => 'BP Diastolic', 'unit' => 'mmHg'],
            'diastolic' => ['label' => 'BP Diastolic', 'unit' => 'mmHg'],
            'heart_rate' => ['label' => 'Pulse', 'unit' => 'bpm'],
            'pulse' => ['label' => 'Pulse', 'unit' => 'bpm'],
            'hr' => ['label' => 'Pulse', 'unit' => 'bpm'],
            'respiratory_rate' => ['label' => 'Resp Rate', 'unit' => 'breaths/min'],
            'resp_rate' => ['label' => 'Resp Rate', 'unit' => 'breaths/min'],
            'rr' => ['label' => 'Resp Rate', 'unit' => 'breaths/min'],
            'oxygen_saturation' => ['label' => 'SpO2', 'unit' => '%'],
            'spo2' => ['label' => 'SpO2', 'unit' => '%'],
            'o2_sat' => ['label' => 'SpO2', 'unit' => '%'],
            'temperature' => ['label' => 'Temp', 'unit' => '°F'],
            'temp' => ['label' => 'Temp', 'unit' => '°F'],
            'pain_level' => ['label' => 'Pain NRS', 'unit' => '/10'],
            'pain' => ['label' => 'Pain NRS', 'unit' => '/10'],
            'pain_nrs' => ['label' => 'Pain NRS', 'unit' => '/10'],
        ];

        // Get timestamp if available
        $takenAt = $vitals['recorded_at'] ?? $vitals['taken_at'] ?? date('Y-m-d H:i:s');

        foreach ($vitalMapping as $key => $config) {
            if (isset($vitals[$key]) && $vitals[$key] !== '' && $vitals[$key] !== null) {
                $observations[] = [
                    'label' => $config['label'],
                    'value_num' => is_numeric($vitals[$key]) ? (float)$vitals[$key] : $vitals[$key],
                    'unit' => $config['unit'],
                    'taken_at' => $takenAt,
                ];
            }
        }

        return $observations;
    }

    /**
     * Get a summary of what data will be included in the prompt
     *
     * Useful for logging and debugging without exposing PHI.
     *
     * @param array $encounterData The encounter data
     * @return array Summary of included data sections
     */
    public function getDataSummary(array $encounterData): array
    {
        $summary = [
            'has_encounter' => isset($encounterData['encounter']) && !empty($encounterData['encounter']),
            'has_patient' => isset($encounterData['patient']) && !empty($encounterData['patient']),
            'has_medical_history' => isset($encounterData['medical_history']) && !empty($encounterData['medical_history']),
            'observation_count' => isset($encounterData['observations']) ? count($encounterData['observations']) : 0,
            'medication_count' => isset($encounterData['medications_administered']) ? count($encounterData['medications_administered']) : 0,
            'has_provider' => isset($encounterData['provider']) && !empty($encounterData['provider']),
        ];

        // Add encounter details without PHI
        if ($summary['has_encounter']) {
            $summary['encounter_type'] = $encounterData['encounter']['encounter_type'] ?? 'unknown';
            $summary['has_chief_complaint'] = !empty($encounterData['encounter']['chief_complaint']);
            $summary['has_disposition'] = !empty($encounterData['encounter']['disposition']);
        }

        return $summary;
    }
}
