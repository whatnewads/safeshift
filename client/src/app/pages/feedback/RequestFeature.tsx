import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Textarea } from '../../components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../components/ui/select';
import { Lightbulb, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';

export default function RequestFeaturePage() {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    title: '',
    priority: '',
    category: '',
    description: '',
    useCases: '',
    businessImpact: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    // Mock submission
    toast.success('Feature request submitted successfully!');
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
          <Lightbulb className="h-8 w-8 text-yellow-600" />
          <h1 className="text-3xl font-bold">Request a Feature</h1>
        </div>
        <p className="text-slate-600">
          Share your ideas to help us build a better product
        </p>
      </div>

      <Card className="p-6">
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="space-y-2">
            <Label htmlFor="title">Feature Title *</Label>
            <Input
              id="title"
              placeholder="Brief description of the feature"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              required
            />
          </div>

          <div className="grid md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="priority">Priority *</Label>
              <Select
                value={formData.priority}
                onValueChange={(value) => setFormData({ ...formData, priority: value })}
                required
              >
                <SelectTrigger id="priority">
                  <SelectValue placeholder="Select priority" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="critical">Critical - Blocking Work</SelectItem>
                  <SelectItem value="high">High - Important Enhancement</SelectItem>
                  <SelectItem value="medium">Medium - Would be Nice</SelectItem>
                  <SelectItem value="low">Low - Future Consideration</SelectItem>
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
                  <SelectItem value="dashboard">Dashboard</SelectItem>
                  <SelectItem value="reports">Reports & Analytics</SelectItem>
                  <SelectItem value="ai">AI Capabilities</SelectItem>
                  <SelectItem value="integration">Integrations</SelectItem>
                  <SelectItem value="mobile">Mobile Experience</SelectItem>
                  <SelectItem value="compliance">Compliance Tools</SelectItem>
                  <SelectItem value="other">Other</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Detailed Description *</Label>
            <Textarea
              id="description"
              placeholder="Describe the feature you'd like to see"
              rows={4}
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              required
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="useCases">Use Cases *</Label>
            <Textarea
              id="useCases"
              placeholder="How would you use this feature? What problems would it solve?"
              rows={4}
              value={formData.useCases}
              onChange={(e) => setFormData({ ...formData, useCases: e.target.value })}
              required
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="businessImpact">Business Impact</Label>
            <Textarea
              id="businessImpact"
              placeholder="How would this feature benefit your workflow, team, or organization?"
              rows={3}
              value={formData.businessImpact}
              onChange={(e) => setFormData({ ...formData, businessImpact: e.target.value })}
            />
          </div>

          <div className="flex gap-3 pt-4">
            <Button type="submit" size="lg">
              <Lightbulb className="h-4 w-4 mr-2" />
              Submit Feature Request
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
