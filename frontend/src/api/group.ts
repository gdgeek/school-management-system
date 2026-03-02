import { request } from '@/utils/request'
import type { Group, GroupFormData, GroupListParams, GroupListResponse, GroupMember } from '@/types/group'

/**
 * 获取小组列表
 */
export function getGroups(params?: GroupListParams) {
  return request.get<GroupListResponse>('/groups', { params })
}

/**
 * 获取小组详情
 */
export function getGroup(id: number) {
  return request.get<Group>(`/groups/${id}`)
}

/**
 * 创建小组
 */
export function createGroup(data: GroupFormData) {
  return request.post<Group>('/groups', data)
}

/**
 * 更新小组
 */
export function updateGroup(id: number, data: GroupFormData) {
  return request.put<Group>(`/groups/${id}`, data)
}

/**
 * 删除小组
 */
export function deleteGroup(id: number) {
  return request.delete(`/groups/${id}`)
}

/**
 * 获取小组成员列表
 */
export function getGroupMembers(id: number) {
  return request.get<GroupMember[]>(`/groups/${id}/members`)
}

/**
 * 添加成员到小组
 */
export function addGroupMember(id: number, userId: number) {
  return request.post(`/groups/${id}/members`, { user_id: userId })
}

/**
 * 从小组移除成员
 */
export function removeGroupMember(id: number, userId: number) {
  return request.delete(`/groups/${id}/members/${userId}`)
}
