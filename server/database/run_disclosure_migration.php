<?php
/**
 * SafeShift EHR Disclosure Tables Migration Runner
 * Creates encounter_disclosures and disclosure_templates tables
 * 
 * This script is idempotent - can be run multiple times safely
 */

require_once __DIR__ . '/../includes/bootstrap.php';

echo "SafeShift EHR Disclosure Tables Migration\n";
echo "==========================================\n\n";

try {
    $db = \App\db\pdo();
    
    // Define the tables we need to create
    $tables_to_create = ['encounter_disclosures', 'disclosure_templates'];
    
    // Check which tables already exist
    echo "Checking existing tables...\n";
    $existing_tables = [];
    foreach ($tables_to_create as $table) {
        // Use direct query with escaped table name (table names are controlled by us)
        $result = $db->query("SHOW TABLES LIKE '" . $table . "'")->fetch();
        if ($result) {
            $existing_tables[] = $table;
            echo "  ✓ $table already exists\n";
        } else {
            echo "  ✗ $table does not exist (will be created)\n";
        }
    }
    
    echo "\n";
    
    // Read the migration SQL file
    $sql_file = __DIR__ . '/migrations/encounter_disclosures.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Split into individual statements (handle comments and multi-line)
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql_content)));
    
    $success_count = 0;
    $skip_count = 0;
    $error_count = 0;
    $errors = [];
    
    echo "Processing " . count($statements) . " SQL statements...\n\n";
    
    foreach ($statements as $index => $statement) {
        if (empty($statement)) continue;
        
        // Skip comment-only statements
        $clean_statement = trim(preg_replace('/--.*$/m', '', $statement));
        if (empty($clean_statement)) {
            continue;
        }
        
        try {
            // Show first 60 chars of statement for context
            $preview = substr(preg_replace('/\s+/', ' ', $clean_statement), 0, 60);
            echo "Statement " . ($index + 1) . ": $preview...\n";
            
            // Check if this is a CREATE TABLE with IF NOT EXISTS (already idempotent)
            if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/i', $statement)) {
                $db->exec($statement);
                $success_count++;
                echo "  ✓ Executed (IF NOT EXISTS handled by MySQL)\n";
            }
            // Check if this is an INSERT statement for templates
            elseif (preg_match('/INSERT\s+INTO\s+disclosure_templates/i', $statement)) {
                // Check if templates already exist
                $stmt = $db->query("SELECT COUNT(*) FROM disclosure_templates");
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $skip_count++;
                    echo "  ⊘ Skipped (disclosure_templates already has data)\n";
                } else {
                    $db->exec($statement);
                    $success_count++;
                    echo "  ✓ Inserted default disclosure templates\n";
                }
            }
            // Check if this is a CREATE INDEX with IF NOT EXISTS
            elseif (preg_match('/CREATE\s+INDEX\s+IF\s+NOT\s+EXISTS/i', $statement)) {
                $db->exec($statement);
                $success_count++;
                echo "  ✓ Executed (IF NOT EXISTS handled by MySQL)\n";
            }
            // Handle regular CREATE INDEX (check if exists first)
            elseif (preg_match('/CREATE\s+INDEX\s+(\w+)\s+ON\s+(\w+)/i', $statement, $matches)) {
                $index_name = $matches[1];
                $table_name = $matches[2];
                
                // Check if index already exists
                $check = $db->query("SHOW INDEX FROM $table_name WHERE Key_name = '$index_name'");
                if ($check->fetch()) {
                    $skip_count++;
                    echo "  ⊘ Skipped (index $index_name already exists)\n";
                } else {
                    $db->exec($statement);
                    $success_count++;
                    echo "  ✓ Created index $index_name\n";
                }
            }
            else {
                // Execute other statements directly
                $db->exec($statement);
                $success_count++;
                echo "  ✓ Success\n";
            }
            
        } catch (PDOException $e) {
            // Handle specific expected errors gracefully
            $error_msg = $e->getMessage();
            
            // Duplicate entry errors for INSERT can be skipped
            if (strpos($error_msg, 'Duplicate entry') !== false) {
                $skip_count++;
                echo "  ⊘ Skipped (data already exists)\n";
            }
            // Table already exists (for non-IF NOT EXISTS statements)
            elseif (strpos($error_msg, 'already exists') !== false) {
                $skip_count++;
                echo "  ⊘ Skipped (already exists)\n";
            }
            else {
                $error_count++;
                echo "  ✗ Failed - $error_msg\n";
                $errors[] = [
                    'statement' => $preview,
                    'error' => $error_msg
                ];
            }
            continue;
        }
    }
    
    echo "\n==========================================\n";
    echo "Migration Summary:\n";
    echo "  Successful: $success_count statements\n";
    echo "  Skipped:    $skip_count statements\n";
    echo "  Failed:     $error_count statements\n";
    
    if ($error_count > 0) {
        echo "\nErrors encountered:\n";
        foreach ($errors as $error) {
            echo "  - {$error['statement']}...\n    Error: {$error['error']}\n";
        }
    }
    
    // Verify tables were created
    echo "\n==========================================\n";
    echo "Verifying tables:\n";
    
    $all_tables_exist = true;
    foreach ($tables_to_create as $table) {
        $result = $db->query("SHOW TABLES LIKE '" . $table . "'")->fetch();
        if ($result) {
            // Get row count
            $count_stmt = $db->query("SELECT COUNT(*) FROM $table");
            $row_count = $count_stmt->fetchColumn();
            echo "  ✓ $table exists ($row_count rows)\n";
        } else {
            echo "  ✗ $table MISSING\n";
            $all_tables_exist = false;
        }
    }
    
    // Verify disclosure_templates has data
    if (in_array('disclosure_templates', $tables_to_create)) {
        $stmt = $db->query("SELECT disclosure_type, title, version FROM disclosure_templates ORDER BY display_order");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($templates) > 0) {
            echo "\nDisclosure templates configured:\n";
            foreach ($templates as $template) {
                echo "  - {$template['disclosure_type']} v{$template['version']}: {$template['title']}\n";
            }
        }
    }
    
    // Log the migration
    \App\log\file_log('info', [
        'action' => 'disclosure_migration',
        'success_count' => $success_count,
        'skip_count' => $skip_count,
        'error_count' => $error_count,
        'all_tables_exist' => $all_tables_exist,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    echo "\n==========================================\n";
    if ($all_tables_exist && $error_count === 0) {
        echo "✓ Migration completed successfully!\n";
        exit(0);
    } else {
        echo "⚠ Migration completed with issues.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    \App\log\file_log('error', [
        'action' => 'disclosure_migration_failed',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
