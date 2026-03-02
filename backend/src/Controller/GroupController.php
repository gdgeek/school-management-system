<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GroupService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 小组管理控制器
 * 处理小组相关的HTTP请求
 */
class GroupController
{
    public function __construct(
        private GroupService $groupService,
        private ResponseHelper $responseHelper
    ) {}

    /**
     * GET /api/groups
     * 获取小组列表
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $pageSize = (int)($params['pageSize'] ?? 20);
            $search = $params['search'] ?? null;
            
            $pageSize = min(max($pageSize, 1), 100);
            $result = $this->groupService->getList($page, $pageSize, $search);
            
            return $this->responseHelper->success($result);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get groups: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/groups/{id}
     * 获取小组详情
     */
    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $group = $this->groupService->getById($id);
            
            if (!$group) {
                return $this->responseHelper->error('Group not found', 404);
            }
            
            return $this->responseHelper->success($group);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/groups
     * 创建小组
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            if (empty($data['name'])) {
                return $this->responseHelper->error('Group name is required', 400, [
                    'name' => ['Name is required']
                ]);
            }
            
            // TODO: 从认证中间件获取当前用户ID
            $userId = $data['user_id'] ?? 1; // 临时使用，实际应从JWT获取
            
            $group = $this->groupService->create($data, $userId);
            
            return $this->responseHelper->success($group, 'Group created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->responseHelper->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to create group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/groups/{id}
     * 更新小组
     */
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            $group = $this->groupService->update($id, $data);
            
            if (!$group) {
                return $this->responseHelper->error('Group not found', 404);
            }
            
            return $this->responseHelper->success($group, 'Group updated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->responseHelper->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to update group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/groups/{id}
     * 删除小组
     */
    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $result = $this->groupService->delete($id);
            
            if (!$result) {
                return $this->responseHelper->error('Group not found', 404);
            }
            
            return $this->responseHelper->success([], 'Group deleted successfully');
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to delete group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/groups/{id}/members
     * 添加成员到小组
     */
    public function addMember(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            if (empty($data['user_id'])) {
                return $this->responseHelper->error('User ID is required', 400, [
                    'user_id' => ['User ID is required']
                ]);
            }
            
            $member = $this->groupService->addMember($id, $data['user_id']);
            
            return $this->responseHelper->success($member, 'Member added successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->responseHelper->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to add member: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/groups/{id}/members/{userId}
     * 从小组移除成员
     */
    public function removeMember(ServerRequestInterface $request, int $id, int $userId): ResponseInterface
    {
        try {
            $result = $this->groupService->removeMember($id, $userId);
            
            if (!$result) {
                return $this->responseHelper->error('Member not found in group', 404);
            }
            
            return $this->responseHelper->success([], 'Member removed successfully');
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to remove member: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/groups/{id}/classes
     * 关联班级到小组
     */
    public function addClass(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            if (empty($data['class_id'])) {
                return $this->responseHelper->error('Class ID is required', 400, [
                    'class_id' => ['Class ID is required']
                ]);
            }
            
            $class = $this->groupService->addClass($id, $data['class_id']);
            
            return $this->responseHelper->success($class, 'Class associated successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->responseHelper->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to associate class: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/groups/{id}/classes/{classId}
     * 取消班级与小组的关联
     */
    public function removeClass(ServerRequestInterface $request, int $id, int $classId): ResponseInterface
    {
        try {
            $result = $this->groupService->removeClass($id, $classId);
            
            if (!$result) {
                return $this->responseHelper->error('Class association not found', 404);
            }
            
            return $this->responseHelper->success([], 'Class association removed successfully');
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to remove class association: ' . $e->getMessage(), 500);
        }
    }
}
