# Changelog

All notable changes to the SafeShift EHR system will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Video Meeting feature for telehealth consultations (see below)

---

## [1.5.0] - 2026-01-11

### Added

#### Video Meeting Feature (Telehealth)

A complete WebRTC-based video meeting system for HIPAA-compliant telehealth visits.

**Database Changes:**
- New table: `video_meetings` - Core meeting records with token-based access
- New table: `meeting_participants` - Participant tracking with join/leave times
- New table: `meeting_chat_messages` - In-meeting chat message storage
- New table: `video_meeting_logs` - Comprehensive audit logging

**Backend API Endpoints:**
- `POST /api/video/create-meeting.php` - Create new video meeting (clinicians only)
- `GET /api/video/validate-token.php` - Validate meeting token (public)
- `POST /api/video/join-meeting.php` - Join meeting with display name (public)
- `POST /api/video/leave-meeting.php` - Leave meeting
- `POST /api/video/end-meeting.php` - End meeting (host only)
- `GET /api/video/meeting-link.php` - Get shareable meeting link
- `GET /api/video/my-meetings.php` - Get user's meetings
- `GET /api/video/participants.php` - Get meeting participants
- `POST /api/video/chat-message.php` - Send chat message
- `GET /api/video/chat-history.php` - Get chat history
- `POST /api/video/signal/register-peer.php` - Register PeerJS peer
- `GET /api/video/signal/get-peers.php` - Get active peers
- `POST /api/video/signal/heartbeat.php` - Keep-alive with peer list
- `POST /api/video/signal/disconnect.php` - Disconnect peer

**ViewModel Layer:**
- New: `ViewModel/VideoMeetingViewModel.php` - Business logic for meetings
  - Token generation/validation
  - RBAC clinician role checking
  - Participant management
  - Chat operations
  - Peer signaling operations
  - Comprehensive logging

**Entity Classes:**
- New: `model/Entities/VideoMeeting.php` - Meeting entity
- New: `model/Entities/MeetingParticipant.php` - Participant entity
- New: `model/Entities/MeetingChatMessage.php` - Chat message entity

**Repository:**
- New: `model/Repositories/VideoMeetingRepository.php` - Data access layer

**Frontend Components:**
- New: `src/app/components/VideoMeeting/VideoMeetingPage.tsx` - Main meeting container
- New: `src/app/components/VideoMeeting/VideoDisplay.tsx` - Video grid display
- New: `src/app/components/VideoMeeting/MeetingControls.tsx` - Audio/video controls
- New: `src/app/components/VideoMeeting/ChatBox.tsx` - In-meeting chat
- New: `src/app/components/VideoMeeting/ParticipantsList.tsx` - Participant list
- New: `src/app/components/VideoMeeting/ShareLinkModal.tsx` - Share meeting link
- New: `src/app/components/VideoMeeting/JoinModal.tsx` - Join meeting dialog
- New: `src/app/components/VideoMeeting/CallTimer.tsx` - Meeting duration timer

**Frontend Services:**
- New: `src/app/services/video-meeting.service.ts` - Meeting API calls
- New: `src/app/services/video-signaling.service.ts` - Peer signaling
- New: `src/app/services/webrtc.service.ts` - PeerJS WebRTC wrapper

**Frontend Hooks:**
- New: `src/app/hooks/useVideoMeeting.ts` - Video meeting state management

**Security Features:**
- 64-character cryptographic tokens (256-bit entropy)
- 24-hour token expiration
- RBAC: Only clinicians can create meetings
- XSS prevention on all user input
- SQL injection prevention via prepared statements
- IP address logging for audit trails
- Comprehensive event logging

**Documentation:**
- New: `docs/VIDEO_MEETING_API.md` - Complete API documentation
- New: `docs/VIDEO_MEETING_MIGRATION_GUIDE.md` - Deployment guide

**Migration:**
- New: `database/migrations/video_meetings_schema.sql` - Schema migration
- New: `database/run_video_meetings_migration.php` - Migration runner

### Technical Details

- **WebRTC**: Peer-to-peer video using PeerJS for signaling
- **Heartbeat System**: 5-second intervals, 30-second stale threshold
- **Token System**: Secure random generation with uniqueness verification
- **Chat**: Real-time text chat with persistent storage
- **Logging**: All events logged for HIPAA compliance

### Dependencies

- PeerJS (npm package for WebRTC signaling)

---

## [1.4.0] - 2025-12-15

### Added
- OSHA ITA Integration Architecture
- Patient Portal Architecture
- Session Management Architecture

### Changed
- React Migration Analysis updates
- MVVM refactoring progress

### Fixed
- Bootstrap cleanup (Phase 9)
- Placeholder content audit and cleanup

---

## [1.3.0] - 2025-11-01

### Added
- Vertical Slice Architecture documentation
- Security documentation improvements
- Testing checklist and guide

### Changed
- MVVM migration mapping updates
- Role mapping improvements

---

## [1.2.0] - 2025-10-01

### Added
- Enhanced encounter workflow
- Dashboard metrics improvements
- User credential management

### Fixed
- Various bug fixes and performance improvements

---

## [1.1.0] - 2025-09-01

### Added
- Initial MVVM architecture
- Core entity classes
- Repository pattern implementation

---

## [1.0.0] - 2025-08-01

### Added
- Initial release of SafeShift EHR
- Core EHR functionality
- User authentication and authorization
- Patient management
- Encounter documentation
- DOT testing module
- OSHA injury tracking

---

[Unreleased]: https://github.com/safeshift/ehr/compare/v1.5.0...HEAD
[1.5.0]: https://github.com/safeshift/ehr/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/safeshift/ehr/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/safeshift/ehr/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/safeshift/ehr/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/safeshift/ehr/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/safeshift/ehr/releases/tag/v1.0.0
