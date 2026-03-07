import type { User } from './user'

// 小组信息接口
export interface Group {
  id: number
  name: string
  description?: string
  image_id?: number
  image_url?: string
  info?: string
  creator_id: number
  creator?: User
  members?: User[]
  member_count?: number
  created_at?: string
  updated_at?: string
}

// 小组表单数据接口
export interface GroupFormData {
  name: string
  description?: string
  info?: string
}

// 小组列表查询参数
export interface GroupListParams {
  page?: number
  page_size?: number
  search?: string
  sort?: string
  order?: 'asc' | 'desc'
}

// 小组列表响应
export interface GroupListResponse {
  items: Group[]
  pagination: {
    total: number
    page: number
    page_size: number
    total_pages: number
  }
}

// 小组成员接口
export interface GroupMember {
  user_id: number
  group_id: number
  user?: User
  joined_at?: string
}
