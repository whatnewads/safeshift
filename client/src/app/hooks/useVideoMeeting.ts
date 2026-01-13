/**
 * Video Meeting Hook for SafeShift EHR
 * 
 * Custom hook for managing video meeting state including WebRTC connections,
 * participant management, chat, and media controls.
 * 
 * @package SafeShift\Hooks
 * @author SafeShift Development Team
 * @copyright 2025 SafeShift EHR
 */

import { useState, useCallback, useRef, useEffect } from 'react';
import { videoMeetingService } from '../services/video-meeting.service.js';
import { videoSignalingService, type PeerInfo } from '../services/video-signaling.service.js';
import { webrtcService } from '../services/webrtc.service.js';
import type {
  Meeting,
  Participant,
  ChatMessage,
  MediaState,
  RemoteStream,
  VideoMeetingState,
} from '../types/video-meeting.types.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for the useVideoMeeting hook
 */
export interface UseVideoMeetingReturn {
  // State
  /** Current meeting info */
  meeting: Meeting | null;
  /** Current participant info */
  participant: Participant | null;
  /** All participants in the meeting */
  participants: Participant[];
  /** Chat messages */
  chatMessages: ChatMessage[];
  /** Local media stream */
  localStream: MediaStream | null;
  /** Remote streams */
  remoteStreams: RemoteStream[];
  /** Media controls state */
  mediaState: MediaState;
  /** Whether meeting has ended */
  meetingEnded: boolean;
  /** Meeting start time */
  meetingStartTime: Date | null;
  /** Connection status */
  connectionStatus: VideoMeetingState['connectionStatus'];
  /** Loading state */
  loading: boolean;
  /** Error message */
  error: string | null;

  // Meeting actions
  /** Create a new meeting */
  createMeeting: () => Promise<{ meeting: Meeting; joinUrl: string } | null>;
  /** Join an existing meeting */
  joinMeeting: (token: string, displayName: string) => Promise<boolean>;
  /** Leave the current meeting */
  leaveMeeting: () => Promise<void>;
  /** End the meeting (host only) */
  endMeeting: () => Promise<void>;

  // Media controls
  /** Toggle audio on/off */
  toggleAudio: () => void;
  /** Toggle video on/off */
  toggleVideo: () => void;
  /** Toggle screen sharing */
  toggleScreenShare: () => Promise<void>;
  /** Initialize local media */
  initializeMedia: (video?: boolean, audio?: boolean) => Promise<MediaStream | null>;

  // Chat
  /** Send a chat message */
  sendMessage: (message: string) => Promise<void>;
  /** Refresh chat history */
  refreshChat: () => Promise<void>;

  // Utilities
  /** Get meeting link */
  getMeetingLink: () => Promise<string>;
  /** Clear error */
  clearError: () => void;
}

// ============================================================================
// Initial State
// ============================================================================

const initialMediaState: MediaState = {
  videoEnabled: true,
  audioEnabled: true,
  screenShareEnabled: false,
};

// ============================================================================
// Hook Implementation
// ============================================================================

/**
 * Custom hook for managing video meeting state and operations
 * 
 * @example
 * ```typescript
 * const {
 *   meeting,
 *   participants,
 *   localStream,
 *   remoteStreams,
 *   createMeeting,
 *   joinMeeting,
 *   toggleAudio,
 *   toggleVideo,
 *   sendMessage,
 * } = useVideoMeeting();
 * 
 * // Create a meeting
 * const result = await createMeeting();
 * console.log('Share this link:', result?.joinUrl);
 * 
 * // Join a meeting
 * await joinMeeting('abc123', 'John Doe');
 * ```
 */
export function useVideoMeeting(): UseVideoMeetingReturn {
  // ==========================================================================
  // State
  // ==========================================================================
  
  const [meeting, setMeeting] = useState<Meeting | null>(null);
  const [participant, setParticipant] = useState<Participant | null>(null);
  const [participants, setParticipants] = useState<Participant[]>([]);
  const [chatMessages, setChatMessages] = useState<ChatMessage[]>([]);
  const [localStream, setLocalStream] = useState<MediaStream | null>(null);
  const [remoteStreamsMap, setRemoteStreamsMap] = useState<Map<string, RemoteStream>>(new Map());
  const [mediaState, setMediaState] = useState<MediaState>(initialMediaState);
  const [meetingEnded, setMeetingEnded] = useState(false);
  const [meetingStartTime, setMeetingStartTime] = useState<Date | null>(null);
  const [connectionStatus, setConnectionStatus] = useState<VideoMeetingState['connectionStatus']>('disconnected');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Refs
  const screenShareStreamRef = useRef<MediaStream | null>(null);
  const chatPollIntervalRef = useRef<number | null>(null);
  const mountedRef = useRef(true);

  // ==========================================================================
  // Cleanup
  // ==========================================================================

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
      // Cleanup on unmount
      cleanup();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const cleanup = useCallback(() => {
    // Stop chat polling
    if (chatPollIntervalRef.current) {
      clearInterval(chatPollIntervalRef.current);
      chatPollIntervalRef.current = null;
    }

    // Stop screen share
    if (screenShareStreamRef.current) {
      screenShareStreamRef.current.getTracks().forEach((track) => track.stop());
      screenShareStreamRef.current = null;
    }

    // Cleanup WebRTC
    webrtcService.cleanup();

    // Disconnect signaling
    if (meeting && participant) {
      videoSignalingService.disconnect(meeting.meetingId, participant.participantId);
    }
  }, [meeting, participant]);

  // ==========================================================================
  // Media Management
  // ==========================================================================

  /**
   * Initialize local media stream
   */
  const initializeMedia = useCallback(async (
    video = true,
    audio = true
  ): Promise<MediaStream | null> => {
    try {
      const stream = await webrtcService.getLocalStream(video, audio);
      
      if (mountedRef.current) {
        setLocalStream(stream);
        setMediaState((prev) => ({
          ...prev,
          videoEnabled: video,
          audioEnabled: audio,
        }));
      }
      
      return stream;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to access media devices';
      if (mountedRef.current) {
        setError(errorMessage);
      }
      return null;
    }
  }, []);

  /**
   * Toggle audio on/off
   */
  const toggleAudio = useCallback(() => {
    const newState = !mediaState.audioEnabled;
    webrtcService.toggleAudio(newState);
    setMediaState((prev) => ({ ...prev, audioEnabled: newState }));
  }, [mediaState.audioEnabled]);

  /**
   * Toggle video on/off
   */
  const toggleVideo = useCallback(() => {
    const newState = !mediaState.videoEnabled;
    webrtcService.toggleVideo(newState);
    setMediaState((prev) => ({ ...prev, videoEnabled: newState }));
  }, [mediaState.videoEnabled]);

  /**
   * Toggle screen sharing
   */
  const toggleScreenShare = useCallback(async () => {
    if (mediaState.screenShareEnabled) {
      // Stop screen share
      if (screenShareStreamRef.current) {
        screenShareStreamRef.current.getTracks().forEach((track) => track.stop());
        screenShareStreamRef.current = null;
      }
      setMediaState((prev) => ({ ...prev, screenShareEnabled: false }));
    } else {
      // Start screen share
      try {
        const stream = await webrtcService.startScreenShare();
        screenShareStreamRef.current = stream;
        
        // Handle when user stops sharing via browser controls
        const videoTrack = stream.getVideoTracks()[0];
        if (videoTrack) {
          videoTrack.addEventListener('ended', () => {
            setMediaState((prev) => ({ ...prev, screenShareEnabled: false }));
            screenShareStreamRef.current = null;
          });
        }
        
        setMediaState((prev) => ({ ...prev, screenShareEnabled: true }));
      } catch (err) {
        const errorMessage = err instanceof Error ? err.message : 'Failed to start screen sharing';
        setError(errorMessage);
      }
    }
  }, [mediaState.screenShareEnabled]);

  // ==========================================================================
  // WebRTC Connection Management
  // ==========================================================================

  /**
   * Handle peer updates from signaling service
   */
  const handlePeersUpdated = useCallback((peers: PeerInfo[]) => {
    // Update participants based on peer info
    setParticipants((currentParticipants) => {
      const updatedParticipants = [...currentParticipants];
      
      peers.forEach((peer) => {
        const existingIndex = updatedParticipants.findIndex(
          (p) => p.participantId === peer.participantId
        );
        
        if (existingIndex === -1) {
          // Add new participant
          updatedParticipants.push({
            participantId: peer.participantId,
            meetingId: meeting?.meetingId ?? 0,
            displayName: peer.displayName,
            joinedAt: new Date().toISOString(),
            leftAt: null,
            peerId: peer.peerId,
          });
        } else {
          // Update peer ID
          const existingParticipant = updatedParticipants[existingIndex];
          if (existingParticipant) {
            existingParticipant.peerId = peer.peerId;
          }
        }
      });
      
      return updatedParticipants;
    });

    // Connect to new peers
    const currentStream = webrtcService.getCurrentLocalStream();
    if (currentStream && participant) {
      peers.forEach(async (peer) => {
        // Don't connect to ourselves
        if (peer.participantId === participant.participantId) return;
        
        // Check if already connected
        if (remoteStreamsMap.has(peer.peerId)) return;
        
        try {
          const remoteStream = await videoSignalingService.connectToPeer(peer.peerId, currentStream);
          
          if (mountedRef.current) {
            setRemoteStreamsMap((prev) => {
              const next = new Map(prev);
              next.set(peer.peerId, {
                peerId: peer.peerId,
                participant: {
                  participantId: peer.participantId,
                  meetingId: meeting?.meetingId ?? 0,
                  displayName: peer.displayName,
                  joinedAt: new Date().toISOString(),
                  leftAt: null,
                  peerId: peer.peerId,
                },
                stream: remoteStream,
              });
              return next;
            });
          }
        } catch (err) {
          console.error('[useVideoMeeting] Failed to connect to peer:', peer.peerId, err);
        }
      });
    }
  }, [meeting, participant, remoteStreamsMap]);

  /**
   * Setup signaling callbacks
   */
  const setupSignalingCallbacks = useCallback(() => {
    // Handle incoming calls
    videoSignalingService.onIncomingCall((call, remoteStream) => {
      const peerId = call.peer;
      
      // Find participant info
      const peerParticipant = participants.find((p) => p.peerId === peerId);
      
      if (mountedRef.current) {
        setRemoteStreamsMap((prev) => {
          const next = new Map(prev);
          next.set(peerId, {
            peerId,
            participant: peerParticipant ?? {
              participantId: 0,
              meetingId: meeting?.meetingId ?? 0,
              displayName: 'Unknown',
              joinedAt: new Date().toISOString(),
              leftAt: null,
              peerId,
            },
            stream: remoteStream,
          });
          return next;
        });
      }
    });

    // Handle connection events
    videoSignalingService.onConnectionEvent((peerId, event) => {
      if (event === 'disconnected' && mountedRef.current) {
        setRemoteStreamsMap((prev) => {
          const next = new Map(prev);
          next.delete(peerId);
          return next;
        });
      }
    });
  }, [meeting, participants]);

  // ==========================================================================
  // Meeting Actions
  // ==========================================================================

  /**
   * Create a new meeting
   */
  const createMeeting = useCallback(async (): Promise<{ meeting: Meeting; joinUrl: string } | null> => {
    setLoading(true);
    setError(null);

    try {
      const response = await videoMeetingService.createMeeting();
      
      if (!response.success || !response.meeting) {
        throw new Error(response.error ?? 'Failed to create meeting');
      }

      if (mountedRef.current) {
        setMeeting(response.meeting);
        if (response.participant) {
          setParticipant(response.participant);
        }
        setMeetingStartTime(new Date());
        setConnectionStatus('connected');
      }

      return {
        meeting: response.meeting,
        joinUrl: response.joinUrl ?? '',
      };
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to create meeting';
      if (mountedRef.current) {
        setError(errorMessage);
      }
      return null;
    } finally {
      if (mountedRef.current) {
        setLoading(false);
      }
    }
  }, []);

  /**
   * Join an existing meeting
   */
  const joinMeeting = useCallback(async (token: string, displayName: string): Promise<boolean> => {
    setLoading(true);
    setError(null);
    setConnectionStatus('connecting');

    try {
      // Join via API
      const response = await videoMeetingService.joinMeeting(token, displayName);
      
      if (!response.success || !response.meeting || !response.participant) {
        throw new Error(response.error ?? 'Failed to join meeting');
      }

      if (mountedRef.current) {
        setMeeting(response.meeting);
        setParticipant(response.participant);
        setParticipants(response.participants ?? []);
        setMeetingStartTime(new Date(response.meeting.createdAt));
      }

      // Initialize local media
      const stream = await initializeMedia(true, true);
      if (!stream) {
        throw new Error('Failed to initialize media');
      }

      // Initialize PeerJS
      const peerId = await videoSignalingService.initialize();
      
      // Register peer with backend
      await videoSignalingService.registerPeer(
        response.meeting.meetingId,
        response.participant.participantId,
        peerId
      );

      // Set local stream for signaling service
      videoSignalingService.setLocalStream(stream);

      // Setup callbacks
      setupSignalingCallbacks();

      // Start heartbeat
      videoSignalingService.startHeartbeat(
        response.meeting.meetingId,
        response.participant.participantId,
        peerId
      );

      // Start polling for peer updates
      videoSignalingService.startPolling(response.meeting.meetingId, handlePeersUpdated);

      // Load chat history
      const messages = await videoMeetingService.getChatHistory(response.meeting.meetingId);
      if (mountedRef.current) {
        setChatMessages(messages);
      }

      // Start chat polling
      chatPollIntervalRef.current = window.setInterval(async () => {
        if (response.meeting) {
          const updatedMessages = await videoMeetingService.getChatHistory(response.meeting.meetingId);
          if (mountedRef.current) {
            setChatMessages(updatedMessages);
          }
        }
      }, 5000);

      if (mountedRef.current) {
        setConnectionStatus('connected');
      }

      return true;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to join meeting';
      if (mountedRef.current) {
        setError(errorMessage);
        setConnectionStatus('disconnected');
      }
      return false;
    } finally {
      if (mountedRef.current) {
        setLoading(false);
      }
    }
  }, [initializeMedia, setupSignalingCallbacks, handlePeersUpdated]);

  /**
   * Leave the current meeting
   */
  const leaveMeeting = useCallback(async () => {
    if (!meeting || !participant) return;

    try {
      // Notify backend
      await videoMeetingService.leaveMeeting(participant.participantId, meeting.meetingId);
    } catch (err) {
      console.error('[useVideoMeeting] Error leaving meeting:', err);
    } finally {
      // Cleanup regardless of API success
      cleanup();
      
      if (mountedRef.current) {
        setMeeting(null);
        setParticipant(null);
        setParticipants([]);
        setChatMessages([]);
        setLocalStream(null);
        setRemoteStreamsMap(new Map());
        setMediaState(initialMediaState);
        setMeetingEnded(true);
        setConnectionStatus('disconnected');
      }
    }
  }, [meeting, participant, cleanup]);

  /**
   * End the meeting (host only)
   */
  const endMeeting = useCallback(async () => {
    if (!meeting) return;

    try {
      await videoMeetingService.endMeeting(meeting.meetingId);
    } catch (err) {
      console.error('[useVideoMeeting] Error ending meeting:', err);
    } finally {
      // Cleanup and mark as ended
      cleanup();
      
      if (mountedRef.current) {
        setMeeting((prev) => prev ? { ...prev, isActive: false, endedAt: new Date().toISOString() } : null);
        setMeetingEnded(true);
        setConnectionStatus('disconnected');
      }
    }
  }, [meeting, cleanup]);

  // ==========================================================================
  // Chat
  // ==========================================================================

  /**
   * Send a chat message
   */
  const sendMessage = useCallback(async (message: string) => {
    if (!meeting || !participant || !message.trim()) return;

    try {
      const chatMessage = await videoMeetingService.sendChatMessage(
        meeting.meetingId,
        participant.participantId,
        message.trim()
      );
      
      if (mountedRef.current) {
        setChatMessages((prev) => [...prev, chatMessage]);
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to send message';
      if (mountedRef.current) {
        setError(errorMessage);
      }
    }
  }, [meeting, participant]);

  /**
   * Refresh chat history
   */
  const refreshChat = useCallback(async () => {
    if (!meeting) return;

    try {
      const messages = await videoMeetingService.getChatHistory(meeting.meetingId);
      if (mountedRef.current) {
        setChatMessages(messages);
      }
    } catch (err) {
      console.error('[useVideoMeeting] Error refreshing chat:', err);
    }
  }, [meeting]);

  // ==========================================================================
  // Utilities
  // ==========================================================================

  /**
   * Get meeting link
   */
  const getMeetingLink = useCallback(async (): Promise<string> => {
    if (!meeting) return '';
    
    try {
      return await videoMeetingService.getMeetingLink(meeting.meetingId);
    } catch (err) {
      console.error('[useVideoMeeting] Error getting meeting link:', err);
      return '';
    }
  }, [meeting]);

  /**
   * Clear error
   */
  const clearError = useCallback(() => {
    setError(null);
  }, []);

  // ==========================================================================
  // Return
  // ==========================================================================

  // Convert remote streams map to array
  const remoteStreams = Array.from(remoteStreamsMap.values());

  return {
    // State
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

    // Meeting actions
    createMeeting,
    joinMeeting,
    leaveMeeting,
    endMeeting,

    // Media controls
    toggleAudio,
    toggleVideo,
    toggleScreenShare,
    initializeMedia,

    // Chat
    sendMessage,
    refreshChat,

    // Utilities
    getMeetingLink,
    clearError,
  };
}

export default useVideoMeeting;
