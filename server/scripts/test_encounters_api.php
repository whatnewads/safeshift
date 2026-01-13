<?php
/**
 * Test Encounters API Directly
 * 
 * This script bypasses HTTP and tests the encounter creation logic directly.
 * Run with: php scripts/test_encounters_api.php
 */

echo "=================================================\n";
echo "Testing Encounters API Logic Directly\n";
echo "=================================================\n\n";

// Set up environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/v1/encounters';

echo "1. Loading bootstrap...\n";
require_once __DIR__ . '/../includes/bootstrap.php';

echo "   ✓ Bootstrap loaded\n\n";

echo "2. Checking required files...\n";
$requiredFiles = [
    __DIR__ . '/../api/v1/encounters.php' => 'encounters.php',
    __DIR__ . '/../model/Core/Database.php' => 'Database.php',
    __DIR__ . '/../model/Entities/Encounter.php' => 'Encounter entity',
    __DIR__ . '/../model/Repositories/EncounterRepository.php' => 'EncounterRepository',
    __DIR__ . '/../ViewModel/EncounterViewModel.php' => 'EncounterViewModel',
];

$allFilesExist = true;
foreach ($requiredFiles as $path => $name) {
    if (file_exists($path)) {
        echo "   ✓ $name exists\n";
    } else {
        echo "   ✗ $name MISSING at: $path\n";
        $allFilesExist = false;
    }
}
echo "\n";

if (!$allFilesExist) {
    echo "ERROR: Some required files are missing!\n";
    exit(1);
}

echo "3. Testing Database Connection...\n";
try {
    require_once __DIR__ . '/../model/Core/Database.php';
    $db = \Model\Core\Database::getInstance();
    $pdo = $db->getConnection();
    echo "   ✓ Database connected\n\n";
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "4. Testing EncounterViewModel instantiation...\n";
try {
    require_once __DIR__ . '/../model/Entities/Encounter.php';
    require_once __DIR__ . '/../model/Entities/Patient.php';
    require_once __DIR__ . '/../model/Repositories/EncounterRepository.php';
    require_once __DIR__ . '/../model/Repositories/PatientRepository.php';
    require_once __DIR__ . '/../model/Validators/EncounterValidator.php';
    require_once __DIR__ . '/../ViewModel/EncounterViewModel.php';
    require_once __DIR__ . '/../ViewModel/Core/ApiResponse.php';
    
    $viewModel = new \ViewModel\EncounterViewModel($pdo);
    echo "   ✓ EncounterViewModel created\n\n";
} catch (Exception $e) {
    echo "   ✗ EncounterViewModel creation failed: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "5. Testing handleEncountersRoute function exists...\n";
require_once __DIR__ . '/../api/v1/encounters.php';
if (function_exists('handleEncountersRoute')) {
    echo "   ✓ handleEncountersRoute function exists\n\n";
} else {
    echo "   ✗ handleEncountersRoute function NOT FOUND\n";
    exit(1);
}

echo "6. Setting up test session (simulating logged-in user)...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user'] = [
    'user_id' => 1,
    'username' => 'test_user',
    'role' => 'clinical_provider',
    'clinic_id' => 1,
];
echo "   ✓ Test session created\n\n";

echo "7. Testing GET /encounters (list encounters)...\n";
try {
    // Capture output
    ob_start();
    $viewModel->setCurrentUser(1);
    $viewModel->setCurrentClinic(1);
    $response = $viewModel->getEncounters([], 1, 10);
    ob_end_clean();
    
    echo "   Response status: " . ($response['status'] ?? 'unknown') . "\n";
    echo "   Success: " . (isset($response['data']) ? 'yes' : 'no') . "\n";
    if (isset($response['data']['encounters'])) {
        echo "   Encounters found: " . count($response['data']['encounters']) . "\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ GET /encounters failed: " . $e->getMessage() . "\n";
}

echo "8. Testing encounter creation validation...\n";
try {
    // Test with minimal data to trigger validation
    $testData = [
        'patient_id' => null,
        'encounter_type' => '',
    ];
    
    ob_start();
    $response = $viewModel->createEncounter($testData);
    ob_end_clean();
    
    echo "   Response status: " . ($response['status'] ?? 'unknown') . "\n";
    if (isset($response['errors'])) {
        echo "   Validation errors (expected): " . json_encode($response['errors']) . "\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Creation test failed: " . $e->getMessage() . "\n";
}

echo "=================================================\n";
echo "TEST COMPLETE\n";
echo "=================================================\n";
echo "\nIf all steps passed, the encounters API logic is working.\n";
echo "The 404 issue is likely in routing, not in the API logic itself.\n";
echo "\nCheck the logs:\n";
echo "  - logs/router_debug.log\n";
echo "  - logs/api_debug.log\n";
echo "  - logs/session_debug.log\n";
