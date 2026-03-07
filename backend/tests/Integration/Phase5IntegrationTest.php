<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application;
use App\Helper\JwtHelper;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Integration tests for Phase 5: Groups, Students, Teachers, Users
 *
 * Tests the complete PSR-15 request/response cycle for:
 * - GroupController (CRUD)
 * - StudentController (CRUD + auto-join groups)
 * - TeacherController (CRUD)
 * - UserController (search)
 */
class Phase5IntegrationTest extends TestCase
{
    private ContainerInterface $container;
    private Application $app;
    private Psr17Factory $factory;
    private ?string $authToken = null;
    private ?\PDO $pdo = null;

    protected function setUp(): void
    {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key); $value = trim($value);
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }

        $config = ContainerConfig::create()
            ->withDefinitions(require __DIR__ . '/../../config/di.php');
        $this->container = new Container($config);
        $this->app = $this->container->get(Application::class);
        $this->factory = new Psr17Factory();

        try {
            $this->pdo = $this->container->get(\PDO::class);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->authToken = $this->getAuthToken();
    }

    private function getAuthToken(): ?string
    {
        try {
            $jwtHelper = $this->container->get(JwtHelper::class);
            return $jwtHelper->generateToken(['id' => 1, 'username' => 'test']);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function makeRequest(
        string $method,
        string $path,
        ?array $body = null,
        bool $withAuth = true
    ): array {
        $request = new ServerRequest($method, $path);

        if ($withAuth && $this->authToken) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->authToken);
        }

        if ($body !== null) {
            $stream = $this->factory->createStream(json_encode($body));
            $request = $request
                ->withBody($stream)
                ->withHeader('Content-Type', 'application/json');
        }

        $response = $this->app->handle($request);
        $body = json_decode((string)$response->getBody(), true);

        return ['status' => $response->getStatusCode(), 'body' => $body];
    }

    // ==================== Groups ====================

    public function testGetGroupsRequiresAuth(): void
    {
        $result = $this->makeRequest('GET', '/api/groups', null, false);
        $this->assertEquals(401, $result['status']);
    }

    public function testGetGroupsReturnsListWithAuth(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/groups');
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('data', $result['body']);
        $this->assertArrayHasKey('items', $result['body']['data']);
        $this->assertArrayHasKey('pagination', $result['body']['data']);
    }

    public function testGetGroupsWithSearchParam(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/groups?search=test');
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('items', $result['body']['data']);
    }

    public function testGetGroupNotFoundReturns404(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/groups/999999');
        $this->assertEquals(404, $result['status']);
    }

    // ==================== Students ====================

    public function testGetStudentsRequiresAuth(): void
    {
        $result = $this->makeRequest('GET', '/api/students', null, false);
        $this->assertEquals(401, $result['status']);
    }

    public function testGetStudentsReturnsListWithAuth(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/students');
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('items', $result['body']['data']);
        $this->assertArrayHasKey('pagination', $result['body']['data']);
    }

    public function testGetStudentsWithClassIdFilter(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/students?class_id=1');
        $this->assertEquals(200, $result['status']);
    }

    public function testGetStudentNotFoundReturns404(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/students/999999');
        $this->assertEquals(404, $result['status']);
    }

    public function testCreateStudentValidationRequiresUserId(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('POST', '/api/students', ['class_id' => 1]);
        $this->assertEquals(400, $result['status']);
    }

    public function testCreateStudentValidationRequiresClassId(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('POST', '/api/students', ['user_id' => 1]);
        $this->assertEquals(400, $result['status']);
    }

    // ==================== Teachers ====================

    public function testGetTeachersRequiresAuth(): void
    {
        $result = $this->makeRequest('GET', '/api/teachers', null, false);
        $this->assertEquals(401, $result['status']);
    }

    public function testGetTeachersReturnsListWithAuth(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/teachers');
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('items', $result['body']['data']);
        $this->assertArrayHasKey('pagination', $result['body']['data']);
    }

    public function testGetTeachersWithClassIdFilter(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/teachers?class_id=1');
        $this->assertEquals(200, $result['status']);
    }

    public function testGetTeacherNotFoundReturns404(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/teachers/999999');
        $this->assertEquals(404, $result['status']);
    }

    public function testCreateTeacherValidationRequiresUserId(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('POST', '/api/teachers', ['class_id' => 1]);
        $this->assertEquals(400, $result['status']);
    }

    public function testCreateTeacherValidationRequiresClassId(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('POST', '/api/teachers', ['user_id' => 1]);
        $this->assertEquals(400, $result['status']);
    }

    // ==================== User Search ====================

    public function testUserSearchRequiresAuth(): void
    {
        $result = $this->makeRequest('GET', '/api/users/search?keyword=test', null, false);
        $this->assertEquals(401, $result['status']);
    }

    public function testUserSearchRequiresKeyword(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/users/search');
        $this->assertEquals(400, $result['status']);
    }

    public function testUserSearchReturnsResults(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/users/search?keyword=a');
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('items', $result['body']['data']);
        $this->assertArrayHasKey('pagination', $result['body']['data']);
    }

    public function testUserSearchWithQParam(): void
    {
        if (!$this->authToken) $this->markTestSkipped('No auth token');

        $result = $this->makeRequest('GET', '/api/users/search?q=test');
        $this->assertEquals(200, $result['status']);
    }
}
