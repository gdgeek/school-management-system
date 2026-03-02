<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TeacherService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 教师管理控制器
 * 处理教师相关的HTTP请求
 */
class TeacherController
{
    public function __construct(
        private TeacherService $teacherService,
        private ResponseHelper $responseHelper
    ) {}

    /**
     * GET /api/teachers
     * 获取教师列表
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $pageSize = (int)($params['pageSize'] ?? 20);
            $classId = isset($params['class_id']) ? (int)$params['class_id'] : null;
            
            $pageSize = min(max($pageSize, 1), 100);
            $result = $this->teacherService->getList($page, $pageSize, $classId);
            
            return $this->responseHelper->success($result);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get teachers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/teachers/{id}
     * 获取教师详情
     */
    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $teacher = $this->teacherService->getById($id);
            
            if (!$teacher) {
                return $this->responseHelper->error('Teacher not found', 404);
            }
            
            return $this->responseHelper->success($teacher);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get teacher: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/teachers
     * 添加教师到班级
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            if (empty($data['user_id'])) {
                return $this->responseHelper->error('User ID is required', 400, [
                    'user_id' => ['User ID is required']
                ]);
            }
            if (empty($data['class_id'])) {
                return $this->responseHelper->error('Class ID is required', 400, [
                    'class_id' => ['Class ID is required']
                ]);
            }
            
            $teacher = $this->teacherService->create($data);
            
            return $this->responseHelper->success($teacher, 'Teacher added successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->responseHelper->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to add teacher: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/teachers/{id}
     * 移除教师
     */
    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $result = $this->teacherService->delete($id);
            
            if (!$result) {
                return $this->responseHelper->error('Teacher not found', 404);
            }
            
            return $this->responseHelper->success([], 'Teacher removed successfully');
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to remove teacher: ' . $e->getMessage(), 500);
        }
    }
}
