import { useState, useEffect } from 'react';
import { AssessmentPanel, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import { Button } from '../ui/button.js';
import type { AssessmentStatus } from './AssessmentPanel.js';


interface SkinData {
  color: {
    normal: boolean;
    pale: boolean;
    flushed: boolean;
    cyanotic: boolean;
    jaundiced: boolean;
    mottled: boolean;
    ashen: boolean;
  };
  temperature: string;
  moisture: string;
  integrity: {
    noLesions: boolean;
    rash: boolean;
    rashLocation: string;
    laceration: boolean;
    lacerationLocation: string;
    burn: boolean;
    burnLocation: string;
    bruising: boolean;
    bruisingLocation: string;
  };
}

const defaultData: SkinData = {
  color: {
    normal: true,
    pale: false,
    flushed: false,
    cyanotic: false,
    jaundiced: false,
    mottled: false,
    ashen: false,
  },
  temperature: 'Warm',
  moisture: 'Dry',
  integrity: {
    noLesions: true,
    rash: false,
    rashLocation: '',
    laceration: false,
    lacerationLocation: '',
    burn: false,
    burnLocation: '',
    bruising: false,
    bruisingLocation: '',
  },
};

export function SkinAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<SkinData>(defaultData);

  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  const handleQuickNormal = () => {
    setData({
      ...defaultData,
      color: { ...defaultData.color, normal: true },
      temperature: 'Warm',
      moisture: 'Dry',
    });
    setStatus('normal');
  };

  const summary = "Pink, warm, dry. No lesions or abnormalities noted.";

  return (
    <AssessmentPanel
      title="Skin"
      status={status}
      onStatusChange={setStatus}
      summary={summary}
    >
      <div className="space-y-6">
        {/* Quick Normal Button */}
        <div>
          <Button
            type="button"
            onClick={handleQuickNormal}
            className="w-full bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800"
          >
            ✓ Quick Normal: Pink / Warm / Dry
          </Button>
        </div>

        {/* Color */}
        <div>
          <Label className="mb-2 block">Color</Label>
          <div className="flex flex-wrap gap-2">
            <CheckboxField
              label="Normal for patient"
              checked={data.color.normal}
              onChange={(checked) => setData({ 
                ...data, 
                color: { ...data.color, normal: checked } 
              })}
            />
            <CheckboxField
              label="Pale"
              checked={data.color.pale}
              onChange={(checked) => setData({ 
                ...data, 
                color: { ...data.color, pale: checked } 
              })}
            />
            <CheckboxField
              label="Flushed"
              checked={data.color.flushed}
              onChange={(checked) => setData({ 
                ...data, 
                color: { ...data.color, flushed: checked } 
              })}
            />
            <CheckboxField
              label="Cyanotic"
              checked={data.color.cyanotic}
              onChange={(checked) => setData({ 
                ...data, 
                color: { ...data.color, cyanotic: checked } 
              })}
            />
            <CheckboxField
              label="Jaundiced"
              checked={data.color.jaundiced}
              onChange={(checked) => setData({ 
                ...data, 
                color: { ...data.color, jaundiced: checked } 
              })}
            />
            <CheckboxField
              label="Mottled"
              checked={data.color.mottled}
              onChange={(checked) => setData({ 
                ...data, 
                color: { ...data.color, mottled: checked } 
              })}
            />
            <CheckboxField
              label="Ashen"
              checked={data.color.ashen}
              onChange={(checked) => setData({ 
                ...data, 
                color: { ...data.color, ashen: checked } 
              })}
            />
          </div>
        </div>

        {/* Temperature */}
        <div>
          <Label className="mb-3 block">Temperature</Label>
          <div className="flex gap-2 flex-wrap">
            {['Warm', 'Cool', 'Cold', 'Hot'].map((temp) => (
              <button
                key={temp}
                type="button"
                onClick={() => setData({ ...data, temperature: temp })}
                className={`flex items-center gap-2.5 px-3 py-2 rounded-full transition-all cursor-pointer ${
                  data.temperature === temp
                    ? 'bg-purple-100 dark:bg-purple-900/30 border border-purple-400 dark:border-purple-600'
                    : 'bg-slate-50/50 dark:bg-slate-800/50 border border-transparent hover:border-slate-300 dark:hover:border-slate-600'
                }`}
              >
                <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 ${
                  data.temperature === temp
                    ? 'border-purple-600 dark:border-purple-400 bg-purple-600 dark:bg-purple-500'
                    : 'border-slate-400 dark:border-slate-500'
                }`}>
                  {data.temperature === temp && (
                    <div className="w-2.5 h-2.5 rounded-full bg-white"></div>
                  )}
                </div>
                <span className={`text-sm ${
                  data.temperature === temp
                    ? 'text-purple-700 dark:text-purple-300 font-medium'
                    : 'text-slate-700 dark:text-slate-300'
                }`}>
                  {temp}
                </span>
              </button>
            ))}
          </div>
        </div>

        {/* Moisture */}
        <div>
          <Label className="mb-3 block">Moisture</Label>
          <div className="flex gap-2 flex-wrap">
            {['Dry', 'Diaphoretic', 'Clammy'].map((moisture) => (
              <button
                key={moisture}
                type="button"
                onClick={() => setData({ ...data, moisture: moisture })}
                className={`flex items-center gap-2.5 px-3 py-2 rounded-full transition-all cursor-pointer ${
                  data.moisture === moisture
                    ? 'bg-purple-100 dark:bg-purple-900/30 border border-purple-400 dark:border-purple-600'
                    : 'bg-slate-50/50 dark:bg-slate-800/50 border border-transparent hover:border-slate-300 dark:hover:border-slate-600'
                }`}
              >
                <div className={`w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 ${
                  data.moisture === moisture
                    ? 'border-purple-600 dark:border-purple-400 bg-purple-600 dark:bg-purple-500'
                    : 'border-slate-400 dark:border-slate-500'
                }`}>
                  {data.moisture === moisture && (
                    <div className="w-2.5 h-2.5 rounded-full bg-white"></div>
                  )}
                </div>
                <span className={`text-sm ${
                  data.moisture === moisture
                    ? 'text-purple-700 dark:text-purple-300 font-medium'
                    : 'text-slate-700 dark:text-slate-300'
                }`}>
                  {moisture}
                </span>
              </button>
            ))}
          </div>
        </div>

        {/* Integrity */}
        <div>
          <Label className="mb-3 block">Skin Integrity</Label>
          <div className="space-y-4">
            <CheckboxField
              label="No lesions or abnormalities"
              checked={data.integrity.noLesions}
              onChange={(checked) => setData({ 
                ...data, 
                integrity: { ...data.integrity, noLesions: checked } 
              })}
            />

            {/* Rash */}
            <div className="pl-4 border-l-2 border-slate-200 dark:border-slate-700">
              <CheckboxField
                label="Rash"
                checked={data.integrity.rash}
                onChange={(checked) => setData({ 
                  ...data, 
                  integrity: { ...data.integrity, rash: checked, rashLocation: checked ? data.integrity.rashLocation : '' } 
                })}
              />
              {data.integrity.rash && (
                <input
                  type="text"
                  placeholder="Location..."
                  value={data.integrity.rashLocation}
                  onChange={(e) => setData({ 
                    ...data, 
                    integrity: { ...data.integrity, rashLocation: e.target.value } 
                  })}
                  className="mt-2 w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                />
              )}
            </div>

            {/* Laceration */}
            <div className="pl-4 border-l-2 border-slate-200 dark:border-slate-700">
              <CheckboxField
                label="Laceration"
                checked={data.integrity.laceration}
                onChange={(checked) => setData({ 
                  ...data, 
                  integrity: { ...data.integrity, laceration: checked, lacerationLocation: checked ? data.integrity.lacerationLocation : '' } 
                })}
              />
              {data.integrity.laceration && (
                <input
                  type="text"
                  placeholder="Location..."
                  value={data.integrity.lacerationLocation}
                  onChange={(e) => setData({ 
                    ...data, 
                    integrity: { ...data.integrity, lacerationLocation: e.target.value } 
                  })}
                  className="mt-2 w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                />
              )}
            </div>

            {/* Burn */}
            <div className="pl-4 border-l-2 border-slate-200 dark:border-slate-700">
              <CheckboxField
                label="Burn"
                checked={data.integrity.burn}
                onChange={(checked) => setData({ 
                  ...data, 
                  integrity: { ...data.integrity, burn: checked, burnLocation: checked ? data.integrity.burnLocation : '' } 
                })}
              />
              {data.integrity.burn && (
                <input
                  type="text"
                  placeholder="Location..."
                  value={data.integrity.burnLocation}
                  onChange={(e) => setData({ 
                    ...data, 
                    integrity: { ...data.integrity, burnLocation: e.target.value } 
                  })}
                  className="mt-2 w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                />
              )}
            </div>

            {/* Bruising */}
            <div className="pl-4 border-l-2 border-slate-200 dark:border-slate-700">
              <CheckboxField
                label="Bruising"
                checked={data.integrity.bruising}
                onChange={(checked) => setData({ 
                  ...data, 
                  integrity: { ...data.integrity, bruising: checked, bruisingLocation: checked ? data.integrity.bruisingLocation : '' } 
                })}
              />
              {data.integrity.bruising && (
                <input
                  type="text"
                  placeholder="Location..."
                  value={data.integrity.bruisingLocation}
                  onChange={(e) => setData({ 
                    ...data, 
                    integrity: { ...data.integrity, bruisingLocation: e.target.value } 
                  })}
                  className="mt-2 w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                />
              )}
            </div>
          </div>
        </div>
      </div>
    </AssessmentPanel>
  );
}