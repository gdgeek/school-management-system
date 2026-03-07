# PSR-15 Middleware Architecture

## Overview

The backend now runs entirely on a PSR-15 middleware stack. The legacy switch-case routing in `public/index.php` has been removed. All HTTP requests flow through a DI-managed middleware pipeline.

## Request Flow

```
HTTP Request
    │
    ▼
public/index.php
    │  Creates PSR-7 ServerRequest from globals
    │  Loads DI container (config/container.php)
    ▼
Application::handle()
    │
    ▼
CorsMiddleware       ← Sets CORS headers
    │
    ▼
SecurityMiddleware   ← Security headers (X-Frame-Options, etc.)
    │
    ▼
RouterMiddleware     ← FastRoute dispatch
    │  Matches path + method → controller::method
    │  Injects route params as request attributes (e.g. 'id')
    │  Runs route-level middleware (e.g. AuthMiddleware)
    ▼
AuthMiddleware       ← Validates JWT, sets user_id / roles on request
    │
    ▼
Controller::method(ServerRequestInterface $request)
    │
    ▼
PSR-7 ResponseInterface
    │
    ▼
emitResponse()       ← Writes headers + body to PHP output
```

## Key Files

| File | Purpose |
|------|---------|
| `public/index.php` | Entry point — builds request, runs app, emits response |
| `config/container.php` | Builds the DI container |
| `config/di.php` | All service/controller/repository definitions |
| `config/routes.php` | All route definitions (FastRoute syntax) |
| `config/middleware.php` | Global middleware stack configuration |
| `src/Application.php` | Runs the middleware pipeline |
| `src/Middleware/RouterMiddleware.php` | Route matching + controller dispatch |
| `src/Middleware/AuthMiddleware.php` | JWT validation |
| `src/Middleware/CorsMiddleware.php` | CORS headers |
| `src/Controller/AbstractController.php` | Base class with `success()`, `error()`, `getJsonBody()`, `getUserId()` |

## Controller Pattern

All controllers extend `AbstractController`:

```php
class MyController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private MyService $myService
    ) {
        parent::__construct($responseFactory);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $result = $this->myService->getList(...);
        return $this->success($result);
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $id = (int)$request->getAttribute('id');  // from route params
        $item = $this->myService->getById($id);
        if (!$item) return $this->error('Not found', 404);
        return $this->success($item);
    }
}
```

Key rules:
- Every method takes **only** `ServerRequestInterface $request`
- Route parameters (e.g. `{id}`) are read via `$request->getAttribute('id')`
- Authenticated user ID is read via `$this->getUserId($request)` (set by AuthMiddleware)
- Use `$this->success($data)` and `$this->error($message, $code)` for responses

## Route Definition Pattern

```php
// config/routes.php
[
    'name'       => 'resource.action',
    'pattern'    => '/api/resource/{id:\d+}',
    'methods'    => ['GET'],
    'handler'    => \App\Controller\MyController::class . '::show',
    'middleware' => [\App\Middleware\AuthMiddleware::class],
],
```

## DI Registration Pattern

```php
// config/di.php
\App\Controller\MyController::class => static function (ContainerInterface $container) {
    return new \App\Controller\MyController(
        $container->get(ResponseFactoryInterface::class),
        $container->get(\App\Service\MyService::class)
    );
},
```

## Migrated Endpoints

| Module | Endpoints |
|--------|-----------|
| Health | `GET /api/health`, `GET /api/health/detailed`, `GET /api/version` |
| Auth | `POST /api/auth/login`, `GET /api/auth/user` |
| Schools | `GET/POST /api/schools`, `GET/PUT/DELETE /api/schools/{id}` |
| Classes | `GET/POST /api/classes`, `GET/PUT/DELETE /api/classes/{id}` |
| Groups | `GET/POST /api/groups`, `GET/PUT/DELETE /api/groups/{id}`, member/class sub-resources |
| Students | `GET/POST /api/students`, `GET/DELETE /api/students/{id}` |
| Teachers | `GET/POST /api/teachers`, `GET/DELETE /api/teachers/{id}` |
| Users | `GET /api/users/search` |
