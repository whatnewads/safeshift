import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../components/ui/select';
import { Bug, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';

export default function SubmitBugPage() {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    title: '',
    severity: '',
    category: '',
    description: '',
    stepsToReproduce: '',
    expectedBehavior: '',
    actualBehavior: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    // Mock submission
    toast.success('Bug report submitted successfully!');
    navigate('/dashboard');
  };

  return (
    <div className="p-6 max-w-4xl mx-auto">
      <Button
        variant="ghost"
        className="mb-6"
        onClick={() => navigate(-1)}
      >
        <ArrowLeft className="h-4 w-4 mr-2" />
        Back
      </Button>

      <div className="mb-6">
        <div className="flex items-center gap-3 mb-2">
          <Bug className="h-8 w-8 text-red-600" />
          <h1 className="text-3xl font-bold">Submit a Bug Report</h1>
        </div>
        <p className="text-slate-600">
          Help us improve by reporting issues you encounter
        </p>
      </div>

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="space-y-2">
            <Label htmlFor="title">Bug Title *</Label>
            <Input
              id="title"
              placeholder="Brief description of the issue"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              required
            />
          </div>

          <div className="grid md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="severity">Severity *</Label>
              <Select
                value={formData.severity}
                onValueChange={(value) => setFormData({ ...formData, severity: value })}
                required
              >
                <SelectTrigger id="severity">
                  <SelectValue placeholder="Select severity" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="critical">Critical - System Down</SelectItem>
                  <SelectItem value="high">High - Major Feature Broken</SelectItem>
                  <SelectItem value="medium">Medium - Feature Impaired</SelectItem>
                  <SelectItem value="low">Low - Minor Issue</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="category">Category *</Label>
              <Select
                value={formData.category}
                onValueChange={(value) => setFormData({ ...formData, category: value })}
                required
              >
                <SelectTrigger id="category">
                  <SelectValue placeholder="Select category" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="encounter">Encounter Workspace</SelectItem>
                  <SelectItem value="authentication">Authentication</SelectItem>
                  <SelectItem value="dashboard">Dashboard</SelectItem>
                  <SelectItem value="sync">Offline Sync</SelectItem>
                  <SelectItem value="ai">AI Features</SelectItem>
                  <SelectItem value="reports">Reports</SelectItem>
                  <SelectItem value="other">Other</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description *</Label>
            <Textarea
              id="description"
              placeholder="Detailed description of the bug"
              rows={4}
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              required
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="stepsToReproduce">Steps to Reproduce *</Label>
            <Textarea
              id="stepsToReproduce"
              placeholder="1. Go to...&#10;2. Click on...&#10;3. See error..."
              rows={4}
              value={formData.stepsToReproduce}
              onChange={(e) => setFormData({ ...formData, stepsToReproduce: e.target.value })}
              required
            />
          </div>

          <div className="grid md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="expectedBehavior">Expected Behavior</Label>
              <Textarea
                id="expectedBehavior"
                placeholder="What should happen"
                rows={3}
                value={formData.expectedBehavior}
                onChange={(e) => setFormData({ ...formData, expectedBehavior: e.target.value })}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="actualBehavior">Actual Behavior</Label>
              <Textarea
                id="actualBehavior"
                placeholder="What actually happens"
                rows={3}
                value={formData.actualBehavior}
                onChange={(e) => setFormData({ ...formData, actualBehavior: e.target.value })}
              />
            </div>
          </div>

          <div className="flex gap-3 pt-4">
            <Button type="submit" size="lg">
              <Bug className="h-4 w-4 mr-2" />
              Submit Bug Report
            </Button>
            <Button
              type="button"
              variant="outline"
              size="lg"
              onClick={() => navigate(-1)}
            >
              Cancel
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
