<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * 用户控制器
 * 处理用户搜索等功能
 */
class UserController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private UserRepository $userRepository
    ) {
        parent::__construct($responseFactory);
    }

    /**
     * GET /api/users/search
     * 搜索用户（按昵称或用户名）
     */
    public function search(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $keyword = trim($params['keyword'] ?? $params['q'] ?? '');

            if (empty($keyword)) {
                return $this->error('Search keyword is required', 400);
            }

            $page = (int)($params['page'] ?? 1);
            $pageSize = (int)($params['pageSize'] ?? 20);
            $pageSize = min(max($pageSize, 1), 100);
            $offset = ($page - 1) * $pageSize;

            $users = $this->userRepository->search($keyword, $pageSize, $offset);
            $total = $this->userRepository->countSearch($keyword);

            return $this->success([
                'items' => $users,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'totalPages' => (int)ceil($total / $pageSize),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to search users: ' . $e->getMessage(), 500);
        }
    }
}
