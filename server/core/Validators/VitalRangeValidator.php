<?php
/**
 * Vital Range Validator
 * 
 * Validates vital signs against clinical reference ranges
 * Determines status (normal, warning, critical) for vital signs
 */

namespace Core\Validators;

class VitalRangeValidator
{
    /**
     * Clinical reference ranges for vital signs
     */
    private array $ranges = [
        'temperature' => [
            'units' => ['F', '°F'],
            'normal' => ['min' => 97.0, 'max' => 99.0],
            'warning_low' => ['min' => 95.0, 'max' => 96.9],
            'warning_high' => ['min' => 99.1, 'max' => 103.0],
            'critical_low' => ['max' => 94.9],
            'critical_high' => ['min' => 103.1]
        ],
        'bp_systolic' => [
            'units' => ['mmHg'],
            'normal' => ['min' => 90, 'max' => 120],
            'warning_low' => ['min' => 80, 'max' => 89],
            'warning_high' => ['min' => 121, 'max' => 139],
            'critical_low' => ['max' => 79],
            'critical_high' => ['min' => 140]
        ],
        'bp_diastolic' => [
            'units' => ['mmHg'],
            'normal' => ['min' => 60, 'max' => 80],
            'warning_low' => ['min' => 50, 'max' => 59],
            'warning_high' => ['min' => 81, 'max' => 89],
            'critical_low' => ['max' => 49],
            'critical_high' => ['min' => 90]
        ],
        'pulse' => [
            'units' => ['bpm', 'beats/min'],
            'normal' => ['min' => 60, 'max' => 100],
            'warning_low' => ['min' => 50, 'max' => 59],
            'warning_high' => ['min' => 101, 'max' => 110],
            'critical_low' => ['max' => 49],
            'critical_high' => ['min' => 111]
        ],
        'respiration' => [
            'units' => ['breaths/min', 'rpm'],
            'normal' => ['min' => 12, 'max' => 20],
            'warning_low' => ['min' => 10, 'max' => 11],
            'warning_high' => ['min' => 21, 'max' => 24],
            'critical_low' => ['max' => 9],
            'critical_high' => ['min' => 25]
        ],
        'spo2' => [
            'units' => ['%'],
            'normal' => ['min' => 95, 'max' => 100],
            'warning' => ['min' => 90, 'max' => 94],
            'critical' => ['max' => 89]
        ],
        'blood_sugar' => [
            'units' => ['mg/dL'],
            'normal' => ['min' => 70, 'max' => 140],
            'warning_low' => ['min' => 60, 'max' => 69],
            'warning_high' => ['min' => 141, 'max' => 180],
            'critical_low' => ['max' => 59],
            'critical_high' => ['min' => 181]
        ],
        'pain_scale' => [
            'units' => [''],
            'mild' => ['min' => 0, 'max' => 3],
            'moderate' => ['min' => 4, 'max' => 6],
            'severe' => ['min' => 7, 'max' => 10]
        ]
    ];
    
    /**
     * Get vital status (normal, warning, critical)
     */
    public function getVitalStatus(string $vitalCode, float $value, ?string $patientAge = null): string
    {
        if (!isset($this->ranges[$vitalCode])) {
            return 'unknown';
        }
        
        $ranges = $this->ranges[$vitalCode];
        
        // Special handling for pain scale
        if ($vitalCode === 'pain_scale') {
            if ($value <= 3) return 'mild';
            if ($value <= 6) return 'moderate';
            return 'severe';
        }
        
        // Check critical ranges first
        if (isset($ranges['critical_low']) && $value <= $ranges['critical_low']['max']) {
            return 'critical';
        }
        if (isset($ranges['critical_high']) && $value >= $ranges['critical_high']['min']) {
            return 'critical';
        }
        if (isset($ranges['critical']) && $value <= $ranges['critical']['max']) {
            return 'critical';
        }
        
        // Check warning ranges
        if (isset($ranges['warning_low']) && $this->isInRange($value, $ranges['warning_low'])) {
            return 'warning';
        }
        if (isset($ranges['warning_high']) && $this->isInRange($value, $ranges['warning_high'])) {
            return 'warning';
        }
        if (isset($ranges['warning']) && $this->isInRange($value, $ranges['warning'])) {
            return 'warning';
        }
        
        // Check normal range
        if (isset($ranges['normal']) && $this->isInRange($value, $ranges['normal'])) {
            return 'normal';
        }
        
        // If not in any defined range
        return 'abnormal';
    }
    
    /**
     * Get color code for vital status
     */
    public function getStatusColor(string $vitalCode, float $value, ?string $patientAge = null): string
    {
        $status = $this->getVitalStatus($vitalCode, $value, $patientAge);
        
        $colorMap = [
            'normal' => 'green',
            'mild' => 'green',
            'warning' => 'yellow',
            'moderate' => 'yellow',
            'critical' => 'red',
            'severe' => 'red',
            'abnormal' => 'orange',
            'unknown' => 'gray'
        ];
        
        return $colorMap[$status] ?? 'gray';
    }
    
    /**
     * Get abnormal flag for HL7 compatibility (H, L, HH, LL, N)
     */
    public function getAbnormalFlag(string $vitalCode, float $value): ?string
    {
        if (!isset($this->ranges[$vitalCode])) {
            return null;
        }
        
        $ranges = $this->ranges[$vitalCode];
        
        // Check critical high/low
        if (isset($ranges['critical_high']) && $value >= $ranges['critical_high']['min']) {
            return 'HH'; // Critical high
        }
        if (isset($ranges['critical_low']) && $value <= $ranges['critical_low']['max']) {
            return 'LL'; // Critical low
        }
        
        // Check warning high/low
        if (isset($ranges['warning_high']) && $this->isInRange($value, $ranges['warning_high'])) {
            return 'H'; // High
        }
        if (isset($ranges['warning_low']) && $this->isInRange($value, $ranges['warning_low'])) {
            return 'L'; // Low
        }
        
        // Check normal
        if (isset($ranges['normal']) && $this->isInRange($value, $ranges['normal'])) {
            return 'N'; // Normal
        }
        
        // Abnormal but not specifically high or low
        return 'A';
    }
    
    /**
     * Get reference range text for display
     */
    public function getReferenceRange(string $vitalCode, ?string $patientAge = null): ?string
    {
        if (!isset($this->ranges[$vitalCode]['normal'])) {
            return null;
        }
        
        $normal = $this->ranges[$vitalCode]['normal'];
        $units = $this->ranges[$vitalCode]['units'][0] ?? '';
        
        if (isset($normal['min']) && isset($normal['max'])) {
            return "{$normal['min']}-{$normal['max']} $units";
        } elseif (isset($normal['min'])) {
            return ">= {$normal['min']} $units";
        } elseif (isset($normal['max'])) {
            return "<= {$normal['max']} $units";
        }
        
        return null;
    }
    
    /**
     * Validate combined blood pressure
     */
    public function validateBloodPressure(float $systolic, float $diastolic): array
    {
        $systolicStatus = $this->getVitalStatus('bp_systolic', $systolic);
        $diastolicStatus = $this->getVitalStatus('bp_diastolic', $diastolic);
        
        // Determine overall status (worst of the two)
        $statusPriority = [
            'normal' => 0,
            'warning' => 1,
            'critical' => 2
        ];
        
        $overallStatus = 'normal';
        if ($statusPriority[$systolicStatus] > $statusPriority[$overallStatus]) {
            $overallStatus = $systolicStatus;
        }
        if ($statusPriority[$diastolicStatus] > $statusPriority[$overallStatus]) {
            $overallStatus = $diastolicStatus;
        }
        
        return [
            'systolic_status' => $systolicStatus,
            'diastolic_status' => $diastolicStatus,
            'overall_status' => $overallStatus,
            'color' => $this->getStatusColorFromStatus($overallStatus),
            'display_value' => "$systolic/$diastolic"
        ];
    }
    
    /**
     * Check if value is in range
     */
    private function isInRange(float $value, array $range): bool
    {
        if (isset($range['min']) && $value < $range['min']) {
            return false;
        }
        if (isset($range['max']) && $value > $range['max']) {
            return false;
        }
        return true;
    }
    
    /**
     * Get color from status
     */
    private function getStatusColorFromStatus(string $status): string
    {
        $colorMap = [
            'normal' => 'green',
            'warning' => 'yellow',
            'critical' => 'red'
        ];
        
        return $colorMap[$status] ?? 'gray';
    }
    
    /**
     * Get supported vital codes
     */
    public function getSupportedVitalCodes(): array
    {
        return array_keys($this->ranges);
    }
    
    /**
     * Check if vital code is supported
     */
    public function isSupported(string $vitalCode): bool
    {
        return isset($this->ranges[$vitalCode]);
    }
}