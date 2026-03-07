<template>
  <div class="group-detail">
    <PageHeader :title="group?.name || '小组详情'">
      <template #actions>
        <el-button @click="handleBack">返回</el-button>
        <el-button type="primary" @click="handleEdit">编辑</el-button>
      </template>
    </PageHeader>

    <el-card v-loading="loading">
      <template v-if="group">
        <!-- 组长信息 - 显著位置 -->
        <div class="creator-section" v-if="group.creator">
          <div class="creator-info">
            <el-avatar 
              :size="50" 
              :src="group.creator.avatar"
            >
              {{ group.creator.nickname?.charAt(0) || '?' }}
            </el-avatar>
            <div class="creator-details">
              <div class="creator-label">组长</div>
              <div class="creator-name">{{ group.creator.nickname }}</div>
              <div class="creator-username">@{{ group.creator.username }}</div>
            </div>
          </div>
        </div>

        <el-divider />

        <div class="detail-section">
          <h3>基本信息</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="小组ID">{{ group.id }}</el-descriptions-item>
            <el-descriptions-item label="小组名称">{{ group.name }}</el-descriptions-item>
            <el-descriptions-item label="成员数">
              {{ group.members?.length || 0 }}
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">
              {{ group.created_at }}
            </el-descriptions-item>
            <el-descriptions-item label="描述" :span="2">
              {{ group.description || '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="详细信息" :span="2">
              {{ group.info || '-' }}
            </el-descriptions-item>
          </el-descriptions>
        </div>

        <div class="detail-section">
          <div class="section-header">
            <h3>小组成员 ({{ group.members?.length || 0 }})</h3>
            <el-button type="primary" size="small" @click="handleAddMember">
              <el-icon><Plus /></el-icon>
              添加成员
            </el-button>
          </div>
          
          <el-table :data="group.members || []" border>
            <el-table-column label="头像" width="80">
              <template #default="{ row }">
                <el-avatar :size="40" :src="row.avatar">
                  {{ row.nickname?.charAt(0) || '?' }}
                </el-avatar>
              </template>
            </el-table-column>
            <el-table-column prop="id" label="用户ID" width="100" />
            <el-table-column prop="nickname" label="姓名" min-width="150" />
            <el-table-column prop="username" label="用户名" min-width="150" />
            <el-table-column label="角色" width="100">
              <template #default="{ row }">
                <el-tag v-if="row.id === group.creator_id" type="warning" size="small">
                  组长
                </el-tag>
                <el-tag v-else type="info" size="small">
                  成员
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button
                  v-if="row.id !== group.creator_id"
                  type="danger"
                  size="small"
                  @click="handleRemoveMember(row)"
                >
                  移除
                </el-button>
                <span v-else style="color: #999; font-size: 12px;">-</span>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </template>
    </el-card>

    <GroupForm
      v-model:visible="formVisible"
      :group="group"
      mode="edit"
      @success="handleFormSuccess"
    />

    <el-dialog
      v-model="memberDialogVisible"
      title="添加成员"
      width="400px"
    >
      <el-form :model="memberForm" label-width="80px">
        <el-form-item label="用户ID">
          <el-input
            v-model.number="memberForm.user_id"
            type="number"
            placeholder="请输入用户ID"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="memberDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitMember">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import PageHeader from '@/components/common/PageHeader.vue'
import GroupForm from './GroupForm.vue'
import { getGroup, addGroupMember, removeGroupMember } from '@/api/group'
import type { Group } from '@/types/group'

const router = useRouter()
const route = useRoute()

const group = ref<Group>()
const loading = ref(false)
const formVisible = ref(false)
const memberDialogVisible = ref(false)

const memberForm = reactive({
  user_id: 0
})

// 加载小组详情
async function loadGroup() {
  const id = Number(route.params.id)
  if (!id) return

  try {
    loading.value = true
    group.value = await getGroup(id)
  } catch (error) {
    ElMessage.error('加载小组详情失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

// 返回
function handleBack() {
  router.back()
}

// 编辑
function handleEdit() {
  formVisible.value = true
}

// 表单提交成功
function handleFormSuccess() {
  formVisible.value = false
  loadGroup()
}

// 添加成员
function handleAddMember() {
  memberForm.user_id = 0
  memberDialogVisible.value = true
}

// 提交添加成员
async function handleSubmitMember() {
  if (!memberForm.user_id || !group.value) {
    ElMessage.warning('请输入用户ID')
    return
  }

  try {
    await addGroupMember(group.value.id, memberForm.user_id)
    ElMessage.success('添加成功')
    memberDialogVisible.value = false
    loadGroup() // 重新加载小组详情（包含更新的成员列表）
  } catch (error) {
    ElMessage.error('添加失败')
    console.error(error)
  }
}

// 移除成员
async function handleRemoveMember(member: any) {
  if (!group.value) return

  try {
    await ElMessageBox.confirm(
      `确定要移除成员"${member.nickname}"吗？`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    await removeGroupMember(group.value.id, member.id)
    ElMessage.success('移除成功')
    loadGroup() // 重新加载小组详情（包含更新的成员列表）
  } catch (error: any) {
    if (error !== 'cancel') {
      ElMessage.error('移除失败')
      console.error(error)
    }
  }
}

onMounted(() => {
  loadGroup()
})
</script>

<style scoped>
.group-detail {
  padding: 20px;
}

.creator-section {
  margin-bottom: 20px;
}

.creator-info {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 8px;
  color: white;
}

.creator-details {
  flex: 1;
}

.creator-label {
  font-size: 12px;
  opacity: 0.9;
  margin-bottom: 4px;
}

.creator-name {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 2px;
}

.creator-username {
  font-size: 13px;
  opacity: 0.8;
}

.detail-section {
  margin-bottom: 24px;
}

.detail-section h3 {
  margin-bottom: 16px;
  font-size: 16px;
  font-weight: 600;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.section-header h3 {
  margin: 0;
}
</style>
