import { useState, useEffect } from 'react';
import { AssessmentPanel, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import type { AssessmentStatus } from './AssessmentPanel.js';


interface BackData {
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
  symptoms: {
    tingling: boolean;
    numbness: boolean;
  };
  spine: {
    scoliosis: boolean;
    tenderness: boolean;
    deformity: boolean;
  };
  pastMedicalNotes: string;
  notes: string;
  bodyFindings: any[];
}

const defaultData: BackData = {
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
  symptoms: {
    tingling: false,
    numbness: false,
  },
  spine: {
    scoliosis: false,
    tenderness: false,
    deformity: false,
  },
  pastMedicalNotes: '',
  notes: '',
  bodyFindings: [],
};

export function BackAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<BackData>(defaultData);

  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  const summary = 'Back without tenderness, deformity, or neurological symptoms.';

  return (
    <AssessmentPanel
      title="Back"
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
          {/* Symptoms */}
          <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
            <Label className="mb-3 block text-sm font-medium">Symptoms</Label>
            <div className="space-y-2">
              <CheckboxField
                label="Tingling"
                checked={data.symptoms.tingling}
                onChange={(checked) =>
                  setData({ ...data, symptoms: { ...data.symptoms, tingling: checked } })
                }
              />
              <CheckboxField
                label="Numbness"
                checked={data.symptoms.numbness}
                onChange={(checked) =>
                  setData({ ...data, symptoms: { ...data.symptoms, numbness: checked } })
                }
              />
            </div>
          </div>

          {/* Spine */}
          <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
            <Label className="mb-3 block text-sm font-medium">Spine</Label>
            <div className="space-y-2">
              <CheckboxField
                label="Scoliosis"
                checked={data.spine.scoliosis}
                onChange={(checked) =>
                  setData({ ...data, spine: { ...data.spine, scoliosis: checked } })
                }
              />
              <CheckboxField
                label="Tenderness"
                checked={data.spine.tenderness}
                onChange={(checked) =>
                  setData({ ...data, spine: { ...data.spine, tenderness: checked } })
                }
              />
              <CheckboxField
                label="Deformity"
                checked={data.spine.deformity}
                onChange={(checked) =>
                  setData({ ...data, spine: { ...data.spine, deformity: checked } })
                }
              />
            </div>
          </div>
        </div>

        {/* Past Medical History */}
        <div>
          <Label className="mb-2 block">Past Medical History (Back Issues)</Label>
          <textarea
            value={data.pastMedicalNotes}
            onChange={(e) => setData({ ...data, pastMedicalNotes: e.target.value })}
            placeholder="Document any prior back injuries, surgeries, chronic conditions..."
            rows={3}
            maxLength={300}
            className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
          />
          <p className="text-xs text-slate-500 dark:text-slate-500 mt-1">
            {data.pastMedicalNotes.length}/300 characters
          </p>
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