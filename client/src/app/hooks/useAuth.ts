/**
 * Authentication Hooks for SafeShift EHR
 * 
 * Provides convenient hooks for accessing authentication state,
 * protecting routes, and managing role-based access control.
 */

import { useContext, useEffect, useMemo } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { AuthContext, type AuthContextType, type AuthUser } from '../contexts/AuthContext.js';
import { type UIRole, hasRoleAccess, getDashboardRoute } from '../utils/roleMapper.js';

// ============================================================================
// Core Auth Hook
// ============================================================================

/**
 * Hook to access the authentication context
 * 
 * @throws Error if used outside of AuthProvider
 * @returns The authentication context with all auth methods and state
 * 
 * @example
 * ```typescript
 * function MyComponent() {
 *   const { user, isAuthenticated, login, logout } = useAuth();
 *   // ...
 * }
 * ```
 */
export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  
  return context;
}

// ============================================================================
// Authentication Guard Hook
// ============================================================================

/**
 * Hook that requires authentication and redirects to login if not authenticated
 * 
 * @param redirectTo - Custom redirect path (defaults to /login)
 * @returns Authentication state and loading status
 * 
 * @example
 * ```typescript
 * function ProtectedPage() {
 *   const { isAuthenticated, loading } = useRequireAuth();
 *   
 *   if (loading) {
 *     return <LoadingSpinner />;
 *   }
 *   
 *   // User is guaranteed to be authenticated here
 *   return <ProtectedContent />;
 * }
 * ```
 */
export function useRequireAuth(redirectTo: string = '/login'): {
  isAuthenticated: boolean;
  loading: boolean;
  user: AuthUser | null;
} {
  const { isAuthenticated, loading, user } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  
  useEffect(() => {
    if (!loading && !isAuthenticated) {
      // Store the current location to redirect back after login
      const returnUrl = encodeURIComponent(location.pathname + location.search);
      navigate(`${redirectTo}?returnUrl=${returnUrl}`, { replace: true });
    }
  }, [isAuthenticated, loading, navigate, location, redirectTo]);
  
  return { isAuthenticated, loading, user };
}

// ============================================================================
// Role-Based Access Hook
// ============================================================================

/**
 * Hook that requires specific roles and redirects if user doesn't have access
 * 
 * @param requiredRoles - Array of roles that can access this resource
 * @param redirectTo - Path to redirect to if access is denied (defaults to /unauthorized)
 * @returns Access status, loading state, and user information
 * 
 * @example
 * ```typescript
 * function AdminPage() {
 *   const { hasAccess, loading } = useRequireRole(['admin', 'super-admin']);
 *   
 *   if (loading) {
 *     return <LoadingSpinner />;
 *   }
 *   
 *   if (!hasAccess) {
 *     return null; // Will be redirected
 *   }
 *   
 *   return <AdminContent />;
 * }
 * ```
 */
export function useRequireRole(
  requiredRoles: UIRole[],
  redirectTo: string = '/unauthorized'
): {
  hasAccess: boolean;
  loading: boolean;
  user: AuthUser | null;
  currentRole: UIRole | null;
} {
  const { isAuthenticated, loading, user } = useAuth();
  const navigate = useNavigate();
  
  const hasAccess = useMemo(() => {
    if (!user) return false;
    return hasRoleAccess(user.currentRole, requiredRoles);
  }, [user, requiredRoles]);
  
  const currentRole = user?.currentRole ?? null;
  
  useEffect(() => {
    // Wait for loading to complete
    if (loading) return;
    
    // First check if authenticated
    if (!isAuthenticated) {
      navigate('/login', { replace: true });
      return;
    }
    
    // Then check role access
    if (!hasAccess) {
      navigate(redirectTo, { replace: true });
    }
  }, [isAuthenticated, loading, hasAccess, navigate, redirectTo]);
  
  return { hasAccess, loading, user, currentRole };
}

// ============================================================================
// User Information Hook
// ============================================================================

/**
 * Hook to get current user information with computed properties
 * 
 * @returns User information with computed display values
 * 
 * @example
 * ```typescript
 * function UserProfile() {
 *   const { displayName, initials, roleLabel, isAdmin } = useCurrentUser();
 *   
 *   return (
 *     <div>
 *       <Avatar>{initials}</Avatar>
 *       <span>{displayName}</span>
 *       <Badge>{roleLabel}</Badge>
 *     </div>
 *   );
 * }
 * ```
 */
export function useCurrentUser(): {
  user: AuthUser | null;
  displayName: string;
  initials: string;
  email: string;
  roleLabel: string;
  currentRole: UIRole | null;
  availableRoles: UIRole[];
  isAdmin: boolean;
  isSuperAdmin: boolean;
  isProvider: boolean;
  hasMultipleRoles: boolean;
} {
  const { user } = useAuth();
  
  return useMemo(() => {
    if (!user) {
      return {
        user: null,
        displayName: '',
        initials: '',
        email: '',
        roleLabel: '',
        currentRole: null,
        availableRoles: [],
        isAdmin: false,
        isSuperAdmin: false,
        isProvider: false,
        hasMultipleRoles: false,
      };
    }
    
    // Generate initials from name
    const nameParts = user.name.split(' ').filter(Boolean);
    let initials = '';
    if (nameParts.length >= 2) {
      const firstPart = nameParts[0];
      const lastPart = nameParts[nameParts.length - 1];
      if (firstPart && lastPart && firstPart.length > 0 && lastPart.length > 0) {
        initials = `${firstPart[0]}${lastPart[0]}`.toUpperCase();
      } else {
        initials = user.name.substring(0, 2).toUpperCase();
      }
    } else {
      initials = user.name.substring(0, 2).toUpperCase();
    }
    
    // Get role display label
    const roleLabels: Record<UIRole, string> = {
      'provider': 'Provider',
      'registration': 'Registration',
      'admin': 'Admin',
      'super-admin': 'Super Admin',
      'technician': 'Technician',
      'manager': 'Manager',
      'qa': 'QA',
      'privacy-officer': 'Privacy Officer',
      'security-officer': 'Security Officer',
    };
    
    return {
      user,
      displayName: user.name,
      initials,
      email: user.email,
      roleLabel: roleLabels[user.currentRole] || user.currentRole,
      currentRole: user.currentRole,
      availableRoles: user.availableRoles,
      isAdmin: user.currentRole === 'admin' || user.currentRole === 'super-admin',
      isSuperAdmin: user.currentRole === 'super-admin',
      isProvider: user.currentRole === 'provider',
      hasMultipleRoles: user.availableRoles.length > 1,
    };
  }, [user]);
}

// ============================================================================
// Dashboard Navigation Hook
// ============================================================================

/**
 * Hook to navigate to the appropriate dashboard based on user role
 * 
 * @returns Navigation function and dashboard path
 * 
 * @example
 * ```typescript
 * function LoginSuccessHandler() {
 *   const { navigateToDashboard, dashboardPath } = useDashboardNavigation();
 *   
 *   useEffect(() => {
 *     navigateToDashboard();
 *   }, []);
 * }
 * ```
 */
export function useDashboardNavigation(): {
  navigateToDashboard: () => void;
  dashboardPath: string;
} {
  const { user } = useAuth();
  const navigate = useNavigate();
  
  const dashboardPath = useMemo(() => {
    if (!user) return '/login';
    return getDashboardRoute(user.currentRole);
  }, [user]);
  
  const navigateToDashboard = () => {
    navigate(dashboardPath, { replace: true });
  };
  
  return { navigateToDashboard, dashboardPath };
}

// ============================================================================
// Permission Check Hook
// ============================================================================

/**
 * Hook to check if user has specific permissions
 * 
 * @param requiredPermissions - Array of permission strings to check
 * @param requireAll - If true, user must have ALL permissions. If false, user needs at least one
 * @returns Whether the user has the required permissions
 * 
 * @example
 * ```typescript
 * function EditButton() {
 *   const canEdit = useHasPermission(['encounters.edit', 'encounters.create']);
 *   
 *   if (!canEdit) return null;
 *   
 *   return <Button>Edit</Button>;
 * }
 * ```
 */
export function useHasPermission(
  requiredPermissions: string[],
  requireAll: boolean = false
): boolean {
  const { user } = useAuth();
  
  return useMemo(() => {
    if (!user || !user.permissions || user.permissions.length === 0) {
      return false;
    }
    
    // Super admin has all permissions
    if (user.currentRole === 'super-admin') {
      return true;
    }
    
    if (requireAll) {
      return requiredPermissions.every(perm => user.permissions.includes(perm));
    } else {
      return requiredPermissions.some(perm => user.permissions.includes(perm));
    }
  }, [user, requiredPermissions, requireAll]);
}

// ============================================================================
// Authentication Status Hook
// ============================================================================

/**
 * Hook to get detailed authentication status
 * 
 * @returns Detailed auth status object
 * 
 * @example
 * ```typescript
 * function AuthStatusIndicator() {
 *   const { status, isLoggingIn, isLoggingOut, needsVerification } = useAuthStatus();
 *   // ...
 * }
 * ```
 */
export function useAuthStatus(): {
  status: 'loading' | 'unauthenticated' | 'needs-verification' | 'authenticated';
  isLoading: boolean;
  isLoggingIn: boolean;
  needsVerification: boolean;
  isAuthenticated: boolean;
  hasError: boolean;
  errorMessage: string | null;
} {
  const { loading, isAuthenticated, stage, error } = useAuth();
  
  return useMemo(() => {
    let status: 'loading' | 'unauthenticated' | 'needs-verification' | 'authenticated';
    
    if (loading) {
      status = 'loading';
    } else if (stage === 'otp') {
      status = 'needs-verification';
    } else if (isAuthenticated) {
      status = 'authenticated';
    } else {
      status = 'unauthenticated';
    }
    
    return {
      status,
      isLoading: loading,
      isLoggingIn: stage === 'credentials',
      needsVerification: stage === 'otp',
      isAuthenticated,
      hasError: error !== null,
      errorMessage: error,
    };
  }, [loading, isAuthenticated, stage, error]);
}

// ============================================================================
// Return URL Hook
// ============================================================================

/**
 * Hook to handle return URL after login
 * 
 * @returns The return URL from query params or default dashboard
 * 
 * @example
 * ```typescript
 * function LoginPage() {
 *   const returnUrl = useReturnUrl();
 *   const { login } = useAuth();
 *   
 *   const handleLogin = async () => {
 *     await login(username, password);
 *     navigate(returnUrl);
 *   };
 * }
 * ```
 */
export function useReturnUrl(defaultPath: string = '/'): string {
  const location = useLocation();
  const { user } = useAuth();
  
  return useMemo(() => {
    const params = new URLSearchParams(location.search);
    const returnUrl = params.get('returnUrl');
    
    if (returnUrl) {
      // Decode and validate the return URL
      try {
        const decoded = decodeURIComponent(returnUrl);
        // Ensure it's a relative URL (security check)
        if (decoded.startsWith('/') && !decoded.startsWith('//')) {
          return decoded;
        }
      } catch {
        // Invalid URL encoding, fall through to default
      }
    }
    
    // If user exists, return their dashboard
    if (user) {
      return getDashboardRoute(user.currentRole);
    }
    
    return defaultPath;
  }, [location.search, user, defaultPath]);
}

// ============================================================================
// Export Types
// ============================================================================

export type { AuthContextType, AuthUser, UIRole };
