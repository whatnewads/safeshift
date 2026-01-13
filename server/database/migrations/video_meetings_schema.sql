-- ============================================================================
-- Video Meetings Schema Migration
-- SafeShift EHR - WebRTC Video Meeting Feature
--
-- This migration creates the necessary tables for video meeting functionality:
-- - video_meetings: Core meeting records
-- - meeting_participants: Participant tracking
-- - meeting_chat_messages: In-meeting chat history
-- - video_meeting_logs: Audit logging for all meeting events
--
-- @author     SafeShift Development Team
-- @copyright  2025 SafeShift EHR
-- @license    Proprietary
-- ============================================================================

-- Drop existing tables if they exist (for clean reinstall)
DROP TABLE IF EXISTS `meeting_chat_messages`;
DROP TABLE IF EXISTS `meeting_participants`;
DROP TABLE IF EXISTS `video_meeting_logs`;
DROP TABLE IF EXISTS `video_meetings`;

-- ============================================================================
-- Table: video_meetings
-- Core video meeting records with token-based access
-- ============================================================================
CREATE TABLE `video_meetings` (
    `meeting_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique meeting identifier',
    `created_by` INT UNSIGNED NOT NULL COMMENT 'User ID of meeting creator (clinician)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Meeting creation timestamp',
    `token` VARCHAR(128) NOT NULL COMMENT 'Unique secure token for meeting access',
    `token_expires_at` DATETIME NOT NULL COMMENT 'Token expiration timestamp',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether meeting is currently active',
    `ended_at` DATETIME NULL DEFAULT NULL COMMENT 'Meeting end timestamp',
    
    -- Indexes for performance
    UNIQUE KEY `idx_token` (`token`) COMMENT 'Unique index for fast token lookups',
    INDEX `idx_created_by` (`created_by`) COMMENT 'Index for creator-based queries',
    INDEX `idx_is_active` (`is_active`) COMMENT 'Index for active meeting queries',
    INDEX `idx_token_expires_at` (`token_expires_at`) COMMENT 'Index for expiration cleanup'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Video meeting records for WebRTC telehealth sessions';

-- ============================================================================
-- Table: meeting_participants
-- Tracks all participants who join video meetings
-- ============================================================================
CREATE TABLE `meeting_participants` (
    `participant_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique participant record ID',
    `meeting_id` INT UNSIGNED NOT NULL COMMENT 'Reference to video_meetings table',
    `display_name` VARCHAR(100) NOT NULL COMMENT 'Participant display name in meeting',
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when participant joined',
    `left_at` DATETIME NULL DEFAULT NULL COMMENT 'Timestamp when participant left',
    `ip_address` VARCHAR(45) NULL COMMENT 'Participant IP address (supports IPv6)',
    
    -- Indexes for performance
    INDEX `idx_meeting_id` (`meeting_id`) COMMENT 'Index for meeting-based queries',
    INDEX `idx_joined_at` (`joined_at`) COMMENT 'Index for time-based queries'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Participant records for video meeting sessions';

-- ============================================================================
-- Table: meeting_chat_messages
-- Stores in-meeting chat messages for audit and review
-- ============================================================================
CREATE TABLE `meeting_chat_messages` (
    `message_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique message ID',
    `meeting_id` INT UNSIGNED NOT NULL COMMENT 'Reference to video_meetings table',
    `participant_id` INT UNSIGNED NOT NULL COMMENT 'Reference to meeting_participants table',
    `message_text` TEXT NOT NULL COMMENT 'Chat message content',
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Message sent timestamp',
    
    -- Indexes for performance
    INDEX `idx_meeting_id` (`meeting_id`) COMMENT 'Index for meeting-based message queries',
    INDEX `idx_participant_id` (`participant_id`) COMMENT 'Index for participant message queries',
    INDEX `idx_sent_at` (`sent_at`) COMMENT 'Index for chronological message ordering'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Chat message history for video meeting sessions';

-- ============================================================================
-- Table: video_meeting_logs
-- Comprehensive audit logging for all meeting-related events
-- ============================================================================
CREATE TABLE `video_meeting_logs` (
    `log_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique log entry ID',
    `log_type` VARCHAR(50) NOT NULL COMMENT 'Event type (meeting_created, token_validated, participant_joined, etc.)',
    `meeting_id` INT UNSIGNED NULL COMMENT 'Reference to video_meetings (nullable for pre-meeting events)',
    `user_id` INT UNSIGNED NULL COMMENT 'User ID if authenticated (nullable for guest events)',
    `action` VARCHAR(100) NOT NULL COMMENT 'Human-readable action description',
    `details` TEXT NULL COMMENT 'Additional event details in JSON format',
    `ip_address` VARCHAR(45) NULL COMMENT 'Client IP address (supports IPv6)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Log entry timestamp',
    
    -- Indexes for performance and audit queries
    INDEX `idx_meeting_id` (`meeting_id`) COMMENT 'Index for meeting-specific log queries',
    INDEX `idx_log_type` (`log_type`) COMMENT 'Index for event type filtering',
    INDEX `idx_created_at` (`created_at`) COMMENT 'Index for time-based log queries',
    INDEX `idx_user_id` (`user_id`) COMMENT 'Index for user activity queries',
    INDEX `idx_ip_address` (`ip_address`) COMMENT 'Index for IP-based security queries'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log for video meeting events and security tracking';

-- ============================================================================
-- Log Type Reference (for documentation)
-- ============================================================================
-- Common log_type values:
-- - 'meeting_created'      : New meeting created by clinician
-- - 'meeting_started'      : Meeting session started
-- - 'meeting_ended'        : Meeting session ended
-- - 'token_generated'      : New meeting token generated
-- - 'token_validated'      : Token successfully validated
-- - 'token_expired'        : Token expired during validation
-- - 'token_invalid'        : Invalid token attempted
-- - 'participant_joined'   : Participant joined meeting
-- - 'participant_left'     : Participant left meeting
-- - 'chat_message_sent'    : Chat message sent in meeting
-- - 'screen_share_started' : Screen sharing started
-- - 'screen_share_stopped' : Screen sharing stopped
-- - 'connection_error'     : WebRTC connection error
-- - 'security_violation'   : Security policy violation detected
-- ============================================================================
