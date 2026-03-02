// 用户信息接口
export interface User {
  id: number
  username: string
  nickname: string
  avatar?: string
  email?: string
  roles: string[]
  created_at?: string
  updated_at?: string
}

// 登录请求接口
export interface LoginRequest {
  username: string
  password: string
}

// 登录响应接口
export interface LoginResponse {
  access_token: string
  refresh_token: string
  expires_in: number
  user: User
}

// 会话验证请求接口
export interface VerifySessionRequest {
  session_token: string
}
