# SafeShift EHR Refactored Components Documentation

**Last Updated:** November 25, 2025  
**Architecture Pattern:** Model-View-ViewModel (MVVM)  
**Backward Compatibility:** 100% Maintained

## Table of Contents
1. [Core Infrastructure Components](#core-infrastructure-components)
2. [ViewModel Components](#viewmodel-components)
3. [View Layer Components](#view-layer-components)
4. [API Layer Components](#api-layer-components)
5. [Backward Compatibility Mappings](#backward-compatibility-mappings)
6. [Deprecation Roadmap](#deprecation-roadmap)

## Core Infrastructure Components

### Services (`/core/Services/`)

#### Authentication & Security
| Service | Purpose | Key Methods |
|---------|---------|-------------|
| `AuthService.php` | User authentication and authorization | `authenticate()`, `logout()`, `requireAuth()`, `hasRole()` |
| `SessionService.php` | Session management and security | `start()`, `regenerate()`, `destroy()`, `setTimeout()` |
| `CsrfService.php` | CSRF token generation/validation | `generateToken()`, `validateToken()`, `getTokenName()` |
| `EncryptionService.php` | Data encryption/decryption | `encrypt()`, `decrypt()`, `hashPassword()`, `verifyPassword()` |

#### Data Management
| Service | Purpose | Key Methods |
|---------|---------|-------------|
| `DatabaseService.php` | PDO connection management (singleton) | `getConnection()`, `beginTransaction()`, `commit()`, `rollback()` |
| `CacheService.php` | In-memory caching for performance | `get()`, `set()`, `delete()`, `flush()` |
| `LoggerService.php` | Comprehensive logging system | `info()`, `error()`, `audit()`, `debug()` |
| `BackupService.php` | Database backup operations | `createBackup()`, `restoreBackup()`, `listBackups()` |

#### Business Logic
| Service | Purpose | Key Methods |
|---------|---------|-------------|
| `EmailService.php` | Email sending with templates | `sendOTP()`, `sendNotification()`, `sendReport()` |
| `ValidationService.php` | Input validation and sanitization | `validate()`, `sanitize()`, `validateEmail()`, `validateUUID()` |
| `AuditService.php` | HIPAA-compliant audit logging | `logAccess()`, `logModification()`, `logAuthentication()` |
| `FileUploadService.php` | Secure file upload handling | `upload()`, `validate()`, `scan()`, `store()` |

#### Feature-Specific Services
| Service | Purpose | Key Methods |
|---------|---------|-------------|
| `PatientAccessService.php` | Recent patients functionality | `logAccess()`, `getRecentPatients()`, `clearHistory()` |
| `DashboardStatsService.php` | Dashboard statistics calculation | `getStats()`, `calculateKPIs()`, `getTrends()` |
| `PatientVitalsService.php` | Vital signs management | `recordVitals()`, `getVitalTrends()`, `checkAbnormal()` |
| `NotificationService.php` | User notification system | `create()`, `markRead()`, `getUnread()`, `sendPush()` |
| `EPCRService.php` | EMS ePCR management | `saveDraft()`, `submit()`, `validate()`, `lock()` |
| `TemplateService.php` | Chart template management | `create()`, `load()`, `update()`, `share()` |
| `TooltipService.php` | Context-sensitive help system | `get()`, `update()`, `getByRole()` |
| `FlagService.php` | High-risk call flagging | `createFlag()`, `assignFlag()`, `resolveFlag()` |
| `TrainingComplianceService.php` | Training tracking | `checkCompliance()`, `scheduleReminder()`, `generateReport()` |
| `QualityReviewService.php` | QA review workflow | `submitForReview()`, `approve()`, `reject()`, `getQueue()` |
| `ComplianceService.php` | Regulatory compliance monitoring | `calculateKPIs()`, `checkThresholds()`, `generateAlerts()` |
| `RegulatoryUpdateService.php` | AI-powered regulation analysis | `analyzeDocument()`, `generateSummary()`, `createChecklist()` |

### Repositories (`/core/Repositories/`)

#### User & Authentication
| Repository | Entity | Key Methods |
|-----------|---------|-------------|
| `UserRepository.php` | Users | `findById()`, `findByUsername()`, `update()`, `updateLastLogin()` |
| `OtpRepository.php` | OTP codes | `create()`, `verify()`, `invalidate()`, `cleanup()` |
| `RoleRepository.php` | User roles | `getUserRoles()`, `hasRole()`, `assignRole()` |
| `SessionRepository.php` | Active sessions | `save()`, `load()`, `destroy()`, `cleanup()` |

#### Clinical Data
| Repository | Entity | Key Methods |
|-----------|---------|-------------|
| `PatientRepository.php` | Patients | `findById()`, `search()`, `create()`, `update()` |
| `EncounterRepository.php` | Patient encounters | `findByPatient()`, `create()`, `update()`, `close()` |
| `ObservationRepository.php` | Clinical observations | `create()`, `findByEncounter()`, `update()` |
| `VitalRepository.php` | Vital signs | `record()`, `getLatest()`, `getTrends()`, `getAbnormal()` |
| `DocumentRepository.php` | Medical documents | `store()`, `retrieve()`, `list()`, `delete()` |

#### System & Features
| Repository | Entity | Key Methods |
|-----------|---------|-------------|
| `AuditLogRepository.php` | Audit logs | `create()`, `search()`, `export()`, `archive()` |
| `NotificationRepository.php` | Notifications | `create()`, `findByUser()`, `markRead()`, `delete()` |
| `TemplateRepository.php` | Chart templates | `save()`, `findByUser()`, `findShared()`, `delete()` |
| `FlagRepository.php` | High-risk flags | `create()`, `findActive()`, `resolve()`, `escalate()` |
| `TrainingRepository.php` | Training records | `record()`, `findExpiring()`, `getCompliance()` |
| `EPCRRepository.php` | EMS reports | `save()`, `findIncomplete()`, `submit()`, `lock()` |

### Entities (`/core/Entities/`)

| Entity | Purpose | Key Properties |
|--------|---------|---------------|
| `User.php` | User account model | `id`, `username`, `email`, `roles`, `status` |
| `Patient.php` | Patient information | `id`, `mrn`, `name`, `dob`, `demographics` |
| `Encounter.php` | Clinical encounter | `id`, `patient_id`, `date`, `type`, `status` |
| `Vital.php` | Vital sign reading | `id`, `encounter_id`, `type`, `value`, `timestamp` |
| `Observation.php` | Clinical observation | `id`, `encounter_id`, `code`, `value`, `notes` |
| `AuditLog.php` | Audit trail entry | `id`, `user_id`, `action`, `resource`, `timestamp` |
| `Notification.php` | User notification | `id`, `user_id`, `type`, `message`, `read_status` |
| `Template.php` | Chart template | `id`, `name`, `content`, `owner_id`, `shared` |
| `Flag.php` | High-risk flag | `id`, `encounter_id`, `severity`, `assigned_to`, `status` |
| `EPCR.php` | EMS report | `id`, `patient_id`, `incident_data`, `status`, `locked` |

### Validators (`/core/Validators/`)

| Validator | Purpose | Key Validations |
|-----------|---------|-----------------|
| `LoginValidator.php` | Login input validation | Username format, password complexity |
| `OTPValidator.php` | OTP/2FA validation | Code format, expiration |
| `PatientValidator.php` | Patient data validation | Demographics, identifiers |
| `VitalRangeValidator.php` | Clinical value validation | Normal ranges, outliers |
| `EPCRValidator.php` | EMS report validation | Required fields, protocols |
| `EmailValidator.php` | Email format validation | RFC compliance, domain verification |
| `UUIDValidator.php` | UUID format validation | Version 4 UUID format |
| `DateTimeValidator.php` | Date/time validation | Format, ranges, timezones |

## ViewModel Components (`/ViewModel/`)

### Authentication ViewModels
| ViewModel | Purpose | View Support |
|-----------|---------|--------------|
| `LoginViewModel.php` | Login page logic | `/View/login/login.php` |
| `TwoFactorViewModel.php` | 2FA verification | `/View/2fa/2fa.php` |
| `ForgotPasswordViewModel.php` | Password reset | `/View/auth/forgot-password.php` |

### Dashboard ViewModels
| ViewModel | Purpose | View Support |
|-----------|---------|--------------|
| `DashboardStatsViewModel.php` | Statistics display | Dashboard widgets |
| `RecentPatientsViewModel.php` | Recent patients list | Sidebar component |
| `OneClinicianDashboardViewModel.php` | 1Clinician dashboard | `/View/dashboards/1clinician/` |
| `PClinicianDashboardViewModel.php` | Primary clinician | `/View/dashboards/pclinician/` |
| `AdminDashboardViewModel.php` | Admin dashboard | `/View/dashboards/admin/` |

### API ViewModels
| ViewModel | Purpose | Endpoints |
|-----------|---------|-----------|
| `PatientVitalsViewModel.php` | Vitals API | `/api/patient-vitals/*` |
| `NotificationsViewModel.php` | Notifications API | `/api/notifications/*` |
| `EPCRViewModel.php` | EMS ePCR API | `/api/ems/*` |
| `TemplateViewModel.php` | Templates API | `/api/templates/*` |
| `FlagViewModel.php` | Flags API | `/api/flags/*` |
| `ComplianceViewModel.php` | Compliance API | `/api/compliance/*` |

### Feature ViewModels
| ViewModel | Purpose | View Support |
|-----------|---------|--------------|
| `AuditLogViewModel.php` | Audit log viewer | `/View/admin/audit-logs.php` |
| `TrainingComplianceViewModel.php` | Training dashboard | `/View/manager/training.php` |
| `QualityReviewViewModel.php` | QA review panel | `/View/qa/review-panel.php` |
| `RegulatoryUpdateViewModel.php` | Regulation assistant | `/View/admin/regulatory.php` |

## View Layer Components (`/View/`)

### Directory Structure
```
/View/
├── assets/
│   ├── css/          # All stylesheets (migrated from /assets/css/)
│   ├── js/           # All JavaScript (migrated from /assets/js/)
│   └── images/       # All images (migrated from /assets/images/)
├── includes/
│   ├── header.php    # Pure HTML header template
│   └── footer.php    # Pure HTML footer template
├── login/
│   └── login.php     # Pure login form template
├── 2fa/
│   └── 2fa.php       # Pure 2FA form template
└── [other view directories]
```

### Asset Migration
| Original Location | New Location | Files |
|------------------|--------------|--------|
| `/assets/css/` | `/View/assets/css/` | 17 CSS files |
| `/assets/js/` | `/View/assets/js/` | 24 JavaScript files |
| `/assets/images/` | `/View/assets/images/` | 1 image file |

## API Layer Components

### Centralized Router (`/api/index.php`)
- Single entry point for all API requests
- HTTP method routing (GET, POST, PUT, DELETE)
- Authentication and CSRF validation
- Rate limiting middleware
- Global error handling

### API Endpoints Mapping
| Old Endpoint | New Route | ViewModel |
|--------------|-----------|-----------|
| `/api/dashboard-stats.php` | `GET /api/dashboard-stats` | `DashboardStatsViewModel` |
| `/api/recent-patients.php` | `GET /api/recent-patients` | `RecentPatientsViewModel` |
| `/api/patient-vitals.php` | `GET/POST /api/patient-vitals` | `PatientVitalsViewModel` |
| `/api/notifications.php` | `GET/POST /api/notifications` | `NotificationsViewModel` |
| `/api/save-epcr.php` | `POST /api/ems/save-epcr` | `EPCRViewModel` |
| `/api/templates.php` | `GET/POST /api/templates` | `TemplateViewModel` |

## Backward Compatibility Mappings

### Database Functions (`/includes/db.php`)
| Legacy Function | Maps To | Status |
|----------------|---------|--------|
| `pdo()` | `DatabaseService::getConnection()` | Maintained |
| `checkConnection()` | `DatabaseService::checkConnection()` | Maintained |
| `lastInsertId()` | `DatabaseService::lastInsertId()` | Maintained |
| `beginTransaction()` | `DatabaseService::beginTransaction()` | Maintained |
| `commit()` | `DatabaseService::commit()` | Maintained |
| `rollback()` | `DatabaseService::rollback()` | Maintained |

### Authentication Functions (`/includes/auth.php`)
| Legacy Function | Maps To | Status |
|----------------|---------|--------|
| `login_start()` | `AuthService::authenticate()` | Maintained |
| `login_complete()` | `AuthService::completeLogin()` | Maintained |
| `check_auth()` | `AuthService::requireAuth()` | Maintained |
| `logout_user()` | `AuthService::logout()` | Maintained |
| `is_logged_in()` | `AuthService::isLoggedIn()` | Maintained |
| `get_user_role()` | `AuthService::getUserRole()` | Maintained |
| `has_permission()` | `AuthService::hasPermission()` | Maintained |

### Validation Functions (`/includes/validation.php`)
| Legacy Function | Maps To | Status |
|----------------|---------|--------|
| `validate_email()` | `ValidationService::validateEmail()` | Maintained |
| `validate_phone()` | `ValidationService::validatePhone()` | Maintained |
| `validate_required()` | `ValidationService::validateRequired()` | Maintained |
| `validate_uuid()` | `ValidationService::validateUUID()` | Maintained |
| `validate_date()` | `ValidationService::validateDate()` | Maintained |

### Sanitization Functions (`/includes/sanitization.php`)
| Legacy Function | Maps To | Status |
|----------------|---------|--------|
| `sanitize_input()` | `ValidationService::sanitize()` | Maintained |
| `sanitize_html()` | `ValidationService::sanitizeHtml()` | Maintained |
| `sanitize_sql()` | Use prepared statements | Deprecated |
| `clean_array()` | `ValidationService::sanitizeArray()` | Maintained |

### Utility Functions (`/includes/functions.php`)
| Legacy Function | Maps To | Status |
|----------------|---------|--------|
| `log_error()` | `LoggerService::error()` | Maintained |
| `log_audit()` | `AuditService::log()` | Maintained |
| `send_email()` | `EmailService::send()` | Maintained |
| `generate_uuid()` | `Utilities::generateUUID()` | Maintained |
| `format_date()` | `Utilities::formatDate()` | Maintained |

## Deprecation Roadmap

### Phase 1: Immediate (Can be removed after verification)
These files are duplicates or test files that can be safely removed:
- `/app_login/login_fixed.php` - Duplicate implementation
- `/app_login/login_original.php` - Legacy anti-pattern version
- `/api/[individual endpoint files]` - Replaced by centralized router
- `/test_*.php` files in root - Development test files

### Phase 2: Short Term (3-6 months)
After all code is migrated to use new services:
- `/includes/db.php` - Database wrapper functions
- `/includes/auth.php` - Authentication wrapper functions
- `/includes/validation.php` - Validation wrapper functions
- `/includes/sanitization.php` - Sanitization wrapper functions

### Phase 3: Medium Term (6-12 months)
After all dashboards are refactored:
- `/dashboards/[mixed concern files]` - Old dashboard implementations
- `/clinician/[mixed concern files]` - Old clinician interfaces
- Legacy JavaScript files using old API endpoints

### Phase 4: Long Term (12+ months)
After complete migration and testing:
- `/includes/functions.php` - Remaining utility functions
- Old header/footer files with mixed concerns
- Any remaining procedural code files

### Files to Retain Permanently
These files should remain as they serve important purposes:
- `/includes/bootstrap.php` - Application initialization
- `/includes/config.php` - Configuration management
- `/includes/autoloader.php` - Class autoloading
- `/includes/.htaccess` - Security rules
- `/.env` and `/.env.example` - Environment configuration
- `/composer.json` - Dependency management

## Migration Checklist for Developers

### When Creating New Features
1. ✅ Create Repository for data access
2. ✅ Create Service for business logic
3. ✅ Create Validator for input validation
4. ✅ Create ViewModel for request handling
5. ✅ Create pure View template
6. ✅ Add route to appropriate router
7. ✅ Write unit tests for each component
8. ✅ Update documentation

### When Refactoring Existing Code
1. ✅ Identify all database queries → move to Repository
2. ✅ Extract business logic → move to Service
3. ✅ Separate validation logic → move to Validator
4. ✅ Create ViewModel to coordinate components
5. ✅ Convert view to pure template
6. ✅ Update router to use ViewModel
7. ✅ Test backward compatibility
8. ✅ Update dependent code

### Testing Requirements
1. ✅ Unit tests for Services and Repositories
2. ✅ Integration tests for ViewModels
3. ✅ Backward compatibility tests for wrappers
4. ✅ Performance tests for critical paths
5. ✅ Security tests for all inputs
6. ✅ User acceptance tests for UI changes

## Support and Documentation

### For Questions About Components
- Check inline documentation in each class
- Review test files for usage examples
- Consult architecture diagrams in `/docs/`
- Contact the development team

### Additional Resources
- Architecture Guide: `/docs/ARCHITECTURE.md`
- Security Guide: `/docs/SECURITY.md`
- API Documentation: `/docs/API.md`
- Migration Guide: `/INFRASTRUCTURE_REFACTORING_COMPLETE.md`

---

**Last Updated By:** SafeShift Development Team  
**Review Schedule:** Quarterly  
**Next Review:** Q1 2026