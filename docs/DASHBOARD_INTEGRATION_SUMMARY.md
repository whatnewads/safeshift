# Dashboard Integration Summary

## Executive Summary

This document provides a comprehensive summary of the dashboard functionality implementation project for the SafeShift EHR system. All dashboards have been updated to replace placeholder text and mock data with real database-driven content using vertical slice architecture.

### Project Scope

- **Total Dashboards Implemented:** 10
- **Architecture Pattern:** Vertical Slice (View → API → ViewModel → Repository → Database)
- **Status:** ✅ Complete

### Key Accomplishments

- Replaced all placeholder/mock data with real database-driven content
- Implemented consistent vertical slice architecture across all dashboards
- Added proper loading, empty, and error states throughout
- Implemented role-based authorization at the API level
- Used TypeScript types throughout the frontend
- Utilized prepared statements for all database queries

---

## Architecture Pattern

The vertical slice architecture ensures a clean separation of concerns and consistent data flow across all dashboards:

```
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (React/TypeScript)                  │
├─────────────────────────────────────────────────────────────────┤
│  React Component (View)                                         │
│       └── Dashboard Page (e.g., Registration.tsx)               │
│              │                                                  │
│              ▼                                                  │
│  Custom Hook (useXxx)                                           │
│       └── State management, loading/error handling              │
│              │                                                  │
│              ▼                                                  │
│  Service (xxx.service.ts)                                       │
│       └── HTTP calls, request/response transformation           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ HTTP GET/POST
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      BACKEND (PHP)                              │
├─────────────────────────────────────────────────────────────────┤
│  API Endpoint (api/v1/xxx.php)                                  │
│       └── Route parsing, authentication, method dispatch        │
│              │                                                  │
│              ▼                                                  │
│  ViewModel (XxxViewModel.php)                                   │
│       └── Business logic, data aggregation, response shaping    │
│              │                                                  │
│              ▼                                                  │
│  Repository (XxxRepository.php)                                 │
│       └── Database queries, prepared statements, entity mapping │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ SQL Queries
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      DATABASE (MySQL)                           │
├─────────────────────────────────────────────────────────────────┤
│  Tables: encounters, patients, user, audit_log, etc.            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implemented Dashboards

### 1. Registration Dashboard

**Role:** `1clinician` (Intake Clinician)  
**Route:** `/dashboard/registration`

#### Files Created/Modified

| Layer | File Path | Purpose |
|-------|-----------|---------|
| Repository | [`model/Repositories/RegistrationRepository.php`](../model/Repositories/RegistrationRepository.php) | Database queries for registration data |
| ViewModel | [`ViewModel/RegistrationViewModel.php`](../ViewModel/RegistrationViewModel.php) | Business logic and data transformation |
| API | [`api/v1/registration.php`](../api/v1/registration.php) | REST endpoint handler |
| Service | [`src/app/services/registration.service.ts`](../src/app/services/registration.service.ts) | HTTP client service |
| Hook | [`src/app/hooks/useRegistration.ts`](../src/app/hooks/useRegistration.ts) | React state management |
| View | [`src/app/pages/dashboards/Registration.tsx`](../src/app/pages/dashboards/Registration.tsx) | Dashboard UI component |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/registration/dashboard` | GET | Get registration dashboard data |
| `/api/v1/registration/queue` | GET | Get pending registration queue |
| `/api/v1/registration/check-in/:id` | POST | Check in a patient |

#### Key Features

- Queue statistics (pending, checked-in, completed today)
- Pending registrations list with patient details
- Recently completed registrations
- Patient check-in workflow support
- Real-time queue updates

---

### 2. Clinical Provider Dashboard

**Role:** `pclinician` (Clinical Provider)  
**Route:** `/dashboard/provider`

#### Files Created/Modified

| Layer | File Path | Purpose |
|-------|-----------|---------|
| Repository | [`model/Repositories/ClinicalProviderRepository.php`](../model/Repositories/ClinicalProviderRepository.php) | Database queries for provider data |
| ViewModel | [`ViewModel/ClinicalProviderViewModel.php`](../ViewModel/ClinicalProviderViewModel.php) | Business logic and data transformation |
| API | [`api/v1/clinicalprovider.php`](../api/v1/clinicalprovider.php) | REST endpoint handler |
| Service | [`src/app/services/clinicalprovider.service.ts`](../src/app/services/clinicalprovider.service.ts) | HTTP client service |
| Hook | [`src/app/hooks/useClinicalProvider.ts`](../src/app/hooks/useClinicalProvider.ts) | React state management |
| View | [`src/app/pages/dashboards/ClinicalProvider.tsx`](../src/app/pages/dashboards/ClinicalProvider.tsx) | Dashboard UI component |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/clinicalprovider/dashboard` | GET | Get provider dashboard data |
| `/api/v1/clinicalprovider/encounters` | GET | Get provider's encounters |
| `/api/v1/clinicalprovider/patients/:id` | GET | Get patient summary |

#### Key Features

- Provider statistics (in-progress, pending review, completed today)
- Active encounters list with patient information
- Recent encounters history
- Encounter status tracking
- Patient quick access

---

### 3. Privacy Officer Dashboard

**Role:** `PrivacyOfficer`  
**Route:** `/dashboard/privacy`

#### Files Created/Modified

| Layer | File Path | Purpose |
|-------|-----------|---------|
| Repository | [`model/Repositories/PrivacyOfficerRepository.php`](../model/Repositories/PrivacyOfficerRepository.php) | Database queries for privacy/compliance data |
| ViewModel | [`ViewModel/PrivacyOfficerViewModel.php`](../ViewModel/PrivacyOfficerViewModel.php) | Business logic and data transformation |
| API | [`api/v1/privacy.php`](../api/v1/privacy.php) | REST endpoint handler |
| Service | [`src/app/services/privacy.service.ts`](../src/app/services/privacy.service.ts) | HTTP client service |
| Hook | [`src/app/hooks/usePrivacyOfficer.ts`](../src/app/hooks/usePrivacyOfficer.ts) | React state management |
| View | [`src/app/pages/dashboards/PrivacyOfficer.tsx`](../src/app/pages/dashboards/PrivacyOfficer.tsx) | Dashboard UI component |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/privacy/dashboard` | GET | Get privacy officer dashboard |
| `/api/v1/privacy/audit-logs` | GET | Get PHI access audit logs |
| `/api/v1/privacy/audit-logs/export` | POST | Export audit logs |
| `/api/v1/privacy/consents` | GET | Get consent records |

#### Key Features

- Compliance rate metrics and KPIs
- PHI access summary and tracking
- Pending regulatory updates
- Training compliance tracking
- Consent management overview

---

### 4. Security Officer Dashboard

**Role:** `SecurityOfficer`  
**Route:** `/dashboard/security`

#### Files Created/Modified

| Layer | File Path | Purpose |
|-------|-----------|---------|
| Repository | [`model/Repositories/SecurityOfficerRepository.php`](../model/Repositories/SecurityOfficerRepository.php) | Database queries for security data |
| ViewModel | [`ViewModel/SecurityOfficerViewModel.php`](../ViewModel/SecurityOfficerViewModel.php) | Business logic and data transformation |
| API | [`api/v1/security.php`](../api/v1/security.php) | REST endpoint handler |
| Service | [`src/app/services/security.service.ts`](../src/app/services/security.service.ts) | HTTP client service |
| Hook | [`src/app/hooks/useSecurityOfficer.ts`](../src/app/hooks/useSecurityOfficer.ts) | React state management |
| View | [`src/app/pages/dashboards/SecurityOfficer.tsx`](../src/app/pages/dashboards/SecurityOfficer.tsx) | Dashboard UI component |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/security/dashboard` | GET | Get security dashboard data |
| `/api/v1/security/audit-logs` | GET | Get security audit logs |
| `/api/v1/security/anomalies` | GET | Get detected anomalies |
| `/api/v1/security/users/:id/unlock` | POST | Unlock user account |

#### Key Features

- System security status overview
- Real-time event monitoring
- Anomaly detection alerts
- Active user tracking
- MFA status overview
- Failed login monitoring

---

### 5. Admin Dashboard

**Roles:** `tadmin`, `cadmin`  
**Route:** `/dashboard/admin`

#### Files Created/Modified

| Layer | File Path | Purpose |
|-------|-----------|---------|
| Repository | [`model/Repositories/AdminRepository.php`](../model/Repositories/AdminRepository.php) | Database queries for admin data |
| ViewModel | [`ViewModel/AdminViewModel.php`](../ViewModel/AdminViewModel.php) | Business logic and data transformation |
| API | [`api/v1/admin.php`](../api/v1/admin.php) | REST endpoint handler |
| Service | [`src/app/services/admin.service.ts`](../src/app/services/admin.service.ts) | HTTP client service |
| Hook | [`src/app/hooks/useAdmin.ts`](../src/app/hooks/useAdmin.ts) | React state management |
| View | [`src/app/pages/dashboards/Admin.tsx`](../src/app/pages/dashboards/Admin.tsx) | Dashboard UI component |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/admin/dashboard` | GET | Get admin dashboard data |
| `/api/v1/admin/cases` | GET | Get case statistics |
| `/api/v1/admin/compliance-alerts` | GET | Get compliance alerts |
| `/api/v1/admin/training-modules` | GET | Get training modules |

#### Key Features

- Case statistics and management
- Compliance alerts monitoring
- Training module tracking
- OSHA 300 log access (read-only)
- Patient flow metrics
- Site performance overview

---

### 6. SuperAdmin Dashboard

**Role:** `Admin` (System Administrator)  
**Route:** `/dashboard/super-admin`

#### Files Created/Modified

| Layer | File Path | Purpose |
|-------|-----------|---------|
| Repository | [`model/Repositories/SuperAdminRepository.php`](../model/Repositories/SuperAdminRepository.php) | Database queries for system admin data |
| ViewModel | [`ViewModel/SuperAdminViewModel.php`](../ViewModel/SuperAdminViewModel.php) | Business logic and data transformation |
| API | [`api/v1/superadmin.php`](../api/v1/superadmin.php) | REST endpoint handler |
| Service | [`src/app/services/superadmin.service.ts`](../src/app/services/superadmin.service.ts) | HTTP client service |
| Hook | [`src/app/hooks/useSuperAdmin.ts`](../src/app/hooks/useSuperAdmin.ts) | React state management |
| View | [`src/app/pages/dashboards/SuperAdmin.tsx`](../src/app/pages/dashboards/SuperAdmin.tsx) | Dashboard UI component |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/superadmin/dashboard` | GET | Get super admin dashboard |
| `/api/v1/superadmin/users` | GET | Get system users |
| `/api/v1/superadmin/clinics` | GET | Get all clinics |
| `/api/v1/superadmin/incidents` | GET | Get security incidents |

#### Key Features

- System-wide user management
- Multi-clinic overview
- Security incident tracking
- Override request management
- Organization configuration
- Audit statistics

---

### 7. Doctor/MRO Dashboard

**Role:** `pclinician` (with MRO privileges)  
**Route:** `/dashboard/doctor`

#### Files Created/Modified

| Layer | File Path | Purpose |
|-------|-----------|---------|
| Repository | [`model/Repositories/DoctorRepository.php`](../model/Repositories/DoctorRepository.php) | Database queries for doctor data |
| ViewModel | [`ViewModel/DoctorViewModel.php`](../ViewModel/DoctorViewModel.php) | Business logic and data transformation |
| API | [`api/v1/doctor.php`](../api/v1/doctor.php) | REST endpoint handler |
| Service | [`src/app/services/doctor.service.ts`](../src/app/services/doctor.service.ts) | HTTP client service |
| Hook | [`src/app/hooks/useDoctor.ts`](../src/app/hooks/useDoctor.ts) | React state management |
| View | [`src/app/pages/dashboards/Doctor.tsx`](../src/app/pages/dashboards/Doctor.tsx) | Dashboard UI component |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/doctor/dashboard` | GET | Get doctor dashboard data |
| `/api/v1/doctor/mro-queue` | GET | Get MRO review queue |
| `/api/v1/doctor/mro-queue/:testId/verify` | POST | Complete MRO verification |
| `/api/v1/doctor/orders/:orderId/sign` | POST | Sign an order |

#### Key Features

- MRO (Medical Review Officer) verification queue
- DOT test review workflow
- Pending signatures queue
- Patient chart access
- Today's schedule overview

---

### 8. SuperManager Dashboard

**Role:** `Manager` (elevated permissions)  
**Route:** `/dashboard/super-manager`

#### Files Created/Modified

| Layer | File Path | Purpose |
|-------|-----------|---------|
| Repository | [`model/Repositories/SuperManagerRepository.php`](../model/Repositories/SuperManagerRepository.php) | Database queries for manager data |
| ViewModel | [`ViewModel/SuperManagerViewModel.php`](../ViewModel/SuperManagerViewModel.php) | Business logic and data transformation |
| API | [`api/v1/supermanager.php`](../api/v1/supermanager.php) | REST endpoint handler |
| Service | [`src/app/services/supermanager.service.ts`](../src/app/services/supermanager.service.ts) | HTTP client service |
| Hook | [`src/app/hooks/useSuperManager.ts`](../src/app/hooks/useSuperManager.ts) | React state management |
| View | [`src/app/pages/dashboards/SuperManager.tsx`](../src/app/pages/dashboards/SuperManager.tsx) | Dashboard UI component |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/supermanager/dashboard` | GET | Get super manager dashboard |
| `/api/v1/supermanager/clinics` | GET | Get all clinics with stats |
| `/api/v1/supermanager/staff` | GET | Get staff across clinics |
| `/api/v1/supermanager/staff/:id/roles` | PUT | Update staff roles |

#### Key Features

- Multi-clinic oversight
- Cross-clinic statistics
- Staff management
- Aggregate reporting
- Compliance overview
- Alert summary across locations

---

### 9. Manager Dashboard

**Role:** `Manager`  
**Route:** `/dashboard/manager`  
**Status:** ✅ Previously Integrated (Verified Complete)

#### Files

| Layer | File Path |
|-------|-----------|
| View | [`src/app/pages/dashboards/Manager.tsx`](../src/app/pages/dashboards/Manager.tsx) |

#### Key Features

- Clinic operations overview
- Staff management
- Performance metrics
- Compliance tracking

---

### 10. Technician Dashboard

**Role:** `Technician`  
**Route:** `/dashboard/technician`  
**Status:** ✅ Previously Integrated (Verified Complete)

#### Files

| Layer | File Path |
|-------|-----------|
| View | [`src/app/pages/dashboards/Technician.tsx`](../src/app/pages/dashboards/Technician.tsx) |

#### Key Features

- Testing queue management
- Sample collection workflow
- Equipment status tracking
- Daily workload overview

---

### Notifications System

**Status:** ✅ Verified Complete

#### Files

| Layer | File Path |
|-------|-----------|
| Repository | [`model/Repositories/NotificationRepository.php`](../model/Repositories/NotificationRepository.php) |
| ViewModel | [`ViewModel/NotificationsViewModel.php`](../ViewModel/NotificationsViewModel.php) |
| API | [`api/v1/notifications.php`](../api/v1/notifications.php) |
| Service | [`src/app/services/notification.service.ts`](../src/app/services/notification.service.ts) |
| Hook | [`src/app/hooks/useNotifications.ts`](../src/app/hooks/useNotifications.ts) |

#### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/notifications` | GET | List notifications |
| `/api/v1/notifications/:id/read` | PUT | Mark as read |
| `/api/v1/notifications/read-all` | PUT | Mark all as read |
| `/api/v1/notifications/:id` | DELETE | Delete notification |

---

## Common Features Implemented

All dashboards implement the following consistent patterns:

### Loading States
- Skeleton components displayed during initial data fetch
- Prevents layout shift during loading
- Consistent visual feedback across all dashboards

### Empty States
- Helpful messages when no data is available
- Clear call-to-action guidance
- Role-appropriate messaging

### Error States
- Error message display with context
- Retry capability for failed requests
- Graceful degradation

### Authorization
- Role-based authorization at API level
- Session validation on each request
- Appropriate error responses for unauthorized access

### TypeScript Types
- Strong typing throughout frontend code
- Interface definitions for all API responses
- Type-safe component props

### Database Security
- Prepared statements for all database queries
- Parameterized inputs to prevent SQL injection
- Input validation at ViewModel layer

---

## API Endpoints Reference

### Complete Endpoint Table

| Endpoint | Method | Auth Required | Description |
|----------|--------|---------------|-------------|
| `/api/v1/registration/dashboard` | GET | ✅ | Registration dashboard data |
| `/api/v1/registration/queue` | GET | ✅ | Pending registration queue |
| `/api/v1/registration/check-in/:id` | POST | ✅ | Check in patient |
| `/api/v1/clinicalprovider/dashboard` | GET | ✅ | Provider dashboard data |
| `/api/v1/clinicalprovider/encounters` | GET | ✅ | Provider's encounters |
| `/api/v1/clinicalprovider/patients/:id` | GET | ✅ | Patient summary |
| `/api/v1/privacy/dashboard` | GET | ✅ | Privacy officer dashboard |
| `/api/v1/privacy/audit-logs` | GET | ✅ | PHI access audit logs |
| `/api/v1/privacy/audit-logs/export` | POST | ✅ | Export audit logs |
| `/api/v1/privacy/consents` | GET | ✅ | Consent records |
| `/api/v1/security/dashboard` | GET | ✅ | Security dashboard data |
| `/api/v1/security/audit-logs` | GET | ✅ | Security audit logs |
| `/api/v1/security/anomalies` | GET | ✅ | Detected anomalies |
| `/api/v1/security/users/:id/unlock` | POST | ✅ | Unlock user account |
| `/api/v1/admin/dashboard` | GET | ✅ | Admin dashboard data |
| `/api/v1/admin/cases` | GET | ✅ | Case statistics |
| `/api/v1/admin/compliance-alerts` | GET | ✅ | Compliance alerts |
| `/api/v1/admin/training-modules` | GET | ✅ | Training modules |
| `/api/v1/superadmin/dashboard` | GET | ✅ | Super admin dashboard |
| `/api/v1/superadmin/users` | GET | ✅ | System users |
| `/api/v1/superadmin/clinics` | GET | ✅ | All clinics |
| `/api/v1/superadmin/incidents` | GET | ✅ | Security incidents |
| `/api/v1/doctor/dashboard` | GET | ✅ | Doctor dashboard data |
| `/api/v1/doctor/mro-queue` | GET | ✅ | MRO review queue |
| `/api/v1/doctor/mro-queue/:testId/verify` | POST | ✅ | Complete MRO verification |
| `/api/v1/doctor/orders/:orderId/sign` | POST | ✅ | Sign an order |
| `/api/v1/supermanager/dashboard` | GET | ✅ | Super manager dashboard |
| `/api/v1/supermanager/clinics` | GET | ✅ | All clinics with stats |
| `/api/v1/supermanager/staff` | GET | ✅ | Staff across clinics |
| `/api/v1/supermanager/staff/:id/roles` | PUT | ✅ | Update staff roles |
| `/api/v1/notifications` | GET | ✅ | List notifications |
| `/api/v1/notifications/:id/read` | PUT | ✅ | Mark notification as read |
| `/api/v1/notifications/read-all` | PUT | ✅ | Mark all as read |
| `/api/v1/notifications/:id` | DELETE | ✅ | Delete notification |

---

## Database Tables Referenced

### Primary Tables by Dashboard

| Dashboard | Primary Tables |
|-----------|---------------|
| Registration | `appointments`, `patients`, `encounters` |
| Clinical Provider | `encounters`, `patients`, `encounter_observations` |
| Privacy Officer | `audit_log`, `compliance_kpis`, `compliance_kpi_values`, `consents`, `regulatory_updates`, `staff_training_records` |
| Security Officer | `audit_log`, `auditevent`, `user`, `user_device` |
| Admin | `encounters`, `cases`, `compliance_alerts`, `training_requirements`, `osha_300_log` |
| SuperAdmin | `user`, `establishment`, `audit_log`, `override_requests` |
| Doctor | `encounters`, `patients`, `dot_tests`, `encounter_orders` |
| SuperManager | `encounters`, `establishment`, `user`, `userrole`, `compliance_kpi_values` |
| Manager | `encounters`, `user`, `establishment` |
| Technician | `dot_tests`, `samples`, `equipment` |
| Notifications | `user_notification` |

---

## Testing Recommendations

### Manual Testing Steps

#### For Each Dashboard:

1. **Authentication Test**
   - Attempt to access dashboard without login → Should redirect to login
   - Login with correct role → Should see dashboard with real data
   - Login with incorrect role → Should see access denied

2. **Data Loading Test**
   - Verify loading skeleton appears during fetch
   - Confirm real data replaces loading state
   - Check data matches database records

3. **Empty State Test**
   - Remove all relevant data from database
   - Verify helpful empty state message appears
   - Confirm no JavaScript errors in console

4. **Error State Test**
   - Disable database connection
   - Verify error message appears
   - Confirm retry button works

5. **Action Test** (where applicable)
   - Test check-in, mark as read, unlock user, etc.
   - Verify database updates correctly
   - Confirm UI refreshes with new state

### Integration Test Requirements

- API endpoint authentication tests
- ViewModel unit tests for data transformation
- Repository tests with test database
- End-to-end tests for critical workflows

---

## Future Enhancements

### Opportunities for Additional Workflows

1. **Registration Dashboard**
   - Batch check-in capability
   - Appointment scheduling integration
   - Wait time estimation

2. **Clinical Provider Dashboard**
   - Encounter handoff workflow
   - Quick note templates
   - Patient history quick view

3. **Privacy Officer Dashboard**
   - Automated compliance reporting
   - Breach notification workflow
   - Training reminder automation

4. **Security Officer Dashboard**
   - Automated anomaly alerting
   - Session termination capability
   - Device management features

5. **Admin Dashboard**
   - Customizable KPI thresholds
   - Report generation
   - Staff scheduling integration

6. **SuperAdmin Dashboard**
   - Bulk user management
   - System configuration UI
   - Backup/restore functionality

### TODO Items Discovered

- [ ] Add pagination to long lists (encounters, audit logs)
- [ ] Implement real-time updates via WebSocket for security dashboard
- [ ] Add export functionality to all dashboards
- [ ] Create dashboard-specific help documentation
- [ ] Add keyboard shortcuts for power users
- [ ] Implement dashboard customization (widget arrangement)
- [ ] Add date range filters to historical data views

---

## Appendix: File Structure Overview

```
project/
├── api/v1/
│   ├── admin.php
│   ├── clinicalprovider.php
│   ├── doctor.php
│   ├── notifications.php
│   ├── privacy.php
│   ├── registration.php
│   ├── security.php
│   ├── superadmin.php
│   └── supermanager.php
│
├── model/Repositories/
│   ├── AdminRepository.php
│   ├── ClinicalProviderRepository.php
│   ├── DoctorRepository.php
│   ├── NotificationRepository.php
│   ├── PrivacyOfficerRepository.php
│   ├── RegistrationRepository.php
│   ├── SecurityOfficerRepository.php
│   ├── SuperAdminRepository.php
│   └── SuperManagerRepository.php
│
├── ViewModel/
│   ├── AdminViewModel.php
│   ├── ClinicalProviderViewModel.php
│   ├── DoctorViewModel.php
│   ├── NotificationsViewModel.php
│   ├── PrivacyOfficerViewModel.php
│   ├── RegistrationViewModel.php
│   ├── SecurityOfficerViewModel.php
│   ├── SuperAdminViewModel.php
│   └── SuperManagerViewModel.php
│
├── src/app/
│   ├── hooks/
│   │   ├── useAdmin.ts
│   │   ├── useClinicalProvider.ts
│   │   ├── useDoctor.ts
│   │   ├── useNotifications.ts
│   │   ├── usePrivacyOfficer.ts
│   │   ├── useRegistration.ts
│   │   ├── useSecurityOfficer.ts
│   │   ├── useSuperAdmin.ts
│   │   └── useSuperManager.ts
│   │
│   ├── services/
│   │   ├── admin.service.ts
│   │   ├── clinicalprovider.service.ts
│   │   ├── doctor.service.ts
│   │   ├── notification.service.ts
│   │   ├── privacy.service.ts
│   │   ├── registration.service.ts
│   │   ├── security.service.ts
│   │   ├── superadmin.service.ts
│   │   └── supermanager.service.ts
│   │
│   └── pages/dashboards/
│       ├── Admin.tsx
│       ├── ClinicalProvider.tsx
│       ├── Doctor.tsx
│       ├── Manager.tsx
│       ├── PrivacyOfficer.tsx
│       ├── Registration.tsx
│       ├── SecurityOfficer.tsx
│       ├── SuperAdmin.tsx
│       ├── SuperManager.tsx
│       └── Technician.tsx
```

---

*Document generated: December 27, 2025*  
*Version: 1.0*
