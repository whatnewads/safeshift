# SafeShift EHR Comprehensive Testing Checklist

## Document Information

| Property | Value |
|----------|-------|
| **Version** | 1.0 |
| **Created** | December 28, 2025 |
| **Last Updated** | December 28, 2025 |
| **Status** | Ready for QA |
| **Project** | SafeShift EHR Comprehensive Development |

---

## Table of Contents

1. [Pre-Testing Setup](#1-pre-testing-setup)
2. [Task 1: Session Management](#2-task-1-session-management)
3. [Task 2: Clinical Dashboard Updates](#3-task-2-clinical-dashboard-updates)
4. [Task 3: Admin Dashboard Updates](#4-task-3-admin-dashboard-updates)
5. [Task 4: EHR Form Updates](#5-task-4-ehr-form-updates)
6. [Task 5: Disposition Tab Enhancements](#6-task-5-disposition-tab-enhancements)
7. [Task 6: Email Notification System](#7-task-6-email-notification-system)
8. [Task 7: Terms & Conditions / Disclosures](#8-task-7-terms--conditions--disclosures)
9. [Task 8: OSHA ITA Integration](#9-task-8-osha-ita-integration)
10. [Task 9: Database & API Integration](#10-task-9-database--api-integration)
11. [Integration Tests](#11-integration-tests)
12. [Security Tests](#12-security-tests)
13. [Accessibility Tests](#13-accessibility-tests)
14. [Mobile/Responsive Tests](#14-mobileresponsive-tests)
15. [Performance Tests](#15-performance-tests)
16. [Error Handling Tests](#16-error-handling-tests)
17. [Test Sign-Off](#17-test-sign-off)

---

## 1. Pre-Testing Setup

### 1.1 Database Migrations

Run the following migrations before testing:

| Migration | Command | Status |
|-----------|---------|--------|
| Session Management Tables | `php database/run_session_management_migration.php` | [ ] |
| Clinic Email Recipients | `php database/migrations/clinic_email_recipients.sql` | [ ] |
| Encounter Disclosures | `php database/migrations/encounter_disclosures.sql` | [ ] |
| SMS Logs | `php database/migrations/sms_logs.sql` | [ ] |
| Appointment Documents | `php database/migrations/appointment_documents.sql` | [ ] |
| Email Logs | `php database/migrations/email_logs.sql` | [ ] |
| Complete Schema | `php database/migrations/safeshift_complete_schema_final.sql` | [ ] |

### 1.2 Environment Configuration

Verify the following environment variables are set:

```bash
# Database
DB_HOST=localhost
DB_NAME=safeshift_ehr
DB_USER=<your_db_user>
DB_PASS=<your_db_password>

# Session Configuration
SESSION_LIFETIME=3600
SESSION_IDLE_TIMEOUT=900
SESSION_REGEN_INTERVAL=300

# Email Configuration (for Task 6)
SMTP_HOST=<smtp_host>
SMTP_PORT=587
SMTP_USER=<smtp_user>
SMTP_PASS=<smtp_password>
SMTP_FROM=noreply@safeshift.com

# Twilio SMS Configuration (for Task 5)
TWILIO_ACCOUNT_SID=<twilio_sid>
TWILIO_AUTH_TOKEN=<twilio_token>
TWILIO_PHONE_NUMBER=<twilio_phone>
```

### 1.3 Test User Accounts

| Username | Role | UI Role | Password | Purpose |
|----------|------|---------|----------|---------|
| `admin` | Admin | super-admin | `AdminPass123!` | Full system access testing |
| `tadmin` | tadmin | admin | `TAdminPass123!` | Admin dashboard testing |
| `cadmin` | cadmin | admin | `CAdminPass123!` | Clinic admin testing |
| `provider1` | pclinician | provider | `ProviderPass123!` | Clinical provider testing |
| `tech1` | dclinician | technician | `TechPass123!` | DOT technician testing |
| `intake1` | 1clinician | registration | `IntakePass123!` | Registration testing |
| `qa1` | QA | qa | `QAPass123!` | QA review testing |
| `manager1` | Manager | manager | `ManagerPass123!` | Manager testing |

### 1.4 Required Test Data

Before testing, ensure the following data exists:

- [ ] At least 3 clinics in the system
- [ ] At least 10 patients with various demographics
- [ ] At least 5 active encounters (different statuses)
- [ ] At least 2 work-related encounters
- [ ] Configured email recipients for at least 1 clinic
- [ ] At least 1 appointment with a patient phone number

---

## 2. Task 1: Session Management

### 2.1 Session Timeout Configuration

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| ST-001 | Session expires after max timeout | User logged in | 1. Login as any user 2. Leave idle for 60+ minutes without activity | User is logged out and redirected to login page | [ ] | |
| ST-002 | Activity ping extends session | User logged in | 1. Login 2. Interact with app (click, scroll, type) 3. Check session status via API | Session remains active, `last_activity` timestamp updated | [ ] | |
| ST-003 | Configurable timeout range | Logged in as any user | 1. Navigate to Settings 2. Attempt to set timeout < 5 min 3. Attempt to set timeout > 60 min | System rejects values outside 5-60 minute range | [ ] | |
| ST-004 | Default timeout is 15 minutes | New user account | 1. Create new user 2. Check user_preferences table | `session_timeout_minutes` defaults to 15 | [ ] | |
| ST-005 | Session timeout respects user preference | User with custom timeout set | 1. Set timeout to 30 minutes 2. Leave idle for 25 minutes 3. Check session status | Session remains active | [ ] | |
| ST-006 | Session timeout enforces user preference | User with custom timeout set | 1. Set timeout to 10 minutes 2. Leave idle for 15 minutes | Session expires, user logged out | [ ] | |

### 2.2 Session Warning Modal

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SW-001 | Warning appears 2 min before timeout | User logged in with 10-min timeout | 1. Login 2. Wait 8 minutes without activity | Warning modal appears with countdown timer | [ ] | |
| SW-002 | Extend Session button works | Warning modal displayed | 1. Click Extend Session button | Modal closes, session extended, activity ping sent | [ ] | |
| SW-003 | Logout Now button works | Warning modal displayed | 1. Click Logout Now button | User immediately logged out, redirected to login | [ ] | |
| SW-004 | Countdown timer displays correctly | Warning modal displayed | 1. Observe countdown | Timer shows MM:SS format, counts down in real-time | [ ] | |
| SW-005 | Modal auto-closes if session expires | Warning modal displayed | 1. Let countdown reach 0:00 | Modal closes, user logged out, redirected to login | [ ] | |

### 2.3 Session Settings UI

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SS-001 | Settings page displays timeout options | User logged in | 1. Navigate to Settings/Profile 2. Find Session section | Dropdown with 5, 10, 15, 30, 45, 60 minute options visible | [ ] | |
| SS-002 | Current timeout preference displayed | User with custom timeout | 1. Navigate to Settings | Dropdown shows currently saved preference | [ ] | |
| SS-003 | Saving timeout preference works | User logged in | 1. Select new timeout value 2. Save 3. Refresh page | New value persists after refresh | [ ] | |
| SS-004 | Max session note displayed | User viewing settings | 1. View session settings | Note explains max 60-min duration regardless of idle setting | [ ] | |

### 2.4 Active Sessions Management

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| AS-001 | Active sessions list displayed | User logged in from 2+ devices | 1. Navigate to Session Settings | List shows all active sessions with device info, IP, last activity | [ ] | |
| AS-002 | Current session marked | User viewing sessions list | 1. View active sessions | Current session has Current badge/indicator | [ ] | |
| AS-003 | Logout specific session | Multiple sessions active | 1. Click Logout on non-current session | That session terminated, removed from list | [ ] | |
| AS-004 | Logout other sessions works | Multiple sessions active | 1. Click Logout Other Sessions 2. Confirm action | All sessions except current terminated | [ ] | |
| AS-005 | Logout everywhere works | User logged in | 1. Click Logout Everywhere 2. Confirm action | All sessions including current terminated, redirected to login | [ ] | |
| AS-006 | Session list refreshes | Viewing sessions | 1. Login from another device 2. Refresh session list | New session appears in list | [ ] | |

---

## 3. Task 2: Clinical Dashboard Updates

### 3.1 Widget Removal

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| WR-001 | Active Encounters Pending Order widget removed | Logged in as provider | 1. Navigate to Clinical Dashboard | Widget for Active Encounters Pending Order is NOT present | [ ] | |
| WR-002 | Pending QA Reviews widget removed | Logged in as provider | 1. Navigate to Clinical Dashboard | Widget for Pending QA Reviews is NOT present | [ ] | |
| WR-003 | Other widgets unaffected | Logged in as provider | 1. Navigate to Clinical Dashboard | All other expected widgets display correctly | [ ] | |

### 3.2 View All Active Encounters Button

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| VE-001 | Button is visible | Logged in as provider | 1. Navigate to Clinical Dashboard | View All Active Encounters button is visible | [ ] | |
| VE-002 | Button navigates when route exists | Encounters list page exists | 1. Click View All Active Encounters | User navigated to active encounters list page | [ ] | |
| VE-003 | Flash message when route unavailable | Route not configured | 1. Click View All Active Encounters | Flash message displays feature under development | [ ] | |

### 3.3 Wi-Fi Status Indicator

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| WF-001 | Connected status displays | Network connected | 1. View dashboard header/status bar | Green Wi-Fi icon with Connected status | [ ] | |
| WF-002 | Disconnected status displays | Network disconnected | 1. Disable network 2. Observe status | Red Wi-Fi icon with Disconnected status | [ ] | |
| WF-003 | Reconnecting status with animation | Network reconnecting | 1. Restore network connection | Blinking/pulsing Wi-Fi icon during reconnection | [ ] | |
| WF-004 | Hover shows expanded text | Connected state | 1. Hover over Wi-Fi indicator | Tooltip shows Network Status: Connected - All systems operational or similar | [ ] | |
| WF-005 | Click to reconnect works | Disconnected state | 1. Click on disconnected Wi-Fi indicator | System attempts reconnection, status updates | [ ] | |
| WF-006 | Status persists across navigation | Any connection state | 1. Note Wi-Fi status 2. Navigate to different page | Wi-Fi status indicator remains consistent | [ ] | |

### 3.4 Dynamic Notifications Badge

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| NB-001 | Badge shows notification count | Notifications exist | 1. View notification bell icon | Badge displays count of unread notifications | [ ] | |
| NB-002 | Badge hidden when count is 0 | No notifications | 1. Mark all notifications read 2. View bell icon | Badge is hidden, only bell icon visible | [ ] | |
| NB-003 | Badge caps at 99+ | 100+ notifications | 1. Generate 100+ notifications 2. View badge | Badge displays 99+ | [ ] | |
| NB-004 | Badge updates dynamically | Viewing dashboard | 1. Receive new notification | Badge count increments without page refresh | [ ] | |
| NB-005 | Badge decrements on read | Unread notifications exist | 1. Click notification to read | Badge count decrements | [ ] | |

---

## 4. Task 3: Admin Dashboard Updates

### 4.1 Tab Styling

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| TS-001 | Tabs are more rectangular | Admin dashboard | 1. Navigate to Admin Dashboard 2. Observe tab styling | Tabs have rectangular shape, not rounded pills | [ ] | |
| TS-002 | Active tab has better contrast | Admin dashboard | 1. Click different tabs 2. Observe active tab | Active tab clearly distinguishable with high contrast | [ ] | |
| TS-003 | Tab hover state visible | Admin dashboard | 1. Hover over inactive tabs | Hover state provides visual feedback | [ ] | |
| TS-004 | Tab focus indicator accessible | Using keyboard | 1. Tab through navigation | Focus indicator clearly visible on tabs | [ ] | |

### 4.2 Case Management Actions Dropdown

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| CM-001 | Actions dropdown visible | Admin dashboard, cases tab | 1. View case management area | Actions dropdown button visible | [ ] | |
| CM-002 | Dropdown opens on click | Admin dashboard | 1. Click Actions dropdown | Menu opens with available actions | [ ] | |
| CM-003 | Dropdown closes on click outside | Dropdown open | 1. Click outside dropdown | Dropdown closes | [ ] | |
| CM-004 | Actions execute correctly | Dropdown open | 1. Select an action | Selected action executes as expected | [ ] | |

### 4.3 Email Settings Modal

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| EM-001 | Email Settings button accessible | Logged in as admin | 1. Navigate to Admin Dashboard 2. Find Email Settings | Email Settings button/link visible | [ ] | |
| EM-002 | Modal opens with clinic dropdown | Click Email Settings | 1. Click Email Settings button | Modal opens with clinic selection dropdown | [ ] | |
| EM-003 | Clinic dropdown populated | Modal open | 1. View clinic dropdown | All clinics the admin has access to are listed | [ ] | |
| EM-004 | Selecting clinic loads recipients | Clinic selected | 1. Select a clinic | Email recipients for that clinic are displayed | [ ] | |
| EM-005 | Empty state for no recipients | Clinic with no recipients | 1. Select clinic with no recipients | Message indicates no recipients configured | [ ] | |

### 4.4 Email Recipients CRUD

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| ER-001 | Add recipient button visible | Email modal open | 1. View email recipients section | Add Recipient or + button visible | [ ] | |
| ER-002 | Add recipient with valid email | Modal open | 1. Click Add 2. Enter valid email 3. Save | Recipient added, appears in list | [ ] | |
| ER-003 | Add recipient validates email | Modal open | 1. Click Add 2. Enter invalid email 3. Save | Validation error shown, recipient not added | [ ] | |
| ER-004 | Edit recipient email | Recipients exist | 1. Click Edit on recipient 2. Change email 3. Save | Email updated in list | [ ] | |
| ER-005 | Remove recipient with confirmation | Recipients exist | 1. Click Remove 2. Confirm removal | Recipient removed from list | [ ] | |
| ER-006 | Cancel remove does not delete | Remove confirmation shown | 1. Click Cancel on confirmation | Recipient remains in list | [ ] | |
| ER-007 | Duplicate email prevented | Recipient exists | 1. Try to add same email address | Error message prevents duplicate | [ ] | |

---

## 5. Task 4: EHR Form Updates

### 5.1 Color Mode Removal

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| CR-001 | Protanopia mode removed | Any EHR form | 1. Open encounter form 2. Search for color/accessibility settings | Protanopia display mode option NOT present | [ ] | |
| CR-002 | Tritanopia mode removed | Any EHR form | 1. Open encounter form 2. Search for color/accessibility settings | Tritanopia display mode option NOT present | [ ] | |
| CR-003 | Default color scheme works | Any EHR form | 1. View form with default settings | Form displays correctly without color mode options | [ ] | |

### 5.2 Nature of Incident Other Field

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| NI-001 | Other option available | EHR form Nature of Incident | 1. Click Nature of Incident dropdown | Other option is in the dropdown list | [ ] | |
| NI-002 | Text input appears on Other | Nature of Incident field | 1. Select Other from dropdown | Additional text input field appears | [ ] | |
| NI-003 | Other text input required | Other selected | 1. Select Other 2. Leave text blank 3. Try to save | Validation requires text input when Other selected | [ ] | |
| NI-004 | Other text saves correctly | Other with text | 1. Select Other 2. Enter description 3. Save | Other text saved and retrievable | [ ] | |
| NI-005 | Text input hidden for other values | Other selected, text entered | 1. Change selection from Other to specific value | Text input field hides | [ ] | |

### 5.3 Mass Casualty Default

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| MC-001 | Mass Casualty defaults to NO | New encounter | 1. Create new encounter 2. View Mass Casualty field | Mass Casualty field pre-selected as NO | [ ] | |
| MC-002 | Mass Casualty can be changed to YES | New encounter | 1. Change Mass Casualty to YES 2. Save | YES value saves correctly | [ ] | |
| MC-003 | Default applies to all encounter types | Various encounter types | 1. Create encounters of different types | All default to Mass Casualty = NO | [ ] | |

### 5.4 Injury/Illness Classification

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| IC-001 | Classification dropdown present | EHR form | 1. Open encounter form | Injury/Illness Classification dropdown visible | [ ] | |
| IC-002 | Personal option available | Classification dropdown | 1. Click dropdown | Personal option is available | [ ] | |
| IC-003 | Work Related option available | Classification dropdown | 1. Click dropdown | Work Related option is available | [ ] | |
| IC-004 | Selection saves correctly | Encounter form | 1. Select Personal or Work Related 2. Save | Selection persists after save | [ ] | |
| IC-005 | Work Related triggers workflows | Encounter form | 1. Select Work Related 2. Save | Appropriate work-related workflows trigger (email, disclosures) | [ ] | |

### 5.5 Required Fields Validation

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| RF-001 | All spec-required fields validated | New encounter | 1. Try to save with empty required fields | Validation errors for all required fields | [ ] | |
| RF-002 | Required field indicators visible | EHR form | 1. View form | Required fields have asterisk or other indicator | [ ] | |
| RF-003 | Validation messages are clear | Missing required fields | 1. Submit incomplete form | Each error message identifies specific field | [ ] | |
| RF-004 | Form submits with all required | All required filled | 1. Fill all required fields 2. Submit | Form saves successfully | [ ] | |

### 5.6 Auto-fill from Shift

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| AF-001 | Auto-fill button visible | Encounter form, active shift | 1. Open encounter during active shift | Auto-fill from Shift button visible | [ ] | |
| AF-002 | Button populates shift data | Click auto-fill | 1. Click Auto-fill from Shift | Relevant fields populate with shift data | [ ] | |
| AF-003 | Auto-fill does not overwrite | Some fields already filled | 1. Fill some fields 2. Click auto-fill | Existing data not overwritten, only empty fields filled | [ ] | |
| AF-004 | Button disabled if no shift | No active shift | 1. View form without active shift | Auto-fill button disabled or hidden | [ ] | |

### 5.7 Placeholder Text Removal

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| PT-001 | No placeholder text in fields | EHR form | 1. View all form fields | No placeholder text inside input fields | [ ] | |
| PT-002 | Labels still present | EHR form | 1. View form fields | Field labels are visible above/beside fields | [ ] | |
| PT-003 | Helper text separate if needed | Complex fields | 1. View complex fields | Any helper text is below field, not as placeholder | [ ] | |

---

## 6. Task 5: Disposition Tab Enhancements

### 6.1 SMS Reminder Feature

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SMS-001 | Send SMS button visible | Appointment with patient phone | 1. Open Disposition tab 2. View appointment | Send SMS Reminder button visible | [ ] | |
| SMS-002 | SMS sends successfully | Valid phone number | 1. Click Send SMS 2. Confirm | SMS delivered, success message shown | [ ] | |
| SMS-003 | SMS logged in system | SMS sent | 1. Send SMS 2. Check sms_logs table | SMS delivery logged with status | [ ] | |
| SMS-004 | Invalid phone handled | Invalid phone format | 1. Attempt SMS to invalid number | Error message shown, SMS not sent | [ ] | |
| SMS-005 | No phone number handled | Patient without phone | 1. View appointment | Send SMS button disabled/hidden with reason | [ ] | |
| SMS-006 | SMS content is HIPAA safe | SMS sent | 1. Check SMS content | No PHI in message, only time/location | [ ] | |

### 6.2 Photo Upload for Appointments

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| PU-001 | Upload button visible | Disposition tab | 1. View appointment details | Photo upload button/area visible | [ ] | |
| PU-002 | Upload accepts images | Upload interface | 1. Click upload 2. Select image file | Image uploads successfully | [ ] | |
| PU-003 | Rejects non-image files | Upload interface | 1. Try to upload PDF/doc | Upload rejected with error message | [ ] | |
| PU-004 | File size limit enforced | Large file selected | 1. Try to upload >10MB image | Upload rejected with size error | [ ] | |
| PU-005 | Uploaded image displays | Image uploaded | 1. Upload image 2. Refresh | Image thumbnail visible in appointment | [ ] | |
| PU-006 | Multiple images supported | Upload interface | 1. Upload multiple images | All images save and display | [ ] | |
| PU-007 | Delete uploaded image | Images exist | 1. Click delete on image 2. Confirm | Image removed from appointment | [ ] | |

### 6.3 Patient Portal Architecture (Documentation)

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| PP-001 | Architecture document exists | File system | 1. Check docs/PATIENT_PORTAL_ARCHITECTURE.md | Document exists and is comprehensive | [ ] | |
| PP-002 | Database schema documented | Architecture doc | 1. Review database section | All patient portal tables documented | [ ] | |
| PP-003 | API endpoints documented | Architecture doc | 1. Review API section | All patient portal endpoints specified | [ ] | |
| PP-004 | Security considerations documented | Architecture doc | 1. Review security section | HIPAA, authentication, authorization covered | [ ] | |

---

## 7. Task 6: Email Notification System

### 7.1 Work-Related Report Email Trigger

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| EN-001 | Email triggers on work-related | Clinic with email recipients | 1. Create encounter 2. Mark as Work Related 3. Complete/sign | Email sent to configured recipients | [ ] | |
| EN-002 | Email NOT sent for personal | Clinic with email recipients | 1. Create encounter 2. Mark as Personal 3. Complete | No email triggered | [ ] | |
| EN-003 | Email sent to all recipients | Multiple recipients configured | 1. Complete work-related encounter | All configured recipients receive email | [ ] | |
| EN-004 | No email if no recipients | Clinic with no recipients | 1. Complete work-related encounter | No error, logged as no recipients | [ ] | |

### 7.2 Email Content - HIPAA Safe

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| EC-001 | Email contains no PHI | Work-related email sent | 1. Review sent email content | No patient name, DOB, SSN, or diagnosis in email | [ ] | |
| EC-002 | Email identifies clinic | Work-related email | 1. Review email | Clinic name included | [ ] | |
| EC-003 | Email has generic notification | Work-related email | 1. Review email | Message indicates new work-related report filed | [ ] | |
| EC-004 | Email has link to portal | Work-related email | 1. Review email | Secure link to view details in EHR system | [ ] | |
| EC-005 | Email logged in system | Email sent | 1. Check email_logs table | Email delivery logged with recipient, timestamp, status | [ ] | |

### 7.3 Email Delivery

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| ED-001 | SMTP delivery works | Valid SMTP config | 1. Trigger email | Email delivered via SMTP | [ ] | |
| ED-002 | Failed delivery logged | Invalid recipient | 1. Configure invalid recipient 2. Trigger email | Failure logged, error handled gracefully | [ ] | |
| ED-003 | Retry on transient failure | SMTP timeout | 1. Simulate timeout | System retries or queues for retry | [ ] | |

---

## 8. Task 7: Terms & Conditions / Disclosures

### 8.1 Standard Disclosure

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SD-001 | Standard disclosure displays | Any encounter | 1. Create new encounter 2. Reach disclosure step | Standard disclosure text displayed | [ ] | |
| SD-002 | Checkbox for acknowledgment | Disclosure displayed | 1. View disclosure | Checkbox to acknowledge is present | [ ] | |
| SD-003 | Checkbox starts unchecked | New encounter | 1. View disclosure section | Checkbox is unchecked by default | [ ] | |
| SD-004 | Disclosure text matches spec | Disclosure displayed | 1. Compare text to specification | Text matches approved disclosure content | [ ] | |

### 8.2 Work-Related Authorization

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| WA-001 | Work-related disclosure shows conditionally | Encounter marked Work Related | 1. Select Work Related classification | Additional authorization disclosure appears | [ ] | |
| WA-002 | Work-related disclosure hidden for personal | Encounter marked Personal | 1. Select Personal classification | Work-related authorization NOT shown | [ ] | |
| WA-003 | Separate checkbox for work authorization | Work-related selected | 1. View disclosures | Separate checkbox for work-related authorization | [ ] | |
| WA-004 | Authorization text matches spec | Work-related disclosure | 1. Compare to specification | Text matches approved authorization content | [ ] | |

### 8.3 Signature Validation

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SV-001 | Signature disabled until acknowledged | Disclosure shown, unchecked | 1. View signature area | Signature pad/button disabled | [ ] | |
| SV-002 | Signature enables after standard ack | Standard disclosure | 1. Check standard disclosure checkbox | Signature pad becomes enabled (if only disclosure) | [ ] | |
| SV-003 | Both checkboxes required for work-related | Work-related encounter | 1. Check only one checkbox | Signature remains disabled | [ ] | |
| SV-004 | Signature enables after all acks | Work-related encounter | 1. Check both disclosures | Signature pad becomes enabled | [ ] | |
| SV-005 | Cannot submit without signature | Disclosures acknowledged | 1. Try to submit without signing | Validation error requires signature | [ ] | |
| SV-006 | Signed encounter saves disclosures | Complete encounter | 1. Acknowledge and sign 2. Save | Disclosure acknowledgments saved to encounter_disclosures | [ ] | |

---

## 9. Task 8: OSHA ITA Integration

### 9.1 Architecture Documentation

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| OA-001 | Architecture document exists | File system | 1. Check docs/OSHA_ITA_INTEGRATION_ARCHITECTURE.md | Document exists and is comprehensive | [ ] | |
| OA-002 | Data mapping documented | Architecture doc | 1. Review data mapping section | All form 300/300A/301 fields mapped | [ ] | |
| OA-003 | Recordability rules documented | Architecture doc | 1. Review submission logic | Decision tree for OSHA recordability complete | [ ] | |
| OA-004 | CSV export specs documented | Architecture doc | 1. Review export section | CSV format specifications included | [ ] | |

### 9.2 Submission Logic Rules

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SL-001 | Work-related check documented | Architecture doc | 1. Review work-relatedness criteria | 29 CFR 1904.5 criteria documented | [ ] | |
| SL-002 | First aid vs medical treatment | Architecture doc | 1. Review recordability | First aid exceptions listed per 1904.7 | [ ] | |
| SL-003 | Immediate reporting rules | Architecture doc | 1. Review immediate reporting | 8-hour fatality, 24-hour hospitalization rules | [ ] | |
| SL-004 | Privacy case criteria | Architecture doc | 1. Review privacy cases | Sexual assault, HIV, mental illness, needlestick documented | [ ] | |
| SL-005 | Establishment size thresholds | Architecture doc | 1. Review size requirements | 10/20/250 employee thresholds documented | [ ] | |

---

## 10. Task 9: Database & API Integration

### 10.1 API Endpoint Verification

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| API-001 | Auth endpoints work | Server running | 1. Test POST /api/v1/auth/login | Returns session on valid credentials | [ ] | |
| API-002 | Patient endpoints work | Authenticated | 1. Test GET /api/v1/patients | Returns patient list | [ ] | |
| API-003 | Encounter endpoints work | Authenticated | 1. Test GET /api/v1/encounters | Returns encounter list | [ ] | |
| API-004 | Session endpoints work | Authenticated | 1. Test GET /api/v1/auth/active-sessions | Returns session list | [ ] | |
| API-005 | CSRF protection active | Authenticated | 1. POST without CSRF token | Returns 403/419 error | [ ] | |
| API-006 | Rate limiting works | Server running | 1. Make 10 rapid login attempts | Rate limit response after threshold | [ ] | |

### 10.2 Error Handling Standardization

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| EH-001 | 400 Bad Request format | API endpoint | 1. Send malformed request | Standardized error JSON returned | [ ] | |
| EH-002 | 401 Unauthorized format | No auth | 1. Access protected endpoint | Standardized 401 response | [ ] | |
| EH-003 | 403 Forbidden format | Wrong permissions | 1. Access restricted resource | Standardized 403 response | [ ] | |
| EH-004 | 404 Not Found format | API endpoint | 1. Request non-existent resource | Standardized 404 response | [ ] | |
| EH-005 | 422 Validation Error format | API endpoint | 1. Submit invalid data | Standardized validation errors with field names | [ ] | |
| EH-006 | 500 Server Error format | Simulated error | 1. Trigger server error | Standardized 500 response, no stack trace | [ ] | |

### 10.3 Database Migrations

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| DM-001 | Session tables exist | Run migrations | 1. Check user_sessions table | Table exists with correct schema | [ ] | |
| DM-002 | Preferences table exists | Run migrations | 1. Check user_preferences table | Table exists with correct schema | [ ] | |
| DM-003 | Email recipients table exists | Run migrations | 1. Check clinic_email_recipients table | Table exists with correct schema | [ ] | |
| DM-004 | Disclosures table exists | Run migrations | 1. Check encounter_disclosures table | Table exists with correct schema | [ ] | |
| DM-005 | SMS logs table exists | Run migrations | 1. Check sms_logs table | Table exists with correct schema | [ ] | |
| DM-006 | Foreign keys correct | All tables | 1. Check foreign key constraints | All FK relationships valid | [ ] | |

---

## 11. Integration Tests

### 11.1 End-to-End User Flows

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| E2E-001 | Complete login flow | Valid credentials | 1. Enter username/password 2. Submit 3. Complete 2FA if required | User logged in, redirected to dashboard | [ ] | |
| E2E-002 | Create patient to encounter | Logged in as provider | 1. Create patient 2. Create encounter 3. Complete encounter | Full workflow completes successfully | [ ] | |
| E2E-003 | Work-related encounter full flow | Provider logged in | 1. Create encounter 2. Mark work-related 3. Acknowledge disclosures 4. Sign 5. Complete | Email sent, disclosures saved, encounter completed | [ ] | |
| E2E-004 | Session timeout and re-login | User logged in | 1. Wait for timeout 2. Get logged out 3. Re-login | Seamless re-authentication, return to previous location | [ ] | |
| E2E-005 | Admin email config flow | Logged in as admin | 1. Open email settings 2. Select clinic 3. Add recipient 4. Save | Recipient saved and used for notifications | [ ] | |

### 11.2 Cross-Feature Interactions

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| CF-001 | Session + Notifications | User logged in | 1. Session warning appears 2. Click extend 3. New notification arrives | Both features work without conflict | [ ] | |
| CF-002 | Email + Disclosures | Work-related encounter | 1. Complete work-related 2. Acknowledge disclosures | Email only sent after proper acknowledgment | [ ] | |
| CF-003 | Dashboard + Wi-Fi + Notifications | Provider dashboard | 1. View dashboard 2. Check Wi-Fi status 3. Check notifications | All indicators update correctly | [ ] | |
| CF-004 | Form + Auto-fill + Validation | EHR form | 1. Auto-fill from shift 2. Edit fields 3. Submit | Validation works on auto-filled and manual data | [ ] | |

---

## 12. Security Tests

### 12.1 Authentication

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SEC-001 | Invalid credentials rejected | Login page | 1. Enter wrong password | Login fails with generic error message | [ ] | |
| SEC-002 | Account lockout after failures | Login page | 1. Fail login 5 times | Account locked, wait time required | [ ] | |
| SEC-003 | Session token not guessable | Logged in | 1. Inspect session token | Token is cryptographically random | [ ] | |
| SEC-004 | Password hashing secure | Database | 1. Check stored password | bcrypt with cost 12+ | [ ] | |
| SEC-005 | 2FA required for sensitive roles | Admin login | 1. Login as admin | 2FA verification required | [ ] | |

### 12.2 Authorization

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SEC-006 | Role-based access enforced | Provider logged in | 1. Try to access admin endpoints | Access denied | [ ] | |
| SEC-007 | Clinic isolation enforced | Provider at Clinic A | 1. Try to access Clinic B patient | Access denied | [ ] | |
| SEC-008 | Permission checks on all endpoints | Various endpoints | 1. Test each endpoint with wrong role | All return 403 appropriately | [ ] | |

### 12.3 HIPAA Compliance

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SEC-009 | Audit logging captures access | Any PHI access | 1. View patient record 2. Check audit log | Access logged with user, time, action | [ ] | |
| SEC-010 | PHI not in URLs | Navigate app | 1. Check all URLs | No SSN, DOB, or patient names in URLs | [ ] | |
| SEC-011 | PHI not in error messages | Trigger errors | 1. Cause validation errors | Error messages don't expose PHI | [ ] | |
| SEC-012 | Session timeout HIPAA compliant | Active session | 1. Leave idle 60+ minutes | Auto-logout occurs | [ ] | |
| SEC-013 | Data encrypted in transit | All requests | 1. Verify HTTPS | All API calls over TLS 1.2+ | [ ] | |

### 12.4 CSRF and XSS Prevention

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| SEC-014 | CSRF token required | Authenticated | 1. POST without CSRF token | Request rejected | [ ] | |
| SEC-015 | XSS in input prevented | Form input | 1. Enter `<script>alert(1)</script>` 2. Save 3. View | Script not executed, properly escaped | [ ] | |
| SEC-016 | SQL injection prevented | Search field | 1. Enter `'; DROP TABLE--` | No SQL error, normal response | [ ] | |

---

## 13. Accessibility Tests

### 13.1 Keyboard Navigation

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| A11Y-001 | Tab order logical | Any page | 1. Tab through page elements | Focus moves in logical order | [ ] | |
| A11Y-002 | Focus indicator visible | Any interactive element | 1. Tab to element | Clear focus ring visible | [ ] | |
| A11Y-003 | Modals trap focus | Modal open | 1. Tab through modal | Focus stays within modal | [ ] | |
| A11Y-004 | Escape closes modals | Modal open | 1. Press Escape | Modal closes | [ ] | |
| A11Y-005 | Enter activates buttons | Button focused | 1. Focus button 2. Press Enter | Button action triggered | [ ] | |

### 13.2 Screen Reader Compatibility

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| A11Y-006 | Page headings structured | Any page | 1. Check heading hierarchy | h1 > h2 > h3 properly nested | [ ] | |
| A11Y-007 | Images have alt text | Pages with images | 1. Check all images | All images have descriptive alt text | [ ] | |
| A11Y-008 | Form labels associated | Any form | 1. Check label-input association | Labels programmatically linked to inputs | [ ] | |
| A11Y-009 | Error messages announced | Form validation | 1. Trigger validation error | Error read by screen reader | [ ] | |
| A11Y-010 | ARIA landmarks present | Any page | 1. Check main, nav, banner roles | Page regions properly marked | [ ] | |

### 13.3 Color Contrast

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| A11Y-011 | Text contrast 4.5:1 minimum | All text | 1. Use contrast checker | All text meets WCAG AA | [ ] | |
| A11Y-012 | Interactive element contrast | Buttons, links | 1. Check contrast | Meets 3:1 minimum | [ ] | |
| A11Y-013 | Error states not color-only | Form errors | 1. Trigger error | Icon or text accompanies color | [ ] | |

---

## 14. Mobile/Responsive Tests

### 14.1 Tablet View (768px - 1024px)

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| MOB-001 | Layout adapts to tablet | Tablet viewport | 1. View dashboard at 768px | Layout readable, no horizontal scroll | [ ] | |
| MOB-002 | Navigation accessible | Tablet viewport | 1. Access navigation | Nav menu usable (hamburger or visible) | [ ] | |
| MOB-003 | Forms usable | Tablet viewport | 1. Fill out EHR form | All fields accessible and usable | [ ] | |
| MOB-004 | Tables responsive | Tablet viewport | 1. View patient list | Table scrolls or adapts | [ ] | |

### 14.2 Mobile View (< 768px)

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| MOB-005 | Layout adapts to mobile | Mobile viewport | 1. View at 375px | Single column layout, no overflow | [ ] | |
| MOB-006 | Touch targets adequate | Mobile viewport | 1. Check button/link sizes | Minimum 44x44px touch targets | [ ] | |
| MOB-007 | Modals fit screen | Mobile viewport | 1. Open session warning modal | Modal fits within viewport | [ ] | |
| MOB-008 | Input fields accessible | Mobile viewport | 1. Tap form fields | Keyboard appears, field visible | [ ] | |

### 14.3 Touch Interactions

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| MOB-009 | Dropdowns touch-friendly | Touch device | 1. Tap dropdown | Options display, selectable | [ ] | |
| MOB-010 | Signature pad works | Touch device | 1. Draw signature with finger | Signature captures correctly | [ ] | |
| MOB-011 | Swipe gestures (if any) | Touch device | 1. Test any swipe features | Gestures work correctly | [ ] | |

---

## 15. Performance Tests

### 15.1 Page Load Times

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| PERF-001 | Login page < 2s | Fresh browser | 1. Navigate to login | Page interactive within 2 seconds | [ ] | |
| PERF-002 | Dashboard < 3s | Logged in | 1. Navigate to dashboard | Page loads within 3 seconds | [ ] | |
| PERF-003 | Patient list < 2s | 100 patients | 1. Load patient list | List renders within 2 seconds | [ ] | |
| PERF-004 | EHR form < 2s | Click new encounter | 1. Open encounter form | Form ready within 2 seconds | [ ] | |

### 15.2 API Response Times

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| PERF-005 | GET endpoints < 500ms | Normal load | 1. Time GET /api/v1/patients | Response within 500ms | [ ] | |
| PERF-006 | POST endpoints < 1s | Normal load | 1. Time POST /api/v1/encounters | Response within 1 second | [ ] | |
| PERF-007 | Search < 500ms | 1000+ patients | 1. Search patients | Results within 500ms | [ ] | |

### 15.3 Session Handling Under Load

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| PERF-008 | Multiple concurrent sessions | Load test tool | 1. Simulate 50 concurrent users | All sessions maintain correctly | [ ] | |
| PERF-009 | Activity ping under load | Multiple sessions | 1. All sessions send pings | Pings processed without timeout | [ ] | |
| PERF-010 | Session validation performance | 100 validations/min | 1. Benchmark validation | Each validation < 50ms | [ ] | |

---

## 16. Error Handling Tests

### 16.1 Invalid Input Handling

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| ERR-001 | Invalid email format | Email field | 1. Enter `notanemail` 2. Submit | Validation error shown | [ ] | |
| ERR-002 | Invalid phone format | Phone field | 1. Enter `123` 2. Submit | Validation error shown | [ ] | |
| ERR-003 | Invalid date format | Date field | 1. Enter `13/45/2025` | Validation error or prevented | [ ] | |
| ERR-004 | XSS attempt handled | Text field | 1. Enter script tag 2. Submit | Input sanitized, no XSS | [ ] | |
| ERR-005 | SQL injection handled | Search field | 1. Enter SQL 2. Submit | Input sanitized, no error | [ ] | |
| ERR-006 | Oversized input handled | Text area | 1. Paste 100KB of text | Truncated or rejected gracefully | [ ] | |

### 16.2 Network Failure Handling

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| ERR-007 | API timeout handled | Slow network | 1. Trigger slow request | Loading indicator, then error message | [ ] | |
| ERR-008 | Connection lost notification | Active session | 1. Disconnect network | User notified of connection issue | [ ] | |
| ERR-009 | Reconnection automatic | Network restored | 1. Restore network | System reconnects, resumes operation | [ ] | |
| ERR-010 | Unsaved data warning | Form with changes | 1. Lose connection while editing | User warned, data preserved locally | [ ] | |

### 16.3 Session Expiry Handling

| ID | Test Case | Pre-conditions | Test Steps | Expected Result | Pass | Notes |
|----|-----------|----------------|------------|-----------------|------|-------|
| ERR-011 | Expired session redirect | Session expired | 1. Make API request with expired session | Redirect to login, message shown | [ ] | |
| ERR-012 | Form data preserved on expiry | Form with data, session expires | 1. Fill form 2. Session expires 3. Re-login | Option to restore form data | [ ] | |
| ERR-013 | Concurrent tab handling | Multiple tabs | 1. Logout in one tab | Other tabs detect logout, redirect | [ ] | |
| ERR-014 | Logout everywhere notification | Multiple devices | 1. Logout everywhere | All sessions notified and closed | [ ] | |

---

## 17. Test Sign-Off

### 17.1 Summary

| Category | Total Tests | Passed | Failed | Blocked | Not Run |
|----------|-------------|--------|--------|---------|---------|
| Session Management | 24 | | | | |
| Clinical Dashboard | 16 | | | | |
| Admin Dashboard | 15 | | | | |
| EHR Form Updates | 20 | | | | |
| Disposition Tab | 14 | | | | |
| Email Notification | 12 | | | | |
| Terms & Conditions | 12 | | | | |
| OSHA ITA | 9 | | | | |
| Database & API | 18 | | | | |
| Integration | 9 | | | | |
| Security | 16 | | | | |
| Accessibility | 13 | | | | |
| Mobile/Responsive | 11 | | | | |
| Performance | 10 | | | | |
| Error Handling | 14 | | | | |
| **TOTAL** | **213** | | | | |

### 17.2 Test Environment

| Property | Value |
|----------|-------|
| **Browser(s)** | |
| **OS** | |
| **Database Version** | |
| **PHP Version** | |
| **Node Version** | |

### 17.3 Approval

| Role | Name | Date | Signature |
|------|------|------|-----------|
| QA Lead | | | |
| Dev Lead | | | |
| Product Owner | | | |

### 17.4 Notes and Known Issues

<!-- Document any known issues, workarounds, or test limitations here -->

---

## Appendix A: Test Data Generation Scripts

### Create Test Patients

```sql
INSERT INTO Patient (patient_id, legal_first_name, legal_last_name, dob, ssn_encrypted, primary_phone)
VALUES 
('test-patient-001', 'John', 'Doe', '1980-01-15', 'encrypted_ssn_1', '3035551234'),
('test-patient-002', 'Jane', 'Smith', '1992-06-20', 'encrypted_ssn_2', '3035555678'),
('test-patient-003', 'Bob', 'Johnson', '1975-11-30', 'encrypted_ssn_3', '3035559012');
```

### Create Test Encounters

```sql
INSERT INTO Encounter (encounter_id, patient_id, status, encounter_type, created_at)
VALUES
('test-enc-001', 'test-patient-001', 'active', 'VISIT', NOW()),
('test-enc-002', 'test-patient-002', 'active', 'WORK_RELATED', NOW());
```

### Create Test Clinic Email Recipients

```sql
INSERT INTO clinic_email_recipients (clinic_id, email_address, recipient_name, is_active)
VALUES
('clinic-001', 'test@example.com', 'Test Recipient', 1);
```

---

## Appendix B: Related Documentation

- [Session Management Architecture](./SESSION_MANAGEMENT_ARCHITECTURE.md)
- [Patient Portal Architecture](./PATIENT_PORTAL_ARCHITECTURE.md)
- [OSHA ITA Integration Architecture](./OSHA_ITA_INTEGRATION_ARCHITECTURE.md)
- [Testing Guide](./TESTING_GUIDE.md)
- [Security Documentation](./SECURITY.md)
- [HIPAA Compliance](./HIPAA_COMPLIANCE.md)
- [API Documentation](./API.md)

---

*This testing checklist is part of the SafeShift EHR quality assurance process. For questions or updates, contact the QA team.*
