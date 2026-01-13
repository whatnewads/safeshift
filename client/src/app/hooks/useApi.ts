/**
 * Generic API Hook for SafeShift EHR
 * 
 * A reusable hook for data fetching with loading, error, and data states.
 * Supports automatic refetching, memoization, and abort on unmount.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { getErrorMessage, isApiError } from '../services/api.js';
import type { ApiError } from '../types/api.types.js';

// ============================================================================
// Types
// ============================================================================

/**
 * State returned by the useApi hook
 */
export interface UseApiState<T> {
  /** The fetched data, or undefined if not yet loaded */
  data: T | undefined;
  /** Whether a request is currently in progress */
  loading: boolean;
  /** Error message if the request failed, or null if successful */
  error: string | null;
  /** Detailed API error object if available */
  apiError: ApiError | null;
}

/**
 * Return value of the useApi hook
 */
export interface UseApiResult<T> extends UseApiState<T> {
  /** Function to manually trigger a refetch */
  refetch: () => Promise<void>;
  /** Function to manually set the data */
  setData: React.Dispatch<React.SetStateAction<T | undefined>>;
  /** Function to clear the error state */
  clearError: () => void;
  /** Whether data has been successfully loaded at least once */
  isLoaded: boolean;
}

/**
 * Options for the useApi hook
 */
export interface UseApiOptions<T> {
  /** Whether to automatically fetch on mount (default: true) */
  immediate?: boolean;
  /** Initial data value */
  initialData?: T;
  /** Callback when request succeeds */
  onSuccess?: (data: T) => void;
  /** Callback when request fails */
  onError?: (error: string, apiError?: ApiError) => void;
  /** Dependencies that trigger a refetch when changed */
  deps?: unknown[];
  /** Whether to skip the fetch entirely */
  skip?: boolean;
}

// ============================================================================
// Hook Implementation
// ============================================================================

/**
 * Generic hook for making API requests with state management
 * 
 * @template T - The expected data type
 * @param fetcher - Async function that returns the data
 * @param options - Configuration options
 * @returns Object containing data, loading, error states and control functions
 * 
 * @example
 * ```typescript
 * // Basic usage
 * const { data: patients, loading, error } = useApi(
 *   () => patientService.getPatients()
 * );
 * 
 * // With dependencies
 * const { data: patient, refetch } = useApi(
 *   () => patientService.getPatient(patientId),
 *   { deps: [patientId] }
 * );
 * 
 * // Skip until ready
 * const { data } = useApi(
 *   () => fetchData(id),
 *   { skip: !id }
 * );
 * ```
 */
export function useApi<T>(
  fetcher: () => Promise<T>,
  options: UseApiOptions<T> = {}
): UseApiResult<T> {
  const {
    immediate = true,
    initialData,
    onSuccess,
    onError,
    deps = [],
    skip = false,
  } = options;

  // State
  const [data, setData] = useState<T | undefined>(initialData);
  const [loading, setLoading] = useState<boolean>(immediate && !skip);
  const [error, setError] = useState<string | null>(null);
  const [apiError, setApiError] = useState<ApiError | null>(null);
  const [isLoaded, setIsLoaded] = useState<boolean>(!!initialData);

  // Refs for cleanup and memoization
  const abortControllerRef = useRef<AbortController | null>(null);
  const mountedRef = useRef<boolean>(true);
  const fetcherRef = useRef(fetcher);
  
  // Keep fetcher ref updated
  fetcherRef.current = fetcher;

  /**
   * Perform the fetch operation
   */
  const doFetch = useCallback(async (): Promise<void> => {
    // Skip if explicitly told to
    if (skip) {
      setLoading(false);
      return;
    }

    // Cancel any in-flight request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Create new abort controller
    abortControllerRef.current = new AbortController();

    setLoading(true);
    setError(null);
    setApiError(null);

    try {
      const result = await fetcherRef.current();
      
      // Only update state if component is still mounted
      if (mountedRef.current) {
        setData(result);
        setIsLoaded(true);
        setError(null);
        setApiError(null);
        onSuccess?.(result);
      }
    } catch (err) {
      // Only update state if component is still mounted and not aborted
      if (mountedRef.current) {
        const errorMessage = getErrorMessage(err, 'An error occurred while fetching data');
        setError(errorMessage);
        
        if (isApiError(err)) {
          const apiErr = err.response?.data ?? null;
          setApiError(apiErr);
          onError?.(errorMessage, apiErr ?? undefined);
        } else {
          setApiError(null);
          onError?.(errorMessage);
        }
      }
    } finally {
      if (mountedRef.current) {
        setLoading(false);
      }
    }
  }, [skip, onSuccess, onError]);

  /**
   * Refetch function for manual triggering
   */
  const refetch = useCallback(async (): Promise<void> => {
    await doFetch();
  }, [doFetch]);

  /**
   * Clear error state
   */
  const clearError = useCallback((): void => {
    setError(null);
    setApiError(null);
  }, []);

  // Effect for initial fetch and dependency changes
  useEffect(() => {
    if (immediate && !skip) {
      void doFetch();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [immediate, skip, ...deps]);

  // Cleanup on unmount
  useEffect(() => {
    mountedRef.current = true;
    
    return () => {
      mountedRef.current = false;
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, []);

  return {
    data,
    loading,
    error,
    apiError,
    refetch,
    setData,
    clearError,
    isLoaded,
  };
}

// ============================================================================
// Specialized Hooks
// ============================================================================

/**
 * Hook for lazy loading - doesn't fetch until explicitly triggered
 * 
 * @template T - The expected data type
 * @param fetcher - Async function that returns the data
 * @param options - Configuration options (immediate is always false)
 * @returns Object containing data, loading, error states and control functions
 * 
 * @example
 * ```typescript
 * const { data, loading, execute } = useLazyApi(
 *   () => patientService.deletePatient(id)
 * );
 * 
 * // Later, when needed
 * await execute();
 * ```
 */
export function useLazyApi<T>(
  fetcher: () => Promise<T>,
  options: Omit<UseApiOptions<T>, 'immediate'> = {}
): UseApiResult<T> & { execute: () => Promise<T | undefined> } {
  const result = useApi(fetcher, { ...options, immediate: false });
  
  const execute = useCallback(async (): Promise<T | undefined> => {
    await result.refetch();
    return result.data;
  }, [result]);

  return {
    ...result,
    execute,
  };
}

/**
 * Hook for mutation operations (POST, PUT, DELETE)
 * 
 * @template T - The expected response data type
 * @template P - The parameters type for the mutation
 * @param mutator - Function that takes params and returns a promise
 * @param options - Configuration options
 * @returns Object with mutate function and state
 * 
 * @example
 * ```typescript
 * const { mutate, loading, error } = useMutation(
 *   (data: CreatePatientDTO) => patientService.createPatient(data),
 *   { onSuccess: (patient) => navigate(`/patients/${patient.id}`) }
 * );
 * 
 * // In a form handler
 * await mutate({ firstName: 'John', lastName: 'Doe', ... });
 * ```
 */
export function useMutation<T, P = void>(
  mutator: (params: P) => Promise<T>,
  options: {
    onSuccess?: (data: T) => void;
    onError?: (error: string, apiError?: ApiError) => void;
  } = {}
): {
  mutate: (params: P) => Promise<T | undefined>;
  data: T | undefined;
  loading: boolean;
  error: string | null;
  apiError: ApiError | null;
  reset: () => void;
} {
  const { onSuccess, onError } = options;
  
  const [data, setData] = useState<T | undefined>(undefined);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [apiError, setApiError] = useState<ApiError | null>(null);
  
  const mountedRef = useRef(true);

  const mutate = useCallback(async (params: P): Promise<T | undefined> => {
    setLoading(true);
    setError(null);
    setApiError(null);

    try {
      const result = await mutator(params);
      
      if (mountedRef.current) {
        setData(result);
        onSuccess?.(result);
      }
      
      return result;
    } catch (err) {
      if (mountedRef.current) {
        const errorMessage = getErrorMessage(err, 'An error occurred');
        setError(errorMessage);
        
        if (isApiError(err)) {
          const apiErr = err.response?.data ?? null;
          setApiError(apiErr);
          onError?.(errorMessage, apiErr ?? undefined);
        } else {
          onError?.(errorMessage);
        }
      }
      return undefined;
    } finally {
      if (mountedRef.current) {
        setLoading(false);
      }
    }
  }, [mutator, onSuccess, onError]);

  const reset = useCallback(() => {
    setData(undefined);
    setLoading(false);
    setError(null);
    setApiError(null);
  }, []);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  return {
    mutate,
    data,
    loading,
    error,
    apiError,
    reset,
  };
}

export default useApi;
