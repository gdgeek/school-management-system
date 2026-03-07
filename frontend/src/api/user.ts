import { request } from '@/utils/request'

export interface UserItem {
  id: number
  username: string
  nickname: string | null
  email: string | null
}

/**
 * 搜索用户
 */
export function searchUsers(keyword: string, limit = 20) {
  return request.get<UserItem[]>('/users/search', { params: { keyword, limit } })
}
