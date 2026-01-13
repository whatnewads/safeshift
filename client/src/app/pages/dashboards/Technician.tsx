import { Card } from '../../components/ui/card.js';
import { Badge } from '../../components/ui/badge.js';
import { Button } from '../../components/ui/button.js';
import { TestTube, Activity, ClipboardCheck, Loader2 } from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '../../components/ui/table.js';
import { useTechnicianDashboard } from '../../hooks/useDashboard.js';

interface TaskItem {
  encounter_id?: string;
  encounterId?: string;
  patient_first_name?: string;
  patient_last_name?: string;
  patient?: string;
  taskType?: string;
  taskDescription?: string;
  chief_complaint?: string;
  chiefComplaint?: string;
  provider_id?: string;
  providerId?: string;
  status?: string;
}

export default function TechnicianDashboard() {
  const { stats, taskQueue, loading, error, refetch } = useTechnicianDashboard();

  // Get patient name from task
  const getPatientName = (task: TaskItem): string => {
    if (task.patient) return task.patient;
    if (task.patient_first_name || task.patient_last_name) {
      return `${task.patient_first_name || ''} ${task.patient_last_name || ''}`.trim();
    }
    return 'Unknown Patient';
  };

  // Get task type from task
  const getTaskType = (task: TaskItem): string => {
    return task.taskType || task.taskDescription || 'Vitals';
  };

  // Determine priority based on task type or status
  const getPriority = (task: TaskItem): 'high' | 'medium' | 'low' => {
    if (task.status === 'urgent' || task.taskType === 'critical') return 'high';
    if (task.taskType === 'vitals') return 'high';
    return 'medium';
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
        <h1 className="text-3xl font-bold mb-2">Technician Dashboard</h1>
        <p className="text-slate-600">Complete assigned tasks and procedures</p>
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
      <div className="grid md:grid-cols-3 gap-6">
        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-blue-100 flex items-center justify-center">
              <ClipboardCheck className="h-6 w-6 text-blue-600" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats?.pendingTasks ?? taskQueue.length}</p>
              <p className="text-sm text-slate-600">Tasks in Queue</p>
            </div>
          </div>
        </Card>

        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-green-100 flex items-center justify-center">
              <Activity className="h-6 w-6 text-green-600" />
            </div>
            <div>
              <p className="text-2xl font-bold">{stats?.completedToday ?? 0}</p>
              <p className="text-sm text-slate-600">Completed Today</p>
            </div>
          </div>
        </Card>

        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-purple-100 flex items-center justify-center">
              <TestTube className="h-6 w-6 text-purple-600" />
            </div>
            <div>
              <p className="text-2xl font-bold">{taskQueue.filter((t: TaskItem) => t.taskType === 'drug_screen').length}</p>
              <p className="text-sm text-slate-600">Tests Pending</p>
            </div>
          </div>
        </Card>
      </div>

      {/* Task Queue */}
      <Card className="p-6">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-semibold">Task Queue</h2>
          <Button variant="outline" size="sm" onClick={refetch}>
            Refresh
          </Button>
        </div>
        
        {loading ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
          </div>
        ) : taskQueue.length === 0 ? (
          <div className="text-center py-8 text-slate-500">
            No tasks in queue. Great job!
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Patient</TableHead>
                <TableHead>Task Type</TableHead>
                <TableHead>Details</TableHead>
                <TableHead>Priority</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {taskQueue.map((task: TaskItem, index: number) => {
                const priority = getPriority(task);
                return (
                  <TableRow key={task.encounter_id || task.encounterId || index}>
                    <TableCell className="font-medium">{getPatientName(task)}</TableCell>
                    <TableCell>{getTaskType(task)}</TableCell>
                    <TableCell className="text-slate-600">
                      {task.taskDescription || task.chief_complaint || task.chiefComplaint || '-'}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          priority === 'high' ? 'destructive' :
                          priority === 'medium' ? 'default' :
                          'secondary'
                        }
                      >
                        {priority}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button size="sm">Start Task</Button>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        )}
      </Card>
    </div>
  );
}
