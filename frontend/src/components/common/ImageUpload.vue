<template>
  <div class="image-upload">
    <el-upload
      :action="uploadUrl"
      :headers="uploadHeaders"
      :show-file-list="false"
      :before-upload="beforeUpload"
      :on-success="handleSuccess"
      :on-error="handleError"
      accept="image/*"
    >
      <div v-if="imageUrl" class="image-preview">
        <LazyImage 
          :src="imageUrl" 
          :alt="alt"
          fit="cover"
        />
        <div class="image-overlay">
          <el-icon @click.stop="handleRemove"><Delete /></el-icon>
        </div>
      </div>
      <div v-else class="upload-placeholder">
        <el-icon><Plus /></el-icon>
        <div class="upload-text">Upload Image</div>
      </div>
    </el-upload>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Plus, Delete } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import type { UploadProps } from 'element-plus'
import { getToken } from '@/utils/auth'
import LazyImage from './LazyImage.vue'

interface Props {
  modelValue?: string
  maxSize?: number // MB
  alt?: string
}

const props = withDefaults(defineProps<Props>(), {
  maxSize: 5,
  alt: 'Uploaded image'
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  success: [url: string]
}>()

const imageUrl = ref(props.modelValue)
const uploadUrl = ref(import.meta.env.VITE_API_BASE_URL + '/upload/image')
const uploadHeaders = ref({
  Authorization: `Bearer ${getToken()}`
})

watch(() => props.modelValue, (val) => {
  imageUrl.value = val
})

const beforeUpload: UploadProps['beforeUpload'] = (file) => {
  const isImage = file.type.startsWith('image/')
  const isLt5M = file.size / 1024 / 1024 < props.maxSize

  if (!isImage) {
    ElMessage.error('Only image files are allowed!')
    return false
  }
  if (!isLt5M) {
    ElMessage.error(`Image size must be less than ${props.maxSize}MB!`)
    return false
  }
  return true
}

function handleSuccess(response: any) {
  if (response.code === 200) {
    imageUrl.value = response.data.url
    emit('update:modelValue', response.data.url)
    emit('success', response.data.url)
    ElMessage.success('Image uploaded successfully')
  } else {
    ElMessage.error(response.message || 'Upload failed')
  }
}

function handleError(error: Error) {
  console.error('Upload error:', error)
  ElMessage.error('Upload failed')
}

function handleRemove() {
  imageUrl.value = ''
  emit('update:modelValue', '')
}
</script>

<style scoped>
.image-upload {
  display: inline-block;
}

.image-preview {
  position: relative;
  width: 148px;
  height: 148px;
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  overflow: hidden;
  cursor: pointer;
}

.image-preview:hover .image-overlay {
  opacity: 1;
}

.image-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
  opacity: 0;
  transition: opacity 0.3s;
}

.image-overlay .el-icon {
  font-size: 24px;
  color: #fff;
  cursor: pointer;
}

.upload-placeholder {
  width: 148px;
  height: 148px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  transition: border-color 0.3s;
}

.upload-placeholder:hover {
  border-color: #409eff;
}

.upload-placeholder .el-icon {
  font-size: 28px;
  color: #8c939d;
  margin-bottom: 8px;
}

.upload-text {
  font-size: 14px;
  color: #8c939d;
}
</style>
