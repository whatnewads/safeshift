# Database Migration Plan for New Role System

## Overview
This document outlines the database migration strategy for implementing the new 6-role system in SafeShift EHR.

## Pre-Migration Checklist
- [ ] Ensure all users are logged out
- [ ] Put application in maintenance mode
- [ ] Create full database backup
- [ ] Document current user-role assignments
- [ ] Test restoration procedure

## Migration Script Structure

### 1. Backup Creation
```sql
-- Create backup of current data
CREATE TABLE role_backup AS SELECT * FROM Role;
CREATE TABLE user_backup AS SELECT * FROM User;
CREATE TABLE userrole_backup AS SELECT * FROM UserRole;

-- Export full database backup
mysqldump -u safeshift_admin -p safeshift_ehr_001_0 > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Data Cleanup (ORDERED)
```sql
-- Step 1: Delete all user-role assignments
DELETE FROM UserRole;

-- Step 2: Delete all users (preserves audit trail)
DELETE FROM User;

-- Step 3: Clear roles table
DELETE FROM Role;
```

### 3. Insert New Roles
```sql
-- Insert new roles with UUIDs
INSERT INTO Role (role_id, name, description, created_at, updated_at) VALUES
(UUID(), 'tadmin', 'Technical Administrator - Full system access', NOW(), NOW()),
(UUID(), 'cadmin', 'Clinical Administrator - Clinical configuration access', NOW(), NOW()),
(UUID(), 'pclinician', 'Primary Clinician - Full clinical access', NOW(), NOW()),
(UUID(), 'dclinician', 'Delegated Clinician - Limited clinical access', NOW(), NOW()),
(UUID(), '1clinician', 'First Response Clinician - Emergency/urgent care focus', NOW(), NOW()),
(UUID(), 'custom', 'Custom Role - Configurable permissions', NOW(), NOW());
```

### 4. Role Permissions Structure
```sql
-- Create permissions table if not exists
CREATE TABLE IF NOT EXISTS role_permissions (
    permission_id CHAR(36) PRIMARY KEY,
    role_id CHAR(36) NOT NULL,
    resource VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL,
    allowed BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES Role(role_id) ON DELETE CASCADE
);

-- Define base permissions for each role
-- tadmin: All permissions
INSERT INTO role_permissions (permission_id, role_id, resource, action, allowed)
SELECT UUID(), role_id, resource, action, TRUE
FROM Role CROSS JOIN (
    SELECT 'system' as resource, 'manage' as action UNION ALL
    SELECT 'users', 'create' UNION ALL
    SELECT 'users', 'read' UNION ALL
    SELECT 'users', 'update' UNION ALL
    SELECT 'users', 'delete' UNION ALL
    SELECT 'patients', 'create' UNION ALL
    SELECT 'patients', 'read' UNION ALL
    SELECT 'patients', 'update' UNION ALL
    SELECT 'patients', 'delete' UNION ALL
    SELECT 'logs', 'read' UNION ALL
    SELECT 'settings', 'manage'
) permissions
WHERE Role.name = 'tadmin';

-- cadmin: Clinical management permissions
INSERT INTO role_permissions (permission_id, role_id, resource, action, allowed)
SELECT UUID(), role_id, resource, action, TRUE
FROM Role CROSS JOIN (
    SELECT 'clinical_protocols' as resource, 'manage' as action UNION ALL
    SELECT 'providers', 'manage' UNION ALL
    SELECT 'schedules', 'manage' UNION ALL
    SELECT 'reports', 'generate' UNION ALL
    SELECT 'patients', 'read' UNION ALL
    SELECT 'quality_metrics', 'manage'
) permissions
WHERE Role.name = 'cadmin';

-- 1clinician: Primary interface permissions (PRIORITY)
INSERT INTO role_permissions (permission_id, role_id, resource, action, allowed)
SELECT UUID(), role_id, resource, action, TRUE
FROM Role CROSS JOIN (
    SELECT 'patients' as resource, 'create' as action UNION ALL
    SELECT 'patients', 'read' UNION ALL
    SELECT 'patients', 'update' UNION ALL
    SELECT 'procedures', 'create' UNION ALL
    SELECT 'procedures', 'read' UNION ALL
    SELECT 'procedures', 'update' UNION ALL
    SELECT 'appointments', 'manage' UNION ALL
    SELECT 'prescreening', 'perform' UNION ALL
    SELECT 'vitals', 'record' UNION ALL
    SELECT 'emergency_protocols', 'access'
) permissions
WHERE Role.name = '1clinician';

-- pclinician: Full clinical permissions
INSERT INTO role_permissions (permission_id, role_id, resource, action, allowed)
SELECT UUID(), role_id, resource, action, TRUE
FROM Role CROSS JOIN (
    SELECT 'patients' as resource, 'create' as action UNION ALL
    SELECT 'patients', 'read' UNION ALL
    SELECT 'patients', 'update' UNION ALL
    SELECT 'medical_records', 'full_access' UNION ALL
    SELECT 'prescriptions', 'write' UNION ALL
    SELECT 'referrals', 'create' UNION ALL
    SELECT 'lab_orders', 'create' UNION ALL
    SELECT 'diagnoses', 'record'
) permissions
WHERE Role.name = 'pclinician';

-- dclinician: Limited clinical permissions
INSERT INTO role_permissions (permission_id, role_id, resource, action, allowed)
SELECT UUID(), role_id, resource, action, TRUE
FROM Role CROSS JOIN (
    SELECT 'patients' as resource, 'read' as action UNION ALL
    SELECT 'patients', 'update_limited' UNION ALL
    SELECT 'vitals', 'record' UNION ALL
    SELECT 'procedures', 'assist' UNION ALL
    SELECT 'notes', 'add'
) permissions
WHERE Role.name = 'dclinician';

-- custom: No default permissions (configured per deployment)
```

### 5. Create Test Users
```sql
-- Create test users for each role
INSERT INTO User (user_id, username, email, password_hash, mfa_enabled, status, created_at, updated_at) VALUES
(UUID(), 'tadmin_test', 'tadmin@safeshift.ai', '$2y$12$hashed_password_here', 1, 'active', NOW(), NOW()),
(UUID(), 'cadmin_test', 'cadmin@safeshift.ai', '$2y$12$hashed_password_here', 1, 'active', NOW(), NOW()),
(UUID(), '1clinician_test', '1clinician@safeshift.ai', '$2y$12$hashed_password_here', 0, 'active', NOW(), NOW()),
(UUID(), 'pclinician_test', 'pclinician@safeshift.ai', '$2y$12$hashed_password_here', 0, 'active', NOW(), NOW()),
(UUID(), 'dclinician_test', 'dclinician@safeshift.ai', '$2y$12$hashed_password_here', 0, 'active', NOW(), NOW());

-- Assign roles to test users
INSERT INTO UserRole (user_role_id, user_id, role_id, created_at)
SELECT UUID(), u.user_id, r.role_id, NOW()
FROM User u
JOIN Role r ON REPLACE(u.username, '_test', '') = r.name
WHERE u.username LIKE '%_test';
```

### 6. Update Routing Configuration
The following routes need to be updated in `index.php`:
- `/dashboards/tadmin` → Technical Admin Dashboard
- `/dashboards/cadmin` → Clinical Admin Dashboard
- `/dashboards/1clinician` → First Response Dashboard (PRIMARY)
- `/dashboards/pclinician` → Primary Clinician Dashboard
- `/dashboards/dclinician` → Delegated Clinician Dashboard
- `/dashboards/custom` → Custom Role Dashboard

### 7. Audit Trail Updates
```sql
-- Update audit event types for new roles
INSERT INTO audit_event_type (type_id, name, description) VALUES
(UUID(), 'ROLE_MIGRATION', 'System role migration event'),
(UUID(), 'TADMIN_ACCESS', 'Technical admin access event'),
(UUID(), 'CADMIN_ACCESS', 'Clinical admin access event'),
(UUID(), '1CLINICIAN_ACCESS', 'First response clinician access event');

-- Log migration event
INSERT INTO audit_event (event_id, event_type, entity_type, entity_id, user_id, event_data, created_at)
VALUES (UUID(), 'ROLE_MIGRATION', 'System', 'role_system', NULL, 
        JSON_OBJECT('action', 'migrate_to_6_roles', 'timestamp', NOW()), NOW());
```

## Rollback Plan
```sql
-- If migration fails, restore from backup
-- Step 1: Clear migrated data
DELETE FROM UserRole;
DELETE FROM User;
DELETE FROM Role;

-- Step 2: Restore from backup tables
INSERT INTO Role SELECT * FROM role_backup;
INSERT INTO User SELECT * FROM user_backup;
INSERT INTO UserRole SELECT * FROM userrole_backup;

-- Step 3: Drop backup tables after verification
DROP TABLE IF EXISTS role_backup;
DROP TABLE IF EXISTS user_backup;
DROP TABLE IF EXISTS userrole_backup;
```

## Post-Migration Verification
1. Verify all 6 roles exist in Role table
2. Check test users can login
3. Confirm role-based routing works
4. Test 1clinician dashboard access
5. Verify audit logging is functional
6. Check permissions are properly enforced

## Performance Considerations
- Add indexes on frequently queried columns:
```sql
CREATE INDEX idx_role_name ON Role(name);
CREATE INDEX idx_user_status ON User(status);
CREATE INDEX idx_userrole_userid ON UserRole(user_id);
CREATE INDEX idx_permissions_roleid ON role_permissions(role_id);
```

## Security Checklist
- [ ] All passwords use bcrypt with cost factor 12+
- [ ] MFA enabled for admin roles
- [ ] Session timeout configured
- [ ] Audit trail captures all role changes
- [ ] CSRF tokens implemented for all forms
- [ ] SQL injection prevention verified

## Timeline
1. **Backup & Preparation**: 30 minutes
2. **Data Cleanup**: 15 minutes
3. **Role Creation**: 15 minutes
4. **Permission Setup**: 30 minutes
5. **Testing**: 60 minutes
6. **Total Estimated Time**: 2.5 hours

## Contact for Issues
- Technical Lead: [Contact Info]
- Database Admin: [Contact Info]
- Emergency Rollback: [Procedure]