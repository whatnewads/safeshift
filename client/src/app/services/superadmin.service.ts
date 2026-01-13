/**
 * SuperAdmin Service
 *
 * Handles API calls for super admin operations including user management,
 * system configuration, security incidents, and audit logs.
 *
 * @module services/superadmin
 */

import { get, post, put, del } from './api.js';

// ============================================================================
// Types
// ============================================================================

export interface SuperAdminStats {
  activeUsers: number;
  totalUsers: number;
  activeClinics: number;
  dotTestsThisMonth: number;
  systemUptime: number;
  openIncidents: number;
  auditLogsToday: number;
}

export interface Role {
  id: string;
  name: string;
  description: string | null;
}

export interface SystemUser {
  id: string;
  username: string;
  email: string;
  name: string;
  firstName: string | null;
  lastName: string | null;
  roles: string[];
  status: 'active' | 'inactive';
  lastLogin: string | null;
  createdAt: string;
}

export interface UserDetail {
  id: string;
  username: string;
  email: string;
  name: string;
  firstName: string | null;
  lastName: string | null;
  roles: Role[];
  status: 'active' | 'inactive';
  lastLogin: string | null;
  createdAt: string;
}

export interface Clinic {
  id: string;
  name: string;
  address: string;
  city: string;
  state: string;
  zipCode: string;
  phone: string;
  status: 'active' | 'inactive';
  employeeCount: number;
  createdAt: string;
}

export interface AuditLog {
  id: string;
  user: string;
  userId: string;
  action: string;
  resourceType: string;
  resourceId: string;
  ipAddress: string;
  timestamp: string;
  details: string | null;
}

export interface AuditStats {
  totalEvents: number;
  flaggedEvents: number;
  uniqueUsers: number;
  systemsAccessed: number;
}

export interface SecurityIncident {
  id: string;
  type: string;
  severity: 'critical' | 'high' | 'medium' | 'low';
  status: 'open' | 'investigating' | 'resolved';
  description: string;
  reportedBy: string;
  timestamp: string;
  resolvedAt: string | null;
  resolutionNotes: string | null;
}

export interface OverrideRequest {
  id: string;
  type: string;
  entityType: string;
  entityId: string | null;
  requestedBy: string;
  requestedById: string | null;
  reason: string;
  status: 'pending' | 'approved' | 'denied';
  timestamp: string;
  approvedBy: string | null;
  approvedAt: string | null;
  resolutionNotes: string | null;
  patientName: string | null;
}

export interface FullDashboardData {
  systemStats: SuperAdminStats;
  users: Array<{
    id: string;
    name: string;
    email: string;
    role: string;
    status: 'active' | 'inactive';
    lastLogin: string | null;
  }>;
  clinics: Array<{
    id: string;
    name: string;
    location: string;
    status: 'active' | 'inactive';
    userCount: number;
  }>;
  securityIncidents: Array<{
    id: string;
    type: string;
    severity: 'critical' | 'high' | 'medium' | 'low';
    status: string;
    timestamp: string;
  }>;
  overrideRequests: Array<{
    id: string;
    type: string;
    requestedBy: string;
    status: string;
    timestamp: string;
  }>;
}

// ============================================================================
// API Response Types
// ============================================================================

interface SuperAdminDashboardResponse {
  stats: SuperAdminStats;
}

interface UsersResponse {
  users: SystemUser[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

interface UserResponse {
  user: UserDetail;
}

interface RolesResponse {
  roles: Role[];
}

interface ClinicsResponse {
  clinics: Clinic[];
}

interface AuditLogsResponse {
  logs: AuditLog[];
  pagination: {
    page: number;
    per_page: number;
  };
}

interface AuditStatsResponse {
  stats: AuditStats;
}

interface SecurityIncidentsResponse {
  incidents: SecurityIncident[];
}

interface OverrideRequestsResponse {
  overrideRequests: OverrideRequest[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

interface FullDashboardResponse {
  systemStats: SuperAdminStats;
  users: FullDashboardData['users'];
  clinics: FullDashboardData['clinics'];
  securityIncidents: FullDashboardData['securityIncidents'];
  overrideRequests: FullDashboardData['overrideRequests'];
}

// ============================================================================
// Dashboard
// ============================================================================

/**
 * Get super admin dashboard data (stats only)
 */
export async function getSuperAdminDashboard(): Promise<SuperAdminStats> {
  const response = await get<SuperAdminDashboardResponse>('/superadmin');
  return response.data.stats;
}

/**
 * Get full dashboard data including users, clinics, incidents, and override requests
 */
export async function getFullDashboard(): Promise<FullDashboardData> {
  const response = await get<FullDashboardResponse>('/superadmin/dashboard');
  return {
    systemStats: response.data.systemStats,
    users: response.data.users,
    clinics: response.data.clinics,
    securityIncidents: response.data.securityIncidents,
    overrideRequests: response.data.overrideRequests,
  };
}

// ============================================================================
// User Management
// ============================================================================

/**
 * Get all system users
 */
export async function getUsers(params?: {
  page?: number;
  perPage?: number;
}): Promise<{
  users: SystemUser[];
  pagination: UsersResponse['pagination'];
}> {
  const queryParams = new URLSearchParams();
  if (params?.page) queryParams.append('page', params.page.toString());
  if (params?.perPage) queryParams.append('per_page', params.perPage.toString());

  const queryString = queryParams.toString();
  const url = `/superadmin/users${queryString ? `?${queryString}` : ''}`;
  
  const response = await get<UsersResponse>(url);
  return {
    users: response.data.users,
    pagination: response.data.pagination,
  };
}

/**
 * Get a single user by ID
 */
export async function getUser(userId: string): Promise<UserDetail> {
  const response = await get<UserResponse>(`/superadmin/users/${userId}`);
  return response.data.user;
}

/**
 * Create a new user
 */
export async function createUser(userData: {
  username: string;
  email: string;
  firstName?: string;
  lastName?: string;
  password?: string;
  roles?: string[];
}): Promise<{ userId: string }> {
  const response = await post<{ userId: string; message: string }>('/superadmin/users', {
    username: userData.username,
    email: userData.email,
    first_name: userData.firstName,
    last_name: userData.lastName,
    password: userData.password,
    roles: userData.roles,
  });
  return { userId: response.data.userId };
}

/**
 * Update user status (activate/deactivate)
 */
export async function updateUserStatus(userId: string, isActive: boolean): Promise<void> {
  await put(`/superadmin/users/${userId}/status`, { isActive });
}

/**
 * Assign a role to a user
 */
export async function assignRole(userId: string, roleId: string): Promise<void> {
  await post(`/superadmin/users/${userId}/roles`, { roleId });
}

/**
 * Remove a role from a user
 */
export async function removeRole(userId: string, roleId: string): Promise<void> {
  await del(`/superadmin/users/${userId}/roles/${roleId}`);
}

/**
 * Get all available roles
 */
export async function getRoles(): Promise<Role[]> {
  const response = await get<RolesResponse>('/superadmin/roles');
  return response.data.roles;
}

// ============================================================================
// Clinic Management
// ============================================================================

/**
 * Get all clinics
 */
export async function getClinics(): Promise<Clinic[]> {
  const response = await get<ClinicsResponse>('/superadmin/clinics');
  return response.data.clinics;
}

/**
 * Create a new clinic
 */
export async function createClinic(clinicData: {
  name: string;
  address?: string;
  city?: string;
  state?: string;
  zipCode?: string;
  phone?: string;
}): Promise<{ clinicId: string }> {
  const response = await post<{ clinicId: string; message: string }>('/superadmin/clinics', {
    clinic_name: clinicData.name,
    address: clinicData.address,
    city: clinicData.city,
    state: clinicData.state,
    zip_code: clinicData.zipCode,
    phone: clinicData.phone,
  });
  return { clinicId: response.data.clinicId };
}

// ============================================================================
// Audit Logs
// ============================================================================

/**
 * Get audit logs
 */
export async function getAuditLogs(params?: {
  userId?: string;
  action?: string;
  startDate?: string;
  endDate?: string;
  page?: number;
  perPage?: number;
}): Promise<{
  logs: AuditLog[];
}> {
  const queryParams = new URLSearchParams();
  if (params?.userId) queryParams.append('userId', params.userId);
  if (params?.action) queryParams.append('action', params.action);
  if (params?.startDate) queryParams.append('startDate', params.startDate);
  if (params?.endDate) queryParams.append('endDate', params.endDate);
  if (params?.page) queryParams.append('page', params.page.toString());
  if (params?.perPage) queryParams.append('per_page', params.perPage.toString());

  const queryString = queryParams.toString();
  const url = `/superadmin/audit${queryString ? `?${queryString}` : ''}`;
  
  const response = await get<AuditLogsResponse>(url);
  return { logs: response.data.logs };
}

/**
 * Get audit statistics
 */
export async function getAuditStats(date?: string): Promise<AuditStats> {
  const url = `/superadmin/audit/stats${date ? `?date=${date}` : ''}`;
  const response = await get<AuditStatsResponse>(url);
  return response.data.stats;
}

// ============================================================================
// Security Incidents
// ============================================================================

/**
 * Get security incidents
 */
export async function getSecurityIncidents(status?: string): Promise<SecurityIncident[]> {
  const url = `/superadmin/incidents${status ? `?status=${status}` : ''}`;
  const response = await get<SecurityIncidentsResponse>(url);
  return response.data.incidents;
}

/**
 * Create a security incident
 */
export async function createSecurityIncident(incidentData: {
  type: string;
  severity?: string;
  description?: string;
}): Promise<{ incidentId: string }> {
  const response = await post<{ incidentId: string; message: string }>('/superadmin/incidents', {
    incident_type: incidentData.type,
    severity: incidentData.severity,
    description: incidentData.description,
  });
  return { incidentId: response.data.incidentId };
}

/**
 * Resolve a security incident
 */
export async function resolveSecurityIncident(
  incidentId: string,
  resolutionNotes: string
): Promise<void> {
  await put(`/superadmin/incidents/${incidentId}/resolve`, { resolutionNotes });
}

// ============================================================================
// Override Requests
// ============================================================================

/**
 * Get override requests
 */
export async function getOverrideRequests(params?: {
  status?: string;
  page?: number;
  perPage?: number;
}): Promise<{
  overrideRequests: OverrideRequest[];
  pagination: OverrideRequestsResponse['pagination'];
}> {
  const queryParams = new URLSearchParams();
  if (params?.status) queryParams.append('status', params.status);
  if (params?.page) queryParams.append('page', params.page.toString());
  if (params?.perPage) queryParams.append('per_page', params.perPage.toString());

  const queryString = queryParams.toString();
  const url = `/superadmin/overrides${queryString ? `?${queryString}` : ''}`;
  
  const response = await get<OverrideRequestsResponse>(url);
  return {
    overrideRequests: response.data.overrideRequests,
    pagination: response.data.pagination,
  };
}

/**
 * Approve an override request
 */
export async function approveOverrideRequest(
  requestId: string,
  notes?: string
): Promise<void> {
  await put(`/superadmin/overrides/${requestId}/approve`, { notes: notes || '' });
}

/**
 * Deny an override request
 */
export async function denyOverrideRequest(
  requestId: string,
  reason?: string
): Promise<void> {
  await put(`/superadmin/overrides/${requestId}/deny`, { reason: reason || '' });
}

// ============================================================================
// Export Default
// ============================================================================

export default {
  getSuperAdminDashboard,
  getFullDashboard,
  getUsers,
  getUser,
  createUser,
  updateUserStatus,
  assignRole,
  removeRole,
  getRoles,
  getClinics,
  createClinic,
  getAuditLogs,
  getAuditStats,
  getSecurityIncidents,
  createSecurityIncident,
  resolveSecurityIncident,
  getOverrideRequests,
  approveOverrideRequest,
  denyOverrideRequest,
};
