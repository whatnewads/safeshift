# Clinician Dashboard Integration Guide

This document provides comprehensive guidance for PHP backend developers on integrating with the new React-based Clinician Dashboard.

## Table of Contents

- [Overview](#overview)
- [What Changed](#what-changed)
- [Required API Endpoints](#required-api-endpoints)
- [CSRF Token Endpoint](#csrf-token-endpoint)
- [Authentication Endpoints](#authentication-endpoints)
- [Dashboard API Endpoints](#dashboard-api-endpoints)
- [Response Formats](#response-formats)
- [Error Handling](#error-handling)
- [CORS Configuration](#cors-configuration)
- [Deployment Options](#deployment-options)
- [Migration Strategy](#migration-strategy)

---

## Overview

The SafeShift Clinician Dashboard has been modernized from a traditional PHP-rendered view to a React Single Page Application (SPA). This provides:

- **Better Performance**: Client-side rendering, code splitting, and caching
- **Enhanced UX**: Instant navigation, real-time updates, responsive design
- **Maintainability**: Component-based architecture, modern tooling
- **Offline Capability**: Service worker support for offline functionality

**Key Point:** The React app is a **frontend only** - it consumes the existing PHP backend APIs. The PHP backend remains the source of truth for all data and business logic.

---

## What Changed

### Before (PHP-rendered)
```
Browser → PHP Router → PHP View → HTML Response
```

### After (React SPA)
```
Browser → React SPA → API Requests → PHP Endpoints → JSON Response
```

### Files Affected

| Old File | New Implementation |
|----------|-------------------|
| `View/clinician/dashboard_view.php` | `clinician-dashboard/src/components/Dashboard/` |
| `View/assets/js/dashboard-clinician.js` | React components & hooks |
| `View/assets/css/dashboard-clinician.css` | Tailwind CSS |

### What Stays the Same

- All PHP API endpoints
- Database schema
- Authentication logic
- Session management
- Business rules

---

## Required API Endpoints

The React dashboard requires the following API endpoints. If these don't exist, they must be created.

### Endpoint Summary

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/csrf-token.php` | GET | Get CSRF token |
| `/api/auth/session.php` | GET | Validate session |
| `/api/auth/login.php` | POST | User login |
| `/api/auth/logout.php` | POST | User logout |
| `/api/dashboard-stats.php` | GET | Dashboard statistics |
| `/api/notifications.php` | GET | User notifications |
| `/api/notifications.php` | PUT | Mark notification read |
| `/api/recent-patients.php` | GET | Recent patient list |
| `/api/patient-vitals.php` | GET | Patient vital signs |
| `/api/patients.php` | GET | Patient search |
| `/api/patients.php` | POST | Create patient |

---

## CSRF Token Endpoint

### `GET /api/csrf-token.php`

Returns a CSRF token for protecting against cross-site request forgery.

**Request:**
```http
GET /api/csrf-token.php HTTP/1.1
Cookie: PHPSESSID=abc123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "a1b2c3d4e5f6g7h8i9j0..."
  }
}
```

**Implementation Example:**
```php
<?php
// api/csrf-token.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../core/Security/CsrfManager.php';

header('Content-Type: application/json');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $csrfManager = new CsrfManager();
    $token = $csrfManager->generateToken();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'token' => $token
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate CSRF token'
    ]);
}
```

**Token Requirements:**
- Minimum 32 characters
- Cryptographically secure random
- Stored in session for validation
- Valid for session duration

---

## Authentication Endpoints

### `GET /api/auth/session.php`

Validates the current session and returns user data if authenticated.

**Request:**
```http
GET /api/auth/session.php HTTP/1.1
Cookie: PHPSESSID=abc123
```

**Response (Authenticated):**
```json
{
  "success": true,
  "data": {
    "authenticated": true,
    "user": {
      "id": 123,
      "username": "jsmith",
      "email": "jsmith@hospital.org",
      "first_name": "John",
      "last_name": "Smith",
      "role": "clinician",
      "role_id": 2,
      "establishment_id": 1,
      "establishment_name": "Main Hospital",
      "permissions": ["view_patients", "edit_patients", "view_dashboard"],
      "last_login": "2024-01-15T10:30:00Z"
    }
  }
}
```

**Response (Not Authenticated):**
```json
{
  "success": true,
  "data": {
    "authenticated": false,
    "user": null
  }
}
```

### `POST /api/auth/login.php`

Authenticates user credentials and creates a session.

**Request:**
```http
POST /api/auth/login.php HTTP/1.1
Content-Type: application/json
X-CSRF-Token: abc123...

{
  "username": "jsmith",
  "password": "securepassword123",
  "remember_me": false
}
```

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 123,
      "username": "jsmith",
      "email": "jsmith@hospital.org",
      "first_name": "John",
      "last_name": "Smith",
      "role": "clinician",
      "role_id": 2,
      "establishment_id": 1,
      "permissions": ["view_patients", "edit_patients", "view_dashboard"]
    },
    "redirect": "/clinician/dashboard"
  }
}
```

**Response (2FA Required):**
```json
{
  "success": true,
  "data": {
    "requires_2fa": true,
    "method": "email",
    "masked_destination": "j***@hospital.org"
  }
}
```

**Response (Failure):**
```json
{
  "success": false,
  "error": {
    "code": "INVALID_CREDENTIALS",
    "message": "Invalid username or password"
  }
}
```

### `POST /api/auth/logout.php`

Terminates the current session.

**Request:**
```http
POST /api/auth/logout.php HTTP/1.1
Cookie: PHPSESSID=abc123
X-CSRF-Token: abc123...
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Successfully logged out",
    "redirect": "/login"
  }
}
```

---

## Dashboard API Endpoints

### `GET /api/dashboard-stats.php`

Returns dashboard statistics for the current user.

**Request:**
```http
GET /api/dashboard-stats.php HTTP/1.1
Cookie: PHPSESSID=abc123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "totalPatients": 156,
    "activeIncidents": 12,
    "pendingReviews": 8,
    "completedToday": 23,
    "trends": {
      "patients": {
        "current": 156,
        "previous": 142,
        "change": 9.86,
        "direction": "up"
      },
      "incidents": {
        "current": 12,
        "previous": 15,
        "change": -20,
        "direction": "down"
      }
    },
    "lastUpdated": "2024-01-15T10:30:00Z"
  }
}
```

### `GET /api/notifications.php`

Returns notifications for the current user.

**Request:**
```http
GET /api/notifications.php?limit=10&unread_only=true HTTP/1.1
Cookie: PHPSESSID=abc123
```

**Query Parameters:**
- `limit` (optional): Max notifications to return (default: 20)
- `unread_only` (optional): Only unread notifications (default: false)
- `page` (optional): Page number for pagination (default: 1)

**Response:**
```json
{
  "success": true,
  "data": {
    "notifications": [
      {
        "id": 1,
        "type": "alert",
        "title": "Critical Lab Result",
        "message": "Patient John Doe has critical potassium level",
        "priority": "high",
        "read": false,
        "action_url": "/patients/123/labs",
        "created_at": "2024-01-15T09:30:00Z"
      },
      {
        "id": 2,
        "type": "info",
        "title": "Shift Change",
        "message": "Your shift starts in 30 minutes",
        "priority": "normal",
        "read": false,
        "action_url": null,
        "created_at": "2024-01-15T08:00:00Z"
      }
    ],
    "unread_count": 5,
    "total": 23,
    "page": 1,
    "pages": 3
  }
}
```

### `PUT /api/notifications.php`

Mark notification(s) as read.

**Request:**
```http
PUT /api/notifications.php HTTP/1.1
Content-Type: application/json
Cookie: PHPSESSID=abc123
X-CSRF-Token: abc123...

{
  "notification_ids": [1, 2, 3],
  "action": "mark_read"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "updated_count": 3
  }
}
```

### `GET /api/recent-patients.php`

Returns recent patients for the clinician.

**Request:**
```http
GET /api/recent-patients.php?limit=5 HTTP/1.1
Cookie: PHPSESSID=abc123
```

**Response:**
```json
{
  "success": true,
  "data": {
    "patients": [
      {
        "id": 123,
        "mrn": "MRN001234",
        "first_name": "John",
        "last_name": "Doe",
        "date_of_birth": "1985-03-15",
        "age": 38,
        "gender": "male",
        "status": "active",
        "last_visit": "2024-01-15T08:30:00Z",
        "chief_complaint": "Chest pain",
        "priority": "high",
        "avatar_url": null
      }
    ],
    "total": 156
  }
}
```

### `GET /api/patient-vitals.php`

Returns vital signs for a specific patient.

**Request:**
```http
GET /api/patient-vitals.php?patient_id=123&hours=24 HTTP/1.1
Cookie: PHPSESSID=abc123
```

**Query Parameters:**
- `patient_id` (required): Patient ID
- `hours` (optional): Hours of history (default: 24)

**Response:**
```json
{
  "success": true,
  "data": {
    "patient_id": 123,
    "vitals": [
      {
        "timestamp": "2024-01-15T10:00:00Z",
        "heart_rate": 72,
        "blood_pressure_systolic": 120,
        "blood_pressure_diastolic": 80,
        "respiratory_rate": 16,
        "temperature": 98.6,
        "oxygen_saturation": 98,
        "pain_level": 2
      },
      {
        "timestamp": "2024-01-15T06:00:00Z",
        "heart_rate": 68,
        "blood_pressure_systolic": 118,
        "blood_pressure_diastolic": 78,
        "respiratory_rate": 14,
        "temperature": 98.4,
        "oxygen_saturation": 99,
        "pain_level": 1
      }
    ],
    "normal_ranges": {
      "heart_rate": { "min": 60, "max": 100 },
      "blood_pressure_systolic": { "min": 90, "max": 140 },
      "blood_pressure_diastolic": { "min": 60, "max": 90 },
      "respiratory_rate": { "min": 12, "max": 20 },
      "temperature": { "min": 97.0, "max": 99.5 },
      "oxygen_saturation": { "min": 95, "max": 100 }
    }
  }
}
```

### `GET /api/patients.php`

Search for patients.

**Request:**
```http
GET /api/patients.php?q=john&limit=20&page=1 HTTP/1.1
Cookie: PHPSESSID=abc123
```

**Query Parameters:**
- `q` (optional): Search query (name, MRN, DOB)
- `status` (optional): Filter by status
- `limit` (optional): Results per page (default: 20)
- `page` (optional): Page number (default: 1)

**Response:**
```json
{
  "success": true,
  "data": {
    "patients": [...],
    "total": 45,
    "page": 1,
    "pages": 3
  }
}
```

### `POST /api/patients.php`

Create a new patient (quick registration).

**Request:**
```http
POST /api/patients.php HTTP/1.1
Content-Type: application/json
Cookie: PHPSESSID=abc123
X-CSRF-Token: abc123...

{
  "first_name": "Jane",
  "last_name": "Doe",
  "date_of_birth": "1990-05-20",
  "gender": "female",
  "phone": "555-0123",
  "chief_complaint": "Headache",
  "priority": "normal"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "patient": {
      "id": 124,
      "mrn": "MRN001235",
      "first_name": "Jane",
      "last_name": "Doe",
      ...
    },
    "message": "Patient created successfully"
  }
}
```

---

## Response Formats

### Standard Success Response

```json
{
  "success": true,
  "data": {
    // Response payload
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "uuid-here"
  }
}
```

### Standard Error Response

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      // Optional additional error details
    }
  },
  "meta": {
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "uuid-here"
  }
}
```

### HTTP Status Codes

| Code | Meaning | Use Case |
|------|---------|----------|
| 200 | OK | Successful GET, PUT |
| 201 | Created | Successful POST (new resource) |
| 400 | Bad Request | Invalid request data |
| 401 | Unauthorized | Not authenticated |
| 403 | Forbidden | No permission |
| 404 | Not Found | Resource not found |
| 422 | Unprocessable | Validation errors |
| 429 | Too Many Requests | Rate limited |
| 500 | Server Error | Internal error |

---

## Error Handling

### Validation Errors (422)

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "fields": {
        "email": ["Invalid email format"],
        "date_of_birth": ["Date cannot be in the future"]
      }
    }
  }
}
```

### Authentication Errors (401)

```json
{
  "success": false,
  "error": {
    "code": "SESSION_EXPIRED",
    "message": "Your session has expired. Please log in again."
  }
}
```

### PHP Implementation

```php
<?php
// Standard error response function
function sendError($code, $message, $httpStatus = 400, $details = null) {
    http_response_code($httpStatus);
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message
        ],
        'meta' => [
            'timestamp' => date('c'),
            'request_id' => uniqid()
        ]
    ];
    
    if ($details) {
        $response['error']['details'] = $details;
    }
    
    echo json_encode($response);
    exit;
}

// Usage
sendError('VALIDATION_ERROR', 'Email is required', 422, [
    'fields' => ['email' => ['Email is required']]
]);
```

---

## CORS Configuration

When the React app is served from a different domain, configure CORS headers.

### PHP Implementation

```php
<?php
// includes/cors.php

$allowedOrigins = [
    'http://localhost:3000',      // Development
    'https://app.yourdomain.com', // Production
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    header('Access-Control-Max-Age: 86400'); // 24 hours cache
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
```

### Include in API Endpoints

```php
<?php
// At the top of each API endpoint
require_once __DIR__ . '/../includes/cors.php';
```

---

## Deployment Options

### Option A: Serve from PHP (Recommended)

The React build is served by the PHP application. Best for:
- Single domain deployment
- No CORS configuration needed
- Shared session management

**Setup:**

1. Build the React app:
   ```bash
   cd clinician-dashboard
   npm run build
   ```

2. Copy to PHP public directory:
   ```bash
   cp -r dist/* /var/www/safeshift/public/app/
   ```

3. Configure PHP router:
   ```php
   // router.php
   if (preg_match('/^\/app/', $_SERVER['REQUEST_URI'])) {
       // Serve React app
       $path = $_SERVER['DOCUMENT_ROOT'] . '/app/index.html';
       if (file_exists($path)) {
           include $path;
           return;
       }
   }
   ```

4. Configure web server (Apache .htaccess):
   ```apache
   # Handle React routes
   RewriteEngine On
   RewriteBase /app/
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^ /app/index.html [L]
   ```

### Option B: Separate Subdomain

React app on a different subdomain (e.g., `app.yourdomain.com`).

**Setup:**

1. Deploy React build to static hosting (Nginx, CDN, etc.)
2. Configure CORS on PHP backend
3. Update `.env`:
   ```env
   VITE_API_URL=https://api.yourdomain.com
   ```

### Option C: CDN + PHP API

React app served from CDN for best performance.

**Setup:**

1. Deploy to CDN (Cloudflare, AWS CloudFront, etc.)
2. Configure CORS
3. Handle SPA routing with CDN rules

---

## Migration Strategy

### Phase 1: Parallel Operation

Run both PHP views and React app simultaneously:

```php
// Check feature flag
if (isFeatureEnabled('react_dashboard')) {
    // Redirect to React app
    header('Location: /app/');
    exit;
} else {
    // Use PHP view
    include 'View/clinician/dashboard_view.php';
}
```

### Phase 2: Gradual Rollout

Enable for specific users/roles:

```php
function shouldUseReactDashboard($user) {
    // Beta testers
    if (in_array($user->id, getBetaTesterIds())) {
        return true;
    }
    
    // Percentage rollout
    if (getFeaturePercentage('react_dashboard') > rand(1, 100)) {
        return true;
    }
    
    return false;
}
```

### Phase 3: Full Migration

1. Monitor error rates and performance
2. Gather user feedback
3. Remove PHP views
4. Update documentation

### Rollback Plan

If issues occur:

1. Disable feature flag
2. Users automatically fall back to PHP views
3. React app remains available for testing
4. Fix issues and re-enable

---

## Testing API Endpoints

### Using cURL

```bash
# Get CSRF token
curl -c cookies.txt http://localhost:8000/api/csrf-token.php

# Login
curl -b cookies.txt -c cookies.txt \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_TOKEN" \
  -d '{"username":"test","password":"test123"}' \
  http://localhost:8000/api/auth/login.php

# Get dashboard stats
curl -b cookies.txt http://localhost:8000/api/dashboard-stats.php
```

### Using Postman

1. Import the API endpoints
2. Set up environment variables for base URL and tokens
3. Configure cookie handling
4. Test each endpoint

---

## Checklist for PHP Developers

- [ ] All required API endpoints exist and return correct formats
- [ ] CSRF token endpoint is implemented
- [ ] Session validation returns user data
- [ ] CORS headers configured (if needed)
- [ ] Error responses follow standard format
- [ ] API endpoints return proper HTTP status codes
- [ ] Validation errors include field-level details
- [ ] Rate limiting in place for sensitive endpoints
- [ ] API documentation is up to date
- [ ] Feature flags configured for gradual rollout

---

## Support

For questions or issues:

1. Check the [React Dashboard README](../clinician-dashboard/README.md)
2. Review PHP error logs
3. Check browser console for API errors
4. Contact the development team

---

*Last updated: December 2024*