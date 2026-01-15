/**
 * Video Meeting Page for Providers - SafeShift EHR
 * 
 * Dashboard page for authenticated providers to create and manage video meetings.
 * 
 * @package SafeShift\Pages
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Video,
  Plus,
  Clock,
  Link,
  Copy,
  Check,
  ExternalLink,
  AlertCircle,
  Loader2,
  Calendar,
  VideoOff,
} from 'lucide-react';
import { Button } from '../components/ui/button.js';
import { ShareLinkModal } from '../components/VideoMeeting/ShareLinkModal.js';
import { videoMeetingService } from '../services/video-meeting.service.js';
import { WebRTCService } from '../services/webrtc.service.js';
import { useAuth } from '../contexts/AuthContext.js';
import type { Meeting } from '../types/video-meeting.types.js';

// ============================================================================
// Meeting Card Component
// ============================================================================

interface MeetingCardProps {
  meeting: Meeting;
  onJoin: (meetingId: number) => void;
  onShare: (meetingLink: string) => void;
  onEnd: (meetingId: number) => void;
}

/**
 * Format date for display
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString([], {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Meeting card component
 */
function MeetingCard({ meeting, onJoin, onShare, onEnd }: MeetingCardProps) {
  const [copied, setCopied] = useState(false);
  const [loading, setLoading] = useState(false);

  const handleCopyLink = async () => {
    try {
      const link = await videoMeetingService.getMeetingLink(meeting.meetingId);
      await navigator.clipboard.writeText(link);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error('[MeetingCard] Failed to copy link:', err);
    }
  };

  const handleShare = async () => {
    setLoading(true);
    try {
      const link = await videoMeetingService.getMeetingLink(meeting.meetingId);
      onShare(link);
    } catch (err) {
      console.error('[MeetingCard] Failed to get link:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleEnd = async () => {
    if (confirm('Are you sure you want to end this meeting?')) {
      onEnd(meeting.meetingId);
    }
  };

  return (
    <div className="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-4">
      <div className="flex items-start justify-between gap-4">
        {/* Meeting info */}
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-2">
            <div className={`p-2 rounded-lg ${
              meeting.isActive 
                ? 'bg-green-100 dark:bg-green-900/30' 
                : 'bg-slate-100 dark:bg-slate-700'
            }`}>
              {meeting.isActive ? (
                <Video className="h-5 w-5 text-green-600 dark:text-green-400" />
              ) : (
                <VideoOff className="h-5 w-5 text-slate-400 dark:text-slate-500" />
              )}
            </div>
            <div>
              <span className={`text-sm font-medium px-2 py-0.5 rounded ${
                meeting.isActive
                  ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                  : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
              }`}>
                {meeting.isActive ? 'Active' : 'Ended'}
              </span>
            </div>
          </div>
          
          <p className="text-sm text-slate-600 dark:text-slate-400 flex items-center gap-2">
            <Calendar className="h-4 w-4" />
            Created: {formatDate(meeting.createdAt)}
          </p>
          
          {meeting.endedAt && (
            <p className="text-sm text-slate-500 dark:text-slate-500 flex items-center gap-2 mt-1">
              <Clock className="h-4 w-4" />
              Ended: {formatDate(meeting.endedAt)}
            </p>
          )}
        </div>

        {/* Actions */}
        <div className="flex items-center gap-2">
          {meeting.isActive && (
            <>
              <Button
                variant="outline"
                size="sm"
                onClick={handleCopyLink}
                className="gap-1"
              >
                {copied ? (
                  <Check className="h-4 w-4" />
                ) : (
                  <Copy className="h-4 w-4" />
                )}
                {copied ? 'Copied!' : 'Copy Link'}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={handleShare}
                disabled={loading}
                className="gap-1"
              >
                <Link className="h-4 w-4" />
                Share
              </Button>
              <Button
                size="sm"
                onClick={() => onJoin(meeting.meetingId)}
                className="gap-1"
              >
                <ExternalLink className="h-4 w-4" />
                Join
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={handleEnd}
              >
                End
              </Button>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Video Meeting Page
// ============================================================================

/**
 * Video Meeting Page
 * 
 * Provider dashboard for creating and managing video meetings.
 * 
 * @example
 * ```tsx
 * // Route: /video
 * <VideoMeeting />
 * ```
 */
export function VideoMeeting() {
  const navigate = useNavigate();
  useAuth(); // Ensures user is authenticated

  // State
  const [meetings, setMeetings] = useState<Meeting[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showShareModal, setShowShareModal] = useState(false);
  const [shareMeetingLink, setShareMeetingLink] = useState('');

  // Check WebRTC support
  const isWebRTCSupported = WebRTCService.isSupported();

  /**
   * Load user's meetings
   */
  const loadMeetings = useCallback(async () => {
    try {
      setLoading(true);
      const userMeetings = await videoMeetingService.getMyMeetings();
      // Sort by createdAt descending
      userMeetings.sort((a, b) => 
        new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
      );
      setMeetings(userMeetings);
    } catch (err) {
      console.error('[VideoMeeting] Failed to load meetings:', err);
      setError(err instanceof Error ? err.message : 'Failed to load meetings');
    } finally {
      setLoading(false);
    }
  }, []);

  // Load meetings on mount
  useEffect(() => {
    loadMeetings();
  }, [loadMeetings]);

  /**
   * Create a new meeting
   */
  const handleCreateMeeting = useCallback(async () => {
    setCreating(true);
    setError(null);

    try {
      const response = await videoMeetingService.createMeeting();
      
      if (!response.success || !response.meeting) {
        throw new Error(response.error ?? 'Failed to create meeting');
      }

      // Show share modal with link
      setShareMeetingLink(response.joinUrl ?? '');
      setShowShareModal(true);

      // Reload meetings
      await loadMeetings();
    } catch (err) {
      console.error('[VideoMeeting] Failed to create meeting:', err);
      setError(err instanceof Error ? err.message : 'Failed to create meeting');
    } finally {
      setCreating(false);
    }
  }, [loadMeetings]);

  /**
   * Join a meeting
   */
  const handleJoinMeeting = useCallback((meetingId: number) => {
    navigate(`/video/meeting/${meetingId}`);
  }, [navigate]);

  /**
   * Share meeting link
   */
  const handleShareMeeting = useCallback((link: string) => {
    setShareMeetingLink(link);
    setShowShareModal(true);
  }, []);

  /**
   * End a meeting
   */
  const handleEndMeeting = useCallback(async (meetingId: number) => {
    try {
      await videoMeetingService.endMeeting(meetingId);
      await loadMeetings();
    } catch (err) {
      console.error('[VideoMeeting] Failed to end meeting:', err);
      setError(err instanceof Error ? err.message : 'Failed to end meeting');
    }
  }, [loadMeetings]);

  // Separate active and ended meetings
  const activeMeetings = meetings.filter((m) => m.isActive);
  const endedMeetings = meetings.filter((m) => !m.isActive);

  // ============================================================================
  // Render
  // ============================================================================

  // WebRTC not supported
  if (!isWebRTCSupported) {
    return (
      <div className="p-6">
        <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <AlertCircle className="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="font-medium text-amber-800 dark:text-amber-200">
                Browser Not Supported
              </h3>
              <p className="text-sm text-amber-700 dark:text-amber-300 mt-1">
                Your browser doesn't support video calls. Please use Chrome, Firefox, Safari, or Edge.
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <Video className="h-7 w-7 text-blue-600 dark:text-blue-400" />
            Video Meetings
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Create and manage video meetings with patients or colleagues
          </p>
        </div>
        <Button
          onClick={handleCreateMeeting}
          disabled={creating}
          className="gap-2"
        >
          {creating ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Plus className="h-4 w-4" />
          )}
          Create Meeting
        </Button>
      </div>

      {/* Error banner */}
      {error && (
        <div className="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <AlertCircle className="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
            </div>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setError(null)}
              className="text-red-600 dark:text-red-400"
            >
              Dismiss
            </Button>
          </div>
        </div>
      )}

      {/* Loading state */}
      {loading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-8 w-8 text-blue-600 animate-spin" />
        </div>
      ) : (
        <>
          {/* Active Meetings */}
          <section className="mb-8">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
              <div className="h-2 w-2 rounded-full bg-green-500" />
              Active Meetings
              <span className="text-sm font-normal text-slate-500 dark:text-slate-400">
                ({activeMeetings.length})
              </span>
            </h2>

            {activeMeetings.length === 0 ? (
              <div className="bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-200 dark:border-slate-700 p-8 text-center">
                <Video className="h-12 w-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" />
                <p className="text-slate-500 dark:text-slate-400 mb-4">
                  No active meetings
                </p>
                <Button onClick={handleCreateMeeting} disabled={creating}>
                  <Plus className="h-4 w-4 mr-2" />
                  Create Your First Meeting
                </Button>
              </div>
            ) : (
              <div className="space-y-3">
                {activeMeetings.map((meeting) => (
                  <MeetingCard
                    key={meeting.meetingId}
                    meeting={meeting}
                    onJoin={handleJoinMeeting}
                    onShare={handleShareMeeting}
                    onEnd={handleEndMeeting}
                  />
                ))}
              </div>
            )}
          </section>

          {/* Recent Meetings */}
          {endedMeetings.length > 0 && (
            <section>
              <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                <Clock className="h-5 w-5 text-slate-400" />
                Recent Meetings
                <span className="text-sm font-normal text-slate-500 dark:text-slate-400">
                  ({endedMeetings.length})
                </span>
              </h2>

              <div className="space-y-3">
                {endedMeetings.slice(0, 10).map((meeting) => (
                  <MeetingCard
                    key={meeting.meetingId}
                    meeting={meeting}
                    onJoin={handleJoinMeeting}
                    onShare={handleShareMeeting}
                    onEnd={handleEndMeeting}
                  />
                ))}
              </div>

              {endedMeetings.length > 10 && (
                <p className="text-sm text-slate-500 dark:text-slate-400 text-center mt-4">
                  Showing 10 of {endedMeetings.length} ended meetings
                </p>
              )}
            </section>
          )}
        </>
      )}

      {/* Share Link Modal */}
      <ShareLinkModal
        isOpen={showShareModal}
        meetingLink={shareMeetingLink}
        onClose={() => setShowShareModal(false)}
        onJoinMeeting={() => {
          setShowShareModal(false);
          // Navigate to the meeting join page using the shareMeetingLink
          if (shareMeetingLink) {
            window.location.href = shareMeetingLink;
          }
        }}
      />
    </div>
  );
}

export default VideoMeeting;
