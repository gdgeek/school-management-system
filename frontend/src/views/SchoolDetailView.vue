<template>
  <div class="school-detail">
    <PageHeader :title="school?.name || '学校详情'">
      <template #actions>
        <el-button @click="handleBack">返回</el-button>
      </template>
    </PageHeader>

    <el-card v-loading="loading">
      <template v-if="school">
        <div class="detail-section">
          <h3>基本信息</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="学校ID">{{ school.id }}</el-descriptions-item>
            <el-descriptions-item label="学校名称">{{ school.name }}</el-descriptions-item>
            <el-descriptions-item label="校长">
              {{ school.principal?.nickname || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">
              {{ school.created_at }}
            </el-descriptions-item>
            <el-descriptions-item label="简介" :span="2">
              {{ school.info || '-' }}
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <div class="detail-section">
          <h3>班级列表</h3>
          <el-table :data="classes" border>
            <el-table-column prop="id" label="班级ID" width="100" />
            <el-table-column prop="name" label="班级名称" min-width="200" />
            <el-table-column prop="info" label="简介" min-width="200" show-overflow-tooltip />
            <el-table-column prop="created_at" label="创建时间" width="180" />
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
import { getSchool, getSchoolClasses } from '@/api/school'
import type { School } from '@/types/school'
import type { Class } from '@/types/class'

const router = useRouter()
const route = useRoute()

const school = ref<School>()
const classes = ref<Class[]>([])
const loading = ref(false)

async function loadSchool() {
  const id = Number(route.params.id)
  if (!id) return

  try {
    loading.value = true
    school.value = await getSchool(id)
  } catch (error) {
    ElMessage.error('加载学校详情失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

async function loadClasses() {
  const id = Number(route.params.id)
  if (!id) return

  try {
    const response = await getSchoolClasses(id, { page: 1, page_size: 100 })
    classes.value = response.items || []
  } catch (error) {
    console.error('加载班级列表失败:', error)
  }
}

function handleBack() {
  router.back()
}

onMounted(() => {
  loadSchool()
  loadClasses()
})
</script>

<style scoped>
.school-detail {
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
