import { useState } from 'react';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Badge } from '../../components/ui/badge';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../components/ui/tabs';
import {
  useSuperAdminDashboard,
  useUsers,
  useClinics,
  useAuditLogs,
  useSecurityIncidents,
  useOverrideRequests,
} from '../../hooks/useSuperAdmin.js';
import {
  Shield,
  Users,
  Lock,
  Bot,
  Download,
  Upload,
  AlertTriangle,
  Eye,
  UserPlus,
  UserX,
  Key,
  Database,
  FileText,
  CheckCircle,
  XCircle,
  Building,
  Palette,
  Loader2,
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
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '../../components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../../components/ui/select';
import { Switch } from '../../components/ui/switch';
import { Textarea } from '../../components/ui/textarea';

export default function SuperAdminDashboard() {
  // API hooks for real data
  const { stats: dashboardStats, loading: statsLoading } = useSuperAdminDashboard();
  const { users: apiUsers, loading: usersLoading } = useUsers();
  const { clinics: apiClinics, loading: clinicsLoading } = useClinics();
  const { stats: auditStats, loading: auditLoading } = useAuditLogs();
  const { incidents: apiIncidents, loading: incidentsLoading } = useSecurityIncidents();
  const { requests: overrideRequests, loading: overridesLoading } = useOverrideRequests();

  const [isCreateUserModalOpen, setIsCreateUserModalOpen] = useState(false);
  const [isForceCloseModalOpen, setIsForceCloseModalOpen] = useState(false);

  // Format user data from API
  const systemUsers = apiUsers.map(u => ({
    id: u.id,
    name: u.name,
    email: u.email,
    roles: u.roles,
    status: u.status,
    lastLogin: u.lastLogin || 'Never',
  }));

  // Format clinic data from API
  const clinics = apiClinics.map(c => ({
    id: c.id,
    name: c.name,
    address: c.address || 'Address not set',
    status: c.status,
    employeeCount: c.employeeCount || 0,
  }));

  // Compliance Engine Data (static for now - can be connected to AI config table later)
  const aiModels = [
    { id: '1', name: 'OSHA Recordability Detection', version: '2.1.4', status: 'active', accuracy: '94.2%', lastUpdated: '12/15/2024' },
    { id: '2', name: 'Narrative Generation', version: '1.8.2', status: 'active', accuracy: '91.7%', lastUpdated: '12/10/2024' },
    { id: '3', name: 'Compliance Alert System', version: '3.0.1', status: 'active', accuracy: '96.5%', lastUpdated: '12/18/2024' },
  ];

  const policyDocuments = [
    { id: '1', title: 'HIPAA Privacy Policy', uploadedDate: '11/20/2024', status: 'active', version: '3.2' },
    { id: '2', title: 'OSHA Compliance Procedures', uploadedDate: '12/01/2024', status: 'active', version: '2.1' },
    { id: '3', title: 'Drug Testing Protocol', uploadedDate: '11/15/2024', status: 'under-review', version: '1.5' },
  ];

  // Format security incidents from API
  const securityIncidents = apiIncidents.map(i => ({
    id: i.id,
    type: i.type,
    severity: i.severity,
    reportedBy: i.reportedBy || 'System',
    timestamp: i.timestamp,
    status: i.status,
  }));

  // Format override requests from API
  const casesNeedingOverride = overrideRequests.map(r => ({
    id: r.id,
    patient: r.patientName || 'N/A',
    type: r.type,
    currentStatus: r.status,
    requestedBy: r.requestedBy,
    reason: r.reason || 'No reason provided',
  }));

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-3xl font-bold mb-2">Super Admin Dashboard</h1>
          <p className="text-slate-600 dark:text-slate-400">
            System-level governance, configuration, and escalation authority
          </p>
        </div>
        <Badge variant="destructive" className="text-sm px-3 py-1">
          <Shield className="h-4 w-4 mr-2" />
          Full System Authority
        </Badge>
      </div>

      {/* Critical Alerts */}
      {((dashboardStats?.openIncidents ?? 0) > 0 || casesNeedingOverride.length > 0) && (
        <Card className="p-4 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800">
          <div className="flex items-start gap-3">
            <AlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <p className="font-medium text-red-900 dark:text-red-300">System Alerts</p>
              <div className="mt-2 space-y-2">
                {(dashboardStats?.openIncidents ?? 0) > 0 && (
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-red-700 dark:text-red-400">
                      {dashboardStats?.openIncidents} security incident{(dashboardStats?.openIncidents ?? 0) > 1 ? 's' : ''} require{(dashboardStats?.openIncidents ?? 0) === 1 ? 's' : ''} attention
                    </span>
                    <Button size="sm" variant="outline">Review</Button>
                  </div>
                )}
                {casesNeedingOverride.length > 0 && (
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-red-700 dark:text-red-400">
                      {casesNeedingOverride.length} clearance override request{casesNeedingOverride.length > 1 ? 's' : ''} pending
                    </span>
                    <Button size="sm" variant="outline">Review</Button>
                  </div>
                )}
              </div>
            </div>
          </div>
        </Card>
      )}

      {/* Main Stats */}
      <div className="grid md:grid-cols-5 gap-4">
        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <Users className="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <p className="text-xl font-bold">
                {statsLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (dashboardStats?.totalUsers ?? 0)}
              </p>
              <p className="text-xs text-slate-600 dark:text-slate-400">System Users</p>
            </div>
          </div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
              <Building className="h-5 w-5 text-green-600 dark:text-green-400" />
            </div>
            <div>
              <p className="text-xl font-bold">
                {statsLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (dashboardStats?.activeClinics ?? 0)}
              </p>
              <p className="text-xs text-slate-600 dark:text-slate-400">Active Clinics</p>
            </div>
          </div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
              <Bot className="h-5 w-5 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
              <p className="text-xl font-bold">
                {statsLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (dashboardStats?.dotTestsThisMonth ?? 0)}
              </p>
              <p className="text-xs text-slate-600 dark:text-slate-400">DOT Tests This Month</p>
            </div>
          </div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
              <AlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400" />
            </div>
            <div>
              <p className="text-xl font-bold">
                {statsLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (dashboardStats?.openIncidents ?? 0)}
              </p>
              <p className="text-xs text-slate-600 dark:text-slate-400">Open Incidents</p>
            </div>
          </div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
              <Eye className="h-5 w-5 text-orange-600 dark:text-orange-400" />
            </div>
            <div>
              <p className="text-xl font-bold">
                {statsLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (dashboardStats?.auditLogsToday ?? 0).toLocaleString()}
              </p>
              <p className="text-xs text-slate-600 dark:text-slate-400">Audit Logs Today</p>
            </div>
          </div>
        </Card>
      </div>

      {/* Tabbed Interface */}
      <Tabs defaultValue="users" className="w-full">
        <TabsList className="grid w-full grid-cols-6">
          <TabsTrigger value="users">Users & Roles</TabsTrigger>
          <TabsTrigger value="config">System Config</TabsTrigger>
          <TabsTrigger value="compliance">Compliance Engine</TabsTrigger>
          <TabsTrigger value="audit">Full Audit</TabsTrigger>
          <TabsTrigger value="security">Security Incidents</TabsTrigger>
          <TabsTrigger value="overrides">Overrides</TabsTrigger>
        </TabsList>

        {/* User & Role Management Tab */}
        <TabsContent value="users" className="space-y-6">
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold">System Users</h2>
              <Dialog open={isCreateUserModalOpen} onOpenChange={setIsCreateUserModalOpen}>
                <DialogTrigger asChild>
                  <Button>
                    <UserPlus className="h-4 w-4 mr-2" />
                    Create User
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>Create New User</DialogTitle>
                    <DialogDescription>
                      Add a new user and assign roles
                    </DialogDescription>
                  </DialogHeader>
                  <div className="grid gap-4 py-4">
                    <div>
                      <Label htmlFor="user-name">Full Name</Label>
                      <Input id="user-name" placeholder="John Doe" />
                    </div>
                    <div>
                      <Label htmlFor="user-email">Email</Label>
                      <Input id="user-email" type="email" placeholder="john.doe@example.com" />
                    </div>
                    <div>
                      <Label>Assign Roles</Label>
                      <div className="space-y-2 mt-2">
                        <div className="flex items-center gap-2">
                          <Switch id="role-provider" />
                          <Label htmlFor="role-provider" className="cursor-pointer">Provider</Label>
                        </div>
                        <div className="flex items-center gap-2">
                          <Switch id="role-registration" />
                          <Label htmlFor="role-registration" className="cursor-pointer">Registration</Label>
                        </div>
                        <div className="flex items-center gap-2">
                          <Switch id="role-admin" />
                          <Label htmlFor="role-admin" className="cursor-pointer">Admin</Label>
                        </div>
                        <div className="flex items-center gap-2">
                          <Switch id="role-super-admin" />
                          <Label htmlFor="role-super-admin" className="cursor-pointer">Super Admin</Label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <DialogFooter>
                    <Button variant="outline" onClick={() => setIsCreateUserModalOpen(false)}>Cancel</Button>
                    <Button onClick={() => setIsCreateUserModalOpen(false)}>Create User</Button>
                  </DialogFooter>
                </DialogContent>
              </Dialog>
            </div>

            {usersLoading ? (
              <div className="flex items-center justify-center py-8">
                <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
                <span className="ml-2 text-slate-600 dark:text-slate-400">Loading users...</span>
              </div>
            ) : systemUsers.length === 0 ? (
              <div className="text-center py-8 text-slate-500 dark:text-slate-400">
                <Users className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No users found</p>
              </div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Roles</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Last Login</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {systemUsers.map((user) => (
                    <TableRow key={user.id}>
                      <TableCell className="font-medium">{user.name}</TableCell>
                      <TableCell>{user.email}</TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          {user.roles.map((role) => (
                            <Badge key={role} variant="outline" className="text-xs">
                              {role}
                            </Badge>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant={user.status === 'active' ? 'default' : 'secondary'}>
                          {user.status}
                        </Badge>
                      </TableCell>
                      <TableCell>{user.lastLogin}</TableCell>
                      <TableCell className="text-right">
                        <div className="flex gap-2 justify-end">
                          <Button variant="ghost" size="sm">Edit</Button>
                          <Button variant="ghost" size="sm">
                            <UserX className="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="sm">
                            <Key className="h-4 w-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </Card>

          {/* Emergency Access */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <Lock className="h-5 w-5 text-red-600 dark:text-red-400" />
              <h2 className="text-xl font-semibold">Emergency Access Controls</h2>
            </div>
            <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
              Emergency access allows temporary override of normal access restrictions. All emergency access is logged and audited.
            </p>
            <div className="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
              <div>
                <p className="font-medium">Emergency Access Mode</p>
                <p className="text-sm text-slate-600 dark:text-slate-400">Bypass standard access controls (Full audit trail)</p>
              </div>
              <div className="flex items-center gap-3">
                <Switch id="emergency-access" />
                <Label htmlFor="emergency-access" className="cursor-pointer">
                  {/* Toggle label handled by switch */}
                </Label>
              </div>
            </div>
          </Card>
        </TabsContent>

        {/* System Configuration Tab */}
        <TabsContent value="config" className="space-y-6">
          {/* Organization Settings */}
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-4">Organization Settings</h2>
            <div className="grid gap-4">
              <div>
                <Label htmlFor="org-name">Organization Name</Label>
                <Input id="org-name" defaultValue="Occupational Health EHR" />
              </div>
              <div>
                <Label htmlFor="org-ein">EIN / Tax ID</Label>
                <Input id="org-ein" defaultValue="12-3456789" />
              </div>
              <div>
                <Label htmlFor="org-address">Primary Address</Label>
                <Input id="org-address" defaultValue="123 Healthcare Blvd, Medical City, ST 12345" />
              </div>
            </div>
            <Button className="mt-4">Save Organization Settings</Button>
          </Card>

          {/* Clinic Management */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold">Clinic Locations</h2>
              <Button size="sm">
                <Building className="h-4 w-4 mr-2" />
                Add Clinic
              </Button>
            </div>
            {clinicsLoading ? (
              <div className="flex items-center justify-center py-8">
                <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
                <span className="ml-2 text-slate-600 dark:text-slate-400">Loading clinics...</span>
              </div>
            ) : clinics.length === 0 ? (
              <div className="text-center py-8 text-slate-500 dark:text-slate-400">
                <Building className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No clinics found</p>
              </div>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Clinic Name</TableHead>
                    <TableHead>Address</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Employees</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {clinics.map((clinic) => (
                    <TableRow key={clinic.id}>
                      <TableCell className="font-medium">{clinic.name}</TableCell>
                      <TableCell>{clinic.address}</TableCell>
                      <TableCell>
                        <Badge variant="default">{clinic.status}</Badge>
                      </TableCell>
                      <TableCell>{clinic.employeeCount}</TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm">Edit</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </Card>

          {/* Branding */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <Palette className="h-5 w-5 text-purple-600 dark:text-purple-400" />
              <h2 className="text-xl font-semibold">Branding & Appearance</h2>
            </div>
            <div className="grid gap-4">
              <div>
                <Label htmlFor="logo-upload">Organization Logo</Label>
                <div className="mt-2 flex items-center gap-4">
                  <div className="h-16 w-16 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-700 flex items-center justify-center">
                    <Upload className="h-6 w-6 text-slate-400" />
                  </div>
                  <Button variant="outline" size="sm">Upload Logo</Button>
                </div>
              </div>
              <div>
                <Label htmlFor="primary-color">Primary Brand Color</Label>
                <div className="flex items-center gap-2 mt-2">
                  <Input id="primary-color" type="color" defaultValue="#3b82f6" className="w-20 h-10" />
                  <Input defaultValue="#3b82f6" className="flex-1" />
                </div>
              </div>
            </div>
          </Card>

          {/* Integration Placeholders */}
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-4">External Integrations</h2>
            <div className="space-y-3">
              <div className="p-4 border border-slate-200 dark:border-slate-700 rounded-lg flex items-center justify-between">
                <div>
                  <p className="font-medium">OSHA Integration</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Automated OSHA 300 log submission</p>
                </div>
                <Button variant="outline" size="sm">Configure</Button>
              </div>
              <div className="p-4 border border-slate-200 dark:border-slate-700 rounded-lg flex items-center justify-between">
                <div>
                  <p className="font-medium">DOT Integration</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">DOT drug testing reporting</p>
                </div>
                <Button variant="outline" size="sm">Configure</Button>
              </div>
              <div className="p-4 border border-slate-200 dark:border-slate-700 rounded-lg flex items-center justify-between">
                <div>
                  <p className="font-medium">State Registrar Integration</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Worker's compensation reporting</p>
                </div>
                <Button variant="outline" size="sm">Configure</Button>
              </div>
            </div>
          </Card>
        </TabsContent>

        {/* Compliance Engine Tab */}
        <TabsContent value="compliance" className="space-y-6">
          {/* AI Models */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <Bot className="h-6 w-6 text-purple-600 dark:text-purple-400" />
              <h2 className="text-xl font-semibold">AI Models & Training</h2>
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Model Name</TableHead>
                  <TableHead>Version</TableHead>
                  <TableHead>Accuracy</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Last Updated</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {aiModels.map((model) => (
                  <TableRow key={model.id}>
                    <TableCell className="font-medium">{model.name}</TableCell>
                    <TableCell>v{model.version}</TableCell>
                    <TableCell>{model.accuracy}</TableCell>
                    <TableCell>
                      <Badge variant="default">{model.status}</Badge>
                    </TableCell>
                    <TableCell>{model.lastUpdated}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm">Configure</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Card>

          {/* Policy Documents */}
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold">Company Policies & Procedures</h2>
              <Button size="sm">
                <Upload className="h-4 w-4 mr-2" />
                Upload Policy
              </Button>
            </div>
            <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
              Upload company policies for AI training and compliance automation
            </p>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Document Title</TableHead>
                  <TableHead>Version</TableHead>
                  <TableHead>Uploaded Date</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {policyDocuments.map((doc) => (
                  <TableRow key={doc.id}>
                    <TableCell className="font-medium">{doc.title}</TableCell>
                    <TableCell>v{doc.version}</TableCell>
                    <TableCell>{doc.uploadedDate}</TableCell>
                    <TableCell>
                      <Badge variant={doc.status === 'active' ? 'default' : 'outline'}>
                        {doc.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex gap-2 justify-end">
                        <Button variant="ghost" size="sm">
                          <Download className="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm">Edit</Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Card>

          {/* Compliance Automation */}
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-4">Compliance Automation Features</h2>
            <div className="space-y-3">
              <div className="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
                <div>
                  <p className="font-medium">Auto-detect OSHA Recordability</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">AI analyzes encounters for OSHA 300 log criteria</p>
                </div>
                <Switch id="osha-auto" defaultChecked />
              </div>
              <div className="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
                <div>
                  <p className="font-medium">AI Narrative Generation</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Generate clinical narratives from structured data</p>
                </div>
                <Switch id="narrative-auto" defaultChecked />
              </div>
              <div className="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
                <div>
                  <p className="font-medium">Compliance Alert System</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Automatic alerts for regulatory updates</p>
                </div>
                <Switch id="alerts-auto" defaultChecked />
              </div>
            </div>
          </Card>
        </TabsContent>

        {/* Full Audit Tab */}
        <TabsContent value="audit" className="space-y-6">
          <Card className="p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-semibold">Full Audit Log Access</h2>
              <div className="flex gap-2">
                <Button variant="outline" size="sm">
                  <Download className="h-4 w-4 mr-2" />
                  Export Full Audit Trail
                </Button>
                <Button variant="outline" size="sm">
                  <Database className="h-4 w-4 mr-2" />
                  Archive Logs
                </Button>
              </div>
            </div>
            <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
              Full system audit log with complete access to all entries, export capabilities, and archival controls
            </p>
            
            {/* Audit Statistics */}
            <div className="grid md:grid-cols-4 gap-4 mb-6">
              <Card className="p-4">
                <p className="text-sm text-slate-600 dark:text-slate-400">Total Events Today</p>
                <p className="text-2xl font-bold">
                  {auditLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (auditStats?.totalEvents ?? 0).toLocaleString()}
                </p>
              </Card>
              <Card className="p-4">
                <p className="text-sm text-slate-600 dark:text-slate-400">Flagged Events</p>
                <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                  {auditLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (auditStats?.flaggedEvents ?? 0)}
                </p>
              </Card>
              <Card className="p-4">
                <p className="text-sm text-slate-600 dark:text-slate-400">Unique Users</p>
                <p className="text-2xl font-bold">
                  {auditLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (auditStats?.uniqueUsers ?? 0)}
                </p>
              </Card>
              <Card className="p-4">
                <p className="text-sm text-slate-600 dark:text-slate-400">Systems Accessed</p>
                <p className="text-2xl font-bold">
                  {auditLoading ? <Loader2 className="h-5 w-5 animate-spin" /> : (auditStats?.systemsAccessed ?? 0)}
                </p>
              </Card>
            </div>

            <div className="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
              <p className="text-sm text-slate-600 dark:text-slate-400">
                <strong>Super Admin Privileges:</strong> You have full access to view, export, and archive audit logs. 
                Audit logs cannot be deleted, only archived for compliance retention.
              </p>
            </div>
          </Card>
        </TabsContent>

        {/* Security Incidents Tab */}
        <TabsContent value="security" className="space-y-6">
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
              <AlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400" />
              Security Incidents
            </h2>
            {incidentsLoading ? (
              <div className="flex items-center justify-center py-8">
                <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
                <span className="ml-2 text-slate-600 dark:text-slate-400">Loading security incidents...</span>
              </div>
            ) : securityIncidents.length === 0 ? (
              <div className="text-center py-8 text-slate-500 dark:text-slate-400">
                <Shield className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No security incidents found</p>
                <p className="text-sm mt-1">System is operating normally</p>
              </div>
            ) : (
              <div className="space-y-3">
                {securityIncidents.map((incident) => (
                <div 
                  key={incident.id}
                  className={`p-4 border rounded-lg ${
                    incident.severity === 'high' 
                      ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
                      : incident.severity === 'medium'
                      ? 'border-orange-300 bg-orange-50 dark:border-orange-800 dark:bg-orange-900/20'
                      : 'border-slate-300 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50'
                  }`}
                >
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <p className="font-medium">{incident.type}</p>
                        <Badge variant={
                          incident.severity === 'high' ? 'destructive' :
                          incident.severity === 'medium' ? 'default' :
                          'outline'
                        }>
                          {incident.severity}
                        </Badge>
                        <Badge variant="outline">{incident.status}</Badge>
                      </div>
                      <p className="text-sm text-slate-600 dark:text-slate-400">
                        Reported by: {incident.reportedBy} • {incident.timestamp}
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button size="sm">View Details</Button>
                    <Button size="sm" variant="outline">Add Notes</Button>
                    {incident.status !== 'resolved' && (
                      <Button size="sm" variant="default">
                        <CheckCircle className="h-4 w-4 mr-1" />
                        Close Incident
                      </Button>
                    )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </Card>

          {/* Incident Resolution Template */}
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-4">Document Resolution</h2>
            <div className="space-y-4">
              <div>
                <Label htmlFor="resolution-notes">Resolution Notes</Label>
                <Textarea 
                  id="resolution-notes"
                  placeholder="Document the investigation findings, actions taken, and resolution..."
                  rows={4}
                />
              </div>
              <div>
                <Label htmlFor="preventive-measures">Preventive Measures</Label>
                <Textarea 
                  id="preventive-measures"
                  placeholder="List preventive measures to avoid similar incidents..."
                  rows={3}
                />
              </div>
              <Button>Save & Close Incident</Button>
            </div>
          </Card>
        </TabsContent>

        {/* Overrides & Escalation Tab */}
        <TabsContent value="overrides" className="space-y-6">
          {/* Clearance Override Requests */}
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-4">Clearance Override Requests</h2>
            {overridesLoading ? (
              <div className="flex items-center justify-center py-8">
                <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
                <span className="ml-2 text-slate-600 dark:text-slate-400">Loading override requests...</span>
              </div>
            ) : casesNeedingOverride.length === 0 ? (
              <div className="text-center py-8 text-slate-500 dark:text-slate-400">
                <CheckCircle className="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No pending override requests</p>
                <p className="text-sm mt-1">All clearance requests have been processed</p>
              </div>
            ) : (
              <div className="space-y-3">
                {casesNeedingOverride.map((case_) => (
                <div key={case_.id} className="p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
                  <div className="flex items-start justify-between mb-2">
                    <div>
                      <p className="font-medium">{case_.patient} - {case_.type}</p>
                      <p className="text-sm text-slate-600 dark:text-slate-400">
                        Current Status: <Badge variant="outline">{case_.currentStatus}</Badge>
                      </p>
                      <p className="text-sm text-slate-600 dark:text-slate-400 mt-1">
                        Requested by: {case_.requestedBy}
                      </p>
                      <p className="text-sm text-slate-500 mt-1 italic">
                        Reason: {case_.reason}
                      </p>
                    </div>
                  </div>
                  <div className="flex gap-2 mt-3">
                    <Dialog>
                      <DialogTrigger asChild>
                        <Button size="sm">
                          <CheckCircle className="h-4 w-4 mr-1" />
                          Override Clearance
                        </Button>
                      </DialogTrigger>
                      <DialogContent>
                        <DialogHeader>
                          <DialogTitle>Override Clearance Status</DialogTitle>
                          <DialogDescription>
                            This action will override the current clearance status. All overrides are logged and audited.
                          </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                          <div>
                            <Label htmlFor="new-status">New Clearance Status</Label>
                            <Select>
                              <SelectTrigger>
                                <SelectValue placeholder="Select new status" />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="cleared">Cleared</SelectItem>
                                <SelectItem value="not-cleared">Not Cleared</SelectItem>
                                <SelectItem value="conditional">Conditional Clearance</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
                          <div>
                            <Label htmlFor="override-justification">Justification (Required)</Label>
                            <Textarea 
                              id="override-justification"
                              placeholder="Provide detailed justification for this override..."
                              rows={4}
                            />
                          </div>
                        </div>
                        <DialogFooter>
                          <Button variant="outline">Cancel</Button>
                          <Button variant="destructive">Confirm Override</Button>
                        </DialogFooter>
                      </DialogContent>
                    </Dialog>
                    <Button size="sm" variant="outline">Request More Info</Button>
                    <Button size="sm" variant="ghost">
                      <XCircle className="h-4 w-4 mr-1" />
                      Deny
                    </Button>
                  </div>
                  </div>
                ))}
            </div>
          )}
        </Card>

          {/* Force Close Cases */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <Lock className="h-5 w-5 text-red-600 dark:text-red-400" />
              <h2 className="text-xl font-semibold">Force Close Cases</h2>
            </div>
            <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
              Force close open cases that cannot be resolved through normal workflow. Requires justification and creates audit trail.
            </p>
            <Dialog open={isForceCloseModalOpen} onOpenChange={setIsForceCloseModalOpen}>
              <DialogTrigger asChild>
                <Button variant="destructive">
                  Force Close Case
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Force Close Case</DialogTitle>
                  <DialogDescription>
                    This action will forcefully close a case, bypassing normal workflow. This action is logged and audited.
                  </DialogDescription>
                </DialogHeader>
                <div className="space-y-4 py-4">
                  <div>
                    <Label htmlFor="case-id">Case ID</Label>
                    <Input id="case-id" placeholder="Enter case ID..." />
                  </div>
                  <div>
                    <Label htmlFor="close-justification">Justification (Required)</Label>
                    <Textarea 
                      id="close-justification"
                      placeholder="Provide detailed justification for force closing this case..."
                      rows={4}
                    />
                  </div>
                </div>
                <DialogFooter>
                  <Button variant="outline" onClick={() => setIsForceCloseModalOpen(false)}>Cancel</Button>
                  <Button variant="destructive" onClick={() => setIsForceCloseModalOpen(false)}>
                    Confirm Force Close
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </Card>

          {/* Re-open Submitted Encounters */}
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <FileText className="h-5 w-5 text-orange-600 dark:text-orange-400" />
              <h2 className="text-xl font-semibold">Re-open Submitted Encounters</h2>
            </div>
            <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
              Re-open encounters that have been submitted and signed. This action is audited and requires justification.
            </p>
            <Button variant="outline">
              Search Encounters to Re-open
            </Button>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
