import type { ReactNode } from 'react';
import { useState } from 'react';
import { Card } from '../ui/card.js';
import { Label } from '../ui/label.js';
import { BodyModelModal } from './BodyModelModal.js';

export type AssessmentStatus = 'normal' | 'abnormal' | 'not-assessed';

interface AssessmentPanelProps {
  title: string;
  status: AssessmentStatus;
  onStatusChange: (status: AssessmentStatus) => void;
  children: ReactNode;
  summary?: string;
  showBodyModel?: boolean;
  onBodyFindings?: (findings: any[]) => void;
  bodyFindings?: any[];
}

export function AssessmentPanel({
  title,
  status,
  onStatusChange,
  children,
  summary,
  showBodyModel: _showBodyModel = false,
  onBodyFindings,
  bodyFindings = [],
}: AssessmentPanelProps) {
  const [showBodyModelModal, setShowBodyModelModal] = useState(false);

  // Get status color and text
  const getStatusDisplay = () => {
    if (status === 'normal') {
      return { color: 'text-green-600 dark:text-green-400', text: '✓ No Abnormalities' };
    } else if (status === 'abnormal') {
      return { color: 'text-orange-600 dark:text-orange-400', text: '⚠ Abnormalities' };
    } else {
      return { color: 'text-red-600 dark:text-red-400', text: '✗ Not Assessed' };
    }
  };

  const statusDisplay = getStatusDisplay();

  return (
    <>
      <Card className="p-6 border-0">
        <div className="mb-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-semibold">{title}</h3>
            <span className={`text-sm font-medium ${statusDisplay.color}`}>
              {statusDisplay.text}
            </span>
          </div>
          
          {/* Status Toggle */}
          <div className="flex gap-3 mb-4 flex-wrap">
            <button
              type="button"
              onClick={() => onStatusChange('normal')}
              className={`flex items-center gap-2.5 px-4 py-2 rounded-full transition-all cursor-pointer ${ 
                status === 'normal'
                  ? 'bg-green-100 dark:bg-green-900/30 border border-green-500 dark:border-green-600'
                  : 'bg-green-50/50 dark:bg-green-900/10 border border-transparent hover:border-green-300 dark:hover:border-green-700'
              }`}
            >
              <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center ${
                status === 'normal'
                  ? 'border-green-600 dark:border-green-400 bg-green-600 dark:bg-green-500'
                  : 'border-green-400 dark:border-green-600'
              }`}>
                {status === 'normal' && (
                  <div className="w-2.5 h-2.5 rounded-full bg-white"></div>
                )}
              </div>
              <span className={`text-sm font-medium ${
                status === 'normal'
                  ? 'text-green-700 dark:text-green-300'
                  : 'text-green-600 dark:text-green-400'
              }`}>
                No Abnormalities
              </span>
            </button>
            
            <button
              type="button"
              onClick={() => onStatusChange('abnormal')}
              className={`flex items-center gap-2.5 px-4 py-2 rounded-full transition-all cursor-pointer ${
                status === 'abnormal'
                  ? 'bg-orange-100 dark:bg-orange-900/30 border border-orange-500 dark:border-orange-600'
                  : 'bg-orange-50/50 dark:bg-orange-900/10 border border-transparent hover:border-orange-300 dark:hover:border-orange-700'
              }`}
            >
              <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center ${
                status === 'abnormal'
                  ? 'border-orange-600 dark:border-orange-400 bg-orange-600 dark:bg-orange-500'
                  : 'border-orange-400 dark:border-orange-600'
              }`}>
                {status === 'abnormal' && (
                  <div className="w-2.5 h-2.5 rounded-full bg-white"></div>
                )}
              </div>
              <span className={`text-sm font-medium ${
                status === 'abnormal'
                  ? 'text-orange-700 dark:text-orange-300'
                  : 'text-orange-600 dark:text-orange-400'
              }`}>
                Abnormal
              </span>
            </button>
            
            <button
              type="button"
              onClick={() => onStatusChange('not-assessed')}
              className={`flex items-center gap-2.5 px-4 py-2 rounded-full transition-all cursor-pointer ${
                status === 'not-assessed'
                  ? 'bg-red-100 dark:bg-red-900/30 border border-red-500 dark:border-red-600'
                  : 'bg-red-50/50 dark:bg-red-900/10 border border-transparent hover:border-red-300 dark:hover:border-red-700'
              }`}
            >
              <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center ${
                status === 'not-assessed'
                  ? 'border-red-600 dark:border-red-400 bg-red-600 dark:bg-red-500'
                  : 'border-red-400 dark:border-red-600'
              }`}>
                {status === 'not-assessed' && (
                  <div className="w-2.5 h-2.5 rounded-full bg-white"></div>
                )}
              </div>
              <span className={`text-sm font-medium ${
                status === 'not-assessed'
                  ? 'text-red-700 dark:text-red-300'
                  : 'text-red-600 dark:text-red-400'
              }`}>
                Not Assessed
              </span>
            </button>
          </div>

          {/* Auto-generated Summary */}
          {summary && status === 'normal' && (
            <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 text-sm text-green-800 dark:text-green-300">
              ✓ {summary}
            </div>
          )}
        </div>

        {/* Content - disabled if not assessed */}
        <div className={status === 'not-assessed' ? 'opacity-50 pointer-events-none' : ''}>
          {children}
        </div>
      </Card>

      {/* Body Model Modal */}
      {showBodyModelModal && (
        <BodyModelModal
          isOpen={showBodyModelModal}
          onClose={() => setShowBodyModelModal(false)}
          onSave={(findings) => {
            if (onBodyFindings) {
              onBodyFindings(findings);
            }
            setShowBodyModelModal(false);
          }}
          initialFindings={bodyFindings}
        />
      )}
    </>
  );
}

interface CheckboxFieldProps {
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
}

// v2: Compact button styling with minimal padding
export function CheckboxField({ label, checked, onChange, disabled }: CheckboxFieldProps) {
  return (
    <button
      type="button"
      onClick={() => !disabled && onChange(!checked)}
      disabled={disabled}
      className={`flex items-center gap-2 px-2.5 py-1.5 rounded-lg transition-all cursor-pointer w-fit ${
        checked
          ? 'bg-blue-100 dark:bg-blue-900/30 border border-blue-400 dark:border-blue-600'
          : 'bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500'
      } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
    >
      <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0 ${
        checked
          ? 'border-blue-600 dark:border-blue-400 bg-blue-600 dark:bg-blue-500'
          : 'border-slate-800 dark:border-slate-200'
      }`}>
        {checked && (
          <div className="w-2 h-2 rounded-full bg-white"></div>
        )}
      </div>
      <span className={`text-sm ${
        checked
          ? 'text-slate-900 dark:text-slate-100 font-medium'
          : 'text-slate-900 dark:text-slate-100'
      }`}>
        {label}
      </span>
    </button>
  );
}

interface RadioGroupProps {
  label: string;
  options: string[];
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
}

// v2: Compact styling matching CheckboxField
export function RadioGroup({ label, options, value, onChange, disabled }: RadioGroupProps) {
  return (
    <div>
      <Label className="mb-2 block">{label}</Label>
      <div className="flex flex-wrap gap-2">
        {options.map((option) => (
          <button
            key={option}
            type="button"
            onClick={() => !disabled && onChange(option)}
            disabled={disabled}
            className={`flex items-center gap-2 px-2.5 py-1.5 rounded-lg transition-all cursor-pointer w-fit ${
              value === option
                ? 'bg-blue-100 dark:bg-blue-900/30 border border-blue-400 dark:border-blue-600'
                : 'bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500'
            } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
          >
            <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0 ${
              value === option
                ? 'border-blue-600 dark:border-blue-400 bg-blue-600 dark:bg-blue-500'
                : 'border-slate-800 dark:border-slate-200'
            }`}>
              {value === option && (
                <div className="w-2 h-2 rounded-full bg-white"></div>
              )}
            </div>
            <span className={`text-sm ${
              value === option
                ? 'text-slate-900 dark:text-slate-100 font-medium'
                : 'text-slate-900 dark:text-slate-100'
            }`}>
              {option}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}