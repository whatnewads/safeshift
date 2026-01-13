/**
 * Patient Service for SafeShift EHR
 * 
 * Handles all patient-related API calls including CRUD operations,
 * search functionality, and related data retrieval.
 */

import { get, post, put, del } from './api.js';
import type {
  PaginatedResponse,
  PatientFilters,
  CreatePatientDTO,
  UpdatePatientDTO,
} from '../types/api.types.js';
import type { Patient, Encounter } from '../types/index.js';

// ============================================================================
// Patient Endpoints
// ============================================================================

const PATIENT_ENDPOINTS = {
  base: '/patients',
  byId: (id: string) => `/patients/${id}`,
  search: '/patients/search',
  encounters: (patientId: string) => `/patients/${patientId}/encounters`,
} as const;

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Convert filter object to URL query parameters
 */
function filtersToParams(
  filters?: PatientFilters
): Record<string, string | number | boolean | undefined> | undefined {
  if (!filters) return undefined;
  
  return {
    search: filters.search,
    employerId: filters.employerId,
    jobSite: filters.jobSite,
    active: filters.active,
    page: filters.page,
    limit: filters.limit,
    sortBy: filters.sortBy,
    sortOrder: filters.sortOrder,
  };
}

// ============================================================================
// Patient Service
// ============================================================================

/**
 * Get a paginated list of patients with optional filtering
 * 
 * @param filters - Optional filters to apply to the query
 * @returns Promise resolving to paginated patient list
 * 
 * @example
 * ```typescript
 * // Get first page of patients
 * const patients = await getPatients();
 * 
 * // Get patients for a specific employer
 * const filtered = await getPatients({
 *   employerId: 'emp-123',
 *   page: 1,
 *   limit: 20,
 * });
 * ```
 */
export async function getPatients(
  filters?: PatientFilters
): Promise<PaginatedResponse<Patient>> {
  const params = filtersToParams(filters);
  const response = await get<PaginatedResponse<Patient>>(
    PATIENT_ENDPOINTS.base,
    params ? { params } : undefined
  );
  return response.data;
}

/**
 * Get a single patient by ID
 * 
 * @param id - The patient's unique identifier
 * @returns Promise resolving to the patient data
 * @throws ApiError if patient is not found
 * 
 * @example
 * ```typescript
 * const patient = await getPatient('pat-123');
 * console.log(`${patient.firstName} ${patient.lastName}`);
 * ```
 */
export async function getPatient(id: string): Promise<Patient> {
  const response = await get<Patient>(PATIENT_ENDPOINTS.byId(id));
  return response.data;
}

/**
 * Create a new patient record
 * 
 * @param data - The patient data to create
 * @returns Promise resolving to the created patient
 * @throws ApiError if validation fails
 * 
 * @example
 * ```typescript
 * const newPatient = await createPatient({
 *   firstName: 'John',
 *   lastName: 'Doe',
 *   dateOfBirth: '1990-01-15',
 *   employerId: 'emp-123',
 * });
 * ```
 */
export async function createPatient(data: CreatePatientDTO): Promise<Patient> {
  const response = await post<Patient, CreatePatientDTO>(PATIENT_ENDPOINTS.base, data);
  return response.data;
}

/**
 * Update an existing patient record
 * 
 * @param id - The patient's unique identifier
 * @param data - The patient data to update (partial)
 * @returns Promise resolving to the updated patient
 * @throws ApiError if patient is not found or validation fails
 * 
 * @example
 * ```typescript
 * const updated = await updatePatient('pat-123', {
 *   phone: '555-1234',
 *   address: '123 Main St',
 * });
 * ```
 */
export async function updatePatient(
  id: string,
  data: UpdatePatientDTO
): Promise<Patient> {
  const response = await put<Patient, UpdatePatientDTO>(PATIENT_ENDPOINTS.byId(id), data);
  return response.data;
}

/**
 * Delete a patient record
 * 
 * Note: In most healthcare systems, this performs a soft delete
 * to maintain audit trail and HIPAA compliance.
 * 
 * @param id - The patient's unique identifier
 * @returns Promise that resolves when deletion is complete
 * @throws ApiError if patient is not found or deletion is not allowed
 * 
 * @example
 * ```typescript
 * await deletePatient('pat-123');
 * ```
 */
export async function deletePatient(id: string): Promise<void> {
  await del<void>(PATIENT_ENDPOINTS.byId(id));
}

/**
 * Search for patients by name, MRN, or other criteria
 * 
 * This is optimized for quick searches and autocomplete functionality.
 * For more complex filtering, use getPatients with filters.
 * 
 * @param query - The search query string
 * @returns Promise resolving to an array of matching patients
 * 
 * @example
 * ```typescript
 * // Search by name
 * const results = await searchPatients('John');
 * 
 * // Search by MRN
 * const byMrn = await searchPatients('MRN-12345');
 * ```
 */
export async function searchPatients(query: string): Promise<Patient[]> {
  const response = await get<Patient[]>(PATIENT_ENDPOINTS.search, {
    params: { q: query },
  });
  return response.data;
}

/**
 * Get all encounters for a specific patient
 * 
 * @param patientId - The patient's unique identifier
 * @returns Promise resolving to array of patient's encounters
 * 
 * @example
 * ```typescript
 * const encounters = await getPatientEncounters('pat-123');
 * const recentEncounters = encounters.filter(e => 
 *   new Date(e.createdAt) > oneWeekAgo
 * );
 * ```
 */
export async function getPatientEncounters(patientId: string): Promise<Encounter[]> {
  const response = await get<Encounter[]>(PATIENT_ENDPOINTS.encounters(patientId));
  return response.data;
}

// ============================================================================
// Export as Namespace Object
// ============================================================================

/**
 * Patient service object containing all patient-related methods
 */
export const patientService = {
  getPatients,
  getPatient,
  createPatient,
  updatePatient,
  deletePatient,
  searchPatients,
  getPatientEncounters,
} as const;

export default patientService;
