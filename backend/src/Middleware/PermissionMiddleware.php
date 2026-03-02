<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\ForbiddenException;
use App\Helper\PermissionHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * 权限检查中间件
 * 基于角色的访问控制（RBAC）
 */
class PermissionMiddleware implements MiddlewareInterface
{
    private PermissionHelper $permissionHelper;
    private ResponseFactoryInterface $responseFactory;
    private array $requiredRoles;

    public function __construct(
        PermissionHelper $permissionHelper,
        ResponseFactoryInterface $responseFactory,
        array $requiredRoles = []
    ) {
        $this->permissionHelper = $permissionHelper;
        $this->responseFactory = $responseFactory;
        $this->requiredRoles = $requiredRoles;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // 获取用户信息（由AuthMiddleware注入）
            $userId = $request->getAttribute('user_id');
            $userRoles = $request->getAttribute('roles', []);
            
            if (empty($userId)) {
                return $this->forbiddenResponse('User not authenticated');
            }

            // 检查是否有所需角色
            if (!empty($this->requiredRoles)) {
                $hasPermission = $this->checkRoles($userRoles, $this->requiredRoles);
                
                if (!$hasPermission) {
                    return $this->forbiddenResponse('Insufficient permissions');
                }
            }

            return $handler->handle($request);
            
        } catch (ForbiddenException $e) {
            return $this->forbiddenResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->forbiddenResponse('Permission check failed');
        }
    }

    /**
     * 检查用户是否拥有所需角色之一
     */
    private function checkRoles(array $userRoles, array $requiredRoles): bool
    {
        // 系统管理员拥有所有权限
        if (in_array('admin', $userRoles, true)) {
            return true;
        }

        // 检查是否有任一所需角色
        foreach ($requiredRoles as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 返回403禁止访问响应
     */
    private function forbiddenResponse(string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(403);
        $response->getBody()->write(json_encode([
            'code' => 403,
            'message' => $message,
            'timestamp' => time(),
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
