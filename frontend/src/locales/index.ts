import { createI18n } from 'vue-i18n'
import zhCN from './zh-CN'
import en from './en'

export type MessageSchema = typeof zhCN

const savedLocale = localStorage.getItem('locale') || 'zh-CN'

const i18n = createI18n<[MessageSchema], 'zh-CN' | 'en'>({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'zh-CN',
  messages: {
    'zh-CN': zhCN,
    'en': en,
  },
})

export default i18n
