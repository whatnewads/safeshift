<?php
/**
 * MeetingChatMessage.php - Meeting Chat Message Entity for SafeShift EHR
 * 
 * Represents a chat message sent during a video meeting session.
 * Stores message content and sender information for audit purposes.
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
 * MeetingChatMessage entity
 * 
 * Represents a chat message in a video meeting in the SafeShift EHR system.
 * Used for in-meeting communication and audit trail.
 */
class MeetingChatMessage implements EntityInterface
{
    /** @var int|null Message unique identifier */
    protected ?int $messageId;
    
    /** @var int Reference to video_meetings table */
    protected int $meetingId;
    
    /** @var int Reference to meeting_participants table */
    protected int $participantId;
    
    /** @var string Chat message content */
    protected string $messageText;
    
    /** @var DateTimeInterface|null Message sent timestamp */
    protected ?DateTimeInterface $sentAt;

    /**
     * Create a new MeetingChatMessage instance
     * 
     * @param int $meetingId Reference to video meeting
     * @param int $participantId Reference to meeting participant
     * @param string $messageText Chat message content
     */
    public function __construct(
        int $meetingId,
        int $participantId,
        string $messageText
    ) {
        $this->messageId = null;
        $this->meetingId = $meetingId;
        $this->participantId = $participantId;
        $this->messageText = $messageText;
        $this->sentAt = new DateTimeImmutable();
    }

    /**
     * Get the message ID
     * 
     * @return string|null Returns message ID as string for EntityInterface compatibility
     */
    public function getId(): ?string
    {
        return $this->messageId !== null ? (string) $this->messageId : null;
    }

    /**
     * Get the message ID as integer
     * 
     * @return int|null
     */
    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    /**
     * Set the message ID
     * 
     * @param int $messageId
     * @return self
     */
    public function setMessageId(int $messageId): self
    {
        $this->messageId = $messageId;
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
     * Get the participant ID
     * 
     * @return int
     */
    public function getParticipantId(): int
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
     * Get the message text
     * 
     * @return string
     */
    public function getMessageText(): string
    {
        return $this->messageText;
    }

    /**
     * Set the message text
     * 
     * @param string $messageText
     * @return self
     */
    public function setMessageText(string $messageText): self
    {
        $this->messageText = $messageText;
        return $this;
    }

    /**
     * Get the sent timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getSentAt(): ?DateTimeInterface
    {
        return $this->sentAt;
    }

    /**
     * Set the sent timestamp
     * 
     * @param DateTimeInterface $sentAt
     * @return self
     */
    public function setSentAt(DateTimeInterface $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    /**
     * Get creation timestamp (alias for sentAt)
     * 
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->sentAt;
    }

    /**
     * Get update timestamp (same as sentAt for immutable messages)
     * 
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->sentAt;
    }

    /**
     * Check if entity has been persisted
     * 
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->messageId !== null;
    }

    /**
     * Get message length
     * 
     * @return int
     */
    public function getMessageLength(): int
    {
        return strlen($this->messageText);
    }

    /**
     * Check if message is empty
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty(trim($this->messageText));
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
        
        if ($this->participantId <= 0) {
            $errors['participant_id'] = 'Participant ID is required';
        }
        
        if (empty(trim($this->messageText))) {
            $errors['message_text'] = 'Message text cannot be empty';
        }
        
        // Limit message length to prevent abuse (64KB max for TEXT field)
        if (strlen($this->messageText) > 65535) {
            $errors['message_text'] = 'Message text exceeds maximum length';
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
            'message_id' => $this->messageId,
            'meeting_id' => $this->meetingId,
            'participant_id' => $this->participantId,
            'message_text' => $this->messageText,
            'sent_at' => $this->sentAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert to safe array (same as toArray for chat messages)
     * 
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'meeting_id' => $this->meetingId,
            'participant_id' => $this->participantId,
            'message_text' => $this->messageText,
            'sent_at' => $this->sentAt?->format('Y-m-d H:i:s'),
            'message_length' => $this->getMessageLength(),
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
        $message = new static(
            (int) $data['meeting_id'],
            (int) $data['participant_id'],
            $data['message_text']
        );
        
        if (isset($data['message_id'])) {
            $message->messageId = (int) $data['message_id'];
        }
        
        if (isset($data['sent_at'])) {
            $message->sentAt = $data['sent_at'] instanceof DateTimeInterface
                ? $data['sent_at']
                : new DateTimeImmutable($data['sent_at']);
        }
        
        return $message;
    }
}
