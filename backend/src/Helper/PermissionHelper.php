<?php

declare(strict_types=1);

namespace App\Helper;

use App\Repository\SchoolRepository;
use App\Repository\ClassRepository;

/**
 * 权限辅助类
 * 提供细粒度的权限检查功能
 */
class PermissionHelper
{
    private SchoolRepository $schoolRepository;
    private ClassRepository $classRepository;

    public function __construct(
        SchoolRepository $schoolRepository,
        ClassRepository $classRepository
    ) {
        $this->schoolRepository = $schoolRepository;
        $this->classRepository = $classRepository;
    }

    /**
     * 检查用户是否是学校管理员（校长）
     */
    public function isSchoolAdmin(int $userId, int $schoolId): bool
    {
        $school = $this->schoolRepository->findById($schoolId);
        return $school && $school['principal_id'] === $userId;
    }

    /**
     * 检查用户是否是班级教师
     */
    public function isClassTeacher(int $userId, int $classId): bool
    {
        // 这里需要查询edu_teacher表
        // 暂时返回false，后续在实现教师管理时完善
        return false;
    }

    /**
     * 检查用户是否是班级学生
     */
    public function isClassStudent(int $userId, int $classId): bool
    {
        // 这里需要查询edu_student表
        // 暂时返回false，后续在实现学生管理时完善
        return false;
    }

    /**
     * 检查用户是否有权限访问学校资源
     */
    public function canAccessSchool(int $userId, array $roles, int $schoolId): bool
    {
        // 系统管理员可以访问所有学校
        if (in_array('admin', $roles, true)) {
            return true;
        }

        // 校长可以访问自己的学校
        if ($this->isSchoolAdmin($userId, $schoolId)) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否有权限访问班级资源
     */
    public function canAccessClass(int $userId, array $roles, int $classId): bool
    {
        // 系统管理员可以访问所有班级
        if (in_array('admin', $roles, true)) {
            return true;
        }

        // 获取班级所属学校
        $class = $this->classRepository->findById($classId);
        if (!$class) {
            return false;
        }

        // 校长可以访问学校下的所有班级
        if ($this->isSchoolAdmin($userId, $class['school_id'])) {
            return true;
        }

        // 教师可以访问自己的班级
        if (in_array('teacher', $roles, true) && $this->isClassTeacher($userId, $classId)) {
            return true;
        }

        // 学生可以访问自己的班级
        if (in_array('student', $roles, true) && $this->isClassStudent($userId, $classId)) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否有权限修改学校资源
     */
    public function canModifySchool(int $userId, array $roles, int $schoolId): bool
    {
        // 只有系统管理员和校长可以修改学校
        if (in_array('admin', $roles, true)) {
            return true;
        }

        return $this->isSchoolAdmin($userId, $schoolId);
    }

    /**
     * 检查用户是否有权限修改班级资源
     */
    public function canModifyClass(int $userId, array $roles, int $classId): bool
    {
        // 只有系统管理员和校长可以修改班级
        if (in_array('admin', $roles, true)) {
            return true;
        }

        $class = $this->classRepository->findById($classId);
        if (!$class) {
            return false;
        }

        return $this->isSchoolAdmin($userId, $class['school_id']);
    }
}
