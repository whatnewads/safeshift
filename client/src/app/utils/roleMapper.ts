/**
 * Role Mapper Utility for SafeShift EHR
 * 
 * Maps backend database roles to frontend UI roles for consistent
 * routing and permission handling across the application.
 * 
 * IMPORTANT: This file must stay in sync with /model/Services/RoleService.php
 * 
 * Backend Roles (from database):
 * - '1clinician'      - Intake clinician (registration/front desk)
 * - 'dclinician'      - Drug screen clinician/technician
 * - 'pclinician'      - Primary clinician/clinical provider
 * - 'cadmin'          - Clinic administrator
 * - 'tadmin'          - Technical/Tenant administrator
 * - 'Admin'           - System super administrator
 * - 'Manager'         - Manager role
 * - 'QA'              - Quality assurance
 * - 'PrivacyOfficer'  - Privacy compliance officer
 * - 'SecurityOfficer' - Security compliance officer
 */

// ============================================================================
// Type Definitions
// ============================================================================

/**
 * Frontend UI roles used for routing and permission checks
 */
export type UIRole = 
  | 'provider'
  | 'registration'
  | 'admin'
  | 'super-admin'
  | 'technician'
  | 'manager'
  | 'qa'
  | 'privacy-officer'
  | 'security-officer';

/**
 * Backend database roles
 */
export type BackendRole = 
  | '1clinician'
  | 'dclinician'
  | 'pclinician'
  | 'cadmin'
  | 'tadmin'
  | 'Admin'
  | 'Manager'
  | 'QA'
  | 'PrivacyOfficer'
  | 'SecurityOfficer';

/**
 * Permission strings that can be assigned to roles
 * Must match PHP RoleService::ALL_PERMISSIONS
 */
export type Permission = 
  // Patient permissions
  | 'patient.view' 
  | 'patient.create' 
  | 'patient.edit' 
  | 'patient.delete' 
  | 'patient.*'
  // Encounter permissions
  | 'encounter.view' 
  | 'encounter.create' 
  | 'encounter.edit'
  | 'encounter.sign' 
  | 'encounter.review' 
  | 'encounter.amend'
  | 'encounter.*'
  // Vitals permissions
  | 'vitals.view'
  | 'vitals.record'
  | 'vitals.*'
  // DOT testing permissions
  | 'dot.view'
  | 'dot.manage'
  | 'dot.*'
  // OSHA permissions
  | 'osha.view' 
  | 'osha.manage' 
  | 'osha.report'
  | 'osha.*'
  // Reports permissions
  | 'reports.view' 
  | 'reports.export'
  | 'reports.*'
  // User management permissions
  | 'user.view' 
  | 'user.create'
  | 'user.edit'
  | 'user.delete'
  | 'user.manage' 
  | 'user.*'
  // Audit permissions
  | 'audit.view'
  | 'audit.export'
  | 'audit.*'
  // System permissions
  | 'system.configure'
  | 'system.*'
  // Privacy permissions
  | 'privacy.view'
  | 'privacy.manage'
  | 'privacy.*'
  // Security permissions
  | 'security.view'
  | 'security.manage'
  | 'security.*'
  // Full access
  | '*';

// ============================================================================
// Role Mappings (Must match PHP RoleService::UI_ROLE_MAP)
// ============================================================================

/**
 * Map backend database roles to frontend UI roles
 * IMPORTANT: Must stay in sync with PHP RoleService::UI_ROLE_MAP
 */
const ROLE_MAP: Record<string, UIRole> = {
  // Primary backend roles
  '1clinician': 'registration',      // Intake clinician → registration
  'dclinician': 'technician',        // Drug screen → technician
  'pclinician': 'provider',          // Primary clinician → provider
  'cadmin': 'admin',                 // Clinic admin → admin
  'tadmin': 'admin',                 // Tech admin → admin
  'Admin': 'super-admin',            // System admin → super-admin
  'Manager': 'manager',              // Manager → manager
  'QA': 'qa',                        // QA → qa
  'PrivacyOfficer': 'privacy-officer',   // Privacy officer
  'SecurityOfficer': 'security-officer', // Security officer
  
  // Legacy/alternate role names (for backwards compatibility)
  'clinician': 'provider',
  'Clinician': 'provider',
  'Provider': 'provider',
  'provider': 'provider',
  'Doctor': 'provider',
  'doctor': 'provider',
  'Nurse': 'provider',
  'nurse': 'provider',
  'NP': 'provider',
  'PA': 'provider',
  'ClinicAdmin': 'admin',
  'TenantAdmin': 'admin',
  'admin': 'admin',
  'SuperAdmin': 'super-admin',
  'super-admin': 'super-admin',
  'superadmin': 'super-admin',
  'manager': 'manager',
  'qa': 'qa',
  'QualityAssurance': 'qa',
  'privacy-officer': 'privacy-officer',
  'privacyofficer': 'privacy-officer',
  'security-officer': 'security-officer',
  'securityofficer': 'security-officer',
  'Technician': 'technician',
  'technician': 'technician',
  'LabTech': 'technician',
  'labtech': 'technician',
  'Registration': 'registration',
  'registration': 'registration',
  'FrontDesk': 'registration',
  'frontdesk': 'registration',
  'Receptionist': 'registration',
  'receptionist': 'registration',
};

/**
 * Default role to use when backend role is unknown
 */
const DEFAULT_ROLE: UIRole = 'provider';

// ============================================================================
// Permission Mappings (Must match PHP RoleService::ROLE_PERMISSIONS)
// ============================================================================

/**
 * Permissions assigned to each backend role
 * IMPORTANT: Must stay in sync with PHP RoleService::ROLE_PERMISSIONS
 */
export const ROLE_PERMISSIONS: Record<string, Permission[]> = {
  '1clinician': [
    'patient.view',
    'patient.create',
    'encounter.view',
  ],
  
  'dclinician': [
    'patient.view',
    'encounter.view',
    'encounter.create',
    'dot.manage',
  ],
  
  'pclinician': [
    'patient.view',
    'patient.create',
    'patient.edit',
    'encounter.view',
    'encounter.create',
    'encounter.sign',
    'vitals.record',
  ],
  
  'cadmin': [
    'patient.*',
    'encounter.*',
    'user.view',
    'reports.view',
  ],
  
  'tadmin': [
    'patient.*',
    'encounter.*',
    'user.view',
    'system.configure',
  ],
  
  'Admin': ['*'], // Full system access
  
  'Manager': [
    'patient.*',
    'encounter.*',
    'user.*',
    'reports.*',
    'osha.*',
  ],
  
  'QA': [
    'patient.view',
    'encounter.view',
    'encounter.review',
    'reports.view',
  ],
  
  'PrivacyOfficer': [
    'patient.view',
    'encounter.view',
    'audit.view',
    'reports.view',
    'privacy.*',
  ],
  
  'SecurityOfficer': [
    'audit.view',
    'audit.export',
    'security.*',
    'user.view',
    'reports.view',
  ],
};

// ============================================================================
// Display Names (Must match PHP RoleService::ROLE_DISPLAY_NAMES)
// ============================================================================

/**
 * Human-readable display names for backend roles
 */
const ROLE_DISPLAY_NAMES: Record<string, string> = {
  '1clinician': 'Intake Clinician',
  'dclinician': 'Drug Screen Technician',
  'pclinician': 'Clinical Provider',
  'cadmin': 'Clinic Administrator',
  'tadmin': 'Technical Administrator',
  'Admin': 'System Administrator',
  'Manager': 'Manager',
  'QA': 'Quality Assurance',
  'PrivacyOfficer': 'Privacy Officer',
  'SecurityOfficer': 'Security Officer',
};

/**
 * Display names for UI roles
 */
const UI_ROLE_DISPLAY_NAMES: Record<UIRole, string> = {
  'provider': 'Clinical Provider',
  'registration': 'Registration',
  'admin': 'Administrator',
  'super-admin': 'Super Administrator',
  'technician': 'Technician',
  'manager': 'Manager',
  'qa': 'Quality Assurance',
  'privacy-officer': 'Privacy Officer',
  'security-officer': 'Security Officer',
};

// ============================================================================
// Dashboard Routes (Must match PHP RoleService::DASHBOARD_ROUTES)
// ============================================================================

/**
 * Dashboard routes for each backend role
 */
const BACKEND_DASHBOARD_ROUTES: Record<string, string> = {
  '1clinician': '/dashboard/registration',
  'dclinician': '/dashboard/technician',
  'pclinician': '/dashboard/provider',
  'cadmin': '/dashboard/admin',
  'tadmin': '/dashboard/admin',
  'Admin': '/dashboard/super-admin',
  'Manager': '/dashboard/manager',
  'QA': '/dashboard/qa',
  'PrivacyOfficer': '/dashboard/privacy',
  'SecurityOfficer': '/dashboard/security',
};

/**
 * Dashboard routes for UI roles
 */
const UI_DASHBOARD_ROUTES: Record<UIRole, string> = {
  'provider': '/dashboard/clinician',
  'registration': '/dashboard/registration',
  'admin': '/dashboard/admin',
  'super-admin': '/dashboard/admin',
  'technician': '/dashboard/technician',
  'manager': '/dashboard/manager',
  'qa': '/dashboard/qa',
  'privacy-officer': '/dashboard/privacy',
  'security-officer': '/dashboard/security',
};

// ============================================================================
// Core Mapping Functions
// ============================================================================

/**
 * Map a backend role string to a frontend UI role
 * 
 * @param backendRole - The role string from the backend/database
 * @returns The corresponding UI role for frontend routing
 * 
 * @example
 * ```typescript
 * const uiRole = mapBackendRole('1clinician'); // returns 'registration'
 * const uiRole = mapBackendRole('Admin'); // returns 'super-admin'
 * ```
 */
export function mapBackendRole(backendRole: string): UIRole {
  if (!backendRole) {
    console.warn('Empty backend role provided, using default:', DEFAULT_ROLE);
    return DEFAULT_ROLE;
  }
  
  const mappedRole = ROLE_MAP[backendRole];
  
  if (!mappedRole) {
    console.warn(`Unknown backend role "${backendRole}", using default:`, DEFAULT_ROLE);
    return DEFAULT_ROLE;
  }
  
  return mappedRole;
}

/**
 * Map an array of backend roles to UI roles
 * 
 * @param backendRoles - Array of role strings from the backend
 * @returns Array of unique UI roles
 * 
 * @example
 * ```typescript
 * const uiRoles = mapBackendRoles(['1clinician', 'cadmin']); 
 * // returns ['registration', 'admin']
 * ```
 */
export function mapBackendRoles(backendRoles: string[]): UIRole[] {
  if (!backendRoles || backendRoles.length === 0) {
    return [DEFAULT_ROLE];
  }
  
  const mappedRoles = backendRoles.map(mapBackendRole);
  
  // Remove duplicates
  return [...new Set(mappedRoles)];
}

/**
 * Get the primary UI role from an array of backend roles
 * Uses a priority order to determine the "main" role
 * 
 * @param backendRoles - Array of role strings from the backend
 * @returns The primary UI role based on priority
 */
export function getPrimaryUIRole(backendRoles: string[]): UIRole {
  const uiRoles = mapBackendRoles(backendRoles);
  
  // Priority order (highest first)
  const rolePriority: UIRole[] = [
    'super-admin',
    'admin',
    'manager',
    'privacy-officer',
    'security-officer',
    'provider',
    'qa',
    'technician',
    'registration',
  ];
  
  for (const role of rolePriority) {
    if (uiRoles.includes(role)) {
      return role;
    }
  }
  
  return DEFAULT_ROLE;
}

// ============================================================================
// Permission Functions
// ============================================================================

/**
 * Get permissions for a backend role
 * 
 * @param backendRole - The backend role string
 * @returns Array of permission strings
 */
export function getRolePermissions(backendRole: string): Permission[] {
  return ROLE_PERMISSIONS[backendRole] ?? [];
}

/**
 * Check if a role has a specific permission
 * Supports wildcard permissions (e.g., 'patient.*' matches 'patient.view')
 * 
 * @param backendRole - The backend role
 * @param permission - The permission to check
 * @returns True if role has the permission
 */
export function hasPermission(backendRole: string, permission: string): boolean {
  const permissions = getRolePermissions(backendRole);
  
  // Check for full access
  if (permissions.includes('*')) {
    return true;
  }
  
  // Check for exact match
  if (permissions.includes(permission as Permission)) {
    return true;
  }
  
  // Check for wildcard match (e.g., 'patient.*' matches 'patient.view')
  const permissionParts = permission.split('.');
  if (permissionParts.length >= 2) {
    const wildcardPermission = `${permissionParts[0]}.*` as Permission;
    if (permissions.includes(wildcardPermission)) {
      return true;
    }
  }
  
  return false;
}

/**
 * Check if a UI role has access to a specific feature/route
 * 
 * @param userRole - The user's UI role
 * @param allowedRoles - Array of roles allowed to access the feature
 * @returns True if the user has access
 */
export function hasRoleAccess(userRole: UIRole, allowedRoles: UIRole[]): boolean {
  // Super-admin has access to everything
  if (userRole === 'super-admin') {
    return true;
  }
  
  return allowedRoles.includes(userRole);
}

// ============================================================================
// Display Name Functions
// ============================================================================

/**
 * Get display name for a backend role
 * 
 * @param backendRole - The backend role
 * @returns Human-readable role name
 */
export function getBackendRoleDisplayName(backendRole: string): string {
  return ROLE_DISPLAY_NAMES[backendRole] ?? backendRole;
}

/**
 * Get display name for a UI role
 * 
 * @param uiRole - The UI role
 * @returns Human-readable role name
 */
export function getRoleDisplayName(uiRole: UIRole): string {
  return UI_ROLE_DISPLAY_NAMES[uiRole] ?? 'Unknown Role';
}

// ============================================================================
// Dashboard Route Functions
// ============================================================================

/**
 * Get the dashboard route for a backend role
 * 
 * @param backendRole - The backend role
 * @returns Dashboard route path
 */
export function getBackendRoleDashboardRoute(backendRole: string): string {
  return BACKEND_DASHBOARD_ROUTES[backendRole] ?? '/dashboard';
}

/**
 * Get the default dashboard route for a given UI role
 * 
 * @param uiRole - The user's UI role
 * @returns The dashboard route path
 */
export function getDashboardRoute(uiRole: UIRole): string {
  return UI_DASHBOARD_ROUTES[uiRole] ?? '/dashboard/clinician';
}

// ============================================================================
// Validation Functions
// ============================================================================

/**
 * Check if a backend role is valid
 * 
 * @param role - The role to validate
 * @returns True if valid
 */
export function isValidBackendRole(role: string): boolean {
  return role in ROLE_MAP;
}

/**
 * Check if a UI role is valid
 * 
 * @param role - The role to validate
 * @returns True if valid
 */
export function isValidUIRole(role: string): role is UIRole {
  return Object.values(ROLE_MAP).includes(role as UIRole);
}

/**
 * Check if role is admin level (admin or super-admin)
 * 
 * @param backendRole - The backend role
 * @returns True if admin level
 */
export function isAdmin(backendRole: string): boolean {
  const adminRoles = ['cadmin', 'tadmin', 'Admin'];
  return adminRoles.includes(backendRole);
}

/**
 * Check if role is super admin
 * 
 * @param backendRole - The backend role
 * @returns True if super admin
 */
export function isSuperAdmin(backendRole: string): boolean {
  return backendRole === 'Admin';
}

/**
 * Get all valid UI roles
 * 
 * @returns Array of all UI roles
 */
export function getAllUIRoles(): UIRole[] {
  return [
    'provider',
    'registration',
    'admin',
    'super-admin',
    'technician',
    'manager',
    'qa',
    'privacy-officer',
    'security-officer',
  ];
}

/**
 * Get all valid backend roles
 * 
 * @returns Array of primary backend roles
 */
export function getAllBackendRoles(): BackendRole[] {
  return [
    '1clinician',
    'dclinician',
    'pclinician',
    'cadmin',
    'tadmin',
    'Admin',
    'Manager',
    'QA',
    'PrivacyOfficer',
    'SecurityOfficer',
  ];
}
