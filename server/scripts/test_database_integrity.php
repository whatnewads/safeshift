<?php
/**
 * Database Integrity Test Script
 * 
 * Tests for data consistency, orphaned records, required field validation,
 * data type consistency, enum validation, and duplicate detection.
 * 
 * Part of MVP validation testing before production deployment.
 * 
 * Usage: php scripts/test_database_integrity.php [--verbose] [--fix]
 * 
 * @package SafeShift\Scripts
 * @version 1.0.0
 */

declare(strict_types=1);

// Bootstrap
require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Database Integrity Tester
 */
class DatabaseIntegrityTester
{
    private PDO $pdo;
    private array $results = [];
    private bool $verbose = false;
    private bool $fixMode = false;
    private int $errorCount = 0;
    private int $warningCount = 0;
    private int $passCount = 0;

    public function __construct(PDO $pdo, bool $verbose = false, bool $fixMode = false)
    {
        $this->pdo = $pdo;
        $this->verbose = $verbose;
        $this->fixMode = $fixMode;
    }

    /**
     * Run all integrity tests
     */
    public function runAllTests(): array
    {
        $this->log("=== Database Integrity Test Suite ===", 'header');
        $this->log("Started: " . date('Y-m-d H:i:s'), 'info');
        $this->log("");

        // 1. Orphaned Records Tests
        $this->testOrphanedRecords();
        
        // 2. Required Fields Tests
        $this->testRequiredFields();
        
        // 3. Data Type Consistency Tests
        $this->testDataTypeConsistency();
        
        // 4. Enum Validation Tests
        $this->testEnumValues();
        
        // 5. Duplicate Detection Tests
        $this->testDuplicates();
        
        // 6. Foreign Key Integrity Tests
        $this->testForeignKeyIntegrity();
        
        // 7. Timestamp Consistency Tests
        $this->testTimestampConsistency();

        // Summary
        $this->printSummary();

        return $this->results;
    }

    // =========================================================================
    // 1. ORPHANED RECORDS TESTS
    // =========================================================================

    private function testOrphanedRecords(): void
    {
        $this->log("=== Testing Orphaned Records ===", 'section');

        // Encounters without valid patients
        $this->checkOrphanedRecords(
            'Encounters with invalid patient_id',
            'encounters',
            'patient_id',
            'patients',
            'patient_id',
            'deleted_at IS NULL'
        );

        // DOT tests without valid patients
        $this->checkOrphanedRecords(
            'DOT tests with invalid patient_id',
            'dot_tests',
            'patient_id',
            'patients',
            'patient_id'
        );

        // DOT tests without valid encounters
        $this->checkOrphanedRecords(
            'DOT tests with invalid encounter_id',
            'dot_tests',
            'encounter_id',
            'encounters',
            'encounter_id'
        );

        // Encounter orders without valid encounters
        $this->checkOrphanedRecords(
            'Encounter orders with invalid encounter_id',
            'encounter_orders',
            'encounter_id',
            'encounters',
            'encounter_id'
        );

        // QA review queue without valid encounters
        $this->checkOrphanedRecords(
            'QA reviews with invalid encounter_id',
            'qa_review_queue',
            'encounter_id',
            'encounters',
            'encounter_id'
        );

        // User roles without valid users
        $this->checkOrphanedRecords(
            'User roles with invalid user_id',
            'userrole',
            'user_id',
            'user',
            'user_id'
        );

        // User roles without valid roles
        $this->checkOrphanedRecords(
            'User roles with invalid role_id',
            'userrole',
            'role_id',
            'role',
            'role_id'
        );

        // Notifications without valid users
        $this->checkOrphanedRecords(
            'Notifications with invalid user_id',
            'notifications',
            'user_id',
            'user',
            'user_id'
        );

        // Chain of custody without valid tests
        $this->checkOrphanedRecords(
            'Chain of custody with invalid test_id',
            'chainofcustodyform',
            'test_id',
            'dot_tests',
            'test_id'
        );

        // Staff training records without valid users
        $this->checkOrphanedRecords(
            'Training records with invalid user_id',
            'staff_training_records',
            'user_id',
            'user',
            'user_id'
        );

        $this->log("");
    }

    private function checkOrphanedRecords(
        string $testName,
        string $childTable,
        string $childColumn,
        string $parentTable,
        string $parentColumn,
        ?string $additionalCondition = null
    ): void {
        try {
            $condition = $additionalCondition ? "AND c.{$additionalCondition}" : "";
            
            $sql = "SELECT COUNT(*) as orphan_count
                    FROM `{$childTable}` c
                    LEFT JOIN `{$parentTable}` p ON c.`{$childColumn}` = p.`{$parentColumn}`
                    WHERE p.`{$parentColumn}` IS NULL
                    AND c.`{$childColumn}` IS NOT NULL
                    {$condition}";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['orphan_count'] ?? 0);

            if ($count > 0) {
                $this->log("✗ {$testName}: {$count} orphaned records found", 'error');
                $this->results['orphaned_records'][$testName] = [
                    'status' => 'FAIL',
                    'count' => $count,
                    'child_table' => $childTable,
                    'parent_table' => $parentTable,
                ];
                $this->errorCount++;
            } else {
                $this->log("✓ {$testName}: No orphaned records", 'pass');
                $this->results['orphaned_records'][$testName] = [
                    'status' => 'PASS',
                    'count' => 0,
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$testName}: Table may not exist - {$e->getMessage()}", 'warning');
            $this->results['orphaned_records'][$testName] = [
                'status' => 'SKIP',
                'error' => $e->getMessage(),
            ];
            $this->warningCount++;
        }
    }

    // =========================================================================
    // 2. REQUIRED FIELDS TESTS
    // =========================================================================

    private function testRequiredFields(): void
    {
        $this->log("=== Testing Required Fields ===", 'section');

        // Patients required fields
        $this->checkRequiredField('patients', 'patient_id', 'Patient ID');
        $this->checkRequiredField('patients', 'legal_first_name', 'Patient First Name', "deleted_at IS NULL");
        $this->checkRequiredField('patients', 'legal_last_name', 'Patient Last Name', "deleted_at IS NULL");
        $this->checkRequiredField('patients', 'dob', 'Patient DOB', "deleted_at IS NULL");

        // Users required fields
        $this->checkRequiredField('user', 'user_id', 'User ID');
        $this->checkRequiredField('user', 'username', 'Username');
        $this->checkRequiredField('user', 'email', 'User Email');
        $this->checkRequiredField('user', 'password', 'User Password Hash');

        // Encounters required fields
        $this->checkRequiredField('encounters', 'encounter_id', 'Encounter ID');
        $this->checkRequiredField('encounters', 'patient_id', 'Encounter Patient ID', "deleted_at IS NULL");
        $this->checkRequiredField('encounters', 'status', 'Encounter Status', "deleted_at IS NULL");

        // DOT Tests required fields
        $this->checkRequiredField('dot_tests', 'test_id', 'DOT Test ID');
        $this->checkRequiredField('dot_tests', 'patient_id', 'DOT Test Patient ID');
        $this->checkRequiredField('dot_tests', 'modality', 'DOT Test Modality');

        // Roles required fields
        $this->checkRequiredField('role', 'role_id', 'Role ID');
        $this->checkRequiredField('role', 'name', 'Role Name');
        $this->checkRequiredField('role', 'slug', 'Role Slug');

        $this->log("");
    }

    private function checkRequiredField(
        string $table,
        string $column,
        string $fieldName,
        ?string $condition = null
    ): void {
        try {
            $whereClause = $condition ? "WHERE ({$condition}) AND" : "WHERE";
            
            $sql = "SELECT COUNT(*) as null_count
                    FROM `{$table}`
                    {$whereClause} (`{$column}` IS NULL OR `{$column}` = '')";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['null_count'] ?? 0);

            if ($count > 0) {
                $this->log("✗ {$fieldName} ({$table}.{$column}): {$count} NULL/empty values", 'error');
                $this->results['required_fields']["{$table}.{$column}"] = [
                    'status' => 'FAIL',
                    'count' => $count,
                    'field_name' => $fieldName,
                ];
                $this->errorCount++;
            } else {
                $this->log("✓ {$fieldName} ({$table}.{$column}): All values populated", 'pass');
                $this->results['required_fields']["{$table}.{$column}"] = [
                    'status' => 'PASS',
                    'count' => 0,
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$fieldName}: Column may not exist - {$e->getMessage()}", 'warning');
            $this->results['required_fields']["{$table}.{$column}"] = [
                'status' => 'SKIP',
                'error' => $e->getMessage(),
            ];
            $this->warningCount++;
        }
    }

    // =========================================================================
    // 3. DATA TYPE CONSISTENCY TESTS
    // =========================================================================

    private function testDataTypeConsistency(): void
    {
        $this->log("=== Testing Data Type Consistency ===", 'section');

        // Check UUID format for primary keys
        $this->checkUuidFormat('patients', 'patient_id', 'Patient IDs');
        $this->checkUuidFormat('encounters', 'encounter_id', 'Encounter IDs');
        $this->checkUuidFormat('user', 'user_id', 'User IDs');
        $this->checkUuidFormat('dot_tests', 'test_id', 'DOT Test IDs');

        // Check email format
        $this->checkEmailFormat('patients', 'email', 'Patient Emails');
        $this->checkEmailFormat('user', 'email', 'User Emails');

        // Check phone number format
        $this->checkPhoneFormat('patients', 'phone', 'Patient Phone Numbers');

        // Check date formats
        $this->checkDateFormat('patients', 'dob', 'Patient DOB');
        $this->checkDateFormat('encounters', 'occurred_on', 'Encounter Occurred Date');

        // Check JSON fields
        $this->checkJsonFormat('dot_tests', 'results', 'DOT Test Results');

        $this->log("");
    }

    private function checkUuidFormat(string $table, string $column, string $fieldName): void
    {
        try {
            // Check for invalid UUID format (should be 36 chars with hyphens or 32 chars without)
            $sql = "SELECT COUNT(*) as invalid_count
                    FROM `{$table}`
                    WHERE `{$column}` IS NOT NULL
                    AND `{$column}` NOT REGEXP '^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$'
                    AND `{$column}` NOT REGEXP '^[a-fA-F0-9]{32}$'
                    AND LENGTH(`{$column}`) > 0";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['invalid_count'] ?? 0);

            if ($count > 0) {
                $this->log("⚠ {$fieldName} ({$table}.{$column}): {$count} non-UUID format values", 'warning');
                $this->results['data_types']["{$table}.{$column}"] = [
                    'status' => 'WARNING',
                    'count' => $count,
                    'issue' => 'Non-UUID format detected',
                ];
                $this->warningCount++;
            } else {
                $this->log("✓ {$fieldName}: All UUIDs valid format", 'pass');
                $this->results['data_types']["{$table}.{$column}"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$fieldName}: Check failed - {$e->getMessage()}", 'warning');
            $this->results['data_types']["{$table}.{$column}"] = [
                'status' => 'SKIP',
                'error' => $e->getMessage(),
            ];
            $this->warningCount++;
        }
    }

    private function checkEmailFormat(string $table, string $column, string $fieldName): void
    {
        try {
            $sql = "SELECT COUNT(*) as invalid_count
                    FROM `{$table}`
                    WHERE `{$column}` IS NOT NULL
                    AND `{$column}` != ''
                    AND `{$column}` NOT REGEXP '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['invalid_count'] ?? 0);

            if ($count > 0) {
                $this->log("⚠ {$fieldName} ({$table}.{$column}): {$count} invalid email formats", 'warning');
                $this->results['data_types']["{$table}.{$column}_email"] = [
                    'status' => 'WARNING',
                    'count' => $count,
                    'issue' => 'Invalid email format',
                ];
                $this->warningCount++;
            } else {
                $this->log("✓ {$fieldName}: All emails valid format", 'pass');
                $this->results['data_types']["{$table}.{$column}_email"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$fieldName}: Check failed - {$e->getMessage()}", 'warning');
            $this->warningCount++;
        }
    }

    private function checkPhoneFormat(string $table, string $column, string $fieldName): void
    {
        try {
            // Allow various phone formats: (xxx) xxx-xxxx, xxx-xxx-xxxx, xxxxxxxxxx, +1xxxxxxxxxx
            $sql = "SELECT COUNT(*) as invalid_count
                    FROM `{$table}`
                    WHERE `{$column}` IS NOT NULL
                    AND `{$column}` != ''
                    AND LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`{$column}`, '-', ''), '(', ''), ')', ''), ' ', ''), '+', '')) < 10";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['invalid_count'] ?? 0);

            if ($count > 0) {
                $this->log("⚠ {$fieldName}: {$count} potentially invalid phone numbers (< 10 digits)", 'warning');
                $this->results['data_types']["{$table}.{$column}_phone"] = [
                    'status' => 'WARNING',
                    'count' => $count,
                ];
                $this->warningCount++;
            } else {
                $this->log("✓ {$fieldName}: All phone numbers appear valid", 'pass');
                $this->results['data_types']["{$table}.{$column}_phone"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$fieldName}: Check failed - {$e->getMessage()}", 'warning');
            $this->warningCount++;
        }
    }

    private function checkDateFormat(string $table, string $column, string $fieldName): void
    {
        try {
            // Check for dates that are clearly invalid (year 0000, etc.)
            $sql = "SELECT COUNT(*) as invalid_count
                    FROM `{$table}`
                    WHERE `{$column}` IS NOT NULL
                    AND (`{$column}` = '0000-00-00' 
                         OR `{$column}` = '0000-00-00 00:00:00'
                         OR YEAR(`{$column}`) < 1900
                         OR YEAR(`{$column}`) > 2100)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['invalid_count'] ?? 0);

            if ($count > 0) {
                $this->log("✗ {$fieldName} ({$table}.{$column}): {$count} invalid dates", 'error');
                $this->results['data_types']["{$table}.{$column}_date"] = [
                    'status' => 'FAIL',
                    'count' => $count,
                ];
                $this->errorCount++;
            } else {
                $this->log("✓ {$fieldName}: All dates valid", 'pass');
                $this->results['data_types']["{$table}.{$column}_date"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$fieldName}: Check failed - {$e->getMessage()}", 'warning');
            $this->warningCount++;
        }
    }

    private function checkJsonFormat(string $table, string $column, string $fieldName): void
    {
        try {
            // Check for invalid JSON in JSON columns
            $sql = "SELECT COUNT(*) as invalid_count
                    FROM `{$table}`
                    WHERE `{$column}` IS NOT NULL
                    AND `{$column}` != ''
                    AND JSON_VALID(`{$column}`) = 0";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['invalid_count'] ?? 0);

            if ($count > 0) {
                $this->log("✗ {$fieldName} ({$table}.{$column}): {$count} invalid JSON values", 'error');
                $this->results['data_types']["{$table}.{$column}_json"] = [
                    'status' => 'FAIL',
                    'count' => $count,
                ];
                $this->errorCount++;
            } else {
                $this->log("✓ {$fieldName}: All JSON valid", 'pass');
                $this->results['data_types']["{$table}.{$column}_json"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$fieldName}: Check failed - {$e->getMessage()}", 'warning');
            $this->warningCount++;
        }
    }

    // =========================================================================
    // 4. ENUM VALIDATION TESTS
    // =========================================================================

    private function testEnumValues(): void
    {
        $this->log("=== Testing Enum Values ===", 'section');

        // Patient gender enum
        $this->checkEnumValues(
            'patients',
            'sex_assigned_at_birth',
            ['M', 'F', 'O', 'U', 'male', 'female', 'other', 'unknown'],
            'Patient Gender'
        );

        // Encounter status enum
        $this->checkEnumValues(
            'encounters',
            'status',
            ['planned', 'arrived', 'in_progress', 'completed', 'pending_review', 'cancelled', 'no_show'],
            'Encounter Status'
        );

        // Encounter type enum
        $this->checkEnumValues(
            'encounters',
            'encounter_type',
            ['clinic', 'ems', 'telemedicine', 'other', 'injury', 'illness', 'exam', 'surveillance', 'dot'],
            'Encounter Type'
        );

        // DOT test status enum
        $this->checkEnumValues(
            'dot_tests',
            'status',
            ['pending', 'in_progress', 'completed', 'negative', 'positive', 'cancelled', 'invalid'],
            'DOT Test Status'
        );

        // DOT test modality enum
        $this->checkEnumValues(
            'dot_tests',
            'modality',
            ['drug_test', 'alcohol_test', 'breath_alcohol', 'urine', 'hair', 'oral_fluid'],
            'DOT Test Modality'
        );

        // Order status enum
        $this->checkEnumValues(
            'encounter_orders',
            'status',
            ['pending', 'signed', 'completed', 'cancelled', 'in_progress'],
            'Order Status'
        );

        // User status
        $this->checkEnumValues(
            'user',
            'status',
            ['active', 'inactive', 'suspended', 'pending', 'locked'],
            'User Status'
        );

        $this->log("");
    }

    private function checkEnumValues(
        string $table,
        string $column,
        array $allowedValues,
        string $fieldName
    ): void {
        try {
            $placeholders = implode(',', array_fill(0, count($allowedValues), '?'));
            
            $sql = "SELECT DISTINCT `{$column}` as value, COUNT(*) as count
                    FROM `{$table}`
                    WHERE `{$column}` IS NOT NULL
                    AND `{$column}` != ''
                    AND LOWER(`{$column}`) NOT IN ({$placeholders})
                    GROUP BY `{$column}`";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_map('strtolower', $allowedValues));
            $invalidValues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($invalidValues) > 0) {
                $totalCount = array_sum(array_column($invalidValues, 'count'));
                $values = array_column($invalidValues, 'value');
                $this->log("✗ {$fieldName} ({$table}.{$column}): {$totalCount} invalid values: " . implode(', ', array_slice($values, 0, 5)), 'error');
                $this->results['enum_values']["{$table}.{$column}"] = [
                    'status' => 'FAIL',
                    'count' => $totalCount,
                    'invalid_values' => $values,
                    'allowed_values' => $allowedValues,
                ];
                $this->errorCount++;
            } else {
                $this->log("✓ {$fieldName}: All values within allowed range", 'pass');
                $this->results['enum_values']["{$table}.{$column}"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$fieldName}: Check failed - {$e->getMessage()}", 'warning');
            $this->results['enum_values']["{$table}.{$column}"] = [
                'status' => 'SKIP',
                'error' => $e->getMessage(),
            ];
            $this->warningCount++;
        }
    }

    // =========================================================================
    // 5. DUPLICATE DETECTION TESTS
    // =========================================================================

    private function testDuplicates(): void
    {
        $this->log("=== Testing for Duplicates ===", 'section');

        // Duplicate patient MRNs
        $this->checkDuplicates(
            'patients',
            ['mrn'],
            'Patient MRN',
            "deleted_at IS NULL AND mrn IS NOT NULL AND mrn != ''"
        );

        // Duplicate user emails
        $this->checkDuplicates(
            'user',
            ['email'],
            'User Email',
            "email IS NOT NULL AND email != ''"
        );

        // Duplicate usernames
        $this->checkDuplicates(
            'user',
            ['username'],
            'Username',
            "username IS NOT NULL AND username != ''"
        );

        // Duplicate role slugs
        $this->checkDuplicates(
            'role',
            ['slug'],
            'Role Slug',
            "slug IS NOT NULL AND slug != ''"
        );

        // Duplicate user-role assignments
        $this->checkDuplicates(
            'userrole',
            ['user_id', 'role_id'],
            'User-Role Assignment'
        );

        // Duplicate patient-encounter on same day (potential data entry error)
        $this->checkDuplicates(
            'encounters',
            ['patient_id', 'DATE(occurred_on)'],
            'Patient Encounter Same Day',
            "deleted_at IS NULL AND occurred_on IS NOT NULL"
        );

        $this->log("");
    }

    private function checkDuplicates(
        string $table,
        array $columns,
        string $fieldName,
        ?string $condition = null
    ): void {
        try {
            $columnList = implode(', ', array_map(function($col) {
                return strpos($col, '(') !== false ? $col : "`{$col}`";
            }, $columns));
            
            $whereClause = $condition ? "WHERE {$condition}" : "";
            
            $sql = "SELECT {$columnList}, COUNT(*) as dup_count
                    FROM `{$table}`
                    {$whereClause}
                    GROUP BY {$columnList}
                    HAVING COUNT(*) > 1
                    LIMIT 10";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($duplicates) > 0) {
                $totalDups = array_sum(array_column($duplicates, 'dup_count'));
                $this->log("⚠ {$fieldName}: {$totalDups} duplicate entries found in " . count($duplicates) . " groups", 'warning');
                $this->results['duplicates']["{$table}." . implode('+', $columns)] = [
                    'status' => 'WARNING',
                    'duplicate_groups' => count($duplicates),
                    'total_duplicates' => $totalDups,
                    'samples' => array_slice($duplicates, 0, 5),
                ];
                $this->warningCount++;
            } else {
                $this->log("✓ {$fieldName}: No duplicates found", 'pass');
                $this->results['duplicates']["{$table}." . implode('+', $columns)] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$fieldName}: Check failed - {$e->getMessage()}", 'warning');
            $this->results['duplicates']["{$table}." . implode('+', $columns)] = [
                'status' => 'SKIP',
                'error' => $e->getMessage(),
            ];
            $this->warningCount++;
        }
    }

    // =========================================================================
    // 6. FOREIGN KEY INTEGRITY TESTS
    // =========================================================================

    private function testForeignKeyIntegrity(): void
    {
        $this->log("=== Testing Foreign Key Integrity ===", 'section');

        // Test encounters.site_id references establishment
        $this->checkForeignKeyIntegrity(
            'encounters',
            'site_id',
            'establishment',
            'Id',
            'Encounter Site'
        );

        // Test encounters.npi_provider references user
        $this->checkForeignKeyIntegrity(
            'encounters',
            'npi_provider',
            'user',
            'user_id',
            'Encounter Provider'
        );

        // Test 300_log references patients
        $this->checkForeignKeyIntegrity(
            '300_log',
            'patient_id',
            'patients',
            'patient_id',
            'OSHA 300 Log Patient'
        );

        // Test staff_training_records.requirement_id references training_requirements
        $this->checkForeignKeyIntegrity(
            'staff_training_records',
            'requirement_id',
            'training_requirements',
            'requirement_id',
            'Training Record Requirement'
        );

        $this->log("");
    }

    private function checkForeignKeyIntegrity(
        string $childTable,
        string $childColumn,
        string $parentTable,
        string $parentColumn,
        string $relationName
    ): void {
        try {
            $sql = "SELECT COUNT(*) as invalid_count
                    FROM `{$childTable}` c
                    LEFT JOIN `{$parentTable}` p ON c.`{$childColumn}` = p.`{$parentColumn}`
                    WHERE c.`{$childColumn}` IS NOT NULL
                    AND p.`{$parentColumn}` IS NULL";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['invalid_count'] ?? 0);

            if ($count > 0) {
                $this->log("✗ {$relationName}: {$count} broken foreign key references", 'error');
                $this->results['foreign_keys']["{$childTable}.{$childColumn}"] = [
                    'status' => 'FAIL',
                    'count' => $count,
                    'parent_table' => $parentTable,
                ];
                $this->errorCount++;
            } else {
                $this->log("✓ {$relationName}: All references valid", 'pass');
                $this->results['foreign_keys']["{$childTable}.{$childColumn}"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$relationName}: Check failed - {$e->getMessage()}", 'warning');
            $this->results['foreign_keys']["{$childTable}.{$childColumn}"] = [
                'status' => 'SKIP',
                'error' => $e->getMessage(),
            ];
            $this->warningCount++;
        }
    }

    // =========================================================================
    // 7. TIMESTAMP CONSISTENCY TESTS
    // =========================================================================

    private function testTimestampConsistency(): void
    {
        $this->log("=== Testing Timestamp Consistency ===", 'section');

        // Check created_at <= updated_at
        $this->checkTimestampOrder('patients', 'created_at', 'updated_at', 'Patient Timestamps');
        $this->checkTimestampOrder('encounters', 'created_at', 'modified_at', 'Encounter Timestamps');
        $this->checkTimestampOrder('user', 'created_at', 'updated_at', 'User Timestamps');

        // Check arrived_on <= discharged_on for encounters
        $this->checkTimestampOrder('encounters', 'arrived_on', 'discharged_on', 'Encounter Arrival/Discharge');

        // Check for future timestamps
        $this->checkFutureTimestamps('patients', 'created_at', 'Patient Created');
        $this->checkFutureTimestamps('encounters', 'created_at', 'Encounter Created');
        $this->checkFutureTimestamps('encounters', 'occurred_on', 'Encounter Occurred');

        $this->log("");
    }

    private function checkTimestampOrder(
        string $table,
        string $earlierColumn,
        string $laterColumn,
        string $testName
    ): void {
        try {
            $sql = "SELECT COUNT(*) as invalid_count
                    FROM `{$table}`
                    WHERE `{$earlierColumn}` IS NOT NULL
                    AND `{$laterColumn}` IS NOT NULL
                    AND `{$earlierColumn}` > `{$laterColumn}`";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['invalid_count'] ?? 0);

            if ($count > 0) {
                $this->log("✗ {$testName}: {$count} records with {$earlierColumn} > {$laterColumn}", 'error');
                $this->results['timestamps']["{$table}.{$earlierColumn}_vs_{$laterColumn}"] = [
                    'status' => 'FAIL',
                    'count' => $count,
                ];
                $this->errorCount++;
            } else {
                $this->log("✓ {$testName}: Timestamp order consistent", 'pass');
                $this->results['timestamps']["{$table}.{$earlierColumn}_vs_{$laterColumn}"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$testName}: Check failed - {$e->getMessage()}", 'warning');
            $this->warningCount++;
        }
    }

    private function checkFutureTimestamps(string $table, string $column, string $testName): void
    {
        try {
            $sql = "SELECT COUNT(*) as future_count
                    FROM `{$table}`
                    WHERE `{$column}` > NOW() + INTERVAL 1 DAY";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = (int)($result['future_count'] ?? 0);

            if ($count > 0) {
                $this->log("⚠ {$testName}: {$count} records with future timestamps", 'warning');
                $this->results['timestamps']["{$table}.{$column}_future"] = [
                    'status' => 'WARNING',
                    'count' => $count,
                ];
                $this->warningCount++;
            } else {
                $this->log("✓ {$testName}: No future timestamps", 'pass');
                $this->results['timestamps']["{$table}.{$column}_future"] = [
                    'status' => 'PASS',
                ];
                $this->passCount++;
            }
        } catch (PDOException $e) {
            $this->log("⚠ {$testName}: Check failed - {$e->getMessage()}", 'warning');
            $this->warningCount++;
        }
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    private function log(string $message, string $type = 'info'): void
    {
        $colors = [
            'header' => "\033[1;36m",
            'section' => "\033[1;33m",
            'pass' => "\033[32m",
            'error' => "\033[31m",
            'warning' => "\033[33m",
            'info' => "\033[0m",
        ];
        $reset = "\033[0m";

        if (php_sapi_name() === 'cli') {
            echo ($colors[$type] ?? '') . $message . $reset . PHP_EOL;
        } else {
            echo $message . "<br>\n";
        }
    }

    private function printSummary(): void
    {
        $this->log("", 'info');
        $this->log("=== TEST SUMMARY ===", 'header');
        $this->log("Passed: {$this->passCount}", 'pass');
        $this->log("Warnings: {$this->warningCount}", 'warning');
        $this->log("Errors: {$this->errorCount}", 'error');
        $this->log("");
        
        $total = $this->passCount + $this->warningCount + $this->errorCount;
        $passRate = $total > 0 ? round(($this->passCount / $total) * 100, 1) : 0;
        
        if ($this->errorCount === 0) {
            $this->log("✓ DATABASE INTEGRITY: PASS ({$passRate}% pass rate)", 'pass');
        } else {
            $this->log("✗ DATABASE INTEGRITY: FAIL ({$this->errorCount} critical issues)", 'error');
        }
        
        $this->results['summary'] = [
            'passed' => $this->passCount,
            'warnings' => $this->warningCount,
            'errors' => $this->errorCount,
            'total' => $total,
            'pass_rate' => $passRate,
            'status' => $this->errorCount === 0 ? 'PASS' : 'FAIL',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function exportResults(string $filename): void
    {
        file_put_contents($filename, json_encode($this->results, JSON_PRETTY_PRINT));
        $this->log("Results exported to: {$filename}", 'info');
    }
}

// =========================================================================
// MAIN EXECUTION
// =========================================================================

// Parse command line arguments
$verbose = in_array('--verbose', $argv ?? []) || in_array('-v', $argv ?? []);
$fixMode = in_array('--fix', $argv ?? []);

try {
    // Get database connection
    $config = require __DIR__ . '/../includes/config.php';
    
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['db']['host'] ?? 'localhost',
        $config['db']['database'] ?? 'safeshift_ehr'
    );
    
    $pdo = new PDO(
        $dsn,
        $config['db']['username'] ?? 'root',
        $config['db']['password'] ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Run tests
    $tester = new DatabaseIntegrityTester($pdo, $verbose, $fixMode);
    $results = $tester->runAllTests();

    // Export results to JSON
    $resultsFile = __DIR__ . '/../logs/database_integrity_results_' . date('Y-m-d_His') . '.json';
    $tester->exportResults($resultsFile);

    // Exit with appropriate code
    exit($results['summary']['errors'] > 0 ? 1 : 0);

} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
