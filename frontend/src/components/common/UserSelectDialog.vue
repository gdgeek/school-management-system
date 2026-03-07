<template>
  <el-dialog
    v-model="dialogVisible"
    title="选择用户"
    width="500px"
    :close-on-click-modal="false"
  >
    <el-input
      v-model="keyword"
      placeholder="输入用户名或昵称搜索"
      clearable
      @input="handleSearch"
      @clear="handleClear"
    >
      <template #prefix>
        <el-icon><Search /></el-icon>
      </template>
    </el-input>

    <div class="user-list" v-loading="loading">
      <div v-if="users.length === 0 && !loading" class="empty-tip">
        {{ keyword ? '未找到匹配用户' : '请输入关键词搜索' }}
      </div>
      <div
        v-for="user in users"
        :key="user.id"
        class="user-item"
        :class="{ selected: selectedUser?.id === user.id }"
        @click="selectUser(user)"
      >
        <div class="user-info">
          <span class="user-name">{{ user.nickname || user.username }}</span>
          <span class="user-username">@{{ user.username }}</span>
        </div>
        <el-icon v-if="selectedUser?.id === user.id" class="check-icon"><Check /></el-icon>
      </div>
    </div>

    <template #footer>
      <el-button @click="handleCancel">取消</el-button>
      <el-button type="primary" :disabled="!selectedUser" @click="handleConfirm">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Search, Check } from '@element-plus/icons-vue'
import { searchUsers, type UserItem } from '@/api/user'

interface Props {
  visible: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:visible': [value: boolean]
  select: [user: UserItem]
}>()

const dialogVisible = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const keyword = ref('')
const users = ref<UserItem[]>([])
const loading = ref(false)
const selectedUser = ref<UserItem | null>(null)
let searchTimer: ReturnType<typeof setTimeout> | null = null

watch(() => props.visible, (val) => {
  if (!val) {
    keyword.value = ''
    users.value = []
    selectedUser.value = null
  }
})

function handleSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(async () => {
    if (!keyword.value.trim()) {
      users.value = []
      return
    }
    loading.value = true
    try {
      users.value = await searchUsers(keyword.value.trim())
    } catch (e) {
      console.error('搜索用户失败:', e)
      users.value = []
    } finally {
      loading.value = false
    }
  }, 300)
}

function handleClear() {
  users.value = []
  selectedUser.value = null
}

function selectUser(user: UserItem) {
  selectedUser.value = user
}

function handleConfirm() {
  if (selectedUser.value) {
    emit('select', selectedUser.value)
    emit('update:visible', false)
  }
}

function handleCancel() {
  emit('update:visible', false)
}
</script>

<style scoped>
.user-list {
  margin-top: 12px;
  max-height: 300px;
  overflow-y: auto;
  min-height: 100px;
}
.empty-tip {
  text-align: center;
  color: #999;
  padding: 40px 0;
}
.user-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
}
.user-item:hover {
  background: #f5f7fa;
}
.user-item.selected {
  background: #ecf5ff;
}
.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
}
.user-name {
  font-weight: 500;
}
.user-username {
  color: #999;
  font-size: 13px;
}
.check-icon {
  color: #409eff;
}
</style>
