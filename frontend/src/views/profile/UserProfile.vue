<template>
  <div class="user-profile">
    <PageHeader title="个人信息" />

    <el-card v-loading="loading">
      <template v-if="user">
        <div class="profile-header">
          <el-avatar :size="80">
            {{ user.nickname?.charAt(0) }}
          </el-avatar>
          <div class="profile-info">
            <h2>{{ user.nickname }}</h2>
            <p class="username">@{{ user.username }}</p>
          </div>
        </div>

        <el-divider />

        <div class="detail-section">
          <h3>基本信息</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="用户ID">{{ user.id }}</el-descriptions-item>
            <el-descriptions-item label="用户名">{{ user.username }}</el-descriptions-item>
            <el-descriptions-item label="昵称">{{ user.nickname }}</el-descriptions-item>
            <el-descriptions-item label="邮箱">{{ user.email || '-' }}</el-descriptions-item>
            <el-descriptions-item label="角色" :span="2">
              <el-tag
                v-for="role in user.roles"
                :key="role"
                type="success"
                style="margin-right: 8px"
              >
                {{ getRoleLabel(role) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="注册时间" :span="2">
              {{ user.created_at }}
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <div class="detail-section" v-if="isTeacher || isStudent">
          <h3>我的班级</h3>
          <el-table :data="myClasses" border>
            <el-table-column prop="id" label="班级ID" width="100" />
            <el-table-column prop="name" label="班级名称" min-width="200" />
            <el-table-column prop="school.name" label="所属学校" min-width="200" />
            <el-table-column label="身份" width="100">
              <template #default="{ row }">
                <el-tag :type="row.role === 'teacher' ? 'success' : 'primary'">
                  {{ row.role === 'teacher' ? '教师' : '学生' }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <div class="detail-section">
          <h3>我的小组</h3>
          <el-table :data="myGroups" border>
            <el-table-column prop="id" label="小组ID" width="100" />
            <el-table-column prop="name" label="小组名称" min-width="200" />
            <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
            <el-table-column prop="member_count" label="成员数" width="100" />
            <el-table-column label="操作" width="120">
              <template #default="{ row }">
                <el-button
                  type="primary"
                  size="small"
                  @click="handleViewGroup(row)"
                >
                  查看
                </el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </template>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useAuthStore } from '@/stores/auth'
import PageHeader from '@/components/common/PageHeader.vue'
import { getTeachers } from '@/api/teacher'
import { getStudents } from '@/api/student'
import { getGroups } from '@/api/group'
import { getClasses } from '@/api/class'
import type { Group } from '@/types/group'
import type { Class } from '@/types/class'

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)
const isTeacher = computed(() => authStore.isTeacher)
const isStudent = computed(() => authStore.isStudent)

const loading = ref(false)
const myClasses = ref<Array<Class & { role: string }>>([])
const myGroups = ref<Group[]>([])

// 获取角色标签
function getRoleLabel(role: string): string {
  const roleMap: Record<string, string> = {
    admin: '系统管理员',
    school_admin: '学校管理员',
    teacher: '教师',
    student: '学生'
  }
  return roleMap[role] || role
}

// 加载我的班级
async function loadMyClasses() {
  if (!user.value) return

  try {
    // 如果是教师，加载教师班级
    if (isTeacher.value) {
      const response = await getTeachers({
        page: 1,
        page_size: 100
      })
      const teacherClasses = response.items
        .filter(t => t.user_id === user.value?.id)
        .map(t => ({
          ...t.class!,
          role: 'teacher'
        }))
      myClasses.value.push(...teacherClasses)
    }

    // 如果是学生，加载学生班级
    if (isStudent.value) {
      const response = await getStudents({
        page: 1,
        page_size: 100
      })
      const studentClasses = response.items
        .filter(s => s.user_id === user.value?.id)
        .map(s => ({
          ...s.class!,
          role: 'student'
        }))
      myClasses.value.push(...studentClasses)
    }

    // 加载完整的班级信息
    const classIds = myClasses.value.map(c => c.id)
    if (classIds.length > 0) {
      const classesResponse = await getClasses({ page: 1, page_size: 100 })
      myClasses.value = myClasses.value.map(c => {
        const fullClass = classesResponse.items.find(fc => fc.id === c.id)
        return fullClass ? { ...fullClass, role: c.role } : c
      })
    }
  } catch (error) {
    console.error('加载班级信息失败:', error)
  }
}

// 加载我的小组
async function loadMyGroups() {
  if (!user.value) return

  try {
    const response = await getGroups({
      page: 1,
      page_size: 100
    })
    // 这里简化处理，实际应该有专门的API获取用户的小组
    myGroups.value = response.items.filter(g => g.creator_id === user.value?.id)
  } catch (error) {
    console.error('加载小组信息失败:', error)
  }
}

// 查看小组
function handleViewGroup(group: Group) {
  router.push({ name: 'GroupDetail', params: { id: group.id } })
}

// 加载数据
async function loadData() {
  loading.value = true
  try {
    await Promise.all([
      loadMyClasses(),
      loadMyGroups()
    ])
  } catch (error) {
    ElMessage.error('加载数据失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.user-profile {
  padding: 20px;
}

.profile-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 24px;
}

.profile-info h2 {
  margin: 0 0 8px 0;
  font-size: 24px;
  font-weight: 600;
}

.profile-info .username {
  margin: 0;
  color: #909399;
  font-size: 14px;
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
