/**
 * Video Meeting Page Component for SafeShift EHR
 * 
 * Main container page for video meetings. Brings together video display,
 * chat, participants list, and meeting controls.
 * 
 * @package SafeShift\Components\VideoMeeting
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { 
  ArrowLeft, 
  Share2, 
  Video as VideoIcon,
  AlertCircle,
  Loader2,
  Home,
} from 'lucide-react';
import { Button } from '../ui/button.js';
import { VideoDisplay } from './VideoDisplay.js';
import { ChatBox } from './ChatBox.js';
import { ParticipantsList } from './ParticipantsList.js';
import { MeetingControls } from './MeetingControls.js';
import { ShareLinkModal } from './ShareLinkModal.js';
import { CallTimer } from './CallTimer.js';
import { useVideoMeeting } from '../../hooks/useVideoMeeting.js';
import { WebRTCService } from '../../services/webrtc.service.js';

// ============================================================================
// Video Meeting Page Component
// ============================================================================

/**
 * Video Meeting Page
 * 
 * Main container for active video meetings. Shows video streams,
 * chat, participants, and meeting controls.
 * 
 * @example
 * ```tsx
 * // Route: /video/meeting/:meetingId
 * <VideoMeetingPage />
 * ```
 */
export function VideoMeetingPage() {
  const navigate = useNavigate();
  const { meetingId } = useParams<{ meetingId: string }>();
  
  // Video meeting hook
  const {
    meeting,
    participant,
    participants,
    chatMessages,
    localStream,
    remoteStreams,
    mediaState,
    meetingEnded,
    meetingStartTime,
    connectionStatus,
    loading,
    error,
    toggleAudio,
    toggleVideo,
    toggleScreenShare,
    leaveMeeting,
    endMeeting,
    sendMessage,
    getMeetingLink,
    clearError,
  } = useVideoMeeting();

  // Local state
  const [showShareModal, setShowShareModal] = useState(false);
  const [meetingLink, setMeetingLink] = useState('');
  const [sidebarTab, setSidebarTab] = useState<'chat' | 'participants'>('chat');

  // Check WebRTC support
  const isWebRTCSupported = WebRTCService.isSupported();
  const isScreenShareSupported = WebRTCService.isScreenShareSupported();

  /**
   * Handle share button click
   */
  const handleShare = useCallback(async () => {
    const link = await getMeetingLink();
    setMeetingLink(link);
    setShowShareModal(true);
  }, [getMeetingLink]);

  /**
   * Handle leave meeting
   */
  const handleLeave = useCallback(async () => {
    await leaveMeeting();
    navigate('/dashboard');
  }, [leaveMeeting, navigate]);

  /**
   * Handle end meeting (host only)
   */
  const handleEndMeeting = useCallback(async () => {
    await endMeeting();
  }, [endMeeting]);

  /**
   * Handle back to dashboard
   */
  const handleBackToDashboard = useCallback(() => {
    navigate('/dashboard');
  }, [navigate]);

  // Create remote streams map for ParticipantsList
  const remoteStreamsMap = new Map(
    remoteStreams.map((rs) => [rs.peerId, rs])
  );

  // ============================================================================
  // Render States
  // ============================================================================

  // WebRTC not supported
  if (!isWebRTCSupported) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-900 p-6">
        <AlertCircle className="h-16 w-16 text-red-500 mb-4" />
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">
          Browser Not Supported
        </h1>
        <p className="text-slate-600 dark:text-slate-400 text-center max-w-md mb-6">
          Your browser doesn't support video calls. Please use a modern browser
          like Chrome, Firefox, Safari, or Edge.
        </p>
        <Button onClick={handleBackToDashboard}>
          <Home className="h-4 w-4 mr-2" />
          Go to Dashboard
        </Button>
      </div>
    );
  }

  // Loading state
  if (loading && !meeting) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-900">
        <Loader2 className="h-12 w-12 text-blue-600 animate-spin mb-4" />
        <p className="text-slate-600 dark:text-slate-400">Loading meeting...</p>
      </div>
    );
  }

  // Meeting ended state
  if (meetingEnded || (meeting && !meeting.isActive)) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-900 p-6">
        <VideoIcon className="h-16 w-16 text-slate-400 mb-4" />
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">
          Meeting Ended
        </h1>
        <p className="text-slate-600 dark:text-slate-400 text-center max-w-md mb-6">
          {meeting?.endedAt 
            ? `This meeting ended at ${new Date(meeting.endedAt).toLocaleTimeString()}.`
            : 'This meeting has ended. Thank you for participating.'}
        </p>
        <Button onClick={handleBackToDashboard} size="lg">
          <Home className="h-4 w-4 mr-2" />
          Go Back to Dashboard
        </Button>
      </div>
    );
  }

  // Error state
  if (error && !meeting) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-900 p-6">
        <AlertCircle className="h-16 w-16 text-red-500 mb-4" />
        <h1 className="text-2xl font-bold text-slate-900 dark:text-white mb-2">
          Unable to Join Meeting
        </h1>
        <p className="text-slate-600 dark:text-slate-400 text-center max-w-md mb-6">
          {error}
        </p>
        <div className="flex gap-3">
          <Button variant="outline" onClick={() => window.location.reload()}>
            Try Again
          </Button>
          <Button onClick={handleBackToDashboard}>
            <Home className="h-4 w-4 mr-2" />
            Go to Dashboard
          </Button>
        </div>
      </div>
    );
  }

  // ============================================================================
  // Main Meeting UI
  // ============================================================================

  return (
    <div className="flex flex-col h-screen bg-slate-100 dark:bg-slate-900">
      {/* Top Bar */}
      <header className="flex items-center justify-between px-4 py-2 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
        {/* Left: Back button and title */}
        <div className="flex items-center gap-3">
          <Button
            variant="ghost"
            size="sm"
            onClick={handleBackToDashboard}
            className="h-8 w-8 p-0"
            title="Back to Dashboard"
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div className="flex items-center gap-2">
            <VideoIcon className="h-5 w-5 text-blue-600 dark:text-blue-400" />
            <span className="font-semibold text-slate-900 dark:text-white">
              Custom Video Meeting
            </span>
          </div>
        </div>

        {/* Center: Timer */}
        <CallTimer startTime={meetingStartTime} />

        {/* Right: Share and Recording status */}
        <div className="flex items-center gap-3">
          <Button
            variant="ghost"
            size="sm"
            onClick={handleShare}
            title="Share meeting link"
          >
            <Share2 className="h-4 w-4" />
          </Button>
          <span className="text-sm text-slate-500 dark:text-slate-400 px-2 py-1 bg-slate-100 dark:bg-slate-700 rounded">
            Not Recording
          </span>
        </div>
      </header>

      {/* Error banner */}
      {error && (
        <div className="bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800 px-4 py-2">
          <div className="flex items-center gap-2">
            <AlertCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
            <p className="text-sm text-red-600 dark:text-red-400 flex-1">{error}</p>
            <Button
              variant="ghost"
              size="sm"
              onClick={clearError}
              className="text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30"
            >
              Dismiss
            </Button>
          </div>
        </div>
      )}

      {/* Main Content */}
      <div className="flex-1 flex overflow-hidden">
        {/* Video Area */}
        <div className="flex-1 p-4">
          <VideoDisplay
            localStream={localStream}
            remoteStreams={remoteStreams}
            localVideoEnabled={mediaState.videoEnabled}
            localDisplayName={participant?.displayName ?? 'You'}
          />
        </div>

        {/* Sidebar */}
        <div className="w-80 flex flex-col border-l border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
          {/* Sidebar tabs */}
          <div className="flex border-b border-slate-200 dark:border-slate-700">
            <button
              onClick={() => setSidebarTab('chat')}
              className={`flex-1 px-4 py-2 text-sm font-medium transition-colors ${
                sidebarTab === 'chat'
                  ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
                  : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'
              }`}
            >
              Chat
              {chatMessages.length > 0 && (
                <span className="ml-2 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded-full">
                  {chatMessages.length}
                </span>
              )}
            </button>
            <button
              onClick={() => setSidebarTab('participants')}
              className={`flex-1 px-4 py-2 text-sm font-medium transition-colors ${
                sidebarTab === 'participants'
                  ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
                  : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'
              }`}
            >
              Participants
              <span className="ml-2 text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 px-1.5 py-0.5 rounded-full">
                {participants.filter((p) => !p.leftAt).length}
              </span>
            </button>
          </div>

          {/* Sidebar content */}
          <div className="flex-1 overflow-hidden">
            {sidebarTab === 'chat' ? (
              <ChatBox
                messages={chatMessages}
                currentParticipantId={participant?.participantId ?? 0}
                onSendMessage={sendMessage}
                loading={loading}
              />
            ) : (
              <ParticipantsList
                participants={participants}
                currentParticipantId={participant?.participantId ?? 0}
                remoteStreams={remoteStreamsMap}
              />
            )}
          </div>
        </div>
      </div>

      {/* Meeting Controls */}
      <MeetingControls
        mediaState={mediaState}
        onToggleAudio={toggleAudio}
        onToggleVideo={toggleVideo}
        onToggleScreenShare={toggleScreenShare}
        onEndCall={handleLeave}
        screenShareSupported={isScreenShareSupported}
      />

      {/* Share Link Modal */}
      <ShareLinkModal
        isOpen={showShareModal}
        meetingLink={meetingLink}
        onClose={() => setShowShareModal(false)}
      />
    </div>
  );
}

export default VideoMeetingPage;
