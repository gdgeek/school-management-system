<template>
  <div class="school-list">
    <PageHeader :title="$t('school.title')">
      <template #actions>
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          {{ $t('school.create') }}
        </el-button>
      </template>
    </PageHeader>

    <el-card>
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item :label="$t('common.search')">
          <el-input
            v-model="searchForm.search"
            :placeholder="$t('school.searchPlaceholder')"
            clearable
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">{{ $t('common.search') }}</el-button>
          <el-button @click="handleReset">{{ $t('common.reset') }}</el-button>
        </el-form-item>
      </el-form>

      <DataTable
        :data="schools"
        :columns="columns"
        :loading="loading"
        :pagination="pagination"
        @page-change="handlePageChange"
        @edit="handleEdit"
        @delete="handleDelete"
      />
    </el-card>

    <SchoolForm
      v-model:visible="formVisible"
      :school="currentSchool"
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
import SchoolForm from './SchoolForm.vue'
import { getSchools, deleteSchool } from '@/api/school'
import { invalidateCache } from '@/utils/request'
import type { School } from '@/types/school'

const { t } = useI18n()

// 搜索表单
const searchForm = reactive({
  search: ''
})

// 列表数据
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
  { prop: 'name', label: t('school.name'), minWidth: 200 },
  {
    prop: 'principal',
    label: t('school.principal'),
    minWidth: 150,
    formatter: (row: School) => row.principal?.nickname || row.principal?.username || '-'
  },
  {
    prop: 'info',
    label: t('school.info'),
    minWidth: 200,
    showOverflowTooltip: true,
    formatter: (row: School) => {
      if (!row.info || (Array.isArray(row.info) && row.info.length === 0)) return '-'
      if (typeof row.info === 'object' && 'description' in row.info) return row.info.description
      if (typeof row.info === 'string') return row.info
      return JSON.stringify(row.info)
    }
  },
  { prop: 'created_at', label: t('common.createdAt'), width: 180 }
])

// 表单相关
const formVisible = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const currentSchool = ref<School | undefined>()

// 加载学校列表
async function loadSchools() {
  try {
    loading.value = true
    const response = await getSchools({
      page: pagination.page,
      page_size: pagination.pageSize,
      search: searchForm.search || undefined
    })
    schools.value = response.items
    pagination.total = response.pagination.total
  } catch (error) {
    console.error('[SchoolList] 加载失败:', error)
    ElMessage.error(t('common.failed'))
  } finally {
    loading.value = false
  }
}

// 搜索
function handleSearch() {
  pagination.page = 1
  loadSchools()
}

// 重置
function handleReset() {
  searchForm.search = ''
  pagination.page = 1
  loadSchools()
}

// 分页变化
function handlePageChange(page: number) {
  pagination.page = page
  loadSchools()
}

// 创建
function handleCreate() {
  formMode.value = 'create'
  currentSchool.value = undefined
  formVisible.value = true
}

// 编辑
function handleEdit(school: School) {
  formMode.value = 'edit'
  currentSchool.value = school
  formVisible.value = true
}

// 删除
async function handleDelete(school: School) {
  try {
    await ElMessageBox.confirm(
      t('school.deleteConfirm', { name: school.name }),
      t('common.deleteConfirm'),
      {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        type: 'warning'
      }
    )
    
    await deleteSchool(school.id)
    ElMessage.success(t('school.deleteSuccess'))
    invalidateCache('/schools')
    loadSchools()
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
  invalidateCache('/schools')
  loadSchools()
}

onMounted(() => {
  loadSchools()
})
</script>

<style scoped>
.school-list {
  padding: 20px;
}
</style>
