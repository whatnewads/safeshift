# API Endpoint Test Results

**Generated:** 2026-01-12 10:10:50
**Base URL:** http://localhost:8000

## Summary

| Metric | Count |
|--------|-------|
| Total Endpoints Tested | 66 |
| Passed | 65 |
| Failed | 0 |
| Warnings | 1 |
| Skipped | 0 |

**Pass Rate:** 98.5%

## Detailed Results by Module

### Authentication

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/auth/csrf-token` | 200 | 85.7ms |
| ✅ | GET | `/api/v1/auth/session-status` | 200 | 86.44ms |
| ✅ | GET | `/api/v1/auth/current-user` | 401 | 89.96ms |
| ✅ | GET | `/api/v1/auth/active-sessions` | 401 | 87.54ms |

### Patients

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/patients` | 401 | 19.46ms |
| ✅ | GET | `/api/v1/patients?page=1&per_page=10` | 401 | 54.86ms |
| ✅ | GET | `/api/v1/patients/search?q=test` | 401 | 49.17ms |
| ✅ | GET | `/api/v1/patients/recent` | 401 | 63.55ms |
| ⚠️ | GET | `/api/v1/patients/invalid-uuid-format` | 401 | 38.99ms |

### Encounters

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/encounters` | 401 | 35.58ms |
| ✅ | GET | `/api/v1/encounters?page=1&per_page=10` | 401 | 41.11ms |
| ✅ | GET | `/api/v1/encounters/today` | 401 | 25.98ms |
| ✅ | GET | `/api/v1/encounters/pending` | 401 | 64.51ms |

### Admin

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/admin` | 200 | 71.53ms |
| ✅ | GET | `/api/v1/admin/stats` | 200 | 39.74ms |
| ✅ | GET | `/api/v1/admin/cases` | 200 | 33.07ms |
| ✅ | GET | `/api/v1/admin/patient-flow` | 200 | 50.85ms |
| ✅ | GET | `/api/v1/admin/sites` | 200 | 37.31ms |
| ✅ | GET | `/api/v1/admin/providers` | 200 | 43.69ms |
| ✅ | GET | `/api/v1/admin/staff` | 200 | 30.32ms |
| ✅ | GET | `/api/v1/admin/clearance` | 200 | 43.5ms |
| ✅ | GET | `/api/v1/admin/compliance` | 200 | 39.82ms |
| ✅ | GET | `/api/v1/admin/training` | 200 | 79.98ms |
| ✅ | GET | `/api/v1/admin/credentials` | 200 | 59.51ms |
| ✅ | GET | `/api/v1/admin/osha/300` | 200 | 50.07ms |
| ✅ | GET | `/api/v1/admin/osha/300a` | 200 | 33.6ms |

### Notifications

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/notifications` | 401 | 28.82ms |
| ✅ | GET | `/api/v1/notifications/unread-count` | 401 | 35.22ms |

### Doctor/MRO

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/doctor/dashboard` | 401 | 40.06ms |
| ✅ | GET | `/api/v1/doctor/stats` | 401 | 25ms |
| ✅ | GET | `/api/v1/doctor/verifications/pending` | 401 | 37.09ms |
| ✅ | GET | `/api/v1/doctor/verifications/history` | 401 | 33.33ms |
| ✅ | GET | `/api/v1/doctor/orders/pending` | 401 | 48.22ms |

### Clinical Provider

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/clinicalprovider/dashboard` | 401 | 32.1ms |
| ✅ | GET | `/api/v1/clinicalprovider/stats` | 401 | 79.39ms |
| ✅ | GET | `/api/v1/clinicalprovider/encounters/active` | 401 | 32.01ms |
| ✅ | GET | `/api/v1/clinicalprovider/encounters/recent` | 401 | 37.71ms |
| ✅ | GET | `/api/v1/clinicalprovider/orders/pending` | 401 | 40.08ms |
| ✅ | GET | `/api/v1/clinicalprovider/qa/pending` | 401 | 53.54ms |

### Reports

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/reports` | 401 | 37.13ms |
| ✅ | GET | `/api/v1/reports/dashboard` | 401 | 35.44ms |
| ✅ | GET | `/api/v1/reports/safety` | 401 | 24.89ms |
| ✅ | GET | `/api/v1/reports/compliance` | 401 | 38.06ms |
| ✅ | GET | `/api/v1/reports/patient-volume` | 401 | 16.92ms |
| ✅ | GET | `/api/v1/reports/encounter-summary` | 401 | 36.5ms |
| ✅ | GET | `/api/v1/reports/dot-summary` | 401 | 38.99ms |
| ✅ | GET | `/api/v1/reports/osha-summary` | 401 | 14.26ms |
| ✅ | GET | `/api/v1/reports/provider-productivity` | 401 | 53.76ms |

### DOT Tests

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/dot-tests` | 401 | 56.65ms |
| ✅ | GET | `/api/v1/dot-tests/deadline` | 401 | 37.39ms |
| ✅ | GET | `/api/v1/dot-tests/status/pending` | 401 | 37.71ms |

### OSHA

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/osha/injuries` | 401 | 16.24ms |
| ✅ | GET | `/api/v1/osha/300-log` | 401 | 15.09ms |
| ✅ | GET | `/api/v1/osha/300a-log` | 401 | 14.27ms |
| ✅ | GET | `/api/v1/osha/rates` | 401 | 36.29ms |

### Privacy Officer

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/privacy/dashboard` | 401 | 37.55ms |
| ✅ | GET | `/api/v1/privacy/compliance/kpis` | 401 | 17.89ms |
| ✅ | GET | `/api/v1/privacy/access-logs` | 401 | 49.53ms |
| ✅ | GET | `/api/v1/privacy/consents` | 401 | 48.18ms |
| ✅ | GET | `/api/v1/privacy/regulatory` | 401 | 33.31ms |
| ✅ | GET | `/api/v1/privacy/breaches` | 401 | 32.45ms |
| ✅ | GET | `/api/v1/privacy/training` | 401 | 78.45ms |

### Disclosures

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/disclosures/templates` | 200 | 60.56ms |

### Dashboard Stats

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/dashboard-stats` | 401 | 36.07ms |

### Video Meetings

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/video/my-meetings` | 401 | 34.29ms |

### Error Handling

| Status | Method | Endpoint | HTTP Code | Response Time |
|--------|--------|----------|-----------|---------------|
| ✅ | GET | `/api/v1/nonexistent-endpoint` | 404 | 34.69ms |

## Complete API Endpoint Inventory

### Authentication

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/auth/csrf-token` | Get CSRF token | 🔓 No |
| GET | `/api/v1/auth/session-status` | Get session status | 🔒 Yes |
| GET | `/api/v1/auth/current-user` | Get current user | 🔒 Yes |
| GET | `/api/v1/auth/active-sessions` | Get active sessions | 🔒 Yes |

### Patients

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/patients` | List patients | 🔒 Yes |
| GET | `/api/v1/patients?page=1&per_page=10` | List patients (paginated) | 🔒 Yes |
| GET | `/api/v1/patients/search?q=test` | Search patients | 🔒 Yes |
| GET | `/api/v1/patients/recent` | Get recent patients | 🔒 Yes |

### Encounters

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/encounters` | List encounters | 🔒 Yes |
| GET | `/api/v1/encounters?page=1&per_page=10` | List encounters (paginated) | 🔒 Yes |
| GET | `/api/v1/encounters/today` | Get today's encounters | 🔒 Yes |
| GET | `/api/v1/encounters/pending` | Get pending encounters | 🔒 Yes |

### Admin

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/admin` | Admin dashboard | 🔒 Yes |
| GET | `/api/v1/admin/stats` | Admin stats | 🔒 Yes |
| GET | `/api/v1/admin/cases` | Recent cases | 🔒 Yes |
| GET | `/api/v1/admin/patient-flow` | Patient flow metrics | 🔒 Yes |
| GET | `/api/v1/admin/sites` | Site performance | 🔒 Yes |
| GET | `/api/v1/admin/providers` | Provider performance | 🔒 Yes |
| GET | `/api/v1/admin/staff` | Staff list | 🔒 Yes |
| GET | `/api/v1/admin/clearance` | Clearance stats | 🔒 Yes |
| GET | `/api/v1/admin/compliance` | Compliance alerts | 🔒 Yes |
| GET | `/api/v1/admin/training` | Training modules | 🔒 Yes |
| GET | `/api/v1/admin/credentials` | Expiring credentials | 🔒 Yes |
| GET | `/api/v1/admin/osha/300` | OSHA 300 Log | 🔒 Yes |
| GET | `/api/v1/admin/osha/300a` | OSHA 300A Summary | 🔒 Yes |

### Notifications

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/notifications` | List notifications | 🔒 Yes |
| GET | `/api/v1/notifications/unread-count` | Unread count | 🔒 Yes |

### Doctor/MRO

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/doctor/dashboard` | Doctor dashboard | 🔒 Yes |
| GET | `/api/v1/doctor/stats` | Doctor stats | 🔒 Yes |
| GET | `/api/v1/doctor/verifications/pending` | Pending verifications | 🔒 Yes |
| GET | `/api/v1/doctor/verifications/history` | Verification history | 🔒 Yes |
| GET | `/api/v1/doctor/orders/pending` | Pending orders | 🔒 Yes |

### Clinical Provider

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/clinicalprovider/dashboard` | Clinical provider dashboard | 🔒 Yes |
| GET | `/api/v1/clinicalprovider/stats` | Provider stats | 🔒 Yes |
| GET | `/api/v1/clinicalprovider/encounters/active` | Active encounters | 🔒 Yes |
| GET | `/api/v1/clinicalprovider/encounters/recent` | Recent encounters | 🔒 Yes |
| GET | `/api/v1/clinicalprovider/orders/pending` | Pending orders | 🔒 Yes |
| GET | `/api/v1/clinicalprovider/qa/pending` | Pending QA reviews | 🔒 Yes |

### Reports

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/reports` | List report types | 🔒 Yes |
| GET | `/api/v1/reports/dashboard` | Dashboard report | 🔒 Yes |
| GET | `/api/v1/reports/safety` | Safety report | 🔒 Yes |
| GET | `/api/v1/reports/compliance` | Compliance report | 🔒 Yes |
| GET | `/api/v1/reports/patient-volume` | Patient volume report | 🔒 Yes |
| GET | `/api/v1/reports/encounter-summary` | Encounter summary | 🔒 Yes |
| GET | `/api/v1/reports/dot-summary` | DOT summary | 🔒 Yes |
| GET | `/api/v1/reports/osha-summary` | OSHA summary | 🔒 Yes |
| GET | `/api/v1/reports/provider-productivity` | Provider productivity | 🔒 Yes |

### DOT Tests

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/dot-tests` | List DOT tests | 🔒 Yes |
| GET | `/api/v1/dot-tests/deadline` | Tests approaching deadline | 🔒 Yes |
| GET | `/api/v1/dot-tests/status/pending` | Pending tests | 🔒 Yes |

### OSHA

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/osha/injuries` | List injuries | 🔒 Yes |
| GET | `/api/v1/osha/300-log` | OSHA 300 Log | 🔒 Yes |
| GET | `/api/v1/osha/300a-log` | OSHA 300A Log | 🔒 Yes |
| GET | `/api/v1/osha/rates` | TRIR/DART rates | 🔒 Yes |

### Privacy Officer

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/privacy/dashboard` | Privacy dashboard | 🔒 Yes |
| GET | `/api/v1/privacy/compliance/kpis` | Compliance KPIs | 🔒 Yes |
| GET | `/api/v1/privacy/access-logs` | PHI access logs | 🔒 Yes |
| GET | `/api/v1/privacy/consents` | Consent status | 🔒 Yes |
| GET | `/api/v1/privacy/regulatory` | Regulatory updates | 🔒 Yes |
| GET | `/api/v1/privacy/breaches` | Breach incidents | 🔒 Yes |
| GET | `/api/v1/privacy/training` | Training compliance | 🔒 Yes |

### Disclosures

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/disclosures/templates` | List disclosure templates | 🔒 Yes |

### Dashboard Stats

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/dashboard-stats` | Dashboard statistics | 🔒 Yes |

### Video Meetings

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/video/my-meetings` | My meetings | 🔒 Yes |

### Error Handling

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/v1/nonexistent-endpoint` | Non-existent endpoint | 🔒 Yes |
| GET | `/api/v1/patients/invalid-uuid-format` | Invalid UUID format | 🔒 Yes |

