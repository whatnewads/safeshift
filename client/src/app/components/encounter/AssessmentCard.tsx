import { Card } from '../ui/card';
import { Clock, CheckCircle2, AlertCircle, MinusCircle, Pencil, Trash2 } from 'lucide-react';

// Flexible assessment data interface - accepts various shapes from context or forms
export interface AssessmentData {
  id: string;
  time?: string;
  timestamp?: string; // Alternative field name from context
  editableTime?: string;
  region?: string; // Single region from context
  findings?: string; // From context
  regions?: {
    mentalStatus?: string;
    skin?: string;
    heent?: string;
    chest?: string;
    abdomen?: string;
    back?: string;
    pelvisGUI?: string;
    extremities?: string;
    neurological?: string;
    [key: string]: string | undefined;
  };
  savedAt?: string;
  patientName?: string;
  notes?: string;
  [key: string]: any; // Allow additional properties
}

interface AssessmentCardProps {
  assessment: AssessmentData | any;
  onClick?: () => void;
  onEdit?: (assessment: AssessmentData) => void;
  onDelete?: (assessmentId: string) => void;
}

export function AssessmentCard({ assessment, onClick, onEdit, onDelete }: AssessmentCardProps) {
  // Handle both context type (single region) and EncounterWorkspace type (regions object)
  const regions = assessment.regions || {};
  const hasRegionsObject = Object.keys(regions).length > 0;
  
  // Count assessed regions for regions object
  const assessedCount = hasRegionsObject
    ? Object.values(regions).filter(
        (v) => v === 'Assessed' || v === 'No Abnormalities'
      ).length
    : assessment.findings ? 1 : 0;
  const totalRegions = hasRegionsObject ? Object.keys(regions).length : 1;

  // Get display time - handle both time and timestamp fields
  const displayTime = assessment.editableTime || assessment.time || assessment.timestamp || '';

  // Get status color based on region value
  const getStatusIcon = (value: string) => {
    switch (value) {
      case 'Assessed':
        return <CheckCircle2 className="h-3.5 w-3.5 text-green-500" />;
      case 'No Abnormalities':
        return <CheckCircle2 className="h-3.5 w-3.5 text-blue-500" />;
      case 'Not Assessed':
        return <MinusCircle className="h-3.5 w-3.5 text-slate-400" />;
      default:
        return <AlertCircle className="h-3.5 w-3.5 text-amber-500" />;
    }
  };

  // Format region key to readable label
  const formatRegionLabel = (key: string) => {
    return key
      .replace(/([A-Z])/g, ' $1')
      .replace(/^./, (str) => str.toUpperCase())
      .replace('Heent', 'HEENT')
      .replace('Pelvis GUI', 'Pelvis/GU/GI')
      .trim();
  };

  // Select key regions to display in the card summary
  const keyRegions = ['mentalStatus', 'chest', 'neurological', 'extremities'];
  const displayRegions = hasRegionsObject
    ? keyRegions.filter((key) => key in regions)
    : [];

  // Handle edit button click
  const handleEditClick = (e: React.MouseEvent) => {
    e.stopPropagation(); // Prevent card onClick from firing
    onEdit?.(assessment);
  };

  // Handle delete button click with confirmation
  const handleDeleteClick = (e: React.MouseEvent) => {
    e.stopPropagation(); // Prevent card onClick from firing
    if (window.confirm('Are you sure you want to delete this assessment?')) {
      onDelete?.(assessment.id);
    }
  };

  return (
    <Card
      className="p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm hover:shadow-md hover:border-slate-300 dark:hover:border-slate-600 transition-all cursor-pointer relative group"
      onClick={onClick}
    >
      {/* Edit/Delete Icons - Show on hover */}
      {(onEdit || onDelete) && (
        <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          {onEdit && (
            <button
              onClick={handleEditClick}
              className="p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
              title="Edit assessment"
              aria-label="Edit assessment"
            >
              <Pencil className="h-4 w-4" />
            </button>
          )}
          {onDelete && (
            <button
              onClick={handleDeleteClick}
              className="p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400"
              title="Delete assessment"
              aria-label="Delete assessment"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
        </div>
      )}

      {/* Header with timestamp */}
      <div className="flex items-center gap-2 pb-3 mb-3 border-b border-slate-100 dark:border-slate-700">
        <Clock className="h-4 w-4 text-purple-500 dark:text-purple-400" />
        <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
          {displayTime || 'No time recorded'}
        </span>
        <span className={`ml-auto px-2 py-0.5 text-xs font-medium rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 ${(onEdit || onDelete) ? 'mr-12' : ''}`}>
          {assessedCount}/{totalRegions} assessed
        </span>
      </div>

      {/* Assessment Summary - Handle both regions object and single region from context */}
      <div className="space-y-2">
        {/* Show regions object (from EncounterWorkspace) */}
        {hasRegionsObject && displayRegions.map((regionKey) => {
          const value = regions[regionKey as keyof typeof regions];
          return (
            <div key={regionKey} className="flex items-center justify-between">
              <span className="text-sm text-slate-600 dark:text-slate-400">
                {formatRegionLabel(regionKey)}
              </span>
              <div className="flex items-center gap-1.5">
                {getStatusIcon(value || '')}
                <span
                  className={`text-xs font-medium ${
                    value === 'Assessed'
                      ? 'text-green-600 dark:text-green-400'
                      : value === 'No Abnormalities'
                      ? 'text-blue-600 dark:text-blue-400'
                      : 'text-slate-500 dark:text-slate-400'
                  }`}
                >
                  {value}
                </span>
              </div>
            </div>
          );
        })}
        
        {/* Show single region (from context type) */}
        {!hasRegionsObject && assessment.region && (
          <div className="flex items-center justify-between">
            <span className="text-sm text-slate-600 dark:text-slate-400">
              {formatRegionLabel(assessment.region)}
            </span>
            <div className="flex items-center gap-1.5">
              {getStatusIcon(assessment.findings ? 'Assessed' : 'Not Assessed')}
              <span className="text-xs font-medium text-green-600 dark:text-green-400">
                {assessment.findings ? 'Assessed' : 'Not Assessed'}
              </span>
            </div>
          </div>
        )}
        
        {/* Show findings/notes if present */}
        {(assessment.findings || assessment.notes) && (
          <div className="text-sm text-slate-600 dark:text-slate-400 mt-2">
            <span className="font-medium">Findings:</span>{' '}
            <span className="line-clamp-2">{assessment.findings || assessment.notes}</span>
          </div>
        )}
      </div>

      {/* Show more regions indicator */}
      {hasRegionsObject && totalRegions > displayRegions.length && (
        <div className="mt-2 pt-2 border-t border-slate-100 dark:border-slate-700">
          <span className="text-xs text-slate-500 dark:text-slate-400">
            + {totalRegions - displayRegions.length} more body regions
          </span>
        </div>
      )}
    </Card>
  );
}

export default AssessmentCard;
