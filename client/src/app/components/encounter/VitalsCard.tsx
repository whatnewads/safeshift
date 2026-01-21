import { Card } from '../ui/card';
import { Clock, Heart, Droplet, Wind, Thermometer, Gauge, Brain, Activity, Pencil, Trash2 } from 'lucide-react';

// Flexible vital data interface - accepts various shapes from context or forms
export interface VitalData {
  id: string;
  time: string;
  date?: string;
  bp?: string;
  pulse?: string;
  respiration?: string;
  resp?: string; // Alternative field name from context
  spo2?: string;
  temp?: string;
  glucose?: string;
  bloodGlucose?: string; // Alternative field name from context
  pain?: string;
  painLevel?: string; // Alternative field name from context
  avpu?: string;
  gcsTotal?: string;
  gcs?: string; // Alternative field name from context
  gcsEye?: string;
  gcsVerbal?: string;
  gcsMotor?: string;
  notes?: string;
  [key: string]: any; // Allow additional properties
}

interface VitalsCardProps {
  vital: VitalData | any;
  onClick?: () => void;
  onEdit?: (vital: VitalData) => void;
  onDelete?: (vitalId: string) => void;
}

export function VitalsCard({ vital, onClick, onEdit, onDelete }: VitalsCardProps) {
  // Format display time/date
  const displayDateTime = vital.date 
    ? `${vital.date} ${vital.time}` 
    : vital.time;

  // Helper to render a vital stat
  const VitalStat = ({ 
    icon: Icon, 
    label, 
    value, 
    unit = '' 
  }: { 
    icon: React.ElementType; 
    label: string; 
    value: string | undefined; 
    unit?: string 
  }) => {
    if (!value) return null;
    return (
      <div className="flex items-center gap-2">
        <Icon className="h-4 w-4 text-slate-400 dark:text-slate-500" />
        <span className="text-xs text-slate-500 dark:text-slate-400">{label}:</span>
        <span className="text-sm font-medium text-slate-900 dark:text-slate-100">
          {value}{unit}
        </span>
      </div>
    );
  };

  // Map AVPU values to readable labels
  const getAvpuLabel = (avpu: string) => {
    switch (avpu) {
      case 'A': return 'Alert';
      case 'V': return 'Verbal';
      case 'P': return 'Pain';
      case 'U': return 'Unresponsive';
      default: return avpu;
    }
  };

  // Handle edit button click
  const handleEditClick = (e: React.MouseEvent) => {
    e.stopPropagation(); // Prevent card onClick from firing
    onEdit?.(vital);
  };

  // Handle delete button click with confirmation
  const handleDeleteClick = (e: React.MouseEvent) => {
    e.stopPropagation(); // Prevent card onClick from firing
    if (window.confirm('Are you sure you want to delete this vitals record?')) {
      onDelete?.(vital.id);
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
              title="Edit vitals"
              aria-label="Edit vitals"
            >
              <Pencil className="h-4 w-4" />
            </button>
          )}
          {onDelete && (
            <button
              onClick={handleDeleteClick}
              className="p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400"
              title="Delete vitals"
              aria-label="Delete vitals"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
        </div>
      )}

      {/* Header with timestamp */}
      <div className="flex items-center gap-2 pb-3 mb-3 border-b border-slate-100 dark:border-slate-700">
        <Clock className="h-4 w-4 text-blue-500 dark:text-blue-400" />
        <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
          {displayDateTime}
        </span>
        {vital.avpu && (
          <span className={`ml-auto px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 ${(onEdit || onDelete) ? 'mr-12' : ''}`}>
            AVPU: {getAvpuLabel(vital.avpu)}
          </span>
        )}
      </div>

      {/* Vitals Grid */}
      <div className="grid grid-cols-2 gap-3">
        <VitalStat icon={Droplet} label="BP" value={vital.bp} unit=" mmHg" />
        <VitalStat icon={Heart} label="Pulse" value={vital.pulse} unit=" bpm" />
        <VitalStat icon={Wind} label="RR" value={vital.respiration || vital.resp} unit="/min" />
        <VitalStat icon={Gauge} label="SpO₂" value={vital.spo2} unit="%" />
        <VitalStat icon={Thermometer} label="Temp" value={vital.temp} unit="°F" />
        <VitalStat icon={Activity} label="Glucose" value={vital.glucose || vital.bloodGlucose} unit=" mg/dL" />
        {(vital.pain || vital.painLevel) && (
          <div className="flex items-center gap-2">
            <span className="text-xs text-slate-500 dark:text-slate-400">Pain:</span>
            <span className="text-sm font-medium text-slate-900 dark:text-slate-100">
              {vital.pain || vital.painLevel}/10
            </span>
          </div>
        )}
        {(vital.gcsTotal || vital.gcs) && (
          <div className="flex items-center gap-2">
            <Brain className="h-4 w-4 text-slate-400 dark:text-slate-500" />
            <span className="text-xs text-slate-500 dark:text-slate-400">GCS:</span>
            <span className="text-sm font-medium text-slate-900 dark:text-slate-100">
              {vital.gcsTotal || vital.gcs}
            </span>
          </div>
        )}
      </div>
    </Card>
  );
}

export default VitalsCard;
