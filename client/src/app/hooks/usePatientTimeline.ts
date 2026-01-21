/**
 * usePatientTimeline Hook
 * 
 * Custom hook for fetching and managing patient timeline data.
 * Provides loading, error, and data states for the patient timeline view.
 */

import { useState, useEffect, useCallback } from 'react';
import { patientService } from '../services/patient.service.js';
import type { 
  PatientTimelineResponse, 
  TimelineEncounter 
} from '../types/api.types.js';

/**
 * Return type for the usePatientTimeline hook
 */
interface UsePatientTimelineReturn {
  /** Patient information from the timeline */
  patient: PatientTimelineResponse['patient'] | null;
  /** Timeline summary data */
  timeline: PatientTimelineResponse['timeline'] | null;
  /** Array of encounters for the timeline */
  encounters: TimelineEncounter[];
  /** Whether data is currently being fetched */
  isLoading: boolean;
  /** Error message if fetch failed */
  error: string | null;
  /** Function to manually refetch timeline data */
  refetch: () => Promise<void>;
}

/**
 * Hook for fetching patient timeline data
 * 
 * @param patientId - The patient's unique identifier
 * @returns Object containing patient, timeline, encounters, loading state, error, and refetch function
 * 
 * @example
 * ```typescript
 * const { patient, timeline, encounters, isLoading, error, refetch } = usePatientTimeline('pat-123');
 * 
 * if (isLoading) return <Spinner />;
 * if (error) return <ErrorMessage message={error} onRetry={refetch} />;
 * 
 * return (
 *   <TimelineView 
 *     patient={patient} 
 *     encounters={encounters} 
 *   />
 * );
 * ```
 */
export function usePatientTimeline(patientId: string): UsePatientTimelineReturn {
  const [patient, setPatient] = useState<PatientTimelineResponse['patient'] | null>(null);
  const [timeline, setTimeline] = useState<PatientTimelineResponse['timeline'] | null>(null);
  const [encounters, setEncounters] = useState<TimelineEncounter[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  /**
   * Fetch timeline data from the API
   */
  const fetchTimeline = useCallback(async () => {
    if (!patientId) {
      setError('Patient ID is required');
      setIsLoading(false);
      return;
    }

    try {
      setIsLoading(true);
      setError(null);

      const data = await patientService.getPatientTimeline(patientId);
      
      setPatient(data.patient);
      setTimeline(data.timeline);
      setEncounters(data.encounters);
    } catch (err) {
      const errorMessage = err instanceof Error 
        ? err.message 
        : 'Failed to load patient timeline';
      setError(errorMessage);
      console.error('Error fetching patient timeline:', err);
    } finally {
      setIsLoading(false);
    }
  }, [patientId]);

  /**
   * Fetch timeline data on mount and when patientId changes
   */
  useEffect(() => {
    fetchTimeline();
  }, [fetchTimeline]);

  return {
    patient,
    timeline,
    encounters,
    isLoading,
    error,
    refetch: fetchTimeline,
  };
}

export default usePatientTimeline;
