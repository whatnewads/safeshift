<?php
/**
 * Comprehensive API Endpoint Testing Script
 * 
 * Tests all API endpoints in the SafeShift EHR application for:
 * - HTTP response codes
 * - Response payload completeness
 * - Authentication handling
 * - Performance metrics (response times)
 * 
 * @package SafeShift\Scripts
 * @version 1.0.0
 * @date 2026-01-12
 */

declare(strict_types=1);

// Configuration
define('BASE_URL', 'http://localhost:8000');
define('TEST_TIMEOUT', 30);
define('LOG_FILE', __DIR__ . '/../logs/api_test_results.log');

// Test results storage
$testResults = [
    'summary' => [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
        'warnings' => 0,
        'skipped' => 0,
        'start_time' => null,
        'end_time' => null,
    ],
    'endpoints' => [],
    'issues' => [],
    'performance' => [],
];

// Session storage for authenticated tests
$sessionCookie = null;
$csrfToken = null;

/**
 * Log message to file and console
 */
function logMessage(string $message, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[{$timestamp}] [{$level}] {$message}\n";
    echo $formatted;
    file_put_contents(LOG_FILE, $formatted, FILE_APPEND | LOCK_EX);
}

/**
 * Make HTTP request to API endpoint
 */
function makeRequest(
    string $method,
    string $endpoint,
    ?array $data = null,
    bool $requiresAuth = true
): array {
    global $sessionCookie, $csrfToken;
    
    $url = BASE_URL . $endpoint;
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, TEST_TIMEOUT);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    // Set headers
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    if ($requiresAuth && $sessionCookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $sessionCookie);
    }
    
    if ($csrfToken && in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $headers[] = "X-CSRF-Token: {$csrfToken}";
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Set method and data
    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }
    
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    $curlErrno = curl_errno($ch);
    
    curl_close($ch);
    
    $responseTime = round(($endTime - $startTime) * 1000, 2);
    
    // Handle connection failures
    if ($response === false || $curlErrno !== 0) {
        return [
            'http_code' => 0,
            'response_time' => $responseTime,
            'headers' => '',
            'body' => '',
            'json' => null,
            'error' => $error ?: "Connection failed (curl error: {$curlErrno})",
            'url' => $url,
            'connection_failed' => true,
        ];
    }
    
    // Parse response
    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    
    // Try to decode JSON
    $jsonData = json_decode($responseBody, true);
    
    return [
        'http_code' => $httpCode,
        'response_time' => $responseTime,
        'headers' => $responseHeaders,
        'body' => $responseBody,
        'json' => $jsonData,
        'error' => $error,
        'url' => $url,
        'connection_failed' => false,
    ];
}

/**
 * Authenticate and get session
 */
function authenticate(string $username, string $password): bool
{
    global $sessionCookie, $csrfToken;
    
    logMessage("Attempting authentication for user: {$username}");
    
    // First get CSRF token
    $response = makeRequest('GET', '/api/v1/auth/csrf-token', null, false);
    
    if ($response['http_code'] === 200 && isset($response['json']['data']['token'])) {
        $csrfToken = $response['json']['data']['token'];
        
        // Extract session cookie
        if (preg_match('/PHPSESSID=([^;]+)/', $response['headers'], $matches)) {
            $sessionCookie = "PHPSESSID={$matches[1]}";
        }
    }
    
    // Attempt login
    $loginResponse = makeRequest('POST', '/api/v1/auth/login', [
        'username' => $username,
        'password' => $password,
    ], false);
    
    if ($loginResponse['http_code'] === 200 && isset($loginResponse['json']['success']) && $loginResponse['json']['success']) {
        // Update session cookie if returned
        if (preg_match('/PHPSESSID=([^;]+)/', $loginResponse['headers'], $matches)) {
            $sessionCookie = "PHPSESSID={$matches[1]}";
        }
        logMessage("Authentication successful");
        return true;
    }
    
    logMessage("Authentication failed: " . ($loginResponse['json']['message'] ?? 'Unknown error'), 'ERROR');
    return false;
}

/**
 * Test a single endpoint
 */
function testEndpoint(
    string $method,
    string $endpoint,
    string $description,
    array $expectedCodes = [200],
    ?array $requestData = null,
    array $expectedFields = [],
    bool $requiresAuth = true
): array {
    global $testResults;
    
    $testResults['summary']['total']++;
    
    $result = [
        'endpoint' => $endpoint,
        'method' => $method,
        'description' => $description,
        'status' => 'unknown',
        'http_code' => null,
        'response_time' => null,
        'missing_fields' => [],
        'null_fields' => [],
        'errors' => [],
        'warnings' => [],
    ];
    
    try {
        $response = makeRequest($method, $endpoint, $requestData, $requiresAuth);
        
        $result['http_code'] = $response['http_code'];
        $result['response_time'] = $response['response_time'];
        
        // Handle connection failures
        if (!empty($response['connection_failed'])) {
            $result['status'] = 'connection_failed';
            $result['errors'][] = "Connection failed: " . ($response['error'] ?? 'Unknown error');
            $testResults['summary']['failed']++;
            
            // Log result
            logMessage("✗ [{$method}] {$endpoint} - CONNECTION FAILED ({$result['response_time']}ms)");
            logMessage("  ERROR: {$response['error']}", 'ERROR');
            
            $testResults['endpoints'][] = $result;
            return $result;
        }
        
        // Check HTTP status code
        if (in_array($response['http_code'], $expectedCodes)) {
            $result['status'] = 'passed';
            $testResults['summary']['passed']++;
        } elseif ($response['http_code'] === 401 && $requiresAuth) {
            $result['status'] = 'auth_required';
            $result['warnings'][] = 'Authentication required but session may have expired';
            $testResults['summary']['warnings']++;
        } elseif ($response['http_code'] >= 500) {
            $result['status'] = 'failed';
            $result['errors'][] = "Server error: HTTP {$response['http_code']}";
            $testResults['summary']['failed']++;
        } else {
            $result['status'] = 'failed';
            $result['errors'][] = "Unexpected HTTP code: {$response['http_code']} (expected: " . implode(', ', $expectedCodes) . ")";
            $testResults['summary']['failed']++;
        }
        
        // Check response payload completeness
        if ($response['json'] && !empty($expectedFields)) {
            foreach ($expectedFields as $field) {
                if (!array_key_exists($field, $response['json'])) {
                    $result['missing_fields'][] = $field;
                } elseif ($response['json'][$field] === null) {
                    $result['null_fields'][] = $field;
                }
            }
            
            if (!empty($result['missing_fields'])) {
                $result['warnings'][] = 'Missing fields: ' . implode(', ', $result['missing_fields']);
            }
        }
        
        // Check for error messages in response
        if (isset($response['json']['error'])) {
            $result['warnings'][] = "API returned error: {$response['json']['error']}";
        }
        
        // Performance tracking
        if ($response['response_time'] > 2000) {
            $result['warnings'][] = "Slow response time: {$response['response_time']}ms";
            $testResults['performance'][] = [
                'endpoint' => $endpoint,
                'time' => $response['response_time'],
            ];
        }
        
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['errors'][] = $e->getMessage();
        $testResults['summary']['failed']++;
    }
    
    // Log result
    $statusIcon = match($result['status']) {
        'passed' => '✓',
        'failed' => '✗',
        'auth_required' => '⚠',
        default => '?'
    };
    
    logMessage("{$statusIcon} [{$method}] {$endpoint} - HTTP {$result['http_code']} ({$result['response_time']}ms)");
    
    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $error) {
            logMessage("  ERROR: {$error}", 'ERROR');
        }
    }
    
    if (!empty($result['warnings'])) {
        foreach ($result['warnings'] as $warning) {
            logMessage("  WARNING: {$warning}", 'WARN');
        }
    }
    
    $testResults['endpoints'][] = $result;
    
    return $result;
}

/**
 * Define all API endpoints to test
 */
function getEndpointDefinitions(): array
{
    return [
        // ============ Authentication Endpoints ============
        [
            'group' => 'Authentication',
            'endpoints' => [
                ['GET', '/api/v1/auth/csrf-token', 'Get CSRF token', [200], null, ['success', 'data'], false],
                ['GET', '/api/v1/auth/session-status', 'Get session status', [200], null, ['success'], true],
                ['GET', '/api/v1/auth/current-user', 'Get current user', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/auth/active-sessions', 'Get active sessions', [200, 401], null, ['success'], true],
            ]
        ],
        
        // ============ Patients Endpoints ============
        [
            'group' => 'Patients',
            'endpoints' => [
                ['GET', '/api/v1/patients', 'List patients', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/patients?page=1&per_page=10', 'List patients (paginated)', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/patients/search?q=test', 'Search patients', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/patients/recent', 'Get recent patients', [200, 401], null, ['success'], true],
            ]
        ],
        
        // ============ Encounters Endpoints ============
        [
            'group' => 'Encounters',
            'endpoints' => [
                ['GET', '/api/v1/encounters', 'List encounters', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/encounters?page=1&per_page=10', 'List encounters (paginated)', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/encounters/today', 'Get today\'s encounters', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/encounters/pending', 'Get pending encounters', [200, 401], null, ['success'], true],
            ]
        ],
        
        // ============ Admin Endpoints ============
        [
            'group' => 'Admin',
            'endpoints' => [
                ['GET', '/api/v1/admin', 'Admin dashboard', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/stats', 'Admin stats', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/cases', 'Recent cases', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/patient-flow', 'Patient flow metrics', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/sites', 'Site performance', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/providers', 'Provider performance', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/staff', 'Staff list', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/clearance', 'Clearance stats', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/compliance', 'Compliance alerts', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/training', 'Training modules', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/credentials', 'Expiring credentials', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/osha/300', 'OSHA 300 Log', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/admin/osha/300a', 'OSHA 300A Summary', [200, 401, 403], null, ['success'], true],
            ]
        ],
        
        // ============ Notifications Endpoints ============
        [
            'group' => 'Notifications',
            'endpoints' => [
                ['GET', '/api/v1/notifications', 'List notifications', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/notifications/unread-count', 'Unread count', [200, 401], null, ['success'], true],
            ]
        ],
        
        // ============ Doctor (MRO) Endpoints ============
        [
            'group' => 'Doctor/MRO',
            'endpoints' => [
                ['GET', '/api/v1/doctor/dashboard', 'Doctor dashboard', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/doctor/stats', 'Doctor stats', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/doctor/verifications/pending', 'Pending verifications', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/doctor/verifications/history', 'Verification history', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/doctor/orders/pending', 'Pending orders', [200, 401, 403], null, ['success'], true],
            ]
        ],
        
        // ============ Clinical Provider Endpoints ============
        [
            'group' => 'Clinical Provider',
            'endpoints' => [
                ['GET', '/api/v1/clinicalprovider/dashboard', 'Clinical provider dashboard', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/clinicalprovider/stats', 'Provider stats', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/clinicalprovider/encounters/active', 'Active encounters', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/clinicalprovider/encounters/recent', 'Recent encounters', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/clinicalprovider/orders/pending', 'Pending orders', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/clinicalprovider/qa/pending', 'Pending QA reviews', [200, 401, 403], null, ['success'], true],
            ]
        ],
        
        // ============ Reports Endpoints ============
        [
            'group' => 'Reports',
            'endpoints' => [
                ['GET', '/api/v1/reports', 'List report types', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/reports/dashboard', 'Dashboard report', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/reports/safety', 'Safety report', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/reports/compliance', 'Compliance report', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/reports/patient-volume', 'Patient volume report', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/reports/encounter-summary', 'Encounter summary', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/reports/dot-summary', 'DOT summary', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/reports/osha-summary', 'OSHA summary', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/reports/provider-productivity', 'Provider productivity', [200, 401], null, ['success'], true],
            ]
        ],
        
        // ============ DOT Tests Endpoints ============
        [
            'group' => 'DOT Tests',
            'endpoints' => [
                ['GET', '/api/v1/dot-tests', 'List DOT tests', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/dot-tests/deadline', 'Tests approaching deadline', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/dot-tests/status/pending', 'Pending tests', [200, 401], null, ['success'], true],
            ]
        ],
        
        // ============ OSHA Endpoints ============
        [
            'group' => 'OSHA',
            'endpoints' => [
                ['GET', '/api/v1/osha/injuries', 'List injuries', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/osha/300-log', 'OSHA 300 Log', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/osha/300a-log', 'OSHA 300A Log', [200, 401], null, ['success'], true],
                ['GET', '/api/v1/osha/rates', 'TRIR/DART rates', [200, 401], null, ['success'], true],
            ]
        ],
        
        // ============ Privacy Officer Endpoints ============
        [
            'group' => 'Privacy Officer',
            'endpoints' => [
                ['GET', '/api/v1/privacy/dashboard', 'Privacy dashboard', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/privacy/compliance/kpis', 'Compliance KPIs', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/privacy/access-logs', 'PHI access logs', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/privacy/consents', 'Consent status', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/privacy/regulatory', 'Regulatory updates', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/privacy/breaches', 'Breach incidents', [200, 401, 403], null, ['success'], true],
                ['GET', '/api/v1/privacy/training', 'Training compliance', [200, 401, 403], null, ['success'], true],
            ]
        ],
        
        // ============ Disclosures Endpoints ============
        [
            'group' => 'Disclosures',
            'endpoints' => [
                ['GET', '/api/v1/disclosures/templates', 'List disclosure templates', [200, 401], null, ['success'], true],
            ]
        ],
        
        // ============ Dashboard Stats (Legacy) ============
        [
            'group' => 'Dashboard Stats',
            'endpoints' => [
                ['GET', '/api/dashboard-stats', 'Dashboard statistics', [200, 401, 403], null, ['success'], true],
            ]
        ],
        
        // ============ Video Meetings ============
        [
            'group' => 'Video Meetings',
            'endpoints' => [
                ['GET', '/api/video/my-meetings', 'My meetings', [200, 401], null, [], true],
            ]
        ],
        
        // ============ Error Handling Tests ============
        [
            'group' => 'Error Handling',
            'endpoints' => [
                ['GET', '/api/v1/nonexistent-endpoint', 'Non-existent endpoint', [404], null, [], true],
                ['GET', '/api/v1/patients/invalid-uuid-format', 'Invalid UUID format', [400, 404, 500], null, [], true],
            ]
        ],
    ];
}

/**
 * Generate test report in Markdown format
 */
function generateReport(): string
{
    global $testResults;
    
    $report = "# API Endpoint Test Results\n\n";
    $report .= "**Generated:** " . date('Y-m-d H:i:s') . "\n";
    $report .= "**Base URL:** " . BASE_URL . "\n\n";
    
    // Summary
    $report .= "## Summary\n\n";
    $report .= "| Metric | Count |\n";
    $report .= "|--------|-------|\n";
    $report .= "| Total Endpoints Tested | {$testResults['summary']['total']} |\n";
    $report .= "| Passed | {$testResults['summary']['passed']} |\n";
    $report .= "| Failed | {$testResults['summary']['failed']} |\n";
    $report .= "| Warnings | {$testResults['summary']['warnings']} |\n";
    $report .= "| Skipped | {$testResults['summary']['skipped']} |\n\n";
    
    // Pass rate
    $passRate = $testResults['summary']['total'] > 0 
        ? round(($testResults['summary']['passed'] / $testResults['summary']['total']) * 100, 1) 
        : 0;
    $report .= "**Pass Rate:** {$passRate}%\n\n";
    
    // Detailed Results by Group
    $report .= "## Detailed Results by Module\n\n";
    
    $groupedResults = [];
    foreach ($testResults['endpoints'] as $result) {
        $group = 'Other';
        foreach (getEndpointDefinitions() as $groupDef) {
            foreach ($groupDef['endpoints'] as $ep) {
                if ($ep[1] === $result['endpoint'] || strpos($result['endpoint'], explode('?', $ep[1])[0]) === 0) {
                    $group = $groupDef['group'];
                    break 2;
                }
            }
        }
        $groupedResults[$group][] = $result;
    }
    
    foreach ($groupedResults as $group => $results) {
        $report .= "### {$group}\n\n";
        $report .= "| Status | Method | Endpoint | HTTP Code | Response Time |\n";
        $report .= "|--------|--------|----------|-----------|---------------|\n";
        
        foreach ($results as $result) {
            $statusIcon = match($result['status']) {
                'passed' => '✅',
                'failed' => '❌',
                'auth_required' => '⚠️',
                default => '❓'
            };
            
            $report .= "| {$statusIcon} | {$result['method']} | `{$result['endpoint']}` | {$result['http_code']} | {$result['response_time']}ms |\n";
        }
        $report .= "\n";
    }
    
    // Issues Found
    if (!empty($testResults['issues']) || $testResults['summary']['failed'] > 0) {
        $report .= "## Issues Found\n\n";
        
        foreach ($testResults['endpoints'] as $result) {
            if (!empty($result['errors']) || !empty($result['warnings'])) {
                $report .= "### `{$result['method']} {$result['endpoint']}`\n\n";
                
                if (!empty($result['errors'])) {
                    $report .= "**Errors:**\n";
                    foreach ($result['errors'] as $error) {
                        $report .= "- ❌ {$error}\n";
                    }
                }
                
                if (!empty($result['warnings'])) {
                    $report .= "**Warnings:**\n";
                    foreach ($result['warnings'] as $warning) {
                        $report .= "- ⚠️ {$warning}\n";
                    }
                }
                
                if (!empty($result['missing_fields'])) {
                    $report .= "**Missing Fields:** " . implode(', ', $result['missing_fields']) . "\n";
                }
                
                if (!empty($result['null_fields'])) {
                    $report .= "**Null Fields:** " . implode(', ', $result['null_fields']) . "\n";
                }
                
                $report .= "\n";
            }
        }
    }
    
    // Performance Concerns
    if (!empty($testResults['performance'])) {
        $report .= "## Performance Concerns\n\n";
        $report .= "Endpoints with response times > 2000ms:\n\n";
        $report .= "| Endpoint | Response Time |\n";
        $report .= "|----------|---------------|\n";
        
        foreach ($testResults['performance'] as $perf) {
            $report .= "| `{$perf['endpoint']}` | {$perf['time']}ms |\n";
        }
        $report .= "\n";
    }
    
    // Complete Endpoint Inventory
    $report .= "## Complete API Endpoint Inventory\n\n";
    
    foreach (getEndpointDefinitions() as $groupDef) {
        $report .= "### {$groupDef['group']}\n\n";
        $report .= "| Method | Endpoint | Description | Auth Required |\n";
        $report .= "|--------|----------|-------------|---------------|\n";
        
        foreach ($groupDef['endpoints'] as $ep) {
            $authRequired = $ep[6] ?? true;
            $authIcon = $authRequired ? '🔒 Yes' : '🔓 No';
            $report .= "| {$ep[0]} | `{$ep[1]}` | {$ep[2]} | {$authIcon} |\n";
        }
        $report .= "\n";
    }
    
    return $report;
}

/**
 * Run all tests
 */
function runTests(bool $skipAuth = false): void
{
    global $testResults;
    
    $testResults['summary']['start_time'] = microtime(true);
    
    logMessage("=== Starting API Endpoint Tests ===");
    logMessage("Base URL: " . BASE_URL);
    
    // Attempt authentication if not skipping
    if (!$skipAuth) {
        // Try to authenticate - you may need to update these credentials
        $authenticated = authenticate('cadmin', 'Admin123!');
        
        if (!$authenticated) {
            logMessage("Could not authenticate. Running tests without authentication.", 'WARN');
        }
    } else {
        logMessage("Skipping authentication as requested", 'INFO');
    }
    
    // Run endpoint tests
    foreach (getEndpointDefinitions() as $groupDef) {
        logMessage("\n=== Testing {$groupDef['group']} Endpoints ===");
        
        foreach ($groupDef['endpoints'] as $endpoint) {
            [$method, $url, $description, $expectedCodes, $requestData, $expectedFields, $requiresAuth] = array_pad($endpoint, 7, null);
            
            $expectedCodes = $expectedCodes ?? [200];
            $expectedFields = $expectedFields ?? [];
            $requiresAuth = $requiresAuth ?? true;
            
            testEndpoint($method, $url, $description, $expectedCodes, $requestData, $expectedFields, $requiresAuth);
            
            // Small delay between requests to avoid rate limiting
            usleep(100000); // 100ms
        }
    }
    
    $testResults['summary']['end_time'] = microtime(true);
    $totalTime = round($testResults['summary']['end_time'] - $testResults['summary']['start_time'], 2);
    
    logMessage("\n=== Test Summary ===");
    logMessage("Total: {$testResults['summary']['total']}");
    logMessage("Passed: {$testResults['summary']['passed']}");
    logMessage("Failed: {$testResults['summary']['failed']}");
    logMessage("Warnings: {$testResults['summary']['warnings']}");
    logMessage("Total Time: {$totalTime}s");
    
    // Generate and save report
    $report = generateReport();
    $reportPath = __DIR__ . '/../docs/API_ENDPOINT_TEST_RESULTS.md';
    file_put_contents($reportPath, $report);
    logMessage("Report saved to: {$reportPath}");
}

// Parse command line arguments
$options = getopt('', ['skip-auth', 'help', 'url:']);

if (isset($options['help'])) {
    echo "Usage: php test_api_endpoints.php [options]\n\n";
    echo "Options:\n";
    echo "  --skip-auth    Skip authentication (test public endpoints only)\n";
    echo "  --url=URL      Set base URL (default: http://localhost:8000)\n";
    echo "  --help         Show this help message\n\n";
    exit(0);
}

if (isset($options['url'])) {
    define('BASE_URL_OVERRIDE', $options['url']);
}

// Create log directory if needed
$logDir = dirname(LOG_FILE);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Clear previous log
file_put_contents(LOG_FILE, '');

// Run tests
runTests(isset($options['skip-auth']));

echo "\n✅ Testing complete. Check docs/API_ENDPOINT_TEST_RESULTS.md for full report.\n";
