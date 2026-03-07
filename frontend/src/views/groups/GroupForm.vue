<template>
  <FormDialog
    v-model:visible="dialogVisible"
    :title="mode === 'create' ? '创建小组' : '编辑小组'"
    :loading="loading"
    @confirm="handleSubmit"
    @cancel="handleCancel"
  >
    <el-form
      ref="formRef"
      :model="formData"
      :rules="rules"
      label-width="100px"
    >
      <el-form-item label="小组名称" prop="name">
        <el-input
          v-model="formData.name"
          placeholder="请输入小组名称"
          maxlength="100"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="小组描述" prop="description">
        <el-input
          v-model="formData.description"
          type="textarea"
          :rows="3"
          placeholder="请输入小组描述"
          maxlength="200"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="详细信息" prop="info">
        <el-input
          v-model="formData.info"
          type="textarea"
          :rows="4"
          placeholder="请输入详细信息"
          maxlength="500"
          show-word-limit
        />
      </el-form-item>
    </el-form>
  </FormDialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import FormDialog from '@/components/common/FormDialog.vue'
import { createGroup, updateGroup } from '@/api/group'
import type { Group, GroupFormData } from '@/types/group'
import { useFormErrors } from '@/composables/useFormErrors'

interface Props {
  visible: boolean
  group?: Group
  mode: 'create' | 'edit'
}

const props = withDefaults(defineProps<Props>(), {
  visible: false,
  mode: 'create'
})

const emit = defineEmits<{
  'update:visible': [value: boolean]
  success: []
}>()

const dialogVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

const formRef = ref<FormInstance>()
const loading = ref(false)
const { applyErrors, clearErrors } = useFormErrors(formRef)

const formData = reactive<GroupFormData>({
  name: '',
  description: '',
  info: ''
})

const rules: FormRules = {
  name: [
    { required: true, message: '请输入小组名称', trigger: 'blur' },
    { min: 2, max: 100, message: '长度在 2 到 100 个字符', trigger: 'blur' }
  ]
}

// 监听小组数据变化，填充表单
watch(
  () => props.group,
  (group) => {
    if (group && props.mode === 'edit') {
      formData.name = group.name
      formData.description = group.description || ''
      formData.info = group.info || ''
    } else {
      resetForm()
    }
  },
  { immediate: true }
)

// 重置表单
function resetForm() {
  formData.name = ''
  formData.description = ''
  formData.info = ''
  clearErrors()
}

// 提交表单
async function handleSubmit() {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    loading.value = true

    if (props.mode === 'create') {
      await createGroup(formData)
      ElMessage.success('创建成功')
    } else if (props.group) {
      await updateGroup(props.group.id, formData)
      ElMessage.success('更新成功')
    }

    emit('success')
    resetForm()
  } catch (error: unknown) {
    if (error !== false && !applyErrors(error)) {
      ElMessage.error(props.mode === 'create' ? '创建失败' : '更新失败')
      console.error(error)
    }
  } finally {
    loading.value = false
  }
}

// 取消
function handleCancel() {
  resetForm()
  emit('update:visible', false)
}
</script>
