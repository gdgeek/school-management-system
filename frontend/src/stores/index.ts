import { createPinia } from 'pinia'

const pinia = createPinia()

export default pinia

// 导出所有stores
export { useAuthStore } from './auth'
export { useAppStore } from './app'
