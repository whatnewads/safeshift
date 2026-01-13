-- SafeShift EHR Complete Database Schema
-- Implements all 11 features from specification
-- Date: 2025-11-07

-- =====================================================
-- PHASE 0: CRITICAL FIXES
-- =====================================================

-- Add missing slug column to role table
ALTER TABLE role ADD COLUMN IF NOT EXISTS slug VARCHAR(50) AFTER name;
UPDATE role SET slug = '1clinician' WHERE name = '1clinician';
UPDATE role SET slug = 'pclinician' WHERE name = 'pclinician';
UPDATE role SET slug = 'dclinician' WHERE name = 'dclinician';
UPDATE role SET slug = 'cadmin' WHERE name = 'cadmin';
UPDATE role SET slug = 'tadmin' WHERE name = 'tadmin';
UPDATE role SET slug = 'employee' WHERE name = 'employee';
UPDATE role SET slug = 'employer' WHERE name = 'employer';
UPDATE role SET slug = 'custom' WHERE name = 'custom';
ALTER TABLE role MODIFY COLUMN slug VARCHAR(50) NOT NULL UNIQUE;

-- =====================================================
-- FEATURE 1.1: Recent Patients / Recently Viewed
-- =====================================================

CREATE TABLE IF NOT EXISTS patient_access_log (
    log_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    patient_uuid CHAR(36) NOT NULL,
    accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    access_type ENUM('view', 'edit') DEFAULT 'view',
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_user_accessed (user_id, accessed_at DESC),
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_uuid) REFERENCES patients(patient_uuid) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS ui_tooltips (
    tooltip_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    field_identifier VARCHAR(255) UNIQUE NOT NULL,
    tooltip_text TEXT NOT NULL,
    tooltip_type ENUM('info', 'warning', 'help', 'compliance') DEFAULT 'info',
    role_filter VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_field (field_identifier, status)
);

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

CREATE TABLE IF NOT EXISTS flag_rules (
    rule_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    rule_name VARCHAR(255) NOT NULL,
    rule_type VARCHAR(100),
    rule_condition JSON NOT NULL,
    flag_severity ENUM('critical', 'high', 'medium', 'low') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- FEATURE 2.2: Training Compliance Dashboard
-- =====================================================

CREATE TABLE IF NOT EXISTS training_requirements (
    requirement_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    training_name VARCHAR(255) NOT NULL,
    training_description TEXT,
    training_category VARCHAR(100),
    required_roles JSON,
    recurrence_interval INT,
    grace_period INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS staff_training_records (
    record_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    requirement_id CHAR(36) NOT NULL,
    completion_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    certification_number VARCHAR(100),
    proof_document_path VARCHAR(500),
    completed_by CHAR(36),
    status VARCHAR(20) GENERATED ALWAYS AS (
        CASE
            WHEN expiration_date < CURDATE() THEN 'expired'
            WHEN expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring_soon'
            ELSE 'current'
        END
    ) STORED,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
    FOREIGN KEY (requirement_id) REFERENCES training_requirements(requirement_id) ON DELETE CASCADE
);

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

-- Update existing audit_log table if needed
ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS checksum CHAR(64) AFTER payload;
ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS user_agent TEXT AFTER ip_address;
ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS session_id VARCHAR(255) AFTER user_agent;

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

CREATE TABLE IF NOT EXISTS compliance_kpis (
    kpi_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    kpi_name VARCHAR(255) NOT NULL,
    kpi_category VARCHAR(100),
    calculation_method TEXT,
    threshold_warning DECIMAL(10,2),
    threshold_critical DECIMAL(10,2),
    unit VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS compliance_kpi_values (
    value_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    kpi_id CHAR(36) NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    status ENUM('compliant', 'warning', 'critical') NOT NULL,
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kpi_calculated (kpi_id, calculated_at),
    FOREIGN KEY (kpi_id) REFERENCES compliance_kpis(kpi_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS compliance_alerts (
    alert_id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    kpi_id CHAR(36) NOT NULL,
    alert_message TEXT NOT NULL,
    severity ENUM('warning', 'critical') NOT NULL,
    sent_to JSON,
    acknowledged_by CHAR(36),
    acknowledged_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kpi_id) REFERENCES compliance_kpis(kpi_id) ON DELETE CASCADE
);

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
    FOREIGN KEY (created_by) REFERENCES user(user_id)
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
    FOREIGN KEY (patient_id) REFERENCES patients(patient_uuid) ON DELETE CASCADE,
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
    FOREIGN KEY (patient_id) REFERENCES patients(patient_uuid) ON DELETE CASCADE,
    FOREIGN KEY (mro_reviewed_by) REFERENCES user(user_id) ON DELETE SET NULL
);

-- =====================================================
-- INITIAL DATA INSERTS
-- =====================================================

-- Insert default flag rules
INSERT INTO flag_rules (rule_name, rule_type, rule_condition, flag_severity, is_active) VALUES
('High Blood Pressure', 'vital_threshold', '{"field": "systolic_bp", "operator": ">", "value": 180}', 'critical', TRUE),
('Very High Blood Pressure', 'vital_threshold', '{"field": "diastolic_bp", "operator": ">", "value": 110}', 'critical', TRUE),
('Missing Vitals', 'missing_field', '{"field": "vitals", "required": true}', 'high', TRUE),
('Incomplete Documentation', 'missing_field', '{"field": "chief_complaint", "required": true}', 'medium', TRUE),
('OSHA Recordable', 'injury_severity', '{"field": "injury_type", "operator": "in", "value": ["lost_time", "hospitalization"]}', 'high', TRUE);

-- Insert training requirements
INSERT INTO training_requirements (training_name, training_description, training_category, required_roles, recurrence_interval) VALUES
('HIPAA Privacy Training', 'Annual HIPAA privacy and security training', 'compliance', '["1clinician", "pclinician", "dclinician", "cadmin", "tadmin"]', 365),
('OSHA Safety Training', 'Occupational safety and health training', 'safety', '["1clinician", "pclinician", "dclinician"]', 365),
('BLS Certification', 'Basic Life Support certification', 'clinical', '["1clinician", "pclinician", "dclinician"]', 730),
('DOT Examiner Training', 'DOT Medical Examiner certification', 'clinical', '["pclinician"]', 1825);

-- Insert compliance KPIs
INSERT INTO compliance_kpis (kpi_name, kpi_category, calculation_method, threshold_warning, threshold_critical, unit) VALUES
('Chart Completion Rate', 'clinical', 'SELECT (COUNT(CASE WHEN status = "complete" THEN 1 END) * 100.0) / COUNT(*) FROM encounters WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)', 90.00, 80.00, 'percentage'),
('OSHA TRIR', 'osha', 'SELECT (COUNT(*) * 200000.0) / (SELECT SUM(hours_worked) FROM employee_hours WHERE year = YEAR(CURDATE())) FROM osha_cases WHERE recordable = TRUE AND year = YEAR(CURDATE())', 3.00, 4.00, 'rate'),
('DOT Test Completion', 'dot', 'SELECT (COUNT(CASE WHEN status = "completed" THEN 1 END) * 100.0) / COUNT(*) FROM dot_tests WHERE test_type = "random" AND year = YEAR(CURDATE())', 95.00, 90.00, 'percentage'),
('Training Compliance', 'hipaa', 'SELECT (COUNT(CASE WHEN status = "current" THEN 1 END) * 100.0) / COUNT(*) FROM staff_training_records WHERE requirement_id IN (SELECT requirement_id FROM training_requirements WHERE training_category = "compliance")', 98.00, 95.00, 'percentage');

-- Insert tooltips
INSERT INTO ui_tooltips (field_identifier, tooltip_text, tooltip_type, role_filter) VALUES
('vitals.systolic_bp', 'Enter systolic blood pressure (top number). Normal range: 90-120 mmHg. Flag if >180 mmHg.', 'info', 'all'),
('vitals.diastolic_bp', 'Enter diastolic blood pressure (bottom number). Normal range: 60-80 mmHg. Flag if >110 mmHg.', 'info', 'all'),
('chief_complaint', 'Document the patient''s main reason for visit in their own words. This field is required for all encounters.', 'help', 'all'),
('dot_test.specimen_id', 'Enter the Chain of Custody form number. Must match the specimen label exactly.', 'compliance', '1clinician,pclinician'),
('injury.osha_recordable', 'Check if this injury results in: death, days away from work, restricted work/transfer, medical treatment beyond first aid, loss of consciousness, or significant diagnosed injury/illness.', 'warning', 'all');