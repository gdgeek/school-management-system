<template>
  <div class="login-container">
    <el-card class="login-card">
      <h2>{{ $t('auth.systemTitle') }}</h2>
      <p class="subtitle">{{ $t('auth.systemSubtitle') }}</p>
      
      <el-alert
        v-if="errorMessage"
        :title="errorMessage"
        type="error"
        :closable="false"
        style="margin-bottom: 20px"
      />

      <el-alert
        v-if="verifying"
        :title="$t('auth.verifying')"
        type="info"
        :closable="false"
        style="margin-bottom: 20px"
      />

      <!-- Token 输入模式 -->
      <el-form @submit.prevent="handleTokenLogin" v-if="!verifying">
        <el-form-item>
          <el-input
            v-model="tokenInput"
            :placeholder="$t('auth.tokenPlaceholder')"
            size="large"
            type="textarea"
            :rows="3"
          />
        </el-form-item>

        <el-button
          type="primary"
          size="large"
          :loading="loading"
          style="width: 100%"
          @click="handleTokenLogin"
        >
          {{ $t('auth.tokenLogin') }}
        </el-button>
      </el-form>

      <el-divider>{{ $t('auth.or') }}</el-divider>

      <el-button
        size="large"
        style="width: 100%"
        @click="goToMainSystem"
      >
        {{ $t('auth.goToMainSystem') }}
      </el-button>

      <div class="tips">
        <p>{{ $t('auth.tips1') }}</p>
        <p>{{ $t('auth.tips2') }}</p>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { setToken, setUserInfo } from '@/utils/auth'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const loading = ref(false)
const verifying = ref(false)
const errorMessage = ref('')
const tokenInput = ref('')

/**
 * 手动输入 token 登录
 */
async function handleTokenLogin() {
  const token = tokenInput.value.trim()
  if (!token) {
    ElMessage.warning(t('auth.tokenRequired'))
    return
  }

  try {
    loading.value = true
    errorMessage.value = ''

    // 直接存储 token，然后尝试获取用户信息
    setToken(token.trim())
    authStore.token = token.trim()

    try {
      // 尝试用 token 获取用户信息
      await authStore.fetchUserInfo()
      ElMessage.success(t('auth.loginSuccess'))
      const redirect = (route.query.redirect as string) || '/'
      router.replace(redirect)
    } catch {
      // 如果获取用户信息失败，token 可能格式正确但后端没有 /auth/user 接口
      // 先解析 JWT payload 作为基本用户信息
      const payload = parseJwtPayload(token)
      if (payload && payload.user_id) {
        const user = {
          id: payload.user_id,
          username: payload.username || 'user',
          nickname: payload.username || 'User',
          roles: payload.roles || ['user'],
        }
        setUserInfo(user)
        authStore.user = user
        ElMessage.success(t('auth.tokenVerifySuccess'))
        const redirect = (route.query.redirect as string) || '/'
        router.replace(redirect)
      } else {
        errorMessage.value = t('auth.tokenInvalid')
        authStore.logout()
      }
    }
  } catch (error) {
    errorMessage.value = t('auth.verifyFailed')
    console.error(error)
  } finally {
    loading.value = false
  }
}

/**
 * 解析 JWT payload（不验证签名，仅客户端解码）
 */
function parseJwtPayload(token: string): any | null {
  try {
    const parts = token.split('.')
    if (parts.length !== 3) return null
    const payload = JSON.parse(atob(parts[1]))
    // 检查是否过期
    if (payload.exp && payload.exp < Date.now() / 1000) {
      return null
    }
    return payload
  } catch {
    return null
  }
}

/**
 * 跳转到主系统
 */
function goToMainSystem() {
  const mainSystemUrl = import.meta.env.VITE_MAIN_SYSTEM_URL || '/'
  window.location.href = mainSystemUrl
}

/**
 * 检查 URL 中的会话令牌
 */
async function checkSessionToken() {
  const token = (route.query.token as string) || (route.query.session_token as string)
  if (token) {
    try {
      verifying.value = true
      const success = await authStore.verifySession(token)
      if (success) {
        ElMessage.success(t('auth.loginSuccess'))
        const redirect = (route.query.redirect as string) || '/'
        router.replace(redirect)
      } else {
        errorMessage.value = t('auth.sessionVerifyFailed')
      }
    } catch (error) {
      errorMessage.value = t('auth.verifyFailed')
      console.error(error)
    } finally {
      verifying.value = false
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
  width: 440px;
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
  margin-top: 16px;
  text-align: center;
}

.tips p {
  margin: 4px 0;
  font-size: 12px;
  color: #909399;
}
</style>
