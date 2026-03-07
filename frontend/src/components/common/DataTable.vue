<template>
  <div class="data-table" :class="{ 'mobile-view': isMobile }">
    <!-- Desktop/Tablet: Table view with optional virtual scrolling -->
    <template v-if="!isMobile">
      <!-- Virtual scrolling for large lists -->
      <div v-if="enableVirtualScroll && data.length > virtualScrollThreshold" class="virtual-table-container">
        <VirtualList
          :data="data"
          :item-height="virtualItemHeight"
          :loading="loading"
        >
          <template #default="{ item }">
            <div class="virtual-table-row">
              <slot name="virtual-row" :item="item"></slot>
            </div>
          </template>
        </VirtualList>
      </div>
      
      <!-- Regular table for smaller lists -->
      <el-table
        v-else
        :data="data"
        :loading="loading"
        v-bind="$attrs"
        @selection-change="handleSelectionChange"
      >
        <slot>
          <!-- Auto-generate columns from columns prop -->
          <template v-if="columns && columns.length">
            <el-table-column
              v-for="col in columns"
              :key="col.prop"
              :prop="col.prop"
              :label="col.label"
              :width="col.width"
              :min-width="col.minWidth"
              :show-overflow-tooltip="col.showOverflowTooltip"
            >
              <template v-if="col.formatter" #default="{ row }">
                {{ col.formatter(row) }}
              </template>
              <template v-else #default="{ row }">
                {{ row[col.prop] }}
              </template>
            </el-table-column>
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="handleEdit(row)">编辑</el-button>
                <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
              </template>
            </el-table-column>
          </template>
        </slot>
      </el-table>
    </template>
    
    <!-- Mobile: Card view with virtual scrolling -->
    <div v-else class="card-view">
      <div v-if="loading" class="loading-container">
        <el-icon class="is-loading"><Loading /></el-icon>
        <span>Loading...</span>
      </div>
      
      <div v-else-if="data.length === 0" class="empty-container">
        <el-empty description="No data" />
      </div>
      
      <!-- Virtual scrolling for large lists on mobile -->
      <VirtualList
        v-else-if="enableVirtualScroll && data.length > virtualScrollThreshold"
        :data="data"
        :item-height="mobileCardHeight"
        class="mobile-virtual-list"
      >
        <template #default="{ item }">
          <div class="data-card">
            <slot name="card-content" :item="item">
              <div class="card-content">
                {{ item }}
              </div>
            </slot>
          </div>
        </template>
      </VirtualList>
      
      <!-- Regular card list for smaller lists -->
      <div v-else class="card-list">
        <slot name="card" :data="data">
          <!-- Default card layout if no custom card slot provided -->
          <div
            v-for="(item, index) in data"
            :key="index"
            class="data-card"
          >
            <slot name="card-content" :item="item">
              <div class="card-content">
                {{ item }}
              </div>
            </slot>
          </div>
        </slot>
      </div>
    </div>
    
    <el-pagination
      v-if="pagination"
      v-model:current-page="currentPage"
      v-model:page-size="pageSize"
      :total="pagination.total"
      :page-sizes="pageSizes"
      :layout="isMobile ? 'prev, pager, next' : layout"
      :small="isMobile"
      @current-change="handlePageChange"
      @size-change="handleSizeChange"
      class="pagination"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Loading } from '@element-plus/icons-vue'
import { useAppStore } from '@/stores/app'
import VirtualList from './VirtualList.vue'
import type { PaginationResponse } from '@/utils/request'

interface Column {
  prop: string
  label: string
  width?: number
  minWidth?: number
  showOverflowTooltip?: boolean
  formatter?: (row: any) => string
}

interface Props {
  data: any[]
  columns?: Column[]
  loading?: boolean
  pagination?: PaginationResponse['pagination']
  pageSizes?: number[]
  layout?: string
  enableVirtualScroll?: boolean
  virtualScrollThreshold?: number
  virtualItemHeight?: number
  mobileCardHeight?: number
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  pageSizes: () => [10, 20, 50, 100],
  layout: 'total, sizes, prev, pager, next, jumper',
  enableVirtualScroll: true,
  virtualScrollThreshold: 100,
  virtualItemHeight: 60,
  mobileCardHeight: 120
})

const emit = defineEmits<{
  pageChange: [page: number]
  sizeChange: [size: number]
  selectionChange: [selection: any[]]
  edit: [row: any]
  delete: [row: any]
}>()

const appStore = useAppStore()
const isMobile = computed(() => appStore.isMobile)

const currentPage = ref(props.pagination?.page || 1)
const pageSize = ref(props.pagination?.pageSize || 20)

watch(() => props.pagination, (newPagination) => {
  if (newPagination) {
    currentPage.value = newPagination.page
    pageSize.value = newPagination.pageSize
  }
})

function handlePageChange(page: number) {
  emit('pageChange', page)
}

function handleSizeChange(size: number) {
  emit('sizeChange', size)
}

function handleSelectionChange(selection: any[]) {
  emit('selectionChange', selection)
}

function handleEdit(row: any) {
  emit('edit', row)
}

function handleDelete(row: any) {
  emit('delete', row)
}
</script>

<style scoped>
.data-table {
  width: 100%;
}

.pagination {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

/* Virtual table container */
.virtual-table-container {
  height: 600px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  overflow: hidden;
}

.virtual-table-row {
  padding: 12px 16px;
  border-bottom: 1px solid #ebeef5;
  background: #fff;
  transition: background-color 0.3s;
}

.virtual-table-row:hover {
  background-color: #f5f7fa;
}

/* Mobile card view styles */
.card-view {
  width: 100%;
}

.loading-container,
.empty-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  color: #909399;
}

.loading-container .el-icon {
  font-size: 32px;
  margin-bottom: 12px;
}

.card-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.mobile-virtual-list {
  height: 600px;
}

.data-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  transition: box-shadow 0.3s;
  margin-bottom: 12px;
}

.data-card:active {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.card-content {
  font-size: 14px;
  color: #333;
}

/* Mobile responsive pagination */
.mobile-view .pagination {
  justify-content: center;
  margin-top: 20px;
}

/* Tablet responsive styles */
@media (min-width: 768px) and (max-width: 1024px) {
  .pagination {
    margin-top: 20px;
  }
  
  .virtual-table-container {
    height: 500px;
  }
}

/* Mobile responsive styles */
@media (max-width: 768px) {
  .mobile-virtual-list {
    height: calc(100vh - 300px);
    min-height: 400px;
  }
}
</style>
