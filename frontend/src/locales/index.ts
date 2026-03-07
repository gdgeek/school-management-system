import { createI18n } from 'vue-i18n'
import zhCN from './zh-CN'
import zhTW from './zh-TW'
import en from './en'

export type MessageSchema = typeof zhCN

const savedLocale = localStorage.getItem('locale') || 'zh-CN'

const i18n = createI18n<[MessageSchema], 'zh-CN' | 'zh-TW' | 'en'>({
  legacy: false,
  locale: savedLocale,
  fallbackLocale: 'zh-CN',
  messages: {
    'zh-CN': zhCN,
    'zh-TW': zhTW,
    'en': en,
  },
})

export default i18n
