-- Add missing columns to User table
-- This migration adds lockout_until, last_login, and is_active columns

-- Add lockout_until column for account lockout functionality
ALTER TABLE `User` 
ADD COLUMN `lockout_until` DATETIME NULL DEFAULT NULL 
COMMENT 'Timestamp until which the account is locked' 
AFTER `status`;

-- Add last_login column to track user login times
ALTER TABLE `User` 
ADD COLUMN `last_login` DATETIME NULL DEFAULT NULL 
COMMENT 'Last successful login timestamp' 
AFTER `lockout_until`;

-- Add is_active column for additional user status tracking
ALTER TABLE `User` 
ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 
COMMENT 'Whether the user account is active' 
AFTER `last_login`;

-- Update existing users to set is_active based on status
UPDATE `User` 
SET `is_active` = CASE 
    WHEN `status` = 'active' THEN 1 
    ELSE 0 
END;

-- Add index on is_active for performance
ALTER TABLE `User` 
ADD INDEX `idx_is_active` (`is_active`);

-- Add index on lockout_until for performance
ALTER TABLE `User` 
ADD INDEX `idx_lockout_until` (`lockout_until`);