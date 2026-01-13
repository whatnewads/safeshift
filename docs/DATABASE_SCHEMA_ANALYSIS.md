# Database Schema Analysis for Dashboard Implementation

## Overview

This document provides a comprehensive analysis of the SafeShift EHR database schema (`safeshift_ehr_001_0`) to support dashboard implementation. The analysis focuses on tables relevant to role-based dashboards, excluding OSHA compliance tables.

**Database**: `safeshift_ehr_001_0`  
**Server**: MariaDB 10.4.32  
**Character Set**: utf8mb4  
**Generated**: December 27, 2025

---

## Table Categories

### 1. User/Role Management Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`user`](#user) | Core user accounts | `user_id` (UUID) |
| [`role`](#role) | Role definitions | `role_id` (UUID) |
| [`userrole`](#userrole) | User-to-role assignments (M:N) | `user_role_id` (UUID) |
| [`user_permission`](#user_permission) | Permission definitions | `permission_id` (UUID) |
| [`login_otp`](#login_otp) | OTP codes for MFA | `otp_id` (UUID) |
| [`user_device`](#user_device) | Registered user devices | `device_id` (UUID) |

### 2. Patient Data Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`patients`](#patients) | Core patient demographics | `patient_id` (UUID) |
| [`patient_addresses`](#patient_addresses) | Patient address records | `address_id` (INT) |
| [`patient_identifiers`](#patient_identifiers) | Patient ID documents | `identifier_id` (INT) |
| [`patient_allergies`](#patient_allergies) | Patient allergy records | `allergy_id` (INT) |
| [`patient_medications`](#patient_medications) | Current medications | `med_id` (INT) |
| [`patient_conditions`](#patient_conditions) | Medical conditions/diagnoses | `condition_id` (INT) |
| [`patient_immunizations`](#patient_immunizations) | Immunization records | `imm_id` (INT) |
| [`patient_insurance`](#patient_insurance) | Insurance coverage | `insurance_id` (INT) |
| [`patient_observations`](#patient_observations) | Patient-level observations | `obs_id` (INT) |
| [`patient_access_log`](#patient_access_log) | PHI access tracking | `log_id` (UUID) |
| [`consents`](#consents) | Patient consent records | `consent_id` (INT) |

### 3. Encounter/Clinical Data Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`encounters`](#encounters) | Core encounter records | `encounter_id` (UUID) |
| [`encounter_clinic`](#encounter_clinic) | Clinic visit details | `encounter_id` (UUID) |
| [`encounter_response`](#encounter_response) | EMS response details | `encounter_id` (UUID) |
| [`encounter_crew`](#encounter_crew) | EMS crew assignments | `crew_id` (INT) |
| [`encounter_observations`](#encounter_observations) | Vitals/clinical observations | `obs_id` (INT) |
| [`encounter_med_admin`](#encounter_med_admin) | Medication administration | `med_admin_id` (INT) |
| [`encounter_procedures`](#encounter_procedures) | Procedures performed | `procedure_id` (INT) |
| [`encounter_orders`](#encounter_orders) | Clinical orders | `order_id` (INT) |
| [`encounter_flags`](#encounter_flags) | Encounter alerts/flags | `flag_id` (UUID) |
| [`orders`](#orders) | Simplified orders table | `order_id` (UUID) |
| [`appointments`](#appointments) | Patient appointments | `appointment_id` (UUID) |
| [`chart_templates`](#chart_templates) | Documentation templates | `template_id` (UUID) |

### 4. DOT Testing Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`dot_tests`](#dot_tests) | DOT drug/alcohol tests | `test_id` (UUID) |
| [`chainofcustodyform`](#chainofcustodyform) | Chain of custody forms | `ccf_id` (UUID) |

### 5. QA/Review Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`qa_review_queue`](#qa_review_queue) | QA review workflow | `review_id` (UUID) |
| [`qa_bulk_actions`](#qa_bulk_actions) | Bulk QA operations | `action_id` (UUID) |
| [`flag_rules`](#flag_rules) | Auto-flagging rules | `rule_id` (UUID) |

### 6. Notification Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`user_notification`](#user_notification) | User notifications | `notification_id` (UUID) |

### 7. Audit/Compliance Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`audit_log`](#audit_log) | General audit log | `log_id` (BIGINT) |
| [`auditevent`](#auditevent) | Detailed audit events | `audit_id` (UUID) |
| [`audit_exports`](#audit_exports) | Audit export records | `export_id` (UUID) |
| [`compliance_kpis`](#compliance_kpis) | Compliance KPIs | `kpi_id` (UUID) |
| [`compliance_kpi_values`](#compliance_kpi_values) | KPI measurements | `value_id` (UUID) |
| [`compliance_alerts`](#compliance_alerts) | Compliance alerts | `alert_id` (UUID) |

### 8. Training/Regulatory Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`training_requirements`](#training_requirements) | Training requirements | `requirement_id` (UUID) |
| [`staff_training_records`](#staff_training_records) | Staff training completion | `record_id` (UUID) |
| [`training_reminders`](#training_reminders) | Training reminder logs | `reminder_id` (UUID) |
| [`regulatory_updates`](#regulatory_updates) | Regulation changes | `update_id` (UUID) |
| [`regulation_trainings`](#regulation_trainings) | Training from regulations | `training_id` (UUID) |
| [`implementation_checklists`](#implementation_checklists) | Implementation tracking | `checklist_id` (UUID) |

### 9. Reference/Lookup Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`ref_diagnosis_codes`](#ref_diagnosis_codes) | ICD-10/SNOMED codes | `coding_id` (INT) |
| [`ref_gender_identity`](#ref_gender_identity) | Gender identity options | `code` (VARCHAR) |
| [`ref_pronouns`](#ref_pronouns) | Pronoun options | `code` (VARCHAR) |

### 10. System/Sync Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`syncstate`](#syncstate) | Offline sync state | `sync_id` (UUID) |
| [`sync_queue`](#sync_queue) | Pending sync operations | `queue_id` (UUID) |
| [`offline_conflicts`](#offline_conflicts) | Sync conflict resolution | `conflict_id` (UUID) |
| [`ui_tooltips`](#ui_tooltips) | Context-sensitive tooltips | `tooltip_id` (UUID) |
| [`user_tooltip_preferences`](#user_tooltip_preferences) | User tooltip settings | `user_id` (UUID) |

### 11. Establishment/Provider Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`establishment`](#establishment) | Business establishments | `Id` (INT) |
| [`establishment_provider`](#establishment_provider) | Healthcare providers | `provider_id` (UUID) |

### 12. Quality of Life Tables

| Table | Purpose | Primary Key |
|-------|---------|-------------|
| [`qol_assessments`](#qol_assessments) | PHQ-9, GAD-7, etc. | `qol_id` (INT) |

---

## ⚠️ Tables to AVOID (OSHA Compliance)

The following tables are OSHA compliance tables and **MUST NOT be modified**:

| Table | Description |
|-------|-------------|
| `300a` | OSHA Form 300A - Annual Summary |
| `300_log` | OSHA Form 300 - Log entries |
| `301` | OSHA Form 301 - Incident reports |

These tables have foreign key references to external OSHA case tables and are governed by regulatory requirements.

---

## Detailed Table Structures

### User/Role Tables

#### user

Core user accounts table.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `user_id` | char(36) | NO | UUID primary key |
| `username` | varchar(190) | NO | Unique login name |
| `email` | varchar(320) | NO | Email address |
| `password_hash` | varchar(255) | YES | Bcrypt hash |
| `mfa_enabled` | tinyint(1) | YES | MFA status |
| `status` | varchar(32) | YES | Account status (default: active) |
| `lockout_until` | datetime | YES | Account lockout timestamp |
| `last_login` | datetime | YES | Last successful login |
| `is_active` | tinyint(1) | NO | Active flag |
| `attributes` | JSON | YES | Extended attributes |
| `created_at` | timestamp | NO | Creation timestamp |
| `updated_at` | timestamp | NO | Last update |
| `login_attempts` | int(11) | YES | Failed login counter |
| `ip_address` | varchar(45) | YES | Last IP address |
| `account_locked_until` | timestamp | YES | Alternative lockout field |

**Indexes**: PRIMARY(`user_id`), UNIQUE(`username`), KEY(`email`, `status`, `is_active`, `lockout_until`)

---

#### role

Role definitions with JSON attributes.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `role_id` | char(36) | NO | UUID primary key |
| `name` | varchar(100) | NO | Human-readable name |
| `slug` | varchar(50) | NO | URL-safe identifier |
| `description` | text | NO | Role description |
| `attributes` | JSON | YES | Extended role attributes |
| `created_at` | timestamp | NO | Creation timestamp |
| `updated_at` | timestamp | NO | Last update |

**Indexes**: PRIMARY(`role_id`), UNIQUE(`name`, `slug`)

---

#### userrole

Junction table for user-role assignments (many-to-many).

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `user_role_id` | char(36) | NO | UUID primary key |
| `user_id` | char(36) | NO | FK to user |
| `role_id` | char(36) | NO | FK to role |
| `assigned_at` | datetime | YES | Assignment timestamp |
| `created_at` | timestamp | NO | Creation timestamp |
| `assigned_by` | text | NO | Who assigned the role |

**Indexes**: PRIMARY(`user_role_id`), UNIQUE(`user_id`, `role_id`)

---

#### user_permission

Permission definitions (resource.action pattern).

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `permission_id` | char(36) | NO | UUID primary key |
| `name` | varchar(128) | NO | Permission name |
| `resource` | varchar(128) | NO | Resource type |
| `action` | varchar(64) | NO | Action type |
| `created_at` | timestamp | NO | Creation timestamp |
| `updated_at` | timestamp | NO | Last update |

**Indexes**: PRIMARY(`permission_id`), UNIQUE(`name`, `resource`, `action`)

---

#### user_notification

User notification system.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `notification_id` | char(36) | NO | UUID primary key |
| `user_id` | char(36) | NO | FK to user |
| `type` | varchar(64) | NO | Notification type |
| `priority` | varchar(32) | YES | Priority level (default: normal) |
| `title` | varchar(255) | NO | Notification title |
| `message` | text | YES | Notification body |
| `data` | JSON | YES | Additional data payload |
| `is_read` | tinyint(1) | YES | Read status |
| `read_at` | datetime(6) | YES | When read |
| `created_at` | timestamp | NO | Creation timestamp |
| `expires_at` | datetime(6) | YES | Expiration timestamp |

**Indexes**: PRIMARY(`notification_id`), KEY(`user_id`, `is_read`, `created_at`)
**Foreign Keys**: `user_id` → `user(user_id)`

---

### Patient Tables

#### patients

Core patient demographics.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `patient_id` | char(36) | NO | UUID primary key |
| `legal_first_name` | varchar(100) | NO | Legal first name |
| `legal_last_name` | varchar(100) | NO | Legal last name |
| `dob` | date | NO | Date of birth |
| `sex_assigned_at_birth` | enum | NO | M, F, X, U |
| `preferred_name` | varchar(100) | YES | Preferred name |
| `gender_identity` | varchar(20) | YES | FK to ref_gender_identity |
| `pronouns` | varchar(20) | YES | FK to ref_pronouns |
| `phone` | varchar(20) | YES | Phone number |
| `email` | varchar(255) | YES | Email address |
| `county` | varchar(100) | YES | County |
| `zip_code` | char(10) | YES | ZIP code |
| `preferred_language` | varchar(50) | NO | Language (default: en) |
| `interpreter_required` | tinyint(1) | NO | Interpreter needed |
| `primary_contact_method` | enum | YES | sms, call, email, other |
| `sms_consent` | tinyint(1) | NO | SMS consent |
| `email_consent` | tinyint(1) | NO | Email consent |
| `created_at` | datetime | NO | Creation timestamp |
| `created_by` | int(10) | NO | Creating user ID |
| `modified_at` | datetime | YES | Last modification |
| `modified_by` | int(10) | YES | Modifying user ID |
| `deleted_at` | datetime | YES | Soft delete timestamp |
| `deleted_by` | int(10) | YES | Deleting user ID |

**Indexes**: PRIMARY(`patient_id`), KEY(`legal_last_name`, `legal_first_name`), KEY(`phone`, `email`, `dob`)
**Foreign Keys**: `gender_identity` → `ref_gender_identity(code)`, `pronouns` → `ref_pronouns(code)`

---

### Encounter Tables

#### encounters

Core encounter records.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `encounter_id` | char(36) | NO | UUID primary key |
| `patient_id` | char(36) | NO | FK to patients |
| `site_id` | int(10) | YES | Site/facility reference |
| `employer_name` | varchar(255) | YES | Employer at time of encounter |
| `encounter_type` | enum | NO | ems, clinic, telemedicine, other |
| `encounter_type_other` | varchar(100) | YES | Custom type description |
| `status` | enum | NO | planned, arrived, in_progress, completed, cancelled, voided |
| `chief_complaint` | text | YES | Chief complaint |
| `onset_context` | enum | YES | work_related, off_duty, unknown |
| `occurred_on` | datetime | NO | Incident date/time |
| `arrived_on` | datetime | YES | Arrival time |
| `discharged_on` | datetime | YES | Discharge time |
| `disposition` | varchar(255) | YES | Disposition type |
| `npi_provider` | varchar(10) | YES | Provider NPI |
| `created_at` | datetime | NO | Creation timestamp |
| `created_by` | int(10) | NO | Creating user |
| `modified_at` | datetime | YES | Last modification |
| `modified_by` | int(10) | YES | Modifying user |
| `deleted_at` | datetime | YES | Soft delete timestamp |
| `deleted_by` | int(10) | YES | Deleting user |

**Indexes**: PRIMARY(`encounter_id`), UNIQUE(`encounter_id`, `patient_id`), KEY(`patient_id`, `occurred_on`), KEY(`status`, `occurred_on`)
**Foreign Keys**: `patient_id` → `patients(patient_id)`

---

#### encounter_observations

Vital signs and clinical observations within encounters.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `obs_id` | int(10) | NO | Auto-increment primary key |
| `encounter_id` | char(36) | NO | FK to encounters |
| `patient_id` | char(36) | NO | FK to patients |
| `label` | varchar(100) | NO | Observation type (BP, Pulse, SpO2, etc.) |
| `posture` | enum | YES | Patient position |
| `posture_other` | varchar(100) | YES | Custom position |
| `value_num` | decimal(10,2) | YES | Numeric value |
| `value_text` | text | YES | Text value |
| `unit` | varchar(50) | YES | Unit of measure |
| `method` | varchar(100) | YES | Measurement method |
| `taken_at` | datetime | NO | When taken |
| `notes` | text | YES | Additional notes |
| `created_at` | datetime | NO | Creation timestamp |
| `created_by` | int(10) | NO | Creating user |
| `modified_at` | datetime | YES | Last modification |
| `modified_by` | int(10) | YES | Modifying user |
| `deleted_at` | datetime | YES | Soft delete |
| `deleted_by` | int(10) | YES | Deleting user |

**Foreign Keys**: `encounter_id` → `encounters(encounter_id)`, `patient_id` → `patients(patient_id)`

---

### DOT Testing Tables

#### dot_tests

DOT drug and alcohol testing records.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `test_id` | char(36) | NO | UUID primary key |
| `encounter_id` | char(36) | NO | FK to encounters |
| `patient_id` | char(36) | NO | FK to patients |
| `modality` | enum | NO | drug_test, alcohol_test |
| `test_type` | varchar(100) | YES | Specific test type |
| `specimen_id` | varchar(100) | YES | Specimen identifier |
| `collected_at` | datetime | YES | Collection timestamp |
| `results` | JSON | YES | Test results data |
| `mro_review_required` | tinyint(1) | YES | MRO review needed |
| `mro_reviewed_by` | char(36) | YES | Reviewing MRO |
| `mro_reviewed_at` | datetime | YES | Review timestamp |
| `status` | enum | YES | pending, negative, positive, cancelled, invalid |
| `created_at` | datetime | YES | Creation timestamp |

**Indexes**: PRIMARY(`test_id`), KEY(`patient_id`, `modality`)

---

### QA Review Tables

#### qa_review_queue

Quality assurance review workflow.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `review_id` | char(36) | NO | UUID primary key |
| `encounter_id` | char(36) | NO | FK to encounters |
| `reviewer_id` | char(36) | YES | Assigned reviewer |
| `review_status` | enum | YES | pending, approved, rejected, flagged |
| `review_notes` | text | YES | Review comments |
| `reviewed_at` | datetime | YES | Review timestamp |
| `created_at` | datetime | YES | Creation timestamp |

**Indexes**: PRIMARY(`review_id`), KEY(`reviewer_id`, `review_status`)

---

### Audit Tables

#### audit_log

Comprehensive audit logging for HIPAA compliance.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `log_id` | bigint(20) | NO | Auto-increment primary key |
| `table_name` | varchar(64) | NO | Affected table |
| `record_id` | varchar(36) | NO | Affected record PK |
| `action` | enum | NO | view, insert, update, delete, export, print |
| `user_id` | int(10) | NO | Acting user |
| `user_role` | varchar(50) | YES | User's role at time of action |
| `logged_at` | datetime(3) | NO | Timestamp (millisecond precision) |
| `ip_address` | varbinary(16) | YES | IPv4 or IPv6 |
| `user_agent` | varchar(255) | YES | Browser/client info |
| `session_id` | varchar(255) | YES | Session identifier |
| `changed_fields` | JSON | YES | Before/after values |
| `context` | varchar(255) | YES | Screen/module context |
| `checksum` | char(64) | YES | SHA-256 integrity hash |

**Indexes**: PRIMARY(`log_id`), KEY(`table_name`, `record_id`, `logged_at`), KEY(`user_id`, `logged_at`), KEY(`action`, `logged_at`)

---

## Entity Relationship Diagram (Text)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           USER & ROLE MANAGEMENT                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   ┌──────────┐       ┌───────────┐       ┌──────────┐                       │
│   │   user   │◄─────►│ userrole  │◄─────►│   role   │                       │
│   └────┬─────┘       └───────────┘       └──────────┘                       │
│        │                                                                    │
│        │ 1:N                                                                │
│        ▼                                                                    │
│   ┌────────────────┐    ┌───────────────────┐    ┌──────────────┐          │
│   │ user_device    │    │ user_notification │    │  login_otp   │          │
│   └────────────────┘    └───────────────────┘    └──────────────┘          │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                              PATIENT DATA                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│                           ┌────────────┐                                    │
│                           │  patients  │                                    │
│                           └─────┬──────┘                                    │
│                                 │                                           │
│        ┌────────────────────────┼────────────────────────┐                  │
│        │              │         │         │              │                  │
│        ▼              ▼         ▼         ▼              ▼                  │
│  ┌───────────┐  ┌──────────┐  ┌─────┐  ┌────────┐  ┌──────────┐            │
│  │ addresses │  │ allergies│  │meds │  │conditions│ │identifiers│           │
│  └───────────┘  └──────────┘  └─────┘  └────────┘  └──────────┘            │
│                                                                             │
│        ┌────────────────────────┼────────────────────────┐                  │
│        │              │         │         │              │                  │
│        ▼              ▼         ▼         ▼              ▼                  │
│  ┌───────────┐  ┌──────────┐  ┌─────────┐ ┌────────┐  ┌──────────┐         │
│  │immunizations│ │insurance│ │observations│ │consents│ │qol_assess│         │
│  └───────────┘  └──────────┘  └─────────┘ └────────┘  └──────────┘         │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                           CLINICAL ENCOUNTERS                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   patients                                                                  │
│      │                                                                      │
│      │ 1:N                                                                  │
│      ▼                                                                      │
│   ┌────────────┐                                                            │
│   │ encounters │                                                            │
│   └─────┬──────┘                                                            │
│         │                                                                   │
│    ┌────┴────┬─────────────┬──────────────┬──────────────┬────────────┐    │
│    │         │             │              │              │            │     │
│    ▼         ▼             ▼              ▼              ▼            ▼     │
│ ┌───────┐ ┌────────┐ ┌───────────┐ ┌───────────┐ ┌──────────┐ ┌─────────┐ │
│ │clinic │ │response│ │observations│ │procedures │ │med_admin │ │ orders  │ │
│ └───────┘ └────────┘ └───────────┘ └───────────┘ └──────────┘ └─────────┘ │
│                                                                             │
│    ┌───────────────┬────────────────┬────────────────┐                      │
│    │               │                │                │                      │
│    ▼               ▼                ▼                ▼                      │
│ ┌─────────┐  ┌──────────┐  ┌────────────────┐  ┌───────────────┐            │
│ │  crew   │  │  flags   │  │ qa_review_queue│  │   dot_tests   │            │
│ └─────────┘  └──────────┘  └────────────────┘  └───────────────┘            │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Foreign Key Relationships

### User ↔ Role Relationships

```sql
-- User to UserRole (one-to-many)
userrole.user_id → user.user_id

-- Role to UserRole (one-to-many)
userrole.role_id → role.role_id

-- User to Notifications (one-to-many)
user_notification.user_id → user.user_id

-- User to Devices (one-to-many)
user_device.user_id → user.user_id

-- User to Audit Events (one-to-many)
auditevent.user_id → user.user_id
```

### Patient ↔ Encounter Relationships

```sql
-- Patient to Encounters (one-to-many)
encounters.patient_id → patients.patient_id

-- Encounter to Observations (one-to-many, composite FK)
encounter_observations.(encounter_id, patient_id) → encounters.(encounter_id, patient_id)

-- Encounter to Procedures (one-to-many, composite FK)
encounter_procedures.(encounter_id, patient_id) → encounters.(encounter_id, patient_id)

-- Encounter to Medication Administration (one-to-many, composite FK)
encounter_med_admin.(encounter_id, patient_id) → encounters.(encounter_id, patient_id)

-- Encounter to Clinic Details (one-to-one)
encounter_clinic.encounter_id → encounters.encounter_id

-- Encounter to Response Details (one-to-one)
encounter_response.encounter_id → encounters.encounter_id

-- Encounter to Orders (one-to-many)
encounter_orders.encounter_id → encounters.encounter_id
```

### Patient Related Data

```sql
-- All patient child tables reference patients
patient_addresses.patient_id → patients.patient_id
patient_allergies.patient_id → patients.patient_id
patient_conditions.patient_id → patients.patient_id
patient_identifiers.patient_id → patients.patient_id
patient_immunizations.patient_id → patients.patient_id
patient_insurance.patient_id → patients.patient_id
patient_medications.patient_id → patients.patient_id
patient_observations.patient_id → patients.patient_id
consents.patient_id → patients.patient_id
qol_assessments.patient_id → patients.patient_id
```

### Reference Data

```sql
-- Patient to Gender Identity reference
patients.gender_identity → ref_gender_identity.code

-- Patient to Pronouns reference
patients.pronouns → ref_pronouns.code

-- Conditions to Diagnosis Codes
patient_conditions.coding_id → ref_diagnosis_codes.coding_id
```

---

## Role Definitions

Based on the [`role`](#role) table and [ROLE_MAPPING.md](./ROLE_MAPPING.md), here are the defined roles:

### Backend Role Slugs

| Slug | Display Name | UI Role | Dashboard Route |
|------|--------------|---------|-----------------|
| `1clinician` | Intake Clinician | `registration` | `/dashboard/registration` |
| `dclinician` | Drug Screen Technician | `technician` | `/dashboard/technician` |
| `pclinician` | Clinical Provider | `provider` | `/dashboard/provider` |
| `cadmin` | Clinic Administrator | `admin` | `/dashboard/admin` |
| `tadmin` | Technical Administrator | `admin` | `/dashboard/admin` |
| `Admin` | System Administrator | `super-admin` | `/dashboard/super-admin` |
| `Manager` | Manager | `manager` | `/dashboard/manager` |
| `QA` | Quality Assurance | `qa` | `/dashboard/qa` |
| `PrivacyOfficer` | Privacy Officer | `privacy-officer` | `/dashboard/privacy` |
| `SecurityOfficer` | Security Officer | `security-officer` | `/dashboard/security` |

### Role Hierarchy (Conceptual)

```
super-admin (Admin)
    │
    ├── manager (Manager)
    │       │
    │       ├── admin (cadmin, tadmin)
    │       │
    │       ├── provider (pclinician)
    │       │
    │       ├── technician (dclinician)
    │       │
    │       └── registration (1clinician)
    │
    ├── qa (QA)
    │
    ├── privacy-officer (PrivacyOfficer)
    │
    └── security-officer (SecurityOfficer)
```

---

## Dashboard-to-Table Mapping

### Registration Dashboard (`/dashboard/registration`)
**Role**: `1clinician` (Intake Clinician)

| Table | Usage | Operations |
|-------|-------|------------|
| `patients` | Patient lookup/creation | CREATE, READ |
| `patient_addresses` | Address management | CREATE, READ |
| `patient_identifiers` | ID verification | CREATE, READ |
| `patient_insurance` | Insurance capture | CREATE, READ |
| `appointments` | Check-in workflow | READ, UPDATE |
| `encounters` | Encounter initiation | CREATE, READ |
| `consents` | Consent collection | CREATE, READ |

---

### Technician Dashboard (`/dashboard/technician`)
**Role**: `dclinician` (Drug Screen Technician)

| Table | Usage | Operations |
|-------|-------|------------|
| `patients` | Patient identification | READ |
| `encounters` | Active encounters | READ, UPDATE |
| `dot_tests` | DOT test management | CREATE, READ, UPDATE |
| `chainofcustodyform` | CCF documentation | CREATE, READ, UPDATE |
| `encounter_observations` | Vitals (if required) | CREATE, READ |

---

### Provider Dashboard (`/dashboard/provider`)
**Role**: `pclinician` (Clinical Provider)

| Table | Usage | Operations |
|-------|-------|------------|
| `patients` | Full patient access | READ, UPDATE |
| `patient_allergies` | Allergy review/update | CREATE, READ, UPDATE |
| `patient_medications` | Medication management | CREATE, READ, UPDATE |
| `patient_conditions` | Problem list | CREATE, READ, UPDATE |
| `encounters` | Full encounter management | CREATE, READ, UPDATE |
| `encounter_observations` | Vitals/assessments | CREATE, READ, UPDATE |
| `encounter_med_admin` | Medication administration | CREATE, READ, UPDATE |
| `encounter_procedures` | Procedure documentation | CREATE, READ, UPDATE |
| `encounter_orders` | Orders management | CREATE, READ, UPDATE |
| `chart_templates` | Documentation templates | READ |
| `qol_assessments` | Quality of life screening | CREATE, READ, UPDATE |

---

### Admin Dashboard (`/dashboard/admin`)
**Roles**: `cadmin`, `tadmin` (Clinic/Technical Admin)

| Table | Usage | Operations |
|-------|-------|------------|
| `user` | User management | READ, UPDATE |
| `userrole` | Role assignments | READ |
| `patients` | Patient oversight | READ |
| `encounters` | Encounter oversight | READ |
| `appointments` | Scheduling management | READ, UPDATE |
| `compliance_kpis` | Compliance monitoring | READ |
| `compliance_kpi_values` | KPI values | READ |
| `training_requirements` | Training management | CREATE, READ, UPDATE |
| `staff_training_records` | Training tracking | READ |
| `audit_log` | Audit review | READ |

---

### Manager Dashboard (`/dashboard/manager`)
**Role**: `Manager`

| Table | Usage | Operations |
|-------|-------|------------|
| `user` | Staff management | CREATE, READ, UPDATE |
| `userrole` | Role management | CREATE, READ, UPDATE |
| `patients` | Full patient access | READ |
| `encounters` | Full encounter access | READ |
| `audit_log` | Audit review | READ |
| `compliance_kpis` | KPI management | CREATE, READ, UPDATE |
| `compliance_kpi_values` | KPI tracking | READ |
| `compliance_alerts` | Alert management | READ, UPDATE |
| `training_requirements` | Training setup | CREATE, READ, UPDATE, DELETE |
| `staff_training_records` | Training oversight | READ |
| `establishment` | Establishment management | READ |

---

### Super Admin Dashboard (`/dashboard/super-admin`)
**Role**: `Admin` (System Administrator)

| Table | Usage | Operations |
|-------|-------|------------|
| **ALL TABLES** | Full system access | ALL OPERATIONS |
| `user` | Full user management | CREATE, READ, UPDATE, DELETE |
| `role` | Role definitions | CREATE, READ, UPDATE, DELETE |
| `user_permission` | Permission management | CREATE, READ, UPDATE, DELETE |
| `regulatory_updates` | Regulatory tracking | CREATE, READ, UPDATE |
| `flag_rules` | Auto-flag configuration | CREATE, READ, UPDATE, DELETE |
| `ui_tooltips` | UI configuration | CREATE, READ, UPDATE, DELETE |

---

### QA Dashboard (`/dashboard/qa`)
**Role**: `QA` (Quality Assurance)

| Table | Usage | Operations |
|-------|-------|------------|
| `patients` | Patient review | READ |
| `encounters` | Encounter review | READ |
| `encounter_observations` | Clinical data review | READ |
| `encounter_procedures` | Procedure review | READ |
| `qa_review_queue` | QA workflow | CREATE, READ, UPDATE |
| `qa_bulk_actions` | Bulk operations | CREATE, READ |
| `encounter_flags` | Flag management | READ, UPDATE |
| `flag_rules` | Rule review | READ |

---

### Privacy Officer Dashboard (`/dashboard/privacy`)
**Role**: `PrivacyOfficer`

| Table | Usage | Operations |
|-------|-------|------------|
| `patients` | Patient record review | READ |
| `encounters` | Encounter review | READ |
| `audit_log` | Access audit | READ, EXPORT |
| `auditevent` | Detailed audit events | READ |
| `audit_exports` | Export tracking | CREATE, READ |
| `patient_access_log` | PHI access tracking | READ |
| `consents` | Consent verification | READ |
| `user_notification` | Breach notifications | CREATE, READ |

---

### Security Officer Dashboard (`/dashboard/security`)
**Role**: `SecurityOfficer`

| Table | Usage | Operations |
|-------|-------|------------|
| `user` | User account review | READ |
| `audit_log` | Security audit | READ, EXPORT |
| `auditevent` | Security events | READ |
| `audit_exports` | Export management | CREATE, READ |
| `login_otp` | MFA monitoring | READ |
| `user_device` | Device management | READ |
| `syncstate` | Sync security | READ |

---

## Recommended Queries by Dashboard

### Registration Dashboard - Patient Search

```sql
SELECT 
    p.patient_id,
    p.legal_first_name,
    p.legal_last_name,
    p.dob,
    p.phone,
    pi.id_value AS mrn
FROM patients p
LEFT JOIN patient_identifiers pi ON p.patient_id = pi.patient_id 
    AND pi.id_type = 'mrn' AND pi.is_primary = 1
WHERE p.deleted_at IS NULL
    AND (p.legal_last_name LIKE :search OR p.phone LIKE :search)
ORDER BY p.legal_last_name, p.legal_first_name
LIMIT 50;
```

### Provider Dashboard - Active Encounters

```sql
SELECT 
    e.encounter_id,
    e.status,
    e.occurred_on,
    e.chief_complaint,
    p.legal_first_name,
    p.legal_last_name,
    p.dob
FROM encounters e
JOIN patients p ON e.patient_id = p.patient_id
WHERE e.status IN ('arrived', 'in_progress')
    AND e.deleted_at IS NULL
    AND p.deleted_at IS NULL
    AND e.site_id = :current_site_id
ORDER BY e.occurred_on DESC;
```

### Technician Dashboard - Pending DOT Tests

```sql
SELECT 
    dt.test_id,
    dt.modality,
    dt.test_type,
    dt.status,
    dt.collected_at,
    p.legal_first_name,
    p.legal_last_name,
    e.encounter_id
FROM dot_tests dt
JOIN patients p ON dt.patient_id = p.patient_id
JOIN encounters e ON dt.encounter_id = e.encounter_id
WHERE dt.status = 'pending'
ORDER BY dt.created_at ASC;
```

### Admin Dashboard - User List with Roles

```sql
SELECT 
    u.user_id,
    u.username,
    u.email,
    u.status,
    u.last_login,
    r.name AS role_name,
    r.slug AS role_slug
FROM user u
LEFT JOIN userrole ur ON u.user_id = ur.user_id
LEFT JOIN role r ON ur.role_id = r.role_id
WHERE u.is_active = 1
ORDER BY u.username;
```

### QA Dashboard - Review Queue

```sql
SELECT 
    qr.review_id,
    qr.review_status,
    qr.created_at,
    e.encounter_id,
    e.occurred_on,
    e.status AS encounter_status,
    p.legal_first_name,
    p.legal_last_name
FROM qa_review_queue qr
JOIN encounters e ON qr.encounter_id = e.encounter_id
JOIN patients p ON e.patient_id = p.patient_id
WHERE qr.review_status = 'pending'
ORDER BY qr.created_at ASC;
```

### Privacy Officer - Access Log Summary

```sql
SELECT 
    DATE(al.logged_at) AS log_date,
    al.action,
    COUNT(*) AS action_count,
    COUNT(DISTINCT al.user_id) AS unique_users,
    COUNT(DISTINCT al.record_id) AS unique_records
FROM audit_log al
WHERE al.table_name IN ('patients', 'encounters', 'encounter_observations')
    AND al.logged_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(al.logged_at), al.action
ORDER BY log_date DESC, action;
```

---

## Data Integrity Notes

### Soft Delete Pattern
Most clinical tables implement soft delete using:
- `deleted_at` - timestamp when deleted (NULL = active)
- `deleted_by` - user who deleted the record

**Always filter by `deleted_at IS NULL` for active records.**

### UUID Usage
Primary keys use UUID (char(36)) for:
- `user_id`, `role_id`, `patient_id`, `encounter_id`
- Most modern tables

Legacy tables may use auto-increment integers.

### Audit Trail
All PHI-containing tables should log to:
- `audit_log` for general actions
- `auditevent` for detailed security events
- `patient_access_log` for PHI-specific access

### JSON Columns
Several tables use JSON for flexible attributes:
- `user.attributes`
- `role.attributes`
- `user_notification.data`
- `dot_tests.results`
- `encounter_observations.value_text` (when complex)

---

## Summary

This database schema supports a comprehensive EHR system with:

1. **Multi-role access control** via `user` → `userrole` → `role` relationships
2. **Complete patient lifecycle** from registration through clinical encounters
3. **DOT compliance** with drug testing and chain of custody tracking
4. **QA workflow** with review queues and bulk actions
5. **Full audit trail** for HIPAA compliance
6. **Offline sync support** for field operations
7. **Training and compliance tracking** for staff management

The schema is well-suited for the vertical slice architecture, with clear separation between:
- User/authentication concerns
- Patient demographics
- Clinical encounters
- Compliance/audit functions
- Administrative functions
