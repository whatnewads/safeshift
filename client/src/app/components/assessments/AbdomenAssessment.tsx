import { useState, useEffect } from 'react';
import { AssessmentPanel, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import type { AssessmentStatus } from './AssessmentPanel.js';
 
interface AbdomenData {
  injuryFindings: {
    deformities: boolean;
    contusions: boolean;
    abrasions: boolean;
    penetratingTrauma: boolean;
    burns: boolean;
    tenderness: boolean;
    lacerations: boolean;
    swelling: boolean;
    discharge: boolean;
  };
  quadrants: {
    upperRight: {
      soft: boolean;
      nonTender: boolean;
      guarding: boolean;
      reboundTenderness: boolean;
      pulsatingMass: boolean;
      rigidity: boolean;
    };
    upperLeft: {
      soft: boolean;
      nonTender: boolean;
      guarding: boolean;
      reboundTenderness: boolean;
      pulsatingMass: boolean;
      rigidity: boolean;
    };
    lowerRight: {
      soft: boolean;
      nonTender: boolean;
      guarding: boolean;
      reboundTenderness: boolean;
      pulsatingMass: boolean;
      rigidity: boolean;
    };
    lowerLeft: {
      soft: boolean;
      nonTender: boolean;
      guarding: boolean;
      reboundTenderness: boolean;
      pulsatingMass: boolean;
      rigidity: boolean;
    };
  };
  notes: string;
  bodyFindings: any[];
}

const defaultData: AbdomenData = {
  injuryFindings: {
    deformities: false,
    contusions: false,
    abrasions: false,
    penetratingTrauma: false,
    burns: false,
    tenderness: false,
    lacerations: false,
    swelling: false,
    discharge: false,
  },
  quadrants: {
    upperRight: { soft: true, nonTender: true, guarding: false, reboundTenderness: false, pulsatingMass: false, rigidity: false },
    upperLeft: { soft: true, nonTender: true, guarding: false, reboundTenderness: false, pulsatingMass: false, rigidity: false },
    lowerRight: { soft: true, nonTender: true, guarding: false, reboundTenderness: false, pulsatingMass: false, rigidity: false },
    lowerLeft: { soft: true, nonTender: true, guarding: false, reboundTenderness: false, pulsatingMass: false, rigidity: false },
  },
  notes: '',
  bodyFindings: [],
};

export function AbdomenAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<AbdomenData>(defaultData);

  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  const handleQuadrantChange = (
    quadrant: keyof AbdomenData['quadrants'],
    field: keyof AbdomenData['quadrants']['upperRight'],
    value: boolean
  ) => {
    setData({
      ...data,
      quadrants: {
        ...data.quadrants,
        [quadrant]: {
          ...data.quadrants[quadrant],
          [field]: value,
        },
      },
    });
  };

  const QuadrantPanel = ({
    label,
    quadrant,
  }: {
    label: string;
    quadrant: keyof AbdomenData['quadrants'];
  }) => (
    <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
      <Label className="mb-3 block text-sm font-medium">{label}</Label>
      <div className="space-y-3">
        <div className="pb-2 border-b border-slate-200 dark:border-slate-600">
          <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">No Abnormalities:</p>
          <div className="space-y-2">
            <CheckboxField
              label="Soft"
              checked={data.quadrants[quadrant].soft}
              onChange={(checked) => handleQuadrantChange(quadrant, 'soft', checked)}
            />
            <CheckboxField
              label="Non-tender"
              checked={data.quadrants[quadrant].nonTender}
              onChange={(checked) => handleQuadrantChange(quadrant, 'nonTender', checked)}
            />
          </div>
        </div>
        <div>
          <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">Abnormal Findings:</p>
          <div className="space-y-2">
            <CheckboxField
              label="Guarding"
              checked={data.quadrants[quadrant].guarding}
              onChange={(checked) => handleQuadrantChange(quadrant, 'guarding', checked)}
            />
            <CheckboxField
              label="Rebound Tenderness"
              checked={data.quadrants[quadrant].reboundTenderness}
              onChange={(checked) => handleQuadrantChange(quadrant, 'reboundTenderness', checked)}
            />
            <CheckboxField
              label="Pulsating Mass"
              checked={data.quadrants[quadrant].pulsatingMass}
              onChange={(checked) => handleQuadrantChange(quadrant, 'pulsatingMass', checked)}
            />
            <CheckboxField
              label="Rigidity"
              checked={data.quadrants[quadrant].rigidity}
              onChange={(checked) => handleQuadrantChange(quadrant, 'rigidity', checked)}
            />
          </div>
        </div>
      </div>
    </div>
  );

  const summary = 'Abdomen soft, non-tender, no guarding or rebound tenderness.';

  return (
    <AssessmentPanel
      title="Abdomen"
      status={status}
      onStatusChange={setStatus}
      summary={summary}
      showBodyModel={true}
      onBodyFindings={(findings) => setData({ ...data, bodyFindings: findings })}
      bodyFindings={data.bodyFindings}
    >
      <div className="space-y-6">
        {/* Injury Findings */}
        <div>
          <Label className="mb-3 block text-base">Injury Findings</Label>
          <div className="space-y-2">
            <CheckboxField
              label="Deformities"
              checked={data.injuryFindings.deformities}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, deformities: checked }
              })}
            />
            <CheckboxField
              label="Contusions"
              checked={data.injuryFindings.contusions}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, contusions: checked }
              })}
            />
            <CheckboxField
              label="Abrasions"
              checked={data.injuryFindings.abrasions}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, abrasions: checked }
              })}
            />
            <CheckboxField
              label="Penetrating Trauma"
              checked={data.injuryFindings.penetratingTrauma}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, penetratingTrauma: checked }
              })}
            />
            <CheckboxField
              label="Burns"
              checked={data.injuryFindings.burns}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, burns: checked }
              })}
            />
            <CheckboxField
              label="Tenderness"
              checked={data.injuryFindings.tenderness}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, tenderness: checked }
              })}
            />
            <CheckboxField
              label="Lacerations"
              checked={data.injuryFindings.lacerations}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, lacerations: checked }
              })}
            />
            <CheckboxField
              label="Swelling"
              checked={data.injuryFindings.swelling}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, swelling: checked }
              })}
            />
            <CheckboxField
              label="Discharge"
              checked={data.injuryFindings.discharge}
              onChange={(checked) => setData({
                ...data,
                injuryFindings: { ...data.injuryFindings, discharge: checked }
              })}
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <QuadrantPanel label="Upper Right" quadrant="upperRight" />
          <QuadrantPanel label="Upper Left" quadrant="upperLeft" />
          <QuadrantPanel label="Lower Right" quadrant="lowerRight" />
          <QuadrantPanel label="Lower Left" quadrant="lowerLeft" />
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