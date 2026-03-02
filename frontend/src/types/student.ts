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
  page_size?: number
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
    page_size: number
    total_pages: number
  }
}
