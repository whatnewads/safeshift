import { useState, useEffect } from 'react';
import { AssessmentPanel, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import { ChevronDown, ChevronRight } from 'lucide-react';
import type { AssessmentStatus } from './AssessmentPanel.js';

interface HEENTData {
  head: {
    noAbnormality: boolean;
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
  eyes: {
    noAbnormality: boolean;
    perrl: boolean;
    sluggish: boolean;
    unequal: boolean;
    nonReactive: boolean;
    leftPupilSize: number;
    rightPupilSize: number;
    discharge: boolean;
    redness: boolean;
    periorbitalSwelling: boolean;
  };
  ears: {
    noAbnormality: boolean;
    bloodFromEars: boolean;
    fluidFromEars: boolean;
    battlesSign: boolean;
  };
  nose: {
    noAbnormality: boolean;
    bloodFromNose: boolean;
    fluidFromNose: boolean;
    deformity: boolean;
  };
  throat: {
    noAbnormality: boolean;
    airwayClear: boolean;
    swelling: boolean;
    erythema: boolean;
    visualObstruction: boolean;
  };
  neck: {
    noAbnormality: boolean;
    tracheaMidline: boolean;
    jvdPresent: boolean;
    tenderness: boolean;
    swelling: boolean;
    limitedROM: boolean;
  };
}

const defaultData: HEENTData = {
  head: {
    noAbnormality: true,
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
  eyes: {
    noAbnormality: true,
    perrl: true,
    sluggish: false,
    unequal: false,
    nonReactive: false,
    leftPupilSize: 4,
    rightPupilSize: 4,
    discharge: false,
    redness: false,
    periorbitalSwelling: false,
  },
  ears: {
    noAbnormality: true,
    bloodFromEars: false,
    fluidFromEars: false,
    battlesSign: false,
  },
  nose: {
    noAbnormality: true,
    bloodFromNose: false,
    fluidFromNose: false,
    deformity: false,
  },
  throat: {
    noAbnormality: true,
    airwayClear: true,
    swelling: false,
    erythema: false,
    visualObstruction: false,
  },
  neck: {
    noAbnormality: true,
    tracheaMidline: true,
    jvdPresent: false,
    tenderness: false,
    swelling: false,
    limitedROM: false,
  },
};

export function HEENTAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<HEENTData>(defaultData);
  const [expandedSections, setExpandedSections] = useState<{
    head: boolean;
    eyes: boolean;
    ears: boolean;
    nose: boolean;
    throat: boolean;
    neck: boolean;
  }>({
    head: true,
    eyes: true,
    ears: false,
    nose: false,
    throat: true,
    neck: true,
  });

  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  const toggleSection = (section: keyof typeof expandedSections) => {
    setExpandedSections({
      ...expandedSections,
      [section]: !expandedSections[section],
    });
  };

  const summary = "HEENT: PERRL. No trauma, airway clear, trachea midline. No abnormalities noted.";

  return (
    <AssessmentPanel
      title="HEENT (Head, Eyes, Ears, Nose, Throat, Neck)"
      status={status}
      onStatusChange={setStatus}
      summary={summary}
    >
      <div className="space-y-4">
        {/* Head */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg">
          <button
            type="button"
            onClick={() => toggleSection('head')}
            className="w-full flex items-center justify-between p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            <h4 className="font-semibold">Head</h4>
            {expandedSections.head ? (
              <ChevronDown className="h-5 w-5 text-slate-400" />
            ) : (
              <ChevronRight className="h-5 w-5 text-slate-400" />
            )}
          </button>
          {expandedSections.head && (
            <div className="p-4 pt-0 space-y-3">
              <CheckboxField
                label="No abnormality"
                checked={data.head.noAbnormality}
                onChange={(checked) => setData({ 
                  ...data, 
                  head: { ...data.head, noAbnormality: checked } 
                })}
              />
              {!data.head.noAbnormality && (
                <div className="space-y-3 pl-4 border-l-2 border-red-200 dark:border-red-800">
                  <CheckboxField
                    label="Deformities"
                    checked={data.head.deformities}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, deformities: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Contusions"
                    checked={data.head.contusions}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, contusions: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Abrasions"
                    checked={data.head.abrasions}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, abrasions: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Penetrating trauma"
                    checked={data.head.penetratingTrauma}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, penetratingTrauma: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Burns"
                    checked={data.head.burns}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, burns: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Tenderness to palpation"
                    checked={data.head.tenderness}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, tenderness: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Lacerations"
                    checked={data.head.lacerations}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, lacerations: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Swelling"
                    checked={data.head.swelling}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, swelling: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Discharge"
                    checked={data.head.discharge}
                    onChange={(checked) => setData({ 
                      ...data, 
                      head: { ...data.head, discharge: checked } 
                    })}
                  />
                </div>
              )}
            </div>
          )}
        </div>

        {/* Eyes */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg">
          <button
            type="button"
            onClick={() => toggleSection('eyes')}
            className="w-full flex items-center justify-between p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            <h4 className="font-semibold">Eyes</h4>
            {expandedSections.eyes ? (
              <ChevronDown className="h-5 w-5 text-slate-400" />
            ) : (
              <ChevronRight className="h-5 w-5 text-slate-400" />
            )}
          </button>
          {expandedSections.eyes && (
            <div className="p-4 pt-0 space-y-3">
              <CheckboxField
                label="No abnormality"
                checked={data.eyes.noAbnormality}
                onChange={(checked) => setData({ 
                  ...data, 
                  eyes: { ...data.eyes, noAbnormality: checked } 
                })}
              />
              <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 space-y-2">
                <Label className="text-xs font-medium block mb-2">Pupils</Label>
                <CheckboxField
                  label="PERRL (Pupils Equal, Round, Reactive to Light)"
                  checked={data.eyes.perrl}
                  onChange={(checked) => setData({ 
                    ...data, 
                    eyes: { ...data.eyes, perrl: checked } 
                  })}
                />
                <CheckboxField
                  label="Sluggish"
                  checked={data.eyes.sluggish}
                  onChange={(checked) => setData({ 
                    ...data, 
                    eyes: { ...data.eyes, sluggish: checked } 
                  })}
                />
                <CheckboxField
                  label="Unequal"
                  checked={data.eyes.unequal}
                  onChange={(checked) => setData({ 
                    ...data, 
                    eyes: { ...data.eyes, unequal: checked } 
                  })}
                />
                <CheckboxField
                  label="Non-reactive"
                  checked={data.eyes.nonReactive}
                  onChange={(checked) => setData({ 
                    ...data, 
                    eyes: { ...data.eyes, nonReactive: checked } 
                  })}
                />
                {!data.eyes.perrl && (
                  <div className="grid grid-cols-2 gap-3 mt-3">
                    <div>
                      <Label className="text-xs mb-1 block">Left (mm)</Label>
                      <input
                        type="number"
                        min="1"
                        max="9"
                        value={data.eyes.leftPupilSize}
                        onChange={(e) => setData({ 
                          ...data, 
                          eyes: { ...data.eyes, leftPupilSize: Number(e.target.value) } 
                        })}
                        className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                      />
                    </div>
                    <div>
                      <Label className="text-xs mb-1 block">Right (mm)</Label>
                      <input
                        type="number"
                        min="1"
                        max="9"
                        value={data.eyes.rightPupilSize}
                        onChange={(e) => setData({ 
                          ...data, 
                          eyes: { ...data.eyes, rightPupilSize: Number(e.target.value) } 
                        })}
                        className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md text-sm"
                      />
                    </div>
                  </div>
                )}
              </div>
              {!data.eyes.noAbnormality && (
                <div className="space-y-2 pl-4 border-l-2 border-red-200 dark:border-red-800">
                  <CheckboxField
                    label="Discharge"
                    checked={data.eyes.discharge}
                    onChange={(checked) => setData({ 
                      ...data, 
                      eyes: { ...data.eyes, discharge: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Redness"
                    checked={data.eyes.redness}
                    onChange={(checked) => setData({ 
                      ...data, 
                      eyes: { ...data.eyes, redness: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Periorbital swelling"
                    checked={data.eyes.periorbitalSwelling}
                    onChange={(checked) => setData({ 
                      ...data, 
                      eyes: { ...data.eyes, periorbitalSwelling: checked } 
                    })}
                  />
                </div>
              )}
            </div>
          )}
        </div>

        {/* Ears */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg">
          <button
            type="button"
            onClick={() => toggleSection('ears')}
            className="w-full flex items-center justify-between p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            <h4 className="font-semibold">Ears</h4>
            {expandedSections.ears ? (
              <ChevronDown className="h-5 w-5 text-slate-400" />
            ) : (
              <ChevronRight className="h-5 w-5 text-slate-400" />
            )}
          </button>
          {expandedSections.ears && (
            <div className="p-4 pt-0 space-y-3">
              <CheckboxField
                label="No abnormality"
                checked={data.ears.noAbnormality}
                onChange={(checked) => setData({ 
                  ...data, 
                  ears: { ...data.ears, noAbnormality: checked } 
                })}
              />
              {!data.ears.noAbnormality && (
                <div className="space-y-2 pl-4 border-l-2 border-red-200 dark:border-red-800">
                  <CheckboxField
                    label="Blood from ears"
                    checked={data.ears.bloodFromEars}
                    onChange={(checked) => setData({ 
                      ...data, 
                      ears: { ...data.ears, bloodFromEars: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Clear fluid from ears"
                    checked={data.ears.fluidFromEars}
                    onChange={(checked) => setData({ 
                      ...data, 
                      ears: { ...data.ears, fluidFromEars: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Battle's sign (mastoid ecchymosis)"
                    checked={data.ears.battlesSign}
                    onChange={(checked) => setData({ 
                      ...data, 
                      ears: { ...data.ears, battlesSign: checked } 
                    })}
                  />
                </div>
              )}
            </div>
          )}
        </div>

        {/* Nose */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg">
          <button
            type="button"
            onClick={() => toggleSection('nose')}
            className="w-full flex items-center justify-between p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            <h4 className="font-semibold">Nose</h4>
            {expandedSections.nose ? (
              <ChevronDown className="h-5 w-5 text-slate-400" />
            ) : (
              <ChevronRight className="h-5 w-5 text-slate-400" />
            )}
          </button>
          {expandedSections.nose && (
            <div className="p-4 pt-0 space-y-3">
              <CheckboxField
                label="No abnormality"
                checked={data.nose.noAbnormality}
                onChange={(checked) => setData({ 
                  ...data, 
                  nose: { ...data.nose, noAbnormality: checked } 
                })}
              />
              {!data.nose.noAbnormality && (
                <div className="space-y-2 pl-4 border-l-2 border-red-200 dark:border-red-800">
                  <CheckboxField
                    label="Blood from nose"
                    checked={data.nose.bloodFromNose}
                    onChange={(checked) => setData({ 
                      ...data, 
                      nose: { ...data.nose, bloodFromNose: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Clear fluid from nose"
                    checked={data.nose.fluidFromNose}
                    onChange={(checked) => setData({ 
                      ...data, 
                      nose: { ...data.nose, fluidFromNose: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Deformity"
                    checked={data.nose.deformity}
                    onChange={(checked) => setData({ 
                      ...data, 
                      nose: { ...data.nose, deformity: checked } 
                    })}
                  />
                </div>
              )}
            </div>
          )}
        </div>

        {/* Throat */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg">
          <button
            type="button"
            onClick={() => toggleSection('throat')}
            className="w-full flex items-center justify-between p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            <h4 className="font-semibold">Throat / Airway</h4>
            {expandedSections.throat ? (
              <ChevronDown className="h-5 w-5 text-slate-400" />
            ) : (
              <ChevronRight className="h-5 w-5 text-slate-400" />
            )}
          </button>
          {expandedSections.throat && (
            <div className="p-4 pt-0 space-y-3">
              <CheckboxField
                label="No abnormality"
                checked={data.throat.noAbnormality}
                onChange={(checked) => setData({ 
                  ...data, 
                  throat: { ...data.throat, noAbnormality: checked } 
                })}
              />
              <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                <CheckboxField
                  label="Airway clear"
                  checked={data.throat.airwayClear}
                  onChange={(checked) => setData({ 
                    ...data, 
                    throat: { ...data.throat, airwayClear: checked } 
                  })}
                />
              </div>
              {!data.throat.noAbnormality && (
                <div className="space-y-2 pl-4 border-l-2 border-red-200 dark:border-red-800">
                  <CheckboxField
                    label="Swelling"
                    checked={data.throat.swelling}
                    onChange={(checked) => setData({ 
                      ...data, 
                      throat: { ...data.throat, swelling: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Erythema (redness)"
                    checked={data.throat.erythema}
                    onChange={(checked) => setData({ 
                      ...data, 
                      throat: { ...data.throat, erythema: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Visual obstruction"
                    checked={data.throat.visualObstruction}
                    onChange={(checked) => setData({ 
                      ...data, 
                      throat: { ...data.throat, visualObstruction: checked } 
                    })}
                  />
                </div>
              )}
            </div>
          )}
        </div>

        {/* Neck */}
        <div className="bg-slate-50/50 dark:bg-slate-800/50 rounded-lg">
          <button
            type="button"
            onClick={() => toggleSection('neck')}
            className="w-full flex items-center justify-between p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          >
            <h4 className="font-semibold">Neck</h4>
            {expandedSections.neck ? (
              <ChevronDown className="h-5 w-5 text-slate-400" />
            ) : (
              <ChevronRight className="h-5 w-5 text-slate-400" />
            )}
          </button>
          {expandedSections.neck && (
            <div className="p-4 pt-0 space-y-3">
              <CheckboxField
                label="No abnormality"
                checked={data.neck.noAbnormality}
                onChange={(checked) => setData({ 
                  ...data, 
                  neck: { ...data.neck, noAbnormality: checked } 
                })}
              />
              <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                <CheckboxField
                  label="Trachea midline"
                  checked={data.neck.tracheaMidline}
                  onChange={(checked) => setData({ 
                    ...data, 
                    neck: { ...data.neck, tracheaMidline: checked } 
                  })}
                />
              </div>
              {!data.neck.noAbnormality && (
                <div className="space-y-2 pl-4 border-l-2 border-red-200 dark:border-red-800">
                  <CheckboxField
                    label="JVD (jugular venous distension) present"
                    checked={data.neck.jvdPresent}
                    onChange={(checked) => setData({ 
                      ...data, 
                      neck: { ...data.neck, jvdPresent: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Tenderness"
                    checked={data.neck.tenderness}
                    onChange={(checked) => setData({ 
                      ...data, 
                      neck: { ...data.neck, tenderness: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Swelling"
                    checked={data.neck.swelling}
                    onChange={(checked) => setData({ 
                      ...data, 
                      neck: { ...data.neck, swelling: checked } 
                    })}
                  />
                  <CheckboxField
                    label="Limited range of motion"
                    checked={data.neck.limitedROM}
                    onChange={(checked) => setData({ 
                      ...data, 
                      neck: { ...data.neck, limitedROM: checked } 
                    })}
                  />
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </AssessmentPanel>
  );
}