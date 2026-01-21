import { createContext, useContext, useState, ReactNode } from 'react';

// Type definitions for tab data
export interface VitalSet {
  id: string;
  time: string;
  bp: string;
  pulse: string;
  resp?: string;
  respiration?: string;
  spo2: string;
  temp: string;
  painLevel?: string;
  pain?: string;
  gcs?: string;
  bloodGlucose?: string;
  glucose?: string;
  notes?: string;
  // Extended fields from VitalsEntryModal
  date?: string;
  avpu?: string;
  bpSystolic?: string;
  bpDiastolic?: string;
  bpMethod?: string;
  pulseMethod?: string;
  spo2NotAvailable?: boolean;
  respirationQuality?: string;
  tempMethod?: string;
  gcsEye?: string;
  gcsVerbal?: string;
  gcsMotor?: string;
  gcsTotal?: string;
}

// Assessment with regions object (used in UI)
export interface AssessmentWithRegions {
  id: string;
  time?: string;
  editableTime?: string;
  regions: Record<string, string>;
}

// Legacy single-region assessment (for backwards compatibility)
export interface Assessment {
  id: string;
  region: string;
  findings: string;
  notes?: string;
  timestamp: string;
  // Extended fields to support regions object pattern
  time?: string;
  editableTime?: string;
  regions?: Record<string, string>;
}

export interface Treatment {
  id: string;
  type: string;
  description: string;
  time: string;
  provider?: string;
  notes?: string;
}

export interface Disclosure {
  id: string;
  title: string;
  content: string;
  required: boolean;
  conditionalOn?: string;
  acknowledged?: boolean;
}

interface EncounterContextType {
  activeEncounter: any | null;
  setActiveEncounter: (encounter: any | null) => void;
  activeTab: string;
  setActiveTab: (tab: string) => void;
  incidentForm: any;
  setIncidentForm: (form: any) => void;
  patientForm: any;
  setPatientForm: (form: any) => void;
  disposition: string;
  setDisposition: (value: string) => void;
  dispositionNotes: string;
  setDispositionNotes: (value: string) => void;
  providerSignature: string;
  setProviderSignature: (value: string) => void;
  witnessSignature: string;
  setWitnessSignature: (value: string) => void;
  expectedFollowUpDate: string;
  setExpectedFollowUpDate: (value: string) => void;
  sendSms: boolean;
  setSendSms: (value: boolean) => void;
  appointments: any[];
  setAppointments: (value: any[]) => void;
  
  // Server encounter ID - used to track whether encounter has been saved to backend
  serverEncounterId: string | null;
  setServerEncounterId: (id: string | null) => void;
  
  // New state for tabs - fixing data persistence issues
  vitals: VitalSet[];
  setVitals: (vitals: VitalSet[]) => void;
  
  assessments: Assessment[];
  setAssessments: (assessments: Assessment[]) => void;
  
  treatments: Treatment[];
  setTreatments: (treatments: Treatment[]) => void;
  
  narrative: string;
  setNarrative: (narrative: string) => void;
  
  disclosures: Disclosure[];
  setDisclosures: (disclosures: Disclosure[]) => void;
  disclosuresAcknowledged: boolean;
  setDisclosuresAcknowledged: (acknowledged: boolean) => void;
}

const EncounterContext = createContext<EncounterContextType | null>(null);

export function EncounterProvider({ children }: { children: ReactNode }) {
  const [activeEncounter, setActiveEncounter] = useState<any | null>(null);
  const [activeTab, setActiveTab] = useState('incident');
  const [incidentForm, setIncidentForm] = useState({
    mayDayTime: '',
    patientContactTime: '',
    transferOfCareTime: '',
    clearedClinicTime: '',
    clinicName: '',
    clinicStreetAddress: '',
    clinicCity: '',
    clinicState: '',
    clinicCounty: '',
    clinicUnitNumber: '',
    providerName: '',
    providerRole: '',
    massCasualty: 'no', // Default to "NO"
    location: '',
    injuryClassifiedBy: '',
    injuryClassifiedByFirstName: '',
    injuryClassifiedByLastName: '',
    injuryClassification: '', // Personal or Work Related
    natureOfIllness: '',
    natureOfIllnessOtherDetails: '', // When "Other" is selected
    mechanismofinjury: '',
    mechanismofinjuryOtherDetails: '', // When "Other" is selected
  });
  const [patientForm, setPatientForm] = useState({
    id: Date.now().toString(), // Unique patient ID
    firstName: '',
    lastName: '',
    dob: '',
    sex: '',
    employeeId: '',
    ssn: '',
    dlNumber: '',
    dlState: '',
    phone: '',
    email: '',
    streetAddress: '',
    city: '',
    county: '',
    state: '',
    country: 'USA',
    unitNumber: '',
    employer: '',
    supervisorName: '',
    supervisorPhone: '',
    medicalHistory: '',
    allergies: '',
    currentMedications: '',
    pcpName: '',
    pcpPhone: '',
    pcpEmail: '',
  });
  const [disposition, setDisposition] = useState('');
  const [dispositionNotes, setDispositionNotes] = useState('');
  const [providerSignature, setProviderSignature] = useState('');
  const [witnessSignature, setWitnessSignature] = useState('');
  const [expectedFollowUpDate, setExpectedFollowUpDate] = useState('');
  const [sendSms, setSendSms] = useState(false);
  const [appointments, setAppointments] = useState<any[]>([]);
  
  // Server encounter ID - used to track whether encounter has been saved to backend
  const [serverEncounterId, setServerEncounterId] = useState<string | null>(null);
  
  // New state for tabs - fixing data persistence issues
  const [vitals, setVitals] = useState<VitalSet[]>([]);
  const [assessments, setAssessments] = useState<Assessment[]>([]);
  const [treatments, setTreatments] = useState<Treatment[]>([]);
  const [narrative, setNarrative] = useState('');
  const [disclosures, setDisclosures] = useState<Disclosure[]>([]);
  const [disclosuresAcknowledged, setDisclosuresAcknowledged] = useState(false);

  return (
    <EncounterContext.Provider
      value={{
        activeEncounter,
        setActiveEncounter,
        activeTab,
        setActiveTab,
        incidentForm,
        setIncidentForm,
        patientForm,
        setPatientForm,
        disposition,
        setDisposition,
        dispositionNotes,
        setDispositionNotes,
        providerSignature,
        setProviderSignature,
        witnessSignature,
        setWitnessSignature,
        expectedFollowUpDate,
        setExpectedFollowUpDate,
        sendSms,
        setSendSms,
        appointments,
        setAppointments,
        // Server encounter ID - used to track whether encounter has been saved to backend
        serverEncounterId,
        setServerEncounterId,
        // New state for tabs - fixing data persistence issues
        vitals,
        setVitals,
        assessments,
        setAssessments,
        treatments,
        setTreatments,
        narrative,
        setNarrative,
        disclosures,
        setDisclosures,
        disclosuresAcknowledged,
        setDisclosuresAcknowledged,
      }}
    >
      {children}
    </EncounterContext.Provider>
  );
}

export function useEncounter() {
  const context = useContext(EncounterContext);
  if (!context) {
    return null;
  }
  return context;
}