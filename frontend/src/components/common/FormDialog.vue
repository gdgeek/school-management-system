<template>
  <el-dialog
    v-model="visible"
    :title="title"
    :width="width"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <el-form
      ref="formRef"
      :model="formData"
      :rules="rules"
      :label-width="labelWidth"
    >
      <slot :form="formData"></slot>
    </el-form>
    
    <template #footer>
      <el-button @click="handleCancel">Cancel</el-button>
      <el-button type="primary" :loading="loading" @click="handleSubmit">
        {{ submitText }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'

interface Props {
  modelValue: boolean
  title: string
  formData: Record<string, any>
  rules?: FormRules
  width?: string | number
  labelWidth?: string | number
  submitText?: string
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  width: '600px',
  labelWidth: '100px',
  submitText: 'Submit',
  loading: false
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [data: Record<string, any>]
  cancel: []
}>()

const visible = ref(props.modelValue)
const formRef = ref<FormInstance>()

watch(() => props.modelValue, (val) => {
  visible.value = val
})

watch(visible, (val) => {
  emit('update:modelValue', val)
})

async function handleSubmit() {
  if (!formRef.value) return
  
  try {
    await formRef.value.validate()
    emit('submit', props.formData)
  } catch (error) {
    console.error('Form validation failed:', error)
  }
}

function handleCancel() {
  visible.value = false
  emit('cancel')
}

function handleClose() {
  formRef.value?.resetFields()
  emit('cancel')
}

defineExpose({
  resetFields: () => formRef.value?.resetFields(),
  validate: () => formRef.value?.validate()
})
</script>
