import { useState } from 'react';
import { X } from 'lucide-react';
import { Button } from '../ui/button.js';
import { Card } from '../ui/card.js';
import { Label } from '../ui/label.js';

interface BodyFinding {
  region: string;
  findings: string[];
}

interface BodyModelModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (findings: BodyFinding[]) => void;
  initialFindings?: BodyFinding[];
}

const bodyRegions = [
  { id: 'head', label: 'Head', x: 50, y: 10 },
  { id: 'neck', label: 'Neck', x: 50, y: 18 },
  { id: 'chest-upper-left', label: 'Chest Upper Left', x: 40, y: 28 },
  { id: 'chest-upper-right', label: 'Chest Upper Right', x: 60, y: 28 },
  { id: 'chest-lower-left', label: 'Chest Lower Left', x: 40, y: 35 },
  { id: 'chest-lower-right', label: 'Chest Lower Right', x: 60, y: 35 },
  { id: 'abdomen-upper-left', label: 'Abdomen Upper Left', x: 40, y: 42 },
  { id: 'abdomen-upper-right', label: 'Abdomen Upper Right', x: 60, y: 42 },
  { id: 'abdomen-lower-left', label: 'Abdomen Lower Left', x: 40, y: 49 },
  { id: 'abdomen-lower-right', label: 'Abdomen Lower Right', x: 60, y: 49 },
  { id: 'upper-back', label: 'Upper Back', x: 50, y: 28 },
  { id: 'spine', label: 'Spine', x: 50, y: 42 },
  { id: 'lower-back', label: 'Lower Back', x: 50, y: 49 },
  { id: 'pelvis', label: 'Pelvis', x: 50, y: 56 },
  { id: 'left-shoulder', label: 'Left Shoulder', x: 35, y: 22 },
  { id: 'right-shoulder', label: 'Right Shoulder', x: 65, y: 22 },
  { id: 'left-arm', label: 'Left Arm', x: 30, y: 35 },
  { id: 'right-arm', label: 'Right Arm', x: 70, y: 35 },
  { id: 'left-elbow', label: 'Left Elbow', x: 25, y: 42 },
  { id: 'right-elbow', label: 'Right Elbow', x: 75, y: 42 },
  { id: 'left-forearm', label: 'Left Forearm', x: 25, y: 49 },
  { id: 'right-forearm', label: 'Right Forearm', x: 75, y: 49 },
  { id: 'left-hand', label: 'Left Hand', x: 25, y: 56 },
  { id: 'right-hand', label: 'Right Hand', x: 75, y: 56 },
  { id: 'left-thigh', label: 'Left Thigh', x: 43, y: 63 },
  { id: 'right-thigh', label: 'Right Thigh', x: 57, y: 63 },
  { id: 'left-knee', label: 'Left Knee', x: 43, y: 70 },
  { id: 'right-knee', label: 'Right Knee', x: 57, y: 70 },
  { id: 'left-leg', label: 'Left Leg', x: 43, y: 77 },
  { id: 'right-leg', label: 'Right Leg', x: 57, y: 77 },
  { id: 'left-ankle', label: 'Left Ankle', x: 43, y: 84 },
  { id: 'right-ankle', label: 'Right Ankle', x: 57, y: 84 },
  { id: 'left-foot', label: 'Left Foot', x: 43, y: 91 },
  { id: 'right-foot', label: 'Right Foot', x: 57, y: 91 },
];

const findingOptions = [
  'Deformities',
  'Contusions',
  'Abrasions',
  'Penetrating Trauma',
  'Burns',
  'Tenderness',
  'Lacerations',
  'Swelling',
];

export function BodyModelModal({ isOpen, onClose, onSave, initialFindings = [] }: BodyModelModalProps) {
  const [gender, setGender] = useState<'male' | 'female'>('male');
  const [selectedRegion, setSelectedRegion] = useState<string | null>(null);
  const [findings, setFindings] = useState<BodyFinding[]>(initialFindings);

  if (!isOpen) return null;

  const handleRegionClick = (regionId: string) => {
    setSelectedRegion(regionId);
  };

  const handleFindingToggle = (finding: string) => {
    if (!selectedRegion) return;

    const existingFinding = findings.find(f => f.region === selectedRegion);
    
    if (existingFinding) {
      if (existingFinding.findings.includes(finding)) {
        // Remove the finding
        const updatedFindings = findings.map(f => 
          f.region === selectedRegion 
            ? { ...f, findings: f.findings.filter(fi => fi !== finding) }
            : f
        ).filter(f => f.findings.length > 0);
        setFindings(updatedFindings);
      } else {
        // Add the finding
        const updatedFindings = findings.map(f => 
          f.region === selectedRegion 
            ? { ...f, findings: [...f.findings, finding] }
            : f
        );
        setFindings(updatedFindings);
      }
    } else {
      // Create new finding for this region
      setFindings([...findings, { region: selectedRegion, findings: [finding] }]);
    }
  };

  const getRegionFindings = (regionId: string) => {
    return findings.find(f => f.region === regionId)?.findings || [];
  };

  const hasFindings = (regionId: string) => {
    return getRegionFindings(regionId).length > 0;
  };

  const handleSave = () => {
    onSave(findings);
    onClose();
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <Card className="w-full max-w-6xl max-h-[90vh] overflow-y-auto bg-white dark:bg-slate-800">
        <div className="p-6">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-xl font-semibold">Body Assessment Model</h2>
            <Button variant="ghost" size="sm" onClick={onClose}>
              <X className="h-5 w-5" />
            </Button>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Body Model */}
            <div className="lg:col-span-2">
              <div className="mb-4 flex gap-3">
                <Button
                  variant={gender === 'male' ? 'default' : 'outline'}
                  onClick={() => setGender('male')}
                  size="sm"
                  className={gender === 'male' ? 'bg-blue-600 hover:bg-blue-700 text-white' : ''}
                >
                  Male
                </Button>
                <Button
                  variant={gender === 'female' ? 'default' : 'outline'}
                  onClick={() => setGender('female')}
                  size="sm"
                  className={gender === 'female' ? 'bg-pink-600 hover:bg-pink-700 text-white' : ''}
                >
                  Female
                </Button>
              </div>

              <div className="relative bg-slate-100 dark:bg-slate-700 rounded-lg p-4 min-h-[600px]">
                <svg width="100%" height="600" viewBox="0 0 100 100" className="overflow-visible">
                  {/* Simple body outline */}
                  <ellipse cx="50" cy="12" rx="8" ry="10" fill="#FDE68A" stroke="#92400E" strokeWidth="0.5" />
                  
                  {/* Neck */}
                  <rect x="48" y="20" width="4" height="5" fill="#FDE68A" stroke="#92400E" strokeWidth="0.5" />
                  
                  {/* Torso */}
                  <ellipse cx="50" cy="40" rx="15" ry="18" fill="#FDE68A" stroke="#92400E" strokeWidth="0.5" />
                  
                  {/* Arms */}
                  <line x1="35" y1="25" x2="20" y2="60" stroke="#FDE68A" strokeWidth="4" strokeLinecap="round" />
                  <line x1="65" y1="25" x2="80" y2="60" stroke="#FDE68A" strokeWidth="4" strokeLinecap="round" />
                  
                  {/* Legs */}
                  <line x1="45" y1="56" x2="43" y2="95" stroke="#FDE68A" strokeWidth="5" strokeLinecap="round" />
                  <line x1="55" y1="56" x2="57" y2="95" stroke="#FDE68A" strokeWidth="5" strokeLinecap="round" />

                  {/* Interactive regions */}
                  {bodyRegions.map((region) => (
                    <g key={region.id}>
                      <circle
                        cx={region.x}
                        cy={region.y}
                        r="3"
                        fill={selectedRegion === region.id ? '#3B82F6' : hasFindings(region.id) ? '#EF4444' : '#94A3B8'}
                        stroke={selectedRegion === region.id ? '#1E40AF' : hasFindings(region.id) ? '#991B1B' : '#475569'}
                        strokeWidth="0.5"
                        className="cursor-pointer hover:opacity-80 transition-opacity"
                        onClick={() => handleRegionClick(region.id)}
                      />
                      {hasFindings(region.id) && (
                        <text
                          x={region.x}
                          y={region.y}
                          textAnchor="middle"
                          dominantBaseline="central"
                          className="text-[4px] fill-white font-bold pointer-events-none"
                        >
                          !
                        </text>
                      )}
                    </g>
                  ))}
                </svg>

                <div className="mt-4 text-sm text-slate-600 dark:text-slate-400">
                  <p>Click on any body region to document findings</p>
                  <div className="flex gap-4 mt-2">
                    <div className="flex items-center gap-2">
                      <div className="w-3 h-3 rounded-full bg-slate-400"></div>
                      <span>No findings</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="w-3 h-3 rounded-full bg-red-500"></div>
                      <span>Has findings</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="w-3 h-3 rounded-full bg-blue-500"></div>
                      <span>Selected</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Findings Panel */}
            <div className="space-y-4">
              {selectedRegion ? (
                <>
                  <div>
                    <Label className="text-lg">
                      {bodyRegions.find(r => r.id === selectedRegion)?.label}
                    </Label>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                      Select all applicable findings
                    </p>
                  </div>

                  <div className="space-y-2">
                    {findingOptions.map((finding) => (
                      <label
                        key={finding}
                        className="flex items-center gap-2 p-3 rounded-lg border border-slate-200 dark:border-slate-600 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700"
                      >
                        <input
                          type="checkbox"
                          checked={getRegionFindings(selectedRegion).includes(finding)}
                          onChange={() => handleFindingToggle(finding)}
                          className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span className="text-sm">{finding}</span>
                      </label>
                    ))}
                  </div>

                  {getRegionFindings(selectedRegion).length > 0 && (
                    <div className="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                      <p className="text-sm font-medium text-red-800 dark:text-red-300 mb-2">
                        Active Findings:
                      </p>
                      <ul className="text-sm text-red-700 dark:text-red-400 space-y-1">
                        {getRegionFindings(selectedRegion).map((finding) => (
                          <li key={finding}>• {finding}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </>
              ) : (
                <div className="text-center text-slate-500 dark:text-slate-400 py-12">
                  <p>Select a body region to document findings</p>
                </div>
              )}
            </div>
          </div>

          <div className="flex gap-3 mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
            <Button
              onClick={handleSave}
              className="flex-1 bg-blue-600 hover:bg-blue-700 text-white"
            >
              Save Findings
            </Button>
            <Button
              variant="outline"
              onClick={onClose}
              className="flex-1"
            >
              Cancel
            </Button>
          </div>
        </div>
      </Card>
    </div>
  );
}
