/**
 * Doctor (MRO) Dashboard
 *
 * Medical Review Officer interface for DOT drug testing, result verification,
 * and order signing. Part of the Doctor Dashboard workflow:
 * View → Hook → Service → API → ViewModel → Repository → Database
 *
 * The Doctor role is associated with 'pclinician' role type (provider clinician).
 *
 * @module pages/dashboards/Doctor
 */

import React, { useState, useCallback } from 'react';
import { Button } from '../../components/ui/button.js';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card.js';
import { Badge } from '../../components/ui/badge.js';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../components/ui/tabs.js';
import { Skeleton } from '../../components/ui/skeleton.js';
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../../components/ui/select.js';
import { Textarea } from '../../components/ui/textarea.js';
import { Label } from '../../components/ui/label.js';
import {
  AlertCircle,
  Clock,
  CheckCircle,
  FileSignature,
  ClipboardCheck,
  History,
  RefreshCw,
  Stethoscope,
  Timer,
  FileText,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Eye,
  PenLine,
} from 'lucide-react';
import { useDoctor } from '../../hooks/useDoctor.js';
import type {
  PendingVerification,
  PendingOrder,
  VerificationResult,
} from '../../services/doctor.service.js';

// ============================================================================
// Types
// ============================================================================

interface VerifyDialogState {
  open: boolean;
  testId: string;
  patientName: string;
  testType: string;
}

interface SignDialogState {
  open: boolean;
  orderId: string;
  patientName: string;
  orderType: string;
  description: string;
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
 * Format a date string with time for display
 */
function formatDateTime(dateString: string): string {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Get badge variant based on priority
 */
function getPriorityBadgeVariant(priority: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (priority) {
    case 'urgent':
      return 'destructive';
    case 'high':
      return 'default';
    default:
      return 'secondary';
  }
}

/**
 * Get badge variant based on chain of custody status
 */
function getCocStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (status) {
    case 'verified':
      return 'default';
    case 'failed':
      return 'destructive';
    case 'in_progress':
    case 'pending':
      return 'outline';
    default:
      return 'secondary';
  }
}

/**
 * Format chain of custody status for display
 */
function formatCocStatus(status: string): string {
  const statusMap: Record<string, string> = {
    not_started: 'Not Started',
    pending: 'Pending',
    in_progress: 'In Progress',
    verified: 'Verified',
    failed: 'Failed',
  };
  return statusMap[status] || status;
}

/**
 * Format test status for display
 */
function formatTestStatus(status: string): string {
  const statusMap: Record<string, string> = {
    pending: 'Pending',
    positive: 'Positive',
    negative: 'Negative',
    cancelled: 'Cancelled',
    invalid: 'Invalid',
  };
  return statusMap[status] || status;
}

/**
 * Get badge variant based on test status
 */
function getTestStatusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (status) {
    case 'positive':
      return 'destructive';
    case 'negative':
      return 'default';
    case 'cancelled':
    case 'invalid':
      return 'outline';
    default:
      return 'secondary';
  }
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
  pendingVerifications,
  ordersToSign,
  reviewedToday,
  avgTurnaroundHours,
}: {
  pendingVerifications: number;
  ordersToSign: number;
  reviewedToday: number;
  avgTurnaroundHours: number;
}) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0">
            <ClipboardCheck className="h-6 w-6 text-orange-600 dark:text-orange-400" />
          </div>
          <div>
            <p className="text-2xl font-bold">{pendingVerifications}</p>
            <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
              Pending Verifications
            </p>
          </div>
        </div>
      </Card>

      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
            <FileSignature className="h-6 w-6 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <p className="text-2xl font-bold">{ordersToSign}</p>
            <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
              Orders to Sign
            </p>
          </div>
        </div>
      </Card>

      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
            <CheckCircle className="h-6 w-6 text-green-600 dark:text-green-400" />
          </div>
          <div>
            <p className="text-2xl font-bold">{reviewedToday}</p>
            <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
              Reviewed Today
            </p>
          </div>
        </div>
      </Card>

      <Card className="p-6">
        <div className="flex items-center gap-4">
          <div className="h-12 w-12 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
            <Timer className="h-6 w-6 text-purple-600 dark:text-purple-400" />
          </div>
          <div>
            <p className="text-2xl font-bold">{avgTurnaroundHours.toFixed(1)}h</p>
            <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
              Avg Turnaround
            </p>
          </div>
        </div>
      </Card>
    </div>
  );
}

// ============================================================================
// Pending Verifications Table Component
// ============================================================================

function PendingVerificationsTable({
  verifications,
  onVerify,
  onViewDetails,
}: {
  verifications: PendingVerification[];
  onVerify: (verification: PendingVerification) => void;
  onViewDetails: (verification: PendingVerification) => void;
}) {
  if (verifications.length === 0) {
    return (
      <EmptyState
        icon={CheckCircle2}
        title="All caught up!"
        description="There are no pending DOT verifications requiring your review. Great job staying on top of your workload!"
      />
    );
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Patient</TableHead>
            <TableHead>Test Type</TableHead>
            <TableHead>Collection Date</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Priority</TableHead>
            <TableHead>Chain of Custody</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {verifications.map((verification) => (
            <TableRow key={verification.id}>
              <TableCell className="font-medium">{verification.patientName}</TableCell>
              <TableCell>{verification.testType}</TableCell>
              <TableCell>{formatDate(verification.collectionDate)}</TableCell>
              <TableCell>
                <Badge variant={getTestStatusBadgeVariant(verification.status)}>
                  {formatTestStatus(verification.status)}
                </Badge>
              </TableCell>
              <TableCell>
                <Badge variant={getPriorityBadgeVariant(verification.priority)}>
                  {verification.priority.charAt(0).toUpperCase() + verification.priority.slice(1)}
                </Badge>
              </TableCell>
              <TableCell>
                <Badge variant={getCocStatusBadgeVariant(verification.chainOfCustodyStatus)}>
                  {formatCocStatus(verification.chainOfCustodyStatus)}
                </Badge>
              </TableCell>
              <TableCell className="text-right">
                <div className="flex gap-2 justify-end">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onViewDetails(verification)}
                  >
                    <Eye className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="default"
                    size="sm"
                    onClick={() => onVerify(verification)}
                  >
                    <PenLine className="h-4 w-4 mr-1" />
                    Verify
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
// Pending Orders Table Component
// ============================================================================

function PendingOrdersTable({
  orders,
  onSign,
}: {
  orders: PendingOrder[];
  onSign: (order: PendingOrder) => void;
}) {
  if (orders.length === 0) {
    return (
      <EmptyState
        icon={FileText}
        title="No pending orders"
        description="There are no orders requiring your signature at this time."
      />
    );
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Patient</TableHead>
            <TableHead>Order Type</TableHead>
            <TableHead>Description</TableHead>
            <TableHead>Request Date</TableHead>
            <TableHead>Priority</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {orders.map((order) => (
            <TableRow key={order.id}>
              <TableCell className="font-medium">{order.patientName}</TableCell>
              <TableCell>{order.orderType}</TableCell>
              <TableCell className="max-w-[200px] truncate">{order.description}</TableCell>
              <TableCell>{formatDate(order.requestDate)}</TableCell>
              <TableCell>
                <Badge
                  variant={
                    order.priority === 'stat'
                      ? 'destructive'
                      : order.priority === 'urgent'
                        ? 'default'
                        : 'secondary'
                  }
                >
                  {order.priority.toUpperCase()}
                </Badge>
              </TableCell>
              <TableCell className="text-right">
                <Button variant="default" size="sm" onClick={() => onSign(order)}>
                  <FileSignature className="h-4 w-4 mr-1" />
                  Sign
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ============================================================================
// Verification History Table Component
// ============================================================================

function VerificationHistoryTable({
  history,
}: {
  history: Array<{
    id: string;
    patientName: string;
    testType: string;
    result: string;
    verifiedAt: string;
  }>;
}) {
  if (history.length === 0) {
    return (
      <EmptyState
        icon={History}
        title="No verification history"
        description="Your verification history will appear here once you've completed some reviews."
      />
    );
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Patient</TableHead>
            <TableHead>Test Type</TableHead>
            <TableHead>Result</TableHead>
            <TableHead>Verified At</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {history.map((entry) => (
            <TableRow key={entry.id}>
              <TableCell className="font-medium">{entry.patientName}</TableCell>
              <TableCell>{entry.testType}</TableCell>
              <TableCell>
                <Badge
                  variant={
                    entry.result === 'negative'
                      ? 'default'
                      : entry.result === 'positive'
                        ? 'destructive'
                        : 'outline'
                  }
                >
                  {entry.result.charAt(0).toUpperCase() + entry.result.slice(1)}
                </Badge>
              </TableCell>
              <TableCell>{formatDateTime(entry.verifiedAt)}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

// ============================================================================
// Verification Dialog Component
// ============================================================================

function VerificationDialog({
  open,
  onOpenChange,
  testId,
  patientName,
  testType,
  onSubmit,
  isSubmitting,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  testId: string;
  patientName: string;
  testType: string;
  onSubmit: (result: VerificationResult, comments: string) => Promise<void>;
  isSubmitting: boolean;
}) {
  const [result, setResult] = useState<VerificationResult>('negative');
  const [comments, setComments] = useState('');

  const handleSubmit = async () => {
    await onSubmit(result, comments);
    setResult('negative');
    setComments('');
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>MRO Verification</DialogTitle>
          <DialogDescription>
            Submit your verification decision for this DOT drug test.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label className="text-sm font-medium">Patient</Label>
            <p className="text-sm text-slate-600 dark:text-slate-400">{patientName}</p>
          </div>

          <div className="space-y-2">
            <Label className="text-sm font-medium">Test Type</Label>
            <p className="text-sm text-slate-600 dark:text-slate-400">{testType}</p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="result">Verification Result</Label>
            <Select value={result} onValueChange={(value: string) => setResult(value as VerificationResult)}>
              <SelectTrigger id="result">
                <SelectValue placeholder="Select result" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="negative">Negative</SelectItem>
                <SelectItem value="positive">Positive</SelectItem>
                <SelectItem value="dilute">Dilute</SelectItem>
                <SelectItem value="substituted">Substituted</SelectItem>
                <SelectItem value="adulterated">Adulterated</SelectItem>
                <SelectItem value="invalid">Invalid</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
                <SelectItem value="refused">Refused</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="comments">MRO Comments (Optional)</Label>
            <Textarea
              id="comments"
              placeholder="Enter any comments or notes..."
              value={comments}
              onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setComments(e.target.value)}
              rows={3}
            />
          </div>

          {(result === 'positive' || result === 'adulterated' || result === 'substituted') && (
            <div className="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
              <div className="flex items-start gap-2">
                <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-amber-900 dark:text-amber-300">
                    Non-Negative Result
                  </p>
                  <p className="text-xs text-amber-700 dark:text-amber-400 mt-1">
                    This result will be reported as non-negative to the employer. Ensure all review
                    procedures have been followed.
                  </p>
                </div>
              </div>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                Submitting...
              </>
            ) : (
              <>
                <CheckCircle className="h-4 w-4 mr-2" />
                Submit Verification
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ============================================================================
// Sign Order Dialog Component
// ============================================================================

function SignOrderDialog({
  open,
  onOpenChange,
  orderId,
  patientName,
  orderType,
  description,
  onSubmit,
  isSubmitting,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  orderId: string;
  patientName: string;
  orderType: string;
  description: string;
  onSubmit: () => Promise<void>;
  isSubmitting: boolean;
}) {
  const handleSubmit = async () => {
    await onSubmit();
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Sign Order</DialogTitle>
          <DialogDescription>
            Review and sign this order to authorize it.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label className="text-sm font-medium">Patient</Label>
            <p className="text-sm text-slate-600 dark:text-slate-400">{patientName}</p>
          </div>

          <div className="space-y-2">
            <Label className="text-sm font-medium">Order Type</Label>
            <p className="text-sm text-slate-600 dark:text-slate-400">{orderType}</p>
          </div>

          <div className="space-y-2">
            <Label className="text-sm font-medium">Description</Label>
            <p className="text-sm text-slate-600 dark:text-slate-400">{description}</p>
          </div>

          <div className="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div className="flex items-start gap-2">
              <FileSignature className="h-5 w-5 text-blue-600 dark:text-blue-500 flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-sm font-medium text-blue-900 dark:text-blue-300">
                  Electronic Signature
                </p>
                <p className="text-xs text-blue-700 dark:text-blue-400 mt-1">
                  By clicking "Sign Order", you are electronically signing this order and
                  authorizing its execution.
                </p>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={isSubmitting}>
            {isSubmitting ? (
              <>
                <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                Signing...
              </>
            ) : (
              <>
                <FileSignature className="h-4 w-4 mr-2" />
                Sign Order
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ============================================================================
// Main Doctor Dashboard Component
// ============================================================================

export default function DoctorDashboard() {
  // Dashboard data hook
  const {
    stats,
    pendingVerifications,
    pendingOrders,
    verificationHistory,
    loading,
    error,
    refetch,
    submitVerification,
    submitSignOrder,
  } = useDoctor();

  // Dialog states
  const [verifyDialog, setVerifyDialog] = useState<VerifyDialogState>({
    open: false,
    testId: '',
    patientName: '',
    testType: '',
  });
  const [signDialog, setSignDialog] = useState<SignDialogState>({
    open: false,
    orderId: '',
    patientName: '',
    orderType: '',
    description: '',
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  // Handle opening verify dialog
  const handleOpenVerifyDialog = useCallback((verification: PendingVerification) => {
    setVerifyDialog({
      open: true,
      testId: verification.id,
      patientName: verification.patientName,
      testType: verification.testType,
    });
    setActionError(null);
  }, []);

  // Handle opening sign dialog
  const handleOpenSignDialog = useCallback((order: PendingOrder) => {
    setSignDialog({
      open: true,
      orderId: order.id,
      patientName: order.patientName,
      orderType: order.orderType,
      description: order.description,
    });
    setActionError(null);
  }, []);

  // Handle view details (placeholder for future implementation)
  const handleViewDetails = useCallback((verification: PendingVerification) => {
    // TODO: Implement detailed view modal for verification
    // This would show test details, chain of custody info, lab results, etc.
  }, []);

  // Handle verification submission
  const handleSubmitVerification = useCallback(
    async (result: VerificationResult, comments: string) => {
      setIsSubmitting(true);
      setActionError(null);
      try {
        await submitVerification(verifyDialog.testId, result, comments);
      } catch (err) {
        setActionError(err instanceof Error ? err.message : 'Failed to submit verification');
        throw err;
      } finally {
        setIsSubmitting(false);
      }
    },
    [submitVerification, verifyDialog.testId]
  );

  // Handle order signing
  const handleSignOrder = useCallback(async () => {
    setIsSubmitting(true);
    setActionError(null);
    try {
      await submitSignOrder(signDialog.orderId);
    } catch (err) {
      setActionError(err instanceof Error ? err.message : 'Failed to sign order');
      throw err;
    } finally {
      setIsSubmitting(false);
    }
  }, [submitSignOrder, signDialog.orderId]);

  // Render error state
  if (error && !loading) {
    return (
      <div className="p-4 sm:p-6">
        <div className="mb-6">
          <h1 className="text-2xl sm:text-3xl font-bold mb-2">Doctor Dashboard</h1>
          <p className="text-sm sm:text-base text-slate-600 dark:text-slate-400">
            MRO Interface for DOT Drug Testing & Order Management
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
            <div className="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <Stethoscope className="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <h1 className="text-2xl sm:text-3xl font-bold">Doctor Dashboard</h1>
          </div>
          <p className="text-sm sm:text-base text-slate-600 dark:text-slate-400">
            MRO Interface for DOT Drug Testing & Order Management
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
          pendingVerifications={stats.pendingVerifications}
          ordersToSign={stats.ordersToSign}
          reviewedToday={stats.reviewedToday}
          avgTurnaroundHours={stats.avgTurnaroundHours}
        />
      ) : null}

      {/* Tabbed Content */}
      <Tabs defaultValue="verifications" className="w-full">
        <TabsList className="grid w-full grid-cols-3">
          <TabsTrigger value="verifications" className="flex items-center gap-2">
            <ClipboardCheck className="h-4 w-4" />
            <span className="hidden sm:inline">Verifications</span>
            {stats && stats.pendingVerifications > 0 && (
              <Badge variant="secondary" className="ml-1">
                {stats.pendingVerifications}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="orders" className="flex items-center gap-2">
            <FileSignature className="h-4 w-4" />
            <span className="hidden sm:inline">Orders</span>
            {stats && stats.ordersToSign > 0 && (
              <Badge variant="secondary" className="ml-1">
                {stats.ordersToSign}
              </Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="history" className="flex items-center gap-2">
            <History className="h-4 w-4" />
            <span className="hidden sm:inline">History</span>
          </TabsTrigger>
        </TabsList>

        {/* Pending Verifications Tab */}
        <TabsContent value="verifications" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ClipboardCheck className="h-5 w-5" />
                Pending DOT Verifications
              </CardTitle>
            </CardHeader>
            <CardContent>
              {loading ? (
                <TableLoadingSkeleton />
              ) : (
                <PendingVerificationsTable
                  verifications={pendingVerifications}
                  onVerify={handleOpenVerifyDialog}
                  onViewDetails={handleViewDetails}
                />
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Pending Orders Tab */}
        <TabsContent value="orders" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <FileSignature className="h-5 w-5" />
                Orders Requiring Signature
              </CardTitle>
            </CardHeader>
            <CardContent>
              {loading ? (
                <TableLoadingSkeleton />
              ) : (
                <PendingOrdersTable orders={pendingOrders} onSign={handleOpenSignDialog} />
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Verification History Tab */}
        <TabsContent value="history" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <History className="h-5 w-5" />
                Verification History
              </CardTitle>
            </CardHeader>
            <CardContent>
              {loading ? (
                <TableLoadingSkeleton />
              ) : (
                <VerificationHistoryTable history={verificationHistory} />
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Verification Dialog */}
      <VerificationDialog
        open={verifyDialog.open}
        onOpenChange={(open) => setVerifyDialog((prev) => ({ ...prev, open }))}
        testId={verifyDialog.testId}
        patientName={verifyDialog.patientName}
        testType={verifyDialog.testType}
        onSubmit={handleSubmitVerification}
        isSubmitting={isSubmitting}
      />

      {/* Sign Order Dialog */}
      <SignOrderDialog
        open={signDialog.open}
        onOpenChange={(open) => setSignDialog((prev) => ({ ...prev, open }))}
        orderId={signDialog.orderId}
        patientName={signDialog.patientName}
        orderType={signDialog.orderType}
        description={signDialog.description}
        onSubmit={handleSignOrder}
        isSubmitting={isSubmitting}
      />
    </div>
  );
}
