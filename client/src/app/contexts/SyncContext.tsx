/**
 * Sync Context for SafeShift EHR
 * 
 * Provides sync state and functionality throughout the application.
 * Handles automatic background sync when reconnecting to the network.
 */

import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { syncService } from '../services/sync.service.js';
import { offlineService } from '../services/offline.service.js';

/**
 * Shape of the sync context value
 */
interface SyncContextValue {
    /** Whether a sync operation is currently in progress */
    isSyncing: boolean;
    /** Timestamp of the last successful sync */
    lastSyncTime: Date | null;
    /** Number of encounters pending sync */
    pendingCount: number;
    /** Manually trigger a sync operation */
    triggerSync: () => Promise<void>;
    /** Retry syncing a specific encounter */
    retrySingle: (encounterId: string) => Promise<void>;
}

const SyncContext = createContext<SyncContextValue | undefined>(undefined);

/**
 * SyncProvider Component
 * 
 * Wraps the application to provide sync state and functionality.
 * Automatically syncs pending data when the device comes back online.
 * 
 * @example
 * ```tsx
 * function App() {
 *     return (
 *         <SyncProvider>
 *             <YourApp />
 *         </SyncProvider>
 *     );
 * }
 * ```
 */
export function SyncProvider({ children }: { children: React.ReactNode }) {
    const [isSyncing, setIsSyncing] = useState(false);
    const [lastSyncTime, setLastSyncTime] = useState<Date | null>(null);
    const [pendingCount, setPendingCount] = useState(0);
    
    /**
     * Listen for sync status changes from the sync service
     */
    useEffect(() => {
        const unsubscribe = syncService.addSyncListener(setIsSyncing);
        return unsubscribe;
    }, []);
    
    /**
     * Auto-sync when coming online and handle initial sync
     */
    useEffect(() => {
        const handleOnline = async () => {
            // Small delay to ensure network is stable before syncing
            setTimeout(async () => {
                if (navigator.onLine) {
                    await syncService.syncAllPending();
                    setLastSyncTime(new Date());
                    const count = await offlineService.getOfflineDataCount();
                    setPendingCount(count);
                }
            }, 1000);
        };
        
        window.addEventListener('online', handleOnline);
        
        // Initial sync check if online and has pending data
        const initialSync = async () => {
            try {
                await offlineService.initDB();
                const hasPending = await offlineService.hasOfflineData();
                const count = await offlineService.getOfflineDataCount();
                setPendingCount(count);
                
                if (navigator.onLine && hasPending) {
                    await syncService.syncAllPending();
                    setLastSyncTime(new Date());
                    // Update count after sync
                    const newCount = await offlineService.getOfflineDataCount();
                    setPendingCount(newCount);
                }
            } catch (error) {
                console.error('Failed to initialize sync:', error);
            }
        };
        initialSync();
        
        return () => {
            window.removeEventListener('online', handleOnline);
        };
    }, []);
    
    /**
     * Update pending count periodically
     */
    useEffect(() => {
        const interval = setInterval(async () => {
            try {
                const count = await offlineService.getOfflineDataCount();
                setPendingCount(count);
            } catch (error) {
                console.error('Failed to update pending count:', error);
            }
        }, 5000);
        
        return () => clearInterval(interval);
    }, []);
    
    /**
     * Manually trigger a sync operation
     */
    const triggerSync = useCallback(async () => {
        await syncService.syncAllPending();
        setLastSyncTime(new Date());
        const count = await offlineService.getOfflineDataCount();
        setPendingCount(count);
    }, []);
    
    /**
     * Retry syncing a specific encounter
     */
    const retrySingle = useCallback(async (encounterId: string) => {
        await syncService.retrySingleEncounter(encounterId);
        const count = await offlineService.getOfflineDataCount();
        setPendingCount(count);
    }, []);
    
    return (
        <SyncContext.Provider value={{ 
            isSyncing, 
            lastSyncTime, 
            pendingCount, 
            triggerSync,
            retrySingle,
        }}>
            {children}
        </SyncContext.Provider>
    );
}

/**
 * Hook to access sync context
 * 
 * @throws Error if used outside of SyncProvider
 * @returns Sync context value
 * 
 * @example
 * ```tsx
 * function MyComponent() {
 *     const { isSyncing, pendingCount, triggerSync } = useSync();
 *     
 *     return (
 *         <button onClick={triggerSync} disabled={isSyncing}>
 *             Sync ({pendingCount} pending)
 *         </button>
 *     );
 * }
 * ```
 */
export function useSync(): SyncContextValue {
    const context = useContext(SyncContext);
    if (!context) {
        throw new Error('useSync must be used within a SyncProvider');
    }
    return context;
}
