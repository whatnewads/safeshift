<?php
/**
 * Test script that simulates browser EHR submission
 * Tests patient auto-creation and encounter creation with timestamp-based IDs
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../model/Core/Database.php';
require_once __DIR__ . '/../ViewModel/EncounterViewModel.php';
require_once __DIR__ . '/../ViewModel/Core/ApiResponse.php';

use Model\Core\Database;
use ViewModel\EncounterViewModel;

echo "Testing EHR submission with browser-like data...\n";
echo "================================================\n\n";

try {
    $pdo = Database::getInstance()->getConnection();
    $vm = new EncounterViewModel($pdo);
    $vm->setCurrentUser('1')->setCurrentClinic('1');
    
    // Simulate browser request with timestamp-based patient ID (like the frontend sends)
    $browserData = [
        'patientId' => '1767038599760',  // Timestamp-based ID
        'patient_id' => '1767038599760',
        'patientFirstName' => 'Wesley',
        'patient_first_name' => 'Wesley',
        'patientLastName' => 'TestBrowser',
        'patient_last_name' => 'TestBrowser',
        'patientDob' => '1996-12-11',
        'patient_dob' => '1996-12-11',
        'patientSsn' => '987654321',
        'patient_ssn' => '987654321',
        'patientPhone' => '4175551234',
        'patient_phone' => '4175551234',
        'patientEmail' => 'testbrowser@example.com',
        'patient_email' => 'testbrowser@example.com',
        'patientAddress' => '123 Test St',
        'patient_address' => '123 Test St',
        'patientCity' => 'Springfield',
        'patient_city' => 'Springfield',
        'patientState' => 'MO',
        'patient_state' => 'MO',
        'patientEmployer' => 'Test Corp',
        'patient_employer' => 'Test Corp',
        'encounterType' => 'clinical',
        'encounter_type' => 'clinical',
        'status' => 'draft',
        'narrative' => 'Test encounter from browser simulation',
        'chief_complaint' => 'Test encounter from browser ' . date('Y-m-d H:i:s'),
        'formData' => [
            'patientForm' => [
                'id' => '1767038599760',
                'firstName' => 'Wesley',
                'lastName' => 'TestBrowser',
                'dob' => '1996-12-11',
                'sex' => 'male',
                'ssn' => '987654321',
                'phone' => '4175551234',
                'email' => 'testbrowser@example.com',
                'streetAddress' => '123 Test St',
                'city' => 'Springfield',
                'state' => 'MO',
                'employer' => 'Test Corp',
            ],
        ],
    ];
    
    echo "Input patient_id: " . $browserData['patient_id'] . " (timestamp-based)\n\n";
    echo "Calling createEncounter...\n\n";
    
    $result = $vm->createEncounter($browserData);
    
    echo "Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
    if ($result['success'] ?? false) {
        echo "✓ SUCCESS: EHR encounter created!\n";
        echo "Encounter ID: " . ($result['data']['encounter']['encounter_id'] ?? 'N/A') . "\n";
        echo "Patient ID used: " . ($result['data']['encounter']['patient_id'] ?? 'N/A') . "\n";
        
        // Verify the patient was created
        $patientId = $result['data']['encounter']['patient_id'] ?? null;
        if ($patientId) {
            $stmt = $pdo->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE patient_id = ?");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient) {
                echo "\n✓ Patient verified in database:\n";
                echo "  - ID: " . $patient['patient_id'] . "\n";
                echo "  - Name: " . ($patient['first_name'] ?? '') . " " . ($patient['last_name'] ?? '') . "\n";
            }
        }
    } else {
        echo "✗ FAILED: " . ($result['message'] ?? 'Unknown error') . "\n";
        if (!empty($result['errors'])) {
            echo "Errors: " . json_encode($result['errors']) . "\n";
        }
    }
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
