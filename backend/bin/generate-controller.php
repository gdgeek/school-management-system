#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PSR-15 Controller Code Generator
 *
 * Usage:
 *   php bin/generate-controller.php <Name>
 *   php bin/generate-controller.php <Name> --with-service
 *   php bin/generate-controller.php <Name> --with-service --force
 *
 * Examples:
 *   php bin/generate-controller.php Product
 *   php bin/generate-controller.php Product --with-service
 *   php bin/generate-controller.php Product --with-service --force
 */

// ── Helpers ──────────────────────────────────────────────────────────────────

function printLine(string $text = ''): void
{
    echo $text . PHP_EOL;
}

function printSuccess(string $text): void
{
    echo "\033[32m✓ {$text}\033[0m" . PHP_EOL;
}

function printError(string $text): void
{
    echo "\033[31m✗ {$text}\033[0m" . PHP_EOL;
}

function printInfo(string $text): void
{
    echo "\033[36m  {$text}\033[0m" . PHP_EOL;
}

function printWarning(string $text): void
{
    echo "\033[33m⚠ {$text}\033[0m" . PHP_EOL;
}

function printHeader(string $text): void
{
    $line = str_repeat('─', strlen($text) + 4);
    echo PHP_EOL . "\033[1m┌{$line}┐\033[0m" . PHP_EOL;
    echo "\033[1m│  {$text}  │\033[0m" . PHP_EOL;
    echo "\033[1m└{$line}┘\033[0m" . PHP_EOL . PHP_EOL;
}

// ── Argument Parsing ──────────────────────────────────────────────────────────

$args = array_slice($argv, 1);

$helpRequested = in_array('--help', $args, true) || in_array('-h', $args, true);

if ($helpRequested || empty($args)) {
    printLine("PSR-15 Controller Generator");
    printLine();
    printLine("Usage:");
    printLine("  php bin/generate-controller.php <Name> [options]");
    printLine();
    printLine("Arguments:");
    printLine("  <Name>           Controller name in PascalCase (e.g. Product, BlogPost)");
    printLine();
    printLine("Options:");
    printLine("  --with-service   Also generate a Service stub");
    printLine("  --force          Overwrite existing files");
    printLine("  --help, -h       Show this help message");
    printLine();
    printLine("Examples:");
    printLine("  php bin/generate-controller.php Product");
    printLine("  php bin/generate-controller.php Product --with-service");
    printLine("  php bin/generate-controller.php Product --with-service --force");
    exit($helpRequested ? 0 : 1);
}

$name        = null;
$withService = false;
$force       = false;

foreach ($args as $arg) {
    if ($arg === '--with-service') {
        $withService = true;
    } elseif ($arg === '--force') {
        $force = true;
    } elseif (str_starts_with($arg, '--')) {
        printError("Unknown option: {$arg}");
        exit(1);
    } else {
        if ($name !== null) {
            printError("Unexpected argument: {$arg}");
            exit(1);
        }
        $name = $arg;
    }
}

if ($name === null) {
    printError("Controller name is required.");
    printLine("  Usage: php bin/generate-controller.php <Name>");
    exit(1);
}

// Validate name: must be PascalCase letters only
if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
    printError("Controller name must start with an uppercase letter and contain only alphanumeric characters.");
    printLine("  Examples: Product, BlogPost, UserProfile");
    exit(1);
}

// ── Path Resolution ───────────────────────────────────────────────────────────

// Script lives at bin/generate-controller.php; backend root is one level up
$backendRoot    = dirname(__DIR__);
$controllerDir  = $backendRoot . '/src/Controller';
$serviceDir     = $backendRoot . '/src/Service';

$controllerFile = "{$controllerDir}/{$name}Controller.php";
$serviceFile    = "{$serviceDir}/{$name}Service.php";

$nameLower      = lcfirst($name);
$nameSnake      = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
$namePlural     = $nameSnake . 's'; // simple pluralisation

// ── Pre-flight Checks ─────────────────────────────────────────────────────────

$errors = [];

if (!is_dir($controllerDir)) {
    $errors[] = "Controller directory not found: {$controllerDir}";
}

if ($withService && !is_dir($serviceDir)) {
    $errors[] = "Service directory not found: {$serviceDir}";
}

if (!$force && file_exists($controllerFile)) {
    $errors[] = "Controller already exists: src/Controller/{$name}Controller.php  (use --force to overwrite)";
}

if ($withService && !$force && file_exists($serviceFile)) {
    $errors[] = "Service already exists: src/Service/{$name}Service.php  (use --force to overwrite)";
}

if (!empty($errors)) {
    foreach ($errors as $err) {
        printError($err);
    }
    exit(1);
}

// ── Templates ─────────────────────────────────────────────────────────────────

function controllerTemplate(string $name, bool $withService): string
{
    $nameLower  = lcfirst($name);
    $nameSnake  = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    $namePlural = $nameSnake . 's';

    $serviceUse         = $withService ? "\nuse App\\Service\\{$name}Service;" : '';
    $serviceProperty    = $withService ? "\n        private {$name}Service \${$nameLower}Service" : '';
    $serviceConstructor = $withService ? "\n        parent::__construct(\$responseFactory);" : "\n        parent::__construct(\$responseFactory);";
    $serviceCall        = $withService ? "\$this->{$nameLower}Service" : '/* TODO: inject service */';

    if ($withService) {
        $constructorBody = <<<PHP
    public function __construct(
        ResponseFactoryInterface \$responseFactory,
        private {$name}Service \${$nameLower}Service
    ) {
        parent::__construct(\$responseFactory);
    }
PHP;
    } else {
        $constructorBody = <<<PHP
    public function __construct(
        ResponseFactoryInterface \$responseFactory
    ) {
        parent::__construct(\$responseFactory);
    }
PHP;
    }

    $serviceRef = $withService ? "\$this->{$nameLower}Service" : '/* TODO: inject service */';

    return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controller;
{$serviceUse}
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * {$name} controller
 *
 * Handles HTTP requests for the {$nameSnake} resource.
 */
class {$name}Controller extends AbstractController
{
{$constructorBody}

    /**
     * GET /api/{$namePlural}
     *
     * @param ServerRequestInterface \$request
     * @param array                  \$params  Route parameters
     * @return ResponseInterface
     */
    public function index(ServerRequestInterface \$request, array \$params = []): ResponseInterface
    {
        try {
            \$query    = \$request->getQueryParams();
            \$page     = (int)(\$query['page'] ?? 1);
            \$pageSize = min(max((int)(\$query['pageSize'] ?? 20), 1), 100);

            // TODO: call {$serviceRef}->getList(\$page, \$pageSize)
            \$result = ['items' => [], 'pagination' => ['total' => 0, 'page' => \$page, 'pageSize' => \$pageSize]];

            return \$this->success(\$result);
        } catch (\Exception \$e) {
            return \$this->error('Failed to get {$namePlural}: ' . \$e->getMessage(), 500);
        }
    }

    /**
     * GET /api/{$namePlural}/{id}
     *
     * @param ServerRequestInterface \$request
     * @param array                  \$params  Route parameters (includes 'id')
     * @return ResponseInterface
     */
    public function show(ServerRequestInterface \$request, array \$params = []): ResponseInterface
    {
        try {
            \$id   = (int)\$request->getAttribute('id');

            // TODO: \$item = {$serviceRef}->getById(\$id);
            \$item = null;

            if (!\$item) {
                return \$this->error('{$name} not found', 404);
            }

            return \$this->success(\$item);
        } catch (\Exception \$e) {
            return \$this->error('Failed to get {$nameSnake}: ' . \$e->getMessage(), 500);
        }
    }

    /**
     * POST /api/{$namePlural}
     *
     * @param ServerRequestInterface \$request
     * @param array                  \$params  Route parameters
     * @return ResponseInterface
     */
    public function create(ServerRequestInterface \$request, array \$params = []): ResponseInterface
    {
        try {
            \$data   = \$this->getJsonBody(\$request);
            \$userId = \$this->getUserId(\$request);

            if (!\$userId) {
                return \$this->error('Unauthorized', 401);
            }

            // TODO: validate \$data fields
            // TODO: \$item = {$serviceRef}->create(\$data, \$userId);
            \$item = \$data;

            return \$this->success(\$item, '{$name} created successfully');
        } catch (\App\Exception\ValidationException \$e) {
            return \$this->error(\$e->getMessage(), 422);
        } catch (\App\Exception\UnauthorizedException \$e) {
            return \$this->error(\$e->getMessage(), 401);
        } catch (\App\Exception\ForbiddenException \$e) {
            return \$this->error(\$e->getMessage(), 403);
        } catch (\Exception \$e) {
            return \$this->error('Failed to create {$nameSnake}: ' . \$e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/{$namePlural}/{id}
     *
     * @param ServerRequestInterface \$request
     * @param array                  \$params  Route parameters (includes 'id')
     * @return ResponseInterface
     */
    public function update(ServerRequestInterface \$request, array \$params = []): ResponseInterface
    {
        try {
            \$id   = (int)\$request->getAttribute('id');
            \$data = \$this->getJsonBody(\$request);

            // TODO: \$item = {$serviceRef}->update(\$id, \$data);
            \$item = null;

            if (!\$item) {
                return \$this->error('{$name} not found', 404);
            }

            return \$this->success(\$item, '{$name} updated successfully');
        } catch (\App\Exception\ValidationException \$e) {
            return \$this->error(\$e->getMessage(), 422);
        } catch (\App\Exception\UnauthorizedException \$e) {
            return \$this->error(\$e->getMessage(), 401);
        } catch (\App\Exception\ForbiddenException \$e) {
            return \$this->error(\$e->getMessage(), 403);
        } catch (\App\Exception\NotFoundException \$e) {
            return \$this->error(\$e->getMessage(), 404);
        } catch (\Exception \$e) {
            return \$this->error('Failed to update {$nameSnake}: ' . \$e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/{$namePlural}/{id}
     *
     * @param ServerRequestInterface \$request
     * @param array                  \$params  Route parameters (includes 'id')
     * @return ResponseInterface
     */
    public function delete(ServerRequestInterface \$request, array \$params = []): ResponseInterface
    {
        try {
            \$id = (int)\$request->getAttribute('id');

            // TODO: \$result = {$serviceRef}->delete(\$id);
            \$result = false;

            if (!\$result) {
                return \$this->error('{$name} not found', 404);
            }

            return \$this->success([], '{$name} deleted successfully');
        } catch (\Exception \$e) {
            return \$this->error('Failed to delete {$nameSnake}: ' . \$e->getMessage(), 500);
        }
    }
}
PHP;
}

function serviceTemplate(string $name): string
{
    $nameSnake  = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    $namePlural = $nameSnake . 's';

    return <<<PHP
<?php

declare(strict_types=1);

namespace App\Service;

/**
 * {$name} service
 *
 * Contains business logic for the {$nameSnake} resource.
 * TODO: inject required repositories and helpers via constructor.
 */
class {$name}Service
{
    public function __construct(
        // TODO: inject App\Repository\{$name}Repository and App\Helper\DatabaseHelper
    ) {}

    /**
     * Return a paginated list of {$namePlural}.
     */
    public function getList(int \$page = 1, int \$pageSize = 20): array
    {
        // TODO: implement
        return [
            'items'      => [],
            'pagination' => [
                'total'      => 0,
                'page'       => \$page,
                'pageSize'   => \$pageSize,
                'totalPages' => 0,
            ],
        ];
    }

    /**
     * Find a single {$nameSnake} by ID.
     */
    public function getById(int \$id): ?array
    {
        // TODO: implement
        return null;
    }

    /**
     * Create a new {$nameSnake}.
     */
    public function create(array \$data, int \$userId): array
    {
        // TODO: implement
        return \$data;
    }

    /**
     * Update an existing {$nameSnake}.
     */
    public function update(int \$id, array \$data): ?array
    {
        // TODO: implement
        return null;
    }

    /**
     * Delete a {$nameSnake} by ID.
     */
    public function delete(int \$id): bool
    {
        // TODO: implement
        return false;
    }
}
PHP;
}

// ── Generate Files ────────────────────────────────────────────────────────────

printHeader("PSR-15 Controller Generator");

// Controller
$controllerContent = controllerTemplate($name, $withService);
if (file_put_contents($controllerFile, $controllerContent) === false) {
    printError("Failed to write controller file: {$controllerFile}");
    exit(1);
}
printSuccess("Created src/Controller/{$name}Controller.php");

// Service (optional)
if ($withService) {
    $serviceContent = serviceTemplate($name);
    if (file_put_contents($serviceFile, $serviceContent) === false) {
        printError("Failed to write service file: {$serviceFile}");
        exit(1);
    }
    printSuccess("Created src/Service/{$name}Service.php");
}

// ── Next Steps ────────────────────────────────────────────────────────────────

$nameLower  = lcfirst($name);
$nameSnake  = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
$namePlural = $nameSnake . 's';

printLine();
printLine("\033[1mNext steps:\033[0m");
printLine();

// 1. Routes
printLine("1. Register routes in \033[33mconfig/routes.php\033[0m:");
printLine();
printInfo("[");
printInfo("    ['name' => '{$namePlural}.list',   'pattern' => '/api/{$namePlural}',          'methods' => ['GET'],    'handler' => \\App\\Controller\\{$name}Controller::class . '::index',  'middleware' => [\\App\\Middleware\\AuthMiddleware::class]],");
printInfo("    ['name' => '{$namePlural}.show',   'pattern' => '/api/{$namePlural}/{id:\\\\d+}', 'methods' => ['GET'],    'handler' => \\App\\Controller\\{$name}Controller::class . '::show',   'middleware' => [\\App\\Middleware\\AuthMiddleware::class]],");
printInfo("    ['name' => '{$namePlural}.create', 'pattern' => '/api/{$namePlural}',          'methods' => ['POST'],   'handler' => \\App\\Controller\\{$name}Controller::class . '::create', 'middleware' => [\\App\\Middleware\\AuthMiddleware::class]],");
printInfo("    ['name' => '{$namePlural}.update', 'pattern' => '/api/{$namePlural}/{id:\\\\d+}', 'methods' => ['PUT'],    'handler' => \\App\\Controller\\{$name}Controller::class . '::update', 'middleware' => [\\App\\Middleware\\AuthMiddleware::class]],");
printInfo("    ['name' => '{$namePlural}.delete', 'pattern' => '/api/{$namePlural}/{id:\\\\d+}', 'methods' => ['DELETE'], 'handler' => \\App\\Controller\\{$name}Controller::class . '::delete', 'middleware' => [\\App\\Middleware\\AuthMiddleware::class]],");
printInfo("]");
printLine();

// 2. DI
printLine("2. Register in DI container in \033[33mconfig/di.php\033[0m:");
printLine();
if ($withService) {
    printInfo("\\App\\Service\\{$name}Service::class => static function (ContainerInterface \$c) {");
    printInfo("    return new \\App\\Service\\{$name}Service(");
    printInfo("        // TODO: pass repositories");
    printInfo("    );");
    printInfo("},");
    printLine();
}
printInfo("\\App\\Controller\\{$name}Controller::class => static function (ContainerInterface \$c) {");
printInfo("    return new \\App\\Controller\\{$name}Controller(");
printInfo("        \$c->get(ResponseFactoryInterface::class),");
if ($withService) {
    printInfo("        \$c->get(\\App\\Service\\{$name}Service::class)");
}
printInfo("    );");
printInfo("},");
printLine();

// 3. Restart
printLine("3. Restart the backend container:");
printLine();
printInfo("docker restart xrugc-school-backend");
printLine();

// 4. Tests
printLine("4. Write tests:");
printLine();
printInfo("tests/Unit/Controller/{$name}ControllerTest.php   — unit tests (mock the service)");
if ($withService) {
    printInfo("tests/Unit/Service/{$name}ServiceTest.php         — unit tests for service logic");
}
printInfo("tests/Integration/{$name}IntegrationTest.php      — integration tests through PSR-15 stack");
printLine();

printSuccess("Done! Implement the TODO comments in the generated files.");
printLine();
