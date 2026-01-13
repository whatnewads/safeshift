-- Migration: Add session_id column to AuditEvent table
-- Purpose: Enable session tracking for HIPAA compliance audit trails
-- Date: 2025-12-05
-- Related Issue: Column not found: 1054 Unknown column 'session_id' in 'field list'

-- Check if column exists before adding (safe migration)
SET @dbname = DATABASE();
SET @tablename = 'AuditEvent';
SET @columnname = 'session_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1 /* column already exists */',
  'ALTER TABLE AuditEvent ADD COLUMN session_id VARCHAR(128) NULL AFTER user_agent'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for session_id to improve query performance on session-based lookups
SET @indexExists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND INDEX_NAME = 'idx_audit_session_id');

SET @createIndex = IF(@indexExists = 0,
    'CREATE INDEX idx_audit_session_id ON AuditEvent(session_id)',
    'SELECT 1 /* index already exists */');

PREPARE createIndexStmt FROM @createIndex;
EXECUTE createIndexStmt;
DEALLOCATE PREPARE createIndexStmt;

-- Verification query (optional - run to verify the column was added)
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'AuditEvent' AND COLUMN_NAME = 'session_id';