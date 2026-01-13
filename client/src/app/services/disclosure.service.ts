/**
 * Disclosure Service for SafeShift EHR
 * 
 * Handles disclosure templates and acknowledgment records for patient signatures.
 * Disclosures are required before patients can sign encounter reports.
 * 
 * TODO: LEGAL REVIEW REQUIRED - The disclosure text content should be reviewed
 * by legal counsel before production deployment.
 */

import { get, post } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Disclosure template from the database
 */
export interface DisclosureTemplate {
  id: number;
  disclosure_type: string;
  title: string;
  content: string;
  version: string;
  requires_work_related: boolean;
  display_order: number;
}

/**
 * Disclosure acknowledgment record
 */
export interface DisclosureAcknowledgment {
  id: number;
  encounter_id: string;
  disclosure_type: string;
  disclosure_version: string;
  disclosure_text: string;
  acknowledged_at: string;
  acknowledged_by_patient: boolean;
  ip_address?: string;
}

/**
 * Request payload for recording a single disclosure acknowledgment
 */
export interface RecordDisclosureRequest {
  disclosure_type: string;
  disclosure_text: string;
  disclosure_version: string;
  acknowledged_by_patient?: boolean;
}

/**
 * Request payload for recording multiple disclosure acknowledgments at once
 */
export interface BatchDisclosureRequest {
  disclosures: RecordDisclosureRequest[];
}

/**
 * Response from recording a disclosure acknowledgment
 */
export interface RecordDisclosureResponse {
  id: number;
  encounter_id: string;
  disclosure_type: string;
  acknowledged_at: string;
}

/**
 * Response from batch recording disclosure acknowledgments
 */
export interface BatchDisclosureResponse {
  recorded: Array<{
    id: number;
    disclosure_type: string;
  }>;
  count: number;
  encounter_id: string;
}

// ============================================================================
// API Functions
// ============================================================================

/**
 * Get all active disclosure templates
 * 
 * @returns Promise resolving to array of disclosure templates
 */
export async function getDisclosureTemplates(): Promise<DisclosureTemplate[]> {
  const response = await get<DisclosureTemplate[]>('/disclosures/templates');
  return response.data ?? [];
}

/**
 * Get a specific disclosure template by type
 * 
 * @param type - The disclosure type to retrieve
 * @returns Promise resolving to the disclosure template
 */
export async function getDisclosureTemplateByType(
  type: string
): Promise<DisclosureTemplate | null> {
  try {
    const response = await get<DisclosureTemplate>(`/disclosures/templates/${type}`);
    return response.data ?? null;
  } catch {
    return null;
  }
}

/**
 * Get disclosure acknowledgments for an encounter
 * 
 * @param encounterId - The encounter ID to get disclosures for
 * @returns Promise resolving to array of acknowledgment records
 */
export async function getEncounterDisclosures(
  encounterId: string
): Promise<DisclosureAcknowledgment[]> {
  const response = await get<DisclosureAcknowledgment[]>(
    `/encounters/${encounterId}/disclosures`
  );
  return response.data ?? [];
}

/**
 * Record a single disclosure acknowledgment
 * 
 * @param encounterId - The encounter ID to record acknowledgment for
 * @param disclosure - The disclosure acknowledgment data
 * @returns Promise resolving to the recorded acknowledgment
 */
export async function recordDisclosureAcknowledgment(
  encounterId: string,
  disclosure: RecordDisclosureRequest
): Promise<RecordDisclosureResponse> {
  const response = await post<RecordDisclosureResponse, RecordDisclosureRequest>(
    `/encounters/${encounterId}/disclosures`,
    disclosure
  );
  return response.data!;
}

/**
 * Record multiple disclosure acknowledgments at once
 * 
 * This is useful when signing an encounter where all disclosures
 * need to be acknowledged simultaneously.
 * 
 * @param encounterId - The encounter ID to record acknowledgments for
 * @param disclosures - Array of disclosure acknowledgments
 * @returns Promise resolving to the batch response
 */
export async function recordBatchDisclosureAcknowledgments(
  encounterId: string,
  disclosures: RecordDisclosureRequest[]
): Promise<BatchDisclosureResponse> {
  const response = await post<BatchDisclosureResponse, BatchDisclosureRequest>(
    `/encounters/${encounterId}/disclosures/batch`,
    { disclosures }
  );
  return response.data!;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Filter disclosure templates to only show applicable ones based on
 * whether the encounter is work-related
 * 
 * @param templates - Array of all disclosure templates
 * @param isWorkRelated - Whether the encounter is work-related
 * @returns Filtered array of applicable templates
 */
export function filterApplicableDisclosures(
  templates: DisclosureTemplate[],
  isWorkRelated: boolean
): DisclosureTemplate[] {
  return templates.filter(
    (template) => !template.requires_work_related || isWorkRelated
  );
}

/**
 * Check if all required disclosures have been acknowledged
 * 
 * @param requiredTemplates - Array of required disclosure templates
 * @param acknowledgments - Record of disclosure type to boolean (acknowledged)
 * @returns True if all required disclosures are acknowledged
 */
export function areAllDisclosuresAcknowledged(
  requiredTemplates: DisclosureTemplate[],
  acknowledgments: Record<string, boolean>
): boolean {
  return requiredTemplates.every(
    (template) => acknowledgments[template.disclosure_type] === true
  );
}

/**
 * Convert templates and acknowledgment state to request format
 * for recording disclosures when signing
 * 
 * @param templates - Array of disclosure templates that were acknowledged
 * @returns Array of disclosure request objects ready for API
 */
export function prepareDisclosureRequests(
  templates: DisclosureTemplate[]
): RecordDisclosureRequest[] {
  return templates.map((template) => ({
    disclosure_type: template.disclosure_type,
    disclosure_text: template.content,
    disclosure_version: template.version,
    acknowledged_by_patient: true,
  }));
}

// ============================================================================
// Default Export
// ============================================================================

export default {
  getDisclosureTemplates,
  getDisclosureTemplateByType,
  getEncounterDisclosures,
  recordDisclosureAcknowledgment,
  recordBatchDisclosureAcknowledgments,
  filterApplicableDisclosures,
  areAllDisclosuresAcknowledged,
  prepareDisclosureRequests,
};
