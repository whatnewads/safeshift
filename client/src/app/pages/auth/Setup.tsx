import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '../../components/ui/button';
import { Card } from '../../components/ui/card';
import { Checkbox } from '../../components/ui/checkbox';
import { Label } from '../../components/ui/label';
import { Input } from '../../components/ui/input';
import { Activity, ShieldCheck, CheckCircle } from 'lucide-react';
import { toast } from 'sonner';

export default function SetupPage() {
  const [step, setStep] = useState(1);
  const [acceptedPolicy, setAcceptedPolicy] = useState(false);
  const [deviceLabel, setDeviceLabel] = useState('');
  const navigate = useNavigate();

  const handleComplete = () => {
    toast.success('Setup complete! Welcome to OccHealth EHR');
    navigate('/dashboard');
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-slate-100 flex items-center justify-center p-4">
      <Card className="w-full max-w-2xl p-8">
        <div className="text-center mb-8">
          <div className="flex items-center justify-center gap-2 mb-4">
            <Activity className="h-8 w-8 text-blue-600" />
            <h1 className="text-2xl font-bold">First-Time Setup</h1>
          </div>
          <p className="text-slate-600 dark:text-slate-400">
            Complete the following steps to access your account
          </p>
        </div>

        {/* Progress Steps */}
        <div className="flex items-center justify-center mb-8 gap-4">
          {[1, 2, 3].map((s) => (
            <div key={s} className="flex items-center">
              <div
                className={`w-10 h-10 rounded-full flex items-center justify-center ${
                  s <= step
                    ? 'bg-blue-600 text-white'
                    : 'bg-slate-200 text-slate-500'
                }`}
              >
                {s < step ? <CheckCircle className="h-5 w-5" /> : s}
              </div>
              {s < 3 && (
                <div
                  className={`w-16 h-1 mx-2 ${
                    s < step ? 'bg-blue-600' : 'bg-slate-200'
                  }`}
                />
              )}
            </div>
          ))}
        </div>

        {/* Step 1: Policy Acceptance */}
        {step === 1 && (
          <div className="space-y-6">
            <div>
              <h2 className="text-xl font-semibold mb-4">Acceptable Use & HIPAA Acknowledgment</h2>
              <div className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 max-h-96 overflow-y-auto">
                <h3 className="font-semibold mb-2">Terms of Use</h3>
                <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
                  By accessing this system, you agree to comply with all organizational policies 
                  regarding data privacy, security, and acceptable use.
                </p>

                <h3 className="font-semibold mb-2">HIPAA Compliance</h3>
                <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
                  You acknowledge that you will handle all patient health information in accordance 
                  with HIPAA regulations and organizational policies. Unauthorized disclosure may 
                  result in civil and criminal penalties.
                </p>

                <h3 className="font-semibold mb-2">System Monitoring</h3>
                <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
                  All system activity is logged and monitored. You have no expectation of privacy 
                  when using this system.
                </p>

                <h3 className="font-semibold mb-2">Data Collection Notice</h3>
                <p className="text-sm text-slate-600 dark:text-slate-400">
                  This system is not intended for collecting personally identifiable information 
                  beyond clinical requirements. Do not store sensitive personal data unrelated to 
                  patient care.
                </p>
              </div>
            </div>

            <div className="flex items-center space-x-2">
              <Checkbox
                id="accept"
                checked={acceptedPolicy}
                onCheckedChange={(checked) => setAcceptedPolicy(checked as boolean)}
              />
              <Label htmlFor="accept" className="cursor-pointer">
                I have read and agree to the terms above
              </Label>
            </div>

            <Button
              onClick={() => setStep(2)}
              className="w-full"
              disabled={!acceptedPolicy}
            >
              Continue
            </Button>
          </div>
        )}

        {/* Step 2: 2FA Setup */}
        {step === 2 && (
          <div className="space-y-6">
            <div>
              <h2 className="text-xl font-semibold mb-4">Setup Two-Factor Authentication</h2>
              <div className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
                <div className="flex items-start gap-4 mb-6">
                  <ShieldCheck className="h-8 w-8 text-blue-600 flex-shrink-0" />
                  <div>
                    <h3 className="font-semibold mb-2">Microsoft Authenticator Required</h3>
                    <p className="text-sm text-slate-600 dark:text-slate-400">
                      For security, all users must enable two-factor authentication using 
                      Microsoft Authenticator.
                    </p>
                  </div>
                </div>

                <div className="space-y-4">
                  <div className="flex items-start gap-3">
                    <div className="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-semibold flex-shrink-0">
                      1
                    </div>
                    <p className="text-sm">Download Microsoft Authenticator app on your mobile device</p>
                  </div>

                  <div className="flex items-start gap-3">
                    <div className="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-semibold flex-shrink-0">
                      2
                    </div>
                    <p className="text-sm">Scan the QR code with the app (simulated)</p>
                  </div>

                  <div className="flex items-start gap-3">
                    <div className="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-semibold flex-shrink-0">
                      3
                    </div>
                    <p className="text-sm">Enter the 6-digit code to verify</p>
                  </div>
                </div>

                <div className="mt-6 p-4 bg-slate-100 rounded-lg text-center">
                  <p className="text-xs text-slate-600 mb-2">QR Code Placeholder</p>
                  <div className="w-48 h-48 mx-auto bg-white border-4 border-slate-300 rounded-lg flex items-center justify-center">
                    <p className="text-slate-400">QR Code</p>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex gap-3">
              <Button variant="outline" onClick={() => setStep(1)} className="flex-1">
                Back
              </Button>
              <Button onClick={() => setStep(3)} className="flex-1">
                Continue
              </Button>
            </div>
          </div>
        )}

        {/* Step 3: Device Registration */}
        {step === 3 && (
          <div className="space-y-6">
            <div>
              <h2 className="text-xl font-semibold mb-4">Register This Device</h2>
              <p className="text-slate-600 dark:text-slate-400 mb-6">
                For audit purposes, please provide a label for this device
              </p>

              <div className="space-y-4">
                <div>
                  <Label htmlFor="device">Device Label</Label>
                  <Input
                    id="device"
                    placeholder="e.g., Work Laptop, Office Desktop, Tablet"
                    value={deviceLabel}
                    onChange={(e) => setDeviceLabel(e.target.value)}
                  />
                  <p className="text-xs text-slate-500 mt-1">
                    This helps identify your device in audit logs
                  </p>
                </div>

                <div className="bg-slate-100 rounded-lg p-4 text-sm">
                  <p className="font-medium mb-2">Device Information (Auto-detected)</p>
                  <div className="space-y-1 text-slate-600 dark:text-slate-400">
                    <p>Browser: Chrome 120.0</p>
                    <p>OS: Windows 11</p>
                    <p>IP: 192.168.1.100</p>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex gap-3">
              <Button variant="outline" onClick={() => setStep(2)} className="flex-1">
                Back
              </Button>
              <Button
                onClick={handleComplete}
                className="flex-1"
                disabled={!deviceLabel.trim()}
              >
                Complete Setup
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}
