import { defineStore } from 'pinia'
import { ref, computed, onMounted, onUnmounted } from 'vue'

export const useAppStore = defineStore('app', () => {
  // 全局加载状态
  const loading = ref(false)
  const loadingText = ref('')
  
  // 侧边栏状态
  const sidebarCollapsed = ref(false)
  const sidebarVisible = ref(false)
  
  // 响应式状态
  const windowWidth = ref(window.innerWidth)
  
  // 语言设置
  const locale = ref(localStorage.getItem('locale') || 'zh-CN')
  
  // 主题设置
  const theme = ref(localStorage.getItem('theme') || 'light')

  // 计算属性：判断是否为移动设备
  const isMobile = computed(() => windowWidth.value < 768)
  const isTablet = computed(() => windowWidth.value >= 768 && windowWidth.value < 1024)
  const isDesktop = computed(() => windowWidth.value >= 1024)

  /**
   * 显示全局加载
   */
  function showLoading(text = 'Loading...'): void {
    loading.value = true
    loadingText.value = text
  }

  /**
   * 隐藏全局加载
   */
  function hideLoading(): void {
    loading.value = false
    loadingText.value = ''
  }

  /**
   * 切换侧边栏
   */
  function toggleSidebar(): void {
    if (isMobile.value) {
      sidebarVisible.value = !sidebarVisible.value
    } else {
      sidebarCollapsed.value = !sidebarCollapsed.value
    }
  }

  /**
   * 设置语言
   */
  function setLocale(newLocale: string): void {
    locale.value = newLocale
    localStorage.setItem('locale', newLocale)
  }

  /**
   * 设置主题
   */
  function setTheme(newTheme: string): void {
    theme.value = newTheme
    localStorage.setItem('theme', newTheme)
    
    // 更新HTML类名
    if (newTheme === 'dark') {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }

  /**
   * 处理窗口大小变化
   */
  function handleResize(): void {
    windowWidth.value = window.innerWidth
    
    // 在移动设备上自动关闭侧边栏
    if (isMobile.value) {
      sidebarVisible.value = false
    }
  }

  // 初始化主题
  if (theme.value === 'dark') {
    document.documentElement.classList.add('dark')
  }

  // 监听窗口大小变化
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', handleResize)
  }

  return {
    // 状态
    loading,
    loadingText,
    sidebarCollapsed,
    sidebarVisible,
    windowWidth,
    locale,
    theme,
    
    // 计算属性
    isMobile,
    isTablet,
    isDesktop,
    
    // 方法
    showLoading,
    hideLoading,
    toggleSidebar,
    setLocale,
    setTheme,
    handleResize
  }
})
