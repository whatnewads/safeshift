/**
 * Meeting Controls Component for SafeShift EHR
 * 
 * Bottom control bar for video meeting with media controls,
 * screen sharing, and end call functionality.
 * 
 * @package SafeShift\Components\VideoMeeting
 */

import { useState } from 'react';
import {
  Mic,
  MicOff,
  Video,
  VideoOff,
  Monitor,
  MonitorOff,
  PhoneOff,
  Settings,
} from 'lucide-react';
import {
  SimpleDropdown,
  SimpleDropdownItem,
  SimpleDropdownLabel,
  SimpleDropdownSeparator,
} from '../ui/simple-dropdown.js';
import type { MeetingControlsProps } from '../../types/video-meeting.types.js';

// ============================================================================
// Control Button Component
// ============================================================================

interface ControlButtonProps {
  icon: React.ReactNode;
  activeIcon?: React.ReactNode;
  label: string;
  isActive?: boolean;
  isDestructive?: boolean;
  onClick: () => void;
  disabled?: boolean;
}

/**
 * Individual control button with tooltip
 */
function ControlButton({
  icon,
  activeIcon,
  label,
  isActive = false,
  isDestructive = false,
  onClick,
  disabled = false,
}: ControlButtonProps) {
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      title={label}
      className={`
        flex flex-col items-center gap-1 p-3 rounded-lg transition-all
        ${disabled ? 'opacity-50 cursor-not-allowed' : 'hover:scale-105'}
        ${isDestructive 
          ? 'bg-red-600 hover:bg-red-700 text-white' 
          : isActive
            ? 'bg-slate-700 text-white'
            : 'bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-white'
        }
      `}
    >
      <div className="h-6 w-6 flex items-center justify-center">
        {isActive && activeIcon ? activeIcon : icon}
      </div>
      <span className="text-xs font-medium hidden sm:block">{label}</span>
    </button>
  );
}

// ============================================================================
// Meeting Controls Component
// ============================================================================

/**
 * Meeting Controls Component
 * 
 * Bottom bar with audio, video, screen share, and end call controls.
 * 
 * @example
 * ```tsx
 * <MeetingControls
 *   mediaState={mediaState}
 *   onToggleAudio={toggleAudio}
 *   onToggleVideo={toggleVideo}
 *   onToggleScreenShare={toggleScreenShare}
 *   onEndCall={endCall}
 * />
 * ```
 */
export function MeetingControls({
  mediaState,
  onToggleAudio,
  onToggleVideo,
  onToggleScreenShare,
  onEndCall,
  onOpenSettings,
  screenShareSupported = true,
}: MeetingControlsProps) {
  const [showEndConfirm, setShowEndConfirm] = useState(false);

  const handleEndCall = () => {
    if (showEndConfirm) {
      onEndCall();
      setShowEndConfirm(false);
    } else {
      setShowEndConfirm(true);
      // Auto-hide confirmation after 3 seconds
      setTimeout(() => setShowEndConfirm(false), 3000);
    }
  };

  return (
    <div className="bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 px-4 py-3">
      <div className="flex items-center justify-center gap-2 sm:gap-4">
        {/* Audio control */}
        <ControlButton
          icon={<Mic className="h-5 w-5" />}
          activeIcon={<MicOff className="h-5 w-5" />}
          label={mediaState.audioEnabled ? 'Mute' : 'Unmute'}
          isActive={!mediaState.audioEnabled}
          onClick={onToggleAudio}
        />

        {/* Video control */}
        <ControlButton
          icon={<Video className="h-5 w-5" />}
          activeIcon={<VideoOff className="h-5 w-5" />}
          label={mediaState.videoEnabled ? 'Stop Video' : 'Start Video'}
          isActive={!mediaState.videoEnabled}
          onClick={onToggleVideo}
        />

        {/* Screen share control */}
        {screenShareSupported && (
          <ControlButton
            icon={<Monitor className="h-5 w-5" />}
            activeIcon={<MonitorOff className="h-5 w-5" />}
            label={mediaState.screenShareEnabled ? 'Stop Sharing' : 'Share Screen'}
            isActive={mediaState.screenShareEnabled}
            onClick={onToggleScreenShare}
          />
        )}

        {/* Settings dropdown */}
        {onOpenSettings && (
          <SimpleDropdown
            side="top"
            align="center"
            trigger={
              <button
                className="flex flex-col items-center gap-1 p-3 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-white transition-all hover:scale-105"
              >
                <div className="h-6 w-6 flex items-center justify-center">
                  <Settings className="h-5 w-5" />
                </div>
                <span className="text-xs font-medium hidden sm:block">Settings</span>
              </button>
            }
            className="w-48"
          >
            <SimpleDropdownLabel>Audio & Video</SimpleDropdownLabel>
            <SimpleDropdownSeparator />
            <SimpleDropdownItem onClick={onOpenSettings}>
              <Settings className="h-4 w-4 mr-2" />
              Device Settings
            </SimpleDropdownItem>
          </SimpleDropdown>
        )}

        {/* Spacer */}
        <div className="w-4 sm:w-8" />

        {/* End call button */}
        <ControlButton
          icon={<PhoneOff className="h-5 w-5" />}
          label={showEndConfirm ? 'Click to Confirm' : 'End Call'}
          isDestructive={true}
          onClick={handleEndCall}
        />
      </div>

      {/* End call confirmation toast */}
      {showEndConfirm && (
        <div className="absolute bottom-20 left-1/2 -translate-x-1/2 bg-red-600 text-white px-4 py-2 rounded-lg shadow-lg text-sm animate-bounce">
          Click again to leave the meeting
        </div>
      )}
    </div>
  );
}

export default MeetingControls;
