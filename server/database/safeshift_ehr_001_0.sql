-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 13, 2026 at 04:01 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `safeshift_ehr_001_0`
--

-- --------------------------------------------------------

--
-- Table structure for table `300a`
--

CREATE TABLE `300a` (
  `Id` int(11) NOT NULL,
  `annual_average_employees` int(10) NOT NULL,
  `total_hours_worked` int(10) NOT NULL,
  `no_injuries_illnesses` int(1) NOT NULL,
  `total_deaths` int(10) NOT NULL,
  `total_dafw_cases` int(10) NOT NULL,
  `total_djtr_cases` int(10) NOT NULL,
  `total_other_cases` int(10) NOT NULL,
  `total_dafw_days` int(10) NOT NULL,
  `total_djtr_days` int(10) NOT NULL,
  `total_injuries` int(10) NOT NULL,
  `total_skin_disorders` int(10) NOT NULL,
  `total_respiratory_conditions` int(10) NOT NULL,
  `total_poisonings` int(10) NOT NULL,
  `total_hearing_loss` int(10) NOT NULL,
  `total_other_illnesses` int(10) NOT NULL,
  `change_reason` char(100) NOT NULL,
  `establishment_id` int(11) NOT NULL,
  `year_filing_for` int(11) NOT NULL,
  `errors` int(11) NOT NULL,
  `warnings` int(11) NOT NULL,
  `links` int(11) NOT NULL,
  `company_id` char(36) DEFAULT NULL COMMENT 'Reference to company/employer table',
  `certified_by_name` varchar(200) DEFAULT NULL COMMENT 'Name of company executive who certifies the form',
  `certified_by_title` varchar(100) DEFAULT NULL COMMENT 'Title of certifying official',
  `certified_date` date DEFAULT NULL COMMENT 'Date when form was certified',
  `submitted_to_osha` tinyint(1) DEFAULT 0 COMMENT 'Has this summary been submitted to OSHA',
  `submission_date` datetime DEFAULT NULL COMMENT 'Date and time of OSHA submission',
  `submission_confirmation` varchar(100) DEFAULT NULL COMMENT 'OSHA submission confirmation number',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record last update timestamp',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who created the record',
  `updated_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who last updated the record'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `300_log`
--

CREATE TABLE `300_log` (
  `form300line_id` char(36) NOT NULL,
  `employer_id` char(36) NOT NULL,
  `calendar_year` int(11) NOT NULL,
  `osha_case_id` char(36) NOT NULL,
  `category` varchar(64) DEFAULT NULL,
  `days_away` tinyint(1) DEFAULT 0,
  `job_transfer_restriction` tinyint(1) DEFAULT 0,
  `death` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `case_number` varchar(20) DEFAULT NULL COMMENT 'OSHA case number format: YYYY-XXXXXX',
  `encounter_id` char(36) DEFAULT NULL COMMENT 'Reference to clinical encounter',
  `patient_id` char(36) DEFAULT NULL COMMENT 'Reference to patient record',
  `establishment_id` char(36) DEFAULT NULL COMMENT 'Reference to specific work location',
  `employee_name` varchar(255) DEFAULT NULL COMMENT 'Employee name - may be privacy masked',
  `job_title` varchar(255) DEFAULT NULL COMMENT 'Employee job title at time of incident',
  `date_of_injury_illness` date DEFAULT NULL COMMENT 'Date when injury/illness occurred',
  `time_of_event` time DEFAULT NULL COMMENT 'Time when incident occurred',
  `location_of_incident` varchar(255) DEFAULT NULL COMMENT 'Where on premises the incident occurred',
  `description_of_incident` text DEFAULT NULL COMMENT 'Detailed description of what happened',
  `injury_illness_category_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Reference to injury_illness_categories lookup table',
  `body_part_affected` varchar(100) DEFAULT NULL COMMENT 'Body part that was injured',
  `object_substance` varchar(255) DEFAULT NULL COMMENT 'Object or substance that directly caused harm',
  `death_date` date DEFAULT NULL COMMENT 'Date of death if death=1',
  `days_away_from_work` int(11) DEFAULT 0 COMMENT 'Total number of days away from work',
  `days_restricted_duty` int(11) DEFAULT 0 COMMENT 'Total days on restricted duty',
  `days_job_transfer` int(11) DEFAULT 0 COMMENT 'Total days of job transfer',
  `medical_treatment_beyond_first_aid` tinyint(1) DEFAULT 0 COMMENT 'Treatment beyond first aid was required',
  `is_privacy_case` tinyint(1) DEFAULT 0 COMMENT 'OSHA privacy case flag',
  `privacy_case_reason` text DEFAULT NULL COMMENT 'Reason for privacy case designation',
  `case_status` enum('open','closed','amended') DEFAULT 'open' COMMENT 'Current status of the case',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who created the record',
  `updated_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who last updated the record'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `301`
--

CREATE TABLE `301` (
  `form301_id` char(36) NOT NULL,
  `osha_case_id` char(36) NOT NULL,
  `status` varchar(32) DEFAULT NULL,
  `storage_uri` varchar(1024) DEFAULT NULL,
  `sha256` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `employee_treated_in_emergency` tinyint(1) DEFAULT 0 COMMENT 'Was employee treated in emergency room',
  `employee_hospitalized_overnight` tinyint(1) DEFAULT 0 COMMENT 'Was employee hospitalized overnight as inpatient',
  `witness_name` varchar(200) DEFAULT NULL COMMENT 'Name of person who witnessed the incident',
  `witness_phone` varchar(20) DEFAULT NULL COMMENT 'Phone number of witness',
  `physician_name` varchar(200) DEFAULT NULL COMMENT 'Name of treating physician or healthcare professional',
  `physician_facility` varchar(255) DEFAULT NULL COMMENT 'Name of medical facility where treated',
  `physician_phone` varchar(20) DEFAULT NULL COMMENT 'Phone number of physician/facility',
  `treatment_provided` text DEFAULT NULL COMMENT 'Description of medical treatment provided',
  `root_cause` text DEFAULT NULL COMMENT 'Root cause analysis of the incident',
  `corrective_actions` text DEFAULT NULL COMMENT 'Corrective actions taken to prevent recurrence',
  `investigated_by` varchar(200) DEFAULT NULL COMMENT 'Name of person who investigated the incident',
  `investigation_date` date DEFAULT NULL COMMENT 'Date investigation was conducted',
  `investigation_findings` text DEFAULT NULL COMMENT 'Findings and conclusions from investigation',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who created the record',
  `updated_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID who last updated the record'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` char(36) NOT NULL DEFAULT uuid(),
  `patient_id` char(36) NOT NULL,
  `provider_id` char(36) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `visit_reason` varchar(255) DEFAULT NULL,
  `appointment_type` varchar(100) DEFAULT NULL,
  `status` enum('scheduled','confirmed','checked_in','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `created_by` char(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auditevent`
--

CREATE TABLE `auditevent` (
  `audit_id` char(36) NOT NULL,
  `user_id` char(36) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `user_role` varchar(50) DEFAULT NULL,
  `subject_type` varchar(64) DEFAULT NULL,
  `subject_id` char(36) DEFAULT NULL,
  `action` varchar(32) DEFAULT NULL,
  `occurred_at` datetime(6) NOT NULL,
  `source_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `flagged` tinyint(1) DEFAULT 0,
  `checksum` char(64) DEFAULT NULL COMMENT 'SHA-256 hash for tamper detection / integrity verification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`modified_fields`)),
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_exports`
--

CREATE TABLE `audit_exports` (
  `export_id` char(36) NOT NULL DEFAULT uuid(),
  `exported_by` char(36) NOT NULL,
  `export_format` enum('pdf','csv','json') NOT NULL,
  `filter_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filter_criteria`)),
  `file_path` varchar(500) DEFAULT NULL,
  `record_count` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` bigint(20) UNSIGNED NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `record_id` varchar(36) NOT NULL COMMENT 'Primary key of affected record',
  `action` enum('view','insert','update','delete','export','print') NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `user_role` varchar(50) DEFAULT NULL,
  `logged_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `ip_address` varbinary(16) DEFAULT NULL COMMENT 'IPv4 or IPv6',
  `user_agent` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED DEFAULT NULL,
  `changed_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Before/after values for updates' CHECK (json_valid(`changed_fields`)),
  `context` varchar(255) DEFAULT NULL COMMENT 'Screen/module where action occurred',
  `checksum` char(64) DEFAULT NULL,
  `modified_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`modified_fields`)),
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chainofcustodyform`
--

CREATE TABLE `chainofcustodyform` (
  `ccf_id` char(36) NOT NULL,
  `dot_test_id` char(36) NOT NULL,
  `ccf_number` varchar(64) DEFAULT NULL,
  `sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sections`)),
  `storage_uri` varchar(1024) DEFAULT NULL,
  `sha256` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chart_templates`
--

CREATE TABLE `chart_templates` (
  `template_id` char(36) NOT NULL DEFAULT uuid(),
  `template_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `encounter_type` varchar(100) DEFAULT NULL,
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`template_data`)),
  `created_by` char(36) NOT NULL,
  `visibility` enum('personal','organization') DEFAULT 'personal',
  `status` enum('active','archived','pending_approval') DEFAULT 'active',
  `version` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_alerts`
--

CREATE TABLE `compliance_alerts` (
  `alert_id` char(36) NOT NULL DEFAULT uuid(),
  `kpi_id` char(36) NOT NULL,
  `alert_message` text NOT NULL,
  `severity` enum('warning','critical') NOT NULL,
  `sent_to` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`sent_to`)),
  `acknowledged_by` char(36) DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_kpis`
--

CREATE TABLE `compliance_kpis` (
  `kpi_id` char(36) NOT NULL DEFAULT uuid(),
  `kpi_name` varchar(255) NOT NULL,
  `kpi_category` varchar(100) DEFAULT NULL,
  `calculation_method` text DEFAULT NULL,
  `threshold_warning` decimal(10,2) DEFAULT NULL,
  `threshold_critical` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_kpi_values`
--

CREATE TABLE `compliance_kpi_values` (
  `value_id` char(36) NOT NULL DEFAULT uuid(),
  `kpi_id` char(36) NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `status` enum('compliant','warning','critical') NOT NULL,
  `calculated_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consents`
--

CREATE TABLE `consents` (
  `consent_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `consent_type` varchar(100) NOT NULL COMMENT 'treatment, research, information_sharing, etc',
  `consent_status` enum('granted','denied','withdrawn','expired') NOT NULL DEFAULT 'granted',
  `signed_at` datetime NOT NULL,
  `signed_via` enum('paper','electronic','verbal','implied') NOT NULL,
  `document_hash` char(64) DEFAULT NULL COMMENT 'SHA-256 hash of signed document',
  `document_path` varchar(500) DEFAULT NULL COMMENT 'Path to stored consent document',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disclosure_templates`
--

CREATE TABLE `disclosure_templates` (
  `id` int(11) NOT NULL,
  `disclosure_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL COMMENT 'TODO: Content requires legal review before production',
  `version` varchar(20) DEFAULT '1.0',
  `is_active` tinyint(1) DEFAULT 1,
  `requires_work_related` tinyint(1) DEFAULT 0 COMMENT 'Only show for work-related incidents',
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dot_tests`
--

CREATE TABLE `dot_tests` (
  `test_id` char(36) NOT NULL DEFAULT uuid(),
  `encounter_id` char(36) NOT NULL,
  `patient_id` char(36) NOT NULL,
  `modality` enum('drug_test','alcohol_test') NOT NULL,
  `test_type` varchar(100) DEFAULT NULL,
  `specimen_id` varchar(100) DEFAULT NULL,
  `collected_at` datetime DEFAULT NULL,
  `results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`results`)),
  `mro_review_required` tinyint(1) DEFAULT 0,
  `mro_reviewed_by` char(36) DEFAULT NULL,
  `mro_reviewed_at` datetime DEFAULT NULL,
  `status` enum('pending','negative','positive','cancelled','invalid') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_rate_limits`
--

CREATE TABLE `email_rate_limits` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` char(36) NOT NULL,
  `email_type` varchar(64) NOT NULL COMMENT 'Type of email being rate limited',
  `count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `window_start` datetime NOT NULL COMMENT 'Start of rate limit window',
  `last_sent_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounters`
--

CREATE TABLE `encounters` (
  `encounter_id` char(36) NOT NULL COMMENT 'UUID',
  `patient_id` char(36) NOT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Reference to sites/facilities table',
  `employer_name` varchar(255) DEFAULT NULL COMMENT 'Employer at time of encounter (snapshot)',
  `encounter_type` enum('ems','clinic','telemedicine','other') NOT NULL,
  `encounter_type_other` varchar(100) DEFAULT NULL,
  `status` enum('planned','arrived','in_progress','completed','cancelled','voided') NOT NULL DEFAULT 'planned',
  `chief_complaint` text DEFAULT NULL,
  `onset_context` enum('work_related','off_duty','unknown') DEFAULT NULL,
  `occurred_on` datetime NOT NULL COMMENT 'Date/time of incident',
  `arrived_on` datetime DEFAULT NULL COMMENT 'Arrival at scene or clinic',
  `discharged_on` datetime DEFAULT NULL COMMENT 'Departure/disposition time',
  `disposition` varchar(255) DEFAULT NULL COMMENT 'Treated/Released, Transported, Refused, etc',
  `npi_provider` varchar(10) DEFAULT NULL COMMENT 'NPI of responsible provider',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounter_clinic`
--

CREATE TABLE `encounter_clinic` (
  `encounter_id` char(36) NOT NULL,
  `arrival_mode` enum('walk_in','brought_by_supervisor','wheelchair','stretcher','other') DEFAULT NULL,
  `arrival_mode_other` varchar(100) DEFAULT NULL,
  `arrived_from` varchar(255) DEFAULT NULL COMMENT 'Work area, home, etc',
  `visit_reason` text DEFAULT NULL,
  `work_related` tinyint(1) DEFAULT NULL,
  `followup_days` int(10) UNSIGNED DEFAULT NULL COMMENT 'Return in X days',
  `transportation_home` varchar(255) DEFAULT NULL COMMENT 'Self, supervisor, ambulance',
  `visit_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounter_crew`
--

CREATE TABLE `encounter_crew` (
  `crew_id` int(10) UNSIGNED NOT NULL,
  `encounter_id` char(36) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'References users table',
  `role` enum('paramedic','emt','aemt','driver','supervisor','observer','other') NOT NULL,
  `role_other` varchar(100) DEFAULT NULL,
  `certification_level` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounter_disclosures`
--

CREATE TABLE `encounter_disclosures` (
  `id` int(11) NOT NULL,
  `encounter_id` char(36) NOT NULL,
  `disclosure_type` enum('general_consent','privacy_practices','work_related_auth','hipaa_acknowledgment') NOT NULL,
  `disclosure_version` varchar(20) DEFAULT '1.0',
  `disclosure_text` text NOT NULL COMMENT 'Full text of disclosure at time of acknowledgment for legal record',
  `acknowledged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `acknowledged_by_patient` tinyint(1) DEFAULT 1,
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
  `user_agent` text DEFAULT NULL COMMENT 'Browser/device information for audit trail',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounter_flags`
--

CREATE TABLE `encounter_flags` (
  `flag_id` char(36) NOT NULL DEFAULT uuid(),
  `encounter_id` char(36) NOT NULL,
  `flag_type` varchar(100) NOT NULL,
  `severity` enum('critical','high','medium','low') NOT NULL,
  `flag_reason` text NOT NULL,
  `auto_flagged` tinyint(1) DEFAULT 1,
  `flagged_by` char(36) DEFAULT NULL,
  `assigned_to` char(36) DEFAULT NULL,
  `status` enum('pending','under_review','resolved','escalated') DEFAULT 'pending',
  `due_date` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` char(36) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `encounter_header_summary`
-- (See below for the actual view)
--
CREATE TABLE `encounter_header_summary` (
);

-- --------------------------------------------------------

--
-- Table structure for table `encounter_med_admin`
--

CREATE TABLE `encounter_med_admin` (
  `med_admin_id` int(10) UNSIGNED NOT NULL,
  `encounter_id` char(36) NOT NULL,
  `patient_id` char(36) NOT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dose` varchar(100) NOT NULL,
  `route` varchar(50) NOT NULL COMMENT 'PO, IV, IM, SubQ, Intranasal, etc',
  `given_at` datetime NOT NULL,
  `given_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID of provider',
  `response` text DEFAULT NULL COMMENT 'Patient response to medication',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounter_observations`
--

CREATE TABLE `encounter_observations` (
  `obs_id` int(10) UNSIGNED NOT NULL,
  `encounter_id` char(36) NOT NULL,
  `patient_id` char(36) NOT NULL,
  `label` varchar(100) NOT NULL COMMENT 'BP Systolic, BP Diastolic, Pulse, SpO2, Temp, Resp Rate, Pain NRS, etc',
  `posture` enum('standing','sitting','supine','prone','left_lateral','right_lateral','other') DEFAULT NULL,
  `posture_other` varchar(100) DEFAULT NULL,
  `value_num` decimal(10,2) DEFAULT NULL,
  `value_text` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL COMMENT 'mmHg, bpm, %, F, C, etc',
  `method` varchar(100) DEFAULT NULL COMMENT 'Manual, automatic, auscultation, palpation',
  `taken_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounter_orders`
--

CREATE TABLE `encounter_orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `encounter_id` char(36) NOT NULL,
  `order_type` enum('imaging','lab','rx','referral','work_restriction','followup','other') NOT NULL,
  `order_type_other` varchar(100) DEFAULT NULL,
  `details` text NOT NULL,
  `status` enum('placed','acknowledged','in_progress','resulted','completed','cancelled','voided') NOT NULL DEFAULT 'placed',
  `ordered_at` datetime NOT NULL,
  `due_on` datetime DEFAULT NULL,
  `resulted_at` datetime DEFAULT NULL,
  `results` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounter_procedures`
--

CREATE TABLE `encounter_procedures` (
  `procedure_id` int(10) UNSIGNED NOT NULL,
  `encounter_id` char(36) NOT NULL,
  `patient_id` char(36) NOT NULL,
  `description` text NOT NULL,
  `procedure_code` varchar(20) DEFAULT NULL COMMENT 'CPT or internal code',
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `provider` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID of performing provider',
  `outcome` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encounter_response`
--

CREATE TABLE `encounter_response` (
  `encounter_id` char(36) NOT NULL,
  `unit_number` varchar(50) DEFAULT NULL,
  `dispatched_on` datetime DEFAULT NULL,
  `enroute_on` datetime DEFAULT NULL,
  `onscene_on` datetime DEFAULT NULL,
  `transport_on` datetime DEFAULT NULL,
  `at_dest_on` datetime DEFAULT NULL,
  `available_on` datetime DEFAULT NULL,
  `response_priority` enum('emergency','urgent','routine','standby') DEFAULT NULL,
  `miles_scene_to_dest` decimal(6,2) DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL COMMENT 'Hospital or facility name',
  `narrative` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `establishment`
--

CREATE TABLE `establishment` (
  `Id` int(11) NOT NULL,
  `establishment_name` char(100) NOT NULL,
  `ein` char(9) NOT NULL,
  `company_name` char(100) NOT NULL,
  `street` char(100) NOT NULL,
  `city` char(100) NOT NULL,
  `state` char(2) NOT NULL,
  `zip` text NOT NULL,
  `naics_code` int(6) NOT NULL,
  `industry_description` char(255) NOT NULL,
  `size` int(2) NOT NULL,
  `establishment_type` int(1) NOT NULL,
  `establishment_status` int(1) NOT NULL,
  `submission_status` int(11) NOT NULL COMMENT 'Response field - 1, not added, 2 in progress, 3 is submitted.',
  `establishment_status_response` int(11) NOT NULL COMMENT 'Response field. 1 is active, 2 is inactive/removed.',
  `years_submitted` int(11) NOT NULL COMMENT 'Response field',
  `errors` int(11) NOT NULL COMMENT 'Response field',
  `links` int(11) NOT NULL COMMENT 'Response field. self - link points back to establishment. form300Alinks - array of links to 300A forms filed for establishment. Submissions - array of links to submissions filed for establishment. ',
  `success` int(11) NOT NULL COMMENT 'Response field. request to ITA was received, no fatal errors.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `establishment_provider`
--

CREATE TABLE `establishment_provider` (
  `provider_id` char(36) NOT NULL,
  `npi` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `role` varchar(64) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flag_rules`
--

CREATE TABLE `flag_rules` (
  `rule_id` char(36) NOT NULL DEFAULT uuid(),
  `rule_name` varchar(255) NOT NULL,
  `rule_type` varchar(100) DEFAULT NULL,
  `rule_condition` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rule_condition`)),
  `flag_severity` enum('critical','high','medium','low') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `implementation_checklists`
--

CREATE TABLE `implementation_checklists` (
  `checklist_id` char(36) NOT NULL DEFAULT uuid(),
  `update_id` char(36) NOT NULL,
  `checklist_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`checklist_items`)),
  `completion_pct` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_otp`
--

CREATE TABLE `login_otp` (
  `otp_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `consumed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_log`
--

CREATE TABLE `mail_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` char(36) DEFAULT NULL COMMENT 'User who triggered the email (if applicable)',
  `recipient_email` varchar(320) NOT NULL COMMENT 'Masked or full email based on sensitivity',
  `email_type` varchar(64) NOT NULL COMMENT 'otp, password_reset, reminder, notification, work_related',
  `subject` varchar(500) DEFAULT NULL,
  `body_preview` varchar(500) DEFAULT NULL COMMENT 'First 500 chars for debugging',
  `status` enum('queued','sent','failed','bounced','complained') NOT NULL DEFAULT 'queued',
  `ses_message_id` varchar(100) DEFAULT NULL COMMENT 'Amazon SES Message ID for tracking',
  `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
  `sent_at` datetime DEFAULT NULL COMMENT 'When email was actually sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meeting_chat_messages`
--

CREATE TABLE `meeting_chat_messages` (
  `message_id` int(10) UNSIGNED NOT NULL COMMENT 'Unique message ID',
  `meeting_id` int(10) UNSIGNED NOT NULL COMMENT 'Reference to video_meetings table',
  `participant_id` int(10) UNSIGNED NOT NULL COMMENT 'Reference to meeting_participants table',
  `message_text` text NOT NULL COMMENT 'Chat message content',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Message sent timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chat message history for video meeting sessions';

-- --------------------------------------------------------

--
-- Table structure for table `meeting_participants`
--

CREATE TABLE `meeting_participants` (
  `participant_id` int(10) UNSIGNED NOT NULL COMMENT 'Unique participant record ID',
  `meeting_id` int(10) UNSIGNED NOT NULL COMMENT 'Reference to video_meetings table',
  `display_name` varchar(100) NOT NULL COMMENT 'Participant display name in meeting',
  `joined_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp when participant joined',
  `left_at` datetime DEFAULT NULL COMMENT 'Timestamp when participant left',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Participant IP address (supports IPv6)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Participant records for video meeting sessions';

-- --------------------------------------------------------

--
-- Table structure for table `offline_conflicts`
--

CREATE TABLE `offline_conflicts` (
  `conflict_id` char(36) NOT NULL DEFAULT uuid(),
  `queue_id` char(36) NOT NULL,
  `server_version` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`server_version`)),
  `client_version` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`client_version`)),
  `resolution_status` enum('pending','resolved_client','resolved_server','merged') DEFAULT 'pending',
  `resolved_by` char(36) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` char(36) NOT NULL DEFAULT uuid(),
  `encounter_id` char(36) NOT NULL,
  `order_type` varchar(100) NOT NULL,
  `order_description` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `ordered_by` char(36) DEFAULT NULL,
  `ordered_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `results` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` char(36) NOT NULL COMMENT 'UUID',
  `legal_first_name` varchar(100) NOT NULL,
  `legal_last_name` varchar(100) NOT NULL,
  `dob` date NOT NULL,
  `sex_assigned_at_birth` enum('M','F','X','U') NOT NULL COMMENT 'M=Male, F=Female, X=Intersex, U=Unknown',
  `preferred_name` varchar(100) DEFAULT NULL,
  `gender_identity` varchar(20) DEFAULT NULL,
  `pronouns` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `county` varchar(100) DEFAULT NULL,
  `zip_code` char(10) DEFAULT NULL,
  `preferred_language` varchar(50) NOT NULL DEFAULT 'en',
  `interpreter_required` tinyint(1) NOT NULL DEFAULT 0,
  `primary_contact_method` enum('sms','call','email','other') DEFAULT NULL,
  `primary_contact_method_other` varchar(100) DEFAULT NULL,
  `sms_consent` tinyint(1) NOT NULL DEFAULT 0,
  `email_consent` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_access_log`
--

CREATE TABLE `patient_access_log` (
  `log_id` char(36) NOT NULL DEFAULT uuid(),
  `user_id` char(36) NOT NULL,
  `patient_id` char(36) NOT NULL,
  `accessed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `access_type` enum('view','edit') DEFAULT 'view',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_addresses`
--

CREATE TABLE `patient_addresses` (
  `address_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `use` enum('physical','mailing','billing','temporary','old') NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `street` varchar(255) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `county` varchar(100) DEFAULT NULL,
  `state` char(2) NOT NULL,
  `postal_code` char(10) NOT NULL,
  `country` char(2) NOT NULL DEFAULT 'US',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_allergies`
--

CREATE TABLE `patient_allergies` (
  `allergy_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `substance` varchar(255) NOT NULL,
  `reaction` text DEFAULT NULL,
  `severity` enum('mild','moderate','severe','anaphylaxis','unknown') NOT NULL,
  `status` enum('active','inactive','resolved','entered_in_error') NOT NULL DEFAULT 'active',
  `noted_on` date NOT NULL,
  `resolved_on` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_conditions`
--

CREATE TABLE `patient_conditions` (
  `condition_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `diagnosis` text NOT NULL,
  `coding_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','history_of','resolved','entered_in_error') NOT NULL DEFAULT 'active',
  `onset_date` date DEFAULT NULL,
  `resolved_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_identifiers`
--

CREATE TABLE `patient_identifiers` (
  `identifier_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `id_type` enum('mrn','employer_badge','drivers_license','passport','last4_ssn','state_id','other') NOT NULL,
  `id_type_other` varchar(100) DEFAULT NULL,
  `id_value` varchar(100) NOT NULL,
  `issuing_jurisdiction` varchar(100) DEFAULT NULL COMMENT 'State, country, or organization',
  `issued_on` date DEFAULT NULL,
  `expires_on` date DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_immunizations`
--

CREATE TABLE `patient_immunizations` (
  `imm_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `vaccine` varchar(255) NOT NULL,
  `cvx_code` varchar(10) DEFAULT NULL COMMENT 'CDC CVX code',
  `lot_number` varchar(100) DEFAULT NULL,
  `administered_on` date NOT NULL,
  `site` varchar(100) DEFAULT NULL COMMENT 'Left deltoid, right thigh, etc',
  `dose_number` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Dose # in series',
  `administered_by` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_insurance`
--

CREATE TABLE `patient_insurance` (
  `insurance_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `coverage_type` enum('workers_comp','commercial','medicare','medicaid','self_pay','employer_pay','other') NOT NULL,
  `coverage_type_other` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `payer_name` varchar(255) DEFAULT NULL,
  `member_id` varchar(100) DEFAULT NULL,
  `group_id` varchar(100) DEFAULT NULL,
  `claim_number` varchar(100) DEFAULT NULL,
  `adjuster_name` varchar(255) DEFAULT NULL,
  `adjuster_phone` varchar(20) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `authorization_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_medications`
--

CREATE TABLE `patient_medications` (
  `med_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `med_name` varchar(255) NOT NULL,
  `dose` varchar(100) DEFAULT NULL,
  `route` varchar(50) DEFAULT NULL COMMENT 'PO, IV, IM, SubQ, etc',
  `frequency` varchar(100) DEFAULT NULL COMMENT 'BID, TID, QID, PRN, etc',
  `prn_reason` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_observations`
--

CREATE TABLE `patient_observations` (
  `obs_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `label` varchar(100) NOT NULL COMMENT 'Pain NRS, Fatigue Scale, etc',
  `value_num` decimal(10,2) DEFAULT NULL,
  `value_text` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `taken_at` datetime NOT NULL,
  `context` varchar(100) DEFAULT NULL COMMENT 'baseline, pre_shift, post_shift',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qa_bulk_actions`
--

CREATE TABLE `qa_bulk_actions` (
  `action_id` char(36) NOT NULL DEFAULT uuid(),
  `reviewer_id` char(36) NOT NULL,
  `action_type` enum('bulk_approve','bulk_reject') NOT NULL,
  `encounter_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`encounter_ids`)),
  `performed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qa_review_queue`
--

CREATE TABLE `qa_review_queue` (
  `review_id` char(36) NOT NULL DEFAULT uuid(),
  `encounter_id` char(36) NOT NULL,
  `reviewer_id` char(36) DEFAULT NULL,
  `review_status` enum('pending','approved','rejected','flagged') DEFAULT 'pending',
  `review_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qol_assessments`
--

CREATE TABLE `qol_assessments` (
  `qol_id` int(10) UNSIGNED NOT NULL,
  `patient_id` char(36) NOT NULL,
  `instrument` varchar(50) NOT NULL COMMENT 'PROMIS, PHQ-9, GAD-7, etc',
  `raw_score` decimal(6,2) DEFAULT NULL,
  `t_score` decimal(6,2) DEFAULT NULL,
  `severity_band` varchar(50) DEFAULT NULL COMMENT 'minimal, mild, moderate, severe',
  `taken_at` datetime NOT NULL,
  `context` varchar(100) DEFAULT NULL COMMENT 'baseline, pre_shift, post_shift, followup',
  `responses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Individual question responses' CHECK (json_valid(`responses`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED NOT NULL,
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `modified_by` int(10) UNSIGNED DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ref_diagnosis_codes`
--

CREATE TABLE `ref_diagnosis_codes` (
  `coding_id` int(10) UNSIGNED NOT NULL,
  `system` enum('ICD10','SNOMED','CPT','OTHER') NOT NULL,
  `code` varchar(20) NOT NULL,
  `display` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ref_gender_identity`
--

CREATE TABLE `ref_gender_identity` (
  `code` varchar(20) NOT NULL,
  `display` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 999,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ref_pronouns`
--

CREATE TABLE `ref_pronouns` (
  `code` varchar(20) NOT NULL,
  `display` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 999,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `regulation_trainings`
--

CREATE TABLE `regulation_trainings` (
  `training_id` char(36) NOT NULL DEFAULT uuid(),
  `update_id` char(36) NOT NULL,
  `training_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`training_content`)),
  `assigned_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assigned_roles`)),
  `due_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `regulatory_updates`
--

CREATE TABLE `regulatory_updates` (
  `update_id` char(36) NOT NULL DEFAULT uuid(),
  `regulation_title` varchar(500) NOT NULL,
  `regulation_agency` varchar(100) DEFAULT NULL,
  `regulation_type` varchar(100) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `document_path` varchar(500) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `full_text` longtext DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `role_id` char(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_training_records`
--

CREATE TABLE `staff_training_records` (
  `record_id` char(36) NOT NULL DEFAULT uuid(),
  `user_id` char(36) NOT NULL,
  `requirement_id` char(36) NOT NULL,
  `completion_date` date NOT NULL,
  `expiration_date` date NOT NULL,
  `certification_number` varchar(100) DEFAULT NULL,
  `proof_document_path` varchar(500) DEFAULT NULL,
  `completed_by` char(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `syncstate`
--

CREATE TABLE `syncstate` (
  `sync_id` char(36) NOT NULL,
  `device_id` char(36) NOT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_id` char(36) DEFAULT NULL,
  `version_hash` varchar(128) DEFAULT NULL,
  `last_synced_at` datetime(6) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sync_queue`
--

CREATE TABLE `sync_queue` (
  `queue_id` char(36) NOT NULL DEFAULT uuid(),
  `user_id` char(36) NOT NULL,
  `operation_type` enum('create','update','delete') NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` char(36) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','syncing','completed','failed','conflict') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `synced_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_reminders`
--

CREATE TABLE `training_reminders` (
  `reminder_id` char(36) NOT NULL DEFAULT uuid(),
  `record_id` char(36) NOT NULL,
  `reminder_type` enum('30_day','14_day','7_day','overdue') NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `training_requirements`
--

CREATE TABLE `training_requirements` (
  `requirement_id` char(36) NOT NULL DEFAULT uuid(),
  `training_name` varchar(255) NOT NULL,
  `training_description` text DEFAULT NULL,
  `training_category` varchar(100) DEFAULT NULL,
  `required_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_roles`)),
  `recurrence_interval` int(11) DEFAULT NULL,
  `grace_period` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `two_factor_codes`
--

CREATE TABLE `two_factor_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` char(36) NOT NULL COMMENT 'References user.user_id',
  `code_hash` varchar(255) NOT NULL COMMENT 'bcrypt hash of 6-digit code',
  `purpose` enum('login','password_reset','email_change','security') NOT NULL DEFAULT 'login',
  `expires_at` datetime NOT NULL COMMENT 'Code expiration time (usually 10 mins)',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Failed verification attempts',
  `max_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Max allowed attempts before invalidation',
  `consumed_at` datetime DEFAULT NULL COMMENT 'When code was successfully used',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP that requested the code',
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ui_tooltips`
--

CREATE TABLE `ui_tooltips` (
  `tooltip_id` char(36) NOT NULL DEFAULT uuid(),
  `field_identifier` varchar(255) NOT NULL,
  `tooltip_text` text NOT NULL,
  `tooltip_type` enum('info','warning','help','compliance') DEFAULT 'info',
  `role_filter` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` char(36) NOT NULL,
  `username` varchar(190) NOT NULL,
  `email` varchar(320) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `mfa_enabled` tinyint(1) DEFAULT 0,
  `status` varchar(32) DEFAULT 'active',
  `lockout_until` datetime DEFAULT NULL COMMENT 'Timestamp until which the account is locked',
  `last_login` datetime DEFAULT NULL COMMENT 'Last successful login timestamp',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether the user account is active',
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `login_attempts` int(11) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `last_reminder_sent_at` datetime DEFAULT NULL COMMENT 'Last inactivity reminder email sent',
  `email_opt_in_reminders` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'User opted into inactivity reminders',
  `email_opt_in_security` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'User opted into security emails (2FA, etc)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userrole`
--

CREATE TABLE `userrole` (
  `user_role_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `role_id` char(36) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp() COMMENT 'Timestamp when the role was assigned to the user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `users_needing_reminder`
-- (See below for the actual view)
--
CREATE TABLE `users_needing_reminder` (
`user_id` char(36)
,`username` varchar(190)
,`email` varchar(320)
,`last_login` datetime
,`last_reminder_sent_at` datetime
,`unread_notifications` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `user_device`
--

CREATE TABLE `user_device` (
  `device_id` char(36) NOT NULL,
  `user_id` char(36) DEFAULT NULL,
  `platform` varchar(32) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `last_seen_at` datetime(6) DEFAULT NULL,
  `encrypted_at_rest` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_notification`
--

CREATE TABLE `user_notification` (
  `notification_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `type` varchar(64) NOT NULL,
  `priority` varchar(32) DEFAULT 'normal',
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime(6) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_permission`
--

CREATE TABLE `user_permission` (
  `permission_id` char(36) NOT NULL,
  `name` varchar(128) NOT NULL,
  `resource` varchar(128) NOT NULL,
  `action` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_tooltip_preferences`
--

CREATE TABLE `user_tooltip_preferences` (
  `user_id` char(36) NOT NULL,
  `tooltips_enabled` tinyint(1) DEFAULT 1,
  `dismissed_tooltips` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dismissed_tooltips`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_meetings_v2`
--

CREATE TABLE `video_meetings_v2` (
  `meeting_id` int(10) UNSIGNED NOT NULL COMMENT 'Unique meeting identifier',
  `created_by` int(10) UNSIGNED NOT NULL COMMENT 'User ID of meeting creator (clinician)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Meeting creation timestamp',
  `token` varchar(128) NOT NULL COMMENT 'Unique secure token for meeting access',
  `token_expires_at` datetime NOT NULL COMMENT 'Token expiration timestamp',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether meeting is currently active',
  `ended_at` datetime DEFAULT NULL COMMENT 'Meeting end timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_meeting_logs`
--

CREATE TABLE `video_meeting_logs` (
  `log_id` int(10) UNSIGNED NOT NULL COMMENT 'Unique log entry ID',
  `log_type` varchar(50) NOT NULL COMMENT 'Event type (meeting_created, token_validated, participant_joined, etc.)',
  `meeting_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Reference to video_meetings (nullable for pre-meeting events)',
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID if authenticated (nullable for guest events)',
  `action` varchar(100) NOT NULL COMMENT 'Human-readable action description',
  `details` text DEFAULT NULL COMMENT 'Additional event details in JSON format',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP address (supports IPv6)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Log entry timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for video meeting events and security tracking';

-- --------------------------------------------------------

--
-- Structure for view `encounter_header_summary`
--
DROP TABLE IF EXISTS `encounter_header_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `encounter_header_summary`  AS SELECT `e`.`encounter_id` AS `encounter_id`, `e`.`patient_id` AS `patient_id`, `e`.`type` AS `type`, `e`.`status` AS `status`, `e`.`occurred_on` AS `occurred_on`, `e`.`started_at` AS `started_at`, `e`.`triage_started_on` AS `triage_started_on`, `e`.`discharged_on` AS `discharged_on`, `d`.`first_name` AS `first_name`, `d`.`last_name` AS `last_name`, `d`.`dob` AS `dob`, `d`.`sex` AS `sex` FROM (`patient_encounter` `e` join `patient_demographics` `d` on(`e`.`patient_id` = `d`.`patient_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `users_needing_reminder`
--
DROP TABLE IF EXISTS `users_needing_reminder`;

CREATE ALGORITHM=UNDEFINED DEFINER=`safeshift_admin`@`%` SQL SECURITY DEFINER VIEW `users_needing_reminder`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`last_login` AS `last_login`, `u`.`last_reminder_sent_at` AS `last_reminder_sent_at`, count(case when `n`.`is_read` = 0 then 1 end) AS `unread_notifications` FROM (`user` `u` left join `user_notification` `n` on(`u`.`user_id` = `n`.`user_id`)) WHERE `u`.`is_active` = 1 AND `u`.`email_opt_in_reminders` = 1 AND `u`.`status` = 'active' AND (`u`.`last_login` is null OR `u`.`last_login` <= current_timestamp() - interval 3 day) AND (`u`.`last_reminder_sent_at` is null OR `u`.`last_reminder_sent_at` <= current_timestamp() - interval 3 day) GROUP BY `u`.`user_id` HAVING `unread_notifications` > 0 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `300a`
--
ALTER TABLE `300a`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `uk_300a_establishment_year` (`establishment_id`,`year_filing_for`),
  ADD KEY `idx_300a_company` (`company_id`),
  ADD KEY `idx_300a_establishment` (`establishment_id`),
  ADD KEY `idx_300a_year` (`year_filing_for`),
  ADD KEY `idx_300a_submission` (`submitted_to_osha`);

--
-- Indexes for table `300_log`
--
ALTER TABLE `300_log`
  ADD PRIMARY KEY (`form300line_id`),
  ADD UNIQUE KEY `uq_300_employer_year_case` (`employer_id`,`calendar_year`,`osha_case_id`),
  ADD KEY `fk_300_case` (`osha_case_id`),
  ADD KEY `idx_300_year` (`calendar_year`),
  ADD KEY `idx_300_category` (`category`),
  ADD KEY `idx_300log_case_number` (`case_number`),
  ADD KEY `idx_300log_date` (`date_of_injury_illness`),
  ADD KEY `idx_300log_employer` (`employer_id`),
  ADD KEY `idx_300log_establishment` (`establishment_id`),
  ADD KEY `idx_300log_patient` (`patient_id`),
  ADD KEY `idx_300log_status` (`case_status`),
  ADD KEY `idx_300log_category` (`injury_illness_category_id`);

--
-- Indexes for table `301`
--
ALTER TABLE `301`
  ADD PRIMARY KEY (`form301_id`),
  ADD KEY `idx_301_case` (`osha_case_id`),
  ADD KEY `idx_301_status` (`status`),
  ADD KEY `idx_301_osha_case` (`osha_case_id`),
  ADD KEY `idx_301_investigation_date` (`investigation_date`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `idx_patient_date` (`patient_id`,`start_time`),
  ADD KEY `idx_provider_date` (`provider_id`,`start_time`);

--
-- Indexes for table `auditevent`
--
ALTER TABLE `auditevent`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_subject` (`subject_type`,`subject_id`),
  ADD KEY `idx_audit_action_time` (`action`,`occurred_at`),
  ADD KEY `idx_audit_flagged` (`flagged`),
  ADD KEY `idx_audit_session_id` (`session_id`),
  ADD KEY `idx_ae_patient_id` (`patient_id`),
  ADD KEY `idx_ae_success` (`success`),
  ADD KEY `idx_ae_user_name` (`user_name`);

--
-- Indexes for table `audit_exports`
--
ALTER TABLE `audit_exports`
  ADD PRIMARY KEY (`export_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`,`logged_at`),
  ADD KEY `idx_user_time` (`user_id`,`logged_at`),
  ADD KEY `idx_action_time` (`action`,`logged_at`),
  ADD KEY `idx_table_action` (`table_name`,`action`,`logged_at`),
  ADD KEY `idx_logged` (`logged_at`),
  ADD KEY `idx_audit_patient_id` (`patient_id`),
  ADD KEY `idx_audit_success` (`success`);

--
-- Indexes for table `chainofcustodyform`
--
ALTER TABLE `chainofcustodyform`
  ADD PRIMARY KEY (`ccf_id`),
  ADD UNIQUE KEY `dot_test_id` (`dot_test_id`),
  ADD KEY `idx_ccf_number` (`ccf_number`),
  ADD KEY `idx_ccf_sha` (`sha256`);

--
-- Indexes for table `chart_templates`
--
ALTER TABLE `chart_templates`
  ADD PRIMARY KEY (`template_id`),
  ADD KEY `idx_user_templates` (`created_by`,`status`);

--
-- Indexes for table `compliance_alerts`
--
ALTER TABLE `compliance_alerts`
  ADD PRIMARY KEY (`alert_id`),
  ADD KEY `kpi_id` (`kpi_id`);

--
-- Indexes for table `compliance_kpis`
--
ALTER TABLE `compliance_kpis`
  ADD PRIMARY KEY (`kpi_id`);

--
-- Indexes for table `compliance_kpi_values`
--
ALTER TABLE `compliance_kpi_values`
  ADD PRIMARY KEY (`value_id`),
  ADD KEY `idx_kpi_calculated` (`kpi_id`,`calculated_at`);

--
-- Indexes for table `consents`
--
ALTER TABLE `consents`
  ADD PRIMARY KEY (`consent_id`),
  ADD KEY `idx_patient_type` (`patient_id`,`consent_type`,`deleted_at`),
  ADD KEY `idx_patient_type_signed` (`patient_id`,`consent_type`,`signed_at`,`deleted_at`),
  ADD KEY `idx_status` (`consent_status`,`deleted_at`),
  ADD KEY `idx_expires` (`expires_at`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `disclosure_templates`
--
ALTER TABLE `disclosure_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `disclosure_type` (`disclosure_type`),
  ADD KEY `idx_template_type` (`disclosure_type`),
  ADD KEY `idx_template_active` (`is_active`),
  ADD KEY `idx_template_order` (`display_order`);

--
-- Indexes for table `dot_tests`
--
ALTER TABLE `dot_tests`
  ADD PRIMARY KEY (`test_id`),
  ADD KEY `idx_patient_modality` (`patient_id`,`modality`);

--
-- Indexes for table `email_rate_limits`
--
ALTER TABLE `email_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rate_user_type` (`user_id`,`email_type`),
  ADD KEY `idx_rate_window` (`window_start`);

--
-- Indexes for table `encounters`
--
ALTER TABLE `encounters`
  ADD PRIMARY KEY (`encounter_id`),
  ADD UNIQUE KEY `uk_enc_patient` (`encounter_id`,`patient_id`),
  ADD KEY `idx_patient_occurred` (`patient_id`,`occurred_on`,`deleted_at`),
  ADD KEY `idx_site_occurred` (`site_id`,`occurred_on`,`deleted_at`),
  ADD KEY `idx_status_occurred` (`status`,`occurred_on`,`deleted_at`),
  ADD KEY `idx_site_status_date` (`site_id`,`status`,`occurred_on`,`deleted_at`),
  ADD KEY `idx_status_patient` (`status`,`patient_id`,`occurred_on`,`deleted_at`),
  ADD KEY `idx_type` (`encounter_type`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `encounter_clinic`
--
ALTER TABLE `encounter_clinic`
  ADD PRIMARY KEY (`encounter_id`),
  ADD KEY `idx_work_related` (`work_related`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `encounter_crew`
--
ALTER TABLE `encounter_crew`
  ADD PRIMARY KEY (`crew_id`),
  ADD KEY `idx_encounter` (`encounter_id`,`deleted_at`),
  ADD KEY `idx_user` (`user_id`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `encounter_disclosures`
--
ALTER TABLE `encounter_disclosures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_disclosure_encounter` (`encounter_id`),
  ADD KEY `idx_disclosure_type` (`disclosure_type`),
  ADD KEY `idx_disclosure_acknowledged` (`acknowledged_at`),
  ADD KEY `idx_disclosure_encounter_type` (`encounter_id`,`disclosure_type`);

--
-- Indexes for table `encounter_flags`
--
ALTER TABLE `encounter_flags`
  ADD PRIMARY KEY (`flag_id`),
  ADD KEY `idx_status_severity` (`status`,`severity`,`created_at`);

--
-- Indexes for table `encounter_med_admin`
--
ALTER TABLE `encounter_med_admin`
  ADD PRIMARY KEY (`med_admin_id`),
  ADD KEY `idx_encounter_time` (`encounter_id`,`given_at`,`deleted_at`),
  ADD KEY `idx_patient` (`patient_id`,`deleted_at`),
  ADD KEY `idx_medication` (`medication_name`(100),`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`),
  ADD KEY `fk_enc_med_enc_patient` (`encounter_id`,`patient_id`);

--
-- Indexes for table `encounter_observations`
--
ALTER TABLE `encounter_observations`
  ADD PRIMARY KEY (`obs_id`),
  ADD KEY `idx_encounter_time` (`encounter_id`,`taken_at`,`deleted_at`),
  ADD KEY `idx_encounter_label` (`encounter_id`,`label`,`taken_at`,`deleted_at`),
  ADD KEY `idx_patient_label` (`patient_id`,`label`,`taken_at`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`),
  ADD KEY `fk_eobs_enc_patient` (`encounter_id`,`patient_id`);

--
-- Indexes for table `encounter_orders`
--
ALTER TABLE `encounter_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_encounter_type_status` (`encounter_id`,`order_type`,`status`,`deleted_at`),
  ADD KEY `idx_status_type_due` (`status`,`order_type`,`due_on`,`deleted_at`),
  ADD KEY `idx_ordered` (`ordered_at`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `encounter_procedures`
--
ALTER TABLE `encounter_procedures`
  ADD PRIMARY KEY (`procedure_id`),
  ADD KEY `idx_encounter_time` (`encounter_id`,`started_at`,`deleted_at`),
  ADD KEY `idx_patient` (`patient_id`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`),
  ADD KEY `fk_proc_enc_patient` (`encounter_id`,`patient_id`);

--
-- Indexes for table `encounter_response`
--
ALTER TABLE `encounter_response`
  ADD PRIMARY KEY (`encounter_id`),
  ADD KEY `idx_unit` (`unit_number`,`deleted_at`),
  ADD KEY `idx_dispatched` (`dispatched_on`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `establishment`
--
ALTER TABLE `establishment`
  ADD PRIMARY KEY (`Id`);

--
-- Indexes for table `establishment_provider`
--
ALTER TABLE `establishment_provider`
  ADD PRIMARY KEY (`provider_id`),
  ADD KEY `idx_provider_name` (`last_name`,`first_name`),
  ADD KEY `idx_provider_role` (`role`);

--
-- Indexes for table `flag_rules`
--
ALTER TABLE `flag_rules`
  ADD PRIMARY KEY (`rule_id`);

--
-- Indexes for table `implementation_checklists`
--
ALTER TABLE `implementation_checklists`
  ADD PRIMARY KEY (`checklist_id`);

--
-- Indexes for table `login_otp`
--
ALTER TABLE `login_otp`
  ADD PRIMARY KEY (`otp_id`),
  ADD KEY `idx_otp_user` (`user_id`),
  ADD KEY `idx_otp_expires` (`expires_at`);

--
-- Indexes for table `mail_log`
--
ALTER TABLE `mail_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mail_user` (`user_id`),
  ADD KEY `idx_mail_type` (`email_type`),
  ADD KEY `idx_mail_status` (`status`),
  ADD KEY `idx_mail_created` (`created_at`),
  ADD KEY `idx_mail_recipient` (`recipient_email`(100));

--
-- Indexes for table `meeting_chat_messages`
--
ALTER TABLE `meeting_chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_meeting_id` (`meeting_id`) COMMENT 'Index for meeting-based message queries',
  ADD KEY `idx_participant_id` (`participant_id`) COMMENT 'Index for participant message queries',
  ADD KEY `idx_sent_at` (`sent_at`) COMMENT 'Index for chronological message ordering';

--
-- Indexes for table `meeting_participants`
--
ALTER TABLE `meeting_participants`
  ADD PRIMARY KEY (`participant_id`),
  ADD KEY `idx_meeting_id` (`meeting_id`) COMMENT 'Index for meeting-based queries',
  ADD KEY `idx_joined_at` (`joined_at`) COMMENT 'Index for time-based queries';

--
-- Indexes for table `offline_conflicts`
--
ALTER TABLE `offline_conflicts`
  ADD PRIMARY KEY (`conflict_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_encounter_status` (`encounter_id`,`status`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `idx_name_lookup` (`legal_last_name`,`legal_first_name`,`deleted_at`),
  ADD KEY `idx_phone` (`phone`,`deleted_at`),
  ADD KEY `idx_email` (`email`,`deleted_at`),
  ADD KEY `idx_dob` (`dob`,`deleted_at`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_deleted` (`deleted_at`),
  ADD KEY `fk_patient_gender` (`gender_identity`),
  ADD KEY `fk_patient_pronouns` (`pronouns`);

--
-- Indexes for table `patient_access_log`
--
ALTER TABLE `patient_access_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_accessed` (`user_id`,`accessed_at`);

--
-- Indexes for table `patient_addresses`
--
ALTER TABLE `patient_addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_patient_primary` (`patient_id`,`is_primary`,`deleted_at`),
  ADD KEY `idx_patient_current` (`patient_id`,`valid_from`,`valid_to`,`deleted_at`),
  ADD KEY `idx_postal` (`postal_code`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  ADD PRIMARY KEY (`allergy_id`),
  ADD KEY `idx_patient_status` (`patient_id`,`status`,`deleted_at`),
  ADD KEY `idx_substance` (`substance`(100),`deleted_at`),
  ADD KEY `idx_severity` (`severity`,`status`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `patient_conditions`
--
ALTER TABLE `patient_conditions`
  ADD PRIMARY KEY (`condition_id`),
  ADD KEY `idx_patient_status` (`patient_id`,`status`,`deleted_at`),
  ADD KEY `idx_coding` (`coding_id`,`deleted_at`),
  ADD KEY `idx_onset` (`onset_date`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `patient_identifiers`
--
ALTER TABLE `patient_identifiers`
  ADD PRIMARY KEY (`identifier_id`),
  ADD KEY `idx_patient_type` (`patient_id`,`id_type`,`is_primary`,`deleted_at`),
  ADD KEY `idx_value_search` (`id_value`,`deleted_at`),
  ADD KEY `idx_expires` (`expires_on`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `patient_immunizations`
--
ALTER TABLE `patient_immunizations`
  ADD PRIMARY KEY (`imm_id`),
  ADD KEY `idx_patient_vaccine` (`patient_id`,`vaccine`(100),`deleted_at`),
  ADD KEY `idx_patient_date` (`patient_id`,`administered_on`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD PRIMARY KEY (`insurance_id`),
  ADD KEY `idx_patient_primary` (`patient_id`,`is_primary`,`deleted_at`),
  ADD KEY `idx_claim_number` (`claim_number`,`deleted_at`),
  ADD KEY `idx_coverage_dates` (`coverage_type`,`effective_from`,`effective_to`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `patient_medications`
--
ALTER TABLE `patient_medications`
  ADD PRIMARY KEY (`med_id`),
  ADD KEY `idx_patient_active` (`patient_id`,`active`,`deleted_at`),
  ADD KEY `idx_active_patient` (`active`,`patient_id`,`start_date`),
  ADD KEY `idx_med_name` (`med_name`(100),`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `patient_observations`
--
ALTER TABLE `patient_observations`
  ADD PRIMARY KEY (`obs_id`),
  ADD KEY `idx_patient_label` (`patient_id`,`label`,`taken_at`,`deleted_at`),
  ADD KEY `idx_patient_context` (`patient_id`,`context`,`taken_at`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `qa_bulk_actions`
--
ALTER TABLE `qa_bulk_actions`
  ADD PRIMARY KEY (`action_id`);

--
-- Indexes for table `qa_review_queue`
--
ALTER TABLE `qa_review_queue`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `idx_reviewer_status` (`reviewer_id`,`review_status`);

--
-- Indexes for table `qol_assessments`
--
ALTER TABLE `qol_assessments`
  ADD PRIMARY KEY (`qol_id`),
  ADD KEY `idx_patient_instrument` (`patient_id`,`instrument`,`taken_at`,`deleted_at`),
  ADD KEY `idx_patient_context` (`patient_id`,`context`,`taken_at`,`deleted_at`),
  ADD KEY `idx_deleted` (`deleted_at`);

--
-- Indexes for table `ref_diagnosis_codes`
--
ALTER TABLE `ref_diagnosis_codes`
  ADD PRIMARY KEY (`coding_id`),
  ADD UNIQUE KEY `uk_system_code` (`system`,`code`),
  ADD KEY `idx_active` (`active`,`system`),
  ADD KEY `idx_code_search` (`code`,`active`);

--
-- Indexes for table `ref_gender_identity`
--
ALTER TABLE `ref_gender_identity`
  ADD PRIMARY KEY (`code`),
  ADD KEY `idx_active` (`active`,`sort_order`);

--
-- Indexes for table `ref_pronouns`
--
ALTER TABLE `ref_pronouns`
  ADD PRIMARY KEY (`code`),
  ADD KEY `idx_active` (`active`,`sort_order`);

--
-- Indexes for table `regulation_trainings`
--
ALTER TABLE `regulation_trainings`
  ADD PRIMARY KEY (`training_id`);

--
-- Indexes for table `regulatory_updates`
--
ALTER TABLE `regulatory_updates`
  ADD PRIMARY KEY (`update_id`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `slug_2` (`slug`);

--
-- Indexes for table `staff_training_records`
--
ALTER TABLE `staff_training_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `idx_user_status` (`user_id`,`expiration_date`);

--
-- Indexes for table `syncstate`
--
ALTER TABLE `syncstate`
  ADD PRIMARY KEY (`sync_id`),
  ADD KEY `idx_sync_device` (`device_id`),
  ADD KEY `idx_sync_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_sync_last_synced` (`last_synced_at`);

--
-- Indexes for table `sync_queue`
--
ALTER TABLE `sync_queue`
  ADD PRIMARY KEY (`queue_id`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- Indexes for table `training_reminders`
--
ALTER TABLE `training_reminders`
  ADD PRIMARY KEY (`reminder_id`);

--
-- Indexes for table `training_requirements`
--
ALTER TABLE `training_requirements`
  ADD PRIMARY KEY (`requirement_id`);

--
-- Indexes for table `two_factor_codes`
--
ALTER TABLE `two_factor_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_2fa_user_id` (`user_id`),
  ADD KEY `idx_2fa_expires` (`expires_at`),
  ADD KEY `idx_2fa_user_purpose` (`user_id`,`purpose`,`expires_at`);

--
-- Indexes for table `ui_tooltips`
--
ALTER TABLE `ui_tooltips`
  ADD PRIMARY KEY (`tooltip_id`),
  ADD UNIQUE KEY `field_identifier` (`field_identifier`),
  ADD KEY `idx_field` (`field_identifier`,`status`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_user_email` (`email`),
  ADD KEY `idx_user_status` (`status`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_lockout_until` (`lockout_until`),
  ADD KEY `idx_user_reminder` (`last_login`,`last_reminder_sent_at`,`email_opt_in_reminders`);

--
-- Indexes for table `userrole`
--
ALTER TABLE `userrole`
  ADD PRIMARY KEY (`user_role_id`),
  ADD UNIQUE KEY `uq_user_role` (`user_id`,`role_id`),
  ADD KEY `idx_userrole_user` (`user_id`),
  ADD KEY `idx_userrole_role` (`role_id`);

--
-- Indexes for table `user_device`
--
ALTER TABLE `user_device`
  ADD PRIMARY KEY (`device_id`),
  ADD KEY `idx_device_user` (`user_id`),
  ADD KEY `idx_device_status` (`status`),
  ADD KEY `idx_device_last_seen` (`last_seen_at`);

--
-- Indexes for table `user_notification`
--
ALTER TABLE `user_notification`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`,`created_at`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `user_permission`
--
ALTER TABLE `user_permission`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `uq_perm` (`name`,`resource`,`action`);

--
-- Indexes for table `user_tooltip_preferences`
--
ALTER TABLE `user_tooltip_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `video_meetings_v2`
--
ALTER TABLE `video_meetings_v2`
  ADD PRIMARY KEY (`meeting_id`),
  ADD UNIQUE KEY `idx_token` (`token`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_token_expires_at` (`token_expires_at`);

--
-- Indexes for table `video_meeting_logs`
--
ALTER TABLE `video_meeting_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_meeting_id` (`meeting_id`) COMMENT 'Index for meeting-specific log queries',
  ADD KEY `idx_log_type` (`log_type`) COMMENT 'Index for event type filtering',
  ADD KEY `idx_created_at` (`created_at`) COMMENT 'Index for time-based log queries',
  ADD KEY `idx_user_id` (`user_id`) COMMENT 'Index for user activity queries',
  ADD KEY `idx_ip_address` (`ip_address`) COMMENT 'Index for IP-based security queries';

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `300a`
--
ALTER TABLE `300a`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consents`
--
ALTER TABLE `consents`
  MODIFY `consent_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disclosure_templates`
--
ALTER TABLE `disclosure_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_rate_limits`
--
ALTER TABLE `email_rate_limits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encounter_crew`
--
ALTER TABLE `encounter_crew`
  MODIFY `crew_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encounter_disclosures`
--
ALTER TABLE `encounter_disclosures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encounter_med_admin`
--
ALTER TABLE `encounter_med_admin`
  MODIFY `med_admin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encounter_observations`
--
ALTER TABLE `encounter_observations`
  MODIFY `obs_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encounter_orders`
--
ALTER TABLE `encounter_orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encounter_procedures`
--
ALTER TABLE `encounter_procedures`
  MODIFY `procedure_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `establishment`
--
ALTER TABLE `establishment`
  MODIFY `Id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mail_log`
--
ALTER TABLE `mail_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meeting_chat_messages`
--
ALTER TABLE `meeting_chat_messages`
  MODIFY `message_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique message ID';

--
-- AUTO_INCREMENT for table `meeting_participants`
--
ALTER TABLE `meeting_participants`
  MODIFY `participant_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique participant record ID';

--
-- AUTO_INCREMENT for table `patient_addresses`
--
ALTER TABLE `patient_addresses`
  MODIFY `address_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  MODIFY `allergy_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_conditions`
--
ALTER TABLE `patient_conditions`
  MODIFY `condition_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_identifiers`
--
ALTER TABLE `patient_identifiers`
  MODIFY `identifier_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_immunizations`
--
ALTER TABLE `patient_immunizations`
  MODIFY `imm_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  MODIFY `insurance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_medications`
--
ALTER TABLE `patient_medications`
  MODIFY `med_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_observations`
--
ALTER TABLE `patient_observations`
  MODIFY `obs_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qol_assessments`
--
ALTER TABLE `qol_assessments`
  MODIFY `qol_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ref_diagnosis_codes`
--
ALTER TABLE `ref_diagnosis_codes`
  MODIFY `coding_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `two_factor_codes`
--
ALTER TABLE `two_factor_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_meetings_v2`
--
ALTER TABLE `video_meetings_v2`
  MODIFY `meeting_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique meeting identifier';

--
-- AUTO_INCREMENT for table `video_meeting_logs`
--
ALTER TABLE `video_meeting_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Unique log entry ID';

--
-- Constraints for dumped tables
--

--
-- Constraints for table `300_log`
--
ALTER TABLE `300_log`
  ADD CONSTRAINT `fk_300_case` FOREIGN KEY (`osha_case_id`) REFERENCES `osha_case` (`osha_case_id`),
  ADD CONSTRAINT `fk_300_employer` FOREIGN KEY (`employer_id`) REFERENCES `patient_employer` (`employer_id`);

--
-- Constraints for table `301`
--
ALTER TABLE `301`
  ADD CONSTRAINT `fk_301_case` FOREIGN KEY (`osha_case_id`) REFERENCES `osha_case` (`osha_case_id`);

--
-- Constraints for table `auditevent`
--
ALTER TABLE `auditevent`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `chainofcustodyform`
--
ALTER TABLE `chainofcustodyform`
  ADD CONSTRAINT `fk_ccf_dot` FOREIGN KEY (`dot_test_id`) REFERENCES `dot_test` (`dot_test_id`);

--
-- Constraints for table `compliance_alerts`
--
ALTER TABLE `compliance_alerts`
  ADD CONSTRAINT `compliance_alerts_ibfk_1` FOREIGN KEY (`kpi_id`) REFERENCES `compliance_kpis` (`kpi_id`) ON DELETE CASCADE;

--
-- Constraints for table `compliance_kpi_values`
--
ALTER TABLE `compliance_kpi_values`
  ADD CONSTRAINT `compliance_kpi_values_ibfk_1` FOREIGN KEY (`kpi_id`) REFERENCES `compliance_kpis` (`kpi_id`) ON DELETE CASCADE;

--
-- Constraints for table `consents`
--
ALTER TABLE `consents`
  ADD CONSTRAINT `fk_consent_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `encounters`
--
ALTER TABLE `encounters`
  ADD CONSTRAINT `fk_enc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `encounter_clinic`
--
ALTER TABLE `encounter_clinic`
  ADD CONSTRAINT `fk_clinic_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`encounter_id`);

--
-- Constraints for table `encounter_crew`
--
ALTER TABLE `encounter_crew`
  ADD CONSTRAINT `fk_crew_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`encounter_id`);

--
-- Constraints for table `encounter_disclosures`
--
ALTER TABLE `encounter_disclosures`
  ADD CONSTRAINT `encounter_disclosures_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`encounter_id`) ON DELETE CASCADE;

--
-- Constraints for table `encounter_med_admin`
--
ALTER TABLE `encounter_med_admin`
  ADD CONSTRAINT `fk_enc_med_enc_patient` FOREIGN KEY (`encounter_id`,`patient_id`) REFERENCES `encounters` (`encounter_id`, `patient_id`),
  ADD CONSTRAINT `fk_enc_med_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`encounter_id`),
  ADD CONSTRAINT `fk_enc_med_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `encounter_observations`
--
ALTER TABLE `encounter_observations`
  ADD CONSTRAINT `fk_eobs_enc_patient` FOREIGN KEY (`encounter_id`,`patient_id`) REFERENCES `encounters` (`encounter_id`, `patient_id`),
  ADD CONSTRAINT `fk_eobs_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`encounter_id`),
  ADD CONSTRAINT `fk_eobs_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `encounter_orders`
--
ALTER TABLE `encounter_orders`
  ADD CONSTRAINT `fk_order_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`encounter_id`);

--
-- Constraints for table `encounter_procedures`
--
ALTER TABLE `encounter_procedures`
  ADD CONSTRAINT `fk_proc_enc_patient` FOREIGN KEY (`encounter_id`,`patient_id`) REFERENCES `encounters` (`encounter_id`, `patient_id`),
  ADD CONSTRAINT `fk_proc_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`encounter_id`),
  ADD CONSTRAINT `fk_proc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `encounter_response`
--
ALTER TABLE `encounter_response`
  ADD CONSTRAINT `fk_resp_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`encounter_id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patient_gender` FOREIGN KEY (`gender_identity`) REFERENCES `ref_gender_identity` (`code`),
  ADD CONSTRAINT `fk_patient_pronouns` FOREIGN KEY (`pronouns`) REFERENCES `ref_pronouns` (`code`);

--
-- Constraints for table `patient_addresses`
--
ALTER TABLE `patient_addresses`
  ADD CONSTRAINT `fk_addr_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patient_allergies`
--
ALTER TABLE `patient_allergies`
  ADD CONSTRAINT `fk_allergy_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patient_conditions`
--
ALTER TABLE `patient_conditions`
  ADD CONSTRAINT `fk_cond_coding` FOREIGN KEY (`coding_id`) REFERENCES `ref_diagnosis_codes` (`coding_id`),
  ADD CONSTRAINT `fk_cond_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patient_identifiers`
--
ALTER TABLE `patient_identifiers`
  ADD CONSTRAINT `fk_ident_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patient_immunizations`
--
ALTER TABLE `patient_immunizations`
  ADD CONSTRAINT `fk_imm_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD CONSTRAINT `fk_ins_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patient_medications`
--
ALTER TABLE `patient_medications`
  ADD CONSTRAINT `fk_med_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patient_observations`
--
ALTER TABLE `patient_observations`
  ADD CONSTRAINT `fk_pobs_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `qol_assessments`
--
ALTER TABLE `qol_assessments`
  ADD CONSTRAINT `fk_qol_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `syncstate`
--
ALTER TABLE `syncstate`
  ADD CONSTRAINT `fk_sync_device` FOREIGN KEY (`device_id`) REFERENCES `user_device` (`device_id`);

--
-- Constraints for table `two_factor_codes`
--
ALTER TABLE `two_factor_codes`
  ADD CONSTRAINT `fk_2fa_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_device`
--
ALTER TABLE `user_device`
  ADD CONSTRAINT `fk_device_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `user_notification`
--
ALTER TABLE `user_notification`
  ADD CONSTRAINT `user_notification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
