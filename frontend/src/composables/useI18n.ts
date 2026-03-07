import { useI18n as useVueI18n } from 'vue-i18n'

/**
 * 多语言 composable
 * 简化组件中的多语言使用
 */
export function useI18n() {
  const { t, locale } = useVueI18n()
  
  return {
    t,
    locale,
    // 常用翻译快捷方式
    common: {
      confirm: () => t('common.confirm'),
      cancel: () => t('common.cancel'),
      save: () => t('common.save'),
      edit: () => t('common.edit'),
      delete: () => t('common.delete'),
      create: () => t('common.create'),
      search: () => t('common.search'),
      reset: () => t('common.reset'),
      submit: () => t('common.submit'),
      back: () => t('common.back'),
      close: () => t('common.close'),
      loading: () => t('common.loading'),
      noData: () => t('common.noData'),
      actions: () => t('common.actions'),
      success: () => t('common.success'),
      failed: () => t('common.failed'),
    },
  }
}
