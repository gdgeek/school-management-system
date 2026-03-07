import { request } from '@/utils/request'
import type { Class, ClassFormData, ClassListParams, ClassListResponse } from '@/types/class'

/**
 * 获取班级列表
 */
export function getClasses(params?: ClassListParams) {
  return request.get<ClassListResponse>('/classes', { params })
}

/**
 * 获取班级详情
 */
export function getClass(id: number) {
  return request.get<Class>(`/classes/${id}`)
}

/**
 * 创建班级
 */
export function createClass(data: ClassFormData) {
  return request.post<Class>('/classes', data)
}

/**
 * 更新班级
 */
export function updateClass(id: number, data: ClassFormData) {
  return request.put<Class>(`/classes/${id}`, data)
}

/**
 * 删除班级
 * @param id 班级ID
 * @param deleteGroups 是否同时删除关联的小组
 */
export function deleteClass(id: number, deleteGroups = false) {
  return request.delete(`/classes/${id}`, { params: { deleteGroups: deleteGroups.toString() } })
}

/**
 * 获取班级的教师列表
 */
export function getClassTeachers(id: number, params?: { page?: number; page_size?: number }) {
  return request.get(`/classes/${id}/teachers`, { params })
}

/**
 * 获取班级的学生列表
 */
export function getClassStudents(id: number, params?: { page?: number; page_size?: number }) {
  return request.get(`/classes/${id}/students`, { params })
}
