<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TeacherService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class TeacherController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private TeacherService $teacherService
    ) {
        parent::__construct($responseFactory);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $pageSize = min(max((int)($params['pageSize'] ?? 20), 1), 100);
        $classId = isset($params['class_id']) ? (int)$params['class_id'] : null;

        $result = $this->teacherService->getList($page, $pageSize, $classId);
        return $this->success($result);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $teacher = $this->teacherService->getById($id);
        return $this->success($teacher);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $teacher = $this->teacherService->create($data);
        return $this->success($teacher, 'Teacher added successfully');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $this->teacherService->delete($id);
        return $this->success([], 'Teacher removed successfully');
    }
}
