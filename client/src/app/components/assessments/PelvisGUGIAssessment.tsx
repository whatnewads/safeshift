import { useState, useEffect } from 'react';
import { AssessmentPanel, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import type { AssessmentStatus } from './AssessmentPanel.js';


interface PelvisGUGIData {
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
  gi: {
    nausea: boolean;
    vomiting: boolean;
    bloodInVomit: boolean;
    diarrhea: boolean;
    constipation: boolean;
    bloodInStool: boolean;
  };
  gu: {
    frequentUrination: boolean;
    infrequentUrination: boolean;
    incontinence: boolean;
    bloodInUrine: boolean;
  };
  reproductive: {
    abnormalDischarge: boolean;
    pain: boolean;
    bleeding: boolean;
  };
  trauma: {
    instabilityLeft: boolean;
    instabilityRight: boolean;
    instabilityBoth: boolean;
  };
  notes: string;
  bodyFindings: any[];
}

const defaultData: PelvisGUGIData = {
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
  gi: {
    nausea: false,
    vomiting: false,
    bloodInVomit: false,
    diarrhea: false,
    constipation: false,
    bloodInStool: false,
  },
  gu: {
    frequentUrination: false,
    infrequentUrination: false,
    incontinence: false,
    bloodInUrine: false,
  },
  reproductive: {
    abnormalDischarge: false,
    pain: false,
    bleeding: false,
  },
  trauma: {
    instabilityLeft: false,
    instabilityRight: false,
    instabilityBoth: false,
  },
  notes: '',
  bodyFindings: [],
};

export function PelvisGUGIAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<PelvisGUGIData>(defaultData);

  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  const summary = 'No GI/GU complaints. Pelvis stable without tenderness.';

  return (
    <AssessmentPanel
      title="Pelvis / GU / GI"
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

        {/* GI Assessment */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
          <Label className="mb-3 block text-base font-medium">Gastrointestinal (GI)</Label>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <CheckboxField
                label="Nausea"
                checked={data.gi.nausea}
                onChange={(checked) =>
                  setData({ ...data, gi: { ...data.gi, nausea: checked } })
                }
              />
              <CheckboxField
                label="Vomiting"
                checked={data.gi.vomiting}
                onChange={(checked) =>
                  setData({ ...data, gi: { ...data.gi, vomiting: checked } })
                }
              />
              <CheckboxField
                label="Blood in Vomit"
                checked={data.gi.bloodInVomit}
                onChange={(checked) =>
                  setData({ ...data, gi: { ...data.gi, bloodInVomit: checked } })
                }
              />
            </div>
            <div className="space-y-2">
              <CheckboxField
                label="Diarrhea"
                checked={data.gi.diarrhea}
                onChange={(checked) =>
                  setData({ ...data, gi: { ...data.gi, diarrhea: checked } })
                }
              />
              <CheckboxField
                label="Constipation"
                checked={data.gi.constipation}
                onChange={(checked) =>
                  setData({ ...data, gi: { ...data.gi, constipation: checked } })
                }
              />
              <CheckboxField
                label="Blood in Stool"
                checked={data.gi.bloodInStool}
                onChange={(checked) =>
                  setData({ ...data, gi: { ...data.gi, bloodInStool: checked } })
                }
              />
            </div>
          </div>
        </div>

        {/* GU Assessment */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
          <Label className="mb-3 block text-base font-medium">Genitourinary (GU)</Label>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <CheckboxField
                label="Frequent Urination"
                checked={data.gu.frequentUrination}
                onChange={(checked) =>
                  setData({ ...data, gu: { ...data.gu, frequentUrination: checked } })
                }
              />
              <CheckboxField
                label="Infrequent Urination"
                checked={data.gu.infrequentUrination}
                onChange={(checked) =>
                  setData({ ...data, gu: { ...data.gu, infrequentUrination: checked } })
                }
              />
            </div>
            <div className="space-y-2">
              <CheckboxField
                label="Incontinence"
                checked={data.gu.incontinence}
                onChange={(checked) =>
                  setData({ ...data, gu: { ...data.gu, incontinence: checked } })
                }
              />
              <CheckboxField
                label="Blood in Urine"
                checked={data.gu.bloodInUrine}
                onChange={(checked) =>
                  setData({ ...data, gu: { ...data.gu, bloodInUrine: checked } })
                }
              />
            </div>
          </div>
        </div>

        {/* Reproductive Assessment */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
          <Label className="mb-3 block text-base font-medium">Reproductive</Label>
          <div className="grid grid-cols-3 gap-2">
            <CheckboxField
              label="Abnormal Discharge"
              checked={data.reproductive.abnormalDischarge}
              onChange={(checked) =>
                setData({ ...data, reproductive: { ...data.reproductive, abnormalDischarge: checked } })
              }
            />
            <CheckboxField
              label="Pain"
              checked={data.reproductive.pain}
              onChange={(checked) =>
                setData({ ...data, reproductive: { ...data.reproductive, pain: checked } })
              }
            />
            <CheckboxField
              label="Bleeding"
              checked={data.reproductive.bleeding}
              onChange={(checked) =>
                setData({ ...data, reproductive: { ...data.reproductive, bleeding: checked } })
              }
            />
          </div>
        </div>

        {/* Pelvis Trauma Assessment */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
          <Label className="mb-3 block text-base font-medium">Pelvis Stability (Trauma)</Label>
          <div className="space-y-2">
            <CheckboxField
              label="Instability - Left"
              checked={data.trauma.instabilityLeft}
              onChange={(checked) =>
                setData({ ...data, trauma: { ...data.trauma, instabilityLeft: checked } })
              }
            />
            <CheckboxField
              label="Instability - Right"
              checked={data.trauma.instabilityRight}
              onChange={(checked) =>
                setData({ ...data, trauma: { ...data.trauma, instabilityRight: checked } })
              }
            />
            <CheckboxField
              label="Instability - Both Sides"
              checked={data.trauma.instabilityBoth}
              onChange={(checked) =>
                setData({ ...data, trauma: { ...data.trauma, instabilityBoth: checked } })
              }
            />
          </div>
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