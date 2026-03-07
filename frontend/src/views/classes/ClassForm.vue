<template>
  <FormDialog
    v-model:visible="dialogVisible"
    :title="mode === 'create' ? '创建班级' : '编辑班级'"
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
      <el-form-item label="所属学校" prop="school_id">
        <el-select
          v-model="formData.school_id"
          placeholder="请选择学校"
          style="width: 100%"
        >
          <el-option
            v-for="school in schools"
            :key="school.id"
            :label="school.name"
            :value="school.id"
          />
        </el-select>
      </el-form-item>

      <el-form-item label="班级名称" prop="name">
        <el-input
          v-model="formData.name"
          placeholder="请输入班级名称"
          maxlength="100"
          show-word-limit
        />
      </el-form-item>

      <el-form-item label="班级简介" prop="info">
        <el-input
          v-model="formData.info"
          type="textarea"
          :rows="4"
          placeholder="请输入班级简介"
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
import { createClass, updateClass } from '@/api/class'
import { getSchools } from '@/api/school'
import type { Class, ClassFormData } from '@/types/class'
import type { School } from '@/types/school'
import { useFormErrors } from '@/composables/useFormErrors'

interface Props {
  visible: boolean
  classData?: Class
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
const schools = ref<School[]>([])
const { applyErrors, clearErrors } = useFormErrors(formRef)

const formData = reactive<ClassFormData>({
  school_id: null as any,
  name: '',
  info: ''
})

const rules: FormRules = {
  school_id: [
    { required: true, message: '请选择学校', trigger: 'change' },
    { 
      validator: (_rule, value, callback) => {
        if (!value || value === 0) {
          callback(new Error('请选择学校'))
        } else {
          callback()
        }
      }, 
      trigger: 'change' 
    }
  ],
  name: [
    { required: true, message: '请输入班级名称', trigger: 'blur' },
    { min: 2, max: 100, message: '长度在 2 到 100 个字符', trigger: 'blur' }
  ]
}

// 加载学校列表
async function loadSchools() {
  try {
    const response = await getSchools({ page: 1, page_size: 1000 })
    schools.value = response.items
  } catch (error: any) {
    // 忽略请求去重导致的取消错误
    if (error?.code !== 'ERR_CANCELED') {
      console.error('加载学校列表失败:', error)
    }
  }
}

// 监听班级数据变化，填充表单
watch(
  () => props.classData,
  (classData) => {
    if (classData && props.mode === 'edit') {
      formData.school_id = classData.school_id
      formData.name = classData.name
      formData.info = classData.info || ''
    } else {
      resetForm()
    }
  },
  { immediate: true }
)

// 重置表单
function resetForm() {
  formData.school_id = null as any
  formData.name = ''
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
      await createClass(formData)
      ElMessage.success('创建成功')
    } else if (props.classData) {
      await updateClass(props.classData.id, formData)
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

// 监听对话框打开时加载学校列表
watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      loadSchools()
    }
  }
)
</script>
