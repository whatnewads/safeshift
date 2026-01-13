import type { ReactElement } from 'react';
import { Cloud, CloudOff, Loader2, RefreshCw } from 'lucide-react';
import { useOffline } from '../hooks/useOffline.js';
import { useSync } from '../contexts/SyncContext.js';

/**
 * OfflineIndicator Component
 *
 * Displays the current online/offline status and any pending sync data.
 * Shows different states:
 * - Online with no pending data (green)
 * - Syncing (yellow, animated)
 * - Offline (red)
 * - Online but has pending data to sync (yellow with sync button)
 */
export function OfflineIndicator(): ReactElement {
    const { isOnline, offlineCount } = useOffline();
    const { isSyncing, triggerSync, pendingCount } = useSync();
    
    // Use pending count from sync context if available
    const count = pendingCount > 0 ? pendingCount : offlineCount;
    
    // Online with no pending data
    if (isOnline && count === 0) {
        return (
            <div className="flex items-center gap-2 text-green-600">
                <Cloud className="h-4 w-4" />
                <span className="text-sm">Online</span>
            </div>
        );
    }
    
    // Currently syncing
    if (isSyncing) {
        return (
            <div className="flex items-center gap-2 text-yellow-600 animate-pulse">
                <Loader2 className="h-4 w-4 animate-spin" />
                <span className="text-sm">Syncing...</span>
            </div>
        );
    }
    
    // Offline
    if (!isOnline) {
        return (
            <div className="flex items-center gap-2 text-red-600">
                <CloudOff className="h-4 w-4" />
                <span className="text-sm">Offline</span>
                {count > 0 && (
                    <span className="bg-red-100 text-red-800 px-2 py-0.5 rounded text-xs">
                        {count} pending
                    </span>
                )}
            </div>
        );
    }
    
    // Online but has offline data to sync - show sync button
    return (
        <div className="flex items-center gap-2 text-yellow-600">
            <Cloud className="h-4 w-4" />
            <span className="text-sm">Online</span>
            <button
                onClick={triggerSync}
                className="flex items-center gap-1 text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 transition-colors"
                title="Sync pending encounters"
            >
                <RefreshCw className="h-3 w-3" />
                Sync {count} now
            </button>
        </div>
    );
}

/**
 * Compact version of the OfflineIndicator for use in headers/toolbars
 */
export function OfflineIndicatorCompact(): ReactElement {
    const { isOnline, offlineCount } = useOffline();
    const { isSyncing, triggerSync, pendingCount } = useSync();
    
    // Use pending count from sync context if available
    const count = pendingCount > 0 ? pendingCount : offlineCount;
    
    if (isSyncing) {
        return (
            <div className="relative" title="Syncing data...">
                <Loader2 className="h-5 w-5 text-yellow-600 animate-spin" />
            </div>
        );
    }
    
    if (!isOnline) {
        return (
            <div className="relative" title={`Offline - ${count} encounters pending`}>
                <CloudOff className="h-5 w-5 text-red-600" />
                {count > 0 && (
                    <span className="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                        {count > 9 ? '9+' : count}
                    </span>
                )}
            </div>
        );
    }
    
    if (count > 0) {
        return (
            <button
                onClick={triggerSync}
                className="relative hover:opacity-80 transition-opacity"
                title={`${count} encounters to sync - click to sync now`}
            >
                <Cloud className="h-5 w-5 text-yellow-600" />
                <span className="absolute -top-1 -right-1 bg-yellow-600 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">
                    {count > 9 ? '9+' : count}
                </span>
            </button>
        );
    }
    
    return (
        <div title="Online">
            <Cloud className="h-5 w-5 text-green-600" />
        </div>
    );
}

export default OfflineIndicator;
