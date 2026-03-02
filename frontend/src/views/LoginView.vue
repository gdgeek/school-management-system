<template>
  <div class="login-container">
    <el-card class="login-card">
      <h2>学校管理系统</h2>
      <p class="subtitle">School Management System</p>
      
      <el-alert
        v-if="errorMessage"
        :title="errorMessage"
        type="error"
        :closable="false"
        style="margin-bottom: 20px"
      />

      <el-form
        ref="formRef"
        :model="loginForm"
        :rules="rules"
        @submit.prevent="handleLogin"
      >
        <el-form-item prop="username">
          <el-input
            v-model="loginForm.username"
            placeholder="用户名"
            size="large"
            prefix-icon="User"
          />
        </el-form-item>

        <el-form-item prop="password">
          <el-input
            v-model="loginForm.password"
            type="password"
            placeholder="密码"
            size="large"
            prefix-icon="Lock"
            @keyup.enter="handleLogin"
          />
        </el-form-item>

        <el-button
          type="primary"
          size="large"
          :loading="loading"
          style="width: 100%"
          @click="handleLogin"
        >
          登录
        </el-button>
      </el-form>

      <div class="tips">
        <p>提示：本系统使用统一认证，请从主系统跳转访问</p>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const formRef = ref<FormInstance>()
const loading = ref(false)
const errorMessage = ref('')

const loginForm = reactive({
  username: '',
  password: ''
})

const rules: FormRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' }
  ]
}

async function handleLogin() {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    loading.value = true
    errorMessage.value = ''

    // 这里应该调用登录API，但由于使用统一认证，暂时不实现
    ElMessage.warning('请从主系统跳转访问')
  } catch (error) {
    console.error('登录失败:', error)
  } finally {
    loading.value = false
  }
}

// 检查是否有会话令牌
async function checkSessionToken() {
  const token = route.query.token as string
  if (token) {
    try {
      loading.value = true
      const success = await authStore.verifySession(token)
      if (success) {
        ElMessage.success('登录成功')
        const redirect = route.query.redirect as string || '/'
        router.replace(redirect)
      } else {
        errorMessage.value = '会话验证失败，请重新登录'
      }
    } catch (error) {
      errorMessage.value = '会话验证失败，请重新登录'
      console.error(error)
    } finally {
      loading.value = false
    }
  }
}

onMounted(() => {
  checkSessionToken()
})
</script>

<style scoped>
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-card {
  width: 400px;
  padding: 20px;
}

.login-card h2 {
  text-align: center;
  margin: 0 0 8px 0;
  font-size: 24px;
  color: #303133;
}

.subtitle {
  text-align: center;
  margin: 0 0 32px 0;
  color: #909399;
  font-size: 14px;
}

.tips {
  margin-top: 20px;
  text-align: center;
}

.tips p {
  margin: 0;
  font-size: 12px;
  color: #909399;
}
</style>
