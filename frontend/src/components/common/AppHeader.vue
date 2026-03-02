<template>
  <el-header class="app-header">
    <div class="header-left">
      <el-button
        :icon="Fold"
        circle
        @click="toggleSidebar"
        class="sidebar-toggle"
      />
      <h1 class="app-title">School Management System</h1>
    </div>
    
    <div class="header-right">
      <!-- 返回主系统按钮 -->
      <el-button
        type="primary"
        :icon="Back"
        @click="goToMainSystem"
        class="back-btn"
        plain
      >
        <span v-if="!isMobile">返回主系统</span>
      </el-button>

      <!-- 语言切换 -->
      <el-dropdown @command="handleLanguageChange" v-if="!isMobile">
        <el-button :icon="Globe" circle />
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item command="zh-CN">中文</el-dropdown-item>
            <el-dropdown-item command="en-US">English</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
      
      <!-- 用户菜单 -->
      <el-dropdown @command="handleUserCommand">
        <div class="user-info">
          <el-avatar :src="authStore.user?.avatar" :size="isMobile ? 28 : 32">
            {{ authStore.user?.nickname?.charAt(0) }}
          </el-avatar>
          <span class="username" v-if="!isMobile">{{ authStore.user?.nickname }}</span>
        </div>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item disabled>
              {{ authStore.user?.username }}
            </el-dropdown-item>
            <el-dropdown-item divided command="logout">
              退出登录
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </el-header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Fold, Globe, Back } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { ElMessage } from 'element-plus'

const authStore = useAuthStore()
const appStore = useAppStore()

const isMobile = computed(() => appStore.isMobile)

function toggleSidebar() {
  appStore.toggleSidebar()
}

function handleLanguageChange(locale: string) {
  appStore.setLocale(locale)
  ElMessage.success(`Language changed to ${locale}`)
}

function goToMainSystem() {
  const mainSystemUrl = import.meta.env.VITE_MAIN_SYSTEM_URL || '/'
  window.location.href = mainSystemUrl
}

function handleUserCommand(command: string) {
  if (command === 'logout') {
    authStore.logout()
    window.location.href = '/login'
  }
}
</script>

<style scoped>
.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
  background: #fff;
  border-bottom: 1px solid #e8e8e8;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  height: 60px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.app-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #333;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: background-color 0.3s;
}

.user-info:hover {
  background-color: #f5f5f5;
}

.username {
  font-size: 14px;
  color: #333;
}

/* Mobile responsive styles */
@media (max-width: 768px) {
  .app-header {
    padding: 0 12px;
  }
  
  .header-left {
    gap: 8px;
  }
  
  .app-title {
    font-size: 16px;
  }
  
  .header-right {
    gap: 8px;
  }
  
  .sidebar-toggle {
    min-width: 40px;
    min-height: 40px;
  }
}

/* Tablet responsive styles */
@media (min-width: 768px) and (max-width: 1024px) {
  .app-header {
    padding: 0 16px;
  }
  
  .app-title {
    font-size: 17px;
  }
}

.back-btn {
  font-size: 13px;
}
</style>
