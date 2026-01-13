<?php
/**
 * Role Mapping and Permission Service
 * 
 * Centralizes role logic for frontend-backend consistency.
 * Maps backend database roles to frontend UI roles and manages permissions.
 * 
 * @package SafeShift\Model\Services
 */

namespace Model\Services;

/**
 * RoleService - Centralized role management
 * 
 * Backend Roles (from database):
 * - '1clinician' - Intake clinician (registration/front desk)
 * - 'dclinician' - Drug screen clinician/technician
 * - 'pclinician' - Primary clinician/clinical provider
 * - 'cadmin' - Clinic administrator
 * - 'tadmin' - Technical/Tenant administrator
 * - 'Admin' - System super administrator
 * - 'Manager' - Manager role
 * - 'QA' - Quality assurance
 * - 'PrivacyOfficer' - Privacy compliance officer
 * - 'SecurityOfficer' - Security compliance officer
 */
class RoleService
{
    // =========================================================================
    // Backend Database Role Constants
    // =========================================================================
    
    /** @var string Intake clinician - handles patient registration/check-in */
    public const ROLE_1CLINICIAN = '1clinician';
    
    /** @var string Drug screen clinician - handles DOT testing */
    public const ROLE_DCLINICIAN = 'dclinician';
    
    /** @var string Primary clinician - clinical provider */
    public const ROLE_PCLINICIAN = 'pclinician';
    
    /** @var string Clinic administrator */
    public const ROLE_CADMIN = 'cadmin';
    
    /** @var string Technical/Tenant administrator */
    public const ROLE_TADMIN = 'tadmin';
    
    /** @var string System super administrator */
    public const ROLE_ADMIN = 'Admin';
    
    /** @var string Manager role */
    public const ROLE_MANAGER = 'Manager';
    
    /** @var string Quality assurance role */
    public const ROLE_QA = 'QA';
    
    /** @var string Privacy officer role */
    public const ROLE_PRIVACY_OFFICER = 'PrivacyOfficer';
    
    /** @var string Security officer role */
    public const ROLE_SECURITY_OFFICER = 'SecurityOfficer';
    
    // =========================================================================
    // UI Role Constants (Frontend)
    // =========================================================================
    
    /** @var string Provider UI role */
    public const UI_ROLE_PROVIDER = 'provider';
    
    /** @var string Registration UI role */
    public const UI_ROLE_REGISTRATION = 'registration';
    
    /** @var string Admin UI role */
    public const UI_ROLE_ADMIN = 'admin';
    
    /** @var string Super admin UI role */
    public const UI_ROLE_SUPER_ADMIN = 'super-admin';
    
    /** @var string Technician UI role */
    public const UI_ROLE_TECHNICIAN = 'technician';
    
    /** @var string Manager UI role */
    public const UI_ROLE_MANAGER = 'manager';
    
    /** @var string QA UI role */
    public const UI_ROLE_QA = 'qa';
    
    /** @var string Privacy officer UI role */
    public const UI_ROLE_PRIVACY_OFFICER = 'privacy-officer';
    
    /** @var string Security officer UI role */
    public const UI_ROLE_SECURITY_OFFICER = 'security-officer';
    
    // =========================================================================
    // Role Mappings
    // =========================================================================
    
    /**
     * Map backend database roles to frontend UI roles
     * IMPORTANT: Must stay in sync with frontend roleMapper.ts
     * 
     * @var array<string, string>
     */
    private const UI_ROLE_MAP = [
        // Clinical roles
        '1clinician'      => 'registration',    // Intake clinician → registration
        'dclinician'      => 'technician',      // Drug screen → technician
        'pclinician'      => 'provider',        // Primary clinician → provider
        
        // Administrative roles
        'cadmin'          => 'admin',           // Clinic admin → admin
        'tadmin'          => 'admin',           // Tech admin → admin
        'Admin'           => 'super-admin',     // System admin → super-admin
        
        // Other roles
        'Manager'         => 'manager',         // Manager → manager
        'QA'              => 'qa',              // QA → qa
        'PrivacyOfficer'  => 'privacy-officer', // Privacy officer
        'SecurityOfficer' => 'security-officer', // Security officer
        
        // Legacy/alternate role names (for backwards compatibility)
        'clinician'       => 'provider',
        'Clinician'       => 'provider',
        'Provider'        => 'provider',
        'provider'        => 'provider',
        'Doctor'          => 'provider',
        'doctor'          => 'provider',
        'Nurse'           => 'provider',
        'nurse'           => 'provider',
        'NP'              => 'provider',
        'PA'              => 'provider',
        'ClinicAdmin'     => 'admin',
        'TenantAdmin'     => 'admin',
        'admin'           => 'admin',
        'SuperAdmin'      => 'super-admin',
        'super-admin'     => 'super-admin',
        'superadmin'      => 'super-admin',
        'manager'         => 'manager',
        'qa'              => 'qa',
        'QualityAssurance' => 'qa',
        'privacy-officer' => 'privacy-officer',
        'privacyofficer'  => 'privacy-officer',
        'security-officer' => 'security-officer',
        'securityofficer' => 'security-officer',
        'Technician'      => 'technician',
        'technician'      => 'technician',
        'LabTech'         => 'technician',
        'labtech'         => 'technician',
        'Registration'    => 'registration',
        'registration'    => 'registration',
        'FrontDesk'       => 'registration',
        'frontdesk'       => 'registration',
        'Receptionist'    => 'registration',
        'receptionist'    => 'registration',
    ];
    
    /**
     * Human-readable display names for backend roles
     * 
     * @var array<string, string>
     */
    private const ROLE_DISPLAY_NAMES = [
        '1clinician'      => 'Intake Clinician',
        'dclinician'      => 'Drug Screen Technician',
        'pclinician'      => 'Clinical Provider',
        'cadmin'          => 'Clinic Administrator',
        'tadmin'          => 'Technical Administrator',
        'Admin'           => 'System Administrator',
        'Manager'         => 'Manager',
        'QA'              => 'Quality Assurance',
        'PrivacyOfficer'  => 'Privacy Officer',
        'SecurityOfficer' => 'Security Officer',
    ];
    
    /**
     * Dashboard routes for each backend role
     * 
     * @var array<string, string>
     */
    private const DASHBOARD_ROUTES = [
        '1clinician'      => '/dashboard/registration',
        'dclinician'      => '/dashboard/technician',
        'pclinician'      => '/dashboard/provider',
        'cadmin'          => '/dashboard/admin',
        'tadmin'          => '/dashboard/admin',
        'Admin'           => '/dashboard/super-admin',
        'Manager'         => '/dashboard/manager',
        'QA'              => '/dashboard/qa',
        'PrivacyOfficer'  => '/dashboard/privacy',
        'SecurityOfficer' => '/dashboard/security',
    ];
    
    // =========================================================================
    // Permissions
    // =========================================================================
    
    /**
     * Permissions assigned to each backend role
     * Wildcards (*) grant full access to a resource category
     * 
     * @var array<string, array<string>>
     */
    private const ROLE_PERMISSIONS = [
        '1clinician' => [
            'patient.view',
            'patient.create',
            'encounter.view',
        ],
        
        'dclinician' => [
            'patient.view',
            'encounter.view',
            'encounter.create',
            'dot.manage',
        ],
        
        'pclinician' => [
            'patient.view',
            'patient.create',
            'patient.edit',
            'encounter.view',
            'encounter.create',
            'encounter.sign',
            'vitals.record',
        ],
        
        'cadmin' => [
            'patient.*',
            'encounter.*',
            'user.view',
            'reports.view',
        ],
        
        'tadmin' => [
            'patient.*',
            'encounter.*',
            'user.view',
            'system.configure',
        ],
        
        'Admin' => [
            '*', // Full system access
        ],
        
        'Manager' => [
            'patient.*',
            'encounter.*',
            'user.*',
            'reports.*',
            'osha.*',
        ],
        
        'QA' => [
            'patient.view',
            'encounter.view',
            'encounter.review',
            'reports.view',
        ],
        
        'PrivacyOfficer' => [
            'patient.view',
            'encounter.view',
            'audit.view',
            'reports.view',
            'privacy.*',
        ],
        
        'SecurityOfficer' => [
            'audit.view',
            'audit.export',
            'security.*',
            'user.view',
            'reports.view',
        ],
    ];
    
    /**
     * All available permissions in the system
     * Used for documentation and validation
     * 
     * @var array<string>
     */
    private const ALL_PERMISSIONS = [
        // Patient permissions
        'patient.view',
        'patient.create',
        'patient.edit',
        'patient.delete',
        'patient.*',
        
        // Encounter permissions
        'encounter.view',
        'encounter.create',
        'encounter.edit',
        'encounter.sign',
        'encounter.review',
        'encounter.amend',
        'encounter.*',
        
        // Vitals permissions
        'vitals.view',
        'vitals.record',
        'vitals.*',
        
        // DOT permissions
        'dot.view',
        'dot.manage',
        'dot.*',
        
        // OSHA permissions
        'osha.view',
        'osha.manage',
        'osha.report',
        'osha.*',
        
        // Reports permissions
        'reports.view',
        'reports.export',
        'reports.*',
        
        // User management permissions
        'user.view',
        'user.create',
        'user.edit',
        'user.delete',
        'user.manage',
        'user.*',
        
        // Audit permissions
        'audit.view',
        'audit.export',
        'audit.*',
        
        // System permissions
        'system.configure',
        'system.*',
        
        // Privacy permissions
        'privacy.view',
        'privacy.manage',
        'privacy.*',
        
        // Security permissions
        'security.view',
        'security.manage',
        'security.*',
        
        // Full access
        '*',
    ];
    
    // =========================================================================
    // Public Methods
    // =========================================================================
    
    /**
     * Map backend role to UI role for frontend
     * 
     * @param string $backendRole The backend database role
     * @return string The UI role for frontend routing
     * 
     * @example
     * ```php
     * $uiRole = RoleService::toUiRole('pclinician'); // Returns 'provider'
     * $uiRole = RoleService::toUiRole('Admin'); // Returns 'super-admin'
     * ```
     */
    public static function toUiRole(string $backendRole): string
    {
        return self::UI_ROLE_MAP[$backendRole] ?? self::UI_ROLE_PROVIDER;
    }
    
    /**
     * Get display name for a role
     * 
     * @param string $role The backend role
     * @return string Human-readable role name
     * 
     * @example
     * ```php
     * $name = RoleService::getDisplayName('pclinician'); // Returns 'Clinical Provider'
     * ```
     */
    public static function getDisplayName(string $role): string
    {
        return self::ROLE_DISPLAY_NAMES[$role] ?? ucfirst($role);
    }
    
    /**
     * Get permissions for a role
     * 
     * @param string $role The backend role
     * @return array<string> Array of permission strings
     * 
     * @example
     * ```php
     * $perms = RoleService::getPermissions('pclinician');
     * // Returns ['patient.view', 'patient.create', 'patient.edit', ...]
     * ```
     */
    public static function getPermissions(string $role): array
    {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }
    
    /**
     * Check if role has a specific permission
     * Supports wildcard permissions (e.g., 'patient.*' matches 'patient.view')
     * 
     * @param string $role The backend role
     * @param string $permission The permission to check
     * @return bool True if role has the permission
     * 
     * @example
     * ```php
     * $can = RoleService::hasPermission('Admin', 'patient.delete'); // true (has *)
     * $can = RoleService::hasPermission('QA', 'patient.create'); // false
     * ```
     */
    public static function hasPermission(string $role, string $permission): bool
    {
        $permissions = self::getPermissions($role);
        
        // Check for full access
        if (in_array('*', $permissions, true)) {
            return true;
        }
        
        // Check for exact match
        if (in_array($permission, $permissions, true)) {
            return true;
        }
        
        // Check for wildcard match (e.g., 'patient.*' matches 'patient.view')
        $permissionParts = explode('.', $permission);
        if (count($permissionParts) >= 2) {
            $wildcardPermission = $permissionParts[0] . '.*';
            if (in_array($wildcardPermission, $permissions, true)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get dashboard route for a role
     * 
     * @param string $role The backend role
     * @return string Dashboard route path
     * 
     * @example
     * ```php
     * $route = RoleService::getDashboardRoute('pclinician'); // '/dashboard/provider'
     * ```
     */
    public static function getDashboardRoute(string $role): string
    {
        return self::DASHBOARD_ROUTES[$role] ?? '/dashboard';
    }
    
    /**
     * Get all available backend roles
     * 
     * @return array<string> Array of valid backend role constants
     */
    public static function getAllRoles(): array
    {
        return [
            self::ROLE_1CLINICIAN,
            self::ROLE_DCLINICIAN,
            self::ROLE_PCLINICIAN,
            self::ROLE_CADMIN,
            self::ROLE_TADMIN,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_QA,
            self::ROLE_PRIVACY_OFFICER,
            self::ROLE_SECURITY_OFFICER,
        ];
    }
    
    /**
     * Get all available UI roles
     * 
     * @return array<string> Array of valid UI roles
     */
    public static function getAllUiRoles(): array
    {
        return [
            self::UI_ROLE_PROVIDER,
            self::UI_ROLE_REGISTRATION,
            self::UI_ROLE_ADMIN,
            self::UI_ROLE_SUPER_ADMIN,
            self::UI_ROLE_TECHNICIAN,
            self::UI_ROLE_MANAGER,
            self::UI_ROLE_QA,
            self::UI_ROLE_PRIVACY_OFFICER,
            self::UI_ROLE_SECURITY_OFFICER,
        ];
    }
    
    /**
     * Validate if role string is a valid backend role
     * 
     * @param string $role The role to validate
     * @return bool True if valid
     * 
     * @example
     * ```php
     * RoleService::isValidRole('pclinician'); // true
     * RoleService::isValidRole('invalid'); // false
     * ```
     */
    public static function isValidRole(string $role): bool
    {
        return array_key_exists($role, self::UI_ROLE_MAP);
    }
    
    /**
     * Get all available permissions
     * 
     * @return array<string> Array of all permission strings
     */
    public static function getAllPermissions(): array
    {
        return self::ALL_PERMISSIONS;
    }
    
    /**
     * Check if role has admin-level privileges
     * 
     * @param string $role The backend role
     * @return bool True if role is admin or super-admin level
     */
    public static function isAdmin(string $role): bool
    {
        $adminRoles = [
            self::ROLE_CADMIN,
            self::ROLE_TADMIN,
            self::ROLE_ADMIN,
        ];
        
        return in_array($role, $adminRoles, true);
    }
    
    /**
     * Check if role has super-admin privileges
     * 
     * @param string $role The backend role
     * @return bool True if role is super-admin
     */
    public static function isSuperAdmin(string $role): bool
    {
        return $role === self::ROLE_ADMIN;
    }
    
    /**
     * Format user data with role information for API response
     * 
     * @param array $user User data from database/session
     * @return array Formatted user data with role mappings
     */
    public static function formatUserWithRole(array $user): array
    {
        $backendRole = $user['role'] ?? $user['primary_role']['slug'] ?? 'unknown';
        
        return [
            'id' => $user['user_id'] ?? $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'firstName' => $user['first_name'] ?? null,
            'lastName' => $user['last_name'] ?? null,
            'role' => $backendRole,
            'uiRole' => self::toUiRole($backendRole),
            'displayRole' => self::getDisplayName($backendRole),
            'permissions' => self::getPermissions($backendRole),
            'dashboardRoute' => self::getDashboardRoute($backendRole),
            'clinicId' => $user['clinic_id'] ?? null,
            'clinicName' => $user['clinic_name'] ?? null,
            'twoFactorEnabled' => (bool)($user['two_factor_enabled'] ?? false),
            'lastLogin' => $user['last_login'] ?? null,
        ];
    }
    
    /**
     * Get role hierarchy level (higher = more privileges)
     * Used for determining access in role-based comparisons
     * 
     * @param string $role The backend role
     * @return int Hierarchy level (1-10)
     */
    public static function getRoleLevel(string $role): int
    {
        $levels = [
            self::ROLE_1CLINICIAN     => 1,
            self::ROLE_DCLINICIAN     => 2,
            self::ROLE_PCLINICIAN     => 3,
            self::ROLE_QA             => 4,
            self::ROLE_CADMIN         => 5,
            self::ROLE_TADMIN         => 5,
            self::ROLE_PRIVACY_OFFICER => 6,
            self::ROLE_SECURITY_OFFICER => 6,
            self::ROLE_MANAGER        => 7,
            self::ROLE_ADMIN          => 10,
        ];
        
        return $levels[$role] ?? 0;
    }
    
    /**
     * Compare two roles and determine which has higher privileges
     * 
     * @param string $role1 First role
     * @param string $role2 Second role
     * @return int -1 if role1 < role2, 0 if equal, 1 if role1 > role2
     */
    public static function compareRoles(string $role1, string $role2): int
    {
        $level1 = self::getRoleLevel($role1);
        $level2 = self::getRoleLevel($role2);
        
        return $level1 <=> $level2;
    }
}
