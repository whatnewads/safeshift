<?php
/**
 * VideoMeetingRepository.php - Video Meeting Repository for SafeShift EHR
 * 
 * Data access layer for video meeting functionality.
 * Handles CRUD operations for meetings, participants, chat messages, and logging.
 * Uses prepared statements for SQL injection prevention.
 * 
 * @package    SafeShift\Model\Repositories
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Repositories;

use Model\Entities\VideoMeeting;
use Model\Entities\MeetingParticipant;
use Model\Entities\MeetingChatMessage;
use PDO;
use PDOException;
use DateTime;
use DateTimeImmutable;

/**
 * Video Meeting Repository
 * 
 * Data access layer for video meeting management in the SafeShift EHR system.
 * All methods use prepared statements to prevent SQL injection.
 */
class VideoMeetingRepository
{
    /**
     * @var string Table name for video meetings
     * Use 'video_meetings_v2' if original table has tablespace issues
     */
    private const TABLE_VIDEO_MEETINGS = 'video_meetings_v2';
    
    /** @var string Table name for participants */
    private const TABLE_PARTICIPANTS = 'meeting_participants';
    
    /** @var string Table name for chat messages */
    private const TABLE_CHAT_MESSAGES = 'meeting_chat_messages';
    
    /** @var string Table name for logs */
    private const TABLE_LOGS = 'video_meeting_logs';

    /** @var PDO Database connection */
    private PDO $pdo;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection instance
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // Meeting Operations
    // =========================================================================

    /**
     * Create a new video meeting
     * 
     * @param VideoMeeting $meeting Meeting entity to create
     * @return int The created meeting ID
     * @throws PDOException On database error
     */
    public function create(VideoMeeting $meeting): int
    {
        $table = self::TABLE_VIDEO_MEETINGS;
        $sql = "INSERT INTO {$table}
                (created_by, token, token_expires_at, is_active, created_at)
                VALUES (:created_by, :token, :token_expires_at, :is_active, :created_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'created_by' => $meeting->getCreatedBy(),
            'token' => $meeting->getToken(),
            'token_expires_at' => $meeting->getTokenExpiresAt()->format('Y-m-d H:i:s'),
            'is_active' => $meeting->isActive() ? 1 : 0,
            'created_at' => $meeting->getCreatedAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
        ]);

        $meetingId = (int) $this->pdo->lastInsertId();
        $meeting->setMeetingId($meetingId);

        return $meetingId;
    }

    /**
     * Find a meeting by its secure token
     * 
     * @param string $token Meeting access token
     * @return VideoMeeting|null Meeting entity or null if not found
     */
    public function findByToken(string $token): ?VideoMeeting
    {
        $table = self::TABLE_VIDEO_MEETINGS;
        $sql = "SELECT meeting_id, created_by, created_at, token,
                       token_expires_at, is_active, ended_at
                FROM {$table}
                WHERE token = :token
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return VideoMeeting::fromArray($row);
    }

    /**
     * Find a meeting by its ID
     * 
     * @param int $meetingId Meeting ID
     * @return VideoMeeting|null Meeting entity or null if not found
     */
    public function findById(int $meetingId): ?VideoMeeting
    {
        $table = self::TABLE_VIDEO_MEETINGS;
        $sql = "SELECT meeting_id, created_by, created_at, token,
                       token_expires_at, is_active, ended_at
                FROM {$table}
                WHERE meeting_id = :meeting_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['meeting_id' => $meetingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return VideoMeeting::fromArray($row);
    }

    /**
     * Find all meetings created by a specific user
     *
     * @param string $userId Creator user ID (UUID)
     * @param bool $activeOnly Only return active meetings
     * @param int $limit Maximum number of results
     * @param int $offset Pagination offset
     * @return array<VideoMeeting> Array of meeting entities
     */
    public function findByCreator(
        string $userId,
        bool $activeOnly = false,
        int $limit = 50,
        int $offset = 0
    ): array {
        $table = self::TABLE_VIDEO_MEETINGS;
        $sql = "SELECT meeting_id, created_by, created_at, token,
                       token_expires_at, is_active, ended_at
                FROM {$table}
                WHERE created_by = :user_id";

        if ($activeOnly) {
            $sql .= " AND is_active = 1 AND token_expires_at > NOW()";
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $meetings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $meetings[] = VideoMeeting::fromArray($row);
        }

        return $meetings;
    }

    /**
     * Update meeting status (active/ended)
     * 
     * @param int $meetingId Meeting ID to update
     * @param bool $isActive New active status
     * @param DateTime|null $endedAt End timestamp (null to clear)
     * @return bool True on success, false on failure
     */
    public function updateStatus(int $meetingId, bool $isActive, ?DateTime $endedAt = null): bool
    {
        $table = self::TABLE_VIDEO_MEETINGS;
        $sql = "UPDATE {$table}
                SET is_active = :is_active, ended_at = :ended_at
                WHERE meeting_id = :meeting_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'meeting_id' => $meetingId,
            'is_active' => $isActive ? 1 : 0,
            'ended_at' => $endedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * End a meeting
     * 
     * @param int $meetingId Meeting ID to end
     * @return bool True on success
     */
    public function endMeeting(int $meetingId): bool
    {
        return $this->updateStatus($meetingId, false, new DateTime());
    }

    // =========================================================================
    // Participant Operations
    // =========================================================================

    /**
     * Add a participant to a meeting
     * 
     * @param MeetingParticipant $participant Participant entity to add
     * @return int The created participant ID
     * @throws PDOException On database error
     */
    public function addParticipant(MeetingParticipant $participant): int
    {
        $sql = "INSERT INTO meeting_participants 
                (meeting_id, display_name, joined_at, ip_address)
                VALUES (:meeting_id, :display_name, :joined_at, :ip_address)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'meeting_id' => $participant->getMeetingId(),
            'display_name' => $participant->getDisplayName(),
            'joined_at' => $participant->getJoinedAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            'ip_address' => $participant->getIpAddress(),
        ]);

        $participantId = (int) $this->pdo->lastInsertId();
        $participant->setParticipantId($participantId);

        return $participantId;
    }

    /**
     * Mark a participant as having left the meeting
     * 
     * @param int $participantId Participant ID
     * @param DateTime $leftAt Timestamp when participant left
     * @return bool True on success, false on failure
     */
    public function removeParticipant(int $participantId, DateTime $leftAt): bool
    {
        $sql = "UPDATE meeting_participants 
                SET left_at = :left_at
                WHERE participant_id = :participant_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'participant_id' => $participantId,
            'left_at' => $leftAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get participants for a meeting
     * 
     * @param int $meetingId Meeting ID
     * @param bool $activeOnly Only return participants currently in meeting
     * @return array<MeetingParticipant> Array of participant entities
     */
    public function getParticipants(int $meetingId, bool $activeOnly = true): array
    {
        $sql = "SELECT participant_id, meeting_id, display_name, 
                       joined_at, left_at, ip_address
                FROM meeting_participants
                WHERE meeting_id = :meeting_id";

        if ($activeOnly) {
            $sql .= " AND left_at IS NULL";
        }

        $sql .= " ORDER BY joined_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['meeting_id' => $meetingId]);

        $participants = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $participants[] = MeetingParticipant::fromArray($row);
        }

        return $participants;
    }

    /**
     * Find a participant by ID
     * 
     * @param int $participantId Participant ID
     * @return MeetingParticipant|null Participant entity or null
     */
    public function findParticipantById(int $participantId): ?MeetingParticipant
    {
        $sql = "SELECT participant_id, meeting_id, display_name, 
                       joined_at, left_at, ip_address
                FROM meeting_participants
                WHERE participant_id = :participant_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['participant_id' => $participantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return MeetingParticipant::fromArray($row);
    }

    /**
     * Count active participants in a meeting
     * 
     * @param int $meetingId Meeting ID
     * @return int Number of active participants
     */
    public function countActiveParticipants(int $meetingId): int
    {
        $sql = "SELECT COUNT(*) FROM meeting_participants
                WHERE meeting_id = :meeting_id AND left_at IS NULL";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['meeting_id' => $meetingId]);

        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // Chat Message Operations
    // =========================================================================

    /**
     * Add a chat message to a meeting
     * 
     * @param MeetingChatMessage $message Message entity to add
     * @return int The created message ID
     * @throws PDOException On database error
     */
    public function addChatMessage(MeetingChatMessage $message): int
    {
        $sql = "INSERT INTO meeting_chat_messages 
                (meeting_id, participant_id, message_text, sent_at)
                VALUES (:meeting_id, :participant_id, :message_text, :sent_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'meeting_id' => $message->getMeetingId(),
            'participant_id' => $message->getParticipantId(),
            'message_text' => $message->getMessageText(),
            'sent_at' => $message->getSentAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
        ]);

        $messageId = (int) $this->pdo->lastInsertId();
        $message->setMessageId($messageId);

        return $messageId;
    }

    /**
     * Get chat messages for a meeting
     * 
     * @param int $meetingId Meeting ID
     * @param int|null $afterMessageId Only return messages after this ID (for pagination)
     * @param int $limit Maximum number of messages to return
     * @return array<MeetingChatMessage> Array of message entities
     */
    public function getChatMessages(int $meetingId, ?int $afterMessageId = null, int $limit = 100): array
    {
        $sql = "SELECT m.message_id, m.meeting_id, m.participant_id, 
                       m.message_text, m.sent_at,
                       p.display_name AS sender_name
                FROM meeting_chat_messages m
                LEFT JOIN meeting_participants p ON m.participant_id = p.participant_id
                WHERE m.meeting_id = :meeting_id";

        $params = ['meeting_id' => $meetingId];

        if ($afterMessageId !== null) {
            $sql .= " AND m.message_id > :after_id";
            $params['after_id'] = $afterMessageId;
        }

        $sql .= " ORDER BY m.sent_at ASC, m.message_id ASC LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $messages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $message = MeetingChatMessage::fromArray($row);
            // Store sender name as additional data (not part of entity but useful)
            $messages[] = [
                'message' => $message,
                'sender_name' => $row['sender_name'] ?? 'Unknown',
            ];
        }

        return $messages;
    }

    /**
     * Get chat messages as entities only (without sender name)
     * 
     * @param int $meetingId Meeting ID
     * @return array<MeetingChatMessage> Array of message entities
     */
    public function getChatMessagesEntities(int $meetingId): array
    {
        $sql = "SELECT message_id, meeting_id, participant_id, message_text, sent_at
                FROM meeting_chat_messages
                WHERE meeting_id = :meeting_id
                ORDER BY sent_at ASC, message_id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['meeting_id' => $meetingId]);

        $messages = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $messages[] = MeetingChatMessage::fromArray($row);
        }

        return $messages;
    }

    // =========================================================================
    // Logging Operations
    // =========================================================================

    /**
     * Log a video meeting event
     *
     * @param string $logType Event type (meeting_created, participant_joined, etc.)
     * @param string|int|null $meetingId Meeting ID (nullable for pre-meeting events)
     * @param string|int|null $userId User ID (nullable for guest events, can be UUID string)
     * @param string $action Human-readable action description
     * @param array<string, mixed> $details Additional event details
     * @param string $ipAddress Client IP address
     * @return void
     */
    public function logEvent(
        string $logType,
        string|int|null $meetingId,
        string|int|null $userId,
        string $action,
        array $details,
        string $ipAddress
    ): void {
        $sql = "INSERT INTO video_meeting_logs
                (log_type, meeting_id, user_id, action, details, ip_address, created_at)
                VALUES (:log_type, :meeting_id, :user_id, :action, :details, :ip_address, NOW())";

        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            // Table may not exist or SQL error - log and return gracefully
            error_log("VideoMeetingRepository::logEvent - Failed to prepare statement");
            return;
        }
        $stmt->execute([
            'log_type' => $logType,
            'meeting_id' => $meetingId,
            'user_id' => $userId,
            'action' => $action,
            'details' => json_encode($details),
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Get logs for a specific meeting
     * 
     * @param int $meetingId Meeting ID
     * @param string|null $logType Filter by log type
     * @param int $limit Maximum number of logs
     * @return array<array<string, mixed>> Array of log entries
     */
    public function getMeetingLogs(int $meetingId, ?string $logType = null, int $limit = 100): array
    {
        $sql = "SELECT log_id, log_type, meeting_id, user_id, action, 
                       details, ip_address, created_at
                FROM video_meeting_logs
                WHERE meeting_id = :meeting_id";

        $params = ['meeting_id' => $meetingId];

        if ($logType !== null) {
            $sql .= " AND log_type = :log_type";
            $params['log_type'] = $logType;
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $logs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['details'] = $row['details'] ? json_decode($row['details'], true) : [];
            $logs[] = $row;
        }

        return $logs;
    }

    /**
     * Get logs by user
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of logs
     * @return array<array<string, mixed>> Array of log entries
     */
    public function getLogsByUser(int $userId, int $limit = 100): array
    {
        $sql = "SELECT log_id, log_type, meeting_id, user_id, action, 
                       details, ip_address, created_at
                FROM video_meeting_logs
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $logs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['details'] = $row['details'] ? json_decode($row['details'], true) : [];
            $logs[] = $row;
        }

        return $logs;
    }

    // =========================================================================
    // Utility Operations
    // =========================================================================

    /**
     * Check if a token is unique
     * 
     * @param string $token Token to check
     * @return bool True if token is unique (doesn't exist)
     */
    public function isTokenUnique(string $token): bool
    {
        $table = self::TABLE_VIDEO_MEETINGS;
        $sql = "SELECT COUNT(*) FROM {$table} WHERE token = :token";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['token' => $token]);

        return (int) $stmt->fetchColumn() === 0;
    }

    /**
     * Get meeting statistics for a user
     * 
     * @param int $userId User ID
     * @return array<string, mixed> Statistics array
     */
    public function getUserMeetingStats(int $userId): array
    {
        $table = self::TABLE_VIDEO_MEETINGS;
        
        // Total meetings created
        $sql = "SELECT COUNT(*) FROM {$table} WHERE created_by = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $totalMeetings = (int) $stmt->fetchColumn();

        // Active meetings
        $sql = "SELECT COUNT(*) FROM {$table}
                WHERE created_by = :user_id AND is_active = 1 AND token_expires_at > NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $activeMeetings = (int) $stmt->fetchColumn();

        // Total participants across all meetings
        $sql = "SELECT COUNT(mp.participant_id)
                FROM meeting_participants mp
                JOIN {$table} vm ON mp.meeting_id = vm.meeting_id
                WHERE vm.created_by = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $totalParticipants = (int) $stmt->fetchColumn();

        return [
            'total_meetings' => $totalMeetings,
            'active_meetings' => $activeMeetings,
            'total_participants' => $totalParticipants,
        ];
    }

    /**
     * Clean up expired meetings (for cron job)
     * 
     * @param int $olderThanDays Delete meetings older than this many days
     * @return int Number of deleted meetings
     */
    public function cleanupExpiredMeetings(int $olderThanDays = 30): int
    {
        $table = self::TABLE_VIDEO_MEETINGS;
        $sql = "DELETE FROM {$table}
                WHERE is_active = 0
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['days' => $olderThanDays]);

        return $stmt->rowCount();
    }

    /**
     * Deactivate all expired meetings
     * 
     * @return int Number of meetings deactivated
     */
    public function deactivateExpiredMeetings(): int
    {
        $table = self::TABLE_VIDEO_MEETINGS;
        $sql = "UPDATE {$table}
                SET is_active = 0, ended_at = NOW()
                WHERE is_active = 1 AND token_expires_at < NOW()";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->rowCount();
    }

    // =========================================================================
    // Peer/Signaling Operations
    // =========================================================================

    /**
     * Update the PeerJS peer ID for a participant
     *
     * @param int $participantId Participant ID
     * @param string|null $peerId PeerJS peer ID (null to clear)
     * @return bool True on success, false on failure
     */
    public function updatePeerId(int $participantId, ?string $peerId): bool
    {
        $sql = "UPDATE meeting_participants
                SET peer_id = :peer_id, last_heartbeat = NOW()
                WHERE participant_id = :participant_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'participant_id' => $participantId,
            'peer_id' => $peerId,
        ]);
    }

    /**
     * Update the heartbeat timestamp for a participant
     *
     * @param int $participantId Participant ID
     * @return bool True on success, false on failure
     */
    public function updateHeartbeat(int $participantId): bool
    {
        $sql = "UPDATE meeting_participants
                SET last_heartbeat = NOW()
                WHERE participant_id = :participant_id AND left_at IS NULL";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['participant_id' => $participantId]);
    }

    /**
     * Get all active peers in a meeting (not stale, not left)
     *
     * @param int $meetingId Meeting ID
     * @param int $staleThreshold Seconds after which a participant is considered stale
     * @return array<array<string, mixed>> Array of peer data
     */
    public function getActivePeers(int $meetingId, int $staleThreshold = 30): array
    {
        $sql = "SELECT participant_id, peer_id, display_name, joined_at, last_heartbeat
                FROM meeting_participants
                WHERE meeting_id = :meeting_id
                AND left_at IS NULL
                AND peer_id IS NOT NULL
                AND (last_heartbeat IS NULL OR last_heartbeat > DATE_SUB(NOW(), INTERVAL :threshold SECOND))
                ORDER BY joined_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':meeting_id', $meetingId, PDO::PARAM_INT);
        $stmt->bindValue(':threshold', $staleThreshold, PDO::PARAM_INT);
        $stmt->execute();

        $peers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $peers[] = [
                'participant_id' => (int) $row['participant_id'],
                'peer_id' => $row['peer_id'],
                'display_name' => $row['display_name'],
                'joined_at' => $row['joined_at'],
                'last_heartbeat' => $row['last_heartbeat'],
            ];
        }

        return $peers;
    }

    /**
     * Remove stale participants from a meeting
     * Marks participants as left if they haven't sent a heartbeat within the threshold
     *
     * @param int $meetingId Meeting ID
     * @param int $staleThreshold Seconds after which a participant is considered stale
     * @return int Number of participants marked as stale/left
     */
    public function removeStaleParticipants(int $meetingId, int $staleThreshold = 30): int
    {
        $sql = "UPDATE meeting_participants
                SET left_at = NOW(), peer_id = NULL
                WHERE meeting_id = :meeting_id
                AND left_at IS NULL
                AND last_heartbeat IS NOT NULL
                AND last_heartbeat < DATE_SUB(NOW(), INTERVAL :threshold SECOND)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':meeting_id', $meetingId, PDO::PARAM_INT);
        $stmt->bindValue(':threshold', $staleThreshold, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Find participant by meeting ID and peer ID
     *
     * @param int $meetingId Meeting ID
     * @param string $peerId PeerJS peer ID
     * @return MeetingParticipant|null Participant entity or null
     */
    public function findParticipantByPeerId(int $meetingId, string $peerId): ?MeetingParticipant
    {
        $sql = "SELECT participant_id, meeting_id, display_name,
                       joined_at, left_at, ip_address, peer_id, last_heartbeat
                FROM meeting_participants
                WHERE meeting_id = :meeting_id AND peer_id = :peer_id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'meeting_id' => $meetingId,
            'peer_id' => $peerId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return MeetingParticipant::fromArray($row);
    }

    /**
     * Clear peer ID for a participant (on disconnect)
     *
     * @param int $meetingId Meeting ID
     * @param int $participantId Participant ID
     * @return bool True on success
     */
    public function clearPeerId(int $meetingId, int $participantId): bool
    {
        $sql = "UPDATE meeting_participants
                SET peer_id = NULL
                WHERE meeting_id = :meeting_id AND participant_id = :participant_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'meeting_id' => $meetingId,
            'participant_id' => $participantId,
        ]);
    }
}
