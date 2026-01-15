/**
 * Video Meeting Service for SafeShift EHR
 * 
 * Handles all API calls related to video meetings including creating meetings,
 * joining, managing participants, and chat functionality.
 * 
 * @package SafeShift\Services
 * @author SafeShift Development Team
 * @copyright 2025 SafeShift EHR
 */

import { get, post } from './api.js';
import type {
  Meeting,
  Participant,
  ChatMessage,
  CreateMeetingResponse,
  ValidateTokenResponse,
  JoinMeetingResponse,
  GetParticipantsResponse,
  SendMessageResponse,
  GetChatHistoryResponse,
  GetMeetingLinkResponse,
  GetMyMeetingsResponse,
} from '../types/video-meeting.types.js';

// ============================================================================
// API Endpoints
// ============================================================================

const VIDEO_ENDPOINTS = {
  /** Create a new meeting (providers only) */
  createMeeting: '/video/create',
  /** Validate a meeting token (public) */
  validateToken: '/video/validate-token',
  /** Join a meeting with token */
  joinMeeting: '/video/join',
  /** Leave a meeting */
  leaveMeeting: '/video/leave',
  /** End a meeting (host only) */
  endMeeting: '/video/end',
  /** Get meeting participants */
  participants: (meetingId: number) => `/video/meetings/${meetingId}/participants`,
  /** Chat endpoints */
  sendMessage: '/video/chat/send',
  chatHistory: (meetingId: number) => `/video/meetings/${meetingId}/chat`,
  /** Get meeting link */
  meetingLink: (meetingId: number) => `/video/meetings/${meetingId}/link`,
  /** Get user's meetings */
  myMeetings: '/video/my-meetings',
  /** Get meeting by ID */
  meeting: (meetingId: number) => `/video/meetings/${meetingId}`,
} as const;

// ============================================================================
// Meeting Management
// ============================================================================

/**
 * Raw backend response structure for meeting creation
 */
interface RawMeetingData {
  meeting_id: number;
  meeting_url: string;
  token: string;
  expires_at: string;
  is_active: boolean;
  created_by?: number;
  created_at?: string;
}

/**
 * Build the correct frontend meeting join URL from a token
 *
 * The backend may return a URL pointing to the backend server (e.g., localhost:8000),
 * but we need the URL to point to the frontend (e.g., localhost:5173).
 * This function ensures the URL uses the current frontend origin.
 *
 * @param token - The meeting token
 * @returns The correct frontend join URL
 */
function buildFrontendMeetingUrl(token: string): string {
  return `${window.location.origin}/video/join?token=${token}`;
}

/**
 * Extract the token from a backend meeting URL and rebuild it for the frontend
 *
 * @param backendUrl - The URL returned from the backend (may point to wrong host)
 * @returns The corrected frontend URL
 */
function fixMeetingUrl(backendUrl: string): string {
  try {
    const url = new URL(backendUrl);
    const token = url.searchParams.get('token');
    if (token) {
      return buildFrontendMeetingUrl(token);
    }
  } catch {
    // If URL parsing fails, try to extract token with regex
    const tokenMatch = backendUrl.match(/[?&]token=([a-f0-9]+)/i);
    if (tokenMatch?.[1]) {
      return buildFrontendMeetingUrl(tokenMatch[1]);
    }
  }
  // Fallback: return the original URL if we can't extract the token
  return backendUrl;
}

/**
 * Create a new video meeting
 *
 * Only authenticated providers can create meetings.
 * Returns meeting info with a shareable token.
 *
 * @returns Promise resolving to created meeting info
 * @throws ApiError if unauthorized or creation fails
 *
 * @example
 * ```typescript
 * const response = await createMeeting();
 * if (response.success) {
 *   console.log('Meeting created:', response.meeting);
 *   console.log('Share this link:', response.joinUrl);
 * }
 * ```
 */
export async function createMeeting(): Promise<CreateMeetingResponse> {
  const response = await post<RawMeetingData>(VIDEO_ENDPOINTS.createMeeting, {});
  
  // Transform the snake_case backend response to camelCase frontend format
  const rawData = response.data;
  
  // Build the correct frontend URL instead of using the backend-generated URL
  // This fixes the issue where the backend URL points to localhost:8000 instead of localhost:5173
  const correctJoinUrl = buildFrontendMeetingUrl(rawData.token);
  
  return {
    success: response.success,
    message: response.message,
    meeting: {
      meetingId: rawData.meeting_id,
      createdBy: rawData.created_by ?? 0,
      createdAt: rawData.created_at ?? new Date().toISOString(),
      token: rawData.token,
      tokenExpiresAt: rawData.expires_at,
      isActive: rawData.is_active,
      endedAt: null,
    },
    joinUrl: correctJoinUrl,
  };
}

/**
 * Raw backend response structure for token validation
 */
interface RawValidateTokenData {
  valid: boolean;
  meeting_id?: number;
  can_join?: boolean;
  error?: string;
  expires_at?: string;
  meeting?: {
    meeting_id: number;
    created_by?: number;
    created_at?: string;
    token?: string;
    token_expires_at?: string;
    is_active?: boolean;
    ended_at?: string | null;
  };
}

/**
 * Validate a meeting token
 *
 * Public endpoint - no authentication required.
 * Checks if token is valid and meeting is active.
 *
 * @param token - The meeting token to validate
 * @returns Promise resolving to validation result
 *
 * @example
 * ```typescript
 * const result = await validateToken('abc123');
 * if (result.valid) {
 *   console.log('Token valid, meeting:', result.meeting);
 * } else {
 *   console.log('Invalid or expired token');
 * }
 * ```
 */
export async function validateToken(token: string): Promise<ValidateTokenResponse> {
  const response = await get<RawValidateTokenData>(VIDEO_ENDPOINTS.validateToken, {
    params: { token },
  });
  
  // The API returns { valid, meeting_id, can_join } in response.data
  // We need to transform this to match the expected ValidateTokenResponse format
  const rawData = response.data;
  
  // Build a Meeting object if validation succeeded
  let meeting: Meeting | undefined;
  if (rawData.valid && rawData.meeting_id) {
    // If the API provided full meeting details, use them
    if (rawData.meeting) {
      meeting = {
        meetingId: rawData.meeting.meeting_id,
        createdBy: rawData.meeting.created_by ?? 0,
        createdAt: rawData.meeting.created_at ?? new Date().toISOString(),
        token: rawData.meeting.token ?? token,
        tokenExpiresAt: rawData.meeting.token_expires_at ?? rawData.expires_at ?? '',
        isActive: rawData.meeting.is_active ?? true,
        endedAt: rawData.meeting.ended_at ?? null,
      };
    } else {
      // Create a minimal meeting object from available data
      meeting = {
        meetingId: rawData.meeting_id,
        createdBy: 0,
        createdAt: new Date().toISOString(),
        token: token,
        tokenExpiresAt: rawData.expires_at ?? '',
        isActive: rawData.can_join ?? true,
        endedAt: null,
      };
    }
  }
  
  return {
    success: rawData.valid === true,
    valid: rawData.valid,
    meeting,
    ...(rawData.error && { error: rawData.error }),
    ...(rawData.expires_at && { expiresAt: rawData.expires_at }),
  } as ValidateTokenResponse;
}

/**
 * Raw backend response structure for join meeting
 */
interface RawJoinMeetingData {
  participant_id: number;
  meeting_id: number;
  display_name: string;
  joined_at: string;
  session_data?: {
    participant_count: number;
    meeting_active: boolean;
  };
  error?: string;
}

/**
 * Join a meeting with a token and display name
 *
 * Public endpoint - anyone with a valid token can join.
 * Creates a participant record and returns session info.
 *
 * @param token - The meeting token
 * @param displayName - Name to display in the meeting
 * @returns Promise resolving to join result with participant info
 * @throws ApiError if token invalid or meeting ended
 *
 * @example
 * ```typescript
 * const result = await joinMeeting('abc123', 'John Doe');
 * if (result.success) {
 *   console.log('Joined as:', result.participant.displayName);
 *   console.log('Meeting ID:', result.meeting.meetingId);
 * }
 * ```
 */
export async function joinMeeting(token: string, displayName: string): Promise<JoinMeetingResponse> {
  const response = await post<RawJoinMeetingData>(VIDEO_ENDPOINTS.joinMeeting, {
    token,
    display_name: displayName,
  });
  
  // Transform the snake_case backend response to camelCase frontend format
  const rawData = response.data;
  
  // Check if backend returned an error
  if (rawData.error) {
    return {
      success: false,
      error: rawData.error,
    };
  }
  
  // Build Meeting object from the response data
  // Note: The backend only returns meeting_id, so we create a minimal meeting object
  const meeting: Meeting = {
    meetingId: rawData.meeting_id,
    createdBy: 0, // Not provided by join endpoint
    createdAt: new Date().toISOString(),
    token: token,
    tokenExpiresAt: '', // Not provided by join endpoint
    isActive: rawData.session_data?.meeting_active ?? true,
    endedAt: null,
  };
  
  // Build Participant object from the response data
  const participant: Participant = {
    participantId: rawData.participant_id,
    meetingId: rawData.meeting_id,
    displayName: rawData.display_name,
    joinedAt: rawData.joined_at,
    leftAt: null,
    peerId: null,
  };
  
  return {
    success: response.success,
    message: response.message,
    meeting,
    participant,
    // participants array not provided by join endpoint initially
  };
}

/**
 * Leave a meeting
 * 
 * Records the participant's departure from the meeting.
 * 
 * @param participantId - The participant's ID
 * @param meetingId - The meeting ID
 * @returns Promise that resolves when leave is processed
 * 
 * @example
 * ```typescript
 * await leaveMeeting(456, 123);
 * console.log('Left the meeting');
 * ```
 */
export async function leaveMeeting(participantId: number, meetingId: number): Promise<void> {
  await post<void>(VIDEO_ENDPOINTS.leaveMeeting, {
    participant_id: participantId,
    meeting_id: meetingId,
  });
}

/**
 * End a meeting (host only)
 * 
 * Terminates the meeting for all participants.
 * Only the meeting creator can end it.
 * 
 * @param meetingId - The meeting ID to end
 * @returns Promise that resolves when meeting is ended
 * @throws ApiError if unauthorized or meeting not found
 * 
 * @example
 * ```typescript
 * await endMeeting(123);
 * console.log('Meeting ended');
 * ```
 */
export async function endMeeting(meetingId: number): Promise<void> {
  await post<void>(VIDEO_ENDPOINTS.endMeeting, {
    meeting_id: meetingId,
  });
}

/**
 * Get a meeting by ID
 * 
 * @param meetingId - The meeting ID
 * @returns Promise resolving to meeting info
 * @throws ApiError if meeting not found
 */
export async function getMeeting(meetingId: number): Promise<Meeting> {
  const response = await get<{ success: boolean; meeting: Meeting }>(
    VIDEO_ENDPOINTS.meeting(meetingId)
  );
  return response.data.meeting;
}

// ============================================================================
// Participant Management
// ============================================================================

/**
 * Get all participants in a meeting
 * 
 * Returns list of current and past participants.
 * 
 * @param meetingId - The meeting ID
 * @returns Promise resolving to participant list
 * 
 * @example
 * ```typescript
 * const participants = await getParticipants(123);
 * const activeParticipants = participants.filter(p => !p.leftAt);
 * console.log(`${activeParticipants.length} people in meeting`);
 * ```
 */
export async function getParticipants(meetingId: number): Promise<Participant[]> {
  const response = await get<GetParticipantsResponse>(
    VIDEO_ENDPOINTS.participants(meetingId)
  );
  return response.data.participants ?? [];
}

/**
 * Get only active participants (currently in meeting)
 * 
 * @param meetingId - The meeting ID
 * @returns Promise resolving to active participant list
 */
export async function getActiveParticipants(meetingId: number): Promise<Participant[]> {
  const participants = await getParticipants(meetingId);
  return participants.filter((p) => !p.leftAt);
}

// ============================================================================
// Chat Management
// ============================================================================

/**
 * Send a chat message in a meeting
 * 
 * @param meetingId - The meeting ID
 * @param participantId - The sender's participant ID
 * @param message - The message text
 * @returns Promise resolving to the sent message
 * @throws ApiError if message fails to send
 * 
 * @example
 * ```typescript
 * const chatMessage = await sendChatMessage(123, 456, 'Hello everyone!');
 * console.log('Message sent at:', chatMessage.sentAt);
 * ```
 */
export async function sendChatMessage(
  meetingId: number,
  participantId: number,
  message: string
): Promise<ChatMessage> {
  const response = await post<SendMessageResponse>(VIDEO_ENDPOINTS.sendMessage, {
    meeting_id: meetingId,
    participant_id: participantId,
    message_text: message,
  });
  
  if (!response.data.chatMessage) {
    throw new Error('Failed to send message');
  }
  
  return response.data.chatMessage;
}

/**
 * Get chat history for a meeting
 * 
 * @param meetingId - The meeting ID
 * @returns Promise resolving to array of chat messages
 * 
 * @example
 * ```typescript
 * const messages = await getChatHistory(123);
 * messages.forEach(msg => {
 *   console.log(`${msg.displayName}: ${msg.messageText}`);
 * });
 * ```
 */
export async function getChatHistory(meetingId: number): Promise<ChatMessage[]> {
  const response = await get<GetChatHistoryResponse>(
    VIDEO_ENDPOINTS.chatHistory(meetingId)
  );
  return response.data.messages ?? [];
}

// ============================================================================
// Meeting Links
// ============================================================================

/**
 * Get the shareable meeting link
 *
 * @param meetingId - The meeting ID
 * @returns Promise resolving to the join URL (corrected for frontend)
 *
 * @example
 * ```typescript
 * const link = await getMeetingLink(123);
 * console.log('Share this link:', link);
 * ```
 */
export async function getMeetingLink(meetingId: number): Promise<string> {
  const response = await get<GetMeetingLinkResponse>(
    VIDEO_ENDPOINTS.meetingLink(meetingId)
  );
  const backendUrl = response.data.joinUrl ?? '';
  // Fix the URL to point to the frontend instead of the backend
  return backendUrl ? fixMeetingUrl(backendUrl) : '';
}

/**
 * Get the shareable meeting link with full details
 *
 * @param meetingId - The meeting ID
 * @returns Promise resolving to link info with expiration (URL corrected for frontend)
 */
export async function getMeetingLinkDetails(meetingId: number): Promise<GetMeetingLinkResponse> {
  const response = await get<GetMeetingLinkResponse>(
    VIDEO_ENDPOINTS.meetingLink(meetingId)
  );
  // Fix the URL in the response to point to the frontend
  const result = { ...response.data };
  if (result.joinUrl) {
    result.joinUrl = fixMeetingUrl(result.joinUrl);
  }
  return result;
}

// ============================================================================
// User's Meetings
// ============================================================================

/**
 * Get the authenticated user's meetings
 * 
 * Returns meetings created by the current user.
 * 
 * @returns Promise resolving to array of meetings
 * 
 * @example
 * ```typescript
 * const meetings = await getMyMeetings();
 * const activeMeetings = meetings.filter(m => m.isActive);
 * console.log(`You have ${activeMeetings.length} active meetings`);
 * ```
 */
export async function getMyMeetings(): Promise<Meeting[]> {
  const response = await get<GetMyMeetingsResponse>(VIDEO_ENDPOINTS.myMeetings);
  return response.data.meetings ?? [];
}

/**
 * Get only active meetings for the user
 * 
 * @returns Promise resolving to array of active meetings
 */
export async function getMyActiveMeetings(): Promise<Meeting[]> {
  const meetings = await getMyMeetings();
  return meetings.filter((m) => m.isActive);
}

// ============================================================================
// Service Object Export
// ============================================================================

/**
 * Video meeting service object containing all meeting-related methods
 */
export const videoMeetingService = {
  // Meeting management
  createMeeting,
  validateToken,
  joinMeeting,
  leaveMeeting,
  endMeeting,
  getMeeting,
  
  // Participants
  getParticipants,
  getActiveParticipants,
  
  // Chat
  sendChatMessage,
  getChatHistory,
  
  // Links
  getMeetingLink,
  getMeetingLinkDetails,
  
  // User meetings
  getMyMeetings,
  getMyActiveMeetings,
} as const;

export default videoMeetingService;
