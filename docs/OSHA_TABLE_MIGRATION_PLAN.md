# OSHA Table Migration Plan (300a, 300_log, 301)

## Executive Summary

This document outlines the migration strategy to enhance the existing OSHA tables (`300a`, `300_log`, and `301`) to become fully OSHA-compliant while **preserving all existing data and columns**. The migration adds missing OSHA-required fields from the canonical schema defined in [`database/osha_ehr_schema.sql`](../database/osha_ehr_schema.sql).

### Key Principles
1. **No columns will be dropped** - All existing columns are preserved
2. **No tables will be renamed** - Existing table names (`300a`, `300_log`, `301`) remain unchanged
3. **Existing primary keys preserved** - `form301_id`, `form300line_id`, and `Id` remain as primary keys
4. **New columns are NULLABLE** - Unless a sensible default is provided, to prevent breaking existing data
5. **Foreign key references** - Will use soft references due to UUID vs INT primary key differences

### Tables Affected
| Table | Current State | Target State |
|-------|---------------|--------------|
| `301` | 7 columns | 21 columns |
| `300_log` | 10 columns | 31 columns |
| `300a` | 17 columns | 27 columns |

---

## Table-by-Table Analysis

### 1. Table `301` (Form 301 - Injury/Illness Incident Report)

#### Current Schema
```sql
CREATE TABLE `301` (
  `form301_id` char(36) NOT NULL,           -- PRIMARY KEY (KEEP)
  `osha_case_id` char(36) NOT NULL,         -- (KEEP)
  `status` varchar(32) DEFAULT NULL,        -- (KEEP)
  `storage_uri` varchar(1024) DEFAULT NULL, -- (KEEP)
  `sha256` char(64) DEFAULT NULL,           -- (KEEP)
  `created_at` timestamp NOT NULL,          -- (KEEP)
  `updated_at` timestamp NOT NULL,          -- (KEEP)
  PRIMARY KEY (`form301_id`)
);
```

#### Columns to ADD
| Column Name | Data Type | Default | OSHA Purpose |
|-------------|-----------|---------|--------------|
| `employee_treated_in_emergency` | BOOLEAN | FALSE | Was employee treated in emergency room? |
| `employee_hospitalized_overnight` | BOOLEAN | FALSE | Was employee hospitalized overnight? |
| `witness_name` | VARCHAR(200) | NULL | Name of witness to incident |
| `witness_phone` | VARCHAR(20) | NULL | Phone number of witness |
| `physician_name` | VARCHAR(200) | NULL | Treating physician name |
| `physician_facility` | VARCHAR(255) | NULL | Medical facility name |
| `physician_phone` | VARCHAR(20) | NULL | Physician contact phone |
| `treatment_provided` | TEXT | NULL | Description of treatment provided |
| `root_cause` | TEXT | NULL | Root cause analysis |
| `corrective_actions` | TEXT | NULL | Corrective actions taken |
| `investigated_by` | VARCHAR(200) | NULL | Person who investigated |
| `investigation_date` | DATE | NULL | Date of investigation |
| `investigation_findings` | TEXT | NULL | Investigation findings/conclusions |
| `created_by` | INT UNSIGNED | NULL | User who created the record |
| `updated_by` | INT UNSIGNED | NULL | User who last updated the record |

#### Existing Columns Preserved (7 columns)
- `form301_id` - Primary key (UUID format)
- `osha_case_id` - Links to 300_log entry
- `status` - Document status
- `storage_uri` - Document storage location
- `sha256` - Document hash for integrity
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

---

### 2. Table `300_log` (Form 300 - Log of Work-Related Injuries and Illnesses)

#### Current Schema
```sql
CREATE TABLE `300_log` (
  `form300line_id` char(36) NOT NULL,       -- PRIMARY KEY (KEEP)
  `employer_id` char(36) NOT NULL,          -- (KEEP)
  `calendar_year` int(11) NOT NULL,         -- (KEEP)
  `osha_case_id` char(36) NOT NULL,         -- (KEEP)
  `category` varchar(64) DEFAULT NULL,      -- (KEEP)
  `days_away` tinyint(1) DEFAULT 0,         -- (KEEP)
  `job_transfer_restriction` tinyint(1) DEFAULT 0, -- (KEEP)
  `death` tinyint(1) DEFAULT 0,             -- (KEEP)
  `created_at` timestamp NOT NULL,          -- (KEEP)
  `updated_at` timestamp NOT NULL,          -- (KEEP)
  PRIMARY KEY (`form300line_id`)
);
```

#### Columns to ADD
| Column Name | Data Type | Default | OSHA Purpose |
|-------------|-----------|---------|--------------|
| `case_number` | VARCHAR(20) | NULL | OSHA case number (format: YYYY-XXXXXX) |
| `encounter_id` | CHAR(36) | NULL | Link to clinical encounter |
| `patient_id` | CHAR(36) | NULL | Link to patient record |
| `establishment_id` | CHAR(36) | NULL | Specific work location |
| `employee_name` | VARCHAR(255) | NULL | Employee name (may be privacy masked) |
| `job_title` | VARCHAR(255) | NULL | Employee job title |
| `date_of_injury_illness` | DATE | NULL | Date injury/illness occurred |
| `time_of_event` | TIME | NULL | Time of incident |
| `location_of_incident` | VARCHAR(255) | NULL | Where incident occurred |
| `description_of_incident` | TEXT | NULL | Detailed incident description |
| `injury_illness_category_id` | INT UNSIGNED | NULL | Reference to category lookup |
| `body_part_affected` | VARCHAR(100) | NULL | Body part injured |
| `object_substance` | VARCHAR(255) | NULL | Object/substance causing harm |
| `death_date` | DATE | NULL | Date of death (if applicable) |
| `days_away_from_work` | INT | 0 | Total days away from work |
| `days_restricted_duty` | INT | 0 | Days on restricted duty |
| `days_job_transfer` | INT | 0 | Days of job transfer |
| `medical_treatment_beyond_first_aid` | BOOLEAN | FALSE | Treatment beyond first aid? |
| `is_privacy_case` | BOOLEAN | FALSE | OSHA privacy case flag |
| `privacy_case_reason` | TEXT | NULL | Reason for privacy designation |
| `case_status` | ENUM | 'open' | Status: open/closed/amended |
| `created_by` | INT UNSIGNED | NULL | User who created record |
| `updated_by` | INT UNSIGNED | NULL | User who last updated |

#### Existing Columns Preserved (10 columns)
- `form300line_id` - Primary key (UUID format)
- `employer_id` - Company/employer reference
- `calendar_year` - Reporting year
- `osha_case_id` - Case identifier
- `category` - Injury/illness category
- `days_away` - Boolean: has days away
- `job_transfer_restriction` - Boolean: has job transfer/restriction
- `death` - Boolean: resulted in death
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

#### Data Migration Notes
- `days_away_from_work` should be populated based on existing `days_away` flag where possible
- `days_job_transfer` should be populated based on existing `job_transfer_restriction` flag
- Existing `death` boolean extended by new `death_date` field

---

### 3. Table `300a` (Form 300A - Summary of Work-Related Injuries and Illnesses)

#### Current Schema
```sql
CREATE TABLE `300a` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,     -- PRIMARY KEY (KEEP)
  `annual_average_employees` int(10) NOT NULL,  -- (KEEP)
  `total_hours_worked` int(10) NOT NULL,    -- (KEEP)
  `no_injuries_illnesses` int(1) NOT NULL,  -- (KEEP)
  `total_deaths` int(10) NOT NULL,          -- (KEEP)
  `total_dafw_cases` int(10) NOT NULL,      -- (KEEP)
  `total_djtr_cases` int(10) NOT NULL,      -- (KEEP)
  `total_other_cases` int(10) NOT NULL,     -- (KEEP)
  `total_dafw_days` int(10) NOT NULL,       -- (KEEP)
  `total_djtr_days` int(10) NOT NULL,       -- (KEEP)
  `total_injuries` int(10) NOT NULL,        -- (KEEP)
  `total_skin_disorders` int(10) NOT NULL,  -- (KEEP)
  `total_respiratory_conditions` int(10) NOT NULL, -- (KEEP)
  `total_poisonings` int(10) NOT NULL,      -- (KEEP)
  `total_hearing_loss` int(10) NOT NULL,    -- (KEEP)
  `total_other_illnesses` int(10) NOT NULL, -- (KEEP)
  `change_reason` char(100) NOT NULL,       -- (KEEP)
  `establishment_id` int(11) NOT NULL,      -- (KEEP)
  `year_filing_for` int(11) NOT NULL,       -- (KEEP)
  `errors` int(11) NOT NULL,                -- (KEEP - custom field)
  `warnings` int(11) NOT NULL,              -- (KEEP - custom field)
  `links` int(11) NOT NULL,                 -- (KEEP - custom field)
  PRIMARY KEY (`Id`)
);
```

#### Columns to ADD
| Column Name | Data Type | Default | OSHA Purpose |
|-------------|-----------|---------|--------------|
| `company_id` | CHAR(36) | NULL | Link to company/employer |
| `certified_by_name` | VARCHAR(200) | NULL | Name of certifying official |
| `certified_by_title` | VARCHAR(100) | NULL | Title of certifying official |
| `certified_date` | DATE | NULL | Date of certification |
| `submitted_to_osha` | BOOLEAN | FALSE | Has been submitted to OSHA? |
| `submission_date` | DATETIME | NULL | Date/time of OSHA submission |
| `submission_confirmation` | VARCHAR(100) | NULL | OSHA confirmation number |
| `created_at` | TIMESTAMP | CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | CURRENT_TIMESTAMP ON UPDATE | Last update time |
| `created_by` | INT UNSIGNED | NULL | User who created record |
| `updated_by` | INT UNSIGNED | NULL | User who last updated |

#### Existing Columns Preserved (17 columns)
All existing columns preserved - see current schema above.

#### Column Mapping Reference
| Current Column | OSHA Standard Column |
|----------------|---------------------|
| `annual_average_employees` | Same name |
| `total_hours_worked` | Same name |
| `total_deaths` | Same name |
| `total_dafw_cases` | `total_cases_with_days_away` |
| `total_djtr_cases` | `total_cases_with_job_transfer` |
| `total_other_cases` | `total_other_recordable_cases` |
| `total_dafw_days` | `total_days_away_from_work` |
| `total_djtr_days` | `total_days_job_transfer_restriction` |
| `total_injuries` | `injuries_count` |
| `total_skin_disorders` | `skin_disorders_count` |
| `total_respiratory_conditions` | `respiratory_conditions_count` |
| `total_poisonings` | `poisonings_count` |
| `total_hearing_loss` | `hearing_loss_count` |
| `total_other_illnesses` | `all_other_illnesses_count` |
| `year_filing_for` | `reporting_year` |

---

## Foreign Key Strategy

### Challenge
The existing tables use `CHAR(36)` (UUID) primary keys, while the OSHA reference schema uses `INT UNSIGNED AUTO_INCREMENT`. Direct foreign key constraints would fail due to type mismatch.

### Solution: Soft References
Instead of hard foreign key constraints, we will:
1. Add columns with the appropriate UUID type (`CHAR(36)`) to match existing conventions
2. Create indexes on reference columns for query performance
3. Document the logical relationships
4. Enforce referential integrity at the application layer

### Reference Relationships
```
301.osha_case_id -----> 300_log.osha_case_id (existing)
300_log.employer_id --> Company table (employer_id CHAR(36))
300_log.encounter_id -> encounters table (encounter_id CHAR(36))
300_log.patient_id ---> patients table (patient_id CHAR(36))
300a.company_id ------> Company table (employer_id CHAR(36))
300a.establishment_id > establishments table (if exists)
```

---

## SQL Migration Scripts

### Execution Order
1. Add columns to `300_log` first (parent for 301)
2. Add columns to `301` second (child of 300_log)
3. Add columns to `300a` third (aggregation table)
4. Create indexes
5. Verify migration

---

### Script 1: Migrate `300_log` Table

```sql
-- =====================================================
-- Migration Script: 300_log Table Enhancement
-- Version: 1.0.0
-- Date: 2026-01-12
-- Description: Add OSHA-compliant columns to 300_log
-- IMPORTANT: Do not remove any existing columns
-- =====================================================

-- Start transaction for safety
START TRANSACTION;

-- Add OSHA case number (unique identifier format YYYY-XXXXXX)
ALTER TABLE `300_log`
ADD COLUMN `case_number` VARCHAR(20) DEFAULT NULL
COMMENT 'OSHA case number format: YYYY-XXXXXX'
AFTER `osha_case_id`;

-- Add clinical encounter reference
ALTER TABLE `300_log`
ADD COLUMN `encounter_id` CHAR(36) DEFAULT NULL
COMMENT 'Reference to clinical encounter'
AFTER `case_number`;

-- Add patient reference
ALTER TABLE `300_log`
ADD COLUMN `patient_id` CHAR(36) DEFAULT NULL
COMMENT 'Reference to patient record'
AFTER `encounter_id`;

-- Add establishment reference
ALTER TABLE `300_log`
ADD COLUMN `establishment_id` CHAR(36) DEFAULT NULL
COMMENT 'Reference to specific work location'
AFTER `employer_id`;

-- Add employee information
ALTER TABLE `300_log`
ADD COLUMN `employee_name` VARCHAR(255) DEFAULT NULL
COMMENT 'Employee name - may be privacy masked'
AFTER `establishment_id`;

ALTER TABLE `300_log`
ADD COLUMN `job_title` VARCHAR(255) DEFAULT NULL
COMMENT 'Employee job title at time of incident'
AFTER `employee_name`;

-- Add incident details
ALTER TABLE `300_log`
ADD COLUMN `date_of_injury_illness` DATE DEFAULT NULL
COMMENT 'Date when injury/illness occurred'
AFTER `job_title`;

ALTER TABLE `300_log`
ADD COLUMN `time_of_event` TIME DEFAULT NULL
COMMENT 'Time when incident occurred'
AFTER `date_of_injury_illness`;

ALTER TABLE `300_log`
ADD COLUMN `location_of_incident` VARCHAR(255) DEFAULT NULL
COMMENT 'Where on premises the incident occurred'
AFTER `time_of_event`;

ALTER TABLE `300_log`
ADD COLUMN `description_of_incident` TEXT DEFAULT NULL
COMMENT 'Detailed description of what happened'
AFTER `location_of_incident`;

-- Add injury classification
ALTER TABLE `300_log`
ADD COLUMN `injury_illness_category_id` INT UNSIGNED DEFAULT NULL
COMMENT 'Reference to injury_illness_categories lookup table'
AFTER `category`;

ALTER TABLE `300_log`
ADD COLUMN `body_part_affected` VARCHAR(100) DEFAULT NULL
COMMENT 'Body part that was injured'
AFTER `injury_illness_category_id`;

ALTER TABLE `300_log`
ADD COLUMN `object_substance` VARCHAR(255) DEFAULT NULL
COMMENT 'Object or substance that directly caused harm'
AFTER `body_part_affected`;

-- Add outcome details (extends existing boolean fields)
ALTER TABLE `300_log`
ADD COLUMN `death_date` DATE DEFAULT NULL
COMMENT 'Date of death if death=1'
AFTER `death`;

ALTER TABLE `300_log`
ADD COLUMN `days_away_from_work` INT DEFAULT 0
COMMENT 'Total number of days away from work'
AFTER `days_away`;

ALTER TABLE `300_log`
ADD COLUMN `days_restricted_duty` INT DEFAULT 0
COMMENT 'Total days on restricted duty'
AFTER `days_away_from_work`;

ALTER TABLE `300_log`
ADD COLUMN `days_job_transfer` INT DEFAULT 0
COMMENT 'Total days of job transfer'
AFTER `job_transfer_restriction`;

-- Add medical treatment flag
ALTER TABLE `300_log`
ADD COLUMN `medical_treatment_beyond_first_aid` BOOLEAN DEFAULT FALSE
COMMENT 'Treatment beyond first aid was required'
AFTER `days_job_transfer`;

-- Add privacy case fields
ALTER TABLE `300_log`
ADD COLUMN `is_privacy_case` BOOLEAN DEFAULT FALSE
COMMENT 'OSHA privacy case flag'
AFTER `medical_treatment_beyond_first_aid`;

ALTER TABLE `300_log`
ADD COLUMN `privacy_case_reason` TEXT DEFAULT NULL
COMMENT 'Reason for privacy case designation'
AFTER `is_privacy_case`;

-- Add case status
ALTER TABLE `300_log`
ADD COLUMN `case_status` ENUM('open', 'closed', 'amended') DEFAULT 'open'
COMMENT 'Current status of the case'
AFTER `privacy_case_reason`;

-- Add audit tracking
ALTER TABLE `300_log`
ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL
COMMENT 'User ID who created the record'
AFTER `updated_at`;

ALTER TABLE `300_log`
ADD COLUMN `updated_by` INT UNSIGNED DEFAULT NULL
COMMENT 'User ID who last updated the record'
AFTER `created_by`;

-- Create indexes for performance
CREATE INDEX `idx_300log_case_number` ON `300_log` (`case_number`);
CREATE INDEX `idx_300log_date` ON `300_log` (`date_of_injury_illness`);
CREATE INDEX `idx_300log_employer` ON `300_log` (`employer_id`);
CREATE INDEX `idx_300log_establishment` ON `300_log` (`establishment_id`);
CREATE INDEX `idx_300log_patient` ON `300_log` (`patient_id`);
CREATE INDEX `idx_300log_status` ON `300_log` (`case_status`);
CREATE INDEX `idx_300log_category` ON `300_log` (`injury_illness_category_id`);

COMMIT;

-- Verification
SELECT 
    COUNT(*) as total_columns,
    (SELECT COUNT(*) FROM `300_log`) as total_rows
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = '300_log';
```

---

### Script 2: Migrate `301` Table

```sql
-- =====================================================
-- Migration Script: 301 Table Enhancement
-- Version: 1.0.0
-- Date: 2026-01-12
-- Description: Add OSHA-compliant columns to 301
-- IMPORTANT: Do not remove any existing columns
-- =====================================================

-- Start transaction for safety
START TRANSACTION;

-- Add emergency treatment flags
ALTER TABLE `301`
ADD COLUMN `employee_treated_in_emergency` BOOLEAN DEFAULT FALSE
COMMENT 'Was employee treated in emergency room'
AFTER `status`;

ALTER TABLE `301`
ADD COLUMN `employee_hospitalized_overnight` BOOLEAN DEFAULT FALSE
COMMENT 'Was employee hospitalized overnight as inpatient'
AFTER `employee_treated_in_emergency`;

-- Add witness information
ALTER TABLE `301`
ADD COLUMN `witness_name` VARCHAR(200) DEFAULT NULL
COMMENT 'Name of person who witnessed the incident'
AFTER `employee_hospitalized_overnight`;

ALTER TABLE `301`
ADD COLUMN `witness_phone` VARCHAR(20) DEFAULT NULL
COMMENT 'Phone number of witness'
AFTER `witness_name`;

-- Add physician/medical facility information
ALTER TABLE `301`
ADD COLUMN `physician_name` VARCHAR(200) DEFAULT NULL
COMMENT 'Name of treating physician or healthcare professional'
AFTER `witness_phone`;

ALTER TABLE `301`
ADD COLUMN `physician_facility` VARCHAR(255) DEFAULT NULL
COMMENT 'Name of medical facility where treated'
AFTER `physician_name`;

ALTER TABLE `301`
ADD COLUMN `physician_phone` VARCHAR(20) DEFAULT NULL
COMMENT 'Phone number of physician/facility'
AFTER `physician_facility`;

ALTER TABLE `301`
ADD COLUMN `treatment_provided` TEXT DEFAULT NULL
COMMENT 'Description of medical treatment provided'
AFTER `physician_phone`;

-- Add root cause analysis fields
ALTER TABLE `301`
ADD COLUMN `root_cause` TEXT DEFAULT NULL
COMMENT 'Root cause analysis of the incident'
AFTER `treatment_provided`;

ALTER TABLE `301`
ADD COLUMN `corrective_actions` TEXT DEFAULT NULL
COMMENT 'Corrective actions taken to prevent recurrence'
AFTER `root_cause`;

-- Add investigation fields
ALTER TABLE `301`
ADD COLUMN `investigated_by` VARCHAR(200) DEFAULT NULL
COMMENT 'Name of person who investigated the incident'
AFTER `corrective_actions`;

ALTER TABLE `301`
ADD COLUMN `investigation_date` DATE DEFAULT NULL
COMMENT 'Date investigation was conducted'
AFTER `investigated_by`;

ALTER TABLE `301`
ADD COLUMN `investigation_findings` TEXT DEFAULT NULL
COMMENT 'Findings and conclusions from investigation'
AFTER `investigation_date`;

-- Add audit tracking
ALTER TABLE `301`
ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL
COMMENT 'User ID who created the record'
AFTER `updated_at`;

ALTER TABLE `301`
ADD COLUMN `updated_by` INT UNSIGNED DEFAULT NULL
COMMENT 'User ID who last updated the record'
AFTER `created_by`;

-- Create indexes for performance
CREATE INDEX `idx_301_osha_case` ON `301` (`osha_case_id`);
CREATE INDEX `idx_301_investigation_date` ON `301` (`investigation_date`);
CREATE INDEX `idx_301_status` ON `301` (`status`);

COMMIT;

-- Verification
SELECT 
    COUNT(*) as total_columns,
    (SELECT COUNT(*) FROM `301`) as total_rows
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = '301';
```

---

### Script 3: Migrate `300a` Table

```sql
-- =====================================================
-- Migration Script: 300a Table Enhancement
-- Version: 1.0.0
-- Date: 2026-01-12
-- Description: Add OSHA-compliant columns to 300a
-- IMPORTANT: Do not remove any existing columns
-- =====================================================

-- Start transaction for safety
START TRANSACTION;

-- Add company reference
ALTER TABLE `300a`
ADD COLUMN `company_id` CHAR(36) DEFAULT NULL
COMMENT 'Reference to company/employer table'
AFTER `Id`;

-- Add certification fields
ALTER TABLE `300a`
ADD COLUMN `certified_by_name` VARCHAR(200) DEFAULT NULL
COMMENT 'Name of company executive who certifies the form'
AFTER `links`;

ALTER TABLE `300a`
ADD COLUMN `certified_by_title` VARCHAR(100) DEFAULT NULL
COMMENT 'Title of certifying official'
AFTER `certified_by_name`;

ALTER TABLE `300a`
ADD COLUMN `certified_date` DATE DEFAULT NULL
COMMENT 'Date when form was certified'
AFTER `certified_by_title`;

-- Add OSHA submission tracking
ALTER TABLE `300a`
ADD COLUMN `submitted_to_osha` BOOLEAN DEFAULT FALSE
COMMENT 'Has this summary been submitted to OSHA'
AFTER `certified_date`;

ALTER TABLE `300a`
ADD COLUMN `submission_date` DATETIME DEFAULT NULL
COMMENT 'Date and time of OSHA submission'
AFTER `submitted_to_osha`;

ALTER TABLE `300a`
ADD COLUMN `submission_confirmation` VARCHAR(100) DEFAULT NULL
COMMENT 'OSHA submission confirmation number'
AFTER `submission_date`;

-- Add timestamps
ALTER TABLE `300a`
ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
COMMENT 'Record creation timestamp'
AFTER `submission_confirmation`;

ALTER TABLE `300a`
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
COMMENT 'Record last update timestamp'
AFTER `created_at`;

-- Add audit tracking
ALTER TABLE `300a`
ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL
COMMENT 'User ID who created the record'
AFTER `updated_at`;

ALTER TABLE `300a`
ADD COLUMN `updated_by` INT UNSIGNED DEFAULT NULL
COMMENT 'User ID who last updated the record'
AFTER `created_by`;

-- Create indexes for performance
CREATE INDEX `idx_300a_company` ON `300a` (`company_id`);
CREATE INDEX `idx_300a_establishment` ON `300a` (`establishment_id`);
CREATE INDEX `idx_300a_year` ON `300a` (`year_filing_for`);
CREATE INDEX `idx_300a_submission` ON `300a` (`submitted_to_osha`);

-- Add unique constraint for establishment + year combination
ALTER TABLE `300a`
ADD UNIQUE INDEX `uk_300a_establishment_year` (`establishment_id`, `year_filing_for`);

COMMIT;

-- Verification
SELECT 
    COUNT(*) as total_columns,
    (SELECT COUNT(*) FROM `300a`) as total_rows
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = '300a';
```

---

## Rollback Scripts

### Complete Rollback (All Tables)

```sql
-- =====================================================
-- Rollback Script: OSHA Tables Migration
-- Version: 1.0.0
-- Date: 2026-01-12
-- WARNING: This will remove all newly added columns
-- =====================================================

-- Rollback 301 Table
START TRANSACTION;

ALTER TABLE `301`
DROP COLUMN IF EXISTS `employee_treated_in_emergency`,
DROP COLUMN IF EXISTS `employee_hospitalized_overnight`,
DROP COLUMN IF EXISTS `witness_name`,
DROP COLUMN IF EXISTS `witness_phone`,
DROP COLUMN IF EXISTS `physician_name`,
DROP COLUMN IF EXISTS `physician_facility`,
DROP COLUMN IF EXISTS `physician_phone`,
DROP COLUMN IF EXISTS `treatment_provided`,
DROP COLUMN IF EXISTS `root_cause`,
DROP COLUMN IF EXISTS `corrective_actions`,
DROP COLUMN IF EXISTS `investigated_by`,
DROP COLUMN IF EXISTS `investigation_date`,
DROP COLUMN IF EXISTS `investigation_findings`,
DROP COLUMN IF EXISTS `created_by`,
DROP COLUMN IF EXISTS `updated_by`;

DROP INDEX IF EXISTS `idx_301_osha_case` ON `301`;
DROP INDEX IF EXISTS `idx_301_investigation_date` ON `301`;
DROP INDEX IF EXISTS `idx_301_status` ON `301`;

COMMIT;

-- Rollback 300_log Table
START TRANSACTION;

ALTER TABLE `300_log`
DROP COLUMN IF EXISTS `case_number`,
DROP COLUMN IF EXISTS `encounter_id`,
DROP COLUMN IF EXISTS `patient_id`,
DROP COLUMN IF EXISTS `establishment_id`,
DROP COLUMN IF EXISTS `employee_name`,
DROP COLUMN IF EXISTS `job_title`,
DROP COLUMN IF EXISTS `date_of_injury_illness`,
DROP COLUMN IF EXISTS `time_of_event`,
DROP COLUMN IF EXISTS `location_of_incident`,
DROP COLUMN IF EXISTS `description_of_incident`,
DROP COLUMN IF EXISTS `injury_illness_category_id`,
DROP COLUMN IF EXISTS `body_part_affected`,
DROP COLUMN IF EXISTS `object_substance`,
DROP COLUMN IF EXISTS `death_date`,
DROP COLUMN IF EXISTS `days_away_from_work`,
DROP COLUMN IF EXISTS `days_restricted_duty`,
DROP COLUMN IF EXISTS `days_job_transfer`,
DROP COLUMN IF EXISTS `medical_treatment_beyond_first_aid`,
DROP COLUMN IF EXISTS `is_privacy_case`,
DROP COLUMN IF EXISTS `privacy_case_reason`,
DROP COLUMN IF EXISTS `case_status`,
DROP COLUMN IF EXISTS `created_by`,
DROP COLUMN IF EXISTS `updated_by`;

DROP INDEX IF EXISTS `idx_300log_case_number` ON `300_log`;
DROP INDEX IF EXISTS `idx_300log_date` ON `300_log`;
DROP INDEX IF EXISTS `idx_300log_employer` ON `300_log`;
DROP INDEX IF EXISTS `idx_300log_establishment` ON `300_log`;
DROP INDEX IF EXISTS `idx_300log_patient` ON `300_log`;
DROP INDEX IF EXISTS `idx_300log_status` ON `300_log`;
DROP INDEX IF EXISTS `idx_300log_category` ON `300_log`;

COMMIT;

-- Rollback 300a Table
START TRANSACTION;

ALTER TABLE `300a`
DROP COLUMN IF EXISTS `company_id`,
DROP COLUMN IF EXISTS `certified_by_name`,
DROP COLUMN IF EXISTS `certified_by_title`,
DROP COLUMN IF EXISTS `certified_date`,
DROP COLUMN IF EXISTS `submitted_to_osha`,
DROP COLUMN IF EXISTS `submission_date`,
DROP COLUMN IF EXISTS `submission_confirmation`,
DROP COLUMN IF EXISTS `created_at`,
DROP COLUMN IF EXISTS `updated_at`,
DROP COLUMN IF EXISTS `created_by`,
DROP COLUMN IF EXISTS `updated_by`;

DROP INDEX IF EXISTS `idx_300a_company` ON `300a`;
DROP INDEX IF EXISTS `idx_300a_submission` ON `300a`;
DROP INDEX IF EXISTS `uk_300a_establishment_year` ON `300a`;

COMMIT;
```

---

## Verification Queries

### Post-Migration Verification Script

```sql
-- =====================================================
-- Verification Queries: OSHA Tables Migration
-- Run after migration to confirm success
-- =====================================================

-- 1. Verify 301 table structure
SELECT '--- TABLE 301 STRUCTURE ---' as '';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = '301'
ORDER BY ORDINAL_POSITION;

-- 2. Verify 300_log table structure
SELECT '--- TABLE 300_log STRUCTURE ---' as '';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = '300_log'
ORDER BY ORDINAL_POSITION;

-- 3. Verify 300a table structure
SELECT '--- TABLE 300a STRUCTURE ---' as '';
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = '300a'
ORDER BY ORDINAL_POSITION;

-- 4. Count columns per table
SELECT '--- COLUMN COUNTS ---' as '';
SELECT 
    TABLE_NAME,
    COUNT(*) as column_count
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('301', '300_log', '300a')
GROUP BY TABLE_NAME;

-- 5. Verify indexes were created
SELECT '--- INDEXES ---' as '';
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('301', '300_log', '300a')
GROUP BY TABLE_NAME, INDEX_NAME;

-- 6. Verify existing data was preserved
SELECT '--- DATA PRESERVATION CHECK ---' as '';
SELECT '301' as table_name, COUNT(*) as row_count FROM `301`
UNION ALL
SELECT '300_log' as table_name, COUNT(*) as row_count FROM `300_log`
UNION ALL
SELECT '300a' as table_name, COUNT(*) as row_count FROM `300a`;

-- 7. Verify original columns still have data
SELECT '--- 301 ORIGINAL DATA CHECK ---' as '';
SELECT 
    COUNT(*) as total_rows,
    COUNT(form301_id) as has_form301_id,
    COUNT(osha_case_id) as has_osha_case_id,
    COUNT(status) as has_status
FROM `301`;

SELECT '--- 300_log ORIGINAL DATA CHECK ---' as '';
SELECT 
    COUNT(*) as total_rows,
    COUNT(form300line_id) as has_form300line_id,
    COUNT(employer_id) as has_employer_id,
    COUNT(osha_case_id) as has_osha_case_id
FROM `300_log`;

SELECT '--- 300a ORIGINAL DATA CHECK ---' as '';
SELECT 
    COUNT(*) as total_rows,
    COUNT(Id) as has_id,
    COUNT(establishment_id) as has_establishment_id,
    COUNT(year_filing_for) as has_year_filing_for
FROM `300a`;
```

### Expected Column Counts After Migration

| Table | Before Migration | After Migration |
|-------|-----------------|-----------------|
| `301` | 7 columns | 22 columns |
| `300_log` | 10 columns | 33 columns |
| `300a` | 17 columns | 28 columns |

---

## Pre-Migration Checklist

- [ ] Create full database backup
- [ ] Document current row counts for all three tables
- [ ] Verify database user has ALTER privilege
- [ ] Schedule maintenance window
- [ ] Notify stakeholders of planned downtime
- [ ] Test rollback procedure on staging environment
- [ ] Review and approve this migration plan

## Execution Steps

1. **Backup** - Create full database backup
   ```bash
   mysqldump -u [user] -p [database_name] 301 300_log 300a > osha_tables_backup_$(date +%Y%m%d).sql
   ```

2. **Execute Script 1** - Migrate `300_log` table

3. **Verify Script 1** - Run verification queries for `300_log`

4. **Execute Script 2** - Migrate `301` table

5. **Verify Script 2** - Run verification queries for `301`

6. **Execute Script 3** - Migrate `300a` table

7. **Verify Script 3** - Run verification queries for `300a`

8. **Full Verification** - Run complete verification script

9. **Application Testing** - Test application functionality

10. **Cleanup** - Remove backup if migration successful

---

## Post-Migration Tasks

1. **Update Application Code** - Modify DAOs/Repositories to use new columns
2. **Update APIs** - Extend API responses to include new fields
3. **Update Forms** - Add form fields for new columns
4. **Documentation** - Update data dictionary and API documentation
5. **Training** - Train users on new OSHA-compliant fields

---

## Appendix A: Column Reference Mapping

### 301 Table - New Columns to OSHA Form 301 Fields

| New Column | OSHA Form 301 Section |
|------------|----------------------|
| `employee_treated_in_emergency` | Section 9 - Treatment |
| `employee_hospitalized_overnight` | Section 9 - Treatment |
| `witness_name` | Section 10 - Witness |
| `witness_phone` | Section 10 - Witness |
| `physician_name` | Section 11 - Healthcare Professional |
| `physician_facility` | Section 11 - Healthcare Professional |
| `physician_phone` | Section 11 - Healthcare Professional |
| `treatment_provided` | Section 11 - Healthcare Professional |

### 300_log Table - New Columns to OSHA Form 300 Fields

| New Column | OSHA Form 300 Column |
|------------|---------------------|
| `case_number` | Column (A) - Case No. |
| `employee_name` | Column (B) - Employee Name |
| `job_title` | Column (C) - Job Title |
| `date_of_injury_illness` | Column (D) - Date |
| `location_of_incident` | Column (E) - Where |
| `description_of_incident` | Column (F) - Description |
| `days_away_from_work` | Column (K) - Days Away |
| `days_restricted_duty` | Column (L) - Days Restricted |

### 300a Table - New Columns to OSHA Form 300A Fields

| New Column | OSHA Form 300A Section |
|------------|----------------------|
| `certified_by_name` | Certification - Name |
| `certified_by_title` | Certification - Title |
| `certified_date` | Certification - Date |
| `submitted_to_osha` | Electronic Submission |
| `submission_confirmation` | Electronic Submission |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2026-01-12 | System | Initial migration plan |

---

**Note:** This migration plan is designed to be non-destructive. All existing data and columns are preserved. The migration only adds new columns to support full OSHA compliance.
