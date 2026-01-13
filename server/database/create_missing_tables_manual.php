<?php
require_once __DIR__ . '/../includes/bootstrap.php';

echo "Creating Missing SafeShift EHR Tables (Manual)\n";
echo "==============================================\n\n";

try {
    $db = \App\db\pdo();
    
    // Define tables to create
    $tables = [
        'patient_access_log' => "
            CREATE TABLE IF NOT EXISTS patient_access_log (
                log_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                user_id CHAR(36) NOT NULL,
                patient_id CHAR(36) NOT NULL,
                accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                access_type ENUM('view', 'edit') DEFAULT 'view',
                ip_address VARCHAR(45),
                user_agent TEXT,
                INDEX idx_user_accessed (user_id, accessed_at DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'chart_templates' => "
            CREATE TABLE IF NOT EXISTS chart_templates (
                template_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                template_name VARCHAR(255) NOT NULL,
                description TEXT,
                encounter_type VARCHAR(100),
                template_data JSON NOT NULL,
                created_by CHAR(36) NOT NULL,
                visibility ENUM('personal', 'organization') DEFAULT 'personal',
                status ENUM('active', 'archived', 'pending_approval') DEFAULT 'active',
                version INT DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_templates (created_by, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'user_tooltip_preferences' => "
            CREATE TABLE IF NOT EXISTS user_tooltip_preferences (
                user_id CHAR(36) PRIMARY KEY,
                tooltips_enabled BOOLEAN DEFAULT TRUE,
                dismissed_tooltips JSON
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'sync_queue' => "
            CREATE TABLE IF NOT EXISTS sync_queue (
                queue_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                user_id CHAR(36) NOT NULL,
                operation_type ENUM('create', 'update', 'delete') NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id CHAR(36) NOT NULL,
                payload JSON NOT NULL,
                status ENUM('pending', 'syncing', 'completed', 'failed', 'conflict') DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                synced_at DATETIME,
                error_message TEXT,
                INDEX idx_user_status (user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'encounter_flags' => "
            CREATE TABLE IF NOT EXISTS encounter_flags (
                flag_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                encounter_id CHAR(36) NOT NULL,
                flag_type VARCHAR(100) NOT NULL,
                severity ENUM('critical', 'high', 'medium', 'low') NOT NULL,
                flag_reason TEXT NOT NULL,
                auto_flagged BOOLEAN DEFAULT TRUE,
                flagged_by CHAR(36),
                assigned_to CHAR(36),
                status ENUM('pending', 'under_review', 'resolved', 'escalated') DEFAULT 'pending',
                due_date DATETIME,
                resolved_at DATETIME,
                resolved_by CHAR(36),
                resolution_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status_severity (status, severity, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'staff_training_records' => "
            CREATE TABLE IF NOT EXISTS staff_training_records (
                record_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                user_id CHAR(36) NOT NULL,
                requirement_id CHAR(36) NOT NULL,
                completion_date DATE NOT NULL,
                expiration_date DATE NOT NULL,
                certification_number VARCHAR(100),
                proof_document_path VARCHAR(500),
                completed_by CHAR(36),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_status (user_id, expiration_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'qa_review_queue' => "
            CREATE TABLE IF NOT EXISTS qa_review_queue (
                review_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                encounter_id CHAR(36) NOT NULL,
                reviewer_id CHAR(36),
                review_status ENUM('pending', 'approved', 'rejected', 'flagged') DEFAULT 'pending',
                review_notes TEXT,
                reviewed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reviewer_status (reviewer_id, review_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'audit_exports' => "
            CREATE TABLE IF NOT EXISTS audit_exports (
                export_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                exported_by CHAR(36) NOT NULL,
                export_format ENUM('pdf', 'csv', 'json') NOT NULL,
                filter_criteria JSON,
                file_path VARCHAR(500),
                record_count INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'regulatory_updates' => "
            CREATE TABLE IF NOT EXISTS regulatory_updates (
                update_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                regulation_title VARCHAR(500) NOT NULL,
                regulation_agency VARCHAR(100),
                regulation_type VARCHAR(100),
                effective_date DATE,
                document_path VARCHAR(500),
                summary TEXT,
                full_text LONGTEXT,
                created_by CHAR(36),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        'appointments' => "
            CREATE TABLE IF NOT EXISTS appointments (
                appointment_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
                patient_id CHAR(36) NOT NULL,
                provider_id CHAR(36),
                start_time DATETIME NOT NULL,
                end_time DATETIME,
                visit_reason VARCHAR(255),
                appointment_type VARCHAR(100),
                status ENUM('scheduled', 'confirmed', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
                created_by CHAR(36),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_patient_date (patient_id, start_time),
                INDEX idx_provider_date (provider_id, start_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($tables as $table_name => $create_sql) {
        echo "Creating table $table_name... ";
        
        try {
            $db->exec($create_sql);
            $success_count++;
            echo "✓\n";
        } catch (PDOException $e) {
            $error_count++;
            echo "✗ " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n==============================================\n";
    echo "Summary: $success_count successful, $error_count failed\n";
    
    // Verify tables were created
    if ($success_count > 0) {
        echo "\nVerifying created tables:\n";
        foreach ($tables as $table_name => $sql) {
            $stmt = $db->query("SHOW TABLES LIKE '$table_name'");
            if ($stmt->fetch()) {
                echo "✓ $table_name exists\n";
            } else {
                echo "✗ $table_name NOT FOUND\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "\nFATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigration complete!\n";