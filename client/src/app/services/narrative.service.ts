/**
 * Narrative Service for SafeShift EHR
 *
 * Handles AI-generated narrative generation for encounters.
 * Calls the backend API endpoint to generate clinical narratives.
 */

import { post, getErrorMessage } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Response from the narrative generation API
 */
export interface NarrativeResponse {
  /** Whether the generation was successful */
  success: boolean;
  /** The generated narrative text */
  narrative: string;
  /** The encounter ID the narrative was generated for */
  encounter_id: string;
  /** ISO timestamp when the narrative was generated */
  generated_at: string;
  /** HTTP status code */
  status: number;
  /** Error message if generation failed */
  error?: string;
}

// ============================================================================
// Narrative Endpoints
// ============================================================================

const NARRATIVE_ENDPOINTS = {
  generate: (encounterId: string) => `/encounters/${encounterId}/generate-narrative`,
} as const;

// ============================================================================
// Narrative Service Functions
// ============================================================================

/**
 * Generate a clinical narrative for an encounter using AI
 *
 * This calls the backend API endpoint that uses LLM to generate
 * a professional clinical narrative based on the encounter data.
 *
 * @param encounterId - The encounter's unique identifier
 * @returns Promise resolving to the narrative response
 * @throws ApiError if generation fails
 *
 * @example
 * ```typescript
 * const response = await generateNarrative('enc-123');
 * if (response.success) {
 *   console.log('Generated narrative:', response.narrative);
 * } else {
 *   console.error('Failed:', response.error);
 * }
 * ```
 */
export async function generateNarrative(encounterId: string): Promise<NarrativeResponse> {
  if (!encounterId || encounterId.trim() === '') {
    return {
      success: false,
      narrative: '',
      encounter_id: encounterId || '',
      generated_at: new Date().toISOString(),
      status: 400,
      error: 'Encounter ID is required',
    };
  }

  try {
    const response = await post<NarrativeResponse>(
      NARRATIVE_ENDPOINTS.generate(encounterId),
      {}
    );
    
    // Handle wrapped response structure
    const data = response.data;
    
    const result: NarrativeResponse = {
      success: data.success ?? true,
      narrative: data.narrative ?? '',
      encounter_id: data.encounter_id ?? encounterId,
      generated_at: data.generated_at ?? new Date().toISOString(),
      status: data.status ?? 200,
    };
    
    if (data.error) {
      result.error = data.error;
    }
    
    return result;
  } catch (error: unknown) {
    // Extract error message from various error formats
    const errorMessage = getErrorMessage(error, 'Failed to generate narrative');
    
    return {
      success: false,
      narrative: '',
      encounter_id: encounterId,
      generated_at: new Date().toISOString(),
      status: 500,
      error: errorMessage,
    };
  }
}

// ============================================================================
// Export as Namespace Object
// ============================================================================

/**
 * Narrative service object containing all narrative-related methods
 */
export const narrativeService = {
  generateNarrative,
} as const;

export default narrativeService;
