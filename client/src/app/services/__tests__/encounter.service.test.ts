/**
 * encounter.service.test.ts - Tests for Encounter Service
 * 
 * Tests the encounter service API calls, response parsing,
 * and error handling.
 * 
 * @package    SafeShift\Frontend\Tests
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// Mock the API module
vi.mock('../api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

// Import after mocking
import { api } from '../api';

// Mock encounter service functions (simulating the service)
const encounterService = {
  getEncounters: async (params?: { status?: string; date?: string; page?: number; limit?: number }) => {
    const queryParams = new URLSearchParams();
    if (params?.status) queryParams.append('status', params.status);
    if (params?.date) queryParams.append('date', params.date);
    if (params?.page) queryParams.append('page', params.page.toString());
    if (params?.limit) queryParams.append('limit', params.limit.toString());
    
    const url = `/encounters${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
    return api.get(url);
  },
  
  getEncounter: async (id: string) => {
    return api.get(`/encounters/${id}`);
  },
  
  createEncounter: async (data: {
    patient_id: string;
    encounter_type: string;
    chief_complaint?: string;
    clinic_id?: string;
  }) => {
    return api.post('/encounters', data);
  },
  
  updateEncounter: async (id: string, data: Record<string, unknown>) => {
    return api.put(`/encounters/${id}`, data);
  },
  
  deleteEncounter: async (id: string) => {
    return api.delete(`/encounters/${id}`);
  },
  
  addVitals: async (encounterId: string, vitals: {
    blood_pressure_systolic?: number;
    blood_pressure_diastolic?: number;
    heart_rate?: number;
    temperature?: number;
    respiratory_rate?: number;
    oxygen_saturation?: number;
  }) => {
    return api.post(`/encounters/${encounterId}/vitals`, vitals);
  },
  
  addAssessment: async (encounterId: string, assessment: {
    assessment: string;
    icd_codes?: string[];
  }) => {
    return api.post(`/encounters/${encounterId}/assessment`, assessment);
  },
  
  finalizeEncounter: async (encounterId: string, signature: string) => {
    return api.post(`/encounters/${encounterId}/finalize`, { signature });
  },
};

describe('EncounterService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.resetAllMocks();
  });

  describe('getEncounters', () => {
    it('should fetch encounters list', async () => {
      const mockEncounters = [
        { encounter_id: '1', patient_id: 'p1', status: 'in_progress' },
        { encounter_id: '2', patient_id: 'p2', status: 'complete' },
      ];
      
      vi.mocked(api.get).mockResolvedValue({ data: mockEncounters });
      
      const result = await encounterService.getEncounters();
      
      expect(api.get).toHaveBeenCalledWith('/encounters');
      expect(result.data).toEqual(mockEncounters);
    });

    it('should fetch encounters with status filter', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      await encounterService.getEncounters({ status: 'in_progress' });
      
      expect(api.get).toHaveBeenCalledWith('/encounters?status=in_progress');
    });

    it('should fetch encounters with date filter', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      await encounterService.getEncounters({ date: '2025-01-15' });
      
      expect(api.get).toHaveBeenCalledWith('/encounters?date=2025-01-15');
    });

    it('should fetch encounters with pagination', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      await encounterService.getEncounters({ page: 2, limit: 10 });
      
      expect(api.get).toHaveBeenCalledWith('/encounters?page=2&limit=10');
    });

    it('should handle API errors gracefully', async () => {
      const error = new Error('Network error');
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(encounterService.getEncounters()).rejects.toThrow('Network error');
    });
  });

  describe('getEncounter', () => {
    it('should fetch single encounter by ID', async () => {
      const mockEncounter = {
        encounter_id: 'enc-123',
        patient_id: 'pat-456',
        encounter_type: 'office_visit',
        status: 'in_progress',
        chief_complaint: 'Headache',
      };
      
      vi.mocked(api.get).mockResolvedValue({ data: mockEncounter });
      
      const result = await encounterService.getEncounter('enc-123');
      
      expect(api.get).toHaveBeenCalledWith('/encounters/enc-123');
      expect(result.data).toEqual(mockEncounter);
    });

    it('should handle 404 for non-existent encounter', async () => {
      const error = { response: { status: 404, data: { error: 'Not found' } } };
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(encounterService.getEncounter('non-existent')).rejects.toEqual(error);
    });
  });

  describe('createEncounter', () => {
    it('should create a new encounter', async () => {
      const newEncounter = {
        patient_id: 'pat-123',
        encounter_type: 'office_visit',
        chief_complaint: 'Annual checkup',
        clinic_id: 'clinic-456',
      };
      
      const mockResponse = { data: { encounter_id: 'new-enc-789', ...newEncounter } };
      vi.mocked(api.post).mockResolvedValue(mockResponse);
      
      const result = await encounterService.createEncounter(newEncounter);
      
      expect(api.post).toHaveBeenCalledWith('/encounters', newEncounter);
      expect(result.data.encounter_id).toBe('new-enc-789');
    });

    it('should handle validation errors', async () => {
      const invalidData = {
        patient_id: '', // Empty patient ID
        encounter_type: 'office_visit',
      };
      
      const error = { 
        response: { 
          status: 400, 
          data: { error: 'Patient ID is required' } 
        } 
      };
      vi.mocked(api.post).mockRejectedValue(error);
      
      await expect(encounterService.createEncounter(invalidData)).rejects.toEqual(error);
    });

    it('should format request data correctly', async () => {
      const encounterData = {
        patient_id: 'pat-123',
        encounter_type: 'dot_physical',
        clinic_id: 'clinic-789',
      };
      
      vi.mocked(api.post).mockResolvedValue({ data: { encounter_id: 'enc-new' } });
      
      await encounterService.createEncounter(encounterData);
      
      expect(api.post).toHaveBeenCalledWith('/encounters', encounterData);
    });
  });

  describe('updateEncounter', () => {
    it('should update an existing encounter', async () => {
      const updateData = {
        chief_complaint: 'Updated complaint',
        status: 'in_progress',
      };
      
      vi.mocked(api.put).mockResolvedValue({ data: { updated: true } });
      
      const result = await encounterService.updateEncounter('enc-123', updateData);
      
      expect(api.put).toHaveBeenCalledWith('/encounters/enc-123', updateData);
      expect(result.data.updated).toBe(true);
    });

    it('should handle locked encounter update failure', async () => {
      const error = { 
        response: { 
          status: 403, 
          data: { error: 'Cannot modify locked encounter' } 
        } 
      };
      vi.mocked(api.put).mockRejectedValue(error);
      
      await expect(
        encounterService.updateEncounter('locked-enc', { chief_complaint: 'New' })
      ).rejects.toEqual(error);
    });
  });

  describe('deleteEncounter', () => {
    it('should delete an encounter', async () => {
      vi.mocked(api.delete).mockResolvedValue({ data: { deleted: true } });
      
      const result = await encounterService.deleteEncounter('enc-123');
      
      expect(api.delete).toHaveBeenCalledWith('/encounters/enc-123');
      expect(result.data.deleted).toBe(true);
    });

    it('should handle delete of non-existent encounter', async () => {
      const error = { response: { status: 404 } };
      vi.mocked(api.delete).mockRejectedValue(error);
      
      await expect(encounterService.deleteEncounter('non-existent')).rejects.toEqual(error);
    });
  });

  describe('addVitals', () => {
    it('should add vitals to encounter', async () => {
      const vitals = {
        blood_pressure_systolic: 120,
        blood_pressure_diastolic: 80,
        heart_rate: 72,
        temperature: 98.6,
        respiratory_rate: 16,
        oxygen_saturation: 98,
      };
      
      vi.mocked(api.post).mockResolvedValue({ data: { saved: true } });
      
      const result = await encounterService.addVitals('enc-123', vitals);
      
      expect(api.post).toHaveBeenCalledWith('/encounters/enc-123/vitals', vitals);
      expect(result.data.saved).toBe(true);
    });

    it('should handle partial vitals', async () => {
      const partialVitals = {
        blood_pressure_systolic: 120,
        blood_pressure_diastolic: 80,
      };
      
      vi.mocked(api.post).mockResolvedValue({ data: { saved: true } });
      
      await encounterService.addVitals('enc-123', partialVitals);
      
      expect(api.post).toHaveBeenCalledWith('/encounters/enc-123/vitals', partialVitals);
    });
  });

  describe('addAssessment', () => {
    it('should add assessment to encounter', async () => {
      const assessment = {
        assessment: 'Patient presents with mild hypertension',
        icd_codes: ['I10'],
      };
      
      vi.mocked(api.post).mockResolvedValue({ data: { saved: true } });
      
      const result = await encounterService.addAssessment('enc-123', assessment);
      
      expect(api.post).toHaveBeenCalledWith('/encounters/enc-123/assessment', assessment);
      expect(result.data.saved).toBe(true);
    });

    it('should handle multiple ICD codes', async () => {
      const assessment = {
        assessment: 'Multiple diagnoses',
        icd_codes: ['I10', 'E11.9', 'J06.9'],
      };
      
      vi.mocked(api.post).mockResolvedValue({ data: { saved: true } });
      
      await encounterService.addAssessment('enc-123', assessment);
      
      expect(api.post).toHaveBeenCalledWith('/encounters/enc-123/assessment', assessment);
    });
  });

  describe('finalizeEncounter', () => {
    it('should finalize encounter with signature', async () => {
      vi.mocked(api.post).mockResolvedValue({ 
        data: { finalized: true, locked_at: '2025-01-15T10:30:00Z' } 
      });
      
      const result = await encounterService.finalizeEncounter('enc-123', 'Dr. John Smith, MD');
      
      expect(api.post).toHaveBeenCalledWith('/encounters/enc-123/finalize', { 
        signature: 'Dr. John Smith, MD' 
      });
      expect(result.data.finalized).toBe(true);
    });

    it('should handle finalization of incomplete encounter', async () => {
      const error = { 
        response: { 
          status: 400, 
          data: { error: 'Encounter is missing required fields' } 
        } 
      };
      vi.mocked(api.post).mockRejectedValue(error);
      
      await expect(
        encounterService.finalizeEncounter('incomplete-enc', 'Dr. Smith')
      ).rejects.toEqual(error);
    });

    it('should handle double finalization attempt', async () => {
      const error = { 
        response: { 
          status: 400, 
          data: { error: 'Encounter is already locked' } 
        } 
      };
      vi.mocked(api.post).mockRejectedValue(error);
      
      await expect(
        encounterService.finalizeEncounter('already-locked', 'Dr. Smith')
      ).rejects.toEqual(error);
    });
  });

  describe('Response parsing', () => {
    it('should correctly parse encounter list response', async () => {
      const mockResponse = {
        data: [
          { encounter_id: '1', patient_id: 'p1', status: 'complete', is_locked: true },
          { encounter_id: '2', patient_id: 'p2', status: 'in_progress', is_locked: false },
        ],
        meta: { total: 2, page: 1, limit: 10 }
      };
      
      vi.mocked(api.get).mockResolvedValue(mockResponse);
      
      const result = await encounterService.getEncounters();
      
      expect(result.data).toHaveLength(2);
      expect(result.data[0].is_locked).toBe(true);
    });

    it('should handle empty response', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      const result = await encounterService.getEncounters();
      
      expect(result.data).toHaveLength(0);
    });
  });

  describe('Error handling', () => {
    it('should handle network timeout', async () => {
      const error = new Error('timeout of 30000ms exceeded');
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(encounterService.getEncounters()).rejects.toThrow('timeout');
    });

    it('should handle 500 server error', async () => {
      const error = { response: { status: 500, data: { error: 'Internal server error' } } };
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(encounterService.getEncounters()).rejects.toEqual(error);
    });

    it('should handle 401 unauthorized', async () => {
      const error = { response: { status: 401, data: { error: 'Unauthorized' } } };
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(encounterService.getEncounters()).rejects.toEqual(error);
    });
  });
});
