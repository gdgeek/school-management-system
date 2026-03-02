<template>
  <FormDialog
    v-model:visible="dialogVisible"
    :title="mode === 'create' ? '创建学校' : '编辑学校'"
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
      <el-form-item label="学校名称" prop="name">
        <el-input
          v-model="formData.name"
          placeholder="请输入学校名称"
          maxlength="100"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="学校图片" prop="image_id">
        <ImageUpload
          v-model="formData.image_id"
          :image-url="imageUrl"
        />
      </el-form-item>

      <el-form-item label="校长" prop="principal_id">
        <el-input
          v-model="formData.principal_id"
          type="number"
          placeholder="请输入校长用户ID"
        />
      </el-form-item>

      <el-form-item label="学校简介" prop="info">
        <el-input
          v-model="formData.info"
          type="textarea"
          :rows="4"
          placeholder="请输入学校简介"
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
import ImageUpload from '@/components/common/ImageUpload.vue'
import { createSchool, updateSchool } from '@/api/school'
import type { School, SchoolFormData } from '@/types/school'

interface Props {
  visible: boolean
  school?: School
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

const formData = reactive<SchoolFormData>({
  name: '',
  image_id: undefined,
  info: '',
  principal_id: undefined
})

const imageUrl = ref<string>()

const rules: FormRules = {
  name: [
    { required: true, message: '请输入学校名称', trigger: 'blur' },
    { min: 2, max: 100, message: '长度在 2 到 100 个字符', trigger: 'blur' }
  ]
}

// 监听学校数据变化，填充表单
watch(
  () => props.school,
  (school) => {
    if (school && props.mode === 'edit') {
      formData.name = school.name
      formData.image_id = school.image_id
      formData.info = school.info || ''
      formData.principal_id = school.principal_id
      imageUrl.value = school.image_url
    } else {
      resetForm()
    }
  },
  { immediate: true }
)

// 重置表单
function resetForm() {
  formData.name = ''
  formData.image_id = undefined
  formData.info = ''
  formData.principal_id = undefined
  imageUrl.value = undefined
  formRef.value?.clearValidate()
}

// 提交表单
async function handleSubmit() {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    loading.value = true

    if (props.mode === 'create') {
      await createSchool(formData)
      ElMessage.success('创建成功')
    } else if (props.school) {
      await updateSchool(props.school.id, formData)
      ElMessage.success('更新成功')
    }

    emit('success')
    resetForm()
  } catch (error: any) {
    if (error !== false) {
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
