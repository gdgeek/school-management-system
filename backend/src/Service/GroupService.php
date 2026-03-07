<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\GroupRepository;
use App\Repository\GroupUserRepository;
use App\Repository\ClassGroupRepository;
use App\Repository\UserRepository;
use App\Repository\ClassRepository;
use App\Repository\TeacherRepository;
use App\Repository\StudentRepository;
use App\Model\Group;
use App\Model\GroupUser;
use App\Model\ClassGroup;
use App\Helper\DatabaseHelper;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

/**
 * 小组管理服务
 * 处理小组相关的业务逻辑
 */
class GroupService
{
    public function __construct(
        private GroupRepository $groupRepository,
        private GroupUserRepository $groupUserRepository,
        private ClassGroupRepository $classGroupRepository,
        private UserRepository $userRepository,
        private ClassRepository $classRepository,
        private TeacherRepository $teacherRepository,
        private StudentRepository $studentRepository,
        private DatabaseHelper $dbHelper
    ) {}

    /**
     * 获取小组列表
     */
    public function getList(int $page = 1, int $pageSize = 20, ?string $search = null): array
    {
        $offset = ($page - 1) * $pageSize;
        
        if ($search) {
            $groups = $this->groupRepository->search($search, $pageSize, $offset);
        } else {
            $groups = $this->groupRepository->findAll($pageSize, $offset);
        }
        
        $total = $this->groupRepository->count();
        
        return [
            'items' => array_map(fn($group) => $group->toArray(), $groups),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => (int)ceil($total / $pageSize),
            ],
        ];
    }

    /**
     * 获取小组详情（包含创建者、成员和关联班级）
     */
    public function getById(int $id): array
    {
        $group = $this->groupRepository->findById($id);
        
        if (!$group) {
            throw new NotFoundException("Group not found: {$id}");
        }
        
        $groupData = $group->toArray();
        
        // 加载创建者信息
        $creator = $this->userRepository->findById($group->user_id);
        if ($creator) {
            $groupData['creator'] = [
                'id' => $creator['id'],
                'username' => $creator['username'],
                'nickname' => $creator['nickname'],
                'avatar' => $creator['avatar'] ?? null,
            ];
        } else {
            $groupData['creator'] = null;
        }
        
        // 加载成员列表（优化：批量查询避免 N+1）
        $members = $this->groupUserRepository->findByGroupId($id);
        $groupData['members'] = [];
        
        if (!empty($members)) {
            // 收集所有成员的 user_id
            $userIds = array_map(fn($member) => $member->user_id, $members);
            $userIds = array_unique($userIds);
            
            // 批量查询所有用户信息（一次查询）
            $users = $this->userRepository->findByIds($userIds);
            
            // 以 user_id 为 key 建立索引
            $userMap = [];
            foreach ($users as $user) {
                $userMap[$user['id']] = $user;
            }
            
            // 构建成员列表
            foreach ($members as $member) {
                if (isset($userMap[$member->user_id])) {
                    $user = $userMap[$member->user_id];
                    $groupData['members'][] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'nickname' => $user['nickname'],
                        'avatar' => $user['avatar'] ?? null,
                    ];
                }
            }
        }
        
        // 加载关联班级
        $classRelations = $this->classGroupRepository->findByGroupId($id);
        $groupData['classes'] = [];
        foreach ($classRelations as $relation) {
            $class = $this->classRepository->findById($relation->class_id);
            if ($class) {
                $groupData['classes'][] = $class->toArray();
            }
        }
        
        return $groupData;
    }

    /**
     * 创建小组
     */
    /**
     * 创建小组
     */
    public function create(array $data, int $userId): array
    {
        if (empty($userId)) {
            throw new ValidationException(['user_id' => 'User ID is required']);
        }

        if (empty($data['name'])) {
            throw new ValidationException(['name' => 'Group name is required']);
        }

        $group = new Group();
        $group->name = $data['name'];
        $group->description = $data['description'] ?? null;
        $group->user_id = $userId; // 创建者
        $group->image_id = null;
        $group->info = $data['info'] ?? [];

        $id = $this->groupRepository->create($group);
        $group->id = $id;

        return $group->toArray();
    }

    /**
     * 更新小组
     */
    public function update(int $id, array $data): array
    {
        $group = $this->groupRepository->findById($id);
        
        if (!$group) {
            throw new NotFoundException("Group not found: {$id}");
        }
        
        if (isset($data['name'])) {
            $group->name = $data['name'];
        }
        if (isset($data['description'])) {
            $group->description = $data['description'];
        }
        if (isset($data['info'])) {
            $group->info = $data['info'];
        }
        
        $this->groupRepository->update($group);
        
        return $group->toArray();
    }

    /**
     * 删除小组（级联删除关联的班级）
     */
    public function delete(int $id): void
    {
        $group = $this->groupRepository->findById($id);
        
        if (!$group) {
            throw new NotFoundException("Group not found: {$id}");
        }
        
        $this->dbHelper->transaction(function() use ($id) {
            // 获取关联的班级
            $classRelations = $this->classGroupRepository->findByGroupId($id);
            
            // 删除关联的班级（包括班级下的教师和学生）
            foreach ($classRelations as $relation) {
                $classId = $relation->class_id;
                
                // 删除班级下的教师和学生
                $this->teacherRepository->deleteByClassId($classId);
                $this->studentRepository->deleteByClassId($classId);
                
                // 删除班级-小组关联
                $this->classGroupRepository->delete($classId, $id);
                
                // 删除班级
                $this->classRepository->delete($classId);
            }
            
            // 删除小组成员
            $this->groupUserRepository->deleteByGroupId($id);
            
            // 删除小组
            $this->groupRepository->delete($id);
        });
    }

    /**
     * 添加成员到小组
     */
    public function addMember(int $groupId, int $userId): array
    {
        $group = $this->groupRepository->findById($groupId);
        if (!$group) {
            throw new NotFoundException("Group not found: {$groupId}");
        }
        
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new ValidationException(['user_id' => 'Invalid user ID']);
        }
        
        if ($this->groupUserRepository->exists($userId, $groupId)) {
            throw new ValidationException(['user_id' => 'User is already a member of this group']);
        }
        
        $groupUser = new GroupUser();
        $groupUser->user_id = $userId;
        $groupUser->group_id = $groupId;
        
        $this->groupUserRepository->create($groupUser);
        
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'nickname' => $user['nickname'],
            'avatar' => $user['avatar'] ?? null,
        ];
    }

    /**
     * 从小组移除成员
     */
    public function removeMember(int $groupId, int $userId): void
    {
        $group = $this->groupRepository->findById($groupId);
        if (!$group) {
            throw new NotFoundException("Group not found: {$groupId}");
        }
        
        if (!$this->groupUserRepository->exists($userId, $groupId)) {
            throw new NotFoundException('User is not a member of this group');
        }
        
        $this->groupUserRepository->delete($userId, $groupId);
    }

    /**
     * 关联班级到小组
     */
    public function addClass(int $groupId, int $classId): array
    {
        $group = $this->groupRepository->findById($groupId);
        if (!$group) {
            throw new NotFoundException("Group not found: {$groupId}");
        }
        
        $class = $this->classRepository->findById($classId);
        if (!$class) {
            throw new ValidationException(['class_id' => 'Invalid class ID']);
        }
        
        if ($this->classGroupRepository->exists($classId, $groupId)) {
            throw new ValidationException(['class_id' => 'Class is already associated with this group']);
        }
        
        $classGroup = new ClassGroup();
        $classGroup->class_id = $classId;
        $classGroup->group_id = $groupId;
        
        $this->classGroupRepository->create($classGroup);
        
        return $class->toArray();
    }

    /**
     * 取消班级与小组的关联
     */
    public function removeClass(int $groupId, int $classId): void
    {
        $group = $this->groupRepository->findById($groupId);
        if (!$group) {
            throw new NotFoundException("Group not found: {$groupId}");
        }
        
        if (!$this->classGroupRepository->exists($classId, $groupId)) {
            throw new NotFoundException('Class is not associated with this group');
        }
        
        $this->classGroupRepository->delete($classId, $groupId);
    }
}
