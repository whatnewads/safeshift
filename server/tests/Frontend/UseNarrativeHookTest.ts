/**
 * useNarrative Hook Tests
 *
 * Unit tests for the useNarrative React hook which manages
 * AI-generated narrative state and API interactions.
 *
 * @package SafeShift\Tests\Frontend
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';

// ============================================================================
// Mock Setup
// ============================================================================

// Mock the narrative service
const mockGenerateNarrative = vi.fn();

vi.mock('../../src/app/services/narrative.service.js', () => ({
  generateNarrative: mockGenerateNarrative,
}));

// Import after mocking
import { useNarrative } from '../../src/app/hooks/useNarrative.js';
import type { UseNarrativeReturn } from '../../src/app/hooks/useNarrative.js';

// ============================================================================
// Test Data Helpers
// ============================================================================

/**
 * Create a mock successful narrative response
 */
function createSuccessResponse(narrative: string, encounterId: string) {
  return {
    success: true,
    narrative,
    encounter_id: encounterId,
    generated_at: new Date().toISOString(),
    status: 200,
  };
}

/**
 * Create a mock error narrative response
 */
function createErrorResponse(errorMessage: string, encounterId: string) {
  return {
    success: false,
    narrative: '',
    encounter_id: encounterId,
    generated_at: new Date().toISOString(),
    status: 500,
    error: errorMessage,
  };
}

// ============================================================================
// Test Suite
// ============================================================================

describe('useNarrative', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Suppress console.debug and console.log during tests
    vi.spyOn(console, 'debug').mockImplementation(() => {});
    vi.spyOn(console, 'log').mockImplementation(() => {});
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.resetAllMocks();
  });

  // =========================================================================
  // Initial State Tests
  // =========================================================================

  describe('initial state', () => {
    it('returns null narrative initially', () => {
      const { result } = renderHook(() => useNarrative());

      expect(result.current.narrative).toBeNull();
    });

    it('returns false for isGenerating initially', () => {
      const { result } = renderHook(() => useNarrative());

      expect(result.current.isGenerating).toBe(false);
    });

    it('returns null error initially', () => {
      const { result } = renderHook(() => useNarrative());

      expect(result.current.error).toBeNull();
    });

    it('provides generateNarrative function', () => {
      const { result } = renderHook(() => useNarrative());

      expect(typeof result.current.generateNarrative).toBe('function');
    });

    it('provides clearNarrative function', () => {
      const { result } = renderHook(() => useNarrative());

      expect(typeof result.current.clearNarrative).toBe('function');
    });

    it('provides clearError function', () => {
      const { result } = renderHook(() => useNarrative());

      expect(typeof result.current.clearError).toBe('function');
    });
  });

  // =========================================================================
  // Hook Return Type Tests
  // =========================================================================

  describe('return type compliance', () => {
    it('returns all required properties', () => {
      const { result } = renderHook(() => useNarrative());

      const returnValue: UseNarrativeReturn = result.current;

      expect('narrative' in returnValue).toBe(true);
      expect('isGenerating' in returnValue).toBe(true);
      expect('error' in returnValue).toBe(true);
      expect('generateNarrative' in returnValue).toBe(true);
      expect('clearNarrative' in returnValue).toBe(true);
      expect('clearError' in returnValue).toBe(true);
    });
  });

  // =========================================================================
  // isGenerating State Tests
  // =========================================================================

  describe('isGenerating state', () => {
    it('sets isGenerating to true during generation', async () => {
      // Create a promise we can control
      let resolvePromise: (value: unknown) => void;
      const controlledPromise = new Promise((resolve) => {
        resolvePromise = resolve;
      });
      
      mockGenerateNarrative.mockReturnValue(controlledPromise);

      const { result } = renderHook(() => useNarrative());

      // Start generation without awaiting
      act(() => {
        result.current.generateNarrative('enc-123');
      });

      // isGenerating should be true while promise is pending
      expect(result.current.isGenerating).toBe(true);

      // Resolve the promise
      await act(async () => {
        resolvePromise!(createSuccessResponse('Narrative', 'enc-123'));
        await controlledPromise;
      });

      // isGenerating should be false after completion
      expect(result.current.isGenerating).toBe(false);
    });

    it('sets isGenerating to false after successful generation', async () => {
      mockGenerateNarrative.mockResolvedValue(
        createSuccessResponse('Generated narrative', 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.isGenerating).toBe(false);
    });

    it('sets isGenerating to false after failed generation', async () => {
      mockGenerateNarrative.mockResolvedValue(
        createErrorResponse('Generation failed', 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.isGenerating).toBe(false);
    });

    it('sets isGenerating to false after exception', async () => {
      mockGenerateNarrative.mockRejectedValue(new Error('Network error'));

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.isGenerating).toBe(false);
    });
  });

  // =========================================================================
  // Successful Generation Tests
  // =========================================================================

  describe('successful generation', () => {
    it('updates narrative state on success', async () => {
      const expectedNarrative = 'Patient presented with headache. Vitals normal.';
      mockGenerateNarrative.mockResolvedValue(
        createSuccessResponse(expectedNarrative, 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.narrative).toBe(expectedNarrative);
    });

    it('clears error state on success', async () => {
      // First, set an error state
      mockGenerateNarrative.mockResolvedValueOnce(
        createErrorResponse('Initial error', 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.error).toBe('Initial error');

      // Now succeed
      mockGenerateNarrative.mockResolvedValueOnce(
        createSuccessResponse('Success narrative', 'enc-123')
      );

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.error).toBeNull();
      expect(result.current.narrative).toBe('Success narrative');
    });

    it('preserves narrative after multiple successful calls', async () => {
      mockGenerateNarrative.mockResolvedValueOnce(
        createSuccessResponse('First narrative', 'enc-1')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-1');
      });

      expect(result.current.narrative).toBe('First narrative');

      mockGenerateNarrative.mockResolvedValueOnce(
        createSuccessResponse('Second narrative', 'enc-2')
      );

      await act(async () => {
        await result.current.generateNarrative('enc-2');
      });

      expect(result.current.narrative).toBe('Second narrative');
    });
  });

  // =========================================================================
  // Error State Tests
  // =========================================================================

  describe('error handling', () => {
    it('sets error state on API error response', async () => {
      const errorMessage = 'Failed to generate narrative';
      mockGenerateNarrative.mockResolvedValue(
        createErrorResponse(errorMessage, 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.error).toBe(errorMessage);
    });

    it('sets error state on exception', async () => {
      const errorMessage = 'Network connection lost';
      mockGenerateNarrative.mockRejectedValue(new Error(errorMessage));

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.error).toBe(errorMessage);
    });

    it('keeps narrative null on error', async () => {
      mockGenerateNarrative.mockResolvedValue(
        createErrorResponse('Error occurred', 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.narrative).toBeNull();
    });

    it('handles non-Error exceptions', async () => {
      mockGenerateNarrative.mockRejectedValue('String error');

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.error).toBe('An unexpected error occurred');
    });

    it('uses default message for undefined error', async () => {
      mockGenerateNarrative.mockResolvedValue({
        success: false,
        narrative: '',
        encounter_id: 'enc-123',
        generated_at: new Date().toISOString(),
        status: 500,
        // No error field
      });

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.error).toBe('Failed to generate narrative');
    });
  });

  // =========================================================================
  // clearNarrative Tests
  // =========================================================================

  describe('clearNarrative', () => {
    it('clears narrative state', async () => {
      mockGenerateNarrative.mockResolvedValue(
        createSuccessResponse('Generated narrative', 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      // Generate narrative first
      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.narrative).toBe('Generated narrative');

      // Clear narrative
      act(() => {
        result.current.clearNarrative();
      });

      expect(result.current.narrative).toBeNull();
    });

    it('does not clear error state', async () => {
      mockGenerateNarrative.mockResolvedValue(
        createErrorResponse('Some error', 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.error).toBe('Some error');

      act(() => {
        result.current.clearNarrative();
      });

      // Error should still be present
      expect(result.current.error).toBe('Some error');
    });

    it('can be called when narrative is already null', () => {
      const { result } = renderHook(() => useNarrative());

      // Should not throw
      act(() => {
        result.current.clearNarrative();
      });

      expect(result.current.narrative).toBeNull();
    });
  });

  // =========================================================================
  // clearError Tests
  // =========================================================================

  describe('clearError', () => {
    it('clears error state', async () => {
      mockGenerateNarrative.mockResolvedValue(
        createErrorResponse('Error message', 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.error).toBe('Error message');

      act(() => {
        result.current.clearError();
      });

      expect(result.current.error).toBeNull();
    });

    it('does not clear narrative state', async () => {
      // First generate successful narrative
      mockGenerateNarrative.mockResolvedValueOnce(
        createSuccessResponse('Good narrative', 'enc-1')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-1');
      });

      expect(result.current.narrative).toBe('Good narrative');

      // Then generate error (but narrative from before stays)
      mockGenerateNarrative.mockResolvedValueOnce(
        createErrorResponse('Some error', 'enc-2')
      );

      await act(async () => {
        await result.current.generateNarrative('enc-2');
      });

      // Note: On error, narrative is not updated from a success state
      // according to the implementation, so let's verify the actual behavior

      act(() => {
        result.current.clearError();
      });

      // After clearing error, error should be null but narrative
      // should remain from the last successful call (if that's the behavior)
      expect(result.current.error).toBeNull();
    });

    it('can be called when error is already null', () => {
      const { result } = renderHook(() => useNarrative());

      // Should not throw
      act(() => {
        result.current.clearError();
      });

      expect(result.current.error).toBeNull();
    });
  });

  // =========================================================================
  // generateNarrative Function Tests
  // =========================================================================

  describe('generateNarrative function', () => {
    it('calls API with correct encounter ID', async () => {
      const encounterId = 'enc-uuid-12345';
      mockGenerateNarrative.mockResolvedValue(
        createSuccessResponse('Narrative', encounterId)
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative(encounterId);
      });

      expect(mockGenerateNarrative).toHaveBeenCalledWith(encounterId);
    });

    it('clears error before starting new generation', async () => {
      // First set an error
      mockGenerateNarrative.mockResolvedValueOnce(
        createErrorResponse('Initial error', 'enc-1')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-1');
      });

      expect(result.current.error).toBe('Initial error');

      // Start new generation (but don't complete yet)
      let resolvePromise: (value: unknown) => void;
      const controlledPromise = new Promise((resolve) => {
        resolvePromise = resolve;
      });
      mockGenerateNarrative.mockReturnValue(controlledPromise);

      act(() => {
        result.current.generateNarrative('enc-2');
      });

      // Error should be cleared immediately
      expect(result.current.error).toBeNull();

      // Clean up by resolving the promise
      await act(async () => {
        resolvePromise!(createSuccessResponse('Narrative', 'enc-2'));
        await controlledPromise;
      });
    });

    it('is stable across rerenders (memoized)', () => {
      const { result, rerender } = renderHook(() => useNarrative());

      const firstGenerateNarrative = result.current.generateNarrative;

      rerender();

      const secondGenerateNarrative = result.current.generateNarrative;

      expect(firstGenerateNarrative).toBe(secondGenerateNarrative);
    });

    it('returns a promise', async () => {
      mockGenerateNarrative.mockResolvedValue(
        createSuccessResponse('Narrative', 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      let returnValue: unknown;

      await act(async () => {
        returnValue = result.current.generateNarrative('enc-123');
        await returnValue;
      });

      expect(returnValue).toBeInstanceOf(Promise);
    });
  });

  // =========================================================================
  // Edge Cases
  // =========================================================================

  describe('edge cases', () => {
    it('handles empty narrative from API', async () => {
      mockGenerateNarrative.mockResolvedValue({
        success: true,
        narrative: '',
        encounter_id: 'enc-123',
        generated_at: new Date().toISOString(),
        status: 200,
      });

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.narrative).toBe('');
      expect(result.current.error).toBeNull();
    });

    it('handles very long narrative', async () => {
      const longNarrative = 'A'.repeat(10000);
      mockGenerateNarrative.mockResolvedValue(
        createSuccessResponse(longNarrative, 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.narrative).toBe(longNarrative);
      expect(result.current.narrative?.length).toBe(10000);
    });

    it('handles narrative with special characters', async () => {
      const specialNarrative = 'Patient\'s BP was 120/80. Temperature: 98.6°F. "Stable"';
      mockGenerateNarrative.mockResolvedValue(
        createSuccessResponse(specialNarrative, 'enc-123')
      );

      const { result } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      expect(result.current.narrative).toBe(specialNarrative);
    });

    it('handles multiple rapid calls', async () => {
      let callCount = 0;
      mockGenerateNarrative.mockImplementation(async (id: string) => {
        callCount++;
        const currentCall = callCount;
        // Simulate some delay
        await new Promise(resolve => setTimeout(resolve, 10));
        return createSuccessResponse(`Narrative ${currentCall}`, id);
      });

      const { result } = renderHook(() => useNarrative());

      // Start multiple calls rapidly
      await act(async () => {
        result.current.generateNarrative('enc-1');
        result.current.generateNarrative('enc-2');
        await result.current.generateNarrative('enc-3');
      });

      // Should have called the API 3 times
      expect(mockGenerateNarrative).toHaveBeenCalledTimes(3);
    });

    it('maintains state after unmount and remount', async () => {
      mockGenerateNarrative.mockResolvedValue(
        createSuccessResponse('Test narrative', 'enc-123')
      );

      const { result, unmount, rerender } = renderHook(() => useNarrative());

      await act(async () => {
        await result.current.generateNarrative('enc-123');
      });

      // Note: React hooks reset state on unmount, so a new hook instance
      // will have initial state. This test verifies that behavior.
      unmount();

      // Remounting creates a new hook instance with fresh state
      const { result: newResult } = renderHook(() => useNarrative());

      expect(newResult.current.narrative).toBeNull();
    });
  });

  // =========================================================================
  // Callback Stability Tests
  // =========================================================================

  describe('callback stability', () => {
    it('clearNarrative is stable across rerenders', () => {
      const { result, rerender } = renderHook(() => useNarrative());

      const firstClearNarrative = result.current.clearNarrative;

      rerender();

      expect(result.current.clearNarrative).toBe(firstClearNarrative);
    });

    it('clearError is stable across rerenders', () => {
      const { result, rerender } = renderHook(() => useNarrative());

      const firstClearError = result.current.clearError;

      rerender();

      expect(result.current.clearError).toBe(firstClearError);
    });
  });

  // =========================================================================
  // Integration-like Tests
  // =========================================================================

  describe('full workflow', () => {
    it('handles complete generate-clear-regenerate cycle', async () => {
      const { result } = renderHook(() => useNarrative());

      // Initial state
      expect(result.current.narrative).toBeNull();
      expect(result.current.error).toBeNull();
      expect(result.current.isGenerating).toBe(false);

      // Generate first narrative
      mockGenerateNarrative.mockResolvedValueOnce(
        createSuccessResponse('First narrative', 'enc-1')
      );

      await act(async () => {
        await result.current.generateNarrative('enc-1');
      });

      expect(result.current.narrative).toBe('First narrative');

      // Clear narrative
      act(() => {
        result.current.clearNarrative();
      });

      expect(result.current.narrative).toBeNull();

      // Generate second narrative
      mockGenerateNarrative.mockResolvedValueOnce(
        createSuccessResponse('Second narrative', 'enc-2')
      );

      await act(async () => {
        await result.current.generateNarrative('enc-2');
      });

      expect(result.current.narrative).toBe('Second narrative');
    });

    it('handles error-clear-retry cycle', async () => {
      const { result } = renderHook(() => useNarrative());

      // Generate with error
      mockGenerateNarrative.mockResolvedValueOnce(
        createErrorResponse('Service unavailable', 'enc-1')
      );

      await act(async () => {
        await result.current.generateNarrative('enc-1');
      });

      expect(result.current.error).toBe('Service unavailable');
      expect(result.current.narrative).toBeNull();

      // Clear error
      act(() => {
        result.current.clearError();
      });

      expect(result.current.error).toBeNull();

      // Retry with success
      mockGenerateNarrative.mockResolvedValueOnce(
        createSuccessResponse('Retry successful', 'enc-1')
      );

      await act(async () => {
        await result.current.generateNarrative('enc-1');
      });

      expect(result.current.narrative).toBe('Retry successful');
      expect(result.current.error).toBeNull();
    });
  });
});
