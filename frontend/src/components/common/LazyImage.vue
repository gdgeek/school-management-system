<template>
  <div class="lazy-image" :class="{ 'is-loading': isLoading, 'is-error': isError }">
    <img
      v-if="!isError"
      ref="imageRef"
      :src="currentSrc"
      :alt="alt"
      :class="imageClass"
      @load="handleLoad"
      @error="handleError"
    />
    
    <!-- Loading placeholder -->
    <div v-if="isLoading" class="placeholder">
      <el-icon class="is-loading"><Loading /></el-icon>
    </div>
    
    <!-- Error placeholder -->
    <div v-if="isError" class="error-placeholder">
      <el-icon><Picture /></el-icon>
      <span v-if="showErrorText">{{ errorText }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Loading, Picture } from '@element-plus/icons-vue'

interface Props {
  src: string
  alt?: string
  placeholder?: string
  errorPlaceholder?: string
  lazy?: boolean
  threshold?: number
  imageClass?: string
  showErrorText?: boolean
  errorText?: string
}

const props = withDefaults(defineProps<Props>(), {
  alt: '',
  placeholder: '',
  lazy: true,
  threshold: 0.1,
  imageClass: '',
  showErrorText: true,
  errorText: 'Failed to load image'
})

const imageRef = ref<HTMLImageElement | null>(null)
const isLoading = ref(true)
const isError = ref(false)
const isIntersecting = ref(false)

const currentSrc = computed(() => {
  if (!props.lazy) {
    return props.src
  }
  
  if (isIntersecting.value) {
    return props.src
  }
  
  return props.placeholder || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E'
})

let observer: IntersectionObserver | null = null

onMounted(() => {
  if (props.lazy && imageRef.value) {
    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            isIntersecting.value = true
            // Once image is loaded, we can disconnect the observer
            if (observer) {
              observer.disconnect()
            }
          }
        })
      },
      {
        threshold: props.threshold,
        rootMargin: '50px'
      }
    )
    
    observer.observe(imageRef.value)
  } else {
    // If not lazy loading, mark as intersecting immediately
    isIntersecting.value = true
  }
})

onUnmounted(() => {
  if (observer) {
    observer.disconnect()
  }
})

function handleLoad() {
  isLoading.value = false
  isError.value = false
}

function handleError() {
  isLoading.value = false
  isError.value = true
}
</script>

<style scoped>
.lazy-image {
  position: relative;
  display: inline-block;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background-color: #f5f5f5;
}

.lazy-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: opacity 0.3s;
}

.lazy-image.is-loading img {
  opacity: 0;
}

.placeholder,
.error-placeholder {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background-color: #f5f5f5;
  color: #909399;
}

.placeholder .el-icon {
  font-size: 24px;
}

.error-placeholder {
  background-color: #fafafa;
}

.error-placeholder .el-icon {
  font-size: 32px;
  margin-bottom: 8px;
  color: #c0c4cc;
}

.error-placeholder span {
  font-size: 12px;
  color: #909399;
}

/* Fade in animation */
.lazy-image img {
  animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>
