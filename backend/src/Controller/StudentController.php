<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StudentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class StudentController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private StudentService $studentService
    ) {
        parent::__construct($responseFactory);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $pageSize = min(max((int)($params['pageSize'] ?? 20), 1), 100);
        $classId = isset($params['class_id']) ? (int)$params['class_id'] : null;

        $result = $this->studentService->getList($page, $pageSize, $classId);
        return $this->success($result);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $student = $this->studentService->getById($id);
        return $this->success($student);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $student = $this->studentService->create($data);
        return $this->success($student, 'Student added successfully');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $this->studentService->delete($id);
        return $this->success([], 'Student removed successfully');
    }
}
