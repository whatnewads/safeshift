-- Migration: Add 2FA codes and mail log tables for Amazon SES integration
-- Date: 2025-12-31
-- Description: Creates tables for 2FA verification codes and email logging

-- ============================================================================
-- Table: two_factor_codes
-- Stores hashed 2FA verification codes with rate limiting support
-- ============================================================================
CREATE TABLE IF NOT EXISTS `two_factor_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` CHAR(36) NOT NULL COMMENT 'References user.user_id',
    `code_hash` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash of 6-digit code',
    `purpose` ENUM('login', 'password_reset', 'email_change', 'security') NOT NULL DEFAULT 'login',
    `expires_at` DATETIME NOT NULL COMMENT 'Code expiration time (usually 10 mins)',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Failed verification attempts',
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Max allowed attempts before invalidation',
    `consumed_at` DATETIME DEFAULT NULL COMMENT 'When code was successfully used',
    `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP that requested the code',
    `user_agent` VARCHAR(512) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_2fa_user_id` (`user_id`),
    INDEX `idx_2fa_expires` (`expires_at`),
    INDEX `idx_2fa_user_purpose` (`user_id`, `purpose`, `expires_at`),
    CONSTRAINT `fk_2fa_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: mail_log
-- Logs all outgoing emails for audit and debugging
-- ============================================================================
CREATE TABLE IF NOT EXISTS `mail_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` CHAR(36) DEFAULT NULL COMMENT 'User who triggered the email (if applicable)',
    `recipient_email` VARCHAR(320) NOT NULL COMMENT 'Masked or full email based on sensitivity',
    `email_type` VARCHAR(64) NOT NULL COMMENT 'otp, password_reset, reminder, notification, work_related',
    `subject` VARCHAR(500) DEFAULT NULL,
    `body_preview` VARCHAR(500) DEFAULT NULL COMMENT 'First 500 chars for debugging',
    `status` ENUM('queued', 'sent', 'failed', 'bounced', 'complained') NOT NULL DEFAULT 'queued',
    `ses_message_id` VARCHAR(100) DEFAULT NULL COMMENT 'Amazon SES Message ID for tracking',
    `error_message` TEXT DEFAULT NULL COMMENT 'Error details if failed',
    `sent_at` DATETIME DEFAULT NULL COMMENT 'When email was actually sent',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_mail_user` (`user_id`),
    INDEX `idx_mail_type` (`email_type`),
    INDEX `idx_mail_status` (`status`),
    INDEX `idx_mail_created` (`created_at`),
    INDEX `idx_mail_recipient` (`recipient_email`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: email_rate_limits
-- Tracks rate limiting for email sends per user
-- ============================================================================
CREATE TABLE IF NOT EXISTS `email_rate_limits` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` CHAR(36) NOT NULL,
    `email_type` VARCHAR(64) NOT NULL COMMENT 'Type of email being rate limited',
    `count` INT UNSIGNED NOT NULL DEFAULT 1,
    `window_start` DATETIME NOT NULL COMMENT 'Start of rate limit window',
    `last_sent_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rate_user_type` (`user_id`, `email_type`),
    INDEX `idx_rate_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Add columns to user table for reminder tracking
-- ============================================================================
ALTER TABLE `user`
    ADD COLUMN IF NOT EXISTS `last_reminder_sent_at` DATETIME DEFAULT NULL COMMENT 'Last inactivity reminder email sent',
    ADD COLUMN IF NOT EXISTS `email_opt_in_reminders` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'User opted into inactivity reminders',
    ADD COLUMN IF NOT EXISTS `email_opt_in_security` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'User opted into security emails (2FA, etc)';

-- Add index for reminder query performance
ALTER TABLE `user`
    ADD INDEX IF NOT EXISTS `idx_user_reminder` (`last_login`, `last_reminder_sent_at`, `email_opt_in_reminders`);

-- ============================================================================
-- View: users_needing_reminder
-- Users who haven't logged in for 3+ days with unread notifications
-- ============================================================================
CREATE OR REPLACE VIEW `users_needing_reminder` AS
SELECT 
    u.user_id,
    u.username,
    u.email,
    u.last_login,
    u.last_reminder_sent_at,
    COUNT(CASE WHEN n.is_read = 0 THEN 1 END) as unread_notifications
FROM `user` u
LEFT JOIN `user_notification` n ON u.user_id = n.user_id
WHERE 
    u.is_active = 1
    AND u.email_opt_in_reminders = 1
    AND u.status = 'active'
    AND (
        u.last_login IS NULL 
        OR u.last_login <= DATE_SUB(NOW(), INTERVAL 3 DAY)
    )
    AND (
        u.last_reminder_sent_at IS NULL 
        OR u.last_reminder_sent_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
    )
GROUP BY u.user_id
HAVING unread_notifications > 0;

-- ============================================================================
-- Cleanup: Remove old 2FA codes (run via cron)
-- ============================================================================
-- DELETE FROM two_factor_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- ============================================================================
-- Cleanup: Rotate mail logs older than 90 days (run via cron)
-- ============================================================================
-- DELETE FROM mail_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
