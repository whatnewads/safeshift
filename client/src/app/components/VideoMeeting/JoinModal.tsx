/**
 * Join Modal Component for SafeShift EHR
 * 
 * Modal for entering display name before joining a video meeting.
 * This is mandatory and cannot be dismissed without action.
 * 
 * @package SafeShift\Components\VideoMeeting
 */

import { useState, useCallback, useEffect } from 'react';
import { User, Video, AlertCircle, Loader2 } from 'lucide-react';
import { Button } from '../ui/button.js';
import { Input } from '../ui/input.js';
import type { JoinModalProps } from '../../types/video-meeting.types.js';

// ============================================================================
// Join Modal Component
// ============================================================================

/**
 * Join Modal Component
 * 
 * Mandatory modal for name entry before joining a meeting.
 * Cannot be dismissed without entering a valid name or cancelling.
 * 
 * @example
 * ```tsx
 * <JoinModal
 *   isOpen={showJoinModal}
 *   token={meetingToken}
 *   meeting={meeting}
 *   onJoin={(name) => joinMeeting(token, name)}
 *   onCancel={() => navigate('/')}
 * />
 * ```
 */
export function JoinModal({
  isOpen,
  token: _token,
  meeting,
  onJoin,
  onCancel,
  loading = false,
  error = null,
}: JoinModalProps) {
  const [displayName, setDisplayName] = useState('');
  const [validationError, setValidationError] = useState<string | null>(null);

  // Reset state when modal opens
  useEffect(() => {
    if (isOpen) {
      setDisplayName('');
      setValidationError(null);
    }
  }, [isOpen]);

  /**
   * Validate display name
   */
  const validateName = useCallback((name: string): boolean => {
    const trimmed = name.trim();
    
    if (!trimmed) {
      setValidationError('Please enter your name');
      return false;
    }
    
    if (trimmed.length < 1) {
      setValidationError('Name must be at least 1 character');
      return false;
    }
    
    if (trimmed.length > 100) {
      setValidationError('Name must be 100 characters or less');
      return false;
    }
    
    setValidationError(null);
    return true;
  }, []);

  /**
   * Handle form submission
   */
  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      
      if (validateName(displayName)) {
        onJoin(displayName.trim());
      }
    },
    [displayName, validateName, onJoin]
  );

  /**
   * Handle input change
   */
  const handleInputChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const value = e.target.value;
      setDisplayName(value);
      
      // Clear validation error when typing
      if (validationError) {
        setValidationError(null);
      }
    },
    [validationError]
  );

  if (!isOpen) return null;

  const displayError = error || validationError;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop - cannot be clicked to dismiss */}
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" />

      {/* Modal */}
      <div className="relative w-full max-w-md mx-4 bg-white dark:bg-slate-800 rounded-xl shadow-2xl">
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
              <Video className="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                Join Video Meeting
              </h2>
              {meeting && (
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  Meeting ID: {meeting.meetingId}
                </p>
              )}
            </div>
          </div>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-6">
          {/* Name input */}
          <div className="space-y-2">
            <label
              htmlFor="displayName"
              className="block text-sm font-medium text-slate-700 dark:text-slate-300"
            >
              Your Name <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <User className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
              <Input
                id="displayName"
                type="text"
                value={displayName}
                onChange={handleInputChange}
                placeholder="Enter your name"
                className="pl-10"
                maxLength={100}
                autoFocus
                disabled={loading}
                required
              />
            </div>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              This name will be visible to other participants
            </p>
          </div>

          {/* Error message */}
          {displayError && (
            <div className="mt-4 flex items-start gap-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
              <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-600 dark:text-red-400">{displayError}</p>
            </div>
          )}

          {/* Actions */}
          <div className="mt-6 flex gap-3">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={loading}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={!displayName.trim() || loading}
              className="flex-1"
            >
              {loading ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Joining...
                </>
              ) : (
                'Join Meeting'
              )}
            </Button>
          </div>
        </form>

        {/* Footer info */}
        <div className="px-6 py-3 bg-slate-50 dark:bg-slate-700/50 rounded-b-xl border-t border-slate-200 dark:border-slate-700">
          <p className="text-xs text-slate-500 dark:text-slate-400 text-center">
            By joining, you agree to the meeting guidelines and privacy policy.
          </p>
        </div>
      </div>
    </div>
  );
}

export default JoinModal;
