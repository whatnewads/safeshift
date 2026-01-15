/**
 * Registration Dashboard Hook
 *
 * React hooks for registration dashboard functionality including queue
 * statistics, pending registrations, and patient search.
 *
 * Part of the Registration Dashboard workflow: View → Hook → Service → API
 *
 * @module hooks/useRegistration
 */

import { useState, useEffect, useCallback } from 'react';
import {
  getDashboardData,
  getQueueStats,
  getPendingRegistrations,
  searchPatients,
  getPatient,
} from '../services/registration.service.js';
import type {
  QueueStats,
  PendingRegistration,
  PatientSearchResult,
} from '../services/registration.service.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for the main registration dashboard hook
 */
interface UseRegistrationReturn {
  /** Queue statistics (waiting, in progress, completed today) */
  queueStats: QueueStats | null;
  /** List of pending patient registrations */
  pendingRegistrations: PendingRegistration[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch dashboard data */
  refetch: () => Promise<void>;
  /** Clear any errors */
  clearError: () => void;
}

/**
 * Return type for the queue stats only hook
 */
interface UseQueueStatsReturn {
  /** Queue statistics */
  queueStats: QueueStats | null;
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch queue stats */
  refetch: () => Promise<void>;
}

/**
 * Return type for the pending registrations hook
 */
interface UsePendingRegistrationsReturn {
  /** List of pending registrations */
  pendingRegistrations: PendingRegistration[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch pending registrations */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the patient search hook
 */
interface UsePatientSearchReturn {
  /** Search results */
  results: PatientSearchResult[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Perform a search */
  search: (query: string) => Promise<void>;
  /** Clear search results */
  clearResults: () => void;
  /** Current search query */
  query: string;
}

// ============================================================================
// useRegistration Hook (Main Dashboard Hook)
// ============================================================================

/**
 * Main hook for registration dashboard data
 * 
 * Fetches complete dashboard data on mount and provides refetch capability.
 * 
 * @returns Registration dashboard state and operations
 * 
 * @example
 * ```typescript
 * function RegistrationDashboard() {
 *   const { queueStats, pendingRegistrations, loading, error, refetch } = useRegistration();
 *   
 *   if (loading) return <LoadingSpinner />;
 *   if (error) return <ErrorMessage message={error} />;
 *   
 *   return (
 *     <div>
 *       <QueueStatsDisplay stats={queueStats} />
 *       <PendingList registrations={pendingRegistrations} />
 *       <button onClick={refetch}>Refresh</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function useRegistration(): UseRegistrationReturn {
  const [queueStats, setQueueStats] = useState<QueueStats | null>(null);
  const [pendingRegistrations, setPendingRegistrations] = useState<PendingRegistration[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getDashboardData();
      setQueueStats(data.queueStats);
      setPendingRegistrations(data.pendingRegistrations);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load registration dashboard';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return {
    queueStats,
    pendingRegistrations,
    loading,
    error,
    refetch: fetchDashboard,
    clearError,
  };
}

// ============================================================================
// useQueueStats Hook
// ============================================================================

/**
 * Hook for queue statistics only
 * 
 * Useful for displaying just the queue counts without the full registration list.
 * 
 * @returns Queue stats state and operations
 * 
 * @example
 * ```typescript
 * function QueueStatusBar() {
 *   const { queueStats, loading } = useQueueStats();
 *   
 *   if (!queueStats) return null;
 *   
 *   return (
 *     <div>
 *       Waiting: {queueStats.waiting} | 
 *       In Progress: {queueStats.inProgress} | 
 *       Completed: {queueStats.completedToday}
 *     </div>
 *   );
 * }
 * ```
 */
export function useQueueStats(): UseQueueStatsReturn {
  const [queueStats, setQueueStats] = useState<QueueStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchStats = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const stats = await getQueueStats();
      setQueueStats(stats);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load queue statistics';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  return {
    queueStats,
    loading,
    error,
    refetch: fetchStats,
  };
}

// ============================================================================
// usePendingRegistrations Hook
// ============================================================================

/**
 * Hook for pending registrations list
 * 
 * Fetches the list of patients waiting for registration with configurable limit.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Pending registrations state and operations
 * 
 * @example
 * ```typescript
 * function PendingQueue() {
 *   const { pendingRegistrations, loading, setLimit } = usePendingRegistrations(10);
 *   
 *   return (
 *     <div>
 *       {pendingRegistrations.map(p => (
 *         <PatientCard key={p.id} patient={p} />
 *       ))}
 *       <button onClick={() => setLimit(20)}>Show More</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function usePendingRegistrations(initialLimit = 20): UsePendingRegistrationsReturn {
  const [pendingRegistrations, setPendingRegistrations] = useState<PendingRegistration[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchPending = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const registrations = await getPendingRegistrations(limit);
      setPendingRegistrations(registrations);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load pending registrations';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchPending();
  }, [fetchPending]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    pendingRegistrations,
    loading,
    error,
    refetch: fetchPending,
    setLimit,
  };
}

// ============================================================================
// usePatientSearch Hook
// ============================================================================

/**
 * Hook for patient search functionality
 * 
 * Provides debounced patient search for registration workflows.
 * 
 * @returns Patient search state and operations
 * 
 * @example
 * ```typescript
 * function PatientLookup() {
 *   const { results, loading, search, clearResults, query } = usePatientSearch();
 *   
 *   return (
 *     <div>
 *       <input 
 *         type="text"
 *         onChange={(e) => search(e.target.value)}
 *         placeholder="Search patients..."
 *       />
 *       {loading && <LoadingSpinner />}
 *       {results.map(patient => (
 *         <PatientResult key={patient.id} patient={patient} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function usePatientSearch(): UsePatientSearchReturn {
  const [results, setResults] = useState<PatientSearchResult[]>([]);
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const search = useCallback(async (searchQuery: string) => {
    const trimmedQuery = searchQuery.trim();
    setQuery(trimmedQuery);
    
    // Clear results if query is too short
    if (trimmedQuery.length < 2) {
      setResults([]);
      setError(null);
      return;
    }

    try {
      setLoading(true);
      setError(null);
      const patients = await searchPatients(trimmedQuery);
      setResults(patients);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to search patients';
      setError(message);
      setResults([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const clearResults = useCallback(() => {
    setResults([]);
    setQuery('');
    setError(null);
  }, []);

  return {
    results,
    loading,
    error,
    search,
    clearResults,
    query,
  };
}

// ============================================================================
// usePatient Hook
// ============================================================================

/**
 * Hook for fetching a single patient by ID
 * 
 * @param patientId - Patient UUID to fetch
 * @returns Patient data and loading state
 * 
 * @example
 * ```typescript
 * function PatientDetails({ patientId }: { patientId: string }) {
 *   const { patient, loading, error } = usePatient(patientId);
 *   
 *   if (loading) return <LoadingSpinner />;
 *   if (error) return <ErrorMessage message={error} />;
 *   if (!patient) return <NotFound />;
 *   
 *   return (
 *     <div>
 *       <h2>{patient.firstName} {patient.lastName}</h2>
 *       <p>DOB: {patient.dateOfBirth}</p>
 *     </div>
 *   );
 * }
 * ```
 */
export function usePatient(patientId: string | null) {
  const [patient, setPatient] = useState<PatientSearchResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchPatient = useCallback(async () => {
    if (!patientId) {
      setPatient(null);
      return;
    }

    try {
      setLoading(true);
      setError(null);
      const data = await getPatient(patientId);
      setPatient(data);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load patient';
      setError(message);
      setPatient(null);
    } finally {
      setLoading(false);
    }
  }, [patientId]);

  useEffect(() => {
    fetchPatient();
  }, [fetchPatient]);

  return {
    patient,
    loading,
    error,
    refetch: fetchPatient,
  };
}

// ============================================================================
// Re-export types for convenience
// ============================================================================

export type {
  QueueStats,
  PendingRegistration,
  PatientSearchResult,
  RegistrationDashboardData,
} from '../services/registration.service.js';

// ============================================================================
// Export Default
// ============================================================================

export default {
  useRegistration,
  useQueueStats,
  usePendingRegistrations,
  usePatientSearch,
  usePatient,
};
