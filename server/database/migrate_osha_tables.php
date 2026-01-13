<?php
/**
 * OSHA Tables Migration Script
 * 
 * Adds OSHA-compliant columns to the 301, 300_log, and 300a tables
 * Based on the migration plan in docs/OSHA_TABLE_MIGRATION_PLAN.md
 * 
 * Usage: php database/migrate_osha_tables.php
 * 
 * @version 1.0.0
 * @date 2026-01-12
 */

// Ensure this script is run from command line only
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load configuration
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/db.php';

use function App\db\pdo;

/**
 * OSHA Migration Class
 */
class OshaMigration
{
    private $pdo;
    private $verbose = true;
    private $dryRun = false;
    private $errors = [];
    private $successful = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Output a message to console
     */
    private function output(string $message, string $type = 'info'): void
    {
        if (!$this->verbose) return;

        $colors = [
            'info' => "\033[0;36m",    // Cyan
            'success' => "\033[0;32m", // Green
            'warning' => "\033[0;33m", // Yellow
            'error' => "\033[0;31m",   // Red
            'header' => "\033[1;35m",  // Bold Magenta
            'reset' => "\033[0m"
        ];

        $prefix = match($type) {
            'success' => '[✓] ',
            'error' => '[✗] ',
            'warning' => '[!] ',
            'header' => '=== ',
            default => '[i] '
        };

        echo $colors[$type] . $prefix . $message . $colors['reset'] . "\n";
    }

    /**
     * Check if a column exists in a table
     */
    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = :table 
            AND COLUMN_NAME = :column
        ");
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = :table 
            AND INDEX_NAME = :index_name
        ");
        $stmt->execute(['table' => $table, 'index_name' => $indexName]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Add a column if it doesn't exist
     */
    private function addColumnIfNotExists(string $table, string $column, string $definition): bool
    {
        if ($this->columnExists($table, $column)) {
            $this->output("Column `{$column}` already exists in `{$table}` - skipping", 'warning');
            return true;
        }

        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
        
        if ($this->dryRun) {
            $this->output("DRY RUN: {$sql}", 'info');
            return true;
        }

        try {
            $this->pdo->exec($sql);
            $this->output("Added column `{$column}` to `{$table}`", 'success');
            $this->successful[] = "Added `{$table}`.`{$column}`";
            return true;
        } catch (PDOException $e) {
            $this->output("Failed to add column `{$column}` to `{$table}`: " . $e->getMessage(), 'error');
            $this->errors[] = "Failed: `{$table}`.`{$column}` - " . $e->getMessage();
            return false;
        }
    }

    /**
     * Create an index if it doesn't exist
     */
    private function createIndexIfNotExists(string $table, string $indexName, string $columns, bool $unique = false): bool
    {
        if ($this->indexExists($table, $indexName)) {
            $this->output("Index `{$indexName}` already exists on `{$table}` - skipping", 'warning');
            return true;
        }

        $indexType = $unique ? 'UNIQUE INDEX' : 'INDEX';
        $sql = "CREATE {$indexType} `{$indexName}` ON `{$table}` ({$columns})";

        if ($this->dryRun) {
            $this->output("DRY RUN: {$sql}", 'info');
            return true;
        }

        try {
            $this->pdo->exec($sql);
            $this->output("Created index `{$indexName}` on `{$table}`", 'success');
            $this->successful[] = "Created index `{$indexName}` on `{$table}`";
            return true;
        } catch (PDOException $e) {
            $this->output("Failed to create index `{$indexName}` on `{$table}`: " . $e->getMessage(), 'error');
            $this->errors[] = "Failed index: `{$indexName}` on `{$table}` - " . $e->getMessage();
            return false;
        }
    }

    /**
     * Get row count for a table
     */
    private function getRowCount(string $table): int
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Get column count for a table
     */
    private function getColumnCount(string $table): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = :table
        ");
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if table exists
     */
    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = :table
        ");
        $stmt->execute(['table' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Migrate 300_log table (parent table - migrate first)
     */
    public function migrate300Log(): bool
    {
        $this->output("Migrating 300_log table...", 'header');

        if (!$this->tableExists('300_log')) {
            $this->output("Table `300_log` does not exist!", 'error');
            $this->errors[] = "Table `300_log` does not exist";
            return false;
        }

        $columnsBefore = $this->getColumnCount('300_log');
        $rowCount = $this->getRowCount('300_log');
        $this->output("Current state: {$columnsBefore} columns, {$rowCount} rows", 'info');

        // Note: MySQL DDL (ALTER TABLE) causes implicit commit, so transactions are not effective
        // We proceed without explicit transaction for DDL operations

        try {
            // Add columns in order (after specified column where applicable)
            $columns = [
                // OSHA case number
                ['case_number', "VARCHAR(20) DEFAULT NULL COMMENT 'OSHA case number format: YYYY-XXXXXX'"],
                // Clinical references
                ['encounter_id', "CHAR(36) DEFAULT NULL COMMENT 'Reference to clinical encounter'"],
                ['patient_id', "CHAR(36) DEFAULT NULL COMMENT 'Reference to patient record'"],
                ['establishment_id', "CHAR(36) DEFAULT NULL COMMENT 'Reference to specific work location'"],
                // Employee information
                ['employee_name', "VARCHAR(255) DEFAULT NULL COMMENT 'Employee name - may be privacy masked'"],
                ['job_title', "VARCHAR(255) DEFAULT NULL COMMENT 'Employee job title at time of incident'"],
                // Incident details
                ['date_of_injury_illness', "DATE DEFAULT NULL COMMENT 'Date when injury/illness occurred'"],
                ['time_of_event', "TIME DEFAULT NULL COMMENT 'Time when incident occurred'"],
                ['location_of_incident', "VARCHAR(255) DEFAULT NULL COMMENT 'Where on premises the incident occurred'"],
                ['description_of_incident', "TEXT DEFAULT NULL COMMENT 'Detailed description of what happened'"],
                // Injury classification
                ['injury_illness_category_id', "INT UNSIGNED DEFAULT NULL COMMENT 'Reference to injury_illness_categories lookup table'"],
                ['body_part_affected', "VARCHAR(100) DEFAULT NULL COMMENT 'Body part that was injured'"],
                ['object_substance', "VARCHAR(255) DEFAULT NULL COMMENT 'Object or substance that directly caused harm'"],
                // Outcome details
                ['death_date', "DATE DEFAULT NULL COMMENT 'Date of death if death=1'"],
                ['days_away_from_work', "INT DEFAULT 0 COMMENT 'Total number of days away from work'"],
                ['days_restricted_duty', "INT DEFAULT 0 COMMENT 'Total days on restricted duty'"],
                ['days_job_transfer', "INT DEFAULT 0 COMMENT 'Total days of job transfer'"],
                // Medical treatment
                ['medical_treatment_beyond_first_aid', "BOOLEAN DEFAULT FALSE COMMENT 'Treatment beyond first aid was required'"],
                // Privacy case fields
                ['is_privacy_case', "BOOLEAN DEFAULT FALSE COMMENT 'OSHA privacy case flag'"],
                ['privacy_case_reason', "TEXT DEFAULT NULL COMMENT 'Reason for privacy case designation'"],
                // Case status
                ['case_status', "ENUM('open', 'closed', 'amended') DEFAULT 'open' COMMENT 'Current status of the case'"],
                // Audit tracking
                ['created_by', "INT UNSIGNED DEFAULT NULL COMMENT 'User ID who created the record'"],
                ['updated_by', "INT UNSIGNED DEFAULT NULL COMMENT 'User ID who last updated the record'"],
            ];

            foreach ($columns as [$column, $definition]) {
                $this->addColumnIfNotExists('300_log', $column, $definition);
            }

            // Create indexes
            $indexes = [
                ['idx_300log_case_number', '`case_number`', false],
                ['idx_300log_date', '`date_of_injury_illness`', false],
                ['idx_300log_employer', '`employer_id`', false],
                ['idx_300log_establishment', '`establishment_id`', false],
                ['idx_300log_patient', '`patient_id`', false],
                ['idx_300log_status', '`case_status`', false],
                ['idx_300log_category', '`injury_illness_category_id`', false],
            ];

            foreach ($indexes as [$indexName, $columns, $unique]) {
                $this->createIndexIfNotExists('300_log', $indexName, $columns, $unique);
            }

            $columnsAfter = $this->getColumnCount('300_log');
            $this->output("Migration complete: {$columnsAfter} columns (added " . ($columnsAfter - $columnsBefore) . " new columns)", 'success');
            return true;

        } catch (Exception $e) {
            $this->output("Migration failed: " . $e->getMessage(), 'error');
            $this->errors[] = "300_log migration failed: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Migrate 301 table (child table - migrate second)
     */
    public function migrate301(): bool
    {
        $this->output("Migrating 301 table...", 'header');

        if (!$this->tableExists('301')) {
            $this->output("Table `301` does not exist!", 'error');
            $this->errors[] = "Table `301` does not exist";
            return false;
        }

        $columnsBefore = $this->getColumnCount('301');
        $rowCount = $this->getRowCount('301');
        $this->output("Current state: {$columnsBefore} columns, {$rowCount} rows", 'info');

        // Note: MySQL DDL (ALTER TABLE) causes implicit commit, so transactions are not effective
        // We proceed without explicit transaction for DDL operations

        try {
            // Add columns
            $columns = [
                // Emergency treatment flags
                ['employee_treated_in_emergency', "BOOLEAN DEFAULT FALSE COMMENT 'Was employee treated in emergency room'"],
                ['employee_hospitalized_overnight', "BOOLEAN DEFAULT FALSE COMMENT 'Was employee hospitalized overnight as inpatient'"],
                // Witness information
                ['witness_name', "VARCHAR(200) DEFAULT NULL COMMENT 'Name of person who witnessed the incident'"],
                ['witness_phone', "VARCHAR(20) DEFAULT NULL COMMENT 'Phone number of witness'"],
                // Physician/medical facility information
                ['physician_name', "VARCHAR(200) DEFAULT NULL COMMENT 'Name of treating physician or healthcare professional'"],
                ['physician_facility', "VARCHAR(255) DEFAULT NULL COMMENT 'Name of medical facility where treated'"],
                ['physician_phone', "VARCHAR(20) DEFAULT NULL COMMENT 'Phone number of physician/facility'"],
                ['treatment_provided', "TEXT DEFAULT NULL COMMENT 'Description of medical treatment provided'"],
                // Root cause analysis
                ['root_cause', "TEXT DEFAULT NULL COMMENT 'Root cause analysis of the incident'"],
                ['corrective_actions', "TEXT DEFAULT NULL COMMENT 'Corrective actions taken to prevent recurrence'"],
                // Investigation fields
                ['investigated_by', "VARCHAR(200) DEFAULT NULL COMMENT 'Name of person who investigated the incident'"],
                ['investigation_date', "DATE DEFAULT NULL COMMENT 'Date investigation was conducted'"],
                ['investigation_findings', "TEXT DEFAULT NULL COMMENT 'Findings and conclusions from investigation'"],
                // Audit tracking
                ['created_by', "INT UNSIGNED DEFAULT NULL COMMENT 'User ID who created the record'"],
                ['updated_by', "INT UNSIGNED DEFAULT NULL COMMENT 'User ID who last updated the record'"],
            ];

            foreach ($columns as [$column, $definition]) {
                $this->addColumnIfNotExists('301', $column, $definition);
            }

            // Create indexes
            $indexes = [
                ['idx_301_osha_case', '`osha_case_id`', false],
                ['idx_301_investigation_date', '`investigation_date`', false],
                ['idx_301_status', '`status`', false],
            ];

            foreach ($indexes as [$indexName, $columns, $unique]) {
                $this->createIndexIfNotExists('301', $indexName, $columns, $unique);
            }

            $columnsAfter = $this->getColumnCount('301');
            $this->output("Migration complete: {$columnsAfter} columns (added " . ($columnsAfter - $columnsBefore) . " new columns)", 'success');
            return true;

        } catch (Exception $e) {
            $this->output("Migration failed: " . $e->getMessage(), 'error');
            $this->errors[] = "301 migration failed: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Migrate 300a table (aggregation table - migrate third)
     */
    public function migrate300a(): bool
    {
        $this->output("Migrating 300a table...", 'header');

        if (!$this->tableExists('300a')) {
            $this->output("Table `300a` does not exist!", 'error');
            $this->errors[] = "Table `300a` does not exist";
            return false;
        }

        $columnsBefore = $this->getColumnCount('300a');
        $rowCount = $this->getRowCount('300a');
        $this->output("Current state: {$columnsBefore} columns, {$rowCount} rows", 'info');

        // Note: MySQL DDL (ALTER TABLE) causes implicit commit, so transactions are not effective
        // We proceed without explicit transaction for DDL operations

        try {
            // Add columns
            $columns = [
                // Company reference
                ['company_id', "CHAR(36) DEFAULT NULL COMMENT 'Reference to company/employer table'"],
                // Certification fields
                ['certified_by_name', "VARCHAR(200) DEFAULT NULL COMMENT 'Name of company executive who certifies the form'"],
                ['certified_by_title', "VARCHAR(100) DEFAULT NULL COMMENT 'Title of certifying official'"],
                ['certified_date', "DATE DEFAULT NULL COMMENT 'Date when form was certified'"],
                // OSHA submission tracking
                ['submitted_to_osha', "BOOLEAN DEFAULT FALSE COMMENT 'Has this summary been submitted to OSHA'"],
                ['submission_date', "DATETIME DEFAULT NULL COMMENT 'Date and time of OSHA submission'"],
                ['submission_confirmation', "VARCHAR(100) DEFAULT NULL COMMENT 'OSHA submission confirmation number'"],
                // Timestamps
                ['created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp'"],
                ['updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record last update timestamp'"],
                // Audit tracking
                ['created_by', "INT UNSIGNED DEFAULT NULL COMMENT 'User ID who created the record'"],
                ['updated_by', "INT UNSIGNED DEFAULT NULL COMMENT 'User ID who last updated the record'"],
            ];

            foreach ($columns as [$column, $definition]) {
                $this->addColumnIfNotExists('300a', $column, $definition);
            }

            // Create indexes
            $indexes = [
                ['idx_300a_company', '`company_id`', false],
                ['idx_300a_establishment', '`establishment_id`', false],
                ['idx_300a_year', '`year_filing_for`', false],
                ['idx_300a_submission', '`submitted_to_osha`', false],
            ];

            foreach ($indexes as [$indexName, $columns, $unique]) {
                $this->createIndexIfNotExists('300a', $indexName, $columns, $unique);
            }

            // Add unique constraint for establishment + year combination
            $this->createIndexIfNotExists('300a', 'uk_300a_establishment_year', '`establishment_id`, `year_filing_for`', true);

            $columnsAfter = $this->getColumnCount('300a');
            $this->output("Migration complete: {$columnsAfter} columns (added " . ($columnsAfter - $columnsBefore) . " new columns)", 'success');
            return true;

        } catch (Exception $e) {
            $this->output("Migration failed: " . $e->getMessage(), 'error');
            $this->errors[] = "300a migration failed: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Run verification queries
     */
    public function verify(): void
    {
        $this->output("Running verification...", 'header');

        $tables = ['300_log', '301', '300a'];
        $expectedCounts = [
            '300_log' => 33,
            '301' => 22,
            '300a' => 28
        ];

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                $this->output("Table `{$table}` does not exist", 'warning');
                continue;
            }

            $columnCount = $this->getColumnCount($table);
            $rowCount = $this->getRowCount($table);
            $expected = $expectedCounts[$table] ?? 0;

            $status = $columnCount >= $expected ? 'success' : 'warning';
            $this->output("`{$table}`: {$columnCount} columns, {$rowCount} rows (expected >= {$expected} columns)", $status);
        }
    }

    /**
     * Print summary
     */
    public function printSummary(): void
    {
        $this->output("Migration Summary", 'header');

        if (!empty($this->successful)) {
            $this->output("Successful operations: " . count($this->successful), 'success');
        }

        if (!empty($this->errors)) {
            $this->output("Errors encountered: " . count($this->errors), 'error');
            foreach ($this->errors as $error) {
                $this->output("  - {$error}", 'error');
            }
        }

        if (empty($this->errors)) {
            $this->output("All migrations completed successfully!", 'success');
        }
    }

    /**
     * Run all migrations
     */
    public function runAll(): bool
    {
        $this->output("OSHA Tables Migration Script", 'header');
        $this->output("Based on docs/OSHA_TABLE_MIGRATION_PLAN.md", 'info');
        $this->output("Database: " . DB_NAME . " @ " . DB_HOST, 'info');
        echo "\n";

        // Migration order as per the plan:
        // 1. 300_log (parent table)
        // 2. 301 (child table)
        // 3. 300a (aggregation table)

        $success = true;

        // Step 1: Migrate 300_log
        if (!$this->migrate300Log()) {
            $success = false;
        }
        echo "\n";

        // Step 2: Migrate 301
        if (!$this->migrate301()) {
            $success = false;
        }
        echo "\n";

        // Step 3: Migrate 300a
        if (!$this->migrate300a()) {
            $success = false;
        }
        echo "\n";

        // Verify
        $this->verify();
        echo "\n";

        // Summary
        $this->printSummary();

        return $success;
    }

    /**
     * Set dry run mode
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }
}

// ==============================================================================
// MAIN EXECUTION
// ==============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║         OSHA Tables Migration Script v1.0.0                      ║\n";
echo "║         SafeShift EHR Database Migration                         ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Parse command line arguments
$options = getopt('', ['dry-run', 'force', 'help']);

if (isset($options['help'])) {
    echo "Usage: php migrate_osha_tables.php [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run    Show what would be done without making changes\n";
    echo "  --force      Skip backup confirmation prompt\n";
    echo "  --help       Show this help message\n";
    echo "\n";
    exit(0);
}

$dryRun = isset($options['dry-run']);
$force = isset($options['force']);

if ($dryRun) {
    echo "\033[0;33m[!] DRY RUN MODE - No changes will be made\033[0m\n\n";
}

// Check database connection
echo "[i] Checking database connection...\n";
try {
    $pdo = pdo();
    echo "\033[0;32m[✓] Connected to database: " . DB_NAME . "\033[0m\n\n";
} catch (PDOException $e) {
    echo "\033[0;31m[✗] Database connection failed: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

// Backup prompt
if (!$force && !$dryRun) {
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║                     ⚠️  IMPORTANT WARNING ⚠️                       ║\n";
    echo "╠══════════════════════════════════════════════════════════════════╣\n";
    echo "║  This script will modify the following tables:                   ║\n";
    echo "║    - 300_log (adding ~23 columns)                                ║\n";
    echo "║    - 301 (adding ~15 columns)                                    ║\n";
    echo "║    - 300a (adding ~11 columns)                                   ║\n";
    echo "║                                                                  ║\n";
    echo "║  NO COLUMNS WILL BE DROPPED - this is an additive migration.    ║\n";
    echo "║                                                                  ║\n";
    echo "║  Recommended: Create a database backup before proceeding:        ║\n";
    echo "║  mysqldump -u user -p " . DB_NAME . " 301 300_log 300a > backup.sql  ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    echo "\n";

    echo "Have you created a backup? Continue with migration? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) !== 'yes') {
        echo "\n\033[0;33m[!] Migration cancelled by user.\033[0m\n";
        echo "Please create a backup and run the script again.\n\n";
        exit(0);
    }
    echo "\n";
}

// Create and run migration
$migration = new OshaMigration($pdo);
$migration->setDryRun($dryRun);

$startTime = microtime(true);
$success = $migration->runAll();
$endTime = microtime(true);

$duration = round($endTime - $startTime, 2);
echo "\n[i] Migration completed in {$duration} seconds\n";

if ($success) {
    echo "\n\033[0;32m╔══════════════════════════════════════════════════════════════════╗\033[0m\n";
    echo "\033[0;32m║               Migration Completed Successfully!                   ║\033[0m\n";
    echo "\033[0;32m╚══════════════════════════════════════════════════════════════════╝\033[0m\n\n";
    exit(0);
} else {
    echo "\n\033[0;31m╔══════════════════════════════════════════════════════════════════╗\033[0m\n";
    echo "\033[0;31m║               Migration Completed with Errors                     ║\033[0m\n";
    echo "\033[0;31m╚══════════════════════════════════════════════════════════════════╝\033[0m\n\n";
    exit(1);
}
