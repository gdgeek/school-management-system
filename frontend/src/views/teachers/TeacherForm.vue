<template>
  <FormDialog
    v-model:visible="dialogVisible"
    title="添加教师"
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
          @change="handleSchoolChange"
        >
          <el-option
            v-for="school in schools"
            :key="school.id"
            :label="school.name"
            :value="school.id"
          />
        </el-select>
      </el-form-item>

      <el-form-item label="所属班级" prop="class_id">
        <el-select
          v-model="formData.class_id"
          placeholder="请选择班级"
          style="width: 100%"
        >
          <el-option
            v-for="cls in classes"
            :key="cls.id"
            :label="cls.name"
            :value="cls.id"
          />
        </el-select>
      </el-form-item>

      <el-form-item label="教师" prop="user_id">
        <el-input
          :model-value="selectedUserDisplay"
          placeholder="请选择教师"
          readonly
          style="width: 100%; cursor: pointer"
          @click="userSelectVisible = true"
        >
          <template #append>
            <el-button @click="userSelectVisible = true">选择</el-button>
          </template>
        </el-input>
      </el-form-item>
    </el-form>

    <UserSelectDialog
      v-model:visible="userSelectVisible"
      @select="handleUserSelect"
    />
  </FormDialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import FormDialog from '@/components/common/FormDialog.vue'
import UserSelectDialog from '@/components/common/UserSelectDialog.vue'
import { createTeacher } from '@/api/teacher'
import { getSchools } from '@/api/school'
import { getClasses } from '@/api/class'
import type { TeacherFormData } from '@/types/teacher'
import type { School } from '@/types/school'
import type { Class } from '@/types/class'
import type { User } from '@/types/user'
import { useFormErrors } from '@/composables/useFormErrors'

interface Props {
  visible: boolean
}

const props = withDefaults(defineProps<Props>(), {
  visible: false
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
const classes = ref<Class[]>([])
const userSelectVisible = ref(false)
const selectedUser = ref<User | null>(null)
const { applyErrors, clearErrors } = useFormErrors(formRef)

const formData = reactive<TeacherFormData & { school_id?: number }>({
  user_id: 0,
  class_id: undefined,
  school_id: undefined
})

const selectedUserDisplay = computed(() => {
  if (selectedUser.value) {
    return `${selectedUser.value.nickname || selectedUser.value.username} (ID: ${selectedUser.value.id})`
  }
  return ''
})

const rules: FormRules = {
  school_id: [
    { required: true, message: '请选择学校', trigger: 'change' }
  ],
  class_id: [
    { required: true, message: '请选择班级', trigger: 'change' }
  ],
  user_id: [
    { required: true, message: '请选择教师', trigger: 'change' }
  ]
}

// 加载学校列表
async function loadSchools() {
  try {
    const response = await getSchools({ page: 1, page_size: 1000 })
    schools.value = response.items
  } catch (error) {
    console.error('加载学校列表失败:', error)
  }
}

// 加载班级列表
async function loadClasses(schoolId: number) {
  try {
    const response = await getClasses({
      page: 1,
      page_size: 1000,
      school_id: schoolId
    })
    classes.value = response.items
  } catch (error) {
    console.error('加载班级列表失败:', error)
  }
}

// 学校变化
function handleSchoolChange() {
  formData.class_id = undefined
  classes.value = []
  if (formData.school_id) {
    loadClasses(formData.school_id)
  }
}

// 用户选择
function handleUserSelect(user: User) {
  selectedUser.value = user
  formData.user_id = user.id
  userSelectVisible.value = false
}

// 重置表单
function resetForm() {
  formData.user_id = 0
  formData.class_id = undefined
  formData.school_id = undefined
  selectedUser.value = null
  classes.value = []
  clearErrors()
}

// 提交表单
async function handleSubmit() {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    loading.value = true

    await createTeacher({
      user_id: formData.user_id,
      class_id: formData.class_id
    })
    ElMessage.success('添加成功')

    emit('success')
    resetForm()
  } catch (error: unknown) {
    if (error !== false && !applyErrors(error)) {
      ElMessage.error('添加失败')
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

// 监听对话框打开，延迟加载学校列表避免与列表页重复请求
watch(() => props.visible, (newVal) => {
  if (newVal) {
    loadSchools()
  }
})
</script>

<style scoped>
</style>
