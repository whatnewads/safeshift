<?php
/**
 * VideoMeeting.php - Video Meeting Entity for SafeShift EHR
 * 
 * Represents a video meeting session for WebRTC telehealth functionality.
 * Handles token-based access and meeting lifecycle management.
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
 * VideoMeeting entity
 * 
 * Represents a video meeting in the SafeShift EHR system.
 * Manages meeting tokens, status, and lifecycle events.
 */
class VideoMeeting implements EntityInterface
{
    /** @var int|null Meeting unique identifier */
    protected ?int $meetingId;
    
    /** @var string User ID (UUID) of meeting creator (clinician) */
    protected string $createdBy;
    
    /** @var DateTimeInterface|null Meeting creation timestamp */
    protected ?DateTimeInterface $createdAt;
    
    /** @var string Unique secure token for meeting access */
    protected string $token;
    
    /** @var DateTimeInterface Token expiration timestamp */
    protected DateTimeInterface $tokenExpiresAt;
    
    /** @var bool Whether meeting is currently active */
    protected bool $isActive;
    
    /** @var DateTimeInterface|null Meeting end timestamp */
    protected ?DateTimeInterface $endedAt;

    /**
     * Create a new VideoMeeting instance
     *
     * @param string $createdBy User ID (UUID) of meeting creator
     * @param string $token Unique meeting token
     * @param DateTimeInterface $tokenExpiresAt Token expiration timestamp
     */
    public function __construct(
        string $createdBy,
        string $token,
        DateTimeInterface $tokenExpiresAt
    ) {
        $this->meetingId = null;
        $this->createdBy = $createdBy;
        $this->createdAt = new DateTimeImmutable();
        $this->token = $token;
        $this->tokenExpiresAt = $tokenExpiresAt;
        $this->isActive = true;
        $this->endedAt = null;
    }

    /**
     * Get the meeting ID
     * 
     * @return string|null Returns meeting ID as string for EntityInterface compatibility
     */
    public function getId(): ?string
    {
        return $this->meetingId !== null ? (string) $this->meetingId : null;
    }

    /**
     * Get the meeting ID as integer
     * 
     * @return int|null
     */
    public function getMeetingId(): ?int
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
     * Get the creator user ID (UUID)
     *
     * @return string
     */
    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    /**
     * Set the creator user ID (UUID)
     *
     * @param string $createdBy
     * @return self
     */
    public function setCreatedBy(string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Get the meeting token
     * 
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Set the meeting token
     * 
     * @param string $token
     * @return self
     */
    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Get the token expiration timestamp
     * 
     * @return DateTimeInterface
     */
    public function getTokenExpiresAt(): DateTimeInterface
    {
        return $this->tokenExpiresAt;
    }

    /**
     * Set the token expiration timestamp
     * 
     * @param DateTimeInterface $tokenExpiresAt
     * @return self
     */
    public function setTokenExpiresAt(DateTimeInterface $tokenExpiresAt): self
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
        return $this;
    }

    /**
     * Check if meeting is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Set meeting active status
     * 
     * @param bool $isActive
     * @return self
     */
    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Get the meeting end timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getEndedAt(): ?DateTimeInterface
    {
        return $this->endedAt;
    }

    /**
     * Set the meeting end timestamp
     * 
     * @param DateTimeInterface|null $endedAt
     * @return self
     */
    public function setEndedAt(?DateTimeInterface $endedAt): self
    {
        $this->endedAt = $endedAt;
        return $this;
    }

    /**
     * End the meeting
     * 
     * @return self
     */
    public function endMeeting(): self
    {
        $this->isActive = false;
        $this->endedAt = new DateTimeImmutable();
        return $this;
    }

    /**
     * Check if token is expired
     * 
     * @return bool
     */
    public function isTokenExpired(): bool
    {
        return $this->tokenExpiresAt < new DateTimeImmutable();
    }

    /**
     * Check if meeting can be joined
     * 
     * @return bool
     */
    public function canJoin(): bool
    {
        return $this->isActive && !$this->isTokenExpired();
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
     * Set creation timestamp
     * 
     * @param DateTimeInterface $createdAt
     * @return self
     */
    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get update timestamp (not used for this entity but required by interface)
     * 
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->createdAt; // Video meetings don't track updates separately
    }

    /**
     * Check if entity has been persisted
     * 
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->meetingId !== null;
    }

    /**
     * Validate entity data
     * 
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->createdBy)) {
            $errors['created_by'] = 'Creator user ID is required';
        }
        
        if (empty($this->token)) {
            $errors['token'] = 'Meeting token is required';
        } elseif (strlen($this->token) < 32) {
            $errors['token'] = 'Meeting token must be at least 32 characters';
        }
        
        if ($this->tokenExpiresAt <= new DateTimeImmutable()) {
            $errors['token_expires_at'] = 'Token expiration must be in the future';
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
            'meeting_id' => $this->meetingId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'token' => $this->token,
            'token_expires_at' => $this->tokenExpiresAt->format('Y-m-d H:i:s'),
            'is_active' => $this->isActive,
            'ended_at' => $this->endedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert to safe array (excludes sensitive token for external exposure)
     * 
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'meeting_id' => $this->meetingId,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'is_active' => $this->isActive,
            'ended_at' => $this->endedAt?->format('Y-m-d H:i:s'),
            'can_join' => $this->canJoin(),
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
        $tokenExpiresAt = $data['token_expires_at'] instanceof DateTimeInterface
            ? $data['token_expires_at']
            : new DateTimeImmutable($data['token_expires_at']);
            
        $meeting = new static(
            (string) $data['created_by'],
            $data['token'],
            $tokenExpiresAt
        );
        
        if (isset($data['meeting_id'])) {
            $meeting->meetingId = (int) $data['meeting_id'];
        }
        
        if (isset($data['created_at'])) {
            $meeting->createdAt = $data['created_at'] instanceof DateTimeInterface
                ? $data['created_at']
                : new DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['is_active'])) {
            $meeting->isActive = (bool) $data['is_active'];
        }
        
        if (isset($data['ended_at']) && $data['ended_at'] !== null) {
            $meeting->endedAt = $data['ended_at'] instanceof DateTimeInterface
                ? $data['ended_at']
                : new DateTimeImmutable($data['ended_at']);
        }
        
        return $meeting;
    }
}
