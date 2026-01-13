# Patient Portal Architecture

## SafeShift EHR - Patient Portal Feature Design

**Document Version:** 1.0  
**Created:** 2025-12-28  
**Status:** Architecture Design - Pending Business Decisions

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Features Overview](#features-overview)
3. [Technical Architecture](#technical-architecture)
4. [Database Schema](#database-schema)
5. [API Endpoints](#api-endpoints)
6. [Frontend Structure](#frontend-structure)
7. [Security Considerations](#security-considerations)
8. [Integration Points](#integration-points)
9. [User Experience Flows](#user-experience-flows)
10. [Implementation Phases](#implementation-phases)
11. [Technical Considerations](#technical-considerations)
12. [Security Checklist](#security-checklist)
13. [Business Decisions Required](#business-decisions-required)

---

## Executive Summary

### Purpose

The Patient Portal is a secure, HIPAA-compliant web application that provides patients with limited access to their healthcare information within the SafeShift EHR system. This portal enables patients who have had encounters at SafeShift-managed clinics to:

- View their scheduled appointments
- Receive SMS notifications about appointments
- Manage their notification preferences
- Access limited personal health information

### Key Features Overview

| Feature | Description | Priority |
|---------|-------------|----------|
| Patient Authentication | Secure login separate from provider portal | Phase 1 |
| View Appointments | List of upcoming and past appointments | Phase 1 |
| SMS Notifications | Appointment reminders and follow-ups | Phase 2 |
| Profile Management | Update contact info, notification preferences | Phase 2 |
| Appointment Rescheduling | Request reschedule (not direct modification) | Phase 3 |
| Document Viewing | View consent forms, visit summaries | Phase 3 |

### Target Users

- **Primary:** Patients with existing encounters in SafeShift EHR
- **Secondary:** Patients with scheduled future appointments
- **Exclusions:** Walk-in patients without prior registration, minors without guardian access

---

## Features Overview

### 1. Patient Login Portal

#### Secure Authentication (Separate from Provider Portal)

The patient portal uses a completely separate authentication system from the provider portal to ensure:
- Different security contexts
- Isolated session management
- Clear audit trails for patient vs provider access

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    AUTHENTICATION ARCHITECTURE                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────┐              ┌─────────────────────┐               │
│  │   Provider Portal   │              │   Patient Portal    │               │
│  │   ───────────────   │              │   ───────────────   │               │
│  │   /login            │              │   /patient/login    │               │
│  │   users table       │              │   patient_portal_   │               │
│  │   Full EHR access   │              │   accounts table    │               │
│  │   Role-based perms  │              │   Read-only access  │               │
│  └──────────┬──────────┘              └──────────┬──────────┘               │
│             │                                    │                          │
│             └─────────────┬──────────────────────┘                          │
│                           │                                                 │
│                           ▼                                                 │
│              ┌────────────────────────┐                                     │
│              │   SHARED COMPONENTS    │                                     │
│              │   ────────────────     │                                     │
│              │   - AuditService       │                                     │
│              │   - SMS Integration    │                                     │
│              │   - Patient data       │                                     │
│              │     (read-only)        │                                     │
│              └────────────────────────┘                                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Patient Registration/Verification Process

Patients cannot self-register. Portal access is granted through a secure invitation flow:

1. **Invitation Trigger:** After an encounter is completed
2. **Invite Generation:** Clinic staff generates invite link
3. **Identity Verification:** Patient verifies via DOB + SSN last 4 OR phone OTP
4. **Account Creation:** Patient creates password and sets preferences

#### Password Requirements

| Requirement | Value | Notes |
|-------------|-------|-------|
| Minimum Length | 10 characters | Slightly less strict than provider (12) |
| Complexity | Upper, lower, number | Special char optional |
| History | Last 3 passwords | Prevent reuse |
| Expiration | 180 days | Prompt to change, not force |
| Lockout | 5 failed attempts | 15-minute lockout |

#### MFA Options

| Method | Implementation | **[BUSINESS DECISION]** |
|--------|----------------|-------------------------|
| SMS OTP | Twilio integration (existing) | Default option |
| Email OTP | SMTP integration (existing) | Alternative option |
| Authenticator App | TOTP standard | Future consideration |

#### Session Management

| Parameter | Patient Portal | Provider Portal | Reason |
|-----------|----------------|-----------------|--------|
| Idle Timeout | 15 minutes | 60 minutes configurable | Patients on shared devices |
| Max Session | 30 minutes | 60 minutes | Reduced exposure risk |
| Warning Before | 2 minutes | 2 minutes | Consistent UX |
| Concurrent Sessions | 2 max | Unlimited | Prevent sharing |

---

### 2. View Scheduled Appointments

#### Appointment List Features

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    PATIENT APPOINTMENT DASHBOARD                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  UPCOMING APPOINTMENTS                                                │  │
│  ├──────────────────────────────────────────────────────────────────────┤  │
│  │                                                                       │  │
│  │  📅 January 5, 2026 at 10:30 AM                                       │  │
│  │  ─────────────────────────────────────                               │  │
│  │  Provider: Dr. Smith                                                  │  │
│  │  Clinic: Downtown Occupational Health                                 │  │
│  │  Type: Follow-up Visit                                                │  │
│  │  Address: 123 Main St, Denver CO 80202                               │  │
│  │                                                                       │  │
│  │  [Request Reschedule]  [Add to Calendar]  [Get Directions]           │  │
│  │                                                                       │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  PAST APPOINTMENTS (Last 12 months)                                   │  │
│  ├──────────────────────────────────────────────────────────────────────┤  │
│  │                                                                       │  │
│  │  ✓ December 15, 2025 - Annual Physical - Dr. Johnson                 │  │
│  │  ✓ October 3, 2025 - DOT Physical - Dr. Smith                        │  │
│  │  ✓ August 22, 2025 - Follow-up - Dr. Smith                           │  │
│  │                                                                       │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Appointment Details Visible to Patient

| Field | Visible | Notes |
|-------|---------|-------|
| Date/Time | ✅ Yes | Including timezone |
| Clinic Name | ✅ Yes | Full name and address |
| Provider Name | ✅ Yes | Dr. First Last format |
| Appointment Type | ✅ Yes | Generic type, not detailed reason |
| Status | ✅ Yes | Scheduled, Completed, Cancelled |
| Clinical Notes | ❌ No | Provider-only access |
| Diagnosis Codes | ❌ No | HIPAA minimum necessary |
| Billing Info | ❌ No | Separate billing portal |

#### Appointment History

- **Retention:** 12 months of history visible
- **Export:** Option to download appointment list as PDF
- **Search:** Filter by date range, clinic, provider

#### Reschedule Request Flow

```
Patient clicks "Request Reschedule"
         │
         ▼
┌─────────────────────────────┐
│  Select preferred dates     │
│  (up to 3 options)          │
│  + Optional notes           │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  Request submitted to       │
│  scheduling queue           │
│  (NOT instant reschedule)   │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  Clinic staff reviews       │
│  and confirms new time      │
│  via phone/SMS              │
└─────────────────────────────┘
```

---

### 3. SMS Notifications

#### Integration with Existing Twilio Service

The patient portal leverages the existing SMS infrastructure at [`api/v1/sms/send-reminder.php`](../api/v1/sms/send-reminder.php):

```php
// Existing Twilio configuration (from send-reminder.php)
$twilioSid = getenv('TWILIO_ACCOUNT_SID');
$twilioToken = getenv('TWILIO_AUTH_TOKEN');
$twilioFrom = getenv('TWILIO_PHONE_NUMBER');
```

#### Opt-in/Opt-out Management

Patients must explicitly opt-in to receive SMS notifications (TCPA compliance):

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    SMS PREFERENCE MANAGEMENT                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  SMS Notifications                                                          │
│  ─────────────────                                                          │
│                                                                             │
│  ☑ Enable SMS Notifications                                                 │
│                                                                             │
│  Phone Number: (303) 555-1234  [Change]                                     │
│                                                                             │
│  ┌────────────────────────────────────────────────────────────────────┐    │
│  │  Notification Types                                                │    │
│  ├────────────────────────────────────────────────────────────────────┤    │
│  │  ☑ Appointment Reminders (24 hours before)                        │    │
│  │  ☑ Appointment Reminders (2 hours before)                         │    │
│  │  ☑ Appointment Confirmations                                       │    │
│  │  ☐ Follow-up Reminders                                             │    │
│  │  ☐ Clinic Announcements                                            │    │
│  └────────────────────────────────────────────────────────────────────┘    │
│                                                                             │
│  Quiet Hours: ☑ No messages between 9:00 PM and 8:00 AM                    │
│                                                                             │
│  [Save Preferences]                                                         │
│                                                                             │
│  ─────────────────────────────────────────────────────────────────────     │
│  To opt out of all SMS, text STOP to our number or uncheck the box above.  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

#### Notification Types

| Type | Default | Timing | **[BUSINESS DECISION]** |
|------|---------|--------|-------------------------|
| 24-hour Reminder | Opt-in | 24h before appointment | Standard |
| 2-hour Reminder | Opt-in | 2h before appointment | Standard |
| Appointment Confirmed | Opt-in | Immediate | Standard |
| Appointment Rescheduled | Opt-in | Immediate | Standard |
| Appointment Cancelled | Always | Immediate | Required |
| Follow-up Reminder | Opt-in | 7 days after encounter | Needs approval |
| Results Ready | Opt-in | When released | Phase 3 |

#### Frequency Controls

- **Daily Cap:** Maximum 3 messages per day per patient
- **Quiet Hours:** Configurable (default 9 PM - 8 AM)
- **Cool-down:** Minimum 30 minutes between messages

#### Delivery Tracking

```sql
-- Tracks SMS delivery status via Twilio webhooks
-- See sms_logs table in existing schema
SELECT 
    patient_id,
    message_type,
    status,        -- pending, sent, delivered, failed, undelivered
    sent_at,
    delivered_at,
    error_message
FROM sms_logs
WHERE patient_id = :patient_id
ORDER BY created_at DESC;
```

---

## Technical Architecture

### System Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                         PATIENT PORTAL ARCHITECTURE                               │
└──────────────────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────────────────────────┐
                    │          PATIENT DEVICES            │
                    │  ┌───────────┐    ┌───────────┐    │
                    │  │  Mobile   │    │  Desktop  │    │
                    │  │  Browser  │    │  Browser  │    │
                    │  └─────┬─────┘    └─────┬─────┘    │
                    └────────┼────────────────┼──────────┘
                             │                │
                             └───────┬────────┘
                                     │ HTTPS/TLS 1.3
                                     ▼
┌────────────────────────────────────────────────────────────────────────────────┐
│                            CLOUDFLARE / WAF                                     │
│                    (Rate limiting, DDoS protection, SSL)                        │
└────────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌────────────────────────────────────────────────────────────────────────────────┐
│                           FRONTEND LAYER                                        │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                    PATIENT PORTAL SPA (React)                            │   │
│  │  ─────────────────────────────────────────────────────────────────────   │   │
│  │  Route: /patient/*                                                       │   │
│  │                                                                          │   │
│  │  Components:                                                             │   │
│  │  ├── PatientLogin.tsx           - Authentication UI                     │   │
│  │  ├── PatientDashboard.tsx       - Main dashboard                        │   │
│  │  ├── AppointmentList.tsx        - Upcoming/past appointments            │   │
│  │  ├── AppointmentDetail.tsx      - Single appointment view               │   │
│  │  ├── NotificationPrefs.tsx      - SMS/notification settings             │   │
│  │  ├── PatientProfile.tsx         - Profile management                    │   │
│  │  └── RescheduleRequest.tsx      - Request reschedule form               │   │
│  │                                                                          │   │
│  │  Hooks:                                                                  │   │
│  │  ├── usePatientAuth.ts          - Patient authentication                │   │
│  │  ├── useAppointments.ts         - Appointment data fetching             │   │
│  │  └── useNotificationPrefs.ts    - Preference management                 │   │
│  │                                                                          │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
└────────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     │ REST API (JSON)
                                     ▼
┌────────────────────────────────────────────────────────────────────────────────┐
│                            API LAYER (PHP)                                      │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │              API Router: /api/v1/patient-portal/*                        │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                     │                                          │
│                    ┌────────────────┼────────────────┐                         │
│                    ▼                ▼                ▼                         │
│  ┌─────────────────────┐  ┌─────────────────┐  ┌─────────────────┐            │
│  │ PatientAuthViewModel│  │ AppointmentVM   │  │ NotificationVM  │            │
│  │ ─────────────────── │  │ ───────────────│  │ ───────────────│            │
│  │ - login()           │  │ - getList()    │  │ - getPrefs()   │            │
│  │ - verifyOtp()       │  │ - getDetail()  │  │ - updatePrefs()│            │
│  │ - register()        │  │ - reqResched() │  │ - optOut()     │            │
│  │ - resetPassword()   │  └────────┬────────┘  └────────┬────────┘            │
│  └──────────┬──────────┘           │                    │                     │
│             │                      │                    │                     │
│             └──────────────────────┼────────────────────┘                     │
│                                    ▼                                          │
│  ┌─────────────────────────────────────────────────────────────────────────┐  │
│  │                         SERVICE LAYER                                    │  │
│  │  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐       │  │
│  │  │PatientAuthService│  │AppointmentService│  │SMSService        │       │  │
│  │  │(NEW)             │  │(Extended)        │  │(Existing)        │       │  │
│  │  └──────────────────┘  └──────────────────┘  └──────────────────┘       │  │
│  │                                                                          │  │
│  │  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐       │  │
│  │  │AuditService      │  │AuthorizationSvc  │  │EncryptionService │       │  │
│  │  │(Existing)        │  │(Extended)        │  │(Existing)        │       │  │
│  │  └──────────────────┘  └──────────────────┘  └──────────────────┘       │  │
│  └─────────────────────────────────────────────────────────────────────────┘  │
│                                                                                 │
└────────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌────────────────────────────────────────────────────────────────────────────────┐
│                           DATA LAYER (MySQL)                                    │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌──────────────────────────────┐  ┌────────────────────────────────────────┐  │
│  │   NEW TABLES                  │  │   EXISTING TABLES (Read-Only)          │  │
│  │  ─────────────────           │  │  ─────────────────────────             │  │
│  │  - patient_portal_accounts   │  │  - Patient                             │  │
│  │  - patient_portal_sessions   │  │  - Encounter                           │  │
│  │  - patient_notification_prefs│  │  - Appointment                         │  │
│  │  - patient_activity_log      │  │  - Clinic                              │  │
│  │  - patient_reschedule_reqs   │  │  - user (provider lookup only)         │  │
│  └──────────────────────────────┘  └────────────────────────────────────────┘  │
│                                                                                 │
│  ┌──────────────────────────────────────────────────────────────────────────┐  │
│  │   SHARED TABLES                                                           │  │
│  │  ─────────────────                                                       │  │
│  │  - sms_logs (existing - extended)                                        │  │
│  │  - AuditEvent (existing - extended for patient portal)                   │  │
│  └──────────────────────────────────────────────────────────────────────────┘  │
│                                                                                 │
└────────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌────────────────────────────────────────────────────────────────────────────────┐
│                        EXTERNAL INTEGRATIONS                                    │
├────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌────────────────────┐  ┌────────────────────┐  ┌────────────────────┐        │
│  │      TWILIO        │  │       SMTP         │  │   GOOGLE MAPS      │        │
│  │   (SMS Service)    │  │   (Email OTP)      │  │   (Directions)     │        │
│  │   ───────────────  │  │   ───────────────  │  │   ───────────────  │        │
│  │   - Reminders      │  │   - OTP delivery   │  │   - Clinic map     │        │
│  │   - Confirmations  │  │   - Password reset │  │   - Directions     │        │
│  │   - Delivery hooks │  │                    │  │                    │        │
│  └────────────────────┘  └────────────────────┘  └────────────────────┘        │
│                                                                                 │
└────────────────────────────────────────────────────────────────────────────────┘
```

### Authentication System Design

#### Separate Patient Authentication

The patient portal uses a dedicated authentication table separate from the provider `user` table:

```php
// PatientAuthService.php - Key authentication methods

class PatientAuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes
    private const SESSION_DURATION = 1800; // 30 minutes
    private const IDLE_TIMEOUT = 900; // 15 minutes
    
    /**
     * Authenticate patient with email/phone and password
     */
    public function authenticate(string $identifier, string $password): ?array
    {
        // Check lockout status
        if ($this->isLockedOut($identifier)) {
            throw new AuthenticationException('Account locked. Try again later.');
        }
        
        // Find account by email or phone
        $account = $this->repository->findByIdentifier($identifier);
        
        if (!$account || !$this->verifyPassword($password, $account['password_hash'])) {
            $this->recordFailedAttempt($identifier);
            return null;
        }
        
        // Create session
        return $this->createSession($account);
    }
    
    /**
     * Verify patient identity during registration
     * Uses DOB + SSN last 4 OR phone OTP
     */
    public function verifyIdentity(string $patientId, array $verification): bool
    {
        $patient = $this->patientRepo->findById($patientId);
        
        if ($verification['method'] === 'dob_ssn') {
            return $this->verifyDobSsn(
                $patient,
                $verification['dob'],
                $verification['ssn_last_four']
            );
        }
        
        if ($verification['method'] === 'phone_otp') {
            return $this->verifyPhoneOtp(
                $patient['primary_phone'],
                $verification['otp']
            );
        }
        
        return false;
    }
}
```

#### Token-Based Authentication

**[BUSINESS DECISION]** JWT vs Session-based authentication:

| Approach | Pros | Cons | Recommendation |
|----------|------|------|----------------|
| JWT Tokens | Stateless, scalable | Token revocation complex, larger payload | For mobile app future |
| Session-based | Easy revocation, server control | Server state required | **Current recommendation** |
| Hybrid | JWT for auth, DB sessions for tracking | More complex | Future consideration |

**Recommended Approach:** Session-based authentication consistent with the existing provider portal, with database-backed session tracking for audit and revocation capabilities.

#### Password Reset Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PASSWORD RESET FLOW                                  │
└─────────────────────────────────────────────────────────────────────────────┘

Patient clicks "Forgot Password"
         │
         ▼
┌─────────────────────────────┐
│  Enter email or phone       │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  System verifies account    │
│  exists (no reveal if not)  │
└──────────────┬──────────────┘
               │
               ├──────────── Account NOT found ────► "If account exists, you'll
               │                                      receive instructions"
               │
               ▼ Account found
┌─────────────────────────────┐
│  Generate reset token       │
│  (256-bit, 1-hour expiry)   │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  Send via preferred method  │
│  (SMS or Email)             │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  Patient clicks link        │
│  or enters code             │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  Additional verification:   │
│  DOB + SSN last 4           │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  Set new password           │
│  (validate requirements)    │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│  Invalidate all sessions    │
│  Log audit event            │
│  Send confirmation          │
└─────────────────────────────┘
```

---

## Database Schema

### New Tables for Patient Portal

```sql
-- ============================================================================
-- PATIENT PORTAL DATABASE SCHEMA
-- SafeShift EHR - Patient Portal Feature
-- ============================================================================

-- ============================================================================
-- 1. PATIENT PORTAL ACCOUNTS
-- Links patients to their portal login credentials
-- ============================================================================

CREATE TABLE IF NOT EXISTS `patient_portal_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` CHAR(36) NOT NULL 
        COMMENT 'FK to Patient.patient_id',
    `email` VARCHAR(255) NOT NULL 
        COMMENT 'Login email (may differ from Patient.email)',
    `phone` VARCHAR(20) DEFAULT NULL 
        COMMENT 'Alternative login phone',
    `password_hash` VARCHAR(255) NOT NULL 
        COMMENT 'Bcrypt hash (cost 12)',
    `password_changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
        COMMENT 'For expiration tracking',
    `mfa_enabled` BOOLEAN DEFAULT FALSE 
        COMMENT 'Whether MFA is required',
    `mfa_method` ENUM('sms', 'email', 'totp') DEFAULT 'sms' 
        COMMENT 'Preferred MFA method',
    `mfa_secret` VARCHAR(255) DEFAULT NULL 
        COMMENT 'TOTP secret (encrypted)',
    `status` ENUM('pending', 'active', 'locked', 'disabled') DEFAULT 'pending'
        COMMENT 'Account status',
    `failed_attempts` INT DEFAULT 0 
        COMMENT 'Consecutive failed login attempts',
    `locked_until` TIMESTAMP NULL DEFAULT NULL 
        COMMENT 'Lockout expiration',
    `email_verified` BOOLEAN DEFAULT FALSE,
    `phone_verified` BOOLEAN DEFAULT FALSE,
    `terms_accepted_at` TIMESTAMP NULL DEFAULT NULL 
        COMMENT 'HIPAA notice acceptance',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    
    -- Indexes
    UNIQUE KEY `uk_patient_id` (`patient_id`),
    UNIQUE KEY `uk_email` (`email`),
    INDEX `idx_phone` (`phone`),
    INDEX `idx_status` (`status`),
    
    -- Foreign Key
    CONSTRAINT `fk_portal_patient` 
        FOREIGN KEY (`patient_id`) 
        REFERENCES `Patient` (`patient_id`) 
        ON DELETE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Patient portal login accounts - separate from provider users table';


-- ============================================================================
-- 2. PATIENT NOTIFICATION PREFERENCES
-- Stores patient SMS/notification opt-in preferences (TCPA compliant)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `patient_notification_preferences` (
    `patient_id` CHAR(36) NOT NULL PRIMARY KEY
        COMMENT 'FK to Patient.patient_id',
    `sms_enabled` BOOLEAN DEFAULT FALSE 
        COMMENT 'Master SMS opt-in (TCPA)',
    `sms_phone` VARCHAR(20) DEFAULT NULL 
        COMMENT 'SMS delivery number',
    `sms_consent_at` TIMESTAMP NULL DEFAULT NULL 
        COMMENT 'When patient opted in to SMS',
    `sms_consent_method` VARCHAR(50) DEFAULT NULL 
        COMMENT 'How consent was obtained (portal, verbal, form)',
    
    -- Notification type preferences
    `notify_appt_reminder_24h` BOOLEAN DEFAULT TRUE,
    `notify_appt_reminder_2h` BOOLEAN DEFAULT TRUE,
    `notify_appt_confirmed` BOOLEAN DEFAULT TRUE,
    `notify_appt_rescheduled` BOOLEAN DEFAULT TRUE,
    `notify_appt_cancelled` BOOLEAN DEFAULT TRUE 
        COMMENT 'Always sent regardless of preference',
    `notify_followup_reminder` BOOLEAN DEFAULT FALSE,
    `notify_results_ready` BOOLEAN DEFAULT FALSE 
        COMMENT 'Phase 3 feature',
    
    -- Delivery preferences
    `quiet_hours_enabled` BOOLEAN DEFAULT TRUE,
    `quiet_hours_start` TIME DEFAULT '21:00:00' 
        COMMENT 'No messages after this time',
    `quiet_hours_end` TIME DEFAULT '08:00:00' 
        COMMENT 'No messages before this time',
    `timezone` VARCHAR(50) DEFAULT 'America/Denver',
    `daily_message_limit` INT DEFAULT 3,
    
    -- Email preferences (backup)
    `email_enabled` BOOLEAN DEFAULT TRUE,
    `email_address` VARCHAR(255) DEFAULT NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Key
    CONSTRAINT `fk_notif_prefs_patient` 
        FOREIGN KEY (`patient_id`) 
        REFERENCES `Patient` (`patient_id`) 
        ON DELETE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Patient notification preferences for SMS and email';


-- ============================================================================
-- 3. PATIENT PORTAL SESSIONS
-- Database-backed session tracking for audit and security
-- ============================================================================

CREATE TABLE IF NOT EXISTS `patient_portal_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL 
        COMMENT 'FK to patient_portal_accounts',
    `session_token` VARCHAR(255) NOT NULL 
        COMMENT 'Hashed session token',
    `device_info` VARCHAR(255) DEFAULT NULL 
        COMMENT 'Browser/OS (sanitized, no PII)',
    `ip_address` VARCHAR(45) DEFAULT NULL 
        COMMENT 'IPv4 or IPv6',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
        ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL 
        COMMENT 'Hard expiry (30 min max)',
    `is_active` BOOLEAN DEFAULT TRUE,
    
    -- Indexes
    UNIQUE KEY `uk_session_token` (`session_token`),
    INDEX `idx_account_active` (`account_id`, `is_active`),
    INDEX `idx_expires` (`expires_at`),
    
    -- Foreign Key
    CONSTRAINT `fk_session_account` 
        FOREIGN KEY (`account_id`) 
        REFERENCES `patient_portal_accounts` (`id`) 
        ON DELETE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Active patient portal sessions';


-- ============================================================================
-- 4. PATIENT ACTIVITY LOG (HIPAA Audit Trail)
-- Separate audit log for patient portal access
-- ============================================================================

CREATE TABLE IF NOT EXISTS `patient_activity_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT NOT NULL 
        COMMENT 'FK to patient_portal_accounts',
    `patient_id` CHAR(36) NOT NULL 
        COMMENT 'FK to Patient (denormalized for query performance)',
    `action` VARCHAR(50) NOT NULL 
        COMMENT 'Action type: login, logout, view_appointment, etc.',
    `resource_type` VARCHAR(50) DEFAULT NULL 
        COMMENT 'Type of resource accessed',
    `resource_id` VARCHAR(36) DEFAULT NULL 
        COMMENT 'ID of resource accessed',
    `details` JSON DEFAULT NULL 
        COMMENT 'Additional action details',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `session_id` INT DEFAULT NULL 
        COMMENT 'FK to patient_portal_sessions',
    `checksum` VARCHAR(64) NOT NULL 
        COMMENT 'SHA-256 for integrity verification',
    `created_at` TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6),
    
    -- Indexes
    INDEX `idx_account` (`account_id`),
    INDEX `idx_patient` (`patient_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_resource` (`resource_type`, `resource_id`),
    
    -- Foreign Keys
    CONSTRAINT `fk_activity_account` 
        FOREIGN KEY (`account_id`) 
        REFERENCES `patient_portal_accounts` (`id`) 
        ON DELETE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='HIPAA audit trail for patient portal access';


-- ============================================================================
-- 5. PATIENT RESCHEDULE REQUESTS
-- Queue for patient-initiated reschedule requests
-- ============================================================================

CREATE TABLE IF NOT EXISTS `patient_reschedule_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` CHAR(36) NOT NULL,
    `appointment_id` CHAR(36) NOT NULL 
        COMMENT 'FK to Appointment table',
    `preferred_date_1` DATE NOT NULL,
    `preferred_time_1` VARCHAR(20) DEFAULT NULL 
        COMMENT 'AM, PM, or specific time',
    `preferred_date_2` DATE DEFAULT NULL,
    `preferred_time_2` VARCHAR(20) DEFAULT NULL,
    `preferred_date_3` DATE DEFAULT NULL,
    `preferred_time_3` VARCHAR(20) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL 
        COMMENT 'Patient-provided reason',
    `status` ENUM('pending', 'contacted', 'rescheduled', 'cancelled', 'no_show') 
        DEFAULT 'pending',
    `staff_notes` TEXT DEFAULT NULL 
        COMMENT 'Internal staff notes',
    `handled_by` CHAR(36) DEFAULT NULL 
        COMMENT 'FK to user who processed',
    `handled_at` TIMESTAMP NULL DEFAULT NULL,
    `new_appointment_id` CHAR(36) DEFAULT NULL 
        COMMENT 'FK to new appointment if rescheduled',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX `idx_patient` (`patient_id`),
    INDEX `idx_appointment` (`appointment_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`),
    
    -- Foreign Keys
    CONSTRAINT `fk_resched_patient` 
        FOREIGN KEY (`patient_id`) 
        REFERENCES `Patient` (`patient_id`) 
        ON DELETE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Patient-initiated appointment reschedule requests';


-- ============================================================================
-- 6. PATIENT PORTAL INVITATIONS
-- Tracks portal access invitations sent to patients
-- ============================================================================

CREATE TABLE IF NOT EXISTS `patient_portal_invitations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` CHAR(36) NOT NULL,
    `invite_token` VARCHAR(255) NOT NULL 
        COMMENT 'Hashed invitation token',
    `invite_method` ENUM('email', 'sms', 'in_person') NOT NULL,
    `sent_to` VARCHAR(255) NOT NULL 
        COMMENT 'Email or phone where sent',
    `sent_by` CHAR(36) NOT NULL 
        COMMENT 'FK to user who sent invite',
    `expires_at` TIMESTAMP NOT NULL 
        COMMENT '7-day expiration',
    `status` ENUM('sent', 'clicked', 'registered', 'expired') DEFAULT 'sent',
    `clicked_at` TIMESTAMP NULL DEFAULT NULL,
    `registered_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    UNIQUE KEY `uk_invite_token` (`invite_token`),
    INDEX `idx_patient` (`patient_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expires` (`expires_at`),
    
    -- Foreign Keys
    CONSTRAINT `fk_invite_patient` 
        FOREIGN KEY (`patient_id`) 
        REFERENCES `Patient` (`patient_id`) 
        ON DELETE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracking for patient portal invitation links';


-- ============================================================================
-- 7. EXTEND SMS_LOGS TABLE (if needed)
-- Add patient portal specific fields
-- ============================================================================

-- Check if columns exist before adding
-- ALTER TABLE `sms_logs` ADD COLUMN IF NOT EXISTS 
--     `source` ENUM('provider', 'patient_portal', 'automated') DEFAULT 'provider';
-- ALTER TABLE `sms_logs` ADD COLUMN IF NOT EXISTS 
--     `notification_type` VARCHAR(50) DEFAULT NULL;


-- ============================================================================
-- 8. CLEANUP EVENTS
-- Automated maintenance tasks
-- ============================================================================

DELIMITER //

-- Clean up expired sessions (runs every 5 minutes)
CREATE EVENT IF NOT EXISTS `cleanup_patient_sessions`
ON SCHEDULE EVERY 5 MINUTE
ENABLE
DO
BEGIN
    UPDATE `patient_portal_sessions` 
    SET `is_active` = FALSE 
    WHERE `is_active` = TRUE 
      AND `expires_at` < NOW();
      
    -- Delete sessions older than 24 hours
    DELETE FROM `patient_portal_sessions` 
    WHERE `is_active` = FALSE 
      AND `updated_at` < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END //

-- Clean up expired invitations (runs daily)
CREATE EVENT IF NOT EXISTS `cleanup_patient_invitations`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP + INTERVAL 1 HOUR
ENABLE
DO
BEGIN
    UPDATE `patient_portal_invitations` 
    SET `status` = 'expired' 
    WHERE `status` = 'sent' 
      AND `expires_at` < NOW();
END //

DELIMITER ;


-- ============================================================================
-- 9. VIEWS FOR COMMON QUERIES
-- ============================================================================

-- Patient appointments view (optimized for portal)
CREATE OR REPLACE VIEW `v_patient_appointments` AS
SELECT 
    a.appointment_id,
    a.patient_id,
    a.scheduled_date,
    a.scheduled_time,
    a.appointment_type,
    a.status,
    a.duration_minutes,
    c.clinic_name,
    c.address_line_1 AS clinic_address,
    c.city AS clinic_city,
    c.state AS clinic_state,
    c.zip_code AS clinic_zip,
    c.phone AS clinic_phone,
    CONCAT(u.first_name, ' ', u.last_name) AS provider_name,
    u.credentials AS provider_credentials
FROM Appointment a
LEFT JOIN Clinic c ON a.clinic_id = c.clinic_id
LEFT JOIN user u ON a.provider_id = u.user_id
WHERE a.status NOT IN ('deleted', 'no_show');
```

### Entity Relationship Diagram

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                    PATIENT PORTAL ENTITY RELATIONSHIPS                            │
└──────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────┐
│      Patient        │◄─────────────────────────────────────────────┐
│  (existing table)   │                                              │
├─────────────────────┤                                              │
│ patient_id (PK)     │                                              │
│ first_name          │                                              │
│ last_name           │                                              │
│ email               │                                              │
│ primary_phone       │                                              │
│ ...                 │                                              │
└─────────┬───────────┘                                              │
          │                                                          │
          │ 1:1                                                      │
          ▼                                                          │
┌─────────────────────────────┐                                      │
│ patient_portal_accounts     │                                      │
├─────────────────────────────┤                                      │
│ id (PK)                     │                                      │
│ patient_id (FK, UNIQUE)     │──────┐                               │
│ email                       │      │                               │
│ password_hash               │      │                               │
│ mfa_enabled                 │      │                               │
│ status                      │      │                               │
│ ...                         │      │                               │
└─────────────┬───────────────┘      │                               │
              │                      │                               │
              │ 1:N                  │ 1:1                           │
              ▼                      ▼                               │
┌──────────────────────────┐  ┌────────────────────────────────┐    │
│ patient_portal_sessions  │  │ patient_notification_preferences│    │
├──────────────────────────┤  ├────────────────────────────────┤    │
│ id (PK)                  │  │ patient_id (PK, FK)            │    │
│ account_id (FK)          │  │ sms_enabled                    │    │
│ session_token            │  │ sms_phone                      │    │
│ device_info              │  │ notify_appt_reminder_24h       │    │
│ ip_address               │  │ quiet_hours_enabled            │    │
│ expires_at               │  │ ...                            │    │
│ is_active                │  └────────────────────────────────┘    │
└──────────────────────────┘                                        │
                                                                    │
              ┌─────────────────────────────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────┐      ┌─────────────────────────────┐
│ patient_activity_log    │      │ patient_reschedule_requests │
├─────────────────────────┤      ├─────────────────────────────┤
│ id (PK)                 │      │ id (PK)                     │
│ account_id (FK)         │      │ patient_id (FK)             │
│ patient_id (FK)         │      │ appointment_id (FK)         │──┐
│ action                  │      │ preferred_date_1            │  │
│ resource_type           │      │ status                      │  │
│ resource_id             │      │ ...                         │  │
│ checksum                │      └─────────────────────────────┘  │
│ created_at              │                                       │
└─────────────────────────┘                                       │
                                                                  │
                               ┌──────────────────────────────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │    Appointment      │
                    │  (existing table)   │
                    ├─────────────────────┤
                    │ appointment_id (PK) │
                    │ patient_id (FK)     │
                    │ clinic_id (FK)      │
                    │ provider_id (FK)    │
                    │ scheduled_date      │
                    │ status              │
                    │ ...                 │
                    └─────────────────────┘
```

---

## API Endpoints

### API Endpoint Specifications

All patient portal endpoints are prefixed with `/api/v1/patient-portal/`

#### Authentication Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/login` | Patient login | No |
| POST | `/auth/verify-mfa` | Verify MFA code | Partial |
| POST | `/auth/logout` | Logout current session | Yes |
| POST | `/auth/register` | Complete registration from invite | No |
| POST | `/auth/forgot-password` | Request password reset | No |
| POST | `/auth/reset-password` | Complete password reset | No |
| GET | `/auth/session-status` | Check session validity | Yes |
| POST | `/auth/refresh-session` | Refresh session | Yes |

#### Appointment Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/appointments` | List patient appointments | Yes |
| GET | `/appointments/:id` | Get appointment details | Yes |
| POST | `/appointments/:id/reschedule-request` | Request reschedule | Yes |
| GET | `/appointments/upcoming` | Get upcoming appointments | Yes |
| GET | `/appointments/history` | Get past appointments | Yes |

#### Notification Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/notifications/preferences` | Get notification preferences | Yes |
| PUT | `/notifications/preferences` | Update preferences | Yes |
| POST | `/notifications/opt-out` | Opt out of SMS | Yes |
| GET | `/notifications/history` | Get notification history | Yes |

#### Profile Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/profile` | Get patient profile | Yes |
| PUT | `/profile` | Update profile | Yes |
| PUT | `/profile/password` | Change password | Yes |
| PUT | `/profile/mfa` | Configure MFA | Yes |

### Request/Response Examples

#### POST /auth/login

```typescript
// Request
{
  "identifier": "patient@email.com",  // or phone number
  "password": "SecurePassword123"
}

// Success Response (200)
{
  "success": true,
  "data": {
    "requires_mfa": true,
    "mfa_method": "sms",
    "mfa_hint": "***-***-1234",
    "session_token": null  // Not provided until MFA complete
  }
}

// Success Response (200) - No MFA
{
  "success": true,
  "data": {
    "requires_mfa": false,
    "session_token": "eyJhbGciOiJIUzI1NiIs...",
    "patient": {
      "id": "uuid",
      "first_name": "John",
      "last_name": "Doe"
    },
    "expires_at": "2025-12-28T20:30:00Z"
  }
}

// Error Response (401)
{
  "success": false,
  "error": "Invalid credentials",
  "remaining_attempts": 3
}

// Locked Response (429)
{
  "success": false,
  "error": "Account locked due to too many failed attempts",
  "locked_until": "2025-12-28T19:45:00Z"
}
```

#### GET /appointments

```typescript
// Request Headers
Authorization: Bearer <session_token>

// Query Parameters
?status=upcoming|past|all
&limit=10
&offset=0

// Success Response (200)
{
  "success": true,
  "data": {
    "appointments": [
      {
        "id": "uuid",
        "scheduled_date": "2026-01-05",
        "scheduled_time": "10:30:00",
        "type": "Follow-up Visit",
        "status": "scheduled",
        "clinic": {
          "name": "Downtown Occupational Health",
          "address": "123 Main St",
          "city": "Denver",
          "state": "CO",
          "zip": "80202",
          "phone": "(303) 555-1234",
          "map_url": "https://maps.google.com/..."
        },
        "provider": {
          "name": "Dr. Sarah Smith",
          "credentials": "MD"
        },
        "can_reschedule": true,
        "reminder_sent": true
      }
    ],
    "pagination": {
      "total": 5,
      "limit": 10,
      "offset": 0,
      "has_more": false
    }
  }
}
```

#### PUT /notifications/preferences

```typescript
// Request
{
  "sms_enabled": true,
  "sms_phone": "+13035551234",
  "preferences": {
    "appt_reminder_24h": true,
    "appt_reminder_2h": true,
    "appt_confirmed": true,
    "appt_rescheduled": true,
    "followup_reminder": false
  },
  "quiet_hours": {
    "enabled": true,
    "start": "21:00",
    "end": "08:00",
    "timezone": "America/Denver"
  }
}

// Success Response (200)
{
  "success": true,
  "data": {
    "message": "Preferences updated",
    "preferences": {
      "sms_enabled": true,
      "sms_phone": "+13035551234",
      // ... all preferences
    }
  }
}
```

#### POST /appointments/:id/reschedule-request

```typescript
// Request
{
  "preferred_dates": [
    { "date": "2026-01-10", "time_preference": "AM" },
    { "date": "2026-01-11", "time_preference": "PM" },
    { "date": "2026-01-12", "time_preference": "any" }
  ],
  "reason": "Work schedule conflict"
}

// Success Response (201)
{
  "success": true,
  "data": {
    "request_id": "uuid",
    "status": "pending",
    "message": "Your reschedule request has been submitted. The clinic will contact you within 1-2 business days to confirm a new appointment time."
  }
}
```

---

## Frontend Structure

### Route Structure

**[BUSINESS DECISION]** Separate React app vs route group:

| Approach | Pros | Cons | Recommendation |
|----------|------|------|----------------|
| Separate App | Complete isolation, independent deployment | More infrastructure, code duplication | For enterprise scale |
| Route Group | Shared components, single deployment | Larger bundle, shared state concerns | **Current recommendation** |
| Micro-frontend | Best of both, independent teams | Complex setup, learning curve | Future consideration |

**Recommended Structure:** Route group within existing React app at `/patient/*`

```
src/
├── app/
│   ├── components/
│   │   ├── patient-portal/                    # Patient portal components
│   │   │   ├── PatientLayout.tsx              # Portal layout wrapper
│   │   │   ├── PatientHeader.tsx              # Minimal header for patients
│   │   │   ├── PatientFooter.tsx              # Footer with support info
│   │   │   ├── AppointmentCard.tsx            # Appointment display card
│   │   │   ├── AppointmentDetail.tsx          # Full appointment view
│   │   │   ├── NotificationPrefsForm.tsx      # Notification settings form
│   │   │   ├── RescheduleRequestForm.tsx      # Reschedule request form
│   │   │   └── PatientSessionWarning.tsx      # Session timeout warning
│   │   └── ui/                                # Shared UI components (existing)
│   │
│   ├── hooks/
│   │   ├── usePatientAuth.ts                  # Patient authentication hook
│   │   ├── usePatientAppointments.ts          # Appointments data hook
│   │   ├── usePatientNotifications.ts         # Notification prefs hook
│   │   └── usePatientSession.ts               # Patient session management
│   │
│   ├── pages/
│   │   ├── patient/                           # Patient portal pages
│   │   │   ├── PatientLogin.tsx               # Login page
│   │   │   ├── PatientRegister.tsx            # Registration from invite
│   │   │   ├── PatientForgotPassword.tsx      # Password reset request
│   │   │   ├── PatientResetPassword.tsx       # Password reset completion
│   │   │   ├── PatientDashboard.tsx           # Main dashboard
│   │   │   ├── PatientAppointments.tsx        # Appointments list
│   │   │   ├── PatientAppointmentView.tsx     # Single appointment view
│   │   │   ├── PatientProfile.tsx             # Profile settings
│   │   │   └── PatientNotifications.tsx       # Notification preferences
│   │   └── auth/                              # Provider auth (existing)
│   │
│   ├── services/
│   │   ├── patient-auth.service.ts            # Patient auth API calls
│   │   ├── patient-appointments.service.ts    # Appointments API calls
│   │   └── patient-notifications.service.ts   # Notifications API calls
│   │
│   ├── contexts/
│   │   └── PatientAuthContext.tsx             # Patient auth state context
│   │
│   └── types/
│       └── patient-portal.types.ts            # TypeScript types for portal
```

### Mobile-First Responsive Design

```typescript
// Tailwind breakpoints for patient portal
// Designed mobile-first as patients primarily use phones

const responsiveClasses = {
  // Mobile: 0-640px
  container: "px-4 py-2",
  heading: "text-xl font-semibold",
  button: "w-full py-3 text-lg",
  
  // Tablet: 640px-1024px
  containerSm: "sm:px-6 sm:py-4",
  headingSm: "sm:text-2xl",
  buttonSm: "sm:w-auto sm:py-2 sm:px-6",
  
  // Desktop: 1024px+
  containerLg: "lg:px-8 lg:max-w-4xl lg:mx-auto",
  headingLg: "lg:text-3xl",
};
```

### Accessibility Requirements (WCAG 2.1 AA)

| Requirement | Implementation |
|-------------|----------------|
| Color Contrast | 4.5:1 minimum for text |
| Focus Indicators | Visible focus ring on all interactive elements |
| Keyboard Navigation | Full keyboard accessibility |
| Screen Readers | ARIA labels, semantic HTML |
| Text Sizing | Minimum 16px base, resizable to 200% |
| Touch Targets | Minimum 44x44px for touch elements |
| Form Labels | Associated labels for all inputs |
| Error Messages | Clear, descriptive error text |
| Skip Links | Skip to main content link |
| Language | `lang` attribute on HTML |

```tsx
// Example accessible appointment card component
export function AppointmentCard({ appointment }: { appointment: Appointment }) {
  return (
    <article 
      className="border rounded-lg p-4 focus-within:ring-2 focus-within:ring-blue-500"
      aria-labelledby={`appt-${appointment.id}-heading`}
    >
      <h3 
        id={`appt-${appointment.id}-heading`}
        className="text-lg font-semibold"
      >
        {formatDate(appointment.scheduled_date)}
      </h3>
      
      <dl className="mt-2 space-y-1">
        <div>
          <dt className="sr-only">Time</dt>
          <dd>{formatTime(appointment.scheduled_time)}</dd>
        </div>
        <div>
          <dt className="sr-only">Provider</dt>
          <dd>{appointment.provider.name}</dd>
        </div>
        <div>
          <dt className="sr-only">Location</dt>
          <dd>{appointment.clinic.name}</dd>
        </div>
      </dl>
      
      <div className="mt-4 flex gap-2">
        <Button
          variant="outline"
          aria-label={`Request to reschedule appointment on ${formatDate(appointment.scheduled_date)}`}
        >
          Request Reschedule
        </Button>
        <Button
          variant="ghost"
          aria-label={`Get directions to ${appointment.clinic.name}`}
        >
          <MapPinIcon className="h-4 w-4" aria-hidden="true" />
          <span className="sr-only">Get Directions</span>
        </Button>
      </div>
    </article>
  );
}
```

---

## Security Considerations

### HIPAA Compliance Requirements

#### Minimum Necessary Access Principle

Patients should only see data directly relevant to their care:

| Data Type | Patient Access | Notes |
|-----------|----------------|-------|
| Appointment date/time | ✅ Full | Own appointments only |
| Clinic information | ✅ Full | Public information |
| Provider name | ✅ Full | For scheduled appointments |
| Appointment type | ✅ Generic | "Follow-up" not detailed reason |
| Clinical notes | ❌ None | Provider-only |
| Diagnosis codes | ❌ None | Requires clinical context |
| Lab results | ⚠️ Phase 3 | If provider releases |
| Prescription info | ❌ None | Pharmacy handles |
| Other patient data | ❌ None | HIPAA isolation |

#### Audit Logging Requirements

All patient portal access must be logged to [`patient_activity_log`](#4-patient-activity-log-hipaa-audit-trail):

```php
// Required audit events
class PatientAuditEvents
{
    // Authentication events
    const LOGIN_SUCCESS = 'patient_login_success';
    const LOGIN_FAILED = 'patient_login_failed';
    const LOGOUT = 'patient_logout';
    const SESSION_TIMEOUT = 'patient_session_timeout';
    const PASSWORD_RESET = 'patient_password_reset';
    const MFA_ENABLED = 'patient_mfa_enabled';
    
    // Data access events
    const VIEW_APPOINTMENTS = 'patient_view_appointments';
    const VIEW_APPOINTMENT_DETAIL = 'patient_view_appointment_detail';
    const VIEW_PROFILE = 'patient_view_profile';
    const UPDATE_PROFILE = 'patient_update_profile';
    const REQUEST_RESCHEDULE = 'patient_request_reschedule';
    
    // Notification preference events
    const VIEW_NOTIFICATION_PREFS = 'patient_view_notification_prefs';
    const UPDATE_NOTIFICATION_PREFS = 'patient_update_notification_prefs';
    const SMS_OPT_IN = 'patient_sms_opt_in';
    const SMS_OPT_OUT = 'patient_sms_opt_out';
}

// Audit log entry example
$auditService->logPatientActivity(
    accountId: $account['id'],
    patientId: $account['patient_id'],
    action: PatientAuditEvents::VIEW_APPOINTMENT_DETAIL,
    resourceType: 'appointment',
    resourceId: $appointmentId,
    details: ['ip' => $clientIp, 'user_agent' => $userAgent]
);
```

#### Encryption Requirements

| Data | At Rest | In Transit | Method |
|------|---------|------------|--------|
| Passwords | ✅ | ✅ | bcrypt (cost 12) |
| Session tokens | ✅ (hashed) | ✅ | SHA-256 hash stored |
| MFA secrets | ✅ | ✅ | AES-256-GCM |
| Patient data | ✅ | ✅ | Database TDE + TLS 1.3 |
| SMS content | N/A | ✅ | Twilio TLS |

#### BAA Requirements with SMS Provider

**Twilio Business Associate Agreement:**
- Twilio offers HIPAA-eligible services with BAA
- **[BUSINESS DECISION]** Confirm BAA is in place with Twilio
- SMS messages should NOT contain PHI beyond appointment time/location
- No diagnosis, treatment, or clinical information in SMS

```
// ACCEPTABLE SMS content:
"SafeShift Reminder: You have an appointment tomorrow at 10:30 AM
at Downtown Clinic. Reply STOP to opt out."

// NOT ACCEPTABLE SMS content:
"Reminder: Your follow-up for hypertension treatment is tomorrow..."
```

### Authentication Security

#### Rate Limiting

| Endpoint | Limit | Window | Lockout |
|----------|-------|--------|---------|
| `/auth/login` | 5 attempts | 15 minutes | 15 min lockout |
| `/auth/verify-mfa` | 5 attempts | 10 minutes | Account locked |
| `/auth/forgot-password` | 3 requests | 1 hour | Rate limited |
| `/auth/reset-password` | 3 attempts | 1 hour | Token invalidated |
| API general | 60 requests | 1 minute | 429 response |

#### Account Lockout Policies

| Trigger | Action | Recovery |
|---------|--------|----------|
| 5 failed logins | 15-minute lockout | Wait or admin unlock |
| 5 failed MFA | Account locked | Contact clinic |
| Suspicious activity | Account flagged | Security review |
| Multiple sessions | Older sessions invalidated | Re-authenticate |

#### Session Timeout Comparison

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    SESSION TIMEOUT COMPARISON                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  PATIENT PORTAL (More Restrictive)                                          │
│  ─────────────────────────────────                                          │
│                                                                             │
│  0 min         15 min         30 min         45 min         60 min         │
│    │             │              │              │              │             │
│    ├─────────────┼──────────────┤                                          │
│    │    IDLE     │     MAX      │                                          │
│    │   TIMEOUT   │   SESSION    │                                          │
│    │   15 min    │   30 min     │                                          │
│                                                                             │
│  Rationale: Patients may use shared devices, public computers              │
│                                                                             │
│  ─────────────────────────────────────────────────────────────────────     │
│                                                                             │
│  PROVIDER PORTAL (Less Restrictive)                                         │
│  ─────────────────────────────────                                          │
│                                                                             │
│  0 min         15 min         30 min         45 min         60 min         │
│    │             │              │              │              │             │
│    ├─────────────┼──────────────┼──────────────┼──────────────┤             │
│    │         CONFIGURABLE IDLE TIMEOUT         │     MAX      │             │
│    │            5 - 60 minutes                 │   SESSION    │             │
│    │                                           │   60 min     │             │
│                                                                             │
│  Rationale: Providers in secure clinical environments, workflow needs      │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Integration Points

### With Main EHR System

The Patient Portal has **read-only access** to specific EHR data:

| Table | Access Level | Purpose |
|-------|--------------|---------|
| Patient | Read (own record) | Profile data |
| Appointment | Read (own records) | Appointment viewing |
| Clinic | Read | Clinic information |
| user (providers) | Read (name only) | Provider name display |
| Encounter | **No Access** | Clinical data protected |

### With SMS Service (Existing Twilio Integration)

The patient portal leverages the existing SMS service at [`api/v1/sms/send-reminder.php`](../api/v1/sms/send-reminder.php):

```php
// Notification queue integration
class PatientNotificationQueue
{
    /**
     * Queue appointment reminder
     */
    public function queueReminder(string $patientId, string $appointmentId, string $type): void
    {
        // Check patient preferences first
        $prefs = $this->prefsRepo->findByPatientId($patientId);
        
        if (!$prefs['sms_enabled']) {
            return; // Patient opted out
        }
        
        // Check specific notification type preference
        if (!$this->isNotificationTypeEnabled($prefs, $type)) {
            return;
        }
        
        // Check quiet hours
        if ($this->isQuietHours($prefs)) {
            $this->queueForLater($patientId, $appointmentId, $type);
            return;
        }
        
        // Send immediately
        $this->smsService->sendReminder($patientId, $appointmentId, $type);
    }
}
```

---

## Implementation Phases

### Phase 1: Foundation (MVP)

**Goal:** Basic patient portal with authentication and appointment viewing

| Component | Description |
|-----------|-------------|
| Database Schema | Create all portal tables |
| Patient Auth Service | Login, registration, password reset |
| Session Management | Database-backed sessions |
| Appointment Viewing | Read-only appointment list and details |
| Audit Logging | HIPAA-compliant activity logging |
| Basic UI | Login, dashboard, appointment pages |

### Phase 2: Notifications

**Goal:** SMS notification system with patient preferences

| Component | Description |
|-----------|-------------|
| Notification Preferences | CRUD for SMS preferences |
| SMS Integration | Connect to existing Twilio service |
| Reminder Queue | Automated appointment reminders |
| Quiet Hours | Respect patient time preferences |
| Delivery Tracking | Track SMS delivery status |

### Phase 3: Enhanced Features

**Goal:** Additional patient-facing features

| Component | Description |
|-----------|-------------|
| Reschedule Requests | Request queue for rescheduling |
| Document Viewing | Consent forms, visit summaries |
| Profile Enhancements | Additional patient data updates |
| Multi-language Support | Spanish translation (if needed) |

---

## Technical Considerations

### **[BUSINESS DECISION]** Mobile App vs Responsive Web

| Approach | Recommendation |
|----------|----------------|
| **Responsive Web** | **Start here** - Single codebase, instant updates |
| PWA | Phase 2 upgrade for push notifications |
| Native App | Future if patient demand warrants |

### **[BUSINESS DECISION]** Integration with Existing Auth

| Approach | Recommendation |
|----------|----------------|
| **Separate Tables** | **Recommended** - Clear security boundary |
| Shared Infrastructure | More complex, higher risk |

---

## Security Checklist

### Pre-Launch Requirements

- [ ] Patient accounts in separate table from providers
- [ ] Bcrypt password hashing (cost 12)
- [ ] Password requirements enforced (10+ chars)
- [ ] Session tokens cryptographically random (256-bit)
- [ ] Session timeout enforced (15 min idle, 30 min max)
- [ ] Rate limiting on auth endpoints
- [ ] All traffic over TLS 1.3
- [ ] CSRF protection on state-changing requests
- [ ] Patient can only access own data
- [ ] No PHI in SMS messages
- [ ] All access logged to audit table
- [ ] BAA confirmed with Twilio

---

## Business Decisions Required

### Summary of Decisions Needed

| Decision | Options | Recommendation |
|----------|---------|----------------|
| MFA Requirement | Required vs Optional | Optional, encouraged |
| MFA Methods | SMS/Email/TOTP | SMS + Email |
| Session Timeout | 10-30 min | 15 min idle, 30 max |
| Default SMS Opt-in | Opt-in vs Opt-out | Explicit opt-in (TCPA) |
| Mobile App | Web vs PWA vs Native | Responsive web first |
| Multi-language | Which languages | English, Spanish Phase 3 |
| Twilio BAA | Confirm in place | Required for HIPAA |
| Portal URL | Subdomain vs path | safeshift.com/patient |

---

## Related Documentation

- [Session Management Architecture](./SESSION_MANAGEMENT_ARCHITECTURE.md)
- [HIPAA Compliance](./HIPAA_COMPLIANCE.md)
- [Security Documentation](./SECURITY.md)
- [API Documentation](./API.md)

---

## Appendix: Glossary

| Term | Definition |
|------|------------|
| BAA | Business Associate Agreement - HIPAA requirement for vendors |
| MFA | Multi-Factor Authentication |
| OTP | One-Time Password |
| PHI | Protected Health Information |
| TCPA | Telephone Consumer Protection Act |
| WCAG | Web Content Accessibility Guidelines |