<?php
/**
 * Router for PHP built-in web server
 * This file handles URL rewriting for the development server
 *
 * Usage: php -S localhost:8000 router.php
 */

// IMMEDIATE LOGGING - First thing, before anything else
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/router_debug.log';
$timestamp = date('Y-m-d H:i:s');
$logEntry = "[{$timestamp}] ROUTER.PHP HIT\n";
$logEntry .= "  REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '(not set)') . "\n";
$logEntry .= "  REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '(not set)') . "\n";
$logEntry .= "  SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? '(not set)') . "\n";
$logEntry .= "  PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? '(not set)') . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ============================================================================
// API ROUTING - PRIORITY HANDLING FOR /api/* REQUESTS
// ============================================================================
// All API requests must go through index.php which routes to the appropriate API handler
if (strpos($uri, '/api/') === 0 || $uri === '/api') {
    file_put_contents($logFile, "  [API ROUTE] Detected API request, routing to index.php\n", FILE_APPEND);
    $_GET['route'] = $uri;
    require __DIR__ . '/index.php';
    exit;
}

// Define paths that should always go through index.php routing
$always_route = [
    '/app_login',
    '/login/2fa',
    '/login/verify',
];

// Check if this is a path that should always be routed
$should_route = false;
foreach ($always_route as $route_path) {
    if (strpos($uri, $route_path) === 0) {
        $should_route = true;
        break;
    }
}

// Handle test files and other PHP files that should be served directly
if (strpos($uri, '/test_') === 0 && file_exists(__DIR__ . $uri)) {
    file_put_contents($logFile, "  [STATIC] Serving test file directly\n", FILE_APPEND);
    return false; // Let PHP's built-in server handle test files
}

// If the request is for a static file that exists, serve it
// But force routing for login paths
if (!$should_route && $uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    // Only serve static files (images, CSS, JS, etc.)
    $extension = pathinfo($uri, PATHINFO_EXTENSION);
    if ($extension && in_array($extension, ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf'])) {
        file_put_contents($logFile, "  [STATIC] Serving static file: {$uri}\n", FILE_APPEND);
        return false; // Let PHP's built-in server handle static files
    }
}

// Check if it's a directory with index.php that should be routed
if (is_dir(__DIR__ . $uri) && file_exists(__DIR__ . $uri . '/index.php')) {
    // For app_login directory, force routing
    if (strpos($uri, '/app_login') === 0 || strpos($uri, '/login') === 0) {
        $should_route = true;
    }
}

// Otherwise, route everything through index.php
file_put_contents($logFile, "  [DEFAULT] Routing to index.php\n", FILE_APPEND);
$_GET['route'] = $uri;
require __DIR__ . '/index.php';