/**
 * Sync Service for SafeShift EHR
 *
 * Handles background synchronization of offline data when the device
 * regains connectivity. Manages sync queue, retries, and flash messages.
 *
 * Differentiates between:
 * - Draft saves: Just save/update on server (don't submit for review)
 * - Pending submissions: Submit for review when connection restored
 */

import { offlineService } from './offline.service.js';
import { encounterService } from './encounter.service.js';
import { isValidEncounterId } from './validation.service.js';
import { toast } from 'sonner';

/**
 * Offline status types that determine how encounters are synced
 */
export type OfflineStatus = 'draft' | 'pending_submission' | 'synced' | 'error';

/**
 * Result of a single encounter sync operation
 */
export interface SyncResult {
    /** Whether the sync was successful */
    success: boolean;
    /** The encounter ID that was synced */
    encounterId: string;
    /** Human-readable message about the sync result */
    message: string;
    /** Server-assigned ID if sync was successful */
    serverId?: string;
    /** The type of sync that was performed */
    syncType?: 'draft_save' | 'submission';
}

/**
 * Sync Service Class
 * 
 * Manages background synchronization of offline encounters with the server.
 * Provides listener support for UI updates and handles flash messages.
 */
class SyncService {
    /** Flag indicating if a sync operation is currently in progress */
    private isSyncing = false;
    
    /** Listeners that are notified when sync status changes */
    private syncListeners: ((syncing: boolean) => void)[] = [];
    
    /**
     * Add a listener that will be called when sync status changes
     * 
     * @param callback - Function called with sync status (true = syncing, false = idle)
     * @returns Unsubscribe function to remove the listener
     * 
     * @example
     * ```typescript
     * const unsubscribe = syncService.addSyncListener((isSyncing) => {
     *     console.log(isSyncing ? 'Sync started' : 'Sync completed');
     * });
     * 
     * // Later, to stop listening:
     * unsubscribe();
     * ```
     */
    addSyncListener(callback: (syncing: boolean) => void): () => void {
        this.syncListeners.push(callback);
        return () => {
            this.syncListeners = this.syncListeners.filter(cb => cb !== callback);
        };
    }
    
    /**
     * Notify all listeners of sync status change
     */
    private notifyListeners(): void {
        this.syncListeners.forEach(cb => cb(this.isSyncing));
    }
    
    /**
     * Determine the offline status from encounter data
     * Checks both _offlineStatus and status fields for pending_submission
     */
    private getOfflineStatus(data: Record<string, unknown>): OfflineStatus {
        // Check _offlineStatus first (preferred)
        if (data._offlineStatus === 'pending_submission' || data.status === 'pending_submission') {
            return 'pending_submission';
        }
        if (data._offlineStatus === 'draft' || data.status === 'draft') {
            return 'draft';
        }
        // Default to draft if no explicit status (safer - won't auto-submit)
        return 'draft';
    }

    /**
     * Sync all pending encounters to the server
     *
     * This method:
     * 1. Gets all pending encounters from IndexedDB
     * 2. Determines if each encounter should be saved as draft or submitted for review
     * 3. For drafts: Creates/updates on server without submitting
     * 4. For pending submissions: Submits for review
     * 5. Updates the local status based on result
     * 6. Shows appropriate toast messages
     * 7. Clears synced data after a delay
     *
     * @returns Array of sync results for each encounter processed
     *
     * @example
     * ```typescript
     * const results = await syncService.syncAllPending();
     * const successCount = results.filter(r => r.success).length;
     * console.log(`Successfully synced ${successCount} of ${results.length} encounters`);
     * ```
     */
    async syncAllPending(): Promise<SyncResult[]> {
        // Don't start a new sync if one is already in progress
        if (this.isSyncing) {
            return [];
        }
        
        // Don't sync if offline
        if (!navigator.onLine) {
            return [];
        }
        
        this.isSyncing = true;
        this.notifyListeners();
        
        const results: SyncResult[] = [];
        
        try {
            // Get all pending encounters from IndexedDB
            const pending = await offlineService.getPendingEncounters();
            
            if (pending.length === 0) {
                return results;
            }
            
            // Show syncing toast
            toast.loading(`Syncing ${pending.length} encounter${pending.length > 1 ? 's' : ''}...`, {
                id: 'sync-progress',
            });
            
            for (const encounter of pending) {
                try {
                    // Update status to syncing in IndexedDB
                    await offlineService.updateEncounterStatus(encounter.id, 'syncing');
                    
                    // Determine if this is a new encounter (temp ID) or existing one
                    const isNewEncounter = encounter.id.startsWith('temp_');
                    
                    // For existing encounters, validate the ID before attempting sync
                    // New encounters use 'new' as a special identifier for server-side creation
                    if (!isNewEncounter && !isValidEncounterId(encounter.id)) {
                        await offlineService.updateEncounterStatus(
                            encounter.id,
                            'error',
                            'Invalid encounter ID. Cannot sync this encounter.'
                        );
                        results.push({
                            success: false,
                            encounterId: encounter.id,
                            message: 'Invalid encounter ID. Cannot sync this encounter.',
                        });
                        continue;
                    }
                    
                    // Determine the type of sync based on offline status
                    const offlineStatus = this.getOfflineStatus(encounter.data);
                    const isPendingSubmission = offlineStatus === 'pending_submission';
                    
                    console.log(`[SyncService] Processing encounter ${encounter.id}:`, {
                        isNewEncounter,
                        offlineStatus,
                        isPendingSubmission,
                        _offlineStatus: encounter.data._offlineStatus,
                        status: encounter.data.status,
                    });
                    
                    let response: { success: boolean; message?: string; encounter?: { id?: string } };
                    let syncType: 'draft_save' | 'submission';
                    
                    if (isPendingSubmission) {
                        // PENDING SUBMISSION: Submit for review
                        // This is for encounters that the user explicitly submitted while offline
                        console.log(`[SyncService] Submitting encounter ${encounter.id} for review`);
                        syncType = 'submission';
                        response = await encounterService.submitForReview(
                            isNewEncounter ? 'new' : encounter.id,
                            encounter.data
                        );
                    } else {
                        // DRAFT: Just save/update on server (don't submit for review)
                        // This is for auto-saved drafts or manual "Save" clicks while offline
                        console.log(`[SyncService] Saving draft encounter ${encounter.id}`);
                        syncType = 'draft_save';
                        
                        if (isNewEncounter) {
                            // Create new encounter on server
                            try {
                                const newEncounter = await encounterService.createEncounter(encounter.data as any);
                                response = {
                                    success: true,
                                    message: 'Draft saved successfully',
                                    encounter: newEncounter,
                                };
                            } catch (error) {
                                response = {
                                    success: false,
                                    message: error instanceof Error ? error.message : 'Failed to create encounter',
                                };
                            }
                        } else {
                            // Update existing encounter on server
                            try {
                                const updatedEncounter = await encounterService.updateEncounter(
                                    encounter.id,
                                    encounter.data as any
                                );
                                response = {
                                    success: true,
                                    message: 'Draft updated successfully',
                                    encounter: updatedEncounter,
                                };
                            } catch (error) {
                                response = {
                                    success: false,
                                    message: error instanceof Error ? error.message : 'Failed to update encounter',
                                };
                            }
                        }
                    }
                    
                    if (response.success) {
                        // Mark as synced in IndexedDB
                        await offlineService.updateEncounterStatus(encounter.id, 'synced');
                        const syncResult: SyncResult = {
                            success: true,
                            encounterId: encounter.id,
                            message: isPendingSubmission ? 'Submitted for review' : 'Draft saved',
                            syncType,
                        };
                        if (response.encounter?.id) {
                            syncResult.serverId = response.encounter.id;
                        }
                        results.push(syncResult);
                        console.log(`[SyncService] Successfully synced encounter ${encounter.id}:`, syncResult);
                    } else {
                        // Mark as error in IndexedDB
                        await offlineService.updateEncounterStatus(
                            encounter.id,
                            'error',
                            response.message
                        );
                        results.push({
                            success: false,
                            encounterId: encounter.id,
                            message: response.message || 'Sync failed',
                            syncType,
                        });
                        console.error(`[SyncService] Failed to sync encounter ${encounter.id}:`, response.message);
                    }
                } catch (error) {
                    // Mark as error in IndexedDB with error message
                    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
                    await offlineService.updateEncounterStatus(
                        encounter.id,
                        'error',
                        errorMessage
                    );
                    results.push({
                        success: false,
                        encounterId: encounter.id,
                        message: errorMessage,
                    });
                    console.error(`[SyncService] Exception while syncing encounter ${encounter.id}:`, error);
                }
            }
            
            // Dismiss the loading toast
            toast.dismiss('sync-progress');
            
            // Show completion toast based on results
            const successCount = results.filter(r => r.success).length;
            const failCount = results.filter(r => !r.success).length;
            const submittedCount = results.filter(r => r.success && r.syncType === 'submission').length;
            const savedCount = results.filter(r => r.success && r.syncType === 'draft_save').length;
            
            if (failCount === 0) {
                // All successful - show detailed message
                const parts: string[] = [];
                if (submittedCount > 0) {
                    parts.push(`${submittedCount} submitted for review`);
                }
                if (savedCount > 0) {
                    parts.push(`${savedCount} draft${savedCount > 1 ? 's' : ''} saved`);
                }
                toast.success(parts.join(', ') || `${successCount} encounter${successCount > 1 ? 's' : ''} synced`);
            } else if (successCount === 0) {
                // All failed
                toast.error(
                    `Failed to sync ${failCount} encounter${failCount > 1 ? 's' : ''}`
                );
            } else {
                // Partial success
                toast.warning(
                    `Synced ${successCount}, failed ${failCount} encounter${failCount > 1 ? 's' : ''}`
                );
            }
            
            return results;
        } finally {
            this.isSyncing = false;
            this.notifyListeners();
            
            // Clear synced data from IndexedDB after a delay
            // This gives UI time to update before removing the data
            setTimeout(async () => {
                try {
                    await offlineService.clearSyncedData();
                } catch (error) {
                    console.error('Failed to clear synced data:', error);
                }
            }, 5000);
        }
    }
    
    /**
     * Get current sync status
     * 
     * @returns true if sync is in progress, false otherwise
     */
    getSyncStatus(): boolean {
        return this.isSyncing;
    }
    
    /**
     * Retry syncing a specific encounter that previously failed
     *
     * Handles both draft saves and pending submissions based on the encounter's
     * offline status (_offlineStatus or status field).
     *
     * @param encounterId - ID of the encounter to retry
     * @returns Sync result for the encounter
     */
    async retrySingleEncounter(encounterId: string): Promise<SyncResult> {
        if (!navigator.onLine) {
            return {
                success: false,
                encounterId,
                message: 'Device is offline',
            };
        }
        
        try {
            const encounter = await offlineService.getLocalEncounter(encounterId);
            
            if (!encounter) {
                return {
                    success: false,
                    encounterId,
                    message: 'Encounter not found in local storage',
                };
            }
            
            // Determine if this is a new encounter
            const isNewEncounter = encounterId.startsWith('temp_');
            
            // For existing encounters, validate the ID before attempting sync
            if (!isNewEncounter && !isValidEncounterId(encounterId)) {
                return {
                    success: false,
                    encounterId,
                    message: 'Invalid encounter ID. Cannot sync this encounter.',
                };
            }
            
            // Determine the type of sync based on offline status
            const offlineStatus = this.getOfflineStatus(encounter.data);
            const isPendingSubmission = offlineStatus === 'pending_submission';
            
            console.log(`[SyncService] Retrying encounter ${encounterId}:`, {
                isNewEncounter,
                offlineStatus,
                isPendingSubmission,
            });
            
            // Update status to syncing
            await offlineService.updateEncounterStatus(encounterId, 'syncing');
            
            const toastMessage = isPendingSubmission ? 'Submitting for review...' : 'Saving draft...';
            toast.loading(toastMessage, { id: `retry-${encounterId}` });
            
            let response: { success: boolean; message?: string; encounter?: { id?: string } };
            let syncType: 'draft_save' | 'submission';
            
            if (isPendingSubmission) {
                // PENDING SUBMISSION: Submit for review
                syncType = 'submission';
                response = await encounterService.submitForReview(
                    isNewEncounter ? 'new' : encounterId,
                    encounter.data
                );
            } else {
                // DRAFT: Just save/update on server
                syncType = 'draft_save';
                
                if (isNewEncounter) {
                    try {
                        const newEncounter = await encounterService.createEncounter(encounter.data as any);
                        response = {
                            success: true,
                            message: 'Draft saved successfully',
                            encounter: newEncounter,
                        };
                    } catch (error) {
                        response = {
                            success: false,
                            message: error instanceof Error ? error.message : 'Failed to create encounter',
                        };
                    }
                } else {
                    try {
                        const updatedEncounter = await encounterService.updateEncounter(
                            encounterId,
                            encounter.data as any
                        );
                        response = {
                            success: true,
                            message: 'Draft updated successfully',
                            encounter: updatedEncounter,
                        };
                    } catch (error) {
                        response = {
                            success: false,
                            message: error instanceof Error ? error.message : 'Failed to update encounter',
                        };
                    }
                }
            }
            
            toast.dismiss(`retry-${encounterId}`);
            
            if (response.success) {
                await offlineService.updateEncounterStatus(encounterId, 'synced');
                const successMessage = isPendingSubmission
                    ? 'Encounter submitted for review'
                    : 'Draft saved successfully';
                toast.success(successMessage);
                
                // Clear after delay
                setTimeout(async () => {
                    try {
                        await offlineService.clearSyncedData();
                    } catch (error) {
                        console.error('Failed to clear synced data:', error);
                    }
                }, 5000);
                
                const result: SyncResult = {
                    success: true,
                    encounterId,
                    message: successMessage,
                    syncType,
                };
                if (response.encounter?.id) {
                    result.serverId = response.encounter.id;
                }
                return result;
            } else {
                await offlineService.updateEncounterStatus(encounterId, 'error', response.message);
                toast.error(response.message || 'Sync failed');
                return {
                    success: false,
                    encounterId,
                    message: response.message || 'Sync failed',
                    syncType,
                };
            }
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Unknown error';
            await offlineService.updateEncounterStatus(encounterId, 'error', errorMessage);
            toast.dismiss(`retry-${encounterId}`);
            toast.error(`Sync failed: ${errorMessage}`);
            return {
                success: false,
                encounterId,
                message: errorMessage,
            };
        }
    }
}

/**
 * Singleton instance of the SyncService
 */
export const syncService = new SyncService();

export default syncService;
