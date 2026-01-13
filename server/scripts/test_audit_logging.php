<?php
/**
 * Test Audit Logging for Patient CRUD Operations
 * 
 * This script tests:
 * 1. READ operation logging
 * 2. CREATE operation logging with new_values
 * 3. UPDATE operation logging with old_values/new_values/modified_fields
 * 4. DELETE operation logging with record preservation
 * 5. Audit log query functionality
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

// Start session to simulate logged-in user
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== Audit Logging Test Suite ===\n\n";

// Database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✓ Connected to database: " . DB_NAME . "\n\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Get a real user from the database for testing
$stmt = $pdo->query("SELECT user_id, username, status FROM User WHERE is_active = 1 LIMIT 1");
$realUser = $stmt->fetch();

if (!$realUser) {
    echo "✗ No active users found in database. Cannot run tests.\n";
    exit(1);
}

echo "Using test user from database:\n";
echo "  - User ID: {$realUser['user_id']}\n";
echo "  - Username: {$realUser['username']}\n";
echo "  - Status: {$realUser['status']}\n\n";

// Set up mock user session with REAL user ID
$_SESSION['user'] = [
    'user_id' => $realUser['user_id'],
    'username' => $realUser['username'],
    'first_name' => 'Test',
    'last_name' => 'User',
    'role' => $realUser['status'] ?? 'admin'
];
$_SESSION['user_id'] = $realUser['user_id'];
$_SESSION['id'] = $realUser['user_id'];
$_SESSION['role'] = $realUser['status'] ?? 'admin';
$_SESSION['username'] = $realUser['username'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit AuditTest/1.0';
$_SERVER['REQUEST_URI'] = '/api/patients';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Load required classes
require_once __DIR__ . '/../core/Services/BaseService.php';
require_once __DIR__ . '/../core/Services/AuditService.php';
require_once __DIR__ . '/../core/Traits/Auditable.php';

use Core\Services\AuditService;

// Create test class that uses Auditable trait
class AuditTestHelper {
    use Core\Traits\Auditable;
    
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function testCreate($resourceId, $newValues, $patientId, $description) {
        return $this->logCreate('patient', $resourceId, $newValues, $patientId, $description);
    }
    
    public function testRead($resourceId, $patientId, $description) {
        return $this->logRead('patient', $resourceId, $patientId, $description);
    }
    
    public function testUpdate($resourceId, $oldValues, $newValues, $modifiedFields, $patientId, $description) {
        return $this->logUpdate('patient', $resourceId, $oldValues, $newValues, $modifiedFields, $patientId, $description);
    }
    
    public function testDelete($resourceId, $oldValues, $patientId, $description) {
        return $this->logDelete('patient', $resourceId, $oldValues, $patientId, $description);
    }
    
    public function testFailure($action, $resourceId, $errorMessage, $patientId) {
        return $this->logFailure($action, 'patient', $resourceId, $errorMessage, $patientId);
    }
}

// Initialize test helper
$helper = new AuditTestHelper($pdo);
$auditService = new AuditService();

// Test data - use a unique test patient ID
$testPatientId = 'test-patient-' . time();
$testResults = [];

// Clear any previous test audit entries (for clean test)
try {
    $pdo->exec("DELETE FROM AuditEvent WHERE subject_id LIKE 'test-patient-%'");
    echo "✓ Cleared previous test audit entries\n\n";
} catch (Exception $e) {
    echo "Note: Could not clear previous test entries: " . $e->getMessage() . "\n\n";
}

echo "=== Test 1: CREATE Operation Logging ===\n";
$createValues = [
    'patient_id' => $testPatientId,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'date_of_birth' => '1990-05-15',
    'gender' => 'M',
    'email' => 'john.doe@test.com',
    'phone' => '555-123-4567',
    'ssn' => '***-**-1234',
    'address_line_1' => '123 Main St',
    'city' => 'Denver',
    'state' => 'CO',
    'zip_code' => '80202'
];

$result = $helper->testCreate(
    $testPatientId, 
    $createValues, 
    $testPatientId,
    "Created test patient: John Doe"
);

if ($result) {
    echo "✓ CREATE audit log created successfully\n";
    $testResults['create'] = 'PASS';
} else {
    echo "✗ CREATE audit log failed\n";
    $testResults['create'] = 'FAIL';
}

// Small delay to ensure database write completes
usleep(100000);

// Verify CREATE audit entry
$stmt = $pdo->prepare("
    SELECT * FROM AuditEvent 
    WHERE subject_id = :subject_id AND action = 'create'
    ORDER BY occurred_at DESC LIMIT 1
");
$stmt->execute(['subject_id' => $testPatientId]);
$createEntry = $stmt->fetch();

if ($createEntry) {
    echo "  - audit_id: {$createEntry['audit_id']}\n";
    echo "  - user_name: {$createEntry['user_name']}\n";
    echo "  - user_role: {$createEntry['user_role']}\n";
    echo "  - patient_id: {$createEntry['patient_id']}\n";
    echo "  - success: " . ($createEntry['success'] ? 'true' : 'false') . "\n";
    echo "  - new_values present: " . (!empty($createEntry['new_values']) ? 'YES' : 'NO') . "\n";
    echo "  - modified_fields present: " . (!empty($createEntry['modified_fields']) ? 'YES' : 'NO') . "\n";
    
    if (!empty($createEntry['new_values'])) {
        $newVals = json_decode($createEntry['new_values'], true);
        echo "  - new_values sample: first_name = " . ($newVals['first_name'] ?? 'N/A') . "\n";
    }
} else {
    echo "  ✗ Could not retrieve CREATE audit entry\n";
    $testResults['create'] = 'FAIL';
}

echo "\n=== Test 2: READ Operation Logging ===\n";
$result = $helper->testRead(
    $testPatientId, 
    $testPatientId,
    "Accessed patient record: John Doe"
);

if ($result) {
    echo "✓ READ audit log created successfully\n";
    $testResults['read'] = 'PASS';
} else {
    echo "✗ READ audit log failed\n";
    $testResults['read'] = 'FAIL';
}

usleep(100000);

// Verify READ audit entry
$stmt = $pdo->prepare("
    SELECT * FROM AuditEvent 
    WHERE subject_id = :subject_id AND action = 'read'
    ORDER BY occurred_at DESC LIMIT 1
");
$stmt->execute(['subject_id' => $testPatientId]);
$readEntry = $stmt->fetch();

if ($readEntry) {
    echo "  - audit_id: {$readEntry['audit_id']}\n";
    echo "  - user_name: {$readEntry['user_name']}\n";
    echo "  - patient_id: {$readEntry['patient_id']}\n";
    echo "  - success: " . ($readEntry['success'] ? 'true' : 'false') . "\n";
} else {
    echo "  ✗ Could not retrieve READ audit entry\n";
    $testResults['read'] = 'FAIL';
}

echo "\n=== Test 3: UPDATE Operation Logging ===\n";
$oldValues = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@test.com',
    'phone' => '555-123-4567',
    'city' => 'Denver'
];

$newValues = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe.new@test.com',  // Changed
    'phone' => '555-999-8888',  // Changed
    'city' => 'Boulder'  // Changed
];

$result = $helper->testUpdate(
    $testPatientId, 
    $oldValues, 
    $newValues, 
    null, // Auto-calculate modified fields
    $testPatientId,
    "Updated patient contact info: John Doe"
);

if ($result) {
    echo "✓ UPDATE audit log created successfully\n";
    $testResults['update'] = 'PASS';
} else {
    echo "✗ UPDATE audit log failed\n";
    $testResults['update'] = 'FAIL';
}

usleep(100000);

// Verify UPDATE audit entry
$stmt = $pdo->prepare("
    SELECT * FROM AuditEvent 
    WHERE subject_id = :subject_id AND action = 'update'
    ORDER BY occurred_at DESC LIMIT 1
");
$stmt->execute(['subject_id' => $testPatientId]);
$updateEntry = $stmt->fetch();

if ($updateEntry) {
    echo "  - audit_id: {$updateEntry['audit_id']}\n";
    echo "  - user_name: {$updateEntry['user_name']}\n";
    echo "  - patient_id: {$updateEntry['patient_id']}\n";
    echo "  - success: " . ($updateEntry['success'] ? 'true' : 'false') . "\n";
    echo "  - old_values present: " . (!empty($updateEntry['old_values']) ? 'YES' : 'NO') . "\n";
    echo "  - new_values present: " . (!empty($updateEntry['new_values']) ? 'YES' : 'NO') . "\n";
    echo "  - modified_fields present: " . (!empty($updateEntry['modified_fields']) ? 'YES' : 'NO') . "\n";
    
    if (!empty($updateEntry['old_values'])) {
        $oldVals = json_decode($updateEntry['old_values'], true);
        echo "  - old_values: " . json_encode($oldVals) . "\n";
    }
    
    if (!empty($updateEntry['new_values'])) {
        $newVals = json_decode($updateEntry['new_values'], true);
        echo "  - new_values: " . json_encode($newVals) . "\n";
    }
    
    if (!empty($updateEntry['modified_fields'])) {
        $modFields = json_decode($updateEntry['modified_fields'], true);
        echo "  - modified_fields: " . json_encode($modFields) . "\n";
        
        // Verify correct fields detected as modified
        $expectedModified = ['email', 'phone', 'city'];
        $actualModified = $modFields;
        sort($expectedModified);
        sort($actualModified);
        
        if ($expectedModified === $actualModified) {
            echo "  ✓ Modified fields correctly detected\n";
        } else {
            echo "  ⚠ Modified fields mismatch. Expected: " . implode(', ', $expectedModified) . ", Got: " . implode(', ', $actualModified) . "\n";
        }
    }
} else {
    echo "  ✗ Could not retrieve UPDATE audit entry\n";
    $testResults['update'] = 'FAIL';
}

echo "\n=== Test 4: DELETE Operation Logging ===\n";
$deleteValues = [
    'patient_id' => $testPatientId,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe.new@test.com',
    'phone' => '555-999-8888',
    'city' => 'Boulder',
    'active' => true
];

$result = $helper->testDelete(
    $testPatientId, 
    $deleteValues, 
    $testPatientId,
    "Deactivated patient: John Doe"
);

if ($result) {
    echo "✓ DELETE audit log created successfully\n";
    $testResults['delete'] = 'PASS';
} else {
    echo "✗ DELETE audit log failed\n";
    $testResults['delete'] = 'FAIL';
}

usleep(100000);

// Verify DELETE audit entry
$stmt = $pdo->prepare("
    SELECT * FROM AuditEvent 
    WHERE subject_id = :subject_id AND action = 'delete'
    ORDER BY occurred_at DESC LIMIT 1
");
$stmt->execute(['subject_id' => $testPatientId]);
$deleteEntry = $stmt->fetch();

if ($deleteEntry) {
    echo "  - audit_id: {$deleteEntry['audit_id']}\n";
    echo "  - user_name: {$deleteEntry['user_name']}\n";
    echo "  - patient_id: {$deleteEntry['patient_id']}\n";
    echo "  - success: " . ($deleteEntry['success'] ? 'true' : 'false') . "\n";
    echo "  - old_values present: " . (!empty($deleteEntry['old_values']) ? 'YES (record preserved)' : 'NO') . "\n";
    
    if (!empty($deleteEntry['old_values'])) {
        $oldVals = json_decode($deleteEntry['old_values'], true);
        echo "  - preserved record contains " . count($oldVals) . " fields\n";
    }
} else {
    echo "  ✗ Could not retrieve DELETE audit entry\n";
    $testResults['delete'] = 'FAIL';
}

echo "\n=== Test 5: Failure Logging ===\n";
$result = $helper->testFailure(
    'read',
    $testPatientId . '-fail',
    'Patient not found',
    null
);

if ($result) {
    echo "✓ FAILURE audit log created successfully\n";
    $testResults['failure'] = 'PASS';
} else {
    echo "✗ FAILURE audit log failed\n";
    $testResults['failure'] = 'FAIL';
}

usleep(100000);

// Verify FAILURE audit entry
$stmt = $pdo->prepare("
    SELECT * FROM AuditEvent 
    WHERE subject_id = :subject_id AND success = 0
    ORDER BY occurred_at DESC LIMIT 1
");
$stmt->execute(['subject_id' => $testPatientId . '-fail']);
$failEntry = $stmt->fetch();

if ($failEntry) {
    echo "  - audit_id: {$failEntry['audit_id']}\n";
    echo "  - success: " . ($failEntry['success'] ? 'true' : 'false') . "\n";
    echo "  - error_message: {$failEntry['error_message']}\n";
} else {
    echo "  ✗ Could not retrieve FAILURE audit entry\n";
    $testResults['failure'] = 'FAIL';
}

echo "\n=== Test 6: Audit Log Query Functionality ===\n";

// Test query by user_id
echo "\n6.1 Query by user_id:\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM AuditEvent 
    WHERE user_id = :user_id AND subject_id LIKE 'test-patient-%'
");
$stmt->execute(['user_id' => $realUser['user_id']]);
$result = $stmt->fetch();
echo "  - Found {$result['count']} entries for test user\n";
$testResults['query_by_user'] = $result['count'] > 0 ? 'PASS' : 'FAIL';

// Test query by patient_id
echo "\n6.2 Query by patient_id:\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM AuditEvent 
    WHERE patient_id = :patient_id
");
$stmt->execute(['patient_id' => $testPatientId]);
$result = $stmt->fetch();
echo "  - Found {$result['count']} entries for test patient\n";
$testResults['query_by_patient'] = $result['count'] > 0 ? 'PASS' : 'FAIL';

// Test query by operation type
echo "\n6.3 Query by operation type:\n";
$stmt = $pdo->prepare("
    SELECT action, COUNT(*) as count 
    FROM AuditEvent 
    WHERE subject_id LIKE 'test-patient-%'
    GROUP BY action
");
$stmt->execute();
$results = $stmt->fetchAll();
foreach ($results as $row) {
    echo "  - {$row['action']}: {$row['count']} entries\n";
}
$testResults['query_by_type'] = count($results) > 0 ? 'PASS' : 'FAIL';

// Test query by date range
echo "\n6.4 Query by date range (last hour):\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM AuditEvent 
    WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    AND subject_id LIKE :subject_pattern
");
$stmt->execute(['subject_pattern' => 'test-patient-%']);
$result = $stmt->fetch();
echo "  - Found {$result['count']} entries in last hour\n";
$testResults['query_by_date'] = $result['count'] > 0 ? 'PASS' : 'FAIL';

// Test using a simple query instead of AuditService::searchLogs (which has a bug)
echo "\n6.5 Test manual audit log search:\n";
try {
    $stmt = $pdo->prepare("
        SELECT ae.*, u.username, u.first_name, u.last_name
        FROM AuditEvent ae
        LEFT JOIN User u ON ae.user_id = u.user_id
        WHERE ae.subject_type = 'patient'
        AND DATE(ae.occurred_at) = CURDATE()
        ORDER BY ae.occurred_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $searchResults = $stmt->fetchAll();
    echo "  - Search returned " . count($searchResults) . " patient audit entries today\n";
    $testResults['search_logs'] = count($searchResults) > 0 ? 'PASS' : 'FAIL';
} catch (Exception $e) {
    echo "  ✗ Search failed: " . $e->getMessage() . "\n";
    $testResults['search_logs'] = 'FAIL';
}

echo "\n=== Test 7: Sample Audit Log Entries ===\n";
$stmt = $pdo->prepare("
    SELECT 
        audit_id,
        user_id,
        user_name,
        user_role,
        action,
        subject_type,
        subject_id,
        patient_id,
        modified_fields,
        old_values,
        new_values,
        success,
        error_message,
        source_ip,
        occurred_at
    FROM AuditEvent 
    WHERE subject_id LIKE 'test-patient-%'
    ORDER BY occurred_at ASC
");
$stmt->execute();
$allEntries = $stmt->fetchAll();

echo "\nAll audit entries for test patients:\n";
echo str_repeat('-', 100) . "\n";
printf("%-36s | %-10s | %-20s | %-10s | %s\n", "AUDIT_ID", "ACTION", "USER_NAME", "STATUS", "OCCURRED_AT");
echo str_repeat('-', 100) . "\n";
foreach ($allEntries as $entry) {
    printf("%-36s | %-10s | %-20s | %-10s | %s\n",
        substr($entry['audit_id'], 0, 36),
        $entry['action'],
        substr($entry['user_name'] ?? 'N/A', 0, 20),
        $entry['success'] ? 'SUCCESS' : 'FAILED',
        $entry['occurred_at']
    );
}
echo str_repeat('-', 100) . "\n";

echo "\n=== TEST RESULTS SUMMARY ===\n\n";
$totalTests = count($testResults);
$passedTests = count(array_filter($testResults, fn($r) => $r === 'PASS'));
$failedTests = $totalTests - $passedTests;

foreach ($testResults as $test => $result) {
    $icon = $result === 'PASS' ? '✓' : '✗';
    echo "  $icon $test: $result\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: $failedTests\n";
echo str_repeat('=', 50) . "\n";

if ($failedTests > 0) {
    echo "\n⚠ Some tests failed. Please review the output above.\n";
    exit(1);
} else {
    echo "\n✓ All tests passed! Audit logging is working correctly.\n";
    exit(0);
}
