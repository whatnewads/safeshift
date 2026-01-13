-- SafeShift EHR Complete Database Schema (FINAL FIXED)
-- Implements all 11 features from specification
-- Date: 2025-11-07

-- =====================================================
-- PHASE 0: CRITICAL FIXES (Already completed)
-- =====================================================
-- Role slug column already added successfully

-- =====================================================
-- FEATURE 1.1: Recent Patients / Recently Viewed
-- =====================================================

CREATE TABLE IF NOT EXISTS patient_access_log (
    log_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    patient_id CHAR(36) NOT NULL,
    accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    access_type ENUM('view', 'edit') DEFAULT 'view',
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_user_accessed (user_id, accessed_at DESC),
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
);

-- =====================================================
-- FEATURE 1.2: Smart Template Loader
-- =====================================================

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
    INDEX idx_user_templates (created_by, status),
    FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE CASCADE
);

-- =====================================================
-- FEATURE 1.3: Tooltip-based Guided Interface
-- =====================================================
-- ui_tooltips already created successfully

CREATE TABLE IF NOT EXISTS user_tooltip_preferences (
    user_id CHAR(36) PRIMARY KEY,
    tooltips_enabled BOOLEAN DEFAULT TRUE,
    dismissed_tooltips JSON,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
);

-- =====================================================
-- FEATURE 1.4: Offline Mode Support Tables
-- =====================================================

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
    INDEX idx_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS offline_conflicts (
    conflict_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    queue_id CHAR(36) NOT NULL,
    server_version JSON,
    client_version JSON,
    resolution_status ENUM('pending', 'resolved_client', 'resolved_server', 'merged') DEFAULT 'pending',
    resolved_by CHAR(36),
    resolved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (queue_id) REFERENCES sync_queue(queue_id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES user(user_id) ON DELETE SET NULL
);

-- =====================================================
-- FEATURE 2.1: High-Risk Call Flagging
-- =====================================================

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
    INDEX idx_status_severity (status, severity, created_at),
    FOREIGN KEY (encounter_id) REFERENCES encounters(encounter_id) ON DELETE CASCADE,
    FOREIGN KEY (flagged_by) REFERENCES user(user_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES user(user_id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES user(user_id) ON DELETE SET NULL
);

-- flag_rules already created successfully

-- =====================================================
-- FEATURE 2.2: Training Compliance Dashboard
-- =====================================================
-- training_requirements already created successfully

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
    INDEX idx_user_status (user_id, expiration_date),
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (requirement_id) REFERENCES training_requirements(requirement_id) ON DELETE CASCADE
);

-- Add computed status column separately (MariaDB compatibility)
ALTER TABLE staff_training_records 
ADD COLUMN status VARCHAR(20) 
GENERATED ALWAYS AS (
    CASE
        WHEN expiration_date < DATE(NOW()) THEN 'expired'
        WHEN expiration_date <= DATE_ADD(DATE(NOW()), INTERVAL 30 DAY) THEN 'expiring_soon'
        ELSE 'current'
    END
) VIRTUAL AFTER completed_by;

CREATE TABLE IF NOT EXISTS training_reminders (
    reminder_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    record_id CHAR(36) NOT NULL,
    reminder_type ENUM('30_day', '14_day', '7_day', 'overdue') NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (record_id) REFERENCES staff_training_records(record_id) ON DELETE CASCADE
);

-- =====================================================
-- FEATURE 2.3: Mobile-First Quality Review Panel
-- =====================================================

CREATE TABLE IF NOT EXISTS qa_review_queue (
    review_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    encounter_id CHAR(36) NOT NULL,
    reviewer_id CHAR(36),
    review_status ENUM('pending', 'approved', 'rejected', 'flagged') DEFAULT 'pending',
    review_notes TEXT,
    reviewed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviewer_status (reviewer_id, review_status),
    FOREIGN KEY (encounter_id) REFERENCES encounters(encounter_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES user(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS qa_bulk_actions (
    action_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    reviewer_id CHAR(36) NOT NULL,
    action_type ENUM('bulk_approve', 'bulk_reject') NOT NULL,
    encounter_ids JSON NOT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewer_id) REFERENCES user(user_id) ON DELETE CASCADE
);

-- =====================================================
-- FEATURE 3.1: Audit Trail Generator (Enhanced)
-- =====================================================

CREATE TABLE IF NOT EXISTS audit_exports (
    export_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    exported_by CHAR(36) NOT NULL,
    export_format ENUM('pdf', 'csv', 'json') NOT NULL,
    filter_criteria JSON,
    file_path VARCHAR(500),
    record_count INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exported_by) REFERENCES user(user_id) ON DELETE CASCADE
);

-- =====================================================
-- FEATURE 3.2: Live Compliance Monitor
-- =====================================================
-- compliance_kpis, compliance_kpi_values, compliance_alerts already created successfully

-- =====================================================
-- FEATURE 3.3: Regulatory Update Assistant
-- =====================================================

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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS implementation_checklists (
    checklist_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    update_id CHAR(36) NOT NULL,
    checklist_items JSON NOT NULL,
    completion_pct DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (update_id) REFERENCES regulatory_updates(update_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS regulation_trainings (
    training_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    update_id CHAR(36) NOT NULL,
    training_content JSON NOT NULL,
    assigned_roles JSON,
    due_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (update_id) REFERENCES regulatory_updates(update_id) ON DELETE CASCADE
);

-- =====================================================
-- SUPPORTING TABLES FOR MISSING FEATURES
-- =====================================================

-- Create missing appointments table
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
    INDEX idx_provider_date (provider_id, start_time),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES user(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES user(user_id) ON DELETE SET NULL
);

-- Create missing orders table
CREATE TABLE IF NOT EXISTS orders (
    order_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    encounter_id CHAR(36) NOT NULL,
    order_type VARCHAR(100) NOT NULL,
    order_description TEXT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    ordered_by CHAR(36),
    ordered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    results TEXT,
    INDEX idx_encounter_status (encounter_id, status),
    FOREIGN KEY (encounter_id) REFERENCES encounters(encounter_id) ON DELETE CASCADE,
    FOREIGN KEY (ordered_by) REFERENCES user(user_id) ON DELETE SET NULL
);

-- Create missing dot_tests table
CREATE TABLE IF NOT EXISTS dot_tests (
    test_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    encounter_id CHAR(36) NOT NULL,
    patient_id CHAR(36) NOT NULL,
    modality ENUM('drug_test', 'alcohol_test') NOT NULL,
    test_type VARCHAR(100),
    specimen_id VARCHAR(100),
    collected_at DATETIME,
    results JSON,
    mro_review_required BOOLEAN DEFAULT FALSE,
    mro_reviewed_by CHAR(36),
    mro_reviewed_at DATETIME,
    status ENUM('pending', 'negative', 'positive', 'cancelled', 'invalid') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient_modality (patient_id, modality),
    FOREIGN KEY (encounter_id) REFERENCES encounters(encounter_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (mro_reviewed_by) REFERENCES user(user_id) ON DELETE SET NULL
);