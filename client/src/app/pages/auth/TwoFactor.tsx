import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext.js';
import { Button } from '../../components/ui/button.js';
import { Card } from '../../components/ui/card.js';
import { ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { Checkbox } from '../../components/ui/checkbox.js';
import { Label } from '../../components/ui/label.js';
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
} from '../../components/ui/input-otp.js';

export default function TwoFactorPage() {
  const [code, setCode] = useState('');
  const [trustDevice, setTrustDevice] = useState(false);
  const [loading, setLoading] = useState(false);
  const { verify2FA } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (code.length !== 6) {
      toast.error('Please enter a complete 6-digit code');
      return;
    }

    setLoading(true);
    try {
      await verify2FA(code);
      toast.success('Authentication successful');
      navigate('/dashboard');
    } catch (error) {
      console.error('[TwoFactor] verify2FA failed:', error);
      toast.error('Invalid code. Please try again.');
      setCode('');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-slate-100 flex items-center justify-center p-4">
      <Card className="w-full max-w-md p-8">
        <div className="text-center mb-8">
          <div className="flex items-center justify-center gap-2 mb-4">
            <ShieldCheck className="h-8 w-8 text-blue-600" />
            <h1 className="text-2xl font-bold">Two-Factor Authentication</h1>
          </div>
          <p className="text-slate-600 dark:text-slate-400">
            Enter the 6-digit code from Microsoft Authenticator
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="flex justify-center">
            <InputOTP
              maxLength={6}
              value={code}
              onChange={(value) => setCode(value)}
            >
              <InputOTPGroup>
                <InputOTPSlot index={0} />
                <InputOTPSlot index={1} />
                <InputOTPSlot index={2} />
              </InputOTPGroup>
              <InputOTPSeparator />
              <InputOTPGroup>
                <InputOTPSlot index={3} />
                <InputOTPSlot index={4} />
                <InputOTPSlot index={5} />
              </InputOTPGroup>
            </InputOTP>
          </div>

          <div className="flex items-center space-x-2">
            <Checkbox
              id="trust"
              checked={trustDevice}
              onCheckedChange={(checked) => setTrustDevice(checked as boolean)}
            />
            <Label htmlFor="trust" className="text-sm cursor-pointer">
              Trust this device for 30 days
            </Label>
          </div>

          <Button type="submit" className="w-full" disabled={loading || code.length !== 6}>
            {loading ? 'Verifying...' : 'Verify Code'}
          </Button>
        </form>

        <div className="mt-6 space-y-2">
          <button className="w-full text-sm text-blue-600 hover:underline">
            Resend code
          </button>
          <button className="w-full text-sm text-blue-600 hover:underline">
            Use backup code
          </button>
        </div>

        <div className="mt-6 bg-blue-50 rounded-lg p-4 text-xs">
          <p className="font-medium text-blue-900 mb-1">Recovery Contact</p>
          <p className="text-blue-800">
            If you don't have access to your authenticator, contact your system administrator 
            or use your recovery codes provided during setup.
          </p>
        </div>

        <div className="mt-4 text-center">
          <button
            onClick={() => navigate('/login')}
            className="text-sm text-slate-600 dark:text-slate-400 hover:underline"
          >
            Back to login
          </button>
        </div>
      </Card>
    </div>
  );
}
