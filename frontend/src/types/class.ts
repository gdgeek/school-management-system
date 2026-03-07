// 班级信息接口
export interface Class {
  id: number
  school_id: number
  name: string
  image_id?: number
  image_url?: string
  info?: string
  school?: {
    id: number
    name: string
  }
  created_at?: string
  updated_at?: string
}

// 班级表单数据接口
export interface ClassFormData {
  school_id: number | null
  name: string
  info?: string
}

// 班级列表查询参数
export interface ClassListParams {
  page?: number
  page_size?: number
  search?: string
  school_id?: number
  sort?: string
  order?: 'asc' | 'desc'
}

// 班级列表响应
export interface ClassListResponse {
  items: Class[]
  pagination: {
    total: number
    page: number
    page_size: number
    total_pages: number
  }
}
