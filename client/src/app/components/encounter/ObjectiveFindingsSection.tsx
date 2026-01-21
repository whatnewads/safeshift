import { ReactNode } from 'react';
import { Button } from '../ui/button';
import { Plus } from 'lucide-react';

interface ObjectiveFindingsSectionProps {
  title: string;
  icon: ReactNode;
  iconColor?: string;
  children: ReactNode;
  onAdd: () => void;
  addLabel?: string;
  emptyMessage?: string;
  isEmpty?: boolean;
}

export function ObjectiveFindingsSection({
  title,
  icon,
  iconColor = 'text-blue-600 dark:text-blue-400',
  children,
  onAdd,
  addLabel = 'Add',
  emptyMessage = 'No entries yet',
  isEmpty = false,
}: ObjectiveFindingsSectionProps) {
  return (
    <div className="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
      {/* Section Header */}
      <div className="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
        <div className="flex items-center gap-3">
          <div className={iconColor}>
            {icon}
          </div>
          <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            {title}
          </h3>
        </div>
        <Button
          onClick={onAdd}
          size="sm"
          className="bg-green-600 hover:bg-green-700 text-white shadow-sm"
        >
          <Plus className="h-4 w-4 mr-1.5" />
          {addLabel}
        </Button>
      </div>

      {/* Section Content */}
      <div className="p-5">
        {isEmpty ? (
          <div className="text-center py-8">
            <p className="text-slate-500 dark:text-slate-400">{emptyMessage}</p>
            <Button
              onClick={onAdd}
              variant="outline"
              className="mt-4"
            >
              <Plus className="h-4 w-4 mr-1.5" />
              {addLabel}
            </Button>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
            {children}
          </div>
        )}
      </div>
    </div>
  );
}

export default ObjectiveFindingsSection;
