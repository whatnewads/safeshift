import { useState, useEffect, useCallback, type ChangeEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card } from '../../components/ui/card.js';
import { Button } from '../../components/ui/button.js';
import { Input } from '../../components/ui/input.js';
import { Search, User, UserPlus, FileText, Briefcase, HelpCircle, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { patientService } from '../../services/patient.service.js';
import type { Patient } from '../../types/index.js';

// Type for patient display data
interface PatientSearchResult {
  id: string;
  name: string;
  dob: string;
  employeeId: string;
  mrn: string;
}

// Transform API patient to display format
function transformPatientForDisplay(patient: Patient): PatientSearchResult {
  const dob = patient.dateOfBirth
    ? new Date(patient.dateOfBirth).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' })
    : 'N/A';
  return {
    id: patient.id,
    name: `${patient.firstName} ${patient.lastName}`,
    dob,
    employeeId: patient.employerId || 'N/A',
    mrn: patient.id, // Use patient ID as MRN reference
  };
}

export default function StartEncounterPage() {
  const [patientSearch, setPatientSearch] = useState('');
  const [encounterType, setEncounterType] = useState<string | null>(null);
  const [searchResults, setSearchResults] = useState<PatientSearchResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const navigate = useNavigate();

  const handleStartEncounter = (patientId?: string) => {
    // Generate a new encounter ID
    const encounterId = `ENC-${Date.now()}`;
    toast.success('Starting encounter...');
    navigate(`/encounters/${encounterId}`);
  };

  const encounterTypes = [
    {
      id: 'work-related',
      icon: Briefcase,
      title: 'Work-Related',
      description: 'Injury or illness related to work activities',
      color: 'text-orange-600',
      bgColor: 'bg-orange-50',
      borderColor: 'border-orange-200',
    },
    {
      id: 'personal-medical',
      icon: User,
      title: 'Personal Medical',
      description: 'Non-work-related health concern',
      color: 'text-blue-600',
      bgColor: 'bg-blue-50',
      borderColor: 'border-blue-200',
    },
    {
      id: 'unknown',
      icon: HelpCircle,
      title: "Don't Know Yet",
      description: 'Will determine during assessment',
      color: 'text-slate-600',
      bgColor: 'bg-slate-50',
      borderColor: 'border-slate-200',
    },
  ];

  // Debounced patient search using API
  const searchPatientsApi = useCallback(async (query: string) => {
    if (!query || query.length < 2) {
      setSearchResults([]);
      return;
    }
    
    setIsSearching(true);
    setSearchError(null);
    
    try {
      const patients = await patientService.searchPatients(query);
      const displayResults = patients.map(transformPatientForDisplay);
      setSearchResults(displayResults);
    } catch (error) {
      console.error('Patient search failed:', error);
      setSearchError('Failed to search patients. Please try again.');
      setSearchResults([]);
    } finally {
      setIsSearching(false);
    }
  }, []);

  // Debounce search input
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      searchPatientsApi(patientSearch);
    }, 300); // 300ms debounce
    
    return () => clearTimeout(timeoutId);
  }, [patientSearch, searchPatientsApi]);

  const filteredPatients = searchResults;

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold mb-2">Start New Encounter</h1>
        <p className="text-slate-600">Select patient and begin documentation</p>
      </div>

      <div className="grid md:grid-cols-3 gap-4">
        <Card className="md:col-span-2 p-6">
          <div className="mb-6">
            <h2 className="text-xl font-semibold mb-4">Find Patient</h2>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-slate-400" />
              <Input
                placeholder="Search by name, DOB, Employee ID, or MRN..."
                value={patientSearch}
                onChange={(e: ChangeEvent<HTMLInputElement>) => setPatientSearch(e.target.value)}
                className="pl-10 h-12"
                autoFocus
              />
            </div>
          </div>

          {/* Search Results */}
          {patientSearch && filteredPatients.length > 0 && (
            <div className="space-y-2 mb-6">
              <h3 className="font-medium text-sm text-slate-600 mb-2">Search Results</h3>
              <div className="border border-slate-200 rounded-lg divide-y divide-slate-200 max-h-80 overflow-y-auto">
                {filteredPatients.map((patient) => (
                  <div
                    key={patient.id}
                    className="flex items-center justify-between p-4 hover:bg-slate-50 cursor-pointer transition-colors"
                    onClick={() => handleStartEncounter(patient.id)}
                  >
                    <div className="flex items-center gap-3">
                      <div className="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <User className="h-5 w-5 text-blue-600" />
                      </div>
                      <div>
                        <p className="font-medium">{patient.name}</p>
                        <p className="text-sm text-slate-600">
                          DOB: {patient.dob} | EID: {patient.employeeId} | MRN: {patient.mrn}
                        </p>
                      </div>
                    </div>
                    <Button size="sm">
                      <FileText className="h-4 w-4 mr-2" />
                      Start
                    </Button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* No Results */}
          {patientSearch && filteredPatients.length === 0 && (
            <div className="text-center py-8">
              <User className="h-12 w-12 text-slate-300 mx-auto mb-3" />
              <p className="text-slate-600 mb-4">No patients found matching "{patientSearch}"</p>
              <Button variant="outline">
                <UserPlus className="h-4 w-4 mr-2" />
                Create New Patient
              </Button>
            </div>
          )}

          {/* Empty State */}
          {!patientSearch && (
            <div className="text-center py-12 border-2 border-dashed border-slate-200 rounded-lg">
              <Search className="h-12 w-12 text-slate-300 mx-auto mb-3" />
              <p className="text-slate-600">Start typing to search for a patient</p>
              <p className="text-sm text-slate-500 mt-1">
                Search by name, date of birth, employee ID, or MRN
              </p>
            </div>
          )}
        </Card>

        {/* Quick Actions */}
        <div className="space-y-4">
          <Card className="p-6">
            <h3 className="font-semibold mb-4 flex items-center gap-2">
              <UserPlus className="h-5 w-5 text-blue-600" />
              New Patient
            </h3>
            <p className="text-sm text-slate-600 mb-4">
              Can't find the patient? Create a new record or check the registration queue.
            </p>
            <div className="space-y-2">
              <Button variant="outline" className="w-full justify-start">
                <UserPlus className="h-4 w-4 mr-2" />
                Create Patient
              </Button>
              <Button variant="outline" className="w-full justify-start">
                Registration Queue
              </Button>
            </div>
          </Card>

          <Card className="p-6 bg-blue-50 border-blue-200">
            <h3 className="font-semibold mb-2 flex items-center gap-2">
              <FileText className="h-5 w-5 text-blue-600" />
              Quick Start
            </h3>
            <p className="text-sm text-slate-700 mb-4">
              For emergency situations, you can start a blank encounter and add patient details later.
            </p>
            <Button
              variant="outline"
              className="w-full border-blue-300 hover:bg-blue-100"
              onClick={() => handleStartEncounter()}
            >
              Start Blank Encounter
            </Button>
          </Card>
        </div>
      </div>

      {/* Encounter Type Selection (Optional - can be set later in workspace) */}
      <Card className="p-6">
        <h2 className="text-xl font-semibold mb-4">
          Encounter Type <span className="text-sm font-normal text-slate-500">(Optional - can be set later)</span>
        </h2>
        <div className="grid md:grid-cols-3 gap-4">
          {encounterTypes.map((type) => {
            const Icon = type.icon;
            const isSelected = encounterType === type.id;
            return (
              <button
                key={type.id}
                onClick={() => setEncounterType(type.id)}
                className={`
                  p-4 border-2 rounded-lg text-left transition-all
                  ${isSelected 
                    ? `${type.borderColor} ${type.bgColor} border-2` 
                    : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800'
                  }
                `}
              >
                <div className="flex items-start gap-3">
                  <Icon className={`h-6 w-6 ${isSelected ? type.color : 'text-slate-400'}`} />
                  <div>
                    <h3 className="font-medium mb-1">{type.title}</h3>
                    <p className="text-sm text-slate-600">{type.description}</p>
                  </div>
                </div>
              </button>
            );
          })}
        </div>
      </Card>

      {/* Instructions */}
      <Card className="p-4 bg-slate-50 border-slate-200">
        <div className="flex items-start gap-3">
          <HelpCircle className="h-5 w-5 text-slate-500 mt-0.5" />
          <div className="text-sm text-slate-700">
            <p className="font-medium mb-1">Quick Tips</p>
            <ul className="list-disc list-inside space-y-1 text-slate-600">
              <li>Search for existing patients by name, employee ID, or MRN</li>
              <li>Click on a patient to immediately start the encounter workspace</li>
              <li>You can set encounter type now or later during documentation</li>
              <li>All fields in the workspace support non-linear entry - jump to any section</li>
            </ul>
          </div>
        </div>
      </Card>
    </div>
  );
}
