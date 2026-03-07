<template>
  <el-dialog
    v-model="dialogVisible"
    :title="title"
    :width="width"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <slot></slot>
    
    <template #footer>
      <el-button @click="handleCancel">取消</el-button>
      <el-button type="primary" :loading="loading" @click="handleConfirm">
        {{ submitText }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  visible?: boolean
  modelValue?: boolean
  title: string
  width?: string | number
  submitText?: string
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  visible: undefined,
  modelValue: undefined,
  width: '600px',
  submitText: '确定',
  loading: false
})

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'update:modelValue': [value: boolean]
  confirm: []
  cancel: []
  submit: []
}>()

// 支持 v-model:visible 和 v-model 两种用法
const dialogVisible = computed({
  get: () => props.visible ?? props.modelValue ?? false,
  set: (val: boolean) => {
    emit('update:visible', val)
    emit('update:modelValue', val)
  }
})

function handleConfirm() {
  emit('confirm')
  emit('submit')
}

function handleCancel() {
  dialogVisible.value = false
  emit('cancel')
}

function handleClose() {
  emit('cancel')
}
</script>
