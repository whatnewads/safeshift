# SafeShift EHR Role Mapping

## Overview

This document describes the role mapping system used to maintain consistency between the PHP backend and React frontend in the SafeShift EHR application.

The system maps backend database roles to frontend UI roles, ensuring consistent routing, permission handling, and access control across the entire application.

## Architecture

### Files Involved

| Layer | File | Purpose |
|-------|------|---------|
| Backend | [`/model/Services/RoleService.php`](../model/Services/RoleService.php) | Central role mapping and permissions |
| Backend | [`/model/Services/AuthorizationService.php`](../model/Services/AuthorizationService.php) | Permission checking and access control |
| Backend | [`/ViewModel/Auth/AuthViewModel.php`](../ViewModel/Auth/AuthViewModel.php) | Returns user with role mappings |
| Frontend | [`/src/app/utils/roleMapper.ts`](../src/app/utils/roleMapper.ts) | Frontend role mapping utilities |
| Frontend | [`/src/app/types/api.types.ts`](../src/app/types/api.types.ts) | TypeScript type definitions |

### Data Flow

```
Database → Backend Role → RoleService → API Response → Frontend → UIRole → Routing
```

1. User logs in with credentials
2. Backend retrieves user with database role (e.g., `pclinician`)
3. `RoleService` maps to UI role and attaches permissions
4. `AuthViewModel` formats response with both roles
5. Frontend receives `role`, `uiRole`, `permissions`, and `dashboardRoute`
6. Frontend uses `uiRole` for routing, `permissions` for access control

## Role Mappings

### Primary Role Mapping Table

| Backend Role | UI Role | Display Name | Dashboard Route |
|-------------|---------|--------------|-----------------|
| `1clinician` | `registration` | Intake Clinician | `/dashboard/registration` |
| `dclinician` | `technician` | Drug Screen Technician | `/dashboard/technician` |
| `pclinician` | `provider` | Clinical Provider | `/dashboard/provider` |
| `cadmin` | `admin` | Clinic Administrator | `/dashboard/admin` |
| `tadmin` | `admin` | Technical Administrator | `/dashboard/admin` |
| `Admin` | `super-admin` | System Administrator | `/dashboard/super-admin` |
| `Manager` | `manager` | Manager | `/dashboard/manager` |
| `QA` | `qa` | Quality Assurance | `/dashboard/qa` |
| `PrivacyOfficer` | `privacy-officer` | Privacy Officer | `/dashboard/privacy` |
| `SecurityOfficer` | `security-officer` | Security Officer | `/dashboard/security` |

### Backend Role Descriptions

- **`1clinician`** - Intake clinicians handling patient registration and check-in
- **`dclinician`** - Drug screen clinicians managing DOT testing workflows
- **`pclinician`** - Primary clinical providers (doctors, nurses, NPs, PAs)
- **`cadmin`** - Clinic administrators managing clinic operations
- **`tadmin`** - Technical administrators handling system configuration
- **`Admin`** - Super administrators with full system access
- **`Manager`** - Managers overseeing operations and reports
- **`QA`** - Quality assurance staff reviewing encounters
- **`PrivacyOfficer`** - HIPAA privacy compliance officers
- **`SecurityOfficer`** - Security compliance officers

### UI Role Descriptions

- **`provider`** - Clinical staff who see and treat patients
- **`registration`** - Front desk staff handling patient intake
- **`admin`** - Administrative users managing clinic/system
- **`super-admin`** - Full system administrators
- **`technician`** - Lab and diagnostic technicians
- **`manager`** - Operational managers
- **`qa`** - Quality assurance reviewers
- **`privacy-officer`** - Privacy compliance staff
- **`security-officer`** - Security compliance staff

## Permissions

### Permission Format

Permissions follow the pattern: `resource.action`

Examples:
- `patient.view` - View patient records
- `encounter.create` - Create new encounters
- `user.*` - All user management permissions

### Permission Categories

#### Patient Permissions
| Permission | Description |
|-----------|-------------|
| `patient.view` | View patient records |
| `patient.create` | Create new patients |
| `patient.edit` | Edit patient information |
| `patient.delete` | Delete patients (soft delete) |
| `patient.*` | All patient permissions |

#### Encounter Permissions
| Permission | Description |
|-----------|-------------|
| `encounter.view` | View encounters |
| `encounter.create` | Create encounters |
| `encounter.edit` | Edit open encounters |
| `encounter.sign` | Sign/finalize encounters |
| `encounter.review` | QA review encounters |
| `encounter.amend` | Amend signed encounters |
| `encounter.*` | All encounter permissions |

#### Vitals Permissions
| Permission | Description |
|-----------|-------------|
| `vitals.view` | View vital signs |
| `vitals.record` | Record vital signs |
| `vitals.*` | All vitals permissions |

#### DOT Testing Permissions
| Permission | Description |
|-----------|-------------|
| `dot.view` | View DOT test results |
| `dot.manage` | Manage DOT testing workflow |
| `dot.*` | All DOT permissions |

#### OSHA Permissions
| Permission | Description |
|-----------|-------------|
| `osha.view` | View OSHA reports |
| `osha.manage` | Manage OSHA data |
| `osha.report` | Generate OSHA reports |
| `osha.*` | All OSHA permissions |

#### Reports Permissions
| Permission | Description |
|-----------|-------------|
| `reports.view` | View reports |
| `reports.export` | Export reports |
| `reports.*` | All reports permissions |

#### User Management Permissions
| Permission | Description |
|-----------|-------------|
| `user.view` | View user accounts |
| `user.create` | Create users |
| `user.edit` | Edit users |
| `user.delete` | Delete users |
| `user.manage` | Manage user roles |
| `user.*` | All user permissions |

#### Audit Permissions
| Permission | Description |
|-----------|-------------|
| `audit.view` | View audit logs |
| `audit.export` | Export audit logs |
| `audit.*` | All audit permissions |

#### System Permissions
| Permission | Description |
|-----------|-------------|
| `system.configure` | Configure system settings |
| `system.*` | All system permissions |

#### Privacy/Security Permissions
| Permission | Description |
|-----------|-------------|
| `privacy.view` | View privacy settings |
| `privacy.manage` | Manage privacy settings |
| `privacy.*` | All privacy permissions |
| `security.view` | View security settings |
| `security.manage` | Manage security settings |
| `security.*` | All security permissions |

#### Special Permissions
| Permission | Description |
|-----------|-------------|
| `*` | Full system access (super-admin only) |

### Permissions by Role

```
1clinician:
  - patient.view
  - patient.create
  - encounter.view

dclinician:
  - patient.view
  - encounter.view
  - encounter.create
  - dot.manage

pclinician:
  - patient.view
  - patient.create
  - patient.edit
  - encounter.view
  - encounter.create
  - encounter.sign
  - vitals.record

cadmin:
  - patient.*
  - encounter.*
  - user.view
  - reports.view

tadmin:
  - patient.*
  - encounter.*
  - user.view
  - system.configure

Admin:
  - * (full access)

Manager:
  - patient.*
  - encounter.*
  - user.*
  - reports.*
  - osha.*

QA:
  - patient.view
  - encounter.view
  - encounter.review
  - reports.view

PrivacyOfficer:
  - patient.view
  - encounter.view
  - audit.view
  - reports.view
  - privacy.*

SecurityOfficer:
  - audit.view
  - audit.export
  - security.*
  - user.view
  - reports.view
```

## API Response Format

### User Data Structure

When a user authenticates, the API returns the following structure:

```json
{
  "id": "user-uuid",
  "username": "jdoe",
  "email": "jdoe@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "role": "pclinician",
  "uiRole": "provider",
  "displayRole": "Clinical Provider",
  "permissions": [
    "patient.view",
    "patient.create",
    "patient.edit",
    "encounter.view",
    "encounter.create",
    "encounter.sign",
    "vitals.record"
  ],
  "dashboardRoute": "/dashboard/provider",
  "primary_role": {
    "id": "role-uuid",
    "name": "Primary Clinician",
    "slug": "pclinician"
  },
  "roles": [...],
  "clinicId": "clinic-uuid",
  "clinicName": "Main Clinic",
  "twoFactorEnabled": true,
  "lastLogin": "2024-01-15T10:30:00Z"
}
```

### Key Fields

- **`role`** - Original backend database role
- **`uiRole`** - Mapped UI role for frontend routing
- **`displayRole`** - Human-readable role name for display
- **`permissions`** - Array of permission strings for access control
- **`dashboardRoute`** - Default dashboard route for this role

## Integration Guide

### Backend: Checking Permissions

```php
use Model\Services\RoleService;
use Model\Services\AuthorizationService;

// Check if role has permission
if (RoleService::hasPermission($user['role'], 'patient.create')) {
    // Allow patient creation
}

// Using AuthorizationService
if (AuthorizationService::can($user, 'create', 'patient')) {
    // Allow patient creation
}

// Require permission (throws exception if denied)
AuthorizationService::requirePermission($user, 'encounter.sign');
```

### Frontend: Using Role Data

```typescript
import { mapBackendRole, hasPermission, getDashboardRoute } from '@/utils/roleMapper';

// Map backend role to UI role
const uiRole = mapBackendRole(user.role); // 'provider'

// Check permission
if (hasPermission(user.role, 'patient.create')) {
    // Show create patient button
}

// Get dashboard route
const dashboardRoute = getDashboardRoute(user.uiRole);
```

### Route Protection

```tsx
// Using role-based route protection
<ProtectedRoute allowedRoles={['provider', 'admin', 'super-admin']}>
    <PatientWorkspace />
</ProtectedRoute>

// Using permission-based route protection
<PermissionGate permission="encounter.create">
    <CreateEncounterButton />
</PermissionGate>
```

## Synchronization Requirements

### Critical: Both files must stay in sync!

When adding or modifying roles:

1. Update [`/model/Services/RoleService.php`](../model/Services/RoleService.php):
   - Add role constant
   - Update `UI_ROLE_MAP`
   - Update `ROLE_DISPLAY_NAMES`
   - Update `DASHBOARD_ROUTES`
   - Update `ROLE_PERMISSIONS`

2. Update [`/src/app/utils/roleMapper.ts`](../src/app/utils/roleMapper.ts):
   - Add role to `BackendRole` type
   - Update `ROLE_MAP`
   - Update `ROLE_PERMISSIONS`
   - Update `ROLE_DISPLAY_NAMES`
   - Update `BACKEND_DASHBOARD_ROUTES`

3. Update this documentation

### Testing Synchronization

Run the following checks when modifying roles:

1. Verify all backend roles have UI mappings
2. Verify all permissions are documented
3. Test login flow for affected roles
4. Verify dashboard routing works correctly
5. Test permission checks on protected resources

## Troubleshooting

### Common Issues

1. **Unknown role warning in console**
   - Backend is sending a role not in the mapping
   - Check `ROLE_MAP` in both PHP and TypeScript

2. **Permission denied unexpectedly**
   - Verify role has the permission in `ROLE_PERMISSIONS`
   - Check wildcard permissions are being evaluated

3. **Wrong dashboard redirect**
   - Check `DASHBOARD_ROUTES` mapping
   - Verify `uiRole` is correct in API response

4. **Role not displaying correctly**
   - Check `ROLE_DISPLAY_NAMES` mapping
   - Verify `displayRole` field in API response

## Changelog

### Version 1.0.0 (2024-12-25)
- Initial role mapping implementation
- Created RoleService.php with centralized role logic
- Created AuthorizationService.php for permission checking
- Updated AuthViewModel to include role mappings
- Updated frontend roleMapper.ts to match backend
- Added TypeScript types for role data
- Created this documentation
