import type { User } from './user'

// 学生信息接口
export interface Student {
  id: number
  user_id: number
  class_id: number
  user?: User
  class?: {
    id: number
    name: string
    school_id: number
  }
  school?: {
    id: number
    name: string
  }
  groups?: Array<{
    id: number
    name: string
  }>
  auto_joined_groups?: Array<{
    id: number
    name: string
  }>
  created_at?: string
}

// 学生表单数据接口
export interface StudentFormData {
  user_id: number
  class_id: number
}

// 学生列表查询参数
export interface StudentListParams {
  page?: number
  pageSize?: number
  search?: string
  class_id?: number
  school_id?: number
}

// 学生列表响应
export interface StudentListResponse {
  items: Student[]
  pagination: {
    total: number
    page: number
    pageSize: number
    totalPages: number
  }
}
