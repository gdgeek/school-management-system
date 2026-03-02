<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\GroupRepository;
use App\Repository\GroupUserRepository;
use App\Repository\ClassGroupRepository;
use App\Repository\UserRepository;
use App\Repository\ClassRepository;
use App\Model\Group;
use App\Model\GroupUser;
use App\Model\ClassGroup;
use App\Helper\DatabaseHelper;

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
     * 获取小组详情（包含成员和关联班级）
     */
    public function getById(int $id): ?array
    {
        $group = $this->groupRepository->findById($id);
        
        if (!$group) {
            return null;
        }
        
        $groupData = $group->toArray();
        
        // 加载成员列表
        $members = $this->groupUserRepository->findByGroupId($id);
        $groupData['members'] = [];
        foreach ($members as $member) {
            $user = $this->userRepository->findById($member->user_id);
            if ($user) {
                $groupData['members'][] = [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar,
                ];
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
    public function create(array $data, int $userId): array
    {
        // 验证必填字段
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Group name is required');
        }
        
        $group = new Group();
        $group->name = $data['name'];
        $group->description = $data['description'] ?? null;
        $group->user_id = $userId; // 创建者
        $group->image_id = $data['image_id'] ?? null;
        $group->info = $data['info'] ?? [];
        
        $id = $this->groupRepository->create($group);
        $group->id = $id;
        
        return $group->toArray();
    }

    /**
     * 更新小组
     */
    public function update(int $id, array $data): ?array
    {
        $group = $this->groupRepository->findById($id);
        
        if (!$group) {
            return null;
        }
        
        if (isset($data['name'])) {
            $group->name = $data['name'];
        }
        if (isset($data['description'])) {
            $group->description = $data['description'];
        }
        if (isset($data['image_id'])) {
            $group->image_id = $data['image_id'];
        }
        if (isset($data['info'])) {
            $group->info = $data['info'];
        }
        
        $this->groupRepository->update($group);
        
        return $group->toArray();
    }

    /**
     * 删除小组（级联删除成员和班级关联）
     */
    public function delete(int $id): bool
    {
        $group = $this->groupRepository->findById($id);
        
        if (!$group) {
            return false;
        }
        
        return $this->dbHelper->transaction(function() use ($id) {
            // 删除小组成员
            $this->groupUserRepository->deleteByGroupId($id);
            // 删除班级关联
            $this->classGroupRepository->deleteByGroupId($id);
            // 删除小组
            return $this->groupRepository->delete($id);
        });
    }

    /**
     * 添加成员到小组
     */
    public function addMember(int $groupId, int $userId): array
    {
        // 验证小组存在
        $group = $this->groupRepository->findById($groupId);
        if (!$group) {
            throw new \InvalidArgumentException('Invalid group ID');
        }
        
        // 验证用户存在
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new \InvalidArgumentException('Invalid user ID');
        }
        
        // 检查是否已存在
        if ($this->groupUserRepository->exists($userId, $groupId)) {
            throw new \InvalidArgumentException('User is already a member of this group');
        }
        
        $groupUser = new GroupUser();
        $groupUser->user_id = $userId;
        $groupUser->group_id = $groupId;
        
        $this->groupUserRepository->create($groupUser);
        
        return [
            'id' => $user->id,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
        ];
    }

    /**
     * 从小组移除成员
     */
    public function removeMember(int $groupId, int $userId): bool
    {
        // 验证小组存在
        $group = $this->groupRepository->findById($groupId);
        if (!$group) {
            return false;
        }
        
        // 检查成员是否存在
        if (!$this->groupUserRepository->exists($userId, $groupId)) {
            return false;
        }
        
        return $this->groupUserRepository->delete($userId, $groupId);
    }

    /**
     * 关联班级到小组
     */
    public function addClass(int $groupId, int $classId): array
    {
        // 验证小组存在
        $group = $this->groupRepository->findById($groupId);
        if (!$group) {
            throw new \InvalidArgumentException('Invalid group ID');
        }
        
        // 验证班级存在
        $class = $this->classRepository->findById($classId);
        if (!$class) {
            throw new \InvalidArgumentException('Invalid class ID');
        }
        
        // 检查是否已存在
        if ($this->classGroupRepository->exists($classId, $groupId)) {
            throw new \InvalidArgumentException('Class is already associated with this group');
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
    public function removeClass(int $groupId, int $classId): bool
    {
        // 验证小组存在
        $group = $this->groupRepository->findById($groupId);
        if (!$group) {
            return false;
        }
        
        // 检查关联是否存在
        if (!$this->classGroupRepository->exists($classId, $groupId)) {
            return false;
        }
        
        return $this->classGroupRepository->delete($classId, $groupId);
    }
}
