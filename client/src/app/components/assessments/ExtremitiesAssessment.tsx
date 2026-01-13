import { useState, useEffect } from 'react';
import { AssessmentPanel, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import type { AssessmentStatus } from './AssessmentPanel.js';


interface ExtremityData {
  deformities: boolean;
  contusions: boolean;
  abrasions: boolean;
  penetratingTrauma: boolean;
  burns: boolean;
  tenderness: boolean;
  lacerations: boolean;
  swelling: boolean;
  discharge: boolean;
  tingling: boolean;
  numbness: boolean;
  muscularWeakness: boolean;
  atrophy: boolean;
  fullROM: boolean;
  pulsePresent: boolean;
  motorFunction: string;
  sensoryFunction: string;
}

interface ExtremitiesData {
  leftArm: ExtremityData;
  rightArm: ExtremityData;
  leftLeg: ExtremityData;
  rightLeg: ExtremityData;
  notes: string;
  bodyFindings: any[];
}

const defaultExtremityData: ExtremityData = {
  deformities: false,
  contusions: false,
  abrasions: false,
  penetratingTrauma: false,
  burns: false,
  tenderness: false,
  lacerations: false,
  swelling: false,
  discharge: false,
  tingling: false,
  numbness: false,
  muscularWeakness: false,
  atrophy: false,
  fullROM: true,
  pulsePresent: true,
  motorFunction: 'Normal',
  sensoryFunction: 'Normal',
};

const defaultData: ExtremitiesData = {
  leftArm: { ...defaultExtremityData },
  rightArm: { ...defaultExtremityData },
  leftLeg: { ...defaultExtremityData },
  rightLeg: { ...defaultExtremityData },
  notes: '',
  bodyFindings: [],
};

export function ExtremitiesAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<ExtremitiesData>(defaultData);

  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  const handleExtremityChange = (
    extremity: keyof Omit<ExtremitiesData, 'notes' | 'bodyFindings'>,
    field: keyof ExtremityData,
    value: boolean | string
  ) => {
    setData({
      ...data,
      [extremity]: {
        ...data[extremity],
        [field]: value,
      },
    });
  };

  const ExtremityPanel = ({
    label,
    extremity,
  }: {
    label: string;
    extremity: keyof Omit<ExtremitiesData, 'notes' | 'bodyFindings'>;
  }) => (
    <div className="border-0 bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
      <Label className="mb-3 block text-sm font-medium">{label}</Label>
      <div className="space-y-4">
        {/* Injury Findings */}
        <div>
          <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">Injury Findings:</p>
          <div className="space-y-2">
            <CheckboxField
              label="Deformities"
              checked={data[extremity].deformities}
              onChange={(checked) => handleExtremityChange(extremity, 'deformities', checked)}
            />
            <CheckboxField
              label="Contusions"
              checked={data[extremity].contusions}
              onChange={(checked) => handleExtremityChange(extremity, 'contusions', checked)}
            />
            <CheckboxField
              label="Abrasions"
              checked={data[extremity].abrasions}
              onChange={(checked) => handleExtremityChange(extremity, 'abrasions', checked)}
            />
            <CheckboxField
              label="Penetrating Trauma"
              checked={data[extremity].penetratingTrauma}
              onChange={(checked) => handleExtremityChange(extremity, 'penetratingTrauma', checked)}
            />
            <CheckboxField
              label="Burns"
              checked={data[extremity].burns}
              onChange={(checked) => handleExtremityChange(extremity, 'burns', checked)}
            />
            <CheckboxField
              label="Tenderness"
              checked={data[extremity].tenderness}
              onChange={(checked) => handleExtremityChange(extremity, 'tenderness', checked)}
            />
            <CheckboxField
              label="Lacerations"
              checked={data[extremity].lacerations}
              onChange={(checked) => handleExtremityChange(extremity, 'lacerations', checked)}
            />
            <CheckboxField
              label="Swelling"
              checked={data[extremity].swelling}
              onChange={(checked) => handleExtremityChange(extremity, 'swelling', checked)}
            />
            <CheckboxField
              label="Discharge"
              checked={data[extremity].discharge}
              onChange={(checked) => handleExtremityChange(extremity, 'discharge', checked)}
            />
          </div>
        </div>

        {/* Symptoms */}
        <div className="pt-3 border-t border-slate-200 dark:border-slate-600">
          <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">Symptoms:</p>
          <div className="flex flex-wrap gap-2">
            <CheckboxField
              label="Tingling"
              checked={data[extremity].tingling}
              onChange={(checked) => handleExtremityChange(extremity, 'tingling', checked)}
            />
            <CheckboxField
              label="Numbness"
              checked={data[extremity].numbness}
              onChange={(checked) => handleExtremityChange(extremity, 'numbness', checked)}
            />
            <CheckboxField
              label="Muscular Weakness"
              checked={data[extremity].muscularWeakness}
              onChange={(checked) => handleExtremityChange(extremity, 'muscularWeakness', checked)}
            />
            <CheckboxField
              label="Atrophy"
              checked={data[extremity].atrophy}
              onChange={(checked) => handleExtremityChange(extremity, 'atrophy', checked)}
            />
          </div>
        </div>

        {/* Physical Exam */}
        <div className="pt-3 border-t border-slate-200 dark:border-slate-600">
          <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">Physical Exam:</p>
          <div className="space-y-2">
            <CheckboxField
              label="Full ROM"
              checked={data[extremity].fullROM}
              onChange={(checked) => handleExtremityChange(extremity, 'fullROM', checked)}
            />
            <CheckboxField
              label="Pulse Present"
              checked={data[extremity].pulsePresent}
              onChange={(checked) => handleExtremityChange(extremity, 'pulsePresent', checked)}
            />
          </div>

          <div className="mt-3 space-y-2">
            <div>
              <Label className="text-xs mb-1 block">Motor Function</Label>
              <select
                value={data[extremity].motorFunction}
                onChange={(e) => handleExtremityChange(extremity, 'motorFunction', e.target.value)}
                className="w-full px-2 py-1.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-xs"
              >
                <option value="Normal">Normal</option>
                <option value="Reduced">Reduced</option>
                <option value="Absent">Absent</option>
              </select>
            </div>

            <div>
              <Label className="text-xs mb-1 block">Sensory Function</Label>
              <select
                value={data[extremity].sensoryFunction}
                onChange={(e) => handleExtremityChange(extremity, 'sensoryFunction', e.target.value)}
                className="w-full px-2 py-1.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-xs"
              >
                <option value="Normal">Normal</option>
                <option value="Reduced">Reduced</option>
                <option value="Absent">Absent</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
  );

  const summary = 'All extremities with full ROM, pulses present, motor and sensory function intact.';

  return (
    <AssessmentPanel
      title="Extremities"
      status={status}
      onStatusChange={setStatus}
      summary={summary}
      showBodyModel={true}
      onBodyFindings={(findings) => setData({ ...data, bodyFindings: findings })}
      bodyFindings={data.bodyFindings}
    >
      <div className="space-y-6">
        <div className="grid grid-cols-2 gap-4">
          <ExtremityPanel label="Left Arm" extremity="leftArm" />
          <ExtremityPanel label="Right Arm" extremity="rightArm" />
          <ExtremityPanel label="Left Leg" extremity="leftLeg" />
          <ExtremityPanel label="Right Leg" extremity="rightLeg" />
        </div>

        {/* Additional Notes */}
        <div>
          <Label className="mb-2 block">Additional Notes (Optional)</Label>
          <textarea
            value={data.notes}
            onChange={(e) => setData({ ...data, notes: e.target.value })}
            placeholder="Additional observations..."
            rows={2}
            maxLength={200}
            className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
          />
          <p className="text-xs text-slate-500 dark:text-slate-500 mt-1">
            {data.notes.length}/200 characters
          </p>
        </div>
      </div>
    </AssessmentPanel>
  );
}