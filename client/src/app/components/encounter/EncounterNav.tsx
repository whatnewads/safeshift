import { CheckCircle2, AlertTriangle, AlertCircle, Circle, Home } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

interface EncounterNavProps {
  encounterContext: any;
  sidebarOpen: boolean;
}

export function EncounterNav({ encounterContext, sidebarOpen }: EncounterNavProps) {
  const navigate = useNavigate();
  
  // Safely destructure encounterContext with defaults to prevent undefined access errors
  const {
    activeTab = 'incident',
    incidentForm = {},
    patientForm = {},
    disposition = '',
    providerSignature = ''
  } = encounterContext || {};

  const scrollToSection = (sectionId: string) => {
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  // Helper function to check if fields are filled
  const isFieldFilled = (value: any) => {
    return value !== '' && value !== null && value !== undefined;
  };

  // Define sections for each tab
  const incidentSections = [
    {
      id: 'clinic-info',
      label: 'Clinic Information',
      requiredFields: [incidentForm.clinicName, incidentForm.clinicAddress],
    },
    {
      id: 'time-fields',
      label: 'Time-Based Fields',
      requiredFields: [
        incidentForm.mayDayTime,
        incidentForm.patientContactTime,
        incidentForm.transferOfCareTime,
        incidentForm.clearedClinicTime,
      ],
    },
    {
      id: 'provider-info',
      label: 'Provider Information',
      requiredFields: [incidentForm.providerName, incidentForm.providerRole],
    },
    {
      id: 'incident-details',
      label: 'Incident Details',
      requiredFields: [incidentForm.massCasualty, incidentForm.location, incidentForm.disposition],
    },
  ];

  const patientSections = [
    {
      id: 'patient-demographics',
      label: 'Patient Demographics',
      requiredFields: [
        patientForm.firstName,
        patientForm.lastName,
        patientForm.dob,
        patientForm.sex,
        patientForm.employeeId,
        patientForm.ssn,
        patientForm.dlNumber,
        patientForm.dlState,
        patientForm.phone,
        patientForm.email,
      ],
    },
    {
      id: 'home-address',
      label: 'Home Address',
      requiredFields: [
        patientForm.streetAddress,
        patientForm.city,
        patientForm.county,
        patientForm.state,
        patientForm.country,
      ],
    },
    {
      id: 'employment-info',
      label: 'Employment Information',
      requiredFields: [
        patientForm.employer,
        patientForm.supervisorName,
        patientForm.supervisorPhone,
      ],
    },
    {
      id: 'medical-history',
      label: 'Medical History',
      requiredFields: [patientForm.allergies, patientForm.currentMedications],
    },
  ];

  // Consolidated Objective Findings sections (combines assessments, vitals, treatments)
  const objectiveFindingsSections = [
    { id: 'assessments-section', label: 'Assessments', requiredFields: [] },
    { id: 'vitals-section', label: 'Vitals', requiredFields: [] },
    { id: 'treatments-section', label: 'Treatments', requiredFields: [] },
  ];

  const narrativeSections = [
    { id: 'clinical-narrative', label: 'Clinical Narrative', requiredFields: [] },
  ];

  const dispositionSections = [
    {
      id: 'disposition-outcome',
      label: 'Disposition Outcome',
      requiredFields: [disposition],
    },
  ];

  const signaturesSections = [
    {
      id: 'provider-signature',
      label: 'Provider Signature',
      requiredFields: [providerSignature],
    },
    { id: 'witness-signature', label: 'Witness Signature (Optional)', requiredFields: [] },
  ];

  // Determine which sections to display based on active tab
  let sections: any[] = [];
  switch (activeTab) {
    case 'incident':
      sections = incidentSections;
      break;
    case 'patient':
      sections = patientSections;
      break;
    case 'objectiveFindings':
      sections = objectiveFindingsSections;
      break;
    case 'narrative':
      sections = narrativeSections;
      break;
    case 'disposition':
      sections = dispositionSections;
      break;
    case 'signatures':
      sections = signaturesSections;
      break;
  }

  if (!sidebarOpen) {
    return (
      <div className="space-y-2">
        <button
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              navigate('/dashboard');
            }}
            className="w-full flex items-center justify-center py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
            title="Back to Dashboard"
          >
          <Home className="h-5 w-5 text-slate-500 dark:text-slate-400" />
        </button>

        <div className="h-px bg-slate-200 dark:bg-slate-700 my-2" />

        {sections.map((section) => {
          const filledFields = section.requiredFields.filter(isFieldFilled).length;
          const totalFields = section.requiredFields.length;
          const isComplete = totalFields > 0 && filledFields === totalFields;
          const isPartial = totalFields > 0 && filledFields > 0 && filledFields < totalFields;
          const isEmpty = totalFields > 0 && filledFields === 0;

          return (
            <button
              key={section.id}
              onClick={() => scrollToSection(section.id)}
              className="w-full flex items-center justify-center py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
              title={section.label}
            >
              {isComplete && <CheckCircle2 className="h-5 w-5 text-green-600" />}
              {isPartial && <AlertTriangle className="h-5 w-5 text-yellow-600" />}
              {isEmpty && <AlertCircle className="h-5 w-5 text-red-600" />}
              {totalFields === 0 && <Circle className="h-5 w-5 text-slate-300" />}
            </button>
          );
        })}
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <button
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          navigate('/dashboard');
        }}
        className="w-full text-left px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors flex items-center gap-3 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white"
      >
        <Home className="h-4 w-4" />
        <span className="text-sm">Back to Dashboard</span>
      </button>

      <div className="h-px bg-slate-200 dark:bg-slate-700 my-2" />

      <div className="text-xs text-slate-500 dark:text-slate-400 px-3 mb-2">Section Navigation</div>
      {sections.map((section) => {
        const filledFields = section.requiredFields.filter(isFieldFilled).length;
        const totalFields = section.requiredFields.length;
        const isComplete = totalFields > 0 && filledFields === totalFields;
        const isPartial = totalFields > 0 && filledFields > 0 && filledFields < totalFields;
        const isEmpty = totalFields > 0 && filledFields === 0;

        return (
          <button
            key={section.id}
            onClick={() => scrollToSection(section.id)}
            className="w-full text-left px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors flex items-center justify-between group"
          >
            <span className="text-sm">{section.label}</span>
            <div className="flex items-center gap-2">
              {totalFields > 0 && (
                <span className="text-xs text-slate-500">
                  {filledFields}/{totalFields}
                </span>
              )}
              {isComplete && <CheckCircle2 className="h-4 w-4 text-green-600" />}
              {isPartial && <AlertTriangle className="h-4 w-4 text-yellow-600" />}
              {isEmpty && <AlertCircle className="h-4 w-4 text-red-600" />}
              {totalFields === 0 && <Circle className="h-4 w-4 text-slate-300" />}
            </div>
          </button>
        );
      })}
    </div>
  );
}