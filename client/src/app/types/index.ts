// Core types for Occupational Health EHR

export type UserRole = 
  | 'provider'
  | 'registration'
  | 'admin'
  | 'super-admin';

export interface User {
  id: string;
  name: string;
  email: string;
  roles: UserRole[];
  currentRole: UserRole;
  deviceId?: string;
  trustedDevice: boolean;
  avatar?: string;
}

export interface Patient {
  id: string;
  firstName: string;
  lastName: string;
  dateOfBirth: string;
  employer?: string;
  employerId?: string;
  employerName?: string;
  jobSite?: string;
  contractor?: string;
  supervisor?: string;
  ssn?: string;
  gender?: 'male' | 'female' | 'other' | 'unknown';
  email?: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  zipCode?: string;
  emergencyContactName?: string;
  emergencyContactPhone?: string;
  active?: boolean;
  lastVisit?: string;
  createdAt?: string;
  updatedAt?: string;
}

export interface Encounter {
  id: string;
  patientId: string;
  patientName: string;
  type: 'drug-test' | 'fit-test' | 'physical-exam' | 'pre-employment' | 'other';
  reportType: 'personal-medical' | 'work-related' | 'unknown';
  status: 'draft' | 'in-progress' | 'submitted' | 'signed';
  createdAt: string;
  updatedAt: string;
  createdBy: string;
  oshaRecordable?: boolean;
  workRestrictions?: string;
}

export interface Case {
  id: string;
  patientName: string;
  type: string;
  status: 'open' | 'follow-up-due' | 'closed' | 'high-risk';
  lastActivity: string;
  assignedTo: string;
  oshaStatus?: 'pending' | 'submitted' | 'responded' | 'not-applicable';
}

export interface AuditLogEntry {
  id: string;
  timestamp: string;
  userId: string;
  userName: string;
  action: string;
  resourceType: string;
  resourceId: string;
  deviceInfo: string;
  ipAddress: string;
  metadata: Record<string, any>;
  flagged?: boolean;
}

export interface TrainingModule {
  id: string;
  title: string;
  category: 'privacy' | 'security' | 'hipaa' | 'osha' | 'clinical';
  status: 'draft' | 'published' | 'archived';
  version: number;
  assignedTo: string[];
  completionRate: number;
  createdAt: string;
  updatedBy: string;
}

export interface QueueItem {
  id: string;
  type: 'encounter' | 'patient' | 'signature';
  action: string;
  data: any;
  timestamp: string;
  retryCount: number;
  error?: string;
}

export interface SyncStatus {
  isOnline: boolean;
  lastSync?: string;
  queuedItems: number;
  syncing: boolean;
  errors: string[];
}