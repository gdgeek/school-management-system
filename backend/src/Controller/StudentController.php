<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StudentService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 学生管理控制器
 * 处理学生相关的HTTP请求
 */
class StudentController
{
    public function __construct(
        private StudentService $studentService,
        private ResponseHelper $responseHelper
    ) {}

    /**
     * GET /api/students
     * 获取学生列表
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $pageSize = (int)($params['pageSize'] ?? 20);
            $classId = isset($params['class_id']) ? (int)$params['class_id'] : null;
            
            $pageSize = min(max($pageSize, 1), 100);
            $result = $this->studentService->getList($page, $pageSize, $classId);
            
            return $this->responseHelper->success($result);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get students: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/students/{id}
     * 获取学生详情
     */
    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $student = $this->studentService->getById($id);
            
            if (!$student) {
                return $this->responseHelper->error('Student not found', 404);
            }
            
            return $this->responseHelper->success($student);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get student: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/students
     * 添加学生到班级
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
            
            $student = $this->studentService->create($data);
            
            return $this->responseHelper->success($student, 'Student added successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->responseHelper->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to add student: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/students/{id}
     * 移除学生
     */
    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $result = $this->studentService->delete($id);
            
            if (!$result) {
                return $this->responseHelper->error('Student not found', 404);
            }
            
            return $this->responseHelper->success([], 'Student removed successfully');
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to remove student: ' . $e->getMessage(), 500);
        }
    }
}
