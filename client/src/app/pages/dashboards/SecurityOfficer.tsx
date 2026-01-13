import { Card } from '../../components/ui/card.js';
import { Badge } from '../../components/ui/badge.js';
import { Button } from '../../components/ui/button.js';
import { Shield, AlertTriangle, Activity, Eye, RefreshCw, Loader2, XCircle, Inbox, Users, Lock, CheckCircle } from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../../components/ui/table.js';
import { useSecurityOfficer } from '../../hooks/useSecurityOfficer.js';
import type { AuditEvent, SecurityAlert } from '../../hooks/useSecurityOfficer.js';

export default function SecurityOfficerDashboard() {
  // Use the security officer dashboard hook for API data
  const {
    stats,
    auditEvents,
    securityAlerts,
    activeSessions,
    mfaStatus,
    loading,
    error,
    refetch,
    clearError,
  } = useSecurityOfficer();

  // Helper function to format relative time
  const formatRelativeTime = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffSecs < 60) return 'Just now';
    if (diffMins === 1) return '1 min ago';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours === 1) return '1 hour ago';
    if (diffHours < 24) return `${diffHours} hours ago`;
    if (diffDays === 1) return '1 day ago';
    return `${diffDays} days ago`;
  };

  // Determine system status based on security metrics
  const getSystemStatus = (): { status: string; color: string; bgColor: string } => {
    if (!stats) return { status: '--', color: 'text-slate-600', bgColor: 'bg-slate-100' };
    
    const criticalAlerts = securityAlerts.filter(a => a.severity === 'critical').length;
    
    if (criticalAlerts > 0 || stats.anomaliesDetected > 5) {
      return { status: 'At Risk', color: 'text-red-600', bgColor: 'bg-red-100' };
    }
    if (stats.anomaliesDetected > 0 || stats.mfaCompliance < 80) {
      return { status: 'Warning', color: 'text-orange-600', bgColor: 'bg-orange-100' };
    }
    return { status: 'Secure', color: 'text-green-600', bgColor: 'bg-green-100' };
  };

  const systemStatus = getSystemStatus();

  // Loading skeleton for stats
  const StatsSkeleton = () => (
    <div className="grid md:grid-cols-4 gap-6">
      {[1, 2, 3, 4].map((i) => (
        <Card key={i} className="p-6 animate-pulse">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-slate-200"></div>
            <div>
              <div className="h-6 w-12 bg-slate-200 rounded mb-2"></div>
              <div className="h-4 w-24 bg-slate-200 rounded"></div>
            </div>
          </div>
        </Card>
      ))}
    </div>
  );

  // Loading skeleton for anomalies
  const AnomaliesSkeleton = () => (
    <div className="space-y-3">
      {[1, 2].map((i) => (
        <div key={i} className="p-4 border border-slate-200 rounded-lg animate-pulse">
          <div className="flex items-center justify-between">
            <div>
              <div className="h-4 w-48 bg-slate-200 rounded mb-2"></div>
              <div className="h-3 w-32 bg-slate-200 rounded"></div>
            </div>
            <div className="flex items-center gap-2">
              <div className="h-5 w-16 bg-slate-200 rounded"></div>
              <div className="h-8 w-24 bg-slate-200 rounded"></div>
            </div>
          </div>
        </div>
      ))}
    </div>
  );

  // Loading skeleton for audit table
  const AuditTableSkeleton = () => (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>User</TableHead>
          <TableHead>Action</TableHead>
          <TableHead>Time</TableHead>
          <TableHead>Status</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {[1, 2, 3].map((i) => (
          <TableRow key={i} className="animate-pulse">
            <TableCell><div className="h-4 w-24 bg-slate-200 rounded"></div></TableCell>
            <TableCell><div className="h-4 w-40 bg-slate-200 rounded"></div></TableCell>
            <TableCell><div className="h-4 w-20 bg-slate-200 rounded"></div></TableCell>
            <TableCell><div className="h-5 w-16 bg-slate-200 rounded"></div></TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
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

  // Map audit events to display format
  const mapAuditEventToDisplay = (event: AuditEvent) => {
    // Determine if event should be flagged (security-related events)
    const flaggedTypes = ['login_failed', 'unauthorized_access', 'permission_denied', 'suspicious_activity', 'after_hours_access'];
    const isFlagged = event.flagged || flaggedTypes.includes(event.eventType);
    
    // Format the action display
    const actionMap: Record<string, string> = {
      'login_failed': 'Failed Login Attempt',
      'login_success': 'Logged In',
      'logout': 'Logged Out',
      'password_change': 'Changed Password',
      'mfa_enabled': 'Enabled MFA',
      'mfa_disabled': 'Disabled MFA',
      'record_access': 'Accessed Record',
      'record_view': 'Viewed Record',
      'record_update': 'Updated Record',
      'unauthorized_access': 'Unauthorized Access Attempt',
      'permission_denied': 'Permission Denied',
      'after_hours_access': 'Accessed After Hours',
    };
    
    const action = actionMap[event.eventType] || event.eventType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    
    return {
      id: event.id,
      user: event.userName || 'Unknown User',
      action: event.details ? `${action}: ${event.details}` : action,
      time: formatRelativeTime(event.timestamp),
      flagged: isFlagged,
    };
  };

  // Map security alerts to anomalies display format
  const mapAlertToAnomaly = (alert: SecurityAlert) => {
    const severityMap: Record<string, 'high' | 'medium' | 'low'> = {
      'critical': 'high',
      'warning': 'medium',
    };
    
    return {
      id: alert.id,
      type: alert.alertType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
      user: 'System',
      severity: severityMap[alert.severity] || 'medium',
      message: alert.message,
    };
  };

  // Get display data
  const displayAudits = auditEvents.slice(0, 10).map(mapAuditEventToDisplay);
  const displayAnomalies = securityAlerts.map(mapAlertToAnomaly);

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold mb-2">Security Officer Dashboard</h1>
          <p className="text-slate-600">Security monitoring and audit management</p>
        </div>
        <div className="flex items-center gap-2">
          {loading && <Loader2 className="h-4 w-4 animate-spin text-slate-400" />}
          <Button variant="outline" size="sm" onClick={refetch} disabled={loading}>
            <RefreshCw className={`h-4 w-4 mr-1 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Error Display */}
      {error && (
        <ErrorState message={error} onRetry={refetch} onDismiss={clearError} />
      )}

      {/* Stats */}
      {loading ? (
        <StatsSkeleton />
      ) : (
        <div className="grid md:grid-cols-4 gap-6">
          <Card className="p-6">
            <div className="flex items-center gap-4">
              <div className={`h-12 w-12 rounded-lg ${systemStatus.bgColor} flex items-center justify-center`}>
                <Shield className={`h-6 w-6 ${systemStatus.color}`} />
              </div>
              <div>
                <p className={`text-2xl font-bold ${systemStatus.color}`}>{systemStatus.status}</p>
                <p className="text-sm text-slate-600">System Status</p>
              </div>
            </div>
          </Card>

          <Card className="p-6">
            <div className="flex items-center gap-4">
              <div className="h-12 w-12 rounded-lg bg-blue-100 flex items-center justify-center">
                <Activity className="h-6 w-6 text-blue-600" />
              </div>
              <div>
                <p className="text-2xl font-bold">{auditEvents.length > 0 ? auditEvents.length.toLocaleString() : '--'}</p>
                <p className="text-sm text-slate-600">Events Today</p>
              </div>
            </div>
          </Card>

          <Card className="p-6">
            <div className="flex items-center gap-4">
              <div className="h-12 w-12 rounded-lg bg-orange-100 flex items-center justify-center">
                <AlertTriangle className="h-6 w-6 text-orange-600" />
              </div>
              <div>
                <p className="text-2xl font-bold">{stats ? stats.anomaliesDetected : '--'}</p>
                <p className="text-sm text-slate-600">Anomalies Detected</p>
              </div>
            </div>
          </Card>

          <Card className="p-6">
            <div className="flex items-center gap-4">
              <div className="h-12 w-12 rounded-lg bg-purple-100 flex items-center justify-center">
                <Eye className="h-6 w-6 text-purple-600" />
              </div>
              <div>
                <p className="text-2xl font-bold">{stats ? stats.activeSessions : '--'}</p>
                <p className="text-sm text-slate-600">Active Users</p>
              </div>
            </div>
          </Card>
        </div>
      )}

      {/* MFA Compliance and Failed Logins Summary */}
      {!loading && stats && (
        <div className="grid md:grid-cols-3 gap-6">
          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <Lock className="h-5 w-5 text-blue-600" />
              <h3 className="font-semibold">MFA Compliance</h3>
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-3xl font-bold text-blue-600">{stats.mfaCompliance.toFixed(1)}%</p>
                <p className="text-sm text-slate-600">{stats.usersWithMFA} of {stats.totalUsers} users</p>
              </div>
              {stats.mfaCompliance >= 90 ? (
                <CheckCircle className="h-8 w-8 text-green-500" />
              ) : (
                <AlertTriangle className="h-8 w-8 text-orange-500" />
              )}
            </div>
          </Card>

          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <XCircle className="h-5 w-5 text-red-600" />
              <h3 className="font-semibold">Failed Logins (24h)</h3>
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-3xl font-bold text-red-600">{stats.failedLogins24h}</p>
                <p className="text-sm text-slate-600">Authentication failures</p>
              </div>
              {stats.failedLogins24h <= 5 ? (
                <CheckCircle className="h-8 w-8 text-green-500" />
              ) : (
                <AlertTriangle className="h-8 w-8 text-orange-500" />
              )}
            </div>
          </Card>

          <Card className="p-6">
            <div className="flex items-center gap-2 mb-4">
              <Users className="h-5 w-5 text-purple-600" />
              <h3 className="font-semibold">Active Sessions</h3>
            </div>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-3xl font-bold text-purple-600">{activeSessions.length}</p>
                <p className="text-sm text-slate-600">Currently logged in</p>
              </div>
              <Activity className="h-8 w-8 text-purple-500" />
            </div>
          </Card>
        </div>
      )}

      {/* Anomalies */}
      <Card className="p-6">
        <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
          <AlertTriangle className="h-5 w-5 text-orange-600" />
          Security Anomalies
        </h2>
        {loading ? (
          <AnomaliesSkeleton />
        ) : displayAnomalies.length === 0 ? (
          <EmptyState message="No security anomalies detected" icon={CheckCircle} />
        ) : (
          <div className="space-y-3">
            {displayAnomalies.map((anomaly) => (
              <div key={anomaly.id} className="p-4 border border-orange-200 bg-orange-50 rounded-lg flex items-center justify-between">
                <div>
                  <p className="font-medium">{anomaly.type}</p>
                  <p className="text-sm text-slate-600">User: {anomaly.user}</p>
                  {anomaly.message && (
                    <p className="text-sm text-slate-500 mt-1">{anomaly.message}</p>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant={anomaly.severity === 'high' ? 'destructive' : 'default'}>
                    {anomaly.severity}
                  </Badge>
                  <Button size="sm">Investigate</Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>

      {/* Recent Audit Logs */}
      <Card className="p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold">Recent Audit Logs</h2>
          <Button variant="outline" size="sm">Export Audit Trail</Button>
        </div>
        {loading ? (
          <AuditTableSkeleton />
        ) : displayAudits.length === 0 ? (
          <EmptyState message="No audit events recorded" icon={Inbox} />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>User</TableHead>
                <TableHead>Action</TableHead>
                <TableHead>Time</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {displayAudits.map((audit) => (
                <TableRow key={audit.id} className={audit.flagged ? 'bg-red-50' : ''}>
                  <TableCell className="font-medium">{audit.user}</TableCell>
                  <TableCell>{audit.action}</TableCell>
                  <TableCell>{audit.time}</TableCell>
                  <TableCell>
                    {audit.flagged ? (
                      <Badge variant="destructive">Flagged</Badge>
                    ) : (
                      <Badge variant="outline">Normal</Badge>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>

      {/* MFA Status Details */}
      {!loading && mfaStatus && (
        <Card className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold">MFA Status Overview</h2>
            <Button variant="ghost" size="sm">View All Users</Button>
          </div>
          <div className="grid md:grid-cols-4 gap-4">
            <div className="p-3 bg-green-50 rounded-lg text-center">
              <p className="text-xl font-bold text-green-600">{mfaStatus.enabled}</p>
              <p className="text-xs text-green-700">MFA Enabled</p>
            </div>
            <div className="p-3 bg-red-50 rounded-lg text-center">
              <p className="text-xl font-bold text-red-600">{mfaStatus.disabled}</p>
              <p className="text-xs text-red-700">MFA Disabled</p>
            </div>
            <div className="p-3 bg-yellow-50 rounded-lg text-center">
              <p className="text-xl font-bold text-yellow-600">{mfaStatus.pending}</p>
              <p className="text-xs text-yellow-700">Pending Setup</p>
            </div>
            <div className="p-3 bg-blue-50 rounded-lg text-center">
              <p className="text-xl font-bold text-blue-600">{mfaStatus.complianceRate.toFixed(1)}%</p>
              <p className="text-xs text-blue-700">Compliance Rate</p>
            </div>
          </div>
        </Card>
      )}
    </div>
  );
}
