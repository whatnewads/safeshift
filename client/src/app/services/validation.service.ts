/**
 * Validation Service for EHR Encounter Form
 *
 * This service provides client-side validation for the encounter form,
 * preventing submission of incomplete forms and providing detailed error information.
 */

import {
  REQUIRED_FIELDS_BY_TAB,
  calculateTabCompletion,
  type EncounterData,
  type TabRequiredFields,
  type RequiredField,
  type TabSection,
} from '../config/requiredFields.js';

// ============================================================================
// Encounter ID Validation Helpers
// ============================================================================

/**
 * UUID v4 format regex pattern
 * Matches: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
 */
const UUID_V4_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[4][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/**
 * Generic UUID format regex pattern (less strict, allows any version)
 * Matches: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
 */
const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/**
 * Check if a string is a valid UUID format
 *
 * @param value - The string to validate
 * @param strictV4 - If true, only matches UUID v4 format. Default: false
 * @returns true if the value is a valid UUID format
 *
 * @example
 * ```typescript
 * isValidUUID('550e8400-e29b-41d4-a716-446655440000'); // true
 * isValidUUID('invalid'); // false
 * isValidUUID(''); // false
 * isValidUUID('new'); // false
 * ```
 */
export function isValidUUID(value: string | null | undefined, strictV4: boolean = false): boolean {
  if (!value || typeof value !== 'string') {
    return false;
  }
  
  const pattern = strictV4 ? UUID_V4_REGEX : UUID_REGEX;
  return pattern.test(value);
}

/**
 * Check if an encounter ID is valid for API submission
 *
 * This validates that the ID:
 * - Is not empty, null, or undefined
 * - Is not the literal string 'new' (used for new encounter routes)
 * - Does not start with 'temp_' (used for offline temporary IDs)
 * - Optionally validates UUID format
 *
 * @param id - The encounter ID to validate
 * @param requireUUID - If true, also validates UUID format. Default: false
 * @returns true if the ID is valid for submission
 *
 * @example
 * ```typescript
 * isValidEncounterId('550e8400-e29b-41d4-a716-446655440000'); // true
 * isValidEncounterId(''); // false
 * isValidEncounterId('new'); // false
 * isValidEncounterId('temp_123'); // false
 * isValidEncounterId(null); // false
 * isValidEncounterId(undefined); // false
 * ```
 */
export function isValidEncounterId(id: string | null | undefined, requireUUID: boolean = false): boolean {
  // Check for null, undefined, or non-string
  if (!id || typeof id !== 'string') {
    return false;
  }
  
  // Trim and check for empty string
  const trimmedId = id.trim();
  if (trimmedId === '') {
    return false;
  }
  
  // Check for special reserved values
  if (trimmedId.toLowerCase() === 'new') {
    return false;
  }
  
  // Check for temporary/offline IDs
  if (trimmedId.startsWith('temp_')) {
    return false;
  }
  
  // Optionally validate UUID format
  if (requireUUID && !isValidUUID(trimmedId)) {
    return false;
  }
  
  return true;
}

/**
 * Validate an encounter ID and throw a descriptive error if invalid
 *
 * @param id - The encounter ID to validate
 * @param operation - Description of the operation (for error message)
 * @throws Error if the ID is invalid
 *
 * @example
 * ```typescript
 * validateEncounterId('', 'submission');
 * // throws: "Encounter ID is required for submission. Please save the encounter first."
 *
 * validateEncounterId('new', 'submission');
 * // throws: "Cannot submit an unsaved encounter. Please save the encounter first."
 *
 * validateEncounterId('temp_123', 'submission');
 * // throws: "Cannot submit an offline encounter directly. Please sync the encounter first."
 * ```
 */
export function validateEncounterId(id: string | null | undefined, operation: string = 'this operation'): void {
  // Check for null, undefined, or non-string
  if (!id || typeof id !== 'string') {
    throw new Error(`Encounter ID is required for ${operation}. Please save the encounter first.`);
  }
  
  // Trim and check for empty string
  const trimmedId = id.trim();
  if (trimmedId === '') {
    throw new Error(`Encounter ID is required for ${operation}. Please save the encounter first.`);
  }
  
  // Check for 'new' - indicates unsaved encounter
  if (trimmedId.toLowerCase() === 'new') {
    throw new Error(`Cannot perform ${operation} on an unsaved encounter. Please save the encounter first.`);
  }
  
  // Check for temporary/offline IDs
  if (trimmedId.startsWith('temp_')) {
    throw new Error(`Cannot perform ${operation} on an offline encounter directly. Please sync the encounter first.`);
  }
}

/**
 * Represents a single validation error
 */
export interface ValidationError {
  field: string;
  label: string;
  tabId: string;
  tabName: string;
  sectionName: string;
  message: string;
  elementId?: string | undefined;
}

/**
 * Result of a validation operation
 */
export interface ValidationResult {
  isValid: boolean;
  errors: ValidationError[];
  completionPercentage: number;
  completedFields: number;
  totalFields: number;
}

/**
 * Result of validating a single tab
 */
export interface TabValidationResult extends ValidationResult {
  tabId: string;
  tabName: string;
}

/**
 * Validates the entire encounter form data
 * 
 * @param encounterData - The encounter data to validate
 * @returns ValidationResult with all errors and completion status
 */
export function validateEncounter(encounterData: EncounterData): ValidationResult {
  const errors: ValidationError[] = [];
  let totalFields = 0;
  let completedFields = 0;

  REQUIRED_FIELDS_BY_TAB.forEach((tabConfig: TabRequiredFields) => {
    tabConfig.sections.forEach((section: TabSection) => {
      section.fields.forEach((field: RequiredField) => {
        // Check if the field is conditionally required
        const isRequired = field.conditionallyRequired
          ? field.conditionallyRequired(encounterData)
          : true;

        if (isRequired) {
          totalFields++;
          const isCompleted = field.isCompleted(encounterData);
          
          if (isCompleted) {
            completedFields++;
          } else {
            errors.push({
              field: field.name,
              label: field.label,
              tabId: tabConfig.tabId,
              tabName: tabConfig.tabName,
              sectionName: section.name,
              message: `${field.label} is required`,
              elementId: field.elementId,
            });
          }
        }
      });
    });
  });

  const completionPercentage = totalFields > 0 
    ? Math.round((completedFields / totalFields) * 100) 
    : 100;

  return {
    isValid: errors.length === 0,
    errors,
    completionPercentage,
    completedFields,
    totalFields,
  };
}

/**
 * Validates a specific tab
 * 
 * @param tabId - The ID of the tab to validate
 * @param encounterData - The encounter data to validate
 * @returns TabValidationResult with errors for the specific tab
 */
export function validateTab(tabId: string, encounterData: EncounterData): TabValidationResult {
  const tabConfig = REQUIRED_FIELDS_BY_TAB.find((tab: TabRequiredFields) => tab.tabId === tabId);
  
  if (!tabConfig) {
    return {
      isValid: true,
      errors: [],
      completionPercentage: 100,
      completedFields: 0,
      totalFields: 0,
      tabId,
      tabName: '',
    };
  }

  const errors: ValidationError[] = [];
  let totalFields = 0;
  let completedFields = 0;

  tabConfig.sections.forEach((section: TabSection) => {
    section.fields.forEach((field: RequiredField) => {
      // Check if the field is conditionally required
      const isRequired = field.conditionallyRequired
        ? field.conditionallyRequired(encounterData)
        : true;

      if (isRequired) {
        totalFields++;
        const isCompleted = field.isCompleted(encounterData);
        
        if (isCompleted) {
          completedFields++;
        } else {
          errors.push({
            field: field.name,
            label: field.label,
            tabId: tabConfig.tabId,
            tabName: tabConfig.tabName,
            sectionName: section.name,
            message: `${field.label} is required`,
            elementId: field.elementId,
          });
        }
      }
    });
  });

  const completionPercentage = totalFields > 0 
    ? Math.round((completedFields / totalFields) * 100) 
    : 100;

  return {
    isValid: errors.length === 0,
    errors,
    completionPercentage,
    completedFields,
    totalFields,
    tabId,
    tabName: tabConfig.tabName,
  };
}

/**
 * Gets the first tab that has validation errors
 * 
 * @param encounterData - The encounter data to validate
 * @returns The tab ID of the first invalid tab, or null if all tabs are valid
 */
export function getFirstInvalidTab(encounterData: EncounterData): string | null {
  for (const tabConfig of REQUIRED_FIELDS_BY_TAB) {
    const result = validateTab(tabConfig.tabId, encounterData);
    if (!result.isValid) {
      return tabConfig.tabId;
    }
  }
  return null;
}

/**
 * Gets the first validation error from the encounter data
 * 
 * @param encounterData - The encounter data to validate
 * @returns The first ValidationError, or null if no errors
 */
export function getFirstError(encounterData: EncounterData): ValidationError | null {
  const result = validateEncounter(encounterData);
  return result.errors.length > 0 ? result.errors[0] ?? null : null;
}

/**
 * Groups validation errors by tab
 * 
 * @param errors - Array of validation errors
 * @returns Map of tab ID to array of errors for that tab
 */
export function groupErrorsByTab(errors: ValidationError[]): Map<string, ValidationError[]> {
  const grouped = new Map<string, ValidationError[]>();
  
  errors.forEach((error: ValidationError) => {
    const existing = grouped.get(error.tabId) || [];
    existing.push(error);
    grouped.set(error.tabId, existing);
  });
  
  return grouped;
}

/**
 * Gets validation errors for display in a toast
 * Returns the first N errors plus a count of remaining
 * 
 * @param errors - Array of validation errors
 * @param maxDisplay - Maximum number of errors to return (default 3)
 * @returns Object with displayed errors and remaining count
 */
export function getErrorsForToast(
  errors: ValidationError[], 
  maxDisplay: number = 3
): { 
  displayedErrors: ValidationError[]; 
  remainingCount: number;
} {
  const displayedErrors = errors.slice(0, maxDisplay);
  const remainingCount = Math.max(0, errors.length - maxDisplay);
  
  return {
    displayedErrors,
    remainingCount,
  };
}

/**
 * Validates that the encounter has at least minimal required data
 * This is a quick check before detailed validation
 * 
 * @param encounterData - The encounter data to validate
 * @returns boolean indicating if minimum data is present
 */
export function hasMinimumRequiredData(encounterData: EncounterData): boolean {
  // Check for absolute minimum: patient name and at least one provider
  const hasPatientName = !!(
    encounterData.patientForm?.firstName?.trim() && 
    encounterData.patientForm?.lastName?.trim()
  );
  
  const hasProvider = encounterData.providers?.some(
    (p: { id: string; name: string; role: string }) => p.name?.trim() && p.role === 'lead'
  );
  
  return hasPatientName && !!hasProvider;
}

/**
 * Get a summary message for validation status
 * 
 * @param result - The validation result
 * @returns A user-friendly summary message
 */
export function getValidationSummaryMessage(result: ValidationResult): string {
  if (result.isValid) {
    return 'All required fields are complete. Ready to submit.';
  }
  
  const errorCount = result.errors.length;
  const percentage = result.completionPercentage;
  
  if (errorCount === 1) {
    return `1 required field is missing (${percentage}% complete)`;
  }
  
  return `${errorCount} required fields are missing (${percentage}% complete)`;
}

/**
 * Gets tabs that have errors, sorted by the order they appear
 * 
 * @param errors - Array of validation errors  
 * @returns Array of unique tab IDs that have errors, in tab order
 */
export function getTabsWithErrors(errors: ValidationError[]): string[] {
  const tabOrder = REQUIRED_FIELDS_BY_TAB.map((tab: TabRequiredFields) => tab.tabId);
  const tabsWithErrors = new Set(errors.map((e: ValidationError) => e.tabId));
  
  return tabOrder.filter((tabId: string) => tabsWithErrors.has(tabId));
}
