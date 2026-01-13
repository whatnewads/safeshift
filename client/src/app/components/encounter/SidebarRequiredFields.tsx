import { useMemo } from 'react';
import { CheckCircle2, Circle, AlertTriangle, ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useRequiredFields, createEncounterData } from '../../hooks/useRequiredFields.js';
import type { EncounterData } from '../../config/requiredFields.js';
import type { SectionStatus, FieldStatus } from '../../hooks/useRequiredFields.js';

interface SidebarRequiredFieldsProps {
  activeTab: string;
  encounterData: EncounterData;
  onFieldClick?: ((elementId: string) => void) | undefined;
}

/**
 * SidebarRequiredFields Component
 * 
 * Displays the required fields for the currently active tab in the encounter workspace.
 * Shows completion status with checkmarks/circles and allows clicking to navigate to fields.
 */
export function SidebarRequiredFields({
  activeTab,
  encounterData,
  onFieldClick,
}: SidebarRequiredFieldsProps) {
  const {
    currentTabStatus,
    overallCompletion,
    focusField,
  } = useRequiredFields(activeTab, encounterData);

  const [expandedSections, setExpandedSections] = useState<Record<string, boolean>>({});

  // Initialize all sections as expanded by default
  useMemo(() => {
    if (currentTabStatus) {
      const initialExpanded: Record<string, boolean> = {};
      currentTabStatus.sections.forEach((section: SectionStatus) => {
        initialExpanded[section.section.name] = true;
      });
      setExpandedSections(initialExpanded);
    }
  }, [currentTabStatus?.tabId]);

  const toggleSection = (sectionName: string) => {
    setExpandedSections(prev => ({
      ...prev,
      [sectionName]: !prev[sectionName],
    }));
  };

  const handleFieldClick = (elementId?: string) => {
    if (!elementId) return;
    
    if (onFieldClick) {
      onFieldClick(elementId);
    } else {
      focusField(elementId);
    }
  };

  if (!currentTabStatus) {
    return (
      <div className="p-4 text-sm text-slate-500 dark:text-slate-400">
        No required fields for this tab.
      </div>
    );
  }

  // Check if there are any required fields in the current tab
  const hasRequiredFields = currentTabStatus.sections.some(
    (section: SectionStatus) => section.fields.some((f: FieldStatus) => f.isRequired)
  );

  if (!hasRequiredFields) {
    return (
      <div className="p-4">
        <div className="flex items-center gap-2 text-sm text-green-600 dark:text-green-400 mb-2">
          <CheckCircle2 className="h-4 w-4" />
          <span>No required fields</span>
        </div>
        <p className="text-xs text-slate-500 dark:text-slate-400">
          This tab has no required fields.
        </p>
      </div>
    );
  }

  return (
    <div className="flex flex-col h-full">
      {/* Header with overall progress */}
      <div className="p-4 border-b border-slate-200 dark:border-slate-700">
        <div className="flex items-center justify-between mb-2">
          <h3 className="text-sm font-medium text-slate-900 dark:text-slate-100">
            Required Fields
          </h3>
          <span className={`text-xs font-medium ${
            currentTabStatus.isComplete 
              ? 'text-green-600 dark:text-green-400' 
              : 'text-amber-600 dark:text-amber-400'
          }`}>
            {currentTabStatus.completed}/{currentTabStatus.total}
          </span>
        </div>
        
        {/* Progress bar */}
        <div className="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
          <div
            className={`h-full transition-all duration-300 ${
              currentTabStatus.isComplete 
                ? 'bg-green-500' 
                : currentTabStatus.percentage > 50 
                  ? 'bg-amber-500' 
                  : 'bg-red-500'
            }`}
            style={{ width: `${currentTabStatus.percentage}%` }}
          />
        </div>
        
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
          {currentTabStatus.isComplete 
            ? 'All required fields complete' 
            : `${currentTabStatus.percentage}% complete`}
        </p>
      </div>

      {/* Sections and fields */}
      <div className="flex-1 overflow-y-auto p-2">
        {currentTabStatus.sections.map((sectionStatus: SectionStatus) => {
          // Skip sections with no required fields
          const requiredFieldsCount = sectionStatus.fields.filter((f: FieldStatus) => f.isRequired).length;
          if (requiredFieldsCount === 0) return null;

          const isExpanded = expandedSections[sectionStatus.section.name] !== false;

          return (
            <div key={sectionStatus.section.name} className="mb-2">
              {/* Section header */}
              <button
                onClick={() => toggleSection(sectionStatus.section.name)}
                className="w-full flex items-center justify-between p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors"
              >
                <div className="flex items-center gap-2">
                  {isExpanded ? (
                    <ChevronDown className="h-4 w-4 text-slate-400" />
                  ) : (
                    <ChevronRight className="h-4 w-4 text-slate-400" />
                  )}
                  <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
                    {sectionStatus.section.name}
                  </span>
                </div>
                <div className="flex items-center gap-1">
                  <span className={`text-xs ${
                    sectionStatus.isComplete 
                      ? 'text-green-600 dark:text-green-400' 
                      : 'text-slate-500 dark:text-slate-400'
                  }`}>
                    {sectionStatus.completed}/{sectionStatus.total}
                  </span>
                  {sectionStatus.isComplete ? (
                    <CheckCircle2 className="h-4 w-4 text-green-500" />
                  ) : sectionStatus.completed > 0 ? (
                    <AlertTriangle className="h-4 w-4 text-amber-500" />
                  ) : (
                    <Circle className="h-4 w-4 text-slate-300 dark:text-slate-600" />
                  )}
                </div>
              </button>

              {/* Fields */}
              {isExpanded && (
                <div className="ml-6 mt-1 space-y-1">
                  {sectionStatus.fields.map((fieldStatus: FieldStatus) => {
                    // Skip non-required fields
                    if (!fieldStatus.isRequired) return null;

                    return (
                      <button
                        key={fieldStatus.field.name}
                        onClick={() => handleFieldClick(fieldStatus.field.elementId)}
                        className={`
                          w-full flex items-center gap-2 p-2 rounded-lg text-left
                          transition-colors hover:bg-slate-100 dark:hover:bg-slate-700/50
                          ${fieldStatus.isCompleted 
                            ? 'text-slate-500 dark:text-slate-400' 
                            : 'text-slate-900 dark:text-slate-100'}
                        `}
                        title={`Click to navigate to ${fieldStatus.field.label}`}
                      >
                        {fieldStatus.isCompleted ? (
                          <CheckCircle2 className="h-4 w-4 text-green-500 flex-shrink-0" />
                        ) : (
                          <Circle className="h-4 w-4 text-red-400 dark:text-red-500 flex-shrink-0" />
                        )}
                        <span className={`text-sm ${
                          fieldStatus.isCompleted
                            ? 'line-through decoration-slate-400 dark:decoration-slate-500'
                            : ''
                        }`}>
                          {fieldStatus.field.label}
                          {fieldStatus.isRequired && (
                            <span className="text-red-600 dark:text-red-500 ml-0.5">*</span>
                          )}
                        </span>
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Overall completion footer */}
      <div className="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
        <div className="flex items-center justify-between text-xs">
          <span className="text-slate-600 dark:text-slate-400">
            Overall Progress
          </span>
          <span className={`font-medium ${
            overallCompletion.isComplete 
              ? 'text-green-600 dark:text-green-400' 
              : 'text-slate-700 dark:text-slate-300'
          }`}>
            {overallCompletion.completed}/{overallCompletion.total} ({overallCompletion.percentage}%)
          </span>
        </div>
      </div>
    </div>
  );
}

/**
 * Helper component to wrap the SidebarRequiredFields with encounter data from individual form states
 */
interface SidebarRequiredFieldsWrapperProps {
  activeTab: string;
  incidentForm: any;
  patientForm: any;
  providers?: Array<{ id: string; name: string; role: string }>;
  assessments?: any[];
  vitals?: any[];
  narrative?: string;
  disposition?: string;
  disclosures?: Record<string, boolean>;
  onFieldClick?: (elementId: string) => void;
}

export function SidebarRequiredFieldsWrapper({
  activeTab,
  incidentForm,
  patientForm,
  providers = [],
  assessments = [],
  vitals = [],
  narrative = '',
  disposition = '',
  disclosures = {},
  onFieldClick,
}: SidebarRequiredFieldsWrapperProps) {
  const encounterData = useMemo(() => createEncounterData(
    incidentForm,
    patientForm,
    providers,
    assessments,
    vitals,
    narrative,
    disposition,
    disclosures
  ), [incidentForm, patientForm, providers, assessments, vitals, narrative, disposition, disclosures]);

  return (
    <SidebarRequiredFields
      activeTab={activeTab}
      encounterData={encounterData}
      onFieldClick={onFieldClick}
    />
  );
}

export default SidebarRequiredFields;
