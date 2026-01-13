-- ============================================================================
-- Video Meetings Peer Support Migration
-- SafeShift EHR - WebRTC Signaling Support for PeerJS
-- 
-- This migration adds peer_id and heartbeat columns to support WebRTC
-- signaling via PeerJS cloud server with PHP-based room state management.
-- 
-- Run this migration after video_meetings_tables.sql
-- ============================================================================

-- Add peer_id and last_heartbeat columns to meeting_participants table
-- peer_id: Stores the PeerJS peer ID for WebRTC connections
-- last_heartbeat: Tracks when the participant last sent a heartbeat (for stale detection)

ALTER TABLE meeting_participants 
ADD COLUMN IF NOT EXISTS peer_id VARCHAR(64) NULL COMMENT 'PeerJS peer ID for WebRTC signaling',
ADD COLUMN IF NOT EXISTS last_heartbeat TIMESTAMP NULL COMMENT 'Last heartbeat timestamp for stale detection';

-- Create index for efficient peer lookups
CREATE INDEX IF NOT EXISTS idx_meeting_participants_peer_id 
ON meeting_participants(peer_id);

-- Create index for efficient stale participant detection
CREATE INDEX IF NOT EXISTS idx_meeting_participants_heartbeat 
ON meeting_participants(meeting_id, last_heartbeat);

-- Create index for active peer lookups
CREATE INDEX IF NOT EXISTS idx_meeting_participants_active_peers 
ON meeting_participants(meeting_id, left_at, last_heartbeat);

-- ============================================================================
-- Verification Query
-- Run this to verify the migration was successful:
-- ============================================================================
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_NAME = 'meeting_participants'
-- AND COLUMN_NAME IN ('peer_id', 'last_heartbeat');
-- ============================================================================
