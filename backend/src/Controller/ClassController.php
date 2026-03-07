<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ClassService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class ClassController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private ClassService $classService
    ) {
        parent::__construct($responseFactory);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $pageSize = min(max((int)($params['pageSize'] ?? 20), 1), 100);
        $schoolId = isset($params['school_id']) ? (int)$params['school_id'] : null;

        $result = $this->classService->getList($page, $pageSize, $schoolId);
        return $this->success($result);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $class = $this->classService->getById($id);
        return $this->success($class);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $currentUserId = $this->getUserId($request);
        $class = $this->classService->create($data, $currentUserId);
        return $this->success($class, 'Class created successfully');
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $data = $this->getJsonBody($request);
        $class = $this->classService->update($id, $data);
        return $this->success($class, 'Class updated successfully');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $params = $request->getQueryParams();
        $deleteGroups = isset($params['deleteGroups']) && $params['deleteGroups'] === 'true';
        $this->classService->delete($id, $deleteGroups);
        return $this->success([], 'Class deleted successfully');
    }

    public function teachers(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $teachers = $this->classService->getTeachers($id);
        return $this->success(['teachers' => $teachers]);
    }

    public function students(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $students = $this->classService->getStudents($id);
        return $this->success(['students' => $students]);
    }
}
