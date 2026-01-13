/**
 * Call Timer Component for SafeShift EHR
 * 
 * Displays the meeting duration in HH:MM:SS format.
 * Updates every second while the meeting is active.
 * 
 * @package SafeShift\Components\VideoMeeting
 */

import { useState, useEffect, useCallback } from 'react';
import { Clock } from 'lucide-react';
import type { CallTimerProps } from '../../types/video-meeting.types.js';

/**
 * Format seconds into HH:MM:SS string
 */
function formatDuration(totalSeconds: number): string {
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  const pad = (n: number): string => n.toString().padStart(2, '0');

  return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
}

/**
 * Call Timer Component
 * 
 * Displays meeting duration since start time, updating every second.
 * 
 * @example
 * ```tsx
 * <CallTimer startTime={meetingStartTime} />
 * ```
 */
export function CallTimer({ startTime, className = '' }: CallTimerProps) {
  const [duration, setDuration] = useState<string>('00:00:00');

  const updateDuration = useCallback(() => {
    if (!startTime) {
      setDuration('00:00:00');
      return;
    }

    const now = new Date();
    const diffMs = now.getTime() - startTime.getTime();
    const diffSeconds = Math.max(0, Math.floor(diffMs / 1000));
    setDuration(formatDuration(diffSeconds));
  }, [startTime]);

  useEffect(() => {
    // Update immediately
    updateDuration();

    // Update every second
    const interval = setInterval(updateDuration, 1000);

    return () => clearInterval(interval);
  }, [updateDuration]);

  return (
    <div className={`flex items-center gap-2 text-sm font-mono ${className}`}>
      <Clock className="h-4 w-4 text-slate-400 dark:text-slate-500" />
      <span className="text-slate-600 dark:text-slate-300">{duration}</span>
    </div>
  );
}

export default CallTimer;
