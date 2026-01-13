/**
 * ValidationErrors Component
 * 
 * Displays validation errors in a modal dialog, grouped by tab.
 * Allows users to click on errors to navigate directly to the field.
 */

import { useMemo } from 'react';
import { 
  X, 
  AlertCircle, 
  AlertTriangle, 
  ChevronRight,
  FileText,
  User,
  ClipboardList,
  Activity,
  Pill,
  AlignLeft,
  FileCheck,
  PenTool,
} from 'lucide-react';
import { Button } from '../ui/button.js';
import { Card } from '../ui/card.js';
import { Badge } from '../ui/badge.js';
import { 
  type ValidationError, 
  groupErrorsByTab,
  getErrorsForToast,
} from '../../services/validation.service.js';

interface ValidationErrorsProps {
  errors: ValidationError[];
  onErrorClick: (error: ValidationError) => void;
  onClose: () => void;
  completionPercentage?: number;
}

/**
 * Get the icon for a specific tab
 */
function getTabIcon(tabId: string) {
  const iconClass = "h-4 w-4";
  switch (tabId) {
    case 'incident':
      return <FileText className={iconClass} />;
    case 'patient':
      return <User className={iconClass} />;
    case 'assessments':
      return <ClipboardList className={iconClass} />;
    case 'vitals':
      return <Activity className={iconClass} />;
    case 'treatment':
      return <Pill className={iconClass} />;
    case 'narrative':
      return <AlignLeft className={iconClass} />;
    case 'disposition':
      return <FileCheck className={iconClass} />;
    case 'signatures':
      return <PenTool className={iconClass} />;
    default:
      return <AlertCircle className={iconClass} />;
  }
}

/**
 * ValidationErrors Modal Component
 * 
 * Displays a modal with all validation errors grouped by tab.
 * Each error is clickable and navigates to the corresponding field.
 */
export function ValidationErrors({ 
  errors, 
  onErrorClick, 
  onClose,
  completionPercentage = 0,
}: ValidationErrorsProps) {
  // Group errors by tab
  const groupedErrors = useMemo(() => groupErrorsByTab(errors), [errors]);
  
  // Get tab order for consistent display
  const tabOrder = [
    'incident', 'patient', 'assessments', 'vitals', 
    'treatment', 'narrative', 'disposition', 'signatures'
  ];
  
  // Sort tabs by their order
  const sortedTabs = useMemo(() => {
    return Array.from(groupedErrors.keys()).sort((a, b) => {
      return tabOrder.indexOf(a) - tabOrder.indexOf(b);
    });
  }, [groupedErrors]);

  const handleFixFirstError = () => {
    if (errors.length > 0 && errors[0]) {
      onErrorClick(errors[0]);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <Card className="w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col bg-white dark:bg-slate-800">
        {/* Header */}
        <div className="p-6 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
          <div className="flex items-start justify-between">
            <div className="flex items-center gap-3">
              <div className="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                <AlertTriangle className="h-6 w-6 text-red-600 dark:text-red-400" />
              </div>
              <div>
                <h2 className="text-xl font-semibold text-slate-900 dark:text-white">
                  Validation Errors
                </h2>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                  {errors.length} required field{errors.length !== 1 ? 's' : ''} missing
                  <span className="mx-2">•</span>
                  <span className={completionPercentage >= 80 ? 'text-amber-600' : 'text-red-600'}>
                    {completionPercentage}% complete
                  </span>
                </p>
              </div>
            </div>
            <Button 
              variant="ghost" 
              size="sm" 
              onClick={onClose}
              className="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white"
            >
              <X className="h-5 w-5" />
            </Button>
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6 space-y-4">
          {sortedTabs.map((tabId) => {
            const tabErrors = groupedErrors.get(tabId) || [];
            const tabName = tabErrors[0]?.tabName || tabId;
            
            return (
              <div 
                key={tabId} 
                className="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden"
              >
                {/* Tab Header */}
                <div className="bg-slate-50 dark:bg-slate-700/50 px-4 py-3 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    {getTabIcon(tabId)}
                    <span className="font-medium text-slate-900 dark:text-white">
                      {tabName}
                    </span>
                  </div>
                  <Badge variant="destructive" className="text-xs">
                    {tabErrors.length} error{tabErrors.length !== 1 ? 's' : ''}
                  </Badge>
                </div>
                
                {/* Tab Errors */}
                <div className="divide-y divide-slate-100 dark:divide-slate-700">
                  {tabErrors.map((error, index) => (
                    <button
                      key={`${error.tabId}-${error.field}-${index}`}
                      onClick={() => onErrorClick(error)}
                      className="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors text-left group"
                    >
                      <div className="flex items-center gap-3">
                        <AlertCircle className="h-4 w-4 text-red-500 dark:text-red-400 flex-shrink-0" />
                        <div>
                          <p className="text-sm font-medium text-slate-900 dark:text-white">
                            {error.label}
                          </p>
                          <p className="text-xs text-slate-500 dark:text-slate-400">
                            {error.sectionName}
                          </p>
                        </div>
                      </div>
                      <ChevronRight className="h-4 w-4 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300 transition-colors" />
                    </button>
                  ))}
                </div>
              </div>
            );
          })}
        </div>

        {/* Footer */}
        <div className="p-6 border-t border-slate-200 dark:border-slate-700 flex-shrink-0 bg-slate-50 dark:bg-slate-700/30">
          <div className="flex gap-3 justify-end">
            <Button variant="outline" onClick={onClose}>
              Close
            </Button>
            <Button 
              onClick={handleFixFirstError}
              className="bg-blue-600 hover:bg-blue-700 text-white"
            >
              Fix First Issue
            </Button>
          </div>
        </div>
      </Card>
    </div>
  );
}

/**
 * ValidationToast Component
 * 
 * A smaller component for displaying validation errors in a toast notification.
 * Shows first few errors with a link to view all.
 */
interface ValidationToastProps {
  errors: ValidationError[];
  onViewAll: () => void;
  maxDisplay?: number;
}

export function ValidationToast({ 
  errors, 
  onViewAll, 
  maxDisplay = 3 
}: ValidationToastProps) {
  const { displayedErrors, remainingCount } = getErrorsForToast(errors, maxDisplay);

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2 text-red-600 dark:text-red-400 font-medium">
        <AlertTriangle className="h-4 w-4" />
        <span>Please complete required fields</span>
      </div>
      <ul className="space-y-1 text-sm text-slate-600 dark:text-slate-300">
        {displayedErrors.map((error, index) => (
          <li key={`${error.tabId}-${error.field}-${index}`} className="flex items-center gap-2">
            <span className="w-1.5 h-1.5 bg-red-500 rounded-full flex-shrink-0" />
            <span>{error.label}</span>
            <span className="text-xs text-slate-400">({error.tabName})</span>
          </li>
        ))}
      </ul>
      {remainingCount > 0 && (
        <button 
          onClick={onViewAll}
          className="text-sm text-blue-600 dark:text-blue-400 hover:underline mt-2"
        >
          View all {errors.length} errors →
        </button>
      )}
    </div>
  );
}

/**
 * CompactValidationErrors Component
 * 
 * A compact inline component showing validation summary.
 * Used for showing in the header or inline with other content.
 */
interface CompactValidationErrorsProps {
  errors: ValidationError[];
  completionPercentage: number;
  onClick?: () => void;
}

export function CompactValidationErrors({
  errors,
  completionPercentage,
  onClick,
}: CompactValidationErrorsProps) {
  if (errors.length === 0) {
    return null;
  }

  return (
    <button 
      onClick={onClick}
      className="flex items-center gap-2 px-3 py-1.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
    >
      <AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
      <span className="text-sm text-red-700 dark:text-red-300">
        {errors.length} error{errors.length !== 1 ? 's' : ''} 
        <span className="text-red-500 dark:text-red-400 ml-1">
          ({completionPercentage}% complete)
        </span>
      </span>
    </button>
  );
}

export default ValidationErrors;
