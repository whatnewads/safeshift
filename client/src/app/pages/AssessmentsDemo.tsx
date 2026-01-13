import { useState } from 'react';
import { ArrowLeft, Plus } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../components/ui/button';
import { MentalStatusAssessment } from '../components/assessments/MentalStatusAssessment';
import { NeurologicalAssessment } from '../components/assessments/NeurologicalAssessment';
import { SkinAssessment } from '../components/assessments/SkinAssessment';
import { HEENTAssessment } from '../components/assessments/HEENTAssessment';

export default function AssessmentsDemo() {
  const navigate = useNavigate();
  const [activeAssessments, setActiveAssessments] = useState<string[]>([]);
  const [showAddMenu, setShowAddMenu] = useState(false);

  const availableAssessments = [
    { id: 'mental-status', label: 'Mental Status', component: MentalStatusAssessment },
    { id: 'neurological', label: 'Neurological', component: NeurologicalAssessment },
    { id: 'skin', label: 'Skin', component: SkinAssessment },
    { id: 'heent', label: 'HEENT', component: HEENTAssessment },
  ];

  const addAssessment = (id: string) => {
    if (!activeAssessments.includes(id)) {
      setActiveAssessments([...activeAssessments, id]);
    }
    setShowAddMenu(false);
  };

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900">
      {/* Header */}
      <div className="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-10">
        <div className="max-w-5xl mx-auto px-6 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => navigate('/dashboard')}
              >
                <ArrowLeft className="h-4 w-4 mr-2" />
                Back to Dashboard
              </Button>
              <div>
                <h1 className="text-xl font-semibold dark:text-white">Secondary Assessment</h1>
                <p className="text-sm text-slate-600 dark:text-slate-400">
                  Fast, structured clinical documentation
                </p>
              </div>
            </div>
            
            <div className="relative">
              <Button
                onClick={() => setShowAddMenu(!showAddMenu)}
                className="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800"
              >
                <Plus className="h-4 w-4 mr-2" />
                Add Assessment
              </Button>
              
              {showAddMenu && (
                <div className="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl z-20">
                  <div className="p-2">
                    <p className="text-xs font-semibold text-slate-500 dark:text-slate-400 px-3 py-2">
                      Quick Assessment
                    </p>
                    {availableAssessments.map((assessment) => (
                      <button
                        key={assessment.id}
                        onClick={() => addAssessment(assessment.id)}
                        disabled={activeAssessments.includes(assessment.id)}
                        className={`w-full text-left px-3 py-2 rounded-md text-sm transition-colors ${
                          activeAssessments.includes(assessment.id)
                            ? 'text-slate-400 dark:text-slate-600 cursor-not-allowed'
                            : 'hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300'
                        }`}
                      >
                        {assessment.label}
                        {activeAssessments.includes(assessment.id) && (
                          <span className="ml-2 text-xs">✓ Added</span>
                        )}
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Content */}
      <div className="max-w-5xl mx-auto px-6 py-8">
        {activeAssessments.length === 0 ? (
          <div className="text-center py-16">
            <div className="bg-white dark:bg-slate-800 rounded-lg border-2 border-dashed border-slate-300 dark:border-slate-700 p-12">
              <div className="max-w-md mx-auto">
                <div className="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                  <Plus className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                </div>
                <h3 className="text-lg font-semibold mb-2 dark:text-white">No Assessments Added</h3>
                <p className="text-slate-600 dark:text-slate-400 mb-6">
                  Click "Add Assessment" to start documenting system-specific findings.
                </p>
                <Button
                  onClick={() => setShowAddMenu(true)}
                  className="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800"
                >
                  <Plus className="h-4 w-4 mr-2" />
                  Add Your First Assessment
                </Button>
              </div>
            </div>
          </div>
        ) : (
          <div className="space-y-6">
            {activeAssessments.map((id) => {
              const assessment = availableAssessments.find((a) => a.id === id);
              if (!assessment) return null;
              const Component = assessment.component;
              return <Component key={id} />;
            })}
          </div>
        )}

        {/* Quick Tips */}
        {activeAssessments.length > 0 && (
          <div className="mt-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <h4 className="font-semibold text-blue-900 dark:text-blue-300 mb-3">
              💡 Quick Tips
            </h4>
            <ul className="space-y-2 text-sm text-blue-800 dark:text-blue-300">
              <li>• Select "No Abnormalities" to auto-fill normal defaults</li>
              <li>• Select "Abnormal" to access detailed assessment fields</li>
              <li>• Select "Not Assessed" if section wasn't evaluated</li>
              <li>• Assessments auto-generate clean narrative summaries</li>
              <li>• All documentation uses observable, non-diagnostic language</li>
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}
