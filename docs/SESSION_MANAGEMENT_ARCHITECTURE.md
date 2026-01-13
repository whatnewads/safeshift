# Session Management Enhancement Architecture

## SafeShift EHR - HIPAA-Compliant Session Management

**Document Version:** 1.0  
**Created:** 2025-12-28  
**Status:** Architecture Design - Ready for Implementation

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current State Analysis](#current-state-analysis)
3. [Problem Identification](#problem-identification)
4. [Proposed Architecture](#proposed-architecture)
5. [Database Schema](#database-schema)
6. [Backend Implementation](#backend-implementation)
7. [Frontend Implementation](#frontend-implementation)
8. [API Endpoints](#api-endpoints)
9. [Security Considerations](#security-considerations)
10. [Migration Plan](#migration-plan)
11. [Implementation Checklist](#implementation-checklist)

---

## Executive Summary

This document outlines the architecture for enhancing session management in SafeShift EHR to address the current issue of sessions timing out after approximately 5 minutes of idle activity. The solution provides:

- **User-configurable idle timeout** (5 to 60 minutes)
- **Maximum session duration** of 1 hour (hard limit for HIPAA compliance)
- **Active session tracking** with device info and IP addresses
- **Multi-session management** (view sessions, logout other devices)
- **Warning modal** 2 minutes before session expiration
- **Activity-based session refresh** to backend

---

## Current State Analysis

### Backend Session Configuration

#### File: [`includes/header.php`](../includes/header.php:17-56)

```php
// Current configuration
$session_params = [
    'lifetime' => SESSION_LIFETIME ?? 3600,  // Cookie lifetime (1 hour)
    'path' => SESSION_PATH ?? '/',
    'secure' => SESSION_SECURE ?? true,
    'httponly' => SESSION_HTTPONLY ?? true,
    'samesite' => 'Strict'
];

// Session ID regeneration every 5 minutes (line 47)
if (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
}
```

#### File: [`model/Config/AppConfig.php`](../model/Config/AppConfig.php:106-147)

```php
// Current defaults
'lifetime' => 1800,           // 30 minutes (SESSION_LIFETIME env)
'idle_timeout' => 900,        // 15 minutes (SESSION_IDLE_TIMEOUT env)
'regenerate_interval' => 300, // 5 minutes (SESSION_REGEN_INTERVAL env)
```

#### File: [`model/Core/Session.php`](../model/Core/Session.php:218-242)

```php
// Current timeout check
private function isTimedOut(): bool
{
    $idleTime = time() - $_SESSION[self::LAST_ACTIVITY_KEY];
    
    if ($idleTime > $this->sessionConfig['idle_timeout']) {
        return true;  // Idle timeout exceeded
    }
    
    // Also checks absolute session lifetime
    if ($sessionAge > $this->sessionConfig['lifetime']) {
        return true;  // Session lifetime exceeded
    }
    
    return false;
}
```

### Frontend Session Management

#### File: [`src/app/hooks/useSessionManagement.ts`](../src/app/hooks/useSessionManagement.ts:32-38)

```typescript
// Current defaults
const DEFAULT_CONFIG: Required<SessionConfig> = {
  checkInterval: 5 * 60 * 1000,    // 5 minutes
  warningThreshold: 5,             // 5 minutes before expiration
  autoRefresh: true,
  idleTimeout: 30 * 60 * 1000,     // 30 minutes
  activityEvents: ['mousedown', 'keydown', 'touchstart', 'scroll'],
};
```

#### File: [`src/app/contexts/AuthContext.tsx`](../src/app/contexts/AuthContext.tsx:198-200)

```typescript
const SESSION_CHECK_INTERVAL = 5 * 60 * 1000;  // 5 minutes
const SESSION_WARNING_THRESHOLD = 5;            // minutes
```

### Database

**Current state:** No dedicated session tracking table exists. The [`user`](../safeshift_ehr_001_0%20.sql:1173-1189) table has `last_login` but no active session tracking.

Related tables:
- `user` - Basic user info with `last_login` timestamp
- `user_device` - Device registration (not tied to sessions)
- `login_otp` - 2FA codes

---

## Problem Identification

### Root Cause Analysis

The ~5 minute auto-logout is caused by a **mismatch between configuration values and actual behavior**:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         TIMEOUT TIMELINE                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  0 min          5 min         15 min        30 min        60 min       │
│    │              │              │              │              │        │
│    ├──────────────┼──────────────┼──────────────┼──────────────┤        │
│    │              │              │              │              │        │
│    │  [REGEN]     │              │              │              │        │
│    │  Session ID  │  [IDLE]      │              │              │        │
│    │  regenerated │  Timeout     │              │              │        │
│    │              │  (15 min)    │              │              │        │
│    │              │              │  [LIFETIME]  │              │        │
│    │              │              │  AppConfig   │              │        │
│    │              │              │  (30 min)    │              │        │
│    │              │              │              │  [COOKIE]    │        │
│    │              │              │              │  header.php  │        │
│    │              │              │              │  (60 min)    │        │
│                                                                         │
│  ISSUE: Frontend idle timeout (30 min) doesn't update backend          │
│         Backend idle_timeout (15 min) is checking stale timestamps     │
└─────────────────────────────────────────────────────────────────────────┘
```

### Issues Identified

1. **No activity ping to backend**: Frontend tracks activity but doesn't notify backend
2. **Session regeneration conflicts**: 5-minute regeneration may cause session loss
3. **No persistent session tracking**: Sessions are PHP file-based, not database-tracked
4. **Inconsistent timeout values**: Frontend (30 min) vs Backend (15 min) idle timeout
5. **No user preference for timeout**: Hardcoded values only
6. **No multi-device session visibility**: Users cannot see or manage other sessions

---

## Proposed Architecture

### Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                         SESSION MANAGEMENT ARCHITECTURE                       │
└──────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              FRONTEND (React)                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────────┐    ┌──────────────────────┐                      │
│  │  SessionViewModel    │    │   SessionSettings    │                      │
│  │  ──────────────────  │    │   ────────────────   │                      │
│  │  - trackActivity()   │    │   - timeout: 5-60m   │                      │
│  │  - pingBackend()     │◄───┤   - getPreference()  │                      │
│  │  - showWarning()     │    │   - savePreference() │                      │
│  │  - handleTimeout()   │    └──────────────────────┘                      │
│  └──────────┬───────────┘                                                   │
│             │                                                               │
│             │ Every 60s (if activity)                                       │
│             ▼                                                               │
│  ┌──────────────────────┐                                                   │
│  │  SessionWarningModal │ ◄─── Shows 2 min before expiry                   │
│  │  ──────────────────  │                                                   │
│  │  - Extend Session    │                                                   │
│  │  - Logout Now        │                                                   │
│  └──────────────────────┘                                                   │
│                                                                             │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   │
                                   │ HTTP/HTTPS
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              API ENDPOINTS                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  POST /auth/ping-activity      ─── Update last_activity timestamp          │
│  GET  /auth/session-status     ─── Get session info + time remaining       │
│  POST /auth/refresh-session    ─── Extend session, get new CSRF token      │
│  GET  /auth/active-sessions    ─── List all user sessions                  │
│  POST /auth/logout-session     ─── Logout specific session by ID           │
│  POST /auth/logout-all         ─── Logout all sessions except current      │
│  POST /auth/logout-everywhere  ─── Logout ALL sessions including current   │
│  PUT  /user/preferences        ─── Save timeout preference (5-60 min)      │
│  GET  /user/preferences        ─── Get user preferences including timeout  │
│                                                                             │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           BACKEND (PHP)                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                     SessionManager (Enhanced)                         │  │
│  │  ────────────────────────────────────────────────────────────────────│  │
│  │                                                                       │  │
│  │  HARD LIMITS (Non-configurable):                                     │  │
│  │  • Max session duration: 60 minutes                                  │  │
│  │  • Session regeneration: every 5 minutes                             │  │
│  │                                                                       │  │
│  │  USER-CONFIGURABLE:                                                  │  │
│  │  • Idle timeout: 5 to 60 minutes (stored in user_preferences)        │  │
│  │                                                                       │  │
│  │  METHODS:                                                            │  │
│  │  • createSession(userId, deviceInfo, ip)                             │  │
│  │  • validateSession(token) -> bool + user + remaining_time            │  │
│  │  • refreshSession(token) -> new expiry                               │  │
│  │  • updateActivity(token) -> updates last_activity                    │  │
│  │  • getUserSessions(userId) -> list of active sessions                │  │
│  │  • terminateSession(sessionId)                                       │  │
│  │  • terminateAllOtherSessions(userId, currentToken)                   │  │
│  │  • terminateAllSessions(userId)                                      │  │
│  │                                                                       │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           DATABASE (MySQL)                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                        user_sessions                                  │  │
│  │  ────────────────────────────────────────────────────────────────────│  │
│  │  id              INT AUTO_INCREMENT PRIMARY KEY                       │  │
│  │  user_id         CHAR(36) NOT NULL  → FK to user.user_id             │  │
│  │  session_token   VARCHAR(255) NOT NULL UNIQUE                         │  │
│  │  device_info     VARCHAR(255)  -- Browser/OS info (no PII)           │  │
│  │  ip_address      VARCHAR(45)   -- IPv4 or IPv6                       │  │
│  │  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP                  │  │
│  │  last_activity   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE...    │  │
│  │  expires_at      TIMESTAMP NOT NULL  -- Hard expiry (max 60 min)     │  │
│  │  is_active       BOOLEAN DEFAULT TRUE                                 │  │
│  │                                                                       │  │
│  │  INDEX idx_user_active (user_id, is_active)                          │  │
│  │  INDEX idx_session_token (session_token)                             │  │
│  │  INDEX idx_expires (expires_at)                                      │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │                     user_preferences                                  │  │
│  │  ────────────────────────────────────────────────────────────────────│  │
│  │  user_id              CHAR(36) PRIMARY KEY → FK to user.user_id      │  │
│  │  session_timeout_min  INT DEFAULT 15  -- User's preferred timeout    │  │
│  │  theme                VARCHAR(20) DEFAULT 'system'                   │  │
│  │  notifications_enabled BOOLEAN DEFAULT TRUE                          │  │
│  │  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP            │  │
│  │  updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE  │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Session Lifecycle Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SESSION LIFECYCLE FLOW                               │
└─────────────────────────────────────────────────────────────────────────────┘

   USER LOGIN                                                    
       │                                                         
       ▼                                                         
  ┌─────────────┐                                               
  │  Login      │                                               
  │  (username, │                                               
  │   password) │                                               
  └──────┬──────┘                                               
         │                                                       
         ▼                                                       
  ┌─────────────┐     ┌──────────────────────────────────────┐  
  │  2FA/OTP    │────►│  On Success:                         │  
  │  Verify     │     │  1. Create user_sessions record      │  
  └─────────────┘     │  2. Generate session_token (256-bit) │  
                      │  3. Store device_info, IP            │  
                      │  4. Set expires_at = now + 60 min    │  
                      │  5. Return token in secure cookie    │  
                      └──────────────────────────────────────┘  
                                       │                         
                                       ▼                         
  ┌──────────────────────────────────────────────────────────────────────────┐
  │                          ACTIVE SESSION                                   │
  │                                                                          │
  │   ┌────────────────────────────────────────────────────────────────┐    │
  │   │                    FRONTEND ACTIVITY LOOP                       │    │
  │   │                                                                 │    │
  │   │   User Activity ──► Track locally ──► Every 60s: POST /ping   │    │
  │   │        │                                    │                   │    │
  │   │        │                                    ▼                   │    │
  │   │        │                          Update last_activity         │    │
  │   │        │                          in user_sessions             │    │
  │   │        │                                                        │    │
  │   │        └───► No activity for (user_timeout - 2 min)?           │    │
  │   │                      │                                          │    │
  │   │                      ▼                                          │    │
  │   │              Show Warning Modal                                 │    │
  │   │                      │                                          │    │
  │   │              ┌───────┴───────┐                                  │    │
  │   │              ▼               ▼                                  │    │
  │   │         [Extend]        [Logout]                                │    │
  │   │              │               │                                  │    │
  │   │              ▼               ▼                                  │    │
  │   │         POST /refresh   POST /logout                            │    │
  │   │         Reset timer      End session                            │    │
  │   │                                                                 │    │
  │   └─────────────────────────────────────────────────────────────────┘    │
  │                                                                          │
  │   TIMEOUT CONDITIONS (checked on every request):                         │
  │   ─────────────────────────────────────────────────────────────────     │
  │   • Idle timeout exceeded:  last_activity + user_timeout < now           │
  │   • Hard limit exceeded:    created_at + 60 min < now                    │
  │   • Session manually revoked: is_active = FALSE                          │
  │                                                                          │
  └──────────────────────────────────────────────────────────────────────────┘
                                       │                         
                                       ▼                         
  ┌─────────────┐                                               
  │  SESSION    │     ┌──────────────────────────────────────┐  
  │  TERMINATED │────►│  1. Set is_active = FALSE            │  
  │             │     │  2. Log audit event                  │  
  └─────────────┘     │  3. Clear client cookies             │  
                      │  4. Redirect to login                │  
                      └──────────────────────────────────────┘  
```

---

## Database Schema

### Migration Script

```sql
-- ============================================================================
-- Migration: Add Session Management Tables
-- SafeShift EHR - Session Enhancement
-- ============================================================================

-- ============================================================================
-- 1. CREATE user_sessions TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` CHAR(36) NOT NULL COMMENT 'FK to user.user_id',
    `session_token` VARCHAR(255) NOT NULL COMMENT 'Unique session identifier',
    `device_info` VARCHAR(255) DEFAULT NULL COMMENT 'Browser/OS - no PII',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Session creation time',
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
        ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity timestamp',
    `expires_at` TIMESTAMP NOT NULL COMMENT 'Hard expiry time (max 60 min from creation)',
    `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Whether session is currently valid',
    
    -- Indexes
    UNIQUE KEY `uk_session_token` (`session_token`),
    INDEX `idx_user_active` (`user_id`, `is_active`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_last_activity` (`last_activity`),
    
    -- Foreign Key
    CONSTRAINT `fk_session_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `user` (`user_id`) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Active user sessions for session management';


-- ============================================================================
-- 2. CREATE user_preferences TABLE (if not exists)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_preferences` (
    `user_id` CHAR(36) NOT NULL COMMENT 'FK to user.user_id',
    `session_timeout_minutes` INT DEFAULT 15 
        COMMENT 'User-configured idle timeout (5-60 minutes)',
    `theme` VARCHAR(20) DEFAULT 'system' 
        COMMENT 'UI theme preference',
    `notifications_enabled` BOOLEAN DEFAULT TRUE 
        COMMENT 'Enable in-app notifications',
    `language` VARCHAR(10) DEFAULT 'en' 
        COMMENT 'Preferred language code',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_preferences_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `user` (`user_id`) 
        ON DELETE CASCADE,
    
    -- Validate timeout range
    CONSTRAINT `chk_timeout_range` 
        CHECK (`session_timeout_minutes` >= 5 AND `session_timeout_minutes` <= 60)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User preferences including session timeout settings';


-- ============================================================================
-- 3. CREATE CLEANUP EVENT (removes expired sessions)
-- ============================================================================

DELIMITER //

-- Event to cleanup expired sessions (runs every 5 minutes)
CREATE EVENT IF NOT EXISTS `cleanup_expired_sessions`
ON SCHEDULE EVERY 5 MINUTE
ENABLE
DO
BEGIN
    -- Mark expired sessions as inactive
    UPDATE `user_sessions` 
    SET `is_active` = FALSE 
    WHERE `is_active` = TRUE 
      AND (`expires_at` < NOW() OR `last_activity` < DATE_SUB(NOW(), INTERVAL 60 MINUTE));
    
    -- Delete very old inactive sessions (older than 7 days)
    DELETE FROM `user_sessions` 
    WHERE `is_active` = FALSE 
      AND `updated_at` < DATE_SUB(NOW(), INTERVAL 7 DAY);
END //

DELIMITER ;


-- ============================================================================
-- 4. INSERT DEFAULT PREFERENCES for existing users
-- ============================================================================

INSERT IGNORE INTO `user_preferences` (`user_id`, `session_timeout_minutes`)
SELECT `user_id`, 15 FROM `user`;


-- ============================================================================
-- 5. ADD INDEX for efficient session validation
-- ============================================================================

-- Composite index for session validation queries
ALTER TABLE `user_sessions` 
ADD INDEX `idx_validation` (`session_token`, `is_active`, `expires_at`);
```

---

## Backend Implementation

### Enhanced Session Manager Class

Create new file: `model/Core/SessionManager.php`

```php
<?php
/**
 * SessionManager.php - Database-backed Session Manager
 * 
 * Provides database-persisted session tracking with:
 * - User-configurable idle timeout
 * - Hard 60-minute session limit
 * - Multi-device session management
 * 
 * @package    SafeShift\Model\Core
 */

declare(strict_types=1);

namespace Model\Core;

use Model\Config\AppConfig;
use PDO;

final class SessionManager
{
    private PDO $db;
    private AppConfig $config;
    private static ?self $instance = null;
    
    /** Maximum session duration in seconds (1 hour) */
    private const MAX_SESSION_DURATION = 3600;
    
    /** Default idle timeout in minutes */
    private const DEFAULT_IDLE_TIMEOUT = 15;
    
    /** Minimum configurable timeout in minutes */
    private const MIN_TIMEOUT = 5;
    
    /** Maximum configurable timeout in minutes */
    private const MAX_TIMEOUT = 60;
    
    private function __construct(PDO $db)
    {
        $this->db = $db;
        $this->config = AppConfig::getInstance();
    }
    
    public static function getInstance(PDO $db): self
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }
    
    /**
     * Create a new session after successful authentication
     */
    public function createSession(
        string $userId, 
        string $deviceInfo, 
        string $ipAddress
    ): string {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        
        // Calculate expiry (hard limit: 60 minutes)
        $expiresAt = date('Y-m-d H:i:s', time() + self::MAX_SESSION_DURATION);
        
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions 
            (user_id, session_token, device_info, ip_address, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            hash('sha256', $token), // Store hashed token
            $this->sanitizeDeviceInfo($deviceInfo),
            $ipAddress,
            $expiresAt
        ]);
        
        return $token; // Return plain token to client
    }
    
    /**
     * Validate session and return session data
     */
    public function validateSession(string $token): ?array
    {
        $hashedToken = hash('sha256', $token);
        
        // Get user's timeout preference
        $stmt = $this->db->prepare("
            SELECT 
                s.*,
                u.username,
                u.email,
                COALESCE(p.session_timeout_minutes, ?) as idle_timeout_minutes
            FROM user_sessions s
            JOIN user u ON s.user_id = u.user_id
            LEFT JOIN user_preferences p ON s.user_id = p.user_id
            WHERE s.session_token = ?
              AND s.is_active = TRUE
              AND s.expires_at > NOW()
        ");
        
        $stmt->execute([self::DEFAULT_IDLE_TIMEOUT, $hashedToken]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return null;
        }
        
        // Check idle timeout
        $lastActivity = strtotime($session['last_activity']);
        $idleTimeoutSeconds = $session['idle_timeout_minutes'] * 60;
        
        if (time() - $lastActivity > $idleTimeoutSeconds) {
            $this->terminateSession($session['id']);
            return null;
        }
        
        // Calculate remaining time
        $expiresAt = strtotime($session['expires_at']);
        $remainingSeconds = $expiresAt - time();
        $idleRemaining = $idleTimeoutSeconds - (time() - $lastActivity);
        
        return [
            'session_id' => $session['id'],
            'user_id' => $session['user_id'],
            'username' => $session['username'],
            'email' => $session['email'],
            'created_at' => $session['created_at'],
            'last_activity' => $session['last_activity'],
            'expires_at' => $session['expires_at'],
            'remaining_seconds' => min($remainingSeconds, $idleRemaining),
            'idle_timeout_minutes' => $session['idle_timeout_minutes'],
        ];
    }
    
    /**
     * Update last activity timestamp
     */
    public function updateActivity(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET last_activity = NOW()
            WHERE session_token = ?
              AND is_active = TRUE
              AND expires_at > NOW()
        ");
        
        $stmt->execute([$hashedToken]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get all active sessions for a user
     */
    public function getUserSessions(string $userId, string $currentToken): array
    {
        $hashedCurrentToken = hash('sha256', $currentToken);
        
        $stmt = $this->db->prepare("
            SELECT 
                id,
                device_info,
                ip_address,
                created_at,
                last_activity,
                (session_token = ?) as is_current
            FROM user_sessions
            WHERE user_id = ?
              AND is_active = TRUE
              AND expires_at > NOW()
            ORDER BY last_activity DESC
        ");
        
        $stmt->execute([$hashedCurrentToken, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Terminate a specific session
     */
    public function terminateSession(int $sessionId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET is_active = FALSE 
            WHERE id = ?
        ");
        
        $stmt->execute([$sessionId]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Terminate all sessions except the current one
     */
    public function terminateOtherSessions(string $userId, string $currentToken): int
    {
        $hashedToken = hash('sha256', $currentToken);
        
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET is_active = FALSE 
            WHERE user_id = ?
              AND session_token != ?
              AND is_active = TRUE
        ");
        
        $stmt->execute([$userId, $hashedToken]);
        return $stmt->rowCount();
    }
    
    /**
     * Terminate all sessions for a user (logout everywhere)
     */
    public function terminateAllSessions(string $userId): int
    {
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET is_active = FALSE 
            WHERE user_id = ?
              AND is_active = TRUE
        ");
        
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
    
    /**
     * Get/Set user's session timeout preference
     */
    public function getUserTimeoutPreference(string $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT session_timeout_minutes 
            FROM user_preferences 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        $result = $stmt->fetchColumn();
        
        return $result ?: self::DEFAULT_IDLE_TIMEOUT;
    }
    
    public function setUserTimeoutPreference(string $userId, int $minutes): bool
    {
        // Validate range
        $minutes = max(self::MIN_TIMEOUT, min(self::MAX_TIMEOUT, $minutes));
        
        $stmt = $this->db->prepare("
            INSERT INTO user_preferences (user_id, session_timeout_minutes)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE 
                session_timeout_minutes = VALUES(session_timeout_minutes),
                updated_at = NOW()
        ");
        
        $stmt->execute([$userId, $minutes]);
        return true;
    }
    
    /**
     * Sanitize device info to remove any potential PII
     */
    private function sanitizeDeviceInfo(string $deviceInfo): string
    {
        // Extract only browser and OS info, no personal data
        $info = substr($deviceInfo, 0, 255);
        
        // Remove any potential email patterns
        $info = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[email]', $info);
        
        return $info;
    }
}
```

### Session API ViewModel

Create new file: `ViewModel/SessionViewModel.php`

```php
<?php
/**
 * SessionViewModel.php - Session Management API Controller
 * 
 * Handles session-related API endpoints
 */

declare(strict_types=1);

namespace ViewModel;

use Model\Core\SessionManager;
use Model\Core\Database;
use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;

class SessionViewModel extends BaseViewModel
{
    private SessionManager $sessionManager;
    
    public function __construct()
    {
        parent::__construct();
        $db = Database::getInstance()->getConnection();
        $this->sessionManager = SessionManager::getInstance($db);
    }
    
    /**
     * POST /auth/ping-activity
     * Update session activity timestamp
     */
    public function pingActivity(): void
    {
        $token = $this->getSessionToken();
        
        if (!$token) {
            ApiResponse::unauthorized('No session token');
            return;
        }
        
        $updated = $this->sessionManager->updateActivity($token);
        
        if ($updated) {
            ApiResponse::success(['message' => 'Activity recorded']);
        } else {
            ApiResponse::unauthorized('Invalid or expired session');
        }
    }
    
    /**
     * GET /auth/active-sessions
     * Get all active sessions for current user
     */
    public function getActiveSessions(): void
    {
        $token = $this->getSessionToken();
        $session = $this->sessionManager->validateSession($token);
        
        if (!$session) {
            ApiResponse::unauthorized('Invalid session');
            return;
        }
        
        $sessions = $this->sessionManager->getUserSessions(
            $session['user_id'],
            $token
        );
        
        ApiResponse::success([
            'sessions' => $sessions,
            'total' => count($sessions)
        ]);
    }
    
    /**
     * POST /auth/logout-session
     * Logout a specific session by ID
     */
    public function logoutSession(): void
    {
        $token = $this->getSessionToken();
        $session = $this->sessionManager->validateSession($token);
        
        if (!$session) {
            ApiResponse::unauthorized('Invalid session');
            return;
        }
        
        $input = $this->getJsonInput();
        $sessionId = (int)($input['session_id'] ?? 0);
        
        if (!$sessionId) {
            ApiResponse::badRequest('Session ID required');
            return;
        }
        
        $this->sessionManager->terminateSession($sessionId);
        ApiResponse::success(['message' => 'Session terminated']);
    }
    
    /**
     * POST /auth/logout-all
     * Logout all other sessions
     */
    public function logoutAllOther(): void
    {
        $token = $this->getSessionToken();
        $session = $this->sessionManager->validateSession($token);
        
        if (!$session) {
            ApiResponse::unauthorized('Invalid session');
            return;
        }
        
        $count = $this->sessionManager->terminateOtherSessions(
            $session['user_id'],
            $token
        );
        
        ApiResponse::success([
            'message' => "Logged out of {$count} other session(s)",
            'terminated_count' => $count
        ]);
    }
    
    /**
     * POST /auth/logout-everywhere
     * Logout all sessions including current
     */
    public function logoutEverywhere(): void
    {
        $token = $this->getSessionToken();
        $session = $this->sessionManager->validateSession($token);
        
        if (!$session) {
            ApiResponse::unauthorized('Invalid session');
            return;
        }
        
        $count = $this->sessionManager->terminateAllSessions($session['user_id']);
        
        // Clear client cookie
        $this->clearSessionCookie();
        
        ApiResponse::success([
            'message' => "Logged out of all {$count} session(s)",
            'terminated_count' => $count
        ]);
    }
    
    /**
     * GET /user/preferences/timeout
     * Get user's timeout preference
     */
    public function getTimeoutPreference(): void
    {
        $token = $this->getSessionToken();
        $session = $this->sessionManager->validateSession($token);
        
        if (!$session) {
            ApiResponse::unauthorized('Invalid session');
            return;
        }
        
        $timeout = $this->sessionManager->getUserTimeoutPreference($session['user_id']);
        
        ApiResponse::success([
            'session_timeout_minutes' => $timeout,
            'available_options' => [5, 10, 15, 30, 45, 60]
        ]);
    }
    
    /**
     * PUT /user/preferences/timeout
     * Update user's timeout preference
     */
    public function updateTimeoutPreference(): void
    {
        $token = $this->getSessionToken();
        $session = $this->sessionManager->validateSession($token);
        
        if (!$session) {
            ApiResponse::unauthorized('Invalid session');
            return;
        }
        
        $input = $this->getJsonInput();
        $minutes = (int)($input['session_timeout_minutes'] ?? 15);
        
        // Validate range
        if ($minutes < 5 || $minutes > 60) {
            ApiResponse::badRequest('Timeout must be between 5 and 60 minutes');
            return;
        }
        
        $this->sessionManager->setUserTimeoutPreference($session['user_id'], $minutes);
        
        ApiResponse::success([
            'message' => 'Preference updated',
            'session_timeout_minutes' => $minutes
        ]);
    }
}
```

---

## Frontend Implementation

### Enhanced Session ViewModel Hook

Update file: `src/app/hooks/useSessionManagement.ts`

The following changes are needed:

1. **Add activity ping functionality** - Send activity to backend every 60 seconds
2. **Use user's configured timeout** - Fetch from `/user/preferences/timeout`
3. **Show warning 2 minutes before expiry**
4. **Add session management functions** - List sessions, logout others, etc.

```typescript
/**
 * Enhanced Session Management Hook
 * 
 * Key changes from current implementation:
 * 1. Activity ping to backend (every 60s if active)
 * 2. User-configurable timeout preference
 * 3. Warning modal 2 minutes before expiry
 * 4. Multi-session management
 */

// New configuration options to add
export interface EnhancedSessionConfig extends SessionConfig {
  /** Interval to ping backend when active (default: 60 seconds) */
  activityPingInterval?: number;
  /** Minutes before timeout to show warning (default: 2 minutes) */
  warningBeforeTimeout?: number;
}

// New functions to add to the hook return type
export interface SessionManagementReturn {
  // ... existing properties ...
  
  /** User's configured timeout in minutes */
  timeoutPreference: number;
  
  /** Update user's timeout preference */
  updateTimeoutPreference: (minutes: number) => Promise<void>;
  
  /** List all active sessions */
  getActiveSessions: () => Promise<ActiveSession[]>;
  
  /** Logout a specific session */
  logoutSession: (sessionId: number) => Promise<void>;
  
  /** Logout all other sessions */
  logoutAllOther: () => Promise<void>;
  
  /** Logout everywhere including current */
  logoutEverywhere: () => Promise<void>;
}

// Active session type
export interface ActiveSession {
  id: number;
  device_info: string;
  ip_address: string;
  created_at: string;
  last_activity: string;
  is_current: boolean;
}
```

### Session Warning Modal Component

Create new file: `src/app/components/SessionWarningModal.tsx`

```typescript
/**
 * SessionWarningModal.tsx
 * 
 * Displays warning 2 minutes before session expiry
 * Allows user to extend session or logout
 */

import React from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from './ui/dialog';
import { Button } from './ui/button';
import { useSessionCountdown } from '../hooks/useSessionManagement';

interface SessionWarningModalProps {
  isOpen: boolean;
  onExtend: () => void;
  onLogout: () => void;
}

export function SessionWarningModal({ 
  isOpen, 
  onExtend, 
  onLogout 
}: SessionWarningModalProps) {
  const { formatted, minutes, seconds } = useSessionCountdown();
  
  return (
    <Dialog open={isOpen}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="text-amber-600">
            Session Expiring Soon
          </DialogTitle>
        </DialogHeader>
        
        <div className="py-4">
          <p className="text-center text-gray-600 mb-4">
            Your session will expire in
          </p>
          
          <div className="text-center text-4xl font-mono font-bold text-red-600 mb-4">
            {formatted}
          </div>
          
          <p className="text-center text-sm text-gray-500">
            Would you like to continue working?
          </p>
        </div>
        
        <div className="flex justify-end gap-3">
          <Button variant="outline" onClick={onLogout}>
            Logout Now
          </Button>
          <Button onClick={onExtend}>
            Stay Logged In
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
```

### Session Settings UI Component

Create new file: `src/app/components/settings/SessionSettings.tsx`

```typescript
/**
 * SessionSettings.tsx
 * 
 * User interface for session management:
 * - Configure timeout preference
 * - View active sessions
 * - Logout other devices
 */

import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import { useSessionManagement } from '../../hooks/useSessionManagement';
import { formatDistanceToNow } from 'date-fns';

const TIMEOUT_OPTIONS = [
  { value: 5, label: '5 minutes' },
  { value: 10, label: '10 minutes' },
  { value: 15, label: '15 minutes' },
  { value: 30, label: '30 minutes' },
  { value: 45, label: '45 minutes' },
  { value: 60, label: '60 minutes' },
];

export function SessionSettings() {
  const {
    timeoutPreference,
    updateTimeoutPreference,
    getActiveSessions,
    logoutSession,
    logoutAllOther,
    logoutEverywhere,
  } = useSessionManagement();
  
  const [sessions, setSessions] = useState<ActiveSession[]>([]);
  const [loading, setLoading] = useState(false);
  
  useEffect(() => {
    loadSessions();
  }, []);
  
  const loadSessions = async () => {
    setLoading(true);
    try {
      const data = await getActiveSessions();
      setSessions(data);
    } finally {
      setLoading(false);
    }
  };
  
  const handleTimeoutChange = async (value: string) => {
    await updateTimeoutPreference(parseInt(value, 10));
  };
  
  const handleLogoutSession = async (sessionId: number) => {
    await logoutSession(sessionId);
    await loadSessions();
  };
  
  const handleLogoutAllOther = async () => {
    if (confirm('Logout all other sessions?')) {
      await logoutAllOther();
      await loadSessions();
    }
  };
  
  const handleLogoutEverywhere = async () => {
    if (confirm('This will log you out of ALL devices including this one. Continue?')) {
      await logoutEverywhere();
      // Will redirect to login
    }
  };
  
  return (
    <div className="space-y-6">
      {/* Timeout Preference */}
      <Card>
        <CardHeader>
          <CardTitle>Session Timeout</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center gap-4">
            <label className="text-sm text-gray-600">
              Automatically logout after inactivity:
            </label>
            <Select
              value={String(timeoutPreference)}
              onValueChange={handleTimeoutChange}
            >
              <SelectTrigger className="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {TIMEOUT_OPTIONS.map(opt => (
                  <SelectItem key={opt.value} value={String(opt.value)}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <p className="text-xs text-gray-500 mt-2">
            Maximum session duration is 60 minutes regardless of activity.
          </p>
        </CardContent>
      </Card>
      
      {/* Active Sessions */}
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Active Sessions</CardTitle>
          <div className="flex gap-2">
            <Button 
              variant="outline" 
              size="sm"
              onClick={handleLogoutAllOther}
            >
              Logout Other Sessions
            </Button>
            <Button 
              variant="destructive" 
              size="sm"
              onClick={handleLogoutEverywhere}
            >
              Logout Everywhere
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (
            <p>Loading sessions...</p>
          ) : (
            <div className="space-y-3">
              {sessions.map(session => (
                <div 
                  key={session.id}
                  className="flex items-center justify-between p-3 border rounded-lg"
                >
                  <div>
                    <div className="font-medium flex items-center gap-2">
                      {session.device_info || 'Unknown Device'}
                      {session.is_current && (
                        <span className="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">
                          Current
                        </span>
                      )}
                    </div>
                    <div className="text-sm text-gray-500">
                      IP: {session.ip_address} • Last active:{' '}
                      {formatDistanceToNow(new Date(session.last_activity), { addSuffix: true })}
                    </div>
                  </div>
                  {!session.is_current && (
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleLogoutSession(session.id)}
                    >
                      Logout
                    </Button>
                  )}
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
```

---

## API Endpoints

### Endpoint Summary

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/ping-activity` | Update session activity | Yes |
| GET | `/auth/session-status` | Get session info + remaining time | Yes |
| POST | `/auth/refresh-session` | Extend session | Yes |
| GET | `/auth/active-sessions` | List all user sessions | Yes |
| POST | `/auth/logout-session` | Logout specific session | Yes |
| POST | `/auth/logout-all` | Logout all other sessions | Yes |
| POST | `/auth/logout-everywhere` | Logout all including current | Yes |
| GET | `/user/preferences/timeout` | Get timeout preference | Yes |
| PUT | `/user/preferences/timeout` | Update timeout preference | Yes |

### Response Formats

```typescript
// GET /auth/session-status
interface SessionStatusResponse {
  success: boolean;
  data: {
    valid: boolean;
    authenticated: boolean;
    user?: {
      id: string;
      username: string;
      email: string;
      role: string;
    };
    session?: {
      remaining_seconds: number;
      idle_timeout_minutes: number;
      expires_at: string;
      created_at: string;
    };
    csrfToken?: string;
  };
}

// GET /auth/active-sessions
interface ActiveSessionsResponse {
  success: boolean;
  data: {
    sessions: Array<{
      id: number;
      device_info: string;
      ip_address: string;
      created_at: string;
      last_activity: string;
      is_current: boolean;
    }>;
    total: number;
  };
}

// POST /auth/ping-activity
interface PingResponse {
  success: boolean;
  data: {
    message: string;
  };
}
```

---

## Security Considerations

### HIPAA Compliance

1. **Session tokens**: 256-bit random tokens, stored hashed in database
2. **No PII in device info**: Sanitize user-agent strings
3. **Audit logging**: All session events logged (create, terminate, timeout)
4. **Hard session limit**: Maximum 60 minutes regardless of user preference
5. **Secure transmission**: HTTPS only, secure cookie flags

### Session Token Security

```php
// Token generation (server-side)
$token = bin2hex(random_bytes(32)); // 256 bits of entropy

// Token storage (database)
$hashedToken = hash('sha256', $token);

// Token transmission (cookie)
setcookie('session_token', $token, [
    'expires' => time() + 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
```

### Defense in Depth

1. **IP binding optional**: IP changes don't invalidate session (mobile users)
2. **Device fingerprinting**: Basic UA string tracking (no canvas fingerprinting)
3. **Rate limiting**: Activity pings limited to 1/minute
4. **Session enumeration protection**: Sessions only visible to owner

---

## Migration Plan

### Phase 1: Database Setup

1. Create `user_sessions` table
2. Create `user_preferences` table
3. Set up cleanup event
4. Insert default preferences

### Phase 2: Backend Implementation

1. Create `SessionManager` class
2. Create `SessionViewModel` for API endpoints
3. Update existing auth flow to use database sessions
4. Add activity ping endpoint

### Phase 3: Frontend Implementation

1. Update `useSessionManagement` hook
2. Create `SessionWarningModal` component
3. Create `SessionSettings` component
4. Integrate warning modal into App layout

### Phase 4: Testing & Rollout

1. Test all session scenarios
2. Test multi-device behavior
3. Load testing for session validation
4. Gradual rollout with feature flag

---

## Implementation Checklist

### Database Tasks
- [ ] Create `user_sessions` table migration
- [ ] Create `user_preferences` table migration
- [ ] Create cleanup event for expired sessions
- [ ] Add indexes for performance
- [ ] Insert default preferences for existing users

### Backend Tasks
- [ ] Create `SessionManager` class in `model/Core/`
- [ ] Create `SessionViewModel` in `ViewModel/`
- [ ] Update `AuthViewModel` to use database sessions
- [ ] Add `/auth/ping-activity` endpoint
- [ ] Add `/auth/active-sessions` endpoint
- [ ] Add `/auth/logout-session` endpoint
- [ ] Add `/auth/logout-all` endpoint
- [ ] Add `/auth/logout-everywhere` endpoint
- [ ] Add `/user/preferences/timeout` GET endpoint
- [ ] Add `/user/preferences/timeout` PUT endpoint
- [ ] Update session-status to include remaining time
- [ ] Add audit logging for session events

### Frontend Tasks
- [ ] Update `useSessionManagement` hook with activity ping
- [ ] Add timeout preference state management
- [ ] Create `SessionWarningModal` component
- [ ] Create `SessionSettings` component
- [ ] Add session management API calls to auth.service
- [ ] Integrate warning modal in App layout
- [ ] Add Session Settings to user profile/settings page
- [ ] Update logout flow to handle multi-session

### Testing Tasks
- [ ] Unit tests for SessionManager
- [ ] Integration tests for session endpoints
- [ ] E2E tests for session lifecycle
- [ ] Test idle timeout behavior
- [ ] Test hard limit expiry
- [ ] Test multi-device session management
- [ ] Test warning modal timing
- [ ] Performance test session validation queries

### Documentation Tasks
- [ ] Update API documentation
- [ ] Update user documentation for session settings
- [ ] Document HIPAA compliance measures

---

## Appendix: Current vs. Proposed Comparison

| Feature | Current | Proposed |
|---------|---------|----------|
| Session storage | PHP files | MySQL database |
| Idle timeout | 15 min (fixed) | 5-60 min (configurable) |
| Hard limit | 30 min | 60 min |
| Activity tracking | Frontend only | Frontend + Backend sync |
| Warning before timeout | 5 min | 2 min |
| Multi-session view | Not available | Full list with management |
| Logout other devices | Not available | Available |
| Device info tracking | Not available | Browser/OS tracked |
| IP address logging | Partial | Full (IPv4/IPv6) |
