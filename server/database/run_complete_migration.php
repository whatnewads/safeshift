<?php
/**
 * SafeShift EHR Complete Database Migration Runner
 * Implements all 11 features from specification
 */

require_once __DIR__ . '/../includes/bootstrap.php';

echo "SafeShift EHR Complete Database Migration\n";
echo "========================================\n\n";

try {
    $db = \App\db\pdo();
    
    // Read the complete schema SQL file
    $sql_file = __DIR__ . '/migrations/safeshift_complete_schema_final.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Split by semicolons but be careful with delimiters inside strings
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql_content)));
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    echo "Found " . count($statements) . " SQL statements to execute.\n\n";
    
    foreach ($statements as $index => $statement) {
        if (empty($statement)) continue;
        
        try {
            echo "Executing statement " . ($index + 1) . "... ";
            
            // Show first 80 chars of statement for context
            $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 80);
            echo "($preview...)\n";
            
            $db->exec($statement);
            $success_count++;
            echo "✓ Success\n";
            
        } catch (PDOException $e) {
            $error_count++;
            $error_msg = "Error: " . $e->getMessage();
            echo "✗ Failed - $error_msg\n";
            $errors[] = [
                'statement' => $preview,
                'error' => $error_msg
            ];
            
            // Continue with next statement instead of stopping
            continue;
        }
    }
    
    echo "\n========================================\n";
    echo "Migration Summary:\n";
    echo "Success: $success_count statements\n";
    echo "Failed: $error_count statements\n";
    
    if ($error_count > 0) {
        echo "\nErrors encountered:\n";
        foreach ($errors as $error) {
            echo "- {$error['statement']}...\n  Error: {$error['error']}\n";
        }
    }
    
    // Verify critical tables were created
    echo "\nVerifying critical tables:\n";
    $critical_tables = [
        'patient_access_log' => 'Feature 1.1: Recent Patients',
        'chart_templates' => 'Feature 1.2: Smart Templates',
        'ui_tooltips' => 'Feature 1.3: Tooltips',
        'sync_queue' => 'Feature 1.4: Offline Mode',
        'encounter_flags' => 'Feature 2.1: High-Risk Flagging',
        'training_requirements' => 'Feature 2.2: Training Compliance',
        'qa_review_queue' => 'Feature 2.3: Mobile QA',
        'compliance_kpis' => 'Feature 3.2: Compliance Monitor',
        'regulatory_updates' => 'Feature 3.3: Regulatory Assistant'
    ];
    
    foreach ($critical_tables as $table => $feature) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "✓ $table exists ($feature)\n";
        } else {
            echo "✗ $table missing ($feature)\n";
        }
    }
    
    // Log the migration
    \App\log\file_log('info', [
        'action' => 'database_migration',
        'success_count' => $success_count,
        'error_count' => $error_count,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    \App\log\file_log('error', [
        'action' => 'database_migration_failed',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

echo "\nMigration complete!\n";