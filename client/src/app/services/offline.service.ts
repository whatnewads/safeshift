import { openDB } from 'idb';
import type { DBSchema, IDBPDatabase } from 'idb';

interface EncounterDB extends DBSchema {
    encounters: {
        key: string;
        value: {
            id: string;
            tempId: string;
            data: Record<string, unknown>;
            status: 'pending' | 'syncing' | 'synced' | 'error';
            createdAt: Date;
            updatedAt: Date;
            syncAttempts: number;
            lastError?: string | undefined;
        };
        indexes: { 'by-status': string };
    };
    syncQueue: {
        key: string;
        value: {
            id: string;
            action: 'create' | 'update' | 'submit';
            encounterId: string;
            data: Record<string, unknown>;
            priority: number;
            createdAt: Date;
            status: 'pending' | 'processing' | 'failed';
            retryCount: number;
        };
        indexes: { 'by-status': string; 'by-priority': number };
    };
}

/**
 * Generate a UUID v4 for temporary IDs
 */
function generateUUID(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

class OfflineService {
    private db: IDBPDatabase<EncounterDB> | null = null;
    private readonly DB_NAME = 'SafeShiftEHR';
    private readonly DB_VERSION = 1;
    private initPromise: Promise<void> | null = null;
    
    /**
     * Initialize the IndexedDB database
     */
    async initDB(): Promise<void> {
        // Prevent multiple simultaneous initializations
        if (this.initPromise) {
            return this.initPromise;
        }
        
        if (this.db) {
            return;
        }
        
        this.initPromise = this._initDB();
        return this.initPromise;
    }
    
    private async _initDB(): Promise<void> {
        try {
            this.db = await openDB<EncounterDB>(this.DB_NAME, this.DB_VERSION, {
                upgrade(db) {
                    // Create encounters store
                    if (!db.objectStoreNames.contains('encounters')) {
                        const encounterStore = db.createObjectStore('encounters', { keyPath: 'id' });
                        encounterStore.createIndex('by-status', 'status');
                    }
                    
                    // Create sync queue store
                    if (!db.objectStoreNames.contains('syncQueue')) {
                        const syncStore = db.createObjectStore('syncQueue', { keyPath: 'id' });
                        syncStore.createIndex('by-status', 'status');
                        syncStore.createIndex('by-priority', 'priority');
                    }
                },
            });
        } catch (error) {
            console.error('Failed to initialize IndexedDB:', error);
            this.initPromise = null;
            throw error;
        }
    }
    
    /**
     * Ensure database is initialized before operations
     */
    private async ensureDB(): Promise<IDBPDatabase<EncounterDB>> {
        if (!this.db) {
            await this.initDB();
        }
        if (!this.db) {
            throw new Error('Database not initialized');
        }
        return this.db;
    }
    
    /**
     * Save encounter locally to IndexedDB
     * @param encounterId - Existing encounter ID or null for new encounters
     * @param data - Encounter form data
     * @returns The ID used to store the encounter (existing or generated temp ID)
     */
    async saveEncounterLocally(encounterId: string | null, data: Record<string, unknown>): Promise<string> {
        const db = await this.ensureDB();
        const now = new Date();
        
        // Generate temp ID if new encounter
        const tempId = encounterId ? `temp_${encounterId}` : `temp_${generateUUID()}`;
        const id = encounterId || tempId;
        
        // Check if encounter already exists locally
        const existingEncounter = await db.get('encounters', id);
        
        const encounterRecord: EncounterDB['encounters']['value'] = {
            id,
            tempId,
            data,
            status: 'pending',
            createdAt: existingEncounter?.createdAt || now,
            updatedAt: now,
            syncAttempts: existingEncounter?.syncAttempts || 0,
        };
        
        // Save to IndexedDB
        await db.put('encounters', encounterRecord);
        
        // Determine action type
        const action: 'create' | 'update' = encounterId ? 'update' : 'create';
        
        // Add to sync queue
        await this.addToSyncQueue(action, id, data);
        
        return id;
    }
    
    /**
     * Get locally saved encounter by ID
     */
    async getLocalEncounter(id: string): Promise<EncounterDB['encounters']['value'] | undefined> {
        const db = await this.ensureDB();
        return db.get('encounters', id);
    }
    
    /**
     * Get all encounters with 'pending' status
     */
    async getPendingEncounters(): Promise<EncounterDB['encounters']['value'][]> {
        const db = await this.ensureDB();
        return db.getAllFromIndex('encounters', 'by-status', 'pending');
    }
    
    /**
     * Get all locally stored encounters (any status)
     */
    async getAllLocalEncounters(): Promise<EncounterDB['encounters']['value'][]> {
        const db = await this.ensureDB();
        return db.getAll('encounters');
    }
    
    /**
     * Update encounter sync status
     */
    async updateEncounterStatus(
        id: string, 
        status: 'pending' | 'syncing' | 'synced' | 'error', 
        error?: string
    ): Promise<void> {
        const db = await this.ensureDB();
        const encounter = await db.get('encounters', id);
        
        if (!encounter) {
            throw new Error(`Encounter ${id} not found in local storage`);
        }
        
        const updatedEncounter: EncounterDB['encounters']['value'] = {
            ...encounter,
            status,
            updatedAt: new Date(),
            syncAttempts: status === 'syncing' ? encounter.syncAttempts + 1 : encounter.syncAttempts,
            ...(error !== undefined ? { lastError: error } : {}),
        };
        
        await db.put('encounters', updatedEncounter);
    }
    
    /**
     * Add operation to sync queue
     */
    async addToSyncQueue(
        action: 'create' | 'update' | 'submit', 
        encounterId: string, 
        data: Record<string, unknown>
    ): Promise<void> {
        const db = await this.ensureDB();
        
        // Priority: submit > create > update
        const priorityMap = { submit: 1, create: 2, update: 3 };
        
        const syncRecord: EncounterDB['syncQueue']['value'] = {
            id: generateUUID(),
            action,
            encounterId,
            data,
            priority: priorityMap[action],
            createdAt: new Date(),
            status: 'pending',
            retryCount: 0,
        };
        
        await db.add('syncQueue', syncRecord);
    }
    
    /**
     * Get pending sync operations sorted by priority
     */
    async getPendingSyncOperations(): Promise<EncounterDB['syncQueue']['value'][]> {
        const db = await this.ensureDB();
        const pendingOps = await db.getAllFromIndex('syncQueue', 'by-status', 'pending');
        
        // Sort by priority (lower number = higher priority)
        return pendingOps.sort((a, b) => {
            if (a.priority !== b.priority) {
                return a.priority - b.priority;
            }
            // Same priority - sort by creation time (older first)
            return a.createdAt.getTime() - b.createdAt.getTime();
        });
    }
    
    /**
     * Update sync operation status
     */
    async updateSyncOperationStatus(
        id: string, 
        status: 'pending' | 'processing' | 'failed',
        incrementRetry: boolean = false
    ): Promise<void> {
        const db = await this.ensureDB();
        const operation = await db.get('syncQueue', id);
        
        if (!operation) {
            throw new Error(`Sync operation ${id} not found`);
        }
        
        const updatedOperation: EncounterDB['syncQueue']['value'] = {
            ...operation,
            status,
            retryCount: incrementRetry ? operation.retryCount + 1 : operation.retryCount,
        };
        
        await db.put('syncQueue', updatedOperation);
    }
    
    /**
     * Remove sync operation from queue (after successful sync)
     */
    async removeSyncOperation(id: string): Promise<void> {
        const db = await this.ensureDB();
        await db.delete('syncQueue', id);
    }
    
    /**
     * Clear synced data from local storage
     */
    async clearSyncedData(): Promise<void> {
        const db = await this.ensureDB();
        const syncedEncounters = await db.getAllFromIndex('encounters', 'by-status', 'synced');
        
        const tx = db.transaction('encounters', 'readwrite');
        await Promise.all([
            ...syncedEncounters.map(enc => tx.store.delete(enc.id)),
            tx.done,
        ]);
    }
    
    /**
     * Clear all local data (for debugging or user request)
     */
    async clearAllData(): Promise<void> {
        const db = await this.ensureDB();
        
        const encounterTx = db.transaction('encounters', 'readwrite');
        await encounterTx.store.clear();
        await encounterTx.done;
        
        const syncTx = db.transaction('syncQueue', 'readwrite');
        await syncTx.store.clear();
        await syncTx.done;
    }
    
    /**
     * Check if there's offline data pending sync
     */
    async hasOfflineData(): Promise<boolean> {
        const pending = await this.getPendingEncounters();
        return pending.length > 0;
    }
    
    /**
     * Get count of offline encounters pending sync
     */
    async getOfflineDataCount(): Promise<number> {
        const pending = await this.getPendingEncounters();
        return pending.length;
    }
    
    /**
     * Get count of pending sync operations
     */
    async getSyncQueueCount(): Promise<number> {
        const pending = await this.getPendingSyncOperations();
        return pending.length;
    }
    
    /**
     * Delete a specific local encounter
     */
    async deleteLocalEncounter(id: string): Promise<void> {
        const db = await this.ensureDB();
        await db.delete('encounters', id);
        
        // Also remove related sync operations
        const allSyncOps = await db.getAll('syncQueue');
        const relatedOps = allSyncOps.filter(op => op.encounterId === id);
        
        const tx = db.transaction('syncQueue', 'readwrite');
        await Promise.all([
            ...relatedOps.map(op => tx.store.delete(op.id)),
            tx.done,
        ]);
    }
}

export const offlineService = new OfflineService();
