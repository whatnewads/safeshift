-- Email Logs Migration
-- Stores email send attempts for audit trail and troubleshooting
-- Created: 2024-12-28

-- Create the email_logs table
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encounter_id INT DEFAULT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    email_type ENUM('work_related_notification', 'follow_up', 'system', 'otp', 'password_reset') DEFAULT 'work_related_notification',
    subject VARCHAR(500) NOT NULL,
    body_preview VARCHAR(500) DEFAULT NULL COMMENT 'First 500 chars of email body for reference',
    status ENUM('pending', 'sent', 'delivered', 'failed', 'bounced') DEFAULT 'pending',
    error_message TEXT DEFAULT NULL COMMENT 'Error details if status is failed',
    sent_at TIMESTAMP DEFAULT NULL COMMENT 'Timestamp when email was actually sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    FOREIGN KEY (encounter_id) REFERENCES encounters(encounter_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_email_encounter (encounter_id),
    INDEX idx_email_status (status),
    INDEX idx_email_type (email_type),
    INDEX idx_email_recipient (recipient_email(100)),
    INDEX idx_email_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comments for documentation
ALTER TABLE email_logs 
    COMMENT = 'Audit log for all email notifications sent from the system. Used for tracking work-related incident notifications, OTPs, and other system emails.';

-- Sample query to get email statistics
-- SELECT email_type, status, COUNT(*) as count 
-- FROM email_logs 
-- WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
-- GROUP BY email_type, status;

-- Sample query to get failed emails for retry
-- SELECT * FROM email_logs 
-- WHERE status = 'failed' 
-- AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
-- ORDER BY created_at DESC;
