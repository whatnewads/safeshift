/**
 * DOT Testing Hook for SafeShift EHR
 * 
 * Provides React hooks for managing DOT (Department of Transportation)
 * drug and alcohol testing including test initiation, CCF updates,
 * result submission, and MRO verification.
 */

import { useState, useCallback, useEffect } from 'react';
import { dotService, getErrorMessage } from '../services/index.js';
import type { 
  DotTest,
  DotTestFilters,
  CreateDotTestDTO,
  DotTestResults,
  MroVerification
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

interface DotTestStats {
  pending: number;
  inProgress: number;
  completed: number;
  positive: number;
  negative: number;
}

interface UseDotTestsReturn {
  tests: DotTest[];
  loading: boolean;
  error: string | null;
  pagination: PaginationState;
  stats: DotTestStats | null;
  fetchTests: (filters?: DotTestFilters) => Promise<void>;
  fetchByStatus: (status: DotTest['status']) => Promise<DotTest[]>;
  fetchStats: () => Promise<void>;
  initiateTest: (data: CreateDotTestDTO) => Promise<DotTest | null>;
  refetch: (filters?: DotTestFilters) => Promise<void>;
  clearError: () => void;
}

interface UseDotTestReturn {
  test: DotTest | null;
  loading: boolean;
  error: string | null;
  fetchTest: () => Promise<void>;
  updateCcf: (ccfData: Record<string, unknown>) => Promise<DotTest | null>;
  submitResults: (results: DotTestResults) => Promise<DotTest | null>;
  mroVerify: (verification: MroVerification) => Promise<DotTest | null>;
  cancelTest: (reason: string) => Promise<DotTest | null>;
  refetch: () => Promise<void>;
  clearError: () => void;
}

// ============================================================================
// useDotTests Hook - List/Filter DOT Tests
// ============================================================================

/**
 * Hook for managing DOT test list data and operations
 * @param initialFilters - Optional initial filters
 * @returns DOT test list state and operations
 */
export function useDotTests(initialFilters?: DotTestFilters): UseDotTestsReturn {
  const [tests, setTests] = useState<DotTest[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [stats, setStats] = useState<DotTestStats | null>(null);
  const [pagination, setPagination] = useState<PaginationState>({ 
    total: 0, 
    page: 1, 
    limit: 20,
    totalPages: 0
  });

  /**
   * Fetch DOT tests with optional filters
   */
  const fetchTests = useCallback(async (filters?: DotTestFilters) => {
    setLoading(true);
    setError(null);
    try {
      const response = await dotService.getTests(filters);
      if (response.success && response.data) {
        setTests(response.data.data);
        setPagination({
          total: response.data.total,
          page: response.data.page,
          limit: response.data.limit,
          totalPages: response.data.totalPages ?? Math.ceil(response.data.total / response.data.limit),
        });
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch DOT tests'));
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Fetch DOT tests by status
   */
  const fetchByStatus = useCallback(async (status: DotTest['status']): Promise<DotTest[]> => {
    try {
      const response = await dotService.getByStatus(status);
      if (response.success && response.data) {
        return response.data;
      }
      return [];
    } catch (err: unknown) {
      console.error('Failed to fetch tests by status:', getErrorMessage(err));
      return [];
    }
  }, []);

  /**
   * Fetch DOT test statistics
   */
  const fetchStats = useCallback(async () => {
    try {
      const response = await dotService.getStats();
      if (response.success && response.data) {
        setStats(response.data);
      }
    } catch (err: unknown) {
      console.error('Failed to fetch DOT test stats:', getErrorMessage(err));
    }
  }, []);

  /**
   * Initiate a new DOT test
   */
  const initiateTest = useCallback(async (data: CreateDotTestDTO): Promise<DotTest | null> => {
    setLoading(true);
    setError(null);
    try {
      const response = await dotService.initiateTest(data);
      if (response.success && response.data) {
        const newTest = response.data;
        setTests(prev => [newTest, ...prev]);
        setPagination(prev => ({ ...prev, total: prev.total + 1 }));
        return newTest;
      }
      setError(response.message || 'Failed to initiate DOT test');
      return null;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to initiate DOT test'));
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

  // Fetch initial data
  useEffect(() => {
    fetchTests(initialFilters);
  }, [fetchTests, initialFilters]);

  return {
    tests,
    loading,
    error,
    pagination,
    stats,
    fetchTests,
    fetchByStatus,
    fetchStats,
    initiateTest,
    refetch: fetchTests,
    clearError,
  };
}

// ============================================================================
// useDotTest Hook - Single DOT Test Management
// ============================================================================

/**
 * Hook for managing a single DOT test
 * @param testId - The DOT test ID to manage
 * @returns Single DOT test state and operations
 */
export function useDotTest(testId: string): UseDotTestReturn {
  const [test, setTest] = useState<DotTest | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Fetch the DOT test data
   */
  const fetchTest = useCallback(async () => {
    if (!testId) {
      setError('Test ID is required');
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const response = await dotService.getTest(testId);
      if (response.success && response.data) {
        setTest(response.data);
      } else {
        setError(response.message || 'Failed to fetch DOT test');
      }
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch DOT test'));
    } finally {
      setLoading(false);
    }
  }, [testId]);

  /**
   * Update CCF (Custody and Control Form) data
   */
  const updateCcf = useCallback(async (ccfData: Record<string, unknown>): Promise<DotTest | null> => {
    if (!testId) {
      setError('Test ID is required');
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      const response = await dotService.updateCcf(testId, ccfData);
      if (response.success && response.data) {
        setTest(response.data);
        return response.data;
      }
      setError(response.message || 'Failed to update CCF');
      return null;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to update CCF'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [testId]);

  /**
   * Submit test results from laboratory
   */
  const submitResults = useCallback(async (results: DotTestResults): Promise<DotTest | null> => {
    if (!testId) {
      setError('Test ID is required');
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      const response = await dotService.submitResults(testId, results);
      if (response.success && response.data) {
        setTest(response.data);
        return response.data;
      }
      setError(response.message || 'Failed to submit results');
      return null;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to submit results'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [testId]);

  /**
   * Submit MRO (Medical Review Officer) verification
   */
  const mroVerify = useCallback(async (verification: MroVerification): Promise<DotTest | null> => {
    if (!testId) {
      setError('Test ID is required');
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      const response = await dotService.mroVerify(testId, verification);
      if (response.success && response.data) {
        setTest(response.data);
        return response.data;
      }
      setError(response.message || 'Failed to submit MRO verification');
      return null;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to submit MRO verification'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [testId]);

  /**
   * Cancel the DOT test
   */
  const cancelTest = useCallback(async (reason: string): Promise<DotTest | null> => {
    if (!testId) {
      setError('Test ID is required');
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      const response = await dotService.cancel(testId, reason);
      if (response.success && response.data) {
        setTest(response.data);
        return response.data;
      }
      setError(response.message || 'Failed to cancel test');
      return null;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to cancel test'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [testId]);

  /**
   * Clear error state
   */
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  // Fetch test on mount or when ID changes
  useEffect(() => {
    if (testId) {
      fetchTest();
    }
  }, [testId, fetchTest]);

  return {
    test,
    loading,
    error,
    fetchTest,
    updateCcf,
    submitResults,
    mroVerify,
    cancelTest,
    refetch: fetchTest,
    clearError,
  };
}

// ============================================================================
// usePendingDotTests Hook - Tests awaiting action
// ============================================================================

/**
 * Hook for fetching DOT tests that need attention
 * @returns Pending tests state
 */
export function usePendingDotTests() {
  const [pendingCollection, setPendingCollection] = useState<DotTest[]>([]);
  const [pendingResults, setPendingResults] = useState<DotTest[]>([]);
  const [pendingMro, setPendingMro] = useState<DotTest[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchPending = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [collectionRes, resultsRes, mroRes] = await Promise.all([
        dotService.getByStatus('pending'),
        dotService.getByStatus('results-received'),
        dotService.getByStatus('mro-review'),
      ]);

      if (collectionRes.success) setPendingCollection(collectionRes.data || []);
      if (resultsRes.success) setPendingResults(resultsRes.data || []);
      if (mroRes.success) setPendingMro(mroRes.data || []);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch pending DOT tests'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchPending();
  }, [fetchPending]);

  return {
    pendingCollection,
    pendingResults,
    pendingMro,
    totalPending: pendingCollection.length + pendingResults.length + pendingMro.length,
    loading,
    error,
    refetch: fetchPending,
  };
}

export default useDotTests;
