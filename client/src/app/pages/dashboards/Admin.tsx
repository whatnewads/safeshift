import { useState, useEffect, useCallback } from 'react';
import * as React from 'react';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../components/ui/tabs';
import {
  useAdminDashboard,
  useComplianceAlerts,
  useTrainingModules,
  useExpiringCredentials,
  useOsha300Log,
  useOsha300ASummary,
  useClearanceStats,
} from '../../hooks/useAdmin.js';
import type { RecentCase, SitePerformance, ProviderPerformance } from '../../services/admin.service.js';
import {
  AlertCircle,
  Clock,
  CheckCircle,
  FileText,
  Shield,
  Bot,
  AlertTriangle,
  Activity,
  Eye,
  GraduationCap,
  Users,
  Filter,
  Download,
  Search,
  CheckCircle2,
  XCircle,
  MoreVertical,
  BarChart3,
  TrendingUp,
  Timer,
  Send,
  Settings,
  Mail,
  Plus,
  ClipboardList,
  FileCheck,
  ShieldCheck,
  Info,
  RefreshCw,
  Archive,
  ExternalLink,
  PenTool,
  ChevronDown,
} from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../../components/ui/table';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../../components/ui/select';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '../../components/ui/dropdown-menu';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '../../components/ui/dialog';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '../../components/ui/tooltip';
import { Label } from '../../components/ui/label';
import { Checkbox } from '../../components/ui/checkbox';
import EmailSettingsModal from '../../components/admin/EmailSettingsModal.js';

// Compliance Notification Types
interface ComplianceNotification {
  id: string;
  authority: 'HHS' | 'OSHA';
  program: string;
  title: string;
  type: 'new requirement' | 'updated requirement' | 'enforcement clarification' | 'guidance';
  status: 'new' | 'acknowledged' | 'archived';
  published_at: string;
  effective_date: string | null;
  first_seen_at: string;
  last_seen_at: string;
  source_url: string;
  content_body?: string;
  internal_notes?: string;
  acknowledged_at?: string;
  acknowledged_by?: string;
  last_reviewed_at?: string;
  last_reviewed_by?: string;
}

export default function AdminDashboard() {
  // API hooks for real data
  const {
    stats: dashboardStats,
    recentCases,
    sitePerformance,
    providerPerformance,
    clearanceStats,
    loading: statsLoading
  } = useAdminDashboard();
  const { modules: apiTrainingModules, loading: trainingLoading } = useTrainingModules();
  const { credentials: apiCredentials, loading: credentialsLoading } = useExpiringCredentials(60);
  const { entries: osha300Entries, statistics: oshaStats, loading: oshaLoading } = useOsha300Log();
  const { summary: osha300ASummary, loading: osha300ALoading } = useOsha300ASummary();

  const [selectedClinic, setSelectedClinic] = useState<string>('all');
  const [selectedProvider, setSelectedProvider] = useState<string>('all');
  const [isAssignTrainingModalOpen, setIsAssignTrainingModalOpen] = useState(false);
  const [selectedTrainingModule, setSelectedTrainingModule] = useState<string>('');
  const [selectedUsers, setSelectedUsers] = useState<string[]>([]);

  // Case Management Actions State
  const [caseActionsOpen, setCaseActionsOpen] = useState(false);
  const [isEmailSettingsOpen, setIsEmailSettingsOpen] = useState(false);

  // Create Training Modal State
  const [isCreateTrainingModalOpen, setIsCreateTrainingModalOpen] = useState(false);
  const [newTrainingModule, setNewTrainingModule] = useState({
    name: '',
    assignedTo: 'all-staff',
    expirationDate: '',
  });

  // Expiring Credentials Filters
  const [credentialSearchTerm, setCredentialSearchTerm] = useState('');
  const [credentialLicenseFilter, setCredentialLicenseFilter] = useState('all');
  const [credentialShiftFilter, setCredentialShiftFilter] = useState('all');

  // Compliance Notification State
  const [complianceNotifications, setComplianceNotifications] = useState<ComplianceNotification[]>([]);
  const [selectedComplianceNotification, setSelectedComplianceNotification] = useState<ComplianceNotification | null>(null);
  const [complianceFilter, setComplianceFilter] = useState({
    authority: 'all',
    type: 'all',
    status: 'all',
    effectiveDate: 'all',
    dateRange: 'all', // all, last-7-days, last-30-days, last-90-days
  });
  const [complianceInternalNotes, setComplianceInternalNotes] = useState<Record<string, string>>({});
  const lastFetchAt = '2024-12-23T08:00:00Z'; // Simulated last refresh timestamp

  // Mock user list for training assignment
  const allUsers = [
    { id: '1', name: 'Dr. Sarah Johnson', role: 'Provider', license: 'MD', shift: 'Day' },
    { id: '2', name: 'Nurse Davis', role: 'Provider', license: 'RN', shift: 'Day' },
    { id: '3', name: 'Dr. Chen', role: 'Provider', license: 'MD', shift: 'Night' },
    { id: '4', name: 'Nurse Smith', role: 'Provider', license: 'RN', shift: 'Night' },
    { id: '5', name: 'Admin User', role: 'Admin', license: 'N/A', shift: 'Day' },
    { id: '6', name: 'Registration Clerk', role: 'Registration', license: 'N/A', shift: 'Swing' },
  ];

  // Case Management Data - use API data if available, otherwise empty
  const cases = recentCases?.map(c => ({
    id: c.id,
    patient: c.patientName,
    type: c.chiefComplaint,
    status: c.status === 'in_progress' ? 'open' : c.status,
    oshaStatus: c.oshaStatus || 'pending',
    days: c.daysOpen || 0,
    provider: c.assignedProvider
  })) || [];

  // Compliance Data - Cached notifications from backend
  const mockComplianceData: ComplianceNotification[] = [
    {
      id: 'COMP-2024-001',
      authority: 'OSHA',
      program: 'OSHA',
      title: 'Updated Recordkeeping Requirements for 2025',
      type: 'updated requirement',
      status: 'new',
      published_at: '2024-12-20T10:00:00Z',
      effective_date: '2025-01-01',
      first_seen_at: '2024-12-23T06:00:00Z',
      last_seen_at: '2024-12-23T06:00:00Z',
      source_url: 'https://www.osha.gov/recordkeeping/updates-2025',
      content_body: 'OSHA has updated Form 300A requirements for annual summary reporting. Key changes include additional data fields for injury classification and enhanced electronic submission requirements for establishments with 250+ employees.',
    },
    {
      id: 'COMP-2024-002',
      authority: 'HHS',
      program: 'HIPAA',
      title: 'HIPAA Privacy Rule Modifications',
      type: 'new requirement',
      status: 'new',
      published_at: '2024-12-18T14:30:00Z',
      effective_date: '2025-02-15',
      first_seen_at: '2024-12-23T06:00:00Z',
      last_seen_at: '2024-12-23T06:00:00Z',
      source_url: 'https://www.hhs.gov/hipaa/privacy-rule-modifications',
      content_body: 'HHS has issued modifications to the HIPAA Privacy Rule concerning reproductive health information. Covered entities must update Notice of Privacy Practices and implement enhanced safeguards for sensitive health information.',
    },
    {
      id: 'COMP-2024-003',
      authority: 'OSHA',
      program: 'OSHA',
      title: 'Respiratory Protection Standard Enforcement Guidance',
      type: 'enforcement clarification',
      status: 'acknowledged',
      published_at: '2024-12-10T09:00:00Z',
      effective_date: null,
      first_seen_at: '2024-12-15T06:00:00Z',
      last_seen_at: '2024-12-23T06:00:00Z',
      source_url: 'https://www.osha.gov/respiratory-protection/enforcement-2024',
      content_body: 'OSHA clarifies enforcement priorities for respiratory protection programs, emphasizing annual fit testing requirements and proper documentation of medical evaluations.',
      acknowledged_at: '2024-12-16T10:30:00Z',
      acknowledged_by: 'Admin User',
      last_reviewed_at: '2024-12-16T10:30:00Z',
      last_reviewed_by: 'Admin User',
    },
    {
      id: 'COMP-2024-004',
      authority: 'HHS',
      program: 'HIPAA',
      title: 'Security Risk Assessment Best Practices',
      type: 'guidance',
      status: 'acknowledged',
      published_at: '2024-12-05T11:00:00Z',
      effective_date: null,
      first_seen_at: '2024-12-10T06:00:00Z',
      last_seen_at: '2024-12-23T06:00:00Z',
      source_url: 'https://www.hhs.gov/hipaa/security-risk-assessment',
      content_body: 'Updated guidance on conducting comprehensive security risk assessments for electronic protected health information (ePHI), including cloud service considerations.',
      acknowledged_at: '2024-12-12T14:00:00Z',
      acknowledged_by: 'Admin User',
      last_reviewed_at: '2024-12-12T14:00:00Z',
      last_reviewed_by: 'Admin User',
    },
    {
      id: 'COMP-2024-005',
      authority: 'OSHA',
      program: 'OSHA',
      title: 'Bloodborne Pathogens Standard Update',
      type: 'updated requirement',
      status: 'archived',
      published_at: '2024-11-28T08:00:00Z',
      effective_date: '2024-12-15',
      first_seen_at: '2024-12-01T06:00:00Z',
      last_seen_at: '2024-12-23T06:00:00Z',
      source_url: 'https://www.osha.gov/bloodborne-pathogens/update-2024',
      content_body: 'Minor updates to bloodborne pathogens exposure control plan requirements, including enhanced documentation for sharps injury log.',
      acknowledged_at: '2024-12-03T09:15:00Z',
      acknowledged_by: 'Admin User',
      last_reviewed_at: '2024-12-03T09:15:00Z',
      last_reviewed_by: 'Admin User',
    },
  ];

  // Initialize compliance notifications from cached data
  // NOTE: In production, replace mockComplianceData with API call to fetch cached compliance notifications
  // Example: fetch('/api/compliance/notifications').then(res => res.json()).then(setComplianceNotifications)
  React.useEffect(() => {
    setComplianceNotifications(mockComplianceData);
  }, []);

  const complianceAlerts = [
    { id: '1', type: 'OSHA Update', title: 'Updated Recordkeeping Requirements', priority: 'high', date: '2 days ago', status: 'pending' },
    { id: '2', type: 'DOT', title: 'Drug Testing Protocol Changes', priority: 'medium', date: '5 days ago', status: 'under-review' },
  ];

  const aiRecommendations = [
    { id: '1', title: 'Update HIPAA training module based on new guidance', confidence: 95, status: 'pending', generatedDate: '12/18/2024' },
    { id: '2', title: 'Revise data retention policy to align with state requirements', confidence: 87, status: 'pending', generatedDate: '12/17/2024' },
    { id: '3', title: 'Add respirator fit test documentation to workflow', confidence: 92, status: 'approved', generatedDate: '12/15/2024' },
  ];

  // Training Data - use API data if available
  const trainingModules = apiTrainingModules?.map(m => ({
    id: m.id,
    title: m.title,
    assignedTo: m.assignedCount || 0,
    completed: m.completedCount || 0,
    inProgress: m.inProgressCount || 0,
    notStarted: m.notStartedCount || 0,
    expiringCount: m.expiringCount || 0
  })) || [];

  // Expiring Credentials - use API data if available
  const expiringCredentials = apiCredentials?.map(c => ({
    id: c.id,
    user: c.userName,
    credential: c.credentialType,
    license: c.licenseType || 'N/A',
    shift: c.shift || 'Day',
    expiryDate: c.expiryDate ? new Date(c.expiryDate).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' }) : '',
    daysUntilExpiry: c.daysUntilExpiry,
    status: c.daysUntilExpiry <= 14 ? 'critical' : 'expiring-soon'
  })) || [];

  // Security & Audit Data
  const recentAudits = [
    { id: '1', user: 'Dr. Johnson', action: 'Viewed Patient Record', resource: 'Patient #1234', time: '2 min ago', flagged: false, ipAddress: '192.168.1.100' },
    { id: '2', user: 'Nurse Davis', action: 'Updated Encounter', resource: 'ENC-2024-1234', time: '15 min ago', flagged: false, ipAddress: '192.168.1.105' },
    { id: '3', user: 'Admin User', action: 'Accessed After Hours', resource: 'System Settings', time: '1 hour ago', flagged: true, ipAddress: '10.0.0.50' },
    { id: '4', user: 'Dr. Chen', action: 'Exported Patient Data', resource: 'Report #567', time: '2 hours ago', flagged: true, ipAddress: '192.168.1.102' },
  ];

  const anomalies = [
    { id: '1', type: 'Unusual Access Time', user: 'Admin User', severity: 'medium', timestamp: '2:34 AM', details: 'System access outside normal hours' },
    { id: '2', type: 'High Volume Access', user: 'Nurse Smith', severity: 'low', timestamp: '11:23 AM', details: '50+ patient records accessed in 1 hour' },
    { id: '3', type: 'Failed Login Attempts', user: 'Unknown', severity: 'high', timestamp: '3:15 AM', details: '8 failed login attempts from 45.67.89.123' },
  ];

  const handleApproveAIRecommendation = (id: string) => {
    // TODO: Implement API call to approve AI recommendation
  };

  const handleRejectAIRecommendation = (id: 'string') => {
    // TODO: Implement API call to reject AI recommendation
  };

  const handleMarkAuditReviewed = (id: string) => {
    // TODO: Implement API call to mark audit log as reviewed
  };

  const handleEscalateAnomaly = (id: string) => {
    // TODO: Implement API call to escalate anomaly to Super Admin
  };

  const handleOpenAssignTrainingModal = (moduleId: string) => {
    setSelectedTrainingModule(moduleId);
    setIsAssignTrainingModalOpen(true);
  };

  const handleCloseAssignTrainingModal = () => {
    setIsAssignTrainingModalOpen(false);
  };

  const handleAssignTraining = () => {
    // TODO: Implement API call to assign training module to users
    handleCloseAssignTrainingModal();
  };

  const handleOpenCreateTrainingModal = () => {
    setIsCreateTrainingModalOpen(true);
  };

  const handleCloseCreateTrainingModal = () => {
    setIsCreateTrainingModalOpen(false);
    setNewTrainingModule({ name: '', assignedTo: 'all-staff', expirationDate: '' });
  };

  const handleCreateTraining = () => {
    // TODO: Implement API call to create new training module
    handleCloseCreateTrainingModal();
  };

  // Compliance Notification Handlers
  const handleAcknowledgeCompliance = (id: string) => {
    setComplianceNotifications(prev =>
      prev.map(notif =>
        notif.id === id
          ? {
              ...notif,
              status: 'acknowledged',
              acknowledged_at: new Date().toISOString(),
              acknowledged_by: 'Admin User',
              last_reviewed_at: new Date().toISOString(),
              last_reviewed_by: 'Admin User',
            }
          : notif
      )
    );
    if (selectedComplianceNotification?.id === id) {
      setSelectedComplianceNotification(prev =>
        prev ? {
          ...prev,
          status: 'acknowledged',
          acknowledged_at: new Date().toISOString(),
          acknowledged_by: 'Admin User',
          last_reviewed_at: new Date().toISOString(),
          last_reviewed_by: 'Admin User',
        } : null
      );
    }
  };

  const handleArchiveCompliance = (id: string) => {
    setComplianceNotifications(prev =>
      prev.map(notif =>
        notif.id === id
          ? {
              ...notif,
              status: 'archived',
              last_reviewed_at: new Date().toISOString(),
              last_reviewed_by: 'Admin User',
            }
          : notif
      )
    );
    if (selectedComplianceNotification?.id === id) {
      setSelectedComplianceNotification(null);
    }
  };

  const handleSaveComplianceNotes = (id: string) => {
    const notes = complianceInternalNotes[id] || '';
    setComplianceNotifications(prev =>
      prev.map(notif =>
        notif.id === id
          ? {
              ...notif,
              internal_notes: notes,
              last_reviewed_at: new Date().toISOString(),
              last_reviewed_by: 'Admin User',
            }
          : notif
      )
    );
    if (selectedComplianceNotification?.id === id) {
      setSelectedComplianceNotification(prev =>
        prev ? { ...prev, internal_notes: notes } : null
      );
    }
  };

  // Filter and sort compliance notifications
  const filteredComplianceNotifications = React.useMemo(() => {
    const now = new Date();
    
    let filtered = complianceNotifications.filter(notif => {
      // Authority filter
      if (complianceFilter.authority !== 'all' && notif.authority !== complianceFilter.authority) {
        return false;
      }
      // Type filter
      if (complianceFilter.type !== 'all' && notif.type !== complianceFilter.type) {
        return false;
      }
      // Status filter
      if (complianceFilter.status !== 'all' && notif.status !== complianceFilter.status) {
        return false;
      }
      // Effective date filter
      if (complianceFilter.effectiveDate === 'with-date' && !notif.effective_date) {
        return false;
      }
      if (complianceFilter.effectiveDate === 'without-date' && notif.effective_date) {
        return false;
      }
      // Date range filter (based on published_at)
      if (complianceFilter.dateRange !== 'all') {
        const publishedDate = new Date(notif.published_at);
        const diffInDays = (now.getTime() - publishedDate.getTime()) / (1000 * 60 * 60 * 24);
        
        if (complianceFilter.dateRange === 'last-7-days' && diffInDays > 7) {
          return false;
        }
        if (complianceFilter.dateRange === 'last-30-days' && diffInDays > 30) {
          return false;
        }
        if (complianceFilter.dateRange === 'last-90-days' && diffInDays > 90) {
          return false;
        }
      }
      return true;
    });

    // Sort by first_seen_at DESC
    filtered.sort((a, b) => new Date(b.first_seen_at).getTime() - new Date(a.first_seen_at).getTime());

    return filtered;
  }, [complianceNotifications, complianceFilter]);

  // Check if notification is new (first_seen_at > last_fetch_at)
  const isNewNotification = (notification: ComplianceNotification) => {
    return new Date(notification.first_seen_at) > new Date(lastFetchAt);
  };

  // Filter expiring credentials
  const filteredCredentials = React.useMemo(() => {
    return expiringCredentials.filter(cred => {
      // Search filter
      if (credentialSearchTerm && !cred.user.toLowerCase().includes(credentialSearchTerm.toLowerCase())) {
        return false;
      }
      // License filter
      if (credentialLicenseFilter !== 'all' && cred.license !== credentialLicenseFilter) {
        return false;
      }
      // Shift filter
      if (credentialShiftFilter !== 'all' && cred.shift !== credentialShiftFilter) {
        return false;
      }
      return true;
    });
  }, [credentialSearchTerm, credentialLicenseFilter, credentialShiftFilter]);

  // Format date for display
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <div className="p-4 sm:p-6 space-y-4 sm:space-y-6">
      {/* Header with Stats */}
      <div className="flex flex-col lg:flex-row lg:items-center gap-4">
        <div className="flex-shrink-0">
          <h1 className="text-2xl sm:text-3xl font-bold mb-2">Admin Dashboard</h1>
          <p className="text-sm sm:text-base text-slate-600 dark:text-slate-400">
            Day-to-day operations, compliance oversight, and internal control
          </p>
        </div>

        {/* Main Stats - Horizontal Scroll with Fade */}
        <div className="relative flex-1 min-w-0">
          <div className="overflow-x-scroll pb-2">
            <div className="flex gap-4">
              <Card className="p-6 w-[220px] flex-shrink-0">
                <div className="flex items-center gap-4">
                  <div className="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                    <FileText className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{statsLoading ? '...' : (dashboardStats?.openCases ?? 0)}</p>
                    <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">Open Cases</p>
                  </div>
                </div>
              </Card>

              <Card className="p-6 w-[220px] flex-shrink-0">
                <div className="flex items-center gap-4">
                  <div className="h-12 w-12 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0">
                    <Clock className="h-6 w-6 text-orange-600 dark:text-orange-400" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{statsLoading ? '...' : (dashboardStats?.followUpsDue ?? 0)}</p>
                    <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">Follow-ups Due</p>
                  </div>
                </div>
              </Card>

              <Card className="p-6 w-[220px] flex-shrink-0">
                <div className="flex items-center gap-4">
                  <div className="h-12 w-12 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                    <AlertCircle className="h-6 w-6 text-red-600 dark:text-red-400" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{statsLoading ? '...' : (dashboardStats?.highRisk ?? 0)}</p>
                    <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">High Risk</p>
                  </div>
                </div>
              </Card>

              <Card className="p-6 w-[220px] flex-shrink-0">
                <div className="flex items-center gap-4">
                  <div className="h-12 w-12 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
                    <Shield className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{statsLoading ? '...' : (dashboardStats?.complianceAlertsCount ?? 0)}%</p>
                    <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">Compliance</p>
                  </div>
                </div>
              </Card>

              <Card className="p-6 w-[220px] flex-shrink-0">
                <div className="flex items-center gap-4">
                  <div className="h-12 w-12 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                    <AlertTriangle className="h-6 w-6 text-amber-600 dark:text-amber-400" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{statsLoading ? '...' : anomalies.length}</p>
                    <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">Anomalies</p>
                  </div>
                </div>
              </Card>

              <Card className="p-6 w-[220px] flex-shrink-0">
                <div className="flex items-center gap-4">
                  <div className="h-12 w-12 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
                    <GraduationCap className="h-6 w-6 text-green-600 dark:text-green-400" />
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{trainingLoading ? '...' : trainingModules.length}</p>
                    <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">Active Training</p>
                  </div>
                </div>
              </Card>
            </div>
          </div>
          {/* Fade out on right - wider to show partial 4th tile */}
          <div className="absolute right-0 top-0 bottom-0 w-32 bg-gradient-to-l from-slate-50 dark:from-slate-900 via-slate-50/50 dark:via-slate-900/50 to-transparent pointer-events-none hidden lg:block" />
        </div>
      </div>

      {/* Tabbed Interface */}
      <Tabs defaultValue="cases" className="w-full">
        <div className="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
          <TabsList className="inline-flex w-full sm:grid sm:grid-cols-5 min-w-max sm:min-w-0">
            <TabsTrigger value="cases" className="whitespace-nowrap">
              <ClipboardList className="h-4 w-4 mr-2" />
              Cases & Ops
            </TabsTrigger>
            <TabsTrigger value="metrics" className="whitespace-nowrap">
              <BarChart3 className="h-4 w-4 mr-2" />
              Metrics
            </TabsTrigger>
            <TabsTrigger value="training" className="whitespace-nowrap">
              <GraduationCap className="h-4 w-4 mr-2" />
              Training
            </TabsTrigger>
            <TabsTrigger value="ai-review" className="whitespace-nowrap">
              <FileCheck className="h-4 w-4 mr-2" />
              OSHA Log
            </TabsTrigger>
            <TabsTrigger value="compliance" className="whitespace-nowrap">
              <ShieldCheck className="h-4 w-4 mr-2" />
              Compliance
            </TabsTrigger>
          </TabsList>
        </div>

        {/* Cases & Operations Tab */}
        <TabsContent value="cases" className="space-y-6">
          {/* Filters */}
          <Card className="p-4">
            <div className="flex items-center gap-4">
              <Filter className="h-5 w-5 text-slate-400" />
              <Select value={selectedClinic} onValueChange={setSelectedClinic}>
                <SelectTrigger className="w-48">
                  <SelectValue placeholder="All Clinics" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Clinics</SelectItem>
                  <SelectItem value="clinic-1">Main Clinic</SelectItem>
                  <SelectItem value="clinic-2">North Branch</SelectItem>
                </SelectContent>
              </Select>

              <Select value={selectedProvider} onValueChange={setSelectedProvider}>
                <SelectTrigger className="w-48">
                  <SelectValue placeholder="All Providers" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Providers</SelectItem>
                  <SelectItem value="dr-johnson">Dr. Johnson</SelectItem>
                  <SelectItem value="dr-chen">Dr. Chen</SelectItem>
                </SelectContent>
              </Select>

              <div className="flex-1" />
              <Button variant="outline" size="sm">
                <Download className="h-4 w-4 mr-2" />
                Export Report
              </Button>
            </div>
          </Card>

          {/* Cases Table */}
          <Card className="p-4 sm:p-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-lg sm:text-xl font-semibold">Case Management</h2>
              <div className="relative">
                <DropdownMenu open={caseActionsOpen} onOpenChange={setCaseActionsOpen}>
                  <DropdownMenuTrigger asChild>
                    <Button
                      variant="outline"
                      size="sm"
                      className="flex items-center gap-2"
                    >
                      Actions
                      <ChevronDown className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuItem
                      onClick={() => {
                        setIsEmailSettingsOpen(true);
                        setCaseActionsOpen(false);
                      }}
                    >
                      <Mail className="h-4 w-4 mr-2" />
                      Email Settings
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
            <div className="overflow-x-auto -mx-4 sm:mx-0">
              <div className="inline-block min-w-full align-middle">
                <div className="overflow-hidden">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Patient</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Provider</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>OSHA Status</TableHead>
                        <TableHead>Days Open</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                {cases.map((case_) => (
                  <TableRow key={case_.id}>
                    <TableCell className="font-medium">{case_.patient}</TableCell>
                    <TableCell>{case_.type}</TableCell>
                    <TableCell>{case_.provider}</TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          case_.status === 'high-risk' ? 'destructive' :
                          case_.status === 'follow-up-due' ? 'default' :
                          'secondary'
                        }
                      >
                        {case_.status}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">{case_.oshaStatus}</Badge>
                    </TableCell>
                    <TableCell>{case_.days} days</TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm">View</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
                  </Table>
                </div>
              </div>
            </div>
          </Card>

          {/* Clearance Status Overview */}
          <div className="grid md:grid-cols-3 gap-6">
            <Card className="p-6">
              <h3 className="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-2">Cleared</h3>
              <p className="text-3xl font-bold text-green-600 dark:text-green-400">
                {statsLoading ? '...' : (clearanceStats?.cleared ?? 0)}
              </p>
              <p className="text-xs text-slate-500 mt-1">Last 30 days</p>
            </Card>
            <Card className="p-6">
              <h3 className="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-2">Not Cleared</h3>
              <p className="text-3xl font-bold text-red-600 dark:text-red-400">
                {statsLoading ? '...' : (clearanceStats?.notCleared ?? 0)}
              </p>
              <p className="text-xs text-slate-500 mt-1">Requires follow-up</p>
            </Card>
            <Card className="p-6">
              <h3 className="text-sm font-semibold text-slate-600 dark:text-slate-400 mb-2">Pending Review</h3>
              <p className="text-3xl font-bold text-orange-600 dark:text-orange-400">
                {statsLoading ? '...' : (clearanceStats?.pendingReview ?? 0)}
              </p>
              <p className="text-xs text-slate-500 mt-1">Awaiting decision</p>
            </Card>
          </div>
        </TabsContent>

        {/* Compliance Tab */}
        <TabsContent value="compliance" className="space-y-0">
          {/* Filters and System Refresh Status - Combined Card */}
          <Card className="p-4 rounded-b-none border-b-0">
            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
              <Filter className="h-5 w-5 text-slate-400" />
              
              <Select value={complianceFilter.authority} onValueChange={(value) => setComplianceFilter(prev => ({ ...prev, authority: value }))}>
                <SelectTrigger className="w-full sm:w-48">
                  <SelectValue placeholder="Authority" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Authorities</SelectItem>
                  <SelectItem value="HHS">HHS</SelectItem>
                  <SelectItem value="OSHA">OSHA</SelectItem>
                </SelectContent>
              </Select>

              <Select value={complianceFilter.type} onValueChange={(value) => setComplianceFilter(prev => ({ ...prev, type: value }))}>
                <SelectTrigger className="w-full sm:w-48">
                  <SelectValue placeholder="Type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Types</SelectItem>
                  <SelectItem value="new requirement">New Requirement</SelectItem>
                  <SelectItem value="updated requirement">Updated Requirement</SelectItem>
                  <SelectItem value="enforcement clarification">Enforcement Clarification</SelectItem>
                  <SelectItem value="guidance">Guidance</SelectItem>
                </SelectContent>
              </Select>

              <Select value={complianceFilter.status} onValueChange={(value) => setComplianceFilter(prev => ({ ...prev, status: value }))}>
                <SelectTrigger className="w-full sm:w-48">
                  <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Statuses</SelectItem>
                  <SelectItem value="new">New</SelectItem>
                  <SelectItem value="acknowledged">Acknowledged</SelectItem>
                  <SelectItem value="archived">Archived</SelectItem>
                </SelectContent>
              </Select>

              <Select value={complianceFilter.effectiveDate} onValueChange={(value) => setComplianceFilter(prev => ({ ...prev, effectiveDate: value }))}>
                <SelectTrigger className="w-full sm:w-48">
                  <SelectValue placeholder="Effective Date" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="with-date">With Effective Date</SelectItem>
                  <SelectItem value="without-date">No Effective Date</SelectItem>
                </SelectContent>
              </Select>

              <Select value={complianceFilter.dateRange} onValueChange={(value) => setComplianceFilter(prev => ({ ...prev, dateRange: value }))}>
                <SelectTrigger className="w-full sm:w-48">
                  <SelectValue placeholder="Date Range" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Time</SelectItem>
                  <SelectItem value="last-7-days">Last 7 Days</SelectItem>
                  <SelectItem value="last-30-days">Last 30 Days</SelectItem>
                  <SelectItem value="last-90-days">Last 90 Days</SelectItem>
                </SelectContent>
              </Select>
            </div>
            
            {/* System Refresh Status - Below Filters */}
            <div className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
              <Info className="h-4 w-4" />
              <span>Compliance data last refreshed at: {formatDateTime(lastFetchAt)}</span>
            </div>
          </Card>

          {/* Compliance Notifications List and Detail Panel */}
          <div className="grid lg:grid-cols-2 gap-0">
            {/* Notifications List */}
            <Card className="p-6 rounded-t-none rounded-r-none border-t-0 lg:border-r-0">
              <h2 className="text-xl font-semibold mb-4">Compliance Notifications</h2>
              <div className="space-y-3 max-h-[600px] overflow-y-auto">
                {filteredComplianceNotifications.length === 0 ? (
                  <p className="text-sm text-slate-500 dark:text-slate-400 text-center py-8">
                    No compliance notifications match the selected filters.
                  </p>
                ) : (
                  filteredComplianceNotifications.map((notification) => (
                    <div
                      key={notification.id}
                      className={`p-4 border rounded-lg cursor-pointer transition-colors ${
                        notification.status === 'new'
                          ? 'border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20'
                          : 'border-slate-200 dark:border-slate-700'
                      } ${
                        selectedComplianceNotification?.id === notification.id
                          ? 'ring-2 ring-blue-500'
                          : 'hover:border-blue-300 dark:hover:border-blue-700'
                      }`}
                      onClick={() => {
                        setSelectedComplianceNotification(notification);
                        setComplianceInternalNotes(prev => ({
                          ...prev,
                          [notification.id]: notification.internal_notes || ''
                        }));
                      }}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 mb-2 flex-wrap">
                            <Badge variant={notification.authority === 'OSHA' ? 'default' : 'secondary'}>
                              {notification.authority}
                            </Badge>
                            <Badge variant="outline">{notification.program}</Badge>
                            {isNewNotification(notification) && (
                              <Badge className="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                New
                              </Badge>
                            )}
                            {notification.status === 'acknowledged' && (
                              <CheckCircle2 className="h-4 w-4 text-green-600" />
                            )}
                            {notification.status === 'archived' && (
                              <Archive className="h-4 w-4 text-slate-400" />
                            )}
                          </div>
                          <p className="font-medium text-sm mb-1">{notification.title}</p>
                          <p className="text-xs text-slate-600 dark:text-slate-400 capitalize">
                            {notification.type}
                          </p>
                          <p className="text-xs text-slate-500 mt-1">
                            Published: {formatDate(notification.published_at)}
                          </p>
                          {notification.effective_date && (
                            <p className="text-xs text-amber-600 dark:text-amber-400 mt-1">
                              Effective: {formatDate(notification.effective_date)}
                            </p>
                          )}
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </Card>

            {/* Detail Panel */}
            <Card className="p-6 rounded-t-none rounded-l-none border-t-0 lg:border-l-0">
              {selectedComplianceNotification ? (
                <div className="space-y-6">
                  <div>
                    <div className="flex items-start justify-between mb-4">
                      <h2 className="text-xl font-semibold flex-1">Notification Details</h2>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelectedComplianceNotification(null)}
                      >
                        <XCircle className="h-4 w-4" />
                      </Button>
                    </div>

                    {/* Notification Header */}
                    <div className="space-y-3 mb-6">
                      <div className="flex items-center gap-2 flex-wrap">
                        <Badge variant={selectedComplianceNotification.authority === 'OSHA' ? 'default' : 'secondary'}>
                          {selectedComplianceNotification.authority}
                        </Badge>
                        <Badge variant="outline">{selectedComplianceNotification.program}</Badge>
                        <Badge variant="outline" className="capitalize">{selectedComplianceNotification.type}</Badge>
                      </div>
                      <h3 className="font-semibold text-lg">{selectedComplianceNotification.title}</h3>
                    </div>

                    {/* Dates */}
                    <div className="space-y-2 mb-6 text-sm">
                      <div className="flex items-center gap-2">
                        <Clock className="h-4 w-4 text-slate-400" />
                        <span className="text-slate-600 dark:text-slate-400">
                          Published: {formatDate(selectedComplianceNotification.published_at)}
                        </span>
                      </div>
                      {selectedComplianceNotification.effective_date && (
                        <div className="flex items-center gap-2">
                          <AlertTriangle className="h-4 w-4 text-amber-600" />
                          <span className="text-amber-600 dark:text-amber-400">
                            Effective: {formatDate(selectedComplianceNotification.effective_date)}
                          </span>
                        </div>
                      )}
                      <div className="flex items-center gap-2">
                        <a
                          href={selectedComplianceNotification.source_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1"
                        >
                          <ExternalLink className="h-4 w-4" />
                          View Source Document
                        </a>
                      </div>
                    </div>

                    {/* Content Body */}
                    <div className="mb-6">
                      <Label className="text-sm font-semibold mb-2 block">Content</Label>
                      <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg text-sm">
                        {selectedComplianceNotification.content_body || 'No content available.'}
                      </div>
                    </div>

                    {/* Internal Notes */}
                    <div className="mb-6">
                      <Label className="text-sm font-semibold mb-2 block">Internal Notes</Label>
                      <Input
                        value={complianceInternalNotes[selectedComplianceNotification.id] || ''}
                        onChange={(e) => setComplianceInternalNotes(prev => ({
                          ...prev,
                          [selectedComplianceNotification.id]: e.target.value
                        }))}
                        placeholder="Add notes for internal review..."
                        className="mb-2"
                      />
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => handleSaveComplianceNotes(selectedComplianceNotification.id)}
                      >
                        Save Notes
                      </Button>
                    </div>

                    {/* Audit Metadata */}
                    <div className="mb-6 p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                      <Label className="text-sm font-semibold mb-2 block">Audit Trail</Label>
                      <div className="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <div className="flex items-center gap-2">
                          <Eye className="h-4 w-4" />
                          <span>First detected by system: {formatDateTime(selectedComplianceNotification.first_seen_at)}</span>
                        </div>
                        {selectedComplianceNotification.last_reviewed_at && (
                          <div className="flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4" />
                            <span>
                              Last reviewed by {selectedComplianceNotification.last_reviewed_by}: {formatDateTime(selectedComplianceNotification.last_reviewed_at)}
                            </span>
                          </div>
                        )}
                        {selectedComplianceNotification.acknowledged_at && (
                          <div className="flex items-center gap-2">
                            <CheckCircle className="h-4 w-4 text-green-600" />
                            <span>
                              Acknowledged by {selectedComplianceNotification.acknowledged_by}: {formatDateTime(selectedComplianceNotification.acknowledged_at)}
                            </span>
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex gap-3">
                      {selectedComplianceNotification.status === 'new' && (
                        <Button
                          onClick={() => handleAcknowledgeCompliance(selectedComplianceNotification.id)}
                        >
                          <CheckCircle2 className="h-4 w-4 mr-2" />
                          Acknowledge
                        </Button>
                      )}
                      {selectedComplianceNotification.status !== 'archived' && (
                        <Button
                          variant="outline"
                          onClick={() => handleArchiveCompliance(selectedComplianceNotification.id)}
                        >
                          <Archive className="h-4 w-4 mr-2" />
                          Archive
                        </Button>
                      )}
                    </div>
                  </div>
                </div>
              ) : (
                <div className="flex flex-col items-center justify-center py-12 text-center">
                  <Shield className="h-12 w-12 text-slate-300 dark:text-slate-600 mb-4" />
                  <p className="text-slate-600 dark:text-slate-400">
                    Select a compliance notification to view details
                  </p>
                </div>
              )}
            </Card>
          </div>
        </TabsContent>

        {/* OSHA Log Tab */}
        <TabsContent value="ai-review" className="space-y-6">
          {/* OSHA 300 Log */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold">OSHA 300 Log</h2>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm">
                    <Download className="h-4 w-4 mr-2" />
                    Actions
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem>
                    <Download className="h-4 w-4 mr-2" />
                    Export as Excel
                  </DropdownMenuItem>
                  <DropdownMenuItem>
                    <Download className="h-4 w-4 mr-2" />
                    Export as PDF
                  </DropdownMenuItem>
                  <DropdownMenuItem>
                    <Send className="h-4 w-4 mr-2" />
                    Send to OSHA
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <div className="overflow-x-auto">
              <TooltipProvider>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Case No.</TableHead>
                      <TableHead>Employee Name</TableHead>
                      <TableHead>Job Title</TableHead>
                      <TableHead>Date of Injury</TableHead>
                      <TableHead>Where Event Occurred</TableHead>
                      <TableHead>Describe Injury/Illness</TableHead>
                      <TableHead>Days Away</TableHead>
                      <TableHead>Job Transfer/Restriction</TableHead>
                      <TableHead>
                        <div className="flex items-center gap-1">
                          ITA
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <Info className="h-3 w-3 text-slate-400 cursor-help" />
                            </TooltipTrigger>
                            <TooltipContent className="text-white">
                              <p>Tracking online 301 submissions to OSHA</p>
                            </TooltipContent>
                          </Tooltip>
                        </div>
                      </TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow>
                      <TableCell className="font-medium">2024-001</TableCell>
                      <TableCell>John Smith</TableCell>
                      <TableCell>Warehouse Worker</TableCell>
                      <TableCell>03/15/2024</TableCell>
                      <TableCell>Loading Dock</TableCell>
                      <TableCell>Laceration to left hand from box cutter</TableCell>
                      <TableCell>0</TableCell>
                      <TableCell>3</TableCell>
                      <TableCell>
                        <Badge variant="default" className="bg-green-600 dark:bg-green-600">Success</Badge>
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem>
                              <Eye className="h-4 w-4 mr-2" />
                              View 301 Form
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                              <RefreshCw className="h-4 w-4 mr-2" />
                              Send to OSHA Again
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-medium">2024-002</TableCell>
                      <TableCell>Maria Garcia</TableCell>
                      <TableCell>Machine Operator</TableCell>
                      <TableCell>05/22/2024</TableCell>
                      <TableCell>Production Floor</TableCell>
                      <TableCell>Strain to lower back while lifting materials</TableCell>
                      <TableCell>5</TableCell>
                      <TableCell>10</TableCell>
                      <TableCell>
                        <Badge variant="default" className="bg-green-600 dark:bg-green-600">Success</Badge>
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem>
                              <Eye className="h-4 w-4 mr-2" />
                              View 301 Form
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                              <RefreshCw className="h-4 w-4 mr-2" />
                              Send to OSHA Again
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-medium">2024-003</TableCell>
                      <TableCell>Robert Chen</TableCell>
                      <TableCell>Forklift Operator</TableCell>
                      <TableCell>08/10/2024</TableCell>
                      <TableCell>Warehouse</TableCell>
                      <TableCell>Contusion to right knee from falling pallet</TableCell>
                      <TableCell>0</TableCell>
                      <TableCell>0</TableCell>
                      <TableCell>
                        <Badge variant="outline">Not Sent</Badge>
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem>
                              <Eye className="h-4 w-4 mr-2" />
                              View 301 Form
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                              <RefreshCw className="h-4 w-4 mr-2" />
                              Send to OSHA Again
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-medium">2024-004</TableCell>
                      <TableCell>Sarah Johnson</TableCell>
                      <TableCell>Assembly Technician</TableCell>
                      <TableCell>10/05/2024</TableCell>
                      <TableCell>Assembly Line 2</TableCell>
                      <TableCell>Repetitive strain injury - right wrist</TableCell>
                      <TableCell>0</TableCell>
                      <TableCell>14</TableCell>
                      <TableCell>
                        <Badge variant="destructive">Unsuccessful</Badge>
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem>
                              <Eye className="h-4 w-4 mr-2" />
                              View 301 Form
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                              <RefreshCw className="h-4 w-4 mr-2" />
                              Send to OSHA Again
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-medium">2024-005</TableCell>
                      <TableCell>David Martinez</TableCell>
                      <TableCell>Maintenance Tech</TableCell>
                      <TableCell>11/18/2024</TableCell>
                      <TableCell>Equipment Room</TableCell>
                      <TableCell>Chemical exposure - eye irritation</TableCell>
                      <TableCell>1</TableCell>
                      <TableCell>0</TableCell>
                      <TableCell>
                        <Badge variant="default" className="bg-green-600 dark:bg-green-600">Success</Badge>
                      </TableCell>
                      <TableCell>
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem>
                              <Eye className="h-4 w-4 mr-2" />
                              View 301 Form
                            </DropdownMenuItem>
                            <DropdownMenuItem>
                              <RefreshCw className="h-4 w-4 mr-2" />
                              Send to OSHA Again
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </TooltipProvider>
            </div>
            <div className="mt-4 p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
              <div className="grid md:grid-cols-4 gap-4">
                <div>
                  <p className="text-xs text-slate-600 dark:text-slate-400">Total Recordable Cases</p>
                  <p className="text-2xl font-bold">7</p>
                </div>
                <div>
                  <p className="text-xs text-slate-600 dark:text-slate-400">Days Away Cases</p>
                  <p className="text-2xl font-bold">2</p>
                </div>
                <div>
                  <p className="text-xs text-slate-600 dark:text-slate-400">Job Transfer/Restriction</p>
                  <p className="text-2xl font-bold">4</p>
                </div>
                <div>
                  <p className="text-xs text-slate-600 dark:text-slate-400">Other Recordable Cases</p>
                  <p className="text-2xl font-bold">1</p>
                </div>
              </div>
            </div>
          </Card>

          {/* OSHA 300A Summary */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold">OSHA 300A Summary of Work-Related Injuries and Illnesses</h2>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm">
                    <Download className="h-4 w-4 mr-2" />
                    Actions
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem>
                    <Download className="h-4 w-4 mr-2" />
                    Export as PDF
                  </DropdownMenuItem>
                  <DropdownMenuItem>
                    <Download className="h-4 w-4 mr-2" />
                    Print for Posting
                  </DropdownMenuItem>
                  <DropdownMenuItem>
                    <Eye className="h-4 w-4 mr-2" />
                    View Past 300A Documents
                  </DropdownMenuItem>
                  <DropdownMenuItem>
                    <Send className="h-4 w-4 mr-2" />
                    Submit to OSHA
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            
            {/* Summary Stats Grid */}
            <div className="grid md:grid-cols-3 gap-6 mb-6">
              <Card className="p-4 bg-blue-50 dark:bg-blue-900/20">
                <h3 className="text-sm font-semibold text-blue-900 dark:text-blue-300 mb-3">Total Number of Cases</h3>
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Total Deaths (Col G)</p>
                    <p className="text-xl font-bold">0</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Total Cases (Col H)</p>
                    <p className="text-xl font-bold">7</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Days Away from Work (Col I)</p>
                    <p className="text-xl font-bold">2</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Job Transfer/Restriction (Col J)</p>
                    <p className="text-xl font-bold">4</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Other Recordable Cases (Col K)</p>
                    <p className="text-xl font-bold">1</p>
                  </div>
                </div>
              </Card>

              <Card className="p-4 bg-purple-50 dark:bg-purple-900/20">
                <h3 className="text-sm font-semibold text-purple-900 dark:text-purple-300 mb-3">Number of Days</h3>
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Days Away from Work (Col L)</p>
                    <p className="text-xl font-bold">6</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Days of Job Transfer/Restriction (Col M)</p>
                    <p className="text-xl font-bold">27</p>
                  </div>
                </div>
              </Card>

              <Card className="p-4 bg-green-50 dark:bg-green-900/20">
                <h3 className="text-sm font-semibold text-green-900 dark:text-green-300 mb-3">Injury & Illness Types (Col N)</h3>
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Injury Cases</p>
                    <p className="text-xl font-bold">6</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Skin Disorders</p>
                    <p className="text-xl font-bold">0</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Respiratory Conditions</p>
                    <p className="text-xl font-bold">1</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Poisonings</p>
                    <p className="text-xl font-bold">0</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">Hearing Loss</p>
                    <p className="text-xl font-bold">0</p>
                  </div>
                  <div className="flex items-center justify-between">
                    <p className="text-sm text-slate-600 dark:text-slate-400">All Other Illnesses</p>
                    <p className="text-xl font-bold">0</p>
                  </div>
                </div>
              </Card>
            </div>

            {/* Establishment Information */}
            <div className="border-t border-slate-200 dark:border-slate-700 pt-6">
              <h3 className="text-base font-semibold mb-4">Establishment Information</h3>
              <div className="grid md:grid-cols-2 gap-6">
                <div>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-1">Establishment Name</p>
                  <p className="font-medium">Industrial Health Services LLC</p>
                </div>
                <div>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-1">City, State</p>
                  <p className="font-medium">Chicago, IL</p>
                </div>
                <div>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-1">Industry Description</p>
                  <p className="font-medium">Occupational Health Services</p>
                </div>
                <div>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-1">NAICS Code</p>
                  <p className="font-medium">621111</p>
                </div>
                <div>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-1">Annual Average Number of Employees</p>
                  <p className="font-medium">145</p>
                </div>
                <div>
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-1">Total Hours Worked by All Employees</p>
                  <p className="font-medium">301,600</p>
                </div>
              </div>
            </div>

            {/* Injury & Illness Incidence Rates */}
            <div className="border-t border-slate-200 dark:border-slate-700 pt-6 mt-6">
              <h3 className="text-base font-semibold mb-4">Injury & Illness Incidence Rates</h3>
              <div className="grid md:grid-cols-3 gap-4">
                <Card className="p-4 bg-slate-50 dark:bg-slate-900/50">
                  <p className="text-xs text-slate-600 dark:text-slate-400 mb-1">Total Case Incidence Rate</p>
                  <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">4.6</p>
                  <p className="text-xs text-slate-500 mt-1">per 100 FTE</p>
                </Card>
                <Card className="p-4 bg-slate-50 dark:bg-slate-900/50">
                  <p className="text-xs text-slate-600 dark:text-slate-400 mb-1">DART Rate</p>
                  <p className="text-2xl font-bold text-orange-600 dark:text-orange-400">4.0</p>
                  <p className="text-xs text-slate-500 mt-1">Days Away, Restricted, or Transferred</p>
                </Card>
                <Card className="p-4 bg-slate-50 dark:bg-slate-900/50">
                  <p className="text-xs text-slate-600 dark:text-slate-400 mb-1">DAFWII Rate</p>
                  <p className="text-2xl font-bold text-red-600 dark:text-red-400">1.3</p>
                  <p className="text-xs text-slate-500 mt-1">Days Away from Work Injury/Illness</p>
                </Card>
              </div>
            </div>

            {/* Posting Notice */}
            <div className="mt-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
              <div className="flex items-start gap-2">
                <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-amber-900 dark:text-amber-300">
                    Posting Requirement
                  </p>
                  <p className="text-xs text-amber-700 dark:text-amber-400 mt-1">
                    This summary must be posted in a conspicuous place where notices to employees are customarily posted. 
                    The summary must be posted no later than February 1 and must remain posted until April 30.
                  </p>
                </div>
              </div>
            </div>
          </Card>
        </TabsContent>

        {/* Training Tab */}
        <TabsContent value="training" className="space-y-6">
          {/* Training Modules */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold">Training Modules</h2>
              <Button size="sm" onClick={handleOpenCreateTrainingModal}>
                <GraduationCap className="h-4 w-4 mr-2" />
                Create Training
              </Button>
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Module Name</TableHead>
                  <TableHead>Assigned To</TableHead>
                  <TableHead>Completed</TableHead>
                  <TableHead>In Progress</TableHead>
                  <TableHead>Not Started</TableHead>
                  <TableHead>Expiring Soon</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {trainingModules.map((module) => (
                  <TableRow key={module.id}>
                    <TableCell className="font-medium">{module.title}</TableCell>
                    <TableCell>{module.assignedTo}</TableCell>
                    <TableCell>
                      <Badge variant="default">{module.completed}</Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">{module.inProgress}</Badge>
                    </TableCell>
                    <TableCell>
                      {module.notStarted > 0 && (
                        <Badge variant="destructive">{module.notStarted}</Badge>
                      )}
                    </TableCell>
                    <TableCell>
                      {module.expiringCount > 0 && (
                        <Badge variant="secondary" className="bg-amber-100 dark:bg-amber-900/30">
                          {module.expiringCount}
                        </Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem>
                            <PenTool className="h-4 w-4 mr-2" />
                            Design Training
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => handleOpenAssignTrainingModal(module.id)}>
                            <Users className="h-4 w-4 mr-2" />
                            Assign Training
                          </DropdownMenuItem>
                          <DropdownMenuItem>
                            <Eye className="h-4 w-4 mr-2" />
                            View Details
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Card>

          {/* Expiring Credentials */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <AlertCircle className="h-5 w-5 text-orange-600 dark:text-orange-400" />
              <h2 className="text-xl font-semibold">Expiring Credentials</h2>
            </div>

            {/* Search and Filters */}
            <div className="flex flex-col sm:flex-row gap-3 mb-4">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                <Input
                  placeholder="Search by name..."
                  value={credentialSearchTerm}
                  onChange={(e) => setCredentialSearchTerm(e.target.value)}
                  className="pl-9"
                />
              </div>
              <Select value={credentialLicenseFilter} onValueChange={setCredentialLicenseFilter}>
                <SelectTrigger className="w-full sm:w-40">
                  <SelectValue placeholder="License" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Licenses</SelectItem>
                  <SelectItem value="MD">MD</SelectItem>
                  <SelectItem value="RN">RN</SelectItem>
                  <SelectItem value="N/A">N/A</SelectItem>
                </SelectContent>
              </Select>
              <Select value={credentialShiftFilter} onValueChange={setCredentialShiftFilter}>
                <SelectTrigger className="w-full sm:w-40">
                  <SelectValue placeholder="Shift" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Shifts</SelectItem>
                  <SelectItem value="Day">Day</SelectItem>
                  <SelectItem value="Night">Night</SelectItem>
                  <SelectItem value="Swing">Swing</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-3">
              {filteredCredentials.map((cred) => (
                <div 
                  key={cred.id} 
                  className={`p-4 border rounded-lg ${
                    cred.status === 'critical' 
                      ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-900/20' 
                      : 'border-orange-300 bg-orange-50 dark:border-orange-800 dark:bg-orange-900/20'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-medium">{cred.user}</p>
                      <p className="text-sm text-slate-600 dark:text-slate-400">{cred.credential}</p>
                      <p className="text-xs text-slate-500 mt-1">
                        Expires: {cred.expiryDate} ({cred.daysUntilExpiry} days)
                      </p>
                    </div>
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline">Notify User</Button>
                      <Button size="sm" variant="destructive">Restrict Access</Button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </Card>

          {/* Assign Training Modal */}
          <Dialog open={isAssignTrainingModalOpen} onOpenChange={setIsAssignTrainingModalOpen}>
            <DialogContent className="sm:max-w-[425px]">
              <DialogHeader>
                <DialogTitle>Assign Training Module</DialogTitle>
                <DialogDescription>
                  Select users to assign the training module: <strong>{selectedTrainingModule}</strong>
                </DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                {allUsers.map((user) => (
                  <div key={user.id} className="flex items-center">
                    <Checkbox
                      id={user.id}
                      value={user.id}
                      checked={selectedUsers.includes(user.id)}
                      onCheckedChange={(checked) => {
                        if (checked) {
                          setSelectedUsers([...selectedUsers, user.id]);
                        } else {
                          setSelectedUsers(selectedUsers.filter((u) => u !== user.id));
                        }
                      }}
                    />
                    <Label
                      htmlFor={user.id}
                      className="ml-2 text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                    >
                      {user.name} ({user.role})
                    </Label>
                  </div>
                ))}
              </div>
              <DialogHeader>
                <DialogTitle>Selected Users</DialogTitle>
                <DialogDescription>
                  {selectedUsers.length > 0 ? (
                    <ul className="list-disc list-inside">
                      {selectedUsers.map((userId) => (
                        <li key={userId}>{allUsers.find((u) => u.id === userId)?.name}</li>
                      ))}
                    </ul>
                  ) : (
                    <p className="text-sm text-slate-500">No users selected</p>
                  )}
                </DialogDescription>
              </DialogHeader>
              <div className="flex justify-end gap-2">
                <Button size="sm" variant="outline" onClick={handleCloseAssignTrainingModal}>
                  Cancel
                </Button>
                <Button size="sm" onClick={handleAssignTraining}>
                  Assign Training
                </Button>
              </div>
            </DialogContent>
          </Dialog>

          {/* Create Training Modal */}
          <Dialog open={isCreateTrainingModalOpen} onOpenChange={setIsCreateTrainingModalOpen}>
            <DialogContent className="sm:max-w-[500px]">
              <DialogHeader>
                <DialogTitle>Create Training Module</DialogTitle>
                <DialogDescription>
                  Create a new training module and assign it to staff members.
                </DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label htmlFor="module-name" className="mb-1.5 block">Module Name</Label>
                  <Input
                    id="module-name"
                    placeholder="e.g., HIPAA Privacy & Security"
                    value={newTrainingModule.name}
                    onChange={(e) => setNewTrainingModule({ ...newTrainingModule, name: e.target.value })}
                  />
                </div>

                <div>
                  <Label htmlFor="assigned-to" className="mb-1.5 block">Assign To</Label>
                  <Select
                    value={newTrainingModule.assignedTo}
                    onValueChange={(value) => setNewTrainingModule({ ...newTrainingModule, assignedTo: value })}
                  >
                    <SelectTrigger id="assigned-to">
                      <SelectValue placeholder="Select assignment" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all-staff">All Staff</SelectItem>
                      <SelectItem value="providers-only">Providers Only</SelectItem>
                      <SelectItem value="clinical-staff">Clinical Staff</SelectItem>
                      <SelectItem value="administrative">Administrative Staff</SelectItem>
                      <SelectItem value="specific-users">Specific Users (Select Later)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div>
                  <Label htmlFor="expiration-date" className="mb-1.5 block">Completion Due Date</Label>
                  <Input
                    id="expiration-date"
                    type="date"
                    value={newTrainingModule.expirationDate}
                    onChange={(e) => setNewTrainingModule({ ...newTrainingModule, expirationDate: e.target.value })}
                  />
                  <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Staff must complete this training by this date.
                  </p>
                </div>
              </div>
              <div className="flex justify-end gap-2 mt-4">
                <Button variant="outline" onClick={handleCloseCreateTrainingModal}>
                  Cancel
                </Button>
                <Button onClick={handleCreateTraining} disabled={!newTrainingModule.name}>
                  <Plus className="h-4 w-4 mr-2" />
                  Create Module
                </Button>
              </div>
            </DialogContent>
          </Dialog>
        </TabsContent>

        {/* Metrics Tab */}
        <TabsContent value="metrics" className="space-y-6">
          {/* Patient Flow Metrics */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <BarChart3 className="h-6 w-6 text-blue-600 dark:text-blue-400" />
              <h2 className="text-xl font-semibold">Patient Flow & Treatment Times</h2>
            </div>
            <div className="grid md:grid-cols-4 gap-4 mb-6">
              <Card className="p-4 bg-slate-50 dark:bg-slate-900/50">
                <p className="text-xs text-slate-600 dark:text-slate-400">Avg Time to Start Encounter</p>
                <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">8.5 min</p>
                <p className="text-xs text-green-600 dark:text-green-400 mt-1">↓ 12% from last month</p>
              </Card>
              <Card className="p-4 bg-slate-50 dark:bg-slate-900/50">
                <p className="text-xs text-slate-600 dark:text-slate-400">Avg Treatment Duration</p>
                <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">32 min</p>
                <p className="text-xs text-slate-500 mt-1">Steady from last month</p>
              </Card>
              <Card className="p-4 bg-slate-50 dark:bg-slate-900/50">
                <p className="text-xs text-slate-600 dark:text-slate-400">Avg Time to Complete Report</p>
                <p className="text-2xl font-bold text-amber-600 dark:text-amber-400">18 min</p>
                <p className="text-xs text-red-600 dark:text-red-400 mt-1">↑ 5% from last month</p>
              </Card>
              <Card className="p-4 bg-slate-50 dark:bg-slate-900/50">
                <p className="text-xs text-slate-600 dark:text-slate-400">Total Encounters Today</p>
                <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">24</p>
                <p className="text-xs text-slate-500 mt-1">12 completed, 12 in progress</p>
              </Card>
            </div>
            
            {/* By Site */}
            <h3 className="text-base font-semibold mb-3">Performance by Site</h3>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Site</TableHead>
                  <TableHead>Avg Wait Time</TableHead>
                  <TableHead>Avg Encounter Time</TableHead>
                  <TableHead>Patient Satisfaction</TableHead>
                  <TableHead>Encounters Today</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {statsLoading ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-slate-500">
                      Loading site performance data...
                    </TableCell>
                  </TableRow>
                ) : sitePerformance.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-slate-500">
                      No site performance data available
                    </TableCell>
                  </TableRow>
                ) : (
                  sitePerformance.map((site) => (
                    <TableRow key={site.siteId}>
                      <TableCell className="font-medium">{site.siteName}</TableCell>
                      <TableCell>{site.avgWaitTime} min</TableCell>
                      <TableCell>{site.avgTreatmentTime} min</TableCell>
                      <TableCell>{site.patientSatisfaction?.toFixed(1) ?? 'N/A'}</TableCell>
                      <TableCell>{site.encountersToday}</TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </Card>

          {/* Provider Performance */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <Users className="h-6 w-6 text-purple-600 dark:text-purple-400" />
              <h2 className="text-xl font-semibold">Provider Performance Metrics</h2>
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Provider</TableHead>
                  <TableHead>Encounters Today</TableHead>
                  <TableHead>Avg Encounter Time</TableHead>
                  <TableHead>QA Score</TableHead>
                  <TableHead>Completion Rate</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {statsLoading ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-slate-500">
                      Loading provider performance data...
                    </TableCell>
                  </TableRow>
                ) : providerPerformance.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="text-center py-8 text-slate-500">
                      No provider performance data available
                    </TableCell>
                  </TableRow>
                ) : (
                  providerPerformance.map((provider) => (
                    <TableRow key={provider.providerId}>
                      <TableCell className="font-medium">{provider.providerName}</TableCell>
                      <TableCell>{provider.encountersToday}</TableCell>
                      <TableCell>{provider.avgEncounterTime} min</TableCell>
                      <TableCell>
                        <Badge variant={provider.qaScore >= 95 ? 'default' : provider.qaScore >= 80 ? 'secondary' : 'destructive'}>
                          {provider.qaScore?.toFixed(1) ?? 'N/A'}%
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant="default">{provider.completionRate?.toFixed(0) ?? 'N/A'}%</Badge>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </Card>

          {/* MOI/NOI Analysis */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <TrendingUp className="h-6 w-6 text-green-600 dark:text-green-400" />
              <h2 className="text-xl font-semibold">MOI/NOI Trends</h2>
            </div>
            <div className="grid md:grid-cols-2 gap-6">
              {/* By Site */}
              <div>
                <h3 className="text-sm font-semibold mb-3">Top Injuries by Site (Last 30 Days)</h3>
                <div className="space-y-2">
                  <div className="p-3 border border-slate-200 dark:border-slate-700 rounded-lg">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium">Main Clinic</p>
                        <p className="text-sm text-slate-600 dark:text-slate-400">Laceration/Cut (18 cases)</p>
                      </div>
                      <Badge variant="outline">42%</Badge>
                    </div>
                  </div>
                  <div className="p-3 border border-slate-200 dark:border-slate-700 rounded-lg">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium">North Branch</p>
                        <p className="text-sm text-slate-600 dark:text-slate-400">Strain/Sprain (12 cases)</p>
                      </div>
                      <Badge variant="outline">38%</Badge>
                    </div>
                  </div>
                </div>
              </div>

              {/* By Provider */}
              <div>
                <h3 className="text-sm font-semibold mb-3">Top Injuries by Provider (Last 30 Days)</h3>
                <div className="space-y-2">
                  <div className="p-3 border border-slate-200 dark:border-slate-700 rounded-lg">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium">Dr. Johnson</p>
                        <p className="text-sm text-slate-600 dark:text-slate-400">Contusion/Bruise (15 cases)</p>
                      </div>
                      <Badge variant="outline">35%</Badge>
                    </div>
                  </div>
                  <div className="p-3 border border-slate-200 dark:border-slate-700 rounded-lg">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="font-medium">Dr. Chen</p>
                        <p className="text-sm text-slate-600 dark:text-slate-400">Laceration/Cut (11 cases)</p>
                      </div>
                      <Badge variant="outline">32%</Badge>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Card>

          {/* Section Completion Times */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <Timer className="h-6 w-6 text-amber-600 dark:text-amber-400" />
              <h2 className="text-xl font-semibold">Encounter Sections - Avg Completion Time</h2>
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Section</TableHead>
                  <TableHead>Avg Time</TableHead>
                  <TableHead>Fastest Provider</TableHead>
                  <TableHead>Slowest Provider</TableHead>
                  <TableHead>Recommendation</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow>
                  <TableCell className="font-medium">Incident Details</TableCell>
                  <TableCell>2.8 min</TableCell>
                  <TableCell>Dr. Johnson (2.1 min)</TableCell>
                  <TableCell>Dr. Chen (3.5 min)</TableCell>
                  <TableCell className="text-xs text-slate-600 dark:text-slate-400">Consider pre-populated templates</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell className="font-medium">Vitals</TableCell>
                  <TableCell>1.9 min</TableCell>
                  <TableCell>Nurse Davis (1.2 min)</TableCell>
                  <TableCell>Nurse Smith (2.8 min)</TableCell>
                  <TableCell className="text-xs text-slate-600 dark:text-slate-400">Optimal</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell className="font-medium">Assessment</TableCell>
                  <TableCell>5.2 min</TableCell>
                  <TableCell>Dr. Johnson (3.8 min)</TableCell>
                  <TableCell>Dr. Chen (6.9 min)</TableCell>
                  <TableCell className="text-xs text-slate-600 dark:text-slate-400">Review quick-entry options</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell className="font-medium">Disposition</TableCell>
                  <TableCell>3.6 min</TableCell>
                  <TableCell>Dr. Chen (2.9 min)</TableCell>
                  <TableCell>Dr. Johnson (4.5 min)</TableCell>
                  <TableCell className="text-xs text-slate-600 dark:text-slate-400">Optimal</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell className="font-medium">Narrative</TableCell>
                  <TableCell>4.2 min</TableCell>
                  <TableCell>Dr. Johnson (2.8 min)</TableCell>
                  <TableCell>Nurse Davis (6.1 min)</TableCell>
                  <TableCell className="text-xs text-slate-600 dark:text-slate-400">AI generation available</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </Card>
        </TabsContent>

        {/* Security & Audit Tab */}
        <TabsContent value="security" className="space-y-6">
          {/* Security Anomalies */}
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-orange-600 dark:text-orange-400" />
              Security Anomalies
            </h2>
            <div className="space-y-3">
              {anomalies.map((anomaly) => (
                <div 
                  key={anomaly.id} 
                  className="p-4 border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20 rounded-lg flex items-center justify-between"
                >
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <p className="font-medium">{anomaly.type}</p>
                      <Badge variant={
                        anomaly.severity === 'high' ? 'destructive' :
                        anomaly.severity === 'medium' ? 'default' :
                        'outline'
                      }>
                        {anomaly.severity}
                      </Badge>
                    </div>
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                      User: {anomaly.user} • {anomaly.timestamp}
                    </p>
                    <p className="text-xs text-slate-500 mt-1">{anomaly.details}</p>
                  </div>
                  <div className="flex items-center gap-2">
                    <Button size="sm" onClick={() => handleMarkAuditReviewed(anomaly.id)}>
                      Mark Reviewed
                    </Button>
                    <Button 
                      size="sm" 
                      variant="destructive"
                      onClick={() => handleEscalateAnomaly(anomaly.id)}
                    >
                      Escalate to Super Admin
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          </Card>

          {/* Recent Audit Logs */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold">Recent Audit Logs</h2>
              <div className="flex gap-2">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-slate-400" />
                  <Input placeholder="Search logs..." className="pl-9 w-64" />
                </div>
                <Button variant="outline" size="sm">
                  <Download className="h-4 w-4 mr-2" />
                  Export (Admin View Only)
                </Button>
              </div>
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>User</TableHead>
                  <TableHead>Action</TableHead>
                  <TableHead>Resource</TableHead>
                  <TableHead>Time</TableHead>
                  <TableHead>IP Address</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {recentAudits.map((audit) => (
                  <TableRow 
                    key={audit.id} 
                    className={audit.flagged ? 'bg-red-50 dark:bg-red-900/10' : ''}
                  >
                    <TableCell className="font-medium">{audit.user}</TableCell>
                    <TableCell>{audit.action}</TableCell>
                    <TableCell>{audit.resource}</TableCell>
                    <TableCell>{audit.time}</TableCell>
                    <TableCell className="font-mono text-xs">{audit.ipAddress}</TableCell>
                    <TableCell>
                      {audit.flagged ? (
                        <Badge variant="destructive">Flagged</Badge>
                      ) : (
                        <Badge variant="outline">Normal</Badge>
                      )}
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm">View Details</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Card>

          {/* Admin Limitations Note */}
          <Card className="p-4 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800">
            <div className="flex items-start gap-2">
              <Shield className="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-sm font-medium text-amber-900 dark:text-amber-300">
                  Admin Audit Limitations
                </p>
                <p className="text-xs text-amber-700 dark:text-amber-400 mt-1">
                  You can view and mark audit logs as reviewed. Deleting logs, modifying audit rules, 
                  and full audit exports require Super Admin privileges.
                </p>
              </div>
            </div>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Email Settings Modal */}
      <EmailSettingsModal
        open={isEmailSettingsOpen}
        onOpenChange={setIsEmailSettingsOpen}
      />
    </div>
  );
}