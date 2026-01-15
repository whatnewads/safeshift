import { useState, useEffect } from 'react';
import { X } from 'lucide-react';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { useShift } from '../../contexts/ShiftContext';
import { toast } from 'sonner';

interface SetShiftModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function SetShiftModal({ isOpen, onClose }: SetShiftModalProps) {
  const { shiftData, setShiftData } = useShift();
  const [formData, setFormData] = useState({
    shiftStart: '',
    shiftEnd: '',
    clinicName: '',
    clinicAddress: '',
    city: '',
    state: '',
    county: '',
    unitNumber: '',
  });

  useEffect(() => {
    if (shiftData) {
      setFormData(shiftData);
    } else {
      // Set default shift times to current time and 8 hours later
      const now = new Date();
      const end = new Date(now.getTime() + 8 * 60 * 60 * 1000);
      setFormData({
        shiftStart: now.toISOString().slice(0, 16),
        shiftEnd: end.toISOString().slice(0, 16),
        clinicName: '',
        clinicAddress: '',
        city: '',
        state: '',
        county: '',
        unitNumber: '',
      });
    }
  }, [shiftData]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!formData.shiftStart || !formData.shiftEnd || !formData.clinicName || !formData.clinicAddress) {
      toast.error('Please fill in all required fields');
      return;
    }

    setShiftData(formData);
    toast.success('Shift set successfully');
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      {/* Reduced modal size: max-w-sm (384px) instead of max-w-md (448px), max-h-[75vh] */}
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-sm max-h-[75vh] flex flex-col">
        {/* Header - fixed at top */}
        <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
          <h2 className="text-lg font-semibold dark:text-white">Set Shift Information</h2>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Scrollable form content */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-4 space-y-3">
          <div>
            <Label htmlFor="shiftStart" className="mb-2">
              Shift Start
            </Label>
            <Input
              id="shiftStart"
              type="datetime-local"
              value={formData.shiftStart}
              onChange={(e) => setFormData({ ...formData, shiftStart: e.target.value })}
              required
            />
          </div>

          <div>
            <Label htmlFor="shiftEnd" className="mb-2">
              Shift End
            </Label>
            <Input
              id="shiftEnd"
              type="datetime-local"
              value={formData.shiftEnd}
              onChange={(e) => setFormData({ ...formData, shiftEnd: e.target.value })}
              required
            />
          </div>

          <div>
            <Label htmlFor="clinicName" className="mb-2">
              Clinic Name
            </Label>
            <Input
              id="clinicName"
              type="text"
              placeholder="Enter clinic name"
              value={formData.clinicName}
              onChange={(e) => setFormData({ ...formData, clinicName: e.target.value })}
              required
            />
          </div>

          <div>
            <Label htmlFor="clinicAddress" className="mb-2">
              Clinic Address
            </Label>
            <Input
              id="clinicAddress"
              type="text"
              placeholder="Enter clinic address"
              value={formData.clinicAddress}
              onChange={(e) => setFormData({ ...formData, clinicAddress: e.target.value })}
              required
            />
          </div>

          <div>
            <Label htmlFor="city" className="mb-2">
              City
            </Label>
            <Input
              id="city"
              type="text"
              placeholder="Enter city"
              value={formData.city}
              onChange={(e) => setFormData({ ...formData, city: e.target.value })}
            />
          </div>

          <div>
            <Label htmlFor="state" className="mb-2">
              State
            </Label>
            <Input
              id="state"
              type="text"
              placeholder="TX"
              value={formData.state}
              onChange={(e) => setFormData({ ...formData, state: e.target.value })}
            />
          </div>

          <div>
            <Label htmlFor="county" className="mb-2">
              County
            </Label>
            <Input
              id="county"
              type="text"
              placeholder="Enter county"
              value={formData.county}
              onChange={(e) => setFormData({ ...formData, county: e.target.value })}
            />
          </div>

          <div>
            <Label htmlFor="unitNumber" className="mb-2">
              Unit Number (Optional)
            </Label>
            <Input
              id="unitNumber"
              type="text"
              placeholder="Suite, Apt, etc."
              value={formData.unitNumber}
              onChange={(e) => setFormData({ ...formData, unitNumber: e.target.value })}
            />
          </div>

          <div className="flex gap-3 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button type="submit" className="flex-1">
              Set Shift
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}