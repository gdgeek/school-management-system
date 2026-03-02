import { request } from '@/utils/request'
import type { Student, StudentFormData, StudentListParams, StudentListResponse } from '@/types/student'

/**
 * 获取学生列表
 */
export function getStudents(params?: StudentListParams) {
  return request.get<StudentListResponse>('/students', { params })
}

/**
 * 添加学生到班级
 */
export function createStudent(data: StudentFormData) {
  return request.post<Student>('/students', data)
}

/**
 * 移除学生
 */
export function deleteStudent(id: number) {
  return request.delete(`/students/${id}`)
}
