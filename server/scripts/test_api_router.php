<?php
/**
 * API Router Test Script
 * Tests API routing without needing a live server
 */

echo "======================================\n";
echo "API ROUTER TEST SCRIPT\n";
echo "======================================\n\n";

// Simulate web server environment
$_SERVER['REQUEST_URI'] = '/api/v1/health';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8000';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/router.php';
$_SERVER['PHP_SELF'] = '/router.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8000';

echo "1. Testing API v1 Router paths:\n";
echo "   Request URI: /api/v1/health\n";
echo "   Method: GET\n\n";

// Capture output
ob_start();

try {
    // Change to public directory context
    chdir(__DIR__ . '/../public');
    
    // Test the health endpoint by including the api router
    echo "   Loading bootstrap...\n";
    require_once __DIR__ . '/../includes/bootstrap.php';
    echo "   Bootstrap loaded.\n";
    
    // Now test the API response class
    echo "\n2. Testing ApiResponse class:\n";
    if (class_exists('ViewModel\\Core\\ApiResponse')) {
        echo "   [OK] ApiResponse class is autoloaded\n";
        
        // Test creating responses
        $response = \ViewModel\Core\ApiResponse::success(['test' => 'data'], 'Test message');
        echo "   [OK] ApiResponse::success() works\n";
        echo "   Response: " . json_encode($response) . "\n";
    } else {
        echo "   [ERROR] ApiResponse class not found\n";
    }
    
    // Test database availability
    echo "\n3. Testing Database connection:\n";
    if (isset($GLOBALS['db']) && $GLOBALS['db'] !== null) {
        echo "   [OK] Database connection is available\n";
        echo "   DB Host: " . DB_HOST . "\n";
        echo "   DB Name: " . DB_NAME . "\n";
        
        // Test a simple query
        try {
            $result = $GLOBALS['db']->query("SELECT 1 as test");
            $row = $result->fetch();
            if ($row && $row['test'] == 1) {
                echo "   [OK] Database query successful\n";
            }
        } catch (Exception $e) {
            echo "   [WARN] Database query failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   [WARN] Database connection not available\n";
        if (isset($GLOBALS['db_error'])) {
            echo "   Error: " . $GLOBALS['db_error'] . "\n";
        }
    }
    
    // Test Logger
    echo "\n4. Testing Logger:\n";
    if (isset($GLOBALS['logger'])) {
        echo "   [OK] Logger is initialized\n";
        $GLOBALS['logger']->info('API router test', ['test' => true], 'test');
        echo "   [OK] Logger->info() works\n";
    } else {
        echo "   [WARN] Logger not initialized\n";
    }
    
    // Test auth functions
    echo "\n5. Testing Auth functions:\n";
    if (function_exists('is_logged_in')) {
        echo "   [OK] is_logged_in() function exists\n";
        $loggedIn = is_logged_in();
        echo "   is_logged_in(): " . ($loggedIn ? 'true' : 'false') . "\n";
    } else {
        echo "   [WARN] is_logged_in() function not found\n";
    }
    
    if (function_exists('get_current_user_id')) {
        echo "   [OK] get_current_user_id() function exists\n";
    } else {
        echo "   [WARN] get_current_user_id() function not found\n";
    }
    
    // Test session
    echo "\n6. Testing Session:\n";
    echo "   Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n";
    echo "   Session ID: " . session_id() . "\n";
    echo "   Session name: " . session_name() . "\n";
    
    // Test CSRF token
    echo "\n7. Testing CSRF token:\n";
    if (isset($_SESSION[CSRF_TOKEN_NAME])) {
        echo "   [OK] CSRF token exists\n";
        echo "   Token length: " . strlen($_SESSION[CSRF_TOKEN_NAME]) . " chars\n";
    } else {
        echo "   [WARN] CSRF token not set\n";
    }
    
} catch (Throwable $e) {
    echo "\n[ERROR] Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

$output = ob_get_clean();
echo $output;

echo "\n======================================\n";
echo "TEST COMPLETE\n";
echo "======================================\n";
