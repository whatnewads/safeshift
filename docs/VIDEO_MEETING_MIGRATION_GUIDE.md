# Video Meeting Migration Guide

> Step-by-step guide for deploying the Video Meeting feature to SafeShift EHR

**Version:** 1.0.0  
**Last Updated:** 2026-01-11

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Database Migration](#2-database-migration)
3. [Backend Setup](#3-backend-setup)
4. [Frontend Setup](#4-frontend-setup)
5. [Verification Steps](#5-verification-steps)
6. [Rollback Procedures](#6-rollback-procedures)
7. [Post-Migration Tasks](#7-post-migration-tasks)

---

## 1. Prerequisites

### System Requirements

| Requirement | Minimum Version | Notes |
|-------------|-----------------|-------|
| PHP | 8.0+ | With PDO extension |
| MySQL | 8.0+ | InnoDB support required |
| Node.js | 18.x+ | For frontend build |
| npm | 9.x+ | Package management |
| HTTPS | Required | WebRTC requires secure context |

### Required PHP Extensions

```bash
# Verify required extensions
php -m | grep -E "(pdo|pdo_mysql|json|openssl)"
```

- `pdo` - Database abstraction
- `pdo_mysql` - MySQL driver
- `json` - JSON encoding/decoding
- `openssl` - Secure token generation

### Database Access

Ensure you have:
- [ ] Database admin credentials
- [ ] Permission to create tables
- [ ] Permission to create foreign keys
- [ ] Backup of existing database

### Network Requirements

- [ ] HTTPS certificate installed
- [ ] Port 443 accessible
- [ ] Access to PeerJS cloud (or self-hosted PeerJS server)

---

## 2. Database Migration

### 2.1 Pre-Migration Backup

**CRITICAL: Always backup before migration**

```bash
# Create full database backup
mysqldump -u admin -p safeshift_ehr > backup_$(date +%Y%m%d_%H%M%S).sql

# Verify backup
ls -la backup_*.sql
```

### 2.2 Review Migration Script

Preview what will be executed:

```bash
php database/run_video_meetings_migration.php --dry-run
```

This displays the SQL without executing it.

### 2.3 Execute Migration

Run the migration:

```bash
php database/run_video_meetings_migration.php
```

**Expected Output:**
```
[2026-01-11 12:00:00] [INFO] === Video Meetings Migration Started ===
[2026-01-11 12:00:00] [INFO] Migration file loaded successfully (4523 bytes)
[2026-01-11 12:00:00] [INFO] Database connection established
[2026-01-11 12:00:00] [INFO] Transaction started
[2026-01-11 12:00:01] [INFO] Executed CREATE TABLE: video_meetings
[2026-01-11 12:00:01] [INFO] Executed CREATE TABLE: meeting_participants
[2026-01-11 12:00:01] [INFO] Executed CREATE TABLE: meeting_chat_messages
[2026-01-11 12:00:01] [INFO] Executed CREATE TABLE: video_meeting_logs
[2026-01-11 12:00:01] [INFO] Transaction committed
[2026-01-11 12:00:01] [SUCCESS] Verified table 'video_meetings' with 7 columns
[2026-01-11 12:00:01] [SUCCESS] Verified table 'meeting_participants' with 7 columns
[2026-01-11 12:00:01] [SUCCESS] Verified table 'meeting_chat_messages' with 5 columns
[2026-01-11 12:00:01] [SUCCESS] Verified table 'video_meeting_logs' with 8 columns
[2026-01-11 12:00:01] [SUCCESS] === Video Meetings Migration Completed Successfully ===
```

### 2.4 Verify Tables

```sql
-- Connect to MySQL
mysql -u admin -p safeshift_ehr

-- List video meeting tables
SHOW TABLES LIKE '%meeting%';

-- Expected output:
-- +------------------------------------+
-- | Tables_in_safeshift_ehr (%meeting%)|
-- +------------------------------------+
-- | meeting_chat_messages              |
-- | meeting_participants               |
-- | video_meeting_logs                 |
-- | video_meetings                     |
-- +------------------------------------+

-- Verify table structure
DESCRIBE video_meetings;
DESCRIBE meeting_participants;
DESCRIBE meeting_chat_messages;
DESCRIBE video_meeting_logs;

-- Verify foreign keys
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'safeshift_ehr'
AND REFERENCED_TABLE_NAME IS NOT NULL
AND TABLE_NAME LIKE '%meeting%';
```

### 2.5 Migration Log

Check migration log for any issues:

```bash
cat logs/video_meetings_migration.log
```

---

## 3. Backend Setup

### 3.1 Verify File Structure

Ensure all required files exist:

```bash
# API Endpoints
ls -la api/video/
# Expected: create-meeting.php, join-meeting.php, validate-token.php, etc.

# Signaling endpoints
ls -la api/video/signal/
# Expected: register-peer.php, heartbeat.php, get-peers.php, disconnect.php

# ViewModel
ls -la ViewModel/VideoMeetingViewModel.php

# Entities
ls -la model/Entities/VideoMeeting.php
ls -la model/Entities/MeetingParticipant.php
ls -la model/Entities/MeetingChatMessage.php

# Repository
ls -la model/Repositories/VideoMeetingRepository.php
```

### 3.2 Verify Dependencies

Check that required files can be included:

```bash
php -l api/video/create-meeting.php
php -l ViewModel/VideoMeetingViewModel.php
php -l model/Entities/VideoMeeting.php
```

### 3.3 Test API Endpoint Availability

```bash
# Test that endpoint responds (should return 401 without auth)
curl -I https://your-domain.com/api/video/create-meeting.php

# Expected: HTTP/2 401 or similar auth error
```

### 3.4 Configure CORS (if needed)

If frontend is on different domain, verify CORS headers in API files:

```php
// Already included in all API files
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
```

---

## 4. Frontend Setup

### 4.1 Install Dependencies

```bash
# Navigate to project root
cd /path/to/safeshift-ehr

# Install npm packages (includes PeerJS)
npm install

# Verify peerjs is installed
npm list peerjs
```

### 4.2 Verify TypeScript Compilation

```bash
# Check for TypeScript errors
npm run typecheck

# Or if using tsc directly
npx tsc --noEmit
```

### 4.3 Build Frontend

```bash
# Development build
npm run dev

# Production build
npm run build
```

### 4.4 Verify Component Files

```bash
ls -la src/app/components/VideoMeeting/
# Expected files:
# - VideoMeetingPage.tsx
# - VideoDisplay.tsx
# - ChatBox.tsx
# - MeetingControls.tsx
# - ParticipantsList.tsx
# - ShareLinkModal.tsx
# - JoinModal.tsx
# - CallTimer.tsx
# - index.ts

ls -la src/app/services/
# Expected files:
# - video-meeting.service.ts
# - video-signaling.service.ts
# - webrtc.service.ts
```

### 4.5 Configure Routes

Verify video meeting routes are registered in your router:

```typescript
// Example React Router configuration
<Route path="/video/join" element={<JoinPage />} />
<Route path="/video/meeting/:meetingId" element={<VideoMeetingPage />} />
```

---

## 5. Verification Steps

### 5.1 Database Verification

```sql
-- Run test queries to verify tables work
-- This should return empty results (no errors)
SELECT COUNT(*) FROM video_meetings;
SELECT COUNT(*) FROM meeting_participants;
SELECT COUNT(*) FROM meeting_chat_messages;
SELECT COUNT(*) FROM video_meeting_logs;
```

### 5.2 API Endpoint Testing

#### Test Create Meeting (requires clinician session)

```bash
# Replace SESSION_ID with actual session
curl -X POST https://your-domain.com/api/video/create-meeting.php \
  -H "Cookie: PHPSESSID=SESSION_ID" \
  -H "Content-Type: application/json"
```

#### Test Token Validation

```bash
# Replace TOKEN with actual token from create meeting
curl "https://your-domain.com/api/video/validate-token.php?token=TOKEN"
```

#### Test Join Meeting

```bash
curl -X POST https://your-domain.com/api/video/join-meeting.php \
  -H "Content-Type: application/json" \
  -d '{"token":"TOKEN","display_name":"Test User"}'
```

### 5.3 Frontend Verification

1. **Browser Console Check**
   - Open DevTools
   - Navigate to video meeting page
   - Verify no JavaScript errors

2. **WebRTC Support Check**
   ```javascript
   // Run in browser console
   console.log('WebRTC supported:', !!window.RTCPeerConnection);
   console.log('getUserMedia supported:', !!navigator.mediaDevices?.getUserMedia);
   ```

3. **PeerJS Connection Test**
   ```javascript
   // Run in browser console
   import Peer from 'peerjs';
   const peer = new Peer();
   peer.on('open', id => console.log('PeerJS connected:', id));
   peer.on('error', err => console.error('PeerJS error:', err));
   ```

### 5.4 End-to-End Test

1. **Create Meeting** (as clinician)
   - Log in as clinician
   - Navigate to video meeting creation
   - Create new meeting
   - Copy meeting link

2. **Join Meeting** (as guest)
   - Open incognito browser
   - Paste meeting link
   - Enter display name
   - Verify join works

3. **Test Features**
   - [ ] Video display works
   - [ ] Audio works
   - [ ] Chat messages send/receive
   - [ ] Participant list updates
   - [ ] Meeting can be ended by host

---

## 6. Rollback Procedures

### 6.1 Database Rollback

If migration needs to be reversed:

```sql
-- WARNING: This will delete all video meeting data!
-- Only run if necessary for rollback

-- Drop tables in correct order (respect foreign keys)
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS meeting_chat_messages;
DROP TABLE IF EXISTS meeting_participants;
DROP TABLE IF EXISTS video_meeting_logs;
DROP TABLE IF EXISTS video_meetings;

SET FOREIGN_KEY_CHECKS = 1;

-- Verify tables removed
SHOW TABLES LIKE '%meeting%';
```

### 6.2 Restore from Backup

If full rollback needed:

```bash
# Stop application first
systemctl stop apache2  # or nginx

# Restore database
mysql -u admin -p safeshift_ehr < backup_YYYYMMDD_HHMMSS.sql

# Restart application
systemctl start apache2
```

### 6.3 Frontend Rollback

If frontend changes need rollback:

```bash
# Revert to previous git commit
git checkout HEAD~1 -- src/app/components/VideoMeeting/
git checkout HEAD~1 -- src/app/services/video-*.ts
git checkout HEAD~1 -- src/app/services/webrtc.service.ts

# Rebuild
npm run build
```

---

## 7. Post-Migration Tasks

### 7.1 User Communication

- [ ] Notify clinicians of new video meeting feature
- [ ] Provide brief training documentation
- [ ] Set up support channel for questions

### 7.2 Monitoring Setup

```sql
-- Create monitoring query for active meetings
CREATE VIEW v_active_meetings AS
SELECT 
    m.meeting_id,
    m.created_at,
    u.username as creator,
    COUNT(p.participant_id) as participant_count
FROM video_meetings m
JOIN users u ON m.created_by = u.user_id
LEFT JOIN meeting_participants p ON m.meeting_id = p.meeting_id AND p.left_at IS NULL
WHERE m.is_active = 1
GROUP BY m.meeting_id;
```

### 7.3 Log Rotation

Add video meeting logs to log rotation:

```bash
# Add to /etc/logrotate.d/safeshift
/var/www/safeshift/logs/video_meetings_migration.log {
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    notifempty
}
```

### 7.4 Cleanup Job

Set up cron job for expired token cleanup:

```bash
# Add to crontab
# Runs daily at 3am to clean up old data
0 3 * * * php /var/www/safeshift/cron/cleanup_expired_meetings.php
```

### 7.5 Performance Baseline

Record baseline metrics:

```sql
-- Document current row counts
SELECT 'video_meetings' as table_name, COUNT(*) as row_count FROM video_meetings
UNION ALL
SELECT 'meeting_participants', COUNT(*) FROM meeting_participants
UNION ALL
SELECT 'meeting_chat_messages', COUNT(*) FROM meeting_chat_messages
UNION ALL
SELECT 'video_meeting_logs', COUNT(*) FROM video_meeting_logs;
```

---

## Troubleshooting

### Migration Fails with Foreign Key Error

```
Error: Cannot add foreign key constraint
```

**Solution:** Ensure `users` table exists with `user_id` column:

```sql
DESCRIBE users;
-- Should show user_id as INT UNSIGNED
```

### API Returns 500 Error

Check PHP error log:

```bash
tail -f logs/php_errors.log
```

Common causes:
- Missing required files
- Database connection issues
- Syntax errors in PHP files

### Frontend Build Fails

```bash
# Clear cache and rebuild
rm -rf node_modules/.cache
npm run build
```

### PeerJS Connection Fails

Check browser console for:
- CORS errors (configure server)
- WebSocket connection errors (check firewall)
- SSL certificate issues (ensure valid HTTPS)

---

## Support

For issues with video meeting migration:

1. Check [`docs/VIDEO_MEETING_API.md`](./VIDEO_MEETING_API.md) for API reference
2. Review migration logs in `logs/video_meetings_migration.log`
3. Check PHP error logs in `logs/php_errors.log`
4. Contact SafeShift support team

---

*Migration Guide for SafeShift EHR Video Meeting Feature v1.0.0*
