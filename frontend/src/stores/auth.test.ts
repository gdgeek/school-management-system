import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from './auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('initializes with null values when no stored auth', () => {
    const store = useAuthStore()
    expect(store.token).toBeNull()
    expect(store.refreshToken).toBeNull()
    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
  })

  it('isAuthenticated is false when only token exists (no user)', () => {
    localStorage.setItem('access_token', 'test-token')
    setActivePinia(createPinia())
    const store = useAuthStore()
    // isAuthenticated requires both token AND user
    expect(store.isAuthenticated).toBe(false)
  })

  describe('role checks', () => {
    let store: ReturnType<typeof useAuthStore>

    beforeEach(() => {
      localStorage.setItem('access_token', 'test-token')
      localStorage.setItem('user_info', JSON.stringify({
        id: 1,
        nickname: 'Admin',
        roles: ['admin', 'teacher'],
      }))
      setActivePinia(createPinia())
      store = useAuthStore()
    })

    it('hasRole returns true for existing role', () => {
      expect(store.hasRole('admin')).toBe(true)
      expect(store.hasRole('teacher')).toBe(true)
    })

    it('hasRole returns false for non-existing role', () => {
      expect(store.hasRole('student')).toBe(false)
    })

    it('hasAnyRole returns true if any role matches', () => {
      expect(store.hasAnyRole(['student', 'admin'])).toBe(true)
    })

    it('hasAnyRole returns false if no role matches', () => {
      expect(store.hasAnyRole(['student', 'school_admin'])).toBe(false)
    })

    it('hasAllRoles returns true if all roles match', () => {
      expect(store.hasAllRoles(['admin', 'teacher'])).toBe(true)
    })

    it('hasAllRoles returns false if not all roles match', () => {
      expect(store.hasAllRoles(['admin', 'student'])).toBe(false)
    })

    it('computed role flags are correct', () => {
      expect(store.isAdmin).toBe(true)
      expect(store.isTeacher).toBe(true)
      expect(store.isStudent).toBe(false)
      expect(store.isSchoolAdmin).toBe(false)
    })
  })

  describe('logout', () => {
    it('clears all auth state', () => {
      localStorage.setItem('access_token', 'token')
      localStorage.setItem('refresh_token', 'refresh')
      localStorage.setItem('user_info', JSON.stringify({ id: 1 }))
      setActivePinia(createPinia())
      const store = useAuthStore()

      store.logout()

      expect(store.token).toBeNull()
      expect(store.refreshToken).toBeNull()
      expect(store.user).toBeNull()
      expect(store.isAuthenticated).toBe(false)
      expect(localStorage.getItem('access_token')).toBeNull()
      expect(localStorage.getItem('refresh_token')).toBeNull()
      expect(localStorage.getItem('user_info')).toBeNull()
    })
  })
})
