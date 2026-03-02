<template>
  <div class="school-list">
    <PageHeader title="学校管理">
      <template #actions>
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          创建学校
        </el-button>
      </template>
    </PageHeader>

    <el-card>
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="搜索">
          <el-input
            v-model="searchForm.search"
            placeholder="输入学校名称"
            clearable
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
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
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import PageHeader from '@/components/common/PageHeader.vue'
import DataTable from '@/components/common/DataTable.vue'
import SchoolForm from './SchoolForm.vue'
import { getSchools, deleteSchool } from '@/api/school'
import type { School } from '@/types/school'

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

// 表格列配置
const columns = [
  { prop: 'id', label: 'ID', width: 80 },
  { prop: 'name', label: '学校名称', minWidth: 200 },
  {
    prop: 'principal',
    label: '校长',
    minWidth: 150,
    formatter: (row: School) => row.principal?.nickname || '-'
  },
  { prop: 'info', label: '简介', minWidth: 200, showOverflowTooltip: true },
  { prop: 'created_at', label: '创建时间', width: 180 }
]

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
    ElMessage.error('加载学校列表失败')
    console.error(error)
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
      `确定要删除学校"${school.name}"吗？删除后将同时删除该学校下的所有班级、教师和学生数据。`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    await deleteSchool(school.id)
    ElMessage.success('删除成功')
    loadSchools()
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error('删除失败')
      console.error(error)
    }
  }
}

// 表单提交成功
function handleFormSuccess() {
  formVisible.value = false
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
