/**
 * Privacy Officer Dashboard Hook
 *
 * React hooks for privacy officer dashboard functionality including compliance KPIs,
 * PHI access logs, consent status, regulatory updates, and training compliance.
 *
 * Part of the Privacy Officer Dashboard workflow: View → Hook → Service → API
 *
 * @module hooks/usePrivacyOfficer
 */

import { useState, useEffect, useCallback } from 'react';
import {
  getDashboardData,
  getComplianceKPIs,
  getPHIAccessLogs,
  getConsentStatus,
  getRegulatoryUpdates,
  getBreachIncidents,
  getTrainingCompliance,
} from '../services/privacy.service.js';
import type {
  ComplianceKPIs,
  AccessLog,
  ConsentStatus,
  RegulatoryUpdate,
  BreachIncident,
  TrainingStats,
  TrainingRecord,
  TrainingCompliance,
} from '../services/privacy.service.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for the main privacy officer dashboard hook
 */
interface UsePrivacyOfficerReturn {
  /** Compliance KPIs (training completion, consent compliance, breach count, overall score) */
  complianceKPIs: ComplianceKPIs | null;
  /** List of PHI access logs */
  phiAccessLogs: AccessLog[];
  /** List of consent status records */
  consentStatus: ConsentStatus[];
  /** List of regulatory updates */
  regulatoryUpdates: RegulatoryUpdate[];
  /** Training compliance data */
  trainingCompliance: TrainingCompliance | null;
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
 * Return type for the compliance KPIs hook
 */
interface UseComplianceKPIsReturn {
  /** Compliance KPIs */
  complianceKPIs: ComplianceKPIs | null;
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch KPIs */
  refetch: () => Promise<void>;
}

/**
 * Return type for the PHI access logs hook
 */
interface UsePHIAccessLogsReturn {
  /** List of PHI access logs */
  accessLogs: AccessLog[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch access logs */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the consent status hook
 */
interface UseConsentStatusReturn {
  /** List of consent status records */
  consentStatus: ConsentStatus[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch consent status */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the regulatory updates hook
 */
interface UseRegulatoryUpdatesReturn {
  /** List of regulatory updates */
  regulatoryUpdates: RegulatoryUpdate[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch regulatory updates */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the breach incidents hook
 */
interface UseBreachIncidentsReturn {
  /** List of breach incidents */
  breachIncidents: BreachIncident[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch breach incidents */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

/**
 * Return type for the training compliance hook
 */
interface UseTrainingComplianceReturn {
  /** Training statistics */
  trainingStats: TrainingStats | null;
  /** Training records */
  trainingRecords: TrainingRecord[];
  /** Loading state */
  loading: boolean;
  /** Error message if any */
  error: string | null;
  /** Refetch training compliance */
  refetch: () => Promise<void>;
  /** Set the limit for results */
  setLimit: (limit: number) => void;
}

// ============================================================================
// usePrivacyOfficer Hook (Main Dashboard Hook)
// ============================================================================

/**
 * Main hook for privacy officer dashboard data
 * 
 * Fetches complete dashboard data on mount and provides refetch capability.
 * 
 * @returns Privacy officer dashboard state and operations
 * 
 * @example
 * ```typescript
 * function PrivacyOfficerDashboard() {
 *   const { complianceKPIs, phiAccessLogs, regulatoryUpdates, loading, error, refetch } = usePrivacyOfficer();
 *   
 *   if (loading) return <LoadingSpinner />;
 *   if (error) return <ErrorMessage message={error} onRetry={refetch} />;
 *   
 *   return (
 *     <div>
 *       <ComplianceKPIsDisplay kpis={complianceKPIs} />
 *       <AccessLogsList logs={phiAccessLogs} />
 *       <button onClick={refetch}>Refresh</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function usePrivacyOfficer(): UsePrivacyOfficerReturn {
  const [complianceKPIs, setComplianceKPIs] = useState<ComplianceKPIs | null>(null);
  const [phiAccessLogs, setPhiAccessLogs] = useState<AccessLog[]>([]);
  const [consentStatus, setConsentStatus] = useState<ConsentStatus[]>([]);
  const [regulatoryUpdates, setRegulatoryUpdates] = useState<RegulatoryUpdate[]>([]);
  const [trainingCompliance, setTrainingCompliance] = useState<TrainingCompliance | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getDashboardData();
      setComplianceKPIs(data.complianceKPIs);
      setPhiAccessLogs(data.phiAccessLogs);
      setConsentStatus(data.consentStatus);
      setRegulatoryUpdates(data.regulatoryUpdates);
      setTrainingCompliance(data.trainingCompliance);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load privacy officer dashboard';
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
    complianceKPIs,
    phiAccessLogs,
    consentStatus,
    regulatoryUpdates,
    trainingCompliance,
    loading,
    error,
    refetch: fetchDashboard,
    clearError,
  };
}

// ============================================================================
// useComplianceKPIs Hook
// ============================================================================

/**
 * Hook for compliance KPIs only
 * 
 * Useful for displaying just the compliance metrics without the full data lists.
 * 
 * @returns Compliance KPIs state and operations
 * 
 * @example
 * ```typescript
 * function ComplianceStatusBar() {
 *   const { complianceKPIs, loading } = useComplianceKPIs();
 *   
 *   if (!complianceKPIs) return null;
 *   
 *   return (
 *     <div>
 *       Training: {complianceKPIs.trainingCompletion}% | 
 *       Consent: {complianceKPIs.consentCompliance}% | 
 *       Score: {complianceKPIs.overallScore}%
 *     </div>
 *   );
 * }
 * ```
 */
export function useComplianceKPIs(): UseComplianceKPIsReturn {
  const [complianceKPIs, setComplianceKPIs] = useState<ComplianceKPIs | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchKPIs = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const kpis = await getComplianceKPIs();
      setComplianceKPIs(kpis);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load compliance KPIs';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchKPIs();
  }, [fetchKPIs]);

  return {
    complianceKPIs,
    loading,
    error,
    refetch: fetchKPIs,
  };
}

// ============================================================================
// usePHIAccessLogs Hook
// ============================================================================

/**
 * Hook for PHI access logs
 * 
 * Fetches recent PHI access events from the audit log.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns PHI access logs state and operations
 * 
 * @example
 * ```typescript
 * function AccessLogsTable() {
 *   const { accessLogs, loading, setLimit } = usePHIAccessLogs(50);
 *   
 *   return (
 *     <div>
 *       {accessLogs.map(log => (
 *         <AccessLogRow key={log.id} log={log} />
 *       ))}
 *       <button onClick={() => setLimit(100)}>Show More</button>
 *     </div>
 *   );
 * }
 * ```
 */
export function usePHIAccessLogs(initialLimit = 20): UsePHIAccessLogsReturn {
  const [accessLogs, setAccessLogs] = useState<AccessLog[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchLogs = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const logs = await getPHIAccessLogs(limit);
      setAccessLogs(logs);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load PHI access logs';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    accessLogs,
    loading,
    error,
    refetch: fetchLogs,
    setLimit,
  };
}

// ============================================================================
// useConsentStatus Hook
// ============================================================================

/**
 * Hook for patient consent status
 * 
 * Fetches patient consent records with their current status.
 * 
 * @param initialLimit - Initial limit for results (default: 20)
 * @returns Consent status state and operations
 * 
 * @example
 * ```typescript
 * function ConsentStatusList() {
 *   const { consentStatus, loading } = useConsentStatus(20);
 *   
 *   const pending = consentStatus.filter(c => c.status === 'pending');
 *   
 *   return (
 *     <div>
 *       <h3>Pending Consents: {pending.length}</h3>
 *       {consentStatus.map(c => (
 *         <ConsentRow key={c.consentId} consent={c} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useConsentStatus(initialLimit = 20): UseConsentStatusReturn {
  const [consentStatus, setConsentStatus] = useState<ConsentStatus[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchConsents = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const consents = await getConsentStatus(limit);
      setConsentStatus(consents);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load consent status';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchConsents();
  }, [fetchConsents]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    consentStatus,
    loading,
    error,
    refetch: fetchConsents,
    setLimit,
  };
}

// ============================================================================
// useRegulatoryUpdates Hook
// ============================================================================

/**
 * Hook for regulatory updates
 * 
 * Fetches pending HIPAA/regulatory updates that need attention.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Regulatory updates state and operations
 * 
 * @example
 * ```typescript
 * function RegulatoryUpdatesList() {
 *   const { regulatoryUpdates, loading, refetch } = useRegulatoryUpdates(10);
 *   
 *   return (
 *     <div>
 *       {regulatoryUpdates.map(update => (
 *         <UpdateCard key={update.id} update={update} onProcess={() => refetch()} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useRegulatoryUpdates(initialLimit = 10): UseRegulatoryUpdatesReturn {
  const [regulatoryUpdates, setRegulatoryUpdates] = useState<RegulatoryUpdate[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchUpdates = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const updates = await getRegulatoryUpdates(limit);
      setRegulatoryUpdates(updates);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load regulatory updates';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchUpdates();
  }, [fetchUpdates]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    regulatoryUpdates,
    loading,
    error,
    refetch: fetchUpdates,
    setLimit,
  };
}

// ============================================================================
// useBreachIncidents Hook
// ============================================================================

/**
 * Hook for breach incidents
 * 
 * Fetches security breach incident records.
 * 
 * @param initialLimit - Initial limit for results (default: 10)
 * @returns Breach incidents state and operations
 * 
 * @example
 * ```typescript
 * function BreachIncidentsList() {
 *   const { breachIncidents, loading } = useBreachIncidents(10);
 *   
 *   const openIncidents = breachIncidents.filter(i => i.status === 'open');
 *   
 *   return (
 *     <div>
 *       <h3>Open Incidents: {openIncidents.length}</h3>
 *       {breachIncidents.map(incident => (
 *         <IncidentCard key={incident.id} incident={incident} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useBreachIncidents(initialLimit = 10): UseBreachIncidentsReturn {
  const [breachIncidents, setBreachIncidents] = useState<BreachIncident[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchIncidents = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const incidents = await getBreachIncidents(limit);
      setBreachIncidents(incidents);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load breach incidents';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchIncidents();
  }, [fetchIncidents]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 50));
  }, []);

  return {
    breachIncidents,
    loading,
    error,
    refetch: fetchIncidents,
    setLimit,
  };
}

// ============================================================================
// useTrainingCompliance Hook
// ============================================================================

/**
 * Hook for training compliance data
 * 
 * Fetches staff HIPAA training compliance status and records.
 * 
 * @param initialLimit - Initial limit for records (default: 20)
 * @returns Training compliance state and operations
 * 
 * @example
 * ```typescript
 * function TrainingCompliancePanel() {
 *   const { trainingStats, trainingRecords, loading } = useTrainingCompliance(20);
 *   
 *   if (!trainingStats) return null;
 *   
 *   return (
 *     <div>
 *       <p>Compliant: {trainingStats.compliant}/{trainingStats.total}</p>
 *       <p>Expiring Soon: {trainingStats.expiringSoon}</p>
 *       <p>Overdue: {trainingStats.overdue}</p>
 *       {trainingRecords.map(record => (
 *         <TrainingRecordRow key={record.recordId} record={record} />
 *       ))}
 *     </div>
 *   );
 * }
 * ```
 */
export function useTrainingCompliance(initialLimit = 20): UseTrainingComplianceReturn {
  const [trainingStats, setTrainingStats] = useState<TrainingStats | null>(null);
  const [trainingRecords, setTrainingRecords] = useState<TrainingRecord[]>([]);
  const [limit, setLimitState] = useState(initialLimit);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchTraining = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getTrainingCompliance(limit);
      setTrainingStats(data.stats);
      setTrainingRecords(data.records);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to load training compliance';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [limit]);

  useEffect(() => {
    fetchTraining();
  }, [fetchTraining]);

  const setLimit = useCallback((newLimit: number) => {
    setLimitState(Math.min(Math.max(1, newLimit), 100));
  }, []);

  return {
    trainingStats,
    trainingRecords,
    loading,
    error,
    refetch: fetchTraining,
    setLimit,
  };
}

// ============================================================================
// Re-export types for convenience
// ============================================================================

export type {
  ComplianceKPIs,
  AccessLog,
  ConsentStatus,
  RegulatoryUpdate,
  BreachIncident,
  TrainingStats,
  TrainingRecord,
  TrainingCompliance,
  PrivacyOfficerDashboardData,
} from '../services/privacy.service.js';

// ============================================================================
// Export Default
// ============================================================================

export default {
  usePrivacyOfficer,
  useComplianceKPIs,
  usePHIAccessLogs,
  useConsentStatus,
  useRegulatoryUpdates,
  useBreachIncidents,
  useTrainingCompliance,
};
