/**
 * Required Fields Configuration for EHR Form
 * 
 * This configuration defines all required fields for each tab in the encounter workspace.
 * Each tab has sections, and each section has required fields with validation logic.
 */

export interface RequiredField {
  name: string;
  label: string;
  path: string; // Path to value in encounter data
  elementId?: string; // ID of the HTML element to scroll to
  isCompleted: (encounterData: EncounterData) => boolean;
  conditionallyRequired?: (encounterData: EncounterData) => boolean; // If provided, field is only required when this returns true
}

export interface TabSection {
  name: string;
  fields: RequiredField[];
}

export interface TabRequiredFields {
  tabId: string;
  tabName: string;
  sections: TabSection[];
}

export interface EncounterData {
  incidentForm: {
    clinicName?: string;
    clinicStreetAddress?: string;
    clinicCity?: string;
    clinicState?: string;
    clinicCounty?: string;
    clinicUnitNumber?: string;
    patientContactTime?: string;
    clearedClinicTime?: string;
    mayDayTime?: string;
    transferOfCareTime?: string;
    location?: string;
    injuryClassifiedByName?: string;
    injuryClassification?: string;
    massCasualty?: string;
    natureOfIllness?: string;
    mechanismofinjury?: string;
    [key: string]: any;
  };
  patientForm: {
    firstName?: string;
    lastName?: string;
    dob?: string;
    sex?: string;
    phone?: string;
    email?: string;
    streetAddress?: string;
    city?: string;
    state?: string;
    county?: string;
    employer?: string;
    supervisorName?: string;
    supervisorPhone?: string;
    medicalHistory?: string;
    allergies?: string;
    currentMedications?: string;
    optInNotifications?: boolean;
    [key: string]: any;
  };
  providers: Array<{ id: string; name: string; role: string }>;
  assessments: any[];
  vitals: any[];
  narrative?: string;
  disposition?: string;
  disclosures: Record<string, boolean>;
  providerSignature?: string;
  patientSignature?: string;
}

// Helper functions for validation
const hasValue = (value: any): boolean => {
  if (value === null || value === undefined) return false;
  if (typeof value === 'string') return value.trim().length > 0;
  if (Array.isArray(value)) return value.length > 0;
  return true;
};

const hasLeadProvider = (providers: Array<{ id: string; name: string; role: string }>): boolean => {
  return providers?.some(p => p.name && p.role === 'lead') || false;
};

const hasMinimumAssessments = (assessments: any[]): boolean => {
  return assessments?.length >= 1;
};

const hasMinimumNarrativeLength = (narrative: string | undefined): boolean => {
  return (narrative?.trim().length || 0) >= 25;
};

const hasAllDisclosuresAcknowledged = (disclosures: Record<string, boolean>): boolean => {
  if (!disclosures || Object.keys(disclosures).length === 0) return false;
  return Object.values(disclosures).every(v => v === true);
};

export const REQUIRED_FIELDS_BY_TAB: TabRequiredFields[] = [
  // ==================== INCIDENT TAB ====================
  {
    tabId: 'incident',
    tabName: 'Incident',
    sections: [
      {
        name: 'Clinic Information',
        fields: [
          {
            name: 'clinicName',
            label: 'Clinic Name',
            path: 'incidentForm.clinicName',
            elementId: 'incidentClinicName',
            isCompleted: (data) => hasValue(data.incidentForm?.clinicName),
          },
          {
            name: 'clinicStreetAddress',
            label: 'Street Address',
            path: 'incidentForm.clinicStreetAddress',
            elementId: 'incidentClinicStreetAddress',
            isCompleted: (data) => hasValue(data.incidentForm?.clinicStreetAddress),
          },
          {
            name: 'clinicCity',
            label: 'City',
            path: 'incidentForm.clinicCity',
            elementId: 'incidentClinicCity',
            isCompleted: (data) => hasValue(data.incidentForm?.clinicCity),
          },
          {
            name: 'clinicState',
            label: 'State',
            path: 'incidentForm.clinicState',
            elementId: 'incidentClinicState',
            isCompleted: (data) => hasValue(data.incidentForm?.clinicState),
          },
        ],
      },
      {
        name: 'Time Fields',
        fields: [
          {
            name: 'patientContactTime',
            label: 'Patient Contact Time',
            path: 'incidentForm.patientContactTime',
            elementId: 'incidentPatientContactTime',
            isCompleted: (data) => hasValue(data.incidentForm?.patientContactTime),
          },
          {
            name: 'clearedClinicTime',
            label: 'Cleared Clinic Time',
            path: 'incidentForm.clearedClinicTime',
            elementId: 'incidentClearedClinicTime',
            isCompleted: (data) => hasValue(data.incidentForm?.clearedClinicTime),
          },
        ],
      },
      {
        name: 'Incident Details',
        fields: [
          {
            name: 'location',
            label: 'Location of Injury/Illness',
            path: 'incidentForm.location',
            elementId: 'incidentLocation',
            isCompleted: (data) => hasValue(data.incidentForm?.location),
          },
          {
            name: 'injuryClassifiedByName',
            label: 'Classified By (Name)',
            path: 'incidentForm.injuryClassifiedByName',
            elementId: 'injuryClassifiedByFirstName',
            isCompleted: (data) => hasValue(data.incidentForm?.injuryClassifiedByName),
          },
          {
            name: 'injuryClassification',
            label: 'Classification',
            path: 'incidentForm.injuryClassification',
            elementId: 'injuryClassification',
            isCompleted: (data) => hasValue(data.incidentForm?.injuryClassification),
          },
        ],
      },
      {
        name: 'Provider Information',
        fields: [
          {
            name: 'leadProvider',
            label: 'Lead Provider (min 1)',
            path: 'providers',
            elementId: 'provider-info',
            isCompleted: (data) => hasLeadProvider(data.providers),
          },
        ],
      },
    ],
  },

  // ==================== PATIENT TAB ====================
  {
    tabId: 'patient',
    tabName: 'Patient',
    sections: [
      {
        name: 'Demographics',
        fields: [
          {
            name: 'firstName',
            label: 'First Name',
            path: 'patientForm.firstName',
            elementId: 'firstName',
            isCompleted: (data) => hasValue(data.patientForm?.firstName),
          },
          {
            name: 'lastName',
            label: 'Last Name',
            path: 'patientForm.lastName',
            elementId: 'lastName',
            isCompleted: (data) => hasValue(data.patientForm?.lastName),
          },
          {
            name: 'dob',
            label: 'Date of Birth',
            path: 'patientForm.dob',
            elementId: 'dob',
            isCompleted: (data) => hasValue(data.patientForm?.dob),
          },
          {
            name: 'phone',
            label: 'Phone Number',
            path: 'patientForm.phone',
            elementId: 'phone',
            isCompleted: (data) => hasValue(data.patientForm?.phone),
            conditionallyRequired: (data) => data.patientForm?.optInNotifications === true,
          },
          {
            name: 'email',
            label: 'Email Address',
            path: 'patientForm.email',
            elementId: 'email',
            isCompleted: (data) => hasValue(data.patientForm?.email),
            conditionallyRequired: (data) => data.patientForm?.optInNotifications === true,
          },
        ],
      },
      {
        name: 'Home Address',
        fields: [
          {
            name: 'streetAddress',
            label: 'Street Address',
            path: 'patientForm.streetAddress',
            elementId: 'streetAddress',
            isCompleted: (data) => hasValue(data.patientForm?.streetAddress),
          },
          {
            name: 'city',
            label: 'City',
            path: 'patientForm.city',
            elementId: 'city',
            isCompleted: (data) => hasValue(data.patientForm?.city),
          },
          {
            name: 'state',
            label: 'State',
            path: 'patientForm.state',
            elementId: 'state',
            isCompleted: (data) => hasValue(data.patientForm?.state),
          },
        ],
      },
      {
        name: 'Employment',
        fields: [
          {
            name: 'employer',
            label: 'Employer',
            path: 'patientForm.employer',
            elementId: 'employer',
            isCompleted: (data) => hasValue(data.patientForm?.employer),
          },
          {
            name: 'supervisorName',
            label: 'Supervisor Name',
            path: 'patientForm.supervisorName',
            elementId: 'supervisorName',
            isCompleted: (data) => hasValue(data.patientForm?.supervisorName),
          },
          {
            name: 'supervisorPhone',
            label: 'Supervisor Phone',
            path: 'patientForm.supervisorPhone',
            elementId: 'supervisorPhone',
            isCompleted: (data) => hasValue(data.patientForm?.supervisorPhone),
          },
        ],
      },
      {
        name: 'Medical History',
        fields: [
          {
            name: 'medicalHistory',
            label: 'Medical History',
            path: 'patientForm.medicalHistory',
            elementId: 'medicalHistory',
            isCompleted: (data) => hasValue(data.patientForm?.medicalHistory),
          },
          {
            name: 'allergies',
            label: 'Allergies',
            path: 'patientForm.allergies',
            elementId: 'allergies',
            isCompleted: (data) => hasValue(data.patientForm?.allergies),
          },
          {
            name: 'currentMedications',
            label: 'Current Medications',
            path: 'patientForm.currentMedications',
            elementId: 'currentMedications',
            isCompleted: (data) => hasValue(data.patientForm?.currentMedications),
          },
        ],
      },
    ],
  },

  // ==================== ASSESSMENTS TAB ====================
  {
    tabId: 'assessments',
    tabName: 'Assessments',
    sections: [
      {
        name: 'Assessment Requirements',
        fields: [
          {
            name: 'minimumAssessment',
            label: 'Minimum 1 Assessment',
            path: 'assessments',
            elementId: 'assessment-content',
            isCompleted: (data) => hasMinimumAssessments(data.assessments),
          },
        ],
      },
    ],
  },

  // ==================== VITALS TAB ====================
  {
    tabId: 'vitals',
    tabName: 'Vitals',
    sections: [
      {
        name: 'Required Vitals (min 1 complete set)',
        fields: [
          {
            name: 'time',
            label: 'Time',
            path: 'vitals[0].time',
            elementId: 'vitals-table',
            isCompleted: (data) => data.vitals?.some(v => hasValue(v.time)) || false,
          },
          {
            name: 'date',
            label: 'Date',
            path: 'vitals[0].date',
            elementId: 'vitals-table',
            isCompleted: (data) => data.vitals?.some(v => hasValue(v.date)) || false,
          },
          {
            name: 'avpu',
            label: 'AVPU',
            path: 'vitals[0].avpu',
            elementId: 'vitals-table',
            isCompleted: (data) => data.vitals?.some(v => hasValue(v.avpu)) || false,
          },
          {
            name: 'bp',
            label: 'Blood Pressure',
            path: 'vitals[0].bp',
            elementId: 'vitals-table',
            isCompleted: (data) => data.vitals?.some(v => hasValue(v.bp)) || false,
          },
          {
            name: 'bpTaken',
            label: 'BP Method',
            path: 'vitals[0].bpTaken',
            elementId: 'vitals-table',
            isCompleted: (data) => data.vitals?.some(v => hasValue(v.bpTaken)) || false,
          },
          {
            name: 'pulse',
            label: 'Pulse',
            path: 'vitals[0].pulse',
            elementId: 'vitals-table',
            isCompleted: (data) => data.vitals?.some(v => hasValue(v.pulse)) || false,
          },
          {
            name: 'respiration',
            label: 'Respiratory Rate',
            path: 'vitals[0].respiration',
            elementId: 'vitals-table',
            isCompleted: (data) => data.vitals?.some(v => hasValue(v.respiration)) || false,
          },
          {
            name: 'gcs',
            label: 'GCS',
            path: 'vitals[0].gcsTotal',
            elementId: 'vitals-table',
            isCompleted: (data) => data.vitals?.some(v => hasValue(v.gcsTotal)) || false,
          },
        ],
      },
    ],
  },

  // ==================== TREATMENT TAB ====================
  {
    tabId: 'treatment',
    tabName: 'Treatment',
    sections: [
      {
        name: 'Interventions',
        fields: [], // No required fields for treatment tab
      },
    ],
  },

  // ==================== NARRATIVE TAB ====================
  {
    tabId: 'narrative',
    tabName: 'Narrative',
    sections: [
      {
        name: 'Clinical Narrative',
        fields: [
          {
            name: 'narrative',
            label: 'Narrative (min 25 chars)',
            path: 'narrative',
            elementId: 'clinical-narrative',
            isCompleted: (data) => hasMinimumNarrativeLength(data.narrative),
          },
        ],
      },
    ],
  },

  // ==================== DISPOSITION TAB ====================
  {
    tabId: 'disposition',
    tabName: 'Disposition',
    sections: [
      {
        name: 'Disposition',
        fields: [], // Will have SMS reminder and appointment features, but no required fields per spec
      },
    ],
  },

  // ==================== SIGNATURES TAB ====================
  {
    tabId: 'signatures',
    tabName: 'Signatures',
    sections: [
      {
        name: 'Disclosures & Signatures',
        fields: [
          {
            name: 'disclosures',
            label: 'Disclosures Acknowledged',
            path: 'disclosures',
            elementId: 'disclosures',
            isCompleted: (data) => hasAllDisclosuresAcknowledged(data.disclosures),
          },
        ],
      },
    ],
  },
];

// Helper function to get tab configuration by ID
export const getTabRequiredFields = (tabId: string): TabRequiredFields | undefined => {
  return REQUIRED_FIELDS_BY_TAB.find(tab => tab.tabId === tabId);
};

// Helper function to get all tabs
export const getAllTabs = (): TabRequiredFields[] => {
  return REQUIRED_FIELDS_BY_TAB;
};

// Helper function to calculate completion status for a tab
export const calculateTabCompletion = (tabId: string, encounterData: EncounterData): {
  completed: number;
  total: number;
  percentage: number;
  isComplete: boolean;
} => {
  const tabConfig = getTabRequiredFields(tabId);
  if (!tabConfig) {
    return { completed: 0, total: 0, percentage: 100, isComplete: true };
  }

  let totalFields = 0;
  let completedFields = 0;

  tabConfig.sections.forEach(section => {
    section.fields.forEach(field => {
      // Check if the field is conditionally required
      const isRequired = field.conditionallyRequired 
        ? field.conditionallyRequired(encounterData) 
        : true;
      
      if (isRequired) {
        totalFields++;
        if (field.isCompleted(encounterData)) {
          completedFields++;
        }
      }
    });
  });

  const percentage = totalFields > 0 ? Math.round((completedFields / totalFields) * 100) : 100;
  const isComplete = totalFields === 0 || completedFields === totalFields;

  return { completed: completedFields, total: totalFields, percentage, isComplete };
};
