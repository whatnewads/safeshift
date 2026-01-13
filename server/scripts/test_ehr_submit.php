<?php

/**
 * EHR Submit End-to-End Test Script
 * 
 * This script tests the complete encounter workflow:
 * 1. Authenticates as a test user
 * 2. Creates a new encounter
 * 3. Updates the encounter (save draft)
 * 4. Submits the encounter for review
 * 5. Verifies each step returns appropriate status codes
 * 
 * Usage:
 *   php scripts/test_ehr_submit.php
 * 
 * Requirements:
 *   - Database must be running and accessible
 *   - Test user must exist (or use existing admin user)
 * 
 * @package Tests\E2E
 */

declare(strict_types=1);

// Change to project root
chdir(dirname(__DIR__));

// Configuration
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost:8000');
define('API_BASE', BASE_URL . '/api/v1');
define('TEST_USER_EMAIL', getenv('TEST_USER_EMAIL') ?: 'tadmin_user');
define('TEST_USER_PASSWORD', getenv('TEST_USER_PASSWORD') ?: 'TAdmin123!');
define('VERBOSE', in_array('-v', $argv ?? []) || in_array('--verbose', $argv ?? []));

// Test results storage
$testResults = [];
$sessionCookie = null;
$createdEncounterId = null;

/**
 * Output colored text to console
 */
function colorize(string $text, string $color): string
{
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m",
        'bold' => "\033[1m",
    ];
    
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

/**
 * Output test status
 */
function output(string $message, string $type = 'info'): void
{
    $prefix = match($type) {
        'pass' => colorize('✓ PASS', 'green'),
        'fail' => colorize('✗ FAIL', 'red'),
        'info' => colorize('ℹ INFO', 'blue'),
        'warn' => colorize('⚠ WARN', 'yellow'),
        'header' => colorize('═══', 'bold'),
        default => '',
    };
    
    echo "{$prefix} {$message}\n";
}

/**
 * Make HTTP request with session cookie
 */
function makeRequest(
    string $method,
    string $url,
    array $data = [],
    ?string $sessionCookie = null
): array {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    if ($sessionCookie) {
        $headers[] = "Cookie: {$sessionCookie}";
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'status' => 0,
            'error' => $error,
            'data' => null,
            'cookies' => [],
        ];
    }
    
    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Extract cookies from headers
    $cookies = [];
    preg_match_all('/Set-Cookie:\s*([^;]+)/i', $headerStr, $matches);
    if (!empty($matches[1])) {
        $cookies = $matches[1];
    }
    
    $jsonData = json_decode($body, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status' => $httpCode,
        'data' => $jsonData,
        'body' => $body,
        'cookies' => $cookies,
        'headers' => $headerStr,
    ];
}

/**
 * Test Case: Authenticate User
 */
function testAuthenticate(): array
{
    global $sessionCookie;
    
    $result = [
        'name' => 'Authenticate User',
        'passed' => false,
        'status' => 0,
        'message' => '',
    ];
    
    // Step 1: Get CSRF token to establish a session first
    $csrfResponse = makeRequest('GET', API_BASE . '/auth/csrf-token', []);
    $initialSessionCookie = null;
    
    if (!empty($csrfResponse['cookies'])) {
        foreach ($csrfResponse['cookies'] as $cookie) {
            // Look for SAFESHIFT_SESSION (custom) or PHPSESSID (default)
            if (str_starts_with($cookie, 'SAFESHIFT_SESSION=') || str_starts_with($cookie, 'PHPSESSID=')) {
                $initialSessionCookie = $cookie;
                $sessionCookie = $cookie;
                break;
            }
        }
    }
    
    if (VERBOSE) {
        output("CSRF cookies: " . json_encode($csrfResponse['cookies']), 'info');
        if ($initialSessionCookie) {
            output("Initial session: " . $initialSessionCookie, 'info');
        }
    }
    
    // Step 2: Login with the established session
    $response = makeRequest('POST', API_BASE . '/auth/login', [
        'username' => TEST_USER_EMAIL,
        'password' => TEST_USER_PASSWORD,
    ], $initialSessionCookie);
    
    $result['status'] = $response['status'];
    
    if (VERBOSE) {
        output("Login cookies: " . json_encode($response['cookies']), 'info');
        output("Login headers: " . substr($response['headers'], 0, 500), 'info');
    }
    
    // Check for new session cookie in login response (session regeneration)
    if (!empty($response['cookies'])) {
        foreach ($response['cookies'] as $cookie) {
            // Look for SAFESHIFT_SESSION (custom) or PHPSESSID (default)
            if (str_starts_with($cookie, 'SAFESHIFT_SESSION=') || str_starts_with($cookie, 'PHPSESSID=')) {
                $sessionCookie = $cookie;
                if (VERBOSE) {
                    output("Updated session from login: " . $sessionCookie, 'info');
                }
                break;
            }
        }
    }
    
    if ($response['success']) {
        // Verify login was successful (stage: complete or no 2FA required)
        $stage = $response['data']['data']['stage'] ?? '';
        if ($stage === 'complete') {
            $result['passed'] = true;
            $result['message'] = 'Successfully authenticated as ' . TEST_USER_EMAIL . " (stage: {$stage}, session: " . ($sessionCookie ? 'yes' : 'no') . ")";
        } elseif ($stage === '2fa') {
            $result['message'] = '2FA verification required - test user needs MFA disabled';
        } else {
            $result['passed'] = true;
            $result['message'] = 'Authenticated as ' . TEST_USER_EMAIL . " (stage: {$stage})";
        }
    } else {
        $result['message'] = $response['data']['message'] ?? $response['error'] ?? 'Authentication failed';
    }
    
    if (VERBOSE) {
        output("Final session cookie: " . ($sessionCookie ?: '(none)'), 'info');
        output("Response: " . json_encode($response['data'] ?? []), 'info');
    }
    
    return $result;
}

/**
 * Test Case: Create New Encounter
 */
function testCreateEncounter(): array
{
    global $sessionCookie, $createdEncounterId;
    
    $result = [
        'name' => 'Create New Encounter',
        'passed' => false,
        'status' => 0,
        'message' => '',
    ];
    
    // Generate a temporary patient_id (the API will create a patient from demographics if not found)
    $tempPatientId = 'temp-' . time() . '-' . mt_rand(1000, 9999);
    
    $encounterData = [
        // Patient ID is required - use temp ID (system will create patient from demographics)
        'patient_id' => $tempPatientId,
        
        // Patient information (for patient creation)
        'patient_first_name' => 'Test',
        'patient_last_name' => 'Patient',
        'patient_dob' => '1985-03-15',
        'patient_ssn' => '123-45-6789',
        'patient_phone' => '555-123-4567',
        'patient_email' => 'test.patient@example.com',
        'patient_address' => '123 Test Street',
        'patient_city' => 'Test City',
        'patient_state' => 'TX',
        'patient_employer' => 'Test Employer Inc',
        
        // Incident information
        'clinic_name' => 'SafeShift Test Clinic',
        'clinic_address' => '456 Clinic Ave',
        'clinic_city' => 'Clinic City',
        'clinic_state' => 'TX',
        'patient_contact_time' => date('Y-m-d H:i:s'),
        'cleared_clinic_time' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        'location' => 'worksite',
        'mass_casualty' => 'no',
        'injury_classification' => 'work_related',
        
        // Status
        'status' => 'draft',
        'encounter_type' => 'clinical',
        
        // Form data
        'formData' => [
            'incidentForm' => [
                'clinicName' => 'SafeShift Test Clinic',
                'clinicStreetAddress' => '456 Clinic Ave',
                'clinicCity' => 'Clinic City',
                'clinicState' => 'TX',
            ],
            'patientForm' => [
                'firstName' => 'Test',
                'lastName' => 'Patient',
                'dob' => '1985-03-15',
                'ssn' => '123-45-6789',
            ],
        ],
    ];
    
    $response = makeRequest('POST', API_BASE . '/encounters', $encounterData, $sessionCookie);
    
    $result['status'] = $response['status'];
    
    if ($response['success'] && isset($response['data']['data']['encounter']['encounter_id'])) {
        $createdEncounterId = $response['data']['data']['encounter']['encounter_id'];
        $result['passed'] = true;
        $result['message'] = "Created encounter ID: {$createdEncounterId}";
    } elseif ($response['status'] === 200 || $response['status'] === 201) {
        // Try to extract ID from different response formats
        $encounterId = $response['data']['data']['encounter']['id'] 
            ?? $response['data']['encounter']['encounter_id']
            ?? $response['data']['encounter']['id']
            ?? $response['data']['id']
            ?? null;
        
        if ($encounterId) {
            $createdEncounterId = $encounterId;
            $result['passed'] = true;
            $result['message'] = "Created encounter ID: {$createdEncounterId}";
        } else {
            $result['message'] = 'Encounter created but could not extract ID';
        }
    } else {
        $result['message'] = $response['data']['message'] ?? $response['error'] ?? 'Failed to create encounter';
    }
    
    if (VERBOSE) {
        output("Response: " . json_encode($response['data'] ?? []), 'info');
    }
    
    return $result;
}

/**
 * Test Case: Update Encounter (Save Draft)
 */
function testUpdateEncounter(): array
{
    global $sessionCookie, $createdEncounterId;
    
    $result = [
        'name' => 'Update Encounter (Save Draft)',
        'passed' => false,
        'status' => 0,
        'message' => '',
    ];
    
    if (!$createdEncounterId) {
        $result['message'] = 'No encounter ID available (previous test failed)';
        return $result;
    }
    
    $updateData = [
        // Add clinical narrative
        'narrative' => 'Patient presented with work-related injury to right hand. Assessment completed. No significant abnormalities noted. Patient is stable and alert.',
        
        // Add disposition
        'disposition' => 'return-full-duty',
        'disposition_notes' => 'Patient cleared to return to work with no restrictions.',
        
        // Update status
        'status' => 'in-progress',
        
        // Add vitals (as nested data)
        'vitals' => [
            [
                'time' => date('H:i'),
                'bp' => '120/80',
                'pulse' => '72',
                'respiration' => '16',
                'spo2' => '98',
                'temp' => '98.6',
            ],
        ],
        
        // Add assessment regions
        'assessments' => [
            [
                'time' => date('Y-m-d H:i:s'),
                'regions' => [
                    'mentalStatus' => 'No Abnormalities',
                    'skin' => 'No Abnormalities',
                    'heent' => 'No Abnormalities',
                    'chest' => 'No Abnormalities',
                    'abdomen' => 'No Abnormalities',
                    'back' => 'No Abnormalities',
                    'pelvisGUI' => 'No Abnormalities',
                    'extremities' => 'Assessed',
                    'neurological' => 'No Abnormalities',
                ],
            ],
        ],
        
        // Provider information
        'providers' => [
            [
                'name' => 'Test Provider',
                'role' => 'lead',
            ],
        ],
        
        // Medical history
        'medical_history' => 'No significant medical history',
        'allergies' => 'None known',
        'current_medications' => 'None',
        
        // Employment info
        'supervisor_name' => 'Jane Manager',
        'supervisor_phone' => '555-987-6543',
    ];
    
    $response = makeRequest('PUT', API_BASE . "/encounters/{$createdEncounterId}", $updateData, $sessionCookie);
    
    $result['status'] = $response['status'];
    
    if ($response['success']) {
        $result['passed'] = true;
        $result['message'] = "Updated encounter {$createdEncounterId} with draft data";
    } else {
        $result['message'] = $response['data']['message'] ?? $response['error'] ?? 'Failed to update encounter';
    }
    
    if (VERBOSE) {
        output("Response: " . json_encode($response['data'] ?? []), 'info');
    }
    
    return $result;
}

/**
 * Test Case: Get Encounter (Verify Update)
 */
function testGetEncounter(): array
{
    global $sessionCookie, $createdEncounterId;
    
    $result = [
        'name' => 'Get Encounter (Verify Update)',
        'passed' => false,
        'status' => 0,
        'message' => '',
    ];
    
    if (!$createdEncounterId) {
        $result['message'] = 'No encounter ID available (previous test failed)';
        return $result;
    }
    
    $response = makeRequest('GET', API_BASE . "/encounters/{$createdEncounterId}", [], $sessionCookie);
    
    $result['status'] = $response['status'];
    
    if ($response['success']) {
        $encounter = $response['data']['data']['encounter'] ?? $response['data']['encounter'] ?? null;
        
        if ($encounter) {
            $result['passed'] = true;
            $status = $encounter['status'] ?? 'unknown';
            $result['message'] = "Retrieved encounter {$createdEncounterId}, status: {$status}";
        } else {
            $result['message'] = 'Encounter data not found in response';
        }
    } else {
        $result['message'] = $response['data']['message'] ?? $response['error'] ?? 'Failed to get encounter';
    }
    
    if (VERBOSE) {
        output("Response: " . json_encode($response['data'] ?? []), 'info');
    }
    
    return $result;
}

/**
 * Test Case: Submit Encounter for Review
 */
function testSubmitEncounter(): array
{
    global $sessionCookie, $createdEncounterId;
    
    $result = [
        'name' => 'Submit Encounter for Review',
        'passed' => false,
        'status' => 0,
        'message' => '',
    ];
    
    if (!$createdEncounterId) {
        $result['message'] = 'No encounter ID available (previous test failed)';
        return $result;
    }
    
    // Include encounter data for validation
    $submitData = [
        // Patient information (required for validation)
        'patient_first_name' => 'Test',
        'patient_last_name' => 'Patient',
        'patient_dob' => '1985-03-15',
        'patient_ssn' => '123-45-6789',
        'patient_address' => '123 Test Street',
        'patient_city' => 'Test City',
        'patient_state' => 'TX',
        'patient_employer' => 'Test Employer Inc',
        'supervisor_name' => 'Jane Manager',
        'supervisor_phone' => '555-987-6543',
        
        // Incident information
        'clinic_name' => 'SafeShift Test Clinic',
        'clinic_address' => '456 Clinic Ave',
        'clinic_city' => 'Clinic City',
        'clinic_state' => 'TX',
        'patient_contact_time' => date('Y-m-d H:i:s'),
        'cleared_clinic_time' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        'location' => 'worksite',
        'injury_classification' => 'work_related',
        
        // Clinical data
        'narrative' => 'Patient presented with work-related injury to right hand. Assessment completed. No significant abnormalities noted. Patient is stable and alert.',
        'disposition' => 'return-full-duty',
        
        // Medical history
        'medical_history' => 'No significant medical history',
        'allergies' => 'None known',
        'current_medications' => 'None',
        
        // Provider
        'providers' => [
            ['name' => 'Test Provider', 'role' => 'lead'],
        ],
        
        // Assessments
        'assessments' => [
            [
                'time' => date('Y-m-d H:i:s'),
                'regions' => [
                    'mentalStatus' => 'No Abnormalities',
                    'skin' => 'No Abnormalities',
                    'extremities' => 'Assessed',
                ],
            ],
        ],
        
        // Vitals
        'vitals' => [
            [
                'time' => date('H:i'),
                'bp' => '120/80',
                'pulse' => '72',
            ],
        ],
        
        // Disclosures acknowledged
        'disclosures' => [
            'consent_for_treatment' => true,
            'work_related_authorization' => true,
            'hipaa_acknowledgment' => true,
        ],
    ];
    
    $response = makeRequest('PUT', API_BASE . "/encounters/{$createdEncounterId}/submit", $submitData, $sessionCookie);
    
    $result['status'] = $response['status'];
    
    if ($response['success']) {
        $result['passed'] = true;
        $result['message'] = "Submitted encounter {$createdEncounterId} for review";
    } elseif ($response['status'] === 422) {
        // Validation error - show what's missing
        $errors = $response['data']['errors'] ?? [];
        $result['message'] = 'Validation failed: ' . json_encode($errors);
    } else {
        $result['message'] = $response['data']['message'] ?? $response['error'] ?? 'Failed to submit encounter';
    }
    
    if (VERBOSE) {
        output("Response: " . json_encode($response['data'] ?? []), 'info');
    }
    
    return $result;
}

/**
 * Test Case: List Encounters
 */
function testListEncounters(): array
{
    global $sessionCookie;
    
    $result = [
        'name' => 'List Encounters',
        'passed' => false,
        'status' => 0,
        'message' => '',
    ];
    
    $response = makeRequest('GET', API_BASE . '/encounters?page=1&per_page=10', [], $sessionCookie);
    
    $result['status'] = $response['status'];
    
    if ($response['success']) {
        $encounters = $response['data']['data']['encounters'] ?? $response['data']['encounters'] ?? [];
        $count = count($encounters);
        $result['passed'] = true;
        $result['message'] = "Retrieved {$count} encounters";
    } else {
        $result['message'] = $response['data']['message'] ?? $response['error'] ?? 'Failed to list encounters';
    }
    
    if (VERBOSE) {
        output("Response: " . json_encode($response['data'] ?? []), 'info');
    }
    
    return $result;
}

/**
 * Test Case: Unauthenticated Request (should return 401)
 */
function testUnauthenticatedRequest(): array
{
    $result = [
        'name' => 'Unauthenticated Request Returns 401',
        'passed' => false,
        'status' => 0,
        'message' => '',
    ];
    
    // Make request without session cookie
    $response = makeRequest('GET', API_BASE . '/encounters', []);
    
    $result['status'] = $response['status'];
    
    if ($response['status'] === 401) {
        $result['passed'] = true;
        $result['message'] = 'Correctly returned 401 for unauthenticated request';
    } else {
        $result['message'] = "Expected 401, got {$response['status']}";
    }
    
    if (VERBOSE) {
        output("Response: " . json_encode($response['data'] ?? []), 'info');
    }
    
    return $result;
}

/**
 * Test Case: Get Non-Existent Encounter (should return 404)
 */
function testNotFoundEncounter(): array
{
    global $sessionCookie;
    
    $result = [
        'name' => 'Non-Existent Encounter Returns 404',
        'passed' => false,
        'status' => 0,
        'message' => '',
    ];
    
    $response = makeRequest('GET', API_BASE . '/encounters/non-existent-id-12345', [], $sessionCookie);
    
    $result['status'] = $response['status'];
    
    if ($response['status'] === 404) {
        $result['passed'] = true;
        $result['message'] = 'Correctly returned 404 for non-existent encounter';
    } else {
        $result['message'] = "Expected 404, got {$response['status']}";
    }
    
    if (VERBOSE) {
        output("Response: " . json_encode($response['data'] ?? []), 'info');
    }
    
    return $result;
}

/**
 * Run all tests and output results
 */
function runAllTests(): void
{
    global $testResults;
    
    echo "\n";
    output(" EHR Submit End-to-End Tests ", 'header');
    echo colorize("═══════════════════════════════════════════════════════════\n", 'bold');
    echo "\n";
    
    output("Base URL: " . BASE_URL, 'info');
    output("Test User: " . TEST_USER_EMAIL, 'info');
    echo "\n";
    
    // Define test sequence
    $tests = [
        'testAuthenticate',
        'testUnauthenticatedRequest',
        'testCreateEncounter',
        'testUpdateEncounter',
        'testGetEncounter',
        'testSubmitEncounter',
        'testListEncounters',
        'testNotFoundEncounter',
    ];
    
    // Run each test
    foreach ($tests as $testFunction) {
        $result = $testFunction();
        $testResults[] = $result;
        
        $type = $result['passed'] ? 'pass' : 'fail';
        $statusInfo = $result['status'] ? " (HTTP {$result['status']})" : '';
        output("{$result['name']}{$statusInfo}", $type);
        
        if (!$result['passed'] || VERBOSE) {
            output("  → {$result['message']}", 'info');
        }
        
        echo "\n";
    }
    
    // Summary
    echo colorize("═══════════════════════════════════════════════════════════\n", 'bold');
    output(" Test Summary ", 'header');
    echo colorize("═══════════════════════════════════════════════════════════\n", 'bold');
    
    $passed = count(array_filter($testResults, fn($r) => $r['passed']));
    $failed = count($testResults) - $passed;
    $total = count($testResults);
    
    echo "\n";
    output("Total: {$total} tests", 'info');
    output("Passed: {$passed}", $passed > 0 ? 'pass' : 'info');
    
    if ($failed > 0) {
        output("Failed: {$failed}", 'fail');
        echo "\n";
        output("Failed Tests:", 'warn');
        foreach ($testResults as $result) {
            if (!$result['passed']) {
                output("  - {$result['name']}: {$result['message']}", 'fail');
            }
        }
    }
    
    echo "\n";
    
    // Exit code based on results
    if ($failed > 0) {
        exit(1);
    }
    
    exit(0);
}

// Main execution
runAllTests();
