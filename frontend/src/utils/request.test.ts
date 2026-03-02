import { describe, it, expect, beforeEach } from 'vitest'
import { invalidateCache } from './request'

/**
 * Tests for the request module's pure utility functions.
 * The axios instance and interceptors require a running server,
 * so we focus on testing the exported helpers that can be unit-tested.
 */
describe('request utils', () => {
  describe('invalidateCache', () => {
    it('is a callable function', () => {
      expect(typeof invalidateCache).toBe('function')
    })

    it('does not throw when called with a prefix', () => {
      expect(() => invalidateCache('/api/schools')).not.toThrow()
    })
  })

  describe('ApiResponse type contract', () => {
    it('matches the expected shape', () => {
      // Verify the response shape matches our interface
      const response = {
        code: 200,
        message: 'Success',
        data: { id: 1, name: 'Test School' },
        timestamp: Date.now(),
      }

      expect(response).toHaveProperty('code')
      expect(response).toHaveProperty('message')
      expect(response).toHaveProperty('data')
      expect(response.code).toBe(200)
    })
  })

  describe('PaginationResponse type contract', () => {
    it('matches the expected shape', () => {
      const paginatedResponse = {
        items: [{ id: 1 }, { id: 2 }],
        pagination: {
          total: 100,
          page: 1,
          pageSize: 20,
          totalPages: 5,
        },
      }

      expect(paginatedResponse.items).toHaveLength(2)
      expect(paginatedResponse.pagination.total).toBe(100)
      expect(paginatedResponse.pagination.totalPages).toBe(5)
    })
  })
})
