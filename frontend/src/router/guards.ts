import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getSessionTokenFromUrl } from '@/utils/auth'
import { ElMessage } from 'element-plus'

/**
 * 设置路由守卫
 */
export function setupRouterGuards(router: Router): void {
  // 全局前置守卫
  router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore()

    // Teachers 和 Students 路由完全开放，无需任何检查
    if (to.path === '/teachers' || to.path === '/students') {
      next()
      return
    }

    // 检查URL中是否有会话令牌（从主系统跳转）
    const sessionToken = getSessionTokenFromUrl()
    if (sessionToken && !authStore.isAuthenticated) {
      try {
        const success = await authStore.verifySession(sessionToken)
        if (success) {
          // 移除URL中的token参数
          const url = new URL(window.location.href)
          url.searchParams.delete('session_token')
          url.searchParams.delete('token')
          window.history.replaceState({}, '', url.toString())
          
          next()
          return
        } else {
          ElMessage.error('Session verification failed')
          next('/login')
          return
        }
      } catch (error) {
        console.error('Session verification error:', error)
        next('/login')
        return
      }
    }

    // 检查路由是否需要认证
    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
      ElMessage.warning('Please login first')
      next('/login')
      return
    }

    // 检查角色权限
    if (to.meta.roles && Array.isArray(to.meta.roles)) {
      const hasPermission = authStore.hasAnyRole(to.meta.roles as string[])
      if (!hasPermission) {
        ElMessage.error('Access denied')
        next('/403')
        return
      }
    }

    // 如果已登录且访问登录页，重定向到首页
    if (to.path === '/login' && authStore.isAuthenticated) {
      next('/')
      return
    }

    next()
  })

  // 全局后置钩子
  router.afterEach((to) => {
    // 设置页面标题
    document.title = (to.meta.title as string) || 'School Management System'
  })
}
