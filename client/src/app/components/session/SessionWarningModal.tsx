/**
 * Session Warning Modal Component for SafeShift EHR
 * 
 * Displays a modal warning when the user's session is about to expire.
 * Allows the user to extend their session or log out.
 */

import React, { useEffect, useState, useCallback } from 'react';
import { Clock, LogOut, RefreshCw } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '../ui/dialog.js';
import { Button } from '../ui/button.js';

// ============================================================================
// Types
// ============================================================================

export interface SessionWarningModalProps {
  /** Whether the modal is open */
  isOpen: boolean;
  /** Seconds remaining until session expires */
  remainingSeconds: number;
  /** Callback when user clicks "Stay Logged In" */
  onExtend: () => Promise<void>;
  /** Callback when user clicks "Log Out" */
  onLogout: () => Promise<void>;
  /** Callback when session expires (timer reaches 0) */
  onExpired?: () => void;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Format seconds into MM:SS display
 */
function formatTime(seconds: number): string {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

/**
 * Get the urgency level based on remaining time
 */
function getUrgencyLevel(seconds: number): 'normal' | 'warning' | 'critical' {
  if (seconds <= 30) return 'critical';
  if (seconds <= 60) return 'warning';
  return 'normal';
}

// ============================================================================
// Component
// ============================================================================

/**
 * Modal that appears when user's session is about to expire
 * 
 * @example
 * ```tsx
 * <SessionWarningModal
 *   isOpen={showWarning}
 *   remainingSeconds={remainingSeconds}
 *   onExtend={extendSession}
 *   onLogout={forceLogout}
 * />
 * ```
 */
export function SessionWarningModal({
  isOpen,
  remainingSeconds: initialSeconds,
  onExtend,
  onLogout,
  onExpired,
}: SessionWarningModalProps): React.ReactElement | null {
  const [seconds, setSeconds] = useState(initialSeconds);
  const [isExtending, setIsExtending] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  
  // Update seconds when initialSeconds changes
  useEffect(() => {
    setSeconds(initialSeconds);
  }, [initialSeconds]);
  
  // Countdown timer
  useEffect(() => {
    if (!isOpen) return;
    
    const intervalId = setInterval(() => {
      setSeconds(prev => {
        const newValue = prev - 1;
        
        if (newValue <= 0) {
          clearInterval(intervalId);
          onExpired?.();
          return 0;
        }
        
        return newValue;
      });
    }, 1000);
    
    return () => clearInterval(intervalId);
  }, [isOpen, onExpired]);
  
  // Handle extend session
  const handleExtend = useCallback(async () => {
    setIsExtending(true);
    try {
      await onExtend();
    } finally {
      setIsExtending(false);
    }
  }, [onExtend]);
  
  // Handle logout
  const handleLogout = useCallback(async () => {
    setIsLoggingOut(true);
    try {
      await onLogout();
    } finally {
      setIsLoggingOut(false);
    }
  }, [onLogout]);
  
  const urgency = getUrgencyLevel(seconds);
  
  // Don't render if not open
  if (!isOpen) return null;
  
  return (
    <Dialog open={isOpen} onOpenChange={() => {}}>
      <DialogContent 
        className="sm:max-w-md"
        onInteractOutside={(e) => e.preventDefault()}
        onEscapeKeyDown={(e) => e.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-amber-600 dark:text-amber-500">
            <Clock className="h-5 w-5" />
            Session Expiring Soon
          </DialogTitle>
          <DialogDescription>
            Your session is about to expire due to inactivity. Please choose to stay logged in or log out.
          </DialogDescription>
        </DialogHeader>
        
        <div className="flex flex-col items-center py-6">
          {/* Countdown Display */}
          <div 
            className={`
              text-5xl font-mono font-bold tabular-nums
              ${urgency === 'critical' ? 'text-red-600 dark:text-red-500 animate-pulse' : ''}
              ${urgency === 'warning' ? 'text-amber-600 dark:text-amber-500' : ''}
              ${urgency === 'normal' ? 'text-gray-700 dark:text-gray-300' : ''}
            `}
          >
            {formatTime(seconds)}
          </div>
          
          <p className="mt-2 text-sm text-muted-foreground">
            Time remaining
          </p>
          
          {/* Progress Bar */}
          <div className="w-full mt-4 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
            <div 
              className={`
                h-full transition-all duration-1000 ease-linear
                ${urgency === 'critical' ? 'bg-red-500' : ''}
                ${urgency === 'warning' ? 'bg-amber-500' : ''}
                ${urgency === 'normal' ? 'bg-blue-500' : ''}
              `}
              style={{ 
                width: `${Math.max(0, (seconds / 120) * 100)}%`,
              }}
            />
          </div>
          
          {urgency === 'critical' && (
            <p className="mt-3 text-sm text-red-600 dark:text-red-500 font-medium">
              Your session will expire very soon!
            </p>
          )}
        </div>
        
        <DialogFooter className="flex-col sm:flex-row gap-2">
          <Button
            variant="outline"
            onClick={handleLogout}
            disabled={isExtending || isLoggingOut}
            className="w-full sm:w-auto"
          >
            {isLoggingOut ? (
              <>
                <RefreshCw className="h-4 w-4 animate-spin" />
                Logging out...
              </>
            ) : (
              <>
                <LogOut className="h-4 w-4" />
                Log Out
              </>
            )}
          </Button>
          
          <Button
            onClick={handleExtend}
            disabled={isExtending || isLoggingOut}
            className="w-full sm:w-auto"
          >
            {isExtending ? (
              <>
                <RefreshCw className="h-4 w-4 animate-spin" />
                Extending...
              </>
            ) : (
              <>
                <RefreshCw className="h-4 w-4" />
                Stay Logged In
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ============================================================================
// Default Export
// ============================================================================

export default SessionWarningModal;
