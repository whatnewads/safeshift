/**
 * Authentication Context for SafeShift EHR
 * 
 * Provides authentication state and methods throughout the React application.
 * Integrates with the real API backend for authentication operations.
 */

import React, { createContext, useContext, useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { authService } from '../services/auth.service.js';
import { mapBackendRole, mapBackendRoles, getPrimaryUIRole, type UIRole } from '../utils/roleMapper.js';
import type { LoginResponse, AuthResponse, SessionResponse } from '../types/api.types.js';

// ============================================================================
// Type Definitions
// ============================================================================

/**
 * Authentication user object with normalized roles
 */
export interface AuthUser {
  /** Unique user identifier */
  id: string;
  /** User's username */
  username: string;
  /** User's email address */
  email: string;
  /** User's first name */
  firstName: string;
  /** User's last name */
  lastName: string;
  /** User's full display name */
  name: string;
  /** Backend role string */
  role: string;
  /** Normalized UI role for frontend routing */
  uiRole: UIRole;
  /** All available UI roles for this user */
  availableRoles: UIRole[];
  /** Currently active role (for role switching) */
  currentRole: UIRole;
  /** Associated clinic ID */
  clinicId?: string;
  /** Associated clinic name */
  clinicName?: string;
  /** User's permissions array */
  permissions: string[];
  /** Last login timestamp */
  lastLogin?: string;
  /** Whether this device is trusted */
  trustedDevice: boolean;
  /** User's avatar URL */
  avatar?: string;
}

/**
 * Login result indicating next step in auth flow
 */
export interface LoginResult {
  /** Success status */
  success: boolean;
  /** Whether 2FA is required */
  requires2FA: boolean;
  /** OTP delivery method hint */
  otpMethod?: 'email' | 'sms' | 'authenticator' | undefined;
  /** Masked destination where OTP was sent */
  otpDestination?: string | undefined;
  /** Error message if login failed */
  error?: string | undefined;
  /** User data if login completed without 2FA */
  user?: AuthUser | undefined;
}

/**
 * Authentication stage in the login flow
 */
export type AuthStage = 'idle' | 'credentials' | 'otp' | 'authenticated';

/**
 * Authentication context type definition
 */
export interface AuthContextType {
  /** Current authenticated user or null */
  user: AuthUser | null;
  /** Loading state for auth operations */
  loading: boolean;
  /** Error message from last auth operation */
  error: string | null;
  /** Whether user is fully authenticated */
  isAuthenticated: boolean;
  /** Current stage in the authentication flow */
  stage: AuthStage;
  /** Initiate login with username and password */
  login: (username: string, password: string) => Promise<LoginResult>;
  /** Verify 2FA code */
  verify2FA: (code: string, trustDevice?: boolean) => Promise<void>;
  /** Resend OTP code */
  resendOtp: () => Promise<void>;
  /** Log out the current user */
  logout: () => Promise<void>;
  /** Clear the current error */
  clearError: () => void;
  /** Refresh the current session */
  refreshSession: () => Promise<void>;
  /** Switch to a different available role */
  switchRole: (role: UIRole) => void;
  /** Session timeout warning state */
  sessionWarning: boolean;
  /** Dismiss session warning and extend session */
  extendSession: () => Promise<void>;
}

// ============================================================================
// Context Creation
// ============================================================================

const AuthContext = createContext<AuthContextType | undefined>(undefined);

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Transform backend user data to AuthUser format
 */
function transformUser(backendUser: Record<string, unknown>): AuthUser {
  const roleValue = backendUser.role;
  const role = typeof roleValue === 'string'
    ? roleValue
    : (roleValue as Record<string, unknown>)?.name as string
      || (roleValue as Record<string, unknown>)?.role_name as string
      || '';
  const roles = Array.isArray(backendUser.roles)
    ? (backendUser.roles as Array<{slug?: string; name?: string} | string>).map(r =>
        typeof r === 'string' ? r : (r.slug || r.name || '')
      )
    : [role];
  
  const uiRole = mapBackendRole(role);
  const availableRoles = mapBackendRoles(roles);
  
  // Construct display name from first/last name
  const firstName = (backendUser.firstName as string) || 
                   (backendUser.first_name as string) || '';
  const lastName = (backendUser.lastName as string) || 
                  (backendUser.last_name as string) || '';
  const name = (backendUser.name as string) || 
               `${firstName} ${lastName}`.trim() || 
               (backendUser.username as string) || '';
  
  return {
    id: String(backendUser.id || backendUser.user_id || ''),
    username: (backendUser.username as string) || '',
    email: (backendUser.email as string) || '',
    firstName,
    lastName,
    name,
    role,
    uiRole,
    availableRoles,
    currentRole: uiRole,
    clinicId: (backendUser.clinicId as string) || (backendUser.clinic_id as string),
    clinicName: (backendUser.clinicName as string) || (backendUser.clinic_name as string),
    permissions: Array.isArray(backendUser.permissions) 
      ? (backendUser.permissions as string[]) 
      : [],
    lastLogin: (backendUser.lastLogin as string) || (backendUser.last_login as string),
    trustedDevice: Boolean(backendUser.trustedDevice || backendUser.trusted_device),
    avatar: (backendUser.avatar as string) || (backendUser.profile_image as string),
  };
}

/**
 * Get user-friendly error message from API error
 */
function getErrorMessage(error: unknown): string {
  if (error instanceof Error) {
    // Handle specific API errors
    if (error.message.includes('401')) {
      return 'Invalid username or password';
    }
    if (error.message.includes('429')) {
      return 'Too many attempts. Please wait before trying again.';
    }
    if (error.message.includes('403')) {
      return 'Access denied. Please contact your administrator.';
    }
    if (error.message.includes('network') || error.message.includes('fetch')) {
      return 'Unable to connect to server. Please check your connection.';
    }
    return error.message;
  }
  return 'An unexpected error occurred';
}

// ============================================================================
// Session Check Interval (5 minutes)
// ============================================================================

const SESSION_CHECK_INTERVAL = 5 * 60 * 1000; // 5 minutes
const SESSION_WARNING_THRESHOLD = 5; // minutes remaining before warning

// ============================================================================
// Auth Provider Component
// ============================================================================

export function AuthProvider({ children }: { children: React.ReactNode }) {
  // State
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [stage, setStage] = useState<AuthStage>('idle');
  const [sessionWarning, setSessionWarning] = useState(false);
  const [pendingCredentials, setPendingCredentials] = useState<{
    username: string;
    password: string;
  } | null>(null);
  
  // Ref to prevent duplicate session checks (React StrictMode causes double invocation)
  const sessionCheckInitiatedRef = useRef(false);
  
  // Ref to track login flow state - prevents race condition between login() and checkExistingSession()
  const loginInProgressRef = useRef(false);
  
  // Computed state
  const isAuthenticated = useMemo(() => stage === 'authenticated' && user !== null, [stage, user]);
  
  // ========================================================================
  // Session Validation on Mount
  // ========================================================================
  
  useEffect(() => {
    // Prevent duplicate calls caused by React StrictMode double-invocation
    if (sessionCheckInitiatedRef.current) {
      return;
    }
    sessionCheckInitiatedRef.current = true;
    
    const checkExistingSession = async () => {
      // Skip session check if login flow is in progress to prevent race condition
      if (loginInProgressRef.current) {
        setLoading(false);
        return;
      }
      
      try {
        setLoading(true);
        
        // Check if there's an existing valid session
        const sessionStatus = await authService.getSessionStatus();
        
        // FIRST: Check if backend says we're in OTP stage (pending 2FA)
        if (sessionStatus.stage === 'otp') {
          setUser(null);  // Not fully authenticated yet
          setStage('otp');
          return;
        }
        
        // SECOND: Check if fully authenticated
        if (sessionStatus.valid && sessionStatus.authenticated && sessionStatus.user) {
          const authUser = transformUser(sessionStatus.user as unknown as Record<string, unknown>);
          setUser(authUser);
          setStage('authenticated');
        } else {
          setUser(null);
          // Only set stage to idle if we're not in the middle of a login flow
          // This prevents race condition where session check overwrites 'otp' or 'credentials' stages
          setStage((currentStage) => {
            if (currentStage === 'otp' || currentStage === 'credentials') {
              return currentStage;  // Preserve login flow stage
            }
            return 'idle';
          });
        }
      } catch (err) {
        // Session check failed - user is not authenticated
        setUser(null);
        setStage('idle');
      } finally {
        setLoading(false);
      }
    };
    
    checkExistingSession();
  }, []);
  
  // ========================================================================
  // Session Monitoring
  // ========================================================================
  
  useEffect(() => {
    if (!isAuthenticated) {
      setSessionWarning(false);
      return;
    }
    
    const checkSessionStatus = async () => {
      try {
        const status = await authService.getSessionStatus();
        
        // If session returns stage: 'otp', the user was logged out and needs to re-authenticate
        if (status.stage === 'otp' || status.stage === 'idle') {
          setUser(null);
          setStage(status.stage || 'idle');
          return;
        }
        
        if (!status.valid || !status.authenticated) {
          // Session expired, log out
          setUser(null);
          setStage('idle');
          setError('Your session has expired. Please log in again.');
          return;
        }
        
        // Check for session timeout warning
        // Backend returns session.expires_at or session.remaining_seconds
        const expiresAt = status.expiresAt || status.session?.expires_at;
        const remainingSeconds = status.session?.remaining_seconds;
        
        if (remainingSeconds !== undefined) {
          // Use remaining_seconds if available
          const remainingMinutes = remainingSeconds / 60;
          if (remainingMinutes <= SESSION_WARNING_THRESHOLD && remainingMinutes > 0) {
            setSessionWarning(true);
          } else {
            setSessionWarning(false);
          }
        } else if (expiresAt) {
          // Fall back to calculating from expiresAt
          const expiresDate = new Date(expiresAt);
          const now = new Date();
          const remainingMinutes = (expiresDate.getTime() - now.getTime()) / (1000 * 60);
          
          if (remainingMinutes <= SESSION_WARNING_THRESHOLD && remainingMinutes > 0) {
            setSessionWarning(true);
          } else {
            setSessionWarning(false);
          }
        }
      } catch (err) {
        // Session validation failed
        console.error('[AuthContext] Session check failed:', err);
        setUser(null);
        setStage('idle');
      }
    };
    
    // Initial check
    checkSessionStatus();
    
    // Set up periodic checking
    const intervalId = setInterval(checkSessionStatus, SESSION_CHECK_INTERVAL);
    
    return () => clearInterval(intervalId);
  }, [isAuthenticated]);
  
  // ========================================================================
  // Authentication Methods
  // ========================================================================
  
  /**
   * Login with username and password
   */
  const login = useCallback(async (username: string, password: string): Promise<LoginResult> => {
    // Set ref BEFORE any state changes to prevent race condition with checkExistingSession
    loginInProgressRef.current = true;
    
    setLoading(true);
    setError(null);
    setStage('credentials');
    
    try {
      const response: LoginResponse = await authService.login(username, password);
      
      if (response.stage === 'otp') {
        // 2FA is required
        setStage('otp');
        setPendingCredentials({ username, password });
        
        return {
          success: true,
          requires2FA: true,
          otpMethod: response.otpMethod,
          otpDestination: response.otpDestination,
        };
      }
      
      // Login complete (no 2FA required)
      if (response.user) {
        const authUser = transformUser(response.user as unknown as Record<string, unknown>);
        setUser(authUser);
        setStage('authenticated');
        setPendingCredentials(null);
        
        return {
          success: true,
          requires2FA: false,
          user: authUser,
        };
      }
      
      // Unexpected response
      throw new Error('Invalid login response');
      
    } catch (err) {
      const errorMessage = getErrorMessage(err);
      setError(errorMessage);
      setStage('idle');
      setPendingCredentials(null);
      // Reset ref on login failure - login flow is aborted
      loginInProgressRef.current = false;
      
      return {
        success: false,
        requires2FA: false,
        error: errorMessage,
      };
    } finally {
      setLoading(false);
    }
  }, []);
  
  /**
   * Verify 2FA code
   */
  const verify2FA = useCallback(async (code: string, trustDevice = false): Promise<void> => {
    if (stage !== 'otp') {
      throw new Error('2FA verification not expected at this stage');
    }
    
    setLoading(true);
    setError(null);
    
    try {
      const response: AuthResponse = await authService.verify2FA(code, trustDevice);
      
      const authUser = transformUser(response.user as unknown as Record<string, unknown>);
      authUser.trustedDevice = trustDevice;
      
      setUser(authUser);
      setStage('authenticated');
      setPendingCredentials(null);
      // Reset ref on successful 2FA - user is fully logged in
      loginInProgressRef.current = false;
      
    } catch (err) {
      const errorMessage = getErrorMessage(err);
      setError(errorMessage);
      // Stay in OTP stage to allow retry
      throw new Error(errorMessage);
    } finally {
      setLoading(false);
    }
  }, [stage]);
  
  /**
   * Resend OTP code
   */
  const resendOtp = useCallback(async (): Promise<void> => {
    if (stage !== 'otp') {
      throw new Error('Cannot resend OTP at this stage');
    }
    
    setLoading(true);
    setError(null);
    
    try {
      await authService.resendOtp();
    } catch (err) {
      const errorMessage = getErrorMessage(err);
      setError(errorMessage);
      throw new Error(errorMessage);
    } finally {
      setLoading(false);
    }
  }, [stage]);
  
  /**
   * Log out the current user
   */
  const logout = useCallback(async (): Promise<void> => {
    setLoading(true);
    
    try {
      await authService.logout();
    } catch (err) {
      // Log error but continue with local logout
      console.error('Logout API error:', err);
    } finally {
      // Always clear local state
      setUser(null);
      setStage('idle');
      setError(null);
      setSessionWarning(false);
      setPendingCredentials(null);
      setLoading(false);
      // Reset ref on logout to ensure clean state
      loginInProgressRef.current = false;
    }
  }, []);
  
  /**
   * Clear the current error
   */
  const clearError = useCallback((): void => {
    setError(null);
  }, []);
  
  /**
   * Refresh the current session
   */
  const refreshSession = useCallback(async (): Promise<void> => {
    if (!isAuthenticated) {
      return;
    }
    
    try {
      await authService.refreshSession();
      setSessionWarning(false);
    } catch (err) {
      // Session refresh failed - user may need to re-authenticate
      console.error('Session refresh failed:', err);
      setUser(null);
      setStage('idle');
      setError('Your session has expired. Please log in again.');
    }
  }, [isAuthenticated]);
  
  /**
   * Extend session (used when warning is shown)
   */
  const extendSession = useCallback(async (): Promise<void> => {
    await refreshSession();
  }, [refreshSession]);
  
  /**
   * Switch to a different available role
   */
  const switchRole = useCallback((role: UIRole): void => {
    if (!user) {
      return;
    }
    
    if (!user.availableRoles.includes(role)) {
      setError('You do not have access to this role');
      return;
    }
    
    setUser({
      ...user,
      currentRole: role,
    });
  }, [user]);
  
  // ========================================================================
  // Context Value
  // ========================================================================
  
  const value = useMemo<AuthContextType>(() => ({
    user,
    loading,
    error,
    isAuthenticated,
    stage,
    login,
    verify2FA,
    resendOtp,
    logout,
    clearError,
    refreshSession,
    switchRole,
    sessionWarning,
    extendSession,
  }), [
    user,
    loading,
    error,
    isAuthenticated,
    stage,
    login,
    verify2FA,
    resendOtp,
    logout,
    clearError,
    refreshSession,
    switchRole,
    sessionWarning,
    extendSession,
  ]);
  
  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

// ============================================================================
// useAuth Hook (basic)
// ============================================================================

/**
 * Hook to access the authentication context
 * @throws Error if used outside of AuthProvider
 */
export function useAuth(): AuthContextType {
  const context = useContext(AuthContext);
  
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  
  return context;
}

// ============================================================================
// Export Context for Advanced Usage
// ============================================================================

export { AuthContext };
export type { UIRole };
