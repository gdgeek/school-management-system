import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { setupRouterGuards } from './guards'

// 路由配置
const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: '/schools'
  },
  {
    path: '/schools',
    name: 'Schools',
    component: () => import('@/views/SchoolView.vue'),
    meta: {
      title: 'Schools',
      requiresAuth: false
    }
  },
  {
    path: '/schools/:id',
    name: 'SchoolDetail',
    component: () => import('@/views/SchoolDetailView.vue'),
    meta: {
      title: 'School Detail',
      requiresAuth: true
    }
  },
  {
    path: '/classes',
    name: 'Classes',
    component: () => import('@/views/ClassView.vue'),
    meta: {
      title: 'Classes',
      requiresAuth: true
    }
  },
  {
    path: '/classes/:id',
    name: 'ClassDetail',
    component: () => import('@/views/ClassDetailView.vue'),
    meta: {
      title: 'Class Detail',
      requiresAuth: true
    }
  },
  {
    path: '/teachers',
    name: 'Teachers',
    component: () => import('@/views/TeacherView.vue'),
    meta: {
      title: 'Teachers',
      requiresAuth: false
    }
  },
  {
    path: '/students',
    name: 'Students',
    component: () => import('@/views/StudentView.vue'),
    meta: {
      title: 'Students',
      requiresAuth: false
    }
  },
  {
    path: '/groups',
    name: 'Groups',
    component: () => import('@/views/GroupView.vue'),
    meta: {
      title: 'Groups',
      requiresAuth: true
    }
  },
  {
    path: '/groups/:id',
    name: 'GroupDetail',
    component: () => import('@/views/GroupDetailView.vue'),
    meta: {
      title: 'Group Detail',
      requiresAuth: true
    }
  },
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('@/views/profile/UserProfile.vue'),
    meta: {
      title: 'Profile',
      requiresAuth: true
    }
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: {
      title: 'Login'
    }
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/ForbiddenView.vue'),
    meta: {
      title: 'Access Denied'
    }
  },
  {
    path: '/404',
    name: 'NotFound',
    component: () => import('@/views/NotFoundView.vue'),
    meta: {
      title: 'Page Not Found'
    }
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/404'
  }
]

// 创建路由实例
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// 设置路由守卫
setupRouterGuards(router)

export default router
