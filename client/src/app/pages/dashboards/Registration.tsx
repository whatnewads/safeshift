import { useState } from 'react';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { UserPlus, Users, Clock, CheckCircle, Search, Edit, AlertCircle, RefreshCw, Loader2 } from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../../components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '../../components/ui/dialog';
import { Textarea } from '../../components/ui/textarea';
import { useRegistration, usePatientSearch } from '../../hooks/useRegistration.js';
import type { PendingRegistration, PatientSearchResult } from '../../hooks/useRegistration.js';

export default function RegistrationDashboard() {
  const [isNewPatientModalOpen, setIsNewPatientModalOpen] = useState(false);
  const [isEditPatientModalOpen, setIsEditPatientModalOpen] = useState(false);
  const [selectedPatient, setSelectedPatient] = useState<PendingRegistration | null>(null);
  
  // Use the registration hook for dashboard data
  const { 
    queueStats, 
    pendingRegistrations, 
    loading, 
    error, 
    refetch,
    clearError 
  } = useRegistration();
  
  // Use patient search hook
  const {
    results: searchResults,
    loading: searchLoading,
    error: searchError,
    search: performSearch,
    clearResults: clearSearchResults,
    query: searchQuery,
  } = usePatientSearch();

  const handleEditPatient = (patient: PendingRegistration) => {
    setSelectedPatient(patient);
    setIsEditPatientModalOpen(true);
  };

  const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    if (value.length >= 2) {
      performSearch(value);
    } else {
      clearSearchResults();
    }
  };

  // Priority badge variant based on priority level
  const getPriorityBadgeVariant = (priority: string) => {
    switch (priority) {
      case 'urgent':
        return 'destructive';
      case 'high':
        return 'secondary';
      default:
        return 'outline';
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold mb-2">Registration Dashboard</h1>
        <p className="text-slate-600 dark:text-slate-400">Front-desk intake and demographic accuracy</p>
        
        {/* Permissions Notice */}
        <div className="mt-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg flex items-start gap-2">
          <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-medium text-amber-900 dark:text-amber-300">Registration Role Permissions</p>
            <p className="text-xs text-amber-700 dark:text-amber-400 mt-1">
              You can create/edit patient demographics and employment info. You cannot edit clinical assessments, vitals, or submit encounters.
            </p>
          </div>
        </div>
      </div>

      {/* Error Alert */}
      {error && (
        <div className="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-500" />
            <span className="text-red-700 dark:text-red-400">{error}</span>
          </div>
          <div className="flex gap-2">
            <Button variant="ghost" size="sm" onClick={clearError}>
              Dismiss
            </Button>
            <Button variant="outline" size="sm" onClick={refetch}>
              <RefreshCw className="h-4 w-4 mr-1" />
              Retry
            </Button>
          </div>
        </div>
      )}

      {/* Quick Actions */}
      <Card className="p-6 bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20">
        <Dialog open={isNewPatientModalOpen} onOpenChange={setIsNewPatientModalOpen}>
          <DialogTrigger asChild>
            <Button size="lg" className="gap-2">
              <UserPlus className="h-5 w-5" />
              New Patient Registration
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>New Patient Registration</DialogTitle>
              <DialogDescription>
                Enter patient demographics and employment information
              </DialogDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              {/* Demographics */}
              <div className="space-y-4">
                <h3 className="font-semibold">Demographics</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="firstName">First Name *</Label>
                    <Input id="firstName" placeholder="John" />
                  </div>
                  <div>
                    <Label htmlFor="lastName">Last Name *</Label>
                    <Input id="lastName" placeholder="Smith" />
                  </div>
                  <div>
                    <Label htmlFor="dob">Date of Birth *</Label>
                    <Input id="dob" type="date" />
                  </div>
                  <div>
                    <Label htmlFor="ssn">SSN (Last 4)</Label>
                    <Input id="ssn" placeholder="1234" maxLength={4} />
                  </div>
                  <div>
                    <Label htmlFor="phone">Phone</Label>
                    <Input id="phone" type="tel" placeholder="(555) 123-4567" />
                  </div>
                  <div>
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" placeholder="john.smith@example.com" />
                  </div>
                </div>
              </div>

              {/* Employment Info */}
              <div className="space-y-4 border-t pt-4">
                <h3 className="font-semibold">Employment Information</h3>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="employer">Employer *</Label>
                    <Input id="employer" placeholder="ABC Construction" />
                  </div>
                  <div>
                    <Label htmlFor="employeeId">Employee ID</Label>
                    <Input id="employeeId" placeholder="EMP-1001" />
                  </div>
                  <div>
                    <Label htmlFor="jobTitle">Job Title</Label>
                    <Input id="jobTitle" placeholder="Site Supervisor" />
                  </div>
                  <div>
                    <Label htmlFor="department">Department</Label>
                    <Input id="department" placeholder="Construction" />
                  </div>
                  <div>
                    <Label htmlFor="supervisor">Supervisor Name</Label>
                    <Input id="supervisor" placeholder="Jane Doe" />
                  </div>
                  <div>
                    <Label htmlFor="supervisorPhone">Supervisor Phone</Label>
                    <Input id="supervisorPhone" type="tel" placeholder="(555) 987-6543" />
                  </div>
                </div>
              </div>

              {/* Past Medical History */}
              <div className="space-y-4 border-t pt-4">
                <h3 className="font-semibold">Past Medical History</h3>
                <div className="space-y-4">
                  <div>
                    <Label htmlFor="allergies">Allergies</Label>
                    <Textarea 
                      id="allergies" 
                      placeholder="List any known allergies (medications, environmental, food)..."
                      rows={2}
                    />
                  </div>
                  <div>
                    <Label htmlFor="medications">Current Medications</Label>
                    <Textarea 
                      id="medications" 
                      placeholder="List current medications with dosage..."
                      rows={2}
                    />
                  </div>
                  <div>
                    <Label htmlFor="medicalHistory">Medical History</Label>
                    <Textarea 
                      id="medicalHistory" 
                      placeholder="Previous surgeries, chronic conditions, hospitalizations..."
                      rows={3}
                    />
                  </div>
                </div>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsNewPatientModalOpen(false)}>
                Cancel
              </Button>
              <Button onClick={() => setIsNewPatientModalOpen(false)}>
                Save Patient
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </Card>

      {/* Patient Search */}
      <Card className="p-6">
        <h2 className="text-xl font-semibold mb-4">Search Patients</h2>
        <div className="flex gap-2">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input 
              placeholder="Search by name, phone, email..." 
              className="pl-9"
              onChange={handleSearch}
            />
          </div>
          <Button disabled={searchLoading}>
            {searchLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Search'}
          </Button>
        </div>
        
        {/* Search Results */}
        {searchQuery && searchResults.length > 0 && (
          <div className="mt-4 border rounded-lg">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>DOB</TableHead>
                  <TableHead>Phone</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {searchResults.map((patient: PatientSearchResult) => (
                  <TableRow key={patient.id}>
                    <TableCell className="font-medium">
                      {patient.firstName} {patient.lastName}
                      {patient.preferredName && (
                        <span className="text-slate-500 text-sm ml-1">({patient.preferredName})</span>
                      )}
                    </TableCell>
                    <TableCell>{patient.dateOfBirth}</TableCell>
                    <TableCell>{patient.phone || '-'}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="outline" size="sm">
                        Select
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
        
        {/* No Results Message */}
        {searchQuery && searchQuery.length >= 2 && !searchLoading && searchResults.length === 0 && !searchError && (
          <div className="mt-4 text-center text-slate-500 py-4">
            No patients found matching "{searchQuery}"
          </div>
        )}
        
        {/* Search Error */}
        {searchError && (
          <div className="mt-4 text-center text-red-500 py-4">
            {searchError}
          </div>
        )}
      </Card>

      {/* Stats */}
      <div className="grid md:grid-cols-3 gap-6">
        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
              <Clock className="h-6 w-6 text-orange-600 dark:text-orange-400" />
            </div>
            <div>
              {loading ? (
                <div className="h-8 w-12 bg-slate-200 dark:bg-slate-700 animate-pulse rounded" />
              ) : (
                <p className="text-2xl font-bold">{queueStats?.waiting ?? 0}</p>
              )}
              <p className="text-sm text-slate-600 dark:text-slate-400">Waiting</p>
            </div>
          </div>
        </Card>

        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <Users className="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              {loading ? (
                <div className="h-8 w-12 bg-slate-200 dark:bg-slate-700 animate-pulse rounded" />
              ) : (
                <p className="text-2xl font-bold">{queueStats?.inProgress ?? 0}</p>
              )}
              <p className="text-sm text-slate-600 dark:text-slate-400">In Progress</p>
            </div>
          </div>
        </Card>

        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
              <CheckCircle className="h-6 w-6 text-green-600 dark:text-green-400" />
            </div>
            <div>
              {loading ? (
                <div className="h-8 w-12 bg-slate-200 dark:bg-slate-700 animate-pulse rounded" />
              ) : (
                <p className="text-2xl font-bold">{queueStats?.completedToday ?? 0}</p>
              )}
              <p className="text-sm text-slate-600 dark:text-slate-400">Completed Today</p>
            </div>
          </div>
        </Card>
      </div>

      {/* Pending Registrations */}
      <Card className="p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold">Pending Registrations</h2>
          <Button variant="ghost" size="sm" onClick={refetch} disabled={loading}>
            <RefreshCw className={`h-4 w-4 mr-1 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
        </div>
        
        {/* Loading State */}
        {loading && (
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="flex items-center gap-4 p-4 border rounded-lg animate-pulse">
                <div className="h-10 w-32 bg-slate-200 dark:bg-slate-700 rounded" />
                <div className="h-10 w-24 bg-slate-200 dark:bg-slate-700 rounded" />
                <div className="h-10 w-20 bg-slate-200 dark:bg-slate-700 rounded" />
                <div className="flex-1" />
                <div className="h-8 w-20 bg-slate-200 dark:bg-slate-700 rounded" />
              </div>
            ))}
          </div>
        )}
        
        {/* Empty State */}
        {!loading && pendingRegistrations.length === 0 && !error && (
          <div className="text-center py-8 text-slate-500">
            <Users className="h-12 w-12 mx-auto mb-3 opacity-50" />
            <p>No pending registrations</p>
            <p className="text-sm mt-1">New patients will appear here when they check in</p>
          </div>
        )}
        
        {/* Data Table */}
        {!loading && pendingRegistrations.length > 0 && (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Patient Name</TableHead>
                <TableHead>DOB</TableHead>
                <TableHead>Wait Time</TableHead>
                <TableHead>Priority</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {pendingRegistrations.map((reg) => (
                <TableRow key={reg.id}>
                  <TableCell className="font-medium">
                    {reg.firstName} {reg.lastName}
                  </TableCell>
                  <TableCell>{reg.dateOfBirth}</TableCell>
                  <TableCell>{reg.waitTime}</TableCell>
                  <TableCell>
                    <Badge variant={getPriorityBadgeVariant(reg.priority)}>
                      {reg.priority === 'urgent' ? 'Urgent' : reg.priority === 'high' ? 'High' : 'Normal'}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-right">
                    <Button 
                      variant="default" 
                      size="sm"
                      onClick={() => handleEditPatient(reg)}
                    >
                      <Edit className="h-4 w-4 mr-1" />
                      Process
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>

      {/* Edit Patient Modal */}
      <Dialog open={isEditPatientModalOpen} onOpenChange={setIsEditPatientModalOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Edit Patient Information</DialogTitle>
            <DialogDescription>
              Update patient demographics and employment information
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            {/* Same form structure as New Patient modal */}
            <div className="space-y-4">
              <h3 className="font-semibold">Demographics</h3>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="edit-firstName">First Name *</Label>
                  <Input id="edit-firstName" defaultValue={selectedPatient?.firstName || ''} />
                </div>
                <div>
                  <Label htmlFor="edit-lastName">Last Name *</Label>
                  <Input id="edit-lastName" defaultValue={selectedPatient?.lastName || ''} />
                </div>
                <div>
                  <Label htmlFor="edit-dob">Date of Birth *</Label>
                  <Input id="edit-dob" type="date" defaultValue={selectedPatient?.dateOfBirth || ''} />
                </div>
                <div>
                  <Label htmlFor="edit-phone">Phone</Label>
                  <Input id="edit-phone" type="tel" defaultValue={selectedPatient?.phone || ''} />
                </div>
              </div>
            </div>
            
            {/* Chief Complaint if available */}
            {selectedPatient?.chiefComplaint && (
              <div className="space-y-2 border-t pt-4">
                <h3 className="font-semibold">Chief Complaint</h3>
                <p className="text-slate-600 dark:text-slate-400">{selectedPatient.chiefComplaint}</p>
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsEditPatientModalOpen(false)}>
              Cancel
            </Button>
            <Button onClick={() => setIsEditPatientModalOpen(false)}>
              Save Changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
