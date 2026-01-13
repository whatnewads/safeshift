import { Card } from '../../components/ui/card.js';
import { Badge } from '../../components/ui/badge.js';
import { Button } from '../../components/ui/button.js';
import { Shield, FileText, CheckCircle, AlertCircle, Bot, RefreshCw, Loader2, XCircle, Inbox, Clock, Users, Activity } from 'lucide-react';
import { usePrivacyOfficer } from '../../hooks/usePrivacyOfficer.js';
import type { RegulatoryUpdate, ConsentStatus, AccessLog } from '../../hooks/usePrivacyOfficer.js';

export default function PrivacyOfficerDashboard() {
  // Use the privacy officer dashboard hook for API data
  const {
    complianceKPIs,
    phiAccessLogs,
    consentStatus,
    regulatoryUpdates,
    trainingCompliance,
    loading,
    error,
    refetch,
    clearError,
  } = usePrivacyOfficer();

  // Helper function to format relative time
  const formatRelativeTime = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return '1 day ago';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
    return `${Math.floor(diffDays / 30)} months ago`;
  };

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

  // Loading skeleton for recommendations
  const RecommendationsSkeleton = () => (
    <div className="space-y-3">
      {[1, 2].map((i) => (
        <div key={i} className="p-4 border border-slate-200 rounded-lg animate-pulse">
          <div className="flex items-start justify-between mb-2">
            <div>
              <div className="h-4 w-48 bg-slate-200 rounded mb-2"></div>
              <div className="h-3 w-32 bg-slate-200 rounded"></div>
            </div>
            <div className="h-5 w-16 bg-slate-200 rounded"></div>
          </div>
          <div className="flex gap-2 mt-3">
            <div className="h-8 w-20 bg-slate-200 rounded"></div>
            <div className="h-8 w-16 bg-slate-200 rounded"></div>
            <div className="h-8 w-16 bg-slate-200 rounded"></div>
          </div>
        </div>
      ))}
    </div>
  );

  // Loading skeleton for updates
  const UpdatesSkeleton = () => (
    <div className="space-y-3">
      {[1, 2].map((i) => (
        <div key={i} className="p-4 border border-slate-200 rounded-lg flex items-center justify-between animate-pulse">
          <div>
            <div className="flex items-center gap-2 mb-2">
              <div className="h-5 w-12 bg-slate-200 rounded"></div>
              <div className="h-5 w-16 bg-slate-200 rounded"></div>
            </div>
            <div className="h-4 w-64 bg-slate-200 rounded mb-1"></div>
            <div className="h-3 w-20 bg-slate-200 rounded"></div>
          </div>
          <div className="h-9 w-32 bg-slate-200 rounded"></div>
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

  // Generate AI recommendations based on compliance data (derived from real data)
  const getAIRecommendations = () => {
    const recommendations: Array<{id: string; title: string; confidence: number; status: string}> = [];
    
    if (complianceKPIs) {
      if (complianceKPIs.trainingCompletion < 95) {
        recommendations.push({
          id: '1',
          title: 'Update HIPAA training module - Some staff need refresher training',
          confidence: Math.round(100 - complianceKPIs.trainingCompletion),
          status: 'pending'
        });
      }
      if (complianceKPIs.consentCompliance < 98) {
        recommendations.push({
          id: '2',
          title: 'Revise consent collection process - Compliance below target',
          confidence: Math.round(100 - complianceKPIs.consentCompliance),
          status: 'pending'
        });
      }
      if (complianceKPIs.breachCount > 0) {
        recommendations.push({
          id: '3',
          title: 'Review and update security policies - Recent breach detected',
          confidence: 95,
          status: 'pending'
        });
      }
    }
    
    // Add a generic recommendation if we have few
    if (recommendations.length === 0) {
      recommendations.push({
        id: 'default',
        title: 'No urgent compliance actions required',
        confidence: 100,
        status: 'completed'
      });
    }
    
    return recommendations;
  };

  const aiRecommendations = complianceKPIs ? getAIRecommendations() : [];

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold mb-2">Privacy Officer Dashboard</h1>
          <p className="text-slate-600">Compliance automation and training management</p>
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
              <div className="h-12 w-12 rounded-lg bg-blue-100 flex items-center justify-center">
                <Shield className="h-6 w-6 text-blue-600" />
              </div>
              <div>
                <p className="text-2xl font-bold">
                  {complianceKPIs ? `${complianceKPIs.overallScore}%` : '--'}
                </p>
                <p className="text-sm text-slate-600">Compliance Rate</p>
              </div>
            </div>
          </Card>

          <Card className="p-6">
            <div className="flex items-center gap-4">
              <div className="h-12 w-12 rounded-lg bg-purple-100 flex items-center justify-center">
                <Bot className="h-6 w-6 text-purple-600" />
              </div>
              <div>
                <p className="text-2xl font-bold">{aiRecommendations.length}</p>
                <p className="text-sm text-slate-600">AI Recommendations</p>
              </div>
            </div>
          </Card>

          <Card className="p-6">
            <div className="flex items-center gap-4">
              <div className="h-12 w-12 rounded-lg bg-orange-100 flex items-center justify-center">
                <AlertCircle className="h-6 w-6 text-orange-600" />
              </div>
              <div>
                <p className="text-2xl font-bold">{regulatoryUpdates.length}</p>
                <p className="text-sm text-slate-600">Pending Updates</p>
              </div>
            </div>
          </Card>

          <Card className="p-6">
            <div className="flex items-center gap-4">
              <div className="h-12 w-12 rounded-lg bg-green-100 flex items-center justify-center">
                <FileText className="h-6 w-6 text-green-600" />
              </div>
              <div>
                <p className="text-2xl font-bold">
                  {trainingCompliance?.stats?.total ?? '--'}
                </p>
                <p className="text-sm text-slate-600">Active Trainings</p>
              </div>
            </div>
          </Card>
        </div>
      )}

      {/* Compliance KPIs Details */}
      {complianceKPIs && !loading && (
        <Card className="p-6">
          <h2 className="text-xl font-semibold mb-4">Compliance Metrics Detail</h2>
          <div className="grid md:grid-cols-4 gap-4">
            <div className="p-4 border border-slate-200 rounded-lg">
              <div className="flex items-center gap-2 mb-2">
                <Users className="h-5 w-5 text-blue-600" />
                <span className="text-sm font-medium">Training Completion</span>
              </div>
              <p className="text-2xl font-bold text-blue-600">{complianceKPIs.trainingCompletion}%</p>
            </div>
            <div className="p-4 border border-slate-200 rounded-lg">
              <div className="flex items-center gap-2 mb-2">
                <CheckCircle className="h-5 w-5 text-green-600" />
                <span className="text-sm font-medium">Consent Compliance</span>
              </div>
              <p className="text-2xl font-bold text-green-600">{complianceKPIs.consentCompliance}%</p>
            </div>
            <div className="p-4 border border-slate-200 rounded-lg">
              <div className="flex items-center gap-2 mb-2">
                <AlertCircle className="h-5 w-5 text-red-600" />
                <span className="text-sm font-medium">Breach Count (YTD)</span>
              </div>
              <p className="text-2xl font-bold text-red-600">{complianceKPIs.breachCount}</p>
            </div>
            <div className="p-4 border border-slate-200 rounded-lg">
              <div className="flex items-center gap-2 mb-2">
                <Activity className="h-5 w-5 text-purple-600" />
                <span className="text-sm font-medium">Overall Score</span>
              </div>
              <p className="text-2xl font-bold text-purple-600">{complianceKPIs.overallScore}%</p>
            </div>
          </div>
        </Card>
      )}

      {/* AI Compliance Assistant */}
      <Card className="p-6">
        <div className="flex items-center gap-2 mb-4">
          <Bot className="h-6 w-6 text-purple-600" />
          <h2 className="text-xl font-semibold">AI Compliance Assistant</h2>
        </div>
        {loading ? (
          <RecommendationsSkeleton />
        ) : aiRecommendations.length === 0 ? (
          <EmptyState message="No AI recommendations at this time" icon={Bot} />
        ) : (
          <div className="space-y-3">
            {aiRecommendations.map((rec) => (
              <div key={rec.id} className="p-4 border border-slate-200 rounded-lg">
                <div className="flex items-start justify-between mb-2">
                  <div>
                    <p className="font-medium">{rec.title}</p>
                    <p className="text-sm text-slate-600">Confidence: {rec.confidence}%</p>
                  </div>
                  <Badge variant={rec.status === 'completed' ? 'default' : 'outline'}>{rec.status}</Badge>
                </div>
                {rec.status !== 'completed' && (
                  <div className="flex gap-2">
                    <Button size="sm">Approve</Button>
                    <Button size="sm" variant="outline">Review</Button>
                    <Button size="sm" variant="ghost">Dismiss</Button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </Card>

      {/* Regulatory Updates */}
      <Card className="p-6">
        <h2 className="text-xl font-semibold mb-4">Pending Regulatory Updates</h2>
        {loading ? (
          <UpdatesSkeleton />
        ) : regulatoryUpdates.length === 0 ? (
          <EmptyState message="No pending regulatory updates" icon={FileText} />
        ) : (
          <div className="space-y-3">
            {regulatoryUpdates.map((update) => (
              <div key={update.id} className="p-4 border border-slate-200 rounded-lg flex items-center justify-between">
                <div>
                  <div className="flex items-center gap-2 mb-1">
                    <Badge>{update.source}</Badge>
                    <Badge variant={update.priority === 'critical' || update.priority === 'high' ? 'destructive' : 'default'}>
                      {update.priority}
                    </Badge>
                  </div>
                  <p className="font-medium">{update.title}</p>
                  <p className="text-sm text-slate-600">{formatRelativeTime(update.createdAt)}</p>
                </div>
                <Button>Review & Process</Button>
              </div>
            ))}
          </div>
        )}
      </Card>

      {/* PHI Access Logs Summary */}
      {!loading && phiAccessLogs.length > 0 && (
        <Card className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold">Recent PHI Access Logs</h2>
            <Button variant="ghost" size="sm">View All</Button>
          </div>
          <div className="space-y-2">
            {phiAccessLogs.slice(0, 5).map((log) => (
              <div key={log.id} className="flex items-center justify-between p-3 border border-slate-200 rounded-lg text-sm">
                <div className="flex items-center gap-3">
                  <Badge variant="outline">{log.accessType}</Badge>
                  <span className="font-medium">{log.accessorName}</span>
                  <span className="text-slate-500">accessed {log.tableName}</span>
                </div>
                <div className="flex items-center gap-2 text-slate-500">
                  <Clock className="h-4 w-4" />
                  {formatRelativeTime(log.timestamp)}
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* Consent Status Summary */}
      {!loading && consentStatus.length > 0 && (
        <Card className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold">Consent Status Overview</h2>
            <Button variant="ghost" size="sm">View All</Button>
          </div>
          <div className="grid md:grid-cols-4 gap-4 mb-4">
            <div className="p-3 bg-green-50 rounded-lg text-center">
              <p className="text-xl font-bold text-green-600">
                {consentStatus.filter(c => c.status === 'granted').length}
              </p>
              <p className="text-xs text-green-700">Granted</p>
            </div>
            <div className="p-3 bg-yellow-50 rounded-lg text-center">
              <p className="text-xl font-bold text-yellow-600">
                {consentStatus.filter(c => c.status === 'pending').length}
              </p>
              <p className="text-xs text-yellow-700">Pending</p>
            </div>
            <div className="p-3 bg-red-50 rounded-lg text-center">
              <p className="text-xl font-bold text-red-600">
                {consentStatus.filter(c => c.status === 'declined').length}
              </p>
              <p className="text-xs text-red-700">Declined</p>
            </div>
            <div className="p-3 bg-slate-50 rounded-lg text-center">
              <p className="text-xl font-bold text-slate-600">
                {consentStatus.filter(c => c.status === 'expired').length}
              </p>
              <p className="text-xs text-slate-700">Expired</p>
            </div>
          </div>
        </Card>
      )}

      {/* Training Compliance Summary */}
      {!loading && trainingCompliance && trainingCompliance.stats && (
        <Card className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold">Training Compliance</h2>
            <Button variant="ghost" size="sm">View All</Button>
          </div>
          <div className="grid md:grid-cols-4 gap-4">
            <div className="p-3 bg-blue-50 rounded-lg text-center">
              <p className="text-xl font-bold text-blue-600">{trainingCompliance.stats.total}</p>
              <p className="text-xs text-blue-700">Total Records</p>
            </div>
            <div className="p-3 bg-green-50 rounded-lg text-center">
              <p className="text-xl font-bold text-green-600">{trainingCompliance.stats.compliant}</p>
              <p className="text-xs text-green-700">Compliant</p>
            </div>
            <div className="p-3 bg-yellow-50 rounded-lg text-center">
              <p className="text-xl font-bold text-yellow-600">{trainingCompliance.stats.expiringSoon}</p>
              <p className="text-xs text-yellow-700">Expiring Soon</p>
            </div>
            <div className="p-3 bg-red-50 rounded-lg text-center">
              <p className="text-xl font-bold text-red-600">{trainingCompliance.stats.overdue}</p>
              <p className="text-xs text-red-700">Overdue</p>
            </div>
          </div>
        </Card>
      )}
    </div>
  );
}
