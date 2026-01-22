/**
 * Encounter Service for SafeShift EHR
 *
 * Handles all encounter-related API calls including CRUD operations,
 * vitals recording, and encounter amendments.
 */

import { get, post, put } from './api.js';
import { validateEncounterId } from './validation.service.js';
import type {
  PaginatedResponse,
  EncounterFilters,
  CreateEncounterDTO,
  UpdateEncounterDTO,
  Vitals,
  VitalsDTO,
  AmendmentDTO,
  SavedDraftsResponse,
} from '../types/api.types.js';
import type { Encounter } from '../types/index.js';

// ============================================================================
// EHR Submission Logging Utilities
// ============================================================================

/**
 * Log levels for EHR submissions
 */
type EhrLogLevel = 'INFO' | 'WARNING' | 'ERROR' | 'DEBUG';

/**
 * Log an EHR submission event to console with structured format
 * Format: [TIMESTAMP] [LEVEL] [ENCOUNTER_ID] [ACTION] [STATUS] [DETAILS]
 */
function logEhrEvent(
  level: EhrLogLevel,
  encounterId: string | null,
  action: string,
  status: string,
  details?: Record<string, unknown>
): void {
  const timestamp = new Date().toISOString();
  const encounterIdStr = encounterId || 'N/A';
  
  const logMessage = `[EHR] [${timestamp}] [${level}] [${encounterIdStr}] [${action}] [${status}]`;
  const detailsStr = details ? JSON.stringify(details) : '';
  
  switch (level) {
    case 'ERROR':
      console.error(logMessage, detailsStr);
      break;
    case 'WARNING':
      console.warn(logMessage, detailsStr);
      break;
    case 'DEBUG':
      console.debug(logMessage, detailsStr);
      break;
    default:
      console.log(logMessage, detailsStr);
  }
}

/**
 * Log a successful EHR submission
 */
function logEhrSuccess(encounterId: string | null, action: string, details?: Record<string, unknown>): void {
  logEhrEvent('INFO', encounterId, action, 'SUCCESS', details);
}

/**
 * Log a failed EHR submission
 */
function logEhrError(encounterId: string | null, action: string, error: unknown): void {
  const errorMessage = error instanceof Error ? error.message : String(error);
  logEhrEvent('ERROR', encounterId, action, 'FAILED', { error: errorMessage });
}

/**
 * Log an EHR submission attempt
 */
function logEhrAttempt(encounterId: string | null, action: string, details?: Record<string, unknown>): void {
  logEhrEvent('DEBUG', encounterId, action, 'ATTEMPT', details);
}

// ============================================================================
// Encounter Endpoints
// ============================================================================

const ENCOUNTER_ENDPOINTS = {
  base: '/encounters',
  drafts: '/encounters/drafts',
  byId: (id: string) => `/encounters/${id}`,
  vitals: (id: string) => `/encounters/${id}/vitals`,
  amend: (id: string) => `/encounters/${id}/amend`,
  sign: (id: string) => `/encounters/${id}/sign`,
  submit: (id: string) => `/encounters/${id}/submit`,
  finalize: (id: string) => `/encounters/${id}/finalize`,
} as const;

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Convert filter object to URL query parameters
 */
function filtersToParams(
  filters?: EncounterFilters
): Record<string, string | number | boolean | undefined> | undefined {
  if (!filters) return undefined;
  
  return {
    patientId: filters.patientId,
    type: filters.type,
    status: filters.status,
    providerId: filters.providerId,
    dateFrom: filters.dateFrom,
    dateTo: filters.dateTo,
    oshaRecordable: filters.oshaRecordable,
    page: filters.page,
    limit: filters.limit,
    sortBy: filters.sortBy,
    sortOrder: filters.sortOrder,
  };
}

// ============================================================================
// Encounter Service
// ============================================================================

/**
 * Get a paginated list of encounters with optional filtering
 * 
 * @param filters - Optional filters to apply to the query
 * @returns Promise resolving to paginated encounter list
 * 
 * @example
 * ```typescript
 * // Get recent encounters
 * const encounters = await getEncounters();
 * 
 * // Get encounters for a specific patient
 * const patientEncounters = await getEncounters({
 *   patientId: 'pat-123',
 *   page: 1,
 *   limit: 20,
 * });
 * 
 * // Get OSHA recordable encounters
 * const oshaEncounters = await getEncounters({
 *   oshaRecordable: true,
 * });
 * ```
 */
export async function getEncounters(
  filters?: EncounterFilters
): Promise<PaginatedResponse<Encounter>> {
  const params = filtersToParams(filters);
  const response = await get<{
    encounters: Encounter[];
    pagination: {
      page: number;
      per_page: number;
      total: number;
      total_pages: number;
    };
  }>(ENCOUNTER_ENDPOINTS.base, params ? { params } : undefined);
  
  // Transform API response to expected PaginatedResponse format
  const data = response.data;
  return {
    data: data.encounters || [],
    total: data.pagination?.total || 0,
    page: data.pagination?.page || 1,
    limit: data.pagination?.per_page || 20,
    totalPages: data.pagination?.total_pages || 1,
  };
}

/**
 * Get a single encounter by ID
 *
 * @param id - The encounter's unique identifier
 * @returns Promise resolving to the encounter data
 * @throws ApiError if encounter is not found
 *
 * @example
 * ```typescript
 * const encounter = await getEncounter('enc-123');
 * console.log(`Encounter type: ${encounter.type}`);
 * ```
 */
export async function getEncounter(id: string): Promise<Encounter> {
  const response = await get<{ encounter: Encounter }>(ENCOUNTER_ENDPOINTS.byId(id));
  return response.data.encounter;
}

/**
 * Create a new encounter record
 * 
 * @param data - The encounter data to create
 * @returns Promise resolving to the created encounter
 * @throws ApiError if validation fails
 * 
 * @example
 * ```typescript
 * const newEncounter = await createEncounter({
 *   patientId: 'pat-123',
 *   type: 'drug-test',
 *   reportType: 'work-related',
 *   chiefComplaint: 'Pre-employment drug screening',
 * });
 * ```
 */
export async function createEncounter(data: CreateEncounterDTO): Promise<Encounter> {
  logEhrAttempt(null, 'CREATE_ENCOUNTER', { patientId: data.patientId, type: data.type });
  
  try {
    // Build payload with both camelCase and snake_case for backend compatibility
    const payload = {
      ...data,
      // Ensure patient_id is included (backend expects snake_case)
      patient_id: data.patientId || (data as any).patient_id,
    };
    
    console.log('[EHR] [CREATE_ENCOUNTER] Sending payload:', JSON.stringify(payload, null, 2));
    
    const response = await post<{ encounter?: Encounter; data?: { encounter?: Encounter } }, CreateEncounterDTO>(ENCOUNTER_ENDPOINTS.base, payload as CreateEncounterDTO);
    
    // Debug: Log raw response to understand structure
    console.log('[EHR] [CREATE_ENCOUNTER] Raw response:', JSON.stringify(response, null, 2));
    
    // Handle different possible response structures:
    // 1. response.data.encounter (direct)
    // 2. response.data.data.encounter (nested)
    // 3. response.data itself if it has encounter properties
    let encounter: Encounter | undefined;
    
    if (response.data?.encounter) {
      // Direct structure: { data: { encounter: {...} } }
      encounter = response.data.encounter;
    } else if ((response.data as any)?.data?.encounter) {
      // Nested structure: { data: { data: { encounter: {...} } } }
      encounter = (response.data as any).data.encounter;
    } else if (response.data && ('id' in (response.data as Record<string, unknown>) || 'encounter_id' in (response.data as Record<string, unknown>))) {
      // Response.data IS the encounter object itself
      encounter = response.data as unknown as Encounter;
    }
    
    if (!encounter) {
      console.error('[EHR] [CREATE_ENCOUNTER] Could not extract encounter from response:', response);
      throw new Error('Invalid API response: encounter data not found');
    }
    
    const encounterId = encounter.id || (encounter as any).encounter_id || null;
    
    logEhrSuccess(encounterId, 'CREATE_ENCOUNTER', {
      patientId: data.patientId,
      type: data.type,
    });
    
    console.log('[EHR] [CREATE_ENCOUNTER] Successfully created encounter:', encounterId);
    
    return encounter;
  } catch (error) {
    logEhrError(null, 'CREATE_ENCOUNTER', error);
    throw error;
  }
}

/**
 * Update an existing encounter record
 * 
 * @param id - The encounter's unique identifier
 * @param data - The encounter data to update (partial)
 * @returns Promise resolving to the updated encounter
 * @throws ApiError if encounter is not found, is locked, or validation fails
 * 
 * @example
 * ```typescript
 * const updated = await updateEncounter('enc-123', {
 *   status: 'in-progress',
 *   notes: 'Patient arrived for examination',
 * });
 * ```
 */
export async function updateEncounter(
  id: string,
  data: UpdateEncounterDTO
): Promise<Encounter> {
  const response = await put<{ encounter: Encounter }, UpdateEncounterDTO>(ENCOUNTER_ENDPOINTS.byId(id), data);
  return response.data.encounter;
}

/**
 * Get all vitals records for an encounter
 * 
 * @param id - The encounter's unique identifier
 * @returns Promise resolving to array of vitals records
 * 
 * @example
 * ```typescript
 * const vitals = await getEncounterVitals('enc-123');
 * const latestVitals = vitals[vitals.length - 1];
 * console.log(`Latest BP: ${latestVitals.bloodPressureSystolic}/${latestVitals.bloodPressureDiastolic}`);
 * ```
 */
export async function getEncounterVitals(id: string): Promise<Vitals[]> {
  const response = await get<{ vitals: Vitals[] }>(ENCOUNTER_ENDPOINTS.vitals(id));
  return response.data.vitals || [];
}

/**
 * Record new vitals for an encounter
 *
 * @param encounterId - The encounter's unique identifier
 * @param vitals - The vitals data to record
 * @returns Promise resolving to the created vitals record
 * @throws ApiError if encounter is not found or is locked
 *
 * @example
 * ```typescript
 * const newVitals = await recordVitals('enc-123', {
 *   temperature: 98.6,
 *   heartRate: 72,
 *   bloodPressureSystolic: 120,
 *   bloodPressureDiastolic: 80,
 *   oxygenSaturation: 98,
 *   respiratoryRate: 16,
 * });
 * ```
 */
export async function recordVitals(
  encounterId: string,
  vitals: VitalsDTO
): Promise<Vitals> {
  const response = await put<{ vitals: Vitals }, VitalsDTO>(
    ENCOUNTER_ENDPOINTS.vitals(encounterId),
    vitals
  );
  return response.data.vitals;
}

/**
 * Amend a signed/submitted encounter
 *
 * This creates an amendment record that preserves the original
 * data while recording the changes for HIPAA compliance.
 *
 * @param id - The encounter's unique identifier
 * @param amendment - The amendment data with reason and changes
 * @returns Promise resolving to the amended encounter
 * @throws ApiError if encounter cannot be amended
 *
 * @example
 * ```typescript
 * const amended = await amendEncounter('enc-123', {
 *   reason: 'Correcting transcription error',
 *   changes: {
 *     notes: 'Corrected medication dosage from 10mg to 100mg',
 *   },
 *   notes: 'Error identified during chart review',
 * });
 * ```
 */
export async function amendEncounter(
  id: string,
  amendment: AmendmentDTO
): Promise<Encounter> {
  const response = await put<{ encounter: Encounter }, AmendmentDTO>(
    ENCOUNTER_ENDPOINTS.amend(id),
    amendment
  );
  return response.data.encounter;
}

/**
 * Sign an encounter (provider attestation)
 *
 * This locks the encounter and creates an audit trail of the signature.
 *
 * @param id - The encounter's unique identifier
 * @returns Promise resolving to the signed encounter
 * @throws ApiError if encounter cannot be signed (incomplete, already signed, etc.)
 *
 * @example
 * ```typescript
 * const signed = await signEncounter('enc-123');
 * console.log(`Encounter signed, status: ${signed.status}`);
 * ```
 */
export async function signEncounter(id: string): Promise<Encounter> {
  logEhrAttempt(id, 'SIGN_ENCOUNTER');
  
  try {
    const response = await put<{ encounter: Encounter }>(ENCOUNTER_ENDPOINTS.sign(id), {});
    logEhrSuccess(id, 'SIGN_ENCOUNTER', { newStatus: response.data.encounter.status });
    return response.data.encounter;
  } catch (error) {
    logEhrError(id, 'SIGN_ENCOUNTER', error);
    throw error;
  }
}

/**
 * Submit an encounter for review/billing
 *
 * @param id - The encounter's unique identifier
 * @returns Promise resolving to the submitted encounter
 * @throws Error if encounter ID is invalid (empty, 'new', or temporary)
 * @throws ApiError if encounter cannot be submitted
 *
 * @example
 * ```typescript
 * const submitted = await submitEncounter('enc-123');
 * console.log(`Encounter submitted, status: ${submitted.status}`);
 * ```
 */
export async function submitEncounter(id: string): Promise<Encounter> {
  logEhrAttempt(id, 'SUBMIT_ENCOUNTER');
  
  // Validate encounter ID before making API call
  try {
    validateEncounterId(id, 'submission');
  } catch (error) {
    logEhrError(id, 'SUBMIT_ENCOUNTER', error);
    throw error;
  }
  
  try {
    const response = await put<{ encounter: Encounter }>(ENCOUNTER_ENDPOINTS.submit(id), {});
    logEhrSuccess(id, 'SUBMIT_ENCOUNTER', { newStatus: response.data.encounter.status });
    return response.data.encounter;
  } catch (error) {
    logEhrError(id, 'SUBMIT_ENCOUNTER', error);
    throw error;
  }
}

// ============================================================================
// Submit for Review Types and Functions
// ============================================================================

/**
 * API response format for submit for review
 */
export interface SubmitForReviewResponse {
  /** Whether the submission was successful */
  success: boolean;
  /** Response message */
  message: string;
  /** Validation errors (if any) */
  errors?: Record<string, string>;
  /** Error code (if any) */
  code?: string;
  /** The encounter data (if successful) */
  encounter?: Encounter;
}

/**
 * Submit an encounter for review with full data validation
 *
 * This submits the encounter data for server-side validation before
 * marking it as ready for review. Server will validate all required fields.
 *
 * @param id - The encounter's unique identifier (use 'new' for creating new encounters)
 * @param data - The complete encounter data to submit
 * @returns Promise resolving to the submission result
 * @throws Error if encounter ID is invalid (empty or temporary, but 'new' is allowed for creation)
 * @throws ApiError if submission fails due to network or server error
 *
 * @example
 * ```typescript
 * const result = await submitForReview('enc-123', encounterData);
 * if (result.success) {
 *   toast.success('Encounter submitted for review');
 * } else {
 *   // Handle validation errors
 *   console.log(result.errors);
 * }
 * ```
 */
export async function submitForReview(
  id: string,
  data: Record<string, unknown>
): Promise<SubmitForReviewResponse> {
  logEhrAttempt(id, 'SUBMIT_FOR_REVIEW');
  
  // Validate encounter ID - 'new' is explicitly allowed for new encounter creation
  // but empty strings and temp_ IDs are not allowed
  if (!id || typeof id !== 'string') {
    logEhrEvent('WARNING', id, 'SUBMIT_FOR_REVIEW', 'VALIDATION_FAILED', { reason: 'INVALID_ENCOUNTER_ID' });
    return {
      success: false,
      message: 'Encounter ID is required for submission. Please save the encounter first.',
      code: 'INVALID_ENCOUNTER_ID',
    };
  }
  
  const trimmedId = id.trim();
  if (trimmedId === '') {
    logEhrEvent('WARNING', id, 'SUBMIT_FOR_REVIEW', 'VALIDATION_FAILED', { reason: 'EMPTY_ENCOUNTER_ID' });
    return {
      success: false,
      message: 'Encounter ID is required for submission. Please save the encounter first.',
      code: 'INVALID_ENCOUNTER_ID',
    };
  }
  
  // temp_ IDs indicate offline-only data that hasn't been synced
  // These should not be submitted directly to the server
  if (trimmedId.startsWith('temp_')) {
    logEhrEvent('WARNING', id, 'SUBMIT_FOR_REVIEW', 'VALIDATION_FAILED', { reason: 'OFFLINE_ENCOUNTER' });
    return {
      success: false,
      message: 'Cannot submit an offline encounter directly. Please sync the encounter first.',
      code: 'OFFLINE_ENCOUNTER',
    };
  }
  
  try {
    const response = await put<SubmitForReviewResponse, Record<string, unknown>>(
      ENCOUNTER_ENDPOINTS.submit(id),
      data
    );
    logEhrSuccess(id, 'SUBMIT_FOR_REVIEW', { success: response.data.success });
    return {
      success: response.data.success ?? true,
      message: response.data.message ?? 'Encounter submitted for review',
      ...(response.data.encounter && { encounter: response.data.encounter }),
    } as SubmitForReviewResponse;
  } catch (error: unknown) {
    // Handle API error response with validation errors
    if (error && typeof error === 'object' && 'response' in error) {
      const axiosError = error as { response?: { data?: SubmitForReviewResponse; status?: number } };
      if (axiosError.response?.data) {
        logEhrEvent('WARNING', id, 'SUBMIT_FOR_REVIEW', 'VALIDATION_FAILED', {
          errors: Object.keys(axiosError.response.data.errors || {}),
          code: axiosError.response.data.code,
        });
        return {
          success: false,
          message: axiosError.response.data.message || 'Validation failed',
          ...(axiosError.response.data.errors && { errors: axiosError.response.data.errors }),
          ...(axiosError.response.data.code && { code: axiosError.response.data.code }),
        } as SubmitForReviewResponse;
      }
    }
    // Re-throw for other types of errors
    logEhrError(id, 'SUBMIT_FOR_REVIEW', error);
    throw error;
  }
}

// ============================================================================
// Finalize Encounter Types and Functions
// ============================================================================

/**
 * Result of finalizing an encounter
 */
export interface FinalizeEncounterResult {
  /** Whether the finalization was successful */
  success: boolean;
  /** The encounter ID that was finalized */
  encounter_id: string;
  /** New status of the encounter */
  status: string;
  /** When the encounter was finalized */
  finalized_at: string;
  /** User who finalized the encounter */
  finalized_by: number | string;
  /** Number of notification emails sent (if work-related) */
  emails_sent: number | undefined;
  /** List of (masked) email recipients */
  email_recipients: string[] | undefined;
  /** Status of email notifications */
  email_status: 'sent' | 'failed' | 'skipped' | 'not_required' | undefined;
  /** Reason if emails were not sent */
  email_reason: string | undefined;
  /** Error message if email failed */
  email_error: string | undefined;
  /** Any errors that occurred */
  errors?: string[];
}

/**
 * Finalize an encounter
 *
 * This marks the encounter as finalized and triggers work-related
 * incident notifications if the encounter is classified as work-related.
 *
 * @param id - The encounter's unique identifier
 * @returns Promise resolving to the finalization result
 * @throws ApiError if encounter cannot be finalized
 *
 * @example
 * ```typescript
 * const result = await finalizeEncounter('enc-123');
 * if (result.success) {
 *   console.log(`Encounter finalized. Emails sent: ${result.emails_sent}`);
 *   if (result.email_recipients?.length) {
 *     console.log(`Notified: ${result.email_recipients.join(', ')}`);
 *   }
 * }
 * ```
 */
export async function finalizeEncounter(id: string): Promise<FinalizeEncounterResult> {
  logEhrAttempt(id, 'FINALIZE_ENCOUNTER');
  
  try {
    const response = await put<{
      data: FinalizeEncounterResult;
      success: boolean;
      message: string;
    }>(ENCOUNTER_ENDPOINTS.finalize(id), {});
    
    const result = {
      success: response.data.success,
      encounter_id: response.data.data.encounter_id,
      status: response.data.data.status,
      finalized_at: response.data.data.finalized_at,
      finalized_by: response.data.data.finalized_by,
      emails_sent: response.data.data.emails_sent,
      email_recipients: response.data.data.email_recipients,
      email_status: response.data.data.email_status,
      email_reason: response.data.data.email_reason,
      email_error: response.data.data.email_error,
    };
    
    logEhrSuccess(id, 'FINALIZE_ENCOUNTER', {
      status: result.status,
      emailStatus: result.email_status,
      emailsSent: result.emails_sent,
    });
    
    return result;
  } catch (error) {
    logEhrError(id, 'FINALIZE_ENCOUNTER', error);
    throw error;
  }
}

// ============================================================================
// Saved Drafts Functions
// ============================================================================

/**
 * Get all draft encounters for the current user
 *
 * Returns a list of draft (in_progress) encounters that the current
 * user has created and can continue editing.
 *
 * @param limit - Maximum number of drafts to return (default 10)
 * @returns Promise resolving to saved drafts response
 *
 * @example
 * ```typescript
 * const draftsResponse = await getDrafts();
 * console.log(`You have ${draftsResponse.count} saved drafts`);
 * draftsResponse.drafts.forEach(draft => {
 *   console.log(`${draft.patient_display_name} - ${draft.modified_at}`);
 * });
 * ```
 */
export async function getDrafts(limit = 10): Promise<SavedDraftsResponse> {
  logEhrAttempt(null, 'GET_DRAFTS', { limit });
  
  try {
    const response = await get<{
      // Handle multiple possible response structures from backend
      drafts?: Array<Record<string, unknown>>;
      data?: Array<Record<string, unknown>>;
      items?: Array<Record<string, unknown>>;
      encounters?: Array<Record<string, unknown>>;
      count?: number;
      total?: number;
    }>(ENCOUNTER_ENDPOINTS.drafts, {
      params: { limit }
    });
    
    // DEBUG: Log the full response structure to diagnose the issue
    console.log('[getDrafts] Full API response:', JSON.stringify(response, null, 2));
    
    // The API response from backend wraps data in { success, status, data: {...} }
    // response IS the full ApiResponse, so response.data contains the actual payload
    // BUT if backend returns { data: { drafts: [...] } }, we need response.data
    // If backend returns { drafts: [...] } directly, we need response itself
    const rawData = response.data;
    
    console.log('[getDrafts] rawData (response.data):', JSON.stringify(rawData, null, 2));
    console.log('[getDrafts] response.drafts:', response.drafts);
    console.log('[getDrafts] rawData?.drafts:', rawData?.drafts);
    
    // Try multiple paths to find the drafts array:
    // 1. response.data.drafts (standard structure)
    // 2. response.drafts (if data is the response itself)
    // 3. Various fallbacks for different API response formats
    let draftsArray: Array<Record<string, unknown>> = [];
    
    if (rawData && typeof rawData === 'object') {
      // Standard case: response.data contains { drafts, count }
      draftsArray = (rawData as Record<string, unknown>).drafts as Array<Record<string, unknown>>
        ?? (rawData as Record<string, unknown>).data as Array<Record<string, unknown>>
        ?? (rawData as Record<string, unknown>).items as Array<Record<string, unknown>>
        ?? (rawData as Record<string, unknown>).encounters as Array<Record<string, unknown>>
        ?? [];
    } else if (response && typeof response === 'object') {
      // Fallback: try reading directly from response
      draftsArray = (response as Record<string, unknown>).drafts as Array<Record<string, unknown>>
        ?? (response as Record<string, unknown>).items as Array<Record<string, unknown>>
        ?? (response as Record<string, unknown>).encounters as Array<Record<string, unknown>>
        ?? [];
    }
    
    console.log('[getDrafts] Extracted draftsArray:', draftsArray);
    
    // Ensure array
    if (!Array.isArray(draftsArray)) {
      draftsArray = [];
    }
    
    // Transform to expected SavedDraft format with all required fields
    const normalizedDrafts: SavedDraftsResponse['drafts'] = draftsArray.map((draft) => ({
      encounter_id: String(draft.encounter_id ?? draft.id ?? ''),
      patient_id: draft.patient_id != null ? String(draft.patient_id) : null,
      patient_display_name: String(draft.patient_display_name ?? draft.patient_name ?? 'Unknown Patient'),
      chief_complaint: draft.chief_complaint != null ? String(draft.chief_complaint) : null,
      encounter_type: String(draft.encounter_type ?? draft.type ?? 'clinic'),
      created_at: String(draft.created_at ?? new Date().toISOString()),
      modified_at: String(draft.modified_at ?? draft.updated_at ?? draft.created_at ?? new Date().toISOString()),
    }));
    
    const count = rawData.count ?? rawData.total ?? normalizedDrafts.length;
    
    logEhrSuccess(null, 'GET_DRAFTS', { count });
    
    return {
      drafts: normalizedDrafts,
      count,
    };
  } catch (error) {
    logEhrError(null, 'GET_DRAFTS', error);
    throw error;
  }
}

// ============================================================================
// Export as Namespace Object
// ============================================================================

/**
 * Encounter service object containing all encounter-related methods
 */
export const encounterService = {
  getEncounters,
  getEncounter,
  createEncounter,
  updateEncounter,
  getEncounterVitals,
  recordVitals,
  amendEncounter,
  signEncounter,
  submitEncounter,
  submitForReview,
  finalizeEncounter,
  getDrafts,
} as const;

export default encounterService;
