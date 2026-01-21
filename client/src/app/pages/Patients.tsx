import { useState, useMemo, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useShift } from '../contexts/ShiftContext.js';
import { usePatients } from '../hooks/usePatients.js';
import { Input } from '../components/ui/input.js';
import { Button } from '../components/ui/button.js';
import { Badge } from '../components/ui/badge.js';
import { Card } from '../components/ui/card.js';
import { Checkbox } from '../components/ui/checkbox.js';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../components/ui/select.js';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '../components/ui/dropdown-menu.js';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../components/ui/table.js';
import {
  Search,
  Download,
  LayoutGrid,
  LayoutList,
  CheckCircle2,
  XCircle,
  Clock,
  Calendar,
  Building2,
  User,
  FileText,
  AlertTriangle,
  MoreVertical,
  Edit,
  UserPlus,
  FileDown,
  Eye,
  Info,
  Loader2,
  RefreshCw,
  History,
} from 'lucide-react';
import type { PatientFilters } from '../types/api.types.js';

// Extended patient type for display purposes
interface DisplayPatient {
  id: string;
  firstName: string;
  lastName: string;
  employeeId?: string;
  employer?: string;
  lastVisit?: string;
  outcome?: 'cleared' | 'not-cleared' | 'pending';
  chiefComplaint?: string;
  moiNoi?: string;
  disposition?: string;
  provider?: string;
  clinicLocation?: string;
  encounterStatus?: 'completed' | 'in-progress' | 'scheduled';
  reminderSent?: boolean;
}

export default function PatientsPage() {
  const navigate = useNavigate();
  const { shiftData } = useShift();
  const { 
    patients, 
    loading, 
    error, 
    pagination, 
    fetchPatients, 
    clearError 
  } = usePatients();
  
  const [viewMode, setViewMode] = useState<'table' | 'cards'>('table');
  const [searchTerm, setSearchTerm] = useState('');
  const [outcomeFilter, setOutcomeFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [sortBy, setSortBy] = useState<'date' | 'name' | 'outcome'>('date');

  // Fetch patients on mount
  useEffect(() => {
    const filters: PatientFilters = {
      page: 1,
      limit: 50,
    };
    
    if (searchTerm && searchTerm.length >= 2) {
      filters.search = searchTerm;
    }
    
    fetchPatients(filters);
  }, [fetchPatients]);

  // Debounced search
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (searchTerm.length >= 2 || searchTerm.length === 0) {
        fetchPatients({ search: searchTerm || undefined, page: 1, limit: 50 });
      }
    }, 300);
    
    return () => clearTimeout(timeoutId);
  }, [searchTerm, fetchPatients]);

  // Transform API patients to display format
  const displayPatients: DisplayPatient[] = useMemo(() => {
    return patients.map(patient => ({
      id: patient.id,
      firstName: patient.firstName,
      lastName: patient.lastName,
      employeeId: (patient as unknown as { employeeId?: string }).employeeId || `EMP-${patient.id.slice(-4)}`,
      employer: patient.employerName || 'Unknown Employer',
      lastVisit: patient.lastVisit || patient.createdAt,
      outcome: (patient as unknown as { outcome?: string }).outcome as DisplayPatient['outcome'] || 'pending',
      chiefComplaint: (patient as unknown as { chiefComplaint?: string }).chiefComplaint || 'General visit',
      moiNoi: (patient as unknown as { moiNoi?: string }).moiNoi || 'N/A',
      disposition: (patient as unknown as { disposition?: string }).disposition || 'Pending evaluation',
      provider: (patient as unknown as { provider?: string }).provider || 'Unassigned',
      clinicLocation: (patient as unknown as { clinicLocation?: string }).clinicLocation || 'Main Industrial Clinic',
      encounterStatus: (patient as unknown as { encounterStatus?: string }).encounterStatus as DisplayPatient['encounterStatus'] || 'scheduled',
      reminderSent: (patient as unknown as { reminderSent?: boolean }).reminderSent || false,
    }));
  }, [patients]);

  // Filter patients by current clinic location
  const currentClinicName = shiftData?.clinicName || 'Main Industrial Clinic';
  
  // Apply local filters (outcome, status, sort)
  const filteredPatients = useMemo(() => {
    let filtered = [...displayPatients];

    // Outcome filter
    if (outcomeFilter !== 'all') {
      filtered = filtered.filter((p) => p.outcome === outcomeFilter);
    }

    // Status filter
    if (statusFilter !== 'all') {
      filtered = filtered.filter((p) => p.encounterStatus === statusFilter);
    }

    // Sort
    filtered.sort((a, b) => {
      if (sortBy === 'date') {
        const dateA = a.lastVisit ? new Date(a.lastVisit).getTime() : 0;
        const dateB = b.lastVisit ? new Date(b.lastVisit).getTime() : 0;
        return dateB - dateA;
      } else if (sortBy === 'name') {
        return `${a.lastName} ${a.firstName}`.localeCompare(`${b.lastName} ${b.firstName}`);
      } else if (sortBy === 'outcome') {
        return (a.outcome || '').localeCompare(b.outcome || '');
      }
      return 0;
    });

    return filtered;
  }, [displayPatients, outcomeFilter, statusFilter, sortBy]);

  /**
   * Export filtered patients to CSV file
   * Implements LOW-002: Export button functionality
   */
  const handleExportCSV = () => {
    if (filteredPatients.length === 0) {
      alert('No patients to export');
      return;
    }

    // Define CSV headers
    const headers = [
      'Patient ID',
      'First Name',
      'Last Name',
      'Employee ID',
      'Employer',
      'Last Visit',
      'Chief Complaint',
      'MOI/NOI',
      'Outcome',
      'Disposition',
      'Provider',
      'Clinic Location',
      'Encounter Status',
      'Reminder Sent'
    ];

    // Convert patients to CSV rows
    const rows = filteredPatients.map(patient => [
      patient.id,
      patient.firstName,
      patient.lastName,
      patient.employeeId || '',
      patient.employer || '',
      patient.lastVisit || '',
      patient.chiefComplaint || '',
      patient.moiNoi || '',
      patient.outcome || '',
      patient.disposition || '',
      patient.provider || '',
      patient.clinicLocation || '',
      patient.encounterStatus || '',
      patient.reminderSent ? 'Yes' : 'No'
    ]);

    // Escape CSV values (handle commas, quotes, newlines)
    const escapeCSV = (value: string): string => {
      if (value === null || value === undefined) return '';
      const stringValue = String(value);
      if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
        return `"${stringValue.replace(/"/g, '""')}"`;
      }
      return stringValue;
    };

    // Build CSV content
    const csvContent = [
      headers.map(escapeCSV).join(','),
      ...rows.map(row => row.map(escapeCSV).join(','))
    ].join('\n');

    // Create blob and trigger download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const timestamp = new Date().toISOString().split('T')[0];
    link.href = url;
    link.download = `patients_export_${timestamp}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  // Get outcome statistics
  const stats = useMemo(() => {
    const cleared = filteredPatients.filter((p) => p.outcome === 'cleared').length;
    const notCleared = filteredPatients.filter((p) => p.outcome === 'not-cleared').length;
    const pending = filteredPatients.filter((p) => p.outcome === 'pending').length;
    return { cleared, notCleared, pending, total: filteredPatients.length };
  }, [filteredPatients]);

  const formatDate = (dateString?: string) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getOutcomeBadge = (outcome?: string) => {
    switch (outcome) {
      case 'cleared':
        return (
          <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
            <CheckCircle2 className="h-3 w-3 mr-1" />
            Cleared
          </Badge>
        );
      case 'not-cleared':
        return (
          <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
            <XCircle className="h-3 w-3 mr-1" />
            Not Cleared
          </Badge>
        );
      case 'pending':
        return (
          <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
            <Clock className="h-3 w-3 mr-1" />
            Pending
          </Badge>
        );
      default:
        return null;
    }
  };

  const getStatusBadge = (status?: string) => {
    switch (status) {
      case 'completed':
        return (
          <Badge variant="outline" className="border-slate-300 dark:border-slate-600">
            Completed
          </Badge>
        );
      case 'in-progress':
        return (
          <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
            In Progress
          </Badge>
        );
      case 'scheduled':
        return (
          <Badge className="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
            Scheduled
          </Badge>
        );
      default:
        return null;
    }
  };

  const handlePageChange = (newPage: number) => {
    fetchPatients({ page: newPage, limit: pagination.limit });
  };

  // Loading state
  if (loading && patients.length === 0) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600 mx-auto mb-4" />
          <p className="text-slate-600 dark:text-slate-400">Loading patients...</p>
        </div>
      </div>
    );
  }

  // Error state
  if (error && patients.length === 0) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <Card className="p-8 text-center max-w-md">
          <AlertTriangle className="h-12 w-12 text-red-500 mx-auto mb-4" />
          <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-2">
            Error Loading Patients
          </h3>
          <p className="text-slate-600 dark:text-slate-400 mb-4">{error}</p>
          <Button onClick={() => { clearError(); fetchPatients(); }}>
            <RefreshCw className="h-4 w-4 mr-2" />
            Try Again
          </Button>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 relative">
        <div className="flex-shrink-0">
          <h1 className="text-3xl font-semibold text-slate-900 dark:text-white">
            Patients <span className="text-slate-600 dark:text-slate-400 text-xl">at {currentClinicName}</span>
          </h1>
          <p className="text-slate-600 dark:text-slate-400 mt-1">
            View and manage patient encounters
            {pagination.total > 0 && (
              <span className="ml-2">
                ({pagination.total} total)
              </span>
            )}
          </p>
        </div>
        
        {/* Statistics Cards - Scrollable */}
        <div className="flex-1 relative overflow-hidden">
          <div className="overflow-x-scroll relative">
            <div className="flex gap-4 pb-2">
              <Card className="p-5 w-[200px] flex-shrink-0">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-slate-600 dark:text-slate-400">Total</p>
                    <p className="text-2xl font-semibold mt-1 dark:text-white">{stats.total}</p>
                  </div>
                  <div className="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                    <User className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                  </div>
                </div>
              </Card>

              <Card className="p-5 w-[200px] flex-shrink-0">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-slate-600 dark:text-slate-400">Cleared</p>
                    <p className="text-2xl font-semibold mt-1 text-green-600 dark:text-green-400">
                      {stats.cleared}
                    </p>
                  </div>
                  <div className="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
                    <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400" />
                  </div>
                </div>
              </Card>

              <Card className="p-5 w-[200px] flex-shrink-0">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-slate-600 dark:text-slate-400">Not Cleared</p>
                    <p className="text-2xl font-semibold mt-1 text-red-600 dark:text-red-400">
                      {stats.notCleared}
                    </p>
                  </div>
                  <div className="h-10 w-10 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center">
                    <XCircle className="h-5 w-5 text-red-600 dark:text-red-400" />
                  </div>
                </div>
              </Card>

              <Card className="p-5 w-[200px] flex-shrink-0">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm text-slate-600 dark:text-slate-400">Pending</p>
                    <p className="text-2xl font-semibold mt-1 text-yellow-600 dark:text-yellow-400">
                      {stats.pending}
                    </p>
                  </div>
                  <div className="h-10 w-10 rounded-full bg-yellow-100 dark:bg-yellow-900 flex items-center justify-center">
                    <Clock className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                  </div>
                </div>
              </Card>
            </div>
          </div>
          {/* Fade out on right - wider to show partial 4th tile */}
          <div className="absolute right-0 top-0 bottom-0 w-32 bg-gradient-to-l from-slate-50 dark:from-slate-900 via-slate-50/50 dark:via-slate-900/50 to-transparent pointer-events-none" />
        </div>

        <Button onClick={() => navigate('/encounters/workspace')} className="relative z-20 flex-shrink-0">
          <FileText className="h-4 w-4 mr-2" />
          New Encounter
        </Button>
      </div>

      {/* Filters and Search */}
      <Card className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          {/* Search */}
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-slate-400 dark:text-slate-500" />
              <Input
                placeholder="Search by name, employee ID, or employer..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-9"
              />
              {loading && searchTerm && (
                <Loader2 className="absolute right-3 top-1/2 transform -translate-y-1/2 h-4 w-4 animate-spin text-slate-400" />
              )}
            </div>
          </div>

          {/* Outcome Filter */}
          <Select value={outcomeFilter} onValueChange={setOutcomeFilter}>
            <SelectTrigger className="w-full lg:w-48">
              <SelectValue placeholder="Outcome" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Outcomes</SelectItem>
              <SelectItem value="cleared">Cleared</SelectItem>
              <SelectItem value="not-cleared">Not Cleared</SelectItem>
              <SelectItem value="pending">Pending</SelectItem>
            </SelectContent>
          </Select>

          {/* Status Filter */}
          <Select value={statusFilter} onValueChange={setStatusFilter}>
            <SelectTrigger className="w-full lg:w-48">
              <SelectValue placeholder="Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Status</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="in-progress">In Progress</SelectItem>
              <SelectItem value="scheduled">Scheduled</SelectItem>
            </SelectContent>
          </Select>

          {/* Sort By */}
          <Select value={sortBy} onValueChange={(value: 'date' | 'name' | 'outcome') => setSortBy(value)}>
            <SelectTrigger className="w-full lg:w-48">
              <SelectValue placeholder="Sort by" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="date">Date (Recent)</SelectItem>
              <SelectItem value="name">Name (A-Z)</SelectItem>
              <SelectItem value="outcome">Outcome</SelectItem>
            </SelectContent>
          </Select>

          {/* View Toggle */}
          <div className="flex gap-2">
            <Button
              variant={viewMode === 'table' ? 'default' : 'outline'}
              size="sm"
              onClick={() => setViewMode('table')}
            >
              <LayoutList className="h-4 w-4" />
            </Button>
            <Button
              variant={viewMode === 'cards' ? 'default' : 'outline'}
              size="sm"
              onClick={() => setViewMode('cards')}
            >
              <LayoutGrid className="h-4 w-4" />
            </Button>
          </div>

          {/* Export */}
          <Button variant="outline" size="sm" onClick={handleExportCSV}>
            <Download className="h-4 w-4 mr-2" />
            Export
          </Button>

          {/* Refresh */}
          <Button 
            variant="outline" 
            size="sm" 
            onClick={() => fetchPatients()}
            disabled={loading}
          >
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          </Button>
        </div>
      </Card>

      {/* Results */}
      {viewMode === 'table' ? (
        <Card className="overflow-visible">
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Patient ID</TableHead>
                  <TableHead>Name</TableHead>
                  <TableHead>Last Visit</TableHead>
                  <TableHead>Chief Complaint</TableHead>
                  <TableHead>MOI/NOI</TableHead>
                  <TableHead>Outcome</TableHead>
                  <TableHead>
                    <div className="flex items-center gap-1">
                      Reminder
                      <div className="group relative inline-block">
                        <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        <span className="absolute top-full -right-1 -translate-x-full mt-2 px-3 py-2 text-xs text-white bg-slate-900 dark:bg-slate-700 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 w-max max-w-xs whitespace-normal">
                          If box is checked, a text appointment reminder has been sent to the patient
                        </span>
                      </div>
                    </div>
                  </TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredPatients.length > 0 ? (
                  filteredPatients.map((patient) => (
                    <TableRow
                      key={patient.id}
                      className="hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                      <TableCell className="font-mono text-sm">{patient.id}</TableCell>
                      <TableCell className="font-medium">
                        {patient.firstName} {patient.lastName}
                      </TableCell>
                      <TableCell className="text-sm text-slate-600 dark:text-slate-400">
                        {formatDate(patient.lastVisit)}
                      </TableCell>
                      <TableCell>{patient.chiefComplaint}</TableCell>
                      <TableCell>{patient.moiNoi}</TableCell>
                      <TableCell>{getOutcomeBadge(patient.outcome)}</TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Checkbox checked={patient.reminderSent} disabled />
                          {patient.reminderSent && (
                            <button
                              className="group relative"
                              onClick={(e) => {
                                e.stopPropagation();
                                // TODO: Implement view message modal
                              }}
                            >
                              <Eye className="h-4 w-4 text-blue-600 hover:text-blue-700" />
                              <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 text-xs text-white bg-slate-900 dark:bg-slate-700 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-[100]">
                                View message sent
                              </span>
                            </button>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        <DropdownMenu modal={false}>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="z-[100]">
                            <DropdownMenuItem
                              onClick={(e) => {
                                e.stopPropagation();
                                navigate(`/patients/${patient.id}/timeline`);
                              }}
                            >
                              <History className="h-4 w-4 mr-2" />
                              View Timeline
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={(e) => {
                                e.stopPropagation();
                                navigate(`/patients/${patient.id}`);
                              }}
                            >
                              <Edit className="h-4 w-4 mr-2" />
                              Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={(e) => {
                                e.stopPropagation();
                                navigate('/encounters/workspace', {
                                  state: {
                                    patientData: {
                                      firstName: patient.firstName,
                                      lastName: patient.lastName,
                                      employeeId: patient.employeeId,
                                    },
                                    followUp: true,
                                  },
                                });
                              }}
                            >
                              <UserPlus className="h-4 w-4 mr-2" />
                              Follow up
                            </DropdownMenuItem>
                            <DropdownMenuItem
                              onClick={(e) => {
                                e.stopPropagation();
                                // TODO: Implement patient export functionality
                              }}
                            >
                              <FileDown className="h-4 w-4 mr-2" />
                              Export
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center py-8 text-slate-500 dark:text-slate-400">
                      No patients found matching your criteria
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>
          
          {/* Pagination */}
          {pagination.totalPages > 1 && (
            <div className="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700">
              <div className="text-sm text-slate-600 dark:text-slate-400">
                Showing page {pagination.page} of {pagination.totalPages}
              </div>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handlePageChange(pagination.page - 1)}
                  disabled={pagination.page <= 1}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handlePageChange(pagination.page + 1)}
                  disabled={pagination.page >= pagination.totalPages}
                >
                  Next
                </Button>
              </div>
            </div>
          )}
        </Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredPatients.length > 0 ? (
            filteredPatients.map((patient) => (
              <Card
                key={patient.id}
                className="p-6 hover:shadow-lg transition-shadow dark:hover:bg-slate-800"
              >
                <div className="space-y-4">
                  {/* Header */}
                  <div className="flex items-start justify-between">
                    <div>
                      <h3 className="font-semibold text-lg dark:text-white">
                        {patient.firstName} {patient.lastName}
                      </h3>
                      <p className="text-sm text-slate-600 dark:text-slate-400 font-mono">
                        {patient.id}
                      </p>
                    </div>
                    {getOutcomeBadge(patient.outcome)}
                  </div>

                  {/* Details */}
                  <div className="space-y-2 text-sm">
                    <div className="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                      <User className="h-4 w-4" />
                      <span>{patient.employeeId}</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                      <Building2 className="h-4 w-4" />
                      <span>{patient.employer}</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                      <Calendar className="h-4 w-4" />
                      <span>{formatDate(patient.lastVisit)}</span>
                    </div>
                  </div>

                  {/* Chief Complaint */}
                  <div className="pt-3 border-t border-slate-200 dark:border-slate-700">
                    <p className="text-sm font-medium text-slate-700 dark:text-slate-300">
                      {patient.chiefComplaint}
                    </p>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                      {patient.disposition}
                    </p>
                  </div>

                  {/* Footer */}
                  <div className="flex items-center justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                    <span className="text-xs text-slate-500 dark:text-slate-400">
                      {patient.provider}
                    </span>
                    {getStatusBadge(patient.encounterStatus)}
                  </div>

                  {/* Actions */}
                  <div className="flex gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <Button
                      variant="outline"
                      size="sm"
                      className="flex-1"
                      onClick={() => navigate(`/patients/${patient.id}/timeline`)}
                    >
                      <History className="h-4 w-4 mr-2" />
                      Timeline
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      className="flex-1"
                      onClick={() => navigate(`/patients/${patient.id}`)}
                    >
                      <Edit className="h-4 w-4 mr-2" />
                      Edit
                    </Button>
                  </div>
                </div>
              </Card>
            ))
          ) : (
            <div className="col-span-full text-center py-12">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
                <AlertTriangle className="h-8 w-8 text-slate-400 dark:text-slate-500" />
              </div>
              <p className="text-slate-500 dark:text-slate-400">
                No patients found matching your criteria
              </p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
