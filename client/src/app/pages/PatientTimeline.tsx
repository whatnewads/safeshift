/**
 * PatientTimeline Page
 * 
 * Displays a visual timeline of all encounters for a specific patient.
 * Shows patient information at the top, a horizontal timeline visualization,
 * and allows clicking on encounters to view them in read-only mode.
 */

import { useState, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { 
  ArrowLeft, 
  Calendar, 
  User, 
  Loader2, 
  AlertTriangle,
  RefreshCw,
  Clock,
  FileText,
} from 'lucide-react';
import { Button } from '../components/ui/button.js';
import { Card } from '../components/ui/card.js';
import { Badge } from '../components/ui/badge.js';
import { usePatientTimeline } from '../hooks/usePatientTimeline.js';
import type { TimelineEncounter } from '../types/api.types.js';

/**
 * Calculate position of an encounter on the timeline as a percentage
 */
function calculatePosition(
  encounterDate: string, 
  firstDate: string, 
  currentDate: string
): number {
  const start = new Date(firstDate).getTime();
  const end = new Date(currentDate).getTime();
  const encounter = new Date(encounterDate).getTime();
  
  const totalRange = end - start;
  if (totalRange <= 0) return 50; // If same day, center it
  
  const position = ((encounter - start) / totalRange) * 100;
  // Keep dots within 5%-95% to avoid edge overlap
  return Math.min(Math.max(position, 5), 95);
}

/**
 * Get dot color based on encounter status
 */
function getStatusColor(status: string): string {
  switch (status.toLowerCase()) {
    case 'completed':
      return 'bg-green-500 hover:bg-green-600';
    case 'in_progress':
    case 'in-progress':
      return 'bg-yellow-500 hover:bg-yellow-600';
    case 'cancelled':
    case 'voided':
      return 'bg-red-500 hover:bg-red-600';
    case 'planned':
    case 'scheduled':
      return 'bg-purple-500 hover:bg-purple-600';
    default:
      return 'bg-blue-500 hover:bg-blue-600';
  }
}

/**
 * Get status badge variant
 */
function getStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (status.toLowerCase()) {
    case 'completed':
      return 'default';
    case 'in_progress':
    case 'in-progress':
      return 'secondary';
    case 'cancelled':
    case 'voided':
      return 'destructive';
    default:
      return 'outline';
  }
}

/**
 * Format date for display
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Format short date for timeline labels
 */
function formatShortDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    year: 'numeric',
  });
}

/**
 * Timeline Dot Component
 */
interface TimelineDotProps {
  encounter: TimelineEncounter;
  position: number;
  isSelected: boolean;
  onSelect: (id: string | null) => void;
  onClick: (id: string) => void;
}

function TimelineDot({ 
  encounter, 
  position, 
  isSelected, 
  onSelect, 
  onClick 
}: TimelineDotProps) {
  return (
    <div 
      className="absolute top-1/2 -translate-y-1/2 group"
      style={{ left: `${position}%` }}
    >
      {/* Tooltip */}
      <div 
        className={`
          absolute bottom-full left-1/2 -translate-x-1/2 mb-3
          bg-slate-900 dark:bg-slate-700 text-white
          px-3 py-2 rounded-lg shadow-lg
          whitespace-nowrap text-sm
          transition-opacity duration-200
          ${isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'}
          pointer-events-none z-20
        `}
      >
        <p className="font-medium">{formatDate(encounter.occurred_on)}</p>
        <p className="text-slate-300 text-xs">
          {encounter.chief_complaint || encounter.encounter_type}
        </p>
        <p className="text-slate-400 text-xs capitalize">
          {encounter.status.replace('_', ' ')}
        </p>
        {/* Arrow */}
        <div className="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900 dark:border-t-slate-700" />
      </div>
      
      {/* Dot button */}
      <button
        className={`
          -translate-x-1/2 w-4 h-4 rounded-full
          transition-all duration-200 cursor-pointer
          focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500
          ${getStatusColor(encounter.status)}
          ${isSelected ? 'scale-150 ring-4 ring-blue-300 dark:ring-blue-600' : 'hover:scale-125'}
        `}
        onClick={() => onClick(encounter.encounter_id)}
        onMouseEnter={() => onSelect(encounter.encounter_id)}
        onMouseLeave={() => onSelect(null)}
        aria-label={`View encounter from ${formatDate(encounter.occurred_on)}`}
      />
      
      {/* Vertical line to label */}
      <div 
        className={`
          absolute top-4 left-1/2 -translate-x-1/2 w-px h-6
          bg-slate-300 dark:bg-slate-600
          transition-opacity duration-200
          ${isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'}
        `}
      />
      
      {/* Date label below */}
      <div 
        className={`
          absolute top-10 left-1/2 -translate-x-1/2
          text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap
          transition-opacity duration-200
          ${isSelected ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'}
        `}
      >
        {formatShortDate(encounter.occurred_on)}
      </div>
    </div>
  );
}

/**
 * Main PatientTimeline page component
 */
export default function PatientTimeline() {
  const { patientId } = useParams<{ patientId: string }>();
  const navigate = useNavigate();
  const { 
    patient, 
    timeline, 
    encounters, 
    isLoading, 
    error, 
    refetch 
  } = usePatientTimeline(patientId || '');
  
  const [selectedEncounterId, setSelectedEncounterId] = useState<string | null>(null);

  // Calculate positions for timeline dots
  const encounterPositions = useMemo(() => {
    if (!timeline?.first_encounter_date || !timeline?.current_date || encounters.length === 0) {
      return [];
    }
    
    return encounters.map(encounter => ({
      ...encounter,
      position: calculatePosition(
        encounter.occurred_on, 
        timeline.first_encounter_date!, 
        timeline.current_date
      ),
    }));
  }, [encounters, timeline]);

  /**
   * Navigate to encounter in read-only mode
   */
  const handleEncounterClick = (encounterId: string) => {
    navigate(`/encounters/${encounterId}?mode=readonly`);
  };

  /**
   * Go back to patients list
   */
  const handleBackClick = () => {
    navigate('/patients');
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600 mx-auto mb-4" />
          <p className="text-slate-600 dark:text-slate-400">Loading patient timeline...</p>
        </div>
      </div>
    );
  }

  // Error state
  if (error || !patient) {
    return (
      <div className="p-6">
        <Card className="p-8 text-center max-w-md mx-auto">
          <AlertTriangle className="h-12 w-12 text-red-500 mx-auto mb-4" />
          <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-2">
            Error Loading Timeline
          </h3>
          <p className="text-slate-600 dark:text-slate-400 mb-4">
            {error || 'Patient not found'}
          </p>
          <div className="flex gap-3 justify-center">
            <Button variant="outline" onClick={handleBackClick}>
              <ArrowLeft className="h-4 w-4 mr-2" />
              Back to Patients
            </Button>
            <Button onClick={refetch}>
              <RefreshCw className="h-4 w-4 mr-2" />
              Try Again
            </Button>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      {/* Header with Back Button */}
      <div className="flex items-center gap-4">
        <Button variant="ghost" size="sm" onClick={handleBackClick}>
          <ArrowLeft className="h-4 w-4 mr-2" />
          Back to Patients
        </Button>
      </div>

      {/* Patient Info Card */}
      <Card className="p-6">
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
          {/* Avatar */}
          <div className="h-16 w-16 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center flex-shrink-0">
            <User className="h-8 w-8 text-blue-600 dark:text-blue-400" />
          </div>
          
          {/* Patient Details */}
          <div className="flex-1">
            <h1 className="text-2xl font-semibold text-slate-900 dark:text-white">
              {patient.name}
            </h1>
            <div className="flex flex-wrap items-center gap-4 mt-1 text-sm text-slate-600 dark:text-slate-400">
              <span>DOB: {formatDate(patient.dob)}</span>
              <span>Sex: {patient.sex}</span>
            </div>
          </div>
          
          {/* Encounter Count */}
          <div className="text-right">
            <Badge variant="outline" className="text-lg px-4 py-1">
              {timeline?.total_encounters || 0} Encounter{(timeline?.total_encounters || 0) !== 1 ? 's' : ''}
            </Badge>
            {timeline?.first_encounter_date && timeline?.last_encounter_date && (
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">
                {formatShortDate(timeline.first_encounter_date)} - {formatShortDate(timeline.last_encounter_date)}
              </p>
            )}
          </div>
        </div>
      </Card>

      {/* Timeline Visualization */}
      <Card className="p-6">
        <h2 className="text-lg font-semibold mb-2 flex items-center gap-2 text-slate-900 dark:text-white">
          <Calendar className="h-5 w-5" />
          Encounter Timeline
        </h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mb-8">
          Click any dot to view the encounter report
        </p>
        
        {encounters.length === 0 ? (
          /* Empty State */
          <div className="py-12 text-center">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
              <FileText className="h-8 w-8 text-slate-400 dark:text-slate-500" />
            </div>
            <p className="text-slate-600 dark:text-slate-400">
              No encounters found for this patient
            </p>
            <Button 
              variant="outline" 
              className="mt-4"
              onClick={() => navigate('/encounters/workspace', { 
                state: { patientId: patient.patient_id } 
              })}
            >
              Create First Encounter
            </Button>
          </div>
        ) : (
          /* Timeline */
          <div className="relative py-16 px-4 overflow-x-auto">
            {/* Timeline Bar */}
            <div className="absolute left-4 right-4 top-1/2 -translate-y-1/2 h-1 bg-slate-200 dark:bg-slate-700 rounded-full min-w-[600px]" />
            
            {/* Start Marker */}
            <div className="absolute left-4 top-1/2 -translate-y-1/2 flex flex-col items-center">
              <div className="w-3 h-3 bg-slate-400 dark:bg-slate-500 rounded-full" />
              <span className="text-xs text-slate-500 dark:text-slate-400 mt-8 whitespace-nowrap">
                {timeline?.first_encounter_date 
                  ? formatShortDate(timeline.first_encounter_date) 
                  : 'Start'}
              </span>
            </div>
            
            {/* End Marker (Today) */}
            <div className="absolute right-4 top-1/2 -translate-y-1/2 flex flex-col items-center">
              <div className="w-3 h-3 bg-blue-600 dark:bg-blue-500 rounded-full border-2 border-white dark:border-slate-800 shadow" />
              <span className="text-xs text-slate-500 dark:text-slate-400 mt-8">Today</span>
            </div>
            
            {/* Encounter Dots */}
            <div className="relative min-w-[600px] h-full" style={{ minHeight: '80px' }}>
              {encounterPositions.map((encounter) => (
                <TimelineDot
                  key={encounter.encounter_id}
                  encounter={encounter}
                  position={encounter.position}
                  isSelected={selectedEncounterId === encounter.encounter_id}
                  onSelect={setSelectedEncounterId}
                  onClick={handleEncounterClick}
                />
              ))}
            </div>
          </div>
        )}
        
        {/* Legend */}
        {encounters.length > 0 && (
          <div className="flex flex-wrap items-center gap-4 mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
            <span className="text-xs text-slate-500 dark:text-slate-400">Status:</span>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-green-500" />
              <span className="text-xs text-slate-600 dark:text-slate-400">Completed</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-yellow-500" />
              <span className="text-xs text-slate-600 dark:text-slate-400">In Progress</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-purple-500" />
              <span className="text-xs text-slate-600 dark:text-slate-400">Planned</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="w-3 h-3 rounded-full bg-red-500" />
              <span className="text-xs text-slate-600 dark:text-slate-400">Cancelled</span>
            </div>
            <div className="flex items-center gap-2 ml-auto">
              <div className="w-3 h-3 rounded-full bg-blue-600 border-2 border-white shadow" />
              <span className="text-xs text-slate-600 dark:text-slate-400">Today</span>
            </div>
          </div>
        )}
      </Card>

      {/* Encounters List */}
      {encounters.length > 0 && (
        <Card className="p-6">
          <h2 className="text-lg font-semibold mb-4 flex items-center gap-2 text-slate-900 dark:text-white">
            <FileText className="h-5 w-5" />
            All Encounters
          </h2>
          <div className="space-y-3">
            {encounters.map((encounter) => (
              <div
                key={encounter.encounter_id}
                className={`
                  p-4 border rounded-lg cursor-pointer transition-all
                  ${selectedEncounterId === encounter.encounter_id
                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 shadow-md'
                    : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 hover:shadow-sm'
                  }
                `}
                onClick={() => handleEncounterClick(encounter.encounter_id)}
                onMouseEnter={() => setSelectedEncounterId(encounter.encounter_id)}
                onMouseLeave={() => setSelectedEncounterId(null)}
              >
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div className="flex items-start gap-3">
                    <div className={`mt-1 w-3 h-3 rounded-full flex-shrink-0 ${getStatusColor(encounter.status)}`} />
                    <div>
                      <p className="font-medium text-slate-900 dark:text-white">
                        {formatDate(encounter.occurred_on)}
                      </p>
                      <p className="text-sm text-slate-600 dark:text-slate-400">
                        {encounter.chief_complaint || 'No chief complaint recorded'}
                      </p>
                      <p className="text-xs text-slate-500 dark:text-slate-500 mt-1 flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        Created: {formatDate(encounter.created_at)}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2 sm:flex-col sm:items-end">
                    <Badge variant={getStatusBadgeVariant(encounter.status)} className="capitalize">
                      {encounter.status.replace('_', ' ')}
                    </Badge>
                    <span className="text-xs text-slate-500 dark:text-slate-400 capitalize">
                      {encounter.encounter_type}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}
