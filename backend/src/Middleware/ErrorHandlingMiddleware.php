<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Service\ErrorTracker;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ErrorHandlingMiddleware
 *
 * Wraps the entire middleware stack to catch any unhandled exceptions thrown
 * by downstream middleware or controllers.  On exception it:
 *   1. Converts the exception to a 500 JSON response.
 *   2. Records the error via ErrorTracker (which handles logging and alerting).
 *
 * This middleware should be placed early in the global stack so it catches
 * exceptions from all subsequent middleware.
 */
class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ErrorTracker $errorTracker,
        private ResponseFactoryInterface $responseFactory
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            $response = $handler->handle($request);

            // Also track 5xx responses that were returned without throwing
            if ($response->getStatusCode() >= 500) {
                $this->errorTracker->recordError($request, $response->getStatusCode());
            }

            return $response;
        } catch (ValidationException $e) {
            $response = $this->responseFactory->createResponse(422);
            $payload = json_encode([
                'code'      => 422,
                'message'   => $e->getMessage(),
                'errors'    => $e->getErrors(),
                'data'      => null,
                'timestamp' => time(),
            ]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        } catch (NotFoundException $e) {
            $response = $this->responseFactory->createResponse(404);
            $response->getBody()->write(json_encode([
                'code'      => 404,
                'message'   => $e->getMessage(),
                'data'      => null,
                'timestamp' => time(),
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (ForbiddenException $e) {
            $response = $this->responseFactory->createResponse(403);
            $response->getBody()->write(json_encode([
                'code'      => 403,
                'message'   => $e->getMessage(),
                'data'      => null,
                'timestamp' => time(),
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (UnauthorizedException $e) {
            $response = $this->responseFactory->createResponse(401);
            $response->getBody()->write(json_encode([
                'code'      => 401,
                'message'   => $e->getMessage(),
                'data'      => null,
                'timestamp' => time(),
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $this->errorTracker->recordError($request, 500, $e);

            $response = $this->responseFactory->createResponse(500);
            $response->getBody()->write(json_encode([
                'code'      => 500,
                'message'   => 'Internal Server Error',
                'data'      => null,
                'timestamp' => time(),
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }
    }
}
