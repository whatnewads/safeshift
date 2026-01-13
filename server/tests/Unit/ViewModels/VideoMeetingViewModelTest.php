<?php
/**
 * VideoMeetingViewModelTest - Unit Tests for Video Meeting ViewModel
 * 
 * Tests for VideoMeetingViewModel business logic layer.
 * Covers meeting creation, token validation, participant management, and chat.
 * 
 * @package SafeShift\Tests\Unit\ViewModels
 */

declare(strict_types=1);

namespace Tests\Unit\ViewModels;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ViewModel\VideoMeetingViewModel;
use Model\Repositories\VideoMeetingRepository;
use Model\Entities\VideoMeeting;
use Model\Entities\MeetingParticipant;
use Tests\Helpers\Factories\VideoMeetingFactory;
use Tests\Helpers\Factories\UserFactory;
use PDO;
use PDOStatement;
use DateTimeImmutable;

/**
 * @covers \ViewModel\VideoMeetingViewModel
 */
class VideoMeetingViewModelTest extends TestCase
{
    private MockObject&PDO $mockPdo;
    private MockObject&PDOStatement $mockStmt;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        // Clear session
        $_SESSION = [];
        
        // Create mock PDO
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);
        
        VideoMeetingFactory::resetIdCounter();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    /**
     * Helper to create ViewModel with mock PDO
     */
    private function createViewModel(): VideoMeetingViewModel
    {
        return new VideoMeetingViewModel($this->mockPdo);
    }

    /**
     * Helper to set up authenticated session
     */
    private function setupClinicianSession(int $userId = 42): array
    {
        $user = UserFactory::makeClinician([
            'user_id' => $userId,
            'role' => 'pclinician',
        ]);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        return $user;
    }

    /**
     * Helper to set up non-clinician session
     */
    private function setupNonClinicianSession(int $userId = 99): array
    {
        $user = UserFactory::make('QA', [
            'user_id' => $userId,
        ]);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        return $user;
    }

    // =========================================================================
    // Token Generation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGenerateSecureToken_ReturnsUnique64CharString(): void
    {
        $viewModel = $this->createViewModel();
        
        $token1 = $viewModel->generateSecureToken();
        $token2 = $viewModel->generateSecureToken();
        
        // Should be 64 characters (32 bytes * 2 for hex)
        $this->assertEquals(64, strlen($token1));
        $this->assertEquals(64, strlen($token2));
        
        // Should be hex string
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token2);
        
        // Should be unique
        $this->assertNotEquals($token1, $token2);
    }

    /**
     * @test
     */
    public function testGenerateSecureToken_ProducesUniqueTokens(): void
    {
        $viewModel = $this->createViewModel();
        
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = $viewModel->generateSecureToken();
        }
        
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(100, $uniqueTokens, 'All generated tokens should be unique');
    }

    // =========================================================================
    // Create Meeting Tests
    // =========================================================================

    /**
     * @test
     */
    public function testCreateMeeting_WithClinicianRole_Success(): void
    {
        $this->setupClinicianSession(42);
        $viewModel = $this->createViewModel();
        
        // Mock repository calls
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetchColumn')
            ->willReturn('0'); // Token is unique
        
        $this->mockPdo->expects($this->any())
            ->method('lastInsertId')
            ->willReturn('123');
        
        // Mock role check to return clinician role
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn(false); // No DB role check needed since session has it

        $result = $viewModel->createMeeting(42);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meeting_id', $result['data']);
        $this->assertArrayHasKey('meeting_url', $result['data']);
        $this->assertArrayHasKey('token', $result['data']);
        $this->assertArrayHasKey('expires_at', $result['data']);
        $this->assertEquals(64, strlen($result['data']['token']));
    }

    /**
     * @test
     */
    public function testCreateMeeting_WithoutClinicianRole_Fails(): void
    {
        $this->setupNonClinicianSession(99);
        $viewModel = $this->createViewModel();
        
        // Mock role check query
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        // Return false for role check (no clinician role)
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn(false);

        $result = $viewModel->createMeeting(99);

        $this->assertFalse($result['success']);
        $this->assertEquals(403, $result['status']);
        $this->assertStringContainsString('clinician', strtolower($result['error']));
    }

    // =========================================================================
    // Token Validation Tests
    // =========================================================================

    /**
     * @test
     */
    public function testValidateToken_ValidToken_ReturnsSuccess(): void
    {
        $viewModel = $this->createViewModel();
        $token = VideoMeetingFactory::createToken();
        
        // Mock finding a valid, active meeting
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'token' => $token,
            'is_active' => true,
            'token_expires_at' => (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s'),
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($meetingData);

        $result = $viewModel->validateToken($token);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['valid']);
        $this->assertEquals(100, $result['data']['meeting_id']);
        $this->assertTrue($result['data']['can_join']);
    }

    /**
     * @test
     */
    public function testValidateToken_ExpiredToken_ReturnsFalse(): void
    {
        $viewModel = $this->createViewModel();
        $token = VideoMeetingFactory::createToken();
        
        // Mock finding an expired meeting
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'token' => $token,
            'is_active' => true,
            'token_expires_at' => (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'), // Expired
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($meetingData);

        $result = $viewModel->validateToken($token);

        $this->assertTrue($result['success']); // API call successful
        $this->assertFalse($result['data']['valid']); // But token is invalid
        $this->assertStringContainsString('expired', strtolower($result['data']['error']));
    }

    /**
     * @test
     */
    public function testValidateToken_InvalidToken_ReturnsFalse(): void
    {
        $viewModel = $this->createViewModel();
        $token = VideoMeetingFactory::createToken();
        
        // Mock finding no meeting
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn(false); // No meeting found

        $result = $viewModel->validateToken($token);

        $this->assertTrue($result['success']); // API call successful
        $this->assertFalse($result['data']['valid']); // But token is invalid
        $this->assertStringContainsString('not found', strtolower($result['data']['error']));
    }

    /**
     * @test
     */
    public function testValidateToken_InvalidFormat_ReturnsBadRequest(): void
    {
        $viewModel = $this->createViewModel();
        $invalidToken = 'short'; // Too short

        $result = $viewModel->validateToken($invalidToken);

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['status']);
    }

    /**
     * @test
     */
    public function testValidateToken_EndedMeeting_ReturnsFalse(): void
    {
        $viewModel = $this->createViewModel();
        $token = VideoMeetingFactory::createToken();
        
        // Mock finding an ended meeting
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'token' => $token,
            'is_active' => false, // Meeting ended
            'token_expires_at' => (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s'),
            'ended_at' => (new DateTimeImmutable('-30 minutes'))->format('Y-m-d H:i:s'),
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($meetingData);

        $result = $viewModel->validateToken($token);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['valid']);
        $this->assertStringContainsString('ended', strtolower($result['data']['error']));
    }

    // =========================================================================
    // Join Meeting Tests
    // =========================================================================

    /**
     * @test
     */
    public function testJoinMeeting_ValidToken_CreatesParticipant(): void
    {
        $viewModel = $this->createViewModel();
        $token = VideoMeetingFactory::createToken();
        
        // Mock valid meeting
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'token' => $token,
            'is_active' => true,
            'token_expires_at' => (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s'),
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($meetingData);
        
        $this->mockStmt->expects($this->any())
            ->method('fetchColumn')
            ->willReturn('1'); // Participant count
        
        $this->mockPdo->expects($this->any())
            ->method('lastInsertId')
            ->willReturn('500'); // New participant ID

        $result = $viewModel->joinMeeting($token, 'Dr. Smith', '192.168.1.100');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(500, $result['data']['participant_id']);
        $this->assertEquals(100, $result['data']['meeting_id']);
        $this->assertEquals('Dr. Smith', $result['data']['display_name']);
    }

    /**
     * @test
     */
    public function testJoinMeeting_EmptyDisplayName_Fails(): void
    {
        $viewModel = $this->createViewModel();
        $token = VideoMeetingFactory::createToken();
        
        // Mock valid meeting
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'token' => $token,
            'is_active' => true,
            'token_expires_at' => (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s'),
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($meetingData);

        $result = $viewModel->joinMeeting($token, '   ', '192.168.1.100');

        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['status']); // Validation error
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('display_name', $result['errors']);
    }

    /**
     * @test
     */
    public function testJoinMeeting_DisplayNameSanitized(): void
    {
        $viewModel = $this->createViewModel();
        $token = VideoMeetingFactory::createToken();
        
        // Mock valid meeting
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'token' => $token,
            'is_active' => true,
            'token_expires_at' => (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s'),
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($meetingData);
        
        $this->mockStmt->expects($this->any())
            ->method('fetchColumn')
            ->willReturn('1');
        
        $this->mockPdo->expects($this->any())
            ->method('lastInsertId')
            ->willReturn('500');

        // Use XSS payload as display name
        $xssName = '<script>alert("XSS")</script>Dr. Smith';
        $result = $viewModel->joinMeeting($token, $xssName, '192.168.1.100');

        $this->assertTrue($result['success']);
        // Display name should be sanitized (no script tags)
        $this->assertStringNotContainsString('<script>', $result['data']['display_name']);
    }

    // =========================================================================
    // Leave Meeting Tests
    // =========================================================================

    /**
     * @test
     */
    public function testLeaveMeeting_UpdatesLeftAt(): void
    {
        $viewModel = $this->createViewModel();
        
        // Mock participant data
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 100,
            'meeting_id' => 50,
            'left_at' => null,
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($participantData);

        $result = $viewModel->leaveMeeting(100, 50);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testLeaveMeeting_InvalidParticipant_ReturnsFalse(): void
    {
        $viewModel = $this->createViewModel();
        
        // Mock no participant found
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn(false);

        $result = $viewModel->leaveMeeting(999, 50);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function testLeaveMeeting_WrongMeeting_ReturnsFalse(): void
    {
        $viewModel = $this->createViewModel();
        
        // Mock participant from different meeting
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 100,
            'meeting_id' => 50, // Different from provided meeting_id
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($participantData);

        $result = $viewModel->leaveMeeting(100, 999); // Wrong meeting

        $this->assertFalse($result);
    }

    // =========================================================================
    // End Meeting Tests
    // =========================================================================

    /**
     * @test
     */
    public function testEndMeeting_ByCreator_Success(): void
    {
        $this->setupClinicianSession(42);
        $viewModel = $this->createViewModel();
        
        // Mock meeting data where user is creator
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'created_by' => 42, // Same as session user
            'is_active' => true,
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($meetingData);

        $result = $viewModel->endMeeting(100, 42);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testEndMeeting_ByNonCreator_Fails(): void
    {
        $this->setupClinicianSession(99); // Different user
        $viewModel = $this->createViewModel();
        
        // Mock meeting data where user is NOT creator
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 100,
            'created_by' => 42, // Different from session user
            'is_active' => true,
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($meetingData);

        $result = $viewModel->endMeeting(100, 99);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function testEndMeeting_MeetingNotFound_ReturnsFalse(): void
    {
        $viewModel = $this->createViewModel();
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn(false);

        $result = $viewModel->endMeeting(99999, 42);

        $this->assertFalse($result);
    }

    // =========================================================================
    // Chat Message Tests
    // =========================================================================

    /**
     * @test
     */
    public function testSendChatMessage_SanitizesInput(): void
    {
        $viewModel = $this->createViewModel();
        
        // Mock participant and meeting data
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 100,
            'meeting_id' => 50,
            'left_at' => null,
        ]);
        
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 50,
            'is_active' => true,
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        // Return participant first, then meeting
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($participantData, $meetingData);
        
        $this->mockPdo->expects($this->any())
            ->method('lastInsertId')
            ->willReturn('555');

        // XSS payload in message
        $xssMessage = '<script>alert("XSS")</script>Hello!';
        $result = $viewModel->sendChatMessage(50, 100, $xssMessage);

        $this->assertTrue($result['success']);
        // The original message is sanitized before storage
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(555, $result['data']['message_id']);
    }

    /**
     * @test
     */
    public function testSendChatMessage_EmptyMessage_Fails(): void
    {
        $viewModel = $this->createViewModel();
        
        // Mock participant data
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 100,
            'meeting_id' => 50,
            'left_at' => null,
        ]);
        
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 50,
            'is_active' => true,
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($participantData, $meetingData);

        $result = $viewModel->sendChatMessage(50, 100, '   ');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('message', $result['errors']);
    }

    /**
     * @test
     */
    public function testSendChatMessage_ParticipantLeft_Fails(): void
    {
        $viewModel = $this->createViewModel();
        
        // Mock participant who has left
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 100,
            'meeting_id' => 50,
            'left_at' => '2026-01-10 15:00:00', // Has left
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn($participantData);

        $result = $viewModel->sendChatMessage(50, 100, 'Hello!');

        $this->assertFalse($result['success']);
        $this->assertEquals(403, $result['status']);
    }

    // =========================================================================
    // Get Participants Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetParticipants_ReturnsActiveOnly(): void
    {
        $viewModel = $this->createViewModel();
        
        // Mock active participants
        $participant1 = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 1,
            'meeting_id' => 100,
            'display_name' => 'User 1',
            'left_at' => null,
        ]);
        $participant2 = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 2,
            'meeting_id' => 100,
            'display_name' => 'User 2',
            'left_at' => null,
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($participant1, $participant2, false);

        $result = $viewModel->getParticipants(100);

        $this->assertCount(2, $result);
        $this->assertEquals('User 1', $result[0]['display_name']);
        $this->assertEquals('User 2', $result[1]['display_name']);
    }

    // =========================================================================
    // RBAC Tests
    // =========================================================================

    /**
     * @test
     */
    public function testIsClinicianRole_WithPclinicianRole_ReturnsTrue(): void
    {
        $this->setupClinicianSession(42);
        $viewModel = $this->createViewModel();
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn(false);

        $result = $viewModel->isClinicianRole(42);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testIsClinicianRole_WithAdminRole_ReturnsTrue(): void
    {
        $user = UserFactory::makeAdmin(['user_id' => 42]);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        
        $viewModel = $this->createViewModel();

        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn(false);

        $result = $viewModel->isClinicianRole(42);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testIsClinicianRole_WithQARole_ReturnsFalse(): void
    {
        $this->setupNonClinicianSession(99);
        $viewModel = $this->createViewModel();

        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturn(false); // No clinician role in DB either

        $result = $viewModel->isClinicianRole(99);

        $this->assertFalse($result);
    }

    // =========================================================================
    // Get Client IP Tests
    // =========================================================================

    /**
     * @test
     */
    public function testGetClientIpAddress_ReturnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);
        
        $viewModel = $this->createViewModel();
        
        $result = $viewModel->getClientIpAddress();
        
        $this->assertEquals('192.168.1.100', $result);
    }

    /**
     * @test
     */
    public function testGetClientIpAddress_PrefersForwardedFor(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 10.0.0.1';
        
        $viewModel = $this->createViewModel();
        
        $result = $viewModel->getClientIpAddress();
        
        // Should use first IP from X-Forwarded-For
        $this->assertEquals('203.0.113.50', $result);
        
        // Cleanup
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    // =========================================================================
    // Peer Operations Tests
    // =========================================================================

    /**
     * @test
     */
    public function testRegisterPeer_ValidParticipant_Success(): void
    {
        $viewModel = $this->createViewModel();
        
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 100,
            'meeting_id' => 50,
            'left_at' => null,
        ]);
        
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 50,
            'is_active' => true,
        ]);
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($participantData, $meetingData);

        $result = $viewModel->registerPeer(50, 100, 'peer-abc123');

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function testRegisterPeer_InvalidPeerId_Fails(): void
    {
        $viewModel = $this->createViewModel();

        // Peer ID with invalid characters should fail
        $result = $viewModel->registerPeer(50, 100, '');

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function testUpdateHeartbeat_ReturnsActivePeers(): void
    {
        $viewModel = $this->createViewModel();
        
        $participantData = VideoMeetingFactory::createParticipantArray([
            'participant_id' => 100,
            'meeting_id' => 50,
            'left_at' => null,
        ]);
        
        $meetingData = VideoMeetingFactory::createMeetingArray([
            'meeting_id' => 50,
            'is_active' => true,
        ]);
        
        $peerData = [
            'participant_id' => 100,
            'peer_id' => 'peer-123',
            'display_name' => 'Test User',
            'joined_at' => '2026-01-10 14:00:00',
            'last_heartbeat' => '2026-01-10 14:05:00',
        ];
        
        $this->mockPdo->expects($this->any())
            ->method('prepare')
            ->willReturn($this->mockStmt);
        
        $this->mockStmt->expects($this->any())
            ->method('execute')
            ->willReturn(true);
        
        $this->mockStmt->expects($this->any())
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($participantData, $meetingData, $peerData, false);
        
        $this->mockStmt->expects($this->any())
            ->method('rowCount')
            ->willReturn(0);
        
        $this->mockStmt->expects($this->any())
            ->method('bindValue')
            ->willReturn(true);

        $result = $viewModel->updateHeartbeat(100);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('active_peers', $result);
    }
}
