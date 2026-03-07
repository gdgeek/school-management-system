<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\StudentRepository;
use App\Repository\ClassRepository;
use App\Repository\UserRepository;
use App\Repository\SchoolRepository;
use App\Repository\ClassGroupRepository;
use App\Repository\GroupUserRepository;
use App\Repository\GroupRepository;
use App\Model\Student;
use App\Model\GroupUser;
use App\Helper\DatabaseHelper;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

/**
 * 学生管理服务
 * 处理学生相关的业务逻辑
 */
class StudentService
{
    public function __construct(
        private StudentRepository $studentRepository,
        private ClassRepository $classRepository,
        private UserRepository $userRepository,
        private SchoolRepository $schoolRepository,
        private ClassGroupRepository $classGroupRepository,
        private GroupUserRepository $groupUserRepository,
        private GroupRepository $groupRepository,
        private DatabaseHelper $dbHelper
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
        
        // 批量加载用户信息（避免 N+1 查询）
        $userIds = array_map(fn(Student $s) => $s->user_id, $students);
        $userIds = array_unique($userIds);
        $users = $this->userRepository->findByIds($userIds);

        // 以 user id 为 key 建立索引
        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user['id']] = $user;
        }

        // 批量加载班级信息（避免 N+1 查询）
        $classIds = array_map(fn(Student $s) => $s->class_id, $students);
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

        // 批量加载学生的小组信息（避免 N+1 查询）
        $groupMap = [];
        foreach ($userIds as $uid) {
            $groupUsers = $this->groupUserRepository->findByUserId($uid);
            $groupIds = array_map(fn($gu) => $gu->group_id, $groupUsers);
            
            if (!empty($groupIds)) {
                $groups = [];
                foreach ($groupIds as $gid) {
                    $group = $this->groupRepository->findById($gid);
                    if ($group) {
                        $groups[] = [
                            'id' => $group->id,
                            'name' => $group->name,
                        ];
                    }
                }
                $groupMap[$uid] = $groups;
            }
        }

        $studentsWithUser = [];
        foreach ($students as $student) {
            $studentData = $student->toArray();
            $user = $userMap[$student->user_id] ?? null;
            if ($user) {
                $studentData['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'] ?? '',
                    'nickname' => $user['nickname'] ?? '',
                ];
            }
            // 添加班级信息
            if ($student->class_id && isset($classMap[$student->class_id])) {
                $class = $classMap[$student->class_id];
                $studentData['class'] = [
                    'id' => $class->id,
                    'name' => $class->name,
                ];
                // 添加学校信息（通过班级）
                if ($class->school_id && isset($schoolMap[$class->school_id])) {
                    $school = $schoolMap[$class->school_id];
                    $studentData['school'] = [
                        'id' => $school->id,
                        'name' => $school->name,
                    ];
                }
            }
            // 添加小组信息
            $studentData['groups'] = $groupMap[$student->user_id] ?? [];
            
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
    public function getById(int $id): array
    {
        $student = $this->studentRepository->findById($id);
        
        if (!$student) {
            throw new NotFoundException("Student not found: {$id}");
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
     * 自动将学生用户添加到班级关联的所有小组
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
        
        $existingStudent = $this->studentRepository->findByUserId($data['user_id']);
        if ($existingStudent) {
            throw new ValidationException(['user_id' => 'User is already a student in another class']);
        }
        
        if ($this->studentRepository->exists($data['user_id'], $data['class_id'])) {
            throw new ValidationException(['user_id' => 'Student already exists in this class']);
        }
        
        // 使用事务确保原子性
        $result = $this->dbHelper->transaction(function() use ($data, $user) {
            // 步骤 1: 创建学生记录
            $student = new Student();
            $student->user_id = $data['user_id'];
            $student->class_id = $data['class_id'];
            
            $id = $this->studentRepository->create($student);
            $student->id = $id;
            
            // 步骤 2: 查找班级关联的所有小组
            $classGroups = $this->classGroupRepository->findByClassId($data['class_id']);
            
            // 步骤 3: 将用户添加到每个小组（幂等性检查）
            $autoJoinedGroups = [];
            foreach ($classGroups as $classGroup) {
                $groupId = $classGroup->group_id;
                
                // 检查是否已存在（幂等性）
                if (!$this->groupUserRepository->exists($data['user_id'], $groupId)) {
                    $groupUser = new GroupUser();
                    $groupUser->user_id = $data['user_id'];
                    $groupUser->group_id = $groupId;
                    
                    $this->groupUserRepository->create($groupUser);
                    
                    // 获取小组信息用于返回
                    $group = $this->groupRepository->findById($groupId);
                    if ($group) {
                        $autoJoinedGroups[] = [
                            'id' => $group->id,
                            'name' => $group->name,
                        ];
                    }
                }
            }
            
            return [
                'student' => $student,
                'auto_joined_groups' => $autoJoinedGroups,
            ];
        });
        
        // 构建返回数据
        $studentData = $result['student']->toArray();
        $studentData['user'] = [
            'id' => $user['id'] ?? null,
            'nickname' => $user['nickname'] ?? null,
            'avatar' => $user['avatar'] ?? null,
        ];
        $studentData['class'] = [
            'id' => $class->id,
            'name' => $class->name,
        ];
        $studentData['auto_joined_groups'] = $result['auto_joined_groups'];
        
        return $studentData;
    }

    /**
     * 移除学生
     * 自动将学生用户从班级关联的所有小组中移除
     */
    public function delete(int $id): void
    {
        $student = $this->studentRepository->findById($id);
        
        if (!$student) {
            throw new NotFoundException("Student not found: {$id}");
        }
        
        $userId = $student->user_id;
        $classId = $student->class_id;
        
        $this->dbHelper->transaction(function() use ($id, $userId, $classId) {
            $classGroups = $this->classGroupRepository->findByClassId($classId);
            
            foreach ($classGroups as $classGroup) {
                $this->groupUserRepository->delete($userId, $classGroup->group_id);
            }
            
            $this->studentRepository->delete($id);
        });
    }
}
