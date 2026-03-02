<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SchoolService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 学校管理控制器
 * 处理学校相关的HTTP请求
 */
class SchoolController
{
    public function __construct(
        private SchoolService $schoolService,
        private ResponseHelper $responseHelper
    ) {}

    /**
     * GET /api/schools
     * 获取学校列表
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $pageSize = (int)($params['pageSize'] ?? 20);
            $search = $params['search'] ?? null;
            
            // 限制pageSize范围
            $pageSize = min(max($pageSize, 1), 100);
            
            $result = $this->schoolService->getList($page, $pageSize, $search);
            
            return $this->responseHelper->success($result);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get schools: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/schools/{id}
     * 获取学校详情
     */
    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $school = $this->schoolService->getById($id);
            
            if (!$school) {
                return $this->responseHelper->error('School not found', 404);
            }
            
            return $this->responseHelper->success($school);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get school: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/schools
     * 创建学校
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            // 验证必填字段
            if (empty($data['name'])) {
                return $this->responseHelper->error('School name is required', 400, [
                    'name' => ['Name is required']
                ]);
            }
            
            $school = $this->schoolService->create($data);
            
            return $this->responseHelper->success($school, 'School created successfully', 201);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to create school: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/schools/{id}
     * 更新学校
     */
    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            $school = $this->schoolService->update($id, $data);
            
            if (!$school) {
                return $this->responseHelper->error('School not found', 404);
            }
            
            return $this->responseHelper->success($school, 'School updated successfully');
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to update school: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/schools/{id}
     * 删除学校
     */
    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $result = $this->schoolService->delete($id);
            
            if (!$result) {
                return $this->responseHelper->error('School not found', 404);
            }
            
            return $this->responseHelper->success([], 'School deleted successfully');
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to delete school: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/schools/{id}/classes
     * 获取学校的班级列表
     */
    public function classes(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $classes = $this->schoolService->getClasses($id);
            
            return $this->responseHelper->success(['classes' => $classes]);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get classes: ' . $e->getMessage(), 500);
        }
    }
}
