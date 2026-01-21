/**
 * AssessmentModal.tsx
 * 
 * A redesigned assessment modal with tab-based navigation replacing the side menu.
 * Features:
 * - Human body diagram for clicking on body regions (left side)
 * - Horizontal tab bar for assessment categories
 * - Bidirectional sync between body diagram and tabs
 * 
 * @author SafeShift EHR Team
 */

import { useState, useEffect } from 'react';
import {
  Eye,
  Heart,
  Circle,
  ArrowUp,
  Hand,
  Brain,
  SmilePlus,
  Layers,
  Target,
  X,
  CheckCircle2,
  AlertCircle,
} from 'lucide-react';
import { Button } from '../ui/button';
import { Card } from '../ui/card';

// Assessment component imports
import { MentalStatusAssessment } from './MentalStatusAssessment';
import { NeurologicalAssessment } from './NeurologicalAssessment';
import { SkinAssessment } from './SkinAssessment';
import { HEENTAssessment } from './HEENTAssessment';
import { ChestAssessment } from './ChestAssessment';
import { AbdomenAssessment } from './AbdomenAssessment';
import { BackAssessment } from './BackAssessment';
import { PelvisGUGIAssessment } from './PelvisGUGIAssessment';
import { ExtremitiesAssessment } from './ExtremitiesAssessment';

// Types
export type AssessmentTabId = 
  | 'heent' 
  | 'chest' 
  | 'abdomen' 
  | 'back' 
  | 'extremities' 
  | 'neurological' 
  | 'mentalStatus' 
  | 'skin' 
  | 'pelvisGUI';

export type AssessmentStatus = 'not-assessed' | 'normal' | 'abnormal';

interface AssessmentRegions {
  heent: AssessmentStatus;
  chest: AssessmentStatus;
  abdomen: AssessmentStatus;
  back: AssessmentStatus;
  extremities: AssessmentStatus;
  neurological: AssessmentStatus;
  mentalStatus: AssessmentStatus;
  skin: AssessmentStatus;
  pelvisGUI: AssessmentStatus;
}

// Data structure for individual region assessment
export interface RegionAssessmentData {
  status: AssessmentStatus;
  notes: string;
  timestamp: string;
}

// All regions assessment data
export type AllRegionsData = Record<string, RegionAssessmentData>;

interface AssessmentModalProps {
  isOpen: boolean;
  onClose: () => void;
  // Legacy props for backwards compatibility
  assessment?: {
    id: string;
    time: string;
    regions: AssessmentRegions;
  };
  onUpdate?: (region: string, value: string) => void;
  // New props for ObjectiveFindingsTab integration
  encounterId?: string;
  existingAssessments?: AllRegionsData;
  // Updated onSave to optionally accept data
  onSave?: (data?: AllRegionsData) => void;
}

// Tab configuration
const assessmentTabs: Array<{
  id: AssessmentTabId;
  label: string;
  shortLabel: string;
  icon: React.ElementType;
  bodyRegions: string[];
}> = [
  { 
    id: 'heent', 
    label: 'HEENT', 
    shortLabel: 'HEENT',
    icon: Eye,
    bodyRegions: ['head', 'neck']
  },
  { 
    id: 'chest', 
    label: 'Chest/Cardiovascular', 
    shortLabel: 'Chest',
    icon: Heart,
    bodyRegions: ['chest-upper-left', 'chest-upper-right', 'chest-lower-left', 'chest-lower-right']
  },
  { 
    id: 'abdomen', 
    label: 'Abdomen', 
    shortLabel: 'Abdomen',
    icon: Circle,
    bodyRegions: ['abdomen-upper-left', 'abdomen-upper-right', 'abdomen-lower-left', 'abdomen-lower-right']
  },
  { 
    id: 'back', 
    label: 'Back/Spine', 
    shortLabel: 'Back',
    icon: ArrowUp,
    bodyRegions: ['upper-back', 'spine', 'lower-back']
  },
  { 
    id: 'extremities', 
    label: 'Extremities', 
    shortLabel: 'Extrem.',
    icon: Hand,
    bodyRegions: [
      'left-shoulder', 'right-shoulder',
      'left-arm', 'right-arm',
      'left-elbow', 'right-elbow',
      'left-forearm', 'right-forearm',
      'left-hand', 'right-hand',
      'left-thigh', 'right-thigh',
      'left-knee', 'right-knee',
      'left-leg', 'right-leg',
      'left-ankle', 'right-ankle',
      'left-foot', 'right-foot'
    ]
  },
  { 
    id: 'neurological', 
    label: 'Neurological', 
    shortLabel: 'Neuro',
    icon: Brain,
    bodyRegions: ['head']
  },
  { 
    id: 'mentalStatus', 
    label: 'Mental Status', 
    shortLabel: 'Mental',
    icon: SmilePlus,
    bodyRegions: ['head']
  },
  { 
    id: 'skin', 
    label: 'Skin', 
    shortLabel: 'Skin',
    icon: Layers,
    bodyRegions: [] // Skin can be anywhere
  },
  { 
    id: 'pelvisGUI', 
    label: 'Pelvis/GU/GI', 
    shortLabel: 'Pelvis',
    icon: Target,
    bodyRegions: ['pelvis']
  },
];

// Body region data for the SVG diagram
const bodyRegionMap: Record<string, { x: number; y: number; label: string; tab: AssessmentTabId }> = {
  'head': { x: 50, y: 10, label: 'Head', tab: 'heent' },
  'neck': { x: 50, y: 18, label: 'Neck', tab: 'heent' },
  'chest-upper-left': { x: 40, y: 28, label: 'Chest Upper Left', tab: 'chest' },
  'chest-upper-right': { x: 60, y: 28, label: 'Chest Upper Right', tab: 'chest' },
  'chest-lower-left': { x: 40, y: 35, label: 'Chest Lower Left', tab: 'chest' },
  'chest-lower-right': { x: 60, y: 35, label: 'Chest Lower Right', tab: 'chest' },
  'abdomen-upper-left': { x: 40, y: 42, label: 'Abdomen Upper Left', tab: 'abdomen' },
  'abdomen-upper-right': { x: 60, y: 42, label: 'Abdomen Upper Right', tab: 'abdomen' },
  'abdomen-lower-left': { x: 40, y: 49, label: 'Abdomen Lower Left', tab: 'abdomen' },
  'abdomen-lower-right': { x: 60, y: 49, label: 'Abdomen Lower Right', tab: 'abdomen' },
  'upper-back': { x: 50, y: 28, label: 'Upper Back', tab: 'back' },
  'spine': { x: 50, y: 42, label: 'Spine', tab: 'back' },
  'lower-back': { x: 50, y: 49, label: 'Lower Back', tab: 'back' },
  'pelvis': { x: 50, y: 56, label: 'Pelvis', tab: 'pelvisGUI' },
  'left-shoulder': { x: 35, y: 22, label: 'Left Shoulder', tab: 'extremities' },
  'right-shoulder': { x: 65, y: 22, label: 'Right Shoulder', tab: 'extremities' },
  'left-arm': { x: 30, y: 35, label: 'Left Arm', tab: 'extremities' },
  'right-arm': { x: 70, y: 35, label: 'Right Arm', tab: 'extremities' },
  'left-elbow': { x: 25, y: 42, label: 'Left Elbow', tab: 'extremities' },
  'right-elbow': { x: 75, y: 42, label: 'Right Elbow', tab: 'extremities' },
  'left-forearm': { x: 25, y: 49, label: 'Left Forearm', tab: 'extremities' },
  'right-forearm': { x: 75, y: 49, label: 'Right Forearm', tab: 'extremities' },
  'left-hand': { x: 25, y: 56, label: 'Left Hand', tab: 'extremities' },
  'right-hand': { x: 75, y: 56, label: 'Right Hand', tab: 'extremities' },
  'left-thigh': { x: 43, y: 63, label: 'Left Thigh', tab: 'extremities' },
  'right-thigh': { x: 57, y: 63, label: 'Right Thigh', tab: 'extremities' },
  'left-knee': { x: 43, y: 70, label: 'Left Knee', tab: 'extremities' },
  'right-knee': { x: 57, y: 70, label: 'Right Knee', tab: 'extremities' },
  'left-leg': { x: 43, y: 77, label: 'Left Leg', tab: 'extremities' },
  'right-leg': { x: 57, y: 77, label: 'Right Leg', tab: 'extremities' },
  'left-ankle': { x: 43, y: 84, label: 'Left Ankle', tab: 'extremities' },
  'right-ankle': { x: 57, y: 84, label: 'Right Ankle', tab: 'extremities' },
  'left-foot': { x: 43, y: 91, label: 'Left Foot', tab: 'extremities' },
  'right-foot': { x: 57, y: 91, label: 'Right Foot', tab: 'extremities' },
};

export function AssessmentModal({
  isOpen,
  onClose,
  assessment,
  onUpdate,
  encounterId: _encounterId,
  existingAssessments,
  onSave,
}: AssessmentModalProps) {
  const [activeTab, setActiveTab] = useState<AssessmentTabId>('heent');
  const [highlightedRegions, setHighlightedRegions] = useState<string[]>([]);
  
  // Internal state for managing regions when using new props
  const [internalRegions, setInternalRegions] = useState<AllRegionsData>(() => {
    // Initialize from existingAssessments if provided
    if (existingAssessments) {
      return existingAssessments;
    }
    // Initialize with all regions as not-assessed
    const initial: AllRegionsData = {};
    assessmentTabs.forEach(tab => {
      initial[tab.id] = {
        status: 'not-assessed',
        notes: '',
        timestamp: new Date().toISOString(),
      };
    });
    return initial;
  });

  // Determine if we're in "new mode" (using existingAssessments) or "legacy mode" (using assessment prop)
  const isNewMode = !assessment && (existingAssessments !== undefined || !onUpdate);

  // Update highlighted regions when tab changes
  useEffect(() => {
    const currentTab = assessmentTabs.find(t => t.id === activeTab);
    if (currentTab) {
      setHighlightedRegions(currentTab.bodyRegions);
    }
  }, [activeTab]);

  // Update internal state when existingAssessments changes
  useEffect(() => {
    if (existingAssessments && isNewMode) {
      setInternalRegions(existingAssessments);
    }
  }, [existingAssessments, isNewMode]);

  if (!isOpen) return null;

  // Handle clicking on a body region
  const handleBodyRegionClick = (regionId: string) => {
    const region = bodyRegionMap[regionId];
    if (region) {
      setActiveTab(region.tab);
    }
  };

  // Get status for a region - handles both legacy and new mode
  const getRegionStatus = (tabId: AssessmentTabId): AssessmentStatus => {
    if (isNewMode) {
      return internalRegions[tabId]?.status || 'not-assessed';
    }
    // Legacy mode: use assessment.regions
    return assessment?.regions?.[tabId] as AssessmentStatus || 'not-assessed';
  };

  // Get color for region based on status
  const getRegionColor = (regionId: string, isHighlighted: boolean) => {
    const region = bodyRegionMap[regionId];
    if (!region) return { fill: '#94A3B8', stroke: '#475569' };
    
    const status = getRegionStatus(region.tab);
    
    if (isHighlighted) {
      return { fill: '#3B82F6', stroke: '#1E40AF' }; // Blue for highlighted
    }
    
    switch (status) {
      case 'normal':
        return { fill: '#22C55E', stroke: '#166534' }; // Green for normal
      case 'abnormal':
        return { fill: '#EF4444', stroke: '#991B1B' }; // Red for abnormal
      default:
        return { fill: '#94A3B8', stroke: '#475569' }; // Gray for not assessed
    }
  };

  // Get status icon for tab
  const getStatusIcon = (tabId: AssessmentTabId) => {
    const status = getRegionStatus(tabId);
    switch (status) {
      case 'normal':
        return <CheckCircle2 className="h-3 w-3 text-green-500" />;
      case 'abnormal':
        return <AlertCircle className="h-3 w-3 text-red-500" />;
      default:
        return null;
    }
  };

  // Handle save and close
  const handleSave = () => {
    if (onSave) {
      if (isNewMode) {
        // Pass internal regions data to the callback
        onSave(internalRegions);
      } else {
        // Legacy mode - call without data
        onSave();
      }
    }
    onClose();
  };

  // Handle marking region status
  const handleMarkStatus = (status: string) => {
    if (isNewMode) {
      // Update internal state
      const newStatus: AssessmentStatus =
        status === 'No Abnormalities' ? 'normal' :
        status === 'Assessed' ? 'abnormal' : 'not-assessed';
      
      setInternalRegions(prev => ({
        ...prev,
        [activeTab]: {
          ...prev[activeTab],
          status: newStatus,
          timestamp: new Date().toISOString(),
        }
      }));
    } else if (onUpdate) {
      // Legacy mode - call onUpdate
      onUpdate(activeTab, status);
    }
  };

  // Get current time for display
  const displayTime = assessment?.time || new Date().toLocaleString();

  return (
    <div className="fixed inset-0 bg-black/60 dark:bg-black/70 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-6xl max-h-[90vh] flex flex-col border border-slate-200 dark:border-slate-600">
        {/* Header */}
        <div className="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-t-lg flex-shrink-0">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-xl font-semibold dark:text-white">Physical Assessment</h2>
              <p className="text-sm text-slate-600 dark:text-slate-400 mt-1">
                Assessment Time: {displayTime}
              </p>
            </div>
            <Button variant="ghost" size="sm" onClick={onClose}>
              <X className="h-5 w-5" />
            </Button>
          </div>
        </div>

        {/* Main Content */}
        <div className="flex flex-1 min-h-0 overflow-hidden">
          {/* Left Side - Body Diagram */}
          <div className="w-1/3 border-r border-slate-200 dark:border-slate-700 p-4 flex flex-col">
            <h3 className="text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">
              Click on body region to assess
            </h3>
            <div className="flex-1 bg-slate-100 dark:bg-slate-700 rounded-lg p-4 overflow-auto">
              <svg width="100%" height="400" viewBox="0 0 100 100" className="overflow-visible">
                {/* Body outline */}
                <ellipse cx="50" cy="12" rx="8" ry="10" fill="#FDE68A" stroke="#92400E" strokeWidth="0.5" />
                <rect x="48" y="20" width="4" height="5" fill="#FDE68A" stroke="#92400E" strokeWidth="0.5" />
                <ellipse cx="50" cy="40" rx="15" ry="18" fill="#FDE68A" stroke="#92400E" strokeWidth="0.5" />
                <line x1="35" y1="25" x2="20" y2="60" stroke="#FDE68A" strokeWidth="4" strokeLinecap="round" />
                <line x1="65" y1="25" x2="80" y2="60" stroke="#FDE68A" strokeWidth="4" strokeLinecap="round" />
                <line x1="45" y1="56" x2="43" y2="95" stroke="#FDE68A" strokeWidth="5" strokeLinecap="round" />
                <line x1="55" y1="56" x2="57" y2="95" stroke="#FDE68A" strokeWidth="5" strokeLinecap="round" />

                {/* Interactive regions */}
                {Object.entries(bodyRegionMap).map(([regionId, region]) => {
                  const isHighlighted = highlightedRegions.includes(regionId);
                  const colors = getRegionColor(regionId, isHighlighted);
                  
                  return (
                    <g key={regionId}>
                      <circle
                        cx={region.x}
                        cy={region.y}
                        r={isHighlighted ? 3.5 : 3}
                        fill={colors.fill}
                        stroke={colors.stroke}
                        strokeWidth={isHighlighted ? 0.8 : 0.5}
                        className="cursor-pointer hover:opacity-80 transition-all duration-200"
                        onClick={() => handleBodyRegionClick(regionId)}
                      />
                      {/* Show status indicator for abnormal */}
                      {getRegionStatus(region.tab) === 'abnormal' && !isHighlighted && (
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
                  );
                })}
              </svg>
            </div>
            
            {/* Legend */}
            <div className="mt-4 text-xs text-slate-600 dark:text-slate-400 space-y-1">
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 rounded-full bg-slate-400"></div>
                <span>Not Assessed</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 rounded-full bg-green-500"></div>
                <span>No Abnormalities</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 rounded-full bg-red-500"></div>
                <span>Abnormalities Found</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-3 h-3 rounded-full bg-blue-500"></div>
                <span>Currently Selected</span>
              </div>
            </div>
          </div>

          {/* Right Side - Tabs and Content */}
          <div className="w-2/3 flex flex-col min-h-0">
            {/* Tab Bar */}
            <div className="flex-shrink-0 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
              <div className="flex overflow-x-auto scrollbar-thin scrollbar-thumb-slate-300 dark:scrollbar-thumb-slate-600">
                {assessmentTabs.map((tab) => {
                  const Icon = tab.icon;
                  const isActive = activeTab === tab.id;
                  const status = getRegionStatus(tab.id);
                  
                  return (
                    <button
                      key={tab.id}
                      onClick={() => setActiveTab(tab.id)}
                      className={`
                        flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium whitespace-nowrap
                        border-b-2 transition-colors min-w-fit
                        ${isActive
                          ? 'border-blue-600 text-blue-600 dark:text-blue-400 bg-blue-50/50 dark:bg-blue-900/20'
                          : 'border-transparent text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50'
                        }
                      `}
                    >
                      <Icon className="h-4 w-4" />
                      <span className="hidden sm:inline">{tab.shortLabel}</span>
                      {/* Status indicator */}
                      {status !== 'not-assessed' && (
                        <span className="ml-1">
                          {getStatusIcon(tab.id)}
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Quick Status Buttons */}
            <div className="flex-shrink-0 p-3 bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
              <div className="flex items-center gap-3">
                <span className="text-sm text-slate-600 dark:text-slate-400">Quick Mark:</span>
                <Button
                  size="sm"
                  variant={getRegionStatus(activeTab) === 'normal' ? 'default' : 'outline'}
                  className={getRegionStatus(activeTab) === 'normal' ? 'bg-green-600 hover:bg-green-700' : ''}
                  onClick={() => handleMarkStatus('No Abnormalities')}
                >
                  <CheckCircle2 className="h-4 w-4 mr-1" />
                  No Abnormalities
                </Button>
                <Button
                  size="sm"
                  variant={getRegionStatus(activeTab) === 'not-assessed' ? 'default' : 'outline'}
                  className={getRegionStatus(activeTab) === 'not-assessed' ? 'bg-slate-600 hover:bg-slate-700' : ''}
                  onClick={() => handleMarkStatus('Not Assessed')}
                >
                  Not Assessed
                </Button>
              </div>
            </div>

            {/* Tab Content */}
            <div className="flex-1 overflow-y-auto p-4">
              <Card className="p-4 border-0 shadow-none bg-transparent">
                {activeTab === 'heent' && <HEENTAssessment />}
                {activeTab === 'chest' && <ChestAssessment />}
                {activeTab === 'abdomen' && <AbdomenAssessment />}
                {activeTab === 'back' && <BackAssessment />}
                {activeTab === 'extremities' && <ExtremitiesAssessment />}
                {activeTab === 'neurological' && <NeurologicalAssessment />}
                {activeTab === 'mentalStatus' && <MentalStatusAssessment />}
                {activeTab === 'skin' && <SkinAssessment />}
                {activeTab === 'pelvisGUI' && <PelvisGUGIAssessment />}
              </Card>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex-shrink-0 p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-b-lg">
          <div className="flex items-center justify-between">
            {/* Assessment Summary */}
            <div className="flex items-center gap-4 text-sm">
              <span className="text-slate-600 dark:text-slate-400">Assessment Status:</span>
              {assessmentTabs.map((tab) => {
                const status = getRegionStatus(tab.id);
                return (
                  <div
                    key={tab.id}
                    className={`flex items-center gap-1 px-2 py-1 rounded text-xs ${
                      status === 'normal'
                        ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                        : status === 'abnormal'
                        ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                        : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400'
                    }`}
                  >
                    {tab.shortLabel}
                  </div>
                );
              })}
            </div>

            {/* Action Buttons */}
            <div className="flex gap-3">
              <Button variant="outline" onClick={onClose}>
                Cancel
              </Button>
              <Button 
                onClick={handleSave}
                className="bg-green-600 hover:bg-green-700 text-white"
              >
                Save Assessment
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default AssessmentModal;
