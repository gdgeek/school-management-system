<template>
  <el-aside :width="collapsed ? '64px' : '200px'" class="app-sidebar">
    <el-menu
      :default-active="activeMenu"
      :collapse="collapsed"
      router
    >
      <el-menu-item index="/schools">
        <el-icon><School /></el-icon>
        <template #title>Schools</template>
      </el-menu-item>
      
      <el-menu-item index="/classes">
        <el-icon><Reading /></el-icon>
        <template #title>Classes</template>
      </el-menu-item>
      
      <el-menu-item 
        v-if="canAccessTeachers"
        index="/teachers"
      >
        <el-icon><User /></el-icon>
        <template #title>Teachers</template>
      </el-menu-item>
      
      <el-menu-item 
        v-if="canAccessStudents"
        index="/students"
      >
        <el-icon><UserFilled /></el-icon>
        <template #title>Students</template>
      </el-menu-item>
      
      <el-menu-item index="/groups">
        <el-icon><Tickets /></el-icon>
        <template #title>Groups</template>
      </el-menu-item>
    </el-menu>
  </el-aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { School, Reading, User, UserFilled, Tickets } from '@element-plus/icons-vue'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const route = useRoute()
const authStore = useAuthStore()
const appStore = useAppStore()

const collapsed = computed(() => appStore.sidebarCollapsed)
const activeMenu = computed(() => route.path)

const canAccessTeachers = computed(() => 
  authStore.hasAnyRole(['admin', 'school_admin'])
)

const canAccessStudents = computed(() => 
  authStore.hasAnyRole(['admin', 'school_admin', 'teacher'])
)
</script>

<style scoped>
.app-sidebar {
  background: #fff;
  border-right: 1px solid #e8e8e8;
  transition: width 0.3s;
}

.el-menu {
  border-right: none;
}
</style>
