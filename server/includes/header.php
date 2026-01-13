<?php
/**
 * Header Include File
 * Located at: root/includes/header.php
 *
 * Handles session initialization, CSRF token generation, and HTML header
 *
 * Updated: 2025-12-28
 * - Fixed session configuration to prevent premature logout (~5 min issue)
 * - Removed aggressive session regeneration that caused session loss
 * - Set gc_maxlifetime to 3600 (1 hour) for HIPAA compliance
 * - Integrated with SessionManager for database-backed session tracking
 */

// Prevent direct access check removed - no longer using APP_ROOT

// Ensure CSRF_TOKEN_NAME is defined
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings for HIPAA compliance and stability
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    
    // Set garbage collection lifetime to 1 hour (3600 seconds)
    // This is the hard limit - sessions cannot exceed this regardless of activity
    ini_set('session.gc_maxlifetime', '3600');
    
    // Session cookie parameters - secure defaults with HIPAA compliance
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $session_params = [
        'lifetime' => 0, // Session cookie (expires when browser closes) - actual timeout handled by SessionManager
        'path' => defined('SESSION_PATH') ? SESSION_PATH : '/',
        'domain' => '', // Empty string uses current domain
        'secure' => $isSecure, // Only send over HTTPS in production
        'httponly' => true, // Prevent JavaScript access
        'samesite' => 'Strict' // CSRF protection
    ];
    
    session_set_cookie_params($session_params);
    
    // Set session name if defined
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    } else {
        session_name('SAFESHIFT_SESSION');
    }
    
    // Start the session
    session_start();
    
    // Initialize session timestamp if new session
    // NOTE: Session ID regeneration moved to authentication events only (login/logout)
    // Periodic regeneration was causing the ~5 minute logout issue because:
    // 1. session_regenerate_id(true) deletes the old session file immediately
    // 2. If concurrent requests use the old session ID, they get a new empty session
    // 3. This causes loss of authenticated state mid-session
    if (!isset($_SESSION['_session_created'])) {
        $_SESSION['_session_created'] = time();
        $_SESSION['_last_activity'] = time();
        // Only regenerate on initial session creation, not periodically
        session_regenerate_id(true);
    }
    
    // Update last activity timestamp for idle timeout tracking
    // This is separate from the database-backed session tracking in SessionManager
    $_SESSION['_last_activity'] = time();
    
    // Generate CSRF token if not exists
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
}

// Ensure CSRF token always exists
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// Get current user if logged in
$currentUser = $_SESSION['user'] ?? null;

// Get the CSRF token safely
$csrf_token = $_SESSION[CSRF_TOKEN_NAME] ?? '';

// Set security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Define default values if not set
if (!defined('APP_NAME')) {
    define('APP_NAME', 'SafeShift EHR');
}

// Set default page values
$pageTitle = $pageTitle ?? APP_NAME;
$pageDescription = $pageDescription ?? 'SafeShift EHR - HIPAA-compliant Occupational Health Electronic Health Records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SafeShift EHR">
    
    <!-- Security Meta Tags -->
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    
    <!-- CSS -->
    <link rel="stylesheet" href="/assets/css/main.css">
    <?php if (isset($additionalCSS) && is_array($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
    
    <!-- Accessibility -->
    <meta name="theme-color" content="#1a73e8">
</head>
<body<?= isset($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>
    <div id="app">
        <!-- Skip to main content link for accessibility -->
        <a href="#main-content" class="skip-link">Skip to main content</a>
        
        <!-- Main navigation if user is logged in -->
        <?php if ($currentUser): ?>
        <nav class="main-nav" role="navigation" aria-label="Main navigation">
            <div class="nav-container">
                <div class="nav-brand">
                    <a href="/" class="brand-link">
                        <img src="/assets/images/logo.png" alt="SafeShift Logo" class="nav-logo">
                        <span class="brand-text">SafeShift EHR</span>
                    </a>
                </div>
                
                <div class="nav-menu">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="/dashboard" class="nav-link">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a href="/patients" class="nav-link">Patients</a>
                        </li>
                        <li class="nav-item">
                            <a href="/encounters" class="nav-link">Encounters</a>
                        </li>
                        <li class="nav-item">
                            <a href="/reports" class="nav-link">Reports</a>
                        </li>
                        <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Admin'): ?>
                        <li class="nav-item">
                            <a href="/admin" class="nav-link">Admin</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="nav-user">
                    <div class="user-menu">
                        <button class="user-menu-toggle" aria-expanded="false" aria-controls="user-dropdown">
                            <span class="user-name"><?= htmlspecialchars($currentUser['username'] ?? 'User') ?></span>
                            <span class="user-avatar" aria-hidden="true">👤</span>
                        </button>
                        <div id="user-dropdown" class="user-dropdown" hidden>
                            <ul class="dropdown-list">
                                <li><a href="/profile" class="dropdown-link">Profile</a></li>
                                <li><a href="/settings" class="dropdown-link">Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a href="/logout" class="dropdown-link">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        <?php endif; ?>
        
        <!-- Main content area -->
        <main id="main-content" role="main">
            <!-- Session timeout warning (if applicable) -->
            <?php if ($currentUser && defined('SESSION_TIMEOUT')): ?>
            <div id="session-warning" class="session-warning" style="display:none;" role="alert">
                <p>Your session will expire soon. <button type="button" id="extend-session">Extend Session</button></p>
            </div>
            <?php endif; ?>
            
            <!-- Flash messages -->
            <?php if (!empty($_SESSION['flash_message'])): ?>
            <div class="flash-message <?= htmlspecialchars($_SESSION['flash_message']['type'] ?? 'info') ?>" role="alert">
                <?= htmlspecialchars($_SESSION['flash_message']['message']) ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>