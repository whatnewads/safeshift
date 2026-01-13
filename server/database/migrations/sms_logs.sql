-- SMS Logs Table Migration
-- Created for SafeShift EHR SMS Reminder Feature
-- Stores all SMS messages sent through the system for audit trail

CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    encounter_id INT DEFAULT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message_content TEXT NOT NULL,
    message_type ENUM('appointment_reminder', 'follow_up', 'general') DEFAULT 'appointment_reminder',
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    provider VARCHAR(50) DEFAULT 'twilio',
    provider_message_id VARCHAR(100) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (encounter_id) REFERENCES encounters(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sms_patient (patient_id),
    INDEX idx_sms_encounter (encounter_id),
    INDEX idx_sms_status (status),
    INDEX idx_sms_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment for documentation
ALTER TABLE sms_logs COMMENT = 'Audit log for all SMS messages sent through the SafeShift EHR system';
