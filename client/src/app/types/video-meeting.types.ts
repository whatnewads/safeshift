/**
 * Video Meeting Types for SafeShift EHR
 * 
 * Type definitions for video meeting features including meetings,
 * participants, chat messages, and session management.
 * 
 * @package SafeShift\Types
 * @author SafeShift Development Team
 * @copyright 2025 SafeShift EHR
 */

// ============================================================================
// Meeting Types
// ============================================================================

/**
 * Video meeting record
 */
export interface Meeting {
  /** Unique meeting identifier */
  meetingId: number;
  /** User ID who created the meeting */
  createdBy: number;
  /** ISO timestamp when meeting was created */
  createdAt: string;
  /** Unique meeting join token */
  token: string;
  /** ISO timestamp when token expires */
  tokenExpiresAt: string;
  /** Whether the meeting is currently active */
  isActive: boolean;
  /** ISO timestamp when meeting ended, null if active */
  endedAt: string | null;
}

/**
 * Meeting participant record
 */
export interface Participant {
  /** Unique participant identifier */
  participantId: number;
  /** Associated meeting ID */
  meetingId: number;
  /** Participant's display name */
  displayName: string;
  /** ISO timestamp when participant joined */
  joinedAt: string;
  /** ISO timestamp when participant left, null if still in meeting */
  leftAt: string | null;
  /** PeerJS peer ID for WebRTC connections */
  peerId: string | null;
  /** User ID if authenticated (for providers) */
  userId?: number | null;
}

/**
 * Chat message record
 */
export interface ChatMessage {
  /** Unique message identifier */
  messageId: number;
  /** Associated meeting ID */
  meetingId: number;
  /** Participant who sent the message */
  participantId: number;
  /** Display name of sender */
  displayName: string;
  /** Message content */
  messageText: string;
  /** ISO timestamp when message was sent */
  sentAt: string;
}

/**
 * Meeting session containing full context
 */
export interface MeetingSession {
  /** The meeting record */
  meeting: Meeting;
  /** Current user's participant record */
  participant: Participant;
  /** All participants in the meeting */
  participants: Participant[];
}

// ============================================================================
// API Response Types
// ============================================================================

/**
 * Response from creating a meeting
 */
export interface CreateMeetingResponse {
  success: boolean;
  message?: string;
  error?: string;
  meeting?: Meeting;
  participant?: Participant;
  joinUrl?: string;
}

/**
 * Response from validating a meeting token
 */
export interface ValidateTokenResponse {
  success: boolean;
  message?: string;
  error?: string;
  valid?: boolean;
  meeting?: Meeting;
  expiresAt?: string;
}

/**
 * Response from joining a meeting
 */
export interface JoinMeetingResponse {
  success: boolean;
  message?: string;
  error?: string;
  meeting?: Meeting;
  participant?: Participant;
  participants?: Participant[];
}

/**
 * Response from getting participants
 */
export interface GetParticipantsResponse {
  success: boolean;
  message?: string;
  error?: string;
  participants?: Participant[];
  count?: number;
}

/**
 * Response from sending a chat message
 */
export interface SendMessageResponse {
  success: boolean;
  message?: string;
  error?: string;
  chatMessage?: ChatMessage;
}

/**
 * Response from getting chat history
 */
export interface GetChatHistoryResponse {
  success: boolean;
  message?: string;
  error?: string;
  messages?: ChatMessage[];
  count?: number;
}

/**
 * Response from getting meeting link
 */
export interface GetMeetingLinkResponse {
  success: boolean;
  message?: string;
  error?: string;
  joinUrl?: string;
  token?: string;
  expiresAt?: string;
}

/**
 * Response from getting user's meetings
 */
export interface GetMyMeetingsResponse {
  success: boolean;
  message?: string;
  error?: string;
  meetings?: Meeting[];
  count?: number;
}

// ============================================================================
// State Types
// ============================================================================

/**
 * Media state for video controls
 */
export interface MediaState {
  /** Whether video is enabled */
  videoEnabled: boolean;
  /** Whether audio is enabled */
  audioEnabled: boolean;
  /** Whether screen sharing is active */
  screenShareEnabled: boolean;
}

/**
 * Remote stream with peer info
 */
export interface RemoteStream {
  /** Peer ID */
  peerId: string;
  /** Participant info */
  participant: Participant;
  /** Media stream */
  stream: MediaStream;
}

/**
 * Video meeting state
 */
export interface VideoMeetingState {
  /** Current meeting info */
  meeting: Meeting | null;
  /** Current participant info */
  participant: Participant | null;
  /** All participants */
  participants: Participant[];
  /** Chat messages */
  chatMessages: ChatMessage[];
  /** Local media stream */
  localStream: MediaStream | null;
  /** Remote streams by peer ID */
  remoteStreams: Map<string, RemoteStream>;
  /** Media controls state */
  mediaState: MediaState;
  /** Whether meeting has ended */
  meetingEnded: boolean;
  /** Meeting start time for timer */
  meetingStartTime: Date | null;
  /** Connection status */
  connectionStatus: 'disconnected' | 'connecting' | 'connected' | 'reconnecting';
  /** Error message if any */
  error: string | null;
}

// ============================================================================
// Action Types (for potential reducer pattern)
// ============================================================================

/**
 * Meeting action types for state management
 */
export type VideoMeetingAction =
  | { type: 'SET_MEETING'; payload: Meeting }
  | { type: 'SET_PARTICIPANT'; payload: Participant }
  | { type: 'SET_PARTICIPANTS'; payload: Participant[] }
  | { type: 'ADD_PARTICIPANT'; payload: Participant }
  | { type: 'REMOVE_PARTICIPANT'; payload: number }
  | { type: 'SET_CHAT_MESSAGES'; payload: ChatMessage[] }
  | { type: 'ADD_CHAT_MESSAGE'; payload: ChatMessage }
  | { type: 'SET_LOCAL_STREAM'; payload: MediaStream | null }
  | { type: 'ADD_REMOTE_STREAM'; payload: RemoteStream }
  | { type: 'REMOVE_REMOTE_STREAM'; payload: string }
  | { type: 'SET_MEDIA_STATE'; payload: Partial<MediaState> }
  | { type: 'END_MEETING' }
  | { type: 'SET_CONNECTION_STATUS'; payload: VideoMeetingState['connectionStatus'] }
  | { type: 'SET_ERROR'; payload: string | null }
  | { type: 'RESET' };

// ============================================================================
// Component Props Types
// ============================================================================

/**
 * Props for video display component
 */
export interface VideoDisplayProps {
  /** Local media stream */
  localStream: MediaStream | null;
  /** Remote streams */
  remoteStreams: RemoteStream[];
  /** Whether local video is enabled */
  localVideoEnabled: boolean;
  /** Current participant's display name */
  localDisplayName: string;
  /** Callback when fullscreen is toggled */
  onToggleFullscreen?: () => void;
}

/**
 * Props for chat box component
 */
export interface ChatBoxProps {
  /** Chat messages */
  messages: ChatMessage[];
  /** Current participant ID */
  currentParticipantId: number;
  /** Callback to send a message */
  onSendMessage: (message: string) => void;
  /** Whether chat is loading */
  loading?: boolean;
}

/**
 * Props for participants list component
 */
export interface ParticipantsListProps {
  /** List of participants */
  participants: Participant[];
  /** Current participant ID */
  currentParticipantId: number;
  /** Remote streams for status icons */
  remoteStreams: Map<string, RemoteStream>;
}

/**
 * Props for meeting controls component
 */
export interface MeetingControlsProps {
  /** Media state */
  mediaState: MediaState;
  /** Callback to toggle audio */
  onToggleAudio: () => void;
  /** Callback to toggle video */
  onToggleVideo: () => void;
  /** Callback to toggle screen share */
  onToggleScreenShare: () => void;
  /** Callback to end call */
  onEndCall: () => void;
  /** Callback to open settings */
  onOpenSettings?: () => void;
  /** Whether screen share is supported */
  screenShareSupported?: boolean;
}

/**
 * Props for join modal component
 */
export interface JoinModalProps {
  /** Whether modal is open */
  isOpen: boolean;
  /** Meeting token */
  token: string;
  /** Meeting info */
  meeting?: Meeting;
  /** Callback when join is submitted */
  onJoin: (displayName: string) => void;
  /** Callback when cancelled */
  onCancel: () => void;
  /** Whether join is in progress */
  loading?: boolean;
  /** Error message */
  error?: string | null;
}

/**
 * Props for share link modal component
 */
export interface ShareLinkModalProps {
  /** Whether modal is open */
  isOpen: boolean;
  /** Meeting link URL */
  meetingLink: string;
  /** Callback when closed */
  onClose: () => void;
}

/**
 * Props for call timer component
 */
export interface CallTimerProps {
  /** Meeting start time */
  startTime: Date | null;
  /** Optional className */
  className?: string;
}
