/**
 * WebRTC Service for SafeShift EHR
 * 
 * Manages WebRTC media streams (camera/microphone) and connection quality.
 * Works alongside VideoSignalingService to provide complete video meeting functionality.
 * 
 * @package SafeShift\Services
 * @author SafeShift Development Team
 * @copyright 2025 SafeShift EHR
 */

// ============================================================================
// Types
// ============================================================================

/**
 * Connection quality statistics
 */
export interface ConnectionStats {
  /** Current bitrate in kbps */
  bitrate: number;
  /** Packet loss percentage (0-100) */
  packetLoss: number;
  /** Round-trip latency in milliseconds */
  latency: number;
  /** Timestamp of the stats */
  timestamp: number;
}

/**
 * Media device information
 */
export interface MediaDeviceInfo {
  deviceId: string;
  label: string;
  kind: 'videoinput' | 'audioinput' | 'audiooutput';
}

/**
 * Media constraints for getUserMedia
 */
export interface MediaConstraints {
  video: boolean | MediaTrackConstraints;
  audio: boolean | MediaTrackConstraints;
}

/**
 * Stream quality presets
 */
export type QualityPreset = 'low' | 'medium' | 'high' | 'hd';

/**
 * Quality preset configurations
 */
const QUALITY_PRESETS: Record<QualityPreset, MediaTrackConstraints> = {
  low: {
    width: { ideal: 320 },
    height: { ideal: 240 },
    frameRate: { ideal: 15 },
  },
  medium: {
    width: { ideal: 640 },
    height: { ideal: 480 },
    frameRate: { ideal: 24 },
  },
  high: {
    width: { ideal: 1280 },
    height: { ideal: 720 },
    frameRate: { ideal: 30 },
  },
  hd: {
    width: { ideal: 1920 },
    height: { ideal: 1080 },
    frameRate: { ideal: 30 },
  },
};

// ============================================================================
// WebRTC Service
// ============================================================================

/**
 * WebRTC Service
 * 
 * Manages local and remote media streams for video meetings.
 */
export class WebRTCService {
  /** Local media stream */
  private localStream: MediaStream | null = null;
  
  /** Map of remote streams by peer ID */
  private remoteStreams: Map<string, MediaStream> = new Map();
  
  /** Current video enabled state */
  private videoEnabled = true;
  
  /** Current audio enabled state */
  private audioEnabled = true;
  
  /** Current quality preset */
  private currentQuality: QualityPreset = 'medium';
  
  /** Selected video device ID */
  private selectedVideoDevice: string | null = null;
  
  /** Selected audio device ID */
  private selectedAudioDevice: string | null = null;

  // ==========================================================================
  // Local Stream Management
  // ==========================================================================

  /**
   * Get user media (camera/microphone)
   * 
   * @param video - Enable video (true/false or constraints)
   * @param audio - Enable audio (true/false or constraints)
   * @returns Promise resolving to the local media stream
   * @throws Error if media access is denied
   * 
   * @example
   * ```typescript
   * const webrtcService = new WebRTCService();
   * const stream = await webrtcService.getLocalStream(true, true);
   * videoElement.srcObject = stream;
   * ```
   */
  async getLocalStream(video = true, audio = true): Promise<MediaStream> {
    // If we already have a stream with the same settings, return it
    if (this.localStream && this.hasRequiredTracks(video, audio)) {
      return this.localStream;
    }

    // Stop existing stream if any
    this.stopLocalStream();

    try {
      // Build constraints
      const constraints = this.buildConstraints(video, audio);
      
      console.log('[WebRTC] Requesting media with constraints:', constraints);
      
      // Request media access
      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      
      this.localStream = stream;
      this.videoEnabled = video !== false;
      this.audioEnabled = audio !== false;
      
      console.log('[WebRTC] Local stream obtained:', {
        videoTracks: stream.getVideoTracks().length,
        audioTracks: stream.getAudioTracks().length,
      });
      
      return stream;
      
    } catch (error) {
      console.error('[WebRTC] Failed to get local stream:', error);
      throw this.handleMediaError(error);
    }
  }

  /**
   * Get local stream with quality preset
   *
   * @param quality - Quality preset (low, medium, high, hd)
   * @param audio - Enable audio
   * @returns Promise resolving to the local media stream
   */
  async getLocalStreamWithQuality(quality: QualityPreset, audio = true): Promise<MediaStream> {
    this.currentQuality = quality;
    // Use true for video - constraints will be applied in buildConstraints
    return this.getLocalStream(true, audio);
  }

  /**
   * Stop the local media stream
   */
  stopLocalStream(): void {
    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => {
        track.stop();
        console.log('[WebRTC] Stopped track:', track.kind);
      });
      this.localStream = null;
    }
  }

  /**
   * Check if local stream has required tracks
   */
  private hasRequiredTracks(video: boolean | MediaTrackConstraints, audio: boolean | MediaTrackConstraints): boolean {
    if (!this.localStream) return false;
    
    const hasVideo = video === false || this.localStream.getVideoTracks().length > 0;
    const hasAudio = audio === false || this.localStream.getAudioTracks().length > 0;
    
    return hasVideo && hasAudio;
  }

  /**
   * Build media constraints from options
   */
  private buildConstraints(video: boolean | MediaTrackConstraints, audio: boolean | MediaTrackConstraints): MediaStreamConstraints {
    const constraints: MediaStreamConstraints = {};
    
    // Video constraints
    if (video === true) {
      constraints.video = this.selectedVideoDevice 
        ? { deviceId: { exact: this.selectedVideoDevice }, ...QUALITY_PRESETS[this.currentQuality] }
        : QUALITY_PRESETS[this.currentQuality];
    } else if (video && typeof video === 'object') {
      constraints.video = this.selectedVideoDevice
        ? { deviceId: { exact: this.selectedVideoDevice }, ...video }
        : video;
    } else {
      constraints.video = false;
    }
    
    // Audio constraints
    if (audio === true) {
      constraints.audio = this.selectedAudioDevice
        ? { deviceId: { exact: this.selectedAudioDevice }, echoCancellation: true, noiseSuppression: true }
        : { echoCancellation: true, noiseSuppression: true };
    } else if (audio && typeof audio === 'object') {
      constraints.audio = this.selectedAudioDevice
        ? { deviceId: { exact: this.selectedAudioDevice }, ...audio }
        : audio;
    } else {
      constraints.audio = false;
    }
    
    return constraints;
  }

  /**
   * Handle media errors with user-friendly messages
   */
  private handleMediaError(error: unknown): Error {
    if (error instanceof DOMException) {
      switch (error.name) {
        case 'NotAllowedError':
          return new Error('Camera/microphone access was denied. Please allow access in your browser settings.');
        case 'NotFoundError':
          return new Error('No camera or microphone found. Please connect a device and try again.');
        case 'NotReadableError':
          return new Error('Camera or microphone is already in use by another application.');
        case 'OverconstrainedError':
          return new Error('The requested camera settings are not supported by your device.');
        default:
          return new Error(`Media error: ${error.message}`);
      }
    }
    return error instanceof Error ? error : new Error('Unknown media error occurred');
  }

  // ==========================================================================
  // Remote Stream Management
  // ==========================================================================

  /**
   * Add a remote stream
   * 
   * @param peerId - Remote peer ID
   * @param stream - Remote media stream
   */
  addRemoteStream(peerId: string, stream: MediaStream): void {
    console.log('[WebRTC] Adding remote stream for peer:', peerId);
    this.remoteStreams.set(peerId, stream);
  }

  /**
   * Remove a remote stream
   * 
   * @param peerId - Remote peer ID
   */
  removeRemoteStream(peerId: string): void {
    const stream = this.remoteStreams.get(peerId);
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
      this.remoteStreams.delete(peerId);
      console.log('[WebRTC] Removed remote stream for peer:', peerId);
    }
  }

  /**
   * Get all remote streams
   * 
   * @returns Map of peer ID to media stream
   */
  getRemoteStreams(): Map<string, MediaStream> {
    return new Map(this.remoteStreams);
  }

  /**
   * Get a specific remote stream
   * 
   * @param peerId - Remote peer ID
   * @returns The media stream or undefined
   */
  getRemoteStream(peerId: string): MediaStream | undefined {
    return this.remoteStreams.get(peerId);
  }

  /**
   * Clear all remote streams
   */
  clearRemoteStreams(): void {
    this.remoteStreams.forEach((stream) => {
      stream.getTracks().forEach((track) => track.stop());
    });
    this.remoteStreams.clear();
    console.log('[WebRTC] Cleared all remote streams');
  }

  // ==========================================================================
  // Video/Audio Controls
  // ==========================================================================

  /**
   * Toggle video on/off
   * 
   * @param enabled - Enable or disable video
   */
  toggleVideo(enabled: boolean): void {
    if (this.localStream) {
      this.localStream.getVideoTracks().forEach((track) => {
        track.enabled = enabled;
      });
      this.videoEnabled = enabled;
      console.log('[WebRTC] Video toggled:', enabled);
    }
  }

  /**
   * Toggle audio on/off
   * 
   * @param enabled - Enable or disable audio
   */
  toggleAudio(enabled: boolean): void {
    if (this.localStream) {
      this.localStream.getAudioTracks().forEach((track) => {
        track.enabled = enabled;
      });
      this.audioEnabled = enabled;
      console.log('[WebRTC] Audio toggled:', enabled);
    }
  }

  /**
   * Check if video is enabled
   * 
   * @returns True if video is enabled
   */
  isVideoEnabled(): boolean {
    return this.videoEnabled;
  }

  /**
   * Check if audio is enabled
   * 
   * @returns True if audio is enabled
   */
  isAudioEnabled(): boolean {
    return this.audioEnabled;
  }

  // ==========================================================================
  // Device Management
  // ==========================================================================

  /**
   * Get available media devices
   * 
   * @returns Promise resolving to array of device info
   */
  async getMediaDevices(): Promise<MediaDeviceInfo[]> {
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      return devices
        .filter((device) => ['videoinput', 'audioinput', 'audiooutput'].includes(device.kind))
        .map((device) => ({
          deviceId: device.deviceId,
          label: device.label || `${device.kind} (${device.deviceId.slice(0, 8)})`,
          kind: device.kind as 'videoinput' | 'audioinput' | 'audiooutput',
        }));
    } catch (error) {
      console.error('[WebRTC] Failed to enumerate devices:', error);
      return [];
    }
  }

  /**
   * Get video input devices (cameras)
   * 
   * @returns Promise resolving to array of camera info
   */
  async getVideoDevices(): Promise<MediaDeviceInfo[]> {
    const devices = await this.getMediaDevices();
    return devices.filter((d) => d.kind === 'videoinput');
  }

  /**
   * Get audio input devices (microphones)
   * 
   * @returns Promise resolving to array of microphone info
   */
  async getAudioInputDevices(): Promise<MediaDeviceInfo[]> {
    const devices = await this.getMediaDevices();
    return devices.filter((d) => d.kind === 'audioinput');
  }

  /**
   * Get audio output devices (speakers)
   * 
   * @returns Promise resolving to array of speaker info
   */
  async getAudioOutputDevices(): Promise<MediaDeviceInfo[]> {
    const devices = await this.getMediaDevices();
    return devices.filter((d) => d.kind === 'audiooutput');
  }

  /**
   * Select video device
   * 
   * @param deviceId - Device ID to select
   */
  async selectVideoDevice(deviceId: string): Promise<void> {
    this.selectedVideoDevice = deviceId;
    
    // If we have a stream, restart with new device
    if (this.localStream) {
      await this.getLocalStream(this.videoEnabled, this.audioEnabled);
    }
  }

  /**
   * Select audio device
   * 
   * @param deviceId - Device ID to select
   */
  async selectAudioDevice(deviceId: string): Promise<void> {
    this.selectedAudioDevice = deviceId;
    
    // If we have a stream, restart with new device
    if (this.localStream) {
      await this.getLocalStream(this.videoEnabled, this.audioEnabled);
    }
  }

  // ==========================================================================
  // Connection Quality
  // ==========================================================================

  /**
   * Get connection quality metrics
   * 
   * Note: This requires access to RTCPeerConnection stats, which may need
   * to be passed from the signaling service for each connection.
   * 
   * @returns Promise resolving to connection statistics
   */
  async getConnectionStats(): Promise<ConnectionStats> {
    // Default/placeholder stats
    // In a real implementation, you would get these from RTCPeerConnection.getStats()
    const stats: ConnectionStats = {
      bitrate: 0,
      packetLoss: 0,
      latency: 0,
      timestamp: Date.now(),
    };

    // If we have access to RTCPeerConnection, we could get real stats
    // This would typically be done through the signaling service
    
    return stats;
  }

  /**
   * Calculate connection quality from stats
   * 
   * @param stats - Connection statistics
   * @returns Quality rating (1-5)
   */
  calculateQualityRating(stats: ConnectionStats): number {
    // Simple quality calculation based on packet loss and latency
    let rating = 5;
    
    // Reduce rating based on packet loss
    if (stats.packetLoss > 0.1) rating -= 1;
    if (stats.packetLoss > 1) rating -= 1;
    if (stats.packetLoss > 5) rating -= 1;
    
    // Reduce rating based on latency
    if (stats.latency > 100) rating -= 0.5;
    if (stats.latency > 300) rating -= 0.5;
    if (stats.latency > 500) rating -= 1;
    
    return Math.max(1, Math.min(5, rating));
  }

  // ==========================================================================
  // Quality Management
  // ==========================================================================

  /**
   * Set video quality preset
   * 
   * @param quality - Quality preset
   */
  async setQuality(quality: QualityPreset): Promise<void> {
    this.currentQuality = quality;
    
    // If we have a stream, restart with new quality
    if (this.localStream && this.videoEnabled) {
      await this.getLocalStreamWithQuality(quality, this.audioEnabled);
    }
  }

  /**
   * Get current quality preset
   * 
   * @returns Current quality preset
   */
  getQuality(): QualityPreset {
    return this.currentQuality;
  }

  // ==========================================================================
  // Screen Sharing
  // ==========================================================================

  /**
   * Start screen sharing
   *
   * @returns Promise resolving to the screen share stream
   * @throws Error if screen sharing is denied or not supported
   */
  async startScreenShare(): Promise<MediaStream> {
    try {
      // Use any to handle cursor constraint which is valid but not in TypeScript types
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const constraints: any = {
        video: {
          cursor: 'always',
        },
        audio: false,
      };
      
      const stream = await navigator.mediaDevices.getDisplayMedia(constraints);
      
      console.log('[WebRTC] Screen share started');
      return stream;
      
    } catch (error) {
      console.error('[WebRTC] Screen share failed:', error);
      throw new Error('Failed to start screen sharing. Please allow screen access.');
    }
  }

  // ==========================================================================
  // Cleanup
  // ==========================================================================

  /**
   * Clean up all resources
   */
  cleanup(): void {
    this.stopLocalStream();
    this.clearRemoteStreams();
    this.selectedVideoDevice = null;
    this.selectedAudioDevice = null;
    console.log('[WebRTC] Cleanup complete');
  }

  // ==========================================================================
  // Utility Methods
  // ==========================================================================

  /**
   * Check if WebRTC is supported in this browser
   *
   * @returns True if WebRTC is supported
   */
  static isSupported(): boolean {
    return !!(
      typeof navigator !== 'undefined' &&
      navigator.mediaDevices &&
      typeof navigator.mediaDevices.getUserMedia === 'function' &&
      typeof window !== 'undefined' &&
      window.RTCPeerConnection
    );
  }

  /**
   * Check if screen sharing is supported
   * 
   * @returns True if screen sharing is supported
   */
  static isScreenShareSupported(): boolean {
    return !!(navigator.mediaDevices && 'getDisplayMedia' in navigator.mediaDevices);
  }

  /**
   * Get local stream reference (without starting a new one)
   * 
   * @returns The current local stream or null
   */
  getCurrentLocalStream(): MediaStream | null {
    return this.localStream;
  }
}

// ============================================================================
// Singleton Instance
// ============================================================================

/**
 * Singleton instance of the WebRTC service
 */
export const webrtcService = new WebRTCService();

export default webrtcService;
