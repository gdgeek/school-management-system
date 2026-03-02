<template>
  <div class="student-list">
    <PageHeader title="学生管理">
      <template #actions>
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          添加学生
        </el-button>
      </template>
    </PageHeader>

    <el-card>
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="搜索">
          <el-input
            v-model="searchForm.search"
            placeholder="输入学生姓名"
            clearable
            @clear="handleSearch"
          />
        </el-form-item>
        <el-form-item label="学校">
          <el-select
            v-model="searchForm.school_id"
            placeholder="选择学校"
            clearable
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
        <el-form-item label="班级">
          <el-select
            v-model="searchForm.class_id"
            placeholder="选择班级"
            clearable
            @change="handleSearch"
          >
            <el-option
              v-for="cls in classes"
              :key="cls.id"
              :label="cls.name"
              :value="cls.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>

      <DataTable
        :data="students"
        :columns="columns"
        :loading="loading"
        :pagination="pagination"
        @page-change="handlePageChange"
        @delete="handleDelete"
      />
    </el-card>

    <StudentForm
      v-model:visible="formVisible"
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
import StudentForm from './StudentForm.vue'
import { getStudents, deleteStudent } from '@/api/student'
import { getSchools } from '@/api/school'
import { getClasses } from '@/api/class'
import type { Student } from '@/types/student'
import type { School } from '@/types/school'
import type { Class } from '@/types/class'

// 搜索表单
const searchForm = reactive({
  search: '',
  school_id: undefined as number | undefined,
  class_id: undefined as number | undefined
})

// 列表数据
const students = ref<Student[]>([])
const schools = ref<School[]>([])
const classes = ref<Class[]>([])
const loading = ref(false)
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格列配置
const columns = [
  { prop: 'id', label: 'ID', width: 80 },
  {
    prop: 'user',
    label: '学生姓名',
    minWidth: 150,
    formatter: (row: Student) => row.user?.nickname || '-'
  },
  {
    prop: 'user.username',
    label: '用户名',
    minWidth: 150,
    formatter: (row: Student) => row.user?.username || '-'
  },
  {
    prop: 'class',
    label: '所属班级',
    minWidth: 150,
    formatter: (row: Student) => row.class?.name || '-'
  },
  { prop: 'created_at', label: '添加时间', width: 180 }
]

// 表单相关
const formVisible = ref(false)

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
async function loadClasses(schoolId?: number) {
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
  searchForm.class_id = undefined
  if (searchForm.school_id) {
    loadClasses(searchForm.school_id)
  } else {
    classes.value = []
  }
  handleSearch()
}

// 加载学生列表
async function loadStudents() {
  try {
    loading.value = true
    const response = await getStudents({
      page: pagination.page,
      page_size: pagination.pageSize,
      search: searchForm.search || undefined,
      class_id: searchForm.class_id,
      school_id: searchForm.school_id
    })
    students.value = response.items
    pagination.total = response.pagination.total
  } catch (error) {
    ElMessage.error('加载学生列表失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

// 搜索
function handleSearch() {
  pagination.page = 1
  loadStudents()
}

// 重置
function handleReset() {
  searchForm.search = ''
  searchForm.school_id = undefined
  searchForm.class_id = undefined
  classes.value = []
  pagination.page = 1
  loadStudents()
}

// 分页变化
function handlePageChange(page: number) {
  pagination.page = page
  loadStudents()
}

// 创建
function handleCreate() {
  formVisible.value = true
}

// 删除
async function handleDelete(student: Student) {
  try {
    await ElMessageBox.confirm(
      `确定要移除学生"${student.user?.nickname}"吗？`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    await deleteStudent(student.id)
    ElMessage.success('删除成功')
    loadStudents()
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
  loadStudents()
}

onMounted(() => {
  loadSchools()
  loadStudents()
})
</script>

<style scoped>
.student-list {
  padding: 20px;
}
</style>
