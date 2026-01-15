import { useNavigate } from 'react-router-dom';
import { useCallback } from 'react';
import { Button } from '../../components/ui/button.js';
import { Card } from '../../components/ui/card.js';
import { Badge } from '../../components/ui/badge.js';
import { Checkbox } from '../../components/ui/checkbox.js';
import { useShift } from '../../contexts/ShiftContext.js';
import { useSync } from '../../contexts/SyncContext.js';
import { useClinicalProvider } from '../../hooks/useClinicalProvider.js';
import type { RecentEncounter } from '../../hooks/useClinicalProvider.js';
import { toast } from 'sonner';
import {
  PlayCircle,
  TestTube,
  Wind,
  Clipboard,
  AlertCircle,
  FileText,
  User,
  UserPlus,
  Clock,
  Eye,
  Info,
  RefreshCw,
  Loader2,
  XCircle,
  Inbox,
} from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../../components/ui/table.js';

export default function ClinicalProviderDashboard() {
  const navigate = useNavigate();
  const { shiftData } = useShift();
  const { } = useSync();
  
  // Use the clinical provider dashboard hook for API data
  const {
    activeEncounters,
    recentEncounters,
    loading,
    error,
    refetch,
    clearError,
  } = useClinicalProvider();

  const handleStartEncounter = () => {
    // Generate new encounter ID and go directly to workspace
    const encounterId = `ENC-${Date.now()}`;
    navigate(`/encounters/${encounterId}`, {
      state: { shiftData }
    });
  };

  const handleFollowUp = (encounter: RecentEncounter) => {
    // Navigate to encounter workspace with prefilled patient data, history, and shift info
    const encounterId = `ENC-${Date.now()}`;
    navigate(`/encounters/${encounterId}`, { 
      state: { 
        followUp: true,
        patientData: encounter.patientData,
        previousEncounterId: encounter.id,
        previousType: encounter.type,
        shiftData,
        incidentHistory: {
          previousIncidentDate: new Date().toISOString(),
          previousIncidentType: encounter.type,
          previousProvidedCare: 'See previous encounter for details'
        }
      } 
    });
  };

  // Handler for View All Active Encounters button
  const handleViewAllActiveEncounters = useCallback(() => {
    if (activeEncounters.length === 0) {
      toast.info('No active encounters at this time', {
        duration: 3000,
      });
    } else {
      navigate('/encounters');
    }
  }, [activeEncounters, navigate]);

  // Loading skeleton for table rows
  const TableRowSkeleton = () => (
    <TableRow>
      <TableCell>
        <div className="flex items-center gap-2">
          <div className="h-4 w-4 bg-slate-200 rounded animate-pulse"></div>
          <div className="h-4 w-24 bg-slate-200 rounded animate-pulse"></div>
        </div>
      </TableCell>
      <TableCell>
        <div className="h-4 w-4 bg-slate-200 rounded animate-pulse"></div>
      </TableCell>
      <TableCell>
        <div className="h-4 w-20 bg-slate-200 rounded animate-pulse"></div>
      </TableCell>
      <TableCell>
        <div className="h-6 w-16 bg-slate-200 rounded animate-pulse"></div>
      </TableCell>
      <TableCell>
        <div className="h-8 w-20 bg-slate-200 rounded animate-pulse"></div>
      </TableCell>
    </TableRow>
  );

  // Loading skeleton for open cases
  const CasesSkeleton = () => (
    <div className="space-y-3">
      {[1, 2].map((i) => (
        <div key={i} className="p-4 border border-slate-200 rounded-lg animate-pulse">
          <div className="flex items-center justify-between mb-2">
            <div className="h-4 w-24 bg-slate-200 rounded"></div>
            <div className="h-5 w-16 bg-slate-200 rounded"></div>
          </div>
          <div className="h-4 w-32 bg-slate-200 rounded mb-2"></div>
          <div className="flex items-center justify-between">
            <div className="h-3 w-20 bg-slate-200 rounded"></div>
            <div className="h-8 w-16 bg-slate-200 rounded"></div>
          </div>
        </div>
      ))}
    </div>
  );

  // Empty state component
  const EmptyState = ({ message, icon: Icon }: { message: string; icon: typeof Inbox }) => (
    <div className="flex flex-col items-center justify-center py-8 text-slate-500">
      <Icon className="h-12 w-12 mb-3 text-slate-300" />
      <p className="text-sm">{message}</p>
    </div>
  );

  // Error state component
  const ErrorState = ({ message, onRetry, onDismiss }: { message: string; onRetry: () => void; onDismiss: () => void }) => (
    <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
      <div className="flex items-start gap-3">
        <XCircle className="h-5 w-5 text-red-500 mt-0.5" />
        <div className="flex-1">
          <p className="text-sm text-red-700">{message}</p>
          <div className="mt-2 flex gap-2">
            <Button variant="outline" size="sm" onClick={onRetry}>
              <RefreshCw className="h-3 w-3 mr-1" />
              Retry
            </Button>
            <Button variant="ghost" size="sm" onClick={onDismiss}>
              Dismiss
            </Button>
          </div>
        </div>
      </div>
    </div>
  );

  return (
    <div className="space-y-6">
      {/* Hero Section with Giant Start Button */}
      <div className="bg-gradient-to-br from-blue-600 to-blue-700 p-8 text-white">
        <div>
          <h1 className="text-3xl font-bold mb-2">Clinical Provider Dashboard</h1>
          <p className="text-blue-100 mb-4">Start an encounter or view your recent activity</p>
                    
          <Button
            size="lg"
            className="bg-blue-600 hover:bg-blue-700 text-white px-8 py-6 text-lg"
            onClick={() => navigate('/encounters/workspace')}
          >
            <PlayCircle className="h-8 w-8 mr-3" />
            Start Encounter
          </Button>
        </div>
      </div>

      {/* Error Display */}
      {error && (
        <div className="px-6">
          <ErrorState message={error} onRetry={refetch} onDismiss={clearError} />
        </div>
      )}

      {/* Tests Quick Start - Smaller Tiles */}
      <div className="px-6">
        <h2 className="text-lg font-semibold mb-3">Quick Actions</h2>
        <div className="grid md:grid-cols-3 gap-3">
          <Card 
            className="p-4 hover:shadow-lg transition-shadow cursor-pointer"
            onClick={handleStartEncounter}
          >
            <TestTube className="h-6 w-6 text-purple-600 mb-2" />
            <h3 className="text-sm font-semibold">Drug Test</h3>
            <p className="text-xs text-slate-600 dark:text-slate-400">Collection & testing</p>
          </Card>

          <Card 
            className="p-4 hover:shadow-lg transition-shadow cursor-pointer"
            onClick={handleStartEncounter}
          >
            <Wind className="h-6 w-6 text-blue-600 mb-2" />
            <h3 className="text-sm font-semibold">Fit Test</h3>
            <p className="text-xs text-slate-600 dark:text-slate-400">Respirator fitting</p>
          </Card>

          <Card 
            className="p-4 hover:shadow-lg transition-shadow cursor-pointer"
            onClick={handleStartEncounter}
          >
            <Clipboard className="h-6 w-6 text-orange-600 mb-2" />
            <h3 className="text-sm font-semibold">Pre-Employment</h3>
            <p className="text-xs text-slate-600 dark:text-slate-400">Screening evaluation</p>
          </Card>
        </div>
      </div>

      {/* Main Content Grid */}
      <div className="grid lg:grid-cols-2 gap-6 px-6">
        {/* Recent Encounters */}
        <Card className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold">Recent Encounters</h2>
            <div className="flex items-center gap-2">
              {loading && <Loader2 className="h-4 w-4 animate-spin text-slate-400" />}
              <Button variant="ghost" size="sm" onClick={refetch} disabled={loading}>
                <RefreshCw className={`h-4 w-4 mr-1 ${loading ? 'animate-spin' : ''}`} />
                Refresh
              </Button>
              <Button variant="ghost" size="sm">View All</Button>
            </div>
          </div>
          
          {loading ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Patient</TableHead>
                  <TableHead>
                    <div className="flex items-center gap-1">
                      Reminder
                      <div className="group relative inline-block">
                        <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-slate-900 dark:bg-slate-700 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 w-64 text-center">
                          If box is checked, a text appointment reminder has been sent to the patient
                        </span>
                      </div>
                    </div>
                  </TableHead>
                  <TableHead>Last Seen</TableHead>
                  <TableHead>Clearance</TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRowSkeleton />
                <TableRowSkeleton />
                <TableRowSkeleton />
              </TableBody>
            </Table>
          ) : recentEncounters.length === 0 ? (
            <EmptyState message="No recent encounters found" icon={Inbox} />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Patient</TableHead>
                  <TableHead>
                    <div className="flex items-center gap-1">
                      Reminder
                      <div className="group relative inline-block">
                        <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        <span className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-slate-900 dark:bg-slate-700 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 w-64 text-center">
                          If box is checked, a text appointment reminder has been sent to the patient
                        </span>
                      </div>
                    </div>
                  </TableHead>
                  <TableHead>Last Seen</TableHead>
                  <TableHead>Clearance</TableHead>
                  <TableHead>Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentEncounters.map((encounter) => (
                  <TableRow key={encounter.id}>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <User className="h-4 w-4 text-slate-400" />
                        {encounter.patient}
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Checkbox checked={encounter.reminderSent} disabled />
                        {encounter.reminderSent && (
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
                    <TableCell>{encounter.lastSeen}</TableCell>
                    <TableCell>
                      <Badge
                        variant={encounter.clearanceStatus === 'Cleared' ? 'default' : 'destructive'}
                      >
                        {encounter.clearanceStatus}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Button 
                        variant="ghost" 
                        size="sm"
                        onClick={() => handleFollowUp(encounter)}
                      >
                        <UserPlus className="h-4 w-4 mr-1" />
                        Follow Up
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </Card>

        {/* Open Cases (Active Encounters) */}
        <Card className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold flex items-center gap-2">
              <AlertCircle className="h-5 w-5" />
              Active Encounters
            </h2>
            <Button variant="ghost" size="sm" onClick={handleViewAllActiveEncounters}>
              View All Active Encounters
            </Button>
          </div>
          
          {loading ? (
            <CasesSkeleton />
          ) : activeEncounters.length === 0 ? (
            <EmptyState message="No active encounters" icon={Inbox} />
          ) : (
            <div className="space-y-3">
              {activeEncounters.map((encounter) => (
                <div
                  key={encounter.id}
                  className="p-4 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer"
                  onClick={() => navigate(`/encounters/${encounter.id}`)}
                >
                  <div className="flex items-center justify-between mb-2">
                    <p className="font-medium">{encounter.patientName}</p>
                    <Badge 
                      variant={encounter.priority === 'urgent' ? 'destructive' : encounter.priority === 'high' ? 'default' : 'secondary'}
                    >
                      {encounter.priority}
                    </Badge>
                  </div>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-2">
                    {encounter.chiefComplaint || 'No chief complaint recorded'}
                  </p>
                  <div className="flex items-center justify-between">
                    <p className="text-xs text-slate-500 flex items-center gap-1">
                      <Clock className="h-3 w-3" />
                      Started {new Date(encounter.startTime).toLocaleTimeString()}
                    </p>
                    <Button variant="ghost" size="sm">
                      <FileText className="h-4 w-4 mr-1" />
                      Continue
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </Card>
      </div>

      {/* Pending Orders Section removed per requirements */}
    </div>
  );
}
