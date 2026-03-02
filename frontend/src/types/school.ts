// 学校信息接口
export interface School {
  id: number
  name: string
  image_id?: number
  image_url?: string
  info?: string
  principal_id?: number
  principal?: {
    id: number
    nickname: string
    avatar?: string
  }
  created_at?: string
  updated_at?: string
}

// 学校表单数据接口
export interface SchoolFormData {
  name: string
  image_id?: number
  info?: string
  principal_id?: number
}

// 学校列表查询参数
export interface SchoolListParams {
  page?: number
  page_size?: number
  search?: string
  sort?: string
  order?: 'asc' | 'desc'
}

// 学校列表响应
export interface SchoolListResponse {
  items: School[]
  pagination: {
    total: number
    page: number
    page_size: number
    total_pages: number
  }
}
