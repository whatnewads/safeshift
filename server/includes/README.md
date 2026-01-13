# SafeShift EHR - Includes Directory Documentation

**Last Updated:** November 25, 2025  
**Post-Refactoring Status:** Infrastructure Layer Complete

## Directory Overview

The `/includes/` directory contains critical infrastructure files and backward compatibility wrappers for the SafeShift EHR system. Following the MVVM refactoring project, most files in this directory now serve as compatibility layers delegating to new service classes in `/core/`.

## Current State After Refactoring

### Critical Infrastructure Files (MUST RETAIN)

These files are essential for system operation and should never be removed:

| File | Purpose | Status |
|------|---------|--------|
| `bootstrap.php` | Application initialization, autoloading, session management | **Active - Core Infrastructure** |
| `config.php` | Environment configuration, database credentials | **Active - Core Infrastructure** |
| `autoloader.php` | PSR-4 compliant class autoloading | **Active - Core Infrastructure** |
| `.htaccess` | Directory security (deny public access) | **Active - Security** |

### Backward Compatibility Wrappers

These files maintain compatibility with legacy code but delegate to new service classes:

| File | Delegates To | Can Be Removed |
|------|--------------|----------------|
| `db.php` | `Core\Services\DatabaseService` | After full migration |
| `auth.php` | `Core\Services\AuthService` | After full migration |
| `validation.php` | `Core\Services\ValidationService` | After full migration |
| `sanitization.php` | `Core\Services\ValidationService` | After full migration |
| `functions.php` | Various Core Services | After full migration |
| `log.php` | `Core\Services\LoggerService` | After full migration |
| `logger.php` | `Core\Services\LoggerService` | After full migration |
| `error_handler.php` | `Core\Services\ErrorService` | After full migration |

### Deprecated Files

These files have been replaced but may still exist for compatibility:

| File | Replaced By | Notes |
|------|-------------|--------|
| `header.php` | `/View/includes/header.php` | Old mixed-concern version |
| `footer.php` | `/View/includes/footer.php` | Old mixed-concern version |
| `router.php` | `/index.php` (main router) | Legacy routing logic |
| `secure_logger.php` | `Core\Services\LoggerService` | Duplicate functionality |
| `epcr-functions.php` | `Core\Services\EPCRService` | Legacy procedural code |

## Migration Guide for Developers

### Step 1: Update Your Entry Points

Replace legacy includes with bootstrap.php:

```php
// ❌ OLD WAY - Multiple includes
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// ✅ NEW WAY - Single bootstrap
require_once __DIR__ . '/includes/bootstrap.php';
```

### Step 2: Migrate Database Operations

```php
// ❌ OLD WAY - Using wrapper functions
$db = pdo();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);

// ✅ NEW WAY - Using service
use Core\Services\DatabaseService;

$db = DatabaseService::getInstance();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);

// ✅ BETTER WAY - Using repository
use Core\Repositories\UserRepository;

$userRepo = new UserRepository();
$user = $userRepo->findById($id);
```

### Step 3: Update Authentication Checks

```php
// ❌ OLD WAY - Using wrapper functions
check_auth();
if (!has_permission('admin')) {
    die('Access denied');
}

// ✅ NEW WAY - Using service
use Core\Services\AuthService;

$auth = AuthService::getInstance();
$auth->requireAuth();
if (!$auth->hasRole('admin')) {
    throw new \Core\Exceptions\AuthorizationException('Access denied');
}
```

### Step 4: Migrate Validation and Sanitization

```php
// ❌ OLD WAY - Using wrapper functions
$email = sanitize_input($_POST['email']);
if (!validate_email($email)) {
    $error = "Invalid email";
}

// ✅ NEW WAY - Using service
use Core\Services\ValidationService;

$validator = ValidationService::getInstance();
$email = $validator->sanitize($_POST['email']);
if (!$validator->validateEmail($email)) {
    $error = "Invalid email";
}
```

### Step 5: Update Logging

```php
// ❌ OLD WAY - Using wrapper functions
log_error("Something went wrong");
log_audit("User accessed patient record", $patient_id);

// ✅ NEW WAY - Using services
use Core\Services\LoggerService;
use Core\Services\AuditService;

$logger = LoggerService::getInstance();
$logger->error("Something went wrong");

$audit = AuditService::getInstance();
$audit->logAccess('patient_record', $patient_id);
```

## File Contents Overview

### bootstrap.php
```php
<?php
// Core application initialization
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/autoloader.php';

// Initialize core services
\Core\Services\DatabaseService::getInstance();
\Core\Services\SessionService::getInstance()->configure();

// Load backward compatibility wrappers if needed
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
// ... other wrappers
```

### config.php
```php
<?php
// Environment configuration
define('ENVIRONMENT', 'development'); // or 'production'
define('DB_HOST', 'localhost');
define('DB_NAME', 'safeshift_ehr');
define('DB_USER', 'safeshift_user');
define('DB_PASS', 'secure_password');

// Security settings
define('SESSION_LIFETIME', 3600);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('BCRYPT_COST', 12);
```

### autoloader.php
```php
<?php
// PSR-4 compliant autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    $base_dir = __DIR__ . '/../core/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
```

## Compatibility Function Reference

### Database Functions (db.php)
| Function | Service Method | Notes |
|----------|---------------|-------|
| `pdo()` | `DatabaseService::getConnection()` | Returns PDO instance |
| `checkConnection()` | `DatabaseService::checkConnection()` | Tests DB connectivity |
| `lastInsertId()` | `DatabaseService::lastInsertId()` | Gets last insert ID |
| `beginTransaction()` | `DatabaseService::beginTransaction()` | Starts transaction |
| `commit()` | `DatabaseService::commit()` | Commits transaction |
| `rollback()` | `DatabaseService::rollback()` | Rolls back transaction |

### Authentication Functions (auth.php)
| Function | Service Method | Notes |
|----------|---------------|-------|
| `login_start($username, $password)` | `AuthService::authenticate()` | Initial login |
| `login_complete($user_id)` | `AuthService::completeLogin()` | Finalize login |
| `check_auth()` | `AuthService::requireAuth()` | Require authentication |
| `logout_user()` | `AuthService::logout()` | Log out user |
| `is_logged_in()` | `AuthService::isLoggedIn()` | Check login status |
| `get_user_role()` | `AuthService::getUserRole()` | Get user's role |
| `has_permission($permission)` | `AuthService::hasPermission()` | Check permission |

### Validation Functions (validation.php)
| Function | Service Method | Notes |
|----------|---------------|-------|
| `validate_email($email)` | `ValidationService::validateEmail()` | Email validation |
| `validate_phone($phone)` | `ValidationService::validatePhone()` | Phone validation |
| `validate_required($value)` | `ValidationService::validateRequired()` | Required field |
| `validate_uuid($uuid)` | `ValidationService::validateUUID()` | UUID format |
| `validate_date($date)` | `ValidationService::validateDate()` | Date validation |

### Sanitization Functions (sanitization.php)
| Function | Service Method | Notes |
|----------|---------------|-------|
| `sanitize_input($input)` | `ValidationService::sanitize()` | General sanitization |
| `sanitize_html($html)` | `ValidationService::sanitizeHtml()` | HTML sanitization |
| `sanitize_sql($sql)` | **DEPRECATED** | Use prepared statements |
| `clean_array($array)` | `ValidationService::sanitizeArray()` | Array sanitization |

## Testing Backward Compatibility

Run these tests to ensure compatibility wrappers are working:

```bash
# Run backward compatibility test suite
php tests/backward_compatibility_test.php

# Test specific wrapper
php tests/test_auth_functions.php
```

## Deprecation Timeline

### Phase 1 (Immediate - After Testing)
- Remove test files from production
- Remove duplicate implementations

### Phase 2 (3-6 Months)
- Migrate high-traffic pages
- Update documentation
- Train developers on new patterns

### Phase 3 (6-12 Months)
- Complete migration of all code
- Remove compatibility wrappers
- Archive legacy code

### Phase 4 (12+ Months)
- Remove all deprecated functions
- Optimize autoloader
- Final cleanup

## Best Practices

### DO:
- ✅ Always include `bootstrap.php` first
- ✅ Use new service classes for new code
- ✅ Gradually migrate existing code
- ✅ Test thoroughly after migration
- ✅ Keep `config.php` updated

### DON'T:
- ❌ Remove compatibility wrappers prematurely
- ❌ Mix old and new patterns in same file
- ❌ Skip testing after migration
- ❌ Modify core infrastructure files
- ❌ Use deprecated `sanitize_sql()` function

## Troubleshooting

### Common Issues After Refactoring

1. **Class Not Found Errors**
   - Ensure `autoloader.php` is included via `bootstrap.php`
   - Check namespace and file paths match
   - Verify PSR-4 naming conventions

2. **Database Connection Issues**
   - Check `config.php` has correct credentials
   - Ensure `DatabaseService` singleton is initialized
   - Verify PDO extension is enabled

3. **Authentication Failures**
   - Session must be started in `bootstrap.php`
   - Check CSRF token generation
   - Verify session configuration

4. **Compatibility Function Errors**
   - Ensure wrapper file is included
   - Check service class exists
   - Verify correct delegation

## Support

For questions about the refactoring or migration:
- Review `/INFRASTRUCTURE_REFACTORING_COMPLETE.md`
- Check `/docs/REFACTORED_COMPONENTS.md`
- Run test suites in `/tests/`
- Contact the development team

---

**Maintained by:** SafeShift Development Team  
**Architecture:** MVVM Pattern  
**Backward Compatibility:** 100% Maintained