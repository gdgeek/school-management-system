import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { User, LoginResponse, VerifySessionRequest } from '@/types/user'
import { request } from '@/utils/request'
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
  clearAuth
} from '@/utils/auth'

export const useAuthStore = defineStore('auth', () => {
  // 状态
  const token = ref<string | null>(getToken())
  const refreshToken = ref<string | null>(getRefreshToken())
  const user = ref<User | null>(getUserInfo())
  const loading = ref(false)

  // 计算属性
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userRoles = computed(() => user.value?.roles || [])
  const isAdmin = computed(() => userRoles.value.includes('admin'))
  const isSchoolAdmin = computed(() => userRoles.value.includes('school_admin'))
  const isTeacher = computed(() => userRoles.value.includes('teacher'))
  const isStudent = computed(() => userRoles.value.includes('student'))

  /**
   * 验证会话令牌（从主系统跳转）
   */
  async function verifySession(sessionToken: string): Promise<boolean> {
    try {
      loading.value = true
      const data: VerifySessionRequest = { session_token: sessionToken }
      const response = await request.post<LoginResponse>('/auth/verify', data)
      
      // 保存认证信息
      setToken(response.access_token)
      setRefreshToken(response.refresh_token)
      setUserInfo(response.user)
      
      token.value = response.access_token
      refreshToken.value = response.refresh_token
      user.value = response.user
      
      return true
    } catch (error) {
      console.error('Session verification failed:', error)
      return false
    } finally {
      loading.value = false
    }
  }

  /**
   * 刷新访问令牌
   */
  async function refresh(): Promise<boolean> {
    const currentRefreshToken = refreshToken.value
    if (!currentRefreshToken) {
      return false
    }

    try {
      const response = await request.post<{ access_token: string }>('/auth/refresh', {
        refresh_token: currentRefreshToken
      })
      
      setToken(response.access_token)
      token.value = response.access_token
      
      return true
    } catch (error) {
      console.error('Token refresh failed:', error)
      logout()
      return false
    }
  }

  /**
   * 获取当前用户信息
   */
  async function fetchUserInfo(): Promise<void> {
    try {
      loading.value = true
      const userData = await request.get<User>('/auth/user')
      
      setUserInfo(userData)
      user.value = userData
    } catch (error) {
      console.error('Failed to fetch user info:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  /**
   * 登出
   */
  function logout(): void {
    clearAuth()
    token.value = null
    refreshToken.value = null
    user.value = null
  }

  /**
   * 检查权限
   */
  function hasRole(role: string): boolean {
    return userRoles.value.includes(role)
  }

  /**
   * 检查是否有任一权限
   */
  function hasAnyRole(roles: string[]): boolean {
    return roles.some(role => userRoles.value.includes(role))
  }

  /**
   * 检查是否有所有权限
   */
  function hasAllRoles(roles: string[]): boolean {
    return roles.every(role => userRoles.value.includes(role))
  }

  return {
    // 状态
    token,
    refreshToken,
    user,
    loading,
    
    // 计算属性
    isAuthenticated,
    userRoles,
    isAdmin,
    isSchoolAdmin,
    isTeacher,
    isStudent,
    
    // 方法
    verifySession,
    refresh,
    fetchUserInfo,
    logout,
    hasRole,
    hasAnyRole,
    hasAllRoles
  }
})
