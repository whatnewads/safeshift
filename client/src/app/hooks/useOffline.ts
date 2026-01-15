import { useState, useEffect, useCallback, useRef } from 'react';
import { toast } from 'sonner';
import { offlineService } from '../services/offline.service.js';

interface UseOfflineReturn {
    /** Whether the device is currently online */
    isOnline: boolean;
    /** Number of encounters pending sync */
    offlineCount: number;
    /** Whether a sync operation is in progress */
    isSyncing: boolean;
    /** Whether there is any offline data */
    hasOfflineData: boolean;
    /** Save encounter data to local storage */
    saveOffline: (encounterId: string | null, data: Record<string, unknown>) => Promise<string>;
    /** Refresh the offline count */
    refreshOfflineCount: () => Promise<void>;
}

/**
 * Hook for managing offline state and operations
 * Handles online/offline detection and IndexedDB storage
 * 
 * Note: For sync functionality, use the useSync hook from SyncContext
 */
export function useOffline(): UseOfflineReturn {
    const [isOnline, setIsOnline] = useState<boolean>(
        typeof navigator !== 'undefined' ? navigator.onLine : true
    );
    const [offlineCount, setOfflineCount] = useState<number>(0);
    const [isSyncing, _setIsSyncing] = useState<boolean>(false);
    const [isInitialized, setIsInitialized] = useState<boolean>(false);
    
    // Track if component is mounted to prevent state updates after unmount
    const isMountedRef = useRef<boolean>(true);
    
    /**
     * Update the offline data count from IndexedDB
     */
    const updateOfflineCount = useCallback(async (): Promise<void> => {
        try {
            const count = await offlineService.getOfflineDataCount();
            if (isMountedRef.current) {
                setOfflineCount(count);
            }
        } catch (error) {
            console.error('Failed to get offline data count:', error);
        }
    }, []);
    
    /**
     * Initialize IndexedDB and set up event listeners
     */
    useEffect(() => {
        isMountedRef.current = true;
        
        const initializeOffline = async () => {
            try {
                await offlineService.initDB();
                await updateOfflineCount();
                if (isMountedRef.current) {
                    setIsInitialized(true);
                }
            } catch (error) {
                console.error('Failed to initialize offline service:', error);
            }
        };
        
        initializeOffline();
        
        // Handle online/offline events
        const handleOnline = () => {
            if (isMountedRef.current) {
                setIsOnline(true);
            }
        };
        
        const handleOffline = () => {
            if (isMountedRef.current) {
                setIsOnline(false);
            }
        };
        
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        
        return () => {
            isMountedRef.current = false;
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, [updateOfflineCount]);
    
    /**
     * Save encounter data to local IndexedDB storage
     * Shows a toast message indicating the data was saved locally
     */
    const saveOffline = useCallback(async (
        encounterId: string | null, 
        data: Record<string, unknown>
    ): Promise<string> => {
        // Ensure DB is initialized
        if (!isInitialized) {
            await offlineService.initDB();
        }
        
        const id = await offlineService.saveEncounterLocally(encounterId, data);
        
        // Show toast message for offline save
        toast.info('Encounter saved locally. Will sync when online.');
        
        // Update the count after saving
        await updateOfflineCount();
        
        return id;
    }, [isInitialized, updateOfflineCount]);
    
    /**
     * Manually refresh the offline count
     */
    const refreshOfflineCount = useCallback(async (): Promise<void> => {
        await updateOfflineCount();
    }, [updateOfflineCount]);
    
    return {
        isOnline,
        offlineCount,
        isSyncing,
        hasOfflineData: offlineCount > 0,
        saveOffline,
        refreshOfflineCount,
    };
}

/**
 * Export the offline service for direct access when needed
 */
export { offlineService };
