-- Occupational Health EHR Database Schema with OSHA Compliance
-- MySQL 8.0+ Compatible
-- Character Set: utf8mb4
-- Engine: InnoDB
-- Version: 1.0.0
-- Created: 2025-01-14

-- =====================================================
-- DATABASE CONFIGURATION
-- =====================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';
SET FOREIGN_KEY_CHECKS = 0;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS osha_ehr_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE osha_ehr_db;

-- =====================================================
-- LOOKUP TABLES AND ENUMS
-- =====================================================

-- Company size categories per OSHA requirements
CREATE TABLE company_sizes (
    id TINYINT UNSIGNED PRIMARY KEY,
    description VARCHAR(50) NOT NULL,
    employee_range VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Establishment types per OSHA
CREATE TABLE establishment_types (
    id TINYINT UNSIGNED PRIMARY KEY,
    description VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OSHA injury/illness categories
CREATE TABLE injury_illness_categories (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    category_code VARCHAR(10) NOT NULL UNIQUE,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_injury BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CORE PATIENT MANAGEMENT
-- =====================================================

-- Master Patient Index
CREATE TABLE patients (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    mrn VARCHAR(20) UNIQUE NOT NULL COMMENT 'Medical Record Number',
    ssn_encrypted VARCHAR(255) COMMENT 'Encrypted SSN',
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('M', 'F', 'O', 'U') NOT NULL COMMENT 'Male, Female, Other, Unknown',
    race VARCHAR(50),
    ethnicity VARCHAR(50),
    preferred_language VARCHAR(20) DEFAULT 'en',
    
    -- Contact Information
    primary_phone VARCHAR(20),
    secondary_phone VARCHAR(20),
    email VARCHAR(255),
    
    -- Address
    street_address VARCHAR(255),
    apartment_unit VARCHAR(50),
    city VARCHAR(100),
    state CHAR(2),
    zip_code VARCHAR(10),
    country VARCHAR(2) DEFAULT 'US',
    
    -- Emergency Contact
    emergency_contact_name VARCHAR(200),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relationship VARCHAR(50),
    
    -- Medical Information
    blood_type VARCHAR(10),
    allergies TEXT,
    chronic_conditions TEXT,
    current_medications TEXT,
    
    -- Privacy and Compliance
    privacy_concern BOOLEAN DEFAULT FALSE COMMENT 'OSHA privacy concern flag',
    hipaa_consent_date DATETIME,
    hipaa_consent_version VARCHAR(20),
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP NULL,
    deleted_by INT UNSIGNED,
    
    INDEX idx_mrn (mrn),
    INDEX idx_name (last_name, first_name),
    INDEX idx_dob (date_of_birth),
    INDEX idx_ssn (ssn_encrypted),
    INDEX idx_deleted (is_deleted),
    FULLTEXT idx_fulltext_name (first_name, middle_name, last_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EMPLOYER/COMPANY MANAGEMENT
-- =====================================================

-- Company/Employer Master Table
CREATE TABLE companies (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ein VARCHAR(20) NOT NULL COMMENT 'Employer Identification Number',
    company_name VARCHAR(255) NOT NULL,
    dba_name VARCHAR(255) COMMENT 'Doing Business As name',
    
    -- Primary Address
    headquarters_street VARCHAR(255),
    headquarters_city VARCHAR(100),
    headquarters_state CHAR(2),
    headquarters_zip VARCHAR(10),
    
    -- Contact Information
    main_phone VARCHAR(20),
    main_fax VARCHAR(20),
    hr_contact_name VARCHAR(200),
    hr_contact_email VARCHAR(255),
    hr_contact_phone VARCHAR(20),
    safety_contact_name VARCHAR(200),
    safety_contact_email VARCHAR(255),
    safety_contact_phone VARCHAR(20),
    
    -- Company Details
    incorporation_state CHAR(2),
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    UNIQUE KEY uk_ein (ein),
    INDEX idx_company_name (company_name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Establishment/Location Table
CREATE TABLE establishments (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    establishment_name VARCHAR(255) NOT NULL,
    
    -- OSHA Required Fields
    naics_code CHAR(6) NOT NULL COMMENT 'North American Industry Classification System',
    industry_description VARCHAR(255),
    size_category TINYINT UNSIGNED NOT NULL COMMENT 'References company_sizes table',
    establishment_type TINYINT UNSIGNED NOT NULL COMMENT 'References establishment_types table',
    
    -- Location
    street VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state CHAR(2) NOT NULL,
    zip VARCHAR(10) NOT NULL,
    county VARCHAR(100),
    
    -- Contact
    site_phone VARCHAR(20),
    site_manager_name VARCHAR(200),
    site_manager_email VARCHAR(255),
    
    -- Operations
    is_mobile_unit BOOLEAN DEFAULT FALSE,
    mobile_unit_description TEXT,
    operating_hours TEXT,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    activation_date DATE,
    deactivation_date DATE,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (size_category) REFERENCES company_sizes(id),
    FOREIGN KEY (establishment_type) REFERENCES establishment_types(id),
    INDEX idx_company (company_id),
    INDEX idx_naics (naics_code),
    INDEX idx_location (state, city),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Patient-Employer Relationships
CREATE TABLE patient_employers (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    establishment_id INT UNSIGNED NOT NULL,
    employee_id VARCHAR(50) COMMENT 'Company-specific employee ID',
    
    -- Employment Details
    job_title VARCHAR(255),
    department VARCHAR(100),
    supervisor_name VARCHAR(200),
    hire_date DATE,
    termination_date DATE,
    employment_status ENUM('active', 'terminated', 'leave', 'retired') DEFAULT 'active',
    employment_type ENUM('full_time', 'part_time', 'contractor', 'temporary') DEFAULT 'full_time',
    
    -- Work Schedule
    shift_type ENUM('day', 'evening', 'night', 'rotating', 'other'),
    typical_hours_per_week DECIMAL(5,2),
    
    -- Occupational Health
    job_hazards TEXT,
    required_ppe TEXT COMMENT 'Personal Protective Equipment',
    
    -- Status
    is_primary_employer BOOLEAN DEFAULT FALSE,
    start_date DATE NOT NULL,
    end_date DATE,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    UNIQUE KEY uk_patient_employer (patient_id, company_id, establishment_id, start_date),
    INDEX idx_patient (patient_id),
    INDEX idx_company (company_id),
    INDEX idx_establishment (establishment_id),
    INDEX idx_active (employment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ENCOUNTER MANAGEMENT
-- =====================================================

-- Encounter Types
CREATE TABLE encounter_types (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category ENUM('injury', 'illness', 'exam', 'surveillance', 'dot', 'other') NOT NULL,
    description TEXT,
    is_work_related BOOLEAN DEFAULT FALSE,
    requires_osha_reporting BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Main Encounters Table
CREATE TABLE encounters (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    encounter_number VARCHAR(30) UNIQUE NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    encounter_type_id INT UNSIGNED NOT NULL,
    
    -- Provider Information
    provider_id INT UNSIGNED NOT NULL,
    facility_id INT UNSIGNED,
    
    -- Encounter Details
    encounter_date DATE NOT NULL,
    encounter_time TIME NOT NULL,
    chief_complaint TEXT,
    
    -- Work-Related Information
    is_work_related BOOLEAN DEFAULT FALSE,
    employer_id INT UNSIGNED,
    establishment_id INT UNSIGNED,
    supervisor_notified BOOLEAN DEFAULT FALSE,
    supervisor_notification_time DATETIME,
    
    -- Clinical Data
    vital_signs JSON COMMENT 'Stores BP, HR, RR, Temp, O2Sat, etc.',
    height_cm DECIMAL(5,2),
    weight_kg DECIMAL(5,2),
    bmi DECIMAL(4,2),
    
    -- Assessment and Plan
    assessment TEXT,
    treatment_plan TEXT,
    work_restrictions TEXT,
    follow_up_instructions TEXT,
    
    -- Disposition
    disposition ENUM('discharged', 'admitted', 'transferred', 'left_ama', 'expired'),
    discharge_date DATE,
    discharge_time TIME,
    
    -- Status
    status ENUM('scheduled', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    
    -- Billing
    billing_status ENUM('pending', 'submitted', 'paid', 'denied', 'write_off'),
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    is_deleted BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (encounter_type_id) REFERENCES encounter_types(id),
    FOREIGN KEY (employer_id) REFERENCES companies(id),
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    INDEX idx_patient (patient_id),
    INDEX idx_date (encounter_date),
    INDEX idx_work_related (is_work_related),
    INDEX idx_employer (employer_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- OSHA COMPLIANCE TABLES
-- =====================================================

-- OSHA Form 300 Log Entries
CREATE TABLE osha_form_300_log (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    case_number VARCHAR(20) UNIQUE NOT NULL,
    encounter_id INT UNSIGNED,
    patient_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    establishment_id INT UNSIGNED NOT NULL,
    
    -- Employee Information (Limited for privacy)
    employee_name VARCHAR(255) COMMENT 'May be anonymized',
    job_title VARCHAR(255),
    
    -- Incident Information
    date_of_injury_illness DATE NOT NULL,
    time_of_event TIME,
    location_of_incident VARCHAR(255),
    description_of_incident TEXT NOT NULL,
    
    -- Classification
    injury_illness_category_id INT UNSIGNED NOT NULL,
    body_part_affected VARCHAR(100),
    object_substance VARCHAR(255) COMMENT 'Object/substance that directly injured/made person ill',
    
    -- Outcome
    death_date DATE,
    days_away_from_work INT DEFAULT 0,
    days_restricted_duty INT DEFAULT 0,
    days_job_transfer INT DEFAULT 0,
    
    -- Medical Treatment
    medical_treatment_beyond_first_aid BOOLEAN DEFAULT FALSE,
    
    -- Privacy Case
    is_privacy_case BOOLEAN DEFAULT FALSE COMMENT 'Certain cases require privacy protection',
    privacy_case_reason TEXT,
    
    -- Status
    case_status ENUM('open', 'closed', 'amended') DEFAULT 'open',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    
    FOREIGN KEY (encounter_id) REFERENCES encounters(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    FOREIGN KEY (injury_illness_category_id) REFERENCES injury_illness_categories(id),
    INDEX idx_case_number (case_number),
    INDEX idx_date (date_of_injury_illness),
    INDEX idx_company (company_id),
    INDEX idx_establishment (establishment_id),
    INDEX idx_category (injury_illness_category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OSHA Form 300A Annual Summary
CREATE TABLE osha_form_300a_summary (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    establishment_id INT UNSIGNED NOT NULL,
    reporting_year YEAR NOT NULL,
    
    -- Establishment Information
    annual_average_employees DECIMAL(10,2) NOT NULL,
    total_hours_worked INT NOT NULL,
    
    -- Summary Counts
    total_deaths INT DEFAULT 0,
    total_cases_with_days_away INT DEFAULT 0,
    total_cases_with_job_transfer INT DEFAULT 0,
    total_other_recordable_cases INT DEFAULT 0,
    
    -- Days Counts
    total_days_away_from_work INT DEFAULT 0,
    total_days_job_transfer_restriction INT DEFAULT 0,
    
    -- Injury Types
    injuries_count INT DEFAULT 0,
    skin_disorders_count INT DEFAULT 0,
    respiratory_conditions_count INT DEFAULT 0,
    poisonings_count INT DEFAULT 0,
    hearing_loss_count INT DEFAULT 0,
    all_other_illnesses_count INT DEFAULT 0,
    
    -- Certification
    no_injuries_illnesses TINYINT COMMENT '1=had injuries, 2=no injuries',
    certified_by_name VARCHAR(200),
    certified_by_title VARCHAR(100),
    certified_date DATE,
    
    -- Submission
    submitted_to_osha BOOLEAN DEFAULT FALSE,
    submission_date DATETIME,
    submission_confirmation VARCHAR(100),
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    UNIQUE KEY uk_establishment_year (establishment_id, reporting_year),
    INDEX idx_year (reporting_year),
    INDEX idx_submission (submitted_to_osha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OSHA Form 301 Incident Reports
CREATE TABLE osha_form_301_incidents (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    osha_300_log_id INT UNSIGNED NOT NULL,
    
    -- Additional Detailed Information
    employee_treated_in_emergency BOOLEAN DEFAULT FALSE,
    employee_hospitalized_overnight BOOLEAN DEFAULT FALSE,
    
    -- Witness Information
    witness_name VARCHAR(200),
    witness_phone VARCHAR(20),
    
    -- Physician/Healthcare Professional
    physician_name VARCHAR(200),
    physician_facility VARCHAR(255),
    physician_phone VARCHAR(20),
    treatment_provided TEXT,
    
    -- Root Cause Analysis
    root_cause TEXT,
    corrective_actions TEXT,
    
    -- Investigation
    investigated_by VARCHAR(200),
    investigation_date DATE,
    investigation_findings TEXT,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    
    FOREIGN KEY (osha_300_log_id) REFERENCES osha_form_300_log(id),
    INDEX idx_log_entry (osha_300_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REPORT AMENDMENT SYSTEM
-- =====================================================

CREATE TABLE report_amendments (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    original_report_type ENUM('300_log', '300a_summary', '301_incident') NOT NULL,
    original_report_id INT UNSIGNED NOT NULL,
    
    -- Amendment Details
    amendment_reason TEXT NOT NULL,
    change_description TEXT NOT NULL,
    original_values JSON COMMENT 'Stores original field values',
    new_values JSON COMMENT 'Stores new field values',
    
    -- Review and Approval
    reviewed_by INT UNSIGNED,
    review_date DATETIME,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    
    -- OSHA Resubmission
    requires_osha_resubmission BOOLEAN DEFAULT TRUE,
    resubmitted_date DATETIME,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NOT NULL,
    
    INDEX idx_report_type (original_report_type),
    INDEX idx_report_id (original_report_id),
    INDEX idx_status (approval_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DOT TESTING MODULE
-- =====================================================

-- DOT Test Types
CREATE TABLE dot_test_types (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    cfr_reference VARCHAR(50) COMMENT 'Code of Federal Regulations reference'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOT Testing Chain of Custody
CREATE TABLE dot_chain_of_custody (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ccf_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Federal CCF Number',
    patient_id INT UNSIGNED NOT NULL,
    employer_id INT UNSIGNED NOT NULL,
    test_type_id INT UNSIGNED NOT NULL,
    
    -- Collection Information
    collection_date DATE NOT NULL,
    collection_time TIME NOT NULL,
    collection_site_id INT UNSIGNED,
    collector_name VARCHAR(200),
    collector_signature BLOB,
    
    -- Specimen Information
    specimen_id VARCHAR(50) UNIQUE NOT NULL,
    specimen_temperature DECIMAL(4,1),
    specimen_volume_ml INT,
    
    -- Chain of Custody
    received_at_lab_date DATETIME,
    lab_specimen_id VARCHAR(50),
    
    -- Status Tracking
    status ENUM('collected', 'in_transit', 'at_lab', 'testing', 'pending_mro', 'final') DEFAULT 'collected',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (employer_id) REFERENCES companies(id),
    FOREIGN KEY (test_type_id) REFERENCES dot_test_types(id),
    INDEX idx_ccf (ccf_number),
    INDEX idx_patient (patient_id),
    INDEX idx_date (collection_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MRO Verification Records
CREATE TABLE mro_verifications (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    chain_of_custody_id INT UNSIGNED NOT NULL,
    
    -- MRO Information
    mro_name VARCHAR(200) NOT NULL,
    mro_license_number VARCHAR(50),
    
    -- Test Results
    initial_test_result ENUM('negative', 'positive', 'invalid', 'cancelled') NOT NULL,
    confirmatory_test_result ENUM('negative', 'positive', 'invalid', 'cancelled'),
    
    -- Medical Review
    medical_review_conducted BOOLEAN DEFAULT FALSE,
    legitimate_medical_explanation BOOLEAN DEFAULT FALSE,
    medical_explanation_notes TEXT,
    
    -- Final Determination
    final_result ENUM('negative', 'positive', 'cancelled', 'refusal') NOT NULL,
    
    -- Communication
    donor_contacted BOOLEAN DEFAULT FALSE,
    contact_attempts JSON COMMENT 'Array of contact attempt records',
    employer_notified_date DATETIME,
    
    -- Metadata
    verification_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (chain_of_custody_id) REFERENCES dot_chain_of_custody(id),
    INDEX idx_chain (chain_of_custody_id),
    INDEX idx_result (final_result)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MEDICAL EHR COMPREHENSIVE FIELDS
-- =====================================================

-- Medical History
CREATE TABLE medical_history (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id INT UNSIGNED NOT NULL,
    
    -- Past Medical History
    past_medical_conditions JSON COMMENT 'Array of {condition, onset_date, resolved_date, notes}',
    past_surgical_history JSON COMMENT 'Array of {procedure, date, facility, complications}',
    
    -- Family History
    family_history JSON COMMENT 'Array of {relationship, condition, age_at_onset, living_status}',
    
    -- Social History
    tobacco_use ENUM('never', 'former', 'current', 'unknown'),
    tobacco_pack_years DECIMAL(5,2),
    alcohol_use ENUM('none', 'social', 'moderate', 'heavy', 'unknown'),
    substance_use_history TEXT,
    
    -- Occupational History
    occupational_exposures JSON COMMENT 'Array of {substance, duration, ppe_used, employer}',
    military_service BOOLEAN DEFAULT FALSE,
    military_exposures TEXT,
    
    -- Review of Systems
    review_of_systems JSON COMMENT 'Structured ROS data',
    
    -- Metadata
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    UNIQUE KEY uk_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immunizations
CREATE TABLE immunizations (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id INT UNSIGNED NOT NULL,
    vaccine_code VARCHAR(20) NOT NULL COMMENT 'CVX code',
    vaccine_name VARCHAR(255) NOT NULL,
    
    -- Administration Details
    administration_date DATE NOT NULL,
    lot_number VARCHAR(50),
    manufacturer VARCHAR(100),
    expiration_date DATE,
    dose_number INT,
    series_complete BOOLEAN DEFAULT FALSE,
    
    -- Site and Route
    administration_site VARCHAR(50),
    administration_route VARCHAR(50),
    dose_amount DECIMAL(5,2),
    dose_unit VARCHAR(20),
    
    -- Provider
    administering_provider_id INT UNSIGNED,
    ordering_provider_id INT UNSIGNED,
    
    -- Reactions
    adverse_reaction BOOLEAN DEFAULT FALSE,
    reaction_description TEXT,
    
    -- VIS (Vaccine Information Statement)
    vis_date DATE,
    vis_given_date DATE,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    INDEX idx_patient (patient_id),
    INDEX idx_vaccine (vaccine_code),
    INDEX idx_date (administration_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medications
CREATE TABLE medications (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id INT UNSIGNED NOT NULL,
    encounter_id INT UNSIGNED,
    
    -- Drug Information
    drug_name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255),
    ndc_code VARCHAR(20),
    drug_class VARCHAR(100),
    
    -- Prescription Details
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    route VARCHAR(50),
    quantity_prescribed INT,
    refills_authorized INT,
    
    -- Duration
    start_date DATE NOT NULL,
    end_date DATE,
    days_supply INT,
    
    -- Prescriber
    prescriber_id INT UNSIGNED,
    dea_number VARCHAR(20),
    
    -- Status
    status ENUM('active', 'completed', 'discontinued', 'hold') DEFAULT 'active',
    discontinuation_reason TEXT,
    
    -- Pharmacy
    pharmacy_name VARCHAR(255),
    pharmacy_phone VARCHAR(20),
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (encounter_id) REFERENCES encounters(id),
    INDEX idx_patient (patient_id),
    INDEX idx_status (status),
    INDEX idx_drug (drug_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laboratory Results
CREATE TABLE lab_results (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id INT UNSIGNED NOT NULL,
    encounter_id INT UNSIGNED,
    
    -- Order Information
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_date DATETIME NOT NULL,
    ordering_provider_id INT UNSIGNED,
    
    -- Test Information
    test_code VARCHAR(20),
    test_name VARCHAR(255) NOT NULL,
    loinc_code VARCHAR(20),
    
    -- Results
    result_value VARCHAR(255),
    result_unit VARCHAR(50),
    reference_range VARCHAR(100),
    abnormal_flag ENUM('N', 'L', 'H', 'LL', 'HH', 'A') COMMENT 'Normal, Low, High, Critical Low, Critical High, Abnormal',
    
    -- Status
    result_status ENUM('pending', 'preliminary', 'final', 'corrected', 'cancelled') DEFAULT 'pending',
    result_date DATETIME,
    
    -- Lab Information
    performing_lab VARCHAR(255),
    lab_director VARCHAR(200),
    
    -- Clinical Significance
    clinical_notes TEXT,
    critical_value BOOLEAN DEFAULT FALSE,
    critical_value_called DATETIME,
    critical_value_called_to VARCHAR(200),
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (encounter_id) REFERENCES encounters(id),
    INDEX idx_patient (patient_id),
    INDEX idx_order (order_number),
    INDEX idx_test (test_code),
    INDEX idx_date (result_date),
    INDEX idx_abnormal (abnormal_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vital Signs (Extended)
CREATE TABLE vital_signs (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id INT UNSIGNED NOT NULL,
    encounter_id INT UNSIGNED,
    
    -- Basic Vitals
    measurement_datetime DATETIME NOT NULL,
    blood_pressure_systolic INT,
    blood_pressure_diastolic INT,
    heart_rate INT,
    respiratory_rate INT,
    temperature DECIMAL(4,1),
    temperature_unit ENUM('C', 'F') DEFAULT 'F',
    oxygen_saturation INT,
    oxygen_delivery VARCHAR(50) COMMENT 'Room air, nasal cannula, etc.',
    
    -- Additional Measurements
    height_cm DECIMAL(5,2),
    weight_kg DECIMAL(5,2),
    bmi DECIMAL(4,2),
    head_circumference_cm DECIMAL(5,2),
    waist_circumference_cm DECIMAL(5,2),
    
    -- Pain Assessment
    pain_scale INT COMMENT '0-10 scale',
    pain_location VARCHAR(255),
    
    -- Other
    blood_glucose INT,
    peak_flow INT,
    
    -- Position
    position ENUM('sitting', 'standing', 'supine', 'prone'),
    
    -- Metadata
    recorded_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (encounter_id) REFERENCES encounters(id),
    INDEX idx_patient (patient_id),
    INDEX idx_encounter (encounter_id),
    INDEX idx_datetime (measurement_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progress Notes
CREATE TABLE progress_notes (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id INT UNSIGNED NOT NULL,
    encounter_id INT UNSIGNED NOT NULL,
    
    -- Note Details
    note_type ENUM('progress', 'procedure', 'consultation', 'discharge', 'operative') NOT NULL,
    note_date DATETIME NOT NULL,
    
    -- SOAP Format
    subjective TEXT,
    objective TEXT,
    assessment TEXT,
    plan TEXT,
    
    -- Additional Sections
    history_present_illness TEXT,
    physical_exam TEXT,
    medical_decision_making TEXT,
    
    -- Provider
    author_id INT UNSIGNED NOT NULL,
    cosigner_id INT UNSIGNED,
    signed_date DATETIME,
    cosigned_date DATETIME,
    
    -- Status
    status ENUM('draft', 'signed', 'addendum', 'deleted') DEFAULT 'draft',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (encounter_id) REFERENCES encounters(id),
    INDEX idx_patient (patient_id),
    INDEX idx_encounter (encounter_id),
    INDEX idx_date (note_date),
    INDEX idx_type (note_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DOCUMENT MANAGEMENT
-- =====================================================

CREATE TABLE documents (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    patient_id INT UNSIGNED NOT NULL,
    encounter_id INT UNSIGNED,
    
    -- Document Information
    document_type VARCHAR(50) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500),
    file_size INT,
    mime_type VARCHAR(100),
    
    -- Versioning
    version_number INT DEFAULT 1,
    parent_document_id INT UNSIGNED COMMENT 'For version tracking',
    
    -- Security
    is_encrypted BOOLEAN DEFAULT TRUE,
    encryption_key_id VARCHAR(100),
    
    -- Metadata
    uploaded_date DATETIME NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    description TEXT,
    
    -- Status
    status ENUM('active', 'archived', 'deleted') DEFAULT 'active',
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (encounter_id) REFERENCES encounters(id),
    FOREIGN KEY (parent_document_id) REFERENCES documents(id),
    INDEX idx_patient (patient_id),
    INDEX idx_type (document_type),
    INDEX idx_date (uploaded_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AUDIT AND COMPLIANCE
-- =====================================================

CREATE TABLE audit_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    
    -- Change Details
    old_values JSON,
    new_values JSON,
    
    -- Context
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(128),
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_action (action),
    INDEX idx_timestamp (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- HIPAA Access Log
CREATE TABLE hipaa_access_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    
    -- Access Details
    access_type ENUM('view', 'create', 'update', 'delete', 'print', 'export') NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    resource_id INT UNSIGNED,
    
    -- Purpose
    access_purpose VARCHAR(100),
    
    -- Context
    ip_address VARCHAR(45),
    access_datetime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_patient (patient_id),
    INDEX idx_datetime (access_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SUPPORTING TABLES
-- =====================================================

-- Providers/Users (Extended for medical staff)
CREATE TABLE providers (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED UNIQUE NOT NULL COMMENT 'Links to main users table',
    
    -- Professional Information
    npi_number VARCHAR(20) UNIQUE,
    license_number VARCHAR(50),
    license_state CHAR(2),
    license_expiry DATE,
    
    -- Credentials
    degree VARCHAR(20),
    specialty VARCHAR(100),
    board_certifications JSON,
    
    -- DEA for prescriptions
    dea_number VARCHAR(20),
    dea_expiry DATE,
    
    -- Signature
    electronic_signature BLOB,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Facilities
CREATE TABLE facilities (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    facility_name VARCHAR(255) NOT NULL,
    facility_type ENUM('clinic', 'hospital', 'urgent_care', 'mobile_unit') NOT NULL,
    
    -- Address
    street VARCHAR(255),
    city VARCHAR(100),
    state CHAR(2),
    zip VARCHAR(10),
    
    -- Contact
    main_phone VARCHAR(20),
    fax VARCHAR(20),
    
    -- Identifiers
    facility_npi VARCHAR(20),
    tax_id VARCHAR(20),
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VIEWS FOR REPORTING
-- =====================================================

-- Active Work Injuries View
CREATE VIEW v_active_work_injuries AS
SELECT 
    ol.case_number,
    p.mrn,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    c.company_name,
    e.establishment_name,
    ol.date_of_injury_illness,
    ic.category_name,
    ol.days_away_from_work,
    ol.days_restricted_duty,
    ol.case_status
FROM osha_form_300_log ol
JOIN patients p ON ol.patient_id = p.id
JOIN companies c ON ol.company_id = c.id
JOIN establishments e ON ol.establishment_id = e.id
JOIN injury_illness_categories ic ON ol.injury_illness_category_id = ic.id
WHERE ol.case_status = 'open'
AND p.is_deleted = FALSE;

-- OSHA Summary Statistics View
CREATE VIEW v_osha_summary_stats AS
SELECT 
    c.company_name,
    e.establishment_name,
    s.reporting_year,
    s.annual_average_employees,
    s.total_hours_worked,
    s.total_deaths,
    s.total_cases_with_days_away,
    s.total_days_away_from_work,
    (s.total_cases_with_days_away + s.total_cases_with_job_transfer + s.total_other_recordable_cases) as total_recordable_cases,
    ROUND(((s.total_cases_with_days_away + s.total_cases_with_job_transfer + s.total_other_recordable_cases) * 200000.0) / s.total_hours_worked, 2) as trir
FROM osha_form_300a_summary s
JOIN companies c ON s.company_id = c.id
JOIN establishments e ON s.establishment_id = e.id
ORDER BY s.reporting_year DESC, c.company_name;

-- Patient Employment View
CREATE VIEW v_patient_employment AS
SELECT 
    p.id as patient_id,
    p.mrn,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    c.company_name,
    e.establishment_name,
    pe.job_title,
    pe.employment_status,
    pe.hire_date,
    pe.is_primary_employer
FROM patients p
JOIN patient_employers pe ON p.id = pe.patient_id
JOIN companies c ON pe.company_id = c.id
JOIN establishments e ON pe.establishment_id = e.id
WHERE p.is_deleted = FALSE
AND pe.employment_status = 'active';

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER $$

-- Generate OSHA 300A Summary
CREATE PROCEDURE sp_generate_osha_300a_summary(
    IN p_establishment_id INT,
    IN p_year YEAR
)
BEGIN
    DECLARE v_company_id INT;
    DECLARE v_total_deaths INT DEFAULT 0;
    DECLARE v_total_days_away INT DEFAULT 0;
    DECLARE v_total_job_transfer INT DEFAULT 0;
    DECLARE v_total_other INT DEFAULT 0;
    DECLARE v_days_away_count INT DEFAULT 0;
    DECLARE v_days_restricted_count INT DEFAULT 0;
    
    -- Get company ID
    SELECT company_id INTO v_company_id
    FROM establishments
    WHERE id = p_establishment_id;
    
    -- Calculate totals from Form 300 logs
    SELECT 
        COUNT(CASE WHEN death_date IS NOT NULL THEN 1 END),
        COUNT(CASE WHEN days_away_from_work > 0 THEN 1 END),
        COUNT(CASE WHEN days_job_transfer > 0 OR days_restricted_duty > 0 THEN 1 END),
        COUNT(CASE WHEN days_away_from_work = 0 AND days_job_transfer = 0 AND days_restricted_duty = 0 AND medical_treatment_beyond_first_aid = TRUE THEN 1 END),
        COALESCE(SUM(days_away_from_work), 0),
        COALESCE(SUM(days_restricted_duty + days_job_transfer), 0)
    INTO 
        v_total_deaths,
        v_total_days_away,
        v_total_job_transfer,
        v_total_other,
        v_days_away_count,
        v_days_restricted_count
    FROM osha_form_300_log
    WHERE establishment_id = p_establishment_id
    AND YEAR(date_of_injury_illness) = p_year;
    
    -- Insert or update summary
    INSERT INTO osha_form_300a_summary (
        company_id,
        establishment_id,
        reporting_year,
        total_deaths,
        total_cases_with_days_away,
        total_cases_with_job_transfer,
        total_other_recordable_cases,
        total_days_away_from_work,
        total_days_job_transfer_restriction,
        annual_average_employees,
        total_hours_worked,
        created_by
    ) VALUES (
        v_company_id,
        p_establishment_id,
        p_year,
        v_total_deaths,
        v_total_days_away,
        v_total_job_transfer,
        v_total_other,
        v_days_away_count,
        v_days_restricted_count,
        0, -- To be updated manually
        0, -- To be updated manually
        1  -- System user
    )
    ON DUPLICATE KEY UPDATE
        total_deaths = v_total_deaths,
        total_cases_with_days_away = v_total_days_away,
        total_cases_with_job_transfer = v_total_job_transfer,
        total_other_recordable_cases = v_total_other,
        total_days_away_from_work = v_days_away_count,
        total_days_job_transfer_restriction = v_days_restricted_count,
        updated_at = CURRENT_TIMESTAMP,
        updated_by = 1;
END$$

-- Create Work Injury Case
CREATE PROCEDURE sp_create_work_injury_case(
    IN p_encounter_id INT,
    IN p_patient_id INT,
    IN p_company_id INT,
    IN p_establishment_id INT,
    IN p_injury_date DATE,
    IN p_injury_time TIME,
    IN p_location VARCHAR(255),
    IN p_description TEXT,
    IN p_category_id INT,
    IN p_body_part VARCHAR(100),
    IN p_created_by INT
)
BEGIN
    DECLARE v_case_number VARCHAR(20);
    DECLARE v_year VARCHAR(4);
    DECLARE v_seq INT;
    
    -- Generate case number (YYYY-XXXXXX format)
    SET v_year = YEAR(p_injury_date);
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(case_number, 6) AS UNSIGNED)), 0) + 1
    INTO v_seq
    FROM osha_form_300_log
    WHERE case_number LIKE CONCAT(v_year, '-%');
    
    SET v_case_number = CONCAT(v_year, '-', LPAD(v_seq, 6, '0'));
    
    -- Insert the case
    INSERT INTO osha_form_300_log (
        case_number,
        encounter_id,
        patient_id,
        company_id,
        establishment_id,
        date_of_injury_illness,
        time_of_event,
        location_of_incident,
        description_of_incident,
        injury_illness_category_id,
        body_part_affected,
        created_by
    ) VALUES (
        v_case_number,
        p_encounter_id,
        p_patient_id,
        p_company_id,
        p_establishment_id,
        p_injury_date,
        p_injury_time,
        p_location,
        p_description,
        p_category_id,
        p_body_part,
        p_created_by
    );
    
    SELECT LAST_INSERT_ID() as case_id, v_case_number as case_number;
END$$

DELIMITER ;

-- =====================================================
-- TRIGGERS FOR AUDIT TRAIL
-- =====================================================

DELIMITER $$

-- Audit trigger for patients table
CREATE TRIGGER trg_patients_audit_update
AFTER UPDATE ON patients
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values)
    VALUES (
        COALESCE(NEW.updated_by, 0),
        'UPDATE',
        'patients',
        NEW.id,
        JSON_OBJECT(
            'first_name', OLD.first_name,
            'last_name', OLD.last_name,
            'date_of_birth', OLD.date_of_birth,
            'ssn_encrypted', OLD.ssn_encrypted
        ),
        JSON_OBJECT(
            'first_name', NEW.first_name,
            'last_name', NEW.last_name,
            'date_of_birth', NEW.date_of_birth,
            'ssn_encrypted', NEW.ssn_encrypted
        )
    );
END$$

-- HIPAA access logging trigger
CREATE TRIGGER trg_patient_access_log
AFTER SELECT ON patients
FOR EACH ROW
BEGIN
    -- This is a conceptual trigger - actual implementation would be in application layer
    -- MySQL doesn't support SELECT triggers directly
    NULL;
END$$

DELIMITER ;

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional performance indexes
CREATE INDEX idx_encounters_date_patient ON encounters(encounter_date, patient_id);
CREATE INDEX idx_osha_log_date_company ON osha_form_300_log(date_of_injury_illness, company_id);
CREATE INDEX idx_lab_results_patient_date ON lab_results(patient_id, result_date);
CREATE INDEX idx_medications_patient_status ON medications(patient_id, status);
CREATE INDEX idx_audit_user_date ON audit_log(user_id, created_at);

-- =====================================================
-- INITIAL DATA INSERTS
-- =====================================================

-- Insert company sizes
INSERT INTO company_sizes (id, description, employee_range) VALUES
(1, 'Small', '1-19'),
(21, 'Medium', '20-99'),
(22, 'Large', '100-249'),
(3, 'Extra Large', '250+');

-- Insert establishment types
INSERT INTO establishment_types (id, description) VALUES
(1, 'Private Industry'),
(2, 'State Government'),
(3, 'Local Government');

-- Insert injury/illness categories
INSERT INTO injury_illness_categories (category_code, category_name, description, is_injury) VALUES
('INJ', 'Injuries', 'Physical injuries from workplace accidents', TRUE),
('SKIN', 'Skin Disorders', 'Occupational skin diseases or disorders', FALSE),
('RESP', 'Respiratory Conditions', 'Occupational respiratory conditions', FALSE),
('POIS', 'Poisoning', 'Occupational poisonings', FALSE),
('HEAR', 'Hearing Loss', 'Occupational hearing loss', FALSE),
('OTHER', 'All Other Illnesses', 'All other occupational illnesses', FALSE);

-- Insert encounter types
INSERT INTO encounter_types (code, name, category, is_work_related, requires_osha_reporting) VALUES
('WI', 'Work Injury', 'injury', TRUE, TRUE),
('WIL', 'Work Illness', 'illness', TRUE, TRUE),
('PE', 'Pre-Employment Exam', 'exam', FALSE, FALSE),
('APE', 'Annual Physical Exam', 'exam', FALSE, FALSE),
('DOT', 'DOT Physical', 'dot', FALSE, FALSE),
('DOTD', 'DOT Drug Screen', 'dot', FALSE, FALSE),
('FUP', 'Follow-up Visit', 'exam', FALSE, FALSE),
('SURV', 'Surveillance Exam', 'surveillance', TRUE, FALSE);

-- Insert DOT test types
INSERT INTO dot_test_types (code, name, cfr_reference) VALUES
('PRE', 'Pre-employment', '49 CFR 382.301'),
('RAND', 'Random', '49 CFR 382.305'),
('POST', 'Post-accident', '49 CFR 382.303'),
('REAS', 'Reasonable Suspicion', '49 CFR 382.307'),
('RTD', 'Return-to-duty', '49 CFR 382.309'),
('FUP', 'Follow-up', '49 CFR 382.311');

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- GRANT PERMISSIONS (Adjust based on your user roles)
-- =====================================================

-- Create application user if not exists
-- CREATE USER IF NOT EXISTS 'osha_ehr_app'@'localhost' IDENTIFIED BY 'secure_password_here';

-- Grant permissions
-- GRANT SELECT, INSERT, UPDATE, DELETE ON osha_ehr_db.* TO 'osha_ehr_app'@'localhost';
-- GRANT EXECUTE ON osha_ehr_db.* TO 'osha_ehr_app'@'localhost';

-- FLUSH PRIVILEGES;