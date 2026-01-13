/**
 * Core API Client for SafeShift EHR
 * 
 * Axios-based HTTP client with interceptors for authentication,
 * CSRF protection, error handling, and request/response logging.
 */

import axios, {
  type AxiosInstance,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
  type AxiosError,
} from 'axios';
import type {
  ApiResponse,
  ApiError,
  ApiEvent,
  ApiEventType,
  RequestConfig,
} from '../types/api.types.js';

// ============================================================================
// Vite Environment Types
// ============================================================================

interface ImportMetaEnv {
  readonly VITE_API_URL?: string;
  readonly DEV?: boolean;
  readonly PROD?: boolean;
  readonly MODE?: string;
}

interface ImportMetaWithEnv {
  readonly env: ImportMetaEnv;
}

// Safe access to import.meta
function getImportMeta(): ImportMetaWithEnv | undefined {
  try {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const meta = (import.meta as any);
    if (meta && typeof meta.env === 'object') {
      return meta as ImportMetaWithEnv;
    }
  } catch {
    // import.meta not available
  }
  return undefined;
}

// ============================================================================
// Configuration
// ============================================================================

const meta = getImportMeta();

/**
 * API base URL from environment or default to '/api/v1'
 */
const BASE_URL: string = meta?.env?.VITE_API_URL ?? '/api/v1';

/**
 * Whether the app is running in development mode
 */
const IS_DEV: boolean = meta?.env?.DEV === true;

/**
 * Maximum number of retry attempts for failed requests
 */
const MAX_RETRIES = 3;

/**
 * Delay between retries in milliseconds
 */
const RETRY_DELAY = 1000;

// ============================================================================
// Event System
// ============================================================================

type EventCallback = (event: ApiEvent) => void;
const eventListeners: Map<ApiEventType, Set<EventCallback>> = new Map();

/**
 * Subscribe to API events (auth errors, permission denied, etc.)
 * @param type - The event type to listen for
 * @param callback - Function to call when event occurs
 * @returns Unsubscribe function
 */
export function onApiEvent(type: ApiEventType, callback: EventCallback): () => void {
  if (!eventListeners.has(type)) {
    eventListeners.set(type, new Set());
  }
  const listeners = eventListeners.get(type);
  if (listeners) {
    listeners.add(callback);
  }
  
  return () => {
    eventListeners.get(type)?.delete(callback);
  };
}

/**
 * Emit an API event to all registered listeners
 * @param event - The event to emit
 */
function emitApiEvent(event: ApiEvent): void {
  const listeners = eventListeners.get(event.type);
  if (listeners) {
    listeners.forEach((callback) => {
      try {
        callback(event);
      } catch (error) {
        console.error('Error in API event listener:', error);
      }
    });
  }
}

// ============================================================================
// CSRF Token Management
// ============================================================================

let csrfToken: string | null = null;

/**
 * Get CSRF token from meta tag or cookie
 * @returns The CSRF token or null if not found
 */
function getCsrfToken(): string | null {
  // First check if we have a cached token
  if (csrfToken) {
    return csrfToken;
  }

  // Try to get from meta tag
  if (typeof document !== 'undefined') {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
      csrfToken = metaTag.getAttribute('content');
      return csrfToken;
    }
  }

  // Try to get from cookie
  if (typeof document !== 'undefined') {
    const cookies = document.cookie.split(';');
    for (const cookie of cookies) {
      const [name, value] = cookie.trim().split('=');
      if (name === 'XSRF-TOKEN' || name === 'csrf_token') {
        csrfToken = decodeURIComponent(value ?? '');
        return csrfToken;
      }
    }
  }

  return null;
}

/**
 * Set the CSRF token (usually after login or refresh)
 * @param token - The new CSRF token
 */
export function setCsrfToken(token: string): void {
  csrfToken = token;
}

/**
 * Clear the cached CSRF token
 */
export function clearCsrfToken(): void {
  csrfToken = null;
}

// ============================================================================
// Axios Instance Creation
// ============================================================================

/**
 * Create and configure the Axios instance
 */
const apiClient: AxiosInstance = axios.create({
  baseURL: BASE_URL,
  withCredentials: true, // Enable cookie-based sessions
  timeout: 30000, // 30 second timeout
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// ============================================================================
// Request Interceptor
// ============================================================================

/**
 * Extended request config with retry tracking
 */
interface RetryableRequestConfig extends InternalAxiosRequestConfig {
  _retryCount?: number;
  url?: string;
}

apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    // Add CSRF token to state-changing requests
    if (config.method && ['post', 'put', 'patch', 'delete'].includes(config.method.toLowerCase())) {
      const token = getCsrfToken();
      if (token) {
        config.headers.set('X-CSRF-Token', token);
      }
    }

    // Development logging
    if (IS_DEV) {
      console.log(`[API Request] ${config.method?.toUpperCase()} ${config.url}`, {
        params: config.params,
        data: config.data,
      });
    }

    return config;
  },
  (error: unknown) => {
    if (IS_DEV) {
      console.error('[API Request Error]', error);
    }
    return Promise.reject(error);
  }
);

// ============================================================================
// Response Interceptor
// ============================================================================

apiClient.interceptors.response.use(
  (response: AxiosResponse) => {
    // Development logging
    if (IS_DEV) {
      console.log(`[API Response] ${response.status} ${response.config.url}`, {
        data: response.data,
      });
    }

    return response;
  },
  async (error: unknown) => {
    // Type guard for AxiosError
    if (!axios.isAxiosError(error)) {
      emitApiEvent({
        type: 'network:error',
        message: 'Unknown error occurred',
        error: {
          success: false,
          message: 'Unknown error',
        },
      });
      return Promise.reject(error);
    }

    const axiosError = error as AxiosError<ApiError>;
    const config = axiosError.config as RetryableRequestConfig | undefined;
    const status = axiosError.response?.status;
    const errorData = axiosError.response?.data;

    // Development logging
    if (IS_DEV) {
      console.error(`[API Error] ${status} ${config?.url ?? 'unknown'}`, {
        error: errorData,
        message: axiosError.message,
      });
    }

    // Handle specific status codes
    switch (status) {
      case 401:
        // Unauthorized - emit auth error event
        emitApiEvent({
          type: 'auth:error',
          message: errorData?.message ?? 'Authentication required',
          status,
          error: errorData ?? undefined,
        });
        break;

      case 403:
        // Forbidden - emit permission denied event
        emitApiEvent({
          type: 'permission:denied',
          message: errorData?.message ?? 'Permission denied',
          status,
          error: errorData ?? undefined,
        });
        break;

      case 419:
        // CSRF token expired - try to refresh and retry
        if (config && (!config._retryCount || config._retryCount < MAX_RETRIES)) {
          config._retryCount = (config._retryCount ?? 0) + 1;
          
          // Clear the old token
          clearCsrfToken();
          
          // Wait before retrying
          await new Promise((resolve) => setTimeout(resolve, RETRY_DELAY));
          
          // Try to get a fresh CSRF token
          try {
            const csrfResponse = await axios.get<{ csrfToken: string }>(`${BASE_URL}/auth/csrf`, {
              withCredentials: true,
            });
            setCsrfToken(csrfResponse.data.csrfToken);
            
            // Retry the original request
            return apiClient.request(config);
          } catch {
            emitApiEvent({
              type: 'csrf:expired',
              message: 'CSRF token expired and refresh failed',
              status,
            });
          }
        }
        break;

      case 422:
        // Validation errors
        emitApiEvent({
          type: 'validation:error',
          message: errorData?.message ?? 'Validation failed',
          status,
          error: errorData ?? undefined,
        });
        break;

      case 500:
      case 502:
      case 503:
      case 504:
        // Server errors
        emitApiEvent({
          type: 'server:error',
          message: errorData?.message ?? 'Server error occurred',
          status,
          error: errorData ?? undefined,
        });
        break;

      default:
        // Network or unknown error
        if (!axiosError.response) {
          emitApiEvent({
            type: 'network:error',
            message: axiosError.message ?? 'Network error occurred',
            error: {
              success: false,
              message: axiosError.message,
            },
          });
        }
    }

    return Promise.reject(axiosError);
  }
);

// ============================================================================
// Type-Safe Request Methods
// ============================================================================

/**
 * Make a GET request
 * @template T - Expected response data type
 * @param url - Request URL (relative to base URL)
 * @param config - Optional request configuration
 * @returns Promise resolving to the response data
 */
export async function get<T>(
  url: string,
  config?: RequestConfig
): Promise<ApiResponse<T>> {
  const response = await apiClient.get<ApiResponse<T>>(url, {
    params: config?.params,
    headers: config?.headers,
    timeout: config?.timeout,
    signal: config?.signal,
  });
  return response.data;
}

/**
 * Make a POST request
 * @template T - Expected response data type
 * @template D - Request body data type
 * @param url - Request URL (relative to base URL)
 * @param data - Request body data
 * @param config - Optional request configuration
 * @returns Promise resolving to the response data
 */
export async function post<T, D = unknown>(
  url: string,
  data?: D,
  config?: RequestConfig
): Promise<ApiResponse<T>> {
  const response = await apiClient.post<ApiResponse<T>>(url, data, {
    params: config?.params,
    headers: config?.headers,
    timeout: config?.timeout,
    signal: config?.signal,
  });
  return response.data;
}

/**
 * Make a PUT request
 * @template T - Expected response data type
 * @template D - Request body data type
 * @param url - Request URL (relative to base URL)
 * @param data - Request body data
 * @param config - Optional request configuration
 * @returns Promise resolving to the response data
 */
export async function put<T, D = unknown>(
  url: string,
  data?: D,
  config?: RequestConfig
): Promise<ApiResponse<T>> {
  const response = await apiClient.put<ApiResponse<T>>(url, data, {
    params: config?.params,
    headers: config?.headers,
    timeout: config?.timeout,
    signal: config?.signal,
  });
  return response.data;
}

/**
 * Make a PATCH request
 * @template T - Expected response data type
 * @template D - Request body data type
 * @param url - Request URL (relative to base URL)
 * @param data - Request body data
 * @param config - Optional request configuration
 * @returns Promise resolving to the response data
 */
export async function patch<T, D = unknown>(
  url: string,
  data?: D,
  config?: RequestConfig
): Promise<ApiResponse<T>> {
  const response = await apiClient.patch<ApiResponse<T>>(url, data, {
    params: config?.params,
    headers: config?.headers,
    timeout: config?.timeout,
    signal: config?.signal,
  });
  return response.data;
}

/**
 * Make a DELETE request
 * @template T - Expected response data type
 * @param url - Request URL (relative to base URL)
 * @param config - Optional request configuration
 * @returns Promise resolving to the response data
 */
export async function del<T = void>(
  url: string,
  config?: RequestConfig
): Promise<ApiResponse<T>> {
  const response = await apiClient.delete<ApiResponse<T>>(url, {
    params: config?.params,
    headers: config?.headers,
    timeout: config?.timeout,
    signal: config?.signal,
  });
  return response.data;
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Check if an error is an API error response
 * @param error - The error to check
 * @returns True if the error is an API error
 */
export function isApiError(error: unknown): error is AxiosError<ApiError> {
  if (!axios.isAxiosError(error)) {
    return false;
  }
  const axiosErr = error as AxiosError<ApiError>;
  const data = axiosErr.response?.data;
  return typeof data === 'object' &&
    data !== null &&
    (data as ApiError).success === false;
}

/**
 * Extract validation errors from an API error response
 * @param error - The axios error
 * @returns Record of field names to error messages, or null
 */
export function getValidationErrors(
  error: unknown
): Record<string, string[]> | null {
  if (isApiError(error)) {
    return error.response?.data?.errors ?? null;
  }
  return null;
}

/**
 * Extract error message from an API error response
 * @param error - The error object
 * @param fallback - Fallback message if error message cannot be extracted
 * @returns The error message
 */
export function getErrorMessage(error: unknown, fallback = 'An error occurred'): string {
  if (isApiError(error)) {
    return error.response?.data?.message ?? fallback;
  }
  if (typeof error === 'object' && error !== null && 'message' in error) {
    return String((error as { message: unknown }).message);
  }
  return fallback;
}

// ============================================================================
// Export the API Client Instance
// ============================================================================

export { apiClient };
export default apiClient;
