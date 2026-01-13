<?php
/**
 * Encounter.php - Encounter Entity for SafeShift EHR
 * 
 * Represents a clinical encounter (visit) with status tracking,
 * locking, and amendment capabilities.
 * 
 * @package    SafeShift\Model\Entities
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Entities;

use Model\Interfaces\EntityInterface;
use Model\ValueObjects\UUID;
use DateTimeInterface;
use DateTimeImmutable;

/**
 * Encounter entity
 * 
 * Represents a clinical encounter in the SafeShift EHR system.
 * Implements encounter locking for finalized records and
 * amendment tracking for HIPAA compliance.
 */
class Encounter implements EntityInterface
{
    /** @var string|null Encounter unique identifier (UUID) */
    protected ?string $encounterId;
    
    /** @var string Patient ID */
    protected string $patientId;
    
    /** @var string|null Provider ID (clinician) */
    protected ?string $providerId;
    
    /** @var string|null Clinic ID */
    protected ?string $clinicId;
    
    /** @var string Encounter type */
    protected string $encounterType;
    
    /** @var string Encounter status */
    protected string $status;
    
    /** @var string|null Chief complaint */
    protected ?string $chiefComplaint;
    
    /** @var string|null History of present illness */
    protected ?string $hpi;
    
    /** @var string|null Review of systems */
    protected ?string $ros;
    
    /** @var string|null Physical examination findings */
    protected ?string $physicalExam;
    
    /** @var string|null Assessment/Diagnosis */
    protected ?string $assessment;
    
    /** @var string|null Treatment plan */
    protected ?string $plan;
    
    /** @var array<string, mixed> Vitals data */
    protected array $vitals;
    
    /** @var array<string, mixed> Additional clinical data */
    protected array $clinicalData;
    
    /** @var DateTimeInterface|null Encounter date/time */
    protected ?DateTimeInterface $encounterDate;
    
    /** @var DateTimeInterface|null Encounter locked timestamp */
    protected ?DateTimeInterface $lockedAt;
    
    /** @var string|null User ID who locked the encounter */
    protected ?string $lockedBy;
    
    /** @var bool Whether encounter has been amended */
    protected bool $isAmended;
    
    /** @var string|null Amendment reason */
    protected ?string $amendmentReason;
    
    /** @var DateTimeInterface|null Amendment timestamp */
    protected ?DateTimeInterface $amendedAt;
    
    /** @var string|null User ID who made the amendment */
    protected ?string $amendedBy;
    
    /** @var string|null Supervising provider ID (for residents) */
    protected ?string $supervisingProviderId;
    
    /** @var string|null Related appointment ID */
    protected ?string $appointmentId;
    
    /** @var string|null ICD-10 codes (JSON array) */
    protected ?string $icdCodes;
    
    /** @var string|null CPT codes (JSON array) */
    protected ?string $cptCodes;
    
    /** @var string|null Service location */
    protected ?string $serviceLocation;
    
    /** @var DateTimeInterface|null Entity creation timestamp */
    protected ?DateTimeInterface $createdAt;
    
    /** @var DateTimeInterface|null Entity update timestamp */
    protected ?DateTimeInterface $updatedAt;
    
    /** @var string|null Created by user ID */
    protected ?string $createdBy;

    /** Encounter type constants */
    public const TYPE_OFFICE_VISIT = 'office_visit';
    public const TYPE_DOT_PHYSICAL = 'dot_physical';
    public const TYPE_DRUG_SCREEN = 'drug_screen';
    public const TYPE_OSHA_INJURY = 'osha_injury';
    public const TYPE_WORKERS_COMP = 'workers_comp';
    public const TYPE_PRE_EMPLOYMENT = 'pre_employment';
    public const TYPE_URGENT = 'urgent';
    public const TYPE_TELEHEALTH = 'telehealth';
    public const TYPE_FOLLOW_UP = 'follow_up';

    /** Status constants */
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    /**
     * Create a new Encounter instance
     * 
     * @param string $patientId Patient ID
     * @param string $encounterType Encounter type
     * @param string|null $providerId Provider ID
     */
    public function __construct(
        string $patientId,
        string $encounterType = self::TYPE_OFFICE_VISIT,
        ?string $providerId = null
    ) {
        $this->encounterId = null;
        $this->patientId = $patientId;
        $this->providerId = $providerId;
        $this->clinicId = null;
        $this->encounterType = $this->validateEncounterType($encounterType);
        $this->status = self::STATUS_SCHEDULED;
        $this->chiefComplaint = null;
        $this->hpi = null;
        $this->ros = null;
        $this->physicalExam = null;
        $this->assessment = null;
        $this->plan = null;
        $this->vitals = [];
        $this->clinicalData = [];
        $this->encounterDate = new DateTimeImmutable();
        $this->lockedAt = null;
        $this->lockedBy = null;
        $this->isAmended = false;
        $this->amendmentReason = null;
        $this->amendedAt = null;
        $this->amendedBy = null;
        $this->supervisingProviderId = null;
        $this->appointmentId = null;
        $this->icdCodes = null;
        $this->cptCodes = null;
        $this->serviceLocation = null;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->createdBy = null;
    }

    /**
     * Validate encounter type
     * 
     * @param string $type
     * @return string
     */
    private function validateEncounterType(string $type): string
    {
        $validTypes = [
            self::TYPE_OFFICE_VISIT,
            self::TYPE_DOT_PHYSICAL,
            self::TYPE_DRUG_SCREEN,
            self::TYPE_OSHA_INJURY,
            self::TYPE_WORKERS_COMP,
            self::TYPE_PRE_EMPLOYMENT,
            self::TYPE_URGENT,
            self::TYPE_TELEHEALTH,
            self::TYPE_FOLLOW_UP,
        ];
        
        return in_array($type, $validTypes, true) ? $type : self::TYPE_OFFICE_VISIT;
    }

    /**
     * Validate status
     * 
     * @param string $status
     * @return string
     */
    private function validateStatus(string $status): string
    {
        $validStatuses = [
            self::STATUS_SCHEDULED,
            self::STATUS_CHECKED_IN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_COMPLETE,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
        ];
        
        return in_array($status, $validStatuses, true) ? $status : self::STATUS_SCHEDULED;
    }

    /**
     * Get the encounter ID
     * 
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->encounterId;
    }

    /**
     * Set the encounter ID
     * 
     * @param string $encounterId
     * @return self
     */
    public function setId(string $encounterId): self
    {
        $this->encounterId = $encounterId;
        return $this;
    }

    /**
     * Get patient ID
     * 
     * @return string
     */
    public function getPatientId(): string
    {
        return $this->patientId;
    }

    /**
     * Get provider ID
     * 
     * @return string|null
     */
    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    /**
     * Set provider ID
     * 
     * @param string|null $providerId
     * @return self
     */
    public function setProviderId(?string $providerId): self
    {
        $this->guardAgainstLocked();
        $this->providerId = $providerId;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get clinic ID
     * 
     * @return string|null
     */
    public function getClinicId(): ?string
    {
        return $this->clinicId;
    }

    /**
     * Set clinic ID
     * 
     * @param string|null $clinicId
     * @return self
     */
    public function setClinicId(?string $clinicId): self
    {
        $this->guardAgainstLocked();
        $this->clinicId = $clinicId;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get encounter type
     * 
     * @return string
     */
    public function getEncounterType(): string
    {
        return $this->encounterType;
    }

    /**
     * Set encounter type
     * 
     * @param string $type
     * @return self
     */
    public function setEncounterType(string $type): self
    {
        $this->guardAgainstLocked();
        $this->encounterType = $this->validateEncounterType($type);
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get status
     * 
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set status
     * 
     * @param string $status
     * @return self
     */
    public function setStatus(string $status): self
    {
        // Status can be changed even on locked encounters (for workflow)
        $this->status = $this->validateStatus($status);
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get chief complaint
     * 
     * @return string|null
     */
    public function getChiefComplaint(): ?string
    {
        return $this->chiefComplaint;
    }

    /**
     * Set chief complaint
     * 
     * @param string|null $complaint
     * @return self
     */
    public function setChiefComplaint(?string $complaint): self
    {
        $this->guardAgainstLocked();
        $this->chiefComplaint = $complaint;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Set HPI (History of Present Illness)
     * 
     * @param string|null $hpi
     * @return self
     */
    public function setHpi(?string $hpi): self
    {
        $this->guardAgainstLocked();
        $this->hpi = $hpi;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get HPI
     * 
     * @return string|null
     */
    public function getHpi(): ?string
    {
        return $this->hpi;
    }

    /**
     * Set Review of Systems
     * 
     * @param string|null $ros
     * @return self
     */
    public function setRos(?string $ros): self
    {
        $this->guardAgainstLocked();
        $this->ros = $ros;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get Review of Systems
     * 
     * @return string|null
     */
    public function getRos(): ?string
    {
        return $this->ros;
    }

    /**
     * Set physical exam findings
     * 
     * @param string|null $exam
     * @return self
     */
    public function setPhysicalExam(?string $exam): self
    {
        $this->guardAgainstLocked();
        $this->physicalExam = $exam;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get physical exam findings
     * 
     * @return string|null
     */
    public function getPhysicalExam(): ?string
    {
        return $this->physicalExam;
    }

    /**
     * Set assessment/diagnosis
     * 
     * @param string|null $assessment
     * @return self
     */
    public function setAssessment(?string $assessment): self
    {
        $this->guardAgainstLocked();
        $this->assessment = $assessment;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get assessment/diagnosis
     * 
     * @return string|null
     */
    public function getAssessment(): ?string
    {
        return $this->assessment;
    }

    /**
     * Set treatment plan
     * 
     * @param string|null $plan
     * @return self
     */
    public function setPlan(?string $plan): self
    {
        $this->guardAgainstLocked();
        $this->plan = $plan;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get treatment plan
     * 
     * @return string|null
     */
    public function getPlan(): ?string
    {
        return $this->plan;
    }

    /**
     * Set vitals
     * 
     * @param array<string, mixed> $vitals
     * @return self
     */
    public function setVitals(array $vitals): self
    {
        $this->guardAgainstLocked();
        $this->vitals = $vitals;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get vitals
     * 
     * @return array<string, mixed>
     */
    public function getVitals(): array
    {
        return $this->vitals;
    }

    /**
     * Add or update a vital sign
     * 
     * @param string $name Vital sign name (e.g., 'systolic_bp')
     * @param mixed $value Vital sign value
     * @return self
     */
    public function setVital(string $name, mixed $value): self
    {
        $this->guardAgainstLocked();
        $this->vitals[$name] = $value;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get a specific vital sign
     * 
     * @param string $name Vital sign name
     * @return mixed|null
     */
    public function getVital(string $name): mixed
    {
        return $this->vitals[$name] ?? null;
    }

    /**
     * Set clinical data
     * 
     * @param array<string, mixed> $data
     * @return self
     */
    public function setClinicalData(array $data): self
    {
        $this->guardAgainstLocked();
        $this->clinicalData = $data;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get clinical data
     * 
     * @return array<string, mixed>
     */
    public function getClinicalData(): array
    {
        return $this->clinicalData;
    }

    /**
     * Get encounter date
     * 
     * @return DateTimeInterface|null
     */
    public function getEncounterDate(): ?DateTimeInterface
    {
        return $this->encounterDate;
    }

    /**
     * Set encounter date
     * 
     * @param DateTimeInterface $date
     * @return self
     */
    public function setEncounterDate(DateTimeInterface $date): self
    {
        $this->guardAgainstLocked();
        $this->encounterDate = $date;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if encounter is locked
     * 
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->lockedAt !== null;
    }

    /**
     * Get locked timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getLockedAt(): ?DateTimeInterface
    {
        return $this->lockedAt;
    }

    /**
     * Lock the encounter
     * 
     * @param string $userId User ID performing the lock
     * @return self
     * @throws \RuntimeException If already locked
     */
    public function lock(string $userId): self
    {
        if ($this->isLocked()) {
            throw new \RuntimeException('Encounter is already locked');
        }
        
        $this->lockedAt = new DateTimeImmutable();
        $this->lockedBy = $userId;
        $this->status = self::STATUS_COMPLETE;
        $this->updatedAt = new DateTimeImmutable();
        
        return $this;
    }

    /**
     * Guard against modifications to locked encounter
     * 
     * @throws \RuntimeException If encounter is locked
     */
    private function guardAgainstLocked(): void
    {
        if ($this->isLocked() && !$this->isAmended) {
            throw new \RuntimeException(
                'Cannot modify locked encounter. Use amendment process instead.'
            );
        }
    }

    /**
     * Check if encounter can be amended
     * 
     * @return bool
     */
    public function canAmend(): bool
    {
        return $this->isLocked();
    }

    /**
     * Start amendment process
     * 
     * @param string $reason Amendment reason
     * @param string $userId User ID making the amendment
     * @return self
     * @throws \RuntimeException If encounter cannot be amended
     */
    public function startAmendment(string $reason, string $userId): self
    {
        if (!$this->canAmend()) {
            throw new \RuntimeException('Encounter must be locked before it can be amended');
        }
        
        if (empty(trim($reason))) {
            throw new \RuntimeException('Amendment reason is required');
        }
        
        $this->isAmended = true;
        $this->amendmentReason = $reason;
        $this->amendedAt = new DateTimeImmutable();
        $this->amendedBy = $userId;
        $this->updatedAt = new DateTimeImmutable();
        
        return $this;
    }

    /**
     * Check if encounter is currently being amended
     * 
     * @return bool
     */
    public function isBeingAmended(): bool
    {
        return $this->isAmended;
    }

    /**
     * Get amendment reason
     * 
     * @return string|null
     */
    public function getAmendmentReason(): ?string
    {
        return $this->amendmentReason;
    }

    /**
     * Check if entity has been persisted
     * 
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->encounterId !== null;
    }

    /**
     * Get creation timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Get update timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Set ICD-10 codes
     * 
     * @param array<string> $codes
     * @return self
     */
    public function setIcdCodes(array $codes): self
    {
        $this->guardAgainstLocked();
        $this->icdCodes = json_encode($codes);
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get ICD-10 codes
     * 
     * @return array<string>
     */
    public function getIcdCodes(): array
    {
        return $this->icdCodes ? json_decode($this->icdCodes, true) : [];
    }

    /**
     * Set CPT codes
     * 
     * @param array<string> $codes
     * @return self
     */
    public function setCptCodes(array $codes): self
    {
        $this->guardAgainstLocked();
        $this->cptCodes = json_encode($codes);
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get CPT codes
     * 
     * @return array<string>
     */
    public function getCptCodes(): array
    {
        return $this->cptCodes ? json_decode($this->cptCodes, true) : [];
    }

    /**
     * Validate entity data
     * 
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->patientId)) {
            $errors['patient_id'] = 'Patient ID is required';
        }
        
        if ($this->status === self::STATUS_COMPLETE && empty($this->chiefComplaint)) {
            $errors['chief_complaint'] = 'Chief complaint is required for completed encounters';
        }
        
        return $errors;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'encounter_id' => $this->encounterId,
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'clinic_id' => $this->clinicId,
            'encounter_type' => $this->encounterType,
            'status' => $this->status,
            'chief_complaint' => $this->chiefComplaint,
            'hpi' => $this->hpi,
            'ros' => $this->ros,
            'physical_exam' => $this->physicalExam,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'vitals' => $this->vitals,
            'clinical_data' => $this->clinicalData,
            'encounter_date' => $this->encounterDate?->format('Y-m-d H:i:s'),
            'locked_at' => $this->lockedAt?->format('Y-m-d H:i:s'),
            'locked_by' => $this->lockedBy,
            'is_locked' => $this->isLocked(),
            'is_amended' => $this->isAmended,
            'amendment_reason' => $this->amendmentReason,
            'amended_at' => $this->amendedAt?->format('Y-m-d H:i:s'),
            'amended_by' => $this->amendedBy,
            'supervising_provider_id' => $this->supervisingProviderId,
            'appointment_id' => $this->appointmentId,
            'icd_codes' => $this->getIcdCodes(),
            'cpt_codes' => $this->getCptCodes(),
            'service_location' => $this->serviceLocation,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'created_by' => $this->createdBy,
        ];
    }

    /**
     * Convert to safe array (for external exposure)
     * 
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'encounter_id' => $this->encounterId,
            'patient_id' => $this->patientId,
            'encounter_type' => $this->encounterType,
            'status' => $this->status,
            'encounter_date' => $this->encounterDate?->format('Y-m-d H:i:s'),
            'is_locked' => $this->isLocked(),
            'is_amended' => $this->isAmended,
        ];
    }

    /**
     * Create from array data
     * 
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $encounter = new static(
            $data['patient_id'],
            $data['encounter_type'] ?? self::TYPE_OFFICE_VISIT,
            $data['provider_id'] ?? null
        );
        
        if (isset($data['encounter_id'])) {
            $encounter->encounterId = $data['encounter_id'];
        }
        
        if (isset($data['clinic_id'])) {
            $encounter->clinicId = $data['clinic_id'];
        }
        
        if (isset($data['status'])) {
            $encounter->status = $encounter->validateStatus($data['status']);
        }
        
        $encounter->chiefComplaint = $data['chief_complaint'] ?? null;
        $encounter->hpi = $data['hpi'] ?? null;
        $encounter->ros = $data['ros'] ?? null;
        $encounter->physicalExam = $data['physical_exam'] ?? null;
        $encounter->assessment = $data['assessment'] ?? null;
        $encounter->plan = $data['plan'] ?? null;
        
        if (isset($data['vitals'])) {
            $encounter->vitals = is_array($data['vitals']) 
                ? $data['vitals'] 
                : json_decode($data['vitals'], true) ?? [];
        }
        
        if (isset($data['clinical_data'])) {
            $encounter->clinicalData = is_array($data['clinical_data'])
                ? $data['clinical_data']
                : json_decode($data['clinical_data'], true) ?? [];
        }
        
        if (isset($data['encounter_date'])) {
            $encounter->encounterDate = $data['encounter_date'] instanceof DateTimeInterface
                ? $data['encounter_date']
                : new DateTimeImmutable($data['encounter_date']);
        }
        
        if (isset($data['locked_at']) && $data['locked_at'] !== null) {
            $encounter->lockedAt = new DateTimeImmutable($data['locked_at']);
            $encounter->lockedBy = $data['locked_by'] ?? null;
        }
        
        $encounter->isAmended = (bool) ($data['is_amended'] ?? false);
        $encounter->amendmentReason = $data['amendment_reason'] ?? null;
        
        if (isset($data['amended_at']) && $data['amended_at'] !== null) {
            $encounter->amendedAt = new DateTimeImmutable($data['amended_at']);
            $encounter->amendedBy = $data['amended_by'] ?? null;
        }
        
        $encounter->supervisingProviderId = $data['supervising_provider_id'] ?? null;
        $encounter->appointmentId = $data['appointment_id'] ?? null;
        $encounter->icdCodes = isset($data['icd_codes']) && is_array($data['icd_codes'])
            ? json_encode($data['icd_codes'])
            : ($data['icd_codes'] ?? null);
        $encounter->cptCodes = isset($data['cpt_codes']) && is_array($data['cpt_codes'])
            ? json_encode($data['cpt_codes'])
            : ($data['cpt_codes'] ?? null);
        $encounter->serviceLocation = $data['service_location'] ?? null;
        
        if (isset($data['created_at'])) {
            $encounter->createdAt = new DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $encounter->updatedAt = new DateTimeImmutable($data['updated_at']);
        }
        
        $encounter->createdBy = $data['created_by'] ?? null;
        
        return $encounter;
    }
}
