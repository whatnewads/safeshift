/**
 * Narrative Generation Hook for SafeShift EHR
 *
 * Provides React hook for managing AI-generated narrative generation
 * including loading state, error handling, and narrative storage.
 */

import { useState, useCallback } from 'react';
import { generateNarrative as generateNarrativeApi } from '../services/narrative.service.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for useNarrative hook
 */
export interface UseNarrativeReturn {
  /** The generated narrative text, or null if not generated */
  narrative: string | null;
  /** Whether narrative generation is in progress */
  isGenerating: boolean;
  /** Error message if generation failed, or null */
  error: string | null;
  /** Function to generate a narrative for an encounter */
  generateNarrative: (encounterId: string) => Promise<void>;
  /** Function to clear the current narrative */
  clearNarrative: () => void;
  /** Function to clear the current error */
  clearError: () => void;
}

// ============================================================================
// Logging Utilities
// ============================================================================

/**
 * Log a narrative hook operation
 */
function logNarrativeOperation(
  operation: string,
  status: 'START' | 'SUCCESS' | 'ERROR',
  details?: Record<string, unknown>
): void {
  const timestamp = new Date().toISOString();
  const logMessage = `[Narrative-Hook] [${timestamp}] [${operation}] [${status}]`;
  const detailsStr = details ? JSON.stringify(details) : '';
  
  if (status === 'ERROR') {
    console.error(logMessage, detailsStr);
  } else if (status === 'START') {
    console.debug(logMessage, detailsStr);
  } else {
    console.log(logMessage, detailsStr);
  }
}

// ============================================================================
// useNarrative Hook
// ============================================================================

/**
 * Hook for managing AI-generated narrative generation
 *
 * Provides functions to generate narratives for encounters and manage
 * the loading, error, and narrative states.
 *
 * @returns Narrative state and control functions
 *
 * @example
 * ```typescript
 * const {
 *   narrative,
 *   isGenerating,
 *   error,
 *   generateNarrative,
 *   clearNarrative,
 *   clearError,
 * } = useNarrative();
 *
 * // Generate narrative for an encounter
 * await generateNarrative('enc-123');
 *
 * // Display the narrative
 * if (narrative) {
 *   console.log('Generated:', narrative);
 * }
 *
 * // Handle errors
 * if (error) {
 *   console.error('Failed:', error);
 *   clearError();
 * }
 * ```
 */
export function useNarrative(): UseNarrativeReturn {
  const [narrative, setNarrative] = useState<string | null>(null);
  const [isGenerating, setIsGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Generate a narrative for an encounter
   */
  const generateNarrative = useCallback(async (encounterId: string): Promise<void> => {
    logNarrativeOperation('GENERATE_NARRATIVE', 'START', { encounterId });
    
    setIsGenerating(true);
    setError(null);
    
    try {
      const response = await generateNarrativeApi(encounterId);
      
      if (response.success) {
        setNarrative(response.narrative);
        logNarrativeOperation('GENERATE_NARRATIVE', 'SUCCESS', {
          encounterId,
          narrativeLength: response.narrative.length,
        });
      } else {
        const errorMessage = response.error || 'Failed to generate narrative';
        setError(errorMessage);
        logNarrativeOperation('GENERATE_NARRATIVE', 'ERROR', {
          encounterId,
          error: errorMessage,
        });
      }
    } catch (err: unknown) {
      const errorMessage = err instanceof Error ? err.message : 'An unexpected error occurred';
      setError(errorMessage);
      logNarrativeOperation('GENERATE_NARRATIVE', 'ERROR', {
        encounterId,
        error: errorMessage,
      });
    } finally {
      setIsGenerating(false);
    }
  }, []);

  /**
   * Clear the current narrative
   */
  const clearNarrative = useCallback((): void => {
    setNarrative(null);
  }, []);

  /**
   * Clear the current error
   */
  const clearError = useCallback((): void => {
    setError(null);
  }, []);

  return {
    narrative,
    isGenerating,
    error,
    generateNarrative,
    clearNarrative,
    clearError,
  };
}

export default useNarrative;
