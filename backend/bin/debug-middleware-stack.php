#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PSR-15 Middleware Stack Inspector
 *
 * Prints the configured global middleware stack and the per-route middleware
 * for every registered route.  Useful for verifying middleware order without
 * making HTTP requests.
 *
 * Usage:
 *   php bin/debug-middleware-stack.php
 *   php bin/debug-middleware-stack.php --route=schools.list
 *   php bin/debug-middleware-stack.php --json
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

// Load .env if present (needed so middleware.php can read env vars).
// Dotenv is only available when Composer dependencies are installed (e.g. inside Docker).
// Fall back to a simple key=value parser when the class is not loaded.
if (file_exists($root . '/.env')) {
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable($root)->safeLoad();
    } else {
        // Minimal .env parser — handles KEY=value and KEY="value" lines
        foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $val = trim($val, " \t\"'");
            if ($key !== '' && !isset($_ENV[$key])) {
                $_ENV[$key] = $val;
                putenv("{$key}={$val}");
            }
        }
    }
}

// ── CLI argument parsing ───────────────────────────────────────────────────────

$args        = array_slice($argv, 1);
$filterRoute = null;
$jsonOutput  = false;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--route=')) {
        $filterRoute = substr($arg, strlen('--route='));
    } elseif ($arg === '--json') {
        $jsonOutput = true;
    } elseif (in_array($arg, ['-h', '--help'], true)) {
        echo <<<HELP
Usage:
  php bin/debug-middleware-stack.php [options]

Options:
  --route=<name>   Show stack only for the named route (e.g. schools.list)
  --json           Output as JSON instead of human-readable text
  -h, --help       Show this help message

HELP;
        exit(0);
    }
}

// ── Load configuration ────────────────────────────────────────────────────────

$middlewareConfig = require $root . '/config/middleware.php';
$routes           = require $root . '/config/routes.php';

$globalMiddleware = $middlewareConfig['global']  ?? [];
$groups           = $middlewareConfig['groups']  ?? [];
$routeMiddleware  = $middlewareConfig['routes']  ?? [];

// ── Helpers ───────────────────────────────────────────────────────────────────

function shortName(string $fqcn): string
{
    // Handle string aliases (e.g. 'LoginRateLimitMiddleware')
    if (!str_contains($fqcn, '\\')) {
        return $fqcn;
    }
    return basename(str_replace('\\', '/', $fqcn));
}

/**
 * Resolve a middleware entry to a list of FQCN / alias strings.
 * An entry can be:
 *   - a FQCN string
 *   - a string alias (e.g. 'LoginRateLimitMiddleware')
 *   - a group name key from $groups
 */
function resolveMiddleware(string|array $entry, array $groups): array
{
    if (is_array($entry)) {
        $resolved = [];
        foreach ($entry as $item) {
            $resolved = array_merge($resolved, resolveMiddleware($item, $groups));
        }
        return $resolved;
    }

    // Named group reference?
    if (isset($groups[$entry])) {
        return $groups[$entry];
    }

    return [$entry];
}

/**
 * Find route-config middleware groups that apply to a given route name.
 * Supports exact match and wildcard patterns like 'schools.*'.
 */
function routeConfigMiddleware(string $routeName, array $routeMiddleware, array $groups): array
{
    $resolved = [];
    foreach ($routeMiddleware as $pattern => $groupRefs) {
        $regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], $pattern) . '$/';
        if (preg_match($regex, $routeName)) {
            foreach ((array)$groupRefs as $ref) {
                $resolved = array_merge($resolved, resolveMiddleware($ref, $groups));
            }
        }
    }
    return $resolved;
}

/**
 * Build the full ordered middleware stack for a route.
 *
 * Order mirrors Application::buildMiddlewareStack():
 *   1. Global middleware
 *   2. Route-inline middleware (defined in routes.php per route)
 *   3. Route-config middleware (from middleware.php 'routes' mapping)
 */
function buildFullStack(array $route, array $globalMiddleware, array $routeMiddleware, array $groups): array
{
    $inline = $route['middleware'] ?? [];
    $config = routeConfigMiddleware($route['name'], $routeMiddleware, $groups);

    // Deduplicate while preserving order
    $seen  = [];
    $stack = [];
    foreach (array_merge($globalMiddleware, $inline, $config) as $mw) {
        if (!in_array($mw, $seen, true)) {
            $seen[]  = $mw;
            $stack[] = $mw;
        }
    }
    return $stack;
}

// ── Collect data ──────────────────────────────────────────────────────────────

$data = [
    'global_middleware' => array_values(array_filter($globalMiddleware)),
    'routes'            => [],
];

foreach ($routes as $route) {
    $name = $route['name'] ?? '(unnamed)';

    if ($filterRoute !== null && $name !== $filterRoute) {
        continue;
    }

    $fullStack = buildFullStack($route, array_values(array_filter($globalMiddleware)), $routeMiddleware, $groups);

    $data['routes'][] = [
        'name'       => $name,
        'pattern'    => $route['pattern']  ?? '',
        'methods'    => $route['methods']  ?? [],
        'handler'    => $route['handler']  ?? '',
        'middleware' => $fullStack,
    ];
}

// ── Output ────────────────────────────────────────────────────────────────────

if ($jsonOutput) {
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

// ── Human-readable output ─────────────────────────────────────────────────────

$bold  = "\033[1m";
$cyan  = "\033[36m";
$green = "\033[32m";
$gray  = "\033[90m";
$reset = "\033[0m";
$dim   = "\033[2m";

echo PHP_EOL;
echo "{$bold}PSR-15 Middleware Stack Inspector{$reset}" . PHP_EOL;
echo str_repeat('─', 60) . PHP_EOL;

// ── Global middleware ─────────────────────────────────────────────────────────

echo PHP_EOL;
echo "{$bold}Global Middleware{$reset} {$dim}(applied to every request){$reset}" . PHP_EOL;
echo str_repeat('─', 40) . PHP_EOL;

$globalFiltered = array_values(array_filter($globalMiddleware));
if (empty($globalFiltered)) {
    echo "  {$gray}(none){$reset}" . PHP_EOL;
} else {
    foreach ($globalFiltered as $i => $mw) {
        $idx   = str_pad((string)($i + 1), 2, ' ', STR_PAD_LEFT);
        $short = shortName($mw);
        $fqcn  = ($short !== $mw) ? " {$gray}({$mw}){$reset}" : '';
        echo "  {$gray}{$idx}.{$reset} {$cyan}{$short}{$reset}{$fqcn}" . PHP_EOL;
    }
}

// ── Per-route stacks ──────────────────────────────────────────────────────────

echo PHP_EOL;
$label = $filterRoute !== null ? "Route: {$filterRoute}" : 'Route Middleware Stacks';
echo "{$bold}{$label}{$reset}" . PHP_EOL;
echo str_repeat('─', 60) . PHP_EOL;

if (empty($data['routes'])) {
    echo "  {$gray}No routes found" . ($filterRoute !== null ? " matching '{$filterRoute}'" : '') . ".{$reset}" . PHP_EOL;
} else {
    foreach ($data['routes'] as $route) {
        $methods = implode('|', $route['methods']);
        $handler = shortName(explode('::', $route['handler'])[0] ?? '') . '::' . (explode('::', $route['handler'])[1] ?? '');

        echo PHP_EOL;
        echo "  {$bold}{$green}{$route['name']}{$reset}" . PHP_EOL;
        echo "  {$dim}{$methods} {$route['pattern']}{$reset}  →  {$cyan}{$handler}{$reset}" . PHP_EOL;

        if (empty($route['middleware'])) {
            echo "    {$gray}(no middleware){$reset}" . PHP_EOL;
        } else {
            foreach ($route['middleware'] as $i => $mw) {
                $idx   = str_pad((string)($i + 1), 2, ' ', STR_PAD_LEFT);
                $short = shortName($mw);
                $fqcn  = ($short !== $mw) ? " {$gray}({$mw}){$reset}" : '';
                echo "    {$gray}{$idx}.{$reset} {$cyan}{$short}{$reset}{$fqcn}" . PHP_EOL;
            }
        }
    }
}

echo PHP_EOL;
echo str_repeat('─', 60) . PHP_EOL;
echo "{$dim}Tip: use --route=<name> to filter, --json for machine-readable output{$reset}" . PHP_EOL;
echo PHP_EOL;
