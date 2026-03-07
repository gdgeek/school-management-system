<template>
  <div class="class-list">
    <PageHeader :title="$t('class.title')">
      <template #actions>
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          {{ $t('class.create') }}
        </el-button>
      </template>
    </PageHeader>

    <el-card>
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item :label="$t('common.search')">
          <el-input
            v-model="searchForm.search"
            :placeholder="$t('class.searchPlaceholder')"
            clearable
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item :label="$t('class.school')">
          <el-select
            v-model="searchForm.school_id"
            :placeholder="$t('class.selectSchool')"
            clearable
            @change="handleSearch"
          >
            <el-option
              v-for="school in schools"
              :key="school.id"
              :label="school.name"
              :value="school.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">{{ $t('common.search') }}</el-button>
          <el-button @click="handleReset">{{ $t('common.reset') }}</el-button>
        </el-form-item>
      </el-form>

      <DataTable
        :data="classes"
        :columns="columns"
        :loading="loading"
        :pagination="pagination"
        @page-change="handlePageChange"
        @edit="handleEdit"
        @delete="handleDelete"
      />
    </el-card>

    <ClassForm
      v-model:visible="formVisible"
      :class-data="currentClass"
      :mode="formMode"
      @success="handleFormSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '@/components/common/PageHeader.vue'
import DataTable from '@/components/common/DataTable.vue'
import ClassForm from './ClassForm.vue'
import { getClasses, deleteClass } from '@/api/class'
import { getSchools } from '@/api/school'
import type { Class } from '@/types/class'
import type { School } from '@/types/school'

const { t } = useI18n()

// 搜索表单
const searchForm = reactive({
  search: '',
  school_id: undefined as number | undefined
})

// 列表数据
const classes = ref<Class[]>([])
const schools = ref<School[]>([])
const loading = ref(false)
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格列配置 - 使用计算属性以支持语言切换
const columns = computed(() => [
  { prop: 'id', label: 'ID', width: 80 },
  { prop: 'name', label: t('class.name'), minWidth: 200 },
  {
    prop: 'school',
    label: t('class.school'),
    minWidth: 150,
    formatter: (row: Class) => row.school?.name || '-'
  },
  {
    prop: 'info',
    label: t('class.info'),
    minWidth: 200,
    showOverflowTooltip: true,
    formatter: (row: Class) => {
      if (!row.info || (Array.isArray(row.info) && row.info.length === 0)) return '-'
      if (typeof row.info === 'object' && 'description' in row.info) return row.info.description
      if (typeof row.info === 'string') return row.info
      return '-'
    }
  },
  { prop: 'created_at', label: t('common.createdAt'), width: 180 }
])

// 表单相关
const formVisible = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const currentClass = ref<Class | undefined>()

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
async function loadClasses() {
  try {
    loading.value = true
    const response = await getClasses({
      page: pagination.page,
      page_size: pagination.pageSize,
      search: searchForm.search || undefined,
      school_id: searchForm.school_id
    })
    classes.value = response.items
    pagination.total = response.pagination.total
  } catch (error) {
    ElMessage.error(t('common.failed'))
    console.error(error)
  } finally {
    loading.value = false
  }
}

// 搜索
function handleSearch() {
  pagination.page = 1
  loadClasses()
}

// 重置
function handleReset() {
  searchForm.search = ''
  searchForm.school_id = undefined
  pagination.page = 1
  loadClasses()
}

// 分页变化
function handlePageChange(page: number) {
  pagination.page = page
  loadClasses()
}

// 创建
function handleCreate() {
  formMode.value = 'create'
  currentClass.value = undefined
  formVisible.value = true
}

// 编辑
function handleEdit(classData: Class) {
  formMode.value = 'edit'
  currentClass.value = classData
  formVisible.value = true
}

// 删除
async function handleDelete(classData: Class) {
  try {
    // 第一步：确认是否删除班级
    await ElMessageBox.confirm(
      t('class.deleteConfirm', { name: classData.name }),
      t('common.deleteConfirm'),
      {
        confirmButtonText: t('class.nextStep'),
        cancelButtonText: t('common.cancel'),
        type: 'warning'
      }
    )
    
    // 第二步：询问是否删除关联的小组
    let deleteGroups = false
    try {
      await ElMessageBox.confirm(
        t('class.deleteGroupsMessage'),
        t('class.deleteGroupsTitle'),
        {
          confirmButtonText: t('class.deleteGroupsConfirm'),
          cancelButtonText: t('class.keepGroups'),
          type: 'warning',
          distinguishCancelAndClose: true
        }
      )
      deleteGroups = true
    } catch (error) {
      // 用户选择保留小组或关闭对话框
      if (error === 'cancel') {
        deleteGroups = false
      } else {
        // 用户点击了关闭按钮，取消整个删除操作
        return
      }
    }
    
    await deleteClass(classData.id, deleteGroups)
    ElMessage.success(t('class.deleteSuccess'))
    loadClasses()
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error(t('common.failed'))
      console.error(error)
    }
  }
}

// 表单提交成功
function handleFormSuccess() {
  formVisible.value = false
  loadClasses()
}

onMounted(() => {
  loadSchools()
  loadClasses()
})
</script>

<style scoped>
.class-list {
  padding: 20px;
}
</style>
