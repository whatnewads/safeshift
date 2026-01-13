import React, { createContext, useContext, useState, type ReactNode } from 'react';

interface ShiftData {
  shiftStart: string;
  shiftEnd: string;
  clinicName: string;
  clinicAddress: string;
  city: string;
  state: string;
  county: string;
  unitNumber: string;
}

interface ShiftContextType {
  shiftData: ShiftData | null;
  setShiftData: (data: ShiftData) => void;
  clearShift: () => void;
}

const ShiftContext = createContext<ShiftContextType | undefined>(undefined);

export function ShiftProvider({ children }: { children: ReactNode }) {
  const [shiftData, setShiftDataState] = useState<ShiftData | null>(null);

  const setShiftData = (data: ShiftData) => {
    setShiftDataState(data);
    // Store in localStorage for persistence
    localStorage.setItem('currentShift', JSON.stringify(data));
  };

  const clearShift = () => {
    setShiftDataState(null);
    localStorage.removeItem('currentShift');
  };

  // Load shift from localStorage on mount
  React.useEffect(() => {
    const stored = localStorage.getItem('currentShift');
    if (stored) {
      try {
        const parsed = JSON.parse(stored);
        // Migrate old data format if needed (clinicLocation -> clinicAddress)
        if (parsed.clinicLocation && !parsed.clinicAddress) {
          parsed.clinicAddress = parsed.clinicLocation;
          delete parsed.clinicLocation;
        }
        // Ensure all fields exist with defaults
        const migrated: ShiftData = {
          shiftStart: parsed.shiftStart || '',
          shiftEnd: parsed.shiftEnd || '',
          clinicName: parsed.clinicName || '',
          clinicAddress: parsed.clinicAddress || '',
          city: parsed.city || '',
          state: parsed.state || '',
          county: parsed.county || '',
          unitNumber: parsed.unitNumber || '',
        };
        setShiftDataState(migrated);
        // Update localStorage with migrated data
        localStorage.setItem('currentShift', JSON.stringify(migrated));
      } catch (e) {
        console.error('Failed to parse stored shift data:', e);
        localStorage.removeItem('currentShift');
      }
    }
  }, []);

  return (
    <ShiftContext.Provider value={{ shiftData, setShiftData, clearShift }}>
      {children}
    </ShiftContext.Provider>
  );
}

export function useShift() {
  const context = useContext(ShiftContext);
  if (context === undefined) {
    throw new Error('useShift must be used within a ShiftProvider');
  }
  return context;
}
