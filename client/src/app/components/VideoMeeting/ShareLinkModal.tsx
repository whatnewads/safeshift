/**
 * Share Link Modal Component for SafeShift EHR
 * 
 * Modal for displaying and copying the meeting join link.
 * Includes copy to clipboard functionality with confirmation.
 * 
 * @package SafeShift\Components\VideoMeeting
 */

import { useState, useCallback } from 'react';
import { Share2, Copy, Check, Link, X, Video } from 'lucide-react';
import { Button } from '../ui/button.js';
import { Input } from '../ui/input.js';

/**
 * Extended props for ShareLinkModal with optional join meeting callback
 */
interface ShareLinkModalExtendedProps {
  /** Whether modal is open */
  isOpen: boolean;
  /** Meeting link URL */
  meetingLink: string;
  /** Callback when closed */
  onClose: () => void;
  /** Optional callback to join the meeting directly */
  onJoinMeeting?: () => void;
}

// ============================================================================
// Share Link Modal Component
// ============================================================================

/**
 * Share Link Modal Component
 * 
 * Displays the meeting URL with copy to clipboard functionality.
 * 
 * @example
 * ```tsx
 * <ShareLinkModal
 *   isOpen={showShareModal}
 *   meetingLink="https://example.com/video/join?token=abc123"
 *   onClose={() => setShowShareModal(false)}
 * />
 * ```
 */
export function ShareLinkModal({
  isOpen,
  meetingLink,
  onClose,
  onJoinMeeting,
}: ShareLinkModalExtendedProps) {
  const [copied, setCopied] = useState(false);

  /**
   * Copy link to clipboard
   */
  const handleCopy = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(meetingLink);
      setCopied(true);
      
      // Reset copied state after 2 seconds
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error('[ShareLinkModal] Failed to copy:', err);
      // Fallback for older browsers
      const textarea = document.createElement('textarea');
      textarea.value = meetingLink;
      textarea.style.position = 'fixed';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.select();
      try {
        document.execCommand('copy');
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
      } catch (e) {
        console.error('[ShareLinkModal] Fallback copy failed:', e);
      }
      document.body.removeChild(textarea);
    }
  }, [meetingLink]);

  /**
   * Share via Web Share API if available
   */
  const handleShare = useCallback(async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: 'Join Video Meeting',
          text: 'You are invited to join a video meeting.',
          url: meetingLink,
        });
      } catch (err) {
        // User cancelled or share failed
        console.log('[ShareLinkModal] Share cancelled or failed:', err);
      }
    } else {
      // Fallback to copy
      handleCopy();
    }
  }, [meetingLink, handleCopy]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="relative w-full max-w-lg mx-4 bg-white dark:bg-slate-800 rounded-xl shadow-2xl">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
              <Share2 className="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
              Share Meeting Link
            </h2>
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={onClose}
            className="h-8 w-8 p-0"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-4">
          {/* Instructions */}
          <p className="text-sm text-slate-600 dark:text-slate-400">
            Share this link with participants to invite them to your meeting.
            Anyone with the link can join.
          </p>

          {/* Link display */}
          <div className="space-y-2">
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
              Meeting Link
            </label>
            <div className="flex gap-2">
              <div className="relative flex-1">
                <Link className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                <Input
                  type="text"
                  value={meetingLink}
                  readOnly
                  className="pl-9 pr-3 bg-slate-50 dark:bg-slate-700/50 font-mono text-sm"
                  onClick={(e) => (e.target as HTMLInputElement).select()}
                />
              </div>
              <Button
                onClick={handleCopy}
                variant={copied ? 'default' : 'outline'}
                className={`min-w-[100px] ${
                  copied
                    ? 'bg-green-600 hover:bg-green-700 text-white'
                    : ''
                }`}
              >
                {copied ? (
                  <>
                    <Check className="h-4 w-4 mr-2" />
                    Copied!
                  </>
                ) : (
                  <>
                    <Copy className="h-4 w-4 mr-2" />
                    Copy
                  </>
                )}
              </Button>
            </div>
          </div>

          {/* Share button (if Web Share API available) */}
          {'share' in navigator && (
            <Button
              onClick={handleShare}
              variant="outline"
              className="w-full"
            >
              <Share2 className="h-4 w-4 mr-2" />
              Share via...
            </Button>
          )}

          {/* Tips */}
          <div className="p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg">
            <p className="text-xs text-slate-500 dark:text-slate-400">
              <strong>Tip:</strong> You can also share this link via email, messaging apps, or any other method.
              The link will expire when the meeting ends or after 24 hours.
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
          <Button onClick={onClose} variant="outline">
            Done
          </Button>
          {onJoinMeeting && (
            <Button onClick={onJoinMeeting} className="gap-2">
              <Video className="h-4 w-4" />
              Join Meeting
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}

export default ShareLinkModal;
