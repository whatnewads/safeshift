<?php
/**
 * VideoMeetingEntityTest - Unit Tests for Video Meeting Entity Classes
 * 
 * Tests for VideoMeeting, MeetingParticipant, and MeetingChatMessage entities.
 * Covers data mapping, serialization, and validation logic.
 * 
 * @package SafeShift\Tests\Unit\Entities
 */

declare(strict_types=1);

namespace Tests\Unit\Entities;

use PHPUnit\Framework\TestCase;
use Model\Entities\VideoMeeting;
use Model\Entities\MeetingParticipant;
use Model\Entities\MeetingChatMessage;
use Tests\Helpers\Factories\VideoMeetingFactory;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * @covers \Model\Entities\VideoMeeting
 * @covers \Model\Entities\MeetingParticipant
 * @covers \Model\Entities\MeetingChatMessage
 */
class VideoMeetingEntityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        VideoMeetingFactory::resetIdCounter();
    }

    // =========================================================================
    // VideoMeeting Entity Tests
    // =========================================================================

    /**
     * @test
     */
    public function testFromArray_MapsAllFields(): void
    {
        $data = [
            'meeting_id' => 123,
            'created_by' => 45,
            'created_at' => '2026-01-10 14:30:00',
            'token' => str_repeat('a', 64),
            'token_expires_at' => '2026-01-11 14:30:00',
            'is_active' => true,
            'ended_at' => null,
        ];

        $meeting = VideoMeeting::fromArray($data);

        $this->assertEquals(123, $meeting->getMeetingId());
        $this->assertEquals(45, $meeting->getCreatedBy());
        $this->assertEquals(str_repeat('a', 64), $meeting->getToken());
        $this->assertTrue($meeting->isActive());
        $this->assertNull($meeting->getEndedAt());
        $this->assertInstanceOf(DateTimeInterface::class, $meeting->getCreatedAt());
        $this->assertInstanceOf(DateTimeInterface::class, $meeting->getTokenExpiresAt());
    }

    /**
     * @test
     */
    public function testFromArray_WithEndedAt(): void
    {
        $data = [
            'meeting_id' => 123,
            'created_by' => 45,
            'created_at' => '2026-01-10 14:30:00',
            'token' => str_repeat('b', 64),
            'token_expires_at' => '2026-01-11 14:30:00',
            'is_active' => false,
            'ended_at' => '2026-01-10 15:00:00',
        ];

        $meeting = VideoMeeting::fromArray($data);

        $this->assertFalse($meeting->isActive());
        $this->assertNotNull($meeting->getEndedAt());
        $this->assertInstanceOf(DateTimeInterface::class, $meeting->getEndedAt());
    }

    /**
     * @test
     */
    public function testToArray_IncludesAllFields(): void
    {
        $meeting = VideoMeetingFactory::create([
            'meeting_id' => 100,
            'created_by' => 50,
        ]);

        $array = $meeting->toArray();

        $this->assertArrayHasKey('meeting_id', $array);
        $this->assertArrayHasKey('created_by', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('token', $array);
        $this->assertArrayHasKey('token_expires_at', $array);
        $this->assertArrayHasKey('is_active', $array);
        $this->assertArrayHasKey('ended_at', $array);
        
        $this->assertEquals(100, $array['meeting_id']);
        $this->assertEquals(50, $array['created_by']);
        $this->assertNotEmpty($array['token']);
    }

    /**
     * @test
     */
    public function testToSafeArray_ExcludesToken(): void
    {
        $meeting = VideoMeetingFactory::create();
        
        $safeArray = $meeting->toSafeArray();
        
        $this->assertArrayHasKey('meeting_id', $safeArray);
        $this->assertArrayHasKey('created_by', $safeArray);
        $this->assertArrayHasKey('created_at', $safeArray);
        $this->assertArrayHasKey('is_active', $safeArray);
        $this->assertArrayHasKey('ended_at', $safeArray);
        $this->assertArrayHasKey('can_join', $safeArray);
        
        // Token should NOT be in safe array
        $this->assertArrayNotHasKey('token', $safeArray);
        $this->assertArrayNotHasKey('token_expires_at', $safeArray);
    }

    /**
     * @test
     */
    public function testIsActive_ReturnsTrueWhenActive(): void
    {
        $meeting = VideoMeetingFactory::createActiveMeeting();
        
        $this->assertTrue($meeting->isActive());
    }

    /**
     * @test
     */
    public function testIsActive_ReturnsFalseWhenInactive(): void
    {
        $meeting = VideoMeetingFactory::createEndedMeeting();
        
        $this->assertFalse($meeting->isActive());
    }

    /**
     * @test
     */
    public function testIsTokenExpired_ReturnsTrueWhenExpired(): void
    {
        $meeting = VideoMeetingFactory::createExpiredMeeting();
        
        $this->assertTrue($meeting->isTokenExpired());
    }

    /**
     * @test
     */
    public function testIsTokenExpired_ReturnsFalseWhenValid(): void
    {
        $meeting = VideoMeetingFactory::createActiveMeeting();
        
        $this->assertFalse($meeting->isTokenExpired());
    }

    /**
     * @test
     */
    public function testGetId_ReturnsCorrectId(): void
    {
        $meeting = VideoMeetingFactory::create(['meeting_id' => 456]);
        
        $this->assertEquals('456', $meeting->getId());
        $this->assertEquals(456, $meeting->getMeetingId());
    }

    /**
     * @test
     */
    public function testGetId_ReturnsNullWhenNotPersisted(): void
    {
        $meeting = new VideoMeeting(
            1,
            VideoMeetingFactory::createToken(),
            new DateTimeImmutable('+24 hours')
        );
        
        $this->assertNull($meeting->getId());
        $this->assertNull($meeting->getMeetingId());
        $this->assertFalse($meeting->isPersisted());
    }

    /**
     * @test
     */
    public function testCanJoin_ReturnsTrueWhenActiveAndNotExpired(): void
    {
        $meeting = VideoMeetingFactory::create([
            'is_active' => true,
            'token_expires_at' => new DateTimeImmutable('+24 hours'),
        ]);
        
        $this->assertTrue($meeting->canJoin());
    }

    /**
     * @test
     */
    public function testCanJoin_ReturnsFalseWhenExpired(): void
    {
        $meeting = VideoMeetingFactory::createExpiredMeeting();
        
        $this->assertFalse($meeting->canJoin());
    }

    /**
     * @test
     */
    public function testCanJoin_ReturnsFalseWhenInactive(): void
    {
        $meeting = VideoMeetingFactory::createEndedMeeting();
        
        $this->assertFalse($meeting->canJoin());
    }

    /**
     * @test
     */
    public function testEndMeeting_SetsInactiveAndEndedAt(): void
    {
        $meeting = VideoMeetingFactory::createActiveMeeting();
        $this->assertTrue($meeting->isActive());
        $this->assertNull($meeting->getEndedAt());
        
        $meeting->endMeeting();
        
        $this->assertFalse($meeting->isActive());
        $this->assertNotNull($meeting->getEndedAt());
        $this->assertInstanceOf(DateTimeInterface::class, $meeting->getEndedAt());
    }

    /**
     * @test
     */
    public function testValidate_ReturnsEmptyArrayWhenValid(): void
    {
        $meeting = VideoMeetingFactory::createActiveMeeting();
        
        $errors = $meeting->validate();
        
        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function testValidate_ReturnsErrorForInvalidCreator(): void
    {
        $meeting = new VideoMeeting(
            0, // Invalid: must be > 0
            VideoMeetingFactory::createToken(),
            new DateTimeImmutable('+24 hours')
        );
        
        $errors = $meeting->validate();
        
        $this->assertArrayHasKey('created_by', $errors);
    }

    /**
     * @test
     */
    public function testValidate_ReturnsErrorForShortToken(): void
    {
        $meeting = new VideoMeeting(
            1,
            'short_token', // Invalid: less than 32 chars
            new DateTimeImmutable('+24 hours')
        );
        
        $errors = $meeting->validate();
        
        $this->assertArrayHasKey('token', $errors);
    }

    // =========================================================================
    // MeetingParticipant Entity Tests
    // =========================================================================

    /**
     * @test
     */
    public function testParticipant_FromArray_MapsAllFields(): void
    {
        $data = [
            'participant_id' => 789,
            'meeting_id' => 123,
            'display_name' => 'Dr. Smith',
            'joined_at' => '2026-01-10 14:30:00',
            'left_at' => null,
            'ip_address' => '192.168.1.100',
        ];

        $participant = MeetingParticipant::fromArray($data);

        $this->assertEquals(789, $participant->getParticipantId());
        $this->assertEquals(123, $participant->getMeetingId());
        $this->assertEquals('Dr. Smith', $participant->getDisplayName());
        $this->assertEquals('192.168.1.100', $participant->getIpAddress());
        $this->assertNull($participant->getLeftAt());
        $this->assertInstanceOf(DateTimeInterface::class, $participant->getJoinedAt());
    }

    /**
     * @test
     */
    public function testParticipant_ToArray_IncludesAllFields(): void
    {
        $participant = VideoMeetingFactory::createParticipant([
            'participant_id' => 100,
            'meeting_id' => 50,
            'display_name' => 'Test User',
        ]);

        $array = $participant->toArray();

        $this->assertArrayHasKey('participant_id', $array);
        $this->assertArrayHasKey('meeting_id', $array);
        $this->assertArrayHasKey('display_name', $array);
        $this->assertArrayHasKey('joined_at', $array);
        $this->assertArrayHasKey('left_at', $array);
        $this->assertArrayHasKey('ip_address', $array);
        
        $this->assertEquals(100, $array['participant_id']);
        $this->assertEquals(50, $array['meeting_id']);
        $this->assertEquals('Test User', $array['display_name']);
    }

    /**
     * @test
     */
    public function testParticipant_ToSafeArray_ExcludesIPAddress(): void
    {
        $participant = VideoMeetingFactory::createParticipant([
            'ip_address' => '192.168.1.100',
        ]);
        
        $safeArray = $participant->toSafeArray();
        
        $this->assertArrayHasKey('participant_id', $safeArray);
        $this->assertArrayHasKey('meeting_id', $safeArray);
        $this->assertArrayHasKey('display_name', $safeArray);
        $this->assertArrayHasKey('joined_at', $safeArray);
        $this->assertArrayHasKey('is_in_meeting', $safeArray);
        $this->assertArrayHasKey('session_duration', $safeArray);
        
        // IP should NOT be in safe array
        $this->assertArrayNotHasKey('ip_address', $safeArray);
    }

    /**
     * @test
     */
    public function testParticipant_IsInMeeting_ReturnsTrueWhenActive(): void
    {
        $participant = VideoMeetingFactory::createParticipant([
            'left_at' => null,
        ]);
        
        $this->assertTrue($participant->isInMeeting());
    }

    /**
     * @test
     */
    public function testParticipant_IsInMeeting_ReturnsFalseWhenLeft(): void
    {
        $participant = VideoMeetingFactory::createLeftParticipant();
        
        $this->assertFalse($participant->isInMeeting());
    }

    /**
     * @test
     */
    public function testParticipant_Leave_SetsLeftAt(): void
    {
        $participant = VideoMeetingFactory::createParticipant();
        $this->assertNull($participant->getLeftAt());
        
        $participant->leave();
        
        $this->assertNotNull($participant->getLeftAt());
        $this->assertFalse($participant->isInMeeting());
    }

    /**
     * @test
     */
    public function testParticipant_GetSessionDuration_ReturnsNullWhenStillInMeeting(): void
    {
        $participant = VideoMeetingFactory::createParticipant([
            'left_at' => null,
        ]);
        
        $this->assertNull($participant->getSessionDuration());
    }

    /**
     * @test
     */
    public function testParticipant_GetSessionDuration_ReturnsCorrectDuration(): void
    {
        $joinedAt = new DateTimeImmutable('-30 minutes');
        $leftAt = new DateTimeImmutable();
        
        $participant = VideoMeetingFactory::createParticipant([
            'joined_at' => $joinedAt,
            'left_at' => $leftAt,
        ]);
        
        $duration = $participant->getSessionDuration();
        
        $this->assertNotNull($duration);
        // Should be approximately 30 minutes (1800 seconds)
        $this->assertGreaterThan(1700, $duration);
        $this->assertLessThan(1900, $duration);
    }

    /**
     * @test
     */
    public function testParticipant_Validate_ReturnsEmptyArrayWhenValid(): void
    {
        $participant = VideoMeetingFactory::createParticipant([
            'meeting_id' => 1,
            'display_name' => 'Valid Name',
            'ip_address' => '192.168.1.1',
        ]);
        
        $errors = $participant->validate();
        
        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function testParticipant_Validate_ReturnsErrorForEmptyDisplayName(): void
    {
        $participant = new MeetingParticipant(1, '   ', null);
        
        $errors = $participant->validate();
        
        $this->assertArrayHasKey('display_name', $errors);
    }

    /**
     * @test
     */
    public function testParticipant_Validate_ReturnsErrorForLongDisplayName(): void
    {
        $longName = str_repeat('a', 101); // > 100 chars
        $participant = new MeetingParticipant(1, $longName, null);
        
        $errors = $participant->validate();
        
        $this->assertArrayHasKey('display_name', $errors);
    }

    /**
     * @test
     */
    public function testParticipant_Validate_ReturnsErrorForInvalidIPAddress(): void
    {
        $participant = new MeetingParticipant(1, 'Valid Name', 'invalid-ip');
        
        $errors = $participant->validate();
        
        $this->assertArrayHasKey('ip_address', $errors);
    }

    /**
     * @test
     */
    public function testParticipant_Validate_AcceptsValidIPv6Address(): void
    {
        $participant = new MeetingParticipant(1, 'Valid Name', '2001:0db8:85a3:0000:0000:8a2e:0370:7334');
        
        $errors = $participant->validate();
        
        $this->assertArrayNotHasKey('ip_address', $errors);
    }

    // =========================================================================
    // MeetingChatMessage Entity Tests
    // =========================================================================

    /**
     * @test
     */
    public function testChatMessage_FromArray_MapsAllFields(): void
    {
        $data = [
            'message_id' => 999,
            'meeting_id' => 123,
            'participant_id' => 456,
            'message_text' => 'Hello, how are you?',
            'sent_at' => '2026-01-10 14:30:00',
        ];

        $message = MeetingChatMessage::fromArray($data);

        $this->assertEquals(999, $message->getMessageId());
        $this->assertEquals(123, $message->getMeetingId());
        $this->assertEquals(456, $message->getParticipantId());
        $this->assertEquals('Hello, how are you?', $message->getMessageText());
        $this->assertInstanceOf(DateTimeInterface::class, $message->getSentAt());
    }

    /**
     * @test
     */
    public function testChatMessage_ToArray_IncludesAllFields(): void
    {
        $message = VideoMeetingFactory::createChatMessage([
            'message_id' => 100,
            'meeting_id' => 50,
            'participant_id' => 25,
            'message_text' => 'Test message',
        ]);

        $array = $message->toArray();

        $this->assertArrayHasKey('message_id', $array);
        $this->assertArrayHasKey('meeting_id', $array);
        $this->assertArrayHasKey('participant_id', $array);
        $this->assertArrayHasKey('message_text', $array);
        $this->assertArrayHasKey('sent_at', $array);
        
        $this->assertEquals(100, $array['message_id']);
        $this->assertEquals('Test message', $array['message_text']);
    }

    /**
     * @test
     */
    public function testChatMessage_ToSafeArray_IncludesMessageLength(): void
    {
        $messageText = 'Hello, world!';
        $message = VideoMeetingFactory::createChatMessage([
            'message_text' => $messageText,
        ]);
        
        $safeArray = $message->toSafeArray();
        
        $this->assertArrayHasKey('message_length', $safeArray);
        $this->assertEquals(strlen($messageText), $safeArray['message_length']);
    }

    /**
     * @test
     */
    public function testChatMessage_GetMessageLength_ReturnsCorrectLength(): void
    {
        $messageText = 'Test message with 30 characters';
        $message = VideoMeetingFactory::createChatMessage([
            'message_text' => $messageText,
        ]);
        
        $this->assertEquals(strlen($messageText), $message->getMessageLength());
    }

    /**
     * @test
     */
    public function testChatMessage_IsEmpty_ReturnsTrueForEmptyMessage(): void
    {
        $message = VideoMeetingFactory::createChatMessage([
            'message_text' => '   ',
        ]);
        
        $this->assertTrue($message->isEmpty());
    }

    /**
     * @test
     */
    public function testChatMessage_IsEmpty_ReturnsFalseForNonEmptyMessage(): void
    {
        $message = VideoMeetingFactory::createChatMessage([
            'message_text' => 'Hello!',
        ]);
        
        $this->assertFalse($message->isEmpty());
    }

    /**
     * @test
     */
    public function testChatMessage_Validate_ReturnsEmptyArrayWhenValid(): void
    {
        $message = VideoMeetingFactory::createChatMessage([
            'meeting_id' => 1,
            'participant_id' => 1,
            'message_text' => 'Valid message',
        ]);
        
        $errors = $message->validate();
        
        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function testChatMessage_Validate_ReturnsErrorForEmptyMessage(): void
    {
        $message = new MeetingChatMessage(1, 1, '   ');
        
        $errors = $message->validate();
        
        $this->assertArrayHasKey('message_text', $errors);
    }

    /**
     * @test
     */
    public function testChatMessage_Validate_ReturnsErrorForInvalidMeetingId(): void
    {
        $message = new MeetingChatMessage(0, 1, 'Valid message');
        
        $errors = $message->validate();
        
        $this->assertArrayHasKey('meeting_id', $errors);
    }

    /**
     * @test
     */
    public function testChatMessage_Validate_ReturnsErrorForInvalidParticipantId(): void
    {
        $message = new MeetingChatMessage(1, 0, 'Valid message');
        
        $errors = $message->validate();
        
        $this->assertArrayHasKey('participant_id', $errors);
    }

    /**
     * @test
     */
    public function testChatMessage_GetCreatedAt_ReturnsSentAt(): void
    {
        $message = VideoMeetingFactory::createChatMessage();
        
        $this->assertEquals(
            $message->getSentAt()?->format('Y-m-d H:i:s'),
            $message->getCreatedAt()?->format('Y-m-d H:i:s')
        );
    }

    /**
     * @test
     */
    public function testChatMessage_IsPersisted_ReturnsTrueWhenHasId(): void
    {
        $message = VideoMeetingFactory::createChatMessage(['message_id' => 100]);
        
        $this->assertTrue($message->isPersisted());
    }

    /**
     * @test
     */
    public function testChatMessage_IsPersisted_ReturnsFalseWhenNoId(): void
    {
        $message = new MeetingChatMessage(1, 1, 'Test');
        
        $this->assertFalse($message->isPersisted());
    }

    // =========================================================================
    // Factory Method Tests
    // =========================================================================

    /**
     * @test
     */
    public function testFactory_CreateToken_Returns64CharHexString(): void
    {
        $token = VideoMeetingFactory::createToken();
        
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * @test
     */
    public function testFactory_CreateToken_ReturnsUniqueTokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $tokens[] = VideoMeetingFactory::createToken();
        }
        
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(10, $uniqueTokens);
    }

    /**
     * @test
     */
    public function testFactory_CreateWithParticipants_ReturnsCorrectStructure(): void
    {
        $result = VideoMeetingFactory::createWithParticipants(3);
        
        $this->assertArrayHasKey('meeting', $result);
        $this->assertArrayHasKey('participants', $result);
        $this->assertInstanceOf(VideoMeeting::class, $result['meeting']);
        $this->assertCount(3, $result['participants']);
        
        foreach ($result['participants'] as $participant) {
            $this->assertInstanceOf(MeetingParticipant::class, $participant);
            $this->assertEquals(
                $result['meeting']->getMeetingId(),
                $participant->getMeetingId()
            );
        }
    }

    /**
     * @test
     */
    public function testFactory_CreateExpiredMeeting_ReturnsExpiredToken(): void
    {
        $meeting = VideoMeetingFactory::createExpiredMeeting();
        
        $this->assertTrue($meeting->isTokenExpired());
    }

    /**
     * @test
     */
    public function testFactory_CreateEndedMeeting_ReturnsInactiveMeeting(): void
    {
        $meeting = VideoMeetingFactory::createEndedMeeting();
        
        $this->assertFalse($meeting->isActive());
        $this->assertNotNull($meeting->getEndedAt());
    }
}
