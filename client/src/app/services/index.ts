/**
 * Service Layer Index for SafeShift EHR
 *
 * This file exports all API services and utilities from a single entry point.
 * Import from this file for cleaner imports throughout the application.
 *
 * @example
 * ```typescript
 * import { authService, patientService, encounterService } from '@/app/services';
 * // or
 * import { login, getPatients, getEncounter } from '@/app/services';
 * ```
 */

// ============================================================================
// Core API Client
// ============================================================================

export {
  // HTTP Methods
  get,
  post,
  put,
  patch,
  del,
  // CSRF Token Management
  setCsrfToken,
  clearCsrfToken,
  // Event System
  onApiEvent,
  // Error Utilities
  isApiError,
  getValidationErrors,
  getErrorMessage,
  // API Client Instance
  apiClient,
} from './api.js';

// ============================================================================
// Authentication Service
// ============================================================================

export {
  // Individual Functions
  login,
  verify2FA,
  resendOtp,
  logout,
  getCurrentUser,
  getCsrfToken,
  refreshSession,
  getSessionStatus,
  // Service Object
  authService,
} from './auth.service.js';

// ============================================================================
// Patient Service
// ============================================================================

export {
  // Individual Functions
  getPatients,
  getPatient,
  createPatient,
  updatePatient,
  deletePatient,
  searchPatients,
  getPatientEncounters,
  // Service Object
  patientService,
} from './patient.service.js';

// ============================================================================
// Encounter Service
// ============================================================================

export {
  // Individual Functions
  getEncounters,
  getEncounter,
  createEncounter,
  updateEncounter,
  getEncounterVitals,
  recordVitals,
  amendEncounter,
  signEncounter,
  submitEncounter,
  // Service Object
  encounterService,
} from './encounter.service.js';

// ============================================================================
// Narrative Service
// ============================================================================

export {
  // Individual Functions
  generateNarrative,
  // Service Object
  narrativeService,
  // Types
  type NarrativeResponse,
} from './narrative.service.js';

// ============================================================================
// Dashboard Service
// ============================================================================

export {
  // Individual Functions
  getDashboard,
  getManagerDashboard,
  getDashboardStats,
  getCases,
  getCase,
  addCaseFlag,
  resolveFlag,
  getClinicalProviderDashboard,
  getTechnicianDashboard,
  getRegistrationDashboard,
  // Service Object
  dashboardService,
  // Types
  type ManagerStats,
  type Case,
  type CaseFlag,
  type ManagerDashboardData,
  type ClinicalProviderStats,
  type TechnicianStats,
  type RegistrationStats,
} from './dashboard.service.js';

// ============================================================================
// DOT Testing Service
// ============================================================================

export {
  // Individual Functions
  getDotTests,
  getDotTest,
  initiateDotTest,
  updateCcf,
  submitDotResults,
  mroVerify,
  getDotTestsByStatus,
  cancelDotTest,
  deleteDotTest,
  getDotTestStats,
  // Service Object
  dotService,
} from './dot.service.js';

// ============================================================================
// OSHA Service
// ============================================================================

export {
  // Individual Functions
  getOshaInjuries,
  getOshaInjury,
  recordOshaInjury,
  updateOshaInjury,
  deleteOshaInjury,
  get300Log,
  get300ALog,
  getOshaRates,
  submitToIta,
  getItaSubmissions,
  // Service Object
  oshaService,
} from './osha.service.js';

// ============================================================================
// Reports Service
// ============================================================================

export {
  // Individual Functions
  getDashboardReport,
  getSafetyReport,
  getComplianceReport,
  generateReport,
  exportReport,
  getDotTestingReport,
  getOshaInjuryReport,
  getEncounterSummaryReport,
  scheduleReport,
  // Service Object
  reportsService,
} from './reports.service.js';

// ============================================================================
// Notification Service
// ============================================================================

export {
  // Individual Functions
  getNotifications,
  getNotification,
  getUnreadCounts,
  markAsRead,
  markAsUnread,
  markAllAsRead,
  deleteNotification,
  deleteAllNotifications,
  // Service Object
  notificationService,
  // Types
  type Notification,
  type NotificationType,
  type NotificationPriority,
  type NotificationFilters,
  type UnreadCounts,
  type NotificationsResponse,
  type UnreadCountResponse,
  type NotificationActionResponse,
  type MarkAllReadResponse,
  type DeleteAllResponse,
} from './notification.service.js';

// ============================================================================
// Disclosure Service
// ============================================================================

export {
  // Individual Functions
  getDisclosureTemplates,
  getDisclosureTemplateByType,
  getEncounterDisclosures,
  recordDisclosureAcknowledgment,
  recordBatchDisclosureAcknowledgments,
  filterApplicableDisclosures,
  areAllDisclosuresAcknowledged,
  prepareDisclosureRequests,
  // Service Object
  default as disclosureService,
  // Types
  type DisclosureTemplate,
  type DisclosureAcknowledgment,
  type RecordDisclosureRequest,
  type BatchDisclosureRequest,
  type RecordDisclosureResponse,
  type BatchDisclosureResponse,
} from './disclosure.service.js';

// ============================================================================
// Video Signaling Service (WebRTC)
// ============================================================================

export {
  // Class
  VideoSignalingService,
  // Singleton Instance
  videoSignalingService,
  // Types
  type PeerInfo,
} from './video-signaling.service.js';

// ============================================================================
// WebRTC Service
// ============================================================================

export {
  // Class
  WebRTCService,
  // Singleton Instance
  webrtcService,
  // Types
  type ConnectionStats,
  type MediaDeviceInfo,
  type MediaConstraints,
  type QualityPreset,
} from './webrtc.service.js';

// ============================================================================
// Re-export Types
// ============================================================================

export type {
  // API Response Types
  ApiResponse,
  ApiError,
  PaginatedResponse,
  ApiEvent,
  ApiEventType,
  RequestConfig,
  HttpMethod,
  // Auth Types
  LoginRequest,
  LoginResponse,
  AuthResponse,
  SessionResponse,
  Verify2FARequest,
  // Patient Types
  PatientFilters,
  CreatePatientDTO,
  UpdatePatientDTO,
  // Encounter Types
  EncounterFilters,
  CreateEncounterDTO,
  UpdateEncounterDTO,
  Vitals,
  VitalsDTO,
  AmendmentDTO,
  // DOT Testing Types
  DotTest,
  DotTestType,
  DotTestStatus,
  DotTestFilters,
  CreateDotTestDTO,
  DotTestResults,
  MroVerification,
  // OSHA Types
  OshaInjury,
  OshaLog,
  OshaRates,
  OshaFilters,
  OshaCaseClassification,
  CreateInjuryDTO,
  UpdateInjuryDTO,
  // Dashboard Types
  DashboardData,
  DashboardStats,
  Alert,
  Activity,
  ChartData,
  // Report Types
  ReportFilters,
  ReportData,
  ExportFormat,
} from '../types/api.types.js';
