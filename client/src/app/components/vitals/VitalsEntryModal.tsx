import { useState, useEffect, useRef } from 'react';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../ui/select';
import {
  X,
  Clock,
  RotateCcw,
  Heart,
  Droplet,
  Thermometer,
  Brain,
  Activity,
  Wind,
} from 'lucide-react';
import { toast } from 'sonner';

// ============================================================================
// Types
// ============================================================================

interface VitalsEntryModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (vitalData: VitalData) => void;
  initialData?: VitalData | null;
  encounterId?: string;
}

interface VitalData {
  id: string;
  time: string;
  date: string;
  avpu: string;
  bp: string;
  bpSystolic: string;
  bpDiastolic: string;
  bpMethod: string;
  pulse: string;
  pulseMethod: string;
  spo2: string;
  spo2NotAvailable: boolean;
  temp: string;
  tempUnit: 'F' | 'C';
  tempNotAvailable: boolean;
  bloodSugar: string;
  bloodSugarUnit: 'mgdl' | 'mmol';
  bloodSugarNotAvailable: boolean;
  pain: string;
  gcsEye: string;
  gcsVerbal: string;
  gcsMotor: string;
  gcsTotal: string;
  [key: string]: string | boolean;
}

// ============================================================================
// GCS Component
// ============================================================================

interface GCSInputProps {
  gcsEye: string;
  gcsVerbal: string;
  gcsMotor: string;
  gcsTotal: string;
  onChange: (field: string, value: string) => void;
}

function GCSInput({ gcsEye, gcsVerbal, gcsMotor, gcsTotal: _gcsTotal, onChange }: GCSInputProps) {
  const eyeOptions = [
    { value: '4', label: '4 - Eyes open spontaneously' },
    { value: '3', label: '3 - Eyes open to verbal command' },
    { value: '2', label: '2 - Eyes open to pain' },
    { value: '1', label: '1 - No eye opening' },
  ];

  const verbalOptions = [
    { value: '5', label: '5 - Oriented' },
    { value: '4', label: '4 - Confused' },
    { value: '3', label: '3 - Inappropriate words' },
    { value: '2', label: '2 - Incomprehensible sounds' },
    { value: '1', label: '1 - No verbal response' },
  ];

  const motorOptions = [
    { value: '6', label: '6 - Obeys commands' },
    { value: '5', label: '5 - Localizes pain' },
    { value: '4', label: '4 - Withdraws from pain' },
    { value: '3', label: '3 - Abnormal flexion (decorticate)' },
    { value: '2', label: '2 - Extension (decerebrate)' },
    { value: '1', label: '1 - No motor response' },
  ];

  // Calculate total score
  const total = (parseInt(gcsEye) || 0) + (parseInt(gcsVerbal) || 0) + (parseInt(gcsMotor) || 0);
  const displayTotal = gcsEye && gcsVerbal && gcsMotor ? total : 0;

  // Update total when individual scores change
  useEffect(() => {
    if (gcsEye && gcsVerbal && gcsMotor) {
      onChange('gcsTotal', total.toString());
    }
  }, [gcsEye, gcsVerbal, gcsMotor, total, onChange]);

  // Get severity color based on total
  const getSeverityColor = (score: number) => {
    if (score === 0) return 'bg-slate-100 text-slate-500';
    if (score >= 13) return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    if (score >= 9) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
  };

  // Quick set to 15 (Alert & Oriented)
  const setAlertOriented = () => {
    onChange('gcsEye', '4');
    onChange('gcsVerbal', '5');
    onChange('gcsMotor', '6');
    toast.success('GCS set to 15 - Alert & Oriented');
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h4 className="font-medium text-slate-900 dark:text-white flex items-center gap-2">
          <Brain className="h-5 w-5 text-purple-600" />
          Glasgow Coma Scale (GCS)
        </h4>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={setAlertOriented}
          className="text-green-600 hover:text-green-700 hover:bg-green-50"
        >
          <Brain className="h-4 w-4 mr-1" />
          Set GCS 15
        </Button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Eye Response */}
        <div className="space-y-2">
          <Label className="text-sm dark:text-slate-300">Eye Response (E)</Label>
          <Select value={gcsEye} onValueChange={(v) => onChange('gcsEye', v)}>
            <SelectTrigger>
              <SelectValue placeholder="Select..." />
            </SelectTrigger>
            <SelectContent>
              {eyeOptions.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Verbal Response */}
        <div className="space-y-2">
          <Label className="text-sm dark:text-slate-300">Verbal Response (V)</Label>
          <Select value={gcsVerbal} onValueChange={(v) => onChange('gcsVerbal', v)}>
            <SelectTrigger>
              <SelectValue placeholder="Select..." />
            </SelectTrigger>
            <SelectContent>
              {verbalOptions.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Motor Response */}
        <div className="space-y-2">
          <Label className="text-sm dark:text-slate-300">Motor Response (M)</Label>
          <Select value={gcsMotor} onValueChange={(v) => onChange('gcsMotor', v)}>
            <SelectTrigger>
              <SelectValue placeholder="Select..." />
            </SelectTrigger>
            <SelectContent>
              {motorOptions.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Total Score Display */}
      <div className={`rounded-lg p-4 ${getSeverityColor(displayTotal)}`}>
        <div className="flex items-center justify-between">
          <div>
            <span className="text-sm font-medium">Total GCS Score</span>
            <span className="text-xs ml-2 opacity-75">(3-15)</span>
          </div>
          <div className="text-3xl font-bold">{displayTotal || '-'}</div>
        </div>
        {displayTotal > 0 && (
          <div className="text-xs mt-1 opacity-75">
            {displayTotal >= 13 && 'Mild injury (13-15)'}
            {displayTotal >= 9 && displayTotal < 13 && 'Moderate injury (9-12)'}
            {displayTotal < 9 && displayTotal > 0 && 'Severe injury (3-8)'}
          </div>
        )}
      </div>
    </div>
  );
}

// ============================================================================
// Pain Scale Component
// ============================================================================

interface PainScaleProps {
  value: string;
  onChange: (value: string) => void;
}

function PainScale({ value, onChange }: PainScaleProps) {
  const painLevels = [
    { value: 0, label: 'No Pain', color: 'bg-green-500', emoji: '😊' },
    { value: 1, label: '', color: 'bg-green-400', emoji: '🙂' },
    { value: 2, label: 'Mild', color: 'bg-lime-400', emoji: '😐' },
    { value: 3, label: '', color: 'bg-lime-500', emoji: '😐' },
    { value: 4, label: 'Moderate', color: 'bg-yellow-400', emoji: '😟' },
    { value: 5, label: '', color: 'bg-yellow-500', emoji: '😟' },
    { value: 6, label: 'Moderate-Severe', color: 'bg-orange-400', emoji: '😣' },
    { value: 7, label: '', color: 'bg-orange-500', emoji: '😣' },
    { value: 8, label: 'Severe', color: 'bg-red-400', emoji: '😫' },
    { value: 9, label: '', color: 'bg-red-500', emoji: '😫' },
    { value: 10, label: 'Worst Pain', color: 'bg-red-600', emoji: '😭' },
  ];

  const currentValue = parseInt(value) || 0;

  // Get color class for the current value
  const getColorClass = (v: number) => {
    if (v <= 3) return 'bg-green-500 hover:bg-green-600';
    if (v <= 6) return 'bg-yellow-500 hover:bg-yellow-600';
    return 'bg-red-500 hover:bg-red-600';
  };

  return (
    <div className="space-y-4">
      <h4 className="font-medium text-slate-900 dark:text-white">Pain Scale (0-10)</h4>
      
      {/* Visual Pain Scale */}
      <div className="flex items-center gap-1">
        {painLevels.map((level) => (
          <button
            key={level.value}
            type="button"
            onClick={() => onChange(level.value.toString())}
            className={`
              flex-1 h-12 rounded-md transition-all flex flex-col items-center justify-center text-white font-bold
              ${level.color}
              ${currentValue === level.value 
                ? 'ring-2 ring-offset-2 ring-blue-500 scale-110 z-10' 
                : 'hover:scale-105 opacity-80 hover:opacity-100'
              }
            `}
            title={level.label || `Pain level ${level.value}`}
          >
            <span className="text-lg">{level.value}</span>
          </button>
        ))}
      </div>

      {/* Current Selection Display */}
      <div className="flex items-center gap-4">
        <div className={`px-4 py-2 rounded-lg ${getColorClass(currentValue)} text-white`}>
          <span className="text-2xl mr-2">{painLevels[currentValue]?.emoji}</span>
          <span className="font-bold text-xl">{currentValue}</span>
        </div>
        <div className="text-sm text-slate-600 dark:text-slate-400">
          {currentValue === 0 && 'No pain'}
          {currentValue >= 1 && currentValue <= 3 && 'Mild pain - Nagging, annoying'}
          {currentValue >= 4 && currentValue <= 6 && 'Moderate pain - Interferes with concentration'}
          {currentValue >= 7 && currentValue <= 10 && 'Severe pain - Unable to perform activities'}
        </div>
      </div>

      {/* Gradient Bar */}
      <div className="h-3 rounded-full bg-gradient-to-r from-green-500 via-yellow-500 to-red-500 relative">
        <div 
          className="absolute top-1/2 -translate-y-1/2 w-4 h-4 bg-white border-2 border-slate-800 rounded-full shadow-md transition-all"
          style={{ left: `calc(${(currentValue / 10) * 100}% - 8px)` }}
        />
      </div>
    </div>
  );
}

// ============================================================================
// Navigation Links Component
// ============================================================================

interface NavLinksProps {
  activeSection: string;
}

function NavLinks({ activeSection }: NavLinksProps) {
  const sections = [
    { id: 'datetime', label: 'Date/Time' },
    { id: 'neuro', label: 'Neurological' },
    { id: 'cardio', label: 'Cardiovascular' },
    { id: 'respiratory', label: 'Respiratory' },
    { id: 'other', label: 'Other' },
  ];

  const scrollToSection = (sectionId: string) => {
    const element = document.getElementById(`vitals-${sectionId}`);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  return (
    <nav className="hidden lg:flex flex-col gap-2 text-right">
      {sections.map((section) => (
        <button
          key={section.id}
          type="button"
          onClick={() => scrollToSection(section.id)}
          className={`
            text-xs font-medium transition-colors text-right px-2 py-1 rounded
            ${activeSection === section.id 
              ? 'text-blue-600 dark:text-blue-400' 
              : 'text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300'
            }
          `}
        >
          {section.label}
        </button>
      ))}
    </nav>
  );
}

// ============================================================================
// Main VitalsEntryModal Component
// ============================================================================

export function VitalsEntryModal({
  isOpen,
  onClose,
  onSave,
  initialData,
}: VitalsEntryModalProps) {
  const scrollContainerRef = useRef<HTMLDivElement>(null);
  const [activeSection, setActiveSection] = useState('datetime');
  const [showTimeTooltip, setShowTimeTooltip] = useState(false);

  // Default vital data
  const getDefaultVitalData = (): VitalData => ({
    id: Date.now().toString(),
    time: new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' }),
    date: new Date().toISOString().split('T')[0],
    avpu: '',
    bp: '',
    bpSystolic: '',
    bpDiastolic: '',
    bpMethod: '',
    pulse: '',
    pulseMethod: '',
    spo2: '',
    spo2NotAvailable: false,
    temp: '',
    tempUnit: 'F',
    tempNotAvailable: false,
    bloodSugar: '',
    bloodSugarUnit: 'mgdl',
    bloodSugarNotAvailable: false,
    pain: '0',
    gcsEye: '',
    gcsVerbal: '',
    gcsMotor: '',
    gcsTotal: '',
  });

  const [vitalData, setVitalData] = useState<VitalData>(() =>
    initialData ? { ...getDefaultVitalData(), ...initialData } : getDefaultVitalData()
  );

  // Reset state when initialData changes
  useEffect(() => {
    if (initialData) {
      setVitalData({ ...getDefaultVitalData(), ...initialData });
    } else {
      setVitalData(getDefaultVitalData());
    }
  }, [initialData]);

  // Update handler
  const handleChange = (field: string, value: string | boolean) => {
    setVitalData((prev) => ({ ...prev, [field]: value }));
  };

  // Scroll spy for active section
  useEffect(() => {
    const container = scrollContainerRef.current;
    if (!container) return;

    const handleScroll = () => {
      const sections = ['datetime', 'neuro', 'cardio', 'respiratory', 'other'];
      for (const sectionId of sections) {
        const element = document.getElementById(`vitals-${sectionId}`);
        if (element) {
          const rect = element.getBoundingClientRect();
          if (rect.top <= 200 && rect.bottom >= 200) {
            setActiveSection(sectionId);
            break;
          }
        }
      }
    };

    container.addEventListener('scroll', handleScroll);
    return () => container.removeEventListener('scroll', handleScroll);
  }, []);

  // Set current time and date
  const setCurrentTimeAndDate = () => {
    const now = new Date();
    setVitalData({
      ...vitalData,
      time: now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit' }),
      date: now.toISOString().split('T')[0],
    });
    toast.success('Time and date set to now');
  };

  // Validate and save
  const handleSave = () => {
    // Combine BP values
    const finalData = {
      ...vitalData,
      bp: vitalData.bpSystolic && vitalData.bpDiastolic 
        ? `${vitalData.bpSystolic}/${vitalData.bpDiastolic}` 
        : '',
    };

    // Validation warnings
    const warnings: string[] = [];

    // Pulse validation (normal: 60-100)
    if (finalData.pulse) {
      const pulse = parseInt(finalData.pulse);
      if (pulse < 60) warnings.push(`Heart rate ${pulse} bpm is below normal (60-100)`);
      if (pulse > 100) warnings.push(`Heart rate ${pulse} bpm is above normal (60-100)`);
    }

    // BP validation
    if (finalData.bpSystolic) {
      const sys = parseInt(finalData.bpSystolic);
      if (sys < 90) warnings.push(`Systolic BP ${sys} is below normal (90-140)`);
      if (sys > 140) warnings.push(`Systolic BP ${sys} is above normal (90-140)`);
    }
    if (finalData.bpDiastolic) {
      const dia = parseInt(finalData.bpDiastolic);
      if (dia < 60) warnings.push(`Diastolic BP ${dia} is below normal (60-90)`);
      if (dia > 90) warnings.push(`Diastolic BP ${dia} is above normal (60-90)`);
    }

    // SpO2 validation (normal: ≥95%)
    if (finalData.spo2 && !finalData.spo2NotAvailable) {
      const spo2 = parseInt(finalData.spo2);
      if (spo2 < 95) warnings.push(`SpO2 ${spo2}% is below normal (≥95%)`);
    }

    // Temperature validation (normal: 97.0-99.5°F)
    if (finalData.temp && !finalData.tempNotAvailable) {
      const temp = parseFloat(finalData.temp);
      if (finalData.tempUnit === 'F') {
        if (temp < 97) warnings.push(`Temperature ${temp}°F is below normal (97-99.5)`);
        if (temp > 99.5) warnings.push(`Temperature ${temp}°F is above normal (97-99.5)`);
      }
    }

    // GCS validation (normal: 15)
    if (finalData.gcsTotal) {
      const gcs = parseInt(finalData.gcsTotal);
      if (gcs < 15) warnings.push(`GCS ${gcs} is below normal (15)`);
    }

    // Show warnings
    warnings.forEach((warning, index) => {
      setTimeout(() => {
        toast.warning(`⚠️ ${warning}`, { duration: 6000 });
      }, index * 200);
    });

    onSave(finalData);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/60 dark:bg-black/70 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-slate-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col border border-slate-200 dark:border-slate-600">
        {/* Header */}
        <div className="p-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-t-lg flex-shrink-0">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold dark:text-white">
              {initialData ? 'Edit Vitals' : 'Add Vitals'}
            </h2>
            <div className="flex items-center gap-2">
              <Button onClick={handleSave} className="bg-green-600 hover:bg-green-700">
                Save Vitals
              </Button>
              <Button variant="ghost" size="icon" onClick={onClose}>
                <X className="h-5 w-5" />
              </Button>
            </div>
          </div>
        </div>

        {/* Content Area */}
        <div className="flex flex-1 overflow-hidden relative">
          {/* Main Scrollable Content */}
          <div ref={scrollContainerRef} className="flex-1 overflow-y-auto p-6 pr-24 lg:pr-32">
            {/* ============================================================ */}
            {/* DATE/TIME SECTION */}
            {/* ============================================================ */}
            <section id="vitals-datetime" className="mb-8 scroll-mt-4">
              <h3 className="text-lg font-semibold mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 dark:text-white flex items-center gap-2">
                <Clock className="h-5 w-5 text-blue-600" />
                Date & Time
              </h3>
              
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {/* Time */}
                <div className="space-y-2">
                  <Label className="dark:text-slate-300">Time <span className="text-red-500">*</span></Label>
                  <div className="flex items-center gap-2">
                    <Input
                      type="time"
                      value={vitalData.time}
                      onChange={(e) => handleChange('time', e.target.value)}
                      className="flex-1"
                    />
                    <div className="relative">
                      <button
                        type="button"
                        onClick={setCurrentTimeAndDate}
                        onMouseEnter={() => setShowTimeTooltip(true)}
                        onMouseLeave={() => setShowTimeTooltip(false)}
                        className="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition-colors"
                        title="Set to current time"
                      >
                        <RotateCcw className="h-4 w-4 text-slate-600 dark:text-slate-400" />
                      </button>
                      {showTimeTooltip && (
                        <div className="absolute left-1/2 -translate-x-1/2 top-full mt-1 px-2 py-1 bg-slate-800 text-white text-xs rounded whitespace-nowrap z-50">
                          Set to now
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                {/* Date */}
                <div className="space-y-2">
                  <Label className="dark:text-slate-300">Date <span className="text-red-500">*</span></Label>
                  <Input
                    type="date"
                    value={vitalData.date}
                    onChange={(e) => handleChange('date', e.target.value)}
                  />
                </div>

                {/* AVPU */}
                <div className="space-y-2">
                  <Label className="dark:text-slate-300">AVPU <span className="text-red-500">*</span></Label>
                  <Select value={vitalData.avpu} onValueChange={(v) => handleChange('avpu', v)}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select..." />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="A">A - Alert</SelectItem>
                      <SelectItem value="V">V - Verbal</SelectItem>
                      <SelectItem value="P">P - Pain</SelectItem>
                      <SelectItem value="U">U - Unresponsive</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </section>

            {/* ============================================================ */}
            {/* NEUROLOGICAL SECTION (AVPU, GCS) */}
            {/* ============================================================ */}
            <section id="vitals-neuro" className="mb-8 scroll-mt-4">
              <h3 className="text-lg font-semibold mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 dark:text-white flex items-center gap-2">
                <Brain className="h-5 w-5 text-purple-600" />
                Neurological
              </h3>
              
              {/* GCS Component */}
              <GCSInput
                gcsEye={vitalData.gcsEye}
                gcsVerbal={vitalData.gcsVerbal}
                gcsMotor={vitalData.gcsMotor}
                gcsTotal={vitalData.gcsTotal}
                onChange={handleChange}
              />
            </section>

            {/* ============================================================ */}
            {/* CARDIOVASCULAR SECTION (Pulse, BP) */}
            {/* ============================================================ */}
            <section id="vitals-cardio" className="mb-8 scroll-mt-4">
              <h3 className="text-lg font-semibold mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 dark:text-white flex items-center gap-2">
                <Heart className="h-5 w-5 text-red-600" />
                Cardiovascular
              </h3>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Pulse/Heart Rate */}
                <div className="space-y-4">
                  <h4 className="font-medium text-slate-900 dark:text-white">Pulse / Heart Rate</h4>
                  <div className="space-y-2">
                    <Label className="dark:text-slate-300">BPM <span className="text-red-500">*</span></Label>
                    <Input
                      type="number"
                      placeholder="72"
                      value={vitalData.pulse}
                      onChange={(e) => handleChange('pulse', e.target.value)}
                      className="[&::-webkit-inner-spin-button]:appearance-none"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label className="dark:text-slate-300">Method</Label>
                    <Select value={vitalData.pulseMethod} onValueChange={(v) => handleChange('pulseMethod', v)}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select method..." />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="pulse-ox">Pulse Oximeter</SelectItem>
                        <SelectItem value="cardiac-monitor">Cardiac Monitor</SelectItem>
                        <SelectItem value="manual-radial">Manual - Radial</SelectItem>
                        <SelectItem value="manual-carotid">Manual - Carotid</SelectItem>
                        <SelectItem value="manual-other">Manual - Other</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                {/* Blood Pressure */}
                <div className="space-y-4">
                  <h4 className="font-medium text-slate-900 dark:text-white flex items-center gap-2">
                    <Droplet className="h-4 w-4 text-blue-600" />
                    Blood Pressure
                  </h4>
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label className="dark:text-slate-300">Systolic <span className="text-red-500">*</span></Label>
                      <Input
                        type="number"
                        placeholder="120"
                        value={vitalData.bpSystolic}
                        onChange={(e) => handleChange('bpSystolic', e.target.value)}
                        className="[&::-webkit-inner-spin-button]:appearance-none"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label className="dark:text-slate-300">Diastolic <span className="text-red-500">*</span></Label>
                      <Input
                        type="number"
                        placeholder="80"
                        value={vitalData.bpDiastolic}
                        onChange={(e) => handleChange('bpDiastolic', e.target.value)}
                        className="[&::-webkit-inner-spin-button]:appearance-none"
                      />
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label className="dark:text-slate-300">Method</Label>
                    <Select value={vitalData.bpMethod} onValueChange={(v) => handleChange('bpMethod', v)}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select method..." />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="automatic">Automatic</SelectItem>
                        <SelectItem value="manual-auscultated">Manual Auscultated</SelectItem>
                        <SelectItem value="palpated">Palpated</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>
            </section>

            {/* ============================================================ */}
            {/* RESPIRATORY SECTION (SpO2) */}
            {/* ============================================================ */}
            <section id="vitals-respiratory" className="mb-8 scroll-mt-4">
              <h3 className="text-lg font-semibold mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 dark:text-white flex items-center gap-2">
                <Wind className="h-5 w-5 text-cyan-600" />
                Respiratory
              </h3>
              
              <div className="max-w-md space-y-4">
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <Label className="dark:text-slate-300">SpO₂ (%) <span className="text-red-500">*</span></Label>
                    <Button
                      type="button"
                      variant={vitalData.spo2NotAvailable ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => {
                        handleChange('spo2NotAvailable', !vitalData.spo2NotAvailable);
                        if (!vitalData.spo2NotAvailable) handleChange('spo2', '');
                      }}
                    >
                      Not Available
                    </Button>
                  </div>
                  <Input
                    type="number"
                    placeholder="98"
                    value={vitalData.spo2}
                    onChange={(e) => handleChange('spo2', e.target.value)}
                    disabled={vitalData.spo2NotAvailable}
                    className="[&::-webkit-inner-spin-button]:appearance-none"
                  />
                  <p className="text-xs text-slate-500">Normal range: 95-100%</p>
                </div>
              </div>
            </section>

            {/* ============================================================ */}
            {/* OTHER SECTION (Temp, Blood Sugar, Pain) */}
            {/* ============================================================ */}
            <section id="vitals-other" className="mb-8 scroll-mt-4">
              <h3 className="text-lg font-semibold mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 dark:text-white flex items-center gap-2">
                <Activity className="h-5 w-5 text-emerald-600" />
                Other Measurements
              </h3>
              
              <div className="space-y-8">
                {/* Temperature */}
                <div className="space-y-4">
                  <h4 className="font-medium text-slate-900 dark:text-white flex items-center gap-2">
                    <Thermometer className="h-4 w-4 text-orange-600" />
                    Temperature
                  </h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-lg">
                    <div className="space-y-2">
                      <div className="flex items-center justify-between">
                        <Label className="dark:text-slate-300">Value</Label>
                        <Button
                          type="button"
                          variant={vitalData.tempNotAvailable ? 'default' : 'outline'}
                          size="sm"
                          onClick={() => {
                            handleChange('tempNotAvailable', !vitalData.tempNotAvailable);
                            if (!vitalData.tempNotAvailable) handleChange('temp', '');
                          }}
                        >
                          N/A
                        </Button>
                      </div>
                      <Input
                        type="number"
                        step="0.1"
                        placeholder={vitalData.tempUnit === 'F' ? '98.6' : '37.0'}
                        value={vitalData.temp}
                        onChange={(e) => handleChange('temp', e.target.value)}
                        disabled={vitalData.tempNotAvailable}
                        className="[&::-webkit-inner-spin-button]:appearance-none"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label className="dark:text-slate-300">Unit</Label>
                      <Select value={vitalData.tempUnit} onValueChange={(v) => handleChange('tempUnit', v)}>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="F">Fahrenheit (°F)</SelectItem>
                          <SelectItem value="C">Celsius (°C)</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                </div>

                {/* Blood Sugar */}
                <div className="space-y-4">
                  <h4 className="font-medium text-slate-900 dark:text-white flex items-center gap-2">
                    <Droplet className="h-4 w-4 text-pink-600" />
                    Blood Sugar
                  </h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-lg">
                    <div className="space-y-2">
                      <div className="flex items-center justify-between">
                        <Label className="dark:text-slate-300">Value</Label>
                        <Button
                          type="button"
                          variant={vitalData.bloodSugarNotAvailable ? 'default' : 'outline'}
                          size="sm"
                          onClick={() => {
                            handleChange('bloodSugarNotAvailable', !vitalData.bloodSugarNotAvailable);
                            if (!vitalData.bloodSugarNotAvailable) handleChange('bloodSugar', '');
                          }}
                        >
                          N/A
                        </Button>
                      </div>
                      <Input
                        type="number"
                        placeholder={vitalData.bloodSugarUnit === 'mgdl' ? '100' : '5.5'}
                        value={vitalData.bloodSugar}
                        onChange={(e) => handleChange('bloodSugar', e.target.value)}
                        disabled={vitalData.bloodSugarNotAvailable}
                        className="[&::-webkit-inner-spin-button]:appearance-none"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label className="dark:text-slate-300">Unit</Label>
                      <Select value={vitalData.bloodSugarUnit} onValueChange={(v) => handleChange('bloodSugarUnit', v)}>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="mgdl">mg/dL</SelectItem>
                          <SelectItem value="mmol">mmol/L</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                </div>

                {/* Pain Scale */}
                <div className="space-y-4">
                  <PainScale
                    value={vitalData.pain}
                    onChange={(v) => handleChange('pain', v)}
                  />
                </div>
              </div>
            </section>
          </div>

          {/* Floating Navigation Links (Right Side) */}
          <div className="absolute right-4 top-1/2 -translate-y-1/2 hidden lg:block">
            <NavLinks activeSection={activeSection} />
          </div>
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-b-lg flex-shrink-0">
          <div className="flex justify-end gap-3">
            <Button variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button onClick={handleSave} className="bg-green-600 hover:bg-green-700">
              Save Vitals
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default VitalsEntryModal;
