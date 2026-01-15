/**
 * API Type Definitions for SafeShift EHR
 *
 * This file contains all TypeScript types for API requests and responses
 * used throughout the React frontend to communicate with the PHP backend.
 */

import type { User, Encounter } from './index.js';
import type { UIRole, Permission } from '../utils/roleMapper.js';

// ============================================================================
// Generic API Response Types
// ============================================================================

/**
 * Standard API response wrapper for all successful responses
 * @template T - The type of data contained in the response
 */
export interface ApiResponse<T> {
  /** Indicates if the request was successful */
  success: boolean;
  /** Human-readable message about the response */
  message: string;
  /** The actual response data */
  data: T;
  /** ISO 8601 timestamp of when the response was generated */
  timestamp: string;
}

/**
 * Standard API error response
 */
export interface ApiError {
  /** Always false for error responses */
  success: false;
  /** Human-readable error message */
  message: string;
  /** Validation errors keyed by field name */
  errors?: Record<string, string[]>;
  /** Error code for programmatic handling */
  code?: string;
  /** ISO 8601 timestamp of when the error occurred */
  timestamp?: string;
}

/**
 * Paginated response wrapper for list endpoints
 * @template T - The type of items in the data array
 */
export interface PaginatedResponse<T> {
  /** Array of items for the current page */
  data: T[];
  /** Total number of items across all pages */
  total: number;
  /** Current page number (1-indexed) */
  page: number;
  /** Number of items per page */
  limit: number;
  /** Total number of pages */
  totalPages?: number;
  /** Whether there are more pages */
  hasMore?: boolean;
}

// ============================================================================
// Authentication Types
// ============================================================================

/**
 * Login request payload
 */
export interface LoginRequest {
  /** User's username or email */
  username: string;
  /** User's password */
  password: string;
  /** Whether to remember this device */
  rememberDevice?: boolean;
}

/**
 * Login response - may require 2FA or return complete auth
 */
export interface LoginResponse {
  /** Authentication stage: 'otp' if 2FA required, 'complete' if fully authenticated */
  stage: 'otp' | 'complete';
  /** User data (only present when stage is 'complete') */
  user?: User;
  /** CSRF token for subsequent requests (only present when stage is 'complete') */
  csrfToken?: string;
  /** OTP delivery method hint (only present when stage is 'otp') */
  otpMethod?: 'email' | 'sms' | 'authenticator';
  /** Masked email/phone where OTP was sent (only present when stage is 'otp') */
  otpDestination?: string;
}

/**
 * 2FA verification request
 */
export interface Verify2FARequest {
  /** The 6-digit OTP code */
  code: string;
  /** Whether to trust this device for future logins */
  trustDevice?: boolean;
}

/**
 * Full authentication response after successful login or 2FA
 */
export interface AuthResponse {
  /** Authenticated user data */
  user: User;
  /** CSRF token for subsequent requests */
  csrfToken: string;
  /** Session expiration timestamp */
  expiresAt?: string;
}

/**
 * Session status response
 */
export interface SessionResponse {
  /** Whether the session is valid */
  valid: boolean;
  /** Whether the user is fully authenticated (completed 2FA if required) */
  authenticated?: boolean;
  /** Current authentication stage: 'idle', 'otp', or 'authenticated' */
  stage?: 'idle' | 'otp' | 'authenticated';
  /** Current user if session is valid */
  user?: User | { username?: string; email?: string };
  /** CSRF token */
  csrfToken?: string;
  /** Session expiration timestamp */
  expiresAt?: string;
  /** Session timing information */
  session?: {
    /** When the session was started */
    logged_in_at: string;
    /** When the session expires */
    expires_at: string;
    /** Seconds remaining until expiration */
    remaining_seconds: number;
    /** Whether session is about to expire (within 5 minutes) */
    is_expiring: boolean;
  };
}

// ============================================================================
// Role and Permission Types
// ============================================================================

/**
 * User role information with both backend and frontend mappings
 * Returned from the backend to provide complete role context
 */
export interface UserRole {
  /** Backend database role (e.g., 'pclinician', 'Admin') */
  role: string;
  /** Frontend UI role for routing (e.g., 'provider', 'super-admin') */
  uiRole: UIRole;
  /** Human-readable display name (e.g., 'Clinical Provider') */
  displayRole: string;
  /** Array of permission strings for access control */
  permissions: Permission[];
  /** Default dashboard route for this role */
  dashboardRoute: string;
}

/**
 * Primary role object structure (for backwards compatibility)
 */
export interface PrimaryRoleInfo {
  /** Role ID */
  id: string;
  /** Role name */
  name: string;
  /** Role slug (used for mapping) */
  slug: string;
}

/**
 * Authenticated user data returned from the backend
 * Includes complete role mapping information for frontend flexibility
 */
export interface AuthenticatedUser {
  /** Unique user identifier */
  id: string;
  /** Username for login */
  username: string;
  /** User's email address */
  email: string;
  /** User's first name */
  firstName: string;
  /** User's last name */
  lastName: string;
  /** Backend database role (e.g., 'pclinician', 'cadmin') */
  role: string;
  /** Frontend UI role for routing (e.g., 'provider', 'admin') */
  uiRole: UIRole;
  /** Human-readable role display name */
  displayRole: string;
  /** Array of permission strings */
  permissions: Permission[];
  /** Default dashboard route for this role */
  dashboardRoute: string;
  /** Primary role object (for backwards compatibility) */
  primary_role?: PrimaryRoleInfo;
  /** All assigned roles */
  roles?: PrimaryRoleInfo[];
  /** Associated clinic ID */
  clinicId?: string;
  /** Associated clinic name */
  clinicName?: string;
  /** Whether 2FA is enabled */
  twoFactorEnabled: boolean;
  /** Last login timestamp */
  lastLogin?: string;
  /** Current session login timestamp */
  logged_in_at?: string;
}

/**
 * Extended session status response with role information
 */
export interface AuthenticatedSessionResponse {
  /** Whether the user is authenticated */
  authenticated: boolean;
  /** Current user with full role information */
  user?: AuthenticatedUser;
  /** Session timing information */
  session?: {
    /** When the session was started */
    logged_in_at: string;
    /** When the session expires */
    expires_at: string;
    /** Seconds remaining until expiration */
    remaining_seconds: number;
    /** Whether session is about to expire (within 5 minutes) */
    is_expiring: boolean;
  };
}

// ============================================================================
// Patient Types
// ============================================================================

/**
 * Patient filter options for list queries
 */
export interface PatientFilters {
  /** Search query (searches name, MRN, SSN last 4) */
  search?: string;
  /** Filter by employer ID */
  employerId?: string;
  /** Filter by job site */
  jobSite?: string;
  /** Filter by active/inactive status */
  active?: boolean;
  /** Page number (1-indexed) */
  page?: number;
  /** Items per page */
  limit?: number;
  /** Sort field */
  sortBy?: 'name' | 'dateOfBirth' | 'createdAt' | 'lastVisit';
  /** Sort direction */
  sortOrder?: 'asc' | 'desc';
}

/**
 * Data transfer object for creating a new patient
 */
export interface CreatePatientDTO {
  /** Patient's first name */
  firstName: string;
  /** Patient's last name */
  lastName: string;
  /** Date of birth in ISO 8601 format (YYYY-MM-DD) */
  dateOfBirth: string;
  /** Social Security Number (will be encrypted) */
  ssn?: string;
  /** Gender */
  gender?: 'male' | 'female' | 'other' | 'unknown';
  /** Email address */
  email?: string;
  /** Phone number */
  phone?: string;
  /** Street address */
  address?: string;
  /** City */
  city?: string;
  /** State */
  state?: string;
  /** ZIP code */
  zipCode?: string;
  /** Employer ID */
  employerId?: string;
  /** Job site */
  jobSite?: string;
  /** Contractor name */
  contractor?: string;
  /** Supervisor name */
  supervisor?: string;
  /** Emergency contact name */
  emergencyContactName?: string;
  /** Emergency contact phone */
  emergencyContactPhone?: string;
}

/**
 * Data transfer object for updating a patient
 */
export interface UpdatePatientDTO extends Partial<CreatePatientDTO> {
  /** Whether the patient is active */
  active?: boolean;
}

// ============================================================================
// Encounter Types
// ============================================================================

/**
 * Encounter filter options for list queries
 */
export interface EncounterFilters {
  /** Filter by patient ID */
  patientId?: string;
  /** Filter by encounter type */
  type?: Encounter['type'];
  /** Filter by encounter status */
  status?: Encounter['status'];
  /** Filter by provider ID */
  providerId?: string;
  /** Filter by date range start (ISO 8601) */
  dateFrom?: string;
  /** Filter by date range end (ISO 8601) */
  dateTo?: string;
  /** Filter by OSHA recordable status */
  oshaRecordable?: boolean;
  /** Page number (1-indexed) */
  page?: number;
  /** Items per page */
  limit?: number;
  /** Sort field */
  sortBy?: 'createdAt' | 'updatedAt' | 'patientName';
  /** Sort direction */
  sortOrder?: 'asc' | 'desc';
}

/**
 * Data transfer object for creating a new encounter
 */
export interface CreateEncounterDTO {
  /** Patient ID */
  patientId: string;
  /** Encounter type */
  type: Encounter['type'];
  /** Report type classification */
  reportType: Encounter['reportType'];
  /** Chief complaint or reason for visit */
  chiefComplaint?: string;
  /** Initial notes */
  notes?: string;
  /** Employer ID associated with this encounter */
  employerId?: string;
  /** Job site where injury/incident occurred */
  jobSite?: string;
}

/**
 * Data transfer object for updating an encounter
 */
export interface UpdateEncounterDTO {
  /** Updated encounter status */
  status?: Encounter['status'];
  /** Report type classification */
  reportType?: Encounter['reportType'];
  /** Chief complaint or reason for visit */
  chiefComplaint?: string;
  /** Clinical notes */
  notes?: string;
  /** Whether this is OSHA recordable */
  oshaRecordable?: boolean;
  /** Work restrictions */
  workRestrictions?: string;
  /** Assessment findings */
  assessment?: string;
  /** Treatment plan */
  plan?: string;
  /** Follow-up instructions */
  followUp?: string;
}

/**
 * Vitals measurement data
 */
export interface Vitals {
  /** Unique identifier */
  id: string;
  /** Associated encounter ID */
  encounterId: string;
  /** Timestamp when vitals were recorded */
  recordedAt: string;
  /** ID of user who recorded vitals */
  recordedBy: string;
  /** Temperature in Fahrenheit */
  temperature?: number;
  /** Heart rate in BPM */
  heartRate?: number;
  /** Respiratory rate per minute */
  respiratoryRate?: number;
  /** Systolic blood pressure in mmHg */
  bloodPressureSystolic?: number;
  /** Diastolic blood pressure in mmHg */
  bloodPressureDiastolic?: number;
  /** Oxygen saturation percentage */
  oxygenSaturation?: number;
  /** Pain level (0-10 scale) */
  painLevel?: number;
  /** Weight in pounds */
  weight?: number;
  /** Height in inches */
  height?: number;
  /** Blood glucose in mg/dL */
  bloodGlucose?: number;
  /** Additional notes */
  notes?: string;
}

/**
 * Data transfer object for recording vitals
 */
export interface VitalsDTO {
  /** Temperature in Fahrenheit */
  temperature?: number;
  /** Heart rate in BPM */
  heartRate?: number;
  /** Respiratory rate per minute */
  respiratoryRate?: number;
  /** Systolic blood pressure in mmHg */
  bloodPressureSystolic?: number;
  /** Diastolic blood pressure in mmHg */
  bloodPressureDiastolic?: number;
  /** Oxygen saturation percentage */
  oxygenSaturation?: number;
  /** Pain level (0-10 scale) */
  painLevel?: number;
  /** Weight in pounds */
  weight?: number;
  /** Height in inches */
  height?: number;
  /** Blood glucose in mg/dL */
  bloodGlucose?: number;
  /** Additional notes */
  notes?: string;
}

/**
 * Data transfer object for encounter amendments
 */
export interface AmendmentDTO {
  /** Reason for the amendment */
  reason: string;
  /** Fields being amended with new values */
  changes: Record<string, unknown>;
  /** Amendment notes */
  notes?: string;
}

// ============================================================================
// Event Types for Error Handling
// ============================================================================

/**
 * Custom event types for API error handling
 */
export type ApiEventType = 
  | 'auth:error'
  | 'auth:expired'
  | 'permission:denied'
  | 'csrf:expired'
  | 'validation:error'
  | 'server:error'
  | 'network:error';

/**
 * API event payload structure
 */
export interface ApiEvent {
  /** Event type identifier */
  type: ApiEventType;
  /** Error message */
  message: string;
  /** HTTP status code if applicable */
  status?: number;
  /** Original error object */
  error?: ApiError;
  /** Additional context */
  context?: Record<string, unknown>;
}

// ============================================================================
// Utility Types
// ============================================================================

/**
 * HTTP methods supported by the API
 */
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/**
 * Request configuration options
 */
export interface RequestConfig {
  /** Request headers */
  headers?: Record<string, string>;
  /** URL query parameters */
  params?: Record<string, string | number | boolean | undefined>;
  /** Request timeout in milliseconds */
  timeout?: number;
  /** Whether to include credentials (cookies) */
  withCredentials?: boolean;
  /** Abort signal for request cancellation */
  signal?: AbortSignal;
}

// ============================================================================
// DOT Testing Types
// ============================================================================

/**
 * DOT test type classifications
 */
export type DotTestType =
  | 'pre-employment'
  | 'random'
  | 'post-accident'
  | 'reasonable-suspicion'
  | 'return-to-duty'
  | 'follow-up';

/**
 * DOT test status values
 */
export type DotTestStatus =
  | 'pending'
  | 'collected'
  | 'in-transit'
  | 'lab-processing'
  | 'results-received'
  | 'mro-review'
  | 'complete'
  | 'cancelled';

/**
 * DOT drug/alcohol test record
 */
export interface DotTest {
  /** Unique identifier */
  id: string;
  /** Associated patient ID */
  patientId: string;
  /** Patient name (denormalized for display) */
  patientName?: string;
  /** Type of DOT test */
  testType: DotTestType;
  /** Current status of the test */
  status: DotTestStatus;
  /** Reason for the test (e.g., specific suspicion details) */
  reason?: string;
  /** CCF (Custody and Control Form) data */
  ccfData?: {
    formNumber?: string;
    collectorId?: string;
    collectorName?: string;
    collectionSite?: string;
    collectionDate?: string;
    specimenType?: 'urine' | 'oral-fluid' | 'hair';
    remarks?: string;
  };
  /** Lab results data */
  results?: DotTestResults;
  /** MRO verification data */
  mroVerification?: MroVerification;
  /** Employer ID */
  employerId?: string;
  /** Employer name */
  employerName?: string;
  /** Consortium/TPA information */
  consortiumId?: string;
  /** Created timestamp */
  createdAt: string;
  /** Last updated timestamp */
  updatedAt?: string;
  /** Completed timestamp */
  completedAt?: string;
  /** ID of user who created the test */
  createdBy?: string;
}

/**
 * DOT test results from laboratory
 */
export interface DotTestResults {
  /** Lab reference number */
  labReferenceNumber?: string;
  /** Lab name */
  labName?: string;
  /** Date results received */
  resultDate?: string;
  /** Overall result status */
  result: 'negative' | 'positive' | 'cancelled' | 'rejected' | 'dilute-negative' | 'dilute-retest';
  /** Specific substance results */
  substances?: Array<{
    name: string;
    result: 'negative' | 'positive';
    cutoff?: string;
    level?: string;
  }>;
  /** Alcohol test result (if applicable) */
  alcoholResult?: {
    screeningResult?: number;
    confirmationResult?: number;
  };
  /** Additional notes */
  notes?: string;
}

/**
 * MRO (Medical Review Officer) verification
 */
export interface MroVerification {
  /** MRO ID */
  mroId?: string;
  /** MRO name */
  mroName?: string;
  /** Verification date */
  verificationDate?: string;
  /** Final determination */
  determination: 'negative' | 'positive' | 'cancelled' | 'test-not-performed';
  /** Was donor interview conducted */
  donorInterviewConducted?: boolean;
  /** Medical explanation provided */
  medicalExplanation?: string;
  /** Notes/comments */
  notes?: string;
}

/**
 * DOT test filter options
 */
export interface DotTestFilters {
  /** Filter by patient ID */
  patientId?: string;
  /** Filter by test type */
  testType?: DotTestType;
  /** Filter by status */
  status?: DotTestStatus;
  /** Filter by employer ID */
  employerId?: string;
  /** Filter by date range start */
  dateFrom?: string;
  /** Filter by date range end */
  dateTo?: string;
  /** Page number */
  page?: number;
  /** Items per page */
  limit?: number;
  /** Sort field */
  sortBy?: 'createdAt' | 'status' | 'patientName';
  /** Sort direction */
  sortOrder?: 'asc' | 'desc';
}

/**
 * DTO for creating a new DOT test
 */
export interface CreateDotTestDTO {
  /** Patient ID */
  patientId: string;
  /** Type of test */
  testType: DotTestType;
  /** Reason for test */
  reason?: string;
  /** Employer ID */
  employerId?: string;
  /** Consortium ID */
  consortiumId?: string;
}

// ============================================================================
// OSHA Recordkeeping Types
// ============================================================================

/**
 * OSHA case classification
 */
export type OshaCaseClassification =
  | 'death'
  | 'days-away'
  | 'restricted-work'
  | 'other-recordable';

/**
 * OSHA recordable injury/illness
 */
export interface OshaInjury {
  /** Unique identifier */
  id: string;
  /** Employee ID (internal reference) */
  employeeId: string;
  /** Employee name */
  employeeName: string;
  /** Employee job title */
  jobTitle?: string;
  /** Date of injury/illness */
  incidentDate: string;
  /** Where the event occurred */
  location?: string;
  /** Description of injury or illness */
  description: string;
  /** Type of injury */
  injuryType: string;
  /** Body part affected */
  bodyPart: string;
  /** Case classification */
  caseClassification: OshaCaseClassification;
  /** Days away from work */
  daysAway?: number;
  /** Days on restricted/transferred work */
  daysRestricted?: number;
  /** Whether injury resulted from privacy concern case */
  privacyCase?: boolean;
  /** Object/substance that directly injured the employee */
  objectSubstance?: string;
  /** Establishment ID */
  establishmentId: string;
  /** Establishment name */
  establishmentName?: string;
  /** Case number (OSHA Form 300 case number) */
  caseNumber?: string;
  /** Created timestamp */
  createdAt: string;
  /** Updated timestamp */
  updatedAt?: string;
  /** Created by user ID */
  createdBy?: string;
}

/**
 * OSHA 300/300A Log data
 */
export interface OshaLog {
  /** Year of the log */
  year: number;
  /** Establishment ID */
  establishmentId: string;
  /** Establishment name */
  establishmentName?: string;
  /** Individual injury entries */
  entries: OshaInjury[];
  /** Log totals/summary */
  totals: {
    /** Total number of recordable cases */
    totalCases: number;
    /** Number of deaths */
    deaths: number;
    /** Cases with days away from work */
    daysAwayCases: number;
    /** Cases with job transfer or restriction */
    restrictedWorkCases: number;
    /** Other recordable cases */
    otherRecordableCases: number;
    /** Total days away from work */
    totalDaysAway: number;
    /** Total days of restriction/transfer */
    totalDaysRestricted: number;
    /** Injury counts by type */
    injuryTypes?: Record<string, number>;
    /** Illness counts by type */
    illnessTypes?: Record<string, number>;
  };
  /** 300A summary specific fields */
  summary300A?: {
    /** Annual average number of employees */
    averageEmployees: number;
    /** Total hours worked by all employees */
    totalHoursWorked: number;
    /** Establishment NAICS code */
    naicsCode?: string;
    /** Executive signature */
    signedBy?: string;
    /** Signature date */
    signedDate?: string;
  };
}

/**
 * OSHA incident rates
 */
export interface OshaRates {
  /** Total Recordable Incident Rate */
  trir: number;
  /** Days Away, Restricted, or Transferred rate */
  dart: number;
  /** Lost Time Incident Rate */
  ltir: number;
  /** Severity Rate */
  severity: number;
  /** Total hours worked (used in calculations) */
  hoursWorked: number;
  /** Number of employees */
  employees?: number;
  /** Industry average TRIR for comparison */
  industryAvgTrir?: number;
  /** Industry average DART for comparison */
  industryAvgDart?: number;
}

/**
 * OSHA injury filter options
 */
export interface OshaFilters {
  /** Filter by establishment ID */
  establishmentId?: string;
  /** Filter by year */
  year?: number;
  /** Filter by case classification */
  caseClassification?: OshaCaseClassification;
  /** Filter by date range start */
  dateFrom?: string;
  /** Filter by date range end */
  dateTo?: string;
  /** Search by employee name or description */
  search?: string;
  /** Page number */
  page?: number;
  /** Items per page */
  limit?: number;
  /** Sort field */
  sortBy?: 'incidentDate' | 'employeeName' | 'caseClassification';
  /** Sort direction */
  sortOrder?: 'asc' | 'desc';
}

/**
 * DTO for creating a new OSHA injury record
 */
export interface CreateInjuryDTO {
  /** Employee ID */
  employeeId: string;
  /** Employee name */
  employeeName: string;
  /** Job title */
  jobTitle?: string;
  /** Date of injury/illness */
  incidentDate: string;
  /** Location of incident */
  location?: string;
  /** Description */
  description: string;
  /** Injury type */
  injuryType: string;
  /** Body part affected */
  bodyPart: string;
  /** Case classification */
  caseClassification: OshaCaseClassification;
  /** Days away from work */
  daysAway?: number;
  /** Days restricted/transferred */
  daysRestricted?: number;
  /** Privacy case flag */
  privacyCase?: boolean;
  /** Object/substance involved */
  objectSubstance?: string;
  /** Establishment ID */
  establishmentId: string;
}

/**
 * DTO for updating an OSHA injury record
 */
export interface UpdateInjuryDTO extends Partial<CreateInjuryDTO> {
  /** Update days away (for ongoing tracking) */
  daysAway?: number;
  /** Update days restricted */
  daysRestricted?: number;
}

// ============================================================================
// Dashboard Types
// ============================================================================

/**
 * Complete dashboard data structure
 */
export interface DashboardData {
  /** Dashboard statistics */
  stats: DashboardStats;
  /** Active alerts */
  alerts: Alert[];
  /** Recent activity feed */
  recentActivity: Activity[];
  /** Chart data for visualizations */
  charts?: ChartData[];
}

/**
 * Dashboard statistics
 */
export interface DashboardStats {
  /** Total patient count */
  patientCount: number;
  /** Total encounter count */
  encounterCount: number;
  /** Today's encounters */
  todayEncounters: number;
  /** Encounters pending review */
  pendingReviews: number;
  /** DOT tests pending/in-progress */
  dotTestsPending: number;
  /** Overall compliance score (0-100) */
  complianceScore: number;
  /** Active OSHA cases */
  oshaActiveCases?: number;
  /** Open injuries this month */
  monthlyInjuries?: number;
  /** Users online */
  usersOnline?: number;
}

/**
 * Alert/notification structure
 */
export interface Alert {
  /** Unique identifier */
  id: string;
  /** Alert type/severity */
  type: 'warning' | 'error' | 'info' | 'success';
  /** Alert title */
  title: string;
  /** Alert message/description */
  message: string;
  /** When the alert was created */
  createdAt: string;
  /** Whether the alert has been read */
  read: boolean;
  /** Link to related resource */
  link?: string;
  /** Alert category */
  category?: 'compliance' | 'clinical' | 'system' | 'osha' | 'dot';
}

/**
 * Activity feed item
 */
export interface Activity {
  /** Unique identifier */
  id: string;
  /** Activity type (e.g., 'encounter:created', 'patient:updated') */
  type: string;
  /** Human-readable description */
  description: string;
  /** User who performed the activity */
  userId: string;
  /** User name (denormalized) */
  userName: string;
  /** When the activity occurred */
  createdAt: string;
  /** Related entity ID */
  entityId?: string;
  /** Related entity type */
  entityType?: 'patient' | 'encounter' | 'dot-test' | 'osha-injury';
  /** Additional metadata */
  metadata?: Record<string, unknown>;
}

/**
 * Chart data structure for dashboard visualizations
 */
export interface ChartData {
  /** Chart identifier */
  id: string;
  /** Chart type */
  type: 'line' | 'bar' | 'pie' | 'doughnut' | 'area';
  /** Chart title */
  title: string;
  /** Data labels */
  labels: string[];
  /** Data series */
  datasets: Array<{
    label: string;
    data: number[];
    backgroundColor?: string | string[];
    borderColor?: string;
  }>;
}

// ============================================================================
// Report Types
// ============================================================================

/**
 * Report filter options
 */
export interface ReportFilters {
  /** Start date for report period */
  dateFrom?: string;
  /** End date for report period */
  dateTo?: string;
  /** Filter by establishment/clinic */
  establishmentId?: string;
  /** Filter by department */
  departmentId?: string;
  /** Filter by provider */
  providerId?: string;
  /** Group results by */
  groupBy?: 'day' | 'week' | 'month' | 'quarter' | 'year';
  /** Include comparison period */
  includeComparison?: boolean;
}

/**
 * Generic report data structure
 */
export interface ReportData {
  /** Report title */
  title: string;
  /** Report description */
  description?: string;
  /** Report generation timestamp */
  generatedAt: string;
  /** Report period start */
  periodStart: string;
  /** Report period end */
  periodEnd: string;
  /** Summary statistics */
  summary: Record<string, number | string>;
  /** Detailed data rows */
  data: Array<Record<string, unknown>>;
  /** Chart data for visualizations */
  charts?: ChartData[];
  /** Comparison data (if requested) */
  comparison?: {
    periodStart: string;
    periodEnd: string;
    summary: Record<string, number | string>;
    percentChange: Record<string, number>;
  };
}

/**
 * Export format options
 */
export type ExportFormat = 'csv' | 'json' | 'pdf' | 'xlsx';
