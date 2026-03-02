<template>
  <div class="group-list">
    <PageHeader title="小组管理">
      <template #actions>
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          创建小组
        </el-button>
      </template>
    </PageHeader>

    <el-card>
      <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
        <el-form-item label="搜索">
          <el-input
            v-model="searchForm.search"
            placeholder="输入小组名称"
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
        :data="groups"
        :columns="columns"
        :loading="loading"
        :pagination="pagination"
        @page-change="handlePageChange"
        @edit="handleEdit"
        @delete="handleDelete"
      >
        <template #actions="{ row }">
          <el-button
            type="primary"
            size="small"
            @click="handleViewDetail(row)"
          >
            查看详情
          </el-button>
        </template>
      </DataTable>
    </el-card>

    <GroupForm
      v-model:visible="formVisible"
      :group="currentGroup"
      :mode="formMode"
      @success="handleFormSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import PageHeader from '@/components/common/PageHeader.vue'
import DataTable from '@/components/common/DataTable.vue'
import GroupForm from './GroupForm.vue'
import { getGroups, deleteGroup } from '@/api/group'
import type { Group } from '@/types/group'

const router = useRouter()

// 搜索表单
const searchForm = reactive({
  search: ''
})

// 列表数据
const groups = ref<Group[]>([])
const loading = ref(false)
const pagination = reactive({
  page: 1,
  pageSize: 20,
  total: 0
})

// 表格列配置
const columns = [
  { prop: 'id', label: 'ID', width: 80 },
  { prop: 'name', label: '小组名称', minWidth: 200 },
  { prop: 'description', label: '描述', minWidth: 200, showOverflowTooltip: true },
  {
    prop: 'creator',
    label: '创建者',
    minWidth: 150,
    formatter: (row: Group) => row.creator?.nickname || '-'
  },
  {
    prop: 'member_count',
    label: '成员数',
    width: 100,
    formatter: (row: Group) => row.member_count || 0
  },
  { prop: 'created_at', label: '创建时间', width: 180 }
]

// 表单相关
const formVisible = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const currentGroup = ref<Group | undefined>()

// 加载小组列表
async function loadGroups() {
  try {
    loading.value = true
    const response = await getGroups({
      page: pagination.page,
      page_size: pagination.pageSize,
      search: searchForm.search || undefined
    })
    groups.value = response.items
    pagination.total = response.pagination.total
  } catch (error) {
    ElMessage.error('加载小组列表失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

// 搜索
function handleSearch() {
  pagination.page = 1
  loadGroups()
}

// 重置
function handleReset() {
  searchForm.search = ''
  pagination.page = 1
  loadGroups()
}

// 分页变化
function handlePageChange(page: number) {
  pagination.page = page
  loadGroups()
}

// 创建
function handleCreate() {
  formMode.value = 'create'
  currentGroup.value = undefined
  formVisible.value = true
}

// 编辑
function handleEdit(group: Group) {
  formMode.value = 'edit'
  currentGroup.value = group
  formVisible.value = true
}

// 查看详情
function handleViewDetail(group: Group) {
  router.push({ name: 'GroupDetail', params: { id: group.id } })
}

// 删除
async function handleDelete(group: Group) {
  try {
    await ElMessageBox.confirm(
      `确定要删除小组"${group.name}"吗？`,
      '删除确认',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    
    await deleteGroup(group.id)
    ElMessage.success('删除成功')
    loadGroups()
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
  loadGroups()
}

onMounted(() => {
  loadGroups()
})
</script>

<style scoped>
.group-list {
  padding: 20px;
}
</style>
