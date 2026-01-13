-- Phase 0: Critical Database Fixes for SafeShift EHR
-- Purpose: Fix immediate breaking issues to restore basic functionality
-- Date: 2025-11-07

-- 1. Add missing slug column to role table
ALTER TABLE role ADD COLUMN slug VARCHAR(50) AFTER name;

-- Update slug values based on existing role names
UPDATE role SET slug = '1clinician' WHERE name = '1clinician';
UPDATE role SET slug = 'pclinician' WHERE name = 'pclinician';
UPDATE role SET slug = 'dclinician' WHERE name = 'dclinician';
UPDATE role SET slug = 'cadmin' WHERE name = 'cadmin';
UPDATE role SET slug = 'tadmin' WHERE name = 'tadmin';
UPDATE role SET slug = 'employee' WHERE name = 'employee';
UPDATE role SET slug = 'employer' WHERE name = 'employer';
UPDATE role SET slug = 'custom' WHERE name = 'custom';

-- Make slug column unique and not null
ALTER TABLE role MODIFY COLUMN slug VARCHAR(50) NOT NULL UNIQUE;

-- 2. Fix table naming inconsistencies (API expects singular names)
-- Check if tables exist before renaming to avoid errors

-- Only rename if the plural table doesn't exist and singular does
-- Note: This approach keeps the existing plural naming convention and we'll fix the API instead