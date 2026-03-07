#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PSR-15 Route Code Generator
 *
 * Generates and registers CRUD route definitions for a resource in config/routes.php.
 *
 * Usage:
 *   php bin/generate-routes.php <resource>
 *   php bin/generate-routes.php <resource> --controller=ProductController
 *   php bin/generate-routes.php <resource> --prefix=/api/v2/products
 *   php bin/generate-routes.php <resource> --no-auth
 *   php bin/generate-routes.php <resource> --dry-run
 *
 * Examples:
 *   php bin/generate-routes.php product
 *   php bin/generate-routes.php product --no-auth --dry-run
 *   php bin/generate-routes.php product --prefix=/api/v2/products --controller=ProductV2Controller
 */

// ── Helpers ───────────────────────────────────────────────────────────────────

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

function toPascalCase(string $input): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input)));
}

function toSnakeCase(string $input): string
{
    return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
}

function simplePlural(string $word): string
{
    // Basic English pluralisation rules
    if (str_ends_with($word, 'y') && !in_array(substr($word, -2, 1), ['a', 'e', 'i', 'o', 'u'])) {
        return substr($word, 0, -1) . 'ies';
    }
    if (str_ends_with($word, 's') || str_ends_with($word, 'x') ||
        str_ends_with($word, 'z') || str_ends_with($word, 'ch') || str_ends_with($word, 'sh')) {
        return $word . 'es';
    }
    return $word . 's';
}

// ── Argument Parsing ──────────────────────────────────────────────────────────

$args = array_slice($argv, 1);

$helpRequested = in_array('--help', $args, true) || in_array('-h', $args, true);

if ($helpRequested || empty($args)) {
    printLine("PSR-15 Route Generator");
    printLine();
    printLine("Usage:");
    printLine("  php bin/generate-routes.php <resource> [options]");
    printLine();
    printLine("Arguments:");
    printLine("  <resource>                  Resource name in lowercase (e.g. product, blog_post)");
    printLine();
    printLine("Options:");
    printLine("  --controller=<ClassName>    Controller class name (default: {Name}Controller)");
    printLine("  --prefix=<path>             URL prefix (default: /api/{name_plural})");
    printLine("  --no-auth                   Skip AuthMiddleware on generated routes");
    printLine("  --dry-run                   Print what would be added without modifying files");
    printLine("  --help, -h                  Show this help message");
    printLine();
    printLine("Examples:");
    printLine("  php bin/generate-routes.php product");
    printLine("  php bin/generate-routes.php product --no-auth --dry-run");
    printLine("  php bin/generate-routes.php product --prefix=/api/v2/products --controller=ProductV2Controller");
    exit($helpRequested ? 0 : 1);
}

$resource       = null;
$controllerOpt  = null;
$prefixOpt      = null;
$noAuth         = false;
$dryRun         = false;

foreach ($args as $arg) {
    if ($arg === '--no-auth') {
        $noAuth = true;
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--controller=')) {
        $controllerOpt = substr($arg, strlen('--controller='));
    } elseif (str_starts_with($arg, '--prefix=')) {
        $prefixOpt = substr($arg, strlen('--prefix='));
    } elseif (str_starts_with($arg, '--')) {
        printError("Unknown option: {$arg}");
        exit(1);
    } else {
        if ($resource !== null) {
            printError("Unexpected argument: {$arg}");
            exit(1);
        }
        $resource = $arg;
    }
}

if ($resource === null) {
    printError("Resource name is required.");
    printLine("  Usage: php bin/generate-routes.php <resource>");
    exit(1);
}

// Validate resource name: lowercase letters, digits, underscores, hyphens
if (!preg_match('/^[a-z][a-z0-9_-]*$/', $resource)) {
    printError("Resource name must start with a lowercase letter and contain only lowercase alphanumeric characters, underscores, or hyphens.");
    printLine("  Examples: product, blog_post, user-profile");
    exit(1);
}

// ── Derive Names ──────────────────────────────────────────────────────────────

$pascalName     = toPascalCase($resource);
$snakeName      = toSnakeCase($pascalName);
$pluralSnake    = simplePlural($snakeName);

// Controller class name
if ($controllerOpt !== null) {
    // Strip trailing "Controller" if user accidentally included it, then re-add
    $controllerClass = preg_match('/Controller$/', $controllerOpt)
        ? $controllerOpt
        : $controllerOpt . 'Controller';
} else {
    $controllerClass = $pascalName . 'Controller';
}

// Validate controller class name
if (!preg_match('/^[A-Z][A-Za-z0-9]*Controller$/', $controllerClass)) {
    printError("Controller class name must be PascalCase and end with 'Controller': {$controllerClass}");
    exit(1);
}

// URL prefix
if ($prefixOpt !== null) {
    $prefix = rtrim($prefixOpt, '/');
    if (!str_starts_with($prefix, '/')) {
        printError("Prefix must start with '/': {$prefixOpt}");
        exit(1);
    }
} else {
    $prefix = '/api/' . str_replace('_', '-', $pluralSnake);
}

// Route name prefix (e.g. "products")
$routePrefix = str_replace('-', '_', $pluralSnake);

// Middleware
$middlewareLine = $noAuth
    ? "'middleware' => [],"
    : "'middleware' => [\\App\\Middleware\\AuthMiddleware::class],";

// ── Path Resolution ───────────────────────────────────────────────────────────

$backendRoot = dirname(__DIR__);
$routesFile  = $backendRoot . '/config/routes.php';

// ── Pre-flight Checks ─────────────────────────────────────────────────────────

if (!file_exists($routesFile)) {
    printError("Routes file not found: {$routesFile}");
    exit(1);
}

$routesContent = file_get_contents($routesFile);
if ($routesContent === false) {
    printError("Failed to read routes file: {$routesFile}");
    exit(1);
}

// Check if routes for this resource already exist
$existingPatterns = [
    "'{$routePrefix}.list'",
    "'{$routePrefix}.show'",
    "'{$routePrefix}.create'",
    "'{$routePrefix}.update'",
    "'{$routePrefix}.delete'",
];

$alreadyExists = false;
foreach ($existingPatterns as $pattern) {
    if (str_contains($routesContent, $pattern)) {
        $alreadyExists = true;
        break;
    }
}

if ($alreadyExists) {
    printError("Routes for '{$routePrefix}' already exist in config/routes.php.");
    printInfo("Existing route names found: {$routePrefix}.list / .show / .create / .update / .delete");
    printInfo("Use a different resource name or --prefix/--controller to create variant routes.");
    exit(1);
}

// ── Build Route Block ─────────────────────────────────────────────────────────

$controllerFQN = "\\App\\Controller\\{$controllerClass}";
$sectionTitle  = ucfirst(str_replace('_', ' ', $pluralSnake)) . ' Routes';
$authNote      = $noAuth ? ' (Public)' : ' (Protected)';

$routeBlock = <<<PHP

    // ========================================
    // {$sectionTitle}{$authNote}
    // ========================================

    [
        'name' => '{$routePrefix}.list',
        'pattern' => '{$prefix}',
        'methods' => ['GET'],
        'handler' => {$controllerFQN}::class . '::index',
        {$middlewareLine}
    ],

    [
        'name' => '{$routePrefix}.show',
        'pattern' => '{$prefix}/{id:\d+}',
        'methods' => ['GET'],
        'handler' => {$controllerFQN}::class . '::show',
        {$middlewareLine}
    ],

    [
        'name' => '{$routePrefix}.create',
        'pattern' => '{$prefix}',
        'methods' => ['POST'],
        'handler' => {$controllerFQN}::class . '::create',
        {$middlewareLine}
    ],

    [
        'name' => '{$routePrefix}.update',
        'pattern' => '{$prefix}/{id:\d+}',
        'methods' => ['PUT'],
        'handler' => {$controllerFQN}::class . '::update',
        {$middlewareLine}
    ],

    [
        'name' => '{$routePrefix}.delete',
        'pattern' => '{$prefix}/{id:\d+}',
        'methods' => ['DELETE'],
        'handler' => {$controllerFQN}::class . '::delete',
        {$middlewareLine}
    ],
PHP;

// ── Summary ───────────────────────────────────────────────────────────────────

printHeader("PSR-15 Route Generator");

printLine("\033[1mResource:\033[0m  {$resource}");
printLine("\033[1mController:\033[0m {$controllerFQN}");
printLine("\033[1mPrefix:\033[0m    {$prefix}");
printLine("\033[1mAuth:\033[0m      " . ($noAuth ? 'disabled (--no-auth)' : 'enabled (AuthMiddleware)'));
printLine("\033[1mDry run:\033[0m   " . ($dryRun ? 'yes — no files will be modified' : 'no'));
printLine();

printLine("\033[1mRoutes to be added:\033[0m");
printLine();
printInfo("GET    {$prefix}           → {$controllerClass}::index   ({$routePrefix}.list)");
printInfo("GET    {$prefix}/{id}      → {$controllerClass}::show    ({$routePrefix}.show)");
printInfo("POST   {$prefix}           → {$controllerClass}::create  ({$routePrefix}.create)");
printInfo("PUT    {$prefix}/{id}      → {$controllerClass}::update  ({$routePrefix}.update)");
printInfo("DELETE {$prefix}/{id}      → {$controllerClass}::delete  ({$routePrefix}.delete)");
printLine();

if ($dryRun) {
    printWarning("DRY RUN — the following block would be appended to config/routes.php:");
    printLine();
    echo $routeBlock . PHP_EOL;
    printLine();
    printWarning("No files were modified (--dry-run).");
    printLine();
    exit(0);
}

// ── Write to routes.php ───────────────────────────────────────────────────────

// Insert the route block just before the closing "];", preserving the file structure
$closingBracket = '];';
$insertPos = strrpos($routesContent, $closingBracket);

if ($insertPos === false) {
    printError("Could not find closing '};' in config/routes.php. The file may be malformed.");
    exit(1);
}

$newContent = substr($routesContent, 0, $insertPos)
    . $routeBlock . PHP_EOL
    . substr($routesContent, $insertPos);

if (file_put_contents($routesFile, $newContent) === false) {
    printError("Failed to write to config/routes.php");
    exit(1);
}

printSuccess("Appended 5 routes to config/routes.php");
printLine();

// ── Next Steps ────────────────────────────────────────────────────────────────

printLine("\033[1mNext steps:\033[0m");
printLine();

printLine("1. Generate the controller (if it doesn't exist yet):");
printLine();
printInfo("php bin/generate-controller.php {$pascalName} --with-service");
printLine();

printLine("2. Register the controller in \033[33mconfig/di.php\033[0m:");
printLine();
printInfo("\\App\\Controller\\{$controllerClass}::class => static function (ContainerInterface \$c) {");
printInfo("    return new \\App\\Controller\\{$controllerClass}(");
printInfo("        \$c->get(\\Psr\\Http\\Message\\ResponseFactoryInterface::class),");
printInfo("        // \$c->get(\\App\\Service\\{$pascalName}Service::class)");
printInfo("    );");
printInfo("},");
printLine();

printLine("3. Restart the backend container:");
printLine();
printInfo("docker restart xrugc-school-backend");
printLine();

printLine("4. Test the new routes:");
printLine();
printInfo("curl -H 'Authorization: Bearer <token>' http://localhost:8084{$prefix}");
printLine();

printSuccess("Done!");
printLine();
