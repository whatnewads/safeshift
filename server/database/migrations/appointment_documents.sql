-- Appointment Documents Table Migration
-- Created for SafeShift EHR Appointment Photo Upload Feature
-- Stores document/photo uploads associated with appointments

CREATE TABLE IF NOT EXISTS appointment_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encounter_id INT NOT NULL,
    appointment_id INT DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    document_type ENUM('appointment_card', 'referral', 'prescription', 'other') DEFAULT 'other',
    notes TEXT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT DEFAULT NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_at TIMESTAMP DEFAULT NULL,
    deleted_by INT DEFAULT NULL,
    FOREIGN KEY (encounter_id) REFERENCES encounters(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_doc_encounter (encounter_id),
    INDEX idx_doc_deleted (is_deleted),
    INDEX idx_doc_type (document_type),
    INDEX idx_doc_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment for documentation
ALTER TABLE appointment_documents COMMENT = 'Storage for appointment-related documents and photos uploaded through SafeShift EHR';
