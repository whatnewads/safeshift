# SafeShift EHR Bug/Issue Log

**Document Version:** 1.1
**Last Updated:** January 12, 2026
**Status:** Active

---

## Summary Statistics

| Severity | Total | Fixed | Open | Deferred |
|----------|-------|-------|------|----------|
| Critical | 0 | 0 | 0 | 0 |
| High | 3 | 0 | 3 | 0 |
| Medium | 6 | 2 | 1 | 3 |
| Low | 5 | 2 | 2 | 1 |
| **Total** | **14** | **4** | **6** | **4** |

---

## Critical Issues (0)

*No critical issues identified during MVP testing.*

---

## High Severity Issues (3)

### BUG-001: Patients API v1 Returns 501 Not Implemented

| Field | Value |
|-------|-------|
| **ID** | BUG-001 |
| **Severity** | High |
| **Status** | Open |
| **Component** | `api/v1/patients.php` |
| **Discovered** | 2026-01-12 |
| **Phase** | Frontend-Backend Integration Testing |

**Description:**
The Patients API v1 endpoints return HTTP 501 (Not Implemented) status codes. This affects patient listing, search, create, update, and delete operations via the v1 API.

**Affected Endpoints:**
- `GET /api/v1/patients`
- `GET /api/v1/patients/{id}`
- `GET /api/v1/patients/search`
- `POST /api/v1/patients`
- `PUT /api/v1/patients/{id}`
- `DELETE /api/v1/patients/{id}`

**Impact:**
- Patients page functionality degraded
- Frontend service calls receive 501 errors
- Patient data accessible only through legacy endpoints

**Root Cause:**
The `handlePatientsRoute()` function in the API router is not fully implemented.

**Workaround:**
Legacy patient endpoints may still be functional. Frontend uses fallback error handling.

**Recommendation:**
Implement the v1 Patients API handler using the existing `PatientRepository` and `PatientValidator` classes.

---

### BUG-002: Dashboard API v1 Returns 501 Not Implemented

| Field | Value |
|-------|-------|
| **ID** | BUG-002 |
| **Severity** | High |
| **Status** | Open |
| **Component** | `api/v1/dashboard.php` |
| **Discovered** | 2026-01-12 |
| **Phase** | Frontend-Backend Integration Testing |

**Description:**
Dashboard v1 API endpoints return HTTP 501. Dashboard statistics and activity data unavailable through v1 API.

**Affected Endpoints:**
- `GET /api/v1/dashboard/stats`
- `GET /api/v1/dashboard/activity`
- `GET /api/v1/dashboard/alerts`

**Impact:**
- Dashboard components show no data or errors
- Stats widgets non-functional via v1 API

**Root Cause:**
Dashboard v1 API handler not implemented.

**Workaround:**
Legacy endpoint `/api/dashboard-stats` is available and functional.

**Recommendation:**
Either implement v1 dashboard endpoints or update frontend to use legacy endpoint.

---

### BUG-003: AuditService searchLogs() SQL Syntax Error

| Field | Value |
|-------|-------|
| **ID** | BUG-003 |
| **Severity** | High |
| **Status** | Open |
| **Component** | `core/Services/AuditService.php` |
| **Discovered** | 2026-01-12 |
| **Phase** | Audit Logging Testing |

**Description:**
The `searchLogs()` method in AuditService throws a SQL syntax error when building the count query.

**Error Message:**
```
SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax... near ') as t'
```

**Impact:**
- `searchLogs()` API method fails
- Audit log search functionality degraded
- Core CRUD audit logging unaffected

**Root Cause:**
The count query construction logic uses incorrect string replacement, resulting in malformed SQL when converting the main query to a count subquery.

**Workaround:**
Direct database queries for audit logs work correctly. Use `AuditLogRepository` methods directly.

**Recommendation:**
Fix the count query construction in `searchLogs()`:
```php
// Current (broken):
$countSql = preg_replace('/SELECT .+ FROM/', 'SELECT COUNT(*) as total FROM', $sql);

// Fixed:
$countSql = "SELECT COUNT(*) as total FROM AuditEvent " . $whereClause;
```

---

## Medium Severity Issues (6)

### BUG-004: DOT Tests API Returns 501 Not Implemented

| Field | Value |
|-------|-------|
| **ID** | BUG-004 |
| **Severity** | Medium |
| **Status** | Open |
| **Component** | `api/v1/dot-tests.php` |
| **Discovered** | 2026-01-12 |
| **Phase** | API Endpoint Testing |

**Description:**
DOT Testing API endpoints return 501 Not Implemented.

**Affected Endpoints:**
- `GET /api/v1/dot-tests`
- `POST /api/v1/dot-tests`
- `PUT /api/v1/dot-tests/{id}`
- `GET /api/v1/dot-tests/deadline`
- `GET /api/v1/dot-tests/status/pending`

**Impact:**
- DOT testing workflow unavailable
- Chain of custody management not functional
- MRO verification features unavailable

**Recommendation:**
Implement DOT testing endpoints post-MVP using existing `DotTestRepository` and related services.

---

### BUG-005: OSHA API Returns 501 Not Implemented

| Field | Value |
|-------|-------|
| **ID** | BUG-005 |
| **Severity** | Medium |
| **Status** | Open |
| **Component** | `api/v1/osha.php` |
| **Discovered** | 2026-01-12 |
| **Phase** | API Endpoint Testing |

**Description:**
OSHA injury and reporting API endpoints return 501.

**Affected Endpoints:**
- `GET /api/v1/osha/injuries`
- `GET /api/v1/osha/300-log`
- `GET /api/v1/osha/300a-log`
- `GET /api/v1/osha/rates`

**Impact:**
- OSHA 300 Log management unavailable
- OSHA 300A summaries not generatable
- TRIR/DART rate calculations not accessible

**Recommendation:**
Implement OSHA endpoints post-MVP. Schema and entities already defined.

---

### BUG-006: Patient Encounters Endpoint Returns 501

| Field | Value |
|-------|-------|
| **ID** | BUG-006 |
| **Severity** | Medium |
| **Status** | Open |
| **Component** | `ViewModel/PatientViewModel.php` |
| **Discovered** | 2026-01-12 |
| **Phase** | User Flow Testing |

**Description:**
The endpoint to retrieve encounters for a specific patient returns 501.

**Affected Endpoint:**
- `GET /api/v1/patients/{id}/encounters`

**Impact:**
- Cannot view patient's encounter history from patient detail page
- Must navigate to encounters separately

**Recommendation:**
Add `getPatientEncounters()` method to PatientViewModel.

---

### BUG-007: Disclosures Endpoint Returns 501

| Field | Value |
|-------|-------|
| **ID** | BUG-007 |
| **Severity** | Medium |
| **Status** | Open |
| **Component** | `ViewModel/Encounter/EncounterViewModel.php` |
| **Discovered** | 2026-01-12 |
| **Phase** | User Flow Testing |

**Description:**
Disclosure management endpoints in the encounter workflow return 501.

**Impact:**
- Patient disclosure recording limited
- Disclosure tab functionality degraded

**Recommendation:**
Implement disclosure management in encounter workflow.

---

### BUG-008: Schema-Application Column Naming Mismatch

| Field | Value |
|-------|-------|
| **ID** | BUG-008 |
| **Severity** | Medium |
| **Status** | Deferred |
| **Component** | Database Schema / Repositories |
| **Discovered** | 2026-01-12 |
| **Phase** | Database Integrity Testing |

**Description:**
Database schema column names differ from application expectations.

**Affected Mappings:**
| Schema Column | Application Expects |
|--------------|---------------------|
| `legal_first_name` | `first_name` |
| `legal_last_name` | `last_name` |
| `dob` | `date_of_birth` |
| `sex_assigned_at_birth` | `gender` |
| `npi_provider` | `provider_id` |
| `site_id` | `clinic_id` |
| `occurred_on` | `encounter_date` |

**Impact:**
- Requires hydration/mapping in repositories
- Increases code complexity
- Risk of confusion during maintenance

**Current Mitigation:**
Repositories implement `hydrate*()` methods that handle mapping.

**Recommendation (Deferred):**
Consider standardizing naming conventions in a future schema migration.

---

### BUG-009: N+1 Query Patterns in Dashboard Statistics

| Field | Value |
|-------|-------|
| **ID** | BUG-009 |
| **Severity** | Medium |
| **Status** | ✅ Fixed |
| **Component** | Multiple Repositories |
| **Discovered** | 2026-01-12 |
| **Fixed** | 2026-01-12 |
| **Phase** | Database Integrity Testing |

**Description:**
Multiple repository methods execute separate queries that could be combined.

**Affected Methods:**
| Repository | Method | Queries | Status |
|------------|--------|---------|--------|
| `DoctorRepository` | `getDoctorStats()` | 4 → 2 | ✅ Fixed |
| `AdminRepository` | `getCaseStats()` | 4 → 2 | ✅ Fixed |
| `AdminRepository` | `getClearanceStats()` | 3 → 1 | ✅ Fixed |
| `AdminRepository` | `getAdminStats()` | 5+ | Deferred |
| `ClinicalProviderRepository` | `getProviderStats()` | 4 | Deferred |

**Resolution:**
Refactored `DoctorRepository.getDoctorStats()`, `AdminRepository.getCaseStats()`, and `AdminRepository.getClearanceStats()` to use consolidated queries with conditional aggregation:
```sql
SELECT
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
FROM encounters
```

---

## Low Severity Issues (4)

### BUG-010: Export Functionality Not Implemented

| Field | Value |
|-------|-------|
| **ID** | BUG-010 |
| **Severity** | Low |
| **Status** | ✅ Fixed |
| **Component** | `src/app/pages/Patients.tsx` |
| **Discovered** | 2026-01-12 |
| **Fixed** | 2026-01-12 |
| **Phase** | User Flow Testing |

**Description:**
Export button on Patients page has no functionality.

**Resolution:**
Implemented CSV export functionality in `Patients.tsx`:
- Added `handleExportCSV()` function that exports filtered patient data
- Exports include: Patient ID, Name, Employee ID, Employer, Last Visit, Chief Complaint, MOI/NOI, Outcome, Disposition, Provider, Clinic Location, Encounter Status, Reminder Sent
- Proper CSV escaping for commas, quotes, and newlines
- Auto-generates filename with current date

---

### BUG-011: CSP Headers Not Configured

| Field | Value |
|-------|-------|
| **ID** | BUG-011 |
| **Severity** | Low |
| **Status** | ✅ Fixed |
| **Component** | `includes/bootstrap.php` |
| **Discovered** | 2026-01-12 |
| **Fixed** | 2026-01-12 |
| **Phase** | Security Compliance Testing |

**Description:**
Content Security Policy headers were missing the `'unsafe-inline'` directive for script-src needed for React.

**Resolution:**
Enhanced CSP headers in `includes/bootstrap.php` with comprehensive policy:
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline';
style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:;
connect-src 'self' ws: wss:; media-src 'self' blob:; frame-ancestors 'none';
base-uri 'self'; form-action 'self';
```

---

### BUG-012: Log Rotation Not Automated

| Field | Value |
|-------|-------|
| **ID** | BUG-012 |
| **Severity** | Low |
| **Status** | Open |
| **Component** | Logging System |
| **Discovered** | 2026-01-12 |
| **Phase** | Security Compliance Testing |

**Description:**
Application logs do not have automated rotation configured.

**Impact:**
- Log files may grow unbounded
- Disk space consumption over time
- Log management complexity

**Recommendation:**
Configure logrotate or implement application-level log rotation:
```bash
/path/to/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
}
```

---

### BUG-013: Emergency Access Procedure Not Documented

| Field | Value |
|-------|-------|
| **ID** | BUG-013 |
| **Severity** | Low |
| **Status** | Open |
| **Component** | HIPAA Compliance Documentation |
| **Discovered** | 2026-01-12 |
| **Phase** | Security Compliance Testing |

**Description:**
HIPAA requires documented emergency access procedures ("break-glass"), but this procedure is not documented for the application.

**Impact:**
- HIPAA compliance gap
- Unclear procedure for emergency PHI access

**Recommendation:**
Document emergency access procedure including:
- When break-glass access is authorized
- How to request emergency access
- Audit trail requirements
- Post-emergency review process

### BUG-014: Duplicate Route Definition in App.tsx

| Field | Value |
|-------|-------|
| **ID** | BUG-014 |
| **Severity** | Low |
| **Status** | ✅ Fixed |
| **Component** | `src/app/App.tsx` |
| **Discovered** | 2026-01-12 |
| **Fixed** | 2026-01-12 |
| **Phase** | Code Review |

**Description:**
The `/notifications` route was defined twice in `App.tsx`, causing potential routing conflicts.

**Resolution:**
Removed the duplicate route definition (lines 271-280).

---

### BUG-015: Leading Wildcard LIKE Queries Cannot Use Indexes

| Field | Value |
|-------|-------|
| **ID** | BUG-015 |
| **Severity** | Low |
| **Status** | Deferred (Known Limitation) |
| **Component** | `model/Repositories/PatientRepository.php` |
| **Discovered** | 2026-01-12 |
| **Phase** | Performance Testing |

**Description:**
Patient search uses `LIKE '%term%'` pattern which cannot utilize database indexes, potentially causing full table scans.

**Affected Code:**
```php
$stmt = $this->pdo->prepare("SELECT * FROM patients WHERE
    legal_first_name LIKE :search OR
    legal_last_name LIKE :search OR
    ssn_last_four LIKE :search");
$stmt->execute(['search' => "%{$searchTerm}%"]);
```

**Impact:**
- Full table scan on large patient datasets
- Performance degradation with 10,000+ patients
- Currently acceptable for MVP scale

**Recommendation (Deferred):**
For production scale:
1. Implement MySQL FULLTEXT index for name search
2. Use exact match for SSN (no leading wildcard)
3. Consider Elasticsearch for large-scale deployments

**Why Deferred:**
- Current patient volume does not require optimization
- Change would require schema migration and index creation
- Risk of breaking existing search functionality outweighs benefit at MVP scale

---

## Issue Resolution History

| Date | Issue ID | Action | By |
|------|----------|--------|-----|
| 2026-01-12 | BUG-009 | Fixed N+1 queries in DoctorRepository.getDoctorStats(), AdminRepository.getCaseStats(), AdminRepository.getClearanceStats() | AI Assistant |
| 2026-01-12 | BUG-010 | Implemented CSV export in Patients.tsx | AI Assistant |
| 2026-01-12 | BUG-011 | Added comprehensive CSP headers to bootstrap.php | AI Assistant |
| 2026-01-12 | BUG-014 | Removed duplicate /notifications route in App.tsx | AI Assistant |

---

## Appendix: Issue Discovery Sources

| Source Document | Issues Found |
|-----------------|--------------|
| [`AUDIT_LOGGING_TEST_RESULTS.md`](AUDIT_LOGGING_TEST_RESULTS.md) | BUG-003 |
| [`API_ENDPOINT_TEST_RESULTS.md`](API_ENDPOINT_TEST_RESULTS.md) | BUG-001, BUG-002, BUG-004, BUG-005 |
| [`DATABASE_INTEGRITY_TEST_RESULTS.md`](DATABASE_INTEGRITY_TEST_RESULTS.md) | BUG-008, BUG-009 |
| [`FRONTEND_BACKEND_INTEGRATION_TEST.md`](FRONTEND_BACKEND_INTEGRATION_TEST.md) | BUG-001, BUG-002 (confirmed) |
| [`SECURITY_COMPLIANCE_TEST_RESULTS.md`](SECURITY_COMPLIANCE_TEST_RESULTS.md) | BUG-011, BUG-012, BUG-013 |
| [`USER_FLOW_TEST_RESULTS.md`](USER_FLOW_TEST_RESULTS.md) | BUG-006, BUG-007, BUG-010 |

---

**Document Control:**
- Version: 1.0
- Created: January 12, 2026
- Status: Active tracking document
- Next Review: January 19, 2026
