<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\TeacherRepository;
use App\Repository\ClassRepository;
use App\Repository\UserRepository;
use App\Model\Teacher;

/**
 * 教师管理服务
 * 处理教师相关的业务逻辑
 */
class TeacherService
{
    public function __construct(
        private TeacherRepository $teacherRepository,
        private ClassRepository $classRepository,
        private UserRepository $userRepository
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
        
        // 加载用户信息
        $teachersWithUser = [];
        foreach ($teachers as $teacher) {
            $teacherData = $teacher->toArray();
            $user = $this->userRepository->findById($teacher->user_id);
            if ($user) {
                $teacherData['user'] = [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar,
                ];
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
    public function getById(int $id): ?array
    {
        $teacher = $this->teacherRepository->findById($id);
        
        if (!$teacher) {
            return null;
        }
        
        $teacherData = $teacher->toArray();
        
        // 加载用户信息
        $user = $this->userRepository->findById($teacher->user_id);
        if ($user) {
            $teacherData['user'] = [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
            ];
        }
        
        return $teacherData;
    }

    /**
     * 添加教师到班级
     */
    public function create(array $data): array
    {
        // 验证必填字段
        if (empty($data['user_id'])) {
            throw new \InvalidArgumentException('User ID is required');
        }
        if (empty($data['class_id'])) {
            throw new \InvalidArgumentException('Class ID is required');
        }
        
        // 验证用户存在
        $user = $this->userRepository->findById($data['user_id']);
        if (!$user) {
            throw new \InvalidArgumentException('Invalid user ID');
        }
        
        // 验证班级存在
        $class = $this->classRepository->findById($data['class_id']);
        if (!$class) {
            throw new \InvalidArgumentException('Invalid class ID');
        }
        
        // 检查是否已存在
        if ($this->teacherRepository->exists($data['user_id'], $data['class_id'])) {
            throw new \InvalidArgumentException('Teacher already exists in this class');
        }
        
        $teacher = new Teacher();
        $teacher->user_id = $data['user_id'];
        $teacher->class_id = $data['class_id'];
        
        $id = $this->teacherRepository->create($teacher);
        $teacher->id = $id;
        
        $teacherData = $teacher->toArray();
        $teacherData['user'] = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
        ];
        
        return $teacherData;
    }

    /**
     * 移除教师
     */
    public function delete(int $id): bool
    {
        $teacher = $this->teacherRepository->findById($id);
        
        if (!$teacher) {
            return false;
        }
        
        return $this->teacherRepository->delete($id);
    }
}
