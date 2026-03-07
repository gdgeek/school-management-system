<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\GroupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class GroupController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private GroupService $groupService
    ) {
        parent::__construct($responseFactory);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $pageSize = min(max((int)($params['pageSize'] ?? 20), 1), 100);
        $search = $params['search'] ?? null;

        $result = $this->groupService->getList($page, $pageSize, $search);
        return $this->success($result);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $group = $this->groupService->getById($id);
        return $this->success($group);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->getJsonBody($request);
        $userId = $this->getUserId($request);
        $group = $this->groupService->create($data, $userId);
        return $this->success($group, 'Group created successfully');
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $data = $this->getJsonBody($request);
        $group = $this->groupService->update($id, $data);
        return $this->success($group, 'Group updated successfully');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $this->groupService->delete($id);
        return $this->success([], 'Group deleted successfully');
    }

    public function addMember(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $data = $this->getJsonBody($request);
        $member = $this->groupService->addMember($id, (int)$data['user_id']);
        return $this->success($member, 'Member added successfully');
    }

    public function removeMember(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $userId = (int)$request->getAttribute('userId');
        $this->groupService->removeMember($id, $userId);
        return $this->success([], 'Member removed successfully');
    }

    public function addClass(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $data = $this->getJsonBody($request);
        $class = $this->groupService->addClass($id, (int)$data['class_id']);
        return $this->success($class, 'Class associated successfully');
    }

    public function removeClass(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');
        $classId = (int)$request->getAttribute('classId');
        $this->groupService->removeClass($id, $classId);
        return $this->success([], 'Class association removed successfully');
    }
}
