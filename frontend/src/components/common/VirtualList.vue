<template>
  <div 
    ref="containerRef" 
    class="virtual-list" 
    @scroll="handleScroll"
  >
    <div 
      class="virtual-list-phantom" 
      :style="{ height: totalHeight + 'px' }"
    ></div>
    
    <div 
      class="virtual-list-content" 
      :style="{ transform: `translateY(${offsetY}px)` }"
    >
      <div
        v-for="item in visibleData"
        :key="getItemKey(item)"
        class="virtual-list-item"
        :style="{ height: itemHeight + 'px' }"
      >
        <slot :item="item" :index="item.__index"></slot>
      </div>
    </div>
    
    <!-- Loading indicator -->
    <div v-if="loading" class="virtual-list-loading">
      <el-icon class="is-loading"><Loading /></el-icon>
      <span>Loading...</span>
    </div>
    
    <!-- Empty state -->
    <div v-if="!loading && data.length === 0" class="virtual-list-empty">
      <el-empty :description="emptyText" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Loading } from '@element-plus/icons-vue'

interface Props {
  data: any[]
  itemHeight: number
  bufferSize?: number
  loading?: boolean
  emptyText?: string
  itemKey?: string
}

const props = withDefaults(defineProps<Props>(), {
  bufferSize: 5,
  loading: false,
  emptyText: 'No data',
  itemKey: 'id'
})

const containerRef = ref<HTMLElement | null>(null)
const scrollTop = ref(0)
const containerHeight = ref(0)

// Total height of all items
const totalHeight = computed(() => props.data.length * props.itemHeight)

// Number of visible items
const visibleCount = computed(() => 
  Math.ceil(containerHeight.value / props.itemHeight) + props.bufferSize * 2
)

// Start index of visible items
const startIndex = computed(() => {
  const index = Math.floor(scrollTop.value / props.itemHeight) - props.bufferSize
  return Math.max(0, index)
})

// End index of visible items
const endIndex = computed(() => 
  Math.min(props.data.length, startIndex.value + visibleCount.value)
)

// Visible data
const visibleData = computed(() => {
  return props.data
    .slice(startIndex.value, endIndex.value)
    .map((item, index) => ({
      ...item,
      __index: startIndex.value + index
    }))
})

// Offset Y for positioning
const offsetY = computed(() => startIndex.value * props.itemHeight)

function handleScroll(event: Event) {
  const target = event.target as HTMLElement
  scrollTop.value = target.scrollTop
}

function getItemKey(item: any): string | number {
  return item[props.itemKey] || item.__index
}

function updateContainerHeight() {
  if (containerRef.value) {
    containerHeight.value = containerRef.value.clientHeight
  }
}

let resizeObserver: ResizeObserver | null = null

onMounted(() => {
  updateContainerHeight()
  
  // Use ResizeObserver to track container size changes
  if (containerRef.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => {
      updateContainerHeight()
    })
    resizeObserver.observe(containerRef.value)
  }
  
  // Fallback to window resize event
  window.addEventListener('resize', updateContainerHeight)
})

onUnmounted(() => {
  if (resizeObserver && containerRef.value) {
    resizeObserver.unobserve(containerRef.value)
    resizeObserver.disconnect()
  }
  window.removeEventListener('resize', updateContainerHeight)
})

// Reset scroll position when data changes
watch(() => props.data.length, () => {
  if (containerRef.value) {
    containerRef.value.scrollTop = 0
    scrollTop.value = 0
  }
})

// Expose methods for parent component
defineExpose({
  scrollTo: (index: number) => {
    if (containerRef.value) {
      containerRef.value.scrollTop = index * props.itemHeight
    }
  },
  scrollToTop: () => {
    if (containerRef.value) {
      containerRef.value.scrollTop = 0
    }
  }
})
</script>

<style scoped>
.virtual-list {
  position: relative;
  width: 100%;
  height: 100%;
  overflow-y: auto;
  overflow-x: hidden;
}

.virtual-list-phantom {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: -1;
}

.virtual-list-content {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  will-change: transform;
}

.virtual-list-item {
  width: 100%;
  box-sizing: border-box;
}

.virtual-list-loading,
.virtual-list-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #909399;
}

.virtual-list-loading .el-icon {
  font-size: 32px;
  margin-bottom: 12px;
}

.virtual-list-loading span {
  font-size: 14px;
}

/* Smooth scrolling */
.virtual-list {
  scroll-behavior: smooth;
}

/* Custom scrollbar */
.virtual-list::-webkit-scrollbar {
  width: 8px;
}

.virtual-list::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.virtual-list::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

.virtual-list::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>
