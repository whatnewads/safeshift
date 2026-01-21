import { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { useParams, useNavigate, useLocation, useSearchParams } from 'react-router-dom';
import { useOffline } from '../../hooks/useOffline.js';
import { useAuth } from '../../hooks/useAuth.js';
import { useNarrative } from '../../hooks/useNarrative.js';
import { VitalsEntryModal } from '../../components/vitals/VitalsEntryModal';
import { MentalStatusAssessment } from '../../components/assessments/MentalStatusAssessment';
import { NeurologicalAssessment } from '../../components/assessments/NeurologicalAssessment';
import { SkinAssessment } from '../../components/assessments/SkinAssessment';
import { HEENTAssessment } from '../../components/assessments/HEENTAssessment';
import { ChestAssessment } from '../../components/assessments/ChestAssessment';
import { AbdomenAssessment } from '../../components/assessments/AbdomenAssessment';
import { BackAssessment } from '../../components/assessments/BackAssessment';
import { PelvisGUGIAssessment } from '../../components/assessments/PelvisGUGIAssessment';
import { ExtremitiesAssessment } from '../../components/assessments/ExtremitiesAssessment';
import { BodyModelModal } from '../../components/assessments/BodyModelModal';
import { AssessmentModal } from '../../components/assessments/AssessmentModal';
import { MobileEncounterNav } from '../../components/encounter/MobileEncounterNav';
import { useEncounter } from '../../contexts/EncounterContext';
import { useShift } from '../../contexts/ShiftContext';
import { SidebarRequiredFields } from '../../components/encounter/SidebarRequiredFields';
import { ValidationErrors } from '../../components/encounter/ValidationErrors.js';
import { createEncounterData } from '../../hooks/useRequiredFields.js';
import { ObjectiveFindingsTab } from '../../components/encounter/ObjectiveFindingsTab';
import {
  validateEncounter,
  getFirstInvalidTab,
  type ValidationError,
  type ValidationResult,
} from '../../services/validation.service.js';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import { Badge } from '../../components/ui/badge';
import { Card } from '../../components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../components/ui/select';
import {
  Save,
  Send,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  Circle,
  WifiOff,
  ArrowLeft,
  Sparkles,
  Clock,
  Plus,
  Trash2,
  X,
  FileText,
  User,
  ClipboardList,
  Activity,
  Pill,
  AlignLeft,
  FileCheck,
  PenTool,
  Stethoscope,
  Camera,
  MessageSquare,
  Image,
  ChevronRight,
  ChevronDown,
  Eye,
} from 'lucide-react';
import { toast } from 'sonner';
import SignatureCanvas from 'react-signature-canvas';
import { sendSMSReminder, validatePhoneNumber, canSendSMS as _canSendSMS } from '../../services/sms.service.js';
import { uploadAppointmentDocument, validateFileType, validateFileSize, ALLOWED_FILE_TYPES, MAX_FILE_SIZE } from '../../services/document.service.js';
import {
  getDisclosureTemplates,
  recordBatchDisclosureAcknowledgments,
  filterApplicableDisclosures,
  areAllDisclosuresAcknowledged,
  prepareDisclosureRequests,
  type DisclosureTemplate,
} from '../../services/disclosure.service.js';

// Tab status types
type TabStatus = 'empty' | 'partial' | 'complete' | 'error';

interface TabData {
  id: string;
  label: string;
  status: TabStatus;
}

interface VitalRow {
  id: string;
  time: string;
  bp: string;
  hr: string;
  rr: string;
  temp: string;
  spo2: string;
  pain: string;
  avpu: string;
  gcs: string;
  loc: string;
  bloodSugar: string;
}

export default function EncounterWorkspacePage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const [searchParams] = useSearchParams();
  const encounterContext = useEncounter();
  
  // Detect read-only mode from URL query parameter
  const isReadOnly = searchParams.get('mode') === 'readonly';
  const { shiftData: contextShiftData } = useShift(); // Get shift data from ShiftContext
  const { isOnline, saveOffline, offlineCount } = useOffline();
  const [encounterStatus] = useState<'draft' | 'in-progress' | 'submitted'>('in-progress');
  const [autoSaving, setAutoSaving] = useState(false);
  const [assessmentSubTab, setAssessmentSubTab] = useState('traumatic');
  
  // Modal visibility state for ObjectiveFindingsTab
  const [showOFVitalsModal, setShowOFVitalsModal] = useState(false);
  const [showOFAssessmentModal, setShowOFAssessmentModal] = useState(false);
  const [showOFTreatmentModal, setShowOFTreatmentModal] = useState(false);
  const [editingOFVital, setEditingOFVital] = useState<any>(null);
  const [editingOFAssessment, setEditingOFAssessment] = useState<any>(null);
  
  // Validation state
  const [validationErrors, setValidationErrors] = useState<ValidationError[]>([]);
  const [showValidationModal, setShowValidationModal] = useState(false);
  const [_validationResult, setValidationResult] = useState<ValidationResult | null>(null);

  // Extract prefilled data from location state (moved before conditional)
  const prefilledData = location.state || {};
  const { patientData, shiftData: navShiftData, incidentHistory: _incidentHistory, followUp } = prefilledData;
  
  // Use shift data from context first, fall back to navigation state for backwards compatibility
  const shiftData = contextShiftData || navShiftData;

  // Tab configuration with status
  // Note: assessments, vitals, treatment tabs have been combined into "objectiveFindings"
  const [tabs, _setTabs] = useState<TabData[]>([
    { id: 'incident', label: 'Incident', status: 'empty' },
    { id: 'patient', label: 'Patient', status: patientData ? 'partial' : 'empty' },
    { id: 'objectiveFindings', label: 'Objective Findings', status: 'empty' },
    { id: 'narrative', label: 'Narrative', status: 'empty' },
    { id: 'disposition', label: 'Disposition', status: 'empty' },
    { id: 'signatures', label: 'Signatures', status: 'empty' },
  ]);

  // Vitals state
  const [vitalRows, setVitalRows] = useState<VitalRow[]>([
    {
      id: '1',
      time: new Date().toISOString().slice(0, 16),
      bp: '',
      hr: '',
      rr: '',
      temp: '',
      spo2: '',
      pain: '',
      avpu: '',
      gcs: '',
      loc: '',
      bloodSugar: '',
    },
  ]);

  // State for sidebar required fields tracking
  const [providers, setProviders] = useState<Array<{ id: string; name: string; role: string }>>([]);
  const [assessments, setAssessments] = useState<any[]>([]);
  const [vitalsData, setVitalsData] = useState<any[]>([]);
  const [narrativeText, setNarrativeText] = useState('');
  const [disclosureAcknowledgments, setDisclosureAcknowledgments] = useState<Record<string, boolean>>({});

  // Store setActiveEncounter in a ref to avoid stale closure issues
  const setActiveEncounterRef = useRef(encounterContext?.setActiveEncounter);
  setActiveEncounterRef.current = encounterContext?.setActiveEncounter;

  // Set the active encounter when component mounts - use empty deps to run only on mount/unmount
  useEffect(() => {
    // Set active encounter with the encounter ID (not the entire context)
    if (setActiveEncounterRef.current) {
      setActiveEncounterRef.current({ encounterId: id });
    }
    return () => {
      // Clear active encounter when unmounting (navigating away)
      if (setActiveEncounterRef.current) {
        setActiveEncounterRef.current(null);
      }
    };
  }, []); // Empty dependency array - only run on mount/unmount

  // Show toast if this is a follow-up encounter
  useEffect(() => {
    if (followUp && patientData) {
      toast.info(`Follow-up encounter for ${patientData.firstName} ${patientData.lastName}`);
    }
    if (shiftData) {
      toast.success(`Shift data loaded: ${shiftData.clinicName}`);
    }
  }, [followUp, patientData, shiftData]);

  // If context is not available, show error (moved after all hooks)
  if (!encounterContext) {
    return <div>Error: Encounter context not available</div>;
  }

  const {
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
    setActiveEncounter: _setActiveEncounter,
    expectedFollowUpDate,
    setExpectedFollowUpDate,
    sendSms,
    setSendSms,
    appointments,
    setAppointments,
    serverEncounterId,
    setServerEncounterId,
  } = encounterContext;

  // Helper function to get the next tab in order
  const getNextTab = (currentTab: string): string | null => {
    const tabOrder = ['incident', 'patient', 'objectiveFindings', 'narrative', 'disposition', 'signatures'];
    const currentIndex = tabOrder.indexOf(currentTab);
    return currentIndex < tabOrder.length - 1 ? tabOrder[currentIndex + 1] : null;
  };

  // Reusable Next Tab Button component
  const NextTabButton = () => {
    const nextTab = getNextTab(activeTab);
    if (!nextTab) return null;
    
    return (
      <div className="mt-6 pt-6 border-t border-slate-200 flex justify-end">
        <button
          onClick={() => setActiveTab(nextTab)}
          className="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors"
        >
          Move on to Next Tab
          <svg className="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    );
  };

  // Create encounter data object for sidebar required fields
  const encounterData = useMemo(() => createEncounterData(
    incidentForm,
    patientForm,
    providers,
    assessments,
    vitalsData,
    narrativeText,
    disposition,
    disclosureAcknowledgments
  ), [incidentForm, patientForm, providers, assessments, vitalsData, narrativeText, disposition, disclosureAcknowledgments]);

  // Handler for field clicks in the sidebar - enhanced with tab switching
  const handleFieldClick = (fieldName: string, path: string) => {
    // Determine which tab the field belongs to based on the path
    const pathToTabMap: Record<string, string> = {
      'incidentForm': 'incident',
      'patientForm': 'patient',
      'providers': 'incident',
      'assessments': 'objectiveFindings',
      'vitals': 'objectiveFindings',
      'treatments': 'objectiveFindings',
      'narrative': 'narrative',
      'disposition': 'disposition',
      'disclosures': 'signatures',
    };
    
    // Get the tab from the path
    const pathPrefix = path?.split('.')[0] || '';
    const targetTab = pathToTabMap[pathPrefix];
    
    // Switch to the correct tab first
    if (targetTab && activeTab !== targetTab) {
      setActiveTab(targetTab);
    }
    
    // Use setTimeout to allow DOM to update after tab switch
    setTimeout(() => {
      // Try to find the element by ID
      const sectionId = path?.split('.')[0] || '';
      const element = document.getElementById(fieldName) || (sectionId ? document.getElementById(sectionId) : null);
      
      if (element) {
        // Smooth scroll to element
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add visual highlight effect
        element.classList.add('ring-2', 'ring-blue-500', 'ring-offset-2');
        
        // Try to focus if it's an input
        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
          element.focus();
        }
        
        // Remove highlight after 2 seconds
        setTimeout(() => {
          element.classList.remove('ring-2', 'ring-blue-500', 'ring-offset-2');
        }, 2000);
      }
    }, 100); // Small delay for tab transition
  };

  // Mock patient data
  const patient = {
    name: patientData ? `${patientData.firstName} ${patientData.lastName}` : 'New Patient',
    mrn: 'MRN-2024-0156',
    encounterId: id || 'ENC-2024-0234',
  };

  const handleSaveDraft = useCallback(async () => {
    setAutoSaving(true);
    
    try {
      // Build encounter data payload for API
      // Include both camelCase and snake_case versions for backend compatibility
      const encounterPayload = {
        // Patient information - include both formats for backend compatibility
        patientId: patientForm.id || null,
        patient_id: patientForm.id || null, // Backend expects snake_case
        patientFirstName: patientForm.firstName,
        patient_first_name: patientForm.firstName,
        patientLastName: patientForm.lastName,
        patient_last_name: patientForm.lastName,
        patientDob: patientForm.dob,
        patient_dob: patientForm.dob,
        patientSsn: patientForm.ssn,
        patient_ssn: patientForm.ssn,
        patientPhone: patientForm.phone,
        patient_phone: patientForm.phone,
        patientEmail: patientForm.email,
        patient_email: patientForm.email,
        patientAddress: patientForm.streetAddress,
        patient_address: patientForm.streetAddress,
        patientCity: patientForm.city,
        patient_city: patientForm.city,
        patientState: patientForm.state,
        patient_state: patientForm.state,
        patientEmployer: patientForm.employer,
        patient_employer: patientForm.employer,
        
        // Incident information
        clinicName: incidentForm.clinicName,
        clinic_name: incidentForm.clinicName,
        clinicAddress: incidentForm.clinicStreetAddress,
        clinic_address: incidentForm.clinicStreetAddress,
        clinicCity: incidentForm.clinicCity,
        clinic_city: incidentForm.clinicCity,
        clinicState: incidentForm.clinicState,
        clinic_state: incidentForm.clinicState,
        patientContactTime: incidentForm.patientContactTime,
        patient_contact_time: incidentForm.patientContactTime,
        clearedClinicTime: incidentForm.clearedClinicTime,
        cleared_clinic_time: incidentForm.clearedClinicTime,
        location: incidentForm.location,
        massCasualty: incidentForm.massCasualty,
        mass_casualty: incidentForm.massCasualty,
        injuryClassification: incidentForm.injuryClassification,
        injury_classification: incidentForm.injuryClassification,
        natureOfIllness: incidentForm.natureOfIllness,
        nature_of_illness: incidentForm.natureOfIllness,
        mechanismOfInjury: incidentForm.mechanismofinjury,
        mechanism_of_injury: incidentForm.mechanismofinjury,
        
        // Clinical data
        narrative: narrativeText,
        disposition: disposition,
        dispositionNotes: dispositionNotes,
        disposition_notes: dispositionNotes,
        
        // Status
        status: 'draft',
        encounterType: 'clinical',
        encounter_type: 'clinical',
        
        // Include all form data for complete save
        formData: {
          incidentForm,
          patientForm,
          providers,
          assessments,
          vitalsData,
          narrativeText,
          disposition,
          dispositionNotes,
          disclosureAcknowledgments,
        },
      };
      
      // Debug: Log the payload being sent
      console.log('[EHR] [SAVE_DRAFT] Encounter payload:', JSON.stringify(encounterPayload, null, 2));
      
      console.log('[EHR] [SAVE_DRAFT] Starting save operation (offline-first)', {
        hasServerEncounterId: !!serverEncounterId,
        serverEncounterId,
        urlId: id,
        isOnline,
      });
      
      // STEP 1: ALWAYS save to offline storage first (offline-first approach)
      // This ensures data is never lost, even if server save fails
      const offlinePayload = {
        ...encounterPayload,
        _offlineStatus: 'draft',
        _savedAt: new Date().toISOString(),
        _attemptedSubmit: false,
      };
      
      const localEncounterId = serverEncounterId || id || null;
      await saveOffline(localEncounterId, offlinePayload);
      console.log('[EHR] [SAVE_DRAFT] Saved to offline storage successfully');
      
      // STEP 2: If online, also save to server
      if (isOnline) {
        try {
          // Import encounter service dynamically to avoid circular deps
          const { encounterService } = await import('../../services/encounter.service');
          
          if (serverEncounterId) {
            // SUBSEQUENT SAVE: Update existing encounter
            console.log('[EHR] [SAVE_DRAFT] Updating existing encounter on server:', serverEncounterId);
            
            const updatedEncounter = await encounterService.updateEncounter(serverEncounterId, encounterPayload as any);
            
            console.log('[EHR] [SAVE_DRAFT] Encounter updated successfully:', {
              encounterId: updatedEncounter.id || (updatedEncounter as any).encounter_id,
            });
            
            toast.success('Saved and synced to server');
          } else {
            // FIRST SAVE: Create new encounter
            console.log('[EHR] [SAVE_DRAFT] Creating new encounter on server');
            
            const newEncounter = await encounterService.createEncounter(encounterPayload as any);
            
            // Extract the server-generated ID
            const newServerId = newEncounter.id || (newEncounter as any).encounter_id;
            
            if (!newServerId) {
              throw new Error('Server did not return encounter ID');
            }
            
            console.log('[EHR] [SAVE_DRAFT] Encounter created successfully:', {
              newServerId,
              previousUrlId: id,
            });
            
            // Store the server ID in context
            setServerEncounterId(newServerId);
            
            // Update the URL to use the real server ID (replace temporary ID)
            navigate(`/encounters/${newServerId}`, { replace: true });
            
            toast.success('Encounter created and synced to server');
          }
        } catch (serverError) {
          // Server save failed, but data is safely stored offline
          console.error('[EHR] [SAVE_DRAFT] Server save failed, data saved locally:', serverError);
          
          if (serverError instanceof Error) {
            if (serverError.message.includes('401') || serverError.message.includes('Unauthorized')) {
              toast.error('Session expired. Data saved locally - please log in again.');
            } else {
              toast.warning('Saved locally. Server sync failed - will retry when online.');
            }
          } else {
            toast.warning('Saved locally. Will sync when connection is restored.');
          }
        }
      } else {
        // Offline - data already saved locally
        toast.info('Saved locally (offline mode). Will sync when online.');
      }
    } catch (error) {
      console.error('[EHR] [SAVE_DRAFT] Save failed completely:', error);
      
      // Handle error - even offline save failed (very rare)
      if (error instanceof Error) {
        toast.error(`Save failed: ${error.message}`);
      } else {
        toast.error('Failed to save draft. Please try again.');
      }
    } finally {
      setAutoSaving(false);
    }
  }, [
    serverEncounterId,
    setServerEncounterId,
    navigate,
    id,
    patientForm,
    incidentForm,
    narrativeText,
    disposition,
    dispositionNotes,
    providers,
    assessments,
    vitalsData,
    disclosureAcknowledgments,
    isOnline,
    saveOffline,
  ]);

  const handleValidate = useCallback(() => {
    // Run validation and show results
    const result = validateEncounter(encounterData);
    setValidationResult(result);
    
    if (result.isValid) {
      toast.success(`Validation passed! All required fields complete (${result.completionPercentage.toFixed(0)}%)`);
    } else {
      setValidationErrors(result.errors);
      
      // Show toast with summary
      const errorCount = result.errors.length;
      const firstErrors = result.errors.slice(0, 3).map(e => e.label).join(', ');
      toast.error(
        `${errorCount} validation error${errorCount > 1 ? 's' : ''}: ${firstErrors}${errorCount > 3 ? '...' : ''}`,
        {
          action: {
            label: 'View All',
            onClick: () => setShowValidationModal(true),
          },
        }
      );
      
      // Navigate to first invalid tab
      const firstInvalidTab = getFirstInvalidTab(encounterData);
      if (firstInvalidTab) {
        setActiveTab(firstInvalidTab);
      }
    }
  }, [encounterData, setActiveTab]);

  // State for submission loading
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = useCallback(async () => {
    // 1. Run client-side validation before submission
    const result = validateEncounter(encounterData);
    setValidationResult(result);
    
    // 2. If validation fails, show errors and do NOT proceed
    if (!result.isValid) {
      setValidationErrors(result.errors);
      setShowValidationModal(true);
      
      // Navigate to first invalid tab
      const firstInvalidTab = getFirstInvalidTab(encounterData);
      if (firstInvalidTab) {
        setActiveTab(firstInvalidTab);
      }
      
      // Toast notification with completion percentage
      toast.error(
        `Please complete all required fields (${result.completionPercentage.toFixed(0)}% complete)`,
        {
          description: `${result.errors.length} field${result.errors.length > 1 ? 's' : ''} need${result.errors.length === 1 ? 's' : ''} attention`,
          action: {
            label: 'View Errors',
            onClick: () => setShowValidationModal(true),
          },
        }
      );
      
      return; // DO NOT proceed with submission
    }
    
    // 3. Set loading state to prevent double-submissions
    setIsSubmitting(true);
    
    // Build submission payload with offline flags
    const localEncounterId = serverEncounterId || id || `temp_${Date.now()}`;
    
    console.log('[EHR] [SUBMIT] Starting submission (offline-first)', {
      serverEncounterId,
      urlId: id,
      localEncounterId,
      isOnline,
    });
    
    try {
      // 4. ALWAYS save to offline storage first (offline-first approach)
      // This ensures data is never lost, even if submission fails
      const offlineSubmissionPayload = {
        ...encounterData,
        status: 'pending_submission',
        _offlineStatus: 'pending_submission',
        _savedAt: new Date().toISOString(),
        _attemptedSubmit: true,
        _submittedAt: new Date().toISOString(),
      };
      
      await saveOffline(localEncounterId, offlineSubmissionPayload);
      console.log('[EHR] [SUBMIT] Saved to offline storage with pending_submission status');
      
      // 5. Check online status - if offline, we're done (data saved for later sync)
      if (!isOnline) {
        toast.success('📱 Report saved for submission when online', {
          description: 'Your encounter will be automatically submitted when connection is restored.',
          duration: 5000,
        });
        setIsSubmitting(false);
        // Navigate to dashboard - user can see their pending submissions there
        setTimeout(() => {
          navigate('/dashboard');
        }, 1500);
        return;
      }
      
      // 6. ONLINE: Try to submit to server
      // First, ensure we have a server ID by auto-saving if needed
      let submissionId = serverEncounterId;
      
      if (!submissionId || submissionId === 'new' || submissionId.startsWith('temp_')) {
        console.log('[EHR] [SUBMIT] No valid server encounter ID - creating encounter first');
        toast.info('Creating encounter...');
        
        try {
          // Import encounter service dynamically to avoid circular deps
          const { encounterService } = await import('../../services/encounter.service');
          
          // Build encounter payload for creation
          const createPayload = {
            patientId: patientForm.id || null,
            patient_id: patientForm.id || null,
            patientFirstName: patientForm.firstName,
            patient_first_name: patientForm.firstName,
            patientLastName: patientForm.lastName,
            patient_last_name: patientForm.lastName,
            patientDob: patientForm.dob,
            patient_dob: patientForm.dob,
            patientSsn: patientForm.ssn,
            patient_ssn: patientForm.ssn,
            patientPhone: patientForm.phone,
            patient_phone: patientForm.phone,
            patientEmail: patientForm.email,
            patient_email: patientForm.email,
            patientAddress: patientForm.streetAddress,
            patient_address: patientForm.streetAddress,
            patientCity: patientForm.city,
            patient_city: patientForm.city,
            patientState: patientForm.state,
            patient_state: patientForm.state,
            patientEmployer: patientForm.employer,
            patient_employer: patientForm.employer,
            clinicName: incidentForm.clinicName,
            clinic_name: incidentForm.clinicName,
            clinicAddress: incidentForm.clinicStreetAddress,
            clinic_address: incidentForm.clinicStreetAddress,
            clinicCity: incidentForm.clinicCity,
            clinic_city: incidentForm.clinicCity,
            clinicState: incidentForm.clinicState,
            clinic_state: incidentForm.clinicState,
            patientContactTime: incidentForm.patientContactTime,
            patient_contact_time: incidentForm.patientContactTime,
            clearedClinicTime: incidentForm.clearedClinicTime,
            cleared_clinic_time: incidentForm.clearedClinicTime,
            location: incidentForm.location,
            massCasualty: incidentForm.massCasualty,
            mass_casualty: incidentForm.massCasualty,
            injuryClassification: incidentForm.injuryClassification,
            injury_classification: incidentForm.injuryClassification,
            natureOfIllness: incidentForm.natureOfIllness,
            nature_of_illness: incidentForm.natureOfIllness,
            mechanismOfInjury: incidentForm.mechanismofinjury,
            mechanism_of_injury: incidentForm.mechanismofinjury,
            narrative: narrativeText,
            disposition: disposition,
            dispositionNotes: dispositionNotes,
            disposition_notes: dispositionNotes,
            status: 'draft',
            encounterType: 'clinical',
            encounter_type: 'clinical',
            formData: {
              incidentForm,
              patientForm,
              providers,
              assessments,
              vitalsData,
              narrativeText,
              disposition,
              dispositionNotes,
              disclosureAcknowledgments,
            },
          };
          
          const newEncounter = await encounterService.createEncounter(createPayload as any);
          submissionId = newEncounter.id || (newEncounter as any).encounter_id;
          
          if (submissionId) {
            setServerEncounterId(submissionId);
            navigate(`/encounters/${submissionId}`, { replace: true });
          }
        } catch (createError) {
          // Creation failed - data is already saved offline, so inform user
          console.error('[EHR] [SUBMIT] Failed to create encounter on server:', createError);
          toast.warning('📱 Report saved for submission when online', {
            description: 'Could not reach server. Your encounter will be submitted when connection is restored.',
            duration: 5000,
          });
          setIsSubmitting(false);
          setTimeout(() => {
            navigate('/dashboard');
          }, 1500);
          return;
        }
      }
      
      // 7. Now submit the encounter for review
      if (submissionId) {
        console.log('[EHR] [SUBMIT] Calling submitForReview with ID:', submissionId);
        const { encounterService } = await import('../../services/encounter.service');
        const response = await encounterService.submitForReview(submissionId, encounterData as any);
        
        // 8. Handle response
        if (response.success) {
          // SUCCESS: Update offline status and show success message
          console.log('[EHR] [SUBMIT] Submission successful:', response);
          
          // Update offline storage with submitted status
          const submittedPayload = {
            ...encounterData,
            status: 'submitted',
            _offlineStatus: 'synced',
            _savedAt: new Date().toISOString(),
            _attemptedSubmit: true,
            _submittedAt: new Date().toISOString(),
            _serverSyncedAt: new Date().toISOString(),
          };
          await saveOffline(submissionId, submittedPayload);
          
          toast.success('🎉 Encounter submitted successfully!', {
            description: 'Your encounter has been submitted for review.',
            duration: 4000,
            style: {
              backgroundColor: '#10b981', // green-500
              color: 'white',
              border: 'none',
            },
          });
          // Navigate to dashboard after successful submission
          setTimeout(() => {
            navigate('/dashboard');
          }, 1500);
        } else {
          // Server returned validation or other errors
          console.warn('[EHR] [SUBMIT] Submission failed:', response);
          toast.error(response.message || 'Failed to submit encounter');
          
          // If server returns validation errors, display them
          if (response.errors && Object.keys(response.errors).length > 0) {
            // Convert server errors to our error format
            const serverErrors: ValidationError[] = Object.entries(response.errors).map(([field, message]) => ({
              field,
              message: typeof message === 'string' ? message : String(message),
              label: typeof message === 'string' ? message : String(message),
              tabId: field.startsWith('incidentForm') ? 'incident' :
                     field.startsWith('patientForm') ? 'patient' :
                     field === 'providers' ? 'incident' :
                     field === 'assessments' ? 'objectiveFindings' :
                     field === 'vitals' ? 'objectiveFindings' :
                     field === 'treatments' ? 'objectiveFindings' :
                     field === 'narrative' ? 'narrative' :
                     field === 'disclosures' ? 'signatures' : 'incident',
              tabName: field.startsWith('incidentForm') ? 'Incident' :
                       field.startsWith('patientForm') ? 'Patient' :
                       field === 'providers' ? 'Incident' :
                       field === 'assessments' ? 'Objective Findings' :
                       field === 'vitals' ? 'Objective Findings' :
                       field === 'treatments' ? 'Objective Findings' :
                       field === 'narrative' ? 'Narrative' :
                       field === 'disclosures' ? 'Signatures' : 'Incident',
              sectionName: '',
              path: field,
            }));
            
            setValidationErrors(serverErrors);
            setShowValidationModal(true);
            
            // Navigate to first invalid tab
            if (serverErrors.length > 0) {
              setActiveTab(serverErrors[0].tabId);
            }
          }
        }
      } else {
        // No submission ID available - data is saved offline
        toast.warning('📱 Report saved for submission when online', {
          description: 'Your encounter will be submitted when connection is restored.',
          duration: 5000,
        });
        setTimeout(() => {
          navigate('/dashboard');
        }, 1500);
      }
    } catch (error) {
      // Network error or server error (500, etc.)
      console.error('[EHR] [SUBMIT] Submit encounter error:', error);
      
      // Data is already saved offline, so provide reassurance
      toast.warning('📱 Report saved for submission when online', {
        description: 'Could not reach server. Your encounter will be submitted when connection is restored.',
        duration: 5000,
      });
      setTimeout(() => {
        navigate('/dashboard');
      }, 1500);
    } finally {
      setIsSubmitting(false);
    }
  }, [
    encounterData,
    setActiveTab,
    id,
    serverEncounterId,
    setServerEncounterId,
    navigate,
    isOnline,
    saveOffline,
    patientForm,
    incidentForm,
    narrativeText,
    disposition,
    dispositionNotes,
    providers,
    assessments,
    vitalsData,
    disclosureAcknowledgments,
  ]);

  // Handler for clicking on validation errors
  const handleValidationErrorClick = useCallback((error: ValidationError) => {
    // Navigate to the tab containing the error
    setActiveTab(error.tabId);
    setShowValidationModal(false);
    
    // Try to scroll to and focus the field
    setTimeout(() => {
      const element = error.elementId
        ? document.getElementById(error.elementId)
        : document.getElementById(error.field);
      
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
          element.focus();
        }
      }
    }, 100);
  }, [setActiveTab]);

  // Calculate if form is valid for submit button state
  const isFormValid = useMemo(() => {
    const result = validateEncounter(encounterData);
    return result.isValid;
  }, [encounterData]);

  // Get completion percentage for UI display
  const completionPercentage = useMemo(() => {
    const result = validateEncounter(encounterData);
    return result.completionPercentage;
  }, [encounterData]);

  const getTabIcon = (tabId: string) => {
    const iconClass = "h-4 w-4";
    switch (tabId) {
      case 'incident':
        return <FileText className={iconClass} />;
      case 'patient':
        return <User className={iconClass} />;
      case 'objectiveFindings':
        return <Stethoscope className={iconClass} />;
      case 'assessments':
        return <ClipboardList className={iconClass} />;
      case 'vitals':
        return <Activity className={iconClass} />;
      case 'treatment':
        return <Pill className={iconClass} />;
      case 'narrative':
        return <AlignLeft className={iconClass} />;
      case 'disposition':
        return <FileCheck className={iconClass} />;
      case 'signatures':
        return <PenTool className={iconClass} />;
      default:
        return <Circle className={iconClass} />;
    }
  };

  const getStatusBadgeVariant = (status: string) => {
    switch (status) {
      case 'draft':
        return 'outline';
      case 'in-progress':
        return 'secondary';
      case 'submitted':
        return 'default';
      default:
        return 'outline';
    }
  };

  const addVitalRow = () => {
    setVitalRows([
      ...vitalRows,
      {
        id: Date.now().toString(),
        time: new Date().toISOString().slice(0, 16),
        bp: '',
        hr: '',
        rr: '',
        temp: '',
        spo2: '',
        pain: '',
        avpu: '',
        gcs: '',
        loc: '',
        bloodSugar: '',
      },
    ]);
  };

  const removeVitalRow = (id: string) => {
    if (vitalRows.length > 1) {
      setVitalRows(vitalRows.filter((row) => row.id !== id));
    }
  };

  const updateVitalRow = (id: string, field: keyof VitalRow, value: string) => {
    setVitalRows(
      vitalRows.map((row) => (row.id === id ? { ...row, [field]: value } : row))
    );
  };

  return (
    <div className="h-full flex flex-col bg-slate-50 dark:bg-slate-900">
      {/* Read-Only Banner */}
      {isReadOnly && (
        <div className="bg-blue-50 dark:bg-blue-900/30 border-b border-blue-200 dark:border-blue-800 px-4 py-2 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Eye className="h-5 w-5 text-blue-600 dark:text-blue-400" />
            <span className="text-blue-800 dark:text-blue-200 font-medium">Viewing Historical Report - Read Only</span>
          </div>
          <Button variant="outline" size="sm" onClick={() => navigate(-1)} className="border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-800">
            <ArrowLeft className="h-4 w-4 mr-2" />
            Back to Timeline
          </Button>
        </div>
      )}
      
      {/* Sticky Header */}
      <div className="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shadow-sm">
        {/* Top Header Bar */}
        <div className="px-6 py-3 border-b border-slate-100 dark:border-slate-700">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => navigate('/dashboard')}
              >
                <ArrowLeft className="h-4 w-4 mr-2" />
                Back
              </Button>
              <div className="h-6 w-px bg-slate-300 dark:bg-slate-600" />
              <div className="flex items-center gap-6">
                {/* Mobile hamburger menu - only visible on mobile */}
                <MobileEncounterNav
                  activeTab={activeTab}
                  setActiveTab={setActiveTab}
                  tabs={tabs}
                  encounterData={encounterData}
                  onFieldClick={handleFieldClick}
                />
                {/* Hide patient name on mobile - only show on md+ screens */}
                <h1 className="text-lg dark:text-white hidden md:block">
                  <span>{patient.name}</span>
                </h1>
                {/* Hide MRN and Encounter ID on mobile - TASK 5 */}
                <div className="hidden md:flex items-center gap-4 text-sm text-slate-600 dark:text-white">
                  <span>MRN: {patient.mrn}</span>
                  <span>Encounter: {patient.encounterId}</span>
                  <Badge variant={getStatusBadgeVariant(encounterStatus)}>
                    {encounterStatus.replace('-', ' ')}
                  </Badge>
                </div>
              </div>
            </div>

            {/* Action Buttons - Hidden in read-only mode */}
            {!isReadOnly && (
              <div className="flex items-center gap-2">
                {autoSaving && (
                  <span className="text-sm text-slate-500 flex items-center gap-1 hidden md:flex">
                    <Clock className="h-3 w-3 animate-spin" />
                    Saving...
                  </span>
                )}
                {/* Hide Validate button on mobile */}
                <Button variant="outline" size="sm" onClick={handleValidate} className="hidden md:inline-flex">
                  <CheckCircle2 className="h-4 w-4 mr-2" />
                  Validate
                </Button>
                <Button variant="outline" size="sm" onClick={handleSaveDraft}>
                  <Save className="h-4 w-4 md:mr-2" />
                  <span className="hidden md:inline">Save</span>
                </Button>
                <Button
                  size="sm"
                  onClick={handleSubmit}
                  disabled={isSubmitting}
                  className={!isFormValid ? 'bg-amber-600 hover:bg-amber-700' : ''}
                  title={isFormValid ? 'Submit for review' : `Complete all required fields (${completionPercentage.toFixed(0)}% done)`}
                >
                  {isSubmitting ? (
                    <>
                      <Clock className="h-4 w-4 md:mr-2 animate-spin" />
                      <span className="hidden md:inline">Submitting...</span>
                    </>
                  ) : (
                    <>
                      {!isFormValid && <AlertTriangle className="h-4 w-4 md:mr-2" />}
                      {isFormValid && <Send className="h-4 w-4 md:mr-2" />}
                      <span className="hidden md:inline">Submit {!isFormValid && `(${completionPercentage.toFixed(0)}%)`}</span>
                    </>
                  )}
                </Button>
              </div>
            )}
            
            {/* Read-only indicator badge */}
            {isReadOnly && (
              <Badge variant="secondary" className="bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200">
                <Eye className="h-3 w-3 mr-1" />
                Read Only
              </Badge>
            )}
          </div>
        </div>

        {/* Horizontal Tab Navigation - hidden on mobile, use hamburger menu instead */}
        <div className="px-6 hidden md:block">
          <div className="flex gap-1 -mb-px">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`
                  px-4 py-3 text-sm transition-colors
                  flex items-center gap-2
                  ${
                    activeTab === tab.id
                      ? 'border-b-2 border-blue-600 text-blue-600 dark:text-blue-400 bg-blue-50/50 dark:bg-blue-900/20'
                      : 'border-b-2 border-transparent text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50'
                  }
                `}
              >
                {getTabIcon(tab.id)}
                {tab.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Tab Content Area with Sidebar */}
      <div className="flex-1 flex overflow-hidden">
        {/* Main Content */}
        <div className="flex-1 overflow-auto">
          <div className="max-w-6xl mx-auto p-6">
            {activeTab === 'incident' && (
              <>
                <IncidentTab
                  incidentForm={incidentForm}
                  setIncidentForm={setIncidentForm}
                  shiftData={shiftData}
                  onProvidersChange={setProviders}
                  isReadOnly={isReadOnly}
                />
                <NextTabButton />
              </>
            )}
            {activeTab === 'patient' && (
              <>
                <PatientTab patientForm={patientForm} setPatientForm={setPatientForm} isReadOnly={isReadOnly} />
                <NextTabButton />
              </>
            )}
            {activeTab === 'objectiveFindings' && (
              <>
                <ObjectiveFindingsTab
                  isReadOnly={isReadOnly}
                  onShowVitalsModal={() => {
                    if (isReadOnly) return;
                    setEditingOFVital(null);
                    setShowOFVitalsModal(true);
                  }}
                  onEditVital={(vital) => {
                    if (isReadOnly) return;
                    setEditingOFVital(vital);
                    setShowOFVitalsModal(true);
                  }}
                  onDeleteVital={(vitalId) => {
                    if (isReadOnly) return;
                    // Delete vital from context
                    if (encounterContext?.setVitals) {
                      const currentVitals = encounterContext.vitals ?? [];
                      encounterContext.setVitals(currentVitals.filter((v) => v.id !== vitalId));
                      toast.success('Vital record deleted');
                    }
                  }}
                  onShowAssessmentModal={() => {
                    if (isReadOnly) return;
                    setEditingOFAssessment(null);
                    setShowOFAssessmentModal(true);
                  }}
                  onEditAssessment={(assessment) => {
                    if (isReadOnly) return;
                    setEditingOFAssessment(assessment);
                    setShowOFAssessmentModal(true);
                  }}
                  onDeleteAssessment={(assessmentId) => {
                    if (isReadOnly) return;
                    // Delete assessment from context
                    if (encounterContext?.setAssessments) {
                      const currentAssessments = encounterContext.assessments ?? [];
                      encounterContext.setAssessments(currentAssessments.filter((a) => a.id !== assessmentId));
                      toast.success('Assessment record deleted');
                    }
                  }}
                  onShowTreatmentModal={() => {
                    if (isReadOnly) return;
                    setShowOFTreatmentModal(true);
                  }}
                  onEditTreatment={(_treatment) => {
                    if (isReadOnly) return;
                    // For treatments, we'll open the modal - edit functionality can be added later
                    setShowOFTreatmentModal(true);
                    toast.info('Edit treatment - select treatment to modify');
                  }}
                  onDeleteTreatment={(treatmentId) => {
                    if (isReadOnly) return;
                    // Delete treatment from context
                    if (encounterContext?.setTreatments) {
                      const currentTreatments = encounterContext.treatments ?? [];
                      encounterContext.setTreatments(currentTreatments.filter((t) => t.id !== treatmentId));
                      toast.success('Treatment record deleted');
                    }
                  }}
                />
                <NextTabButton />
              </>
            )}
            {activeTab === 'assessments' && (
              <>
                <AssessmentsTab
                  subTab={assessmentSubTab}
                  setSubTab={setAssessmentSubTab}
                  patientForm={patientForm}
                  onAssessmentsChange={setAssessments}
                  isReadOnly={isReadOnly}
                />
                <NextTabButton />
              </>
            )}
            {activeTab === 'vitals' && (
              <>
                <VitalsTab
                  vitalRows={vitalRows}
                  updateVitalRow={updateVitalRow}
                  addVitalRow={addVitalRow}
                  removeVitalRow={removeVitalRow}
                  onVitalsChange={setVitalsData}
                  isReadOnly={isReadOnly}
                />
                <NextTabButton />
              </>
            )}
            {activeTab === 'treatment' && (
              <>
                <TreatmentTab isReadOnly={isReadOnly} />
                <NextTabButton />
              </>
            )}
            {activeTab === 'narrative' && (
              <>
                <NarrativeTab
                  narrativeText={narrativeText}
                  onNarrativeChange={setNarrativeText}
                  encounterId={serverEncounterId || id || ''}
                  isReadOnly={isReadOnly}
                />
                <NextTabButton />
              </>
            )}
            {activeTab === 'disposition' && (
              <>
                <DispositionTab
                  disposition={disposition}
                  setDisposition={setDisposition}
                  dispositionNotes={dispositionNotes}
                  setDispositionNotes={setDispositionNotes}
                  expectedFollowUpDate={expectedFollowUpDate}
                  setExpectedFollowUpDate={setExpectedFollowUpDate}
                  sendSms={sendSms}
                  setSendSms={setSendSms}
                  appointments={appointments}
                  setAppointments={setAppointments}
                  patientPhone={patientForm.phone}
                  patientId={patientForm.id}
                  encounterId={id || ''}
                  incidentForm={incidentForm}
                  isReadOnly={isReadOnly}
                />
                <NextTabButton />
              </>
            )}
            {activeTab === 'signatures' && (
              <SignaturesTab
                providerSignature={providerSignature}
                setProviderSignature={setProviderSignature}
                witnessSignature={witnessSignature}
                setWitnessSignature={setWitnessSignature}
                encounterId={id || ''}
                isWorkRelated={incidentForm.injuryClassification === 'work_related'}
                onDisclosureChange={setDisclosureAcknowledgments}
                isReadOnly={isReadOnly}
              />
            )}
          </div>
        </div>

        {/* Required Fields Sidebar - hidden on mobile, use hamburger menu instead */}
        <div className="hidden md:block w-80 border-l border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-y-auto">
          <SidebarRequiredFields
            activeTab={activeTab}
            encounterData={encounterData}
            onFieldClick={(fieldName) => handleFieldClick(fieldName, '')}
            onTabClick={(tabId) => setActiveTab(tabId)}
          />
        </div>
      </div>

      {/* Offline Banner (Bottom, Persistent) */}
      {!isOnline && (
        <div className="bg-yellow-100 border-t border-yellow-300 px-6 py-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2 text-yellow-900">
              <WifiOff className="h-5 w-5" />
              <span>Offline Mode</span>
              <span className="text-sm">— Data will sync when connection is restored</span>
            </div>
            {offlineCount > 0 && (
              <Badge variant="outline" className="bg-yellow-50">
                {offlineCount} items pending sync
              </Badge>
            )}
          </div>
        </div>
      )}

      {/* Validation Errors Modal */}
      {showValidationModal && (
        <ValidationErrors
          errors={validationErrors}
          onErrorClick={handleValidationErrorClick}
          onClose={() => setShowValidationModal(false)}
        />
      )}

      {/* Objective Findings Tab Modals */}
      {showOFVitalsModal && (
        <VitalsEntryModal
          isOpen={showOFVitalsModal}
          onClose={() => {
            setShowOFVitalsModal(false);
            setEditingOFVital(null);
          }}
          onSave={(vitalData) => {
            if (encounterContext?.setVitals) {
              const currentVitals = encounterContext.vitals ?? [];
              // Ensure vitalData has required id field for VitalSet compatibility
              const vitalWithId = { ...vitalData, id: vitalData.id || Date.now().toString() };
              if (editingOFVital) {
                // Update existing vital
                encounterContext.setVitals(
                  currentVitals.map((v) =>
                    v.id === editingOFVital.id ? { ...vitalWithId, id: editingOFVital.id } : v
                  )
                );
                toast.success('Vitals updated');
              } else {
                // Add new vital
                encounterContext.setVitals([vitalWithId, ...currentVitals]);
                toast.success('Vitals saved');
              }
            }
            setShowOFVitalsModal(false);
            setEditingOFVital(null);
          }}
          initialData={editingOFVital}
        />
      )}

      {showOFAssessmentModal && (
        <AssessmentModal
          isOpen={showOFAssessmentModal}
          onClose={() => {
            setShowOFAssessmentModal(false);
            setEditingOFAssessment(null);
          }}
          encounterId={serverEncounterId || id || ''}
          existingAssessments={
            editingOFAssessment
              ? Object.fromEntries(
                  Object.entries(editingOFAssessment.regions || {}).map(([key, value]) => [
                    key,
                    {
                      status: value === 'No Abnormalities' ? 'normal' : value === 'Assessed' ? 'abnormal' : 'not-assessed',
                      notes: '',
                      timestamp: new Date().toISOString(),
                    },
                  ])
                )
              : {}
          }
          onSave={(updatedAssessments) => {
            if (encounterContext?.setAssessments && updatedAssessments) {
              const currentAssessments = encounterContext.assessments ?? [];
              
              // Convert AssessmentModal format to standard assessment format
              const newRegions = Object.fromEntries(
                Object.entries(updatedAssessments).map(([region, data]) => [
                  region,
                  data.status === 'normal' ? 'No Abnormalities' :
                  data.status === 'abnormal' ? 'Assessed' : 'Not Assessed'
                ])
              );
              
              if (editingOFAssessment) {
                // Update existing assessment
                encounterContext.setAssessments(
                  currentAssessments.map((a) =>
                    a.id === editingOFAssessment.id
                      ? {
                          ...a,
                          regions: newRegions,
                          time: new Date().toLocaleString(),
                          // Ensure required Assessment fields are present
                          region: a.region || 'general',
                          findings: a.findings || '',
                          timestamp: new Date().toISOString()
                        }
                      : a
                  )
                );
                toast.success('Assessment updated');
              } else {
                // Add new assessment with all required fields
                const newAssessment = {
                  id: Date.now().toString(),
                  region: 'general',
                  findings: '',
                  timestamp: new Date().toISOString(),
                  time: new Date().toLocaleString(),
                  editableTime: new Date().toLocaleString(),
                  regions: newRegions,
                };
                encounterContext.setAssessments([newAssessment, ...currentAssessments]);
                toast.success('Assessment saved');
              }
            }
            setShowOFAssessmentModal(false);
            setEditingOFAssessment(null);
          }}
        />
      )}

      {showOFTreatmentModal && (
        <InterventionModal
          isOpen={showOFTreatmentModal}
          onClose={() => setShowOFTreatmentModal(false)}
          onSave={(intervention) => {
            if (encounterContext?.setTreatments) {
              const currentTreatments = encounterContext.treatments ?? [];
              encounterContext.setTreatments([intervention, ...currentTreatments]);
              toast.success('Treatment saved');
            }
            setShowOFTreatmentModal(false);
          }}
        />
      )}
    </div>
  );
}

// Incident Tab Component
function IncidentTab({
  incidentForm,
  setIncidentForm,
  shiftData,
  onProvidersChange,
  isReadOnly = false,
}: {
  incidentForm: any;
  setIncidentForm: any;
  shiftData?: any;
  onProvidersChange?: (providers: Array<{ id: string; name: string; role: string }>) => void;
  isReadOnly?: boolean;
}) {
  // Get logged-in user info for auto-fill
  const { user } = useAuth();
  
  const [providers, setProvidersLocal] = useState<Array<{ id: string; name: string; role: string }>>([
    { id: '1', name: '', role: '' },
  ]);
  const [hasAutoFilledLeadProvider, setHasAutoFilledLeadProvider] = useState(false);
  
  // State for fetched staff list
  const [staffList, setStaffList] = useState<Array<{ id: string; name: string; credentials: string }>>([]);
  const [loadingStaff, setLoadingStaff] = useState(false);
  
  // Fetch staff/providers from API on mount
  useEffect(() => {
    const fetchStaff = async () => {
      setLoadingStaff(true);
      try {
        // Try to fetch from the API
        const response = await fetch('/api/users/providers', {
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
          },
        });
        
        if (response.ok) {
          const data = await response.json();
          if (data.success && Array.isArray(data.providers)) {
            setStaffList(data.providers.map((p: any) => ({
              id: p.id?.toString() || p.user_id?.toString() || '',
              name: p.name || `${p.first_name || ''} ${p.last_name || ''}`.trim(),
              credentials: p.credentials || p.role || '',
            })));
          }
        } else {
          // If API fails, use current user as fallback
          console.log('[IncidentTab] Staff API not available, using fallback');
        }
      } catch (error) {
        console.error('[IncidentTab] Error fetching staff:', error);
      } finally {
        setLoadingStaff(false);
      }
    };
    
    fetchStaff();
  }, []);

  // Auto-fill lead provider from logged-in user on component mount
  useEffect(() => {
    if (user && !hasAutoFilledLeadProvider && providers[0]?.name === '') {
      // Format user name with credentials if available
      const providerName = user.name;
      
      // Update providers with lead provider auto-filled
      const updatedProviders = [
        { id: '1', name: providerName, role: 'lead' },
        ...providers.slice(1)
      ];
      
      setProvidersLocal(updatedProviders);
      onProvidersChange?.(updatedProviders);
      setHasAutoFilledLeadProvider(true);
      
      toast.info(`Lead provider auto-filled: ${providerName}`);
    }
  }, [user, hasAutoFilledLeadProvider, providers, onProvidersChange]);

  // Sync providers with parent
  const setProviders = (newProviders: Array<{ id: string; name: string; role: string }>) => {
    setProvidersLocal(newProviders);
    onProvidersChange?.(newProviders);
  };

  const handleAutofillClinic = () => {
    if (shiftData) {
      setIncidentForm({
        ...incidentForm,
        clinicName: shiftData.clinicName || '',
        clinicStreetAddress: shiftData.clinicAddress || '',
        clinicCity: shiftData.city || '',
        clinicState: shiftData.state || '',
        clinicCounty: shiftData.county || '',
        clinicUnitNumber: shiftData.unitNumber || '',
      });
      toast.success('Clinic information autofilled from shift data');
    } else {
      toast.error('No shift data available to autofill');
    }
  };

  const addProvider = () => {
    setProviders([...providers, { id: Date.now().toString(), name: '', role: '' }]);
  };

  const updateProvider = (id: string, field: 'name' | 'role', value: string) => {
    setProviders(providers.map((p) => (p.id === id ? { ...p, [field]: value } : p)));
  };

  const removeProvider = (id: string) => {
    if (providers.length > 1) {
      setProviders(providers.filter((p) => p.id !== id));
    }
  };

  return (
    <div className="space-y-6">
      <Card className="p-6" id="clinic-info">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg">Clinic Information</h2>
          <Button
            variant="outline"
            size="sm"
            onClick={handleAutofillClinic}
            className="text-blue-600 hover:text-blue-700"
          >
            <Sparkles className="h-4 w-4 mr-2" />
            Autofill from Shift
          </Button>
        </div>
        <div className="space-y-6">
          <div className="space-y-2">
            <Label htmlFor="incidentClinicName">Clinic Name <span className="text-red-600">*</span></Label>
            <Input
              id="incidentClinicName"
              value={incidentForm.clinicName}
              onChange={(e) =>
                setIncidentForm({ ...incidentForm, clinicName: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="incidentClinicStreetAddress">Street Address <span className="text-red-600">*</span></Label>
            <Input
              id="incidentClinicStreetAddress"
              value={incidentForm.clinicStreetAddress}
              onChange={(e) =>
                setIncidentForm({ ...incidentForm, clinicStreetAddress: e.target.value })
              }
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="grid md:grid-cols-2 gap-6">
            <div className="space-y-2">
              <Label htmlFor="incidentClinicCity">City <span className="text-red-600">*</span></Label>
              <Input
                id="incidentClinicCity"
                value={incidentForm.clinicCity}
                onChange={(e) =>
                  setIncidentForm({ ...incidentForm, clinicCity: e.target.value })
                }
                required
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="incidentClinicState">State <span className="text-red-600">*</span></Label>
              <Input
                id="incidentClinicState"
                value={incidentForm.clinicState}
                onChange={(e) =>
                  setIncidentForm({ ...incidentForm, clinicState: e.target.value })
                }
                required
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
            </div>
          </div>
          <div className="grid md:grid-cols-2 gap-6">
            <div className="space-y-2">
              <Label htmlFor="incidentClinicCounty">County</Label>
              <Input
                id="incidentClinicCounty"
                value={incidentForm.clinicCounty}
                onChange={(e) =>
                  setIncidentForm({ ...incidentForm, clinicCounty: e.target.value })
                }
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="incidentClinicUnitNumber">Unit Number</Label>
              <Input
                id="incidentClinicUnitNumber"
                value={incidentForm.clinicUnitNumber}
                onChange={(e) =>
                  setIncidentForm({ ...incidentForm, clinicUnitNumber: e.target.value })
                }
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
              <p className="text-xs text-slate-500">Suite, Apt, etc.</p>
            </div>
          </div>
        </div>
      </Card>

      <div className="grid lg:grid-cols-[auto_1fr] gap-6">
        {/* Time-Based Fields - Tight Column */}
        <Card className="p-6" id="time-fields">
          <h2 className="text-lg mb-4">Time-Based Fields</h2>
          <div className="space-y-6 w-80">
            <div className="space-y-2">
              <Label htmlFor="incidentMayDayTime">May Day Time</Label>
              <Input
                id="incidentMayDayTime"
                type="datetime-local"
                value={incidentForm.mayDayTime}
                onChange={(e) =>
                  setIncidentForm({ ...incidentForm, mayDayTime: e.target.value })
                }
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="incidentPatientContactTime">Patient Contact Time <span className="text-red-600">*</span></Label>
              <Input
                id="incidentPatientContactTime"
                type="datetime-local"
                value={incidentForm.patientContactTime}
                onChange={(e) =>
                  setIncidentForm({ ...incidentForm, patientContactTime: e.target.value })
                }
                required
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="incidentTransferOfCareTime">Transfer of Care Time</Label>
              <Input
                id="incidentTransferOfCareTime"
                type="datetime-local"
                value={incidentForm.transferOfCareTime}
                onChange={(e) =>
                  setIncidentForm({ ...incidentForm, transferOfCareTime: e.target.value })
                }
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="incidentClearedClinicTime">Cleared Clinic Time <span className="text-red-600">*</span></Label>
              <Input
                id="incidentClearedClinicTime"
                type="datetime-local"
                value={incidentForm.clearedClinicTime}
                onChange={(e) =>
                  setIncidentForm({ ...incidentForm, clearedClinicTime: e.target.value })
                }
                required
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
            </div>
          </div>
        </Card>

        {/* Mass Casualty and Location - No container */}
        <div className="space-y-6">
          <div>
            <h3 className="text-sm mb-3">Mass Casualty</h3>
            <div className="flex gap-3">
              <Button
                type="button"
                variant={incidentForm.massCasualty === 'yes' ? 'default' : 'outline'}
                onClick={() => !isReadOnly && setIncidentForm({ ...incidentForm, massCasualty: 'yes' })}
                className={`flex-1 ${isReadOnly ? 'cursor-not-allowed opacity-60' : ''}`}
                disabled={isReadOnly}
              >
                Yes
              </Button>
              <Button
                type="button"
                variant={incidentForm.massCasualty === 'no' ? 'default' : 'outline'}
                onClick={() => !isReadOnly && setIncidentForm({ ...incidentForm, massCasualty: 'no' })}
                className={`flex-1 ${isReadOnly ? 'cursor-not-allowed opacity-60' : ''}`}
                disabled={isReadOnly}
              >
                No
              </Button>
            </div>
          </div>

          <div>
            <h3 className="text-sm mb-3">Location of injury/illness <span className="text-red-600">*</span></h3>
            <Select
              value={incidentForm.location}
              onValueChange={(value) =>
                !isReadOnly && setIncidentForm({ ...incidentForm, location: value })
              }
              disabled={isReadOnly}
            >
              <SelectTrigger id="incidentLocation" className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}>
                <SelectValue placeholder="Select location..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="home">Home</SelectItem>
                <SelectItem value="field">Field</SelectItem>
                <SelectItem value="vehicle">Vehicle</SelectItem>
                <SelectItem value="worksite">Worksite</SelectItem>
                <SelectItem value="other">Other</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* Injury/Illness Classification Section */}
          <div className="space-y-4 p-4 border border-slate-200 rounded-lg">
            <h4 className="text-sm font-medium">Injury/Illness Classified By <span className="text-red-600">*</span></h4>
            <div className="grid grid-cols-12 gap-4">
              <div className="col-span-6 space-y-2">
                <Label htmlFor="injuryClassifiedByFirstName">Name <span className="text-red-600">*</span></Label>
                <Input
                  id="injuryClassifiedByFirstName"
                  type="text"
                  value={incidentForm.injuryClassifiedByName || ''}
                  onChange={(e) =>
                    setIncidentForm({ ...incidentForm, injuryClassifiedByName: e.target.value })
                  }
                  readOnly={isReadOnly}
                  className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
                />
              </div>
              <div className="col-span-4 space-y-2">
                <Label htmlFor="injuryClassification">Classification <span className="text-red-600">*</span></Label>
                <Select
                  value={incidentForm.injuryClassification || ''}
                  onValueChange={(value) =>
                    !isReadOnly && setIncidentForm({ ...incidentForm, injuryClassification: value })
                  }
                  disabled={isReadOnly}
                >
                  <SelectTrigger id="injuryClassification" className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="personal">Personal</SelectItem>
                    <SelectItem value="work_related">Work Related</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="natureOfIllness">Nature of Illness</Label>
            <Select
              value={incidentForm.natureOfIllness || ''}
              onValueChange={(value) =>
                !isReadOnly && setIncidentForm({ ...incidentForm, natureOfIllness: value, natureOfIllnessOtherDetails: value !== 'other' ? '' : incidentForm.natureOfIllnessOtherDetails })
              }
              disabled={isReadOnly}
            >
              <SelectTrigger id="natureOfIllness" className={`text-left ${isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}`}>
                <SelectValue placeholder="Select NOI..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="skin-disorder">Skin Disorder/Dermatitis</SelectItem>
                <SelectItem value="respiratory">Respiratory Condition</SelectItem>
                <SelectItem value="poisoning">Poisoning</SelectItem>
                <SelectItem value="heat-illness">Heat Illness</SelectItem>
                <SelectItem value="cold-exposure">Cold Exposure/Frostbite</SelectItem>
                <SelectItem value="radiation">Radiation Effects</SelectItem>
                <SelectItem value="infectious-disease">Infectious Disease</SelectItem>
                <SelectItem value="hearing-loss">Hearing Loss</SelectItem>
                <SelectItem value="musculoskeletal">Musculoskeletal Disorder</SelectItem>
                <SelectItem value="mental-health">Mental Health Condition</SelectItem>
                <SelectItem value="other">Other</SelectItem>
                <SelectItem value="none">None</SelectItem>
              </SelectContent>
            </Select>
            {incidentForm.natureOfIllness === 'other' && (
              <div className="mt-2 space-y-2">
                <Label htmlFor="natureOfIllnessOtherDetails">Please specify *</Label>
                <Input
                  id="natureOfIllnessOtherDetails"
                  type="text"
                  value={incidentForm.natureOfIllnessOtherDetails || ''}
                  onChange={(e) =>
                    setIncidentForm({ ...incidentForm, natureOfIllnessOtherDetails: e.target.value })
                  }
                  required
                  readOnly={isReadOnly}
                  className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
                />
              </div>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="mechanismofinjury">Mechanism of Injury</Label>
            <Select
              value={incidentForm.mechanismofinjury || ''}
              onValueChange={(value) =>
                !isReadOnly && setIncidentForm({ ...incidentForm, mechanismofinjury: value, mechanismofinjuryOtherDetails: value !== 'other' ? '' : incidentForm.mechanismofinjuryOtherDetails })
              }
              disabled={isReadOnly}
            >
              <SelectTrigger id="mechanismofinjury" className={`text-left ${isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}`}>
                <SelectValue placeholder="Select MOI..." />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="fall-same-level">Fall (Same Level)</SelectItem>
                <SelectItem value="fall-lower-level">Fall (To Lower Level)</SelectItem>
                <SelectItem value="struck-by">Struck By Object</SelectItem>
                <SelectItem value="struck-against">Struck Against Object</SelectItem>
                <SelectItem value="caught-in">Caught In/Between</SelectItem>
                <SelectItem value="overexertion">Overexertion</SelectItem>
                <SelectItem value="repetitive-motion">Repetitive Motion</SelectItem>
                <SelectItem value="exposure-substance">Exposure to Harmful Substance</SelectItem>
                <SelectItem value="transportation">Transportation Incident</SelectItem>
                <SelectItem value="violence">Violence/Assault</SelectItem>
                <SelectItem value="temperature-extreme">Contact with Temperature Extreme</SelectItem>
                <SelectItem value="electrical">Electrical Contact</SelectItem>
                <SelectItem value="slip-trip">Slip/Trip (No Fall)</SelectItem>
                <SelectItem value="animal-insect">Animal/Insect Bite or Sting</SelectItem>
                <SelectItem value="noise-exposure">Noise Exposure</SelectItem>
                <SelectItem value="other">Other</SelectItem>
                <SelectItem value="unknown">Unknown</SelectItem>
              </SelectContent>
            </Select>
            {incidentForm.mechanismofinjury === 'other' && (
              <div className="mt-2 space-y-2">
                <Label htmlFor="mechanismofinjuryOtherDetails">Please specify *</Label>
                <Input
                  id="mechanismofinjuryOtherDetails"
                  type="text"
                  value={incidentForm.mechanismofinjuryOtherDetails || ''}
                  onChange={(e) =>
                    setIncidentForm({ ...incidentForm, mechanismofinjuryOtherDetails: e.target.value })
                  }
                  required
                  readOnly={isReadOnly}
                  className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
                />
              </div>
            )}
          </div>
        </div>
      </div>

      <Card className="p-6" id="provider-info">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg">Provider Information</h2>
          {!isReadOnly && (
            <Button
              variant="outline"
              size="sm"
              onClick={addProvider}
              className="text-green-600 hover:text-green-700"
            >
              <Plus className="h-4 w-4 mr-2" />
              Add Provider
            </Button>
          )}
        </div>
        <div className="space-y-4">
          {providers.map((provider, _index) => (
            <div key={provider.id} className="grid md:grid-cols-2 gap-6 p-4 border border-slate-200 rounded-lg relative">
              {providers.length > 1 && !isReadOnly && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => removeProvider(provider.id)}
                  className="absolute top-2 right-2 text-red-600 hover:text-red-700"
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              )}
              <div className="space-y-2">
                <Label htmlFor={`provider-name-${provider.id}`}>Staff</Label>
                <Select
                  value={provider.name}
                  onValueChange={(value) => !isReadOnly && updateProvider(provider.id, 'name', value)}
                  disabled={loadingStaff || isReadOnly}
                >
                  <SelectTrigger id={`provider-name-${provider.id}`} className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}>
                    <SelectValue placeholder={loadingStaff ? "Loading staff..." : "Select staff member"} />
                  </SelectTrigger>
                  <SelectContent>
                    {/* Dynamic staff list from API */}
                    {staffList.length > 0 ? (
                      staffList.map((staff) => (
                        <SelectItem
                          key={staff.id}
                          value={staff.credentials ? `${staff.name} - ${staff.credentials}` : staff.name}
                        >
                          {staff.credentials ? `${staff.name} - ${staff.credentials}` : staff.name}
                        </SelectItem>
                      ))
                    ) : (
                      /* Fallback options if API fails */
                      <>
                        {user && (
                          <SelectItem value={user.name}>{user.name} (Current User)</SelectItem>
                        )}
                        <SelectItem value="Staff Member 1">Staff Member 1</SelectItem>
                        <SelectItem value="Staff Member 2">Staff Member 2</SelectItem>
                      </>
                    )}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor={`provider-role-${provider.id}`}>Role</Label>
                <Select
                  value={provider.role}
                  onValueChange={(value) => !isReadOnly && updateProvider(provider.id, 'role', value)}
                  disabled={isReadOnly}
                >
                  <SelectTrigger id={`provider-role-${provider.id}`} className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}>
                    <SelectValue placeholder="Select role" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="lead">Lead</SelectItem>
                    <SelectItem value="assist">Assist</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}

// Patient Tab Component
function PatientTab({
  patientForm,
  setPatientForm,
  isReadOnly = false,
}: {
  patientForm: any;
  setPatientForm: any;
  isReadOnly?: boolean;
}) {
  return (
    <div className="space-y-6">
      <Card className="p-6" id="patient-demographics">
        <h2 className="text-lg mb-4">Patient Demographics</h2>
        <div className="grid md:grid-cols-2 gap-6">
          <div className="space-y-2">
            <Label htmlFor="firstName">First Name <span className="text-red-600">*</span></Label>
            <Input
               id="firstName"
               value={patientForm.firstName}
               onChange={(e) =>
                 setPatientForm({ ...patientForm, firstName: e.target.value })
               }
               required
               readOnly={isReadOnly}
               className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
             />
          </div>
          <div className="space-y-2">
            <Label htmlFor="lastName">Last Name <span className="text-red-600">*</span></Label>
            <Input
              id="lastName"
              value={patientForm.lastName}
              onChange={(e) =>
                setPatientForm({ ...patientForm, lastName: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="dob">Date of Birth <span className="text-red-600">*</span></Label>
            <div className="relative">
              <Input
                id="dob"
                type="date"
                value={patientForm.dob}
                onChange={(e) =>
                  setPatientForm({ ...patientForm, dob: e.target.value })
                }
                className={`[&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:right-3 [&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-100 [&::-webkit-calendar-picker-indicator]:filter-none ${isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}`}
                style={{
                  colorScheme: 'light'
                } as React.CSSProperties}
                required
                readOnly={isReadOnly}
              />
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="sex">Sex Assigned at Birth</Label>
            <Select
              value={patientForm.sex}
              onValueChange={(value) => !isReadOnly && setPatientForm({ ...patientForm, sex: value })}
              disabled={isReadOnly}
            >
              <SelectTrigger id="sex" className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}>
                <SelectValue placeholder="Select sex" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="male">Male</SelectItem>
                <SelectItem value="female">Female</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="employeeId">Employee ID Number</Label>
            <Input
              id="employeeId"
              value={patientForm.employeeId}
              onChange={(e) =>
                setPatientForm({ ...patientForm, employeeId: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="ssn">Social Security Number <span className="text-red-600">*</span></Label>
            <Input
              id="ssn"
              value={patientForm.ssn}
              onChange={(e) =>
                setPatientForm({ ...patientForm, ssn: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
            <p className="text-xs text-slate-500">Format: XXX-XX-XXXX</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="dlNumber">Driver's License Number</Label>
            <Input
              id="dlNumber"
              value={patientForm.dlNumber}
              onChange={(e) =>
                setPatientForm({ ...patientForm, dlNumber: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="dlState">Driver's License State</Label>
            <Input
              id="dlState"
              value={patientForm.dlState}
              onChange={(e) =>
                setPatientForm({ ...patientForm, dlState: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
            <p className="text-xs text-slate-500">e.g., TX, CA, NY</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="phone">Phone Number</Label>
            <Input
              id="phone"
              type="tel"
              value={patientForm.phone}
              onChange={(e) =>
                setPatientForm({ ...patientForm, phone: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="email">Email Address</Label>
            <Input
              id="email"
              type="email"
              value={patientForm.email}
              onChange={(e) =>
                setPatientForm({ ...patientForm, email: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
        </div>
      </Card>

      <Card className="p-6" id="home-address">
        <h2 className="text-lg mb-4">Home Address</h2>
        <div className="grid md:grid-cols-2 gap-6">
          <div className="space-y-2 md:col-span-2">
            <Label htmlFor="streetAddress">Street Address <span className="text-red-600">*</span></Label>
            <Input
              id="streetAddress"
              value={patientForm.streetAddress}
              onChange={(e) =>
                setPatientForm({ ...patientForm, streetAddress: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="city">City <span className="text-red-600">*</span></Label>
            <Input
              id="city"
              value={patientForm.city}
              onChange={(e) =>
                setPatientForm({ ...patientForm, city: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="county">County</Label>
            <Input
              id="county"
              value={patientForm.county}
              onChange={(e) =>
                setPatientForm({ ...patientForm, county: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="state">State <span className="text-red-600">*</span></Label>
            <Input
              id="state"
              value={patientForm.state}
              onChange={(e) =>
                setPatientForm({ ...patientForm, state: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
            <p className="text-xs text-slate-500">e.g., TX, CA, NY</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="country">Country</Label>
            <Input
              id="country"
              value={patientForm.country}
              onChange={(e) =>
                setPatientForm({ ...patientForm, country: e.target.value })
              }
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="unitNumber">Unit Number (Apartments)</Label>
            <Input
              id="unitNumber"
              value={patientForm.unitNumber}
              onChange={(e) =>
                setPatientForm({ ...patientForm, unitNumber: e.target.value })
              }
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
            <p className="text-xs text-slate-500">Suite, Apt, etc.</p>
          </div>
        </div>
      </Card>

      <Card className="p-6" id="employment-info">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg">Employment Information</h2>
          {!isReadOnly && (
            <Button
              type="button"
              variant={patientForm.noEmploymentInfo ? 'default' : 'outline'}
              size="sm"
              onClick={() => {
                const isToggling = !patientForm.noEmploymentInfo;
                if (isToggling) {
                  // Fill with N/A and disable fields
                  setPatientForm({
                    ...patientForm,
                    noEmploymentInfo: true,
                    employer: 'N/A',
                    supervisorName: 'N/A',
                    supervisorPhone: 'N/A',
                  });
                  toast.info('Employment fields set to N/A');
                } else {
                  // Re-enable fields and clear N/A values
                  setPatientForm({
                    ...patientForm,
                    noEmploymentInfo: false,
                    employer: '',
                    supervisorName: '',
                    supervisorPhone: '',
                  });
                  toast.info('Employment fields re-enabled');
                }
              }}
              className={patientForm.noEmploymentInfo ? 'bg-amber-600 hover:bg-amber-700' : ''}
            >
              {patientForm.noEmploymentInfo ? '✓ No Employment Info' : 'No Employment Info'}
            </Button>
          )}
        </div>
        <div className={`grid md:grid-cols-2 gap-6 ${patientForm.noEmploymentInfo ? 'opacity-50' : ''}`}>
          <div className="space-y-2">
            <Label htmlFor="employer">Employer <span className="text-red-600">*</span></Label>
            <Input
              id="employer"
              value={patientForm.employer}
              onChange={(e) =>
                setPatientForm({ ...patientForm, employer: e.target.value })
              }
              disabled={patientForm.noEmploymentInfo || isReadOnly}
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="supervisorName">Supervisor Name <span className="text-red-600">*</span></Label>
            <Input
              id="supervisorName"
              value={patientForm.supervisorName}
              onChange={(e) =>
                setPatientForm({ ...patientForm, supervisorName: e.target.value })
              }
              disabled={patientForm.noEmploymentInfo || isReadOnly}
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="supervisorPhone">Supervisor Phone <span className="text-red-600">*</span></Label>
            <Input
              id="supervisorPhone"
              type="tel"
              value={patientForm.supervisorPhone}
              onChange={(e) =>
                setPatientForm({ ...patientForm, supervisorPhone: e.target.value })
              }
              disabled={patientForm.noEmploymentInfo || isReadOnly}
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
        </div>
      </Card>

      <Card className="p-6" id="primary-care-physician">
        <h2 className="text-lg mb-4">Primary Care Physician</h2>
        <div className="grid md:grid-cols-3 gap-6">
          <div className="space-y-2">
            <Label htmlFor="pcpName">PCP Name</Label>
            <Input
              id="pcpName"
              value={patientForm.pcpName}
              onChange={(e) =>
                setPatientForm({ ...patientForm, pcpName: e.target.value })
              }
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="pcpPhone">PCP Phone Number</Label>
            <Input
              id="pcpPhone"
              type="tel"
              value={patientForm.pcpPhone}
              onChange={(e) =>
                setPatientForm({ ...patientForm, pcpPhone: e.target.value })
              }
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="pcpEmail">PCP Email</Label>
            <Input
              id="pcpEmail"
              type="email"
              value={patientForm.pcpEmail}
              onChange={(e) =>
                setPatientForm({ ...patientForm, pcpEmail: e.target.value })
              }
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
          </div>
        </div>
      </Card>

      <Card className="p-6" id="medical-history">
        <h2 className="text-lg mb-4">Medical History</h2>
        <div className="space-y-6">
          <div className="space-y-2">
            <Label htmlFor="medicalHistory">Medical History <span className="text-red-600">*</span></Label>
            <Textarea
              id="medicalHistory"
              value={patientForm.medicalHistory}
              onChange={(e) =>
                setPatientForm({ ...patientForm, medicalHistory: e.target.value })
              }
              rows={4}
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
            <p className="text-xs text-slate-500">Include relevant past conditions and surgeries</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="allergies">Allergies <span className="text-red-600">*</span></Label>
            <Textarea
              id="allergies"
              value={patientForm.allergies}
              onChange={(e) =>
                setPatientForm({ ...patientForm, allergies: e.target.value })
              }
              rows={4}
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
            <p className="text-xs text-slate-500">Enter "None Known" if no allergies</p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="currentMedications">Current Medications <span className="text-red-600">*</span></Label>
            <Textarea
              id="currentMedications"
              value={patientForm.currentMedications}
              onChange={(e) =>
                setPatientForm({ ...patientForm, currentMedications: e.target.value })
              }
              rows={4}
              required
              readOnly={isReadOnly}
              className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
            />
            <p className="text-xs text-slate-500">Enter "None" if not taking any medications</p>
          </div>
        </div>
      </Card>
    </div>
  );
}

// Assessments Tab Component
function AssessmentsTab({
  subTab: _subTab,
  setSubTab: _setSubTab,
  patientForm,
  onAssessmentsChange,
  isReadOnly = false,
}: {
  subTab: string;
  setSubTab: (value: string) => void;
  patientForm: any;
  onAssessmentsChange?: (assessments: any[]) => void;
  isReadOnly?: boolean;
}) {
  // Use context for persistent assessments data
  const encounterContext = useEncounter();
  const contextAssessments = encounterContext?.assessments ?? [];
  const setContextAssessments = encounterContext?.setAssessments;
  
  // Wrapper function that updates both context and parent callback
  const setAssessments = (newAssessments: any[]) => {
    // Update context (primary source of truth)
    if (setContextAssessments) {
      setContextAssessments(newAssessments);
    }
    // Also notify parent for validation tracking
    onAssessmentsChange?.(newAssessments);
  };

  // Use context assessments as the source of truth
  const assessments = contextAssessments;
  const [showQuickAxDialog, setShowQuickAxDialog] = useState(false);
  const [currentAssessmentId, setCurrentAssessmentId] = useState<string | null>(null);
  const [showAssessmentHistoryModal, setShowAssessmentHistoryModal] = useState(false);
  const [patientAssessmentHistory, setPatientAssessmentHistory] = useState<any[]>([]);

  // Helper function to get a stable patient identifier
  const getPatientIdentifier = () => {
    // Prefer employee ID or SSN for stability across encounters
    if (patientForm?.employeeId) return `emp-${patientForm.employeeId}`;
    if (patientForm?.ssn) return `ssn-${patientForm.ssn}`;
    if (patientForm?.id) return `id-${patientForm.id}`;
    return 'unknown';
  };

  // Load patient assessment history when modal opens
  useEffect(() => {
    if (showAssessmentHistoryModal) {
      const patientId = getPatientIdentifier();
      const allAssessments = JSON.parse(localStorage.getItem('patientAssessments') || '{}');
      const patientHistory = allAssessments[patientId] || [];
      setPatientAssessmentHistory(patientHistory);
    }
  }, [showAssessmentHistoryModal, patientForm]);

  // Save assessments to localStorage when they change
  useEffect(() => {
    if (assessments.length > 0) {
      const patientId = getPatientIdentifier();
      const allAssessments = JSON.parse(localStorage.getItem('patientAssessments') || '{}');
      
      // Store assessments with patient ID and encounter metadata
      const assessmentsWithMetadata = assessments.map(assessment => ({
        ...assessment,
        patientId,
        patientName: `${patientForm?.firstName || ''} ${patientForm?.lastName || ''}`.trim(),
        encounterId: Date.now().toString(),
        savedAt: new Date().toISOString(),
      }));
      
      // Merge with existing history
      const existingHistory = allAssessments[patientId] || [];
      allAssessments[patientId] = [...assessmentsWithMetadata, ...existingHistory];
      
      localStorage.setItem('patientAssessments', JSON.stringify(allAssessments));
    }
  }, [assessments, patientForm]);

  const addAssessment = () => {
    const newAssessment = {
      id: Date.now().toString(),
      time: new Date().toLocaleString(),
      editableTime: new Date().toLocaleString(),
      regions: {
        mentalStatus: 'Not Assessed',
        skin: 'Not Assessed',
        heent: 'Not Assessed',
        chest: 'Not Assessed',
        abdomen: 'Not Assessed',
        back: 'Not Assessed',
        pelvisGUI: 'Not Assessed',
        extremities: 'Not Assessed',
        neurological: 'Not Assessed',
      },
    };
    setAssessments([newAssessment, ...assessments]);
  };

  const addOngoingAssessment = () => {
    // Use stable patient identifier
    const patientId = getPatientIdentifier();
    
    // Load patient assessment history from localStorage
    const allAssessments = JSON.parse(localStorage.getItem('patientAssessments') || '{}');
    const patientHistory = allAssessments[patientId] || [];
    
    let lastAssessment;
    
    if (patientHistory.length > 0) {
      // Get the most recent assessment from patient history
      lastAssessment = patientHistory[0];
      toast.success('Autofilled from last patient assessment');
    } else if (assessments.length > 0) {
      // Fallback to current encounter's last assessment
      lastAssessment = assessments[0];
      toast.info('Using current encounter assessment');
    } else {
      // No prior assessments, create blank
      addAssessment();
      toast.info('No prior assessments found. Created blank assessment.');
      return;
    }
    
    const newAssessment = {
      ...lastAssessment,
      id: Date.now().toString(),
      time: new Date().toLocaleString(),
      editableTime: new Date().toLocaleString(),
    };
    setAssessments([newAssessment, ...assessments]);
  };

  const deleteAssessment = (id: string) => {
    setAssessments(assessments.filter((a) => a.id !== id));
  };

  const openQuickAx = (id: string) => {
    setCurrentAssessmentId(id);
    setShowQuickAxDialog(true);
  };

  const updateAssessmentRegion = (id: string, region: string, value: string) => {
    setAssessments(
      assessments.map((a) =>
        a.id === id ? { ...a, regions: { ...(a as any).regions, [region]: value } } : a
      )
    );
  };

  const updateAssessmentTime = (id: string, newTime: string) => {
    setAssessments(
      assessments.map((a) =>
        a.id === id ? { ...a, time: newTime, editableTime: newTime } : a
      )
    );
  };

  if (assessments.length === 0) {
    return (
      <div className="space-y-6">
        <Card className="p-12" id="assessment-content">
          <div className="max-w-2xl mx-auto space-y-6">
            <div className="text-center space-y-2 mb-8">
              <h2 className="text-xl">No Assessments</h2>
              <p className="text-slate-600">
                Click one of the buttons below to add an assessment entry.
              </p>
            </div>
            <div className="grid md:grid-cols-2 gap-6">
              <div className="border border-slate-200 rounded-lg p-6 space-y-3">
                <Button onClick={addAssessment} className="w-full bg-green-600 hover:bg-green-700">
                  Add Initial Assessment
                </Button>
                <p className="text-sm text-slate-600">
                  Adds a blank Assessment entry
                </p>
              </div>
              <div className="border border-slate-200 rounded-lg p-6 space-y-3">
                <Button
                  onClick={addOngoingAssessment}
                  className="w-full bg-blue-600 hover:bg-blue-700"
                >
                  Add Ongoing Assessment
                </Button>
                <p className="text-sm text-slate-600">
                  Autofills from the last assessment. View patient history available.
                </p>
              </div>
            </div>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Card className="p-6" id="assessment-content">
        <div className="space-y-4">
          {assessments.map((assessment) => (
            <div
              key={assessment.id}
              className="border border-slate-200 rounded-lg p-4 space-y-4"
            >
              {/* Time and Actions */}
              <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
                <div className="flex items-center gap-2">
                  <Clock className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                  <Input
                    type="text"
                    value={(assessment as any).editableTime || (assessment as any).time}
                    onChange={(e) => updateAssessmentTime(assessment.id, e.target.value)}
                    className="text-sm h-8 w-64"
                  />
                </div>
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => deleteAssessment(assessment.id)}
                    className="text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
                  >
                    Delete Ax
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => openQuickAx(assessment.id)}
                    className="text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                  >
                    Start Assessment
                  </Button>
                </div>
              </div>

              {/* Body Regions Grid */}
              <div className="grid grid-cols-3 gap-3">
                {Object.entries((assessment as any).regions || {}).map(([key, value]) => (
                  <div key={key} className="space-y-1">
                    <Label className="text-xs">
                      {key
                        .replace(/([A-Z])/g, ' $1')
                        .replace(/^./, (str) => str.toUpperCase())
                        .replace('Heent', 'HEENT')
                        .replace('Pelvis GUI', 'Pelvis/GU/GI')}
                    </Label>
                    <div className={`text-sm ${
                      value === 'Assessed' 
                        ? 'text-green-600 dark:text-green-400 font-medium' 
                        : value === 'No Abnormalities' 
                        ? 'text-blue-600 dark:text-blue-400' 
                        : 'text-slate-600 dark:text-slate-400'
                    }`}>
                      {value as string}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>

        {/* Add Buttons */}
        <div className="flex gap-3 mt-6 pt-6 border-t border-slate-200">
          <Button onClick={addAssessment} className="bg-green-600 hover:bg-green-700">
            Add Initial Assessment
          </Button>
          <Button
            onClick={addOngoingAssessment}
            className="bg-blue-600 hover:bg-blue-700"
          >
            Add Ongoing Assessment
          </Button>
          <Button
            onClick={() => setShowAssessmentHistoryModal(true)}
            variant="outline"
            className="ml-auto"
          >
            <Clock className="h-4 w-4 mr-2" />
            View Patient History
          </Button>
        </div>
      </Card>

      {/* Assessment Modal with Tab-Based Navigation */}
      {showQuickAxDialog && currentAssessmentId && (
        <AssessmentModal
          isOpen={showQuickAxDialog}
          onClose={() => {
            setShowQuickAxDialog(false);
            setCurrentAssessmentId(null);
          }}
          encounterId={currentAssessmentId}
          existingAssessments={
            // Convert current assessment regions to AssessmentModal format
            Object.fromEntries(
              Object.entries(assessments.find((a) => a.id === currentAssessmentId)?.regions || {}).map(
                ([key, value]) => [
                  key,
                  {
                    status: value === 'No Abnormalities' ? 'normal' : value === 'Assessed' ? 'abnormal' : 'not-assessed',
                    notes: '',
                    timestamp: new Date().toISOString(),
                  },
                ]
              )
            )
          }
          onSave={(updatedAssessments) => {
            // Convert AssessmentModal format back to the existing region format
            if (!updatedAssessments) return;
            Object.entries(updatedAssessments).forEach(([region, data]) => {
              const value = data.status === 'normal' ? 'No Abnormalities' :
                           data.status === 'abnormal' ? 'Assessed' : 'Not Assessed';
              updateAssessmentRegion(currentAssessmentId, region, value);
            });
          }}
        />
      )}

      {/* Assessment History Modal */}
      {showAssessmentHistoryModal && (
        <AssessmentHistoryModal
          isOpen={showAssessmentHistoryModal}
          onClose={() => setShowAssessmentHistoryModal(false)}
          patientHistory={patientAssessmentHistory}
          onSelectAssessment={(assessment) => {
            const newAssessment = {
              ...assessment,
              id: Date.now().toString(),
              time: new Date().toLocaleString(),
              editableTime: new Date().toLocaleString(),
            };
            setAssessments([newAssessment, ...assessments]);
            setShowAssessmentHistoryModal(false);
            toast.success('Assessment copied to current encounter');
          }}
        />
      )}
    </div>
  );
}

// Vitals Tab Component
function VitalsTab({
  vitalRows: _vitalRows,
  updateVitalRow: _updateVitalRow,
  addVitalRow: _addVitalRow,
  removeVitalRow: _removeVitalRow,
  onVitalsChange,
  isReadOnly = false,
}: {
  vitalRows: VitalRow[];
  updateVitalRow: (id: string, field: keyof VitalRow, value: string) => void;
  addVitalRow: () => void;
  removeVitalRow: (id: string) => void;
  onVitalsChange?: (vitals: any[]) => void;
  isReadOnly?: boolean;
}) {
  const [showVitalsModal, setShowVitalsModal] = useState(false);
  const [editingVital, setEditingVital] = useState<any>(null);
  
  // Use context for persistent vitals data
  const encounterContext = useEncounter();
  const contextVitals = encounterContext?.vitals ?? [];
  const setContextVitals = encounterContext?.setVitals;
  
  // Wrapper function that updates both context and parent callback
  const setVitals = (newVitals: any[]) => {
    // Update context (primary source of truth)
    if (setContextVitals) {
      setContextVitals(newVitals);
    }
    // Also notify parent for validation tracking
    onVitalsChange?.(newVitals);
  };

  // Use context vitals as the source of truth
  const vitals = contextVitals;

  const addVitalsEntry = () => {
    setEditingVital(null); // Clear any editing state
    setShowVitalsModal(true);
  };

  const editVitalsEntry = (vital: any) => {
    setEditingVital(vital);
    setShowVitalsModal(true);
  };

  const saveVitals = (vitalData: any) => {
    if (editingVital) {
      // Update existing vital (UPDATE)
      const updatedVitals = vitals.map((v) =>
        v.id === editingVital.id ? { ...vitalData, id: editingVital.id } : v
      );
      setVitals(updatedVitals);
      toast.success('Vitals updated');
    } else {
      // Add new vital (INSERT)
      const newVitals = [vitalData, ...vitals];
      setVitals(newVitals);
      toast.success('Vitals saved');
    }
    setShowVitalsModal(false);
    setEditingVital(null);
  };

  const deleteVital = (id: string) => {
    setVitals(vitals.filter((v) => v.id !== id));
    toast.success('Vitals deleted');
  };

  if (vitals.length === 0) {
    return (
      <div className="space-y-6">
        <Card className="p-12" id="vitals-table">
          <div className="max-w-2xl mx-auto space-y-6 text-center">
            <div className="space-y-2 mb-8">
              <h2 className="text-xl">No Vitals</h2>
              <p className="text-slate-600">
                Click the button below to add a vitals entry.
              </p>
            </div>
            <Button onClick={addVitalsEntry} className="bg-green-600 hover:bg-green-700">
              <Plus className="h-4 w-4 mr-2" />
              Add Vitals
            </Button>
          </div>
        </Card>

        {showVitalsModal && (
          <VitalsEntryModal
            isOpen={showVitalsModal}
            onClose={() => setShowVitalsModal(false)}
            onSave={saveVitals}
          />
        )}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Card className="p-6" id="vitals-table">
        <div className="space-y-4">
          {vitals.map((vital) => (
            <div
              key={vital.id}
              className="border border-slate-200 rounded-lg p-4 space-y-4"
            >
              <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
                <div className="flex items-center gap-2">
                  <Clock className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                  <span className="text-sm dark:text-slate-300">{vital.time}</span>
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => editVitalsEntry(vital)}
                    className="text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                  >
                    Edit Vitals
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => deleteVital(vital.id)}
                    className="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                  >
                    Delete Vitals
                  </Button>
                </div>
              </div>

              <div className="grid grid-cols-4 gap-4">
                {vital.bp && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-500">Blood Pressure</Label>
                    <div className="text-sm">{vital.bp}</div>
                  </div>
                )}
                {vital.pulse && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-500">Pulse</Label>
                    <div className="text-sm">{vital.pulse}</div>
                  </div>
                )}
                {(vital as any).respiration && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-500">Respiration</Label>
                    <div className="text-sm">{(vital as any).respiration}</div>
                  </div>
                )}
                {vital.spo2 && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-500">SpO₂</Label>
                    <div className="text-sm">{vital.spo2}%</div>
                  </div>
                )}
                {vital.temp && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-500">Temperature</Label>
                    <div className="text-sm">{vital.temp}°F</div>
                  </div>
                )}
                {(vital as any).glucose && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-500">Blood Glucose</Label>
                    <div className="text-sm">{(vital as any).glucose} mg/dL</div>
                  </div>
                )}
                {(vital as any).pain && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-500">Pain Scale</Label>
                    <div className="text-sm">{(vital as any).pain}/10</div>
                  </div>
                )}
                {(vital as any).avpu && (
                  <div className="space-y-1">
                    <Label className="text-xs text-slate-500">AVPU</Label>
                    <div className="text-sm">{(vital as any).avpu}</div>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>

        <div className="flex gap-3 mt-6 pt-6 border-t border-slate-200">
          <Button onClick={addVitalsEntry} className="bg-green-600 hover:bg-green-700">
            <Plus className="h-4 w-4 mr-2" />
            Add Vitals
          </Button>
        </div>
      </Card>

      {showVitalsModal && (
        <VitalsEntryModal
          isOpen={showVitalsModal}
          onClose={() => {
            setShowVitalsModal(false);
            setEditingVital(null);
          }}
          onSave={saveVitals}
          initialData={editingVital}
        />
      )}
    </div>
  );
}

// Treatment Tab Component
function TreatmentTab({ isReadOnly = false }: { isReadOnly?: boolean }) {
  const [showInterventionModal, setShowInterventionModal] = useState(false);
  
  // Use context for persistent treatments/interventions data
  const encounterContext = useEncounter();
  const contextTreatments = encounterContext?.treatments ?? [];
  const setContextTreatments = encounterContext?.setTreatments;
  
  // Wrapper function that updates context
  const setInterventions = (newInterventions: any[]) => {
    if (setContextTreatments) {
      setContextTreatments(newInterventions);
    }
  };
  
  // Use context treatments as the source of truth
  const interventions = contextTreatments;

  const addInterventionEntry = (intervention: any) => {
    setInterventions([intervention, ...interventions]);
    setShowInterventionModal(false);
  };

  const deleteIntervention = (id: string) => {
    setInterventions(interventions.filter((i: any) => i.id !== id));
  };

  return (
    <div className="space-y-6">
      <Card className="p-6" id="interventions">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg">Interventions</h2>
          <Button
            onClick={() => setShowInterventionModal(true)}
            className="bg-green-600 hover:bg-green-700"
          >
            <Plus className="h-4 w-4 mr-2" />
            Add Intervention
          </Button>
        </div>

        {interventions.length === 0 ? (
          <div className="text-center py-12">
            <p className="text-slate-500 mb-4">No interventions recorded.</p>
            <Button
              onClick={() => setShowInterventionModal(true)}
              className="bg-green-600 hover:bg-green-700"
            >
              <Plus className="h-4 w-4 mr-2" />
              Add Intervention
            </Button>
          </div>
        ) : (
          <div className="space-y-4">
            {interventions.map((intervention) => (
              <div
                key={intervention.id}
                className="border border-slate-200 rounded-lg p-4 space-y-3"
              >
                <div className="flex items-center justify-between pb-3 border-b border-slate-200">
                  <div className="flex items-center gap-2">
                    <Clock className="h-4 w-4 text-slate-500" />
                    <span className="text-sm">{intervention.time}</span>
                  </div>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => deleteIntervention(intervention.id)}
                    className="text-red-600 hover:text-red-700 hover:bg-red-50"
                  >
                    Delete
                  </Button>
                </div>
                <div>
                  <h3 className="font-medium">{(intervention as any).name}</h3>
                  {(intervention as any).category && (
                    <Badge variant="outline" className="mt-2">
                      {(intervention as any).category}
                    </Badge>
                  )}
                </div>
                {(intervention as any).details && (
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    {Object.entries((intervention as any).details).map(([key, value]) => (
                      <div key={key}>
                        <span className="text-slate-500">{key}: </span>
                        <span className="font-medium">{value as string}</span>
                      </div>
                    ))}
                  </div>
                )}
                {intervention.notes && (
                  <div className="text-sm text-slate-600 mt-2">
                    <span className="text-slate-500">Notes: </span>
                    {intervention.notes}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </Card>

      {showInterventionModal && (
        <InterventionModal
          isOpen={showInterventionModal}
          onClose={() => setShowInterventionModal(false)}
          onSave={addInterventionEntry}
        />
      )}
    </div>
  );
}

// Intervention Modal Component
function InterventionModal({
  isOpen,
  onClose,
  onSave,
}: {
  isOpen: boolean;
  onClose: () => void;
  onSave: (intervention: any) => void;
}) {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('criticalCare');
  const [selectedIntervention, setSelectedIntervention] = useState<string | null>(null);
  const [interventionDetails, setInterventionDetails] = useState<any>({
    time: new Date().toLocaleString(),
    dosage: '',
    route: '',
    notes: '',
  });
  // Mobile sidebar collapse state
  const [mobileSidebarCollapsed, setMobileSidebarCollapsed] = useState(true);

  if (!isOpen) return null;

  const categories = [
    { id: 'criticalCare', label: 'CRITICAL CARE' },
    { id: 'basicCare', label: 'BASIC CARE' },
    { id: 'airway', label: 'AIRWAY MANAGEMENT' },
    { id: 'ivAccess', label: 'IV ACCESS' },
    { id: 'medications', label: 'MEDICATIONS' },
    { id: 'procedures', label: 'PROCEDURES' },
    { id: 'diagnostics', label: 'DIAGNOSTICS' },
  ];

  const interventionsByCategory: Record<string, string[]> = {
    criticalCare: [
      'ALS Assessment',
      'Arterial Line Care',
      'Balloon Pump Care',
      'Chest Tube',
      'ECMO',
      'Foley Catheter',
      'Impella Device Care',
      'Lab Values - Blood Gases',
      'Lab Values - Cardiac',
      'Lab Values - CBC',
      'Lab Values - CMP',
      'Lab Values - Coagulation',
      'Pericardiocentesis',
      'REBOA',
      'Ventricular Assist Device Care',
    ],
    basicCare: [
      'Wound Care',
      'Splinting',
      'Ice Pack Application',
      'Heat Pack Application',
      'Bandaging',
      'Dressing Change',
      'Patient Positioning',
      'Range of Motion',
    ],
    airway: [
      'Oral Airway',
      'Nasal Airway',
      'Endotracheal Intubation',
      'Bag Valve Mask',
      'Oxygen Administration',
      'Suction',
      'Cricothyrotomy',
      'Tracheostomy Care',
    ],
    ivAccess: [
      'Peripheral IV',
      'Central Line',
      'Intraosseous Access',
      'IV Fluid Administration',
      'Blood Transfusion',
    ],
    medications: [
      'Aspirin',
      'Nitroglycerin',
      'Epinephrine',
      'Naloxone',
      'Albuterol',
      'Ibuprofen',
      'Acetaminophen',
      'Diphenhydramine',
    ],
    procedures: [
      'CPR',
      'Defibrillation',
      'Cardioversion',
      'Needle Decompression',
      'Tourniquet Application',
      'Spinal Immobilization',
      'Fracture Stabilization',
    ],
    diagnostics: [
      '12-Lead ECG',
      'Point-of-Care Glucose',
      'Cardiac Monitor',
      'Pulse Oximetry',
      'Capnography',
      'Blood Pressure Monitoring',
    ],
  };

  const filteredInterventions = interventionsByCategory[selectedCategory].filter((item) =>
    item.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const handleInterventionClick = (interventionName: string) => {
    setSelectedIntervention(interventionName);
  };

  const handleSave = () => {
    if (selectedIntervention) {
      onSave({
        id: Date.now().toString(),
        name: selectedIntervention,
        category: categories.find((c) => c.id === selectedCategory)?.label,
        time: interventionDetails.time,
        details: {
          Dosage: interventionDetails.dosage,
          Route: interventionDetails.route,
        },
        notes: interventionDetails.notes,
      });
      onClose();
    }
  };

  if (selectedIntervention) {
    return (
      <div className="fixed inset-0 bg-black/60 dark:bg-black/70 flex items-center justify-center z-50">
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-2xl max-h-[80vh] overflow-auto border border-slate-200 dark:border-slate-600">
          <div className="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl">{selectedIntervention}</h2>
                <Badge variant="outline" className="mt-2">
                  {categories.find((c) => c.id === selectedCategory)?.label}
                </Badge>
              </div>
              <Button variant="ghost" size="sm" onClick={() => setSelectedIntervention(null)}>
                <ArrowLeft className="h-4 w-4 mr-2" />
                Back
              </Button>
            </div>
          </div>

          <div className="p-6 space-y-6">
            <div className="space-y-2">
              <Label>Time</Label>
              <Input
                type="datetime-local"
                value={interventionDetails.time}
                onChange={(e) =>
                  setInterventionDetails({ ...interventionDetails, time: e.target.value })
                }
              />
            </div>

            <div className="grid md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Dosage</Label>
                <Input
                  value={interventionDetails.dosage}
                  onChange={(e) =>
                    setInterventionDetails({ ...interventionDetails, dosage: e.target.value })
                  }
                />
                <p className="text-xs text-slate-500">e.g., 200mg</p>
              </div>
              <div className="space-y-2">
                <Label>Route</Label>
                <Select
                  value={interventionDetails.route}
                  onValueChange={(value) =>
                    setInterventionDetails({ ...interventionDetails, route: value })
                  }
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select route" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="oral">Oral</SelectItem>
                    <SelectItem value="iv">IV</SelectItem>
                    <SelectItem value="im">IM</SelectItem>
                    <SelectItem value="sq">SQ</SelectItem>
                    <SelectItem value="topical">Topical</SelectItem>
                    <SelectItem value="inhalation">Inhalation</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label>Notes</Label>
              <Textarea
                rows={4}
                value={interventionDetails.notes}
                onChange={(e) =>
                  setInterventionDetails({ ...interventionDetails, notes: e.target.value })
                }
              />
              <p className="text-xs text-slate-500">Additional notes or observations</p>
            </div>

            <div className="flex justify-end gap-3">
              <Button variant="outline" onClick={() => setSelectedIntervention(null)}>
                Cancel
              </Button>
              <Button onClick={handleSave} className="bg-green-600 hover:bg-green-700">
                Save Intervention
              </Button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 bg-black/60 dark:bg-black/70 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-6xl h-[90vh] flex flex-col border border-slate-200 dark:border-slate-600">
        <div className="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 flex-shrink-0">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl">
              {categories.find((c) => c.id === selectedCategory)?.label || 'INTERVENTIONS'}
            </h2>
            <Button variant="ghost" size="sm" onClick={onClose}>
              <X className="h-4 w-4" />
            </Button>
          </div>

          <div className="flex items-center gap-4">
            <div className="flex-1 relative">
              <Input
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-10"
              />
              <div className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔍</div>
            </div>
            <Button className="bg-green-600 hover:bg-green-700">OK</Button>
          </div>
        </div>

        <div className="flex flex-1 min-h-0">
          {/* Mobile Toggle Button for Sidebar - Only visible on mobile */}
          <button
            type="button"
            onClick={() => setMobileSidebarCollapsed(!mobileSidebarCollapsed)}
            className="md:hidden fixed bottom-4 left-4 z-50 p-3 bg-green-600 hover:bg-green-700 text-white rounded-full shadow-lg"
            aria-label={mobileSidebarCollapsed ? "Show categories" : "Hide categories"}
          >
            {mobileSidebarCollapsed ? (
              <ChevronRight className="h-5 w-5" />
            ) : (
              <ChevronDown className="h-5 w-5" />
            )}
          </button>
          
          {/* Category Sidebar - collapsible on mobile */}
          <div className={`
            bg-slate-50 dark:bg-slate-900 border-r border-slate-200 dark:border-slate-700 overflow-y-auto flex-shrink-0
            transition-all duration-300 ease-in-out
            ${mobileSidebarCollapsed ? 'hidden md:block md:w-64' : 'w-full md:w-64'}
          `}>
            {categories.map((category) => (
              <button
                key={category.id}
                onClick={() => {
                  setSelectedCategory(category.id);
                  // Auto-collapse sidebar on mobile after selection
                  if (window.innerWidth < 768) {
                    setMobileSidebarCollapsed(true);
                  }
                }}
                className={`w-full p-4 text-left border-b border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors ${
                  selectedCategory === category.id
                    ? 'bg-green-100 dark:bg-green-900/30 border-l-4 border-green-600 font-medium'
                    : 'dark:text-slate-300'
                }`}
              >
                {category.label}
              </button>
            ))}
          </div>

          {/* Intervention Tiles - Dark mode enhanced */}
          <div className="flex-1 p-6 overflow-y-auto bg-slate-100 dark:bg-slate-800">
            <div className="grid grid-cols-3 gap-4">
              {filteredInterventions.map((intervention) => (
                <button
                  key={intervention}
                  onClick={() => handleInterventionClick(intervention)}
                  className="bg-slate-700 dark:bg-slate-600 hover:bg-slate-600 dark:hover:bg-slate-500 text-white p-6 rounded-lg text-left transition-colors shadow-md hover:shadow-lg"
                >
                  <div className="font-medium">{intervention}</div>
                </button>
              ))}
            </div>

            {filteredInterventions.length === 0 && (
              <div className="text-center text-slate-500 dark:text-slate-400 py-12">
                No interventions found matching "{searchQuery}"
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

// Narrative Tab Component
function NarrativeTab({
  narrativeText,
  onNarrativeChange,
  encounterId,
  isReadOnly = false,
}: {
  narrativeText: string;
  onNarrativeChange: (value: string) => void;
  encounterId: string;
  isReadOnly?: boolean;
}) {
  // Use context for persistent narrative data
  const encounterContext = useEncounter();
  const contextNarrative = encounterContext?.narrative ?? '';
  const setContextNarrative = encounterContext?.setNarrative;
  
  // Use useNarrative hook for AI generation
  const {
    narrative: generatedNarrative,
    isGenerating,
    error: narrativeError,
    generateNarrative,
    clearError
  } = useNarrative();
  
  // Wrapper function that updates both context and parent callback
  const handleNarrativeChange = (value: string) => {
    // Update context (primary source of truth)
    if (setContextNarrative) {
      setContextNarrative(value);
    }
    // Also notify parent for validation tracking
    onNarrativeChange(value);
  };
  
  // Use context narrative as the source of truth (fallback to prop if context empty)
  const effectiveNarrative = contextNarrative || narrativeText;
  
  // Handle AI narrative generation
  const handleGenerateNarrative = async () => {
    if (!encounterId || encounterId === 'new') {
      toast.error('Please save the encounter first before generating a narrative');
      return;
    }
    
    // Clear any previous errors
    clearError();
    
    try {
      await generateNarrative(encounterId);
    } catch (err) {
      // Error is already handled in the hook and stored in narrativeError
      console.error('[NarrativeTab] Generate narrative error:', err);
    }
  };
  
  // Effect to update narrative field when AI generation completes
  useEffect(() => {
    if (generatedNarrative && !isGenerating) {
      handleNarrativeChange(generatedNarrative);
      toast.success('Narrative generated successfully!', {
        description: 'You can edit the generated text before saving.',
        duration: 4000,
      });
    }
  }, [generatedNarrative, isGenerating]);
  
  // Effect to show error toast when generation fails
  useEffect(() => {
    if (narrativeError) {
      toast.error('Failed to generate narrative', {
        description: narrativeError,
        duration: 5000,
      });
    }
  }, [narrativeError]);
  
  // Determine if generate button should be disabled
  const isGenerateDisabled = !encounterId || encounterId === 'new' || isGenerating;
  
  return (
    <Card className="p-6" id="clinical-narrative">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg">Clinical Narrative *</h2>
        {!isReadOnly && (
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="outline"
              onClick={handleGenerateNarrative}
              disabled={isGenerateDisabled}
              title={!encounterId || encounterId === 'new'
                ? 'Save encounter first to enable AI generation'
                : isGenerating
                  ? 'Generating narrative...'
                  : 'Generate narrative using AI'}
            >
              {isGenerating ? (
                <>
                  <Clock className="h-4 w-4 mr-2 animate-spin" />
                  Generating...
                </>
              ) : (
                <>
                  <Sparkles className="h-4 w-4 mr-2" />
                  Generate with AI
                </>
              )}
            </Button>
            <Button size="sm" variant="outline">
              Audit
            </Button>
          </div>
        )}
      </div>
      
      {/* Error Banner */}
      {narrativeError && (
        <div className="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-start gap-2">
          <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
          <div className="flex-1">
            <p className="text-sm text-red-800 dark:text-red-200">{narrativeError}</p>
            <button
              onClick={clearError}
              className="text-xs text-red-600 dark:text-red-400 underline hover:no-underline mt-1"
            >
              Dismiss
            </button>
          </div>
        </div>
      )}
      
      <div className="space-y-4">
        <Textarea
          id="narrativeText"
          rows={20}
          value={effectiveNarrative}
          onChange={(e) => handleNarrativeChange(e.target.value)}
          className={`resize-y min-h-[100px] max-h-[800px] ${isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}`}
          disabled={isGenerating}
          readOnly={isReadOnly}
          placeholder={isGenerating ? 'Generating narrative with AI...' : 'Enter clinical narrative...'}
        />
        <p className="text-xs text-slate-500">
          Minimum 25 characters required. Document comprehensive clinical narrative including chief complaint, history of present illness, assessment, interventions, and patient response.
          {effectiveNarrative.length > 0 && (
            <span className={effectiveNarrative.length >= 25 ? 'text-green-600' : 'text-amber-600'}>
              {' '}({effectiveNarrative.length}/25 characters)
            </span>
          )}
        </p>
      </div>
    </Card>
  );
}

// Disposition Tab Component
function DispositionTab({
  disposition,
  setDisposition,
  dispositionNotes,
  setDispositionNotes,
  expectedFollowUpDate,
  setExpectedFollowUpDate,
  sendSms,
  setSendSms,
  appointments,
  setAppointments,
  patientPhone,
  patientId,
  encounterId,
  incidentForm,
  isReadOnly = false,
}: {
  disposition: string;
  setDisposition: (value: string) => void;
  dispositionNotes: string;
  setDispositionNotes: (value: string) => void;
  expectedFollowUpDate: string;
  setExpectedFollowUpDate: (value: string) => void;
  sendSms: boolean;
  setSendSms: (value: boolean) => void;
  appointments: any[];
  setAppointments: (value: any[]) => void;
  patientPhone: string;
  patientId: string;
  encounterId: string;
  incidentForm: any;
  isReadOnly?: boolean;
}) {
  const [showAppointmentModal, setShowAppointmentModal] = useState(false);
  const [currentAppointment, setCurrentAppointment] = useState<any>(null);
  const [appointmentType, setAppointmentType] = useState('');
  const [appointmentDate, setAppointmentDate] = useState(new Date().toISOString().slice(0, 16));
  const [imagingType, setImagingType] = useState('');
  const [doctorAppointmentType, setDoctorAppointmentType] = useState('');
  const [followUpOutcome, setFollowUpOutcome] = useState('unknown');
  const [followUpDate, setFollowUpDate] = useState('');
  
  // SMS Reminder state
  const [sendingSms, setSendingSms] = useState(false);
  const [smsHistory, setSmsHistory] = useState<Array<{ sentAt: string; status: string }>>([]);
  
  // Document upload state
  const [uploadingDocument, setUploadingDocument] = useState(false);
  const [appointmentDocuments, setAppointmentDocuments] = useState<any[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const dispositionOptions = [
    { value: 'return-full-duty', label: 'Return to Full Duty' },
    { value: 'light-duty', label: 'Light Duty' },
    { value: 'off-duty', label: 'Off Duty' },
    { value: 'referred-provider', label: 'Referred to Provider' },
    { value: 'in-clinic', label: 'In Clinic' },
  ];

  // Handle SMS Reminder Send
  const handleSendSmsReminder = async () => {
    // Validate phone number
    if (!patientPhone) {
      toast.error('No phone number on file for patient');
      return;
    }
    
    if (!validatePhoneNumber(patientPhone)) {
      toast.error('Invalid phone number format');
      return;
    }
    
    // Check if there's at least one appointment
    if (appointments.length === 0) {
      toast.error('Please add an appointment first before sending SMS reminder');
      return;
    }
    
    // Get the first appointment with a known date
    const appointmentWithDate = appointments.find(app => app.date);
    if (!appointmentWithDate) {
      toast.error('No appointment with date found');
      return;
    }
    
    setSendingSms(true);
    try {
      const appointmentDateTime = new Date(appointmentWithDate.date);
      const response = await sendSMSReminder({
        patientId: parseInt(patientId) || 0,
        encounterId: parseInt(encounterId) || 0,
        phoneNumber: patientPhone,
        appointmentDate: appointmentDateTime.toLocaleDateString(),
        appointmentTime: appointmentDateTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        clinicName: incidentForm?.clinicName || 'SafeShift Clinic',
      });
      
      if (response.success) {
        toast.success('SMS reminder sent successfully');
        setSmsHistory([...smsHistory, { sentAt: new Date().toISOString(), status: 'sent' }]);
      } else {
        toast.error(response.error || 'Failed to send SMS reminder');
      }
    } catch (error: any) {
      console.error('Error sending SMS:', error);
      toast.error(error.message || 'Failed to send SMS reminder');
    } finally {
      setSendingSms(false);
    }
  };

  // Handle Document Upload
  const handleDocumentUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    
    // Validate file type
    if (!validateFileType(file)) {
      toast.error(`Invalid file type. Allowed types: ${Object.keys(ALLOWED_FILE_TYPES).join(', ')}`);
      return;
    }
    
    // Validate file size
    if (!validateFileSize(file)) {
      toast.error(`File too large. Maximum size: ${MAX_FILE_SIZE / (1024 * 1024)}MB`);
      return;
    }
    
    setUploadingDocument(true);
    try {
      const response = await uploadAppointmentDocument(
        file,
        parseInt(encounterId) || 0,
        'appointment_card',
        'Appointment document uploaded from disposition tab'
      );
      
      if (response.success && response.document) {
        toast.success('Document uploaded successfully');
        setAppointmentDocuments([...appointmentDocuments, response.document]);
      } else {
        toast.error(response.error || 'Failed to upload document');
      }
    } catch (error: any) {
      console.error('Error uploading document:', error);
      toast.error(error.message || 'Failed to upload document');
    } finally {
      setUploadingDocument(false);
      // Reset file input
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const openAddAppointmentModal = () => {
    setCurrentAppointment(null);
    setAppointmentType('');
    setAppointmentDate(new Date().toISOString().slice(0, 16));
    setImagingType('');
    setDoctorAppointmentType('');
    setFollowUpOutcome('unknown');
    setFollowUpDate('');
    setShowAppointmentModal(true);
  };

  const saveAppointment = () => {
    const newAppointment = {
      id: currentAppointment?.id || Date.now().toString(),
      type: appointmentType,
      date: appointmentDate,
      imagingType: appointmentType === 'imaging' ? imagingType : undefined,
      doctorAppointmentType: appointmentType === 'doctor' ? doctorAppointmentType : undefined,
      followUpOutcome,
      followUpDate: followUpOutcome === 'known' ? followUpDate : undefined,
    };

    if (currentAppointment) {
      setAppointments(appointments.map(app => app.id === currentAppointment.id ? newAppointment : app));
      toast.success('Appointment updated');
    } else {
      setAppointments([...appointments, newAppointment]);
      toast.success('Appointment added');
    }
    setShowAppointmentModal(false);
  };

  const editAppointment = (appointment: any) => {
    setCurrentAppointment(appointment);
    setAppointmentType(appointment.type);
    setAppointmentDate(appointment.date);
    setImagingType(appointment.imagingType || '');
    setDoctorAppointmentType(appointment.doctorAppointmentType || '');
    setFollowUpOutcome(appointment.followUpOutcome || 'unknown');
    setFollowUpDate(appointment.followUpDate || '');
    setShowAppointmentModal(true);
  };

  const deleteAppointment = (id: string) => {
    setAppointments(appointments.filter(app => app.id !== id));
    toast.success('Appointment removed');
  };

  const getAppointmentTypeLabel = (type: string) => {
    switch(type) {
      case 'imaging': return 'Imaging';
      case 'doctor': return 'Doctor\'s Appointment';
      default: return type;
    }
  };

  return (
    <>
      <Card className="p-6" id="disposition-outcome">
        <h2 className="text-lg mb-4">Disposition</h2>
        <div className="space-y-6">
          <div className="space-y-2">
            <Label>Disposition Outcome</Label>
            <Select value={disposition} onValueChange={(v) => !isReadOnly && setDisposition(v)} disabled={isReadOnly}>
              <SelectTrigger className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}>
                <SelectValue placeholder="Select disposition..." />
              </SelectTrigger>
              <SelectContent>
                {dispositionOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="grid md:grid-cols-2 gap-6">
            <div className="space-y-2">
              <Label htmlFor="expectedFollowUpDate">Expected Follow-Up Date</Label>
              <Input
                id="expectedFollowUpDate"
                type="date"
                value={expectedFollowUpDate}
                onChange={(e) => setExpectedFollowUpDate(e.target.value)}
                readOnly={isReadOnly}
                className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="sendSms" className="flex items-center gap-2 cursor-pointer">
                <input
                  id="sendSms"
                  type="checkbox"
                  checked={sendSms}
                  onChange={(e) => !isReadOnly && setSendSms(e.target.checked)}
                  disabled={isReadOnly}
                  className={`w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 ${isReadOnly ? 'cursor-not-allowed opacity-60' : ''}`}
                />
                Send SMS Reminder
              </Label>
              <p className="text-sm text-slate-500">Patient will receive an SMS reminder about follow-up</p>
            </div>
          </div>

          <div className="space-y-2">
                <Label>Follow-Up Instructions & Notes</Label>
                <Textarea
                  rows={6}
                  value={dispositionNotes}
                  onChange={(e) => setDispositionNotes(e.target.value)}
                  readOnly={isReadOnly}
                  className={isReadOnly ? 'bg-slate-100 dark:bg-slate-700 cursor-not-allowed' : ''}
                />
                <p className="text-xs text-slate-500">Document follow-up instructions, restrictions, next steps, and any additional notes</p>
              </div>
        </div>
      </Card>

      {/* SMS Reminder Section */}
      <Card className="p-6 mt-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg">SMS Reminder</h2>
          <div className="flex items-center gap-2">
            {patientPhone ? (
              <span className="text-sm text-slate-600 dark:text-slate-400">
                Phone: {patientPhone}
              </span>
            ) : (
              <span className="text-sm text-amber-600 dark:text-amber-400">
                No phone number on file
              </span>
            )}
          </div>
        </div>
        
        <div className="space-y-4">
          <p className="text-sm text-slate-600 dark:text-slate-400">
            Send an SMS reminder to the patient about their upcoming appointment. The message will include the appointment date, time, and clinic name.
          </p>
          
          <div className="flex items-center gap-4">
            <Button
              onClick={handleSendSmsReminder}
              disabled={sendingSms || !patientPhone || appointments.length === 0}
              variant="outline"
              className="flex items-center gap-2"
            >
              {sendingSms ? (
                <>
                  <Clock className="h-4 w-4 animate-spin" />
                  Sending...
                </>
              ) : (
                <>
                  <MessageSquare className="h-4 w-4" />
                  Send SMS Reminder
                </>
              )}
            </Button>
            
            {appointments.length === 0 && (
              <span className="text-sm text-amber-600 dark:text-amber-400">
                Add an appointment first to send SMS reminder
              </span>
            )}
          </div>
          
          {/* SMS History */}
          {smsHistory.length > 0 && (
            <div className="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
              <h4 className="text-sm font-medium mb-2">SMS History</h4>
              <div className="space-y-2">
                {smsHistory.map((sms, index) => (
                  <div key={index} className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                    <span>Sent on {new Date(sms.sentAt).toLocaleString()}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </Card>

      {/* Appointments Section */}
      <Card className="p-6 mt-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg">Appointments & Procedures</h2>
          <div className="flex items-center gap-2">
            <Button
              onClick={openAddAppointmentModal}
              size="sm"
              className="text-white bg-blue-600 hover:bg-blue-700"
            >
              <Plus className="h-4 w-4 mr-2" />
              Add Appointment
            </Button>
            <Button
              variant="ghost"
              size="icon"
              onClick={() => fileInputRef.current?.click()}
              disabled={uploadingDocument}
              title="Upload appointment document (photo, PDF)"
              className="h-9 w-9"
            >
              {uploadingDocument ? (
                <Clock className="h-4 w-4 animate-spin" />
              ) : (
                <Camera className="h-4 w-4" />
              )}
            </Button>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*,.pdf"
              className="hidden"
              onChange={handleDocumentUpload}
            />
          </div>
        </div>

        {appointments.length === 0 ? (
          <p className="text-sm text-slate-500 text-center py-8">No appointments added yet</p>
        ) : (
          <div className="space-y-3">
            {appointments.map((appointment) => (
              <div
                key={appointment.id}
                className="p-4 border border-slate-200 dark:border-slate-700 rounded-lg flex items-start justify-between"
              >
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    {appointment.type === 'imaging' && <Activity className="h-4 w-4 text-blue-600 dark:text-blue-400" />}
                    {appointment.type === 'doctor' && <User className="h-4 w-4 text-green-600 dark:text-green-400" />}
                    <span className="font-medium">{getAppointmentTypeLabel(appointment.type)}</span>
                  </div>
                  <div className="text-sm text-slate-600 dark:text-slate-400 space-y-1">
                    <div>Date/Time: {new Date(appointment.date).toLocaleString()}</div>
                    {appointment.imagingType && <div>Type: {appointment.imagingType}</div>}
                    {appointment.doctorAppointmentType && <div>Referral: {appointment.doctorAppointmentType}</div>}
                    {appointment.followUpOutcome === 'known' && appointment.followUpDate && (
                      <div>Follow-up: {new Date(appointment.followUpDate).toLocaleDateString()}</div>
                    )}
                    {appointment.followUpOutcome === 'unknown' && (
                      <div className="text-amber-600 dark:text-amber-400">Follow-up date unknown</div>
                    )}
                  </div>
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => editAppointment(appointment)}
                  >
                    <PenTool className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => deleteAppointment(appointment.id)}
                    className="text-red-600 hover:text-red-700"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Uploaded Documents Section */}
        {appointmentDocuments.length > 0 && (
          <div className="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
            <h3 className="text-md font-medium mb-3 flex items-center gap-2">
              <Image className="h-4 w-4" />
              Uploaded Documents
            </h3>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              {appointmentDocuments.map((doc, index) => (
                <div
                  key={doc.id || index}
                  className="border border-slate-200 dark:border-slate-700 rounded-lg p-3 space-y-2"
                >
                  <div className="flex items-center gap-2">
                    {doc.file_type?.startsWith('image/') ? (
                      <Image className="h-4 w-4 text-blue-600" />
                    ) : (
                      <FileText className="h-4 w-4 text-red-600" />
                    )}
                    <span className="text-sm font-medium truncate" title={doc.original_name}>
                      {doc.original_name}
                    </span>
                  </div>
                  <div className="text-xs text-slate-500">
                    {doc.document_type?.replace('_', ' ')}
                  </div>
                  <div className="text-xs text-slate-400">
                    {new Date(doc.uploaded_at).toLocaleString()}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </Card>

      {/* Appointment Modal */}
      {showAppointmentModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <Card className="w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div className="p-6">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl">{currentAppointment ? 'Edit Appointment' : 'Add Appointment'}</h2>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setShowAppointmentModal(false)}
                >
                  <X className="h-5 w-5" />
                </Button>
              </div>

              <div className="space-y-6">
                <div className="space-y-2">
                  <Label>Appointment Type</Label>
                  <Select value={appointmentType} onValueChange={setAppointmentType}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select type..." />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="imaging">Imaging</SelectItem>
                      <SelectItem value="doctor">Doctor's Appointment</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="appointmentDate">Date & Time</Label>
                  <Input
                    id="appointmentDate"
                    type="datetime-local"
                    value={appointmentDate}
                    onChange={(e) => setAppointmentDate(e.target.value)}
                  />
                </div>

                {appointmentType === 'imaging' && (
                  <div className="space-y-2">
                    <Label>Imaging Type</Label>
                    <Select value={imagingType} onValueChange={setImagingType}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select imaging type..." />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="x-ray">X-Ray</SelectItem>
                        <SelectItem value="mri">MRI</SelectItem>
                        <SelectItem value="ct-scan">CT Scan</SelectItem>
                        <SelectItem value="ultrasound">Ultrasound</SelectItem>
                        <SelectItem value="pet-scan">PET Scan</SelectItem>
                        <SelectItem value="bone-scan">Bone Scan</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                )}

                {appointmentType === 'doctor' && (
                  <div className="space-y-2">
                    <Label>Referral Type</Label>
                    <Select value={doctorAppointmentType} onValueChange={setDoctorAppointmentType}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select referral type..." />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="telehealth">Telehealth</SelectItem>
                        <SelectItem value="urgent-care">Referred to Urgent Care</SelectItem>
                        <SelectItem value="primary-care">Referred to Primary Care Physician</SelectItem>
                        <SelectItem value="specialist">Referred to Specialist</SelectItem>
                        <SelectItem value="emergency">Emergency Department</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                )}

                <div className="space-y-2">
                  <Label>Follow-Up Status</Label>
                  <Select value={followUpOutcome} onValueChange={setFollowUpOutcome}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="unknown">Don't know date of follow-up</SelectItem>
                      <SelectItem value="known">Date of follow-up is</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {followUpOutcome === 'known' && (
                  <div className="space-y-2">
                    <Label htmlFor="followUpDate">Follow-Up Date</Label>
                    <Input
                      id="followUpDate"
                      type="date"
                      value={followUpDate}
                      onChange={(e) => setFollowUpDate(e.target.value)}
                    />
                  </div>
                )}

                <div className="flex gap-3 pt-4">
                  <Button
                    onClick={saveAppointment}
                    disabled={!appointmentType}
                    className="flex-1 bg-blue-600 hover:bg-blue-700 text-white"
                  >
                    {currentAppointment ? 'Update' : 'Add'} Appointment
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => setShowAppointmentModal(false)}
                    className="flex-1"
                  >
                    Cancel
                  </Button>
                </div>
              </div>
            </div>
          </Card>
        </div>
      )}
    </>
  );
}

// Signatures Tab Component
// TODO: LEGAL REVIEW REQUIRED - The disclosure text content should be reviewed
// by legal counsel before production deployment.
function SignaturesTab({
  providerSignature: _providerSignature,
  setProviderSignature: _setProviderSignature2,
  witnessSignature: _witnessSignature,
  setWitnessSignature: _setWitnessSignature,
  encounterId,
  isWorkRelated,
  onDisclosureChange,
  isReadOnly = false,
}: {
  providerSignature: string;
  setProviderSignature: (value: string) => void;
  witnessSignature: string;
  setWitnessSignature: (value: string) => void;
  encounterId: string;
  isWorkRelated: boolean;
  onDisclosureChange?: (acknowledgments: Record<string, boolean>) => void;
  isReadOnly?: boolean;
}) {
  const patientSigRef = useRef<SignatureCanvas | null>(null);
  const parentSigRef = useRef<SignatureCanvas | null>(null);
  const providerSigRef = useRef<SignatureCanvas | null>(null);
  const [patientSignature, setPatientSignature] = useState<string>('');
  const [parentSignature, setParentSignature] = useState<string>('');
  const [providerSignatureData, setProviderSignatureData] = useState<string>('');
  const [patientSignedAt, setPatientSignedAt] = useState<Date | null>(null);
  const [parentSignedAt, setParentSignedAt] = useState<Date | null>(null);
  const [providerSignedAt, setProviderSignedAt] = useState<Date | null>(null);
  
  // Auto-save state
  const [lastAutoSavedPatient, setLastAutoSavedPatient] = useState<string>('');
  const [lastAutoSavedParent, setLastAutoSavedParent] = useState<string>('');
  const [lastAutoSavedProvider, setLastAutoSavedProvider] = useState<string>('');
  const [_autoSaveStatus, setAutoSaveStatus] = useState<'idle' | 'saving' | 'saved'>('idle');
  
  // Debounced auto-save for signature on click-out/blur
  const autoSaveSignature = useCallback((
    sigRef: React.RefObject<SignatureCanvas | null>,
    lastSaved: string,
    setLastSaved: (val: string) => void,
    _signatureType: 'patient' | 'parent' | 'provider'
  ) => {
    if (sigRef.current && !sigRef.current.isEmpty()) {
      const currentData = sigRef.current.toDataURL();
      
      // Only save if signature has changed since last auto-save
      if (currentData !== lastSaved) {
        setAutoSaveStatus('saving');
        
        // Simulate subtle save (in production, this would call API)
        setTimeout(() => {
          setLastSaved(currentData);
          setAutoSaveStatus('saved');
          
          // Brief "Saved" status, then return to idle
          setTimeout(() => {
            setAutoSaveStatus('idle');
          }, 1500);
        }, 300);
      }
    }
  }, []);
  
  // Handle signature pad blur/click-out for auto-save
  const handlePatientSignatureBlur = useCallback(() => {
    autoSaveSignature(patientSigRef, lastAutoSavedPatient, setLastAutoSavedPatient, 'patient');
  }, [autoSaveSignature, lastAutoSavedPatient]);
  
  const handleParentSignatureBlur = useCallback(() => {
    autoSaveSignature(parentSigRef, lastAutoSavedParent, setLastAutoSavedParent, 'parent');
  }, [autoSaveSignature, lastAutoSavedParent]);
  
  const handleProviderSignatureBlur = useCallback(() => {
    autoSaveSignature(providerSigRef, lastAutoSavedProvider, setLastAutoSavedProvider, 'provider');
  }, [autoSaveSignature, lastAutoSavedProvider]);
  
  // Disclosure state
  const [disclosures, setDisclosures] = useState<DisclosureTemplate[]>([]);
  const [acknowledgments, setAcknowledgments] = useState<Record<string, boolean>>({});
  const [allAcknowledged, setAllAcknowledged] = useState(false);
  const [loadingDisclosures, setLoadingDisclosures] = useState(true);
  const [savingDisclosures, setSavingDisclosures] = useState(false);

  // Default disclosures to use when API fails or returns empty
  const getDefaultDisclosures = (): DisclosureTemplate[] => {
    return [
      {
        id: 1,
        disclosure_type: 'consent_for_treatment',
        title: 'Consent for Treatment Documentation',
        content: 'I acknowledge that the information provided is accurate to the best of my knowledge. I consent to this encounter being documented in SafeShift EHR.',
        version: '1.0',
        requires_work_related: false,
        display_order: 1,
      },
      {
        id: 2,
        disclosure_type: 'work_related_authorization',
        title: 'Work-Related Authorization',
        content: 'If this incident is work-related, I authorize SafeShift to notify my employer\'s designated safety contacts with a summary of this incident as required by workplace safety protocols. This summary will not include detailed medical information.',
        version: '1.0',
        requires_work_related: true,
        display_order: 2,
      },
      {
        id: 3,
        disclosure_type: 'hipaa_acknowledgment',
        title: 'HIPAA Privacy Notice Acknowledgment',
        content: 'I acknowledge that I have been provided the opportunity to review the Notice of Privacy Practices, which explains how my health information may be used and disclosed.',
        version: '1.0',
        requires_work_related: false,
        display_order: 3,
      }
    ];
  };

  // Load disclosure templates on mount
  useEffect(() => {
    const loadDisclosures = async () => {
      try {
        setLoadingDisclosures(true);
        const templates = await getDisclosureTemplates();
        
        // Debug logging
        console.log('[SignaturesTab] Loaded templates:', templates);
        
        if (!templates || templates.length === 0) {
          // Provide default disclosures if API returns empty
          console.log('[SignaturesTab] No templates from API, using defaults');
          setDisclosures(getDefaultDisclosures());
        } else {
          setDisclosures(templates);
        }
      } catch (error) {
        console.error('[SignaturesTab] Failed to load disclosure templates:', error);
        // Fallback to default disclosures
        setDisclosures(getDefaultDisclosures());
        toast.error('Using default disclosures - API unavailable');
      } finally {
        setLoadingDisclosures(false);
      }
    };
    loadDisclosures();
  }, []);

  // Filter disclosures based on work-related status
  const applicableDisclosures = filterApplicableDisclosures(disclosures, isWorkRelated);

  // Check if all applicable disclosures are acknowledged
  useEffect(() => {
    const allChecked = areAllDisclosuresAcknowledged(applicableDisclosures, acknowledgments);
    setAllAcknowledged(allChecked);
  }, [acknowledgments, applicableDisclosures]);

  // Handle disclosure checkbox change
  const handleDisclosureChange = (disclosureType: string, checked: boolean) => {
    const newAcknowledgments = {
      ...acknowledgments,
      [disclosureType]: checked
    };
    setAcknowledgments(newAcknowledgments);
    onDisclosureChange?.(newAcknowledgments);
  };

  const clearPatientSignature = () => {
    patientSigRef.current?.clear();
    setPatientSignature('');
    setPatientSignedAt(null);
  };

  const savePatientSignature = async () => {
    if (!allAcknowledged) {
      toast.error('Please acknowledge all disclosures before signing');
      return;
    }
    
    if (patientSigRef.current && !patientSigRef.current.isEmpty()) {
      // First, record the disclosure acknowledgments
      if (encounterId && applicableDisclosures.length > 0) {
        try {
          setSavingDisclosures(true);
          const disclosureRequests = prepareDisclosureRequests(applicableDisclosures);
          await recordBatchDisclosureAcknowledgments(encounterId, disclosureRequests);
        } catch (error) {
          console.error('Failed to record disclosure acknowledgments:', error);
          toast.error('Failed to record disclosure acknowledgments');
          setSavingDisclosures(false);
          return;
        } finally {
          setSavingDisclosures(false);
        }
      }
      
      const dataUrl = patientSigRef.current.toDataURL();
      setPatientSignature(dataUrl);
      setPatientSignedAt(new Date());
      toast.success('Patient signature saved');
    } else {
      toast.error('Please provide a signature');
    }
  };

  const clearParentSignature = () => {
    parentSigRef.current?.clear();
    setParentSignature('');
    setParentSignedAt(null);
  };

  const saveParentSignature = () => {
    if (parentSigRef.current && !parentSigRef.current.isEmpty()) {
      const dataUrl = parentSigRef.current.toDataURL();
      setParentSignature(dataUrl);
      setParentSignedAt(new Date());
      toast.success('Parent/Guardian signature saved');
    } else {
      toast.error('Please provide a signature');
    }
  };

  const clearProviderSignature = () => {
    providerSigRef.current?.clear();
    setProviderSignatureData('');
    setProviderSignedAt(null);
  };

  const saveProviderSignature = () => {
    if (providerSigRef.current && !providerSigRef.current.isEmpty()) {
      const dataUrl = providerSigRef.current.toDataURL();
      setProviderSignatureData(dataUrl);
      setProviderSignedAt(new Date());
      toast.success('Provider signature saved');
    } else {
      toast.error('Please provide a signature');
    }
  };

  return (
    <div className="space-y-6">
      {/* Required Disclosures Section */}
      <Card className="p-6" id="disclosures">
        <h2 className="text-lg font-semibold mb-4">Required Disclosures</h2>
        
        {loadingDisclosures ? (
          <div className="flex items-center justify-center py-8">
            <Clock className="h-5 w-5 animate-spin text-slate-500 mr-2" />
            <span className="text-slate-500">Loading disclosures...</span>
          </div>
        ) : applicableDisclosures.length === 0 ? (
          <p className="text-slate-500 text-center py-4">No disclosures to display.</p>
        ) : (
          <div className="space-y-4">
            {/* TODO: LEGAL REVIEW REQUIRED - Disclosure content requires legal approval */}
            {applicableDisclosures.map((disclosure) => (
              <div
                key={disclosure.disclosure_type}
                className={`border rounded-lg p-4 ${
                  disclosure.requires_work_related
                    ? 'border-amber-300 bg-amber-50 dark:bg-amber-900/10 dark:border-amber-800'
                    : 'border-slate-200 dark:border-slate-700'
                }`}
              >
                <div className="flex items-start gap-3">
                  <input
                    type="checkbox"
                    id={`disclosure-${disclosure.disclosure_type}`}
                    checked={acknowledgments[disclosure.disclosure_type] || false}
                    onChange={(e) => handleDisclosureChange(disclosure.disclosure_type, e.target.checked)}
                    disabled={!!patientSignature}
                    className="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                  />
                  <div className="flex-1">
                    <label
                      htmlFor={`disclosure-${disclosure.disclosure_type}`}
                      className={`text-base font-medium cursor-pointer ${
                        patientSignature ? 'text-slate-400' : 'text-slate-900 dark:text-slate-100'
                      }`}
                    >
                      {disclosure.title}
                      {disclosure.requires_work_related && (
                        <Badge variant="outline" className="ml-2 text-amber-700 border-amber-400 bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400">
                          Work-Related
                        </Badge>
                      )}
                    </label>
                    <div className="mt-2 text-sm text-slate-600 dark:text-slate-400 whitespace-pre-line">
                      {disclosure.content}
                    </div>
                    <div className="mt-1 text-xs text-slate-400">
                      Version: {disclosure.version}
                    </div>
                  </div>
                </div>
              </div>
            ))}
            
            {/* Acknowledgment Warning */}
            {!allAcknowledged && !patientSignature && (
              <div className="flex items-center gap-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0" />
                <p className="text-sm text-amber-800 dark:text-amber-300">
                  All disclosures must be acknowledged before the patient can sign.
                </p>
              </div>
            )}
            
            {/* Already Signed Notice */}
            {patientSignature && (
              <div className="flex items-center gap-2 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                <p className="text-sm text-green-800 dark:text-green-300">
                  Disclosures were acknowledged and recorded when the patient signed.
                </p>
              </div>
            )}
          </div>
        )}
      </Card>

      <Card className="p-6" id="patient-signature">
        <h2 className="text-lg mb-4">Patient Signature</h2>
        <div className="space-y-4">
          {patientSignature ? (
            <div>
              <div className="border-2 border-slate-300 rounded-lg p-4 bg-white">
                <img src={patientSignature} alt="Patient Signature" className="w-full h-40 object-contain" />
              </div>
              <div className="mt-3 flex items-center justify-between">
                <p className="text-sm text-slate-600">
                  Signed on: {patientSignedAt?.toLocaleString()}
                </p>
                <Button variant="outline" size="sm" onClick={clearPatientSignature}>
                  Clear Signature
                </Button>
              </div>
            </div>
          ) : (
            <div>
              <div
                className={`border-2 rounded-lg bg-white ${
                  allAcknowledged
                    ? 'border-slate-300'
                    : 'border-slate-200 opacity-60'
                }`}
                onMouseUp={handlePatientSignatureBlur}
                onMouseLeave={handlePatientSignatureBlur}
              >
                <SignatureCanvas
                  ref={patientSigRef}
                  canvasProps={{
                    className: `w-full h-40 ${allAcknowledged ? 'cursor-crosshair' : 'cursor-not-allowed'}`,
                  }}
                  backgroundColor="rgb(255, 255, 255)"
                />
              </div>
              <div className="mt-3 flex items-center justify-between">
                <p className="text-sm text-slate-600">
                  {allAcknowledged
                    ? 'Sign above'
                    : 'Acknowledge all disclosures above to enable signing'
                  }
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={clearPatientSignature}
                    disabled={!allAcknowledged}
                  >
                    Clear
                  </Button>
                  <Button
                    size="sm"
                    onClick={savePatientSignature}
                    disabled={!allAcknowledged || savingDisclosures}
                  >
                    {savingDisclosures ? (
                      <>
                        <Clock className="h-4 w-4 animate-spin mr-2" />
                        Saving...
                      </>
                    ) : (
                      'Save Signature'
                    )}
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      </Card>

      <Card className="p-6" id="parent-signature">
        <h2 className="text-lg mb-4">Parent/Guardian Signature (If Patient is Minor)</h2>
        <div className="space-y-4">
          {parentSignature ? (
            <div>
              <div className="border-2 border-slate-300 rounded-lg p-4 bg-white">
                <img src={parentSignature} alt="Parent/Guardian Signature" className="w-full h-40 object-contain" />
              </div>
              <div className="mt-3 flex items-center justify-between">
                <p className="text-sm text-slate-600">
                  Signed on: {parentSignedAt?.toLocaleString()}
                </p>
                <Button variant="outline" size="sm" onClick={clearParentSignature}>
                  Clear Signature
                </Button>
              </div>
            </div>
          ) : (
            <div>
              <div
                className="border-2 border-slate-300 rounded-lg bg-white"
                onMouseUp={handleParentSignatureBlur}
                onMouseLeave={handleParentSignatureBlur}
              >
                <SignatureCanvas
                  ref={parentSigRef}
                  canvasProps={{
                    className: 'w-full h-40 cursor-crosshair',
                  }}
                  backgroundColor="rgb(255, 255, 255)"
                />
              </div>
              <div className="mt-3 flex items-center justify-between">
                <p className="text-sm text-slate-600">Sign above (if applicable)</p>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" onClick={clearParentSignature}>
                    Clear
                  </Button>
                  <Button size="sm" onClick={saveParentSignature}>
                    Save Signature
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      </Card>

      <Card className="p-6" id="provider-signature">
        <h2 className="text-lg mb-4">Provider Signature</h2>
        <div className="space-y-4">
          {providerSignatureData ? (
            <div>
              <div className="border-2 border-slate-300 rounded-lg p-4 bg-white">
                <img src={providerSignatureData} alt="Provider Signature" className="w-full h-40 object-contain" />
              </div>
              <div className="mt-3 flex items-center justify-between">
                <p className="text-sm text-slate-600">
                  Signed on: {providerSignedAt?.toLocaleString()}
                </p>
                <Button variant="outline" size="sm" onClick={clearProviderSignature}>
                  Clear Signature
                </Button>
              </div>
            </div>
          ) : (
            <div>
              <div
                className="border-2 border-slate-300 rounded-lg bg-white"
                onMouseUp={handleProviderSignatureBlur}
                onMouseLeave={handleProviderSignatureBlur}
              >
                <SignatureCanvas
                  ref={providerSigRef}
                  canvasProps={{
                    className: 'w-full h-40 cursor-crosshair',
                  }}
                  backgroundColor="rgb(255, 255, 255)"
                />
              </div>
              <div className="mt-3 flex items-center justify-between">
                <p className="text-sm text-slate-600">Sign above</p>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" onClick={clearProviderSignature}>
                    Clear
                  </Button>
                  <Button size="sm" onClick={saveProviderSignature}>
                    Save Signature
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      </Card>
    </div>
  );
}

// Assessment History Modal Component
function AssessmentHistoryModal({
  isOpen,
  onClose,
  patientHistory,
  onSelectAssessment,
}: {
  isOpen: boolean;
  onClose: () => void;
  patientHistory: any[];
  onSelectAssessment: (assessment: any) => void;
}) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/60 dark:bg-black/70 flex items-center justify-center z-50 p-4">
      <Card className="w-full max-w-4xl max-h-[90vh] overflow-y-auto bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600">
        <div className="p-6">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h2 className="text-xl font-semibold">Patient Assessment History</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {patientHistory.length} assessment{patientHistory.length !== 1 ? 's' : ''} found
              </p>
            </div>
            <Button variant="ghost" size="sm" onClick={onClose}>
              <X className="h-5 w-5" />
            </Button>
          </div>

          {patientHistory.length === 0 ? (
            <div className="text-center py-12">
              <p className="text-slate-500 dark:text-slate-400">
                No previous assessments found for this patient.
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {patientHistory.map((assessment, index) => (
                <div
                  key={assessment.id}
                  className="border border-slate-200 dark:border-slate-700 rounded-lg p-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                >
                  <div className="flex items-start justify-between mb-3">
                    <div>
                      <div className="flex items-center gap-2 mb-1">
                        <Clock className="h-4 w-4 text-slate-500 dark:text-slate-400" />
                        <span className="font-medium">{assessment.time}</span>
                        {index === 0 && (
                          <span className="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 text-xs rounded-full">
                            Most Recent
                          </span>
                        )}
                      </div>
                      {assessment.savedAt && (
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                          Saved: {new Date(assessment.savedAt).toLocaleString()}
                        </p>
                      )}
                    </div>
                    <Button
                      size="sm"
                      onClick={() => onSelectAssessment(assessment)}
                      className="bg-blue-600 hover:bg-blue-700 text-white"
                    >
                      Use This Assessment
                    </Button>
                  </div>

                  <div className="grid grid-cols-3 gap-3 mt-3">
                    {Object.entries(assessment.regions).map(([key, value]) => (
                      <div key={key} className="space-y-1">
                        <Label className="text-xs">
                          {key
                            .replace(/([A-Z])/g, ' $1')
                            .replace(/^./, (str) => str.toUpperCase())
                            .replace('Heent', 'HEENT')
                            .replace('Pelvis GUI', 'Pelvis/GU/GI')}
                        </Label>
                        <div
                          className={`text-xs ${
                            value === 'Assessed'
                              ? 'text-green-600 dark:text-green-400 font-medium'
                              : value === 'No Abnormalities'
                              ? 'text-blue-600 dark:text-blue-400'
                              : 'text-slate-600 dark:text-slate-400'
                          }`}
                        >
                          {value as string}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}

          <div className="flex gap-3 mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
            <Button onClick={onClose} variant="outline" className="flex-1">
              Close
            </Button>
          </div>
        </div>
      </Card>
    </div>
  );
}

// Quick Assessment Dialog Component (exported for potential future use - replaced by AssessmentModal)
export function QuickAssessmentDialog({
  isOpen,
  onClose,
  assessment,
  onUpdate,
}: {
  isOpen: boolean;
  onClose: () => void;
  assessment: any;
  onUpdate: (region: string, value: string) => void;
}) {
  const [detailedAssessment, setDetailedAssessment] = useState<string | null>(null);
  const [showBodyModal, setShowBodyModal] = useState(false);
  const [bodyFindings, setBodyFindings] = useState<any[]>([]);

  if (!isOpen) return null;

  const regions = [
    { key: 'mentalStatus', label: 'Mental Status', hasDetail: true },
    { key: 'skin', label: 'Skin', hasDetail: true },
    { key: 'heent', label: 'HEENT', hasDetail: true },
    { key: 'chest', label: 'Chest', hasDetail: true },
    { key: 'abdomen', label: 'Abdomen', hasDetail: true },
    { key: 'back', label: 'Back', hasDetail: true },
    { key: 'pelvisGUI', label: 'Pelvis/GU/GI', hasDetail: true },
    { key: 'extremities', label: 'Extremities', hasDetail: true },
    { key: 'neurological', label: 'Neurological', hasDetail: true },
  ];

  // If detailed assessment is open, show that instead
  if (detailedAssessment) {
    return (
      <div className="fixed inset-0 bg-black/60 dark:bg-black/70 flex items-center justify-center z-50 p-4">
        <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-auto border border-slate-200 dark:border-slate-600">
          <div className="p-6 border-b border-slate-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800 z-10">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold dark:text-white">
                {regions.find(r => r.key === detailedAssessment)?.label} Assessment
              </h2>
              <Button 
                variant="ghost" 
                size="sm" 
                onClick={() => setDetailedAssessment(null)}
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
          </div>
          <div className="p-6">
            {detailedAssessment === 'mentalStatus' && <MentalStatusAssessment />}
            {detailedAssessment === 'neurological' && <NeurologicalAssessment />}
            {detailedAssessment === 'skin' && <SkinAssessment />}
            {detailedAssessment === 'heent' && <HEENTAssessment />}
            {detailedAssessment === 'chest' && <ChestAssessment />}
            {detailedAssessment === 'abdomen' && <AbdomenAssessment />}
            {detailedAssessment === 'back' && <BackAssessment />}
            {detailedAssessment === 'pelvisGUI' && <PelvisGUGIAssessment />}
            {detailedAssessment === 'extremities' && <ExtremitiesAssessment />}
          </div>
          <div className="p-6 border-t border-slate-200 dark:border-slate-700 sticky bottom-0 bg-white dark:bg-slate-800">
            <div className="flex gap-3 justify-end">
              <Button 
                variant="outline" 
                onClick={() => setDetailedAssessment(null)}
              >
                Back to Quick Assessment
              </Button>
              <Button 
                className="bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800"
                onClick={() => {
                  // Mark this region as completed
                  if (detailedAssessment) {
                    onUpdate(detailedAssessment, 'Assessed');
                  }
                  // Go back to Quick Assessment instead of closing completely
                  setDetailedAssessment(null);
                }}
              >
                Save & Return
              </Button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 bg-black/60 dark:bg-black/70 flex items-center justify-center z-50">
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] overflow-auto border border-slate-200 dark:border-slate-600">
        <div className="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <h2 className="text-xl dark:text-white">Start Assessment</h2>
              <button
                onClick={() => setShowBodyModal(true)}
                className="flex items-center gap-2 px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors group"
                title="Click where the patient has been injured and describe it"
              >
                <User className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                <span className="text-sm text-blue-700 dark:text-blue-300">Trauma</span>
              </button>
            </div>
            <Button variant="ghost" size="sm" onClick={onClose}>
              <X className="h-4 w-4" />
            </Button>
          </div>
          <p className="text-sm text-slate-600 dark:text-slate-400 mt-2">
            This view can be used to quickly indicate for a category that there were no abnormalities or that certain categories were not assessed.
          </p>
        </div>

        <div className="p-6">
          <p className="text-sm text-slate-600 mb-4">
            If <strong>No Abnormalities</strong> is selected, all listed abnormalities for the
            category were assessed for and found not to be present.
          </p>

          <div className="grid grid-cols-3 gap-4">
            {regions.map((region) => {
              const currentValue = assessment.regions[region.key];
              return (
                <div key={region.key} className="border border-slate-200 dark:border-slate-700 rounded-lg p-4 space-y-3 bg-white dark:bg-slate-800">
                  <div className="flex items-center justify-between">
                    <h3 className="font-medium dark:text-white">{region.label}</h3>
                    {region.hasDetail && (
                      <Button
                        variant="link"
                        size="sm"
                        className="text-blue-600 dark:text-blue-400 text-xs h-auto p-0"
                        onClick={() => setDetailedAssessment(region.key)}
                      >
                        Assess →
                      </Button>
                    )}
                  </div>

                  <div className="space-y-2">
                    <label className="flex items-center gap-2 text-sm dark:text-slate-300 cursor-pointer">
                      <input
                        type="radio"
                        name={region.key}
                        checked={currentValue === 'No Abnormalities'}
                        onChange={() => onUpdate(region.key, 'No Abnormalities')}
                        className="w-4 h-4 text-green-600 focus:ring-green-500 dark:focus:ring-green-400"
                      />
                      No Abnormalities
                    </label>

                    <Button
                      className={`w-full ${
                        currentValue === 'Not Assessed'
                          ? 'bg-blue-600 hover:bg-blue-700 text-white'
                          : 'bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200'
                      }`}
                      size="sm"
                      onClick={() => onUpdate(region.key, 'Not Assessed')}
                    >
                      Not Assessed
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>

          <div className="mt-6 flex justify-end">
            <Button onClick={onClose} className="bg-green-600 hover:bg-green-700">
              OK
            </Button>
          </div>
        </div>
      </div>

      {/* Body Model Modal */}
      {showBodyModal && (
        <BodyModelModal
          isOpen={showBodyModal}
          onClose={() => setShowBodyModal(false)}
          onSave={(findings) => {
            setBodyFindings(findings);
            setShowBodyModal(false);
          }}
          initialFindings={bodyFindings}
        />
      )}
    </div>
  );
}

