<?php
/**
 * MeetingParticipant.php - Meeting Participant Entity for SafeShift EHR
 * 
 * Represents a participant in a video meeting session.
 * Tracks join/leave times and participant identity.
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
 * MeetingParticipant entity
 * 
 * Represents a participant in a video meeting in the SafeShift EHR system.
 * Tracks participant lifecycle and identification.
 */
class MeetingParticipant implements EntityInterface
{
    /** @var int|null Participant unique identifier */
    protected ?int $participantId;
    
    /** @var int Reference to video_meetings table */
    protected int $meetingId;
    
    /** @var string Participant display name in meeting */
    protected string $displayName;
    
    /** @var DateTimeInterface|null Timestamp when participant joined */
    protected ?DateTimeInterface $joinedAt;
    
    /** @var DateTimeInterface|null Timestamp when participant left */
    protected ?DateTimeInterface $leftAt;
    
    /** @var string|null Participant IP address (supports IPv6) */
    protected ?string $ipAddress;

    /**
     * Create a new MeetingParticipant instance
     * 
     * @param int $meetingId Reference to video meeting
     * @param string $displayName Participant display name
     * @param string|null $ipAddress Participant IP address
     */
    public function __construct(
        int $meetingId,
        string $displayName,
        ?string $ipAddress = null
    ) {
        $this->participantId = null;
        $this->meetingId = $meetingId;
        $this->displayName = $displayName;
        $this->joinedAt = new DateTimeImmutable();
        $this->leftAt = null;
        $this->ipAddress = $ipAddress;
    }

    /**
     * Get the participant ID
     * 
     * @return string|null Returns participant ID as string for EntityInterface compatibility
     */
    public function getId(): ?string
    {
        return $this->participantId !== null ? (string) $this->participantId : null;
    }

    /**
     * Get the participant ID as integer
     * 
     * @return int|null
     */
    public function getParticipantId(): ?int
    {
        return $this->participantId;
    }

    /**
     * Set the participant ID
     * 
     * @param int $participantId
     * @return self
     */
    public function setParticipantId(int $participantId): self
    {
        $this->participantId = $participantId;
        return $this;
    }

    /**
     * Get the meeting ID
     * 
     * @return int
     */
    public function getMeetingId(): int
    {
        return $this->meetingId;
    }

    /**
     * Set the meeting ID
     * 
     * @param int $meetingId
     * @return self
     */
    public function setMeetingId(int $meetingId): self
    {
        $this->meetingId = $meetingId;
        return $this;
    }

    /**
     * Get the display name
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Set the display name
     * 
     * @param string $displayName
     * @return self
     */
    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;
        return $this;
    }

    /**
     * Get the join timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getJoinedAt(): ?DateTimeInterface
    {
        return $this->joinedAt;
    }

    /**
     * Set the join timestamp
     * 
     * @param DateTimeInterface $joinedAt
     * @return self
     */
    public function setJoinedAt(DateTimeInterface $joinedAt): self
    {
        $this->joinedAt = $joinedAt;
        return $this;
    }

    /**
     * Get the left timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getLeftAt(): ?DateTimeInterface
    {
        return $this->leftAt;
    }

    /**
     * Set the left timestamp
     * 
     * @param DateTimeInterface|null $leftAt
     * @return self
     */
    public function setLeftAt(?DateTimeInterface $leftAt): self
    {
        $this->leftAt = $leftAt;
        return $this;
    }

    /**
     * Mark participant as left
     * 
     * @return self
     */
    public function leave(): self
    {
        $this->leftAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if participant is currently in meeting
     * 
     * @return bool
     */
    public function isInMeeting(): bool
    {
        return $this->leftAt === null;
    }

    /**
     * Get the IP address
     * 
     * @return string|null
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * Set the IP address
     * 
     * @param string|null $ipAddress
     * @return self
     */
    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    /**
     * Get creation timestamp (alias for joinedAt)
     * 
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->joinedAt;
    }

    /**
     * Get update timestamp (returns leftAt or joinedAt)
     * 
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->leftAt ?? $this->joinedAt;
    }

    /**
     * Check if entity has been persisted
     * 
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->participantId !== null;
    }

    /**
     * Calculate session duration in seconds
     * 
     * @return int|null Null if still in meeting
     */
    public function getSessionDuration(): ?int
    {
        if ($this->leftAt === null || $this->joinedAt === null) {
            return null;
        }
        
        return $this->leftAt->getTimestamp() - $this->joinedAt->getTimestamp();
    }

    /**
     * Validate entity data
     * 
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];
        
        if ($this->meetingId <= 0) {
            $errors['meeting_id'] = 'Meeting ID is required';
        }
        
        if (empty(trim($this->displayName))) {
            $errors['display_name'] = 'Display name is required';
        } elseif (strlen($this->displayName) > 100) {
            $errors['display_name'] = 'Display name must be 100 characters or less';
        }
        
        // Validate IP address format if provided
        if ($this->ipAddress !== null && !filter_var($this->ipAddress, FILTER_VALIDATE_IP)) {
            $errors['ip_address'] = 'Invalid IP address format';
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
            'participant_id' => $this->participantId,
            'meeting_id' => $this->meetingId,
            'display_name' => $this->displayName,
            'joined_at' => $this->joinedAt?->format('Y-m-d H:i:s'),
            'left_at' => $this->leftAt?->format('Y-m-d H:i:s'),
            'ip_address' => $this->ipAddress,
        ];
    }

    /**
     * Convert to safe array (excludes IP address for privacy)
     * 
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'participant_id' => $this->participantId,
            'meeting_id' => $this->meetingId,
            'display_name' => $this->displayName,
            'joined_at' => $this->joinedAt?->format('Y-m-d H:i:s'),
            'left_at' => $this->leftAt?->format('Y-m-d H:i:s'),
            'is_in_meeting' => $this->isInMeeting(),
            'session_duration' => $this->getSessionDuration(),
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
        $participant = new static(
            (int) $data['meeting_id'],
            $data['display_name'],
            $data['ip_address'] ?? null
        );
        
        if (isset($data['participant_id'])) {
            $participant->participantId = (int) $data['participant_id'];
        }
        
        if (isset($data['joined_at'])) {
            $participant->joinedAt = $data['joined_at'] instanceof DateTimeInterface
                ? $data['joined_at']
                : new DateTimeImmutable($data['joined_at']);
        }
        
        if (isset($data['left_at']) && $data['left_at'] !== null) {
            $participant->leftAt = $data['left_at'] instanceof DateTimeInterface
                ? $data['left_at']
                : new DateTimeImmutable($data['left_at']);
        }
        
        return $participant;
    }
}
