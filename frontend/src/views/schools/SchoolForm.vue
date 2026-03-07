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

      <el-form-item label="学校管理员" prop="principal_id">
        <div class="principal-select">
          <el-input
            :model-value="principalDisplay"
            placeholder="点击选择学校管理员"
            readonly
            @click="showUserSelect = true"
          >
            <template #append>
              <el-button @click="showUserSelect = true">选择</el-button>
            </template>
          </el-input>
          <el-button
            v-if="formData.principal_id"
            link
            type="danger"
            @click="clearPrincipal"
            style="margin-left: 8px"
          >清除</el-button>
        </div>
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

    <UserSelectDialog
      v-model:visible="showUserSelect"
      @select="handleUserSelect"
    />
  </FormDialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import FormDialog from '@/components/common/FormDialog.vue'
import UserSelectDialog from '@/components/common/UserSelectDialog.vue'
import { createSchool, updateSchool } from '@/api/school'
import type { School, SchoolFormData } from '@/types/school'
import type { UserItem } from '@/api/user'
import { useFormErrors } from '@/composables/useFormErrors'

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
const showUserSelect = ref(false)
const selectedPrincipal = ref<UserItem | null>(null)
const { applyErrors, clearErrors } = useFormErrors(formRef)

const principalDisplay = computed(() => {
  if (selectedPrincipal.value) {
    return selectedPrincipal.value.nickname || selectedPrincipal.value.username
  }
  return ''
})

const formData = reactive<SchoolFormData>({
  name: '',
  info: '',
  principal_id: undefined
})

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
      formData.info = school.info || ''
      formData.principal_id = school.principal_id
      if (school.principal) {
        selectedPrincipal.value = {
          id: school.principal_id!,
          username: '',
          nickname: school.principal.nickname || null,
          email: null
        }
      } else {
        selectedPrincipal.value = null
      }
    } else {
      resetForm()
    }
  },
  { immediate: true }
)

// 重置表单
function resetForm() {
  formData.name = ''
  formData.info = ''
  formData.principal_id = undefined
  selectedPrincipal.value = null
  clearErrors()
}

function handleUserSelect(user: UserItem) {
  selectedPrincipal.value = user
  formData.principal_id = user.id
}

function clearPrincipal() {
  selectedPrincipal.value = null
  formData.principal_id = undefined
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
