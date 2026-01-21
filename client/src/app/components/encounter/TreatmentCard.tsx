import { Card } from '../ui/card';
import { Badge } from '../ui/badge';
import { Clock, Pill, Syringe, Activity, Pencil, Trash2 } from 'lucide-react';

// Flexible treatment data interface - accepts various shapes from context or forms
export interface TreatmentData {
  id: string;
  name?: string;
  type?: string; // Alternative field name from context
  description?: string; // From context
  category?: string;
  time: string;
  provider?: string;
  details?: {
    Dosage?: string;
    Route?: string;
    [key: string]: string | undefined;
  };
  notes?: string;
  [key: string]: any; // Allow additional properties
}

interface TreatmentCardProps {
  treatment: TreatmentData | any;
  onClick?: () => void;
  onEdit?: (treatment: TreatmentData) => void;
  onDelete?: (treatmentId: string) => void;
}

export function TreatmentCard({ treatment, onClick, onEdit, onDelete }: TreatmentCardProps) {
  // Get appropriate icon based on category
  const getCategoryIcon = (category: string | undefined) => {
    if (!category) return Pill;
    const lowerCategory = category.toLowerCase();
    if (lowerCategory.includes('medication')) return Pill;
    if (lowerCategory.includes('iv') || lowerCategory.includes('access')) return Syringe;
    if (lowerCategory.includes('airway') || lowerCategory.includes('critical')) return Activity;
    return Pill;
  };

  const CategoryIcon = getCategoryIcon(treatment.category);

  // Get category badge color
  const getCategoryColor = (category: string | undefined) => {
    if (!category) return 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300';
    const lowerCategory = category.toLowerCase();
    if (lowerCategory.includes('critical')) return 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300';
    if (lowerCategory.includes('medication')) return 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300';
    if (lowerCategory.includes('airway')) return 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300';
    if (lowerCategory.includes('iv')) return 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300';
    if (lowerCategory.includes('procedure')) return 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300';
    return 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300';
  };

  // Get treatment name - handle both name and type fields
  const treatmentName = treatment.name || treatment.type || treatment.description || 'Unknown Treatment';

  // Filter out empty details
  const displayDetails: [string, string][] = treatment.details
    ? Object.entries(treatment.details)
        .filter(([_, value]) => value && typeof value === 'string' && value.trim())
        .map(([key, value]) => [key, String(value)])
    : [];

  // Handle edit button click
  const handleEditClick = (e: React.MouseEvent) => {
    e.stopPropagation(); // Prevent card onClick from firing
    onEdit?.(treatment);
  };

  // Handle delete button click with confirmation
  const handleDeleteClick = (e: React.MouseEvent) => {
    e.stopPropagation(); // Prevent card onClick from firing
    if (window.confirm('Are you sure you want to delete this treatment?')) {
      onDelete?.(treatment.id);
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
              title="Edit treatment"
              aria-label="Edit treatment"
            >
              <Pencil className="h-4 w-4" />
            </button>
          )}
          {onDelete && (
            <button
              onClick={handleDeleteClick}
              className="p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400"
              title="Delete treatment"
              aria-label="Delete treatment"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
        </div>
      )}

      {/* Header with timestamp */}
      <div className="flex items-center gap-2 pb-3 mb-3 border-b border-slate-100 dark:border-slate-700">
        <Clock className="h-4 w-4 text-emerald-500 dark:text-emerald-400" />
        <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
          {treatment.time}
        </span>
        {treatment.category && (
          <Badge variant="outline" className={`ml-auto text-xs ${getCategoryColor(treatment.category)} ${(onEdit || onDelete) ? 'mr-12' : ''}`}>
            {treatment.category}
          </Badge>
        )}
      </div>

      {/* Treatment Name */}
      <div className="flex items-start gap-3 mb-3">
        <div className="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
          <CategoryIcon className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
        </div>
        <div className="flex-1">
          <h4 className="font-medium text-slate-900 dark:text-slate-100">
            {treatmentName}
          </h4>
          {/* Show description if type is used instead of name */}
          {treatment.type && treatment.description && (
            <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
              {treatment.description}
            </p>
          )}
        </div>
      </div>

      {/* Treatment Details */}
      {displayDetails.length > 0 && (
        <div className="flex flex-wrap gap-x-4 gap-y-1 mb-2">
          {displayDetails.map(([key, value]) => (
            <div key={key} className="flex items-center gap-1 text-sm">
              <span className="text-slate-500 dark:text-slate-400">{key}:</span>
              <span className="font-medium text-slate-700 dark:text-slate-300">{value}</span>
            </div>
          ))}
        </div>
      )}

      {/* Show provider if present */}
      {treatment.provider && (
        <div className="text-sm text-slate-500 dark:text-slate-400 mb-2">
          <span className="font-medium">Provider:</span> {treatment.provider}
        </div>
      )}

      {/* Notes */}
      {treatment.notes && (
        <div className="pt-2 border-t border-slate-100 dark:border-slate-700">
          <p className="text-sm text-slate-600 dark:text-slate-400 line-clamp-2">
            {treatment.notes}
          </p>
        </div>
      )}
    </Card>
  );
}

export default TreatmentCard;
