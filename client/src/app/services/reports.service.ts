/**
 * Reports Service for SafeShift EHR
 * 
 * Provides API methods for generating and exporting various reports
 * including dashboard, safety, compliance, and custom reports.
 */

import { get, post } from './api.js';
import type { ApiResponse } from '../types/api.types.js';
import type { ReportFilters, ReportData, ExportFormat } from '../types/api.types.js';

// ============================================================================
// Report Functions
// ============================================================================

/**
 * Get dashboard summary report
 * @returns Promise resolving to dashboard report data
 */
export async function getDashboardReport(): Promise<ApiResponse<ReportData>> {
  return get<ReportData>('/reports/dashboard');
}

/**
 * Get safety report
 * @param filters - Optional filters for the report
 * @returns Promise resolving to safety report data
 */
export async function getSafetyReport(filters?: ReportFilters): Promise<ApiResponse<ReportData>> {
  return get<ReportData>('/reports/safety', {
    params: filters as Record<string, string | number | boolean | undefined>,
  });
}

/**
 * Get compliance report
 * @returns Promise resolving to compliance report data
 */
export async function getComplianceReport(): Promise<ApiResponse<ReportData>> {
  return get<ReportData>('/reports/compliance');
}

/**
 * Generate a custom report by type
 * @param type - Report type identifier
 * @param params - Report parameters
 * @returns Promise resolving to report data
 */
export async function generateReport(
  type: string,
  params?: Record<string, string | number | boolean | undefined>
): Promise<ApiResponse<ReportData>> {
  return get<ReportData>(`/reports/${type}`, { params });
}

/**
 * Export a report in the specified format
 * @param type - Report type identifier
 * @param format - Export format (csv, json, pdf)
 * @returns Promise resolving to export URL or blob
 */
export async function exportReport(
  type: string,
  format: ExportFormat = 'csv'
): Promise<ApiResponse<{ url: string; filename: string }>> {
  return get<{ url: string; filename: string }>(`/reports/export/${type}`, {
    params: { format },
  });
}

/**
 * Get DOT testing report
 * @param filters - Optional filters for the report
 * @returns Promise resolving to DOT testing report data
 */
export async function getDotTestingReport(filters?: ReportFilters): Promise<ApiResponse<ReportData>> {
  return get<ReportData>('/reports/dot-testing', {
    params: filters as Record<string, string | number | boolean | undefined>,
  });
}

/**
 * Get OSHA injury report
 * @param year - Year for the report
 * @param establishmentId - Optional establishment ID
 * @returns Promise resolving to OSHA injury report data
 */
export async function getOshaInjuryReport(
  year: number,
  establishmentId?: string
): Promise<ApiResponse<ReportData>> {
  return get<ReportData>('/reports/osha-injuries', {
    params: { year, establishment_id: establishmentId },
  });
}

/**
 * Get encounter summary report
 * @param filters - Optional filters for the report
 * @returns Promise resolving to encounter summary data
 */
export async function getEncounterSummaryReport(filters?: ReportFilters): Promise<ApiResponse<ReportData>> {
  return get<ReportData>('/reports/encounters', {
    params: filters as Record<string, string | number | boolean | undefined>,
  });
}

/**
 * Schedule a recurring report
 * @param reportType - Type of report to schedule
 * @param schedule - Cron-like schedule configuration
 * @param recipients - Email addresses to send the report to
 * @returns Promise resolving to schedule confirmation
 */
export async function scheduleReport(
  reportType: string,
  schedule: { frequency: 'daily' | 'weekly' | 'monthly'; dayOfWeek?: number; dayOfMonth?: number },
  recipients: string[]
): Promise<ApiResponse<{ scheduleId: string }>> {
  return post('/reports/schedule', { reportType, schedule, recipients });
}

// ============================================================================
// Service Object Export
// ============================================================================

/**
 * Reports service object with all report-related API methods
 */
export const reportsService = {
  getDashboardReport,
  getSafetyReport,
  getComplianceReport,
  generateReport,
  exportReport,
  getDotTestingReport,
  getOshaInjuryReport,
  getEncounterSummaryReport,
  scheduleReport,
};

export default reportsService;
