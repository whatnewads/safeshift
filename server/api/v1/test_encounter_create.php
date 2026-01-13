<?php
/**
 * Encounter Creation Test Script
 * 
 * Simulates the full EHR form submission like the frontend would do.
 * Run this file directly to test encounter creation.
 * 
 * Usage: php api/v1/test_encounter_create.php
 *        OR visit http://localhost:8000/api/v1/test_encounter_create.php
 */

declare(strict_types=1);

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set content type for browser viewing
header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/../../logs/test_encounter_debug.log';

function testLog(string $message): void {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message\n";
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

testLog("=== ENCOUNTER CREATION TEST START ===");

try {
    // Step 1: Load bootstrap
    testLog("Step 1: Loading bootstrap...");
    require_once __DIR__ . '/../../includes/bootstrap.php';
    testLog("Bootstrap loaded OK");
    
    // Step 2: Load required files
    testLog("Step 2: Loading required files...");
    require_once __DIR__ . '/../../model/Interfaces/EntityInterface.php';
    require_once __DIR__ . '/../../model/Interfaces/RepositoryInterface.php';
    require_once __DIR__ . '/../../model/Core/Database.php';
    require_once __DIR__ . '/../../model/Entities/Encounter.php';
    require_once __DIR__ . '/../../model/Entities/Patient.php';
    require_once __DIR__ . '/../../model/Repositories/EncounterRepository.php';
    require_once __DIR__ . '/../../model/Repositories/PatientRepository.php';
    require_once __DIR__ . '/../../ViewModel/EncounterViewModel.php';
    require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
    testLog("All required files loaded OK");
    
    // Step 3: Get database connection
    testLog("Step 3: Getting database connection...");
    $db = \Model\Core\Database::getInstance();
    $pdo = $db->getConnection();
    testLog("Database connection OK");
    
    // Step 4: Find a valid patient
    testLog("Step 4: Finding a valid patient...");
    $stmt = $pdo->query("SELECT patient_id, legal_first_name, legal_last_name FROM patients LIMIT 1");
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        testLog("ERROR: No patients found in database! Creating a test patient...");
        
        // Create a test patient if none exists (matching actual schema)
        $testPatientId = uniqid('test-', true);
        $insertSql = "INSERT INTO patients (patient_id, legal_first_name, legal_last_name, dob, sex_assigned_at_birth, created_at)
                      VALUES (:patient_id, 'Test', 'Patient', '1990-01-01', 'M', NOW())";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute(['patient_id' => $testPatientId]);
        testLog("Created test patient with ID: $testPatientId");
        
        $patient = [
            'patient_id' => $testPatientId,
            'legal_first_name' => 'Test',
            'legal_last_name' => 'Patient'
        ];
    }
    
    testLog("Found patient: {$patient['legal_first_name']} {$patient['legal_last_name']} (ID: {$patient['patient_id']})");
    
    // Step 5: Set up mock session (simulating authenticated user)
    testLog("Step 5: Setting up mock session...");
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['user'] = [
        'user_id' => 1,
        'clinic_id' => 1,
        'role' => 'provider',
        'first_name' => 'Test',
        'last_name' => 'Provider'
    ];
    testLog("Session set up: user_id=1, clinic_id=1");
    
    // Step 6: Create EncounterViewModel
    testLog("Step 6: Creating EncounterViewModel...");
    $viewModel = new \ViewModel\EncounterViewModel($pdo);
    $viewModel->setCurrentUser('1');
    $viewModel->setCurrentClinic('1');
    testLog("EncounterViewModel created and configured");
    
    // Step 7: Prepare full EHR form data (simulating what the frontend sends)
    // NOTE: Must match actual database schema:
    //   - encounter_type: enum('ems','clinic','telemedicine','other')
    //   - site_id: int (not clinic_id)
    //   - npi_provider: varchar(10) (not provider_id)
    testLog("Step 7: Preparing EHR form data...");
    $ehrFormData = [
        // Core encounter info (matching actual DB schema)
        'patient_id' => $patient['patient_id'],
        'encounter_type' => 'clinic',  // Valid enum: 'ems','clinic','telemedicine','other'
        'site_id' => 1,                // Using site_id instead of clinic_id
        'npi_provider' => '1234567890', // NPI instead of provider_id
        'status' => 'in_progress',     // Valid: 'planned','arrived','in_progress','completed','cancelled','voided'
        'onset_context' => 'off_duty', // Valid: 'work_related','off_duty','unknown'
        
        // Chief complaint
        'chief_complaint' => 'Annual wellness exam, patient reports feeling generally well',
        
        // Dates
        'occurred_on' => date('Y-m-d H:i:s'),
        'arrived_on' => date('Y-m-d H:i:s'),
        
        // Additional fields that may be in clinical_data or separate tables
        'employer_name' => 'Test Employer Inc.',
        'disposition' => 'Released to normal duties',
    ];
    
    testLog("EHR form data prepared with all fields");
    testLog("Data preview: " . substr(json_encode($ehrFormData), 0, 200) . "...");
    
    // Step 8: Call createEncounter
    testLog("Step 8: Calling createEncounter...");
    $startTime = microtime(true);
    $result = $viewModel->createEncounter($ehrFormData);
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    testLog("createEncounter completed in {$duration}ms");
    
    // Step 9: Analyze result
    testLog("Step 9: Analyzing result...");
    testLog("Result type: " . gettype($result));
    testLog("Result: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    $success = ($result['success'] ?? false) || (isset($result['data']['encounter']));
    $statusCode = $result['status'] ?? 200;
    
    if ($success && $statusCode < 400) {
        testLog("");
        testLog("✓ SUCCESS: Encounter created!");
        
        if (isset($result['data']['encounter']['encounter_id'])) {
            $encounterId = $result['data']['encounter']['encounter_id'];
            testLog("Encounter ID: $encounterId");
            
            // Verify we can retrieve it
            testLog("");
            testLog("Step 10: Verifying encounter retrieval...");
            $retrieveResult = $viewModel->getEncounter($encounterId);
            
            if (isset($retrieveResult['data']['encounter'])) {
                testLog("✓ Encounter retrieved successfully!");
                $encounter = $retrieveResult['data']['encounter'];
                testLog("  - Type: " . ($encounter['encounter_type'] ?? 'N/A'));
                testLog("  - Status: " . ($encounter['status'] ?? 'N/A'));
                testLog("  - Chief Complaint: " . substr($encounter['chief_complaint'] ?? 'N/A', 0, 50) . "...");
            } else {
                testLog("✗ Failed to retrieve created encounter");
            }
        }
    } else {
        testLog("");
        testLog("✗ FAILED to create encounter");
        testLog("Status code: $statusCode");
        
        if (isset($result['message'])) {
            testLog("Message: " . $result['message']);
        }
        if (isset($result['errors'])) {
            testLog("Errors: " . json_encode($result['errors']));
        }
    }
    
} catch (\Throwable $e) {
    testLog("");
    testLog("=== EXCEPTION CAUGHT ===");
    testLog("Error: " . $e->getMessage());
    testLog("File: " . $e->getFile() . ":" . $e->getLine());
    testLog("Trace:");
    testLog($e->getTraceAsString());
}

testLog("");
testLog("=== ENCOUNTER CREATION TEST END ===");
testLog("");

// Also log to the standard encounters debug log
file_put_contents(
    __DIR__ . '/../../logs/encounters_debug.log',
    file_get_contents($logFile),
    FILE_APPEND
);
