import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
import 'element-plus/dist/index.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import { ElMessage, ElNotification } from 'element-plus'

import App from './App.vue'
import router from './router'
import i18n from './locales'
import './style.css'

const app = createApp(App)

// Register Pinia
const pinia = createPinia()
app.use(pinia)

// Register Vue Router
app.use(router)

// Register Element Plus with Chinese locale
app.use(ElementPlus, { locale: zhCn })

// Register all Element Plus icons globally
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

// Register vue-i18n
app.use(i18n)

// Global error handler for Vue errors
app.config.errorHandler = (err: unknown, instance, info) => {
  console.error('[Vue Error]', err, info)
  const message = err instanceof Error ? err.message : String(err)
  ElNotification({
    title: '应用错误',
    message,
    type: 'error',
    duration: 5000,
  })
}

// Global warning handler (dev only)
if (import.meta.env.DEV) {
  app.config.warnHandler = (msg, instance, trace) => {
    console.warn('[Vue Warning]', msg, trace)
  }
}

// Handle unhandled promise rejections
window.addEventListener('unhandledrejection', (event) => {
  console.error('[Unhandled Promise Rejection]', event.reason)
  const message = event.reason instanceof Error
    ? event.reason.message
    : String(event.reason)

  // Avoid showing duplicate messages for axios errors (already handled in request.ts)
  if (!message.includes('Request failed') && !message.includes('Network Error')) {
    ElMessage.error(message || '发生了未知错误')
  }
  // Prevent default browser error logging
  event.preventDefault()
})

// Handle global JS errors
window.addEventListener('error', (event) => {
  console.error('[Global Error]', event.error)
})

app.mount('#app')
