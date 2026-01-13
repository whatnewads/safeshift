/**
 * Protected Route Component for SafeShift EHR
 * 
 * Provides route protection with authentication and role-based access control.
 * Use this component to wrap routes that require authentication or specific roles.
 */

import React, { type ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext.js';
import { type UIRole, hasRoleAccess } from '../utils/roleMapper.js';

// ============================================================================
// Loading Spinner Component
// ============================================================================

/**
 * Simple loading spinner for auth state loading
 */
function LoadingSpinner(): React.ReactElement {
  return (
    <div className="flex items-center justify-center min-h-screen bg-background">
      <div className="flex flex-col items-center gap-4">
        <div className="animate-spin rounded-full h-12 w-12 border-4 border-primary border-t-transparent" />
        <p className="text-muted-foreground">Loading...</p>
      </div>
    </div>
  );
}

// ============================================================================
// Protected Route Props
// ============================================================================

export interface ProtectedRouteProps {
  /** The content to render if access is granted */
  children: ReactNode;
  /** Roles that are allowed to access this route */
  requiredRoles?: UIRole[];
  /** Redirect path when not authenticated (defaults to /login) */
  loginPath?: string;
  /** Redirect path when unauthorized (defaults to /unauthorized) */
  unauthorizedPath?: string;
  /** Custom loading component */
  loadingComponent?: ReactNode;
  /** Custom unauthorized component (shown before redirect) */
  unauthorizedComponent?: ReactNode;
  /** If true, require ALL roles instead of ANY role */
  requireAllRoles?: boolean;
}

// ============================================================================
// Protected Route Component
// ============================================================================

/**
 * Component that protects routes requiring authentication
 * 
 * @param props - Protected route configuration
 * @returns Protected content or redirect
 * 
 * @example
 * ```tsx
 * // Basic authentication protection
 * <Route path="/dashboard" element={
 *   <ProtectedRoute>
 *     <Dashboard />
 *   </ProtectedRoute>
 * } />
 * 
 * // Role-based protection
 * <Route path="/admin" element={
 *   <ProtectedRoute requiredRoles={['admin', 'super-admin']}>
 *     <AdminDashboard />
 *   </ProtectedRoute>
 * } />
 * ```
 */
export function ProtectedRoute({
  children,
  requiredRoles,
  loginPath = '/login',
  unauthorizedPath = '/unauthorized',
  loadingComponent,
  unauthorizedComponent,
  requireAllRoles = false,
}: ProtectedRouteProps): React.ReactElement {
  const { user, loading, isAuthenticated } = useAuth();
  const location = useLocation();
  
  // Show loading state while checking authentication
  if (loading) {
    return loadingComponent ? <>{loadingComponent}</> : <LoadingSpinner />;
  }
  
  // Redirect to login if not authenticated
  if (!isAuthenticated) {
    // Preserve the current URL for redirect after login
    const returnUrl = encodeURIComponent(location.pathname + location.search);
    return <Navigate to={`${loginPath}?returnUrl=${returnUrl}`} replace />;
  }
  
  // Check role-based access if roles are specified
  if (requiredRoles && requiredRoles.length > 0 && user) {
    let hasAccess: boolean;
    
    if (requireAllRoles) {
      // User must have ALL specified roles
      hasAccess = requiredRoles.every(role => 
        user.availableRoles.includes(role) || user.currentRole === role
      );
    } else {
      // User needs at least one of the specified roles (or super-admin)
      hasAccess = hasRoleAccess(user.currentRole, requiredRoles);
    }
    
    if (!hasAccess) {
      if (unauthorizedComponent) {
        return <>{unauthorizedComponent}</>;
      }
      return <Navigate to={unauthorizedPath} replace />;
    }
  }
  
  // User is authenticated and has required roles
  return <>{children}</>;
}

// ============================================================================
// Admin Route Component
// ============================================================================

export interface AdminRouteProps {
  children: ReactNode;
  loadingComponent?: ReactNode;
}

/**
 * Convenience component for routes requiring admin access
 * Allows both 'admin' and 'super-admin' roles
 * 
 * @example
 * ```tsx
 * <Route path="/admin/*" element={
 *   <AdminRoute>
 *     <AdminLayout />
 *   </AdminRoute>
 * } />
 * ```
 */
export function AdminRoute({ 
  children, 
  loadingComponent 
}: AdminRouteProps): React.ReactElement {
  return (
    <ProtectedRoute 
      requiredRoles={['admin', 'super-admin']}
      loadingComponent={loadingComponent}
    >
      {children}
    </ProtectedRoute>
  );
}

// ============================================================================
// Provider Route Component
// ============================================================================

export interface ProviderRouteProps {
  children: ReactNode;
  loadingComponent?: ReactNode;
}

/**
 * Convenience component for routes requiring clinical provider access
 * 
 * @example
 * ```tsx
 * <Route path="/encounters/*" element={
 *   <ProviderRoute>
 *     <EncounterWorkspace />
 *   </ProviderRoute>
 * } />
 * ```
 */
export function ProviderRoute({ 
  children, 
  loadingComponent 
}: ProviderRouteProps): React.ReactElement {
  return (
    <ProtectedRoute 
      requiredRoles={['provider', 'admin', 'super-admin']}
      loadingComponent={loadingComponent}
    >
      {children}
    </ProtectedRoute>
  );
}

// ============================================================================
// Super Admin Route Component
// ============================================================================

export interface SuperAdminRouteProps {
  children: ReactNode;
  loadingComponent?: ReactNode;
}

/**
 * Convenience component for routes requiring super admin access
 * Only allows 'super-admin' role
 * 
 * @example
 * ```tsx
 * <Route path="/system/*" element={
 *   <SuperAdminRoute>
 *     <SystemSettings />
 *   </SuperAdminRoute>
 * } />
 * ```
 */
export function SuperAdminRoute({ 
  children, 
  loadingComponent 
}: SuperAdminRouteProps): React.ReactElement {
  return (
    <ProtectedRoute 
      requiredRoles={['super-admin']}
      loadingComponent={loadingComponent}
    >
      {children}
    </ProtectedRoute>
  );
}

// ============================================================================
// Redirect If Authenticated Component
// ============================================================================

export interface RedirectIfAuthenticatedProps {
  children: ReactNode;
  redirectTo?: string;
}

/**
 * Component that redirects authenticated users away from public pages
 * Useful for login/register pages
 * 
 * @example
 * ```tsx
 * <Route path="/login" element={
 *   <RedirectIfAuthenticated redirectTo="/dashboard">
 *     <LoginPage />
 *   </RedirectIfAuthenticated>
 * } />
 * ```
 */
export function RedirectIfAuthenticated({
  children,
  redirectTo = '/dashboard',
}: RedirectIfAuthenticatedProps): React.ReactElement {
  const { isAuthenticated, loading, user } = useAuth();
  const location = useLocation();
  
  // Show loading while checking auth
  if (loading) {
    return <LoadingSpinner />;
  }
  
  // If authenticated, redirect to dashboard or specified path
  if (isAuthenticated && user) {
    // Check for return URL in query params
    const params = new URLSearchParams(location.search);
    const returnUrl = params.get('returnUrl');
    
    if (returnUrl) {
      try {
        const decoded = decodeURIComponent(returnUrl);
        // Security: ensure it's a relative URL
        if (decoded.startsWith('/') && !decoded.startsWith('//')) {
          return <Navigate to={decoded} replace />;
        }
      } catch {
        // Invalid URL, use default redirect
      }
    }
    
    return <Navigate to={redirectTo} replace />;
  }
  
  // Not authenticated, show children
  return <>{children}</>;
}

// ============================================================================
// Session Warning Modal Component
// ============================================================================

export interface SessionWarningModalProps {
  onExtend: () => void;
  onLogout: () => void;
}

/**
 * Modal component shown when session is about to expire
 * 
 * @example
 * ```tsx
 * function App() {
 *   const { sessionWarning, extendSession, logout } = useAuth();
 *   
 *   return (
 *     <>
 *       <Routes>...</Routes>
 *       {sessionWarning && (
 *         <SessionWarningModal 
 *           onExtend={extendSession} 
 *           onLogout={logout} 
 *         />
 *       )}
 *     </>
 *   );
 * }
 * ```
 */
export function SessionWarningModal({
  onExtend,
  onLogout,
}: SessionWarningModalProps): React.ReactElement {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-background rounded-lg shadow-xl p-6 max-w-md mx-4">
        <h2 className="text-xl font-semibold text-foreground mb-2">
          Session Expiring Soon
        </h2>
        <p className="text-muted-foreground mb-6">
          Your session is about to expire due to inactivity. 
          Would you like to continue working?
        </p>
        <div className="flex gap-3 justify-end">
          <button
            onClick={onLogout}
            className="px-4 py-2 text-muted-foreground hover:text-foreground transition-colors"
          >
            Log Out
          </button>
          <button
            onClick={onExtend}
            className="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 transition-colors"
          >
            Continue Session
          </button>
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Exports
// ============================================================================

export default ProtectedRoute;
