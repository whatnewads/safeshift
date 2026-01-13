<?php
/**
 * DotTest.php - DOT Drug Testing Entity for SafeShift EHR
 * 
 * Represents a DOT (Department of Transportation) drug test with
 * CCF form data, results, and MRO verification tracking.
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
 * DotTest entity
 * 
 * Represents a DOT drug or alcohol test in the SafeShift EHR system.
 * Compliant with 49 CFR Part 40 requirements for drug and alcohol testing.
 */
class DotTest implements EntityInterface
{
    /** @var string|null Test unique identifier (UUID) */
    protected ?string $testId;
    
    /** @var string|null Related encounter ID */
    protected ?string $encounterId;
    
    /** @var string Patient ID */
    protected string $patientId;
    
    /** @var string Test modality (drug_test or alcohol_test) */
    protected string $modality;
    
    /** @var string Test type (pre_employment, random, post_accident, etc.) */
    protected string $testType;
    
    /** @var string|null Specimen ID (CCF number) */
    protected ?string $specimenId;
    
    /** @var string|null CCF (Custody and Control Form) number */
    protected ?string $ccfNumber;
    
    /** @var string|null Collector ID */
    protected ?string $collectorId;
    
    /** @var string|null Collector name */
    protected ?string $collectorName;
    
    /** @var DateTimeInterface|null Collection timestamp */
    protected ?DateTimeInterface $collectedAt;
    
    /** @var string|null Collection site */
    protected ?string $collectionSite;
    
    /** @var string|null Laboratory name */
    protected ?string $laboratory;
    
    /** @var DateTimeInterface|null Specimen received at lab timestamp */
    protected ?DateTimeInterface $receivedAtLab;
    
    /** @var array<string, mixed> Test results data */
    protected array $results;
    
    /** @var string Test status */
    protected string $status;
    
    /** @var bool MRO review required flag */
    protected bool $mroReviewRequired;
    
    /** @var string|null MRO (Medical Review Officer) ID */
    protected ?string $mroReviewedBy;
    
    /** @var DateTimeInterface|null MRO review timestamp */
    protected ?DateTimeInterface $mroReviewedAt;
    
    /** @var string|null MRO determination */
    protected ?string $mroDetermination;
    
    /** @var string|null MRO notes */
    protected ?string $mroNotes;
    
    /** @var string|null Final result */
    protected ?string $finalResult;
    
    /** @var bool Observed collection flag */
    protected bool $observedCollection;
    
    /** @var string|null Observation reason */
    protected ?string $observationReason;
    
    /** @var string|null Donor consent notes */
    protected ?string $donorConsentNotes;
    
    /** @var bool Split specimen flag */
    protected bool $splitSpecimen;
    
    /** @var string|null Split specimen status */
    protected ?string $splitSpecimenStatus;
    
    /** @var string|null Employer ID */
    protected ?string $employerId;
    
    /** @var string|null Employer name */
    protected ?string $employerName;
    
    /** @var string|null DER (Designated Employer Representative) contact */
    protected ?string $derContact;
    
    /** @var string|null Clinic ID */
    protected ?string $clinicId;
    
    /** @var DateTimeInterface|null Reported to employer timestamp */
    protected ?DateTimeInterface $reportedToEmployerAt;
    
    /** @var DateTimeInterface|null Entity creation timestamp */
    protected ?DateTimeInterface $createdAt;
    
    /** @var DateTimeInterface|null Entity update timestamp */
    protected ?DateTimeInterface $updatedAt;
    
    /** @var string|null Created by user ID */
    protected ?string $createdBy;

    /** Modality constants */
    public const MODALITY_DRUG = 'drug_test';
    public const MODALITY_ALCOHOL = 'alcohol_test';

    /** Test type constants (per 49 CFR Part 40) */
    public const TYPE_PRE_EMPLOYMENT = 'pre_employment';
    public const TYPE_RANDOM = 'random';
    public const TYPE_POST_ACCIDENT = 'post_accident';
    public const TYPE_REASONABLE_SUSPICION = 'reasonable_suspicion';
    public const TYPE_RETURN_TO_DUTY = 'return_to_duty';
    public const TYPE_FOLLOW_UP = 'follow_up';

    /** Status constants */
    public const STATUS_PENDING = 'pending';
    public const STATUS_COLLECTED = 'collected';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_AT_LAB = 'at_lab';
    public const STATUS_NEGATIVE = 'negative';
    public const STATUS_POSITIVE = 'positive';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_REFUSED = 'refused';

    /** MRO determination constants */
    public const MRO_NEGATIVE = 'negative';
    public const MRO_POSITIVE = 'positive';
    public const MRO_TEST_CANCELLED = 'test_cancelled';
    public const MRO_REFUSAL = 'refusal';

    /**
     * Create a new DotTest instance
     * 
     * @param string $patientId Patient ID
     * @param string $modality Test modality
     * @param string $testType Test type
     */
    public function __construct(
        string $patientId,
        string $modality = self::MODALITY_DRUG,
        string $testType = self::TYPE_PRE_EMPLOYMENT
    ) {
        $this->testId = null;
        $this->encounterId = null;
        $this->patientId = $patientId;
        $this->modality = $this->validateModality($modality);
        $this->testType = $this->validateTestType($testType);
        $this->specimenId = null;
        $this->ccfNumber = null;
        $this->collectorId = null;
        $this->collectorName = null;
        $this->collectedAt = null;
        $this->collectionSite = null;
        $this->laboratory = null;
        $this->receivedAtLab = null;
        $this->results = [];
        $this->status = self::STATUS_PENDING;
        $this->mroReviewRequired = false;
        $this->mroReviewedBy = null;
        $this->mroReviewedAt = null;
        $this->mroDetermination = null;
        $this->mroNotes = null;
        $this->finalResult = null;
        $this->observedCollection = false;
        $this->observationReason = null;
        $this->donorConsentNotes = null;
        $this->splitSpecimen = true;
        $this->splitSpecimenStatus = null;
        $this->employerId = null;
        $this->employerName = null;
        $this->derContact = null;
        $this->clinicId = null;
        $this->reportedToEmployerAt = null;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->createdBy = null;
    }

    /**
     * Validate modality
     * 
     * @param string $modality
     * @return string
     */
    private function validateModality(string $modality): string
    {
        $validModalities = [self::MODALITY_DRUG, self::MODALITY_ALCOHOL];
        return in_array($modality, $validModalities, true) ? $modality : self::MODALITY_DRUG;
    }

    /**
     * Validate test type
     * 
     * @param string $type
     * @return string
     */
    private function validateTestType(string $type): string
    {
        $validTypes = [
            self::TYPE_PRE_EMPLOYMENT,
            self::TYPE_RANDOM,
            self::TYPE_POST_ACCIDENT,
            self::TYPE_REASONABLE_SUSPICION,
            self::TYPE_RETURN_TO_DUTY,
            self::TYPE_FOLLOW_UP,
        ];
        return in_array($type, $validTypes, true) ? $type : self::TYPE_PRE_EMPLOYMENT;
    }

    /**
     * Get the test ID
     * 
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->testId;
    }

    /**
     * Set the test ID
     * 
     * @param string $testId
     * @return self
     */
    public function setId(string $testId): self
    {
        $this->testId = $testId;
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
     * Get modality
     * 
     * @return string
     */
    public function getModality(): string
    {
        return $this->modality;
    }

    /**
     * Get test type
     * 
     * @return string
     */
    public function getTestType(): string
    {
        return $this->testType;
    }

    /**
     * Set test type
     * 
     * @param string $type
     * @return self
     */
    public function setTestType(string $type): self
    {
        $this->testType = $this->validateTestType($type);
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get specimen ID
     * 
     * @return string|null
     */
    public function getSpecimenId(): ?string
    {
        return $this->specimenId;
    }

    /**
     * Set specimen ID
     * 
     * @param string $specimenId
     * @return self
     */
    public function setSpecimenId(string $specimenId): self
    {
        $this->specimenId = $specimenId;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get CCF number
     * 
     * @return string|null
     */
    public function getCcfNumber(): ?string
    {
        return $this->ccfNumber;
    }

    /**
     * Set CCF number
     * 
     * @param string $ccfNumber
     * @return self
     */
    public function setCcfNumber(string $ccfNumber): self
    {
        $this->ccfNumber = $ccfNumber;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Set collection information
     * 
     * @param string $collectorId
     * @param string $collectorName
     * @param string $collectionSite
     * @param DateTimeInterface|null $collectedAt
     * @return self
     */
    public function setCollectionInfo(
        string $collectorId,
        string $collectorName,
        string $collectionSite,
        ?DateTimeInterface $collectedAt = null
    ): self {
        $this->collectorId = $collectorId;
        $this->collectorName = $collectorName;
        $this->collectionSite = $collectionSite;
        $this->collectedAt = $collectedAt ?? new DateTimeImmutable();
        $this->status = self::STATUS_COLLECTED;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get collection timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getCollectedAt(): ?DateTimeInterface
    {
        return $this->collectedAt;
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
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Set results
     * 
     * @param array<string, mixed> $results
     * @return self
     */
    public function setResults(array $results): self
    {
        $this->results = $results;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Get results
     * 
     * @return array<string, mixed>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Check if MRO review is required
     * 
     * @return bool
     */
    public function isMroReviewRequired(): bool
    {
        return $this->mroReviewRequired;
    }

    /**
     * Set MRO review required
     * 
     * @param bool $required
     * @return self
     */
    public function setMroReviewRequired(bool $required): self
    {
        $this->mroReviewRequired = $required;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Record MRO review
     * 
     * @param string $mroId MRO user ID
     * @param string $determination MRO determination
     * @param string|null $notes MRO notes
     * @return self
     */
    public function recordMroReview(
        string $mroId,
        string $determination,
        ?string $notes = null
    ): self {
        $this->mroReviewedBy = $mroId;
        $this->mroReviewedAt = new DateTimeImmutable();
        $this->mroDetermination = $determination;
        $this->mroNotes = $notes;
        
        // Set final result based on MRO determination
        $this->finalResult = match ($determination) {
            self::MRO_NEGATIVE => self::STATUS_NEGATIVE,
            self::MRO_POSITIVE => self::STATUS_POSITIVE,
            self::MRO_TEST_CANCELLED => self::STATUS_CANCELLED,
            self::MRO_REFUSAL => self::STATUS_REFUSED,
            default => $determination,
        };
        
        $this->status = $this->finalResult;
        $this->updatedAt = new DateTimeImmutable();
        
        return $this;
    }

    /**
     * Get MRO determination
     * 
     * @return string|null
     */
    public function getMroDetermination(): ?string
    {
        return $this->mroDetermination;
    }

    /**
     * Check if test result is positive
     * 
     * @return bool
     */
    public function isPositive(): bool
    {
        return $this->status === self::STATUS_POSITIVE || 
               $this->mroDetermination === self::MRO_POSITIVE;
    }

    /**
     * Check if test result is negative
     * 
     * @return bool
     */
    public function isNegative(): bool
    {
        return $this->status === self::STATUS_NEGATIVE ||
               $this->mroDetermination === self::MRO_NEGATIVE;
    }

    /**
     * Set observed collection
     * 
     * @param bool $observed
     * @param string|null $reason
     * @return self
     */
    public function setObservedCollection(bool $observed, ?string $reason = null): self
    {
        $this->observedCollection = $observed;
        $this->observationReason = $reason;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Is observed collection
     * 
     * @return bool
     */
    public function isObservedCollection(): bool
    {
        return $this->observedCollection;
    }

    /**
     * Set employer information
     * 
     * @param string $employerId
     * @param string $employerName
     * @param string|null $derContact
     * @return self
     */
    public function setEmployerInfo(
        string $employerId,
        string $employerName,
        ?string $derContact = null
    ): self {
        $this->employerId = $employerId;
        $this->employerName = $employerName;
        $this->derContact = $derContact;
        $this->updatedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Record reporting to employer
     * 
     * @return self
     */
    public function recordReportedToEmployer(): self
    {
        $this->reportedToEmployerAt = new DateTimeImmutable();
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
        return $this->testId !== null;
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
            $errors['patient_id'] = 'Patient ID is required';
        }
        
        if ($this->status === self::STATUS_COLLECTED && empty($this->specimenId)) {
            $errors['specimen_id'] = 'Specimen ID is required for collected tests';
        }
        
        if ($this->status === self::STATUS_COLLECTED && empty($this->ccfNumber)) {
            $errors['ccf_number'] = 'CCF number is required for collected tests';
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
            'test_id' => $this->testId,
            'encounter_id' => $this->encounterId,
            'patient_id' => $this->patientId,
            'modality' => $this->modality,
            'test_type' => $this->testType,
            'specimen_id' => $this->specimenId,
            'ccf_number' => $this->ccfNumber,
            'collector_id' => $this->collectorId,
            'collector_name' => $this->collectorName,
            'collected_at' => $this->collectedAt?->format('Y-m-d H:i:s'),
            'collection_site' => $this->collectionSite,
            'laboratory' => $this->laboratory,
            'received_at_lab' => $this->receivedAtLab?->format('Y-m-d H:i:s'),
            'results' => $this->results,
            'status' => $this->status,
            'mro_review_required' => $this->mroReviewRequired,
            'mro_reviewed_by' => $this->mroReviewedBy,
            'mro_reviewed_at' => $this->mroReviewedAt?->format('Y-m-d H:i:s'),
            'mro_determination' => $this->mroDetermination,
            'mro_notes' => $this->mroNotes,
            'final_result' => $this->finalResult,
            'observed_collection' => $this->observedCollection,
            'observation_reason' => $this->observationReason,
            'split_specimen' => $this->splitSpecimen,
            'split_specimen_status' => $this->splitSpecimenStatus,
            'employer_id' => $this->employerId,
            'employer_name' => $this->employerName,
            'der_contact' => $this->derContact,
            'clinic_id' => $this->clinicId,
            'reported_to_employer_at' => $this->reportedToEmployerAt?->format('Y-m-d H:i:s'),
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
            'test_id' => $this->testId,
            'patient_id' => $this->patientId,
            'modality' => $this->modality,
            'test_type' => $this->testType,
            'status' => $this->status,
            'final_result' => $this->finalResult,
            'collected_at' => $this->collectedAt?->format('Y-m-d H:i:s'),
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
        $test = new static(
            $data['patient_id'],
            $data['modality'] ?? self::MODALITY_DRUG,
            $data['test_type'] ?? self::TYPE_PRE_EMPLOYMENT
        );
        
        if (isset($data['test_id'])) {
            $test->testId = $data['test_id'];
        }
        
        $test->encounterId = $data['encounter_id'] ?? null;
        $test->specimenId = $data['specimen_id'] ?? null;
        $test->ccfNumber = $data['ccf_number'] ?? null;
        $test->collectorId = $data['collector_id'] ?? null;
        $test->collectorName = $data['collector_name'] ?? null;
        
        if (isset($data['collected_at'])) {
            $test->collectedAt = new DateTimeImmutable($data['collected_at']);
        }
        
        $test->collectionSite = $data['collection_site'] ?? null;
        $test->laboratory = $data['laboratory'] ?? null;
        
        if (isset($data['received_at_lab'])) {
            $test->receivedAtLab = new DateTimeImmutable($data['received_at_lab']);
        }
        
        if (isset($data['results'])) {
            $test->results = is_array($data['results'])
                ? $data['results']
                : json_decode($data['results'], true) ?? [];
        }
        
        $test->status = $data['status'] ?? self::STATUS_PENDING;
        $test->mroReviewRequired = (bool) ($data['mro_review_required'] ?? false);
        $test->mroReviewedBy = $data['mro_reviewed_by'] ?? null;
        
        if (isset($data['mro_reviewed_at'])) {
            $test->mroReviewedAt = new DateTimeImmutable($data['mro_reviewed_at']);
        }
        
        $test->mroDetermination = $data['mro_determination'] ?? null;
        $test->mroNotes = $data['mro_notes'] ?? null;
        $test->finalResult = $data['final_result'] ?? null;
        $test->observedCollection = (bool) ($data['observed_collection'] ?? false);
        $test->observationReason = $data['observation_reason'] ?? null;
        $test->splitSpecimen = (bool) ($data['split_specimen'] ?? true);
        $test->splitSpecimenStatus = $data['split_specimen_status'] ?? null;
        $test->employerId = $data['employer_id'] ?? null;
        $test->employerName = $data['employer_name'] ?? null;
        $test->derContact = $data['der_contact'] ?? null;
        $test->clinicId = $data['clinic_id'] ?? null;
        
        if (isset($data['reported_to_employer_at'])) {
            $test->reportedToEmployerAt = new DateTimeImmutable($data['reported_to_employer_at']);
        }
        
        if (isset($data['created_at'])) {
            $test->createdAt = new DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $test->updatedAt = new DateTimeImmutable($data['updated_at']);
        }
        
        $test->createdBy = $data['created_by'] ?? null;
        
        return $test;
    }
}
