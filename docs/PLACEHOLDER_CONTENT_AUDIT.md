# Placeholder Content Audit for Dashboard Pages

## Overview

This document identifies all placeholder text and mock data in dashboard pages that need to be replaced with real database content. It maps placeholder content to corresponding database tables from `DATABASE_SCHEMA_ANALYSIS.md` and prioritizes replacements.

**Generated**: December 27, 2025  
**Pages Audited**: 11 dashboard pages + Notifications page

---

## Dashboard Analysis Summary

| Dashboard | Placeholder Count | API Hooks Used | Database Integration Status |
|-----------|------------------|----------------|----------------------------|
| Admin.tsx | 47 | useAdminDashboard, useTrainingModules, useExpiringCredentials, useOsha300Log, useOsha300ASummary | Partial (hooks exist, fallback data used) |
| ClinicalProvider.tsx | 8 | useShift, useSync | Minimal (mock data arrays) |
| Doctor.tsx | 0 | None | Not implemented (stub page) |
| Manager.tsx | 0 | useManagerDashboard | Full (uses API hooks) |
| PrivacyOfficer.tsx | 10 | None | None (all mock data) |
| Registration.tsx | 10 | None | None (all mock data) |
| SecurityOfficer.tsx | 11 | None | None (all mock data) |
| SuperAdmin.tsx | 32 | useSuperAdminDashboard, useUsers, useClinics, useAuditLogs, useSecurityIncidents | Partial (hooks exist, fallback data used) |
| SuperManager.tsx | 0 | None | Not implemented (stub page) |
| Technician.tsx | 0 | useTechnicianDashboard | Full (uses API hooks) |
| Notifications.tsx | 0 | useNotifications | Full (uses API hooks) |

---

## Admin.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| 149-156 | `allUsers` mock array with fake user data | Mock array | `user`, `userrole`, `role` | `GET /api/admin/users` | High |
| 159-163 | `cases` mock array with patient names | Mock array | `encounters`, `patients` | `GET /api/admin/cases` | High |
| 166-249 | `mockComplianceData` array | Mock array | `compliance_alerts`, `regulatory_updates` | `GET /api/compliance/notifications` | Medium |
| 258-261 | `complianceAlerts` array | Mock array | `compliance_alerts` | `GET /api/admin/compliance-alerts` | Medium |
| 263-267 | `aiRecommendations` array | Mock array | Custom AI table or JSON field | `GET /api/admin/ai-recommendations` | Low |
| 270-274 | `trainingModules` array | Mock array | `training_requirements`, `staff_training_records` | `GET /api/admin/training-modules` | High |
| 276-282 | `expiringCredentials` array | Mock array | `staff_training_records`, `user` | `GET /api/admin/expiring-credentials` | High |
| 285-290 | `recentAudits` array | Mock array | `audit_log`, `auditevent` | `GET /api/admin/audit-logs` | High |
| 292-296 | `anomalies` array | Mock array | `audit_log` with flags | `GET /api/admin/anomalies` | Medium |
| 524 | `12` - Open Cases fallback | Static number | `encounters` | Stats endpoint | High |
| 537 | `5` - Follow-ups Due fallback | Static number | `encounters` | Stats endpoint | High |
| 549 | `3` - High Risk fallback | Static number | `encounters` with flags | Stats endpoint | High |
| 561 | `98%` - Compliance fallback | Static number | `compliance_kpi_values` | Stats endpoint | High |
| 584 | `24` - Active Training | Static number | `staff_training_records` | Stats endpoint | High |
| 714 | `45` - Cleared count | Static number | `encounters` | `GET /api/admin/clearance-stats` | Medium |
| 719 | `8` - Not Cleared count | Static number | `encounters` | Stats endpoint | Medium |
| 724 | `12` - Pending Review count | Static number | `qa_review_queue` | Stats endpoint | Medium |
| 1081-1240 | OSHA 300 Log table rows | Hardcoded rows | `300_log` (OSHA - READ ONLY) | `GET /api/osha/300-log` | High |
| 1247-1261 | OSHA summary statistics | Static numbers | `300_log` computed | Stats endpoint | High |
| 1305-1367 | OSHA 300A Summary stats | Static numbers | `300a` (OSHA - READ ONLY) | `GET /api/osha/300a-summary` | High |
| 1377-1399 | Establishment info | Static text | `establishment` | `GET /api/establishment` | Medium |
| 1408-1420 | Incidence Rates | Static numbers | Computed from OSHA tables | Stats endpoint | Medium |
| 1719-1738 | Patient Flow Metrics | Static numbers | `encounters` with timestamps | `GET /api/admin/metrics/patient-flow` | Medium |
| 1753-1769 | Site Performance table | Hardcoded rows | `encounters`, `establishment` | `GET /api/admin/metrics/by-site` | Medium |
| 1789-1821 | Provider Performance table | Hardcoded rows | `encounters`, `user` | `GET /api/admin/metrics/by-provider` | Medium |
| 1836-1880 | MOI/NOI Trends | Hardcoded data | `encounters` aggregated | `GET /api/admin/metrics/moi-noi` | Low |
| 1899-1935 | Section Completion Times | Hardcoded rows | `encounters` timing data | `GET /api/admin/metrics/section-times` | Low |

### Current Data Flow Analysis

**Existing API Hooks:**
- [`useAdminDashboard()`](src/app/hooks/useAdmin.ts) - Returns `stats` object
- [`useTrainingModules()`](src/app/hooks/useAdmin.ts) - Returns `modules` array
- [`useExpiringCredentials()`](src/app/hooks/useAdmin.ts) - Returns `credentials` array
- [`useOsha300Log()`](src/app/hooks/useAdmin.ts) - Returns `entries`, `statistics`
- [`useOsha300ASummary()`](src/app/hooks/useAdmin.ts) - Returns `summary`

**Pattern:** Hooks exist but fallback to mock data when API returns empty/error. The fallback values are hardcoded in the component (lines 524, 537, 549, 561, etc.).

**Loading States:** Uses `statsLoading`, `trainingLoading`, `credentialsLoading`, `oshaLoading`, `osha300ALoading` for conditional rendering.

---

## ClinicalProvider.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| 49-77 | `recentEncounters` array with patient data | Mock array | `encounters`, `patients` | `GET /api/provider/recent-encounters` | High |
| 79-81 | `openCases` array | Mock array | `encounters` | `GET /api/provider/open-cases` | High |
| 53-55 | Patient names: John Smith, Maria Garcia, Robert Chen | Fake names | `patients` | Included in encounters | High |
| 54 | `patientData` with fake DOB and employeeId | Fake data | `patients`, `patient_identifiers` | Patient lookup | High |
| 80 | David Lee - patient name | Fake name | `patients` | Open cases endpoint | High |

### Current Data Flow Analysis

**Existing API Hooks:**
- [`useShift()`](src/app/contexts/ShiftContext.tsx) - Context for shift data
- [`useSync()`](src/app/contexts/SyncContext.tsx) - Context for sync status

**Pattern:** No encounter/patient data hooks. All patient and encounter data is hardcoded mock data.

**Loading States:** None for data - only uses shift and sync context status.

---

## Doctor.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| 1-8 | Entire page is a stub | Stub page | Multiple | Multiple | High |

**Note:** This is a stub page with only header text. Needs complete implementation.

### Current Data Flow Analysis

**Existing API Hooks:** None

**Pattern:** No implementation - just static header text.

---

## Manager.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| N/A | None - Uses API hooks | N/A | N/A | N/A | N/A |

### Current Data Flow Analysis

**Existing API Hooks:**
- [`useManagerDashboard()`](src/app/hooks/useDashboard.ts) - Returns `stats`, `cases`, `loading`, `error`, `fetchCases`, `refetch`

**Pattern:** Fully integrated with API hooks. Component displays API data with proper loading/error states.

**Loading States:** Full loading spinner, error display with retry button, empty state messaging.

---

## PrivacyOfficer.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| 7-10 | `pendingUpdates` array | Mock array | `regulatory_updates` | `GET /api/privacy/pending-updates` | Medium |
| 12-15 | `aiRecommendations` array | Mock array | Custom AI table | `GET /api/privacy/ai-recommendations` | Low |
| 32 | `98%` - Compliance Rate | Static number | `compliance_kpi_values` | Stats endpoint | High |
| 44 | `aiRecommendations.length` - count | Dynamic from mock | AI table | Stats endpoint | Medium |
| 56 | `pendingUpdates.length` - count | Dynamic from mock | `regulatory_updates` | Stats endpoint | Medium |
| 68 | `24` - Active Trainings | Static number | `training_requirements`, `staff_training_records` | Stats endpoint | High |

### Current Data Flow Analysis

**Existing API Hooks:** None

**Pattern:** All data is hardcoded mock arrays. No API integration.

**Loading States:** None

---

## Registration.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| 32-35 | `pendingRegistrations` array | Mock array | `patients`, `appointments` | `GET /api/registration/pending` | High |
| 37-39 | `recentlyCompleted` array | Mock array | `patients`, `encounters` | `GET /api/registration/completed` | High |
| 33-34 | John Smith, Maria Garcia - patient names | Fake names | `patients` | Patient lookup | High |
| 33-34 | ABC Construction, XYZ Logistics - employers | Fake employers | `establishment` or `encounters.employer_name` | Employer lookup | High |
| 209 | `pendingRegistrations.length` - Pending Queue | Dynamic from mock | `appointments` | Stats endpoint | High |
| 221 | `12` - Completed Today | Static number | `encounters` | Stats endpoint | High |
| 234 | `247` - Total Patients | Static number | `patients` | Stats endpoint | High |

### Current Data Flow Analysis

**Existing API Hooks:** None

**Pattern:** All data is hardcoded mock arrays. No API integration.

**Loading States:** None

---

## SecurityOfficer.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| 15-19 | `recentAudits` array | Mock array | `audit_log`, `auditevent` | `GET /api/security/audit-logs` | High |
| 21-24 | `anomalies` array | Mock array | `audit_log` (flagged) | `GET /api/security/anomalies` | High |
| 16-18 | Dr. Johnson, Nurse Davis, Admin User | Fake names | `user` | User lookup | High |
| 22-23 | Admin User, Nurse Smith | Fake names | `user` | Anomaly detection | High |
| 41 | `Secure` - System Status | Static text | System health check | `GET /api/system/health` | High |
| 53 | `1,234` - Events Today | Static number | `audit_log` | Stats endpoint | High |
| 65 | `anomalies.length` - count | Dynamic from mock | `audit_log` | Stats endpoint | High |
| 77 | `45` - Active Users | Static number | `user` (active sessions) | Stats endpoint | High |

### Current Data Flow Analysis

**Existing API Hooks:** None

**Pattern:** All data is hardcoded mock arrays. No API integration.

**Loading States:** None

---

## SuperAdmin.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| 83-88 | `systemUsers` fallback array | Fallback mock | `user`, `userrole`, `role` | `GET /api/superadmin/users` | High |
| 97-100 | `clinics` fallback array | Fallback mock | `establishment` | `GET /api/superadmin/clinics` | High |
| 103-107 | `aiModels` array | Mock array | Custom config table | `GET /api/superadmin/ai-models` | Low |
| 109-113 | `policyDocuments` array | Mock array | Document storage | `GET /api/superadmin/policy-docs` | Low |
| 123-127 | `securityIncidents` fallback | Fallback mock | `audit_log`, security incidents | `GET /api/superadmin/incidents` | High |
| 130-133 | `casesNeedingOverride` array | Mock array | `encounters`, `qa_review_queue` | `GET /api/superadmin/override-requests` | High |
| 179 | Total users count fallback | Static fallback | `user` | Stats endpoint | High |
| 192 | Active clinics count fallback | Static fallback | `establishment` | Stats endpoint | High |
| 204 | `aiModels.length` | Dynamic from mock | Config table | Stats endpoint | Low |
| 216 | Open incidents fallback | Static fallback | Security incidents | Stats endpoint | High |
| 228 | `1,247` - Audit logs fallback | Static fallback | `audit_log` | Stats endpoint | High |
| 383 | `Occupational Health EHR` - Org name | Static text | `establishment` or config | Config endpoint | Medium |
| 388 | `12-3456789` - EIN | Static text | `establishment` or config | Config endpoint | Medium |
| 392 | Address text | Static text | `establishment` | Config endpoint | Medium |
| 627 | `1,247` - Total events fallback | Static fallback | `audit_log` | Stats endpoint | High |
| 631 | `8` - Flagged events fallback | Static fallback | `audit_log` | Stats endpoint | High |
| 635 | `45` - Unique users fallback | Static fallback | `audit_log` | Stats endpoint | Medium |
| 639 | `12` - Systems accessed fallback | Static fallback | `audit_log` | Stats endpoint | Low |

### Current Data Flow Analysis

**Existing API Hooks:**
- [`useSuperAdminDashboard()`](src/app/hooks/useSuperAdmin.ts) - Returns `stats`
- [`useUsers()`](src/app/hooks/useSuperAdmin.ts) - Returns `users` array
- [`useClinics()`](src/app/hooks/useSuperAdmin.ts) - Returns `clinics` array
- [`useAuditLogs()`](src/app/hooks/useSuperAdmin.ts) - Returns `stats`
- [`useSecurityIncidents()`](src/app/hooks/useSuperAdmin.ts) - Returns `incidents` array

**Pattern:** Hooks exist but use fallback mock data when API returns empty. Pattern: `apiData.length > 0 ? apiData.map(...) : fallbackMockArray`

**Loading States:** Uses `statsLoading`, `usersLoading`, `clinicsLoading`, `auditLoading`, `incidentsLoading`

---

## SuperManager.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| 1-8 | Entire page is a stub | Stub page | Multiple | Multiple | Medium |

**Note:** This is a stub page with only header text. Needs complete implementation.

### Current Data Flow Analysis

**Existing API Hooks:** None

**Pattern:** No implementation - just static header text.

---

## Technician.tsx Dashboard

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| N/A | None - Uses API hooks | N/A | N/A | N/A | N/A |

### Current Data Flow Analysis

**Existing API Hooks:**
- [`useTechnicianDashboard()`](src/app/hooks/useDashboard.ts) - Returns `stats`, `taskQueue`, `loading`, `error`, `refetch`

**Pattern:** Fully integrated with API hooks. Component displays API data with proper loading/error states.

**Loading States:** Full loading spinner, error display with retry button, empty state messaging.

---

## Notifications.tsx Page

### Placeholder Content Inventory

| Line # | Placeholder Content | Type | Database Table | API Endpoint Needed | Priority |
|--------|---------------------|------|----------------|---------------------|----------|
| N/A | None - Uses API hooks | N/A | N/A | N/A | N/A |

### Current Data Flow Analysis

**Existing API Hooks:**
- [`useNotifications()`](src/app/hooks/useNotifications.ts) - Returns notifications, unreadCounts, isLoading, isRefreshing, error, and action methods

**Pattern:** Fully integrated with API hooks and notification service. Auto-refreshes every 60 seconds.

**Loading States:** Full loading state, empty state with icon, error display.

---

## Shared Placeholder Patterns

### Common Mock Data Patterns

1. **Patient Names Pattern**
   - Names used: John Smith, Maria Garcia, Robert Chen, David Lee, Sarah Johnson
   - Tables: `patients.legal_first_name`, `patients.legal_last_name`
   - Pattern: Replace with real patient queries

2. **Provider Names Pattern**
   - Names used: Dr. Johnson, Dr. Chen, Nurse Davis, Nurse Smith
   - Tables: `user.username` + `userrole` for role
   - Pattern: Replace with authenticated user lookups

3. **Statistics/Counts Pattern**
   - Hardcoded numbers: 12, 5, 3, 98%, 24, 45, 1234, 247
   - Pattern: All should come from aggregation queries on respective tables

4. **Status Badges Pattern**
   - Values: active, pending, completed, high-risk, cleared, not-cleared
   - Tables: Various status enum fields in `encounters`, `qa_review_queue`

5. **Date/Time Pattern**
   - Formats: "2 min ago", "5 days ago", "12/17/2024"
   - Pattern: Should use actual timestamps from database with client-side relative formatting

### Common Missing Features

1. **Loading States** - Some dashboards lack loading indicators
2. **Error Handling** - Some dashboards have no error state UI
3. **Empty States** - Some dashboards don't handle empty data gracefully
4. **Refresh Capability** - Some dashboards can't manually refresh data

---

## API Endpoint Recommendations

### Shared Endpoints (All Dashboards)

| Endpoint | Method | Description | Priority |
|----------|--------|-------------|----------|
| `/api/dashboard/stats` | GET | Role-based dashboard statistics | High |
| `/api/patients/search` | GET | Patient search with filters | High |
| `/api/encounters/active` | GET | Active encounters for user/role | High |

### Role-Specific Endpoints

#### Admin Role
| Endpoint | Method | Description | Priority |
|----------|--------|-------------|----------|
| `/api/admin/cases` | GET | Case management list | High |
| `/api/admin/training-modules` | GET | Training module overview | High |
| `/api/admin/expiring-credentials` | GET | Staff credentials expiring soon | High |
| `/api/admin/compliance-alerts` | GET | Pending compliance notifications | Medium |
| `/api/admin/metrics/patient-flow` | GET | Patient flow timing metrics | Medium |
| `/api/admin/metrics/by-site` | GET | Performance by establishment | Medium |
| `/api/admin/metrics/by-provider` | GET | Performance by provider | Medium |

#### Provider Role
| Endpoint | Method | Description | Priority |
|----------|--------|-------------|----------|
| `/api/provider/recent-encounters` | GET | User's recent encounters | High |
| `/api/provider/open-cases` | GET | Open cases assigned to user | High |

#### Registration Role
| Endpoint | Method | Description | Priority |
|----------|--------|-------------|----------|
| `/api/registration/pending` | GET | Pending registrations queue | High |
| `/api/registration/completed` | GET | Recently completed registrations | High |
| `/api/registration/stats` | GET | Registration statistics | Medium |

#### Security Officer Role
| Endpoint | Method | Description | Priority |
|----------|--------|-------------|----------|
| `/api/security/audit-logs` | GET | Recent audit logs with filters | High |
| `/api/security/anomalies` | GET | Detected security anomalies | High |
| `/api/security/stats` | GET | Security dashboard statistics | High |

#### Privacy Officer Role
| Endpoint | Method | Description | Priority |
|----------|--------|-------------|----------|
| `/api/privacy/pending-updates` | GET | Regulatory updates pending review | Medium |
| `/api/privacy/compliance-stats` | GET | Compliance metrics | High |
| `/api/privacy/training-stats` | GET | Training compliance overview | High |

#### Super Admin Role
| Endpoint | Method | Description | Priority |
|----------|--------|-------------|----------|
| `/api/superadmin/users` | GET | All system users with roles | High |
| `/api/superadmin/clinics` | GET | All clinic establishments | High |
| `/api/superadmin/incidents` | GET | Security incidents | High |
| `/api/superadmin/override-requests` | GET | Pending override requests | High |
| `/api/superadmin/ai-models` | GET | AI model configurations | Low |
| `/api/superadmin/policy-docs` | GET | Policy documents | Low |

---

## Priority Summary

### High Priority (Core Functionality - Visible to All Users)

| Dashboard | Item Count | Key Items |
|-----------|------------|-----------|
| Admin.tsx | 18 | Case data, training modules, credentials, OSHA logs, stats |
| ClinicalProvider.tsx | 5 | Recent encounters, open cases, patient data |
| Doctor.tsx | 1 | Entire page needs implementation |
| Registration.tsx | 6 | Pending queue, completed registrations, patient lookup |
| SecurityOfficer.tsx | 8 | Audit logs, anomalies, system status |
| SuperAdmin.tsx | 12 | Users, clinics, incidents, override requests |

### Medium Priority (Role-Specific Features)

| Dashboard | Item Count | Key Items |
|-----------|------------|-----------|
| Admin.tsx | 8 | Compliance alerts, clearance stats, site performance |
| PrivacyOfficer.tsx | 4 | Pending updates, AI recommendations |
| SuperAdmin.tsx | 5 | Organization config, audit statistics |
| SuperManager.tsx | 1 | Entire page needs implementation |

### Low Priority (Edge Cases, Rarely Accessed)

| Dashboard | Item Count | Key Items |
|-----------|------------|-----------|
| Admin.tsx | 3 | AI recommendations, MOI/NOI trends, section times |
| PrivacyOfficer.tsx | 2 | AI recommendations |
| SuperAdmin.tsx | 4 | AI models, policy documents |

---

## Implementation Recommendations

### Phase 1: Core Data Integration (High Priority)

1. **Implement shared patient/encounter queries**
   - Create reusable hooks for patient search
   - Create reusable hooks for encounter listing
   - Ensure proper error and loading states

2. **Complete stub pages**
   - Doctor.tsx needs full implementation
   - SuperManager.tsx needs full implementation

3. **Add API integration to mockless dashboards**
   - ClinicalProvider.tsx
   - Registration.tsx
   - SecurityOfficer.tsx
   - PrivacyOfficer.tsx

### Phase 2: Role-Specific Features (Medium Priority)

1. **Enhance Admin dashboard**
   - Connect compliance alerts to database
   - Implement metrics endpoints
   - Add site/provider filtering

2. **Complete Privacy Officer features**
   - Connect regulatory updates
   - Add compliance KPI tracking

3. **Enhance Super Admin**
   - Organization configuration management
   - Full audit log access

### Phase 3: Advanced Features (Low Priority)

1. **AI/ML Features**
   - AI recommendation system
   - Anomaly detection
   - Compliance prediction

2. **Analytics Features**
   - MOI/NOI trend analysis
   - Section timing analytics
   - Provider performance scoring

---

## Database Table Reference Quick Guide

| Feature Area | Primary Tables | Secondary Tables |
|--------------|----------------|------------------|
| User Management | `user`, `role`, `userrole` | `user_permission`, `user_device` |
| Patient Data | `patients` | `patient_addresses`, `patient_identifiers`, `patient_allergies`, `patient_medications` |
| Encounters | `encounters` | `encounter_observations`, `encounter_procedures`, `encounter_med_admin` |
| Training | `training_requirements`, `staff_training_records` | `training_reminders` |
| Compliance | `compliance_kpis`, `compliance_kpi_values`, `compliance_alerts` | `regulatory_updates` |
| Audit | `audit_log`, `auditevent` | `audit_exports`, `patient_access_log` |
| Notifications | `user_notification` | - |
| OSHA (READ ONLY) | `300_log`, `300a`, `301` | - |
| QA Review | `qa_review_queue`, `qa_bulk_actions` | `encounter_flags`, `flag_rules` |
| Establishments | `establishment`, `establishment_provider` | - |

---

## Conclusion

This audit identifies **~130 placeholder items** across 12 pages requiring database integration. The Manager, Technician, and Notifications pages already have full API integration and can serve as templates for implementing the remaining dashboards.

Key patterns to follow:
1. Use existing hooks as templates (e.g., `useManagerDashboard`, `useTechnicianDashboard`)
2. Implement loading, error, and empty states consistently
3. Use fallback patterns only during API development, not in production
4. Leverage shared components for common UI patterns
