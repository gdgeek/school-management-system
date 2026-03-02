<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class ResponseHelper
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    public function success(array $data, string $message = 'Success', int $code = 200): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($code);
        $response->getBody()->write(json_encode([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => time(),
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function error(string $message, int $code = 400, ?array $errors = null): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($code);
        $body = [
            'code' => $code,
            'message' => $message,
            'timestamp' => time(),
        ];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        $response->getBody()->write(json_encode($body));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function paginated(array $items, int $total, int $page, int $pageSize): ResponseInterface
    {
        return $this->success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => (int)ceil($total / $pageSize),
            ],
        ]);
    }
}
