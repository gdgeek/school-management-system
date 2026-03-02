<template>
  <div class="empty-state">
    <el-empty
      :image="image"
      :image-size="imageSize"
      :description="description || $t('common.noData')"
    >
      <slot>
        <el-button v-if="actionText" type="primary" @click="$emit('action')">
          {{ actionText }}
        </el-button>
      </slot>
    </el-empty>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  description?: string
  image?: string
  imageSize?: number
  actionText?: string
}>()

defineEmits<{ action: [] }>()
</script>

<style scoped>
.empty-state {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 40px 20px;
  min-height: 200px;
}
</style>
