import { request } from '@/utils/request'
import type { Teacher, TeacherFormData, TeacherListParams, TeacherListResponse } from '@/types/teacher'

/**
 * 获取教师列表
 */
export function getTeachers(params?: TeacherListParams) {
  return request.get<TeacherListResponse>('/teachers', { params })
}

/**
 * 添加教师到班级
 */
export function createTeacher(data: TeacherFormData) {
  return request.post<Teacher>('/teachers', data)
}

/**
 * 移除教师
 */
export function deleteTeacher(id: number) {
  return request.delete(`/teachers/${id}`)
}
