/**
 * Narrative Service Tests
 *
 * Unit tests for the narrative.service.ts module which handles
 * AI-generated narrative API calls.
 *
 * @package SafeShift\Tests\Frontend
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ============================================================================
// Mock Setup
// ============================================================================

// Mock the api module
const mockPost = vi.fn();
const mockGetErrorMessage = vi.fn();

vi.mock('../../src/app/services/api.js', () => ({
  post: mockPost,
  getErrorMessage: mockGetErrorMessage,
}));

// Import after mocking
import { generateNarrative, narrativeService } from '../../src/app/services/narrative.service.js';
import type { NarrativeResponse } from '../../src/app/services/narrative.service.js';

// ============================================================================
// Test Data Helpers
// ============================================================================

/**
 * Create a mock successful API response
 */
function createSuccessResponse(narrative: string, encounterId: string): { data: NarrativeResponse } {
  return {
    data: {
      success: true,
      narrative,
      encounter_id: encounterId,
      generated_at: new Date().toISOString(),
      status: 200,
    },
  };
}

/**
 * Create a mock error API response
 */
function createErrorResponse(message: string, status: number = 500): { data: NarrativeResponse } {
  return {
    data: {
      success: false,
      narrative: '',
      encounter_id: '',
      generated_at: new Date().toISOString(),
      status,
      error: message,
    },
  };
}

// ============================================================================
// Test Suite
// ============================================================================

describe('NarrativeService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetErrorMessage.mockImplementation((error: unknown, fallback: string) => {
      if (error instanceof Error) {
        return error.message;
      }
      return fallback;
    });
  });

  afterEach(() => {
    vi.resetAllMocks();
  });

  // =========================================================================
  // generateNarrative Tests - Input Validation
  // =========================================================================

  describe('generateNarrative input validation', () => {
    it('returns error for empty encounter ID', async () => {
      const result = await generateNarrative('');

      expect(result.success).toBe(false);
      expect(result.error).toBe('Encounter ID is required');
      expect(result.status).toBe(400);
      expect(mockPost).not.toHaveBeenCalled();
    });

    it('returns error for whitespace-only encounter ID', async () => {
      const result = await generateNarrative('   ');

      expect(result.success).toBe(false);
      expect(result.error).toBe('Encounter ID is required');
      expect(result.status).toBe(400);
      expect(mockPost).not.toHaveBeenCalled();
    });

    it('returns empty narrative for invalid encounter ID', async () => {
      const result = await generateNarrative('');

      expect(result.narrative).toBe('');
      expect(result.encounter_id).toBe('');
    });
  });

  // =========================================================================
  // generateNarrative Tests - Endpoint Calling
  // =========================================================================

  describe('generateNarrative endpoint calling', () => {
    it('calls correct endpoint with encounter ID', async () => {
      const encounterId = 'enc-uuid-12345';
      mockPost.mockResolvedValue(createSuccessResponse('Test narrative', encounterId));

      await generateNarrative(encounterId);

      expect(mockPost).toHaveBeenCalledTimes(1);
      expect(mockPost).toHaveBeenCalledWith(
        `/encounters/${encounterId}/generate-narrative`,
        {}
      );
    });

    it('encodes encounter ID in URL path', async () => {
      const encounterId = 'enc-with-special-chars';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      await generateNarrative(encounterId);

      expect(mockPost).toHaveBeenCalledWith(
        expect.stringContaining(encounterId),
        expect.anything()
      );
    });

    it('sends empty object as request body', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      await generateNarrative(encounterId);

      const callArgs = mockPost.mock.calls[0];
      expect(callArgs[1]).toEqual({});
    });
  });

  // =========================================================================
  // generateNarrative Tests - Success Handling
  // =========================================================================

  describe('generateNarrative success handling', () => {
    it('returns success response with narrative', async () => {
      const encounterId = 'enc-uuid-12345';
      const expectedNarrative = 'Patient presented with headache. Vitals within normal limits.';
      
      mockPost.mockResolvedValue(createSuccessResponse(expectedNarrative, encounterId));

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(true);
      expect(result.narrative).toBe(expectedNarrative);
      expect(result.encounter_id).toBe(encounterId);
      expect(result.status).toBe(200);
    });

    it('includes generated_at timestamp', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      const result = await generateNarrative(encounterId);

      expect(result.generated_at).toBeDefined();
      expect(typeof result.generated_at).toBe('string');
      
      // Should be a valid ISO date string
      const date = new Date(result.generated_at);
      expect(date.toISOString()).toBe(result.generated_at);
    });

    it('handles response with missing fields gracefully', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue({
        data: {
          // Minimal response - some fields missing
          narrative: 'Generated text',
        },
      });

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(true);
      expect(result.narrative).toBe('Generated text');
      expect(result.encounter_id).toBe(encounterId);
      expect(result.status).toBe(200);
    });

    it('preserves full narrative text without truncation', async () => {
      const encounterId = 'enc-123';
      const longNarrative = 'A'.repeat(5000); // Long narrative
      
      mockPost.mockResolvedValue(createSuccessResponse(longNarrative, encounterId));

      const result = await generateNarrative(encounterId);

      expect(result.narrative).toBe(longNarrative);
      expect(result.narrative.length).toBe(5000);
    });
  });

  // =========================================================================
  // generateNarrative Tests - Error Handling
  // =========================================================================

  describe('generateNarrative error handling', () => {
    it('handles API error response', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue(createErrorResponse('Service temporarily unavailable', 503));

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Service temporarily unavailable');
    });

    it('handles network error', async () => {
      const encounterId = 'enc-123';
      const networkError = new Error('Network Error');
      mockPost.mockRejectedValue(networkError);
      mockGetErrorMessage.mockReturnValue('Network Error');

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Network Error');
      expect(result.status).toBe(500);
    });

    it('handles timeout error', async () => {
      const encounterId = 'enc-123';
      const timeoutError = new Error('Request timeout');
      mockPost.mockRejectedValue(timeoutError);
      mockGetErrorMessage.mockReturnValue('Request timeout');

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Request timeout');
    });

    it('uses fallback error message when error extraction fails', async () => {
      const encounterId = 'enc-123';
      mockPost.mockRejectedValue(new Error());
      mockGetErrorMessage.mockReturnValue('Failed to generate narrative');

      const result = await generateNarrative(encounterId);

      expect(result.error).toBe('Failed to generate narrative');
    });

    it('returns empty narrative on error', async () => {
      const encounterId = 'enc-123';
      mockPost.mockRejectedValue(new Error('API Error'));
      mockGetErrorMessage.mockReturnValue('API Error');

      const result = await generateNarrative(encounterId);

      expect(result.narrative).toBe('');
    });

    it('preserves encounter ID on error', async () => {
      const encounterId = 'enc-uuid-12345';
      mockPost.mockRejectedValue(new Error('Error'));
      mockGetErrorMessage.mockReturnValue('Error');

      const result = await generateNarrative(encounterId);

      expect(result.encounter_id).toBe(encounterId);
    });

    it('includes generated_at timestamp on error', async () => {
      const encounterId = 'enc-123';
      mockPost.mockRejectedValue(new Error('Error'));
      mockGetErrorMessage.mockReturnValue('Error');

      const beforeCall = new Date();
      const result = await generateNarrative(encounterId);
      const afterCall = new Date();

      const generatedAt = new Date(result.generated_at);
      expect(generatedAt.getTime()).toBeGreaterThanOrEqual(beforeCall.getTime());
      expect(generatedAt.getTime()).toBeLessThanOrEqual(afterCall.getTime());
    });
  });

  // =========================================================================
  // generateNarrative Tests - Response Parsing
  // =========================================================================

  describe('generateNarrative response parsing', () => {
    it('parses nested data structure correctly', async () => {
      const encounterId = 'enc-123';
      const narrative = 'Test narrative content';
      
      mockPost.mockResolvedValue({
        data: {
          success: true,
          narrative,
          encounter_id: encounterId,
          generated_at: '2025-01-15T10:30:00.000Z',
          status: 200,
        },
      });

      const result = await generateNarrative(encounterId);

      expect(result.narrative).toBe(narrative);
      expect(result.generated_at).toBe('2025-01-15T10:30:00.000Z');
    });

    it('handles null values in response', async () => {
      const encounterId = 'enc-123';
      
      mockPost.mockResolvedValue({
        data: {
          success: true,
          narrative: null,
          encounter_id: null,
          generated_at: null,
          status: null,
        },
      });

      const result = await generateNarrative(encounterId);

      // Should use defaults for null values
      expect(result.narrative).toBe('');
      expect(result.encounter_id).toBe(encounterId);
      expect(result.status).toBe(200);
    });

    it('handles undefined values in response', async () => {
      const encounterId = 'enc-123';
      
      mockPost.mockResolvedValue({
        data: {
          // success is undefined - should default to true
          narrative: 'Some narrative',
        },
      });

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(true);
      expect(result.narrative).toBe('Some narrative');
    });

    it('includes error field when present in response', async () => {
      const encounterId = 'enc-123';
      
      mockPost.mockResolvedValue({
        data: {
          success: false,
          narrative: '',
          encounter_id: encounterId,
          generated_at: new Date().toISOString(),
          status: 400,
          error: 'Missing required data',
        },
      });

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Missing required data');
    });
  });

  // =========================================================================
  // narrativeService Object Tests
  // =========================================================================

  describe('narrativeService object', () => {
    it('exports generateNarrative function', () => {
      expect(narrativeService.generateNarrative).toBeDefined();
      expect(typeof narrativeService.generateNarrative).toBe('function');
    });

    it('generateNarrative from service object works correctly', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      const result = await narrativeService.generateNarrative(encounterId);

      expect(result.success).toBe(true);
      expect(mockPost).toHaveBeenCalled();
    });
  });

  // =========================================================================
  // Response Type Tests
  // =========================================================================

  describe('NarrativeResponse type compliance', () => {
    it('success response has all required fields', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      const result = await generateNarrative(encounterId);

      // Type-check: all fields should be present
      expect('success' in result).toBe(true);
      expect('narrative' in result).toBe(true);
      expect('encounter_id' in result).toBe(true);
      expect('generated_at' in result).toBe(true);
      expect('status' in result).toBe(true);
    });

    it('error response includes error field', async () => {
      const encounterId = 'enc-123';
      mockPost.mockRejectedValue(new Error('Test error'));
      mockGetErrorMessage.mockReturnValue('Test error');

      const result = await generateNarrative(encounterId);

      expect('error' in result).toBe(true);
      expect(result.error).toBeDefined();
    });

    it('success field is boolean', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      const result = await generateNarrative(encounterId);

      expect(typeof result.success).toBe('boolean');
    });

    it('narrative field is string', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative text', encounterId));

      const result = await generateNarrative(encounterId);

      expect(typeof result.narrative).toBe('string');
    });

    it('status field is number', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      const result = await generateNarrative(encounterId);

      expect(typeof result.status).toBe('number');
    });
  });

  // =========================================================================
  // Edge Cases
  // =========================================================================

  describe('edge cases', () => {
    it('handles UUID format encounter ID', async () => {
      const encounterId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(true);
      expect(result.encounter_id).toBe(encounterId);
    });

    it('handles numeric encounter ID', async () => {
      const encounterId = '12345';
      mockPost.mockResolvedValue(createSuccessResponse('Narrative', encounterId));

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(true);
      expect(result.encounter_id).toBe(encounterId);
    });

    it('handles narrative with special characters', async () => {
      const encounterId = 'enc-123';
      const narrativeWithSpecialChars = 'Patient\'s BP was 120/80. Temperature: 98.6°F. Pain: 3/10 ("mild").';
      
      mockPost.mockResolvedValue(createSuccessResponse(narrativeWithSpecialChars, encounterId));

      const result = await generateNarrative(encounterId);

      expect(result.narrative).toBe(narrativeWithSpecialChars);
    });

    it('handles narrative with unicode characters', async () => {
      const encounterId = 'enc-123';
      const narrativeWithUnicode = 'Patient José García-Müller presented with symptoms.';
      
      mockPost.mockResolvedValue(createSuccessResponse(narrativeWithUnicode, encounterId));

      const result = await generateNarrative(encounterId);

      expect(result.narrative).toBe(narrativeWithUnicode);
    });

    it('handles narrative with newlines', async () => {
      const encounterId = 'enc-123';
      const narrativeWithNewlines = 'Paragraph one.\n\nParagraph two.\n\nParagraph three.';
      
      mockPost.mockResolvedValue(createSuccessResponse(narrativeWithNewlines, encounterId));

      const result = await generateNarrative(encounterId);

      expect(result.narrative).toBe(narrativeWithNewlines);
      expect(result.narrative.split('\n').length).toBe(5);
    });

    it('handles empty narrative in successful response', async () => {
      const encounterId = 'enc-123';
      mockPost.mockResolvedValue({
        data: {
          success: true,
          narrative: '',
          encounter_id: encounterId,
          generated_at: new Date().toISOString(),
          status: 200,
        },
      });

      const result = await generateNarrative(encounterId);

      expect(result.success).toBe(true);
      expect(result.narrative).toBe('');
    });

    it('handles concurrent calls', async () => {
      const encounterIds = ['enc-1', 'enc-2', 'enc-3'];
      
      // Set up mocks for each call
      mockPost.mockImplementation((url: string) => {
        const parts = url.split('/');
        const id = parts[2] ?? 'unknown';
        return Promise.resolve(createSuccessResponse(`Narrative for ${id}`, id));
      });

      // Make concurrent calls
      const results = await Promise.all(
        encounterIds.map(id => generateNarrative(id))
      );

      expect(results).toHaveLength(3);
      expect(results[0]?.encounter_id).toBe('enc-1');
      expect(results[1]?.encounter_id).toBe('enc-2');
      expect(results[2]?.encounter_id).toBe('enc-3');
    });
  });
});
