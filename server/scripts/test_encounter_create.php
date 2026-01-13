<?php
/**
 * Quick test for encounter creation
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Testing Encounter Creation ===\n\n";

// Bootstrap the application
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../model/Core/Database.php';
require_once __DIR__ . '/../ViewModel/EncounterViewModel.php';
require_once __DIR__ . '/../ViewModel/Core/ApiResponse.php';

use Model\Core\Database;
use ViewModel\EncounterViewModel;

try {
    echo "1. Getting database connection...\n";
    $pdo = Database::getInstance()->getConnection();
    echo "   ✓ Database connected\n";
    
    echo "\n2. Creating EncounterViewModel...\n";
    $vm = new EncounterViewModel($pdo);
    echo "   ✓ EncounterViewModel created\n";
    
    echo "\n3. Finding a valid patient...\n";
    $stmt = $pdo->query('SELECT patient_id FROM patients LIMIT 1');
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        echo "   ✗ No patients found in database!\n";
        exit(1);
    }
    echo "   ✓ Found patient ID: " . $patient['patient_id'] . "\n";
    
    echo "\n4. Testing validation (missing patient_id)...\n";
    $result = $vm->createEncounter([
        'encounter_type' => 'office_visit',
    ]);
    if (!($result['success'] ?? true)) {
        echo "   ✓ Validation working - correctly rejected: " . ($result['message'] ?? 'Validation failed') . "\n";
    } else {
        echo "   ✗ Validation SHOULD have failed!\n";
    }
    
    echo "\n5. Creating actual encounter...\n";
    $testData = [
        'patient_id' => $patient['patient_id'],
        'encounter_type' => 'office_visit',
        'chief_complaint' => 'CLI Test - ' . date('Y-m-d H:i:s'),
        'status' => 'in_progress'
    ];
    echo "   Data: " . json_encode($testData) . "\n";
    
    $result = $vm->createEncounter($testData);
    
    echo "\n6. Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    if ($result['success'] ?? false) {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "✓ SUCCESS: Encounter created!\n";
        echo "  Encounter ID: " . ($result['data']['encounter']['encounter_id'] ?? 'N/A') . "\n";
        echo str_repeat("=", 50) . "\n";
    } else {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "✗ FAILED: " . ($result['message'] ?? 'Unknown error') . "\n";
        if (!empty($result['errors'])) {
            echo "  Errors: " . json_encode($result['errors']) . "\n";
        }
        echo str_repeat("=", 50) . "\n";
    }
    
} catch (Throwable $e) {
    echo "\n" . str_repeat("!", 50) . "\n";
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    echo str_repeat("!", 50) . "\n";
}
