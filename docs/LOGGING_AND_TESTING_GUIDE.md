# Logging and Testing Guide

## SafeShift EHR - Production Readiness Documentation

**Last Updated:** 2025-12-28  
**Version:** 1.0.0

---

## Table of Contents

1. [Logging System Overview](#1-logging-system-overview)
2. [EHR Logging Reference](#2-ehr-logging-reference)
3. [Dashboard Logging Reference](#3-dashboard-logging-reference)
4. [HIPAA Compliance](#4-hipaa-compliance)
5. [Test Suite Documentation](#5-test-suite-documentation)
6. [Filler Pattern Detection](#6-filler-pattern-detection)
7. [Quick Reference](#7-quick-reference)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Logging System Overview

### Architecture Diagram

```
+---------------------------+
|     Application Layer     |
|  Controllers / Services   |
+------------+--------------+
             |
             v
+------------+--------------+
|     Logging Services      |
|  +-----+  +------------+  |
|  | EHR |  | Dashboard  |  |
|  |Logger| | Logger     |  |
|  +--+--+  +-----+------+  |
|     |           |         |
+-----|-----------|----------+
      |           |
      v           v
+-----+-----------+------+
|   Log File System      |
|  /logs/channel_DATE.log|
|  .hash files for chain |
+------------------------+
             |
             v
+------------------------+
|   Integrity Verification|
|   Hash Chain Validation |
+------------------------+
```

### Log File Locations

All log files are stored in the `/logs/` directory with date-based naming:

| Log Type | File Pattern | Content Description |
|----------|--------------|---------------------|
| EHR Operations | `ehr_YYYY-MM-DD.log` | General EHR operations |
| Encounters | `encounter_YYYY-MM-DD.log` | Encounter CRUD operations |
| Vitals | `vitals_YYYY-MM-DD.log` | Vital signs recording |
| Assessments | `assessment_YYYY-MM-DD.log` | Patient assessments |
| Treatments | `treatment_YYYY-MM-DD.log` | Treatment plans |
| Signatures | `signature_YYYY-MM-DD.log` | Digital signatures |
| Finalization | `finalization_YYYY-MM-DD.log` | Report finalization events |
| PHI Access | `phi_access_YYYY-MM-DD.log` | HIPAA audit trail |
| Dashboard | `dashboard_YYYY-MM-DD.log` | All dashboard operations |

### Log Naming Convention

```
{channel}_{YYYY-MM-DD}.log
```

**Examples:**
- `encounter_2025-12-28.log`
- `phi_access_2025-12-28.log`
- `dashboard_2025-12-28.log`

### Log Rotation and Retention Policies

| Policy | Setting | Notes |
|--------|---------|-------|
| Rotation | Daily | New file created each day |
| Retention | 7 years | HIPAA requirement for medical records |
| Compression | After 30 days | Optional gzip compression |
| Archive | After 90 days | Move to cold storage |
| Format | JSON Lines | One JSON object per line |

### JSON Log Format Specification

Each log entry follows this JSON structure:

```json
{
  "timestamp": "2025-12-28T15:30:45.123Z",
  "level": "INFO",
  "channel": "encounter",
  "operation": "CREATE",
  "user_id": 123,
  "user_role": "clinical_provider",
  "encounter_id": "enc_abc123",
  "patient_id_hash": "sha256_hash_of_patient_id",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "request_id": "ehr_65abc123def456",
  "details": {
    "action": "encounter_created",
    "fields_updated": ["chief_complaint", "vitals"]
  },
  "result": "success",
  "duration_ms": 45,
  "hash": "sha256_chain_hash"
}
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `timestamp` | ISO 8601 | UTC timestamp with milliseconds |
| `level` | string | INFO, WARNING, ERROR, AUDIT, DEBUG, PERF |
| `channel` | string | Log channel name |
| `operation` | string | Operation type constant |
| `user_id` | int/null | Authenticated user ID |
| `user_role` | string/null | User's role |
| `encounter_id` | string/null | Related encounter ID |
| `patient_id_hash` | string/null | SHA256 hash of patient ID |
| `ip_address` | string | Client IP address |
| `user_agent` | string | Browser user agent (truncated to 500 chars) |
| `request_id` | string | Unique request correlation ID |
| `details` | object | Operation-specific details |
| `result` | string | success, failure, logged |
| `duration_ms` | int | Operation duration in milliseconds |
| `hash` | string | SHA256 hash for integrity chain |

---

## 2. EHR Logging Reference

### Service Location

[`core/Services/EHRLogger.php`](../core/Services/EHRLogger.php)

### Usage

```php
use Core\Services\EHRLogger;

$logger = EHRLogger::getInstance();
```

### Available Log Channels

| Channel Constant | Value | Purpose |
|------------------|-------|---------|
| `CHANNEL_EHR` | `ehr` | General EHR operations |
| `CHANNEL_ENCOUNTER` | `encounter` | Encounter CRUD operations |
| `CHANNEL_VITALS` | `vitals` | Vital signs recording |
| `CHANNEL_ASSESSMENT` | `assessment` | Patient assessments |
| `CHANNEL_TREATMENT` | `treatment` | Treatment plans |
| `CHANNEL_SIGNATURE` | `signature` | Digital signatures |
| `CHANNEL_FINALIZATION` | `finalization` | Report finalization |
| `CHANNEL_PHI_ACCESS` | `phi_access` | PHI access audit trail |

### Log Operations

| Operation Constant | Value | Description |
|--------------------|-------|-------------|
| `OP_CREATE` | `CREATE` | Resource created |
| `OP_READ` | `READ` | Resource read/viewed |
| `OP_UPDATE` | `UPDATE` | Resource modified |
| `OP_DELETE` | `DELETE` | Resource deleted |
| `OP_FINALIZE` | `FINALIZE` | Report finalized |
| `OP_SIGN` | `SIGN` | Digital signature applied |
| `OP_AMEND` | `AMEND` | Signed record amended |

### Logging Methods

#### Generic Operation Logging

```php
$logger->logOperation(
    EHRLogger::OP_CREATE,
    [
        'encounter_id' => 'enc_123',
        'patient_id' => 456,
        'details' => ['action' => 'custom_action'],
        'result' => 'success',
        'start_time' => microtime(true)
    ],
    EHRLogger::CHANNEL_EHR
);
```

#### Encounter Operations

```php
// Create encounter
$logger->logEncounterCreated('enc_123', 456, ['visit_type' => 'injury']);

// Read encounter
$logger->logEncounterRead('enc_123', 456);

// Update encounter
$logger->logEncounterUpdated('enc_123', ['chief_complaint', 'vitals']);

// Delete encounter
$logger->logEncounterDeleted('enc_123', 'Duplicate entry');

// Amend signed encounter
$logger->logEncounterAmended('enc_123', 'Correction to diagnosis', $userId);

// Status transition
$logger->logStatusTransition('enc_123', 'draft', 'in_progress', 'Vitals recorded');
```

#### Clinical Data Logging

```php
// Vitals
$logger->logVitalsRecorded('enc_123', [
    'blood_pressure' => '120/80',
    'temperature' => '98.6',
    'pulse' => 72
]);

// Assessment
$logger->logAssessmentAdded('enc_123', [
    'diagnosis' => 'Laceration',
    'icd_codes' => ['S61.411A']
]);

// Treatment
$logger->logTreatmentAdded('enc_123', [
    'plan' => 'Wound closure',
    'cpt_codes' => ['12001'],
    'medications' => ['Lidocaine'],
    'procedures' => ['Suturing']
]);
```

#### Signature and Finalization

```php
// Signature
$logger->logSignatureAdded('enc_123', 'provider', $signedBy);
$logger->logEncounterSigned('enc_123', $signedBy);

// Finalization
$logger->logFinalization(
    'enc_123',
    ['errors' => [], 'warnings' => []],
    true, // success
    ['is_work_related' => true, 'previous_status' => 'in_progress']
);
```

#### PHI Access Logging

```php
$logger->logPHIAccess(
    $userId,
    $patientId,
    'view',
    ['patient_name', 'ssn', 'dob', 'address']
);
```

#### Notification Logging

```php
// Email notification
$logger->logEmailNotification(
    'enc_123',
    ['employer@company.com', 'safety@company.com'],
    true
);

// SMS reminder
$logger->logSMSReminder('enc_123', '555-123-4567', true);
```

#### Error Logging

```php
$logger->logError('FINALIZE', 'Validation failed', [
    'encounter_id' => 'enc_123',
    'channel' => EHRLogger::CHANNEL_FINALIZATION
]);
```

### PHI Redaction Rules

The EHRLogger automatically redacts Protected Health Information (PHI) from log entries.

#### Redacted Field Names

The following field names are automatically redacted to `[REDACTED]`:

| Category | Fields |
|----------|--------|
| Names | `patient_name`, `first_name`, `last_name`, `full_name` |
| Identifiers | `ssn`, `social_security`, `social_security_number`, `mrn`, `medical_record_number` |
| Demographics | `dob`, `date_of_birth`, `birth_date` |
| Contact | `phone`, `phone_number`, `mobile`, `cell`, `home_phone`, `work_phone`, `email`, `email_address` |
| Address | `address`, `street`, `city`, `zip`, `zipcode`, `postal_code` |
| Insurance | `insurance_id`, `policy_number`, `group_number` |
| Other | `drivers_license`, `license_number`, `employer_name`, `company_name` |

#### Pattern-Based Redaction

Values matching these patterns are automatically replaced:

| Pattern | Replacement | Example |
|---------|-------------|---------|
| SSN | `[SSN-REDACTED]` | `123-45-6789` |
| Phone | `[PHONE-REDACTED]` | `555-123-4567` |
| Email | `[EMAIL-REDACTED]` | `user@example.com` |
| Date (MM/DD/YYYY) | `[DATE-REDACTED]` | `12/28/2025` |
| Date (YYYY-MM-DD) | `[DATE-REDACTED]` | `2025-12-28` |

### Example Log Entries

#### Encounter Created

```json
{
  "timestamp": "2025-12-28T15:30:45.123Z",
  "level": "INFO",
  "channel": "encounter",
  "operation": "CREATE",
  "user_id": 5,
  "user_role": "clinical_provider",
  "encounter_id": "enc_abc123",
  "patient_id_hash": "a7b9c3d1e5f2...",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "request_id": "ehr_65f4abc123",
  "details": {
    "action": "encounter_created",
    "visit_type": "injury"
  },
  "result": "success",
  "duration_ms": 23,
  "hash": "sha256..."
}
```

#### PHI Access Audit

```json
{
  "timestamp": "2025-12-28T15:31:00.456Z",
  "level": "AUDIT",
  "channel": "phi_access",
  "operation": "PHI_ACCESS",
  "user_id": 5,
  "user_role": "clinical_provider",
  "patient_id_hash": "a7b9c3d1e5f2...",
  "access_type": "view",
  "fields_accessed": ["patient_name", "dob", "ssn"],
  "fields_count": 3,
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "request_id": "ehr_65f4abc456",
  "purpose": "treatment",
  "result": "logged"
}
```

#### Finalization Event

```json
{
  "timestamp": "2025-12-28T16:00:00.789Z",
  "level": "INFO",
  "channel": "finalization",
  "operation": "FINALIZE",
  "user_id": 5,
  "user_role": "clinical_provider",
  "encounter_id": "enc_abc123",
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "request_id": "ehr_65f4def789",
  "details": {
    "action": "encounter_finalized",
    "validation_passed": true,
    "validation_errors": [],
    "validation_warnings": [],
    "is_work_related": true,
    "status_changed_from": "in_progress",
    "status_changed_to": "finalized"
  },
  "result": "success",
  "duration_ms": 156,
  "hash": "sha256..."
}
```

---

## 3. Dashboard Logging Reference

### Service Location

[`core/Services/DashboardLogger.php`](../core/Services/DashboardLogger.php)

### Usage

```php
use Core\Services\DashboardLogger;

$logger = DashboardLogger::getInstance();
$logger->startRequest(); // Reset counters for new request
```

### Log Channels

| Channel Constant | Value | Purpose |
|------------------|-------|---------|
| `CHANNEL_DASHBOARD` | `dashboard` | General dashboard operations |
| `CHANNEL_METRICS` | `metrics` | Metric calculations and requests |
| `CHANNEL_CACHE` | `cache` | Cache hit/miss operations |
| `CHANNEL_PERFORMANCE` | `performance` | Query and response time tracking |
| `CHANNEL_ACCESS` | `access` | User dashboard access patterns |

### Operations

| Operation Constant | Value | Description |
|--------------------|-------|-------------|
| `OP_DASHBOARD_LOAD` | `DASHBOARD_LOAD` | Full dashboard load |
| `OP_METRIC_REQUEST` | `METRIC_REQUEST` | Metric data requested |
| `OP_METRIC_CALCULATE` | `METRIC_CALCULATE` | Metric calculation performed |
| `OP_CACHE_READ` | `CACHE_READ` | Cache read (hit) |
| `OP_CACHE_WRITE` | `CACHE_WRITE` | Cache write (miss) |
| `OP_QUERY_EXECUTE` | `QUERY_EXECUTE` | Database query executed |
| `OP_DATA_AGGREGATE` | `DATA_AGGREGATE` | Data aggregation performed |
| `OP_TODO_HIT` | `TODO_HIT` | Incomplete feature accessed |

### Performance Thresholds

| Threshold | Constant | Value | Description |
|-----------|----------|-------|-------------|
| Slow Query | `THRESHOLD_SLOW_QUERY_MS` | 100ms | Query execution warning |
| Slow Dashboard | `THRESHOLD_SLOW_DASHBOARD_MS` | 500ms | Dashboard load warning |
| Max Queries | `THRESHOLD_MAX_QUERIES` | 10 | Query count per request |
| Cache Miss Rate | `THRESHOLD_CACHE_MISS_RATE` | 50% | Cache efficiency warning |

### Log Levels

| Level | Constant | Usage |
|-------|----------|-------|
| DEBUG | `LEVEL_DEBUG` | Detailed debugging information |
| INFO | `LEVEL_INFO` | Normal operational events |
| WARNING | `LEVEL_WARNING` | Threshold exceeded, potential issues |
| ERROR | `LEVEL_ERROR` | Operation failures |
| PERF | `LEVEL_PERF` | Performance-specific warnings |

### Dashboard Types

| Type | Constant | Description |
|------|----------|-------------|
| Admin | `DASH_ADMIN` | Administrator dashboard |
| Manager | `DASH_MANAGER` | Manager dashboard |
| Clinical | `DASH_CLINICAL` | Clinical provider dashboard |
| Technician | `DASH_TECHNICIAN` | Technician dashboard |
| Registration | `DASH_REGISTRATION` | Registration dashboard |
| Generic | `DASH_GENERIC` | Generic/default dashboard |

### Logging Methods

#### Metric Request Logging

```php
$logger->logMetricRequest(
    DashboardLogger::DASH_CLINICAL,
    'patient_count',
    $userId,
    ['date_range' => '7d', 'status' => 'active']
);
```

#### Metric Calculation Logging

```php
$startTime = microtime(true);
// ... calculation code ...
$executionTime = microtime(true) - $startTime;

$logger->logMetricCalculation(
    'patient_count',
    ['query_count' => 2, 'row_count' => 150],
    $executionTime
);
```

#### Cache Operation Logging

```php
// Cache hit
$logger->logCacheOperation('dashboard_stats_user_5', true);

// Cache miss (write new entry)
$logger->logCacheOperation('dashboard_stats_user_5', false, 3600); // TTL: 1 hour
```

#### Dashboard Load Logging

```php
$logger->logDashboardLoad(
    DashboardLogger::DASH_CLINICAL,
    $userId,
    ['patient_count', 'encounter_count', 'pending_tasks']
);
```

#### Query Performance Logging

```php
$logger->logQueryPerformance(
    'SELECT patients WHERE status = active',
    0.045, // 45ms
    150    // rows returned
);
```

#### Dashboard Access Logging

```php
$logger->logDashboardAccess($userId, DashboardLogger::DASH_ADMIN, [
    'view' => 'main',
    'filters_applied' => true
]);
```

#### Data Aggregation Logging

```php
$logger->logDataAggregation(
    'monthly_summary',
    ['data_points' => 1000, 'source_tables' => ['encounters', 'patients']],
    0.250 // 250ms
);
```

#### TODO Hit Logging

```php
$logger->logTodoHit(
    'DashboardStatsService.php:145',
    'Real-time sync not implemented',
    ['metric' => 'live_encounters']
);
```

#### Error Logging

```php
$logger->logError(
    'METRIC_CALCULATE',
    'Database connection timeout',
    ['metric' => 'patient_count', 'timeout' => 30]
);
```

#### Performance Summary

```php
$summary = $logger->getPerformanceSummary();
// Returns:
// [
//     'total_time_ms' => 234,
//     'query_count' => 5,
//     'total_query_time_ms' => 180,
//     'slow_query_count' => 1,
//     'cache_hits' => 3,
//     'cache_misses' => 2,
//     'cache_hit_rate' => 0.6,
//     'thresholds_exceeded' => [
//         'slow_dashboard' => false,
//         'max_queries' => false,
//         'cache_miss_rate' => false
//     ]
// ]
```

### Example Log Entries

#### Dashboard Load

```json
{
  "timestamp": "2025-12-28T15:30:45.123Z",
  "level": "INFO",
  "channel": "dashboard",
  "operation": "DASHBOARD_LOAD",
  "dashboard_type": "clinical_provider",
  "user_id": 5,
  "user_role": "clinical_provider",
  "metrics_requested": ["patient_count", "encounter_count"],
  "metrics_count": 2,
  "cache_status": "hit",
  "cache_hit_rate": 0.75,
  "query_count": 3,
  "total_query_time_ms": 120,
  "response_time_ms": 234,
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "request_id": "dash_65f4abc123",
  "warnings": [],
  "result": "success",
  "hash": "sha256..."
}
```

#### Slow Query Warning

```json
{
  "timestamp": "2025-12-28T15:30:46.500Z",
  "level": "PERF",
  "channel": "performance",
  "operation": "QUERY_EXECUTE",
  "query_description": "SELECT patients WHERE status = [VALUE]",
  "execution_time_ms": 150,
  "row_count": 500,
  "is_slow_query": true,
  "user_id": 5,
  "request_id": "dash_65f4abc456"
}
```

#### TODO Hit Warning

```json
{
  "timestamp": "2025-12-28T15:30:47.000Z",
  "level": "WARNING",
  "channel": "dashboard",
  "operation": "TODO_HIT",
  "todo_location": "DashboardStatsService.php:145",
  "todo_description": "Real-time sync not implemented",
  "user_id": 5,
  "user_role": "clinical_provider",
  "context": {
    "metric": "live_encounters"
  },
  "request_id": "dash_65f4abc789",
  "action_required": true
}
```

---

## 4. HIPAA Compliance

### PHI Access Audit Trail

All access to Protected Health Information (PHI) is logged to the `phi_access` channel with:

- **Who** accessed the data (user_id, user_role, ip_address)
- **What** data was accessed (fields_accessed, access_type)
- **When** it was accessed (timestamp)
- **Why** it was accessed (purpose from session)
- **How** it was accessed (user_agent, request_id)

#### Logging PHI Access

```php
$ehrLogger->logPHIAccess(
    $userId,
    $patientId,
    'view',           // access_type: view, export, print, edit
    ['patient_name', 'ssn', 'dob', 'medical_history']
);
```

### What is Logged vs. What is Redacted

| Category | Logged | Redacted |
|----------|--------|----------|
| User Identity | User ID, Role, IP Address | N/A |
| Patient Identity | Patient ID Hash (SHA256) | Patient ID, Name, SSN |
| Access Details | Access type, Fields accessed, Timestamp | Actual PHI values |
| Clinical Data | Field names, Counts, Codes | Actual diagnoses, Notes |
| Contact Info | Field names only | Addresses, Phones, Emails |

### Log Integrity (Hash Chains)

Each log entry includes a cryptographic hash that chains to the previous entry:

```
entry[n].hash = SHA256(JSON(entry[n]) + entry[n-1].hash)
```

This provides:
- **Tamper detection** - Any modification breaks the chain
- **Non-repudiation** - Entries cannot be deleted without detection
- **Audit proof** - Chain can be verified programmatically

#### Verifying Log Integrity

```php
$ehrLogger = EHRLogger::getInstance();
$result = $ehrLogger->verifyLogIntegrity('phi_access', '2025-12-28');

if ($result['valid']) {
    echo "✓ Log integrity verified: {$result['entries_verified']} entries";
} else {
    echo "✗ Integrity failure at line {$result['line']}: {$result['error']}";
}
```

### Retention Requirements

| Requirement | Duration | Source |
|-------------|----------|--------|
| PHI Access Logs | 6 years minimum | HIPAA §164.530(j) |
| Medical Records | 7 years (adults) | State regulations |
| Audit Logs | 6 years | HIPAA Security Rule |
| System Logs | 1 year minimum | Best practice |

### Hash Storage

Hash chain files are stored alongside logs:
- `.ehr_hash_{channel}` - Current hash for EHR channels
- `.dashboard_hash` - Current hash for dashboard logs

---

## 5. Test Suite Documentation

### Test Structure

```
tests/
├── bootstrap.php                    # PHPUnit bootstrap configuration
├── API/                             # API endpoint tests
│   ├── DashboardApiTest.php         # Dashboard API endpoints
│   ├── EncountersApiTest.php        # Encounters API endpoints
│   └── ResponseFillerTest.php       # API response filler detection
├── Helpers/                         # Test utilities
│   ├── TestCase.php                 # Base test case class
│   └── Factories/                   # Test data factories
│       ├── EncounterFactory.php     # Encounter test data
│       ├── PatientFactory.php       # Patient test data
│       └── UserFactory.php          # User test data
├── Integration/                     # Integration tests
│   ├── DashboardMetricsTest.php     # Dashboard metric workflows
│   ├── DatabaseFillerTest.php       # Database filler detection
│   └── EncounterWorkflowTest.php    # Encounter lifecycle tests
├── Quality/                         # Code quality checks
│   └── FillerPatternTest.php        # Filler pattern detection
└── Unit/                            # Unit tests
    ├── Entities/                    # Entity tests
    │   ├── EncounterEntityTest.php
    │   └── PatientEntityTest.php
    ├── Services/                    # Service tests
    │   └── RoleServiceTest.php
    ├── Validators/                  # Validator tests
    │   ├── EncounterValidatorTest.php
    │   └── PatientValidatorTest.php
    └── ViewModels/                  # ViewModel tests
        ├── DashboardStatsViewModelTest.php
        ├── DashboardViewModelTest.php
        └── EncounterViewModelTest.php
```

### Running Tests

#### PHPUnit Commands

```bash
# Run all PHP tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
vendor/bin/phpunit --testsuite API
vendor/bin/phpunit --testsuite Quality

# Run with code coverage
vendor/bin/phpunit --coverage-html coverage/

# Run specific test file
vendor/bin/phpunit tests/Unit/Entities/EncounterEntityTest.php

# Run specific test method
vendor/bin/phpunit --filter testEncounterCreation

# Run with verbose output
vendor/bin/phpunit -v

# Run excluding slow tests
vendor/bin/phpunit --exclude-group slow

# Run excluding database tests
vendor/bin/phpunit --exclude-group database
```

#### Vitest Commands for TypeScript

```bash
# Run all TypeScript tests
npm test

# Run tests in watch mode
npm run test:watch

# Run with coverage
npm run test:coverage

# Run specific test file
npm test -- src/app/components/__tests__/Dashboard.test.tsx
```

#### CI/CD Integration

```yaml
# GitHub Actions example
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Install PHP dependencies
        run: composer install
        
      - name: Run PHP tests
        run: vendor/bin/phpunit --testsuite Unit,Integration
        
      - name: Run quality checks
        run: vendor/bin/phpunit --testsuite Quality
        
      - name: Run filler pattern check
        run: php scripts/check-filler-patterns.php --json --strict
        
      - name: Install Node dependencies
        run: npm ci
        
      - name: Run TypeScript tests
        run: npm test -- --run
```

### Test Coverage Summary

#### Unit Tests

| Category | Test File | Coverage |
|----------|-----------|----------|
| Entities | [`EncounterEntityTest.php`](../tests/Unit/Entities/EncounterEntityTest.php) | Encounter creation, validation, state |
| Entities | [`PatientEntityTest.php`](../tests/Unit/Entities/PatientEntityTest.php) | Patient creation, demographics |
| Validators | [`EncounterValidatorTest.php`](../tests/Unit/Validators/EncounterValidatorTest.php) | Encounter validation rules |
| Validators | [`PatientValidatorTest.php`](../tests/Unit/Validators/PatientValidatorTest.php) | Patient validation rules |
| ViewModels | [`DashboardViewModelTest.php`](../tests/Unit/ViewModels/DashboardViewModelTest.php) | Dashboard data transformation |
| ViewModels | [`DashboardStatsViewModelTest.php`](../tests/Unit/ViewModels/DashboardStatsViewModelTest.php) | Statistics calculation |
| ViewModels | [`EncounterViewModelTest.php`](../tests/Unit/ViewModels/EncounterViewModelTest.php) | Encounter data presentation |
| Services | [`RoleServiceTest.php`](../tests/Unit/Services/RoleServiceTest.php) | Role permission logic |

#### Integration Tests

| Test File | Coverage |
|-----------|----------|
| [`EncounterWorkflowTest.php`](../tests/Integration/EncounterWorkflowTest.php) | Full encounter lifecycle: create → update → sign → finalize |
| [`DashboardMetricsTest.php`](../tests/Integration/DashboardMetricsTest.php) | Metric calculation, caching, performance |
| [`DatabaseFillerTest.php`](../tests/Integration/DatabaseFillerTest.php) | Database filler data detection |

#### API Tests

| Test File | Coverage |
|-----------|----------|
| [`EncountersApiTest.php`](../tests/API/EncountersApiTest.php) | CRUD endpoints, response format, authentication |
| [`DashboardApiTest.php`](../tests/API/DashboardApiTest.php) | Dashboard API endpoints, metrics API |
| [`ResponseFillerTest.php`](../tests/API/ResponseFillerTest.php) | API response filler detection |

#### Quality Tests

| Test File | Coverage |
|-----------|----------|
| [`FillerPatternTest.php`](../tests/Quality/FillerPatternTest.php) | Filler patterns, credentials, debug output |

---

## 6. Filler Pattern Detection

### Overview

Filler pattern detection prevents placeholder data from reaching production. Two mechanisms are provided:

1. **PHPUnit Tests** - Run during test suite (`tests/Quality/FillerPatternTest.php`)
2. **CI/CD Script** - Standalone script for pipelines (`scripts/check-filler-patterns.php`)

### Patterns Detected

#### Critical Severity (Immediate failure)

| Pattern | Description |
|---------|-------------|
| Hardcoded passwords | `password = 'password'`, `'123456'`, `'admin'`, `'test'`, `'secret'` |
| Hardcoded API keys | `api_key = '[20+ char string]'` |
| Hardcoded secrets | `secret_key = '[20+ char string]'` |
| Bearer tokens | `bearer [32+ char string]` |

#### Error Severity (Build failure)

| Pattern | Description |
|---------|-------------|
| Lorem ipsum | `lorem ipsum`, `dolor sit amet` |
| Test emails | `test@test.com`, `example@example.com`, `foo@bar.com` |
| Test phones | `555-xxx-xxxx`, `123-456-7890` |
| Test names | `"John Doe"`, `"Jane Doe"`, `"Test User"`, `"Test Patient"` |
| Test SSNs | `000-00-0000`, `123-45-6789` |
| Hardcoded IDs | `user_id = 123`, `patient_id = 456` |

#### Warning Severity (Pass with warning, fail in strict mode)

| Pattern | Description |
|---------|-------------|
| Debug output | `var_dump()`, `print_r()`, `console.log()` |
| Placeholder markers | `XXX`, empty `// TODO`, empty `// FIXME` |
| Test addresses | `123 Main St` |

### CI/CD Script Usage

```bash
# Basic usage
php scripts/check-filler-patterns.php

# Verbose output (show file contents)
php scripts/check-filler-patterns.php --verbose

# JSON output for CI integration
php scripts/check-filler-patterns.php --json

# Strict mode (warnings = errors)
php scripts/check-filler-patterns.php --strict

# Combined options
php scripts/check-filler-patterns.php --json --strict

# Show help
php scripts/check-filler-patterns.php --help
```

#### Exit Codes

| Code | Meaning |
|------|---------|
| 0 | No issues (or only warnings in non-strict mode) |
| 1 | Filler patterns or errors detected |
| 2 | Script error |

#### JSON Output Format

```json
{
  "passed": false,
  "duration_ms": 450,
  "files_scanned": 234,
  "summary": {
    "critical": 0,
    "errors": 2,
    "warnings": 5,
    "total": 7
  },
  "issues": {
    "critical": [],
    "error": [
      {
        "file": "api/endpoint.php",
        "line": 45,
        "pattern": "Lorem ipsum",
        "content": "$message = 'Lorem ipsum dolor sit amet';"
      }
    ],
    "warning": [
      {
        "file": "ViewModel/Dashboard.php",
        "line": 123,
        "pattern": "var_dump()",
        "content": "var_dump($data);"
      }
    ]
  }
}
```

### How to Add New Patterns

#### In CI/CD Script

Edit [`scripts/check-filler-patterns.php`](../scripts/check-filler-patterns.php):

```php
$patterns = [
    'critical' => [
        // Add new critical patterns here
        [
            'name' => 'My Critical Pattern',
            'pattern' => '/my_pattern/i',
        ],
    ],
    'error' => [
        // Add new error patterns here
    ],
    'warning' => [
        // Add new warning patterns here
    ],
];
```

#### In PHPUnit Tests

Edit [`tests/Quality/FillerPatternTest.php`](../tests/Quality/FillerPatternTest.php):

```php
private array $fillerPatterns = [
    // Add new patterns here
    '/my_new_pattern/i',
];
```

### Excluded Directories

Both detection mechanisms exclude:
- `vendor/`
- `tests/`
- `node_modules/`
- `.git/`
- `cache/`
- `logs/`
- `sessions/`
- `backups/`

---

## 7. Quick Reference

### Log File Locations

| Log Type | Path | Content |
|----------|------|---------|
| EHR Operations | `/logs/ehr_YYYY-MM-DD.log` | General EHR operations |
| Encounters | `/logs/encounter_YYYY-MM-DD.log` | Encounter CRUD |
| Vitals | `/logs/vitals_YYYY-MM-DD.log` | Vital signs recording |
| Assessments | `/logs/assessment_YYYY-MM-DD.log` | Patient assessments |
| Treatments | `/logs/treatment_YYYY-MM-DD.log` | Treatment plans |
| Signatures | `/logs/signature_YYYY-MM-DD.log` | Digital signatures |
| Finalization | `/logs/finalization_YYYY-MM-DD.log` | Report finalization |
| PHI Access | `/logs/phi_access_YYYY-MM-DD.log` | HIPAA audit trail |
| Dashboard | `/logs/dashboard_YYYY-MM-DD.log` | Dashboard operations |

### Test Commands

| Command | Description |
|---------|-------------|
| `vendor/bin/phpunit` | Run all PHP tests |
| `vendor/bin/phpunit --testsuite Unit` | Run unit tests only |
| `vendor/bin/phpunit --testsuite Integration` | Run integration tests |
| `vendor/bin/phpunit --testsuite API` | Run API tests |
| `vendor/bin/phpunit --testsuite Quality` | Run quality checks |
| `vendor/bin/phpunit --coverage-html coverage/` | Generate coverage report |
| `npm test` | Run TypeScript tests |
| `npm run test:watch` | Run tests in watch mode |
| `php scripts/check-filler-patterns.php` | CI/CD filler check |
| `php scripts/check-filler-patterns.php --strict` | Strict filler check |

### Logger Quick Reference

```php
// EHR Logger
$ehr = EHRLogger::getInstance();
$ehr->logEncounterCreated($encId, $patientId, $details);
$ehr->logEncounterRead($encId);
$ehr->logEncounterUpdated($encId, ['field1', 'field2']);
$ehr->logVitalsRecorded($encId, $vitals);
$ehr->logAssessmentAdded($encId, $assessment);
$ehr->logTreatmentAdded($encId, $treatment);
$ehr->logEncounterSigned($encId, $userId);
$ehr->logFinalization($encId, $validation, $success, $details);
$ehr->logPHIAccess($userId, $patientId, $type, $fields);
$ehr->logError($operation, $message, $context);

// Dashboard Logger
$dash = DashboardLogger::getInstance();
$dash->startRequest();
$dash->logDashboardLoad($dashType, $userId, $metrics);
$dash->logMetricRequest($dashType, $metric, $userId, $filters);
$dash->logMetricCalculation($metric, $queryInfo, $execTime);
$dash->logCacheOperation($key, $hit, $ttl);
$dash->logQueryPerformance($query, $execTime, $rowCount);
$dash->logTodoHit($location, $description, $context);
$dash->logError($operation, $message, $context);
$summary = $dash->getPerformanceSummary();
```

---

## 8. Troubleshooting

### Common Logging Issues

#### Issue: Log files not being created

**Symptoms:** No log files in `/logs/` directory

**Solutions:**
1. Check directory permissions: `chmod 750 logs/`
2. Verify PHP has write access: `ls -la logs/`
3. Check for disk space: `df -h`
4. Verify logger is instantiated correctly

```php
// Correct usage
$logger = EHRLogger::getInstance();
$result = $logger->logEncounterCreated($id, $patientId);
if (!$result) {
    error_log("Logging failed - check permissions");
}
```

#### Issue: Hash chain verification fails

**Symptoms:** `verifyLogIntegrity()` returns `valid: false`

**Solutions:**
1. Check if log file was manually edited (not allowed)
2. Verify `.ehr_hash_*` files exist and are readable
3. If logs were legitimately modified, regenerate hash chain

```bash
# Check hash files
ls -la logs/.ehr_hash_*
ls -la logs/.dashboard_hash
```

#### Issue: Log entries missing user_id

**Symptoms:** `user_id: null` in log entries

**Solutions:**
1. Ensure session is started before logging
2. Verify user authentication before logging

```php
session_start();
// User must be authenticated
$_SESSION['user']['user_id'] = $userId;
```

### Test Failure Debugging

#### Issue: Unit tests failing with database errors

**Solutions:**
1. Tests shouldn't need database - mock dependencies
2. Check if test uses `@group database` annotation
3. Run without database tests: `--exclude-group database`

#### Issue: Filler pattern tests failing

**Symptoms:** `FillerPatternTest::testNoFillerPatternsInPHPFiles` fails

**Solutions:**
1. Run verbose check: `php scripts/check-filler-patterns.php --verbose`
2. Review flagged files and remove filler data
3. If legitimate, add to exclusion patterns

#### Issue: Integration tests timeout

**Solutions:**
1. Check database connection
2. Verify test database exists
3. Increase timeout in phpunit.xml:

```xml
<php>
    <ini name="max_execution_time" value="120"/>
</php>
```

### Performance Optimization

#### Slow Dashboard Loading

**Check logs for:**
1. Slow queries (`is_slow_query: true`)
2. High query count (>10 per request)
3. Low cache hit rate (<50%)

```php
// Add at end of dashboard load
$summary = $dashLogger->getPerformanceSummary();
if ($summary['thresholds_exceeded']['slow_dashboard']) {
    // Investigate slow queries
}
```

#### High Log Volume

**Solutions:**
1. Reduce debug logging in production
2. Implement log sampling for high-frequency events
3. Increase log rotation frequency

```php
// Skip debug logging in production
if ($_ENV['APP_ENV'] !== 'production') {
    $logger->logQueryPerformance($query, $time, $rows);
}
```

#### Log Search Performance

**For searching large log files:**
```bash
# Search for specific user
grep '"user_id":5' logs/phi_access_2025-12-28.log

# Search for errors
grep '"level":"ERROR"' logs/*.log

# Search by request ID
grep 'ehr_65f4abc123' logs/*.log

# Count operations
grep -c '"operation":"CREATE"' logs/encounter_*.log
```

---

## Related Documentation

- [EHR Workflow Analysis](./EHR_WORKFLOW_ANALYSIS.md)
- [HIPAA Compliance](./HIPAA_COMPLIANCE.md)
- [Testing Guide](./TESTING_GUIDE.md)
- [Placeholder Cleanup Report](./PLACEHOLDER_CLEANUP_REPORT.md)
- [Deployment Guide](./DEPLOYMENT.md)

---

**Document Version:** 1.0.0  
**Last Updated:** 2025-12-28  
**Authors:** SafeShift Development Team
