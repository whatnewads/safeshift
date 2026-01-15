import { useMemo, useCallback } from 'react';
import {
  REQUIRED_FIELDS_BY_TAB,
  getTabRequiredFields,
  type EncounterData,
  type TabRequiredFields,
  type RequiredField,
  type TabSection,
} from '../config/requiredFields.js';

export interface FieldStatus {
  field: RequiredField;
  isCompleted: boolean;
  isRequired: boolean; // Whether the field is currently required (for conditional fields)
}

export interface SectionStatus {
  section: TabSection;
  fields: FieldStatus[];
  completed: number;
  total: number;
  isComplete: boolean;
}

export interface TabStatus {
  tabId: string;
  tabName: string;
  sections: SectionStatus[];
  completed: number;
  total: number;
  percentage: number;
  isComplete: boolean;
}

export interface UseRequiredFieldsResult {
  // Current tab status
  currentTabStatus: TabStatus | null;
  // All tabs status
  allTabsStatus: TabStatus[];
  // Overall completion
  overallCompletion: {
    completed: number;
    total: number;
    percentage: number;
    isComplete: boolean;
  };
  // Helper functions
  getFieldStatus: (tabId: string, fieldName: string) => FieldStatus | null;
  getSectionStatus: (tabId: string, sectionName: string) => SectionStatus | null;
  scrollToField: (elementId: string) => void;
  focusField: (elementId: string) => void;
}

/**
 * Hook to manage required fields logic for the encounter workspace.
 * 
 * @param activeTab - The currently active tab ID
 * @param encounterData - The current encounter data containing all form values
 * @returns Object containing required field status and helper functions
 */
export function useRequiredFields(
  activeTab: string,
  encounterData: EncounterData
): UseRequiredFieldsResult {
  
  /**
   * Calculate the status for a single tab
   */
  const calculateTabStatus = useCallback((tabConfig: TabRequiredFields): TabStatus => {
    const sectionStatuses: SectionStatus[] = tabConfig.sections.map((section: TabSection) => {
      const fieldStatuses: FieldStatus[] = section.fields.map((field: RequiredField) => {
        // Check if the field is conditionally required
        const isRequired = field.conditionallyRequired
          ? field.conditionallyRequired(encounterData)
          : true;
        
        const isCompleted = field.isCompleted(encounterData);
        
        return {
          field,
          isCompleted,
          isRequired,
        };
      });
      
      // Calculate section completion (only count required fields)
      const requiredFields = fieldStatuses.filter((f: FieldStatus) => f.isRequired);
      const completedFields = requiredFields.filter((f: FieldStatus) => f.isCompleted);
      
      return {
        section,
        fields: fieldStatuses,
        completed: completedFields.length,
        total: requiredFields.length,
        isComplete: requiredFields.length === 0 || completedFields.length === requiredFields.length,
      };
    });
    
    // Calculate overall tab completion
    const totalFields = sectionStatuses.reduce((sum: number, s: SectionStatus) => sum + s.total, 0);
    const completedFields = sectionStatuses.reduce((sum: number, s: SectionStatus) => sum + s.completed, 0);
    const percentage = totalFields > 0 ? Math.round((completedFields / totalFields) * 100) : 100;
    
    return {
      tabId: tabConfig.tabId,
      tabName: tabConfig.tabName,
      sections: sectionStatuses,
      completed: completedFields,
      total: totalFields,
      percentage,
      isComplete: totalFields === 0 || completedFields === totalFields,
    };
  }, [encounterData]);
  
  /**
   * Calculate status for all tabs
   */
  const allTabsStatus = useMemo((): TabStatus[] => {
    return REQUIRED_FIELDS_BY_TAB.map((tabConfig: TabRequiredFields) => calculateTabStatus(tabConfig));
  }, [calculateTabStatus]);
  
  /**
   * Get status for the current tab
   */
  const currentTabStatus = useMemo(() => {
    const tabConfig = getTabRequiredFields(activeTab);
    if (!tabConfig) return null;
    return calculateTabStatus(tabConfig);
  }, [activeTab, calculateTabStatus]);
  
  /**
   * Calculate overall completion across all tabs
   */
  const overallCompletion = useMemo(() => {
    const totalFields = allTabsStatus.reduce((sum: number, tab: TabStatus) => sum + tab.total, 0);
    const completedFields = allTabsStatus.reduce((sum: number, tab: TabStatus) => sum + tab.completed, 0);
    const percentage = totalFields > 0 ? Math.round((completedFields / totalFields) * 100) : 100;
    
    return {
      completed: completedFields,
      total: totalFields,
      percentage,
      isComplete: totalFields === 0 || completedFields === totalFields,
    };
  }, [allTabsStatus]);
  
  /**
   * Get status for a specific field
   */
  const getFieldStatus = useCallback((tabId: string, fieldName: string): FieldStatus | null => {
    const tabStatus = allTabsStatus.find((t: TabStatus) => t.tabId === tabId);
    if (!tabStatus) return null;
    
    for (const section of tabStatus.sections) {
      const field = section.fields.find((f: FieldStatus) => f.field.name === fieldName);
      if (field) return field;
    }
    
    return null;
  }, [allTabsStatus]);
  
  /**
   * Get status for a specific section
   */
  const getSectionStatus = useCallback((tabId: string, sectionName: string): SectionStatus | null => {
    const tabStatus = allTabsStatus.find((t: TabStatus) => t.tabId === tabId);
    if (!tabStatus) return null;
    
    return tabStatus.sections.find((s: SectionStatus) => s.section.name === sectionName) || null;
  }, [allTabsStatus]);
  
  /**
   * Scroll to a field element
   */
  const scrollToField = useCallback((elementId: string) => {
    const element = document.getElementById(elementId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }, []);
  
  /**
   * Focus on a field element
   */
  const focusField = useCallback((elementId: string) => {
    const element = document.getElementById(elementId);
    if (element) {
      // First scroll to it
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      
      // Then focus after a short delay to ensure visibility
      setTimeout(() => {
        if (element instanceof HTMLInputElement || 
            element instanceof HTMLTextAreaElement || 
            element instanceof HTMLSelectElement) {
          element.focus();
        } else {
          // Try to find a focusable child element
          const focusable = element.querySelector<HTMLElement>(
            'input, textarea, select, button, [tabindex]:not([tabindex="-1"])'
          );
          if (focusable) {
            focusable.focus();
          }
        }
      }, 300);
    }
  }, []);
  
  return {
    currentTabStatus,
    allTabsStatus,
    overallCompletion,
    getFieldStatus,
    getSectionStatus,
    scrollToField,
    focusField,
  };
}

/**
 * Create an EncounterData object from form state
 * This helper function transforms the individual form states into the format expected by the hook
 */
export function createEncounterData(
  incidentForm: any,
  patientForm: any,
  providers: Array<{ id: string; name: string; role: string }>,
  assessments: any[],
  vitals: any[],
  narrative: string,
  disposition: string,
  disclosures: Record<string, boolean>
): EncounterData {
  return {
    incidentForm: incidentForm || {},
    patientForm: patientForm || {},
    providers: providers || [],
    assessments: assessments || [],
    vitals: vitals || [],
    narrative: narrative || '',
    disposition: disposition || '',
    disclosures: disclosures || {},
  };
}

export default useRequiredFields;
