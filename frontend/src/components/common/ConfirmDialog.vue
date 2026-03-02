<template>
  <el-dialog
    v-model="visible"
    :title="title"
    width="400px"
    :close-on-click-modal="false"
    @close="handleCancel"
  >
    <div class="confirm-content">
      <el-icon class="confirm-icon" :class="iconClass">
        <component :is="iconComponent" />
      </el-icon>
      <p class="confirm-message">{{ message }}</p>
    </div>

    <template #footer>
      <el-button @click="handleCancel">{{ cancelText }}</el-button>
      <el-button :type="confirmType" :loading="loading" @click="handleConfirm">
        {{ confirmText }}
      </el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { WarningFilled, InfoFilled, CircleCheckFilled } from '@element-plus/icons-vue'

interface Props {
  modelValue: boolean
  title?: string
  message: string
  confirmText?: string
  cancelText?: string
  type?: 'warning' | 'danger' | 'info' | 'success'
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '确认',
  confirmText: '确认',
  cancelText: '取消',
  type: 'warning',
  loading: false
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
  cancel: []
}>()

const visible = ref(props.modelValue)

watch(() => props.modelValue, (val) => { visible.value = val })
watch(visible, (val) => { emit('update:modelValue', val) })

const confirmType = computed(() => props.type === 'danger' ? 'danger' : 'primary')

const iconComponent = computed(() => {
  if (props.type === 'success') return CircleCheckFilled
  if (props.type === 'info') return InfoFilled
  return WarningFilled
})

const iconClass = computed(() => ({
  'icon-warning': props.type === 'warning' || props.type === 'danger',
  'icon-info': props.type === 'info',
  'icon-success': props.type === 'success'
}))

function handleConfirm() {
  emit('confirm')
}

function handleCancel() {
  visible.value = false
  emit('cancel')
}
</script>

<style scoped>
.confirm-content {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 8px 0;
}

.confirm-icon {
  font-size: 24px;
  flex-shrink: 0;
  margin-top: 2px;
}

.icon-warning { color: #e6a23c; }
.icon-info { color: #409eff; }
.icon-success { color: #67c23a; }

.confirm-message {
  margin: 0;
  font-size: 14px;
  color: #606266;
  line-height: 1.6;
}
</style>
