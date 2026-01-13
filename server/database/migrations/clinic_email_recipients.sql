-- Clinic Email Recipients Migration
-- Stores email addresses for work-related incident notifications
-- Created: 2024-12-28

-- Create the clinic_email_recipients table
CREATE TABLE IF NOT EXISTS clinic_email_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clinic_id INT NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    recipient_type ENUM('work_related', 'all') DEFAULT 'work_related',
    recipient_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_clinic_recipients (clinic_id, is_active),
    INDEX idx_recipient_type (recipient_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comments for documentation
ALTER TABLE clinic_email_recipients 
    COMMENT = 'Stores email recipients for clinic notifications, particularly work-related incident reports';
