import type { User } from './user'

// 教师信息接口
export interface Teacher {
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

// 教师表单数据接口
export interface TeacherFormData {
  user_id: number
  class_id: number
}

// 教师列表查询参数
export interface TeacherListParams {
  page?: number
  pageSize?: number
  search?: string
  class_id?: number
  school_id?: number
}

// 教师列表响应
export interface TeacherListResponse {
  items: Teacher[]
  pagination: {
    total: number
    page: number
    pageSize: number
    totalPages: number
  }
}
