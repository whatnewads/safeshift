/**
 * OSHA Reporting Hook for SafeShift EHR
 * 
 * Provides React hooks for managing OSHA injury records,
 * Form 300/300A logs, incident rates, and ITA submissions.
 */

import { useState, useCallback, useEffect } from 'react';
import { oshaService, getErrorMessage } from '../services/index.js';
import type { 
  OshaInjury,
  OshaLog,
  OshaRates,
  OshaFilters,
  CreateInjuryDTO,
  UpdateInjuryDTO
} from '../types/api.types.js';

// ============================================================================
// Types
// ============================================================================

interface PaginationState {
  total: number;
  page: number;
  limit: number;
  totalPages: number;
}

interface UseOshaInjuriesReturn {
  injuries: OshaInjury[];
  loading: boolean;
  error: string | null;
  pagination: PaginationState;
  fetchInjuries: (filters?: OshaFilters) => Promise<void>;
  recordInjury: (data: CreateInjuryDTO) => Promise<OshaInjury | null>;
  updateInjury: (id: string, data: UpdateInjuryDTO) => Promise<OshaInjury | null>;
  deleteInjury: (id: string) => Promise<boolean>;
  refetch: (filters?: OshaFilters) => Promise<void>;
  clearError: () => void;
}

interface UseOshaLogReturn {
  log: OshaLog | null;
  summary: OshaLog | null;
  rates: OshaRates | null;
  loading: boolean;
  error: string | null;
  fetch300Log: () => Promise<void>;
  fetch300ALog: () => Promise<void>;
  fetchRates: () => Promise<void>;
  fetchAll: () => Promise<void>;
  clearError: () => void;
}

// ============================================================================
// useOshaInjuries Hook - Injury List/CRUD
// ============================================================================

/**
 * Hook for managing OSHA injury records
 * @param initialFilters - Optional initial filters
 * @returns OSHA injuries state and operations
 */
export function useOshaInjuries(initialFilters?: OshaFilters): UseOshaInjuriesReturn {
  const [injuries, setInjuries] = useState<OshaInjury[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<PaginationState>({ 
    total: 0, 
    page: 1, 
    limit: 20,
    totalPages: 0
  });

  /**
   * Fetch OSHA injuries with optional filters
   */
  const fetchInjuries = useCallback(async (filters?: OshaFilters) => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.getInjuries(filters);
      if (response.success && response.data) {
        setInjuries(response.data.data);
        setPagination({
          total: response.data.total,
          page: response.data.page,
          limit: response.data.limit,
          totalPages: response.data.totalPages ?? Math.ceil(response.data.total / response.data.limit),
        });
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch OSHA injuries'));
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Record a new OSHA injury
   */
  const recordInjury = useCallback(async (data: CreateInjuryDTO): Promise<OshaInjury | null> => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.recordInjury(data);
      if (response.success && response.data) {
        const newInjury = response.data;
        setInjuries(prev => [newInjury, ...prev]);
        setPagination(prev => ({ ...prev, total: prev.total + 1 }));
        return newInjury;
      }
      setError(response.message || 'Failed to record injury');
      return null;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to record OSHA injury'));
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Update an existing OSHA injury
   */
  const updateInjury = useCallback(async (id: string, data: UpdateInjuryDTO): Promise<OshaInjury | null> => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.updateInjury(id, data);
      if (response.success && response.data) {
        const updatedInjury = response.data;
        setInjuries(prev => prev.map(i => i.id === id ? updatedInjury : i));
        return updatedInjury;
      }
      setError(response.message || 'Failed to update injury');
      return null;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to update OSHA injury'));
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Delete an OSHA injury (soft delete)
   */
  const deleteInjury = useCallback(async (id: string): Promise<boolean> => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.deleteInjury(id);
      if (response.success) {
        setInjuries(prev => prev.filter(i => i.id !== id));
        setPagination(prev => ({ ...prev, total: Math.max(0, prev.total - 1) }));
        return true;
      }
      setError(response.message || 'Failed to delete injury');
      return false;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to delete OSHA injury'));
      return false;
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

  // Fetch initial data
  useEffect(() => {
    fetchInjuries(initialFilters);
  }, [fetchInjuries, initialFilters]);

  return {
    injuries,
    loading,
    error,
    pagination,
    fetchInjuries,
    recordInjury,
    updateInjury,
    deleteInjury,
    refetch: fetchInjuries,
    clearError,
  };
}

// ============================================================================
// useOshaLog Hook - Form 300/300A Log Data
// ============================================================================

/**
 * Hook for managing OSHA 300/300A logs and rates
 * @param year - The year for the log
 * @param establishmentId - Optional establishment ID
 * @returns OSHA log state and operations
 */
export function useOshaLog(year: number, establishmentId?: string): UseOshaLogReturn {
  const [log, setLog] = useState<OshaLog | null>(null);
  const [summary, setSummary] = useState<OshaLog | null>(null);
  const [rates, setRates] = useState<OshaRates | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Fetch OSHA Form 300 log
   */
  const fetch300Log = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.get300Log(year, establishmentId);
      if (response.success && response.data) {
        setLog(response.data);
      } else {
        setError(response.message || 'Failed to fetch 300 log');
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch OSHA 300 log'));
    } finally {
      setLoading(false);
    }
  }, [year, establishmentId]);

  /**
   * Fetch OSHA Form 300A summary log
   */
  const fetch300ALog = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.get300ALog(year, establishmentId);
      if (response.success && response.data) {
        setSummary(response.data);
      } else {
        setError(response.message || 'Failed to fetch 300A log');
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch OSHA 300A log'));
    } finally {
      setLoading(false);
    }
  }, [year, establishmentId]);

  /**
   * Fetch OSHA incident rates
   */
  const fetchRates = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.getRates(year, establishmentId);
      if (response.success && response.data) {
        setRates(response.data);
      } else {
        setError(response.message || 'Failed to fetch rates');
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch OSHA rates'));
    } finally {
      setLoading(false);
    }
  }, [year, establishmentId]);

  /**
   * Fetch all OSHA data (300, 300A, and rates)
   */
  const fetchAll = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [logRes, summaryRes, ratesRes] = await Promise.all([
        oshaService.get300Log(year, establishmentId),
        oshaService.get300ALog(year, establishmentId),
        oshaService.getRates(year, establishmentId),
      ]);

      if (logRes.success) setLog(logRes.data);
      if (summaryRes.success) setSummary(summaryRes.data);
      if (ratesRes.success) setRates(ratesRes.data);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch OSHA data'));
    } finally {
      setLoading(false);
    }
  }, [year, establishmentId]);

  /**
   * Clear error state
   */
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  // Fetch data on mount or when year/establishment changes
  useEffect(() => {
    if (year) {
      fetchAll();
    }
  }, [year, establishmentId, fetchAll]);

  return {
    log,
    summary,
    rates,
    loading,
    error,
    fetch300Log,
    fetch300ALog,
    fetchRates,
    fetchAll,
    clearError,
  };
}

// ============================================================================
// useOshaRates Hook - Incident Rates Only
// ============================================================================

/**
 * Hook for fetching just OSHA incident rates
 * @param year - The year for calculations
 * @param establishmentId - Optional establishment ID
 * @returns OSHA rates state
 */
export function useOshaRates(year: number, establishmentId?: string) {
  const [rates, setRates] = useState<OshaRates | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchRates = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.getRates(year, establishmentId);
      if (response.success && response.data) {
        setRates(response.data);
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch OSHA rates'));
    } finally {
      setLoading(false);
    }
  }, [year, establishmentId]);

  useEffect(() => {
    if (year) {
      fetchRates();
    }
  }, [year, establishmentId, fetchRates]);

  return {
    rates,
    loading,
    error,
    refetch: fetchRates,
    // Convenience getters for common metrics
    trir: rates?.trir ?? null,
    dart: rates?.dart ?? null,
    ltir: rates?.ltir ?? null,
    severity: rates?.severity ?? null,
  };
}

// ============================================================================
// useOshaIta Hook - ITA Submission
// ============================================================================

/**
 * Hook for OSHA ITA (Injury Tracking Application) submissions
 * @returns ITA submission state and operations
 */
export function useOshaIta() {
  const [submissions, setSubmissions] = useState<Array<{
    id: string;
    year: number;
    submittedAt: string;
    status: string;
  }>>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  /**
   * Fetch ITA submission history
   */
  const fetchSubmissions = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await oshaService.getItaSubmissions();
      if (response.success && response.data) {
        setSubmissions(response.data);
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch ITA submissions'));
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Submit data to OSHA ITA
   */
  const submitToIta = useCallback(async (data: {
    year: number;
    establishmentId: string;
    submissionType: 'form-300a' | 'form-300' | 'both';
  }): Promise<{ submissionId: string; status: string } | null> => {
    setSubmitting(true);
    setError(null);
    try {
      const response = await oshaService.submitToIta(data);
      if (response.success && response.data) {
        // Refresh submissions list
        await fetchSubmissions();
        return response.data;
      }
      setError(response.message || 'Failed to submit to ITA');
      return null;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to submit to OSHA ITA'));
      return null;
    } finally {
      setSubmitting(false);
    }
  }, [fetchSubmissions]);

  useEffect(() => {
    fetchSubmissions();
  }, [fetchSubmissions]);

  return {
    submissions,
    loading,
    submitting,
    error,
    submitToIta,
    refetch: fetchSubmissions,
  };
}

export default useOshaInjuries;
