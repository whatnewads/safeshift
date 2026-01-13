<?php
/**
 * VideoMeetingFactory - Test Data Factory for Video Meetings
 * 
 * Creates mock video meeting data for testing purposes.
 * 
 * @package SafeShift\Tests\Helpers\Factories
 */

declare(strict_types=1);

namespace Tests\Helpers\Factories;

use Model\Entities\VideoMeeting;
use Model\Entities\MeetingParticipant;
use Model\Entities\MeetingChatMessage;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Factory for generating test video meeting data
 */
class VideoMeetingFactory
{
    /** @var int Counter for unique IDs */
    private static int $idCounter = 1;
    
    /** @var array<string> Sample display names */
    private const DISPLAY_NAMES = [
        'Dr. Smith',
        'John Patient',
        'Nurse Johnson',
        'Dr. Williams',
        'Jane Doe',
        'Care Coordinator',
        'Dr. Brown',
        'Patient Support',
    ];

    /**
     * Create a VideoMeeting entity
     * 
     * @param array<string, mixed> $overrides
     * @return VideoMeeting
     */
    public static function create(array $overrides = []): VideoMeeting
    {
        $defaults = [
            'created_by' => $overrides['created_by'] ?? random_int(1, 100),
            'token' => $overrides['token'] ?? self::createToken(),
            'token_expires_at' => $overrides['token_expires_at'] ?? new DateTimeImmutable('+24 hours'),
        ];
        
        $meeting = new VideoMeeting(
            $defaults['created_by'],
            $defaults['token'],
            $defaults['token_expires_at']
        );
        
        if (isset($overrides['meeting_id'])) {
            $meeting->setMeetingId($overrides['meeting_id']);
        } else {
            $meeting->setMeetingId(self::$idCounter++);
        }
        
        if (isset($overrides['is_active'])) {
            $meeting->setIsActive($overrides['is_active']);
        }
        
        if (isset($overrides['ended_at'])) {
            $meeting->setEndedAt($overrides['ended_at']);
        }
        
        if (isset($overrides['created_at'])) {
            $meeting->setCreatedAt($overrides['created_at']);
        }
        
        return $meeting;
    }

    /**
     * Create a VideoMeeting with participants
     * 
     * @param int $participantCount Number of participants to create
     * @param array<string, mixed> $meetingOverrides
     * @return array{meeting: VideoMeeting, participants: array<MeetingParticipant>}
     */
    public static function createWithParticipants(
        int $participantCount = 2,
        array $meetingOverrides = []
    ): array {
        $meeting = self::create($meetingOverrides);
        $participants = [];
        
        for ($i = 0; $i < $participantCount; $i++) {
            $participants[] = self::createParticipant([
                'meeting_id' => $meeting->getMeetingId(),
                'display_name' => self::DISPLAY_NAMES[$i % count(self::DISPLAY_NAMES)],
            ]);
        }
        
        return [
            'meeting' => $meeting,
            'participants' => $participants,
        ];
    }

    /**
     * Create an expired meeting (token expired)
     * 
     * @param array<string, mixed> $overrides
     * @return VideoMeeting
     */
    public static function createExpiredMeeting(array $overrides = []): VideoMeeting
    {
        return self::create(array_merge([
            'token_expires_at' => new DateTimeImmutable('-1 hour'),
        ], $overrides));
    }

    /**
     * Create an ended meeting
     * 
     * @param array<string, mixed> $overrides
     * @return VideoMeeting
     */
    public static function createEndedMeeting(array $overrides = []): VideoMeeting
    {
        return self::create(array_merge([
            'is_active' => false,
            'ended_at' => new DateTimeImmutable('-30 minutes'),
        ], $overrides));
    }

    /**
     * Create an active meeting
     * 
     * @param array<string, mixed> $overrides
     * @return VideoMeeting
     */
    public static function createActiveMeeting(array $overrides = []): VideoMeeting
    {
        return self::create(array_merge([
            'is_active' => true,
            'token_expires_at' => new DateTimeImmutable('+24 hours'),
        ], $overrides));
    }

    /**
     * Create a cryptographically secure token (64 hex characters)
     * 
     * @return string
     */
    public static function createToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create a MeetingParticipant entity
     * 
     * @param array<string, mixed> $overrides
     * @return MeetingParticipant
     */
    public static function createParticipant(array $overrides = []): MeetingParticipant
    {
        $defaults = [
            'meeting_id' => $overrides['meeting_id'] ?? random_int(1, 100),
            'display_name' => $overrides['display_name'] ?? self::DISPLAY_NAMES[array_rand(self::DISPLAY_NAMES)],
            'ip_address' => $overrides['ip_address'] ?? '192.168.1.' . random_int(1, 254),
        ];
        
        $participant = new MeetingParticipant(
            $defaults['meeting_id'],
            $defaults['display_name'],
            $defaults['ip_address']
        );
        
        if (isset($overrides['participant_id'])) {
            $participant->setParticipantId($overrides['participant_id']);
        } else {
            $participant->setParticipantId(self::$idCounter++);
        }
        
        if (isset($overrides['joined_at'])) {
            $participant->setJoinedAt($overrides['joined_at']);
        }
        
        if (isset($overrides['left_at'])) {
            $participant->setLeftAt($overrides['left_at']);
        }
        
        return $participant;
    }

    /**
     * Create a participant who has left the meeting
     * 
     * @param array<string, mixed> $overrides
     * @return MeetingParticipant
     */
    public static function createLeftParticipant(array $overrides = []): MeetingParticipant
    {
        return self::createParticipant(array_merge([
            'joined_at' => new DateTimeImmutable('-30 minutes'),
            'left_at' => new DateTimeImmutable('-5 minutes'),
        ], $overrides));
    }

    /**
     * Create a MeetingChatMessage entity
     * 
     * @param array<string, mixed> $overrides
     * @return MeetingChatMessage
     */
    public static function createChatMessage(array $overrides = []): MeetingChatMessage
    {
        $defaults = [
            'meeting_id' => $overrides['meeting_id'] ?? random_int(1, 100),
            'participant_id' => $overrides['participant_id'] ?? random_int(1, 100),
            'message_text' => $overrides['message_text'] ?? 'Test message ' . uniqid(),
        ];
        
        $message = new MeetingChatMessage(
            $defaults['meeting_id'],
            $defaults['participant_id'],
            $defaults['message_text']
        );
        
        if (isset($overrides['message_id'])) {
            $message->setMessageId($overrides['message_id']);
        } else {
            $message->setMessageId(self::$idCounter++);
        }
        
        if (isset($overrides['sent_at'])) {
            $message->setSentAt($overrides['sent_at']);
        }
        
        return $message;
    }

    /**
     * Create multiple chat messages for a meeting
     * 
     * @param int $meetingId
     * @param int $participantId
     * @param int $count
     * @return array<MeetingChatMessage>
     */
    public static function createChatMessages(
        int $meetingId,
        int $participantId,
        int $count = 5
    ): array {
        $messages = [];
        $baseTime = new DateTimeImmutable('-10 minutes');
        
        for ($i = 0; $i < $count; $i++) {
            $messages[] = self::createChatMessage([
                'meeting_id' => $meetingId,
                'participant_id' => $participantId,
                'message_text' => "Test message {$i}",
                'sent_at' => $baseTime->modify("+{$i} minute"),
            ]);
        }
        
        return $messages;
    }

    /**
     * Create meeting array data (for database mock results)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createMeetingArray(array $overrides = []): array
    {
        $token = $overrides['token'] ?? self::createToken();
        $tokenExpiresAt = $overrides['token_expires_at'] ?? new DateTimeImmutable('+24 hours');
        $createdAt = $overrides['created_at'] ?? new DateTimeImmutable();
        
        return array_merge([
            'meeting_id' => self::$idCounter++,
            'created_by' => random_int(1, 100),
            'token' => $token,
            'token_expires_at' => $tokenExpiresAt instanceof DateTimeInterface 
                ? $tokenExpiresAt->format('Y-m-d H:i:s')
                : $tokenExpiresAt,
            'is_active' => true,
            'ended_at' => null,
            'created_at' => $createdAt instanceof DateTimeInterface
                ? $createdAt->format('Y-m-d H:i:s')
                : $createdAt,
        ], $overrides);
    }

    /**
     * Create participant array data (for database mock results)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createParticipantArray(array $overrides = []): array
    {
        $joinedAt = $overrides['joined_at'] ?? new DateTimeImmutable();
        
        return array_merge([
            'participant_id' => self::$idCounter++,
            'meeting_id' => random_int(1, 100),
            'display_name' => self::DISPLAY_NAMES[array_rand(self::DISPLAY_NAMES)],
            'joined_at' => $joinedAt instanceof DateTimeInterface
                ? $joinedAt->format('Y-m-d H:i:s')
                : $joinedAt,
            'left_at' => null,
            'ip_address' => '192.168.1.' . random_int(1, 254),
        ], $overrides);
    }

    /**
     * Create chat message array data (for database mock results)
     * 
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createChatMessageArray(array $overrides = []): array
    {
        $sentAt = $overrides['sent_at'] ?? new DateTimeImmutable();
        
        return array_merge([
            'message_id' => self::$idCounter++,
            'meeting_id' => random_int(1, 100),
            'participant_id' => random_int(1, 100),
            'message_text' => 'Test message ' . uniqid(),
            'sent_at' => $sentAt instanceof DateTimeInterface
                ? $sentAt->format('Y-m-d H:i:s')
                : $sentAt,
        ], $overrides);
    }

    /**
     * Generate an invalid token (wrong length or format)
     * 
     * @param string $type Type of invalid token: 'short', 'long', 'invalid_chars'
     * @return string
     */
    public static function createInvalidToken(string $type = 'short'): string
    {
        return match ($type) {
            'short' => bin2hex(random_bytes(16)),  // 32 chars instead of 64
            'long' => bin2hex(random_bytes(64)),   // 128 chars instead of 64
            'invalid_chars' => 'GHIJ' . bin2hex(random_bytes(30)), // Invalid hex chars
            default => 'invalid',
        };
    }

    /**
     * Reset the ID counter (useful between test methods)
     */
    public static function resetIdCounter(): void
    {
        self::$idCounter = 1;
    }

    /**
     * Generate XSS attack payload for testing sanitization
     * 
     * @param string $type Type of XSS: 'script', 'img', 'event'
     * @return string
     */
    public static function createXSSPayload(string $type = 'script'): string
    {
        return match ($type) {
            'script' => '<script>alert("XSS")</script>',
            'img' => '<img src="x" onerror="alert(\'XSS\')">',
            'event' => '<div onmouseover="alert(\'XSS\')">test</div>',
            'link' => '<a href="javascript:alert(\'XSS\')">click</a>',
            default => '<script>alert("XSS")</script>',
        };
    }

    /**
     * Generate SQL injection payload for testing
     * 
     * @param string $type Type of SQL injection
     * @return string
     */
    public static function createSQLInjectionPayload(string $type = 'basic'): string
    {
        return match ($type) {
            'basic' => "'; DROP TABLE video_meetings; --",
            'union' => "' UNION SELECT * FROM users --",
            'boolean' => "' OR '1'='1",
            'comment' => "admin'--",
            default => "'; DROP TABLE video_meetings; --",
        };
    }

    /**
     * Create a user array suitable for session data
     * 
     * @param string $role User role
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createUserForSession(string $role = 'pclinician', array $overrides = []): array
    {
        return array_merge([
            'user_id' => random_int(1, 100),
            'username' => 'test_user_' . uniqid(),
            'email' => 'test_' . uniqid() . '@test.safeshift.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => $role,
            'primary_role' => $role,
            'status' => 'active',
        ], $overrides);
    }
}
