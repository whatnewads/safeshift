/**
 * Mobile Encounter Navigation Component
 * 
 * Provides a hamburger menu with collapsible tabs and nested required fields
 * for mobile devices. Only active on mobile viewports (max-width: 768px).
 */

import { useState, useCallback, useMemo } from 'react';
import {
  Menu,
  X,
  ChevronDown,
  ChevronRight,
  CheckCircle2,
  Circle,
  AlertTriangle,
  FileText,
  User,
  Stethoscope,
  Activity,
  Pill,
  ClipboardList,
  LogOut as LogOutIcon,
  PenTool,
} from 'lucide-react';
import { Button } from '../ui/button.js';
import {
  getTabRequiredFields,
  type RequiredField,
  type EncounterData,
} from '../../config/requiredFields.js';

// Tab configuration with icons matching EncounterWorkspace
const TAB_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
  incident: FileText,
  patient: User,
  assessments: Stethoscope,
  vitals: Activity,
  treatment: Pill,
  narrative: ClipboardList,
  disposition: LogOutIcon,
  signatures: PenTool,
};

interface TabData {
  id: string;
  label: string;
  status: 'empty' | 'partial' | 'complete' | 'error';
}

interface ComputedFieldStatus {
  name: string;
  label: string;
  elementId: string;
  path: string;
  isCompleted: boolean;
  isRequired: boolean;
}

interface ComputedTabStatus {
  tabId: string;
  label: string;
  completed: number;
  total: number;
  isComplete: boolean;
  fields: ComputedFieldStatus[];
}

interface MobileEncounterNavProps {
  activeTab: string;
  setActiveTab: (tabId: string) => void;
  tabs: TabData[];
  encounterData: EncounterData;
  onFieldClick: (fieldName: string, path: string) => void;
}

export function MobileEncounterNav({
  activeTab,
  setActiveTab,
  tabs,
  encounterData,
  onFieldClick,
}: MobileEncounterNavProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [expandedTabs, setExpandedTabs] = useState<Record<string, boolean>>({});

  const toggleMenu = useCallback(() => {
    setIsOpen(prev => !prev);
  }, []);

  const toggleTab = useCallback((tabId: string, e: React.MouseEvent) => {
    e.stopPropagation(); // Prevent event from bubbling to parent
    e.preventDefault();
    setExpandedTabs(prev => ({
      ...prev,
      [tabId]: !prev[tabId],
    }));
  }, []);

  const handleTabClick = useCallback((tabId: string) => {
    setActiveTab(tabId);
    // Don't close the menu, just switch tab content
  }, [setActiveTab]);

  const handleFieldClickInternal = useCallback((fieldName: string, path: string) => {
    onFieldClick(fieldName, path);
    setIsOpen(false); // Close menu after field selection
  }, [onFieldClick]);

  // Compute tab statuses from encounterData
  const computedTabStatuses = useMemo((): ComputedTabStatus[] => {
    return tabs.map(tab => {
      const tabConfig = getTabRequiredFields(tab.id);
      if (!tabConfig) {
        return {
          tabId: tab.id,
          label: tab.label,
          completed: 0,
          total: 0,
          isComplete: true,
          fields: [],
        };
      }

      // Flatten all fields from all sections
      const allFields: ComputedFieldStatus[] = [];
      let totalRequired = 0;
      let totalCompleted = 0;

      tabConfig.sections.forEach(section => {
        section.fields.forEach((field: RequiredField) => {
          const isRequired = field.conditionallyRequired
            ? field.conditionallyRequired(encounterData)
            : true;
          const isCompleted = field.isCompleted(encounterData);

          if (isRequired) {
            totalRequired++;
            if (isCompleted) {
              totalCompleted++;
            }
          }

          allFields.push({
            name: field.name,
            label: field.label,
            elementId: field.elementId || field.name,
            path: field.path,
            isCompleted,
            isRequired,
          });
        });
      });

      return {
        tabId: tab.id,
        label: tab.label,
        completed: totalCompleted,
        total: totalRequired,
        isComplete: totalRequired === 0 || totalCompleted === totalRequired,
        fields: allFields,
      };
    });
  }, [tabs, encounterData]);

  const getTabStatus = (tabId: string): ComputedTabStatus | undefined => {
    return computedTabStatuses.find(s => s.tabId === tabId);
  };

  return (
    <>
      {/* Hamburger Menu Button - Only visible on mobile */}
      <Button
        variant="ghost"
        size="sm"
        onClick={toggleMenu}
        className="md:hidden flex-shrink-0"
        aria-label="Open navigation menu"
      >
        {isOpen ? (
          <X className="h-5 w-5 text-slate-700 dark:text-white" />
        ) : (
          <Menu className="h-5 w-5 text-slate-700 dark:text-white" />
        )}
      </Button>

      {/* Overlay */}
      {isOpen && (
        <div
          className="md:hidden fixed inset-0 bg-black/50 z-40"
          onClick={() => setIsOpen(false)}
        />
      )}

      {/* Slide-in Menu Panel */}
      <div
        className={`
          md:hidden fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw]
          bg-white dark:bg-slate-800 shadow-xl
          transform transition-transform duration-300 ease-in-out
          ${isOpen ? 'translate-x-0' : '-translate-x-full'}
          flex flex-col
        `}
      >
        {/* Header */}
        <div className="p-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
            Navigation
          </h2>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setIsOpen(false)}
            aria-label="Close navigation menu"
          >
            <X className="h-5 w-5" />
          </Button>
        </div>

        {/* Scrollable Tab List with Nested Fields */}
        <div className="flex-1 overflow-y-auto">
          <nav className="p-2">
            {tabs.map(tab => {
              const status = getTabStatus(tab.id);
              const isExpanded = expandedTabs[tab.id] || false;
              const isActive = activeTab === tab.id;
              const TabIcon = TAB_ICONS[tab.id] || FileText;

              return (
                <div key={tab.id} className="mb-1">
                  {/* Tab Header */}
                  <div
                    className={`
                      flex items-center rounded-lg transition-colors
                      ${isActive 
                        ? 'bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-500' 
                        : 'hover:bg-slate-100 dark:hover:bg-slate-700/50'
                      }
                    `}
                  >
                    {/* Expand/Collapse Button */}
                    <button
                      type="button"
                      onClick={(e) => toggleTab(tab.id, e)}
                      className="p-3 flex-shrink-0"
                      aria-label={`${isExpanded ? 'Collapse' : 'Expand'} ${tab.label}`}
                    >
                      {isExpanded ? (
                        <ChevronDown className="h-4 w-4 text-slate-400" />
                      ) : (
                        <ChevronRight className="h-4 w-4 text-slate-400" />
                      )}
                    </button>

                    {/* Tab Button */}
                    <button
                      type="button"
                      onClick={() => handleTabClick(tab.id)}
                      className="flex-1 flex items-center gap-3 py-3 pr-3 text-left"
                    >
                      <TabIcon className={`h-5 w-5 ${
                        isActive 
                          ? 'text-blue-600 dark:text-blue-400' 
                          : 'text-slate-500 dark:text-slate-400'
                      }`} />
                      <span className={`font-medium ${
                        isActive 
                          ? 'text-blue-700 dark:text-blue-300' 
                          : 'text-slate-700 dark:text-slate-300'
                      }`}>
                        {tab.label}
                      </span>
                    </button>

                    {/* Status Indicator */}
                    {status && status.total > 0 && (
                      <div className="pr-3 flex items-center gap-1">
                        <span className={`text-xs ${
                          status.isComplete 
                            ? 'text-green-600 dark:text-green-400' 
                            : 'text-slate-500 dark:text-slate-400'
                        }`}>
                          {status.completed}/{status.total}
                        </span>
                        {status.isComplete ? (
                          <CheckCircle2 className="h-4 w-4 text-green-500" />
                        ) : status.completed > 0 ? (
                          <AlertTriangle className="h-4 w-4 text-amber-500" />
                        ) : (
                          <Circle className="h-4 w-4 text-slate-300 dark:text-slate-600" />
                        )}
                      </div>
                    )}
                  </div>

                  {/* Nested Required Fields (Collapsible) */}
                  {isExpanded && status && status.fields.length > 0 && (
                    <div className="ml-10 mt-1 mb-2 space-y-1">
                      {status.fields
                        .filter(field => field.isRequired)
                        .map(field => (
                          <button
                            type="button"
                            key={field.elementId}
                            onClick={() => handleFieldClickInternal(field.elementId, field.path)}
                            className={`
                              w-full flex items-center gap-2 p-2 rounded-lg text-left text-sm
                              transition-colors hover:bg-slate-100 dark:hover:bg-slate-700/50
                              ${field.isCompleted 
                                ? 'text-slate-500 dark:text-slate-400' 
                                : 'text-slate-900 dark:text-slate-100'
                              }
                            `}
                          >
                            {field.isCompleted ? (
                              <CheckCircle2 className="h-3.5 w-3.5 text-green-500 flex-shrink-0" />
                            ) : (
                              <Circle className="h-3.5 w-3.5 text-red-400 flex-shrink-0" />
                            )}
                            <span className={field.isCompleted ? 'line-through' : ''}>
                              {field.label}
                              <span className="text-red-600 ml-0.5">*</span>
                            </span>
                          </button>
                        ))
                      }
                      {status.fields.filter(f => f.isRequired).length === 0 && (
                        <p className="text-xs text-slate-500 dark:text-slate-400 px-2 py-1">
                          No required fields
                        </p>
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </nav>
        </div>

        {/* Footer with overall progress */}
        <div className="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
          {computedTabStatuses.length > 0 && (() => {
            const totalCompleted = computedTabStatuses.reduce((sum, s) => sum + s.completed, 0);
            const totalFields = computedTabStatuses.reduce((sum, s) => sum + s.total, 0);
            const percentage = totalFields > 0 ? Math.round((totalCompleted / totalFields) * 100) : 0;

            return (
              <div>
                <div className="flex items-center justify-between text-xs mb-2">
                  <span className="text-slate-600 dark:text-slate-400">
                    Overall Progress
                  </span>
                  <span className={`font-medium ${
                    percentage === 100 
                      ? 'text-green-600 dark:text-green-400' 
                      : 'text-slate-700 dark:text-slate-300'
                  }`}>
                    {totalCompleted}/{totalFields} ({percentage}%)
                  </span>
                </div>
                <div className="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                  <div
                    className={`h-full transition-all duration-300 ${
                      percentage === 100 
                        ? 'bg-green-500' 
                        : percentage > 50 
                          ? 'bg-amber-500' 
                          : 'bg-red-500'
                    }`}
                    style={{ width: `${percentage}%` }}
                  />
                </div>
              </div>
            );
          })()}
        </div>
      </div>
    </>
  );
}

export default MobileEncounterNav;
