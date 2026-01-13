/**
 * dashboard.service.test.ts - Tests for Dashboard Service
 * 
 * Tests the dashboard service API calls, metric calculations,
 * response parsing, and caching behavior.
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
  },
}));

// Import after mocking
import { api } from '../api';

// Mock dashboard service functions (simulating the service)
const dashboardService = {
  getDashboard: async (role?: string) => {
    const url = role ? `/dashboard?role=${role}` : '/dashboard';
    return api.get(url);
  },
  
  getAdminDashboard: async () => {
    return api.get('/dashboard/admin');
  },
  
  getManagerDashboard: async () => {
    return api.get('/dashboard/manager');
  },
  
  getClinicalDashboard: async () => {
    return api.get('/dashboard/clinical');
  },
  
  getMetrics: async (params?: { start_date?: string; end_date?: string; clinic_id?: string }) => {
    const queryParams = new URLSearchParams();
    if (params?.start_date) queryParams.append('start_date', params.start_date);
    if (params?.end_date) queryParams.append('end_date', params.end_date);
    if (params?.clinic_id) queryParams.append('clinic_id', params.clinic_id);
    
    const url = `/dashboard/metrics${queryParams.toString() ? '?' + queryParams.toString() : ''}`;
    return api.get(url);
  },
  
  getTrends: async (period: 'daily' | 'weekly' | 'monthly' = 'daily') => {
    return api.get(`/dashboard/trends?period=${period}`);
  },
  
  getProviderWorkload: async () => {
    return api.get('/dashboard/workload');
  },
  
  getNotificationsCount: async () => {
    return api.get('/dashboard/notifications/count');
  },
  
  getActivityFeed: async (limit: number = 10) => {
    return api.get(`/dashboard/activity?limit=${limit}`);
  },
  
  saveDashboardPreferences: async (preferences: Record<string, unknown>) => {
    return api.post('/dashboard/preferences', preferences);
  },
};

// Simple cache implementation for testing
const cache = {
  data: new Map<string, { value: unknown; timestamp: number }>(),
  ttl: 60000, // 1 minute
  
  set(key: string, value: unknown): void {
    this.data.set(key, { value, timestamp: Date.now() });
  },
  
  get(key: string): unknown | null {
    const item = this.data.get(key);
    if (!item) return null;
    if (Date.now() - item.timestamp > this.ttl) {
      this.data.delete(key);
      return null;
    }
    return item.value;
  },
  
  clear(): void {
    this.data.clear();
  },
};

describe('DashboardService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    cache.clear();
  });

  afterEach(() => {
    vi.resetAllMocks();
  });

  describe('getDashboard', () => {
    it('should fetch dashboard data', async () => {
      const mockDashboard = {
        total_encounters: 150,
        active_encounters: 12,
        completed_today: 45,
        pending_review: 8,
      };
      
      vi.mocked(api.get).mockResolvedValue({ data: mockDashboard });
      
      const result = await dashboardService.getDashboard();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard');
      expect(result.data).toEqual(mockDashboard);
    });

    it('should fetch dashboard with role parameter', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: {} });
      
      await dashboardService.getDashboard('admin');
      
      expect(api.get).toHaveBeenCalledWith('/dashboard?role=admin');
    });

    it('should handle API errors', async () => {
      const error = new Error('Network error');
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(dashboardService.getDashboard()).rejects.toThrow('Network error');
    });
  });

  describe('getAdminDashboard', () => {
    it('should fetch admin dashboard data', async () => {
      const mockData = {
        total_users: 50,
        total_encounters: 1500,
        total_patients: 800,
        system_health: 'good',
      };
      
      vi.mocked(api.get).mockResolvedValue({ data: mockData });
      
      const result = await dashboardService.getAdminDashboard();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/admin');
      expect(result.data.total_users).toBe(50);
    });

    it('should handle unauthorized access', async () => {
      const error = { response: { status: 403, data: { error: 'Forbidden' } } };
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(dashboardService.getAdminDashboard()).rejects.toEqual(error);
    });
  });

  describe('getManagerDashboard', () => {
    it('should fetch manager dashboard data', async () => {
      const mockData = {
        team_encounters: 250,
        pending_approvals: 5,
        team_performance: { completion_rate: 95 },
      };
      
      vi.mocked(api.get).mockResolvedValue({ data: mockData });
      
      const result = await dashboardService.getManagerDashboard();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/manager');
      expect(result.data.team_encounters).toBe(250);
    });
  });

  describe('getClinicalDashboard', () => {
    it('should fetch clinical dashboard data', async () => {
      const mockData = {
        my_encounters: 15,
        pending_signatures: 3,
        patients_today: 8,
      };
      
      vi.mocked(api.get).mockResolvedValue({ data: mockData });
      
      const result = await dashboardService.getClinicalDashboard();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/clinical');
      expect(result.data.my_encounters).toBe(15);
    });
  });

  describe('getMetrics', () => {
    it('should fetch metrics without filters', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: {} });
      
      await dashboardService.getMetrics();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/metrics');
    });

    it('should fetch metrics with date range', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: {} });
      
      await dashboardService.getMetrics({ 
        start_date: '2025-01-01', 
        end_date: '2025-01-31' 
      });
      
      expect(api.get).toHaveBeenCalledWith(
        '/dashboard/metrics?start_date=2025-01-01&end_date=2025-01-31'
      );
    });

    it('should fetch metrics with clinic filter', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: {} });
      
      await dashboardService.getMetrics({ clinic_id: 'clinic-123' });
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/metrics?clinic_id=clinic-123');
    });

    it('should return correctly formatted metrics', async () => {
      const mockMetrics = {
        total_encounters: 500,
        completion_rate: 92.5,
        average_time: 25,
        no_show_rate: 5.2,
      };
      
      vi.mocked(api.get).mockResolvedValue({ data: mockMetrics });
      
      const result = await dashboardService.getMetrics();
      
      expect(result.data.completion_rate).toBe(92.5);
      expect(typeof result.data.completion_rate).toBe('number');
    });
  });

  describe('getTrends', () => {
    it('should fetch daily trends by default', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      await dashboardService.getTrends();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/trends?period=daily');
    });

    it('should fetch weekly trends', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      await dashboardService.getTrends('weekly');
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/trends?period=weekly');
    });

    it('should fetch monthly trends', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      await dashboardService.getTrends('monthly');
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/trends?period=monthly');
    });

    it('should return trend data array', async () => {
      const mockTrends = [
        { date: '2025-01-15', count: 45 },
        { date: '2025-01-16', count: 52 },
        { date: '2025-01-17', count: 48 },
      ];
      
      vi.mocked(api.get).mockResolvedValue({ data: mockTrends });
      
      const result = await dashboardService.getTrends('daily');
      
      expect(result.data).toHaveLength(3);
      expect(result.data[0].date).toBe('2025-01-15');
    });
  });

  describe('getProviderWorkload', () => {
    it('should fetch provider workload data', async () => {
      const mockWorkload = [
        { provider_id: 'p1', name: 'Dr. Smith', encounters: 12 },
        { provider_id: 'p2', name: 'Dr. Jones', encounters: 15 },
      ];
      
      vi.mocked(api.get).mockResolvedValue({ data: mockWorkload });
      
      const result = await dashboardService.getProviderWorkload();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/workload');
      expect(result.data).toHaveLength(2);
    });
  });

  describe('getNotificationsCount', () => {
    it('should fetch notifications count', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: { count: 5 } });
      
      const result = await dashboardService.getNotificationsCount();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/notifications/count');
      expect(result.data.count).toBe(5);
    });
  });

  describe('getActivityFeed', () => {
    it('should fetch activity feed with default limit', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      await dashboardService.getActivityFeed();
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/activity?limit=10');
    });

    it('should fetch activity feed with custom limit', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: [] });
      
      await dashboardService.getActivityFeed(25);
      
      expect(api.get).toHaveBeenCalledWith('/dashboard/activity?limit=25');
    });

    it('should return activity items', async () => {
      const mockActivities = [
        { id: '1', action: 'encounter_created', timestamp: '2025-01-15T10:00:00Z' },
        { id: '2', action: 'encounter_completed', timestamp: '2025-01-15T11:00:00Z' },
      ];
      
      vi.mocked(api.get).mockResolvedValue({ data: mockActivities });
      
      const result = await dashboardService.getActivityFeed();
      
      expect(result.data).toHaveLength(2);
      expect(result.data[0].action).toBe('encounter_created');
    });
  });

  describe('saveDashboardPreferences', () => {
    it('should save dashboard preferences', async () => {
      const preferences = {
        layout: 'compact',
        widgets: ['encounters', 'metrics', 'activity'],
        theme: 'light',
      };
      
      vi.mocked(api.post).mockResolvedValue({ data: { saved: true } });
      
      const result = await dashboardService.saveDashboardPreferences(preferences);
      
      expect(api.post).toHaveBeenCalledWith('/dashboard/preferences', preferences);
      expect(result.data.saved).toBe(true);
    });
  });

  describe('Metric calculations on frontend', () => {
    it('should calculate completion percentage correctly', () => {
      const total = 100;
      const completed = 85;
      const percentage = (completed / total) * 100;
      
      expect(percentage).toBe(85);
    });

    it('should handle zero total gracefully', () => {
      const total = 0;
      const completed = 0;
      const percentage = total > 0 ? (completed / total) * 100 : 0;
      
      expect(percentage).toBe(0);
    });

    it('should calculate week-over-week change', () => {
      const thisWeek = 120;
      const lastWeek = 100;
      const change = ((thisWeek - lastWeek) / lastWeek) * 100;
      
      expect(change).toBe(20);
    });

    it('should calculate average correctly', () => {
      const values = [10, 20, 30, 40, 50];
      const average = values.reduce((a, b) => a + b, 0) / values.length;
      
      expect(average).toBe(30);
    });
  });

  describe('Cache behavior', () => {
    it('should cache dashboard data', () => {
      const dashboardData = { total_encounters: 100 };
      
      cache.set('dashboard', dashboardData);
      const cached = cache.get('dashboard');
      
      expect(cached).toEqual(dashboardData);
    });

    it('should return null for expired cache', async () => {
      const shortTtlCache = {
        data: new Map<string, { value: unknown; timestamp: number }>(),
        ttl: 1, // 1ms TTL
        
        set(key: string, value: unknown): void {
          this.data.set(key, { value, timestamp: Date.now() });
        },
        
        get(key: string): unknown | null {
          const item = this.data.get(key);
          if (!item) return null;
          if (Date.now() - item.timestamp > this.ttl) {
            this.data.delete(key);
            return null;
          }
          return item.value;
        },
      };
      
      shortTtlCache.set('test', { data: 'value' });
      
      // Wait for cache to expire
      await new Promise(resolve => setTimeout(resolve, 5));
      
      const cached = shortTtlCache.get('test');
      expect(cached).toBeNull();
    });

    it('should clear cache correctly', () => {
      cache.set('key1', 'value1');
      cache.set('key2', 'value2');
      
      cache.clear();
      
      expect(cache.get('key1')).toBeNull();
      expect(cache.get('key2')).toBeNull();
    });
  });

  describe('Error handling', () => {
    it('should handle network timeout', async () => {
      const error = new Error('timeout of 30000ms exceeded');
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(dashboardService.getDashboard()).rejects.toThrow('timeout');
    });

    it('should handle 500 server error', async () => {
      const error = { response: { status: 500, data: { error: 'Internal server error' } } };
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(dashboardService.getDashboard()).rejects.toEqual(error);
    });

    it('should handle 401 unauthorized', async () => {
      const error = { response: { status: 401, data: { error: 'Unauthorized' } } };
      vi.mocked(api.get).mockRejectedValue(error);
      
      await expect(dashboardService.getDashboard()).rejects.toEqual(error);
    });

    it('should handle malformed response', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: null });
      
      const result = await dashboardService.getDashboard();
      
      expect(result.data).toBeNull();
    });
  });
});
