/**
 * useNotifications Hook
 *
 * React hook for managing notification state and operations.
 * Provides access to notifications data, loading states, and actions.
 *
 * @package SafeShift\Hooks
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  getNotifications,
  getUnreadCounts,
  markAsRead,
  markAsUnread,
  markAllAsRead,
  deleteNotification,
  deleteAllNotifications,
  type Notification,
  type NotificationType,
  type NotificationFilters,
  type UnreadCounts,
  type NotificationsResponse,
} from '../services/notification.service.js';

// ============================================================================
// Types
// ============================================================================

export interface UseNotificationsState {
  notifications: Notification[];
  unreadCounts: UnreadCounts;
  isLoading: boolean;
  isRefreshing: boolean;
  error: string | null;
  pagination: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
  filters: NotificationFilters;
}

export interface UseNotificationsActions {
  // Data fetching
  refresh: () => Promise<void>;
  loadMore: () => Promise<void>;
  setFilters: (filters: NotificationFilters) => void;
  setPage: (page: number) => void;
  
  // Notification actions
  markRead: (notificationId: string) => Promise<void>;
  markUnread: (notificationId: string) => Promise<void>;
  markAllRead: (type?: NotificationType) => Promise<void>;
  remove: (notificationId: string) => Promise<void>;
  removeAll: () => Promise<void>;
  
  // Utility
  clearError: () => void;
}

export interface UseNotificationsReturn extends UseNotificationsState, UseNotificationsActions {
  hasMore: boolean;
  hasUnread: boolean;
}

// ============================================================================
// Default State
// ============================================================================

const defaultUnreadCounts: UnreadCounts = {
  all: 0,
  training: 0,
  licensure: 0,
  registrar: 0,
  case: 0,
  message: 0,
  system: 0,
};

// ============================================================================
// Hook Implementation
// ============================================================================

/**
 * Hook for managing notifications state and operations
 * 
 * @param initialFilters - Optional initial filter settings
 * @param autoRefresh - Enable auto-refresh interval (default: true)
 * @param refreshInterval - Refresh interval in ms (default: 60000 = 1 minute)
 * 
 * @example
 * ```tsx
 * const {
 *   notifications,
 *   unreadCounts,
 *   isLoading,
 *   markRead,
 *   setFilters,
 * } = useNotifications({ type: 'training' });
 * ```
 */
export function useNotifications(
  initialFilters: NotificationFilters = {},
  autoRefresh: boolean = true,
  refreshInterval: number = 60000
): UseNotificationsReturn {
  // State
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [unreadCounts, setUnreadCounts] = useState<UnreadCounts>(defaultUnreadCounts);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isRefreshing, setIsRefreshing] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [pagination, setPagination] = useState({
    page: 1,
    perPage: 50,
    total: 0,
    totalPages: 0,
  });
  const [filters, setFiltersState] = useState<NotificationFilters>(initialFilters);

  // Refs for cleanup
  const isMountedRef = useRef<boolean>(true);
  const refreshTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ============================================================================
  // Data Fetching
  // ============================================================================

  /**
   * Fetch notifications from API
   */
  const fetchNotifications = useCallback(async (
    currentFilters: NotificationFilters,
    page: number,
    isRefresh: boolean = false
  ): Promise<NotificationsResponse | null> => {
    try {
      if (isRefresh) {
        setIsRefreshing(true);
      } else {
        setIsLoading(true);
      }
      setError(null);

      const response = await getNotifications(currentFilters, page, pagination.perPage);

      if (!isMountedRef.current) return null;

      setNotifications(response.notifications);
      setUnreadCounts(response.unread_counts);
      setPagination({
        page: response.pagination.page,
        perPage: response.pagination.per_page,
        total: response.pagination.total,
        totalPages: response.pagination.total_pages,
      });

      return response;
    } catch (err) {
      if (!isMountedRef.current) return null;
      
      const errorMessage = err instanceof Error ? err.message : 'Failed to load notifications';
      setError(errorMessage);
      console.error('Error fetching notifications:', err);
      return null;
    } finally {
      if (isMountedRef.current) {
        setIsLoading(false);
        setIsRefreshing(false);
      }
    }
  }, [pagination.perPage]);

  /**
   * Refresh notifications (background refresh)
   */
  const refresh = useCallback(async (): Promise<void> => {
    await fetchNotifications(filters, pagination.page, true);
  }, [fetchNotifications, filters, pagination.page]);

  /**
   * Load more notifications (pagination)
   */
  const loadMore = useCallback(async (): Promise<void> => {
    if (pagination.page >= pagination.totalPages) return;
    
    const nextPage = pagination.page + 1;
    try {
      setIsLoading(true);
      const response = await getNotifications(filters, nextPage, pagination.perPage);
      
      if (!isMountedRef.current) return;
      
      // Append to existing notifications
      setNotifications(prev => [...prev, ...response.notifications]);
      setUnreadCounts(response.unread_counts);
      setPagination({
        page: response.pagination.page,
        perPage: response.pagination.per_page,
        total: response.pagination.total,
        totalPages: response.pagination.total_pages,
      });
    } catch (err) {
      if (!isMountedRef.current) return;
      
      const errorMessage = err instanceof Error ? err.message : 'Failed to load more notifications';
      setError(errorMessage);
    } finally {
      if (isMountedRef.current) {
        setIsLoading(false);
      }
    }
  }, [filters, pagination]);

  // ============================================================================
  // Filter Actions
  // ============================================================================

  /**
   * Update filters and refetch
   */
  const setFilters = useCallback((newFilters: NotificationFilters): void => {
    setFiltersState(newFilters);
    // Reset to page 1 when filters change
    setPagination(prev => ({ ...prev, page: 1 }));
  }, []);

  /**
   * Set current page
   */
  const setPage = useCallback((page: number): void => {
    setPagination(prev => ({ ...prev, page }));
  }, []);

  // ============================================================================
  // Notification Actions
  // ============================================================================

  /**
   * Mark a notification as read
   */
  const markRead = useCallback(async (notificationId: string): Promise<void> => {
    try {
      const response = await markAsRead(notificationId);
      
      if (!isMountedRef.current) return;
      
      // Update local state
      setNotifications(prev =>
        prev.map(n =>
          n.id === notificationId
            ? { ...n, is_read: true, read: true }
            : n
        )
      );
      setUnreadCounts(response.unread_counts);
    } catch (err) {
      if (!isMountedRef.current) return;
      
      const errorMessage = err instanceof Error ? err.message : 'Failed to mark notification as read';
      setError(errorMessage);
    }
  }, []);

  /**
   * Mark a notification as unread
   */
  const markUnread = useCallback(async (notificationId: string): Promise<void> => {
    try {
      const response = await markAsUnread(notificationId);
      
      if (!isMountedRef.current) return;
      
      // Update local state
      setNotifications(prev =>
        prev.map(n =>
          n.id === notificationId
            ? { ...n, is_read: false, read: false }
            : n
        )
      );
      setUnreadCounts(response.unread_counts);
    } catch (err) {
      if (!isMountedRef.current) return;
      
      const errorMessage = err instanceof Error ? err.message : 'Failed to mark notification as unread';
      setError(errorMessage);
    }
  }, []);

  /**
   * Mark all notifications as read
   */
  const markAllRead = useCallback(async (type?: NotificationType): Promise<void> => {
    try {
      const response = await markAllAsRead(type);
      
      if (!isMountedRef.current) return;
      
      // Update local state - mark all matching notifications as read
      setNotifications(prev =>
        prev.map(n =>
          (!type || type === 'all' || n.type === type)
            ? { ...n, is_read: true, read: true }
            : n
        )
      );
      setUnreadCounts(response.unread_counts);
    } catch (err) {
      if (!isMountedRef.current) return;
      
      const errorMessage = err instanceof Error ? err.message : 'Failed to mark all notifications as read';
      setError(errorMessage);
    }
  }, []);

  /**
   * Delete a notification
   */
  const remove = useCallback(async (notificationId: string): Promise<void> => {
    try {
      const response = await deleteNotification(notificationId);
      
      if (!isMountedRef.current) return;
      
      // Remove from local state
      setNotifications(prev => prev.filter(n => n.id !== notificationId));
      setUnreadCounts(response.unread_counts);
      
      // Update total count
      setPagination(prev => ({
        ...prev,
        total: Math.max(0, prev.total - 1),
      }));
    } catch (err) {
      if (!isMountedRef.current) return;
      
      const errorMessage = err instanceof Error ? err.message : 'Failed to delete notification';
      setError(errorMessage);
    }
  }, []);

  /**
   * Delete all notifications
   */
  const removeAll = useCallback(async (): Promise<void> => {
    try {
      const response = await deleteAllNotifications();
      
      if (!isMountedRef.current) return;
      
      // Clear local state
      setNotifications([]);
      setUnreadCounts(response.unread_counts);
      setPagination(prev => ({
        ...prev,
        total: 0,
        totalPages: 0,
        page: 1,
      }));
    } catch (err) {
      if (!isMountedRef.current) return;
      
      const errorMessage = err instanceof Error ? err.message : 'Failed to delete all notifications';
      setError(errorMessage);
    }
  }, []);

  /**
   * Clear error state
   */
  const clearError = useCallback((): void => {
    setError(null);
  }, []);

  // ============================================================================
  // Effects
  // ============================================================================

  // Initial fetch and when filters change
  useEffect(() => {
    fetchNotifications(filters, 1, false);
  }, [filters, fetchNotifications]);

  // Auto-refresh timer
  useEffect(() => {
    if (autoRefresh && refreshInterval > 0) {
      refreshTimerRef.current = setInterval(() => {
        if (isMountedRef.current) {
          refresh();
        }
      }, refreshInterval);
    }

    return () => {
      if (refreshTimerRef.current) {
        clearInterval(refreshTimerRef.current);
        refreshTimerRef.current = null;
      }
    };
  }, [autoRefresh, refreshInterval, refresh]);

  // Cleanup on unmount
  useEffect(() => {
    isMountedRef.current = true;
    
    return () => {
      isMountedRef.current = false;
      if (refreshTimerRef.current) {
        clearInterval(refreshTimerRef.current);
      }
    };
  }, []);

  // ============================================================================
  // Computed Values
  // ============================================================================

  const hasMore = pagination.page < pagination.totalPages;
  const hasUnread = unreadCounts.all > 0;

  // ============================================================================
  // Return
  // ============================================================================

  return {
    // State
    notifications,
    unreadCounts,
    isLoading,
    isRefreshing,
    error,
    pagination,
    filters,
    
    // Computed
    hasMore,
    hasUnread,
    
    // Actions
    refresh,
    loadMore,
    setFilters,
    setPage,
    markRead,
    markUnread,
    markAllRead,
    remove,
    removeAll,
    clearError,
  };
}

export default useNotifications;
