import { Activity, ClipboardList, Pill, Stethoscope } from 'lucide-react';
import { toast } from 'sonner';

import { useEncounter } from '../../contexts/EncounterContext';
import { ObjectiveFindingsSection } from './ObjectiveFindingsSection';
import { VitalsCard, VitalData } from './VitalsCard';
import { AssessmentCard, AssessmentData } from './AssessmentCard';
import { TreatmentCard, TreatmentData } from './TreatmentCard';

// Props for modal visibility control - these modals are defined in EncounterWorkspace.tsx
interface ObjectiveFindingsTabProps {
  // Vitals
  onShowVitalsModal: () => void;
  onEditVital: (vital: any) => void;
  onDeleteVital?: (vitalId: string) => void;
  // Assessments
  onShowAssessmentModal: () => void;
  onEditAssessment: (assessment: any) => void;
  onDeleteAssessment?: (assessmentId: string) => void;
  // Treatments
  onShowTreatmentModal: () => void;
  onEditTreatment?: (treatment: any) => void;
  onDeleteTreatment?: (treatmentId: string) => void;
  // Callbacks for data changes (for validation tracking)
  onVitalsChange?: (vitals: any[]) => void;
  onAssessmentsChange?: (assessments: any[]) => void;
}

export function ObjectiveFindingsTab({
  onShowVitalsModal,
  onEditVital,
  onDeleteVital,
  onShowAssessmentModal,
  onEditAssessment,
  onDeleteAssessment,
  onShowTreatmentModal,
  onEditTreatment,
  onDeleteTreatment,
}: ObjectiveFindingsTabProps) {
  // Use context for persistent data
  const encounterContext = useEncounter();
  
  // Get data from context - these use the context types (VitalSet, Assessment, Treatment)
  // but our cards handle any-shaped data
  const vitals = encounterContext?.vitals ?? [];
  const assessments = encounterContext?.assessments ?? [];
  const treatments = encounterContext?.treatments ?? [];

  // Handle vital card click - open edit modal
  const handleVitalClick = (vital: any) => {
    onEditVital(vital);
  };

  // Handle vital edit
  const handleVitalEdit = (vital: VitalData) => {
    onEditVital(vital);
  };

  // Handle vital delete
  const handleVitalDelete = (vitalId: string) => {
    if (onDeleteVital) {
      onDeleteVital(vitalId);
      toast.success('Vital record deleted');
    }
  };

  // Handle assessment card click - open edit modal
  const handleAssessmentClick = (assessment: any) => {
    onEditAssessment(assessment);
  };

  // Handle assessment edit
  const handleAssessmentEdit = (assessment: AssessmentData) => {
    onEditAssessment(assessment);
  };

  // Handle assessment delete
  const handleAssessmentDelete = (assessmentId: string) => {
    if (onDeleteAssessment) {
      onDeleteAssessment(assessmentId);
      toast.success('Assessment deleted');
    }
  };

  // Handle treatment card click - open edit modal if available
  const handleTreatmentClick = (treatment: any) => {
    if (onEditTreatment) {
      onEditTreatment(treatment);
    } else {
      toast.info('Treatment details view coming soon');
    }
  };

  // Handle treatment edit
  const handleTreatmentEdit = (treatment: TreatmentData) => {
    if (onEditTreatment) {
      onEditTreatment(treatment);
    }
  };

  // Handle treatment delete
  const handleTreatmentDelete = (treatmentId: string) => {
    if (onDeleteTreatment) {
      onDeleteTreatment(treatmentId);
      toast.success('Treatment deleted');
    }
  };

  return (
    <div className="space-y-6" id="objective-findings">
      {/* Page Header */}
      <div className="flex items-center gap-3 pb-4 border-b border-slate-200 dark:border-slate-700">
        <div className="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
          <Stethoscope className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
        </div>
        <div>
          <h2 className="text-xl font-semibold text-slate-900 dark:text-slate-100">
            Objective Findings
          </h2>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Document vitals, physical assessments, and treatments
          </p>
        </div>
      </div>

      {/* Vitals Section */}
      <ObjectiveFindingsSection
        title="Vitals"
        icon={<Activity className="h-5 w-5" />}
        iconColor="text-blue-600 dark:text-blue-400"
        onAdd={onShowVitalsModal}
        addLabel="Add Vitals"
        emptyMessage="No vitals recorded yet. Click Add Vitals to record patient vital signs."
        isEmpty={vitals.length === 0}
      >
        {vitals.map((vital) => (
          <VitalsCard
            key={vital.id}
            vital={vital}
            onClick={() => handleVitalClick(vital)}
            onEdit={handleVitalEdit}
            onDelete={onDeleteVital ? handleVitalDelete : undefined}
          />
        ))}
      </ObjectiveFindingsSection>

      {/* Assessments Section */}
      <ObjectiveFindingsSection
        title="Assessments"
        icon={<ClipboardList className="h-5 w-5" />}
        iconColor="text-purple-600 dark:text-purple-400"
        onAdd={onShowAssessmentModal}
        addLabel="Add Assessment"
        emptyMessage="No assessments recorded yet. Click Add Assessment to document physical examination findings."
        isEmpty={assessments.length === 0}
      >
        {assessments.map((assessment) => (
          <AssessmentCard
            key={assessment.id}
            assessment={assessment}
            onClick={() => handleAssessmentClick(assessment)}
            onEdit={handleAssessmentEdit}
            onDelete={onDeleteAssessment ? handleAssessmentDelete : undefined}
          />
        ))}
      </ObjectiveFindingsSection>

      {/* Treatments Section */}
      <ObjectiveFindingsSection
        title="Treatments"
        icon={<Pill className="h-5 w-5" />}
        iconColor="text-emerald-600 dark:text-emerald-400"
        onAdd={onShowTreatmentModal}
        addLabel="Add Intervention"
        emptyMessage="No treatments or interventions recorded yet. Click Add Intervention to document treatments administered."
        isEmpty={treatments.length === 0}
      >
        {treatments.map((treatment) => (
          <TreatmentCard
            key={treatment.id}
            treatment={treatment}
            onClick={() => handleTreatmentClick(treatment)}
            onEdit={onEditTreatment ? handleTreatmentEdit : undefined}
            onDelete={onDeleteTreatment ? handleTreatmentDelete : undefined}
          />
        ))}
      </ObjectiveFindingsSection>
    </div>
  );
}

export default ObjectiveFindingsTab;
