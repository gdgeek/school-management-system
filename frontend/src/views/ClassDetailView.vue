<template>
  <div class="class-detail">
    <PageHeader :title="classData?.name || '班级详情'">
      <template #actions>
        <el-button @click="handleBack">返回</el-button>
      </template>
    </PageHeader>

    <el-card v-loading="loading">
      <template v-if="classData">
        <div class="detail-section">
          <h3>基本信息</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="班级ID">{{ classData.id }}</el-descriptions-item>
            <el-descriptions-item label="班级名称">{{ classData.name }}</el-descriptions-item>
            <el-descriptions-item label="所属学校">
              {{ classData.school?.name || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">
              {{ classData.created_at }}
            </el-descriptions-item>
            <el-descriptions-item label="简介" :span="2">
              {{ classData.info || '-' }}
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <div class="detail-section">
          <h3>教师列表</h3>
          <el-table :data="teachers" border>
            <el-table-column prop="user.id" label="用户ID" width="100" />
            <el-table-column prop="user.nickname" label="姓名" min-width="150" />
            <el-table-column prop="user.username" label="用户名" min-width="150" />
            <el-table-column prop="created_at" label="添加时间" width="180" />
          </el-table>
        </div>

        <div class="detail-section">
          <h3>学生列表</h3>
          <el-table :data="students" border>
            <el-table-column prop="user.id" label="用户ID" width="100" />
            <el-table-column prop="user.nickname" label="姓名" min-width="150" />
            <el-table-column prop="user.username" label="用户名" min-width="150" />
            <el-table-column prop="created_at" label="添加时间" width="180" />
          </el-table>
        </div>
      </template>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import { getClass, getClassTeachers, getClassStudents } from '@/api/class'
import type { Class } from '@/types/class'
import type { Teacher } from '@/types/teacher'
import type { Student } from '@/types/student'

const router = useRouter()
const route = useRoute()

const classData = ref<Class>()
const teachers = ref<Teacher[]>([])
const students = ref<Student[]>([])
const loading = ref(false)

async function loadClass() {
  const id = Number(route.params.id)
  if (!id) return

  try {
    loading.value = true
    classData.value = await getClass(id)
  } catch (error) {
    ElMessage.error('加载班级详情失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

async function loadTeachers() {
  const id = Number(route.params.id)
  if (!id) return

  try {
    const response = await getClassTeachers(id, { page: 1, page_size: 100 })
    teachers.value = response.items || []
  } catch (error) {
    console.error('加载教师列表失败:', error)
  }
}

async function loadStudents() {
  const id = Number(route.params.id)
  if (!id) return

  try {
    const response = await getClassStudents(id, { page: 1, page_size: 100 })
    students.value = response.items || []
  } catch (error) {
    console.error('加载学生列表失败:', error)
  }
}

function handleBack() {
  router.back()
}

onMounted(() => {
  loadClass()
  loadTeachers()
  loadStudents()
})
</script>

<style scoped>
.class-detail {
  padding: 20px;
}

.detail-section {
  margin-bottom: 24px;
}

.detail-section h3 {
  margin-bottom: 16px;
  font-size: 16px;
  font-weight: 600;
}
</style>
