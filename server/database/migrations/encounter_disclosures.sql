-- Encounter Disclosures Migration
-- SafeShift EHR - Terms & Conditions / Disclosures for Signature Tab
-- Date: 2025-12-28
-- 
-- TODO: LEGAL REVIEW REQUIRED - The disclosure text content in this migration
-- should be reviewed by legal counsel before production deployment.

-- =====================================================
-- Encounter Disclosures Table
-- Tracks which disclosures were acknowledged at signature time
-- =====================================================

CREATE TABLE IF NOT EXISTS encounter_disclosures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encounter_id CHAR(36) NOT NULL,
    disclosure_type ENUM('general_consent', 'privacy_practices', 'work_related_auth', 'hipaa_acknowledgment') NOT NULL,
    disclosure_version VARCHAR(20) DEFAULT '1.0',
    disclosure_text TEXT NOT NULL COMMENT 'Full text of disclosure at time of acknowledgment for legal record',
    acknowledged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by_patient BOOLEAN DEFAULT TRUE,
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
    user_agent TEXT DEFAULT NULL COMMENT 'Browser/device information for audit trail',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encounter_id) REFERENCES encounters(encounter_id) ON DELETE CASCADE,
    INDEX idx_disclosure_encounter (encounter_id),
    INDEX idx_disclosure_type (disclosure_type),
    INDEX idx_disclosure_acknowledged (acknowledged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Disclosure Templates Table
-- Stores the current version of each disclosure type
-- =====================================================

CREATE TABLE IF NOT EXISTS disclosure_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disclosure_type VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL COMMENT 'TODO: Content requires legal review before production',
    version VARCHAR(20) DEFAULT '1.0',
    is_active BOOLEAN DEFAULT TRUE,
    requires_work_related BOOLEAN DEFAULT FALSE COMMENT 'Only show for work-related incidents',
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_type (disclosure_type),
    INDEX idx_template_active (is_active),
    INDEX idx_template_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert Default Disclosure Templates
-- TODO: LEGAL REVIEW REQUIRED - All disclosure text below needs legal approval
-- =====================================================

INSERT INTO disclosure_templates (disclosure_type, title, content, version, requires_work_related, display_order) VALUES
('general_consent', 'Consent for Documentation', 
'By signing below, I acknowledge that:

1. The information provided is accurate to the best of my knowledge.
2. I consent to this encounter being documented in SafeShift EHR.
3. I have received or been offered a copy of the Notice of Privacy Practices.', 
'1.0', FALSE, 1),

('work_related_auth', 'Work-Related Incident Authorization',
'WORK-RELATED INCIDENT AUTHORIZATION

Because this incident has been classified as work-related, I authorize SafeShift and the treating facility to:

1. Notify my employer''s designated safety contacts with a summary of this incident as required by workplace safety protocols and OSHA regulations.

2. This summary notification will include:
   • Date and general location of incident
   • Type of incident (not specific injury details)
   • Brief description for safety documentation

3. This summary will NOT include:
   • Specific medical diagnosis or treatment details
   • Personal medical history
   • Information unrelated to the workplace incident

I understand this authorization is required for work-related incidents to comply with occupational safety and health reporting requirements.',
'1.0', TRUE, 2);

-- =====================================================
-- Add index for faster encounter disclosure lookups
-- =====================================================

-- Create composite index for common query patterns
CREATE INDEX IF NOT EXISTS idx_disclosure_encounter_type 
ON encounter_disclosures (encounter_id, disclosure_type);
