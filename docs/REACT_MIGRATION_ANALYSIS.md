# React Migration Analysis - SafeShift EHR Clinician Dashboard

## Executive Summary

This document provides a comprehensive analysis of the existing PHP-based clinician dashboard implementation in the SafeShift EHR system, with recommendations for migrating to React + Vite. The analysis covers current features, data flows, API endpoints, authentication mechanisms, security patterns, and migration considerations.

---

## 1. Current Dashboard Features

### 1.1 Main Dashboard Sections

| Section | Description | Location |
|---------|-------------|----------|
| **Header** | Welcome message, clinic name, global search, navigation tabs, user actions | Lines 22-67 |
| **Left Sidebar** | Records navigation menu with patient search, records, notes, lab results, OSHA reports | Lines 72-98 |
| **Action Cards** | Three main workflow cards: New Patient, Returning Patient, Procedures & Tests | Lines 103-168 |
| **Timeline Section** | Patient encounter timeline with vitals chart (conditionally rendered) | Lines 171-212 |
| **Recent Patients Sidebar** | Recently accessed patients (conditionally rendered) | Lines 216-218 |
| **Notifications** | Real-time notification container | Lines 222-226 |
| **Quick Registration Modal** | Patient registration form modal | Lines 236-298 |
| **Loading Overlay** | Full-screen loading spinner | Lines 229-232 |

### 1.2 Navigation Tabs

```
Forms | Records | Site Trends | [Future Tab 1] | [Future Tab 2] | [Future Tab 3]
```

- **Forms Tab**: Shows action cards (default view)
- **Records Tab**: Redirects to [`/clinician/patient-search.php`](View/clinician/patient-search.php)
- **Trends Tab**: Shows timeline/vitals section
- **Future Tabs**: Cyan-styled placeholder tabs

### 1.3 Action Card Workflows

#### New Patient Card (Green - `#90EE90`)
- Personal Medical → Patient Search
- Work Related → Patient Search
- General → Patient Search
- **START Button** → EMS ePCR form ([`/clinician/ems-epcr`](View/clinician/ems_epcr_view.php))

#### Returning Patient Card (Gold - `#FFD700`)
- Personal Medical → Patient Search
- Work Related → Patient Search
- Patient Summary → Patient Records

#### Procedures and Tests Card (Orange - `#FF8C00`)
- Drug Tests → Patient Search
- Physicals → Patient Search

### 1.4 Feature Flags

From [`OneClinicianDashboardViewModel.php`](ViewModel/OneClinicianDashboardViewModel.php:117-119):
```php
'showTimeline' => true,
'showRecentPatients' => true,
'showNotifications' => true
```

---

## 2. Data Flow Architecture

### 2.1 Current MVVM Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         View Layer                               │
│  View/dashboards/1clinician/index.php                           │
│  - Pure presentation template                                    │
│  - Receives $viewData from ViewModel                            │
│  - No business logic                                            │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                      ViewModel Layer                             │
│  ViewModel/OneClinicianDashboardViewModel.php                   │
│  - Data preparation                                             │
│  - Business logic orchestration                                 │
│  - API endpoint configuration                                   │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Service Layer                               │
│  core/Services/AuthService.php                                  │
│  core/Services/DashboardStatsService.php                        │
│  core/Services/PatientService.php                               │
│  core/Services/NotificationService.php                          │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Repository Layer                              │
│  Core/Repositories/EncounterRepository.php                      │
│  Core/Repositories/PatientRepository.php                        │
│  Core/Repositories/NotificationRepository.php                   │
│  Core/Repositories/UserRepository.php                           │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Database Layer                              │
│  MySQL via PDO                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Client-Side Data Loading

JavaScript initiates data loading via [`dashboard-clinician.js`](View/assets/js/dashboard-clinician.js:29-46):

```javascript
function initializeDashboard() {
    loadDashboardStats();      // GET /api/dashboard-stats.php
    loadNotifications();       // GET /api/notifications.php
    setupEventListeners();
    startPolling();            // Intervals for real-time updates
    initializeSessionTimeout();
}
```

### 2.3 Configuration Injection

PHP passes configuration to JavaScript via embedded script (lines 301-308):

```javascript
const APP_CONFIG = {
    csrfToken: "...",
    userId: "...",
    userRole: "...",
    apiEndpoints: {
        dashboardStats: '/api/dashboard-stats.php',
        recentPatients: '/api/recent-patients.php',
        patientVitals: '/api/patient-vitals.php',
        notifications: '/api/notifications.php',
        logPatientAccess: '/api/log-patient-access.php'
    }
};
```

---

## 3. API Endpoint Inventory

### 3.1 Dashboard API Endpoints

| Endpoint | Method | Description | Auth Required | Rate Limit |
|----------|--------|-------------|---------------|------------|
| [`/api/dashboard-stats.php`](api/dashboard-stats.php) | GET | Dashboard statistics | Yes | 30/min |
| [`/api/notifications.php`](api/notifications.php) | GET, POST | Get/mark notifications | Yes | 120/min |
| [`/api/patient-vitals.php`](api/patient-vitals.php) | GET | Patient vital trends | Yes | 100/min |
| [`/api/recent-patients.php`](api/recent-patients.php) | GET | Recently viewed patients | Yes | 60/min |
| `/api/log-patient-access.php` | POST | Log patient access (HIPAA) | Yes | - |

### 3.2 Dashboard Stats Response Schema

From [`api/dashboard-stats.php`](api/dashboard-stats.php:118-133):

```typescript
interface DashboardStatsResponse {
    success: boolean;
    data: {
        total_patients_today: number;
        new_patients_today: number;
        returning_patients_today: number;
        procedures_completed: number;
        drug_tests_today: number;
        physicals_today: number;
        pending_reviews: number;
        average_wait_time: number;
        appointments_today: number;
        upcoming_appointments: Appointment[];
    };
    timestamp: string; // ISO 8601
}

interface Appointment {
    appointment_id: string;
    patient_name: string;
    patient_id: string;
    time: string;
    visit_reason: string;
}
```

### 3.3 Notifications Response Schema

From [`api/notifications.php`](api/notifications.php:294-306):

```typescript
interface NotificationsResponse {
    success: boolean;
    data: {
        notifications: Notification[];
        total: number;
        unread: number;
        limit: number;
        offset: number;
        has_more: boolean;
    };
    timestamp: string;
}

interface Notification {
    id: string;
    type: 'lab_result' | 'appointment_reminder' | 'system_alert' | 'patient_update' | 'prescription_alert';
    priority: 'critical' | 'high' | 'normal' | 'low';
    title: string;
    message: string;
    data: Record<string, any>;
    is_read: boolean;
    read_at: string | null;
    created_at: string;
    time_ago: string;
    expires_at: string | null;
}
```

### 3.4 Patient Vitals Response Schema

From [`api/patient-vitals.php`](api/patient-vitals.php:196-206):

```typescript
interface PatientVitalsResponse {
    success: boolean;
    data: {
        patient: {
            patient_id: string;
            name: string;
            mrn: string;
        };
        vitals: Vital[];
        trends: Record<string, TrendPoint[]>;
        blood_pressure_combined?: {
            value: string;
            status: 'normal' | 'warning' | 'critical';
            color: 'green' | 'yellow' | 'red';
        };
    };
    period: {
        days: number;
        from: string;
        to: string;
    };
    timestamp: string;
}

interface Vital {
    type: string;
    name: string;
    value: number;
    units: string;
    status: 'normal' | 'warning' | 'critical' | 'mild' | 'moderate' | 'severe';
    color: 'green' | 'yellow' | 'red';
    observed_at: string;
    encounter_id: string;
}

interface TrendPoint {
    value: number;
    date: string;
    encounter_type: string;
}
```

### 3.5 Recent Patients Response Schema

From [`api/recent-patients.php`](api/recent-patients.php:159-164):

```typescript
interface RecentPatientsResponse {
    success: boolean;
    data: RecentPatient[];
    count: number;
    timestamp: string;
}

interface RecentPatient {
    patient_uuid: string;
    full_name: string;
    mrn: string;
    last_encounter_date: string | null;
    employer_name: string;
    accessed_at: string;
    access_type: string;
}
```

---

## 4. Authentication Flow

### 4.1 Session-Based Authentication

The application uses PHP session-based authentication managed by [`core/Services/AuthService.php`](core/Services/AuthService.php).

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│   Login     │────▶│  Verify      │────▶│  MFA?       │
│   Form      │     │  Credentials │     │             │
└─────────────┘     └──────────────┘     └──────┬──────┘
                                                │
                           ┌────────────────────┴────────────────────┐
                           │                                          │
                           ▼                                          ▼
                    ┌─────────────┐                           ┌─────────────┐
                    │  No MFA     │                           │  2FA/OTP    │
                    │  Complete   │                           │  Required   │
                    └──────┬──────┘                           └──────┬──────┘
                           │                                          │
                           ▼                                          ▼
                    ┌─────────────┐                           ┌─────────────┐
                    │  Set        │                           │  Send OTP   │
                    │  Session    │                           │  via Email  │
                    └──────┬──────┘                           └──────┬──────┘
                           │                                          │
                           ▼                                          ▼
                    ┌─────────────┐                           ┌─────────────┐
                    │  Redirect   │                           │  Verify     │
                    │  Dashboard  │                           │  OTP Code   │
                    └─────────────┘                           └──────┬──────┘
                                                                     │
                                                                     ▼
                                                              ┌─────────────┐
                                                              │  Complete   │
                                                              │  Login      │
                                                              └─────────────┘
```

### 4.2 Session Validation

From [`DashboardstatsViewModel.php`](ViewModel/DashboardStatsViewModel.php:67-84):

```php
public function validateSession(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
        return false;
    }
    if (isset($_SESSION['last_activity'])) {
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200; // 20 min
        if ((time() - $_SESSION['last_activity']) > $timeout) {
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}
```

### 4.3 Role-Based Access Control (RBAC)

From [`DashboardstatsViewModel.php`](ViewModel/DashboardStatsViewModel.php:36-51):

```php
private const DASHBOARD_PERMISSIONS = [
    'admin' => ['Admin', 'cadmin', 'tadmin'],
    'manager' => ['Manager', 'Admin', 'dclinician'],
    'clinician' => ['1clinician', 'dclinician', 'pclinician', 'Admin', 'Manager'],
    '1clinician' => ['1clinician'],
    'dclinician' => ['dclinician'],
    'pclinician' => ['pclinician'],
    'tadmin' => ['tadmin'],
    'audit_logs' => ['Admin', 'cadmin', 'tadmin'],
    'compliance' => ['Admin', 'Manager', 'pclinician', 'cadmin', 'tadmin'],
    'qa_review' => ['Manager', 'Admin', 'QA', 'dclinician'],
    'regulatory' => ['Admin', 'pclinician', 'cadmin']
];
```

### 4.4 API Authentication Headers

All API requests must include:

```http
Content-Type: application/json
X-CSRF-Token: <csrf_token>
Cookie: PHPSESSID=<session_id>
```

---

## 5. Security Patterns

### 5.1 CSRF Protection

CSRF tokens are generated via [`core/Services/AuthService.php`](core/Services/AuthService.php:400-403):

```php
public function getCsrfToken(): string {
    return Session::getCsrfToken();
}

public function validateCsrfToken(string $token): bool {
    return Session::validateCsrfToken($token);
}
```

Frontend implementation ([`dashboard-clinician.js`](View/assets/js/dashboard-clinician.js:84-91)):

```javascript
const response = await fetch(APP_CONFIG.apiEndpoints.dashboardStats, {
    method: 'GET',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': APP_CONFIG.csrfToken
    },
    credentials: 'same-origin'
});
```

### 5.2 XSS Prevention

From [`dashboard-clinician.js`](View/assets/js/dashboard-clinician.js:732-741):

```javascript
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
```

PHP-side escaping (View template):

```php
<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
```

### 5.3 Input Sanitization

From [`includes/sanitization.php`](includes/sanitization.php) (delegating to `Core\Helpers\InputSanitizer`):

| Function | Purpose |
|----------|---------|
| `sanitize_input()` | General string sanitization |
| `sanitize_email()` | Email validation/sanitization |
| `sanitize_username()` | Username sanitization |
| `sanitize_number()` | Numeric input sanitization |
| `sanitize_phone()` | Phone number formatting |
| `sanitize_text()` | Text with length limits |
| `sanitize_html()` | Allowed HTML tags only |
| `sanitize_filename()` | Safe filename generation |
| `sanitize_url()` | URL sanitization |
| `sanitize_json()` | JSON validation |
| `sanitize_like()` | SQL LIKE escaping |
| `remove_xss()` | XSS pattern removal |
| `sanitize_otp()` | OTP code validation |

### 5.4 Rate Limiting

From [`api/dashboard-stats.php`](api/dashboard-stats.php:77-106):

```php
$rate_limit_window = 60;  // 1 minute
$max_requests = 30;       // 30 requests per minute

if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true);
    if ($rate_data['window_start'] + $rate_limit_window > $current_time) {
        if ($rate_data['count'] >= $max_requests) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests']);
            exit;
        }
        $rate_data['count']++;
    }
}
```

### 5.5 CORS Configuration

From [`api/dashboard-stats.php`](api/dashboard-stats.php:23-30):

```php
$allowed_origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                  . "://" . $_SERVER['HTTP_HOST'];
if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $allowed_origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}
```

### 5.6 Security Headers

Applied in all API responses:

```php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
```

---

## 6. UI Components Mapping

### 6.1 Proposed React Component Hierarchy

```
App
├── AuthProvider (Context)
├── NotificationProvider (Context)
└── Router
    └── DashboardLayout
        ├── Header
        │   ├── WelcomeSection
        │   ├── GlobalSearch
        │   ├── NavigationTabs
        │   │   ├── TabButton (Forms)
        │   │   ├── TabButton (Records)
        │   │   ├── TabButton (Trends)
        │   │   └── TabButton (Future - x3)
        │   └── UserActions
        │       ├── ProfileButton
        │       ├── SettingsButton
        │       ├── TicketButton
        │       └── LogoutButton
        ├── LeftSidebar
        │   ├── SidebarHeader
        │   └── NavigationMenu
        │       ├── MenuItem (Search Patients)
        │       ├── MenuItem (Patient Records)
        │       ├── MenuItem (Clinical Notes)
        │       ├── MenuItem (Lab Results)
        │       └── MenuItem (OSHA Reports)
        ├── MainContent
        │   ├── ActionCardsSection
        │   │   ├── NewPatientCard
        │   │   │   ├── CardHeader
        │   │   │   ├── StartButton
        │   │   │   └── ActionButtons (3)
        │   │   ├── ReturningPatientCard
        │   │   │   ├── CardHeader
        │   │   │   └── ActionButtons (3)
        │   │   └── ProceduresCard
        │   │       ├── CardHeader
        │   │       └── ActionButtons (2)
        │   └── TimelineSection (conditional)
        │       ├── TimelineHeader
        │       ├── TimelineFilter
        │       ├── TimelineItems
        │       │   └── TimelineItem (multiple)
        │       └── VitalsChart
        │           └── ChartLegend
        ├── RecentPatientsSidebar (conditional)
        │   └── PatientCard (multiple)
        └── NotificationContainer
            └── Notification (multiple)
```

### 6.2 Component to PHP Mapping

| React Component | PHP Source | Lines |
|-----------------|------------|-------|
| `Header` | [`index.php`](View/dashboards/1clinician/index.php:22-67) | 22-67 |
| `WelcomeSection` | [`index.php`](View/dashboards/1clinician/index.php:24-27) | 24-27 |
| `GlobalSearch` | [`index.php`](View/dashboards/1clinician/index.php:29-38) | 29-38 |
| `NavigationTabs` | [`index.php`](View/dashboards/1clinician/index.php:40-47) | 40-47 |
| `UserActions` | [`index.php`](View/dashboards/1clinician/index.php:49-65) | 49-65 |
| `LeftSidebar` | [`index.php`](View/dashboards/1clinician/index.php:72-98) | 72-98 |
| `ActionCardsSection` | [`index.php`](View/dashboards/1clinician/index.php:103-168) | 103-168 |
| `NewPatientCard` | [`index.php`](View/dashboards/1clinician/index.php:106-130) | 106-130 |
| `ReturningPatientCard` | [`index.php`](View/dashboards/1clinician/index.php:133-150) | 133-150 |
| `ProceduresCard` | [`index.php`](View/dashboards/1clinician/index.php:153-167) | 153-167 |
| `TimelineSection` | [`index.php`](View/dashboards/1clinician/index.php:172-212) | 172-212 |
| `QuickRegistrationModal` | [`index.php`](View/dashboards/1clinician/index.php:236-298) | 236-298 |
| `LoadingOverlay` | [`index.php`](View/dashboards/1clinician/index.php:229-232) | 229-232 |

### 6.3 Shared/Reusable Components

| Component | Usage |
|-----------|-------|
| `Button` | Action buttons, navigation, forms |
| `Card` | Dashboard cards, stat cards |
| `Modal` | Quick registration, confirmation dialogs |
| `Input` | Search, forms |
| `Select` | Filters, form selects |
| `Badge` | Notification counts, status indicators |
| `Icon` | Bootstrap Icons or equivalent |
| `Spinner` | Loading states |
| `Toast` | Notification toasts |

---

## 7. State Requirements

### 7.1 Global State (Context/Redux)

```typescript
interface AppState {
    // Authentication
    auth: {
        user: User | null;
        isAuthenticated: boolean;
        csrfToken: string;
        sessionExpiry: Date | null;
    };
    
    // Dashboard Data
    dashboard: {
        stats: DashboardStats | null;
        isLoading: boolean;
        error: string | null;
        lastUpdated: Date | null;
    };
    
    // Notifications
    notifications: {
        items: Notification[];
        unreadCount: number;
        isLoading: boolean;
    };
    
    // UI State
    ui: {
        activeTab: 'forms' | 'records' | 'trends';
        sidebarCollapsed: boolean;
        isLoadingOverlay: boolean;
    };
}
```

### 7.2 Component Local State

#### Header Component
```typescript
interface HeaderState {
    searchQuery: string;
    searchResults: SearchResult[];
    isSearching: boolean;
}
```

#### Timeline Section
```typescript
interface TimelineState {
    patients: TimelinePatient[];
    filter: 'today' | 'week' | 'month' | 'all';
    isLoading: boolean;
    selectedPatientId: string | null;
}
```

#### Vitals Chart
```typescript
interface VitalsChartState {
    vitalsData: PatientVitals | null;
    chartInstance: Chart | null;
    isLoading: boolean;
}
```

#### Quick Registration Modal
```typescript
interface RegistrationModalState {
    isOpen: boolean;
    formData: PatientRegistrationForm;
    errors: Record<string, string>;
    isSubmitting: boolean;
}
```

### 7.3 Data Fetching Strategy

Recommended: **React Query (TanStack Query)** for:
- Automatic caching
- Background refetching
- Polling support (notifications, stats)
- Optimistic updates
- Error handling

```typescript
// Example usage
const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboardStats'],
    queryFn: fetchDashboardStats,
    refetchInterval: 60000, // Poll every minute
});

const { data: notifications } = useQuery({
    queryKey: ['notifications', { unreadOnly: true }],
    queryFn: fetchNotifications,
    refetchInterval: 30000, // Poll every 30 seconds
});
```

---

## 8. Migration Considerations

### 8.1 Technical Challenges

| Challenge | Mitigation Strategy |
|-----------|---------------------|
| **Session Management** | Implement token-based auth (JWT) or maintain PHP sessions with SameSite cookies |
| **CSRF Token Synchronization** | Fetch fresh token on app mount, include in API client |
| **Real-time Polling** | Use React Query polling or implement WebSocket for production |
| **Chart.js Integration** | Use react-chartjs-2 wrapper |
| **Bootstrap Icons** | Continue using Bootstrap Icons CDN or switch to react-icons |
| **Form Validation** | Use React Hook Form + Zod for type-safe validation |
| **PHP Template Variables** | Create dedicated config API endpoint or embed in initial HTML |

### 8.2 Recommended Tech Stack

| Layer | Technology | Justification |
|-------|------------|---------------|
| **Build Tool** | Vite | Fast HMR, optimized builds |
| **Framework** | React 18+ | Component-based, ecosystem |
| **Routing** | React Router v6 | Declarative routing |
| **State Management** | Zustand or Redux Toolkit | Global state |
| **Data Fetching** | TanStack Query | Caching, polling |
| **Styling** | Tailwind CSS | Utility-first, matches existing vars |
| **Forms** | React Hook Form | Performance, validation |
| **Validation** | Zod | TypeScript-first schemas |
| **Charts** | Chart.js + react-chartjs-2 | Existing compatibility |
| **Icons** | react-icons | Bootstrap + more |
| **HTTP Client** | Axios | Interceptors, transforms |
| **Testing** | Vitest + Testing Library | Vite-native testing |

### 8.3 CSS Migration Strategy

The existing CSS in [`dashboard-clinician.css`](View/assets/css/dashboard-clinician.css) uses CSS Custom Properties (variables), making migration straightforward:

1. **Extract CSS variables** (lines 16-75) into Tailwind config or CSS module
2. **Map semantic classes** to Tailwind utilities or keep as CSS modules
3. **Preserve responsive breakpoints** (1024px, 768px)
4. **Maintain print styles** for clinical reports
5. **Keep accessibility features** (high contrast, focus states)

### 8.4 Migration Phases

#### Phase 1: Foundation (Week 1-2)
- [ ] Set up Vite + React project structure
- [ ] Implement authentication context
- [ ] Create API client with interceptors
- [ ] Build base layout components

#### Phase 2: Core Components (Week 3-4)
- [ ] Header with search and navigation
- [ ] Sidebar navigation
- [ ] Action cards section
- [ ] Loading states and error handling

#### Phase 3: Data Integration (Week 5-6)
- [ ] Dashboard stats fetching
- [ ] Notifications system
- [ ] Timeline and vitals chart
- [ ] Recent patients sidebar

#### Phase 4: Forms and Modals (Week 7)
- [ ] Quick registration modal
- [ ] Session timeout warning
- [ ] Toast notifications

#### Phase 5: Testing and Polish (Week 8)
- [ ] Unit tests for components
- [ ] Integration tests for API flows
- [ ] Accessibility audit
- [ ] Performance optimization

### 8.5 API Compatibility Notes

All existing PHP API endpoints will continue to work. For the React app:

1. **Proxy Configuration** (vite.config.ts):
```typescript
export default defineConfig({
    server: {
        proxy: {
            '/api': 'http://localhost:8000'
        }
    }
});
```

2. **Cookie Handling**: Ensure `credentials: 'include'` for all requests
3. **CORS**: API already supports same-origin; for development, use proxy
4. **Error Responses**: Standardize error handling for API responses

### 8.6 Backward Compatibility

During migration, both systems can coexist:

1. **Mount React at specific route** (e.g., `/app/dashboard`)
2. **Share authentication** via PHP sessions
3. **Gradual migration** of pages/features
4. **Feature flags** to toggle between implementations

---

## 9. File Structure Recommendation

```
src/
├── api/
│   ├── client.ts           # Axios instance with interceptors
│   ├── auth.ts             # Auth API calls
│   ├── dashboard.ts        # Dashboard API calls
│   ├── notifications.ts    # Notifications API
│   └── patients.ts         # Patient API calls
├── components/
│   ├── common/
│   │   ├── Button/
│   │   ├── Card/
│   │   ├── Input/
│   │   ├── Modal/
│   │   ├── Spinner/
│   │   └── Toast/
│   ├── layout/
│   │   ├── Header/
│   │   ├── Sidebar/
│   │   └── DashboardLayout/
│   └── dashboard/
│       ├── ActionCards/
│       ├── Timeline/
│       ├── VitalsChart/
│       ├── RecentPatients/
│       └── QuickRegistration/
├── contexts/
│   ├── AuthContext.tsx
│   └── NotificationContext.tsx
├── hooks/
│   ├── useAuth.ts
│   ├── useDashboardStats.ts
│   ├── useNotifications.ts
│   └── usePatientVitals.ts
├── pages/
│   └── Dashboard/
│       └── index.tsx
├── styles/
│   ├── variables.css       # CSS custom properties
│   └── globals.css
├── types/
│   ├── api.ts
│   ├── auth.ts
│   ├── dashboard.ts
│   └── patient.ts
├── utils/
│   ├── formatters.ts
│   ├── validators.ts
│   └── security.ts
├── App.tsx
├── main.tsx
└── vite-env.d.ts
```

---

## 10. Summary

The SafeShift EHR Clinician Dashboard is a well-structured PHP/MVVM application with clear separation of concerns. The existing codebase provides:

- **Robust API layer** with consistent response formats
- **Comprehensive security** (CSRF, XSS, rate limiting)
- **Role-based access control** for multiple user types
- **Modern CSS** with custom properties (easy to migrate)
- **JavaScript functionality** that maps directly to React patterns

The migration to React + Vite is feasible with the recommended approach of maintaining the existing PHP API layer while building a new React frontend that consumes those APIs. The existing MVVM architecture's clean separation will facilitate a smooth transition.

---

*Document generated: 2025-12-05*
*Author: SafeShift Development Team*