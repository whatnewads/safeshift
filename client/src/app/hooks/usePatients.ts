/**
 * Patient Data Hook for SafeShift EHR
 * 
 * Provides React hooks for managing patient data including
 * fetching, creating, updating, deleting, and searching patients.
 */

import { useState, useCallback, useEffect } from 'react';
import { patientService, getErrorMessage } from '../services/index.js';
import type { PatientFilters, CreatePatientDTO, UpdatePatientDTO } from '../types/api.types.js';
import type { Patient, Encounter } from '../types/index.js';

// ============================================================================
// Types
// ============================================================================

interface PaginationState {
  total: number;
  page: number;
  limit: number;
  totalPages: number;
}

interface UsePatientsReturn {
  patients: Patient[];
  loading: boolean;
  error: string | null;
  pagination: PaginationState;
  fetchPatients: (filters?: PatientFilters) => Promise<void>;
  searchPatients: (query: string) => Promise<Patient[]>;
  createPatient: (data: CreatePatientDTO) => Promise<Patient | null>;
  updatePatient: (id: string, data: UpdatePatientDTO) => Promise<Patient | null>;
  deletePatient: (id: string) => Promise<boolean>;
  refetch: (filters?: PatientFilters) => Promise<void>;
  clearError: () => void;
}

interface UsePatientReturn {
  patient: Patient | null;
  encounters: Encounter[];
  loading: boolean;
  error: string | null;
  fetchPatient: () => Promise<void>;
  updatePatient: (data: UpdatePatientDTO) => Promise<Patient | null>;
  fetchEncounters: () => Promise<void>;
  refetch: () => Promise<void>;
  clearError: () => void;
}

// ============================================================================
// usePatients Hook - List/Search/CRUD for Patients
// ============================================================================

/**
 * Hook for managing patient list data and operations
 * @returns Patient list state and operations
 */
export function usePatients(): UsePatientsReturn {
  const [patients, setPatients] = useState<Patient[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState<PaginationState>({ 
    total: 0, 
    page: 1, 
    limit: 20,
    totalPages: 0
  });

  /**
   * Fetch patients with optional filters
   */
  const fetchPatients = useCallback(async (filters?: PatientFilters) => {
    setLoading(true);
    setError(null);
    try {
      // patientService.getPatients returns PaginatedResponse<Patient> directly
      const paginatedData = await patientService.getPatients(filters);
      
      // Defensive: ensure we always have an array, even if API returns unexpected structure
      const patientArray = Array.isArray(paginatedData?.data)
        ? paginatedData.data
        : [];
      
      setPatients(patientArray);
      setPagination({
        total: paginatedData?.total ?? patientArray.length,
        page: paginatedData?.page ?? 1,
        limit: paginatedData?.limit ?? 20,
        totalPages: paginatedData?.totalPages ?? Math.ceil((paginatedData?.total ?? patientArray.length) / (paginatedData?.limit ?? 20)),
      });
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch patients'));
      // Ensure patients is always an array even on error
      setPatients([]);
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Quick search for autocomplete
   */
  const searchPatients = useCallback(async (query: string): Promise<Patient[]> => {
    if (!query || query.length < 2) {
      return [];
    }
    
    try {
      // patientService.searchPatients returns Patient[] directly
      const results = await patientService.searchPatients(query);
      return results;
    } catch (err: unknown) {
      console.error('Patient search error:', getErrorMessage(err));
      return [];
    }
  }, []);

  /**
   * Create a new patient
   */
  const createPatient = useCallback(async (data: CreatePatientDTO): Promise<Patient | null> => {
    setLoading(true);
    setError(null);
    try {
      // patientService.createPatient returns Patient directly
      const newPatient = await patientService.createPatient(data);
      // Add to list if we have patients loaded
      setPatients(prev => [newPatient, ...prev]);
      setPagination(prev => ({ ...prev, total: prev.total + 1 }));
      return newPatient;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to create patient'));
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Update an existing patient
   */
  const updatePatient = useCallback(async (id: string, data: UpdatePatientDTO): Promise<Patient | null> => {
    setLoading(true);
    setError(null);
    try {
      // patientService.updatePatient returns Patient directly
      const updatedPatient = await patientService.updatePatient(id, data);
      // Update in list
      setPatients(prev => prev.map(p => p.id === id ? updatedPatient : p));
      return updatedPatient;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to update patient'));
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  /**
   * Soft delete a patient
   */
  const deletePatient = useCallback(async (id: string): Promise<boolean> => {
    setLoading(true);
    setError(null);
    try {
      // patientService.deletePatient returns void
      await patientService.deletePatient(id);
      // Remove from list
      setPatients(prev => prev.filter(p => p.id !== id));
      setPagination(prev => ({ ...prev, total: Math.max(0, prev.total - 1) }));
      return true;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to delete patient'));
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

  return {
    patients,
    loading,
    error,
    pagination,
    fetchPatients,
    searchPatients,
    createPatient,
    updatePatient,
    deletePatient,
    refetch: fetchPatients,
    clearError,
  };
}

// ============================================================================
// usePatient Hook - Single Patient Management
// ============================================================================

/**
 * Hook for managing a single patient's data
 * @param patientId - The patient ID to manage
 * @returns Single patient state and operations
 */
export function usePatient(patientId: string): UsePatientReturn {
  const [patient, setPatient] = useState<Patient | null>(null);
  const [encounters, setEncounters] = useState<Encounter[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Fetch the patient data
   */
  const fetchPatient = useCallback(async () => {
    if (!patientId) {
      setError('Patient ID is required');
      return;
    }

    setLoading(true);
    setError(null);
    try {
      // patientService.getPatient returns Patient directly
      const patientData = await patientService.getPatient(patientId);
      setPatient(patientData);
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to fetch patient'));
    } finally {
      setLoading(false);
    }
  }, [patientId]);

  /**
   * Fetch patient's encounters
   */
  const fetchEncounters = useCallback(async () => {
    if (!patientId) return;

    try {
      // patientService.getPatientEncounters returns Encounter[] directly
      const encounterData = await patientService.getPatientEncounters(patientId);
      setEncounters(encounterData);
    } catch (err: unknown) {
      console.error('Failed to fetch patient encounters:', getErrorMessage(err));
    }
  }, [patientId]);

  /**
   * Update the patient
   */
  const updatePatient = useCallback(async (data: UpdatePatientDTO): Promise<Patient | null> => {
    if (!patientId) {
      setError('Patient ID is required');
      return null;
    }

    setLoading(true);
    setError(null);
    try {
      // patientService.updatePatient returns Patient directly
      const updatedPatient = await patientService.updatePatient(patientId, data);
      setPatient(updatedPatient);
      return updatedPatient;
    } catch (err: unknown) {
      setError(getErrorMessage(err, 'Failed to update patient'));
      return null;
    } finally {
      setLoading(false);
    }
  }, [patientId]);

  /**
   * Clear error state
   */
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  // Fetch patient on mount or when ID changes
  useEffect(() => {
    if (patientId) {
      fetchPatient();
    }
  }, [patientId, fetchPatient]);

  return {
    patient,
    encounters,
    loading,
    error,
    fetchPatient,
    updatePatient,
    fetchEncounters,
    refetch: fetchPatient,
    clearError,
  };
}

export default usePatients;
