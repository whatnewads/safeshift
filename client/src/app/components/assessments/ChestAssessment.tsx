import { useState, useEffect } from 'react';
import { AssessmentPanel, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import type { AssessmentStatus } from './AssessmentPanel.js';

interface ChestData {
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
  lungSounds: {
    upperLeft: string[];
    upperRight: string[];
    lowerLeft: string[];
    lowerRight: string[];
  };
  notes: string;
  bodyFindings: any[];
}

const defaultData: ChestData = {
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
  lungSounds: {
    upperLeft: ['Clear'],
    upperRight: ['Clear'],
    lowerLeft: ['Clear'],
    lowerRight: ['Clear'],
  },
  notes: '',
  bodyFindings: [],
};

const lungSoundOptions = [
  'Clear',
  'Wheezing',
  'Stridor',
  'Rhonchi',
  'Crackles',
  'Diminished',
  'Absent',
];

export function ChestAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<ChestData>(defaultData);

  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  const handleLungSoundToggle = (quadrant: keyof ChestData['lungSounds'], sound: string) => {
    const currentSounds = data.lungSounds[quadrant];
    let newSounds: string[];

    if (currentSounds.includes(sound)) {
      newSounds = currentSounds.filter((s) => s !== sound);
    } else {
      newSounds = [...currentSounds, sound];
    }

    // Ensure at least one option is selected
    if (newSounds.length === 0) {
      newSounds = ['Clear'];
    }

    setData({
      ...data,
      lungSounds: {
        ...data.lungSounds,
        [quadrant]: newSounds,
      },
    });
  };

  const summary = 'Chest clear to auscultation bilaterally. Respiratory effort normal.';

  return (
    <AssessmentPanel
      title="Chest & Respiratory"
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

        {/* Lung Sounds by Quadrant */}
        <div>
          <Label className="mb-3 block text-base">Lung Sounds by Quadrant</Label>
          <div className="grid grid-cols-2 gap-4">
            {/* Upper Left */}
            <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
              <Label className="mb-2 block text-sm font-medium">Upper Left</Label>
              <div className="space-y-2">
                {lungSoundOptions.map((sound) => (
                  <CheckboxField
                    key={sound}
                    label={sound}
                    checked={data.lungSounds.upperLeft.includes(sound)}
                    onChange={() => handleLungSoundToggle('upperLeft', sound)}
                  />
                ))}
              </div>
            </div>

            {/* Upper Right */}
            <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
              <Label className="mb-2 block text-sm font-medium">Upper Right</Label>
              <div className="space-y-2">
                {lungSoundOptions.map((sound) => (
                  <CheckboxField
                    key={sound}
                    label={sound}
                    checked={data.lungSounds.upperRight.includes(sound)}
                    onChange={() => handleLungSoundToggle('upperRight', sound)}
                  />
                ))}
              </div>
            </div>

            {/* Lower Left */}
            <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
              <Label className="mb-2 block text-sm font-medium">Lower Left</Label>
              <div className="space-y-2">
                {lungSoundOptions.map((sound) => (
                  <CheckboxField
                    key={sound}
                    label={sound}
                    checked={data.lungSounds.lowerLeft.includes(sound)}
                    onChange={() => handleLungSoundToggle('lowerLeft', sound)}
                  />
                ))}
              </div>
            </div>

            {/* Lower Right */}
            <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg p-4">
              <Label className="mb-2 block text-sm font-medium">Lower Right</Label>
              <div className="space-y-2">
                {lungSoundOptions.map((sound) => (
                  <CheckboxField
                    key={sound}
                    label={sound}
                    checked={data.lungSounds.lowerRight.includes(sound)}
                    onChange={() => handleLungSoundToggle('lowerRight', sound)}
                  />
                ))}
              </div>
            </div>
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