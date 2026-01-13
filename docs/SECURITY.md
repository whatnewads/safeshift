# SafeShift EHR Security Documentation

## Overview

SafeShift EHR implements defense-in-depth security for HIPAA compliance. This document describes the security architecture, authentication mechanisms, authorization model, and data protection measures.

## Authentication

### Login Flow

1. User submits credentials (username/password) to [`POST /api/v1/auth/login`](../api/v1/auth.php:186)
2. Backend validates against bcrypt hash (cost 12)
3. Rate limiting enforced: 5 attempts per 5 minutes
4. If 2FA enabled: OTP sent via email/SMS
5. OTP validated at [`POST /api/v1/auth/verify-2fa`](../api/v1/auth.php:226) (10 attempts per 10 minutes limit)
6. Session created with secure settings
7. CSRF token generated and provided via [`GET /api/v1/auth/csrf-token`](../api/v1/auth.php:329)

### Session Security

- **HttpOnly cookies** - JavaScript cannot access session cookie
- **Secure flag** - HTTPS only in production environments
- **SameSite=Lax** - CSRF protection via browser cookie policy
- **Session regeneration** - On privilege change (login, 2FA verification)
- **60-minute timeout** - Configurable session inactivity timeout
- **Session fingerprinting** - IP + User Agent validation

### Password Requirements

- Minimum 12 characters
- Must include: uppercase, lowercase, number, special character
- Bcrypt hashing with cost factor 12
- Password history tracking (no reuse of last 5 passwords)

### Two-Factor Authentication (2FA)

- OTP delivered via email or SMS
- 6-digit codes with time-based expiration
- Rate limited: 3 resend requests per 5 minutes
- Implemented in [`handleResendOtp()`](../api/v1/auth.php:261)

## Authorization

### Role-Based Access Control (RBAC)

The system uses a comprehensive RBAC model defined in [`RoleService`](../model/Services/RoleService.php).

| Backend Role | UI Role | Description | Primary Permissions |
|--------------|---------|-------------|---------------------|
| `Admin` | `super-admin` | System administrator | Full access (`*`) |
| `Manager` | `manager` | Clinic manager | All except system config |
| `pclinician` | `provider` | Clinical provider | Patient/encounter management |
| `1clinician` | `registration` | Intake clinician | Patient registration |
| `dclinician` | `technician` | Drug screen technician | DOT testing |
| `cadmin` | `admin` | Clinic administrator | Clinic-level management |
| `tadmin` | `admin` | Technical administrator | System configuration |
| `QA` | `qa` | Quality assurance | View/review only |
| `PrivacyOfficer` | `privacy-officer` | Privacy compliance | Audit access |
| `SecurityOfficer` | `security-officer` | Security compliance | Security logs access |

### Permission System

Permissions follow the format `resource.action`. See [`RoleService::ROLE_PERMISSIONS`](../model/Services/RoleService.php:204) for the complete mapping.

**Permission Categories:**
- `patient.*` - Patient record access
- `encounter.*` - Encounter management
- `vitals.*` - Vitals recording
- `dot.*` - DOT testing
- `osha.*` - OSHA reporting
- `reports.*` - Report generation
- `user.*` - User management
- `audit.*` - Audit log access
- `system.*` - System configuration
- `privacy.*` - Privacy controls
- `security.*` - Security controls
- `*` - Full system access (Admin only)

### Permission Enforcement

- **Backend**: [`AuthorizationService`](../model/Services/AuthorizationService.php) checks permissions on every request
- **Frontend**: [`ProtectedRoute`](../src/app/components/ProtectedRoute.tsx) components hide unauthorized UI
- **API**: Returns 403 Forbidden for unauthorized access

### Key Authorization Methods

```php
// Check if user can perform action
AuthorizationService::can($user, 'view', 'patient');

// Check specific patient access with clinic filtering
AuthorizationService::canViewPatient($user, $patientId, $clinicId);

// Check encounter edit permissions
AuthorizationService::canEditEncounter($user, $encounter);

// Require permission or throw exception
AuthorizationService::requirePermission($user, 'patient.create');
```

## Data Protection

### Encryption

| Data Type | Method | Notes |
|-----------|--------|-------|
| SSN | AES-256-GCM | Encrypted at rest in database |
| Passwords | Bcrypt (cost 12) | One-way hash |
| Database | TDE recommended | Transparent Data Encryption |
| Transport | TLS 1.3 | Required in production |

### PHI Handling

- **SSN masking** - Displayed as `***-**-1234` in UI
- **Audit logging** - All PHI access logged to [`AuditEvent`](../core/Services/AuditService.php) table
- **Error sanitization** - No PHI in error messages or logs
- **Minimum necessary** - Access restricted to role-appropriate data

## CSRF Protection

Implemented in [`api/v1/auth.php`](../api/v1/auth.php:95).

- Unique token per session
- Token regenerated on login
- Required for all state-changing requests (POST, PUT, DELETE)
- Validated in backend middleware
- Token provided via `GET /api/v1/auth/csrf-token`
- Frontend sends token in `X-CSRF-Token` header

### Endpoints Exempt from CSRF

- `POST /api/v1/auth/login` - No session yet
- `POST /api/v1/auth/verify-2fa` - Session not fully established
- `POST /api/v1/auth/resend-otp` - Session not fully established

## Rate Limiting

Implemented in [`checkRateLimit()`](../api/v1/auth.php:37).

| Endpoint | Limit | Window |
|----------|-------|--------|
| Login | 5 requests | 5 minutes |
| 2FA verification | 10 requests | 10 minutes |
| OTP resend | 3 requests | 5 minutes |
| API general | 100 requests | 1 minute |

Rate limit response includes `Retry-After` header.

## Audit Logging

Implemented in [`AuditService`](../core/Services/AuditService.php).

### Logged Events

- All authentication events (login, logout, failed attempts)
- All PHI access (view, create, update, delete)
- All authorization failures
- System configuration changes
- Dashboard access
- Export operations

### Audit Event Structure

```php
[
    'audit_id' => 'UUID',
    'user_id' => 'user ID',
    'action' => 'action type',
    'subject_type' => 'resource type',
    'subject_id' => 'resource ID',
    'details' => 'JSON metadata',
    'source_ip' => 'client IP',
    'user_agent' => 'browser info',
    'session_id' => 'session ID',
    'checksum' => 'SHA-256 integrity hash',
    'occurred_at' => 'timestamp'
]
```

### Audit Integrity

- Checksum calculated using SHA-256 with salt
- Tamper detection via [`verifyIntegrity()`](../core/Services/AuditService.php:429)
- Retained for 7 years (HIPAA requirement)
- Archival after 2 years to archive table

### Key Audit Methods

```php
// General audit logging
$auditService->audit($actionType, $resourceType, $resourceId, $description, $metadata);

// Specific event logging
$auditService->logLogin($userId, $success);
$auditService->logAccessDenied($resourceType, $resourceId, $reason);
$auditService->logDashboardAccess($dashboardName, $userId, $metadata);
$auditService->logPatientRegistration($patientId, $registrationType);
```

## Security Headers

Applied to all API responses:

| Header | Value | Purpose |
|--------|-------|---------|
| `X-Content-Type-Options` | `nosniff` | Prevent MIME sniffing |
| `X-Frame-Options` | `SAMEORIGIN` | Prevent clickjacking |
| `X-XSS-Protection` | `1; mode=block` | XSS filter (legacy) |
| `Strict-Transport-Security` | `max-age=31536000` | Force HTTPS (production) |
| `Content-Security-Policy` | Configured | Script/resource restrictions |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Control referrer info |

## Input Validation

### Server-Side Validation

- All input sanitized before processing
- SQL injection prevention via prepared statements
- XSS prevention via output encoding
- File upload validation (type, size, content)

### Validation Services

- [`PatientValidator`](../model/Validators/PatientValidator.php) - Patient data validation
- [`EncounterValidator`](../model/Validators/EncounterValidator.php) - Encounter data validation

### SQL Injection Prevention

All database queries use PDO prepared statements:

```php
$stmt = $db->prepare("SELECT * FROM Patient WHERE patient_id = :id");
$stmt->execute(['id' => $patientId]);
```

## API Security

### Response Security

- JSON-only responses (no HTML in errors)
- No sensitive data in error messages
- Request size limits enforced
- Content-Type validation (`application/json`)

### CORS Configuration

Configured in [`api/v1/auth.php`](../api/v1/auth.php:415):

```php
$allowedOrigins = [
    'http://localhost:5173',  // Vite dev server
    'http://localhost:3000',  // Alternative dev server
];
```

Production should use specific domain allowlist.

## Session Management

### Frontend Session Monitoring

Implemented in [`useSessionManagement.ts`](../src/app/hooks/useSessionManagement.ts):

- Activity tracking (mouse, keyboard, scroll)
- Warning displayed before timeout
- Automatic refresh on user activity
- Logout on session expiration

### Backend Session Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/auth/session-status` | GET | Check session validity |
| `/api/v1/auth/refresh-session` | POST | Extend session timeout |
| `/api/v1/auth/logout` | POST | Destroy session |

## Security Incident Response

### Detection

- Failed login attempts logged
- Authorization failures logged
- Rate limit violations tracked
- Audit log integrity verification

### Monitoring

```php
// Get audit statistics
$stats = $auditService->getStatistics(30); // Last 30 days

// Includes:
// - events_by_action
// - events_by_resource
// - top_users
// - failed_access_attempts
// - events_per_day
```

### Response Procedures

1. Security event detected in audit logs
2. Alert generated for Security Officer
3. Investigation using audit trail
4. Containment actions (account lockout, etc.)
5. Documentation and reporting

## Development Security Guidelines

### Code Review Checklist

- [ ] All user input validated and sanitized
- [ ] Prepared statements for all database queries
- [ ] Proper error handling without exposing internals
- [ ] Authentication required for protected endpoints
- [ ] Authorization checks for all resource access
- [ ] PHI access logged to audit trail
- [ ] CSRF tokens validated for state-changing requests

### Testing Requirements

- Unit tests for authorization logic
- Integration tests for authentication flow
- Security tests for CSRF, XSS, SQLi
- Audit log verification tests

## Configuration

### Environment Variables

```env
# Session Configuration
SESSION_LIFETIME=3600
SESSION_SECURE=true       # HTTPS only
SESSION_HTTPONLY=true     # No JS access

# Encryption
ENCRYPTION_KEY=<32-byte-key>
AUDIT_SALT=<random-salt>

# Rate Limiting
RATE_LIMIT_LOGIN=5
RATE_LIMIT_WINDOW=300

# CORS
ALLOWED_ORIGINS=https://yourdomain.com
```

### Production Checklist

- [ ] TLS 1.3 enabled and enforced
- [ ] Session secure flag enabled
- [ ] HSTS header configured
- [ ] Strong encryption keys set
- [ ] Audit log retention configured
- [ ] Backup encryption enabled
- [ ] Database TDE enabled
- [ ] Rate limiting tuned for production load

## Related Documentation

- [HIPAA Compliance Checklist](./HIPAA_COMPLIANCE.md)
- [Testing Guide](./TESTING_GUIDE.md)
- [API Integration Guide](./INTEGRATION_GUIDE.md)
