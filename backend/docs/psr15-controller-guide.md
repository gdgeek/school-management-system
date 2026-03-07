# Adding New Controllers to the PSR-15 Stack

Follow these steps to add a new endpoint.

## 1. Create the Controller

```php
// src/Controller/WidgetController.php
namespace App\Controller;

use App\Service\WidgetService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class WidgetController extends AbstractController
{
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        private WidgetService $widgetService
    ) {
        parent::__construct($responseFactory);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $params   = $request->getQueryParams();
            $page     = (int)($params['page'] ?? 1);
            $pageSize = min(max((int)($params['pageSize'] ?? 20), 1), 100);
            return $this->success($this->widgetService->getList($page, $pageSize));
        } catch (\Exception $e) {
            return $this->error('Failed to get widgets: ' . $e->getMessage(), 500);
        }
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $id   = (int)$request->getAttribute('id');
            $item = $this->widgetService->getById($id);
            return $item ? $this->success($item) : $this->error('Widget not found', 404);
        } catch (\Exception $e) {
            return $this->error('Failed to get widget: ' . $e->getMessage(), 500);
        }
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data   = $this->getJsonBody($request);
            $userId = $this->getUserId($request);
            if (!$userId) return $this->error('Unauthorized', 401);
            if (empty($data['name'])) return $this->error('Name is required', 400);
            return $this->success($this->widgetService->create($data, $userId), 'Created');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->error('Failed to create widget: ' . $e->getMessage(), 500);
        }
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $id   = (int)$request->getAttribute('id');
            $data = $this->getJsonBody($request);
            $item = $this->widgetService->update($id, $data);
            return $item ? $this->success($item, 'Updated') : $this->error('Widget not found', 404);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->error('Failed to update widget: ' . $e->getMessage(), 500);
        }
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $id = (int)$request->getAttribute('id');
            return $this->widgetService->delete($id)
                ? $this->success([], 'Deleted')
                : $this->error('Widget not found', 404);
        } catch (\Exception $e) {
            return $this->error('Failed to delete widget: ' . $e->getMessage(), 500);
        }
    }
}
```

## 2. Register Routes

Add to `config/routes.php`:

```php
[
    'name'       => 'widgets.list',
    'pattern'    => '/api/widgets',
    'methods'    => ['GET'],
    'handler'    => \App\Controller\WidgetController::class . '::index',
    'middleware' => [\App\Middleware\AuthMiddleware::class],
],
[
    'name'       => 'widgets.show',
    'pattern'    => '/api/widgets/{id:\d+}',
    'methods'    => ['GET'],
    'handler'    => \App\Controller\WidgetController::class . '::show',
    'middleware' => [\App\Middleware\AuthMiddleware::class],
],
[
    'name'       => 'widgets.create',
    'pattern'    => '/api/widgets',
    'methods'    => ['POST'],
    'handler'    => \App\Controller\WidgetController::class . '::create',
    'middleware' => [\App\Middleware\AuthMiddleware::class],
],
[
    'name'       => 'widgets.update',
    'pattern'    => '/api/widgets/{id:\d+}',
    'methods'    => ['PUT'],
    'handler'    => \App\Controller\WidgetController::class . '::update',
    'middleware' => [\App\Middleware\AuthMiddleware::class],
],
[
    'name'       => 'widgets.delete',
    'pattern'    => '/api/widgets/{id:\d+}',
    'methods'    => ['DELETE'],
    'handler'    => \App\Controller\WidgetController::class . '::delete',
    'middleware' => [\App\Middleware\AuthMiddleware::class],
],
```

## 3. Register in DI Container

Add to `config/di.php`:

```php
\App\Service\WidgetService::class => static function (ContainerInterface $container) {
    return new \App\Service\WidgetService(
        $container->get(\App\Repository\WidgetRepository::class),
        new \App\Helper\DatabaseHelper($container->get(\PDO::class))
    );
},

\App\Controller\WidgetController::class => static function (ContainerInterface $container) {
    return new \App\Controller\WidgetController(
        $container->get(ResponseFactoryInterface::class),
        $container->get(\App\Service\WidgetService::class)
    );
},
```

## 4. Restart Docker

```bash
docker restart xrugc-school-backend
```

## 5. Use Route Name Constants

`src/Route/RouteNames.php` contains IDE-autocomplete-friendly constants for every route name defined in `config/routes.php`.

```php
use App\Route\RouteNames;

// Instead of a magic string:
$router->generate('schools.list');

// Use the constant:
$router->generate(RouteNames::SCHOOLS_LIST);
```

To regenerate the file after adding or renaming routes in `config/routes.php`:

```bash
php bin/generate-route-constants.php
# or
bin/generate-route-constants.sh
```

The script converts dot-separated camelCase names to `UPPER_SNAKE_CASE` constants (e.g. `groups.addMember` → `GROUPS_ADD_MEMBER`).

> **Note:** `RouteNames.php` is a generated file — do not edit it manually.

## 6. Write Tests

- Unit test: `tests/Unit/Controller/WidgetControllerTest.php` — mock the service, test each method
- Integration test: `tests/Integration/WidgetIntegrationTest.php` — test through the full PSR-15 stack
