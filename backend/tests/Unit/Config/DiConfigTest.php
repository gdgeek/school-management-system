<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\RouterMiddleware;
use App\Middleware\SecurityMiddleware;
use App\Helper\JwtHelper;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Test DI configuration to ensure all services can be resolved
 */
class DiConfigTest extends TestCase
{
    private array $container;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Load DI configuration
        $this->container = require __DIR__ . '/../../../config/di.php';
    }

    public function testPsr7FactoriesAreRegistered(): void
    {
        $this->assertArrayHasKey(ResponseFactoryInterface::class, $this->container);
        $this->assertArrayHasKey(ServerRequestFactoryInterface::class, $this->container);
        $this->assertArrayHasKey(StreamFactoryInterface::class, $this->container);
        $this->assertArrayHasKey(UriFactoryInterface::class, $this->container);
        $this->assertArrayHasKey(UploadedFileFactoryInterface::class, $this->container);
    }

    public function testDatabaseConnectionIsRegistered(): void
    {
        $this->assertArrayHasKey(ConnectionInterface::class, $this->container);
        $this->assertIsCallable($this->container[ConnectionInterface::class]);
    }

    public function testJwtHelperIsRegistered(): void
    {
        $this->assertArrayHasKey(JwtHelper::class, $this->container);
        $this->assertIsCallable($this->container[JwtHelper::class]);
    }

    public function testMiddlewareAreRegistered(): void
    {
        $this->assertArrayHasKey(AuthMiddleware::class, $this->container);
        $this->assertArrayHasKey(CorsMiddleware::class, $this->container);
        $this->assertArrayHasKey(RouterMiddleware::class, $this->container);
        $this->assertArrayHasKey(SecurityMiddleware::class, $this->container);
    }

    public function testAllMiddlewareDefinitionsAreCallable(): void
    {
        $this->assertIsCallable($this->container[AuthMiddleware::class]);
        $this->assertIsCallable($this->container[CorsMiddleware::class]);
        $this->assertIsCallable($this->container[RouterMiddleware::class]);
        $this->assertIsCallable($this->container[SecurityMiddleware::class]);
    }
}
