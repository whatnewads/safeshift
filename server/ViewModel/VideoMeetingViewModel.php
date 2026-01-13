<?php
/**
 * VideoMeetingViewModel - Business logic layer for Video Meeting operations
 * 
 * Handles: Meeting CRUD, token generation/validation, participant management, chat
 * Security: Token-based access, RBAC clinician validation, XSS prevention, audit logging
 * 
 * @package SafeShift\ViewModel
 * @author SafeShift Development Team
 * @copyright 2025 SafeShift EHR
 */

declare(strict_types=1);

namespace ViewModel;

use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;
use Model\Repositories\VideoMeetingRepository;
use Model\Entities\VideoMeeting;
use Model\Entities\MeetingParticipant;
use Model\Entities\MeetingChatMessage;
use PDO;
use DateTime;
use DateTimeImmutable;
use Exception;

/**
 * Video Meeting ViewModel
 * 
 * Business logic for WebRTC video meeting functionality.
 * Provides secure token-based meeting access for telehealth visits.
 */
class VideoMeetingViewModel extends BaseViewModel
{
    /** @var VideoMeetingRepository Meeting repository */
    private VideoMeetingRepository $meetingRepository;
    
    /** @var string Base URL for meeting links */
    private string $baseUrl;
    
    /** @var int Token expiration in hours */
    private const TOKEN_EXPIRY_HOURS = 24;
    
    /** @var int Token length in bytes (64 hex chars = 32 bytes) */
    private const TOKEN_BYTES = 32;
    
    /** @var int Max display name length */
    private const MAX_DISPLAY_NAME_LENGTH = 100;
    
    /** @var int Max message length */
    private const MAX_MESSAGE_LENGTH = 2000;
    
    /** @var string Clinician role identifier */
    private const CLINICIAN_ROLE = '1clinician';

    /**
     * Constructor
     * 
     * @param PDO|null $pdo Database connection
     */
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(null, $pdo);
        
        $this->meetingRepository = new VideoMeetingRepository($this->pdo);
        
        // Build base URL from environment or defaults
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $this->baseUrl = defined('APP_BASE_URL') ? APP_BASE_URL : "{$protocol}://{$host}";
    }

    // =========================================================================
    // Token Generation
    // =========================================================================

    /**
     * Generate a cryptographically secure token
     * 
     * @return string 64-character hex token
     */
    public function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    /**
     * Generate a unique token (checks database for uniqueness)
     * 
     * @param int $maxAttempts Maximum generation attempts
     * @return string Unique 64-character hex token
     * @throws Exception If unable to generate unique token
     */
    private function generateUniqueToken(int $maxAttempts = 5): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $token = $this->generateSecureToken();
            if ($this->meetingRepository->isTokenUnique($token)) {
                return $token;
            }
        }
        
        throw new Exception('Unable to generate unique meeting token');
    }

    // =========================================================================
    // RBAC Validation
    // =========================================================================

    /**
     * Check if user can create video meetings
     *
     * Changed from clinician-only to allow all authenticated users
     * for flexible telehealth use cases.
     *
     * @param string $userId User ID (UUID) to check
     * @return bool True if user can create meetings (all authenticated users)
     */
    public function canCreateMeeting(string $userId): bool
    {
        // All authenticated users can create meetings
        // The authentication check is done before this method is called
        // User ID is a UUID string, so check for non-empty
        return !empty($userId);
    }

    /**
     * Check if user has clinician role
     *
     * @param int $userId User ID to check
     * @return bool True if user has clinician role
     * @deprecated Use canCreateMeeting() for meeting creation permission checks
     */
    public function isClinicianRole(int $userId): bool
    {
        // Check session role first
        $userRole = $this->getCurrentUserRole();
        
        if ($userRole === self::CLINICIAN_ROLE) {
            return true;
        }
        
        // Check admin roles (they have full access)
        if (in_array($userRole, ['tadmin', 'cadmin', 'Admin', 'pclinician', 'dclinician'])) {
            return true;
        }
        
        // Query database for role if not in session
        try {
            $sql = "SELECT r.role_name
                    FROM users u
                    JOIN user_roles ur ON u.user_id = ur.user_id
                    JOIN roles r ON ur.role_id = r.role_id
                    WHERE u.user_id = :user_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $roleName = $row['role_name'];
                if ($roleName === self::CLINICIAN_ROLE ||
                    in_array($roleName, ['tadmin', 'cadmin', 'Admin', 'pclinician', 'dclinician'])) {
                    return true;
                }
            }
        } catch (Exception $e) {
            $this->logError('isClinicianRole', $e, ['user_id' => $userId]);
        }
        
        return false;
    }

    // =========================================================================
    // Meeting Operations
    // =========================================================================

    /**
     * Create a new video meeting
     *
     * @param string $userId UUID of the authenticated user creating the meeting
     * @return array API response with meeting data
     */
    public function createMeeting(string $userId): array
    {
        try {
            // Validate user can create meetings (all authenticated users)
            if (!$this->canCreateMeeting($userId)) {
                $this->logMeetingEvent('meeting_create_denied', null, $userId,
                    'User cannot create meetings', []);
                return ApiResponse::forbidden('Unable to create video meetings');
            }
            
            // Generate unique token
            $token = $this->generateUniqueToken();
            
            // Calculate token expiration (24 hours from now)
            $tokenExpiresAt = new DateTimeImmutable('+' . self::TOKEN_EXPIRY_HOURS . ' hours');
            
            // Create meeting entity
            $meeting = new VideoMeeting($userId, $token, $tokenExpiresAt);
            
            // Validate entity
            $validationErrors = $meeting->validate();
            if (!empty($validationErrors)) {
                return ApiResponse::validationError($validationErrors);
            }
            
            // Persist to database
            $meetingId = $this->meetingRepository->create($meeting);
            
            // Build shareable link
            $meetingUrl = $this->buildMeetingUrl($token);
            
            // Log event
            $this->logMeetingEvent('meeting_created', $meetingId, $userId,
                'Meeting created successfully', [
                    'token_expires_at' => $tokenExpiresAt->format('Y-m-d H:i:s'),
                ]);
            
            // Audit log
            $this->audit('CREATE', 'video_meeting', (string)$meetingId, [
                'created_by' => $userId,
            ]);
            
            return ApiResponse::success([
                'meeting_id' => $meetingId,
                'meeting_url' => $meetingUrl,
                'token' => $token,
                'expires_at' => $tokenExpiresAt->format('Y-m-d\TH:i:s\Z'),
                'is_active' => true,
            ], 'Meeting created successfully');
            
        } catch (Exception $e) {
            $this->logError('createMeeting', $e, ['user_id' => $userId]);
            return ApiResponse::serverError('Failed to create meeting');
        }
    }

    /**
     * Validate a meeting token
     * 
     * @param string $token Meeting access token
     * @return array API response with validation result
     */
    public function validateToken(string $token): array
    {
        try {
            // Sanitize token (hex chars only)
            $token = preg_replace('/[^a-f0-9]/i', '', $token);
            
            if (strlen($token) !== self::TOKEN_BYTES * 2) {
                $this->logMeetingEvent('token_validation_failed', null, null, 
                    'Invalid token format', ['token_length' => strlen($token)]);
                return ApiResponse::badRequest('Invalid token format');
            }
            
            // Find meeting by token
            $meeting = $this->meetingRepository->findByToken($token);
            
            if (!$meeting) {
                $this->logMeetingEvent('token_validation_failed', null, null, 
                    'Token not found', []);
                return ApiResponse::success([
                    'valid' => false,
                    'error' => 'Meeting not found',
                ]);
            }
            
            // Check if token is expired
            if ($meeting->isTokenExpired()) {
                $this->logMeetingEvent('token_validation_failed', $meeting->getMeetingId(), null, 
                    'Token expired', ['expired_at' => $meeting->getTokenExpiresAt()->format('Y-m-d H:i:s')]);
                return ApiResponse::success([
                    'valid' => false,
                    'error' => 'Meeting link has expired',
                    'meeting_id' => $meeting->getMeetingId(),
                ]);
            }
            
            // Check if meeting is still active
            if (!$meeting->isActive()) {
                $this->logMeetingEvent('token_validation_failed', $meeting->getMeetingId(), null, 
                    'Meeting ended', ['ended_at' => $meeting->getEndedAt()?->format('Y-m-d H:i:s')]);
                return ApiResponse::success([
                    'valid' => false,
                    'error' => 'Meeting has ended',
                    'meeting_id' => $meeting->getMeetingId(),
                ]);
            }
            
            // Token is valid
            $this->logMeetingEvent('token_validated', $meeting->getMeetingId(), null, 
                'Token validated successfully', []);
            
            return ApiResponse::success([
                'valid' => true,
                'meeting_id' => $meeting->getMeetingId(),
                'can_join' => $meeting->canJoin(),
            ]);
            
        } catch (Exception $e) {
            $this->logError('validateToken', $e);
            return ApiResponse::serverError('Failed to validate token');
        }
    }

    /**
     * Join a meeting
     * 
     * @param string $token Meeting access token
     * @param string $displayName Participant display name
     * @param string $ipAddress Client IP address
     * @return array API response with participant data
     */
    public function joinMeeting(string $token, string $displayName, string $ipAddress): array
    {
        try {
            // Validate token first
            $tokenResult = $this->validateToken($token);
            
            if (!($tokenResult['data']['valid'] ?? false)) {
                return ApiResponse::badRequest($tokenResult['data']['error'] ?? 'Invalid token');
            }
            
            $meetingId = $tokenResult['data']['meeting_id'];
            
            // Sanitize display name (XSS prevention)
            $displayName = $this->sanitizeDisplayName($displayName);
            
            // Validate display name length
            if (strlen($displayName) < 1 || strlen($displayName) > self::MAX_DISPLAY_NAME_LENGTH) {
                return ApiResponse::validationError([
                    'display_name' => 'Display name must be between 1 and ' . self::MAX_DISPLAY_NAME_LENGTH . ' characters',
                ]);
            }
            
            // Create participant entity
            $participant = new MeetingParticipant($meetingId, $displayName);
            $participant->setIpAddress($ipAddress);
            
            // Validate entity
            $validationErrors = $participant->validate();
            if (!empty($validationErrors)) {
                return ApiResponse::validationError($validationErrors);
            }
            
            // Add participant to database
            $participantId = $this->meetingRepository->addParticipant($participant);
            
            // Log join event
            $this->logMeetingEvent('participant_joined', $meetingId, null, 
                "Participant joined: {$displayName}", [
                    'participant_id' => $participantId,
                    'display_name' => $displayName,
                    'ip_address' => $this->maskIpAddress($ipAddress),
                ]);
            
            // Get current participant count
            $participantCount = $this->meetingRepository->countActiveParticipants($meetingId);
            
            return ApiResponse::success([
                'participant_id' => $participantId,
                'meeting_id' => $meetingId,
                'display_name' => $displayName,
                'joined_at' => date('Y-m-d\TH:i:s\Z'),
                'session_data' => [
                    'participant_count' => $participantCount,
                    'meeting_active' => true,
                ],
            ], 'Successfully joined meeting');
            
        } catch (Exception $e) {
            $this->logError('joinMeeting', $e, ['token' => substr($token, 0, 8) . '...']);
            return ApiResponse::serverError('Failed to join meeting');
        }
    }

    /**
     * Leave a meeting
     * 
     * @param int $participantId Participant ID
     * @param int $meetingId Meeting ID
     * @return bool True on success
     */
    public function leaveMeeting(int $participantId, int $meetingId): bool
    {
        try {
            // Verify participant exists and belongs to meeting
            $participant = $this->meetingRepository->findParticipantById($participantId);
            
            if (!$participant || $participant->getMeetingId() !== $meetingId) {
                return false;
            }
            
            // Update participant left_at timestamp
            $success = $this->meetingRepository->removeParticipant($participantId, new DateTime());
            
            if ($success) {
                // Log leave event
                $this->logMeetingEvent('participant_left', $meetingId, null, 
                    "Participant left: {$participant->getDisplayName()}", [
                        'participant_id' => $participantId,
                    ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logError('leaveMeeting', $e, [
                'participant_id' => $participantId,
                'meeting_id' => $meetingId,
            ]);
            return false;
        }
    }

    /**
     * End a meeting
     *
     * @param int $meetingId Meeting ID
     * @param string $userId User ID (UUID) attempting to end the meeting
     * @return bool True on success
     */
    public function endMeeting(int $meetingId, string $userId): bool
    {
        try {
            // Find meeting
            $meeting = $this->meetingRepository->findById($meetingId);
            
            if (!$meeting) {
                return false;
            }
            
            // Validate user is the meeting creator
            if ($meeting->getCreatedBy() !== $userId) {
                // Check if admin
                if (!$this->isAdmin($userId)) {
                    $this->logMeetingEvent('meeting_end_denied', $meetingId, $userId, 
                        'User is not meeting creator', []);
                    return false;
                }
            }
            
            // End the meeting
            $success = $this->meetingRepository->endMeeting($meetingId);
            
            if ($success) {
                // Log end event
                $this->logMeetingEvent('meeting_ended', $meetingId, $userId, 
                    'Meeting ended by host', []);
                
                // Audit log
                $this->audit('END', 'video_meeting', (string)$meetingId, [
                    'ended_by' => $userId,
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logError('endMeeting', $e, [
                'meeting_id' => $meetingId,
                'user_id' => $userId,
            ]);
            return false;
        }
    }

    /**
     * Get list of active participants in a meeting
     * 
     * @param int $meetingId Meeting ID
     * @return array Array of participant data
     */
    public function getParticipants(int $meetingId): array
    {
        try {
            $participants = $this->meetingRepository->getParticipants($meetingId, true);
            
            $participantData = [];
            foreach ($participants as $participant) {
                $participantData[] = [
                    'participant_id' => $participant->getParticipantId(),
                    'display_name' => $participant->getDisplayName(),
                    'joined_at' => $participant->getJoinedAt()?->format('Y-m-d\TH:i:s\Z'),
                ];
            }
            
            return $participantData;
            
        } catch (Exception $e) {
            $this->logError('getParticipants', $e, ['meeting_id' => $meetingId]);
            return [];
        }
    }

    // =========================================================================
    // Chat Operations
    // =========================================================================

    /**
     * Send a chat message
     * 
     * @param int $meetingId Meeting ID
     * @param int $participantId Participant ID
     * @param string $message Message text
     * @return array API response with message data
     */
    public function sendChatMessage(int $meetingId, int $participantId, string $message): array
    {
        try {
            // Verify participant belongs to meeting
            $participant = $this->meetingRepository->findParticipantById($participantId);
            
            if (!$participant || $participant->getMeetingId() !== $meetingId) {
                return ApiResponse::forbidden('Invalid participant');
            }
            
            // Check participant is still in meeting (hasn't left)
            if ($participant->getLeftAt() !== null) {
                return ApiResponse::forbidden('Participant has left the meeting');
            }
            
            // Verify meeting is still active
            $meeting = $this->meetingRepository->findById($meetingId);
            if (!$meeting || !$meeting->isActive()) {
                return ApiResponse::badRequest('Meeting has ended');
            }
            
            // Sanitize message (XSS prevention)
            $message = $this->sanitizeMessage($message);
            
            // Validate message
            if (empty($message)) {
                return ApiResponse::validationError(['message' => 'Message cannot be empty']);
            }
            
            if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
                return ApiResponse::validationError([
                    'message' => 'Message cannot exceed ' . self::MAX_MESSAGE_LENGTH . ' characters',
                ]);
            }
            
            // Create message entity
            $chatMessage = new MeetingChatMessage($meetingId, $participantId, $message);
            
            // Persist to database
            $messageId = $this->meetingRepository->addChatMessage($chatMessage);
            
            // Log message event
            $this->logMeetingEvent('chat_message_sent', $meetingId, null, 
                'Chat message sent', [
                    'participant_id' => $participantId,
                    'message_length' => strlen($message),
                ]);
            
            return ApiResponse::success([
                'message_id' => $messageId,
                'meeting_id' => $meetingId,
                'participant_id' => $participantId,
                'sent_at' => date('Y-m-d\TH:i:s\Z'),
            ], 'Message sent');
            
        } catch (Exception $e) {
            $this->logError('sendChatMessage', $e, [
                'meeting_id' => $meetingId,
                'participant_id' => $participantId,
            ]);
            return ApiResponse::serverError('Failed to send message');
        }
    }

    /**
     * Get chat history for a meeting
     * 
     * @param int $meetingId Meeting ID
     * @return array Array of chat messages
     */
    public function getChatHistory(int $meetingId): array
    {
        try {
            $messagesData = $this->meetingRepository->getChatMessages($meetingId);
            
            $messages = [];
            foreach ($messagesData as $item) {
                $message = $item['message'];
                $messages[] = [
                    'message_id' => $message->getMessageId(),
                    'participant_id' => $message->getParticipantId(),
                    'sender_name' => $item['sender_name'],
                    'message' => $message->getMessageText(),
                    'sent_at' => $message->getSentAt()?->format('Y-m-d\TH:i:s\Z'),
                ];
            }
            
            return $messages;
            
        } catch (Exception $e) {
            $this->logError('getChatHistory', $e, ['meeting_id' => $meetingId]);
            return [];
        }
    }

    /**
     * Get meeting link for a provider
     *
     * @param int $meetingId Meeting ID
     * @param string $providerId Provider user ID (UUID)
     * @return string|null Meeting URL or null if not authorized
     */
    public function getMeetingLink(int $meetingId, string $providerId): ?string
    {
        try {
            $meeting = $this->meetingRepository->findById($meetingId);
            
            if (!$meeting) {
                return null;
            }
            
            // Validate provider owns the meeting
            if ($meeting->getCreatedBy() !== $providerId && !$this->isAdmin($providerId)) {
                return null;
            }
            
            return $this->buildMeetingUrl($meeting->getToken());
            
        } catch (Exception $e) {
            $this->logError('getMeetingLink', $e, [
                'meeting_id' => $meetingId,
                'provider_id' => $providerId,
            ]);
            return null;
        }
    }

    /**
     * Get meeting by ID
     * 
     * @param int $meetingId Meeting ID
     * @return array|null Meeting data or null if not found
     */
    public function getMeeting(int $meetingId): ?array
    {
        try {
            $meeting = $this->meetingRepository->findById($meetingId);
            
            if (!$meeting) {
                return null;
            }
            
            return $meeting->toSafeArray();
            
        } catch (Exception $e) {
            $this->logError('getMeeting', $e, ['meeting_id' => $meetingId]);
            return null;
        }
    }

    /**
     * Get meetings created by a user
     *
     * @param string $userId User ID (UUID)
     * @param bool $activeOnly Only return active meetings
     * @param int $limit Max results
     * @return array Array of meeting data
     */
    public function getMyMeetings(string $userId, bool $activeOnly = false, int $limit = 50): array
    {
        try {
            $meetings = $this->meetingRepository->findByCreator($userId, $activeOnly, $limit);
            
            $meetingData = [];
            foreach ($meetings as $meeting) {
                $data = $meeting->toSafeArray();
                $data['meeting_url'] = $this->buildMeetingUrl($meeting->getToken());
                $data['participant_count'] = $this->meetingRepository->countActiveParticipants(
                    $meeting->getMeetingId()
                );
                $meetingData[] = $data;
            }
            
            return $meetingData;
            
        } catch (Exception $e) {
            $this->logError('getMyMeetings', $e, ['user_id' => $userId]);
            return [];
        }
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Build meeting URL from token
     * 
     * @param string $token Meeting token
     * @return string Full meeting URL
     */
    private function buildMeetingUrl(string $token): string
    {
        return "{$this->baseUrl}/video/join?token={$token}";
    }

    /**
     * Sanitize display name for XSS prevention
     * 
     * @param string $displayName Raw display name
     * @return string Sanitized display name
     */
    private function sanitizeDisplayName(string $displayName): string
    {
        // Remove HTML tags
        $displayName = strip_tags($displayName);
        
        // Encode special characters
        $displayName = htmlspecialchars($displayName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Trim whitespace
        $displayName = trim($displayName);
        
        // Truncate if too long
        if (strlen($displayName) > self::MAX_DISPLAY_NAME_LENGTH) {
            $displayName = substr($displayName, 0, self::MAX_DISPLAY_NAME_LENGTH);
        }
        
        return $displayName;
    }

    /**
     * Sanitize message for XSS prevention
     * 
     * @param string $message Raw message
     * @return string Sanitized message
     */
    private function sanitizeMessage(string $message): string
    {
        // Remove HTML tags
        $message = strip_tags($message);
        
        // Encode special characters
        $message = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Trim whitespace
        $message = trim($message);
        
        return $message;
    }

    /**
     * Mask IP address for privacy in logs
     * 
     * @param string $ipAddress Full IP address
     * @return string Partially masked IP
     */
    private function maskIpAddress(string $ipAddress): string
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: mask last octet
            $parts = explode('.', $ipAddress);
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }
        
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: mask last 4 groups
            $parts = explode(':', $ipAddress);
            for ($i = 4; $i < count($parts); $i++) {
                $parts[$i] = 'xxxx';
            }
            return implode(':', $parts);
        }
        
        return 'unknown';
    }

    /**
     * Check if user is admin
     * 
     * @param int $userId User ID
     * @return bool True if admin
     */
    private function isAdmin(int $userId): bool
    {
        $role = $this->getCurrentUserRole();
        return in_array($role, ['tadmin', 'cadmin', 'Admin']);
    }

    /**
     * Log a meeting-related event
     * 
     * @param string $logType Event type
     * @param int|null $meetingId Meeting ID
     * @param int|null $userId User ID
     * @param string $action Action description
     * @param array $details Additional details
     */
    private function logMeetingEvent(
        string $logType,
        string|int|null $meetingId,
        ?string $userId,
        string $action,
        array $details
    ): void {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $this->meetingRepository->logEvent($logType, $meetingId, $userId, $action, $details, $ipAddress);
        } catch (Exception $e) {
            // Don't fail the main operation if logging fails
            error_log("Failed to log meeting event: " . $e->getMessage());
        }
    }

    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    public function getClientIpAddress(): string
    {
        // Check for proxy headers
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated list (proxy chain)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }

    // =========================================================================
    // Peer/Signaling Operations (for PeerJS WebRTC)
    // =========================================================================

    /**
     * Register a PeerJS peer ID with a meeting participant
     *
     * @param int $meetingId Meeting ID
     * @param int $participantId Participant ID
     * @param string $peerId PeerJS peer ID
     * @return bool True on success
     */
    public function registerPeer(int $meetingId, int $participantId, string $peerId): bool
    {
        try {
            // Sanitize peer ID (alphanumeric with some allowed chars)
            $peerId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $peerId);
            
            if (empty($peerId) || strlen($peerId) > 64) {
                return false;
            }
            
            // Verify participant exists and belongs to meeting
            $participant = $this->meetingRepository->findParticipantById($participantId);
            
            if (!$participant || $participant->getMeetingId() !== $meetingId) {
                $this->logMeetingEvent('peer_register_denied', $meetingId, null,
                    'Invalid participant', [
                        'participant_id' => $participantId,
                        'peer_id' => $peerId,
                    ]);
                return false;
            }
            
            // Check if participant has already left
            if ($participant->getLeftAt() !== null) {
                return false;
            }
            
            // Verify meeting is still active
            $meeting = $this->meetingRepository->findById($meetingId);
            if (!$meeting || !$meeting->isActive()) {
                return false;
            }
            
            // Update peer ID
            $success = $this->meetingRepository->updatePeerId($participantId, $peerId);
            
            if ($success) {
                $this->logMeetingEvent('peer_registered', $meetingId, null,
                    'Peer registered for participant', [
                        'participant_id' => $participantId,
                        'peer_id' => $peerId,
                        'display_name' => $participant->getDisplayName(),
                    ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logError('registerPeer', $e, [
                'meeting_id' => $meetingId,
                'participant_id' => $participantId,
            ]);
            return false;
        }
    }

    /**
     * Update heartbeat and return active peers
     *
     * @param int $participantId Participant ID
     * @return array Result with active peers or error
     */
    public function updateHeartbeat(int $participantId): array
    {
        try {
            // Find participant
            $participant = $this->meetingRepository->findParticipantById($participantId);
            
            if (!$participant) {
                return [
                    'success' => false,
                    'error' => 'Participant not found',
                ];
            }
            
            // Check if participant has left
            if ($participant->getLeftAt() !== null) {
                return [
                    'success' => false,
                    'error' => 'Participant has left the meeting',
                ];
            }
            
            $meetingId = $participant->getMeetingId();
            
            // Verify meeting is still active
            $meeting = $this->meetingRepository->findById($meetingId);
            if (!$meeting || !$meeting->isActive()) {
                return [
                    'success' => false,
                    'error' => 'Meeting has ended',
                ];
            }
            
            // Update heartbeat
            $this->meetingRepository->updateHeartbeat($participantId);
            
            // Remove stale participants (haven't sent heartbeat in 30 seconds)
            $staleCount = $this->meetingRepository->removeStaleParticipants($meetingId, 30);
            
            if ($staleCount > 0) {
                $this->logMeetingEvent('stale_participants_removed', $meetingId, null,
                    "Removed {$staleCount} stale participants", [
                        'count' => $staleCount,
                    ]);
            }
            
            // Get current active peers
            $activePeers = $this->meetingRepository->getActivePeers($meetingId, 30);
            
            return [
                'success' => true,
                'meeting_id' => $meetingId,
                'active_peers' => $activePeers,
            ];
            
        } catch (Exception $e) {
            $this->logError('updateHeartbeat', $e, [
                'participant_id' => $participantId,
            ]);
            return [
                'success' => false,
                'error' => 'Failed to update heartbeat',
            ];
        }
    }

    /**
     * Get all active peers in a meeting
     *
     * @param int $meetingId Meeting ID
     * @return array Array of active peer data
     */
    public function getActivePeers(int $meetingId): array
    {
        try {
            // Verify meeting exists and is active
            $meeting = $this->meetingRepository->findById($meetingId);
            
            if (!$meeting) {
                return [];
            }
            
            // Remove stale participants first
            $this->meetingRepository->removeStaleParticipants($meetingId, 30);
            
            // Get active peers
            return $this->meetingRepository->getActivePeers($meetingId, 30);
            
        } catch (Exception $e) {
            $this->logError('getActivePeers', $e, ['meeting_id' => $meetingId]);
            return [];
        }
    }

    /**
     * Disconnect a peer (clear peer ID and optionally mark as left)
     *
     * @param int $meetingId Meeting ID
     * @param int $participantId Participant ID
     * @return bool True on success
     */
    public function disconnectPeer(int $meetingId, int $participantId): bool
    {
        try {
            // Verify participant exists and belongs to meeting
            $participant = $this->meetingRepository->findParticipantById($participantId);
            
            if (!$participant || $participant->getMeetingId() !== $meetingId) {
                return false;
            }
            
            // Clear the peer ID
            $success = $this->meetingRepository->clearPeerId($meetingId, $participantId);
            
            if ($success) {
                $this->logMeetingEvent('peer_disconnected', $meetingId, null,
                    'Peer disconnected', [
                        'participant_id' => $participantId,
                        'display_name' => $participant->getDisplayName(),
                    ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logError('disconnectPeer', $e, [
                'meeting_id' => $meetingId,
                'participant_id' => $participantId,
            ]);
            return false;
        }
    }
}
