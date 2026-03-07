import { ref } from 'vue'
import type { FormInstance } from 'element-plus'
import { ApiError } from '../utils/request'

/**
 * 统一处理后端 422 字段级验证错误，映射到 Element Plus 表单
 *
 * 用法：
 *   const { applyErrors, clearErrors } = useFormErrors(formRef)
 *
 *   try {
 *     await createSchool(data)
 *   } catch (e) {
 *     if (!applyErrors(e)) throw e  // 非验证错误继续抛出
 *   }
 */
export function useFormErrors(formRef: { value: FormInstance | undefined }) {
  const serverErrors = ref<Record<string, string>>({})

  /**
   * 尝试将错误应用到表单字段
   * @returns true 如果是 422 验证错误并已处理，false 否则
   */
  function applyErrors(error: unknown): boolean {
    if (!(error instanceof ApiError) || error.status !== 422) {
      return false
    }

    serverErrors.value = error.errors ?? {}

    // 将后端 errors 注入到 Element Plus 表单字段
    if (formRef.value && error.errors) {
      const errMap = error.errors as Record<string, string>
      ;(formRef.value as any).fields?.forEach((formField: any) => {
        const fieldName: string = formField.prop
        if (fieldName && errMap[fieldName]) {
          formField.validateState = 'error'
          formField.validateMessage = errMap[fieldName]
        }
      })
    }

    return true
  }

  function clearErrors() {
    serverErrors.value = {}
    formRef.value?.clearValidate()
  }

  return { serverErrors, applyErrors, clearErrors }
}
