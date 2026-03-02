<template>
  <Transition name="fade">
    <div v-if="appStore.loading" class="global-loading-overlay">
      <div class="loading-box">
        <el-icon class="is-loading loading-icon">
          <Loading />
        </el-icon>
        <p class="loading-text">{{ appStore.loadingText || $t('common.loading') }}</p>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { Loading } from '@element-plus/icons-vue'
import { useAppStore } from '@/stores/app'

const appStore = useAppStore()
</script>

<style scoped>
.global-loading-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.loading-box {
  background: #fff;
  border-radius: 8px;
  padding: 32px 48px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.loading-icon {
  font-size: 40px;
  color: #409eff;
}

.loading-text {
  margin: 0;
  font-size: 14px;
  color: #606266;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
