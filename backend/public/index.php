<?php

declare(strict_types=1);

// Remove PHP version disclosure header emitted by the SAPI before any
// application code runs. SecurityMiddleware removes it from PSR-7 responses,
// but PHP itself queues this header at the SAPI level independently.
header_remove('X-Powered-By');

// Define application root directory
define('ROOT_DIR', dirname(__DIR__));

// Load Composer autoloader
require_once ROOT_DIR . '/vendor/autoload.php';

// Load environment variables
if (file_exists(ROOT_DIR . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(ROOT_DIR);
    $dotenv->load();
}

// Build PSR-7 request from globals
$psr7Request = createServerRequestFromGlobals();

// Route all requests through PSR-15 middleware stack
try {
    $container = require ROOT_DIR . '/config/container.php';
    $app = $container->get(\App\Application::class);
    $response = $app->handle($psr7Request);
    emitResponse($response);
} catch (\Throwable $e) {
    $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    $body = [
        'code'      => 500,
        'message'   => $debug ? $e->getMessage() : 'Internal server error',
        'data'      => null,
        'timestamp' => time(),
    ];
    if ($debug) {
        $body['debug'] = [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
}

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Create a PSR-7 ServerRequest from PHP superglobals.
 */
function createServerRequestFromGlobals(): \Psr\Http\Message\ServerRequestInterface
{
    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    $creator = new \Nyholm\Psr7Server\ServerRequestCreator(
        $factory, $factory, $factory, $factory
    );
    return $creator->fromGlobals();
}

/**
 * Emit a PSR-7 Response to the client.
 */
function emitResponse(\Psr\Http\Message\ResponseInterface $response): void
{
    $statusCode   = $response->getStatusCode();
    $reasonPhrase = $response->getReasonPhrase();
    $protocol     = $response->getProtocolVersion();

    header(sprintf('HTTP/%s %d %s', $protocol, $statusCode, $reasonPhrase), true, $statusCode);

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }

    echo $response->getBody();
}
