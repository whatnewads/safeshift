/**
 * Document Service for SafeShift EHR
 *
 * Handles document/photo upload related API calls for encounters.
 */

import { apiClient } from './api.js';

// ============================================================================
// Constants
// ============================================================================

/**
 * Allowed file types for document uploads
 */
export const ALLOWED_FILE_TYPES = [
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/gif',
  'image/webp',
  'application/pdf',
];

/**
 * Maximum file size in bytes (10MB)
 */
export const MAX_FILE_SIZE = 10 * 1024 * 1024;

// ============================================================================
// Types
// ============================================================================

/**
 * Document type options
 */
export type DocumentType = 'appointment_card' | 'referral' | 'prescription' | 'other';

/**
 * Request payload for uploading a document
 */
export interface DocumentUploadRequest {
  file: File;
  encounterId: number;
  documentType?: DocumentType;
  notes?: string;
}

/**
 * Response from document upload operation
 */
export interface DocumentUploadResponse {
  success: boolean;
  data?: {
    document_id: number;
    encounter_id: number;
    file_name: string;
    original_name: string;
    file_type: string;
    file_size: number;
    document_type: DocumentType;
    uploaded_at: string;
  };
  message?: string;
  error?: string;
}

/**
 * Document record from the database
 */
export interface DocumentRecord {
  id: number;
  encounter_id: number;
  appointment_id: number | null;
  file_name: string;
  original_name: string;
  file_path: string;
  file_type: string;
  file_size: number;
  document_type: DocumentType;
  notes: string | null;
  uploaded_at: string;
  uploaded_by: number | null;
  is_deleted: boolean;
}

// ============================================================================
// API Functions
// ============================================================================

/**
 * Upload a document/photo for an encounter
 *
 * @param data - The document upload request data
 * @returns Promise resolving to the upload response
 * @throws Error if the upload fails
 *
 * @example
 * ```typescript
 * const fileInput = document.querySelector('input[type="file"]');
 * const file = fileInput.files[0];
 *
 * try {
 *   const result = await uploadDocument({
 *     file,
 *     encounterId: 123,
 *     documentType: 'appointment_card',
 *     notes: 'Follow-up appointment card'
 *   });
 *   console.log('Document uploaded:', result.data?.document_id);
 * } catch (error) {
 *   console.error('Failed to upload document:', error);
 * }
 * ```
 */
export async function uploadDocument(
  data: DocumentUploadRequest
): Promise<DocumentUploadResponse> {
  // Create FormData for file upload
  const formData = new FormData();
  formData.append('file', data.file);
  formData.append('encounter_id', String(data.encounterId));
  
  if (data.documentType) {
    formData.append('document_type', data.documentType);
  }
  
  if (data.notes) {
    formData.append('notes', data.notes);
  }

  // Use axios directly for multipart/form-data
  const response = await apiClient.post<DocumentUploadResponse>(
    '/encounters/upload-document',
    formData,
    {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    }
  );

  return response.data;
}

/**
 * Upload an appointment document (alias for uploadDocument)
 *
 * @param file - The file to upload
 * @param encounterId - The encounter ID
 * @param documentType - The document type
 * @param notes - Optional notes
 * @returns Promise resolving to the upload response
 */
export async function uploadAppointmentDocument(
  file: File,
  encounterId: number,
  documentType: DocumentType = 'appointment_card',
  notes?: string
): Promise<{ success: boolean; document?: any; error?: string }> {
  try {
    const request: DocumentUploadRequest = {
      file,
      encounterId,
      documentType,
    };
    
    if (notes !== undefined) {
      request.notes = notes;
    }
    
    const response = await uploadDocument(request);
    
    const result: { success: boolean; document?: any; error?: string } = {
      success: response.success,
    };
    
    if (response.data) {
      result.document = response.data;
    }
    
    if (response.error) {
      result.error = response.error;
    }
    
    return result;
  } catch (error: any) {
    return {
      success: false,
      error: error.message || 'Failed to upload document',
    };
  }
}

/**
 * Validate file type for upload
 *
 * @param file - The file to validate
 * @returns Boolean indicating if the file type is allowed
 */
export function isValidFileType(file: File): boolean {
  return ALLOWED_FILE_TYPES.includes(file.type);
}

/**
 * Validate file type for upload (alias for isValidFileType)
 *
 * @param file - The file to validate
 * @returns Boolean indicating if the file type is allowed
 */
export function validateFileType(file: File): boolean {
  return isValidFileType(file);
}

/**
 * Validate file size for upload
 *
 * @param file - The file to validate
 * @param maxSizeMB - Maximum file size in MB (default: 10)
 * @returns Boolean indicating if the file size is within limits
 */
export function isValidFileSize(file: File, maxSizeMB: number = 10): boolean {
  const maxSizeBytes = maxSizeMB * 1024 * 1024;
  return file.size <= maxSizeBytes;
}

/**
 * Validate file size for upload (alias for isValidFileSize)
 *
 * @param file - The file to validate
 * @returns Boolean indicating if the file size is within MAX_FILE_SIZE
 */
export function validateFileSize(file: File): boolean {
  return file.size <= MAX_FILE_SIZE;
}

/**
 * Get human-readable file size
 *
 * @param bytes - File size in bytes
 * @returns Formatted file size string
 */
export function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 Bytes';
  
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Get file extension from filename
 *
 * @param filename - The filename
 * @returns File extension without dot, or empty string
 */
export function getFileExtension(filename: string): string {
  const parts = filename.split('.');
  return parts.length > 1 ? parts.pop()?.toLowerCase() || '' : '';
}

/**
 * Check if file is an image
 *
 * @param file - The file to check
 * @returns Boolean indicating if the file is an image
 */
export function isImageFile(file: File): boolean {
  return file.type.startsWith('image/');
}

/**
 * Check if file is a PDF
 *
 * @param file - The file to check
 * @returns Boolean indicating if the file is a PDF
 */
export function isPdfFile(file: File): boolean {
  return file.type === 'application/pdf';
}

/**
 * Create a preview URL for an image file
 *
 * @param file - The image file
 * @returns Object URL for preview (remember to revoke when done)
 */
export function createImagePreview(file: File): string | null {
  if (!isImageFile(file)) return null;
  return URL.createObjectURL(file);
}

/**
 * Revoke a preview URL to free memory
 *
 * @param url - The object URL to revoke
 */
export function revokeImagePreview(url: string): void {
  URL.revokeObjectURL(url);
}

// ============================================================================
// Export all functions
// ============================================================================

export default {
  // Constants
  ALLOWED_FILE_TYPES,
  MAX_FILE_SIZE,
  // Upload functions
  uploadDocument,
  uploadAppointmentDocument,
  // Validation functions
  isValidFileType,
  validateFileType,
  isValidFileSize,
  validateFileSize,
  // Utility functions
  formatFileSize,
  getFileExtension,
  isImageFile,
  isPdfFile,
  createImagePreview,
  revokeImagePreview,
};
