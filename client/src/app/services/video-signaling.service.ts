/**
 * Video Signaling Service for SafeShift EHR
 * 
 * Manages PeerJS WebRTC connections and PHP-based signaling for video meetings.
 * Uses PeerJS cloud server for WebRTC signaling with PHP backend for room state management.
 * 
 * Since GoDaddy cPanel doesn't support long-running Node.js processes,
 * we use polling for real-time participant updates.
 * 
 * @package SafeShift\Services
 * @author SafeShift Development Team
 * @copyright 2025 SafeShift EHR
 */

// ============================================================================
// Types
// ============================================================================

/**
 * Information about a peer in the meeting
 */
export interface PeerInfo {
  participantId: number;
  peerId: string;
  displayName: string;
}

/**
 * Peer registration request payload
 */
interface RegisterPeerRequest {
  meeting_id: number;
  participant_id: number;
  peer_id: string;
}

/**
 * Heartbeat request payload
 */
interface HeartbeatRequest {
  meeting_id: number;
  participant_id: number;
  peer_id: string;
}

/**
 * Disconnect request payload
 */
interface DisconnectRequest {
  meeting_id: number;
  participant_id: number;
}

/**
 * API response structure
 */
interface ApiResponse<T = unknown> {
  success: boolean;
  message?: string;
  error?: string;
  data?: T;
  peers?: PeerInfo[];
  active_peers?: PeerInfo[];
  count?: number;
  meeting_id?: number;
}

/**
 * Callback for incoming calls
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type IncomingCallCallback = (call: any, remoteStream: MediaStream) => void;

/**
 * Callback for peer updates
 */
type PeersUpdatedCallback = (peers: PeerInfo[]) => void;

/**
 * Callback for connection events
 */
type ConnectionEventCallback = (peerId: string, event: 'connected' | 'disconnected' | 'error') => void;

// ============================================================================
// Configuration
// ============================================================================

/**
 * API base URL for signaling endpoints
 */
const API_BASE = '/api/video/signal';

/**
 * Heartbeat interval in milliseconds (10 seconds)
 */
const HEARTBEAT_INTERVAL = 10000;

/**
 * Polling interval for peer updates in milliseconds (3 seconds)
 */
const POLL_INTERVAL = 3000;

/**
 * PeerJS connection timeout in milliseconds
 */
const PEER_CONNECTION_TIMEOUT = 15000;

/**
 * Check if running in development mode
 */
function isDevelopment(): boolean {
  try {
    // Check Vite environment
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const meta = (import.meta as any);
    if (meta?.env?.DEV) return true;
    if (meta?.env?.MODE === 'development') return true;
  } catch {
    // Not in Vite environment
  }
  return false;
}

// ============================================================================
// Video Signaling Service
// ============================================================================

/**
 * Video Signaling Service
 * 
 * Manages PeerJS WebRTC connections and coordinates with PHP backend
 * for room state management using polling.
 */
export class VideoSignalingService {
  /** PeerJS peer instance */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private peer: any = null;
  
  /** Map of active media connections by peer ID */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private connections: Map<string, any> = new Map();
  
  /** Map of data connections for chat/signaling by peer ID */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private dataConnections: Map<string, any> = new Map();
  
  /** Heartbeat interval ID */
  private heartbeatInterval: number | null = null;
  
  /** Polling interval ID */
  private pollInterval: number | null = null;
  
  /** Current meeting ID */
  private meetingId: number | null = null;
  
  /** Current participant ID */
  private participantId: number | null = null;
  
  /** Current peer ID */
  private currentPeerId: string | null = null;
  
  /** Callback for incoming calls */
  private incomingCallCallback: IncomingCallCallback | null = null;
  
  /** Callback for connection events */
  private connectionEventCallback: ConnectionEventCallback | null = null;
  
  /** Local media stream */
  private localStream: MediaStream | null = null;
  
  /** Whether the service is initialized */
  private isInitialized = false;
  
  /** PeerJS constructor (loaded dynamically) */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private PeerClass: any = null;

  // ==========================================================================
  // PeerJS Initialization
  // ==========================================================================

  /**
   * Load PeerJS library dynamically
   */
  private async loadPeerJS(): Promise<void> {
    if (this.PeerClass) return;
    
    try {
      // Dynamic import of PeerJS
      // PeerJS must be installed: npm install peerjs
      // @ts-expect-error - peerjs module loaded dynamically, install with: npm install peerjs
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const peerModule = await import('peerjs');
      this.PeerClass = peerModule.default || peerModule.Peer || peerModule;
    } catch (error) {
      console.error('[VideoSignaling] Failed to load PeerJS:', error);
      throw new Error('PeerJS library not installed. Run: npm install peerjs');
    }
  }

  /**
   * Initialize PeerJS with cloud server
   * 
   * @returns Promise resolving to the peer ID
   * @throws Error if initialization fails
   * 
   * @example
   * ```typescript
   * const signalingService = new VideoSignalingService();
   * const peerId = await signalingService.initialize();
   * console.log('Connected with peer ID:', peerId);
   * ```
   */
  async initialize(): Promise<string> {
    // Load PeerJS first
    await this.loadPeerJS();
    
    return new Promise((resolve, reject) => {
      try {
        // Create PeerJS instance with cloud server
        // PeerJS cloud server is free for development/small scale
        this.peer = new this.PeerClass({
          debug: isDevelopment() ? 2 : 0,
          // Using default PeerJS cloud server
          // For production, consider self-hosting PeerServer
        });

        // Handle successful connection
        this.peer.on('open', (id: string) => {
          console.log('[VideoSignaling] PeerJS connected with ID:', id);
          this.currentPeerId = id;
          this.isInitialized = true;
          resolve(id);
        });

        // Handle connection errors
        this.peer.on('error', (error: Error) => {
          console.error('[VideoSignaling] PeerJS error:', error);
          if (!this.isInitialized) {
            reject(error);
          }
        });

        // Handle incoming calls
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        this.peer.on('call', (call: any) => {
          console.log('[VideoSignaling] Incoming call from:', call.peer);
          this.handleIncomingCall(call);
        });

        // Handle incoming data connections
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        this.peer.on('connection', (conn: any) => {
          console.log('[VideoSignaling] Incoming data connection from:', conn.peer);
          this.handleDataConnection(conn);
        });

        // Handle disconnection
        this.peer.on('disconnected', () => {
          console.warn('[VideoSignaling] PeerJS disconnected, attempting to reconnect...');
          this.peer?.reconnect();
        });

        // Handle closure
        this.peer.on('close', () => {
          console.log('[VideoSignaling] PeerJS connection closed');
          this.isInitialized = false;
        });

        // Timeout if connection takes too long
        setTimeout(() => {
          if (!this.isInitialized) {
            reject(new Error('PeerJS connection timeout'));
          }
        }, PEER_CONNECTION_TIMEOUT);

      } catch (error) {
        reject(error);
      }
    });
  }

  // ==========================================================================
  // Peer Registration
  // ==========================================================================

  /**
   * Register peer with backend meeting
   * 
   * @param meetingId - Meeting ID
   * @param participantId - Participant ID
   * @param peerId - PeerJS peer ID
   * @throws Error if registration fails
   * 
   * @example
   * ```typescript
   * await signalingService.registerPeer(123, 456, 'peer-abc123');
   * ```
   */
  async registerPeer(meetingId: number, participantId: number, peerId: string): Promise<void> {
    const payload: RegisterPeerRequest = {
      meeting_id: meetingId,
      participant_id: participantId,
      peer_id: peerId,
    };

    const response = await fetch(`${API_BASE}/register-peer.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(payload),
    });

    const data: ApiResponse = await response.json();

    if (!data.success) {
      throw new Error(data.error ?? 'Failed to register peer');
    }

    // Store current session info
    this.meetingId = meetingId;
    this.participantId = participantId;
    this.currentPeerId = peerId;

    console.log('[VideoSignaling] Peer registered successfully');
  }

  // ==========================================================================
  // Peer Management
  // ==========================================================================

  /**
   * Get active peers in meeting
   * 
   * @param meetingId - Meeting ID
   * @returns Array of peer information
   * 
   * @example
   * ```typescript
   * const peers = await signalingService.getPeers(123);
   * peers.forEach(peer => console.log(peer.displayName));
   * ```
   */
  async getPeers(meetingId: number): Promise<PeerInfo[]> {
    const response = await fetch(`${API_BASE}/get-peers.php?meeting_id=${meetingId}`, {
      method: 'GET',
      credentials: 'include',
    });

    const data: ApiResponse = await response.json();

    if (!data.success) {
      throw new Error(data.error ?? 'Failed to get peers');
    }

    return data.peers ?? [];
  }

  // ==========================================================================
  // Heartbeat Management
  // ==========================================================================

  /**
   * Start heartbeat to maintain connection
   * 
   * @param meetingId - Meeting ID
   * @param participantId - Participant ID
   * @param peerId - PeerJS peer ID
   * 
   * @example
   * ```typescript
   * signalingService.startHeartbeat(123, 456, 'peer-abc123');
   * ```
   */
  startHeartbeat(meetingId: number, participantId: number, peerId: string): void {
    // Clear existing heartbeat if any
    this.stopHeartbeat();

    // Send initial heartbeat
    this.sendHeartbeat(meetingId, participantId, peerId);

    // Set up interval (10 seconds)
    this.heartbeatInterval = window.setInterval(() => {
      this.sendHeartbeat(meetingId, participantId, peerId);
    }, HEARTBEAT_INTERVAL);

    console.log('[VideoSignaling] Heartbeat started');
  }

  /**
   * Stop heartbeat
   */
  stopHeartbeat(): void {
    if (this.heartbeatInterval !== null) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
      console.log('[VideoSignaling] Heartbeat stopped');
    }
  }

  /**
   * Send a single heartbeat
   */
  private async sendHeartbeat(meetingId: number, participantId: number, peerId: string): Promise<void> {
    try {
      const payload: HeartbeatRequest = {
        meeting_id: meetingId,
        participant_id: participantId,
        peer_id: peerId,
      };

      const response = await fetch(`${API_BASE}/heartbeat.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(payload),
      });

      const data: ApiResponse = await response.json();

      if (!data.success) {
        console.warn('[VideoSignaling] Heartbeat failed:', data.error);
      }
    } catch (error) {
      console.error('[VideoSignaling] Heartbeat error:', error);
    }
  }

  // ==========================================================================
  // Polling for Peer Updates
  // ==========================================================================

  /**
   * Start polling for peer updates
   * 
   * @param meetingId - Meeting ID
   * @param onPeersUpdated - Callback when peers list changes
   * 
   * @example
   * ```typescript
   * signalingService.startPolling(123, (peers) => {
   *   console.log('Active peers:', peers);
   * });
   * ```
   */
  startPolling(meetingId: number, onPeersUpdated: PeersUpdatedCallback): void {
    // Clear existing polling if any
    this.stopPolling();

    // Initial fetch
    this.fetchAndUpdatePeers(meetingId, onPeersUpdated);

    // Set up interval (3 seconds)
    this.pollInterval = window.setInterval(() => {
      this.fetchAndUpdatePeers(meetingId, onPeersUpdated);
    }, POLL_INTERVAL);

    console.log('[VideoSignaling] Polling started');
  }

  /**
   * Stop polling for peer updates
   */
  stopPolling(): void {
    if (this.pollInterval !== null) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
      console.log('[VideoSignaling] Polling stopped');
    }
  }

  /**
   * Fetch peers and call update callback
   */
  private async fetchAndUpdatePeers(meetingId: number, callback: PeersUpdatedCallback): Promise<void> {
    try {
      const peers = await this.getPeers(meetingId);
      callback(peers);
    } catch (error) {
      console.error('[VideoSignaling] Poll error:', error);
    }
  }

  // ==========================================================================
  // WebRTC Connection Management
  // ==========================================================================

  /**
   * Connect to another peer and establish media connection
   * 
   * @param peerId - Remote peer ID
   * @param localStream - Local media stream to send
   * @returns Promise resolving to the remote media stream
   * 
   * @example
   * ```typescript
   * const remoteStream = await signalingService.connectToPeer('remote-peer-id', localStream);
   * videoElement.srcObject = remoteStream;
   * ```
   */
  async connectToPeer(peerId: string, localStream: MediaStream): Promise<MediaStream> {
    return new Promise((resolve, reject) => {
      if (!this.peer) {
        reject(new Error('PeerJS not initialized'));
        return;
      }

      // Check if already connected
      if (this.connections.has(peerId)) {
        console.log('[VideoSignaling] Already connected to peer:', peerId);
        // Return existing stream if available
        const existingConn = this.connections.get(peerId);
        if (existingConn?.remoteStream) {
          resolve(existingConn.remoteStream);
          return;
        }
      }

      console.log('[VideoSignaling] Initiating call to peer:', peerId);

      // Initiate call with local stream
      const call = this.peer.call(peerId, localStream);

      // Handle remote stream
      call.on('stream', (remoteStream: MediaStream) => {
        console.log('[VideoSignaling] Received remote stream from:', peerId);
        this.connections.set(peerId, call);
        this.connectionEventCallback?.(peerId, 'connected');
        resolve(remoteStream);
      });

      // Handle call errors
      call.on('error', (error: Error) => {
        console.error('[VideoSignaling] Call error with peer:', peerId, error);
        this.connectionEventCallback?.(peerId, 'error');
        reject(error);
      });

      // Handle call close
      call.on('close', () => {
        console.log('[VideoSignaling] Call closed with peer:', peerId);
        this.connections.delete(peerId);
        this.connectionEventCallback?.(peerId, 'disconnected');
      });

      // Timeout for connection
      setTimeout(() => {
        if (!this.connections.has(peerId)) {
          reject(new Error(`Connection timeout with peer: ${peerId}`));
        }
      }, PEER_CONNECTION_TIMEOUT);
    });
  }

  /**
   * Set callback for incoming calls
   * 
   * @param callback - Function to call when receiving a call
   * 
   * @example
   * ```typescript
   * signalingService.onIncomingCall((call, remoteStream) => {
   *   console.log('Incoming call from:', call.peer);
   *   videoElement.srcObject = remoteStream;
   * });
   * ```
   */
  onIncomingCall(callback: IncomingCallCallback): void {
    this.incomingCallCallback = callback;
  }

  /**
   * Set callback for connection events
   * 
   * @param callback - Function to call on connection state changes
   */
  onConnectionEvent(callback: ConnectionEventCallback): void {
    this.connectionEventCallback = callback;
  }

  /**
   * Handle incoming call
   */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private handleIncomingCall(call: any): void {
    // Answer the call with local stream if available
    if (this.localStream) {
      call.answer(this.localStream);
    } else {
      call.answer();
    }

    // Handle remote stream
    call.on('stream', (remoteStream: MediaStream) => {
      console.log('[VideoSignaling] Received remote stream from incoming call:', call.peer);
      this.connections.set(call.peer, call);
      this.connectionEventCallback?.(call.peer, 'connected');
      
      // Trigger callback
      this.incomingCallCallback?.(call, remoteStream);
    });

    // Handle errors
    call.on('error', (error: Error) => {
      console.error('[VideoSignaling] Incoming call error:', error);
      this.connectionEventCallback?.(call.peer, 'error');
    });

    // Handle close
    call.on('close', () => {
      console.log('[VideoSignaling] Incoming call closed:', call.peer);
      this.connections.delete(call.peer);
      this.connectionEventCallback?.(call.peer, 'disconnected');
    });
  }

  /**
   * Handle incoming data connection
   */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  private handleDataConnection(conn: any): void {
    conn.on('open', () => {
      console.log('[VideoSignaling] Data connection opened with:', conn.peer);
      this.dataConnections.set(conn.peer, conn);
    });

    conn.on('close', () => {
      console.log('[VideoSignaling] Data connection closed with:', conn.peer);
      this.dataConnections.delete(conn.peer);
    });

    conn.on('error', (error: Error) => {
      console.error('[VideoSignaling] Data connection error:', error);
    });
  }

  // ==========================================================================
  // Local Stream Management
  // ==========================================================================

  /**
   * Set local media stream for answering calls
   * 
   * @param stream - Local media stream
   */
  setLocalStream(stream: MediaStream): void {
    this.localStream = stream;
  }

  /**
   * Get current local stream
   * 
   * @returns Local media stream or null
   */
  getLocalStream(): MediaStream | null {
    return this.localStream;
  }

  // ==========================================================================
  // Disconnect and Cleanup
  // ==========================================================================

  /**
   * Disconnect from meeting and cleanup all connections
   * 
   * @param meetingId - Meeting ID
   * @param participantId - Participant ID
   * 
   * @example
   * ```typescript
   * await signalingService.disconnect(123, 456);
   * ```
   */
  async disconnect(meetingId: number, participantId: number): Promise<void> {
    console.log('[VideoSignaling] Disconnecting...');

    // Stop heartbeat and polling
    this.stopHeartbeat();
    this.stopPolling();

    // Notify backend
    try {
      const payload: DisconnectRequest = {
        meeting_id: meetingId,
        participant_id: participantId,
      };

      await fetch(`${API_BASE}/disconnect.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(payload),
      });
    } catch (error) {
      console.error('[VideoSignaling] Disconnect notification error:', error);
    }

    // Close all media connections
    this.connections.forEach((call, peerId) => {
      console.log('[VideoSignaling] Closing connection to:', peerId);
      call.close();
    });
    this.connections.clear();

    // Close all data connections
    this.dataConnections.forEach((conn, peerId) => {
      console.log('[VideoSignaling] Closing data connection to:', peerId);
      conn.close();
    });
    this.dataConnections.clear();

    // Destroy PeerJS instance
    if (this.peer) {
      this.peer.destroy();
      this.peer = null;
    }

    // Clear state
    this.meetingId = null;
    this.participantId = null;
    this.currentPeerId = null;
    this.localStream = null;
    this.isInitialized = false;

    console.log('[VideoSignaling] Disconnected');
  }

  // ==========================================================================
  // Utility Methods
  // ==========================================================================

  /**
   * Get current peer ID
   */
  getPeerId(): string | null {
    return this.currentPeerId;
  }

  /**
   * Get current meeting ID
   */
  getMeetingId(): number | null {
    return this.meetingId;
  }

  /**
   * Get current participant ID
   */
  getParticipantId(): number | null {
    return this.participantId;
  }

  /**
   * Check if service is initialized
   */
  isConnected(): boolean {
    return this.isInitialized && this.peer !== null;
  }

  /**
   * Get number of active connections
   */
  getConnectionCount(): number {
    return this.connections.size;
  }

  /**
   * Get all connected peer IDs
   */
  getConnectedPeerIds(): string[] {
    return Array.from(this.connections.keys());
  }
}

// ============================================================================
// Singleton Instance
// ============================================================================

/**
 * Singleton instance of the video signaling service
 */
export const videoSignalingService = new VideoSignalingService();

export default videoSignalingService;
