/**
 * State Persistence Hook for SafeShift EHR
 * 
 * Provides localStorage-based state persistence for form data,
 * route state, and tab selections to survive app switching on mobile.
 * 
 * Features:
 * - Debounced auto-save on state changes
 * - Visibility API integration for app resume
 * - Automatic state hydration on mount
 * - Encrypted storage option for PHI (future)
 * - Configurable expiration
 */

import { useState, useEffect, useCallback, useRef } from 'react';

// ============================================================================
// Configuration
// ============================================================================

/** Default expiration time for persisted state (24 hours) */
const DEFAULT_EXPIRATION_MS = 24 * 60 * 60 * 1000;

/** Default debounce delay for saves (500ms) */
const DEFAULT_DEBOUNCE_MS = 500;

/** Storage key prefix for all SafeShift data */
const STORAGE_PREFIX = 'safeshift_ehr_';

// ============================================================================
// Types
// ============================================================================

export interface PersistenceConfig {
  /** Key to store the data under (will be prefixed) */
  key: string;
  /** Expiration time in milliseconds (default: 24 hours) */
  expirationMs?: number;
  /** Debounce delay for saves (default: 500ms) */
  debounceMs?: number;
  /** Whether to encrypt stored data (for PHI) - future feature */
  encrypt?: boolean;
}

export interface PersistedState<T> {
  data: T;
  timestamp: number;
  version: string;
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Get the full storage key with prefix
 */
function getStorageKey(key: string): string {
  return `${STORAGE_PREFIX}${key}`;
}

/**
 * Check if persisted state has expired
 */
function isExpired(timestamp: number, expirationMs: number): boolean {
  return Date.now() - timestamp > expirationMs;
}

/**
 * Safely parse JSON from localStorage
 */
function safeJsonParse<T>(value: string | null): T | null {
  if (!value) return null;
  try {
    return JSON.parse(value) as T;
  } catch (error) {
    console.error('Error parsing persisted state:', error);
    return null;
  }
}

/**
 * Create a debounced function
 */
function debounce<TArgs extends unknown[]>(
  func: (...args: TArgs) => void,
  wait: number
): (...args: TArgs) => void {
  let timeout: ReturnType<typeof setTimeout> | null = null;
  
  return (...args: TArgs) => {
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}

// ============================================================================
// Hook: useStatePersistence
// ============================================================================

/**
 * Hook for persisting state to localStorage with automatic save/restore
 * 
 * @param initialValue - Initial state value
 * @param config - Persistence configuration
 * @returns [state, setState, clearState]
 * 
 * @example
 * ```typescript
 * const [formData, setFormData, clearFormData] = useStatePersistence(
 *   { name: '', email: '' },
 *   { key: 'encounter_form', expirationMs: 3600000 }
 * );
 * ```
 */
export function useStatePersistence<T>(
  initialValue: T,
  config: PersistenceConfig
): [T, React.Dispatch<React.SetStateAction<T>>, () => void] {
  const {
    key,
    expirationMs = DEFAULT_EXPIRATION_MS,
    debounceMs = DEFAULT_DEBOUNCE_MS,
  } = config;
  
  const storageKey = getStorageKey(key);
  const isInitialMount = useRef(true);
  
  // Initialize state from localStorage or use initial value
  const [state, setState] = useState<T>(() => {
    if (typeof window === 'undefined') return initialValue;
    
    const stored = localStorage.getItem(storageKey);
    const parsed = safeJsonParse<PersistedState<T>>(stored);
    
    if (parsed && !isExpired(parsed.timestamp, expirationMs)) {
      console.log(`[StatePersistence] Restored state for ${key}`);
      return parsed.data;
    }
    
    return initialValue;
  });
  
  // Create debounced save function
  const saveToStorage = useCallback(
    debounce((data: T) => {
      const persisted: PersistedState<T> = {
        data,
        timestamp: Date.now(),
        version: '1.0',
      };
      
      try {
        localStorage.setItem(storageKey, JSON.stringify(persisted));
        console.log(`[StatePersistence] Saved state for ${key}`);
      } catch (error) {
        console.error(`[StatePersistence] Error saving state for ${key}:`, error);
      }
    }, debounceMs),
    [storageKey, key, debounceMs]
  );
  
  // Save state whenever it changes (skip initial mount)
  useEffect(() => {
    if (isInitialMount.current) {
      isInitialMount.current = false;
      return;
    }
    
    saveToStorage(state);
  }, [state, saveToStorage]);
  
  // Handle visibility change (app resume)
  useEffect(() => {
    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        // App came back to foreground - check if we need to restore
        console.log(`[StatePersistence] App resumed, checking state for ${key}`);
      } else {
        // App went to background - force immediate save
        const persisted: PersistedState<T> = {
          data: state,
          timestamp: Date.now(),
          version: '1.0',
        };
        
        try {
          localStorage.setItem(storageKey, JSON.stringify(persisted));
          console.log(`[StatePersistence] Saved state on background for ${key}`);
        } catch (error) {
          console.error(`[StatePersistence] Error saving on background for ${key}:`, error);
        }
      }
    };
    
    document.addEventListener('visibilitychange', handleVisibilityChange);
    
    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [state, storageKey, key]);
  
  // Clear persisted state
  const clearState = useCallback(() => {
    localStorage.removeItem(storageKey);
    setState(initialValue);
    console.log(`[StatePersistence] Cleared state for ${key}`);
  }, [storageKey, initialValue, key]);
  
  return [state, setState, clearState];
}

// ============================================================================
// Hook: useRouteState
// ============================================================================

export interface RouteState {
  path: string;
  params?: Record<string, string>;
  timestamp: number;
}

/**
 * Hook for persisting current route/navigation state
 * 
 * @example
 * ```typescript
 * const { saveRoute, getLastRoute, clearRoute } = useRouteState();
 * 
 * // Save current route on navigation
 * saveRoute('/encounters/123', { tab: 'patient' });
 * 
 * // Restore on app resume
 * const lastRoute = getLastRoute();
 * if (lastRoute) navigate(lastRoute.path);
 * ```
 */
export function useRouteState() {
  const storageKey = getStorageKey('route_state');
  
  const saveRoute = useCallback((path: string, params?: Record<string, string>) => {
    const routeState: RouteState = {
      path,
      ...(params && { params }),
      timestamp: Date.now(),
    };
    
    try {
      localStorage.setItem(storageKey, JSON.stringify(routeState));
    } catch (error) {
      console.error('[RouteState] Error saving route:', error);
    }
  }, [storageKey]);
  
  const getLastRoute = useCallback((): RouteState | null => {
    const stored = localStorage.getItem(storageKey);
    const parsed = safeJsonParse<RouteState>(stored);
    
    // Expire after 1 hour
    if (parsed && !isExpired(parsed.timestamp, 60 * 60 * 1000)) {
      return parsed;
    }
    
    return null;
  }, [storageKey]);
  
  const clearRoute = useCallback(() => {
    localStorage.removeItem(storageKey);
  }, [storageKey]);
  
  return { saveRoute, getLastRoute, clearRoute };
}

// ============================================================================
// Hook: useFormPersistence
// ============================================================================

/**
 * Higher-level hook specifically for form data persistence
 * Includes additional features like dirty state tracking
 * 
 * @example
 * ```typescript
 * const {
 *   formData,
 *   setFormData,
 *   updateField,
 *   isDirty,
 *   resetForm,
 *   clearPersistence
 * } = useFormPersistence('patient_form', initialPatientData);
 * ```
 */
export function useFormPersistence<T extends Record<string, unknown>>(
  formKey: string,
  initialData: T
) {
  const [formData, setFormData, clearPersistence] = useStatePersistence<T>(
    initialData,
    { key: `form_${formKey}`, expirationMs: 12 * 60 * 60 * 1000 } // 12 hours for forms
  );
  
  const [isDirty, setIsDirty] = useState(false);
  
  // Update a single field
  const updateField = useCallback(<K extends keyof T>(field: K, value: T[K]) => {
    setFormData(prev => ({
      ...prev,
      [field]: value,
    }));
    setIsDirty(true);
  }, [setFormData]);
  
  // Reset form to initial data
  const resetForm = useCallback(() => {
    setFormData(initialData);
    setIsDirty(false);
    clearPersistence();
  }, [initialData, setFormData, clearPersistence]);
  
  return {
    formData,
    setFormData,
    updateField,
    isDirty,
    resetForm,
    clearPersistence,
  };
}

// ============================================================================
// Hook: useEncounterStatePersistence
// ============================================================================

export interface EncounterPersistenceState {
  encounterId: string | null;
  activeTab: string;
  formData: Record<string, unknown>;
  scrollPositions: Record<string, number>;
  lastModified: number;
}

/**
 * Specialized hook for encounter/EHR workspace state persistence
 * 
 * @example
 * ```typescript
 * const {
 *   state,
 *   setActiveTab,
 *   updateFormData,
 *   saveScrollPosition,
 *   clearEncounterState
 * } = useEncounterStatePersistence(encounterId);
 * ```
 */
export function useEncounterStatePersistence(encounterId: string | null) {
  const initialState: EncounterPersistenceState = {
    encounterId,
    activeTab: 'incident',
    formData: {},
    scrollPositions: {},
    lastModified: Date.now(),
  };
  
  const [state, setState, clearState] = useStatePersistence<EncounterPersistenceState>(
    initialState,
    { key: `encounter_${encounterId || 'new'}`, expirationMs: 8 * 60 * 60 * 1000 } // 8 hours
  );
  
  // Check if the persisted state is for the current encounter
  useEffect(() => {
    if (state.encounterId !== encounterId) {
      // Different encounter, reset state
      setState(initialState);
    }
  }, [encounterId, state.encounterId, setState, initialState]);
  
  const setActiveTab = useCallback((tab: string) => {
    setState(prev => ({
      ...prev,
      activeTab: tab,
      lastModified: Date.now(),
    }));
  }, [setState]);
  
  const updateFormData = useCallback((section: string, data: Record<string, unknown>) => {
    setState(prev => ({
      ...prev,
      formData: {
        ...prev.formData,
        [section]: {
          ...(prev.formData[section] as Record<string, unknown> || {}),
          ...data,
        },
      },
      lastModified: Date.now(),
    }));
  }, [setState]);
  
  const saveScrollPosition = useCallback((elementId: string, position: number) => {
    setState(prev => ({
      ...prev,
      scrollPositions: {
        ...prev.scrollPositions,
        [elementId]: position,
      },
    }));
  }, [setState]);
  
  return {
    state,
    setActiveTab,
    updateFormData,
    saveScrollPosition,
    clearEncounterState: clearState,
  };
}

// ============================================================================
// Utility: Clear All Persisted State
// ============================================================================

/**
 * Clear all SafeShift persisted state from localStorage
 * Use with caution - typically on logout or explicit user action
 */
export function clearAllPersistedState(): void {
  const keys = Object.keys(localStorage);
  let cleared = 0;
  
  keys.forEach(key => {
    if (key.startsWith(STORAGE_PREFIX)) {
      localStorage.removeItem(key);
      cleared++;
    }
  });
  
  console.log(`[StatePersistence] Cleared ${cleared} persisted state entries`);
}

// ============================================================================
// Export
// ============================================================================

export default useStatePersistence;
