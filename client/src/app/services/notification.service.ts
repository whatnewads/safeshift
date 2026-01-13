/**
 * Notification Service
 *
 * Frontend API service for notification operations.
 * Handles all communication with the /api/v1/notifications endpoint.
 *
 * @package SafeShift\Services
 */

import { get, post, del } from './api.js';

// ============================================================================
// Types
// ============================================================================

export type NotificationType = 'training' | 'licensure' | 'registrar' | 'case' | 'message' | 'system' | 'all';
export type NotificationPriority = 'high' | 'medium' | 'low' | 'normal';

export interface Notification {
  id: string;
  notification_id: string;
  user_id: string;
  type: NotificationType;
  priority: NotificationPriority;
  title: string;
  message: string | null;
  data: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  is_read: boolean;
  read: boolean;
  read_at: string | null;
  created_at: string;
  expires_at: string | null;
  timestamp: string;
  action_required: boolean;
}

export interface UnreadCounts {
  all: number;
  training: number;
  licensure: number;
  registrar: number;
  case: number;
  message: number;
  system: number;
}

export interface NotificationFilters {
  type?: NotificationType;
  read?: boolean;
  priority?: NotificationPriority;
  search?: string;
}

export interface PaginationInfo {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface NotificationsResponse {
  notifications: Notification[];
  unread_counts: UnreadCounts;
  pagination: PaginationInfo;
  filters: NotificationFilters;
}

export interface UnreadCountResponse {
  unread_counts: UnreadCounts;
  has_unread: boolean;
  total_unread: number;
}

export interface NotificationActionResponse {
  notification?: Notification;
  unread_counts: UnreadCounts;
  message: string;
}

export interface MarkAllReadResponse {
  marked_count: number;
  unread_counts: UnreadCounts;
  message: string;
}

export interface DeleteAllResponse {
  deleted_count: number;
  unread_counts: UnreadCounts;
  message: string;
}

// ============================================================================
// Notification Endpoints
// ============================================================================

const NOTIFICATION_ENDPOINTS = {
  base: '/notifications',
  byId: (id: string) => `/notifications/${id}`,
  unreadCount: '/notifications/unread-count',
  markRead: (id: string) => `/notifications/${id}/read`,
  markUnread: (id: string) => `/notifications/${id}/unread`,
  markAllRead: '/notifications/mark-all-read',
} as const;

// ============================================================================
// API Functions
// ============================================================================

/**
 * Get notifications with optional filters and pagination
 */
export async function getNotifications(
  filters: NotificationFilters = {},
  page: number = 1,
  perPage: number = 50
): Promise<NotificationsResponse> {
  const params: Record<string, string | number | boolean | undefined> = {
    page,
    per_page: perPage,
  };

  if (filters.type && filters.type !== 'all') {
    params.type = filters.type;
  }
  if (filters.read !== undefined) {
    params.read = filters.read;
  }
  if (filters.priority) {
    params.priority = filters.priority;
  }
  if (filters.search) {
    params.search = filters.search;
  }

  const response = await get<NotificationsResponse>(NOTIFICATION_ENDPOINTS.base, { params });
  return response.data;
}

/**
 * Get a single notification by ID
 */
export async function getNotification(notificationId: string): Promise<Notification> {
  const response = await get<{ notification: Notification }>(NOTIFICATION_ENDPOINTS.byId(notificationId));
  return response.data.notification;
}

/**
 * Get unread notification counts by type
 */
export async function getUnreadCounts(): Promise<UnreadCountResponse> {
  const response = await get<UnreadCountResponse>(NOTIFICATION_ENDPOINTS.unreadCount);
  return response.data;
}

/**
 * Mark a notification as read
 */
export async function markAsRead(notificationId: string): Promise<NotificationActionResponse> {
  const response = await post<NotificationActionResponse>(NOTIFICATION_ENDPOINTS.markRead(notificationId));
  return response.data;
}

/**
 * Mark a notification as unread
 */
export async function markAsUnread(notificationId: string): Promise<NotificationActionResponse> {
  const response = await post<NotificationActionResponse>(NOTIFICATION_ENDPOINTS.markUnread(notificationId));
  return response.data;
}

/**
 * Mark all notifications as read (optionally filtered by type)
 */
export async function markAllAsRead(type?: NotificationType): Promise<MarkAllReadResponse> {
  const data: Record<string, string> = {};
  if (type && type !== 'all') {
    data.type = type;
  }
  const response = await post<MarkAllReadResponse, Record<string, string>>(
    NOTIFICATION_ENDPOINTS.markAllRead,
    data
  );
  return response.data;
}

/**
 * Delete a notification
 */
export async function deleteNotification(notificationId: string): Promise<NotificationActionResponse> {
  const response = await del<NotificationActionResponse>(NOTIFICATION_ENDPOINTS.byId(notificationId));
  return response.data;
}

/**
 * Delete all notifications for the current user
 */
export async function deleteAllNotifications(): Promise<DeleteAllResponse> {
  const response = await del<DeleteAllResponse>(NOTIFICATION_ENDPOINTS.base);
  return response.data;
}

// ============================================================================
// Notification Service Object
// ============================================================================

export const notificationService = {
  getNotifications,
  getNotification,
  getUnreadCounts,
  markAsRead,
  markAsUnread,
  markAllAsRead,
  deleteNotification,
  deleteAllNotifications,
};

export default notificationService;
