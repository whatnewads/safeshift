import { useState, useEffect } from 'react';
import { AssessmentPanel, RadioGroup, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import type { AssessmentStatus } from './AssessmentPanel.js';

interface MentalStatusData {
  levelOfConsciousness: string;
  orientationPerson: boolean;
  orientationPlace: boolean;
  orientationTime: boolean;
  orientationEvent: boolean;
  behavior: {
    calm: boolean;
    cooperative: boolean;
    anxious: boolean;
    agitated: boolean;
    confused: boolean;
    combative: boolean;
    withdrawn: boolean;
  };
  psychiatricFlags: {
    suicidalIdeation: boolean;
    homicidalIdeation: boolean;
    hallucinations: boolean;
  };
  showGCS: boolean;
  gcsEye: number;
  gcsVerbal: number;
  gcsMotor: number;
  notes: string;
}

const defaultData: MentalStatusData = {
  levelOfConsciousness: 'Alert',
  orientationPerson: true,
  orientationPlace: true,
  orientationTime: true,
  orientationEvent: true,
  behavior: {
    calm: true,
    cooperative: true,
    anxious: false,
    agitated: false,
    confused: false,
    combative: false,
    withdrawn: false,
  },
  psychiatricFlags: {
    suicidalIdeation: false,
    homicidalIdeation: false,
    hallucinations: false,
  },
  showGCS: false,
  gcsEye: 4,
  gcsVerbal: 5,
  gcsMotor: 6,
  notes: '',
};

export function MentalStatusAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<MentalStatusData>(defaultData);
  const [bodyFindings, setBodyFindings] = useState<any[]>([]);

  // Auto-fill normal defaults when "No Abnormalities" is selected
  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  // Show GCS if LOC is not Alert
  useEffect(() => {
    if (data.levelOfConsciousness !== 'Alert') {
      setData((prev) => ({ ...prev, showGCS: true }));
    }
  }, [data.levelOfConsciousness]);

  const orientationCount = [
    data.orientationPerson,
    data.orientationPlace,
    data.orientationTime,
    data.orientationEvent,
  ].filter(Boolean).length;

  const gcsTotal = data.gcsEye + data.gcsVerbal + data.gcsMotor;

  const summary = "Alert and oriented × 4. Calm, cooperative. Normal speech.";

  return (
    <AssessmentPanel
      title="Mental Status"
      status={status}
      onStatusChange={setStatus}
      summary={summary}
      showBodyModel={true}
      onBodyFindings={setBodyFindings}
      bodyFindings={bodyFindings}
    >
      <div className="space-y-6">
        {/* Level of Consciousness (AVPU) */}
        <div>
          <Label className="mb-3 block">Level of Consciousness (AVPU)</Label>
          <div className="flex gap-2 flex-wrap">
            {[
              { value: 'Alert', color: 'green' },
              { value: 'Responds to Verbal', color: 'yellow' },
              { value: 'Responds to Pain', color: 'orange' },
              { value: 'Unresponsive', color: 'red' },
            ].map(({ value, color }) => {
              const isSelected = data.levelOfConsciousness === value;
              const colorClasses = {
                green: {
                  bg: isSelected ? 'bg-green-100 dark:bg-green-900/30' : 'bg-white dark:bg-slate-800',
                  border: isSelected ? 'border-green-500 dark:border-green-600' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500',
                  circle: isSelected ? 'border-green-600 dark:border-green-400 bg-green-600 dark:bg-green-500' : 'border-slate-800 dark:border-slate-200',
                  text: 'text-slate-900 dark:text-slate-100',
                },
                yellow: {
                  bg: isSelected ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-white dark:bg-slate-800',
                  border: isSelected ? 'border-yellow-500 dark:border-yellow-600' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500',
                  circle: isSelected ? 'border-yellow-600 dark:border-yellow-400 bg-yellow-600 dark:bg-yellow-500' : 'border-slate-800 dark:border-slate-200',
                  text: 'text-slate-900 dark:text-slate-100',
                },
                orange: {
                  bg: isSelected ? 'bg-orange-100 dark:bg-orange-900/30' : 'bg-white dark:bg-slate-800',
                  border: isSelected ? 'border-orange-500 dark:border-orange-600' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500',
                  circle: isSelected ? 'border-orange-600 dark:border-orange-400 bg-orange-600 dark:bg-orange-500' : 'border-slate-800 dark:border-slate-200',
                  text: 'text-slate-900 dark:text-slate-100',
                },
                red: {
                  bg: isSelected ? 'bg-red-100 dark:bg-red-900/30' : 'bg-white dark:bg-slate-800',
                  border: isSelected ? 'border-red-500 dark:border-red-600' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500',
                  circle: isSelected ? 'border-red-600 dark:border-red-400 bg-red-600 dark:bg-red-500' : 'border-slate-800 dark:border-slate-200',
                  text: 'text-slate-900 dark:text-slate-100',
                },
              }[color];

              return (
                <button
                  key={value}
                  type="button"
                  onClick={() => setData({ ...data, levelOfConsciousness: value })}
                  className={`flex items-center gap-2.5 px-3 py-1.5 rounded-full transition-all cursor-pointer ${colorClasses.bg} border ${colorClasses.border}`}
                >
                  <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 ${colorClasses.circle}`}>
                    {isSelected && (
                      <div className="w-2.5 h-2.5 rounded-full bg-white"></div>
                    )}
                  </div>
                  <span className={`text-sm ${isSelected ? 'font-medium' : ''} ${colorClasses.text}`}>
                    {value}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        {/* GCS (conditional) */}
        {data.showGCS && (
          <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div className="flex items-center justify-between mb-3">
              <Label>Glasgow Coma Scale</Label>
              <span className="text-lg font-semibold">Total: {gcsTotal}</span>
            </div>
            <div className="grid grid-cols-3 gap-4">
              <div>
                <Label className="text-xs mb-1 block">Eye</Label>
                <select
                  value={data.gcsEye}
                  onChange={(e) => setData({ ...data, gcsEye: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                >
                  <option value="4">4 - Spontaneous</option>
                  <option value="3">3 - To voice</option>
                  <option value="2">2 - To pain</option>
                  <option value="1">1 - None</option>
                </select>
              </div>
              <div>
                <Label className="text-xs mb-1 block">Verbal</Label>
                <select
                  value={data.gcsVerbal}
                  onChange={(e) => setData({ ...data, gcsVerbal: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                >
                  <option value="5">5 - Oriented</option>
                  <option value="4">4 - Confused</option>
                  <option value="3">3 - Words</option>
                  <option value="2">2 - Sounds</option>
                  <option value="1">1 - None</option>
                </select>
              </div>
              <div>
                <Label className="text-xs mb-1 block">Motor</Label>
                <select
                  value={data.gcsMotor}
                  onChange={(e) => setData({ ...data, gcsMotor: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                >
                  <option value="6">6 - Obeys</option>
                  <option value="5">5 - Localizes</option>
                  <option value="4">4 - Withdraws</option>
                  <option value="3">3 - Flexion</option>
                  <option value="2">2 - Extension</option>
                  <option value="1">1 - None</option>
                </select>
              </div>
            </div>
          </div>
        )}

        {/* Orientation */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <Label>Orientation</Label>
            <span className="text-sm font-medium text-blue-600 dark:text-blue-400">
              Oriented × {orientationCount}
            </span>
          </div>
          <div className="flex flex-wrap gap-2">
            <CheckboxField
              label="Person"
              checked={data.orientationPerson}
              onChange={(checked) => setData({ ...data, orientationPerson: checked })}
            />
            <CheckboxField
              label="Place"
              checked={data.orientationPlace}
              onChange={(checked) => setData({ ...data, orientationPlace: checked })}
            />
            <CheckboxField
              label="Time"
              checked={data.orientationTime}
              onChange={(checked) => setData({ ...data, orientationTime: checked })}
            />
            <CheckboxField
              label="Event"
              checked={data.orientationEvent}
              onChange={(checked) => setData({ ...data, orientationEvent: checked })}
            />
          </div>
        </div>

        {/* Behavior / Affect */}
        <div>
          <Label className="mb-2 block">Behavior / Affect</Label>
          <div className="flex flex-wrap gap-2">
            <CheckboxField
              label="Calm"
              checked={data.behavior.calm}
              onChange={(checked) => setData({ ...data, behavior: { ...data.behavior, calm: checked } })}
            />
            <CheckboxField
              label="Cooperative"
              checked={data.behavior.cooperative}
              onChange={(checked) => setData({ ...data, behavior: { ...data.behavior, cooperative: checked } })}
            />
            <CheckboxField
              label="Anxious"
              checked={data.behavior.anxious}
              onChange={(checked) => setData({ ...data, behavior: { ...data.behavior, anxious: checked } })}
            />
            <CheckboxField
              label="Agitated"
              checked={data.behavior.agitated}
              onChange={(checked) => setData({ ...data, behavior: { ...data.behavior, agitated: checked } })}
            />
            <CheckboxField
              label="Confused"
              checked={data.behavior.confused}
              onChange={(checked) => setData({ ...data, behavior: { ...data.behavior, confused: checked } })}
            />
            <CheckboxField
              label="Combative"
              checked={data.behavior.combative}
              onChange={(checked) => setData({ ...data, behavior: { ...data.behavior, combative: checked } })}
            />
            <CheckboxField
              label="Withdrawn"
              checked={data.behavior.withdrawn}
              onChange={(checked) => setData({ ...data, behavior: { ...data.behavior, withdrawn: checked } })}
            />
          </div>
        </div>

        {/* Psychiatric Red Flags */}
        <div>
          <div className="flex items-center gap-2 mb-2">
            <Label>Psychiatric Red Flags</Label>
          </div>
          <div className="flex flex-wrap gap-2">
            <CheckboxField
              label="Suicidal ideation (patient statement)"
              checked={data.psychiatricFlags.suicidalIdeation}
              onChange={(checked) => setData({ 
                ...data, 
                psychiatricFlags: { ...data.psychiatricFlags, suicidalIdeation: checked } 
              })}
            />
            <CheckboxField
              label="Homicidal ideation (patient statement)"
              checked={data.psychiatricFlags.homicidalIdeation}
              onChange={(checked) => setData({ 
                ...data, 
                psychiatricFlags: { ...data.psychiatricFlags, homicidalIdeation: checked } 
              })}
            />
            <CheckboxField
              label="Hallucinations reported"
              checked={data.psychiatricFlags.hallucinations}
              onChange={(checked) => setData({ 
                ...data, 
                psychiatricFlags: { ...data.psychiatricFlags, hallucinations: checked } 
              })}
            />
          </div>
          <p className="text-xs text-slate-600 dark:text-slate-400 mt-2">
            💡 Document observable statements only. No diagnosis.
          </p>
        </div>

        {/* Optional Notes */}
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