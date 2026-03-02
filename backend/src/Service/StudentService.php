<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\StudentRepository;
use App\Repository\ClassRepository;
use App\Repository\UserRepository;
use App\Model\Student;

/**
 * 学生管理服务
 * 处理学生相关的业务逻辑
 */
class StudentService
{
    public function __construct(
        private StudentRepository $studentRepository,
        private ClassRepository $classRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * 获取学生列表
     */
    public function getList(int $page = 1, int $pageSize = 20, ?int $classId = null): array
    {
        $offset = ($page - 1) * $pageSize;
        
        if ($classId) {
            $students = $this->studentRepository->findByClassId($classId);
            $total = count($students);
            $students = array_slice($students, $offset, $pageSize);
        } else {
            $students = $this->studentRepository->findAll($pageSize, $offset);
            $total = count($this->studentRepository->findAll(10000, 0));
        }
        
        // 加载用户信息
        $studentsWithUser = [];
        foreach ($students as $student) {
            $studentData = $student->toArray();
            $user = $this->userRepository->findById($student->user_id);
            if ($user) {
                $studentData['user'] = [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar,
                ];
            }
            $studentsWithUser[] = $studentData;
        }
        
        return [
            'items' => $studentsWithUser,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => (int)ceil($total / $pageSize),
            ],
        ];
    }

    /**
     * 获取学生详情
     */
    public function getById(int $id): ?array
    {
        $student = $this->studentRepository->findById($id);
        
        if (!$student) {
            return null;
        }
        
        $studentData = $student->toArray();
        
        // 加载用户信息
        $user = $this->userRepository->findById($student->user_id);
        if ($user) {
            $studentData['user'] = [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
            ];
        }
        
        return $studentData;
    }

    /**
     * 添加学生到班级
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
        
        // 检查用户是否已经是其他班级的学生
        $existingStudent = $this->studentRepository->findByUserId($data['user_id']);
        if ($existingStudent) {
            throw new \InvalidArgumentException('User is already a student in another class');
        }
        
        // 检查是否已存在于当前班级
        if ($this->studentRepository->exists($data['user_id'], $data['class_id'])) {
            throw new \InvalidArgumentException('Student already exists in this class');
        }
        
        $student = new Student();
        $student->user_id = $data['user_id'];
        $student->class_id = $data['class_id'];
        
        $id = $this->studentRepository->create($student);
        $student->id = $id;
        
        $studentData = $student->toArray();
        $studentData['user'] = [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
        ];
        
        return $studentData;
    }

    /**
     * 移除学生
     */
    public function delete(int $id): bool
    {
        $student = $this->studentRepository->findById($id);
        
        if (!$student) {
            return false;
        }
        
        return $this->studentRepository->delete($id);
    }
}
