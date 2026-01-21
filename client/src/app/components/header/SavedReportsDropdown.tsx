/**
 * SavedReportsDropdown Component
 *
 * A dropdown component that combines WiFi connection status with
 * saved draft reports functionality. Shows connection status indicator,
 * draft count badge, and a list of saved drafts on click.
 *
 * @package SafeShift\Components\Header
 */

import { useMemo, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Wifi, WifiOff, FileText, Clock, Loader2, RefreshCw } from 'lucide-react';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import {
  SimpleDropdown,
  SimpleDropdownItem,
  SimpleDropdownLabel,
  SimpleDropdownSeparator,
} from '../ui/simple-dropdown';
import { ScrollArea } from '../ui/scroll-area';
import { useSavedDrafts } from '../../hooks/useSavedDrafts.js';
import type { SavedDraft } from '../../types/api.types.js';

// ============================================================================
// Types
// ============================================================================

/** Connection status types */
export type ConnectionStatus = 'connected' | 'disconnected' | 'reconnecting';

export interface SavedReportsDropdownProps {
  /** Current connection status */
  connectionStatus: ConnectionStatus;
  /** Callback when reconnect is requested */
  onReconnect: () => void;
  /** Callback when sync is triggered */
  onSync?: () => void;
  /** Number of pending sync items */
  pendingCount?: number;
  /** Whether sync is in progress */
  isSyncing?: boolean;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Format a date string into a relative time string (e.g., "2 hours ago")
 */
function formatTimeAgo(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSecs = Math.floor(diffMs / 1000);
  const diffMins = Math.floor(diffSecs / 60);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffSecs < 60) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays}d ago`;
  
  // For older dates, show the actual date
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  });
}

// ============================================================================
// Component
// ============================================================================

/**
 * SavedReportsDropdown - WiFi status indicator with saved drafts dropdown
 */
export function SavedReportsDropdown({
  connectionStatus,
  onReconnect,
  onSync,
  pendingCount = 0,
  isSyncing = false,
}: SavedReportsDropdownProps) {
  const navigate = useNavigate();
  const {
    drafts,
    count: draftCount,
    isLoading,
    isRefreshing,
    error,
    refetch,
  } = useSavedDrafts(true, 30000); // Auto-refresh every 30 seconds

  // ============================================================================
  // Handlers
  // ============================================================================

  /**
   * Navigate to a specific draft encounter
   */
  const handleDraftClick = useCallback((draft: SavedDraft) => {
    navigate(`/encounters/${draft.encounter_id}`);
  }, [navigate]);

  /**
   * Navigate to dashboard to see all encounters
   */
  const handleViewAll = useCallback(() => {
    navigate('/dashboard');
  }, [navigate]);

  // ============================================================================
  // Computed Values
  // ============================================================================

  /**
   * Get WiFi icon and styling based on connection status
   */
  const wifiStatus = useMemo(() => {
    switch (connectionStatus) {
      case 'connected':
        return {
          icon: Wifi,
          className: 'text-green-600',
          label: 'Online',
          title: 'Connected to Wi-Fi. Reports will automatically sync.',
        };
      case 'disconnected':
        return {
          icon: WifiOff,
          className: 'text-red-600',
          label: 'Offline',
          title: 'Not connected. Click to attempt reconnect. Reports will be saved locally.',
        };
      case 'reconnecting':
        return {
          icon: Wifi,
          className: 'text-yellow-500 animate-pulse',
          label: 'Connecting...',
          title: 'Attempting to reconnect...',
        };
    }
  }, [connectionStatus]);

  const WifiIcon = wifiStatus.icon;

  // Total badge count (drafts + pending sync items)
  const totalBadgeCount = draftCount + pendingCount;

  // Show loading spinner when syncing or reconnecting
  const showSpinner = isSyncing || connectionStatus === 'reconnecting' || isRefreshing;

  // ============================================================================
  // Render
  // ============================================================================

  return (
    <SimpleDropdown
      align="end"
      trigger={
        <Button
          variant="ghost"
          size="sm"
          className="gap-2"
          title={wifiStatus.title}
        >
          <WifiIcon className={`h-4 w-4 ${wifiStatus.className}`} />
          {totalBadgeCount > 0 && (
            <Badge variant="secondary" className="ml-1 h-5 min-w-5 px-1.5">
              {totalBadgeCount > 99 ? '99+' : totalBadgeCount}
            </Badge>
          )}
          {showSpinner && (
            <Loader2 className="h-3 w-3 animate-spin" />
          )}
        </Button>
      }
      className="w-80"
    >
      {/* Connection Status Section */}
      <div className="px-3 py-2 border-b border-slate-200 dark:border-slate-700">
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium text-slate-900 dark:text-slate-100">
            Connection Status
          </span>
          <div className="flex items-center gap-2">
            <WifiIcon className={`h-4 w-4 ${wifiStatus.className}`} />
            <span className="text-xs text-slate-500 dark:text-slate-400">
              {wifiStatus.label}
            </span>
          </div>
        </div>
        
        {/* Reconnect / Sync buttons */}
        <div className="flex items-center gap-2 mt-2">
          {connectionStatus === 'disconnected' && (
            <Button
              variant="link"
              size="sm"
              onClick={onReconnect}
              className="text-blue-600 dark:text-blue-400 p-0 h-auto text-xs"
            >
              Attempt reconnect
            </Button>
          )}
          {connectionStatus === 'connected' && pendingCount > 0 && onSync && (
            <Button
              variant="link"
              size="sm"
              onClick={onSync}
              disabled={isSyncing}
              className="text-blue-600 dark:text-blue-400 p-0 h-auto text-xs flex items-center gap-1"
            >
              <RefreshCw className={`h-3 w-3 ${isSyncing ? 'animate-spin' : ''}`} />
              Sync {pendingCount} pending
            </Button>
          )}
        </div>
      </div>

      <SimpleDropdownSeparator />

      {/* Saved Reports Section */}
      <SimpleDropdownLabel className="flex items-center justify-between">
        <span>Saved Reports</span>
        {draftCount > 0 && (
          <Badge variant="outline" className="ml-2 text-xs">
            {draftCount}
          </Badge>
        )}
      </SimpleDropdownLabel>

      {/* Loading State */}
      {isLoading && !isRefreshing && (
        <div className="px-3 py-4 text-center">
          <Loader2 className="h-5 w-5 animate-spin mx-auto text-slate-400" />
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">
            Loading drafts...
          </p>
        </div>
      )}

      {/* Error State */}
      {error && !isLoading && (
        <div className="px-3 py-4 text-center">
          <p className="text-xs text-red-500">{error}</p>
          <Button
            variant="link"
            size="sm"
            onClick={refetch}
            className="text-blue-600 dark:text-blue-400 p-0 h-auto text-xs mt-1"
          >
            Retry
          </Button>
        </div>
      )}

      {/* Empty State */}
      {!isLoading && !error && drafts.length === 0 && (
        <div className="px-3 py-4 text-center">
          <FileText className="h-8 w-8 text-slate-300 dark:text-slate-600 mx-auto" />
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-2">
            No saved drafts
          </p>
        </div>
      )}

      {/* Drafts List */}
      {!isLoading && !error && drafts.length > 0 && (
        <ScrollArea className="max-h-64">
          <div className="py-1">
            {drafts.map((draft) => (
              <SimpleDropdownItem
                key={draft.encounter_id}
                onClick={() => handleDraftClick(draft)}
                className="flex flex-col items-start py-3 cursor-pointer"
              >
                <div className="flex items-center gap-2 w-full">
                  <FileText className="h-4 w-4 text-blue-600 dark:text-blue-400 flex-shrink-0" />
                  <span className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate flex-1">
                    {draft.patient_display_name || 'New Patient'}
                  </span>
                </div>
                <div className="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-gray-400 pl-6">
                  <Clock className="h-3 w-3" />
                  <span>{formatTimeAgo(draft.modified_at)}</span>
                  {draft.chief_complaint && (
                    <>
                      <span className="text-slate-300 dark:text-slate-600">•</span>
                      <span className="truncate max-w-32">
                        {draft.chief_complaint}
                      </span>
                    </>
                  )}
                </div>
              </SimpleDropdownItem>
            ))}
          </div>
        </ScrollArea>
      )}

      {/* View All Link */}
      {drafts.length > 0 && (
        <>
          <SimpleDropdownSeparator />
          <SimpleDropdownItem
            onClick={handleViewAll}
            className="text-center text-blue-600 dark:text-blue-400 justify-center"
          >
            View all on Dashboard
          </SimpleDropdownItem>
        </>
      )}
    </SimpleDropdown>
  );
}

export default SavedReportsDropdown;
