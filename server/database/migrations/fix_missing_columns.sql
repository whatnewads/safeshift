-- Migration: Fix missing columns for AuditService and UserRole
-- Date: 2025-12-25
-- Purpose: Add missing columns that cause errors in AuditService and UserRepository
--
-- Issues Fixed:
-- 1. AuditService error: "Column not found: 1054 Unknown column 'checksum' in 'field list'"
-- 2. UserRepository error: "Column not found: 1054 Unknown column 'ur.assigned_at' in 'field list'"

-- =====================================================
-- FIX 1: Add checksum column to auditevent table
-- =====================================================
-- The AuditService uses SHA-256 hashing for tamper detection
-- SHA-256 produces a 64-character hexadecimal string
-- Used in: core/Services/AuditService.php (lines 52, 58, 76, 220, 444)

ALTER TABLE `auditevent`
ADD COLUMN IF NOT EXISTS `checksum` CHAR(64) NULL 
COMMENT 'SHA-256 hash for tamper detection / integrity verification'
AFTER `flagged`;

-- =====================================================
-- FIX 2: Add assigned_at column to userrole table
-- =====================================================
-- The assigned_at column tracks when a role was assigned to a user
-- Used in: create_test_user.php (line 144)

ALTER TABLE `userrole`
ADD COLUMN IF NOT EXISTS `assigned_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP
COMMENT 'Timestamp when the role was assigned to the user'
AFTER `role_id`;

-- =====================================================
-- VERIFICATION QUERIES (optional - run manually to verify)
-- =====================================================
-- Check auditevent table structure:
-- DESCRIBE auditevent;
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auditevent' AND COLUMN_NAME = 'checksum';

-- Check userrole table structure:
-- DESCRIBE userrole;
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'userrole' AND COLUMN_NAME = 'assigned_at';
