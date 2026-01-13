/**
 * Video Display Component for SafeShift EHR
 * 
 * Displays local and remote video streams in a responsive grid layout.
 * Supports fullscreen mode and adapts to number of participants.
 * 
 * @package SafeShift\Components\VideoMeeting
 */

import { useRef, useEffect, useState, useCallback } from 'react';
import { Maximize2, Minimize2, VideoOff, User } from 'lucide-react';
import { Button } from '../ui/button.js';
import type { VideoDisplayProps, RemoteStream } from '../../types/video-meeting.types.js';

// ============================================================================
// Video Element Component
// ============================================================================

interface VideoElementProps {
  stream: MediaStream | null;
  muted?: boolean;
  displayName: string;
  isLocal?: boolean;
  videoEnabled?: boolean;
  className?: string;
}

/**
 * Single video element with placeholder when no video
 */
function VideoElement({
  stream,
  muted = false,
  displayName,
  isLocal = false,
  videoEnabled = true,
  className = '',
}: VideoElementProps) {
  const videoRef = useRef<HTMLVideoElement>(null);

  useEffect(() => {
    if (videoRef.current && stream) {
      videoRef.current.srcObject = stream;
    }
  }, [stream]);

  const initials = displayName
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);

  return (
    <div className={`relative bg-slate-800 rounded-lg overflow-hidden ${className}`}>
      {stream && videoEnabled ? (
        <video
          ref={videoRef}
          autoPlay
          playsInline
          muted={muted}
          className={`w-full h-full object-cover ${isLocal ? 'scale-x-[-1]' : ''}`}
        />
      ) : (
        <div className="w-full h-full flex items-center justify-center bg-slate-700">
          <div className="flex flex-col items-center gap-2">
            {videoEnabled ? (
              <User className="h-16 w-16 text-slate-400" />
            ) : (
              <VideoOff className="h-12 w-12 text-slate-400" />
            )}
            <div className="w-16 h-16 rounded-full bg-slate-600 flex items-center justify-center text-2xl font-medium text-white">
              {initials || '?'}
            </div>
          </div>
        </div>
      )}
      
      {/* Name badge */}
      <div className="absolute bottom-2 left-2 px-2 py-1 bg-black/60 rounded text-sm text-white">
        {displayName} {isLocal && '(You)'}
      </div>
    </div>
  );
}

// ============================================================================
// Grid Layout Helper
// ============================================================================

/**
 * Calculate grid layout based on participant count
 */
function getGridLayout(participantCount: number): { cols: number; rows: number } {
  if (participantCount <= 1) return { cols: 1, rows: 1 };
  if (participantCount === 2) return { cols: 2, rows: 1 };
  if (participantCount <= 4) return { cols: 2, rows: 2 };
  if (participantCount <= 6) return { cols: 3, rows: 2 };
  if (participantCount <= 9) return { cols: 3, rows: 3 };
  return { cols: 4, rows: Math.ceil(participantCount / 4) };
}

// ============================================================================
// Video Display Component
// ============================================================================

/**
 * Video Display Component
 * 
 * Shows local video in corner, remote videos in grid.
 * Supports fullscreen toggle.
 * 
 * @example
 * ```tsx
 * <VideoDisplay
 *   localStream={localStream}
 *   remoteStreams={remoteStreams}
 *   localVideoEnabled={mediaState.videoEnabled}
 *   localDisplayName="John Doe"
 * />
 * ```
 */
export function VideoDisplay({
  localStream,
  remoteStreams,
  localVideoEnabled,
  localDisplayName,
  onToggleFullscreen,
}: VideoDisplayProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);

  // Listen for fullscreen changes
  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(!!document.fullscreenElement);
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  const toggleFullscreen = useCallback(async () => {
    if (!containerRef.current) return;

    try {
      if (!document.fullscreenElement) {
        await containerRef.current.requestFullscreen();
      } else {
        await document.exitFullscreen();
      }
      onToggleFullscreen?.();
    } catch (err) {
      console.error('[VideoDisplay] Fullscreen error:', err);
    }
  }, [onToggleFullscreen]);

  // Calculate grid layout
  const totalParticipants = remoteStreams.length + 1; // +1 for local
  const gridLayout = getGridLayout(totalParticipants);
  const showLocalInGrid = remoteStreams.length === 0;

  return (
    <div
      ref={containerRef}
      className="relative w-full h-full bg-slate-900 rounded-lg overflow-hidden"
    >
      {/* Main video grid */}
      <div
        className="w-full h-full p-2"
        style={{
          display: 'grid',
          gridTemplateColumns: `repeat(${gridLayout.cols}, 1fr)`,
          gridTemplateRows: `repeat(${gridLayout.rows}, 1fr)`,
          gap: '8px',
        }}
      >
        {/* Show local in grid if alone */}
        {showLocalInGrid && (
          <VideoElement
            stream={localStream}
            muted={true}
            displayName={localDisplayName}
            isLocal={true}
            videoEnabled={localVideoEnabled}
            className="w-full h-full"
          />
        )}

        {/* Remote streams */}
        {remoteStreams.map((remote: RemoteStream) => (
          <VideoElement
            key={remote.peerId}
            stream={remote.stream}
            muted={false}
            displayName={remote.participant.displayName}
            videoEnabled={true}
            className="w-full h-full"
          />
        ))}

        {/* Fill empty grid slots if needed */}
        {!showLocalInGrid && remoteStreams.length > 0 && (
          <VideoElement
            stream={localStream}
            muted={true}
            displayName={localDisplayName}
            isLocal={true}
            videoEnabled={localVideoEnabled}
            className="w-full h-full"
          />
        )}
      </div>

      {/* Local video picture-in-picture (when others present) */}
      {!showLocalInGrid && remoteStreams.length > 1 && (
        <div className="absolute bottom-4 right-4 w-48 h-36 z-10">
          <VideoElement
            stream={localStream}
            muted={true}
            displayName={localDisplayName}
            isLocal={true}
            videoEnabled={localVideoEnabled}
            className="w-full h-full shadow-lg"
          />
        </div>
      )}

      {/* Fullscreen toggle button */}
      <Button
        variant="ghost"
        size="sm"
        onClick={toggleFullscreen}
        className="absolute top-2 right-2 bg-black/40 hover:bg-black/60 text-white"
      >
        {isFullscreen ? (
          <Minimize2 className="h-4 w-4" />
        ) : (
          <Maximize2 className="h-4 w-4" />
        )}
      </Button>
    </div>
  );
}

export default VideoDisplay;
