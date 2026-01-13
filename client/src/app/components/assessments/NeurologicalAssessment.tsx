import { useState, useEffect } from 'react';
import { AssessmentPanel, CheckboxField } from './AssessmentPanel.js';
import { Label } from '../ui/label.js';
import { AlertTriangle } from 'lucide-react';
import type { AssessmentStatus } from './AssessmentPanel.js';

interface LimbAssessment {
  movesNormally: boolean;
  weak: boolean;
  noMovement: boolean;
}

interface SensationAssessment {
  intact: boolean;
  decreased: boolean;
  absent: boolean;
}

interface NeurologicalData {
  motor: {
    leftArm: LimbAssessment;
    rightArm: LimbAssessment;
    leftLeg: LimbAssessment;
    rightLeg: LimbAssessment;
  };
  sensation: {
    leftArm: SensationAssessment;
    rightArm: SensationAssessment;
    leftLeg: SensationAssessment;
    rightLeg: SensationAssessment;
  };
  speech: string;
  facialSymmetry: boolean;
  facialDroop: boolean;
  gait: string;
  strokeScreen: {
    facialDroop: boolean;
    armDrift: boolean;
    speechAbnormal: boolean;
  };
}

const defaultLimbAssessment: LimbAssessment = {
  movesNormally: true,
  weak: false,
  noMovement: false,
};

const defaultSensation: SensationAssessment = {
  intact: true,
  decreased: false,
  absent: false,
};

const defaultData: NeurologicalData = {
  motor: {
    leftArm: { ...defaultLimbAssessment },
    rightArm: { ...defaultLimbAssessment },
    leftLeg: { ...defaultLimbAssessment },
    rightLeg: { ...defaultLimbAssessment },
  },
  sensation: {
    leftArm: { ...defaultSensation },
    rightArm: { ...defaultSensation },
    leftLeg: { ...defaultSensation },
    rightLeg: { ...defaultSensation },
  },
  speech: 'Normal',
  facialSymmetry: true,
  facialDroop: false,
  gait: 'Steady',
  strokeScreen: {
    facialDroop: false,
    armDrift: false,
    speechAbnormal: false,
  },
};

export function NeurologicalAssessment() {
  const [status, setStatus] = useState<AssessmentStatus>('normal');
  const [data, setData] = useState<NeurologicalData>(defaultData);

  useEffect(() => {
    if (status === 'normal') {
      setData(defaultData);
    }
  }, [status]);

  // Auto-populate stroke screen
  useEffect(() => {
    const hasArmWeakness = 
      data.motor.leftArm.weak || data.motor.leftArm.noMovement ||
      data.motor.rightArm.weak || data.motor.rightArm.noMovement;
    
    setData((prev) => ({
      ...prev,
      strokeScreen: {
        facialDroop: prev.facialDroop,
        armDrift: hasArmWeakness,
        speechAbnormal: prev.speech !== 'Normal',
      },
    }));
  }, [data.facialDroop, data.motor, data.speech]);

  const showStrokeScreen = 
    data.strokeScreen.facialDroop || 
    data.strokeScreen.armDrift || 
    data.strokeScreen.speechAbnormal;

  const summary = "PERRL. Motor function and sensation intact all extremities. Facial symmetry normal. Speech normal.";

  const handleMotorChange = (limb: keyof typeof data.motor, field: keyof LimbAssessment, checked: boolean) => {
    setData({
      ...data,
      motor: {
        ...data.motor,
        [limb]: {
          movesNormally: field === 'movesNormally' ? checked : false,
          weak: field === 'weak' ? checked : false,
          noMovement: field === 'noMovement' ? checked : false,
        },
      },
    });
  };

  const handleSensationChange = (limb: keyof typeof data.sensation, field: keyof SensationAssessment, checked: boolean) => {
    setData({
      ...data,
      sensation: {
        ...data.sensation,
        [limb]: {
          intact: field === 'intact' ? checked : false,
          decreased: field === 'decreased' ? checked : false,
          absent: field === 'absent' ? checked : false,
        },
      },
    });
  };

  return (
    <AssessmentPanel
      title="Neurological"
      status={status}
      onStatusChange={setStatus}
      summary={summary}
    >
      <div className="space-y-6">
        {/* Motor Function Matrix */}
        <div>
          <Label className="mb-3 block">Motor Function</Label>
          <div className="overflow-x-auto">
            <table className="w-full border-collapse">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-700">
                  <th className="text-left p-2 text-sm font-medium"></th>
                  <th className="text-center p-2 text-sm font-medium">Moves Normally</th>
                  <th className="text-center p-2 text-sm font-medium">Weak</th>
                  <th className="text-center p-2 text-sm font-medium">No Movement</th>
                </tr>
              </thead>
              <tbody>
                {[
                  { key: 'leftArm' as const, label: 'Left Arm' },
                  { key: 'rightArm' as const, label: 'Right Arm' },
                  { key: 'leftLeg' as const, label: 'Left Leg' },
                  { key: 'rightLeg' as const, label: 'Right Leg' },
                ].map(({ key, label }) => (
                  <tr key={key} className="border-b border-slate-100 dark:border-slate-800">
                    <td className="p-2 text-sm font-medium">{label}</td>
                    <td className="p-2 text-center">
                      <input
                        type="checkbox"
                        checked={data.motor[key].movesNormally}
                        onChange={(e) => handleMotorChange(key, 'movesNormally', e.target.checked)}
                        className="w-4 h-4"
                      />
                    </td>
                    <td className="p-2 text-center">
                      <input
                        type="checkbox"
                        checked={data.motor[key].weak}
                        onChange={(e) => handleMotorChange(key, 'weak', e.target.checked)}
                        className="w-4 h-4"
                      />
                    </td>
                    <td className="p-2 text-center">
                      <input
                        type="checkbox"
                        checked={data.motor[key].noMovement}
                        onChange={(e) => handleMotorChange(key, 'noMovement', e.target.checked)}
                        className="w-4 h-4"
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Sensation Matrix */}
        <div>
          <Label className="mb-3 block">Sensation</Label>
          <div className="overflow-x-auto">
            <table className="w-full border-collapse">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-700">
                  <th className="text-left p-2 text-sm font-medium"></th>
                  <th className="text-center p-2 text-sm font-medium">Intact</th>
                  <th className="text-center p-2 text-sm font-medium">Decreased</th>
                  <th className="text-center p-2 text-sm font-medium">Absent</th>
                </tr>
              </thead>
              <tbody>
                {[
                  { key: 'leftArm' as const, label: 'Left Arm' },
                  { key: 'rightArm' as const, label: 'Right Arm' },
                  { key: 'leftLeg' as const, label: 'Left Leg' },
                  { key: 'rightLeg' as const, label: 'Right Leg' },
                ].map(({ key, label }) => (
                  <tr key={key} className="border-b border-slate-100 dark:border-slate-800">
                    <td className="p-2 text-sm font-medium">{label}</td>
                    <td className="p-2 text-center">
                      <input
                        type="checkbox"
                        checked={data.sensation[key].intact}
                        onChange={(e) => handleSensationChange(key, 'intact', e.target.checked)}
                        className="w-4 h-4"
                      />
                    </td>
                    <td className="p-2 text-center">
                      <input
                        type="checkbox"
                        checked={data.sensation[key].decreased}
                        onChange={(e) => handleSensationChange(key, 'decreased', e.target.checked)}
                        className="w-4 h-4"
                      />
                    </td>
                    <td className="p-2 text-center">
                      <input
                        type="checkbox"
                        checked={data.sensation[key].absent}
                        onChange={(e) => handleSensationChange(key, 'absent', e.target.checked)}
                        className="w-4 h-4"
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* Speech */}
        <div>
          <Label className="mb-2 block">Speech</Label>
          <div className="space-y-2">
            <CheckboxField
              label="Normal"
              checked={data.speech === 'Normal'}
              onChange={(checked) => checked && setData({ ...data, speech: 'Normal' })}
            />
            <CheckboxField
              label="Slurred"
              checked={data.speech === 'Slurred'}
              onChange={(checked) => checked && setData({ ...data, speech: 'Slurred' })}
            />
            <CheckboxField
              label="Aphasic / unable to speak"
              checked={data.speech === 'Aphasic'}
              onChange={(checked) => checked && setData({ ...data, speech: 'Aphasic' })}
            />
          </div>
        </div>

        {/* Facial Symmetry */}
        <div>
          <Label className="mb-2 block">Facial Symmetry</Label>
          <div className="space-y-2">
            <CheckboxField
              label="Symmetric"
              checked={data.facialSymmetry}
              onChange={(checked) => setData({ ...data, facialSymmetry: checked, facialDroop: !checked })}
            />
            <CheckboxField
              label="Facial droop present"
              checked={data.facialDroop}
              onChange={(checked) => setData({ ...data, facialDroop: checked, facialSymmetry: !checked })}
            />
          </div>
        </div>

        {/* Gait */}
        <div>
          <Label className="mb-2 block">Gait (if ambulatory)</Label>
          <div className="space-y-2">
            <CheckboxField
              label="Steady"
              checked={data.gait === 'Steady'}
              onChange={(checked) => checked && setData({ ...data, gait: 'Steady' })}
            />
            <CheckboxField
              label="Unsteady"
              checked={data.gait === 'Unsteady'}
              onChange={(checked) => checked && setData({ ...data, gait: 'Unsteady' })}
            />
            <CheckboxField
              label="Unable to assess"
              checked={data.gait === 'Unable'}
              onChange={(checked) => checked && setData({ ...data, gait: 'Unable' })}
            />
          </div>
        </div>

        {/* Stroke Screen (Conditional) */}
        {showStrokeScreen && (
          <div className="bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-800 rounded-lg p-4">
            <div className="flex items-center gap-2 mb-3">
              <AlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400" />
              <h4 className="font-semibold text-red-900 dark:text-red-300">Stroke Screen (FAST/Cincinnati)</h4>
            </div>
            <div className="space-y-2 mb-3">
              <div className="flex items-center justify-between">
                <span className="text-sm">Facial droop</span>
                <span className={`text-sm font-medium ${data.strokeScreen.facialDroop ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                  {data.strokeScreen.facialDroop ? 'Yes' : 'No'}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm">Arm drift / weakness</span>
                <span className={`text-sm font-medium ${data.strokeScreen.armDrift ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                  {data.strokeScreen.armDrift ? 'Yes' : 'No'}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm">Speech abnormal</span>
                <span className={`text-sm font-medium ${data.strokeScreen.speechAbnormal ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'}`}>
                  {data.strokeScreen.speechAbnormal ? 'Yes' : 'No'}
                </span>
              </div>
            </div>
            {(data.strokeScreen.facialDroop || data.strokeScreen.armDrift || data.strokeScreen.speechAbnormal) && (
              <div className="bg-red-100 dark:bg-red-900/40 border border-red-300 dark:border-red-700 rounded p-2">
                <p className="text-sm font-semibold text-red-900 dark:text-red-300">
                  ⚠️ POSITIVE STROKE SCREEN
                </p>
              </div>
            )}
          </div>
        )}
      </div>
    </AssessmentPanel>
  );
}