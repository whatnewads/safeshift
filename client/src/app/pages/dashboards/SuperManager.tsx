/**
 * SuperManager Dashboard
 *
 * Multi-clinic operations oversight interface for SuperManager role.
 * Part of the SuperManager Dashboard workflow:
 * View → Hook → Service → API → ViewModel → Repository → Database
 *
 * The SuperManager role is associated with 'Manager' role type with elevated
 * permissions across multiple establishments/clinics.
 *
 * @module pages/dashboards/SuperManager
 */

import React, { useState, useCallback } from 'react';
import { Button } from '../../components/ui/button.js';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card.js';
import { Badge } from '../../components/ui/badge.js';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../components/ui/tabs.js';
import { Skeleton } from '../../components/ui/skeleton.js';
import { Progress } from '../../components/ui/progress.js';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../../components/ui/table.js';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '../../components/ui/dialog.js';
import { Textarea } from '../../components/ui/textarea.js';
import { Label } from '../../components/ui/label.js';
import {
  AlertCircle,
  Building2,
  Users,
  Activity,
  ClipboardList,
  CheckCircle,
  XCircle,
  RefreshCw,
  Clock,
  GraduationCap,
  ShieldAlert,
  AlertTriangle,
  UserCheck,
  Star,
  Timer,
  TrendingUp,
  Calendar,
} from 'lucide-react';
import { useSuperManager } from '../../hooks/useSuperManager.js';
import type {
  ClinicPerformance,
  StaffMember,
  ExpiringCredential,
  OverdueTraining,
  PendingApproval,
} from '../../services/supermanager.service.js';

// ============================================================================
// Types
// ============================================================================

interface DenyDialogState {
  open: boolean;
  requestId: string;
  type: string;
  staffName: string;
  details: string;
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Format a date string for display
 */
function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

/**
 * Get badge variant based on training status
 */
function getTrainingStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (status) {
    case 'compliant':
      return 'default';
    case 'expiring':
      return 'outline';
    case 'overdue':
      return 'destructive';
    default:
      return 'secondary';
  }
}

/**
 * Get badge variant based on credential status
 */
function getCredentialStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (status) {
    case 'valid':
      return 'default';
    case 'expiring':
      return 'outline';
    case 'expired':
      return 'destructive';
    default:
      return 'secondary';
  }
}

/**
 * Format training/credential status for display
 */
function formatStatus(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

/**
 * Format approval type for display
 */
function formatApprovalType(type: string): string {
  const typeMap: Record<string, string> = {
    time_off: 'Time Off',
    schedule_change: 'Schedule Change',
    chart_review: 'Chart Review',
  };
  return typeMap[type] || type;
}

/**
 * Get urgency badge variant based on days until event
 */
function getUrgencyBadgeVariant(days: number): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (days <= 7) return 'destructive';
  if (days <= 14) return 'outline';
  return 'secondary';
}

// ============================================================================
// Loading Skeleton Components
// ============================================================================

function StatsLoadingSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      {[1, 2, 3, 4].map((i) => (
        <Card key={i} className="p-6">
          <div className="flex items-center gap-4">
            <Skeleton className="h-12 w-12 rounded-lg" />
            <div className="space-y-2">
              <Skeleton className="h-6 w-16" />
              <Skeleton className="h-4 w-24" />
            </div>
          </div>
        </Card>
      ))}
    </div>
  );
}

function TableLoadingSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="space-y-3">
      {[...Array(rows)].map((_, i) => (
        <div key={i} className="flex items-center gap-4 p-4 border rounded-lg">
          <Skeleton className="h-4 w-[25%]" />
          <Skeleton className="h-4 w-[20%]" />
          <Skeleton className="h-4 w-[15%]" />
          <Skeleton className="h-4 w-[15%]" />
          <Skeleton className="h-8 w-20 ml-auto" />
        </div>
      ))}
    </div>
  );
}

// ============================================================================
// Empty State Components
// ============================================================================

function EmptyState({
  icon: Icon,
  title,
  description,
}: {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  description: string;
}) {
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <Icon className="h-12 w-12 text-slate-300 dark:text-slate-600 mb-4" />
      <h3 className="text-lg font-semibold text-slate-600 dark:text-slate-400 mb-2">{title}</h3>
      <p className="text-sm text-slate-500 dark:text-slate-500 max-w-md">{description}</p>
    </div>
  );
}

// ============================================================================
// Error State Component
// ============================================================================

function ErrorState({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <AlertCircle className="h-12 w-12 text-red-500 mb-4" />
      <h3 className="text-lg font-semibold text-slate-600 dark:text-slate-400 mb-2">
        Something went wrong
      </h3>
      <p className="text-sm text-slate-500 dark:text-slate-500 mb-4 max-w-md">{message}</p>
      <Button onClick={onRetry} variant="outline">
        <RefreshCw className="h-4 w-4 mr-2" />
        Try Again
      </Button>
    </div>
  );
}

// ============================================================================
// Stats Cards Component
// ============================================================================

function StatsCards({
  clinicsManaged,
  totalStaff,
  encountersToday,
  pendingApprovals,
}: {
  clinicsManaged: number;
  totalStaff: number;
  encountersToday: number;
  pendingApprovals: number;
}) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
            <Building2 className="h-6 w-6 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <p className="text-2xl font-bold">{clinicsManaged}</p>
            <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
              Clinics Managed
            </p>
          </div>
        </div>
      </Card>

      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
            <Users className="h-6 w-6 text-green-600 dark:text-green-400" />
          </div>
          <div>
            <p className="text-2xl font-bold">{totalStaff}</p>
            <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
              Total Staff
            </p>
          </div>
        </div>
      </Card>

      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
            <Activity className="h-6 w-6 text-purple-600 dark:text-purple-400" />
          </div>
          <div>
            <p className="text-2xl font-bold">{encountersToday}</p>
            <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
              Encounters Today
            </p>
          </div>
        </div>
      </Card>

      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0">
            <ClipboardList className="h-6 w-6 text-orange-600 dark:text-orange-400" />
          </div>
          <div>
            <p className="text-2xl font-bold">{pendingApprovals}</p>
            <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
              Pending Approvals
            </p>
          </div>
        </div>
      </Card>
    </div>
  );
}

// ============================================================================
// Clinic Performance Table Component
// ============================================================================

function ClinicPerformanceTable({
  clinics,
}: {
  clinics: ClinicPerformance[];
}) {
  if (clinics.length === 0) {
    return (
      <EmptyState
        icon={Building2}
        title="No clinics found"
        description="There are no clinics assigned to your management. Contact your administrator if you believe this is an error."
      />
    );
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Clinic Name</TableHead>
            <TableHead className="text-center">Encounters Today</TableHead>
            <TableHead className="text-center">Avg Wait Time</TableHead>
            <TableHead className="text-center">Satisfaction</TableHead>
            <TableHead className="text-center">Staff Count</TableHead>
            <TableHead className="text-center">Status</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {clinics.map((clinic) => (
            <TableRow key={clinic.id}>
              <TableCell className="font-medium">
                <div>
                  {clinic.name}
                  {clinic.location && (
                    <span className="text-xs text-slate-500 dark:text-slate-400 block">
                      {clinic.location}
                    </span>
                  )}
                </div>
              </TableCell>
              <TableCell className="text-center">
                <div className="flex items-center justify-center gap-1">
                  <TrendingUp className="h-4 w-4 text-green-500" />
                  {clinic.encountersToday}
                </div>
              </TableCell>
              <TableCell className="text-center">
                <div className="flex items-center justify-center gap-1">
                  <Timer className="h-4 w-4 text-slate-400" />
                  {clinic.avgWaitTime} min
                </div>
              </TableCell>
              <TableCell className="text-center">
                <div className="flex items-center justify-center gap-1">
                  <Star className="h-4 w-4 text-yellow-500" />
                  {clinic.patientSatisfaction.toFixed(1)}
                </div>
              </TableCell>
              <TableCell className="text-center">
                <div className="flex items-center justify-center gap-1">
                  <Users className="h-4 w-4 text-slate-400" />
                  {clinic.staffCount}
                </div>
              </TableCell>
              <TableCell className="text-center">
                <Badge variant={clinic.status === 'active' ? 'default' : 'secondary'}>
                  {formatStatus(clinic.status)}
                </Badge>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ============================================================================
// Expiring Credentials Table Component
// ============================================================================

function ExpiringCredentialsTable({
  credentials,
}: {
  credentials: ExpiringCredential[];
}) {
  if (credentials.length === 0) {
    return (
      <EmptyState
        icon={ShieldAlert}
        title="No expiring credentials"
        description="All staff credentials are current. Great job maintaining compliance!"
      />
    );
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Staff Name</TableHead>
            <TableHead>Credential</TableHead>
            <TableHead>Clinic</TableHead>
            <TableHead className="text-center">Expires</TableHead>
            <TableHead className="text-center">Days Left</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {credentials.map((credential) => (
            <TableRow key={credential.id}>
              <TableCell className="font-medium">{credential.staffName}</TableCell>
              <TableCell>{credential.credential}</TableCell>
              <TableCell>{credential.clinic}</TableCell>
              <TableCell className="text-center">{formatDate(credential.expiresAt)}</TableCell>
              <TableCell className="text-center">
                <Badge variant={getUrgencyBadgeVariant(credential.daysUntilExpiry)}>
                  {credential.daysUntilExpiry} days
                </Badge>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ============================================================================
// Overdue Training Table Component
// ============================================================================

function OverdueTrainingTable({
  overdue,
}: {
  overdue: OverdueTraining[];
}) {
  if (overdue.length === 0) {
    return (
      <EmptyState
        icon={GraduationCap}
        title="No overdue training"
        description="All staff are up to date with their required training. Excellent work!"
      />
    );
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Staff Name</TableHead>
            <TableHead>Training</TableHead>
            <TableHead>Clinic</TableHead>
            <TableHead className="text-center">Due Date</TableHead>
            <TableHead className="text-center">Days Overdue</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {overdue.map((training) => (
            <TableRow key={training.id}>
              <TableCell className="font-medium">{training.staffName}</TableCell>
              <TableCell>{training.training}</TableCell>
              <TableCell>{training.clinic}</TableCell>
              <TableCell className="text-center">{formatDate(training.dueDate)}</TableCell>
              <TableCell className="text-center">
                <Badge variant="destructive">
                  {training.daysOverdue} days
                </Badge>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ============================================================================
// Pending Approvals Table Component
// ============================================================================

function PendingApprovalsTable({
  approvals,
  onApprove,
  onDeny,
  isProcessing,
}: {
  approvals: PendingApproval[];
  onApprove: (approval: PendingApproval) => void;
  onDeny: (approval: PendingApproval) => void;
  isProcessing: boolean;
}) {
  if (approvals.length === 0) {
    return (
      <EmptyState
        icon={ClipboardList}
        title="No pending approvals"
        description="All requests have been processed. You're all caught up!"
      />
    );
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Type</TableHead>
            <TableHead>Staff Name</TableHead>
            <TableHead>Request Date</TableHead>
            <TableHead>Details</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {approvals.map((approval) => (
            <TableRow key={approval.id}>
              <TableCell>
                <Badge variant="outline">
                  {formatApprovalType(approval.type)}
                </Badge>
              </TableCell>
              <TableCell className="font-medium">{approval.staffName}</TableCell>
              <TableCell>{formatDate(approval.requestDate)}</TableCell>
              <TableCell className="max-w-[200px] truncate">{approval.details}</TableCell>
              <TableCell className="text-right">
                <div className="flex gap-2 justify-end">
                  <Button
                    variant="default"
                    size="sm"
                    onClick={() => onApprove(approval)}
                    disabled={isProcessing}
                  >
                    <CheckCircle className="h-4 w-4 mr-1" />
                    Approve
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onDeny(approval)}
                    disabled={isProcessing}
                  >
                    <XCircle className="h-4 w-4 mr-1" />
                    Deny
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ============================================================================
// Staff Overview Table Component
// ============================================================================

function StaffOverviewTable({
  staff,
}: {
  staff: StaffMember[];
}) {
  if (staff.length === 0) {
    return (
      <EmptyState
        icon={Users}
        title="No staff found"
        description="There are no staff members assigned to your managed clinics."
      />
    );
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Clinic</TableHead>
            <TableHead>Role</TableHead>
            <TableHead className="text-center">Training Status</TableHead>
            <TableHead className="text-center">Credential Status</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {staff.map((member) => (
            <TableRow key={member.id}>
              <TableCell className="font-medium">
                <div className="flex items-center gap-2">
                  <UserCheck className="h-4 w-4 text-slate-400" />
                  {member.name}
                </div>
              </TableCell>
              <TableCell>{member.clinic}</TableCell>
              <TableCell>{member.role}</TableCell>
              <TableCell className="text-center">
                <Badge variant={getTrainingStatusBadgeVariant(member.trainingStatus)}>
                  {formatStatus(member.trainingStatus)}
                </Badge>
              </TableCell>
              <TableCell className="text-center">
                <Badge variant={getCredentialStatusBadgeVariant(member.credentialStatus)}>
                  {formatStatus(member.credentialStatus)}
                </Badge>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ============================================================================
// Deny Request Dialog Component
// ============================================================================

function DenyDialog({
  open,
  onOpenChange,
  staffName,
  type,
  details,
  onSubmit,
  isSubmitting,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  staffName: string;
  type: string;
  details: string;
  onSubmit: (reason: string) => Promise<void>;
  isSubmitting: boolean;
}) {
  const [reason, setReason] = useState('');

  const handleSubmit = async () => {
    await onSubmit(reason);
    setReason('');
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Deny Request</DialogTitle>
          <DialogDescription>
            Provide a reason for denying this request.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label className="text-sm font-medium">Staff Member</Label>
            <p className="text-sm text-slate-600 dark:text-slate-400">{staffName}</p>
          </div>

          <div className="space-y-2">
            <Label className="text-sm font-medium">Request Type</Label>
            <p className="text-sm text-slate-600 dark:text-slate-400">{formatApprovalType(type)}</p>
          </div>

          <div className="space-y-2">
            <Label className="text-sm font-medium">Details</Label>
            <p className="text-sm text-slate-600 dark:text-slate-400">{details}</p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="reason">Reason for Denial (Optional)</Label>
            <Textarea
              id="reason"
              placeholder="Enter a reason for denying this request..."
              value={reason}
              onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setReason(e.target.value)}
              rows={3}
            />
          </div>

          <div className="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <div className="flex items-start gap-2">
              <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-sm font-medium text-amber-900 dark:text-amber-300">
                  Confirm Denial
                </p>
                <p className="text-xs text-amber-700 dark:text-amber-400 mt-1">
                  This action will notify the staff member that their request has been denied.
                </p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button variant="destructive" onClick={handleSubmit} disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                Denying...
              </>
            ) : (
              <>
                <XCircle className="h-4 w-4 mr-2" />
                Deny Request
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ============================================================================
// Main SuperManager Dashboard Component
// ============================================================================

export default function SuperManagerDashboard() {
  // Dashboard data hook
  const {
    stats,
    clinicPerformance,
    staffRequiringAttention,
    pendingApprovals,
    staffOverview,
    loading,
    error,
    refetch,
    handleApprove,
    handleDeny,
  } = useSuperManager();

  // Dialog states
  const [denyDialog, setDenyDialog] = useState<DenyDialogState>({
    open: false,
    requestId: '',
    type: '',
    staffName: '',
    details: '',
  });
  const [isProcessing, setIsProcessing] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  // Handle approve action
  const handleApproveClick = useCallback(async (approval: PendingApproval) => {
    setIsProcessing(true);
    setActionError(null);
    try {
      await handleApprove(approval.id, approval.type);
    } catch (err) {
      setActionError(err instanceof Error ? err.message : 'Failed to approve request');
    } finally {
      setIsProcessing(false);
    }
  }, [handleApprove]);

  // Handle opening deny dialog
  const handleDenyClick = useCallback((approval: PendingApproval) => {
    setDenyDialog({
      open: true,
      requestId: approval.id,
      type: approval.type,
      staffName: approval.staffName,
      details: approval.details,
    });
    setActionError(null);
  }, []);

  // Handle deny submission
  const handleDenySubmit = useCallback(
    async (reason: string) => {
      setIsProcessing(true);
      setActionError(null);
      try {
        await handleDeny(denyDialog.requestId, denyDialog.type, reason);
      } catch (err) {
        setActionError(err instanceof Error ? err.message : 'Failed to deny request');
        throw err;
      } finally {
        setIsProcessing(false);
      }
    },
    [handleDeny, denyDialog.requestId, denyDialog.type]
  );

  // Render error state
  if (error && !loading) {
    return (
      <div className="p-4 sm:p-6">
        <div className="mb-6">
          <h1 className="text-2xl sm:text-3xl font-bold mb-2">SuperManager Dashboard</h1>
          <p className="text-sm sm:text-base text-slate-600 dark:text-slate-400">
            Multi-Clinic Operations Oversight
          </p>
        </div>
        <Card className="p-6">
          <ErrorState message={error} onRetry={refetch} />
        </Card>
      </div>
    );
  }

  return (
    <div className="p-4 sm:p-6 space-y-4 sm:space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div className="flex items-center gap-3 mb-2">
            <div className="h-10 w-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
              <Building2 className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold">SuperManager Dashboard</h1>
          </div>
          <p className="text-sm sm:text-base text-slate-600 dark:text-slate-400">
            Multi-Clinic Operations Oversight
          </p>
        </div>
        <Button variant="outline" onClick={refetch} disabled={loading}>
          <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
      </div>

      {/* Action error notification */}
      {actionError && (
        <div className="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
          <div className="flex items-center gap-2">
            <XCircle className="h-5 w-5 text-red-600 dark:text-red-400" />
            <p className="text-sm text-red-800 dark:text-red-200">{actionError}</p>
            <Button
              variant="ghost"
              size="sm"
              className="ml-auto"
              onClick={() => setActionError(null)}
            >
              Dismiss
            </Button>
          </div>
        </div>
      )}

      {/* Stats Cards */}
      {loading ? (
        <StatsLoadingSkeleton />
      ) : stats ? (
        <StatsCards
          clinicsManaged={stats.clinicsManaged}
          totalStaff={stats.totalStaff}
          encountersToday={stats.encountersToday}
          pendingApprovals={stats.pendingApprovals}
        />
      ) : null}

      {/* Tabbed Content */}
      <Tabs defaultValue="clinics" className="w-full">
        <TabsList className="grid w-full grid-cols-4">
          <TabsTrigger value="clinics" className="flex items-center gap-2">
            <Building2 className="h-4 w-4" />
            <span className="hidden sm:inline">Clinics</span>
          </TabsTrigger>
          <TabsTrigger value="attention" className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4" />
            <span className="hidden sm:inline">Attention</span>
            {staffRequiringAttention && (
              (staffRequiringAttention.expiringCredentials.length > 0 || 
               staffRequiringAttention.overdueTraining.length > 0) && (
                <Badge variant="destructive" className="ml-1">
                  {staffRequiringAttention.expiringCredentials.length + 
                   staffRequiringAttention.overdueTraining.length}
                </Badge>
              )
            )}
          </TabsTrigger>
          <TabsTrigger value="approvals" className="flex items-center gap-2">
            <ClipboardList className="h-4 w-4" />
            <span className="hidden sm:inline">Approvals</span>
            {stats && stats.pendingApprovals > 0 && (
              <Badge variant="secondary" className="ml-1">
                {stats.pendingApprovals}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="staff" className="flex items-center gap-2">
            <Users className="h-4 w-4" />
            <span className="hidden sm:inline">Staff</span>
          </TabsTrigger>
        </TabsList>

        {/* Clinic Performance Tab */}
        <TabsContent value="clinics" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Building2 className="h-5 w-5" />
                Clinic Performance
              </CardTitle>
            </CardHeader>
            <CardContent>
              {loading ? (
                <TableLoadingSkeleton />
              ) : (
                <ClinicPerformanceTable clinics={clinicPerformance} />
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Staff Requiring Attention Tab */}
        <TabsContent value="attention" className="mt-4 space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ShieldAlert className="h-5 w-5 text-orange-500" />
                Expiring Credentials
              </CardTitle>
            </CardHeader>
            <CardContent>
              {loading ? (
                <TableLoadingSkeleton rows={3} />
              ) : (
                <ExpiringCredentialsTable 
                  credentials={staffRequiringAttention?.expiringCredentials || []} 
                />
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <GraduationCap className="h-5 w-5 text-red-500" />
                Overdue Training
              </CardTitle>
            </CardHeader>
            <CardContent>
              {loading ? (
                <TableLoadingSkeleton rows={3} />
              ) : (
                <OverdueTrainingTable 
                  overdue={staffRequiringAttention?.overdueTraining || []} 
                />
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Pending Approvals Tab */}
        <TabsContent value="approvals" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ClipboardList className="h-5 w-5" />
                Pending Approvals
              </CardTitle>
            </CardHeader>
            <CardContent>
              {loading ? (
                <TableLoadingSkeleton />
              ) : (
                <PendingApprovalsTable
                  approvals={pendingApprovals}
                  onApprove={handleApproveClick}
                  onDeny={handleDenyClick}
                  isProcessing={isProcessing}
                />
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Staff Overview Tab */}
        <TabsContent value="staff" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Users className="h-5 w-5" />
                Staff Overview
              </CardTitle>
            </CardHeader>
            <CardContent>
              {loading ? (
                <TableLoadingSkeleton />
              ) : (
                <StaffOverviewTable staff={staffOverview} />
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Deny Request Dialog */}
      <DenyDialog
        open={denyDialog.open}
        onOpenChange={(open) => setDenyDialog((prev) => ({ ...prev, open }))}
        staffName={denyDialog.staffName}
        type={denyDialog.type}
        details={denyDialog.details}
        onSubmit={handleDenySubmit}
        isSubmitting={isProcessing}
      />
    </div>
  );
}
