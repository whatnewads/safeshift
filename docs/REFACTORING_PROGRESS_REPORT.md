# SafeShift EHR MVVM Refactoring Progress Report

## Date: November 25, 2025

## Summary of Completed Work

### 1. Assets Directory Migration ✅
- **Created** new View layer directory structure: `/View/assets/`
- **Moved** all assets from `/assets/` to `/View/assets/`:
  - 17 CSS files moved to `/View/assets/css/`
  - 24 JavaScript files moved to `/View/assets/js/`
  - 1 image file moved to `/View/assets/images/`
- **Updated** `.htaccess` to allow access to new asset location
- **Created** `/View/.htaccess` to protect PHP files while allowing asset access

### 2. Header/Footer Decomposition ✅
- **Created** pure View templates:
  - `/View/includes/header.php` - Pure HTML template with no business logic
  - `/View/includes/footer.php` - Pure HTML template with no business logic
- **Updated** `bootstrap.php` to include session regeneration logic from old header.php
- **Removed** mixed concerns from View layer templates

### 3. ViewModel Updates ✅
- **Updated** `LoginViewModel.php` to provide standard view data structure:
  ```php
  'currentUser' => null,
  'csrf_token' => $this->authService->getCsrfToken(),
  'pageTitle' => 'Login - SafeShift EHR',
  'pageDescription' => 'Secure login to SafeShift EHR...',
  'bodyClass' => 'login-page',
  'additionalCSS' => ['/View/assets/css/login.css'],
  'additionalJS' => ['/View/assets/js/login.js']
  ```

### 4. View Updates ✅
- **Updated** `/View/login/login.php` asset references:
  - Changed from `/assets/css/` to `/View/assets/css/`
  - Changed from `/assets/js/` to `/View/assets/js/`
  - Changed from `/assets/images/` to `/View/assets/images/`

## Current Architecture State

### ✅ Properly Aligned with MVVM:
- `/View/` - Contains pure presentation layer (HTML templates, assets)
- `/ViewModel/` - Contains view logic and data preparation
- `/core/` - Contains business logic, models, and services
- `/includes/bootstrap.php` - Handles session, security, and infrastructure

### ⚠️ Still Needs Refactoring:
- `/dashboards/` - Contains mixed concerns (auth, business logic, HTML)
- `/clinician/` - Contains mixed concerns
- `/includes/header.php` and `/includes/footer.php` - Old versions still exist

## Next Steps Required

### 1. Dashboard Refactoring Pattern
Each dashboard file (e.g., `/dashboards/1clinician/index.php`) needs to be split:

#### A. Create ViewModel (e.g., `/ViewModel/OneClinicianDashboardViewModel.php`):
```php
namespace ViewModel;

class OneClinicianDashboardViewModel {
    public function getDashboardData(): array {
        // Handle authentication/authorization
        $user = $this->authService->getCurrentUser();
        if (!$this->authService->hasRole('1clinician')) {
            throw new AuthorizationException();
        }
        
        // Prepare view data
        return [
            // Standard view data
            'currentUser' => $user,
            'csrf_token' => $_SESSION[CSRF_TOKEN_NAME],
            'pageTitle' => 'Clinician Dashboard - SafeShift EHR',
            'bodyClass' => 'dashboard-clinician',
            'additionalCSS' => [
                '/View/assets/css/dashboard-clinician.css',
                '/View/assets/css/recent-patients.css'
            ],
            'additionalJS' => [
                '/View/assets/js/dashboard-clinician.js',
                '/View/assets/js/recent-patients.js'
            ],
            
            // Dashboard-specific data
            'clinicName' => $this->clinicService->getClinicName($user['clinic_id']),
            'recentPatients' => $this->patientService->getRecentPatients($user['user_id']),
            'stats' => $this->statsService->getClinicianStats($user['user_id'])
        ];
    }
}
```

#### B. Create View (e.g., `/View/dashboards/1clinician/index.php`):
```php
<?php
// Include pure View header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Pure HTML using $viewData -->
    <h1>Welcome, <?= htmlspecialchars($viewData['currentUser']['username'], ENT_QUOTES, 'UTF-8') ?></h1>
    <!-- Rest of dashboard HTML -->
</div>

<?php
// Include pure View footer
require_once __DIR__ . '/../../includes/footer.php';
?>
```

#### C. Update Router (`/index.php`):
```php
case '/dashboards/1clinician':
    require_once __DIR__ . '/ViewModel/OneClinicianDashboardViewModel.php';
    $viewModel = new \ViewModel\OneClinicianDashboardViewModel();
    
    try {
        $viewData = $viewModel->getDashboardData();
        require_once __DIR__ . '/View/dashboards/1clinician/index.php';
    } catch (AuthorizationException $e) {
        http_response_code(403);
        require_once __DIR__ . '/View/errors/403.php';
    }
    break;
```

### 2. Update All Files with Asset References
Search and replace in all PHP files:
- `/assets/css/` → `/View/assets/css/`
- `/assets/js/` → `/View/assets/js/`
- `/assets/images/` → `/View/assets/images/`

### 3. Clean Up Old Files
After verification:
- Delete `/includes/header.php` (old mixed-concern version)
- Delete `/includes/footer.php` (old mixed-concern version)  
- Delete `/assets/` directory (after confirming all files moved)

### 4. Testing Checklist
- [ ] Login page loads with correct styles
- [ ] All JavaScript functionality works
- [ ] Images display correctly
- [ ] CSRF tokens generate properly
- [ ] Session management works
- [ ] Navigation between pages works
- [ ] All dashboards load correctly
- [ ] No 404 errors in browser console
- [ ] Security headers present in responses

## Benefits Achieved

1. **Clean Separation of Concerns**: View layer now contains ONLY presentation logic
2. **Improved Security**: Session/auth logic consolidated in proper layers
3. **Better Maintainability**: Each layer can be modified independently
4. **Consistent Data Flow**: ViewModels provide standardized data structure
5. **Asset Organization**: All presentation resources properly encapsulated

## Risks to Monitor

1. **Path Dependencies**: Some JavaScript may have hardcoded asset paths
2. **CDN Configuration**: May need updates for production deployment
3. **Cache Invalidation**: Users may need to clear browser cache
4. **Third-party Integrations**: Check if any external systems reference old paths

## Recommendation

Complete the dashboard refactoring pattern for one dashboard first (suggest starting with `/dashboards/1clinician/`), test thoroughly, then apply the same pattern to all other dashboards and mixed-concern files.