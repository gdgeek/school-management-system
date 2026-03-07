<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TeacherRepository;
use App\Repository\ClassRepository;
use App\Repository\UserRepository;
use App\Repository\SchoolRepository;
use App\Model\Teacher;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

/**
 * 教师管理服务
 * 处理教师相关的业务逻辑
 */
class TeacherService
{
    public function __construct(
        private TeacherRepository $teacherRepository,
        private ClassRepository $classRepository,
        private UserRepository $userRepository,
        private SchoolRepository $schoolRepository
    ) {}

    /**
     * 获取教师列表
     */
    public function getList(int $page = 1, int $pageSize = 20, ?int $classId = null): array
    {
        $offset = ($page - 1) * $pageSize;

        if ($classId) {
            $teachers = $this->teacherRepository->findByClassId($classId);
            $total = count($teachers);
            $teachers = array_slice($teachers, $offset, $pageSize);
        } else {
            $teachers = $this->teacherRepository->findAll($pageSize, $offset);
            $total = count($this->teacherRepository->findAll(10000, 0));
        }

        // 批量加载用户信息（避免 N+1 查询）
        $userIds = array_map(fn(Teacher $t) => $t->user_id, $teachers);
        $userIds = array_unique($userIds);
        $users = $this->userRepository->findByIds($userIds);

        // 以 user id 为 key 建立索引
        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user['id']] = $user;
        }

        // 批量加载班级信息（避免 N+1 查询）
        $classIds = array_map(fn(Teacher $t) => $t->class_id, $teachers);
        $classIds = array_unique(array_filter($classIds));
        $classMap = [];
        $schoolIds = [];
        if (!empty($classIds)) {
            foreach ($classIds as $cid) {
                $class = $this->classRepository->findById($cid);
                if ($class) {
                    $classMap[$cid] = $class;
                    if ($class->school_id) {
                        $schoolIds[] = $class->school_id;
                    }
                }
            }
        }

        // 批量加载学校信息（通过班级的 school_id）
        $schoolIds = array_unique($schoolIds);
        $schoolMap = [];
        if (!empty($schoolIds)) {
            foreach ($schoolIds as $sid) {
                $school = $this->schoolRepository->findById($sid);
                if ($school) {
                    $schoolMap[$sid] = $school;
                }
            }
        }

        $teachersWithUser = [];
        foreach ($teachers as $teacher) {
            $teacherData = $teacher->toArray();
            $user = $userMap[$teacher->user_id] ?? null;
            if ($user) {
                $teacherData['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'] ?? '',
                    'nickname' => $user['nickname'] ?? '',
                ];
            }
            // 添加班级信息
            if ($teacher->class_id && isset($classMap[$teacher->class_id])) {
                $class = $classMap[$teacher->class_id];
                $teacherData['class'] = [
                    'id' => $class->id,
                    'name' => $class->name,
                ];
                // 添加学校信息（通过班级）
                if ($class->school_id && isset($schoolMap[$class->school_id])) {
                    $school = $schoolMap[$class->school_id];
                    $teacherData['school'] = [
                        'id' => $school->id,
                        'name' => $school->name,
                    ];
                }
            }
            $teachersWithUser[] = $teacherData;
        }

        return [
            'items' => $teachersWithUser,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => (int)ceil($total / $pageSize),
            ],
        ];
    }

    /**
     * 获取教师详情
     */
    public function getById(int $id): array
    {
        $teacher = $this->teacherRepository->findById($id);
        
        if (!$teacher) {
            throw new NotFoundException("Teacher not found: {$id}");
        }
        
        $teacherData = $teacher->toArray();
        
        // 加载用户信息
        $user = $this->userRepository->findById($teacher->user_id);
        if ($user) {
            $teacherData['user'] = [
                'id' => $user['id'],
                'username' => $user['username'] ?? '',
                'nickname' => $user['nickname'] ?? '',
            ];
        }
        
        return $teacherData;
    }

    /**
     * 添加教师到班级
     */
    public function create(array $data): array
    {
        if (empty($data['user_id'])) {
            throw new ValidationException(['user_id' => 'User ID is required']);
        }
        if (empty($data['class_id'])) {
            throw new ValidationException(['class_id' => 'Class ID is required']);
        }
        
        $user = $this->userRepository->findById($data['user_id']);
        if (!$user) {
            throw new ValidationException(['user_id' => 'Invalid user ID']);
        }
        
        $class = $this->classRepository->findById($data['class_id']);
        if (!$class) {
            throw new ValidationException(['class_id' => 'Invalid class ID']);
        }
        
        if ($this->teacherRepository->exists($data['user_id'], $data['class_id'])) {
            throw new ValidationException(['user_id' => 'Teacher already exists in this class']);
        }
        
        $teacher = new Teacher();
        $teacher->user_id = $data['user_id'];
        $teacher->class_id = $data['class_id'];
        
        $id = $this->teacherRepository->create($teacher);
        $teacher->id = $id;
        
        $teacherData = $teacher->toArray();
        $teacherData['user'] = [
            'id' => $user['id'],
            'username' => $user['username'] ?? '',
            'nickname' => $user['nickname'] ?? '',
        ];
        
        return $teacherData;
    }

    /**
     * 移除教师
     */
    public function delete(int $id): void
    {
        $teacher = $this->teacherRepository->findById($id);
        
        if (!$teacher) {
            throw new NotFoundException("Teacher not found: {$id}");
        }
        
        $this->teacherRepository->delete($id);
    }
}
