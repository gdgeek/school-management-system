<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ClassService;
use App\Helper\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ClassController
{
    public function __construct(
        private ClassService $classService,
        private ResponseHelper $responseHelper
    ) {}

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);
            $pageSize = (int)($params['pageSize'] ?? 20);
            $schoolId = isset($params['school_id']) ? (int)$params['school_id'] : null;
            
            $pageSize = min(max($pageSize, 1), 100);
            $result = $this->classService->getList($page, $pageSize, $schoolId);
            
            return $this->responseHelper->success($result);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get classes: ' . $e->getMessage(), 500);
        }
    }

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $class = $this->classService->getById($id);
            if (!$class) {
                return $this->responseHelper->error('Class not found', 404);
            }
            return $this->responseHelper->success($class);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get class: ' . $e->getMessage(), 500);
        }
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            if (empty($data['name'])) {
                return $this->responseHelper->error('Class name is required', 400, ['name' => ['Name is required']]);
            }
            if (empty($data['school_id'])) {
                return $this->responseHelper->error('School ID is required', 400, ['school_id' => ['School ID is required']]);
            }
            
            $class = $this->classService->create($data);
            return $this->responseHelper->success($class, 'Class created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->responseHelper->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to create class: ' . $e->getMessage(), 500);
        }
    }

    public function update(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            $class = $this->classService->update($id, $data);
            
            if (!$class) {
                return $this->responseHelper->error('Class not found', 404);
            }
            return $this->responseHelper->success($class, 'Class updated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->responseHelper->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to update class: ' . $e->getMessage(), 500);
        }
    }

    public function delete(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $result = $this->classService->delete($id);
            if (!$result) {
                return $this->responseHelper->error('Class not found', 404);
            }
            return $this->responseHelper->success([], 'Class deleted successfully');
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to delete class: ' . $e->getMessage(), 500);
        }
    }

    public function teachers(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $teachers = $this->classService->getTeachers($id);
            return $this->responseHelper->success(['teachers' => $teachers]);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get teachers: ' . $e->getMessage(), 500);
        }
    }

    public function students(ServerRequestInterface $request, int $id): ResponseInterface
    {
        try {
            $students = $this->classService->getStudents($id);
            return $this->responseHelper->success(['students' => $students]);
        } catch (\Exception $e) {
            return $this->responseHelper->error('Failed to get students: ' . $e->getMessage(), 500);
        }
    }
}
