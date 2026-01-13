-- Session Management Tables Migration
-- SafeShift EHR - HIPAA Compliant Session Tracking
-- Created: 2025-12-28
-- 
-- Purpose: Implements database-backed session management to fix the ~5 minute
-- auto-logout issue and provide proper session tracking across devices.

-- ============================================================================
-- User Sessions Table (tracks all active sessions per user)
-- ============================================================================
-- This table stores individual session records for each authenticated user.
-- A user may have multiple active sessions (e.g., logged in on multiple devices).
-- Session tokens are stored as hashed values for security.

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE COMMENT 'Hashed session token for security',
    device_info VARCHAR(255) DEFAULT NULL COMMENT 'Browser/device identifier for user display',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IPv4/IPv6 address (45 chars for IPv6)',
    user_agent TEXT DEFAULT NULL COMMENT 'Full user agent string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When session was created',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity timestamp',
    expires_at TIMESTAMP NOT NULL COMMENT 'Hard expiration time',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'FALSE when logged out or expired',
    
    -- Foreign key to users table
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX idx_user_sessions_user_id (user_id),
    INDEX idx_user_sessions_token (session_token),
    INDEX idx_user_sessions_active (is_active, expires_at),
    INDEX idx_user_sessions_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks all active user sessions for HIPAA-compliant session management';

-- ============================================================================
-- User Preferences Table (stores per-user settings including idle timeout)
-- ============================================================================
-- Stores user-specific preferences. Currently focused on session timeout
-- but designed to accommodate additional preferences in the future.

CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE COMMENT 'One preference record per user',
    idle_timeout INT DEFAULT 1800 COMMENT 'Idle timeout in seconds (default 30 min, range 300-3600)',
    theme VARCHAR(20) DEFAULT 'system' COMMENT 'UI theme preference (light/dark/system)',
    notifications_enabled BOOLEAN DEFAULT TRUE COMMENT 'Whether to show notifications',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key to users table
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Index for lookups
    INDEX idx_user_preferences_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User-specific preferences including session timeout settings';

-- ============================================================================
-- Session Activity Log Table (optional - for HIPAA audit compliance)
-- ============================================================================
-- Tracks session-related events for audit purposes.
-- This is separate from the main audit_event table for performance.

CREATE TABLE IF NOT EXISTS session_activity_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id INT DEFAULT NULL COMMENT 'Reference to user_sessions.id (NULL if session already deleted)',
    user_id INT NOT NULL,
    event_type ENUM('login', 'logout', 'timeout', 'activity_ping', 'session_extend', 'forced_logout') NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL COMMENT 'Truncated user agent for storage efficiency',
    event_data JSON DEFAULT NULL COMMENT 'Additional event-specific data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Index for querying user's session history
    INDEX idx_session_activity_user_id (user_id),
    INDEX idx_session_activity_created (created_at),
    INDEX idx_session_activity_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='HIPAA-compliant audit log for session-related events';

-- ============================================================================
-- MySQL Event for Automatic Session Cleanup
-- ============================================================================
-- Runs every 5 minutes to mark expired sessions as inactive and
-- delete old inactive sessions to maintain database performance.
-- 
-- NOTE: MySQL event scheduler must be enabled for this to work:
-- SET GLOBAL event_scheduler = ON;

DELIMITER //

DROP EVENT IF EXISTS cleanup_expired_sessions//

CREATE EVENT IF NOT EXISTS cleanup_expired_sessions
ON SCHEDULE EVERY 5 MINUTE
STARTS CURRENT_TIMESTAMP
ENABLE
COMMENT 'Automatically deactivates expired sessions and cleans up old records'
DO
BEGIN
    -- Mark expired sessions as inactive
    -- This allows users to see their expired sessions in the UI temporarily
    UPDATE user_sessions 
    SET is_active = FALSE 
    WHERE expires_at < NOW() AND is_active = TRUE;
    
    -- Delete sessions that have been inactive for more than 7 days
    -- This is for storage management - audit logs are kept separately
    DELETE FROM user_sessions 
    WHERE is_active = FALSE AND last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Clean up old activity logs (keep 90 days for HIPAA compliance)
    DELETE FROM session_activity_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END//

DELIMITER ;

-- ============================================================================
-- Enable Event Scheduler (if not already enabled)
-- ============================================================================
-- This sets the event scheduler to ON for the current session.
-- For permanent enabling, add event_scheduler=ON to my.cnf/my.ini

SET GLOBAL event_scheduler = ON;

-- ============================================================================
-- Verification Queries
-- ============================================================================
-- Run these after migration to verify tables were created correctly

-- Check tables exist
SELECT 'user_sessions table created' AS status 
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'user_sessions';

SELECT 'user_preferences table created' AS status 
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'user_preferences';

SELECT 'session_activity_log table created' AS status 
FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'session_activity_log';

-- Check event exists
SELECT 'cleanup_expired_sessions event created' AS status 
FROM information_schema.events 
WHERE event_schema = DATABASE() AND event_name = 'cleanup_expired_sessions';

-- Show table structures
DESCRIBE user_sessions;
DESCRIBE user_preferences;
DESCRIBE session_activity_log;
