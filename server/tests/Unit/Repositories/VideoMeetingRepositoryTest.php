<?php
/**
 * VideoMeetingRepositoryTest - Unit Tests for Video Meeting Repository
 * 
 * Tests for VideoMeetingRepository data access layer.
 * Uses mock PDO for isolated unit testing.
 * 
 * @package SafeShift\Tests\Unit\Repositories
 */

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Model\Repositories\VideoMeetingRepository;
use Model\Entities\VideoMeeting;
use Model\Entities\MeetingParticipant;
use Model\Entities\MeetingChatMessage;
use Tests\Helpers\Factories\VideoMeetingFactory;
use PDO;
use PDOStatement;
use DateTime;
use DateTimeImmutable;

/**
 * @covers \Model\Repositories\VideoMeetingRepository
 */
class VideoMeetingRepositoryTest extends TestCase
{
    private VideoMeetingRepository $repository;
    private MockObject&PDO $mockPdo;
    private MockObject&PDOStatement $mockStmt;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);
        
        $this->repository = new VideoMeetingRepository($this->mockPdo);
        
        VideoMeetingFactory::resetIdCounter();
    }

    // =========================================================================
    // Meeting Operations Tests
    // =========================================================================

    /**
     * @test
     */
    public function testCreate_ReturnsMeetingId(): void
    {
        $meeting = VideoMeetingFactory::create([
            'created_by' => 42,
            'token' => VideoMeetingFactory::createToken(),
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('123');

        $result = $this->repository->create($meeting);

        $this->assertEquals(123, $result);
        $this->assertEquals(123, $meeting->getMeetingId());
    }

    /**
     * @test
     */
    public function testFindByToken_ExistingToken_ReturnsMeeting(): void
    {
        $token = VideoMeetingFactory::createToken();
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'token' => $token,
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['token' => $token])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($meetingData);

        $result = $this->repository->findByToken($token);

        $this->assertInstanceOf(VideoMeeting::class, $result);
        $this->assertEquals(100, $result->getMeetingId());
        $this->assertEquals($token, $result->getToken());
    }

    /**
     * @test
     */
    public function testFindByToken_NonExistingToken_ReturnsNull(): void
    {
        $token = VideoMeetingFactory::createToken();

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['token' => $token])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(false);

        $result = $this->repository->findByToken($token);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function testFindById_ReturnsCorrectMeeting(): void
    {
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 456,
            'created_by' => 99,
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['meeting_id' => 456])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($meetingData);

        $result = $this->repository->findById(456);

        $this->assertInstanceOf(VideoMeeting::class, $result);
        $this->assertEquals(456, $result->getMeetingId());
        $this->assertEquals(99, $result->getCreatedBy());
    }

    /**
     * @test
     */
    public function testFindById_NonExisting_ReturnsNull(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn(false);

        $result = $this->repository->findById(99999);

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function testUpdateStatus_SetsIsActiveAndEndedAt(): void
    {
        $endedAt = new DateTime();

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([
                'meeting_id' => 100,
                'is_active' => 0,
                'ended_at' => $endedAt->format('Y-m-d H:i:s'),
            ])
            ->willReturn(true);

        $result = $this->repository->updateStatus(100, false, $endedAt);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testEndMeeting_CallsUpdateStatus(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $result = $this->repository->endMeeting(100);

        $this->assertTrue($result);
    }

    // =========================================================================
    // Participant Operations Tests
    // =========================================================================

    /**
     * @test
     */
    public function testAddParticipant_ReturnsParticipantId(): void
    {
        $participant = VideoMeetingFactory::createParticipant([
            'meeting_id' => 100,
            'display_name' => 'Test User',
            'ip_address' => '192.168.1.50',
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('789');

        $result = $this->repository->addParticipant($participant);

        $this->assertEquals(789, $result);
        $this->assertEquals(789, $participant->getParticipantId());
    }

    /**
     * @test
     */
    public function testGetParticipants_ActiveOnly_FiltersLeft(): void
    {
        $activeParticipant = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 1,
            'meeting_id' => 100,
            'display_name' => 'Active User',
            'left_at' => null,
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('AND left_at IS NULL'))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['meeting_id' => 100])
            ->willReturn(true);
        
        // Mock fetch to return one row then false
        $this->mockStmt->expects($this->exactly(2))
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnOnConsecutiveCalls($activeParticipant, false);

        $result = $this->repository->getParticipants(100, true);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(MeetingParticipant::class, $result[0]);
        $this->assertEquals('Active User', $result[0]->getDisplayName());
    }

    /**
     * @test
     */
    public function testGetParticipants_AllParticipants_IncludesLeft(): void
    {
        $participant1 = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 1,
            'meeting_id' => 100,
            'left_at' => null,
        ]);
        $participant2 = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 2,
            'meeting_id' => 100,
            'left_at' => '2026-01-10 15:00:00',
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->logicalNot($this->stringContains('AND left_at IS NULL')))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($participant1, $participant2, false);

        $result = $this->repository->getParticipants(100, false);

        $this->assertCount(2, $result);
    }

    /**
     * @test
     */
    public function testFindParticipantById_ReturnsParticipant(): void
    {
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 123,
            'display_name' => 'Test Participant',
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['participant_id' => 123])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($participantData);

        $result = $this->repository->findParticipantById(123);

        $this->assertInstanceOf(MeetingParticipant::class, $result);
        $this->assertEquals(123, $result->getParticipantId());
    }

    /**
     * @test
     */
    public function testRemoveParticipant_SetsLeftAt(): void
    {
        $leftAt = new DateTime();

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([
                'participant_id' => 100,
                'left_at' => $leftAt->format('Y-m-d H:i:s'),
            ])
            ->willReturn(true);

        $result = $this->repository->removeParticipant(100, $leftAt);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testCountActiveParticipants_ReturnsCount(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['meeting_id' => 100])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('5');

        $result = $this->repository->countActiveParticipants(100);

        $this->assertEquals(5, $result);
    }

    // =========================================================================
    // Chat Message Operations Tests
    // =========================================================================

    /**
     * @test
     */
    public function testAddChatMessage_ReturnsMessageId(): void
    {
        $message = VideoMeetingFactory::createChatMessage([
            'meeting_id' => 100,
            'participant_id' => 50,
            'message_text' => 'Hello everyone!',
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn('555');

        $result = $this->repository->addChatMessage($message);

        $this->assertEquals(555, $result);
        $this->assertEquals(555, $message->getMessageId());
    }

    /**
     * @test
     */
    public function testGetChatMessages_OrderedByTime(): void
    {
        $message1 = VideoMeetingFactory::createChatMessageArray([
            'message_id' => 1,
            'meeting_id' => 100,
            'sent_at' => '2026-01-10 14:00:00',
        ]);
        $message1['sender_name'] = 'User1';
        
        $message2 = VideoMeetingFactory::createChatMessageArray([
            'message_id' => 2,
            'meeting_id' => 100,
            'sent_at' => '2026-01-10 14:01:00',
        ]);
        $message2['sender_name'] = 'User2';

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('ORDER BY m.sent_at ASC'))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('bindValue')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($message1, $message2, false);

        $result = $this->repository->getChatMessages(100);

        $this->assertCount(2, $result);
        // Verify structure includes message and sender_name
        $this->assertArrayHasKey('message', $result[0]);
        $this->assertArrayHasKey('sender_name', $result[0]);
        $this->assertInstanceOf(MeetingChatMessage::class, $result[0]['message']);
    }

    // =========================================================================
    // Logging Operations Tests
    // =========================================================================

    /**
     * @test
     */
    public function testLogEvent_InsertsLog(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO video_meeting_logs'))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([
                'log_type' => 'meeting_created',
                'meeting_id' => 100,
                'user_id' => 42,
                'action' => 'Meeting created successfully',
                'details' => json_encode(['extra' => 'data']),
                'ip_address' => '192.168.1.1',
            ])
            ->willReturn(true);

        // This method returns void, so we just verify it doesn't throw
        $this->repository->logEvent(
            'meeting_created',
            100,
            42,
            'Meeting created successfully',
            ['extra' => 'data'],
            '192.168.1.1'
        );

        $this->assertTrue(true); // Reached without exception
    }

    /**
     * @test
     */
    public function testGetMeetingLogs_ReturnsLogs(): void
    {
        $log1 = [
            'log_id' => 1,
            'log_type' => 'meeting_created',
            'meeting_id' => 100,
            'user_id' => 42,
            'action' => 'Meeting created',
            'details' => '{"test": "data"}',
            'ip_address' => '192.168.1.1',
            'created_at' => '2026-01-10 14:00:00',
        ];
        
        $log2 = [
            'log_id' => 2,
            'log_type' => 'participant_joined',
            'meeting_id' => 100,
            'user_id' => null,
            'action' => 'Participant joined',
            'details' => '{}',
            'ip_address' => '192.168.1.2',
            'created_at' => '2026-01-10 14:05:00',
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('bindValue')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($log1, $log2, false);

        $result = $this->repository->getMeetingLogs(100);

        $this->assertCount(2, $result);
        $this->assertEquals('meeting_created', $result[0]['log_type']);
        $this->assertEquals(['test' => 'data'], $result[0]['details']);
    }

    // =========================================================================
    // Utility Operations Tests
    // =========================================================================

    /**
     * @test
     */
    public function testIsTokenUnique_ReturnsTrueForNew(): void
    {
        $token = VideoMeetingFactory::createToken();

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['token' => $token])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('0');

        $result = $this->repository->isTokenUnique($token);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testIsTokenUnique_ReturnsFalseForExisting(): void
    {
        $token = VideoMeetingFactory::createToken();

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['token' => $token])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('1');

        $result = $this->repository->isTokenUnique($token);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function testGetUserMeetingStats_ReturnsCorrectStats(): void
    {
        // First query: total meetings
        $this->mockPdo->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->exactly(3))
            ->method('execute')
            ->with(['user_id' => 42])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->exactly(3))
            ->method('fetchColumn')
            ->willReturnOnConsecutiveCalls('10', '2', '50');

        $result = $this->repository->getUserMeetingStats(42);

        $this->assertArrayHasKey('total_meetings', $result);
        $this->assertArrayHasKey('active_meetings', $result);
        $this->assertArrayHasKey('total_participants', $result);
        $this->assertEquals(10, $result['total_meetings']);
        $this->assertEquals(2, $result['active_meetings']);
        $this->assertEquals(50, $result['total_participants']);
    }

    /**
     * @test
     */
    public function testCleanupExpiredMeetings_ReturnsDeletedCount(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['days' => 30])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(5);

        $result = $this->repository->cleanupExpiredMeetings(30);

        $this->assertEquals(5, $result);
    }

    /**
     * @test
     */
    public function testDeactivateExpiredMeetings_ReturnsDeactivatedCount(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(3);

        $result = $this->repository->deactivateExpiredMeetings();

        $this->assertEquals(3, $result);
    }

    // =========================================================================
    // Peer/Signaling Operations Tests
    // =========================================================================

    /**
     * @test
     */
    public function testUpdatePeerId_Success(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([
                'participant_id' => 100,
                'peer_id' => 'peer-abc123',
            ])
            ->willReturn(true);

        $result = $this->repository->updatePeerId(100, 'peer-abc123');

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testUpdateHeartbeat_Success(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['participant_id' => 100])
            ->willReturn(true);

        $result = $this->repository->updateHeartbeat(100);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testGetActivePeers_ReturnsActivePeersOnly(): void
    {
        $peer1 = [
            'participant_id' => 1,
            'peer_id' => 'peer-1',
            'display_name' => 'User 1',
            'joined_at' => '2026-01-10 14:00:00',
            'last_heartbeat' => '2026-01-10 14:05:00',
        ];

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('peer_id IS NOT NULL'))
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('bindValue')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->exactly(2))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($peer1, false);

        $result = $this->repository->getActivePeers(100, 30);

        $this->assertCount(1, $result);
        $this->assertEquals('peer-1', $result[0]['peer_id']);
        $this->assertEquals('User 1', $result[0]['display_name']);
    }

    /**
     * @test
     */
    public function testRemoveStaleParticipants_ReturnsRemovedCount(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('bindValue')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(2);

        $result = $this->repository->removeStaleParticipants(100, 30);

        $this->assertEquals(2, $result);
    }

    /**
     * @test
     */
    public function testClearPeerId_Success(): void
    {
        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([
                'meeting_id' => 100,
                'participant_id' => 50,
            ])
            ->willReturn(true);

        $result = $this->repository->clearPeerId(100, 50);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testFindParticipantByPeerId_ReturnsParticipant(): void
    {
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 123,
            'meeting_id' => 100,
            'display_name' => 'Test User',
        ]);
        $participantData['peer_id'] = 'peer-test';
        $participantData['last_heartbeat'] = '2026-01-10 14:05:00';

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with([
                'meeting_id' => 100,
                'peer_id' => 'peer-test',
            ])
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('fetch')
            ->willReturn($participantData);

        $result = $this->repository->findParticipantByPeerId(100, 'peer-test');

        $this->assertInstanceOf(MeetingParticipant::class, $result);
        $this->assertEquals(123, $result->getParticipantId());
    }

    /**
     * @test
     */
    public function testFindByCreator_ReturnsUserMeetings(): void
    {
        $meeting1 = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 1,
            'created_by' => 42,
        ]);
        $meeting2 = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 2,
            'created_by' => 42,
        ]);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('bindValue')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->exactly(3))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($meeting1, $meeting2, false);

        $result = $this->repository->findByCreator(42, false, 50, 0);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(VideoMeeting::class, $result[0]);
        $this->assertInstanceOf(VideoMeeting::class, $result[1]);
    }
}
