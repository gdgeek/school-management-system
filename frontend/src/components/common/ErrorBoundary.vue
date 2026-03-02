<template>
  <div v-if="hasError" class="error-boundary">
    <el-result
      icon="error"
      :title="$t('error.pageNotFound')"
      :sub-title="errorMessage"
    >
      <template #extra>
        <el-button type="primary" @click="handleRetry">重试</el-button>
        <el-button @click="handleGoHome">返回首页</el-button>
      </template>
    </el-result>
  </div>
  <slot v-else />
</template>

<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const hasError = ref(false)
const errorMessage = ref('')

onErrorCaptured((err: Error) => {
  hasError.value = true
  errorMessage.value = err.message || '发生了未知错误'
  console.error('[ErrorBoundary] Caught error:', err)
  // Return false to prevent propagation
  return false
})

function handleRetry() {
  hasError.value = false
  errorMessage.value = ''
}

function handleGoHome() {
  hasError.value = false
  errorMessage.value = ''
  router.push('/')
}
</script>

<style scoped>
.error-boundary {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  padding: 40px;
}
</style>
