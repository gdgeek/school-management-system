import { request } from '@/utils/request'
import type { School, SchoolFormData, SchoolListParams, SchoolListResponse } from '@/types/school'

/**
 * 获取学校列表
 */
export function getSchools(params?: SchoolListParams) {
  return request.get<SchoolListResponse>('/schools', { params })
}

/**
 * 获取学校详情
 */
export function getSchool(id: number) {
  return request.get<School>(`/schools/${id}`)
}

/**
 * 创建学校
 */
export function createSchool(data: SchoolFormData) {
  return request.post<School>('/schools', data)
}

/**
 * 更新学校
 */
export function updateSchool(id: number, data: SchoolFormData) {
  return request.put<School>(`/schools/${id}`, data)
}

/**
 * 删除学校
 */
export function deleteSchool(id: number) {
  return request.delete(`/schools/${id}`)
}

/**
 * 获取学校的班级列表
 */
export function getSchoolClasses(id: number, params?: { page?: number; page_size?: number }) {
  return request.get(`/schools/${id}/classes`, { params })
}
