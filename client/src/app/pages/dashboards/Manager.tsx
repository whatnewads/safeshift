import { Card } from '../../components/ui/card.js';
import { Badge } from '../../components/ui/badge.js';
import { Button } from '../../components/ui/button.js';
import { AlertCircle, Clock, CheckCircle, FileText, Loader2 } from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../../components/ui/table.js';
import { useManagerDashboard } from '../../hooks/useDashboard.js';

export default function ManagerDashboard() {
  const { 
    stats, 
    cases, 
    loading, 
    error, 
    fetchCases,
    refetch 
  } = useManagerDashboard();

  const handleStatusFilter = (status: string | null) => {
    if (status === null) {
      refetch();
    } else {
      fetchCases(status);
    }
  };

  if (loading && !stats) {
    return (
      <div className="p-6 flex items-center justify-center min-h-[400px]">
        <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold mb-2">Manager Dashboard</h1>
        <p className="text-slate-600">Case management and OSHA reporting</p>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
          {error}
          <Button variant="ghost" size="sm" onClick={refetch} className="ml-2">
            Retry
          </Button>
        </div>
      )}

      {/* Stats */}
      <div className="grid md:grid-cols-4 gap-6">
        <Card 
          className="p-6 cursor-pointer hover:shadow-md transition-shadow"
          onClick={() => handleStatusFilter('open')}
        >
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-blue-100 flex items-center justify-center">
              <FileText className="h-6 w-6 text-blue-600" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats?.openCases ?? 0}</p>
              <p className="text-sm text-slate-600">Open Cases</p>
            </div>
          </div>
        </Card>

        <Card 
          className="p-6 cursor-pointer hover:shadow-md transition-shadow"
          onClick={() => handleStatusFilter('follow-up-due')}
        >
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-orange-100 flex items-center justify-center">
              <Clock className="h-6 w-6 text-orange-600" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats?.followUpsDue ?? 0}</p>
              <p className="text-sm text-slate-600">Follow-ups Due</p>
            </div>
          </div>
        </Card>

        <Card 
          className="p-6 cursor-pointer hover:shadow-md transition-shadow"
          onClick={() => handleStatusFilter('high-risk')}
        >
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-red-100 flex items-center justify-center">
              <AlertCircle className="h-6 w-6 text-red-600" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats?.highRisk ?? 0}</p>
              <p className="text-sm text-slate-600">High Risk</p>
            </div>
          </div>
        </Card>

        <Card 
          className="p-6 cursor-pointer hover:shadow-md transition-shadow"
          onClick={() => handleStatusFilter(null)}
        >
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-green-100 flex items-center justify-center">
              <CheckCircle className="h-6 w-6 text-green-600" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats?.closedThisMonth ?? 0}</p>
              <p className="text-sm text-slate-600">Closed This Month</p>
            </div>
          </div>
        </Card>
      </div>

      {/* Cases Table */}
      <Card className="p-6">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-semibold">Case Management</h2>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => handleStatusFilter(null)}>
              All
            </Button>
            <Button variant="outline" size="sm" onClick={() => handleStatusFilter('open')}>
              Open
            </Button>
            <Button variant="outline" size="sm" onClick={() => handleStatusFilter('high-risk')}>
              High Risk
            </Button>
            <Button variant="outline" size="sm" onClick={() => handleStatusFilter('follow-up-due')}>
              Follow-up Due
            </Button>
          </div>
        </div>
        
        {loading ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
          </div>
        ) : cases.length === 0 ? (
          <div className="text-center py-8 text-slate-500">
            No cases found matching the current filters.
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Patient</TableHead>
                <TableHead>Type</TableHead>
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
                  <TableCell>
                    <Badge
                      variant={
                        case_.status === 'high-risk' ? 'destructive' :
                        case_.status === 'follow-up-due' ? 'default' :
                        case_.status === 'closed' ? 'secondary' :
                        'outline'
                      }
                    >
                      {case_.status}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Badge 
                      variant="outline"
                      className={
                        case_.oshaStatus === 'submitted' ? 'border-green-500 text-green-700' :
                        case_.oshaStatus === 'pending' ? 'border-orange-500 text-orange-700' :
                        ''
                      }
                    >
                      {case_.oshaStatus}
                    </Badge>
                  </TableCell>
                  <TableCell>{case_.days} days</TableCell>
                  <TableCell className="text-right">
                    <Button variant="ghost" size="sm">View</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>
    </div>
  );
}
