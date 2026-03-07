<template>
  <el-aside :width="collapsed ? '64px' : '200px'" class="app-sidebar">
    <el-menu
      :default-active="activeMenu"
      :collapse="collapsed"
      router
    >
      <el-menu-item index="/schools">
        <el-icon><School /></el-icon>
        <template #title>{{ $t('nav.schools') }}</template>
      </el-menu-item>
      
      <el-menu-item index="/classes">
        <el-icon><Reading /></el-icon>
        <template #title>{{ $t('nav.classes') }}</template>
      </el-menu-item>
      
      <el-menu-item index="/teachers">
        <el-icon><User /></el-icon>
        <template #title>{{ $t('nav.teachers') }}</template>
      </el-menu-item>
      
      <el-menu-item index="/students">
        <el-icon><UserFilled /></el-icon>
        <template #title>{{ $t('nav.students') }}</template>
      </el-menu-item>
      
      <el-menu-item index="/groups">
        <el-icon><Tickets /></el-icon>
        <template #title>{{ $t('nav.groups') }}</template>
      </el-menu-item>
    </el-menu>
  </el-aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { School, Reading, User, UserFilled, Tickets } from '@element-plus/icons-vue'
import { useAppStore } from '@/stores/app'

const route = useRoute()
const appStore = useAppStore()

const collapsed = computed(() => appStore.sidebarCollapsed)
const activeMenu = computed(() => route.path)
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
