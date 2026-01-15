/**
 * Participants List Component for SafeShift EHR
 * 
 * Displays list of meeting participants with status indicators.
 * Shows audio/video status and active speaker highlighting.
 * 
 * @package SafeShift\Components\VideoMeeting
 */

import { useMemo } from 'react';
import { Users, Mic, MicOff, Video, VideoOff, Circle } from 'lucide-react';
import { ScrollArea } from '../ui/scroll-area.js';
import type { ParticipantsListProps, Participant } from '../../types/video-meeting.types.js';

// ============================================================================
// Participant Item Component
// ============================================================================

interface ParticipantItemProps {
  participant: Participant;
  isCurrentUser: boolean;
  isConnected: boolean;
  hasAudio?: boolean;
  hasVideo?: boolean;
}

/**
 * Single participant item with status indicators
 */
function ParticipantItem({
  participant,
  isCurrentUser,
  isConnected,
  hasAudio = true,
  hasVideo = true,
}: ParticipantItemProps) {
  const initials = participant.displayName
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);

  return (
    <div className="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
      {/* Avatar */}
      <div className="relative">
        <div className="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-medium text-sm">
          {initials || '?'}
        </div>
        {/* Online indicator */}
        <Circle
          className={`absolute -bottom-0.5 -right-0.5 h-3 w-3 fill-current ${
            isConnected ? 'text-green-500' : 'text-slate-400'
          }`}
        />
      </div>

      {/* Name and status */}
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">
          {participant.displayName}
          {isCurrentUser && (
            <span className="text-slate-400 dark:text-slate-500 ml-1">(You)</span>
          )}
        </p>
        <p className="text-xs text-slate-500 dark:text-slate-400">
          {participant.leftAt ? 'Left' : isConnected ? 'In meeting' : 'Connecting...'}
        </p>
      </div>

      {/* Audio/Video status icons */}
      <div className="flex items-center gap-1">
        {hasAudio ? (
          <Mic className="h-4 w-4 text-slate-400 dark:text-slate-500" />
        ) : (
          <MicOff className="h-4 w-4 text-red-500" />
        )}
        {hasVideo ? (
          <Video className="h-4 w-4 text-slate-400 dark:text-slate-500" />
        ) : (
          <VideoOff className="h-4 w-4 text-red-500" />
        )}
      </div>
    </div>
  );
}

// ============================================================================
// Participants List Component
// ============================================================================

/**
 * Participants List Component
 * 
 * Shows all meeting participants with their connection status.
 * 
 * @example
 * ```tsx
 * <ParticipantsList
 *   participants={participants}
 *   currentParticipantId={participant.participantId}
 *   remoteStreams={remoteStreamsMap}
 * />
 * ```
 */
export function ParticipantsList({
  participants,
  currentParticipantId,
  remoteStreams,
}: ParticipantsListProps) {
  // Filter to only active participants (not left)
  const activeParticipants = useMemo(() => {
    return participants.filter((p) => !p.leftAt);
  }, [participants]);

  // Create a set of connected peer IDs
  const connectedPeerIds = useMemo(() => {
    return new Set(remoteStreams.keys());
  }, [remoteStreams]);

  return (
    <div className="flex flex-col h-full bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
      {/* Header */}
      <div className="flex items-center gap-2 px-4 py-3 border-b border-slate-200 dark:border-slate-700">
        <Users className="h-5 w-5 text-slate-500 dark:text-slate-400" />
        <span className="font-medium text-slate-700 dark:text-slate-200">
          Participants
        </span>
        <span className="ml-auto text-sm text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded-full">
          {activeParticipants.length}
        </span>
      </div>

      {/* Participants list */}
      <ScrollArea className="flex-1">
        <div className="p-2 space-y-1">
          {activeParticipants.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-8 text-slate-400 dark:text-slate-500">
              <Users className="h-8 w-8 mb-2" />
              <p className="text-sm">No participants yet</p>
            </div>
          ) : (
            activeParticipants.map((participant) => {
              const isCurrentUser = participant.participantId === currentParticipantId;
              const isConnected =
                isCurrentUser || (participant.peerId ? connectedPeerIds.has(participant.peerId) : false);

              return (
                <ParticipantItem
                  key={participant.participantId}
                  participant={participant}
                  isCurrentUser={isCurrentUser}
                  isConnected={isConnected}
                />
              );
            })
          )}
        </div>
      </ScrollArea>
    </div>
  );
}

export default ParticipantsList;
