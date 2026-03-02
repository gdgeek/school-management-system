// 认证工具函数

const TOKEN_KEY = 'access_token'
const REFRESH_TOKEN_KEY = 'refresh_token'
const USER_KEY = 'user_info'

/**
 * 获取访问令牌
 */
export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

/**
 * 设置访问令牌
 */
export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

/**
 * 移除访问令牌
 */
export function removeToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

/**
 * 获取刷新令牌
 */
export function getRefreshToken(): string | null {
  return localStorage.getItem(REFRESH_TOKEN_KEY)
}

/**
 * 设置刷新令牌
 */
export function setRefreshToken(token: string): void {
  localStorage.setItem(REFRESH_TOKEN_KEY, token)
}

/**
 * 移除刷新令牌
 */
export function removeRefreshToken(): void {
  localStorage.removeItem(REFRESH_TOKEN_KEY)
}

/**
 * 获取用户信息
 */
export function getUserInfo(): any | null {
  const userStr = localStorage.getItem(USER_KEY)
  if (userStr) {
    try {
      return JSON.parse(userStr)
    } catch (e) {
      console.error('Failed to parse user info:', e)
      return null
    }
  }
  return null
}

/**
 * 设置用户信息
 */
export function setUserInfo(user: any): void {
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

/**
 * 移除用户信息
 */
export function removeUserInfo(): void {
  localStorage.removeItem(USER_KEY)
}

/**
 * 清除所有认证信息
 */
export function clearAuth(): void {
  removeToken()
  removeRefreshToken()
  removeUserInfo()
}

/**
 * 检查是否已登录
 */
export function isAuthenticated(): boolean {
  return !!getToken()
}

/**
 * 从URL参数中获取会话令牌
 */
export function getSessionTokenFromUrl(): string | null {
  const params = new URLSearchParams(window.location.search)
  return params.get('session_token') || params.get('token')
}

/**
 * 跳转到主系统登录页
 */
export function redirectToMainSystem(): void {
  const mainSystemUrl = import.meta.env.VITE_MAIN_SYSTEM_URL || '/'
  clearAuth()
  window.location.href = mainSystemUrl
}
