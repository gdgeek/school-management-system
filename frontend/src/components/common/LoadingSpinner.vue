<template>
  <div class="loading-spinner" :class="[`size-${size}`, { overlay }]">
    <el-icon class="is-loading">
      <Loading />
    </el-icon>
    <span v-if="text" class="loading-text">{{ text }}</span>
  </div>
</template>

<script setup lang="ts">
import { Loading } from '@element-plus/icons-vue'

withDefaults(defineProps<{
  size?: 'small' | 'medium' | 'large'
  text?: string
  overlay?: boolean
}>(), {
  size: 'medium',
  overlay: false
})
</script>

<style scoped>
.loading-spinner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: #409eff;
}

.loading-spinner.overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.8);
  z-index: 10;
}

.size-small .el-icon { font-size: 16px; }
.size-medium .el-icon { font-size: 24px; }
.size-large .el-icon { font-size: 40px; }

.loading-text {
  font-size: 14px;
  color: #909399;
}
</style>
