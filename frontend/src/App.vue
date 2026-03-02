<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from './components/common/AppHeader.vue'
import AppSidebar from './components/common/AppSidebar.vue'
import GlobalLoading from './components/common/GlobalLoading.vue'
import ErrorBoundary from './components/common/ErrorBoundary.vue'
import { useAuthStore } from './stores/auth'
import { useAppStore } from './stores/app'

const route = useRoute()
const authStore = useAuthStore()
const appStore = useAppStore()

const isAuthPage = computed(() => 
  ['/login', '/forbidden', '/404'].includes(route.path)
)

const sidebarCollapsed = computed(() => appStore.sidebarCollapsed)
const isMobile = computed(() => appStore.isMobile)
</script>

<template>
  <div id="app" :class="{ 'mobile-view': isMobile }">
    <!-- Global loading overlay -->
    <GlobalLoading />

    <!-- Auth pages (login, 404, etc.) -->
    <template v-if="isAuthPage">
      <ErrorBoundary>
        <router-view />
      </ErrorBoundary>
    </template>
    
    <!-- Main layout with header and sidebar -->
    <template v-else-if="authStore.isAuthenticated">
      <el-container class="app-container">
        <AppHeader />
        
        <el-container class="main-container">
          <!-- Sidebar - drawer on mobile, fixed on desktop -->
          <el-drawer
            v-if="isMobile"
            v-model="appStore.sidebarVisible"
            :with-header="false"
            direction="ltr"
            size="200px"
          >
            <AppSidebar />
          </el-drawer>
          
          <AppSidebar v-else />
          
          <!-- Main content area -->
          <el-main class="app-main">
            <ErrorBoundary>
              <router-view />
            </ErrorBoundary>
          </el-main>
        </el-container>
      </el-container>
    </template>
    
    <!-- Fallback -->
    <template v-else>
      <ErrorBoundary>
        <router-view />
      </ErrorBoundary>
    </template>
  </div>
</template>

<style>
/* Global responsive styles */
* {
  box-sizing: border-box;
}

html, body {
  margin: 0;
  padding: 0;
  height: 100%;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

#app {
  height: 100%;
  overflow: hidden;
}

.app-container {
  height: 100%;
  flex-direction: column;
}

.main-container {
  height: calc(100% - 60px);
  overflow: hidden;
}

.app-main {
  background: #f5f5f5;
  overflow-y: auto;
  padding: 20px;
}

/* Mobile responsive styles */
@media (max-width: 768px) {
  .app-main {
    padding: 12px;
  }
  
  .mobile-view .app-header .app-title {
    font-size: 16px;
  }
  
  .mobile-view .app-header .username {
    display: none;
  }
}

/* Tablet responsive styles */
@media (min-width: 768px) and (max-width: 1024px) {
  .app-main {
    padding: 16px;
  }
}

/* Desktop responsive styles */
@media (min-width: 1024px) {
  .app-main {
    padding: 24px;
  }
}

/* Touch-friendly UI on mobile */
@media (hover: none) and (pointer: coarse) {
  .el-button {
    min-height: 44px;
    min-width: 44px;
  }
  
  .el-menu-item {
    min-height: 48px;
  }
}
</style>
