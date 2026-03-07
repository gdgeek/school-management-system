<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Base controller class providing common functionality for all PSR-15 controllers
 */
abstract class AbstractController
{
    public function __construct(
        protected ResponseFactoryInterface $responseFactory
    ) {}

    /**
     * Create a JSON response with the given data and status code
     *
     * @param mixed $data The data to include in the response
     * @param int $status HTTP status code
     * @return ResponseInterface
     */
    protected function json(mixed $data, int $status = 200): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a success JSON response with standard format
     *
     * @param mixed $data The data to include in the response
     * @param string $message Success message
     * @return ResponseInterface
     */
    protected function success(mixed $data, string $message = 'ok'): ResponseInterface
    {
        return $this->json([
            'code' => 200,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ], 200);
    }

    /**
     * Create an error JSON response with standard format
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @return ResponseInterface
     */
    protected function error(string $message, int $status = 400): ResponseInterface
    {
        return $this->json([
            'code' => $status,
            'message' => $message,
            'data' => null,
            'timestamp' => time(),
        ], $status);
    }

    /**
     * Parse JSON body from request
     *
     * @param ServerRequestInterface $request
     * @return array Parsed JSON data
     * @throws \RuntimeException If JSON parsing fails
     */
    protected function getJsonBody(ServerRequestInterface $request): array
    {
        $body = (string)$request->getBody();
        
        if (empty($body)) {
            return [];
        }

        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Get authenticated user ID from request attributes
     *
     * @param ServerRequestInterface $request
     * @return int|null User ID or null if not authenticated
     */
    protected function getUserId(ServerRequestInterface $request): ?int
    {
        $userId = $request->getAttribute('user_id');
        return $userId !== null ? (int)$userId : null;
    }

    /**
     * Get authenticated user roles from request attributes
     *
     * @param ServerRequestInterface $request
     * @return array User roles array
     */
    protected function getUserRoles(ServerRequestInterface $request): array
    {
        $roles = $request->getAttribute('roles');
        return is_array($roles) ? $roles : [];
    }
}
