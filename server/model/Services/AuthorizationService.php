<?php
/**
 * Authorization Service
 * 
 * Provides permission checking and access control for the SafeShift EHR system.
 * Used by ViewModels to enforce role-based access control (RBAC).
 * 
 * @package SafeShift\Model\Services
 */

namespace Model\Services;

use Model\Services\RoleService;
use Exception;

/**
 * AuthorizationService - Centralized authorization logic
 * 
 * Handles permission checks, resource access validation, and data filtering
 * based on user roles and clinic assignments.
 */
class AuthorizationService
{
    // =========================================================================
    // Permission Check Methods
    // =========================================================================
    
    /**
     * Check if user can perform an action on a resource
     * 
     * @param array $user User data with 'role' key
     * @param string $action The action (e.g., 'view', 'create', 'edit', 'delete')
     * @param string $resource The resource type (e.g., 'patient', 'encounter')
     * @return bool True if user can perform the action
     * 
     * @example
     * ```php
     * $canView = AuthorizationService::can($user, 'view', 'patient'); // true/false
     * ```
     */
    public static function can(array $user, string $action, string $resource): bool
    {
        $role = self::extractRole($user);
        $permission = $resource . '.' . $action;
        
        return RoleService::hasPermission($role, $permission);
    }
    
    /**
     * Check if user can view a specific patient
     * Considers both permission and clinic assignment
     * 
     * @param array $user User data with 'role' and optionally 'clinic_id'
     * @param string $patientId The patient ID to check
     * @param string|null $patientClinicId The patient's clinic ID (optional)
     * @return bool True if user can view the patient
     */
    public static function canViewPatient(array $user, string $patientId, ?string $patientClinicId = null): bool
    {
        // First check basic permission
        if (!self::can($user, 'view', 'patient')) {
            return false;
        }
        
        // Super admins can view all patients
        if (self::isSuperAdmin($user)) {
            return true;
        }
        
        // If no clinic restriction, allow access
        if ($patientClinicId === null) {
            return true;
        }
        
        // Check clinic-based access
        return self::hasClinicAccess($user, $patientClinicId);
    }
    
    /**
     * Check if user can edit a specific encounter
     * Considers permission, encounter status, and ownership
     * 
     * @param array $user User data
     * @param array $encounter Encounter data with 'status', 'provider_id', 'clinic_id'
     * @return bool True if user can edit the encounter
     */
    public static function canEditEncounter(array $user, array $encounter): bool
    {
        // Check basic permission
        if (!self::can($user, 'edit', 'encounter') && !self::can($user, 'create', 'encounter')) {
            return false;
        }
        
        // Super admins can edit anything
        if (self::isSuperAdmin($user)) {
            return true;
        }
        
        $status = $encounter['status'] ?? '';
        $providerId = $encounter['provider_id'] ?? null;
        $encounterClinicId = $encounter['clinic_id'] ?? null;
        $userId = $user['user_id'] ?? $user['id'] ?? null;
        
        // Signed/locked encounters require special permission to amend
        if (in_array($status, ['signed', 'locked', 'finalized'], true)) {
            // Only providers with amend permission can edit signed encounters
            return RoleService::hasPermission(self::extractRole($user), 'encounter.amend');
        }
        
        // Managers and admins can edit any encounter in their clinic
        if (self::isAdmin($user) || self::isManager($user)) {
            return self::hasClinicAccess($user, $encounterClinicId);
        }
        
        // Providers can only edit their own encounters
        if ($providerId !== null && $userId !== null) {
            return $providerId === $userId;
        }
        
        return true;
    }
    
    /**
     * Check if user can sign/finalize an encounter
     * 
     * @param array $user User data
     * @param array $encounter Encounter data
     * @return bool True if user can sign the encounter
     */
    public static function canSignEncounter(array $user, array $encounter): bool
    {
        $role = self::extractRole($user);
        
        // Must have sign permission
        if (!RoleService::hasPermission($role, 'encounter.sign')) {
            return false;
        }
        
        // Super admins can sign anything
        if (self::isSuperAdmin($user)) {
            return true;
        }
        
        $providerId = $encounter['provider_id'] ?? null;
        $userId = $user['user_id'] ?? $user['id'] ?? null;
        
        // Only the assigned provider can sign their encounter
        return $providerId !== null && $userId !== null && $providerId === $userId;
    }
    
    /**
     * Check if user has admin privileges
     * 
     * @param array $user User data
     * @return bool True if user is admin level
     */
    public static function isAdmin(array $user): bool
    {
        return RoleService::isAdmin(self::extractRole($user));
    }
    
    /**
     * Check if user has super admin privileges
     * 
     * @param array $user User data
     * @return bool True if user is super admin
     */
    public static function isSuperAdmin(array $user): bool
    {
        return RoleService::isSuperAdmin(self::extractRole($user));
    }
    
    /**
     * Check if user is a manager
     * 
     * @param array $user User data
     * @return bool True if user is a manager
     */
    public static function isManager(array $user): bool
    {
        $role = self::extractRole($user);
        return $role === RoleService::ROLE_MANAGER;
    }
    
    /**
     * Check if user is a clinical provider
     * 
     * @param array $user User data
     * @return bool True if user is a clinical role
     */
    public static function isClinician(array $user): bool
    {
        $role = self::extractRole($user);
        $clinicalRoles = [
            RoleService::ROLE_1CLINICIAN,
            RoleService::ROLE_DCLINICIAN,
            RoleService::ROLE_PCLINICIAN,
        ];
        
        return in_array($role, $clinicalRoles, true);
    }
    
    // =========================================================================
    // Data Filtering Methods
    // =========================================================================
    
    /**
     * Filter data based on user's role and clinic access
     * Used to restrict query results to authorized records
     * 
     * @param array $user User data
     * @param array $data Array of records to filter
     * @param string $clinicIdField Field name containing clinic ID
     * @return array Filtered records
     * 
     * @example
     * ```php
     * $patients = $repository->getAll();
     * $filteredPatients = AuthorizationService::filterByAccess($user, $patients);
     * ```
     */
    public static function filterByAccess(array $user, array $data, string $clinicIdField = 'clinic_id'): array
    {
        // Super admins see everything
        if (self::isSuperAdmin($user)) {
            return $data;
        }
        
        // Get user's clinic ID
        $userClinicId = $user['clinic_id'] ?? null;
        
        // If user has no clinic assignment, filter everything (safety measure)
        if ($userClinicId === null) {
            // Admins and managers may see cross-clinic data based on config
            if (self::isAdmin($user) || self::isManager($user)) {
                return $data;
            }
            return [];
        }
        
        // Filter to user's clinic only
        return array_filter($data, function($record) use ($userClinicId, $clinicIdField) {
            $recordClinicId = $record[$clinicIdField] ?? null;
            // Include records with no clinic assignment or matching clinic
            return $recordClinicId === null || $recordClinicId === $userClinicId;
        });
    }
    
    /**
     * Filter encounters to only those the user can access
     * 
     * @param array $user User data
     * @param array $encounters Array of encounter records
     * @return array Filtered encounters
     */
    public static function filterEncounters(array $user, array $encounters): array
    {
        // Super admins see everything
        if (self::isSuperAdmin($user)) {
            return $encounters;
        }
        
        $userId = $user['user_id'] ?? $user['id'] ?? null;
        $userClinicId = $user['clinic_id'] ?? null;
        $role = self::extractRole($user);
        
        return array_filter($encounters, function($encounter) use ($userId, $userClinicId, $role) {
            // Admins and managers see all encounters in their clinic
            if (RoleService::isAdmin($role) || $role === RoleService::ROLE_MANAGER) {
                $encounterClinicId = $encounter['clinic_id'] ?? null;
                return $encounterClinicId === null || $encounterClinicId === $userClinicId;
            }
            
            // QA can view all encounters for review
            if ($role === RoleService::ROLE_QA) {
                $encounterClinicId = $encounter['clinic_id'] ?? null;
                return $encounterClinicId === null || $encounterClinicId === $userClinicId;
            }
            
            // Clinical staff see encounters they're assigned to or created
            $providerId = $encounter['provider_id'] ?? null;
            $createdBy = $encounter['created_by'] ?? null;
            
            return $providerId === $userId || $createdBy === $userId;
        });
    }
    
    // =========================================================================
    // Permission Enforcement Methods
    // =========================================================================
    
    /**
     * Require a specific permission or throw exception
     * 
     * @param array $user User data
     * @param string $permission Permission string (e.g., 'patient.create')
     * @throws Exception If user lacks the permission
     */
    public static function requirePermission(array $user, string $permission): void
    {
        $role = self::extractRole($user);
        
        if (!RoleService::hasPermission($role, $permission)) {
            throw new Exception(
                "Access denied: Permission '$permission' required. " .
                "User role '$role' does not have this permission."
            );
        }
    }
    
    /**
     * Require admin role or throw exception
     * 
     * @param array $user User data
     * @throws Exception If user is not an admin
     */
    public static function requireAdmin(array $user): void
    {
        if (!self::isAdmin($user)) {
            throw new Exception(
                "Access denied: Administrator privileges required."
            );
        }
    }
    
    /**
     * Require super admin role or throw exception
     * 
     * @param array $user User data
     * @throws Exception If user is not a super admin
     */
    public static function requireSuperAdmin(array $user): void
    {
        if (!self::isSuperAdmin($user)) {
            throw new Exception(
                "Access denied: Super Administrator privileges required."
            );
        }
    }
    
    /**
     * Require access to a specific clinic or throw exception
     * 
     * @param array $user User data
     * @param string $clinicId Clinic ID to check
     * @throws Exception If user cannot access the clinic
     */
    public static function requireClinicAccess(array $user, string $clinicId): void
    {
        if (!self::hasClinicAccess($user, $clinicId)) {
            throw new Exception(
                "Access denied: You do not have access to this clinic's data."
            );
        }
    }
    
    // =========================================================================
    // Helper Methods
    // =========================================================================
    
    /**
     * Extract role from user data (handles various data structures)
     * 
     * @param array $user User data
     * @return string Backend role string
     */
    private static function extractRole(array $user): string
    {
        // Direct role field
        if (isset($user['role']) && is_string($user['role'])) {
            return $user['role'];
        }
        
        // Primary role object
        if (isset($user['primary_role']['slug'])) {
            return $user['primary_role']['slug'];
        }
        
        if (isset($user['primary_role']['name'])) {
            return $user['primary_role']['name'];
        }
        
        // Roles array (take first)
        if (isset($user['roles']) && is_array($user['roles']) && !empty($user['roles'])) {
            $firstRole = $user['roles'][0];
            if (is_string($firstRole)) {
                return $firstRole;
            }
            if (isset($firstRole['slug'])) {
                return $firstRole['slug'];
            }
            if (isset($firstRole['name'])) {
                return $firstRole['name'];
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Check if user has access to a specific clinic
     * 
     * @param array $user User data
     * @param string|null $clinicId Clinic ID to check
     * @return bool True if user has access
     */
    private static function hasClinicAccess(array $user, ?string $clinicId): bool
    {
        // Super admins access all clinics
        if (self::isSuperAdmin($user)) {
            return true;
        }
        
        // No clinic specified = no restriction
        if ($clinicId === null) {
            return true;
        }
        
        // Check user's clinic assignment
        $userClinicId = $user['clinic_id'] ?? null;
        
        if ($userClinicId === null) {
            // User has no clinic assignment - depends on role
            // Admins and managers may have multi-clinic access
            return self::isAdmin($user) || self::isManager($user);
        }
        
        return $userClinicId === $clinicId;
    }
    
    /**
     * Get a list of permissions the user has
     * Useful for debugging and audit logging
     * 
     * @param array $user User data
     * @return array<string> List of permission strings
     */
    public static function getUserPermissions(array $user): array
    {
        return RoleService::getPermissions(self::extractRole($user));
    }
    
    /**
     * Get audit context for permission checks
     * Useful for logging authorization decisions
     * 
     * @param array $user User data
     * @param string $action Action being performed
     * @param string $resource Resource being accessed
     * @param bool $allowed Whether access was allowed
     * @return array Audit context data
     */
    public static function getAuditContext(
        array $user,
        string $action,
        string $resource,
        bool $allowed
    ): array {
        return [
            'user_id' => $user['user_id'] ?? $user['id'] ?? 'unknown',
            'username' => $user['username'] ?? 'unknown',
            'role' => self::extractRole($user),
            'action' => $action,
            'resource' => $resource,
            'allowed' => $allowed,
            'clinic_id' => $user['clinic_id'] ?? null,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
