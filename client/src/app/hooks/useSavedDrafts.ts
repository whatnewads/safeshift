/**
 * useSavedDrafts Hook
 *
 * React hook for fetching and managing saved draft encounters.
 * Provides access to draft data, loading states, and refresh functionality.
 *
 * @package SafeShift\Hooks
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { encounterService } from '../services/encounter.service.js';
import type { SavedDraft } from '../types/api.types.js';

// ============================================================================
// Types
// ============================================================================

export interface UseSavedDraftsState {
  /** Array of saved draft encounters */
  drafts: SavedDraft[];
  /** Total count of drafts */
  count: number;
  /** Whether data is being loaded */
  isLoading: boolean;
  /** Whether data is being refreshed (background update) */
  isRefreshing: boolean;
  /** Error message if fetch failed */
  error: string | null;
}

export interface UseSavedDraftsActions {
  /** Refresh the drafts list */
  refetch: () => Promise<void>;
  /** Clear the error state */
  clearError: () => void;
}

export interface UseSavedDraftsReturn extends UseSavedDraftsState, UseSavedDraftsActions {
  /** Whether there are any drafts */
  hasDrafts: boolean;
}

// ============================================================================
// Constants
// ============================================================================

/** Default polling interval: 30 seconds */
const DEFAULT_POLL_INTERVAL = 30000;

/** Default limit for drafts to fetch */
const DEFAULT_LIMIT = 10;

// ============================================================================
// Hook Implementation
// ============================================================================

/**
 * Hook for fetching and managing saved draft encounters
 * 
 * @param autoRefresh - Enable auto-refresh polling (default: true)
 * @param pollInterval - Polling interval in ms (default: 30000 = 30 seconds)
 * @param limit - Maximum number of drafts to fetch (default: 10)
 * 
 * @example
 * ```tsx
 * const {
 *   drafts,
 *   count,
 *   isLoading,
 *   error,
 *   refetch,
 * } = useSavedDrafts();
 * 
 * // Display drafts
 * drafts.map(draft => (
 *   <div key={draft.encounter_id}>
 *     {draft.patient_display_name} - {draft.modified_at}
 *   </div>
 * ));
 * ```
 */
export function useSavedDrafts(
  autoRefresh: boolean = true,
  pollInterval: number = DEFAULT_POLL_INTERVAL,
  limit: number = DEFAULT_LIMIT
): UseSavedDraftsReturn {
  // State
  const [drafts, setDrafts] = useState<SavedDraft[]>([]);
  const [count, setCount] = useState<number>(0);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isRefreshing, setIsRefreshing] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  // Refs for cleanup
  const isMountedRef = useRef<boolean>(true);
  const pollTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ============================================================================
  // Data Fetching
  // ============================================================================

  /**
   * Fetch drafts from API
   */
  const fetchDrafts = useCallback(async (isRefresh: boolean = false): Promise<void> => {
    try {
      if (isRefresh) {
        setIsRefreshing(true);
      } else {
        setIsLoading(true);
      }
      setError(null);

      const response = await encounterService.getDrafts(limit);

      if (!isMountedRef.current) return;

      setDrafts(response.drafts);
      setCount(response.count);
    } catch (err) {
      if (!isMountedRef.current) return;

      const errorMessage = err instanceof Error ? err.message : 'Failed to load saved drafts';
      setError(errorMessage);
      console.error('Error fetching saved drafts:', err);
    } finally {
      if (isMountedRef.current) {
        setIsLoading(false);
        setIsRefreshing(false);
      }
    }
  }, [limit]);

  /**
   * Refetch drafts (can be called externally)
   */
  const refetch = useCallback(async (): Promise<void> => {
    await fetchDrafts(true);
  }, [fetchDrafts]);

  /**
   * Clear error state
   */
  const clearError = useCallback((): void => {
    setError(null);
  }, []);

  // ============================================================================
  // Effects
  // ============================================================================

  // Initial fetch on mount
  useEffect(() => {
    fetchDrafts(false);
  }, [fetchDrafts]);

  // Auto-refresh polling
  useEffect(() => {
    if (autoRefresh && pollInterval > 0) {
      pollTimerRef.current = setInterval(() => {
        if (isMountedRef.current) {
          fetchDrafts(true);
        }
      }, pollInterval);
    }

    return () => {
      if (pollTimerRef.current) {
        clearInterval(pollTimerRef.current);
        pollTimerRef.current = null;
      }
    };
  }, [autoRefresh, pollInterval, fetchDrafts]);

  // Cleanup on unmount
  useEffect(() => {
    isMountedRef.current = true;

    return () => {
      isMountedRef.current = false;
      if (pollTimerRef.current) {
        clearInterval(pollTimerRef.current);
      }
    };
  }, []);

  // ============================================================================
  // Computed Values
  // ============================================================================

  const hasDrafts = drafts.length > 0;

  // ============================================================================
  // Return
  // ============================================================================

  return {
    // State
    drafts,
    count,
    isLoading,
    isRefreshing,
    error,

    // Computed
    hasDrafts,

    // Actions
    refetch,
    clearError,
  };
}

export default useSavedDrafts;
