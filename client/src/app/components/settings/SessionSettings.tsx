/**
 * Session Settings Component for SafeShift EHR
 * 
 * Allows users to manage their session settings including:
 * - Idle timeout preference
 * - View active sessions
 * - Log out other devices
 * - Log out everywhere
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  Monitor,
  Smartphone,
  Tablet,
  Globe,
  Clock,
  LogOut,
  RefreshCw,
  Shield,
  AlertTriangle,
  CheckCircle,
} from 'lucide-react';
import { Button } from '../ui/button.js';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '../ui/card.js';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../ui/select.js';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '../ui/alert-dialog.js';
import { sessionService } from '../../services/session.service.js';
import { TIMEOUT_OPTIONS } from '../../types/session.types.js';
import type { ActiveSession } from '../../types/session.types.js';

// ============================================================================
// Types
// ============================================================================

interface SessionSettingsProps {
  /** Callback when settings are successfully saved */
  onSave?: () => void;
  /** Additional CSS classes */
  className?: string;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Parse device info string and return appropriate icon
 */
function getDeviceIcon(deviceInfo: string): React.ReactElement {
  const lowerInfo = deviceInfo.toLowerCase();
  
  if (lowerInfo.includes('mobile') || lowerInfo.includes('iphone') || lowerInfo.includes('android')) {
    return <Smartphone className="h-5 w-5" />;
  }
  
  if (lowerInfo.includes('tablet') || lowerInfo.includes('ipad')) {
    return <Tablet className="h-5 w-5" />;
  }
  
  return <Monitor className="h-5 w-5" />;
}

/**
 * Format a timestamp into a relative time string
 */
function formatRelativeTime(timestamp: string): string {
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMinutes = Math.floor(diffMs / (1000 * 60));
  const diffHours = Math.floor(diffMinutes / 60);
  const diffDays = Math.floor(diffHours / 24);
  
  if (diffMinutes < 1) {
    return 'Just now';
  }
  
  if (diffMinutes === 1) {
    return '1 minute ago';
  }
  
  if (diffMinutes < 60) {
    return `${diffMinutes} minutes ago`;
  }
  
  if (diffHours === 1) {
    return '1 hour ago';
  }
  
  if (diffHours < 24) {
    return `${diffHours} hours ago`;
  }
  
  if (diffDays === 1) {
    return '1 day ago';
  }
  
  return `${diffDays} days ago`;
}

/**
 * Truncate device info for display
 */
function truncateDeviceInfo(info: string, maxLength: number = 50): string {
  if (info.length <= maxLength) return info;
  return info.substring(0, maxLength) + '...';
}

// ============================================================================
// Component
// ============================================================================

/**
 * Session Settings panel for managing user session preferences
 * 
 * @example
 * ```tsx
 * <SessionSettings onSave={() => toast.success('Settings saved!')} />
 * ```
 */
export function SessionSettings({
  onSave,
  className = '',
}: SessionSettingsProps): React.ReactElement {
  // State
  const [sessions, setSessions] = useState<ActiveSession[]>([]);
  const [idleTimeout, setIdleTimeout] = useState<number>(1800);
  const [isLoadingSessions, setIsLoadingSessions] = useState(true);
  const [isLoadingTimeout, setIsLoadingTimeout] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [isLoggingOutEverywhere, setIsLoggingOutEverywhere] = useState(false);
  const [loggingOutSessionId, setLoggingOutSessionId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  
  // Load active sessions
  const loadSessions = useCallback(async () => {
    setIsLoadingSessions(true);
    setError(null);
    
    try {
      const activeSessions = await sessionService.getActiveSessions();
      setSessions(activeSessions);
    } catch (err) {
      console.error('Failed to load sessions:', err);
      setError('Failed to load active sessions');
    } finally {
      setIsLoadingSessions(false);
    }
  }, []);
  
  // Load timeout preference
  const loadTimeout = useCallback(async () => {
    setIsLoadingTimeout(true);
    
    try {
      const response = await sessionService.getTimeoutPreference();
      setIdleTimeout(response.idle_timeout);
    } catch (err) {
      console.error('Failed to load timeout preference:', err);
    } finally {
      setIsLoadingTimeout(false);
    }
  }, []);
  
  // Initial load
  useEffect(() => {
    loadSessions();
    loadTimeout();
  }, [loadSessions, loadTimeout]);
  
  // Clear success message after delay
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => setSuccessMessage(null), 5000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);
  
  // Handle timeout change
  const handleTimeoutChange = async (value: string) => {
    const newTimeout = parseInt(value, 10);
    setIsSaving(true);
    setError(null);
    
    try {
      await sessionService.setTimeoutPreference(newTimeout);
      setIdleTimeout(newTimeout);
      setSuccessMessage('Idle timeout preference saved');
      onSave?.();
    } catch (err) {
      console.error('Failed to save timeout:', err);
      setError('Failed to save timeout preference');
    } finally {
      setIsSaving(false);
    }
  };
  
  // Handle logout single session
  const handleLogoutSession = async (sessionId: number) => {
    setLoggingOutSessionId(sessionId);
    setError(null);
    
    try {
      await sessionService.logoutSession(sessionId);
      setSuccessMessage('Session logged out successfully');
      await loadSessions();
    } catch (err) {
      console.error('Failed to logout session:', err);
      setError('Failed to log out session');
    } finally {
      setLoggingOutSessionId(null);
    }
  };
  
  // Handle logout all other sessions
  const handleLogoutAll = async () => {
    setIsLoggingOut(true);
    setError(null);
    
    try {
      const result = await sessionService.logoutAllOtherSessions();
      setSuccessMessage(`Logged out ${result.count} other session${result.count !== 1 ? 's' : ''}`);
      await loadSessions();
    } catch (err) {
      console.error('Failed to logout all sessions:', err);
      setError('Failed to log out other sessions');
    } finally {
      setIsLoggingOut(false);
    }
  };
  
  // Handle logout everywhere
  const handleLogoutEverywhere = async () => {
    setIsLoggingOutEverywhere(true);
    setError(null);
    
    try {
      await sessionService.logoutEverywhere();
      // This will log out the current session, so the page will redirect to login
    } catch (err) {
      console.error('Failed to logout everywhere:', err);
      setError('Failed to log out everywhere');
      setIsLoggingOutEverywhere(false);
    }
  };
  
  // Count other sessions
  const otherSessionsCount = sessions.filter(s => !s.is_current).length;
  const currentSession = sessions.find(s => s.is_current);
  
  return (
    <div className={`space-y-6 ${className}`}>
      {/* Error/Success Messages */}
      {error && (
        <div className="flex items-center gap-2 p-4 text-sm text-red-600 bg-red-50 dark:bg-red-950/50 dark:text-red-400 rounded-lg">
          <AlertTriangle className="h-4 w-4 flex-shrink-0" />
          {error}
        </div>
      )}
      
      {successMessage && (
        <div className="flex items-center gap-2 p-4 text-sm text-green-600 bg-green-50 dark:bg-green-950/50 dark:text-green-400 rounded-lg">
          <CheckCircle className="h-4 w-4 flex-shrink-0" />
          {successMessage}
        </div>
      )}
      
      {/* Idle Timeout Setting */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Clock className="h-5 w-5" />
            Session Timeout
          </CardTitle>
          <CardDescription>
            Set how long before your session expires due to inactivity
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col sm:flex-row sm:items-center gap-4">
            <Select
              value={idleTimeout.toString()}
              onValueChange={handleTimeoutChange}
              disabled={isLoadingTimeout || isSaving}
            >
              <SelectTrigger className="w-full sm:w-48">
                <SelectValue placeholder="Select timeout" />
              </SelectTrigger>
              <SelectContent>
                {TIMEOUT_OPTIONS.map((option) => (
                  <SelectItem
                    key={option.idle_timeout}
                    value={option.idle_timeout.toString()}
                  >
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            
            {isSaving && (
              <span className="text-sm text-muted-foreground flex items-center gap-2">
                <RefreshCw className="h-4 w-4 animate-spin" />
                Saving...
              </span>
            )}
          </div>
          
          <p className="mt-4 text-sm text-muted-foreground">
            You'll see a warning 2 minutes before your session expires, giving you time to extend it.
          </p>
        </CardContent>
      </Card>
      
      {/* Active Sessions */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Shield className="h-5 w-5" />
            Active Sessions
          </CardTitle>
          <CardDescription>
            Manage your active sessions across all devices
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoadingSessions ? (
            <div className="flex items-center justify-center py-8">
              <RefreshCw className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : sessions.length === 0 ? (
            <p className="text-center py-8 text-muted-foreground">
              No active sessions found
            </p>
          ) : (
            <div className="space-y-4">
              {/* Current Session */}
              {currentSession && (
                <div className="p-4 border rounded-lg bg-primary/5 border-primary/20">
                  <div className="flex items-start gap-4">
                    <div className="text-primary">
                      {getDeviceIcon(currentSession.device_info)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="font-medium">Current Session</span>
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-400">
                          This Device
                        </span>
                      </div>
                      <p className="text-sm text-muted-foreground mt-1 truncate" title={currentSession.device_info}>
                        {truncateDeviceInfo(currentSession.device_info)}
                      </p>
                      <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1">
                          <Globe className="h-3 w-3" />
                          {currentSession.ip_address}
                        </span>
                        <span>
                          Active {formatRelativeTime(currentSession.last_activity)}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              )}
              
              {/* Other Sessions */}
              {sessions.filter(s => !s.is_current).map((session) => (
                <div key={session.id} className="p-4 border rounded-lg">
                  <div className="flex items-start gap-4">
                    <div className="text-muted-foreground">
                      {getDeviceIcon(session.device_info)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm truncate" title={session.device_info}>
                        {truncateDeviceInfo(session.device_info)}
                      </p>
                      <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1">
                          <Globe className="h-3 w-3" />
                          {session.ip_address}
                        </span>
                        <span>
                          Active {formatRelativeTime(session.last_activity)}
                        </span>
                      </div>
                    </div>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleLogoutSession(session.id)}
                      disabled={loggingOutSessionId === session.id}
                    >
                      {loggingOutSessionId === session.id ? (
                        <RefreshCw className="h-4 w-4 animate-spin" />
                      ) : (
                        <LogOut className="h-4 w-4" />
                      )}
                      <span className="sr-only sm:not-sr-only sm:ml-2">
                        Log Out
                      </span>
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
          
          {/* Session Actions */}
          <div className="flex flex-col sm:flex-row gap-3 mt-6 pt-6 border-t">
            <Button
              variant="outline"
              onClick={handleLogoutAll}
              disabled={isLoggingOut || otherSessionsCount === 0}
              className="flex-1 sm:flex-none"
            >
              {isLoggingOut ? (
                <>
                  <RefreshCw className="h-4 w-4 animate-spin" />
                  Logging out...
                </>
              ) : (
                <>
                  <LogOut className="h-4 w-4" />
                  Log Out Other Devices
                  {otherSessionsCount > 0 && (
                    <span className="ml-1">({otherSessionsCount})</span>
                  )}
                </>
              )}
            </Button>
            
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button
                  variant="destructive"
                  disabled={isLoggingOutEverywhere}
                  className="flex-1 sm:flex-none"
                >
                  {isLoggingOutEverywhere ? (
                    <>
                      <RefreshCw className="h-4 w-4 animate-spin" />
                      Logging out...
                    </>
                  ) : (
                    <>
                      <AlertTriangle className="h-4 w-4" />
                      Log Out Everywhere
                    </>
                  )}
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Log Out Everywhere?</AlertDialogTitle>
                  <AlertDialogDescription>
                    This will log you out of all sessions on all devices, including this one.
                    You will need to log in again to continue using the application.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={handleLogoutEverywhere}
                    className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                  >
                    Log Out Everywhere
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </div>
          
          {/* Refresh Button */}
          <div className="mt-4 text-center">
            <Button
              variant="ghost"
              size="sm"
              onClick={loadSessions}
              disabled={isLoadingSessions}
            >
              {isLoadingSessions ? (
                <RefreshCw className="h-4 w-4 animate-spin" />
              ) : (
                <RefreshCw className="h-4 w-4" />
              )}
              <span className="ml-2">Refresh Sessions</span>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

// ============================================================================
// Default Export
// ============================================================================

export default SessionSettings;
