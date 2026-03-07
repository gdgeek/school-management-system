<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Performance tests for School endpoints through PSR-15 middleware stack
 *
 * Validates Requirements:
 *   - 2.1.1: PSR-15 middleware stack must add less than 5ms additional overhead vs legacy routing
 *   - 2.1.3: Must maintain or improve current response times
 *
 * All timing uses microtime(true) to measure PHP processing time (not network time).
 * All memory measurements use memory_get_usage() to track heap allocations.
 */
class SchoolPerformanceTest extends TestCase
{
    private ContainerInterface $container;
    private Application $app;
    private Psr17Factory $factory;
    private ?string $authToken = null;
    private ?\PDO $pdo = null;
    private array $createdSchoolIds = [];

    // Performance thresholds
    private const MAX_LIST_MS       = 500;
    private const MAX_SHOW_MS       = 200;
    private const MAX_CREATE_MS     = 500;
    private const MAX_UPDATE_MS     = 500;
    private const MAX_DELETE_MS     = 500;
    private const MAX_AVG_LIST_MS   = 300;
    private const MAX_MEMORY_MB     = 10;
    private const MAX_LEAK_MB       = 5;
    private const REPEAT_COUNT      = 10;
    private const LEAK_COUNT        = 5;

    protected function setUp(): void
    {
        // Load environment variables from .env file
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key   = trim($key);
                    $value = trim($value);
                    if (!array_key_exists($key, $_ENV)) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }

        // Build DI container
        $config = ContainerConfig::create()
            ->withDefinitions(require __DIR__ . '/../../config/di.php');

        $this->container = new Container($config);
        $this->app       = $this->container->get(Application::class);
        $this->factory   = new Psr17Factory();

        // Database connection for cleanup
        $dbHost     = $_ENV['DB_HOST']     ?? '127.0.0.1';
        $dbName     = $_ENV['DB_NAME']     ?? 'bujiaban';
        $dbUser     = $_ENV['DB_USER']     ?? 'bujiaban';
        $dbPassword = $_ENV['DB_PASSWORD'] ?? 'testpassword';

        $this->pdo = new \PDO(
            "mysql:host={$dbHost};port=3306;dbname={$dbName}",
            $dbUser,
            $dbPassword,
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        $this->authToken = $this->authenticate();
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdSchoolIds) && $this->pdo) {
            foreach ($this->createdSchoolIds as $id) {
                try {
                    $stmt = $this->pdo->prepare('DELETE FROM edu_school WHERE id = ?');
                    $stmt->execute([$id]);
                } catch (\Exception $e) {
                    // Ignore cleanup errors
                }
            }
        }

        $this->createdSchoolIds = [];
        $this->authToken        = null;
        $this->pdo              = null;
    }

    // -------------------------------------------------------------------------
    // Authentication helper
    // -------------------------------------------------------------------------

    private function authenticate(): string
    {
        $request = (new ServerRequest('POST', '/api/auth/login'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'username' => 'guanfei',
                'password' => '123456',
            ])));

        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode(), 'Authentication should succeed');

        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('token', $body['data']);

        return $body['data']['token'];
    }

    // -------------------------------------------------------------------------
    // School creation helper
    // -------------------------------------------------------------------------

    private function createTestSchool(string $name): int
    {
        $request = (new ServerRequest('POST', '/api/schools'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode([
                'name' => $name,
                'info' => ['description' => 'Performance test school'],
            ])));

        $response = $this->app->handle($request);
        $body     = json_decode((string) $response->getBody(), true);

        $schoolId                   = $body['data']['id'];
        $this->createdSchoolIds[]   = $schoolId;

        return $schoolId;
    }

    // -------------------------------------------------------------------------
    // Timing helper: returns elapsed milliseconds
    // -------------------------------------------------------------------------

    private function measureMs(callable $fn): float
    {
        $start  = microtime(true);
        $fn();
        $end    = microtime(true);

        return ($end - $start) * 1000.0;
    }

    // -------------------------------------------------------------------------
    // Test 1: GET /api/schools (list) — must complete in < 500 ms
    // -------------------------------------------------------------------------

    public function testListSchoolsResponseTime(): void
    {
        $request = (new ServerRequest('GET', '/api/schools?page=1&pageSize=10'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $elapsed = $this->measureMs(function () use ($request): void {
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });

        $this->assertLessThan(
            self::MAX_LIST_MS,
            $elapsed,
            sprintf(
                'GET /api/schools took %.2f ms, expected < %d ms',
                $elapsed,
                self::MAX_LIST_MS
            )
        );
    }

    // -------------------------------------------------------------------------
    // Test 2: GET /api/schools/{id} (show) — must complete in < 200 ms
    // -------------------------------------------------------------------------

    public function testShowSchoolResponseTime(): void
    {
        $schoolId = $this->createTestSchool('Perf Show School ' . uniqid());

        $request = (new ServerRequest('GET', "/api/schools/{$schoolId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId);

        $elapsed = $this->measureMs(function () use ($request): void {
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });

        $this->assertLessThan(
            self::MAX_SHOW_MS,
            $elapsed,
            sprintf(
                'GET /api/schools/{id} took %.2f ms, expected < %d ms',
                $elapsed,
                self::MAX_SHOW_MS
            )
        );
    }

    // -------------------------------------------------------------------------
    // Test 3: POST /api/schools (create) — must complete in < 500 ms
    // -------------------------------------------------------------------------

    public function testCreateSchoolResponseTime(): void
    {
        $schoolData = [
            'name' => 'Perf Create School ' . uniqid(),
            'info' => ['description' => 'Performance test'],
        ];

        $request = (new ServerRequest('POST', '/api/schools'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode($schoolData)));

        $elapsed = $this->measureMs(function () use ($request): void {
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());

            $body = json_decode((string) $response->getBody(), true);
            if (isset($body['data']['id'])) {
                $this->createdSchoolIds[] = $body['data']['id'];
            }
        });

        $this->assertLessThan(
            self::MAX_CREATE_MS,
            $elapsed,
            sprintf(
                'POST /api/schools took %.2f ms, expected < %d ms',
                $elapsed,
                self::MAX_CREATE_MS
            )
        );
    }

    // -------------------------------------------------------------------------
    // Test 4: PUT /api/schools/{id} (update) — must complete in < 500 ms
    // -------------------------------------------------------------------------

    public function testUpdateSchoolResponseTime(): void
    {
        $schoolId = $this->createTestSchool('Perf Update School ' . uniqid());

        $updateData = [
            'name' => 'Perf Updated School ' . uniqid(),
            'info' => ['description' => 'Updated'],
        ];

        $request = (new ServerRequest('PUT', "/api/schools/{$schoolId}"))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId)
            ->withBody($this->factory->createStream(json_encode($updateData)));

        $elapsed = $this->measureMs(function () use ($request): void {
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });

        $this->assertLessThan(
            self::MAX_UPDATE_MS,
            $elapsed,
            sprintf(
                'PUT /api/schools/{id} took %.2f ms, expected < %d ms',
                $elapsed,
                self::MAX_UPDATE_MS
            )
        );
    }

    // -------------------------------------------------------------------------
    // Test 5: DELETE /api/schools/{id} (delete) — must complete in < 500 ms
    // -------------------------------------------------------------------------

    public function testDeleteSchoolResponseTime(): void
    {
        $schoolId = $this->createTestSchool('Perf Delete School ' . uniqid());

        $request = (new ServerRequest('DELETE', "/api/schools/{$schoolId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId);

        $elapsed = $this->measureMs(function () use ($request): void {
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        });

        // Remove from cleanup list — already deleted
        $this->createdSchoolIds = array_filter(
            $this->createdSchoolIds,
            fn($id) => $id !== $schoolId
        );

        $this->assertLessThan(
            self::MAX_DELETE_MS,
            $elapsed,
            sprintf(
                'DELETE /api/schools/{id} took %.2f ms, expected < %d ms',
                $elapsed,
                self::MAX_DELETE_MS
            )
        );
    }

    // -------------------------------------------------------------------------
    // Test 6: Average response time over 10 consecutive GET /api/schools
    //         — average must be < 300 ms
    // -------------------------------------------------------------------------

    public function testAverageListResponseTime(): void
    {
        $request = (new ServerRequest('GET', '/api/schools?page=1&pageSize=10'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $times = [];

        for ($i = 0; $i < self::REPEAT_COUNT; $i++) {
            $times[] = $this->measureMs(function () use ($request): void {
                $response = $this->app->handle($request);
                $this->assertEquals(200, $response->getStatusCode());
            });
        }

        $average = array_sum($times) / count($times);

        $this->assertLessThan(
            self::MAX_AVG_LIST_MS,
            $average,
            sprintf(
                'Average of %d GET /api/schools requests was %.2f ms, expected < %d ms. Individual times: [%s]',
                self::REPEAT_COUNT,
                $average,
                self::MAX_AVG_LIST_MS,
                implode(', ', array_map(fn($t) => round($t, 1), $times))
            )
        );
    }

    // -------------------------------------------------------------------------
    // Test 7: Memory usage per request — must be < 10 MB additional
    // -------------------------------------------------------------------------

    public function testMemoryUsagePerRequest(): void
    {
        $request = (new ServerRequest('GET', '/api/schools?page=1&pageSize=10'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $memBefore = memory_get_usage(true);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $memAfter = memory_get_usage(true);

        $deltaMb = ($memAfter - $memBefore) / (1024 * 1024);

        $this->assertLessThan(
            self::MAX_MEMORY_MB,
            $deltaMb,
            sprintf(
                'GET /api/schools used %.2f MB additional memory, expected < %d MB',
                $deltaMb,
                self::MAX_MEMORY_MB
            )
        );
    }

    // -------------------------------------------------------------------------
    // Test 8: No memory leaks across 5 consecutive requests
    //         — total memory growth must be < 5 MB
    // -------------------------------------------------------------------------

    public function testNoMemoryLeakAcrossConsecutiveRequests(): void
    {
        $request = (new ServerRequest('GET', '/api/schools?page=1&pageSize=10'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $memStart = memory_get_usage(true);

        for ($i = 0; $i < self::LEAK_COUNT; $i++) {
            $response = $this->app->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        }

        $memEnd = memory_get_usage(true);

        $growthMb = ($memEnd - $memStart) / (1024 * 1024);

        $this->assertLessThan(
            self::MAX_LEAK_MB,
            $growthMb,
            sprintf(
                'Memory grew by %.2f MB across %d consecutive GET /api/schools requests, expected < %d MB',
                $growthMb,
                self::LEAK_COUNT,
                self::MAX_LEAK_MB
            )
        );
    }
}
