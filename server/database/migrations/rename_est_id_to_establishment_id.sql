-- Migration: Rename est_id to establishment_id for OSHA API compliance
-- Date: 2025-01-14
-- Description: This migration renames the est_id column to establishment_id in the osha_establishment table
-- to comply with OSHA API reporting management standards

SET FOREIGN_KEY_CHECKS = 0;

-- Step 1: Drop the existing primary key constraint
ALTER TABLE `osha_establishment` 
DROP PRIMARY KEY;

-- Step 2: Rename the column from est_id to establishment_id
ALTER TABLE `osha_establishment` 
CHANGE COLUMN `est_id` `establishment_id` CHAR(36) NOT NULL;

-- Step 3: Re-add the primary key constraint with the new column name
ALTER TABLE `osha_establishment` 
ADD PRIMARY KEY (`establishment_id`);

-- Step 4: Update any foreign key references if they exist
-- Note: Based on the schema review, there don't appear to be any foreign key references to est_id

SET FOREIGN_KEY_CHECKS = 1;

-- Log the migration completion
SELECT 'Migration completed: est_id renamed to establishment_id' AS status;