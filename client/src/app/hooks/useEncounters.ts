/**
 * Encounter Data Hook for SafeShift EHR
 *
 * Provides React hooks for managing encounter data including
 * fetching, creating, updating, vitals recording, signing, and submission.
 */

import { useState, useCallback, useEffect } from 'react';
import { encounterService, getErrorMessage } from '../services/index.js';
import { isValidEncounterId } from '../services/validation.service.js';
import type {
  EncounterFilters,
  CreateEncounterDTO,
  UpdateEncounterDTO,
  Vitals,
  VitalsDTO,
  AmendmentDTO
} from '../types/api.types.js';
import type { Encounter } from '../types/index.js';

// ============================================================================
// EHR Hook Logging Utilities
// ============================================================================

/**
 * Log an EHR hook operation
 */
function logHookOperation(
  hookName: string,
  operation: string,
  status: 'START' | 'SUCCESS' | 'ERROR',
  details?: Record<string, unknown>
): void {
  const timestamp = new Date().toISOString();
  const logMessage = `[EHR-Hook] [${timestamp}] [${hookName}] [${operation}] [${status}]`;
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
// Types
// ============================================================================

interface PaginationState {
  total: number;
  page: number;
  limit: number;
  totalPages: number;
}

interface UseEncountersReturn {
  encounters: Encounter[];
  loading: boolean;
  error: string | null;
  pagination: PaginationState;
  fetchEncounters: (filters?: EncounterFilters) => Promise<void>;
  createEncounter: (data: CreateEncounterDTO) => Promise<Encounter | null>;
  refetch: (filters?: EncounterFilters) => Promise<void>;
  clearError: () => void;
}

interface UseEncounterReturn {
  encounter: Encounter | null;
  vitals: Vitals[];
  loading: boolean;
  error: string | null;
  fetchEncounter: () => Promise<void>;
  updateEncounter: (data: UpdateEncounterDTO) => Promise<Encounter | null>;
  recordVitals: (vitals: VitalsDTO) => Promise<Vitals | null>;
  signEncounter: () => Promise<Encounter | null>;
  submitEncounter: () => Promise<Encounter | null>;
  amendEncounter: (amendment: AmendmentDTO) => Promise<Encounter | null>;
  refetch: () => Promise<void>;
  clearError: () => void;
}

// ============================================================================
// useEncounters Hook - List/Filter Encounters
// ============================================================================

/**
 * Hook for managing encounter list data and operations
 * @returns Encounter list state and operations
 */
export function useEncounters(): UseEncountersReturn {
  const [encounters, setEncounters] = useState<Encounter[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<PaginationState>({ 
    total: 0, 
    page: 1, 
    limit: 20,
    totalPages: 0
  });

  /**
   * Fetch encounters with optional filters
   */
  const fetchEncounters = useCallback(async (filters?: EncounterFilters) => {
    logHookOperation('useEncounters', 'FETCH_ENCOUNTERS', 'START', { filters });
    setLoading(true);
    setError(null);
    try {
      // encounterService.getEncounters returns PaginatedResponse<Encounter> directly
      const paginatedData = await encounterService.getEncounters(filters);
      setEncounters(paginatedData.data);
      setPagination({
        total: paginatedData.total,
        page: paginatedData.page,
        limit: paginatedData.limit,
        totalPages: paginatedData.totalPages ?? Math.ceil(paginatedData.total / paginatedData.limit),
      });
      logHookOperation('useEncounters', 'FETCH_ENCOUNTERS', 'SUCCESS', {
        count: paginatedData.data.length,
        total: paginatedData.total
      });
    } catch (err: unknown) {
      logHookOperation('useEncounters', 'FETCH_ENCOUNTERS', 'ERROR', {
        error: err instanceof Error ? err.message : String(err)
      });
      setError(getErrorMessage(err, 'Failed to fetch encounters'));
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Create a new encounter
   */
  const createEncounter = useCallback(async (data: CreateEncounterDTO): Promise<Encounter | null> => {
    logHookOperation('useEncounters', 'CREATE_ENCOUNTER', 'START', {
      patientId: data.patientId,
      type: data.type
    });
    setLoading(true);
    setError(null);
    try {
      // encounterService.createEncounter returns Encounter directly
      const newEncounter = await encounterService.createEncounter(data);
      // Add to list if we have encounters loaded
      setEncounters(prev => [newEncounter, ...prev]);
      setPagination(prev => ({ ...prev, total: prev.total + 1 }));
      logHookOperation('useEncounters', 'CREATE_ENCOUNTER', 'SUCCESS', {
        encounterId: newEncounter.id
      });
      return newEncounter;
    } catch (err: unknown) {
      logHookOperation('useEncounters', 'CREATE_ENCOUNTER', 'ERROR', {
        error: err instanceof Error ? err.message : String(err)
      });
      setError(getErrorMessage(err, 'Failed to create encounter'));
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Clear error state
   */
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  return {
    encounters,
    loading,
    error,
    pagination,
    fetchEncounters,
    createEncounter,
    refetch: fetchEncounters,
    clearError,
  };
}

// ============================================================================
// useEncounter Hook - Single Encounter Management
// ============================================================================

/**
 * Hook for managing a single encounter's data including vitals
 * @param encounterId - The encounter ID to manage
 * @returns Single encounter state and operations
 */
export function useEncounter(encounterId: string): UseEncounterReturn {
  const [encounter, setEncounter] = useState<Encounter | null>(null);
  const [vitals, setVitals] = useState<Vitals[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Fetch the encounter data
   */
  const fetchEncounter = useCallback(async () => {
    if (!encounterId) {
      setError('Encounter ID is required');
      return;
    }

    setLoading(true);
    setError(null);
    try {
      // encounterService.getEncounter returns Encounter directly
      const encounterData = await encounterService.getEncounter(encounterId);
      setEncounter(encounterData);
      
      // Also fetch vitals
      try {
        const vitalsData = await encounterService.getEncounterVitals(encounterId);
        setVitals(vitalsData);
      } catch {
        // Vitals may not exist yet, that's okay
        setVitals([]);
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch encounter'));
    } finally {
      setLoading(false);
    }
  }, [encounterId]);

  /**
   * Update the encounter
   */
  const updateEncounter = useCallback(async (data: UpdateEncounterDTO): Promise<Encounter | null> => {
    if (!encounterId) {
      setError('Encounter ID is required');
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      const updatedEncounter = await encounterService.updateEncounter(encounterId, data);
      setEncounter(updatedEncounter);
      return updatedEncounter;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to update encounter'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [encounterId]);

  /**
   * Record vitals for this encounter
   */
  const recordVitals = useCallback(async (vitalsData: VitalsDTO): Promise<Vitals | null> => {
    if (!encounterId) {
      setError('Encounter ID is required');
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      const newVitals = await encounterService.recordVitals(encounterId, vitalsData);
      // Add to vitals list
      setVitals(prev => [...prev, newVitals]);
      return newVitals;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to record vitals'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [encounterId]);

  /**
   * Sign the encounter (provider attestation)
   */
  const signEncounter = useCallback(async (): Promise<Encounter | null> => {
    if (!encounterId) {
      logHookOperation('useEncounter', 'SIGN_ENCOUNTER', 'ERROR', { reason: 'missing_encounter_id' });
      setError('Encounter ID is required');
      return null;
    }

    logHookOperation('useEncounter', 'SIGN_ENCOUNTER', 'START', { encounterId });
    setLoading(true);
    setError(null);
    try {
      const signedEncounter = await encounterService.signEncounter(encounterId);
      setEncounter(signedEncounter);
      logHookOperation('useEncounter', 'SIGN_ENCOUNTER', 'SUCCESS', {
        encounterId,
        newStatus: signedEncounter.status
      });
      return signedEncounter;
    } catch (err: unknown) {
      logHookOperation('useEncounter', 'SIGN_ENCOUNTER', 'ERROR', {
        encounterId,
        error: err instanceof Error ? err.message : String(err)
      });
      setError(getErrorMessage(err, 'Failed to sign encounter'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [encounterId]);

  /**
   * Submit the encounter for review/billing
   *
   * Validates that the encounter has been saved (has a valid ID) before
   * attempting submission. Returns null and sets an error if the encounter
   * cannot be submitted.
   */
  const submitEncounter = useCallback(async (): Promise<Encounter | null> => {
    logHookOperation('useEncounter', 'SUBMIT_ENCOUNTER', 'START', { encounterId });
    
    // Validate encounter ID before attempting submission
    if (!encounterId) {
      logHookOperation('useEncounter', 'SUBMIT_ENCOUNTER', 'ERROR', {
        reason: 'missing_encounter_id'
      });
      setError('Encounter ID is required for submission. Please save the encounter first.');
      return null;
    }
    
    // Check for invalid ID patterns (empty, 'new', temp_ prefix)
    if (!isValidEncounterId(encounterId)) {
      let reason = 'invalid_encounter_id';
      if (encounterId.toLowerCase() === 'new') {
        reason = 'unsaved_encounter';
        setError('Cannot submit an unsaved encounter. Please save the encounter first.');
      } else if (encounterId.startsWith('temp_')) {
        reason = 'offline_encounter';
        setError('Cannot submit an offline encounter. Please sync the encounter first.');
      } else {
        setError('Invalid encounter ID. Please save the encounter first.');
      }
      logHookOperation('useEncounter', 'SUBMIT_ENCOUNTER', 'ERROR', { encounterId, reason });
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      const submittedEncounter = await encounterService.submitEncounter(encounterId);
      setEncounter(submittedEncounter);
      logHookOperation('useEncounter', 'SUBMIT_ENCOUNTER', 'SUCCESS', {
        encounterId,
        newStatus: submittedEncounter.status
      });
      return submittedEncounter;
    } catch (err: unknown) {
      logHookOperation('useEncounter', 'SUBMIT_ENCOUNTER', 'ERROR', {
        encounterId,
        error: err instanceof Error ? err.message : String(err)
      });
      setError(getErrorMessage(err, 'Failed to submit encounter'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [encounterId]);

  /**
   * Amend a signed/submitted encounter
   */
  const amendEncounter = useCallback(async (amendment: AmendmentDTO): Promise<Encounter | null> => {
    if (!encounterId) {
      setError('Encounter ID is required');
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      const amendedEncounter = await encounterService.amendEncounter(encounterId, amendment);
      setEncounter(amendedEncounter);
      return amendedEncounter;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to amend encounter'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [encounterId]);

  /**
   * Clear error state
   */
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  // Fetch encounter on mount or when ID changes
  useEffect(() => {
    if (encounterId) {
      fetchEncounter();
    }
  }, [encounterId, fetchEncounter]);

  return {
    encounter,
    vitals,
    loading,
    error,
    fetchEncounter,
    updateEncounter,
    recordVitals,
    signEncounter,
    submitEncounter,
    amendEncounter,
    refetch: fetchEncounter,
    clearError,
  };
}

// ============================================================================
// usePatientEncounters Hook - Encounters for a specific patient
// ============================================================================

/**
 * Hook for fetching encounters for a specific patient
 * @param patientId - The patient ID
 * @returns Patient encounters state
 */
export function usePatientEncounters(patientId: string) {
  const [encounters, setEncounters] = useState<Encounter[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchEncounters = useCallback(async () => {
    if (!patientId) {
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const paginatedData = await encounterService.getEncounters({ patientId });
      setEncounters(paginatedData.data);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch patient encounters'));
    } finally {
      setLoading(false);
    }
  }, [patientId]);

  useEffect(() => {
    if (patientId) {
      fetchEncounters();
    }
  }, [patientId, fetchEncounters]);

  return {
    encounters,
    loading,
    error,
    refetch: fetchEncounters,
  };
}

export default useEncounters;
