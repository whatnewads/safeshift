<?php
// ========================================================================
// IMMEDIATE LOGGING - FIRST THING BEFORE ANYTHING ELSE
// ========================================================================
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/api_debug.log';
$timestamp = date('Y-m-d H:i:s');
$logEntry = "[{$timestamp}] ROOT INDEX.PHP HIT\n";
$logEntry .= "  REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '(not set)') . "\n";
$logEntry .= "  REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '(not set)') . "\n";
$logEntry .= "  SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? '(not set)') . "\n";
$logEntry .= "  PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? '(not set)') . "\n";
$logEntry .= "  __FILE__: " . __FILE__ . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

/**
 * SafeShift EHR - Root Entry Point
 *
 * Development: Redirects to React dev server or serves API
 * Production: Serves built React app (index.html) or routes to API
 */

// Check if this is an API request (shouldn't reach here due to .htaccess)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($uri, '/api/') === 0) {
    // Route to API (fallback if .htaccess didn't catch it)
    if (strpos($uri, '/api/v1/') === 0) {
        require __DIR__ . '/../api/v1/index.php';
    } else {
        require __DIR__ . '/../api/index.php';
    }
    exit;
}

// For all other requests, serve the React SPA
// In production, this serves the built index.html
// In development, configure proxy in vite.config.ts instead

// Check for built React app
$reactIndex = __DIR__ . '/dist/index.html';
if (file_exists($reactIndex)) {
    // Production: serve built React app
    readfile($reactIndex);
    exit;
}

// Development fallback: serve a redirect or placeholder
// (In dev, use Vite's proxy to handle API calls)
$devIndex = __DIR__ . '/index.html';
if (file_exists($devIndex)) {
    readfile($devIndex);
    exit;
}

// If no React build exists, show setup message
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>
<html>
<head>
    <title>SafeShift EHR</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 40px; max-width: 600px; margin: 0 auto; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }
        pre { background: #f4f4f4; padding: 16px; border-radius: 8px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>SafeShift EHR</h1>
    <p>The React frontend has not been built yet.</p>
    <h2>Development</h2>
    <pre>cd src
npm install
npm run dev</pre>
    <p>Then visit <code>http://localhost:5173</code></p>
    <h2>Production</h2>
    <pre>cd src
npm install
npm run build</pre>
    <p>The built files will be served automatically.</p>
    <h2>API Status</h2>
    <p>API is available at <a href="/api/v1/">/api/v1/</a></p>
</body>
</html>';
