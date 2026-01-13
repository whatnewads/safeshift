/**
 * Video Meeting Join Page for SafeShift EHR
 * 
 * Public page for joining video meetings via token link.
 * Validates token and shows JoinModal for name entry.
 * 
 * @package SafeShift\Pages
 */

import { useState, useEffect, useCallback } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { 
  Video, 
  AlertCircle, 
  Loader2, 
  Home,
  Clock,
  Ban,
} from 'lucide-react';
import { Button } from '../components/ui/button.js';
import { JoinModal } from '../components/VideoMeeting/JoinModal.js';
import { videoMeetingService } from '../services/video-meeting.service.js';
import { WebRTCService } from '../services/webrtc.service.js';
import type { Meeting } from '../types/video-meeting.types.js';

// ============================================================================
// Error States
// ============================================================================

type ErrorType = 'invalid' | 'expired' | 'ended' | 'not-found' | 'error';

interface ErrorState {
  type: ErrorType;
  message: string;
}

const ERROR_MESSAGES: Record<ErrorType, { title: string; description: string }> = {
  invalid: {
    title: 'Invalid Meeting Link',
    description: 'This meeting link is not valid. Please check the link and try again.',
  },
  expired: {
    title: 'Meeting Link Expired',
    description: 'This meeting link has expired. Please request a new link from the meeting host.',
  },
  ended: {
    title: 'Meeting Ended',
    description: 'This meeting has already ended.',
  },
  'not-found': {
    title: 'Meeting Not Found',
    description: 'The meeting you\'re trying to join doesn\'t exist.',
  },
  error: {
    title: 'Unable to Join',
    description: 'Something went wrong while trying to join the meeting. Please try again.',
  },
};

// ============================================================================
// Video Meeting Join Page
// ============================================================================

/**
 * Video Meeting Join Page
 * 
 * Handles the public join flow for video meetings:
 * 1. Validates token from URL
 * 2. Shows JoinModal for name entry
 * 3. Redirects to VideoMeetingPage after successful join
 * 
 * @example
 * ```tsx
 * // Route: /video/join?token=abc123
 * <VideoMeetingJoin />
 * ```
 */
export function VideoMeetingJoin() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');

  // State
  const [loading, setLoading] = useState(true);
  const [validating, setValidating] = useState(true);
  const [joining, setJoining] = useState(false);
  const [meeting, setMeeting] = useState<Meeting | null>(null);
  const [errorState, setErrorState] = useState<ErrorState | null>(null);
  const [showJoinModal, setShowJoinModal] = useState(false);
  const [joinError, setJoinError] = useState<string | null>(null);

  // Check WebRTC support
  const isWebRTCSupported = WebRTCService.isSupported();

  /**
   * Validate token on mount
   */
  useEffect(() => {
    const validateToken = async () => {
      if (!token) {
        setErrorState({
          type: 'invalid',
          message: 'No meeting token provided',
        });
        setLoading(false);
        setValidating(false);
        return;
      }

      try {
        const response = await videoMeetingService.validateToken(token);

        if (!response.success || !response.valid) {
          // Determine error type based on response
          if (response.error?.toLowerCase().includes('expired')) {
            setErrorState({ type: 'expired', message: response.error });
          } else if (response.error?.toLowerCase().includes('ended')) {
            setErrorState({ type: 'ended', message: response.error });
          } else if (response.error?.toLowerCase().includes('not found')) {
            setErrorState({ type: 'not-found', message: response.error });
          } else {
            setErrorState({ type: 'invalid', message: response.error ?? 'Invalid token' });
          }
        } else if (response.meeting) {
          // Token is valid
          if (!response.meeting.isActive) {
            setErrorState({ type: 'ended', message: 'Meeting has ended' });
          } else {
            setMeeting(response.meeting);
            setShowJoinModal(true);
          }
        }
      } catch (err) {
        console.error('[VideoMeetingJoin] Token validation error:', err);
        setErrorState({
          type: 'error',
          message: err instanceof Error ? err.message : 'Failed to validate meeting',
        });
      } finally {
        setLoading(false);
        setValidating(false);
      }
    };

    validateToken();
  }, [token]);

  /**
   * Handle join submission
   */
  const handleJoin = useCallback(async (displayName: string) => {
    if (!token) return;

    setJoining(true);
    setJoinError(null);

    try {
      const response = await videoMeetingService.joinMeeting(token, displayName);

      if (!response.success || !response.meeting || !response.participant) {
        throw new Error(response.error ?? 'Failed to join meeting');
      }

      // Redirect to meeting page
      navigate(`/video/meeting/${response.meeting.meetingId}`, {
        state: {
          meeting: response.meeting,
          participant: response.participant,
          participants: response.participants,
        },
      });
    } catch (err) {
      console.error('[VideoMeetingJoin] Join error:', err);
      setJoinError(err instanceof Error ? err.message : 'Failed to join meeting');
    } finally {
      setJoining(false);
    }
  }, [token, navigate]);

  /**
   * Handle cancel
   */
  const handleCancel = useCallback(() => {
    navigate('/');
  }, [navigate]);

  // ============================================================================
  // Render States
  // ============================================================================

  // WebRTC not supported
  if (!isWebRTCSupported) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-900 p-6">
        <Ban className="h-16 w-16 text-red-500 mb-4" />
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">
          Browser Not Supported
        </h1>
        <p className="text-slate-600 dark:text-slate-400 text-center max-w-md mb-6">
          Your browser doesn't support video calls. Please use a modern browser
          like Chrome, Firefox, Safari, or Edge.
        </p>
        <Button onClick={() => navigate('/')}>
          <Home className="h-4 w-4 mr-2" />
          Go Home
        </Button>
      </div>
    );
  }

  // Loading/Validating
  if (loading || validating) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-900">
        <Loader2 className="h-12 w-12 text-blue-600 animate-spin mb-4" />
        <p className="text-slate-600 dark:text-slate-400">Validating meeting link...</p>
      </div>
    );
  }

  // Error state
  if (errorState) {
    const errorInfo = ERROR_MESSAGES[errorState.type];
    const ErrorIcon = errorState.type === 'expired' ? Clock : 
                      errorState.type === 'ended' ? Video :
                      AlertCircle;

    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-900 p-6">
        <ErrorIcon className={`h-16 w-16 mb-4 ${
          errorState.type === 'expired' ? 'text-amber-500' :
          errorState.type === 'ended' ? 'text-slate-400' :
          'text-red-500'
        }`} />
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">
          {errorInfo.title}
        </h1>
        <p className="text-slate-600 dark:text-slate-400 text-center max-w-md mb-6">
          {errorInfo.description}
        </p>
        <div className="flex gap-3">
          {errorState.type !== 'ended' && (
            <Button
              variant="outline"
              onClick={() => window.location.reload()}
            >
              Try Again
            </Button>
          )}
          <Button onClick={() => navigate('/')}>
            <Home className="h-4 w-4 mr-2" />
            Go Home
          </Button>
        </div>
      </div>
    );
  }

  // Main view with Join Modal
  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900">
      {/* Background pattern */}
      <div className="absolute inset-0 bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-slate-900 dark:to-slate-800" />

      {/* Join content */}
      <div className="relative flex flex-col items-center justify-center min-h-screen p-6">
        <div className="text-center mb-8">
          <Video className="h-16 w-16 text-blue-600 dark:text-blue-400 mx-auto mb-4" />
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white mb-2">
            SafeShift Video Meeting
          </h1>
          <p className="text-slate-600 dark:text-slate-400">
            Enter your name to join the meeting
          </p>
        </div>
      </div>

      {/* Join Modal */}
      <JoinModal
        isOpen={showJoinModal}
        token={token ?? ''}
        {...(meeting ? { meeting } : {})}
        onJoin={handleJoin}
        onCancel={handleCancel}
        loading={joining}
        error={joinError}
      />
    </div>
  );
}

export default VideoMeetingJoin;
