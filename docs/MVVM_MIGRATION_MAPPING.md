# MVVM Migration Mapping Document

## Overview

This document provides a comprehensive analysis of the `/clinician/` and `/dashboards/` directories for migration to the target MVVM architecture.

### Target MVVM Structure
```
root/
├── views/           # Presentation layer (HTML, forms, display logic)
├── viewmodels/      # Business logic layer (validation, data transformation, session handling)
├── models/          # Repository layer (database interactions only)
└── includes/        # Shared components (header.php, footer.php, config.php)
```

### Existing Structure
```
root/
├── View/            # Partially populated view structure
├── ViewModel/       # 10 existing ViewModels
├── model/           # Only contains README.md
├── includes/        # Contains header.php with centralized session/CSRF
├── clinician/       # 4 mixed frontend/backend files
└── dashboards/      # 6 root files + 4 sub-dashboard directories
```

---

## Section 1: /clinician/ Directory Analysis

### 1.1 File Inventory

| File | Lines | Purpose | Current State |
|------|-------|---------|---------------|
| `clinical-notes.php` | 1544 | SOAP note editor for clinical documentation | Mixed HTML + backend auth |
| `ems-epcr.php` | 1227 | ePCR (Electronic Patient Care Report) multi-tab form | Mixed HTML + backend auth + audit logging |
| `patient-records.php` | 1734 | Comprehensive patient EHR interface with tabs | Mixed HTML + backend auth + mock data |
| `patient-search.php` | 861 | Patient search form with filters and results table | Mixed HTML + backend auth |

### 1.2 Detailed File Analysis

#### 1.2.1 clinical-notes.php

**Current Backend Code (Lines 1-34):**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

// Role check
$allowedRoles = ['Clinician', '1clinician', 'pclinician', 'dclinician', 'Admin', 'Manager'];
if (!in_array($role, $allowedRoles)) {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();
```

**Frontend Elements:**
- Patient sidebar (patient list with search filter)
- SOAP note editor (Chief Complaint, Subjective, Objective, Assessment, Plan)
- Templates modal for note templates
- Rich text editing interface
- Note history sidebar
- ICD-10/CPT code selection

**Database Interactions:**
- Currently uses mock data
- Needs: PatientRepository, ClinicalNotesRepository, TemplateRepository

**Security Patterns:**
- ✅ Auth check via `require_login()`
- ✅ Role validation against array
- ✅ CSRF token generation
- ✅ `htmlspecialchars()` on output (sporadically)

---

#### 1.2.2 ems-epcr.php

**Current Backend Code (Lines 1-75):**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;
use function App\log\audit;
use function App\log\file_log;

require_login();
$user = current_user();
$role = user_primary_role();
$csrf_token = get_csrf_token();

// Security headers
header("Content-Security-Policy: default-src 'self'; ...");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Audit logging
audit('page_access', 'ems_epcr_page', [
    'user_id' => $user['user_id'] ?? 'unknown',
    'role' => $role,
    'timestamp' => date('c')
]);

// Patient fetch placeholder
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;
// TODO: Fetch patient data from database
$patient_data = null;
```

**Frontend Elements:**
- 8-tab ePCR form:
  1. Incident (dispatch info, location, CAD number)
  2. Patient (demographics, insurance)
  3. Assessment (chief complaint, vitals, primary impression)
  4. Vitals (vital signs recording with timestamps)
  5. Treatment (interventions, medications, procedures)
  6. Narrative (freeform documentation)
  7. Disposition (transport, destination, signatures)
  8. Signatures (crew, patient, witness)
- Auto-save functionality
- Form validation
- Save/Submit buttons

**Database Interactions:**
- Placeholder for patient fetch
- Needs: PatientRepository, EPCRRepository, VitalsRepository, TreatmentRepository

**Security Patterns:**
- ✅ Full CSP headers
- ✅ Auth check
- ✅ Audit logging for page access
- ✅ CSRF token in form
- ✅ `intval()` for patient_id
- ✅ `htmlspecialchars()` on output

---

#### 1.2.3 patient-records.php

**Current Backend Code (Lines 1-56):**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

// Role-based access check
$allowedRoles = ['Clinician', '1clinician', 'pclinician', 'dclinician', 'Admin', 'Manager'];
if (!in_array($role, $allowedRoles)) {
    header('Location: /errors/403.php');
    exit;
}

$csrf_token = get_csrf_token();
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Mock patient data structure
$patient = [
    'id' => 12345,
    'first_name' => 'John',
    // ... extensive mock data
];
```

**Frontend Elements:**
- Tabbed EHR interface:
  1. Overview (patient summary, demographics)
  2. Encounters (visit history)
  3. Vitals (vital signs trends/charts)
  4. Medications (current/past medications)
  5. Lab Results (lab values with reference ranges)
  6. Radiology (imaging studies)
  7. Documents (uploaded documents)
  8. Orders (current orders)
  9. Notes (clinical notes history)
  10. Billing (billing codes, claims)
  11. Forms (regulatory forms - OSHA, DOT)
- Quick action buttons (New Encounter, Add Note, Order Labs)
- Patient header with demographics
- Alerts/Allergies display

**Database Interactions:**
- Uses extensive mock data
- Needs: PatientRepository, EncounterRepository, VitalsRepository, MedicationRepository, LabResultRepository, ImagingRepository, DocumentRepository, OrderRepository, ClinicalNotesRepository, BillingRepository

**Security Patterns:**
- ✅ Auth check
- ✅ Role validation
- ✅ CSRF token
- ✅ `intval()` for patient_id
- ❌ Some outputs lack `htmlspecialchars()`

---

#### 1.2.4 patient-search.php

**Current Backend Code (Lines 1-31):**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

// Simple role check - could be more granular
if (!$role) {
    header('Location: /errors/403.php');
    exit;
}

$csrf_token = get_csrf_token();
```

**Frontend Elements:**
- Search form with filters:
  - Patient name
  - DOB range
  - MRN/SSN
  - Status (active/inactive/all)
  - Insurance type
  - Employer
- Results table with:
  - Patient name (linked)
  - MRN
  - DOB
  - Phone
  - Last visit
  - Status badge
- Pagination
- Quick actions (View, New Encounter)

**Database Interactions:**
- Uses mock patient list
- Needs: PatientRepository with search capabilities

**Security Patterns:**
- ✅ Auth check
- ✅ Basic role check
- ✅ CSRF token
- ⚠️ No input sanitization shown for search parameters

---

## Section 2: /dashboards/ Directory Analysis

### 2.1 File Inventory

#### Root-Level Files

| File | Lines | Purpose | Current State |
|------|-------|---------|---------------|
| `audit-logs.php` | ~1200 | HIPAA-compliant audit log viewer | Mixed HTML + backend auth |
| `compliance-monitor.php` | ~1100 | KPI monitoring dashboard | Mixed HTML + backend auth |
| `dashboard_admin.php` | ~800 | Admin control panel | Mixed HTML + backend auth |
| `dashboard_manager.php` | ~900 | Manager oversight dashboard | Mixed HTML + backend auth |
| `qa-review-mobile.php` | ~700 | PWA-enabled mobile QA review | Mixed HTML + backend auth |
| `regulatory-updates.php` | ~600 | Document upload/regulatory processing | Mixed HTML + backend auth |

#### Sub-Dashboard Directories

| Directory | Index File | Components | CSS/JS |
|-----------|------------|------------|--------|
| `1clinician/` | `index.php` | 0 components | `1clinician.css`, `1clinician.js` |
| `dclinician/` | `index.php` | 11 components | `dclinician.css`, `dclinician.js` |
| `pclinician/` | `index.php` | 14 components | `pclinician.css`, `pclinician.js` |
| `tadmin/` | `index.php` | 11 components | `tadmin.css`, `tadmin.js` |

### 2.2 Detailed File Analysis

#### 2.2.1 audit-logs.php

**Current Backend Code:**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

// Admin-only access
$allowedRoles = ['Admin', 'cadmin', 'tadmin'];
if (!in_array($role, $allowedRoles)) {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();
```

**Frontend Elements:**
- Audit log search/filter form:
  - Date range
  - User filter
  - Action type
  - Category
  - Severity
- Log entries table with pagination
- Export functionality (CSV, PDF)
- Real-time refresh option
- Statistics charts (log activity trends)

**Database Interactions:**
- Needs: AuditLogRepository

**Security Patterns:**
- ✅ Admin-only access restriction
- ✅ Auth check
- ✅ CSRF token

---

#### 2.2.2 compliance-monitor.php

**Current Backend Code:**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

$allowedRoles = ['Admin', 'Manager', 'pclinician', 'cadmin', 'tadmin'];
if (!in_array($role, $allowedRoles)) {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();
```

**Frontend Elements:**
- Tabbed compliance interface:
  1. HIPAA compliance metrics
  2. OSHA compliance metrics
  3. DOT compliance metrics
  4. Clinical quality metrics
- KPI cards with trend indicators
- Compliance score gauges
- Upcoming deadlines list
- Non-compliance alerts
- Action required items

**Database Interactions:**
- Needs: ComplianceRepository, TrainingRepository, CredentialRepository, AuditRepository

**Security Patterns:**
- ✅ Multi-role access control
- ✅ Auth check
- ✅ CSRF token

---

#### 2.2.3 dashboard_admin.php

**Current Backend Code:**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;
use function App\log\audit;

require_login();
$user = current_user();
$role = user_primary_role();

$allowedRoles = ['Admin', 'cadmin', 'tadmin'];
if (!in_array($role, $allowedRoles)) {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();

audit('admin_dashboard_access', 'dashboard', [
    'user_id' => $user['user_id'] ?? 'unknown',
    'role' => $role,
    'timestamp' => date('c')
]);
```

**Frontend Elements:**
- Statistics cards:
  - Total users
  - Active sessions
  - Daily logins
  - Open tickets
- Feature grid (links to admin tools):
  - User management
  - System settings
  - Security logs
  - Database tools
- Activity log (recent admin actions)
- System health indicators

**Database Interactions:**
- Needs: UserRepository, SessionRepository, SystemStatsRepository

**Security Patterns:**
- ✅ Admin-only access
- ✅ Audit logging
- ✅ Auth check
- ✅ CSRF token

---

#### 2.2.4 dashboard_manager.php

**Current Backend Code:**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

$allowedRoles = ['Manager', 'Admin', 'dclinician'];
if (!in_array($role, $allowedRoles)) {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();
```

**Frontend Elements:**
- High-risk patient flagging section
- Training compliance table
- QA review queue
- Staff performance metrics
- Shift coverage overview
- Pending approvals list

**Database Interactions:**
- Needs: PatientRepository (high-risk), TrainingRepository, QARepository, StaffRepository

**Security Patterns:**
- ✅ Manager/Admin access
- ✅ Auth check
- ✅ CSRF token

---

#### 2.2.5 qa-review-mobile.php

**Current Backend Code:**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

$allowedRoles = ['Manager', 'Admin', 'QA', 'dclinician'];
if (!in_array($role, $allowedRoles)) {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();
```

**Frontend Elements:**
- PWA manifest integration
- Mobile-optimized QA review cards
- Swipe actions for approve/reject
- Offline capability indicators
- Touch-friendly filters
- Quick scoring interface

**Database Interactions:**
- Needs: QAReviewRepository, EncounterRepository

**Security Patterns:**
- ✅ Role-based access
- ✅ Auth check
- ✅ CSRF token
- ✅ PWA security headers

---

#### 2.2.6 regulatory-updates.php

**Current Backend Code:**
```php
require_once __DIR__ . '/../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

$allowedRoles = ['Admin', 'pclinician', 'cadmin'];
if (!in_array($role, $allowedRoles)) {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();
```

**Frontend Elements:**
- Document upload interface
- Regulatory category selector
- Document processing status
- Recent updates list
- Notification preferences
- Distribution settings

**Database Interactions:**
- Needs: DocumentRepository, RegulatoryRepository, NotificationRepository

**Security Patterns:**
- ✅ Role-based access
- ✅ Auth check
- ✅ CSRF token
- ⚠️ File upload needs validation

---

### 2.3 Sub-Dashboard Analysis

#### 2.3.1 1clinician/ (Line Clinician Dashboard)

**index.php Backend:**
```php
require_once __DIR__ . '/../../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

if ($role !== '1clinician') {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();
```

**Components:** None (monolithic index.php)

**Frontend Elements:**
- Daily schedule
- Patient queue
- Quick actions (New ePCR, Search Patient)
- Shift status
- Recent encounters list

---

#### 2.3.2 dclinician/ (Department Clinician Dashboard)

**index.php Backend:**
```php
require_once __DIR__ . '/../../includes/bootstrap.php';

use function App\auth\require_login;
use function App\auth\current_user;
use function App\auth\user_primary_role;
use function App\auth\get_csrf_token;

require_login();
$user = current_user();
$role = user_primary_role();

if ($role !== 'dclinician') {
    header('Location: /errors/403.php');
    exit;
}
$csrf_token = get_csrf_token();
```

**Components (11):**
| Component | Purpose |
|-----------|---------|
| `header_user_greeting.php` | User welcome message |
| `kpi_avg_response_time.php` | Response time KPI |
| `kpi_expiring_credentials.php` | Credential expiration alerts |
| `kpi_incident_trend.php` | Incident trend chart |
| `kpi_overdue_trainings.php` | Training overdue alerts |
| `kpi_reports_ontime.php` | Report timeliness metrics |
| `performance_metrics.php` | Staff performance data |
| `report_oversight.php` | Report review section |
| `timeline_view.php` | Activity timeline |
| `todo_alerts.php` | Todo/alert list |
| `training_management.php` | Training assignments |
| `workforce_panel.php` | Staff management |

---

#### 2.3.3 pclinician/ (Privacy Clinician Dashboard)

**index.php Backend:** Same pattern with `pclinician` role check

**Components (14):**
| Component | Purpose |
|-----------|---------|
| `compliance_recommendations.php` | Compliance suggestions |
| `header_user_greeting.php` | User welcome |
| `kpi_avg_response_time.php` | Response time KPI |
| `kpi_expiring_credentials.php` | Credential alerts |
| `kpi_incident_trend.php` | Incident trends |
| `privacy_analytics.php` | Privacy metrics |
| `privacy_credential_tracker.php` | Credential tracking |
| `privacy_osha_integration.php` | OSHA integration |
| `privacy_phi_log.php` | PHI access log |
| `privacy_policy_repo.php` | Policy repository |
| `registrar_updates.php` | Registry updates |
| `report_oversight.php` | Report review |
| `sidebar.php` | Navigation sidebar |
| `training_management.php` | Training management |
| `workforce_panel.php` | Staff panel |

---

#### 2.3.4 tadmin/ (Technical Admin Dashboard)

**index.php Backend:** Same pattern with `tadmin` role check

**Components (11):**
| Component | Purpose |
|-----------|---------|
| `access_audit_logging.php` | Audit log viewer |
| `compliance_recommendations.php` | Compliance suggestions |
| `header_user_greeting.php` | User welcome |
| `kpi_security_overview.php` | Security metrics |
| `registrar_updates.php` | Registry updates |
| `security_compliance_dashboard.php` | Security dashboard |
| `security_incident_management.php` | Incident management |
| `security_reporting.php` | Security reports |
| `sidebar.php` | Navigation |
| `todo_alerts.php` | Todo/alerts |
| `training_verification.php` | Training verification |

---

## Section 3: Migration Mapping

### 3.1 Clinician Files Migration

#### clinical-notes.php

| Current | Target | Notes |
|---------|--------|-------|
| `/clinician/clinical-notes.php` | `/views/clinician/clinical-notes.php` | HTML only |
| Backend auth/role logic | `ClinicianViewModel::initClinicalNotesPage()` | Auth, role check |
| Patient list fetch | `PatientRepository::getClinicianPatients()` | New Model method |
| Templates fetch | `TemplateRepository::getNoteTemplates()` | New Model method |
| CSRF token | `includes/header.php` | Centralized |

**ClinicianViewModel Methods Needed:**
```php
public function initClinicalNotesPage(): array;
public function validateNoteInput(array $data): array;
public function prepareNoteForSave(array $data): array;
public function getNoteTemplates(): array;
public function getPatientSidebar(int $userId): array;
```

---

#### ems-epcr.php

| Current | Target | Notes |
|---------|--------|-------|
| `/clinician/ems-epcr.php` | `/views/clinician/ems-epcr.php` | HTML only |
| Backend auth/audit logic | `EPCRViewModel::initEPCRPage()` | Auth, audit |
| Patient fetch | `PatientRepository::getById()` | Existing or new |
| ePCR save | `EPCRRepository::save()` | New Model method |
| CSRF token | `includes/header.php` | Centralized |

**EPCRViewModel Methods Needed:**
```php
public function initEPCRPage(?int $patientId): array;
public function validateEPCRSection(string $section, array $data): array;
public function prepareEPCRForSave(array $data): array;
public function getVitalsTrends(int $patientId): array;
public function getMedicationOptions(): array;
public function getProcedureOptions(): array;
```

---

#### patient-records.php

| Current | Target | Notes |
|---------|--------|-------|
| `/clinician/patient-records.php` | `/views/clinician/patient-records.php` | HTML only |
| Backend auth logic | `PatientRecordsViewModel::initPatientRecordsPage()` | Auth, role |
| Patient data fetch | `PatientRepository::getFullRecord()` | New comprehensive method |
| Tab data fetch | Various repositories | Split by data type |
| CSRF token | `includes/header.php` | Centralized |

**PatientRecordsViewModel Methods Needed:**
```php
public function initPatientRecordsPage(int $patientId): array;
public function getPatientOverview(int $patientId): array;
public function getPatientEncounters(int $patientId): array;
public function getPatientVitals(int $patientId): array;
public function getPatientMedications(int $patientId): array;
public function getPatientLabResults(int $patientId): array;
public function getPatientDocuments(int $patientId): array;
public function getPatientBilling(int $patientId): array;
```

---

#### patient-search.php

| Current | Target | Notes |
|---------|--------|-------|
| `/clinician/patient-search.php` | `/views/clinician/patient-search.php` | HTML only |
| Backend auth logic | `PatientSearchViewModel::initSearchPage()` | Auth |
| Search execution | `PatientRepository::search()` | With sanitized params |
| CSRF token | `includes/header.php` | Centralized |

**PatientSearchViewModel Methods Needed:** (Already exists - extend)
```php
public function initSearchPage(): array;
public function executeSearch(array $sanitizedFilters): array;
public function prepareSearchResults(array $results): array;
public function getFilterOptions(): array;
```

---

### 3.2 Dashboard Files Migration

#### audit-logs.php

| Current | Target | Notes |
|---------|--------|-------|
| `/dashboards/audit-logs.php` | `/views/dashboards/audit-logs.php` | HTML only |
| Backend auth logic | `AuditLogsViewModel::initPage()` | Admin-only |
| Log fetch | `AuditLogRepository::search()` | New Model |
| Export | `AuditLogRepository::export()` | New Model |

**AuditLogsViewModel Methods Needed:**
```php
public function initPage(): array;
public function searchLogs(array $filters): array;
public function exportLogs(string $format, array $filters): string;
public function getLogStatistics(array $dateRange): array;
```

---

#### compliance-monitor.php

| Current | Target | Notes |
|---------|--------|-------|
| `/dashboards/compliance-monitor.php` | `/views/dashboards/compliance-monitor.php` | HTML only |
| Backend auth logic | `ComplianceViewModel::initPage()` | Multi-role |
| Metrics fetch | Various compliance repositories | New Models |

**ComplianceViewModel Methods Needed:**
```php
public function initPage(): array;
public function getHIPAAMetrics(): array;
public function getOSHAMetrics(): array;
public function getDOTMetrics(): array;
public function getClinicalQualityMetrics(): array;
public function getComplianceAlerts(): array;
public function getUpcomingDeadlines(): array;
```

---

#### dashboard_admin.php

| Current | Target | Notes |
|---------|--------|-------|
| `/dashboards/dashboard_admin.php` | `/views/dashboards/dashboard_admin.php` | HTML only |
| Backend auth/audit logic | `AdminDashboardViewModel::initPage()` | Admin-only + audit |
| Stats fetch | Various admin repositories | New Models |

**AdminDashboardViewModel Methods Needed:**
```php
public function initPage(): array;
public function getUserStats(): array;
public function getSystemHealth(): array;
public function getRecentActivity(): array;
public function getSecurityAlerts(): array;
```

---

#### dashboard_manager.php

| Current | Target | Notes |
|---------|--------|-------|
| `/dashboards/dashboard_manager.php` | `/views/dashboards/dashboard_manager.php` | HTML only |
| Backend auth logic | `ManagerDashboardViewModel::initPage()` | Manager role |
| Data fetch | Various manager repositories | New Models |

**ManagerDashboardViewModel Methods Needed:**
```php
public function initPage(): array;
public function getHighRiskPatients(): array;
public function getTrainingCompliance(): array;
public function getQAQueue(): array;
public function getStaffPerformance(): array;
public function getShiftCoverage(): array;
public function getPendingApprovals(): array;
```

---

#### qa-review-mobile.php

| Current | Target | Notes |
|---------|--------|-------|
| `/dashboards/qa-review-mobile.php` | `/views/dashboards/qa-review-mobile.php` | HTML only |
| Backend auth logic | `QAReviewViewModel::initMobilePage()` | QA role |
| Review data | `QAReviewRepository` | New Model |

**QAReviewViewModel Methods Needed:**
```php
public function initMobilePage(): array;
public function getReviewQueue(): array;
public function submitReview(int $reviewId, array $data): array;
public function getOfflineData(): array;
```

---

#### regulatory-updates.php

| Current | Target | Notes |
|---------|--------|-------|
| `/dashboards/regulatory-updates.php` | `/views/dashboards/regulatory-updates.php` | HTML only |
| Backend auth logic | `RegulatoryViewModel::initPage()` | Admin/Privacy |
| Document upload | `DocumentRepository::upload()` | With validation |

**RegulatoryViewModel Methods Needed:**
```php
public function initPage(): array;
public function getRecentUpdates(): array;
public function validateUpload(array $fileData): array;
public function processDocument(array $data): array;
public function getCategories(): array;
```

---

### 3.3 Sub-Dashboard Migration

#### 1clinician/index.php → views/dashboards/1clinician/index.php

Uses existing `OneClinicianDashboardViewModel`

---

#### dclinician/index.php → views/dashboards/dclinician/index.php

**New DClinicianDashboardViewModel Methods:**
```php
public function initPage(): array;
public function getKPIs(): array;
public function getWorkforceData(): array;
public function getTrainingData(): array;
public function getReportOversight(): array;
public function getTimeline(): array;
```

**Components → View Partials:**
- All 11 components move to `/views/dashboards/dclinician/components/`
- Remove backend logic from components
- Components receive data via parent view variables

---

#### pclinician/index.php → views/dashboards/pclinician/index.php

**New PClinicianDashboardViewModel Methods:**
```php
public function initPage(): array;
public function getPrivacyAnalytics(): array;
public function getPHILog(): array;
public function getCredentialTracking(): array;
public function getComplianceRecommendations(): array;
public function getOSHAIntegration(): array;
```

**Components → View Partials:**
- All 14 components move to `/views/dashboards/pclinician/components/`

---

#### tadmin/index.php → views/dashboards/tadmin/index.php

**New TAdminDashboardViewModel Methods:**
```php
public function initPage(): array;
public function getSecurityOverview(): array;
public function getAccessAuditLogs(): array;
public function getSecurityIncidents(): array;
public function getSecurityReports(): array;
public function getTrainingVerification(): array;
```

**Components → View Partials:**
- All 11 components move to `/views/dashboards/tadmin/components/`

---

## Section 4: Security Requirements

### 4.1 CSRF Validation Requirements

| File | Forms Needing CSRF |
|------|-------------------|
| `clinical-notes.php` | Note save form, template form |
| `ems-epcr.php` | All 8 tab forms, auto-save, final submit |
| `patient-records.php` | Quick action forms, document upload |
| `patient-search.php` | Search form (GET → POST recommended) |
| `audit-logs.php` | Export form, filter form |
| `compliance-monitor.php` | Action items form |
| `dashboard_admin.php` | Admin action forms |
| `dashboard_manager.php` | Approval forms, assignment forms |
| `qa-review-mobile.php` | Review submission forms |
| `regulatory-updates.php` | Document upload form, settings form |

### 4.2 Input Sanitization Requirements

| File | Inputs Requiring Sanitization |
|------|------------------------------|
| `clinical-notes.php` | Note content (XSS), patient_id (int), template_id (int) |
| `ems-epcr.php` | All form fields, patient_id (int), narrative (XSS) |
| `patient-records.php` | patient_id (int), document uploads |
| `patient-search.php` | name (string), MRN (alphanum), DOB (date), SSN (format) |
| `audit-logs.php` | date_range (date), user_id (int), action_type (enum) |
| `compliance-monitor.php` | date_range (date), category (enum) |
| `dashboard_admin.php` | user_id (int), action (enum) |
| `dashboard_manager.php` | employee_id (int), approval_id (int) |
| `qa-review-mobile.php` | review_id (int), score (int 1-5), comments (XSS) |
| `regulatory-updates.php` | file upload (type/size), category (enum), title (XSS) |

### 4.3 Output Escaping Requirements

**All dynamic content must use:**
```php
htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
```

**Helper function in header.php:**
```php
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
```

| File | Output Escaping Status |
|------|----------------------|
| `clinical-notes.php` | ⚠️ Inconsistent - needs review |
| `ems-epcr.php` | ✅ Good coverage |
| `patient-records.php` | ⚠️ Mock data not escaped |
| `patient-search.php` | ⚠️ Results need escaping |
| `audit-logs.php` | ⚠️ Log entries need escaping |
| `compliance-monitor.php` | ⚠️ Needs review |
| `dashboard_admin.php` | ⚠️ Needs review |
| `dashboard_manager.php` | ⚠️ Needs review |
| `qa-review-mobile.php` | ⚠️ Needs review |
| `regulatory-updates.php` | ⚠️ Document titles need escaping |

### 4.4 Session/Permission Checks

| File | Required Roles | Permission Check |
|------|---------------|-----------------|
| `clinical-notes.php` | Clinician, 1clinician, pclinician, dclinician, Admin, Manager | Array check |
| `ems-epcr.php` | Any authenticated (implicit from require_login) | Basic |
| `patient-records.php` | Clinician, 1clinician, pclinician, dclinician, Admin, Manager | Array check |
| `patient-search.php` | Any authenticated | Basic role check |
| `audit-logs.php` | Admin, cadmin, tadmin | Admin-only |
| `compliance-monitor.php` | Admin, Manager, pclinician, cadmin, tadmin | Multi-role |
| `dashboard_admin.php` | Admin, cadmin, tadmin | Admin-only + audit |
| `dashboard_manager.php` | Manager, Admin, dclinician | Manager level |
| `qa-review-mobile.php` | Manager, Admin, QA, dclinician | QA access |
| `regulatory-updates.php` | Admin, pclinician, cadmin | Privacy/Admin |
| `1clinician/index.php` | 1clinician | Exact role match |
| `dclinician/index.php` | dclinician | Exact role match |
| `pclinician/index.php` | pclinician | Exact role match |
| `tadmin/index.php` | tadmin | Exact role match |

---

## Section 5: Proposed File Structure

### 5.1 Before Migration

```
root/
├── clinician/
│   ├── clinical-notes.php          # 1544 lines (mixed)
│   ├── ems-epcr.php                 # 1227 lines (mixed)
│   ├── patient-records.php          # 1734 lines (mixed)
│   └── patient-search.php           # 861 lines (mixed)
├── dashboards/
│   ├── audit-logs.php               # ~1200 lines (mixed)
│   ├── compliance-monitor.php       # ~1100 lines (mixed)
│   ├── dashboard_admin.php          # ~800 lines (mixed)
│   ├── dashboard_manager.php        # ~900 lines (mixed)
│   ├── qa-review-mobile.php         # ~700 lines (mixed)
│   ├── regulatory-updates.php       # ~600 lines (mixed)
│   ├── 1clinician/
│   │   ├── index.php                # Mixed
│   │   ├── css/1clinician.css
│   │   └── js/1clinician.js
│   ├── dclinician/
│   │   ├── index.php                # Mixed
│   │   ├── components/              # 11 files (mixed)
│   │   ├── css/dclinician.css
│   │   └── js/dclinician.js
│   ├── pclinician/
│   │   ├── index.php                # Mixed
│   │   ├── components/              # 14 files (mixed)
│   │   ├── css/pclinician.css
│   │   └── js/pclinician.js
│   └── tadmin/
│       ├── index.php                # Mixed
│       ├── components/              # 11 files (mixed)
│       ├── css/tadmin.css
│       └── js/tadmin.js
├── View/                            # Partially populated
├── ViewModel/
│   ├── DashboardStatsViewModel.php
│   ├── EPCRViewModel.php
│   ├── LoginViewModel.php
│   ├── NotificationsViewModel.php
│   ├── OneClinicianDashboardViewModel.php
│   ├── PatientSearchViewModel.php
│   ├── PatientVitalsViewModel.php
│   ├── RecentPatientsViewModel.php
│   └── TwoFactorViewModel.php
├── model/
│   └── README.md                    # Documentation only
└── includes/
    ├── header.php                   # Centralized session/CSRF
    ├── footer.php
    └── config.php
```

### 5.2 After Migration

```
root/
├── views/
│   ├── clinician/
│   │   ├── clinical-notes.php       # HTML only
│   │   ├── ems-epcr.php             # HTML only
│   │   ├── patient-records.php      # HTML only
│   │   └── patient-search.php       # HTML only
│   ├── dashboards/
│   │   ├── audit-logs.php           # HTML only
│   │   ├── compliance-monitor.php   # HTML only
│   │   ├── dashboard-admin.php      # HTML only
│   │   ├── dashboard-manager.php    # HTML only
│   │   ├── qa-review-mobile.php     # HTML only
│   │   ├── regulatory-updates.php   # HTML only
│   │   ├── 1clinician/
│   │   │   └── index.php            # HTML only
│   │   ├── dclinician/
│   │   │   ├── index.php            # HTML only
│   │   │   └── components/          # HTML partials only
│   │   ├── pclinician/
│   │   │   ├── index.php            # HTML only
│   │   │   └── components/          # HTML partials only
│   │   └── tadmin/
│   │       ├── index.php            # HTML only
│   │       └── components/          # HTML partials only
│   └── includes/
│       ├── header.php               # Moved from root/includes
│       └── footer.php               # Moved from root/includes
├── viewmodels/
│   ├── ClinicianViewModel.php       # NEW
│   ├── ClinicalNotesViewModel.php   # NEW
│   ├── DashboardStatsViewModel.php  # EXISTS - extend
│   ├── EPCRViewModel.php            # EXISTS - extend
│   ├── PatientRecordsViewModel.php  # NEW
│   ├── PatientSearchViewModel.php   # EXISTS - extend
│   ├── AuditLogsViewModel.php       # NEW
│   ├── ComplianceViewModel.php      # NEW
│   ├── AdminDashboardViewModel.php  # NEW
│   ├── ManagerDashboardViewModel.php # NEW
│   ├── QAReviewViewModel.php        # NEW
│   ├── RegulatoryViewModel.php      # NEW
│   ├── DClinicianDashboardViewModel.php # NEW
│   ├── PClinicianDashboardViewModel.php # NEW
│   └── TAdminDashboardViewModel.php # NEW
├── models/
│   ├── repositories/
│   │   ├── PatientRepository.php    # NEW
│   │   ├── EncounterRepository.php  # NEW
│   │   ├── ClinicalNotesRepository.php # NEW
│   │   ├── EPCRRepository.php       # NEW
│   │   ├── VitalsRepository.php     # NEW
│   │   ├── MedicationRepository.php # NEW
│   │   ├── LabResultRepository.php  # NEW
│   │   ├── DocumentRepository.php   # NEW
│   │   ├── AuditLogRepository.php   # NEW
│   │   ├── ComplianceRepository.php # NEW
│   │   ├── TrainingRepository.php   # NEW
│   │   ├── QAReviewRepository.php   # NEW
│   │   └── UserRepository.php       # NEW
│   └── services/
│       ├── AuthorizationService.php # NEW
│       ├── ValidationService.php    # NEW
│       └── EncryptionService.php    # NEW
├── includes/
│   └── config.php
├── assets/
│   ├── css/
│   │   ├── dashboards/
│   │   │   ├── 1clinician.css       # Moved
│   │   │   ├── dclinician.css       # Moved
│   │   │   ├── pclinician.css       # Moved
│   │   │   └── tadmin.css           # Moved
│   │   └── clinician/
│   │       └── ... (existing)
│   └── js/
│       ├── dashboards/
│       │   ├── 1clinician.js        # Moved
│       │   ├── dclinician.js        # Moved
│       │   ├── pclinician.js        # Moved
│       │   └── tadmin.js            # Moved
│       └── clinician/
│           └── ... (existing)
└── clinician/                       # DEPRECATED - redirect only
    └── ... (redirect stubs to new views)
```

---

## Section 6: Implementation Priority

### Phase 1: Foundation (Week 1-2)
1. Create repository base classes in `/models/`
2. Implement `PatientRepository` with basic CRUD
3. Create `AuthorizationService` for centralized permission checks
4. Extend existing ViewModels with new methods

### Phase 2: Clinician Module (Week 3-4)
1. Migrate `patient-search.php` (simplest)
2. Migrate `patient-records.php` (most comprehensive)
3. Migrate `clinical-notes.php`
4. Migrate `ems-epcr.php`

### Phase 3: Dashboard Module (Week 5-6)
1. Migrate root-level dashboard files
2. Migrate sub-dashboard index files
3. Migrate all component files to view partials
4. Consolidate CSS/JS to `/assets/`

### Phase 4: Cleanup (Week 7)
1. Add redirect stubs to old locations
2. Update all internal links
3. Security audit of all views
4. Documentation update

---

## Appendix A: ViewModel Method Signatures

### ClinicianViewModel.php
```php
namespace ViewModel;

class ClinicianViewModel {
    public function __construct(
        PatientRepository $patientRepo,
        AuthorizationService $authService
    );
    
    public function initClinicalNotesPage(int $userId): array;
    public function initPatientRecordsPage(int $userId, int $patientId): array;
    public function initPatientSearchPage(int $userId): array;
    public function initEPCRPage(int $userId, ?int $patientId): array;
}
```

### DashboardStatsViewModel.php (Extension)
```php
// Add to existing class
public function initAuditLogsPage(int $userId): array;
public function initComplianceMonitorPage(int $userId): array;
public function initAdminDashboard(int $userId): array;
public function initManagerDashboard(int $userId): array;
public function initQAReviewPage(int $userId): array;
public function initRegulatoryPage(int $userId): array;
```

---

## Appendix B: Security Checklist

### Per-File Security Audit Checklist

- [ ] Authentication check (require_login)
- [ ] Role-based authorization
- [ ] CSRF token in all forms
- [ ] Input sanitization for all user inputs
- [ ] Output escaping for all dynamic content
- [ ] Audit logging for sensitive actions
- [ ] Error handling (no sensitive data in errors)
- [ ] Rate limiting consideration
- [ ] File upload validation (if applicable)
- [ ] SQL injection prevention (parameterized queries)

---

*Document generated: 2025-12-05*
*Last updated: 2025-12-05*
*Version: 1.0*