/**
 * SMS Service for SafeShift EHR
 *
 * Handles SMS-related API calls including appointment reminders.
 */

import { post } from './api.js';

// ============================================================================
// Types
// ============================================================================

/**
 * Request payload for sending SMS reminder
 */
export interface SMSReminderRequest {
  patientId: number;
  encounterId?: number;
  phoneNumber: string;
  appointmentDate: string;
  appointmentTime: string;
  clinicName: string;
}

/**
 * Response from SMS send operation
 */
export interface SMSReminderResponse {
  success: boolean;
  message_id?: string;
  sms_log_id?: number;
  error?: string;
}

/**
 * SMS log entry for audit/history
 */
export interface SMSLogEntry {
  id: number;
  patient_id: number;
  encounter_id: number | null;
  phone_number: string;
  message_content: string;
  message_type: 'appointment_reminder' | 'follow_up' | 'general';
  status: 'pending' | 'sent' | 'delivered' | 'failed';
  provider: string;
  provider_message_id: string | null;
  error_message: string | null;
  sent_at: string | null;
  created_at: string;
  created_by: number | null;
}

// ============================================================================
// API Functions
// ============================================================================

/**
 * Send an SMS appointment reminder to a patient
 *
 * @param data - The SMS reminder request data
 * @returns Promise resolving to the SMS response
 * @throws Error if the request fails
 *
 * @example
 * ```typescript
 * try {
 *   const result = await sendSMSReminder({
 *     patientId: 123,
 *     encounterId: 456,
 *     phoneNumber: '+15551234567',
 *     appointmentDate: '2024-01-15',
 *     appointmentTime: '14:30',
 *     clinicName: 'SafeShift Medical Center'
 *   });
 *   console.log('SMS sent:', result.message_id);
 * } catch (error) {
 *   console.error('Failed to send SMS:', error);
 * }
 * ```
 */
export async function sendSMSReminder(
  data: SMSReminderRequest
): Promise<SMSReminderResponse> {
  // Transform camelCase to snake_case for PHP API
  const payload = {
    patient_id: data.patientId,
    encounter_id: data.encounterId,
    phone_number: data.phoneNumber,
    appointment_date: data.appointmentDate,
    appointment_time: data.appointmentTime,
    clinic_name: data.clinicName,
  };

  const response = await post<SMSReminderResponse>('/sms/send-reminder', payload);

  // The API returns success in the data object
  if (response.success && response.data) {
    return response.data;
  }

  // Handle error response
  throw new Error(response.message || 'Failed to send SMS reminder');
}

/**
 * Validate a phone number format
 * Returns a normalized phone number or null if invalid
 *
 * @param phoneNumber - The phone number to validate
 * @returns Normalized phone number or null
 */
export function validatePhoneNumber(phoneNumber: string): string | null {
  if (!phoneNumber) return null;

  // Remove all non-digit characters except +
  const cleaned = phoneNumber.replace(/[^\d+]/g, '');

  // Check for valid formats
  // US: 10 digits, or +1 followed by 10 digits
  // International: + followed by 11-15 digits

  if (/^[2-9]\d{9}$/.test(cleaned)) {
    // US number without country code
    return '+1' + cleaned;
  }

  if (/^\+1[2-9]\d{9}$/.test(cleaned)) {
    // US number with country code
    return cleaned;
  }

  if (/^\+\d{11,15}$/.test(cleaned)) {
    // International number
    return cleaned;
  }

  // Also accept numbers starting with 1 (US country code without +)
  if (/^1[2-9]\d{9}$/.test(cleaned)) {
    return '+' + cleaned;
  }

  return null;
}

/**
 * Format a phone number for display
 *
 * @param phoneNumber - The phone number to format
 * @returns Formatted phone number string
 */
export function formatPhoneNumber(phoneNumber: string): string {
  if (!phoneNumber) return '';

  // Remove all non-digit characters
  const digits = phoneNumber.replace(/\D/g, '');

  // Format US numbers
  if (digits.length === 10) {
    return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
  }

  if (digits.length === 11 && digits.startsWith('1')) {
    return `+1 (${digits.slice(1, 4)}) ${digits.slice(4, 7)}-${digits.slice(7)}`;
  }

  // Return original if not standard format
  return phoneNumber;
}

/**
 * Check if SMS feature is available for a patient
 * (has valid phone number on file)
 *
 * @param phoneNumber - Patient's phone number
 * @returns Boolean indicating if SMS can be sent
 */
export function canSendSMS(phoneNumber: string | undefined | null): boolean {
  if (!phoneNumber) return false;
  return validatePhoneNumber(phoneNumber) !== null;
}

// ============================================================================
// Export all functions
// ============================================================================

export default {
  sendSMSReminder,
  validatePhoneNumber,
  formatPhoneNumber,
  canSendSMS,
};
