<?php
/**
 * OshaInjury.php - OSHA Recordable Injury Entity for SafeShift EHR
 * 
 * Represents an OSHA recordable injury/illness case per 29 CFR 1904 requirements.
 * Tracks case classification, days away/restricted, and recordkeeping data.
 * 
 * @package    SafeShift\Model\Entities
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Entities;

use Model\Interfaces\EntityInterface;
use DateTimeInterface;
use DateTimeImmutable;

/**
 * OshaInjury entity
 * 
 * Represents an OSHA recordable injury or illness in the SafeShift EHR system.
 * Compliant with 29 CFR 1904 OSHA recordkeeping requirements.
 */
class OshaInjury implements EntityInterface
{
    /** @var string|null Case unique identifier (UUID) */
    protected ?string $caseId;
    
    /** @var string|null Related encounter ID */
    protected ?string $encounterId;
    
    /** @var string Patient/Employee ID */
    protected string $patientId;
    
    /** @var string|null Employee name */
    protected ?string $employeeName;
    
    /** @var string|null Employee job title */
    protected ?string $jobTitle;
    
    /** @var string|null Employer/Establishment ID */
    protected ?string $establishmentId;
    
    /** @var string|null Establishment name */
    protected ?string $establishmentName;
    
    /** @var int|null OSHA 300 log case number */
    protected ?int $caseNumber;
    
    /** @var int Year for OSHA 300 log */
    protected int $logYear;
    
    /** @var DateTimeInterface Date of injury or illness onset */
    protected DateTimeInterface $injuryDate;
    
    /** @var string|null Where event occurred */
    protected ?string $location;
    
    /** @var string|null Description of injury or illness */
    protected ?string $description;
    
    /** @var string|null How injury/illness occurred */
    protected ?string $howOccurred;
    
    /** @var string Case classification */
    protected string $classification;
    
    /** @var bool Is this case recordable on OSHA 300 log */
    protected bool $isRecordable;
    
    /** @var bool Did injury result in death */
    protected bool $resultedInDeath;
    
    /** @var DateTimeInterface|null Date of death if applicable */
    protected ?DateTimeInterface $deathDate;
    
    /** @var bool Did injury result in days away from work */
    protected bool $daysAwayFromWork;
    
    /** @var int Number of days away from work */
    protected int $daysAwayCount;
    
    /** @var bool Did injury result in job transfer or restriction */
    protected bool $jobTransferRestriction;
    
    /** @var int Number of days of job transfer or restriction */
    protected int $jobTransferRestrictionDays;
    
    /** @var bool Other recordable case */
    protected bool $otherRecordable;
    
    /** @var string|null Injury type code */
    protected ?string $injuryType;
    
    /** @var string|null Body part affected */
    protected ?string $bodyPart;
    
    /** @var string|null Object/Substance that harmed employee */
    protected ?string $sourceOfInjury;
    
    /** @var string|null Event type (how injury occurred) */
    protected ?string $eventType;
    
    /** @var bool Is this a privacy case (Column B) */
    protected bool $isPrivacyCase;
    
    /** @var bool Case involves hearing loss */
    protected bool $hearingLoss;
    
    /** @var bool Case involves needlestick/sharps injury */
    protected bool $needlestickSharps;
    
    /** @var bool Case involves skin disorder */
    protected bool $skinDisorder;
    
    /** @var bool Case involves respiratory condition */
    protected bool $respiratoryCondition;
    
    /** @var bool Case involves poisoning */
    protected bool $poisoning;
    
    /** @var bool All other illnesses */
    protected bool $otherIllness;
    
    /** @var string|null Initial treatment */
    protected ?string $initialTreatment;
    
    /** @var string|null Treating physician */
    protected ?string $treatingPhysician;
    
    /** @var string|null Hospital/Clinic if hospitalized */
    protected ?string $hospital;
    
    /** @var bool Was employee hospitalized */
    protected bool $wasHospitalized;
    
    /** @var DateTimeInterface|null Return to work date */
    protected ?DateTimeInterface $returnToWorkDate;
    
    /** @var string Case status */
    protected string $status;
    
    /** @var string|null Workers comp claim number */
    protected ?string $workersCompClaimNumber;
    
    /** @var string|null Clinic ID */
    protected ?string $clinicId;
    
    /** @var DateTimeInterface|null Entity creation timestamp */
    protected ?DateTimeInterface $createdAt;
    
    /** @var DateTimeInterface|null Entity update timestamp */
    protected ?DateTimeInterface $updatedAt;
    
    /** @var string|null Created by user ID */
    protected ?string $createdBy;

    /** Classification constants per OSHA 300 log */
    public const CLASS_DEATH = 'death';
    public const CLASS_DAYS_AWAY = 'days_away';
    public const CLASS_JOB_TRANSFER_RESTRICTION = 'job_transfer_restriction';
    public const CLASS_OTHER_RECORDABLE = 'other_recordable';

    /** Status constants */
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_AMENDED = 'amended';

    /** Injury type codes (per OSHA) */
    public const INJURY_TYPE_INJURY = 'injury';
    public const INJURY_TYPE_SKIN_DISORDER = 'skin_disorder';
    public const INJURY_TYPE_RESPIRATORY = 'respiratory';
    public const INJURY_TYPE_POISONING = 'poisoning';
    public const INJURY_TYPE_HEARING_LOSS = 'hearing_loss';
    public const INJURY_TYPE_ALL_OTHER = 'all_other';

    /**
     * Create a new OshaInjury instance
     * 
     * @param string $patientId Patient/Employee ID
     * @param DateTimeInterface $injuryDate Date of injury
     */
    public function __construct(
        string $patientId,
        DateTimeInterface $injuryDate
    ) {
        $this->caseId = null;
        $this->encounterId = null;
        $this->patientId = $patientId;
        $this->employeeName = null;
        $this->jobTitle = null;
        $this->establishmentId = null;
        $this->establishmentName = null;
        $this->caseNumber = null;
        $this->logYear = (int) $injuryDate->format('Y');
        $this->injuryDate = $injuryDate;
        $this->location = null;
        $this->description = null;
        $this->howOccurred = null;
        $this->classification = self::CLASS_OTHER_RECORDABLE;
        $this->isRecordable = false;
        $this->resultedInDeath = false;
        $this->deathDate = null;
        $this->daysAwayFromWork = false;
        $this->daysAwayCount = 0;
        $this->jobTransferRestriction = false;
        $this->jobTransferRestrictionDays = 0;
        $this->otherRecordable = false;
        $this->injuryType = self::INJURY_TYPE_INJURY;
        $this->bodyPart = null;
        $this->sourceOfInjury = null;
        $this->eventType = null;
        $this->isPrivacyCase = false;
        $this->hearingLoss = false;
        $this->needlestickSharps = false;
        $this->skinDisorder = false;
        $this->respiratoryCondition = false;
        $this->poisoning = false;
        $this->otherIllness = false;
        $this->initialTreatment = null;
        $this->treatingPhysician = null;
        $this->hospital = null;
        $this->wasHospitalized = false;
        $this->returnToWorkDate = null;
        $this->status = self::STATUS_OPEN;
        $this->workersCompClaimNumber = null;
        $this->clinicId = null;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->createdBy = null;
    }

    /**
     * Get the case ID
     * 
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->caseId;
    }

    /**
     * Set the case ID
     * 
     * @param string $caseId
     * @return self
     */
    public function setId(string $caseId): self
    {
        $this->caseId = $caseId;
        return $this;
    }

    /**
     * Get encounter ID
     * 
     * @return string|null
     */
    public function getEncounterId(): ?string
    {
        return $this->encounterId;
    }

    /**
     * Set encounter ID
     * 
     * @param string|null $encounterId
     * @return self
     */
    public function setEncounterId(?string $encounterId): self
    {
        $this->encounterId = $encounterId;
        $this->updatedAt = new DateTimeImmutable();
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
     * Set employee information
     * 
     * @param string $name Employee name
     * @param string $jobTitle Job title
     * @return self
     */
    public function setEmployeeInfo(string $name, string $jobTitle): self
    {
        $this->employeeName = $name;
        $this->jobTitle = $jobTitle;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get employee name
     * 
     * @return string|null
     */
    public function getEmployeeName(): ?string
    {
        return $this->employeeName;
    }

    /**
     * Get job title
     * 
     * @return string|null
     */
    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    /**
     * Set establishment information
     * 
     * @param string $establishmentId
     * @param string $establishmentName
     * @return self
     */
    public function setEstablishment(string $establishmentId, string $establishmentName): self
    {
        $this->establishmentId = $establishmentId;
        $this->establishmentName = $establishmentName;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get case number
     * 
     * @return int|null
     */
    public function getCaseNumber(): ?int
    {
        return $this->caseNumber;
    }

    /**
     * Set case number
     * 
     * @param int $caseNumber
     * @return self
     */
    public function setCaseNumber(int $caseNumber): self
    {
        $this->caseNumber = $caseNumber;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get log year
     * 
     * @return int
     */
    public function getLogYear(): int
    {
        return $this->logYear;
    }

    /**
     * Get injury date
     * 
     * @return DateTimeInterface
     */
    public function getInjuryDate(): DateTimeInterface
    {
        return $this->injuryDate;
    }

    /**
     * Set injury details
     * 
     * @param string $location Where injury occurred
     * @param string $description Description of injury
     * @param string $howOccurred How injury occurred
     * @return self
     */
    public function setInjuryDetails(
        string $location,
        string $description,
        string $howOccurred
    ): self {
        $this->location = $location;
        $this->description = $description;
        $this->howOccurred = $howOccurred;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get description
     * 
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get classification
     * 
     * @return string
     */
    public function getClassification(): string
    {
        return $this->classification;
    }

    /**
     * Set classification
     * 
     * @param string $classification
     * @return self
     */
    public function setClassification(string $classification): self
    {
        $validClasses = [
            self::CLASS_DEATH,
            self::CLASS_DAYS_AWAY,
            self::CLASS_JOB_TRANSFER_RESTRICTION,
            self::CLASS_OTHER_RECORDABLE,
        ];
        
        if (in_array($classification, $validClasses, true)) {
            $this->classification = $classification;
            $this->updatedAt = new DateTimeImmutable();
        }
        
        return $this;
    }

    /**
     * Check if case is recordable
     * 
     * @return bool
     */
    public function isRecordable(): bool
    {
        return $this->isRecordable;
    }

    /**
     * Set recordable status
     * 
     * @param bool $recordable
     * @return self
     */
    public function setRecordable(bool $recordable): self
    {
        $this->isRecordable = $recordable;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Record death
     * 
     * @param DateTimeInterface|null $deathDate
     * @return self
     */
    public function recordDeath(?DateTimeInterface $deathDate = null): self
    {
        $this->resultedInDeath = true;
        $this->deathDate = $deathDate ?? new DateTimeImmutable();
        $this->classification = self::CLASS_DEATH;
        $this->isRecordable = true;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if resulted in death
     * 
     * @return bool
     */
    public function resultedInDeath(): bool
    {
        return $this->resultedInDeath;
    }

    /**
     * Set days away from work
     * 
     * @param int $days
     * @return self
     */
    public function setDaysAway(int $days): self
    {
        $this->daysAwayFromWork = $days > 0;
        $this->daysAwayCount = $days;
        
        if ($days > 0) {
            $this->classification = self::CLASS_DAYS_AWAY;
            $this->isRecordable = true;
        }
        
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get days away count
     * 
     * @return int
     */
    public function getDaysAwayCount(): int
    {
        return $this->daysAwayCount;
    }

    /**
     * Set job transfer/restriction days
     * 
     * @param int $days
     * @return self
     */
    public function setJobTransferRestrictionDays(int $days): self
    {
        $this->jobTransferRestriction = $days > 0;
        $this->jobTransferRestrictionDays = $days;
        
        if ($days > 0 && $this->classification !== self::CLASS_DAYS_AWAY) {
            $this->classification = self::CLASS_JOB_TRANSFER_RESTRICTION;
            $this->isRecordable = true;
        }
        
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get job transfer/restriction days
     * 
     * @return int
     */
    public function getJobTransferRestrictionDays(): int
    {
        return $this->jobTransferRestrictionDays;
    }

    /**
     * Set injury type information
     * 
     * @param string $injuryType
     * @param string $bodyPart
     * @param string|null $sourceOfInjury
     * @param string|null $eventType
     * @return self
     */
    public function setInjuryTypeInfo(
        string $injuryType,
        string $bodyPart,
        ?string $sourceOfInjury = null,
        ?string $eventType = null
    ): self {
        $this->injuryType = $injuryType;
        $this->bodyPart = $bodyPart;
        $this->sourceOfInjury = $sourceOfInjury;
        $this->eventType = $eventType;
        
        // Set illness type flags
        $this->skinDisorder = $injuryType === self::INJURY_TYPE_SKIN_DISORDER;
        $this->respiratoryCondition = $injuryType === self::INJURY_TYPE_RESPIRATORY;
        $this->poisoning = $injuryType === self::INJURY_TYPE_POISONING;
        $this->hearingLoss = $injuryType === self::INJURY_TYPE_HEARING_LOSS;
        $this->otherIllness = $injuryType === self::INJURY_TYPE_ALL_OTHER;
        
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get injury type
     * 
     * @return string|null
     */
    public function getInjuryType(): ?string
    {
        return $this->injuryType;
    }

    /**
     * Get body part
     * 
     * @return string|null
     */
    public function getBodyPart(): ?string
    {
        return $this->bodyPart;
    }

    /**
     * Set as privacy case
     * 
     * @param bool $isPrivacy
     * @return self
     */
    public function setPrivacyCase(bool $isPrivacy): self
    {
        $this->isPrivacyCase = $isPrivacy;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if privacy case
     * 
     * @return bool
     */
    public function isPrivacyCase(): bool
    {
        return $this->isPrivacyCase;
    }

    /**
     * Set treatment information
     * 
     * @param string $initialTreatment
     * @param string|null $physician
     * @param bool $hospitalized
     * @param string|null $hospital
     * @return self
     */
    public function setTreatmentInfo(
        string $initialTreatment,
        ?string $physician = null,
        bool $hospitalized = false,
        ?string $hospital = null
    ): self {
        $this->initialTreatment = $initialTreatment;
        $this->treatingPhysician = $physician;
        $this->wasHospitalized = $hospitalized;
        $this->hospital = $hospital;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Set return to work date
     * 
     * @param DateTimeInterface $date
     * @return self
     */
    public function setReturnToWorkDate(DateTimeInterface $date): self
    {
        $this->returnToWorkDate = $date;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get return to work date
     * 
     * @return DateTimeInterface|null
     */
    public function getReturnToWorkDate(): ?DateTimeInterface
    {
        return $this->returnToWorkDate;
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
        $validStatuses = [
            self::STATUS_OPEN,
            self::STATUS_CLOSED,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_AMENDED,
        ];
        
        if (in_array($status, $validStatuses, true)) {
            $this->status = $status;
            $this->updatedAt = new DateTimeImmutable();
        }
        
        return $this;
    }

    /**
     * Close case
     * 
     * @return self
     */
    public function closeCase(): self
    {
        $this->status = self::STATUS_CLOSED;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Set workers comp claim number
     * 
     * @param string $claimNumber
     * @return self
     */
    public function setWorkersCompClaim(string $claimNumber): self
    {
        $this->workersCompClaimNumber = $claimNumber;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if entity has been persisted
     * 
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->caseId !== null;
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
     * Validate entity data
     * 
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->patientId)) {
            $errors['patient_id'] = 'Employee/Patient ID is required';
        }
        
        if ($this->isRecordable && empty($this->description)) {
            $errors['description'] = 'Description is required for recordable cases';
        }
        
        if ($this->isRecordable && empty($this->injuryType)) {
            $errors['injury_type'] = 'Injury type is required for recordable cases';
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
            'case_id' => $this->caseId,
            'encounter_id' => $this->encounterId,
            'patient_id' => $this->patientId,
            'employee_name' => $this->employeeName,
            'job_title' => $this->jobTitle,
            'establishment_id' => $this->establishmentId,
            'establishment_name' => $this->establishmentName,
            'case_number' => $this->caseNumber,
            'log_year' => $this->logYear,
            'injury_date' => $this->injuryDate->format('Y-m-d'),
            'location' => $this->location,
            'description' => $this->description,
            'how_occurred' => $this->howOccurred,
            'classification' => $this->classification,
            'is_recordable' => $this->isRecordable,
            'resulted_in_death' => $this->resultedInDeath,
            'death_date' => $this->deathDate?->format('Y-m-d'),
            'days_away_from_work' => $this->daysAwayFromWork,
            'days_away_count' => $this->daysAwayCount,
            'job_transfer_restriction' => $this->jobTransferRestriction,
            'job_transfer_restriction_days' => $this->jobTransferRestrictionDays,
            'other_recordable' => $this->otherRecordable,
            'injury_type' => $this->injuryType,
            'body_part' => $this->bodyPart,
            'source_of_injury' => $this->sourceOfInjury,
            'event_type' => $this->eventType,
            'is_privacy_case' => $this->isPrivacyCase,
            'hearing_loss' => $this->hearingLoss,
            'needlestick_sharps' => $this->needlestickSharps,
            'skin_disorder' => $this->skinDisorder,
            'respiratory_condition' => $this->respiratoryCondition,
            'poisoning' => $this->poisoning,
            'other_illness' => $this->otherIllness,
            'initial_treatment' => $this->initialTreatment,
            'treating_physician' => $this->treatingPhysician,
            'hospital' => $this->hospital,
            'was_hospitalized' => $this->wasHospitalized,
            'return_to_work_date' => $this->returnToWorkDate?->format('Y-m-d'),
            'status' => $this->status,
            'workers_comp_claim_number' => $this->workersCompClaimNumber,
            'clinic_id' => $this->clinicId,
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
            'case_id' => $this->caseId,
            'case_number' => $this->caseNumber,
            'log_year' => $this->logYear,
            'injury_date' => $this->injuryDate->format('Y-m-d'),
            'classification' => $this->classification,
            'is_recordable' => $this->isRecordable,
            'status' => $this->status,
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
        $injuryDate = $data['injury_date'] instanceof DateTimeInterface
            ? $data['injury_date']
            : new DateTimeImmutable($data['injury_date']);
        
        $injury = new static($data['patient_id'], $injuryDate);
        
        if (isset($data['case_id'])) {
            $injury->caseId = $data['case_id'];
        }
        
        $injury->encounterId = $data['encounter_id'] ?? null;
        $injury->employeeName = $data['employee_name'] ?? null;
        $injury->jobTitle = $data['job_title'] ?? null;
        $injury->establishmentId = $data['establishment_id'] ?? null;
        $injury->establishmentName = $data['establishment_name'] ?? null;
        $injury->caseNumber = isset($data['case_number']) ? (int) $data['case_number'] : null;
        $injury->logYear = (int) ($data['log_year'] ?? $injuryDate->format('Y'));
        $injury->location = $data['location'] ?? null;
        $injury->description = $data['description'] ?? null;
        $injury->howOccurred = $data['how_occurred'] ?? null;
        $injury->classification = $data['classification'] ?? self::CLASS_OTHER_RECORDABLE;
        $injury->isRecordable = (bool) ($data['is_recordable'] ?? false);
        $injury->resultedInDeath = (bool) ($data['resulted_in_death'] ?? false);
        
        if (isset($data['death_date'])) {
            $injury->deathDate = new DateTimeImmutable($data['death_date']);
        }
        
        $injury->daysAwayFromWork = (bool) ($data['days_away_from_work'] ?? false);
        $injury->daysAwayCount = (int) ($data['days_away_count'] ?? 0);
        $injury->jobTransferRestriction = (bool) ($data['job_transfer_restriction'] ?? false);
        $injury->jobTransferRestrictionDays = (int) ($data['job_transfer_restriction_days'] ?? 0);
        $injury->otherRecordable = (bool) ($data['other_recordable'] ?? false);
        $injury->injuryType = $data['injury_type'] ?? self::INJURY_TYPE_INJURY;
        $injury->bodyPart = $data['body_part'] ?? null;
        $injury->sourceOfInjury = $data['source_of_injury'] ?? null;
        $injury->eventType = $data['event_type'] ?? null;
        $injury->isPrivacyCase = (bool) ($data['is_privacy_case'] ?? false);
        $injury->hearingLoss = (bool) ($data['hearing_loss'] ?? false);
        $injury->needlestickSharps = (bool) ($data['needlestick_sharps'] ?? false);
        $injury->skinDisorder = (bool) ($data['skin_disorder'] ?? false);
        $injury->respiratoryCondition = (bool) ($data['respiratory_condition'] ?? false);
        $injury->poisoning = (bool) ($data['poisoning'] ?? false);
        $injury->otherIllness = (bool) ($data['other_illness'] ?? false);
        $injury->initialTreatment = $data['initial_treatment'] ?? null;
        $injury->treatingPhysician = $data['treating_physician'] ?? null;
        $injury->hospital = $data['hospital'] ?? null;
        $injury->wasHospitalized = (bool) ($data['was_hospitalized'] ?? false);
        
        if (isset($data['return_to_work_date'])) {
            $injury->returnToWorkDate = new DateTimeImmutable($data['return_to_work_date']);
        }
        
        $injury->status = $data['status'] ?? self::STATUS_OPEN;
        $injury->workersCompClaimNumber = $data['workers_comp_claim_number'] ?? null;
        $injury->clinicId = $data['clinic_id'] ?? null;
        
        if (isset($data['created_at'])) {
            $injury->createdAt = new DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $injury->updatedAt = new DateTimeImmutable($data['updated_at']);
        }
        
        $injury->createdBy = $data['created_by'] ?? null;
        
        return $injury;
    }
}
