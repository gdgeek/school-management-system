<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SchoolService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class SchoolController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private SchoolService $schoolService
    ) {
        parent::__construct($responseFactory);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $pageSize = min(max((int)($params['pageSize'] ?? 20), 1), 100);
        $search = $params['search'] ?? null;

        $result = $this->schoolService->getList($page, $pageSize, $search);
        return $this->success($result);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $school = $this->schoolService->getById($id);
        return $this->success($school);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $school = $this->schoolService->create($data);
        return $this->success($school, 'School created successfully');
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $data = $this->getJsonBody($request);
        $school = $this->schoolService->update($id, $data);
        return $this->success($school, 'School updated successfully');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $this->schoolService->delete($id);
        return $this->success([], 'School deleted successfully');
    }

    public function classes(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $classes = $this->schoolService->getClasses($id);
        return $this->success(['classes' => $classes]);
    }
}
