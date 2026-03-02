<template>
  <div class="class-list">
    <PageHeader title="班级管理">
      <template #actions>
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          创建班级
        </el-button>
      </template>
    </PageHeader>

    <el-card>
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="搜索">
          <el-input
            v-model="searchForm.search"
            placeholder="输入班级名称"
            clearable
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item label="学校">
          <el-select
            v-model="searchForm.school_id"
            placeholder="选择学校"
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
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
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
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import PageHeader from '@/components/common/PageHeader.vue'
import DataTable from '@/components/common/DataTable.vue'
import ClassForm from './ClassForm.vue'
import { getClasses, deleteClass } from '@/api/class'
import { getSchools } from '@/api/school'
import type { Class } from '@/types/class'
import type { School } from '@/types/school'

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

// 表格列配置
const columns = [
  { prop: 'id', label: 'ID', width: 80 },
  { prop: 'name', label: '班级名称', minWidth: 200 },
  {
    prop: 'school',
    label: '所属学校',
    minWidth: 150,
    formatter: (row: Class) => row.school?.name || '-'
  },
  { prop: 'info', label: '简介', minWidth: 200, showOverflowTooltip: true },
  { prop: 'created_at', label: '创建时间', width: 180 }
]

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
    ElMessage.error('加载班级列表失败')
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
    await ElMessageBox.confirm(
      `确定要删除班级"${classData.name}"吗？删除后将同时删除该班级下的所有教师和学生数据。`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    await deleteClass(classData.id)
    ElMessage.success('删除成功')
    loadClasses()
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
