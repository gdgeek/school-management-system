import { describe, it, expect, beforeEach, vi } from 'vitest'
import {
  getToken,
  setToken,
  removeToken,
  getRefreshToken,
  setRefreshToken,
  removeRefreshToken,
  getUserInfo,
  setUserInfo,
  removeUserInfo,
  clearAuth,
  isAuthenticated,
  getSessionTokenFromUrl,
} from './auth'

describe('auth utils', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  describe('access token', () => {
    it('returns null when no token is set', () => {
      expect(getToken()).toBeNull()
    })

    it('stores and retrieves a token', () => {
      setToken('test-token-123')
      expect(getToken()).toBe('test-token-123')
    })

    it('removes the token', () => {
      setToken('test-token-123')
      removeToken()
      expect(getToken()).toBeNull()
    })
  })

  describe('refresh token', () => {
    it('returns null when no refresh token is set', () => {
      expect(getRefreshToken()).toBeNull()
    })

    it('stores and retrieves a refresh token', () => {
      setRefreshToken('refresh-abc')
      expect(getRefreshToken()).toBe('refresh-abc')
    })

    it('removes the refresh token', () => {
      setRefreshToken('refresh-abc')
      removeRefreshToken()
      expect(getRefreshToken()).toBeNull()
    })
  })

  describe('user info', () => {
    it('returns null when no user info is set', () => {
      expect(getUserInfo()).toBeNull()
    })

    it('stores and retrieves user info as JSON', () => {
      const user = { id: 1, name: 'Test User', roles: ['admin'] }
      setUserInfo(user)
      expect(getUserInfo()).toEqual(user)
    })

    it('returns null for invalid JSON', () => {
      localStorage.setItem('user_info', 'not-json')
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      expect(getUserInfo()).toBeNull()
      consoleSpy.mockRestore()
    })

    it('removes user info', () => {
      setUserInfo({ id: 1 })
      removeUserInfo()
      expect(getUserInfo()).toBeNull()
    })
  })

  describe('clearAuth', () => {
    it('clears all auth data', () => {
      setToken('token')
      setRefreshToken('refresh')
      setUserInfo({ id: 1 })

      clearAuth()

      expect(getToken()).toBeNull()
      expect(getRefreshToken()).toBeNull()
      expect(getUserInfo()).toBeNull()
    })
  })

  describe('isAuthenticated', () => {
    it('returns false when no token exists', () => {
      expect(isAuthenticated()).toBe(false)
    })

    it('returns true when a token exists', () => {
      setToken('some-token')
      expect(isAuthenticated()).toBe(true)
    })
  })

  describe('getSessionTokenFromUrl', () => {
    it('returns session_token from URL params', () => {
      Object.defineProperty(window, 'location', {
        value: { search: '?session_token=abc123' },
        writable: true,
      })
      expect(getSessionTokenFromUrl()).toBe('abc123')
    })

    it('returns token param as fallback', () => {
      Object.defineProperty(window, 'location', {
        value: { search: '?token=xyz789' },
        writable: true,
      })
      expect(getSessionTokenFromUrl()).toBe('xyz789')
    })

    it('returns null when no token param exists', () => {
      Object.defineProperty(window, 'location', {
        value: { search: '' },
        writable: true,
      })
      expect(getSessionTokenFromUrl()).toBeNull()
    })
  })
})
