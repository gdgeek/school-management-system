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
 * Compatibility tests for Class endpoints via PSR-15 middleware stack.
 *
 * Validates that the PSR-15 Application produces responses conforming to the
 * documented API contract: {code, message, data, timestamp}.
 *
 * Endpoints covered:
 *   GET    /api/classes
 *   GET    /api/classes/{id}
 *   POST   /api/classes
 *   PUT    /api/classes/{id}
 *   DELETE /api/classes/{id}
 *
 * Requirements validated:
 *   1.7.1  Identical request/response formats for migrated endpoints
 *   1.7.2  Same HTTP status codes as legacy implementation
 *   1.7.3  Same error message formats and structure
 *   1.7.4  JSON response structure: {code, message, data, timestamp}
 *   1.7.5  All existing query parameters, request body fields, and headers supported
 *   4.4.6  All class endpoints return identical responses through PSR-15 as legacy
 */
class ClassCompatibilityTest extends TestCase
{
    private ContainerInterface $container;
    private Application $app;
    private Psr17Factory $factory;
    private ?string $authToken = null;
    private ?\PDO $pdo = null;
    private array $createdClassIds  = [];
    private array $createdSchoolIds = [];

    protected function setUp(): void
    {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key); $value = trim($value);
                    if (!array_key_exists($key, $_ENV)) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }

        $config = ContainerConfig::create()
            ->withDefinitions(require __DIR__ . '/../../config/di.php');
        $this->container = new Container($config);
        $this->app       = $this->container->get(Application::class);
        $this->factory   = new Psr17Factory();

        $this->pdo = new \PDO(
            sprintf('mysql:host=%s;port=3306;dbname=%s',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_NAME'] ?? 'bujiaban'),
            $_ENV['DB_USER'] ?? 'bujiaban',
            $_ENV['DB_PASSWORD'] ?? 'testpassword',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
             \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
        );

        $this->authToken = $this->authenticate();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdClassIds as $id) {
            try {
                $this->pdo->prepare('DELETE FROM edu_class_group WHERE class_id = ?')->execute([$id]);
                $this->pdo->prepare('DELETE FROM edu_class WHERE id = ?')->execute([$id]);
            } catch (\Exception) {}
        }
        foreach ($this->createdSchoolIds as $id) {
            try {
                $this->pdo->prepare('DELETE FROM edu_school WHERE id = ?')->execute([$id]);
            } catch (\Exception) {}
        }
        $this->createdClassIds  = [];
        $this->createdSchoolIds = [];
        $this->authToken        = null;
        $this->pdo              = null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function authenticate(): string
    {
        $request = (new ServerRequest('POST', '/api/auth/login'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['username' => 'guanfei', 'password' => '123456'])));
        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), 'Authentication must succeed');
        $body = json_decode((string) $response->getBody(), true);
        return $body['data']['token'];
    }

    private function createTestSchool(string $name): int
    {
        $request = (new ServerRequest('POST', '/api/schools'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $name, 'info' => ['description' => 'compat test']])));
        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), "createTestSchool($name) must succeed");
        $body = json_decode((string) $response->getBody(), true);
        $id = $body['data']['id'];
        $this->createdSchoolIds[] = $id;
        return $id;
    }

    private function createTestClass(string $name, int $schoolId): int
    {
        $request = (new ServerRequest('POST', '/api/classes'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $name, 'school_id' => $schoolId])));
        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), "createTestClass($name) must succeed");
        $body = json_decode((string) $response->getBody(), true);
        $id = $body['data']['id'];
        $this->createdClassIds[] = $id;
        return $id;
    }

    /** Assert top-level API contract: {code, message, data, timestamp} — Req 1.7.4 */
    private function assertApiContract(array $body): void
    {
        $this->assertArrayHasKey('code',      $body, 'Response must contain "code"');
        $this->assertArrayHasKey('message',   $body, 'Response must contain "message"');
        $this->assertArrayHasKey('data',      $body, 'Response must contain "data"');
        $this->assertArrayHasKey('timestamp', $body, 'Response must contain "timestamp"');
        $this->assertIsInt($body['code'],       '"code" must be integer');
        $this->assertIsString($body['message'], '"message" must be string');
        $this->assertIsInt($body['timestamp'],  '"timestamp" must be integer');
    }

    /** Assert HTTP status matches body code field — Req 1.7.2 */
    private function assertStatusCodeConsistency(int $httpStatus, array $body): void
    {
        $this->assertSame($httpStatus, $body['code'],
            "HTTP status $httpStatus must match body code {$body['code']}");
    }

    // =========================================================================
    // GET /api/classes
    // =========================================================================

    /** PSR-15 GET /api/classes returns documented response format. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testListClassesResponseFormat(): void
    {
        $request = (new ServerRequest('GET', '/api/classes'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotNull($body, 'Response body must be valid JSON');
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('items',      $body['data']);
        $this->assertArrayHasKey('pagination', $body['data']);
        $pagination = $body['data']['pagination'];
        $this->assertArrayHasKey('total',      $pagination);
        $this->assertArrayHasKey('page',       $pagination);
        $this->assertArrayHasKey('pageSize',   $pagination);
        $this->assertArrayHasKey('totalPages', $pagination);
    }

    /** PSR-15 GET /api/classes supports pagination params. Req 1.7.5 */
    public function testListClassesPaginationParams(): void
    {
        $request = (new ServerRequest('GET', '/api/classes?page=1&pageSize=5'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertApiContract($body);
        $this->assertSame(1, $body['data']['pagination']['page']);
        $this->assertSame(5, $body['data']['pagination']['pageSize']);
    }

    /** PSR-15 GET /api/classes supports school_id filter param. Req 1.7.5, 4.4.5 */
    public function testListClassesSchoolIdParam(): void
    {
        $schoolId = $this->createTestSchool('CompatFilter_' . time());
        $classId  = $this->createTestClass('CompatFilterClass_' . time(), $schoolId);

        $request = (new ServerRequest('GET', "/api/classes?school_id={$schoolId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertApiContract($body);

        // All returned items must belong to the filtered school
        foreach ($body['data']['items'] as $item) {
            $this->assertSame($schoolId, $item['school_id'],
                'All items must belong to the filtered school');
        }
    }

    /** PSR-15 GET /api/classes returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testListClassesRequiresAuth(): void
    {
        $response = $this->app->handle(new ServerRequest('GET', '/api/classes'));
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
        $this->assertNotEmpty($body['message']);
    }

    // =========================================================================
    // GET /api/classes/{id}
    // =========================================================================

    /** PSR-15 GET /api/classes/{id} returns documented response format. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testShowClassResponseFormat(): void
    {
        $schoolId = $this->createTestSchool('CompatShow_' . time());
        $classId  = $this->createTestClass('CompatShowClass_' . time(), $schoolId);

        $request = (new ServerRequest('GET', "/api/classes/{$classId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('id',        $body['data']);
        $this->assertArrayHasKey('name',      $body['data']);
        $this->assertArrayHasKey('school_id', $body['data']);
        $this->assertSame($classId,  $body['data']['id']);
        $this->assertSame($schoolId, $body['data']['school_id']);
    }

    /** PSR-15 GET /api/classes/{id} returns 404 for non-existent class. Req 1.7.2, 1.7.3 */
    public function testShowClassNotFound(): void
    {
        $request = (new ServerRequest('GET', '/api/classes/999999'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(404, $body);
        $this->assertNull($body['data']);
        $this->assertStringContainsString('not found', strtolower($body['message']));
    }

    /** PSR-15 GET /api/classes/{id} returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testShowClassRequiresAuth(): void
    {
        $response = $this->app->handle(new ServerRequest('GET', '/api/classes/1'));
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
    }

    // =========================================================================
    // POST /api/classes
    // =========================================================================

    /** PSR-15 POST /api/classes returns documented response format on success. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testCreateClassResponseFormat(): void
    {
        $schoolId  = $this->createTestSchool('CompatCreate_' . time());
        $className = 'CompatCreateClass_' . time();

        $request = (new ServerRequest('POST', '/api/classes'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $className, 'school_id' => $schoolId])));
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertSame('Class created successfully', $body['message']);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('id',       $body['data']);
        $this->assertArrayHasKey('name',     $body['data']);
        $this->assertArrayHasKey('group_id', $body['data']);
        $this->assertSame($className, $body['data']['name']);
        $this->createdClassIds[] = $body['data']['id'];
    }

    /** PSR-15 POST /api/classes returns 400 when name is missing. Req 1.7.2, 1.7.3 */
    public function testCreateClassMissingName(): void
    {
        $schoolId = $this->createTestSchool('CompatMissingName_' . time());

        $request = (new ServerRequest('POST', '/api/classes'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['school_id' => $schoolId])));
        $response = $this->app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(400, $body);
        $this->assertNull($body['data']);
        $this->assertStringContainsString('required', strtolower($body['message']));
    }

    /** PSR-15 POST /api/classes returns 400 when school_id is missing. Req 1.7.2, 1.7.3 */
    public function testCreateClassMissingSchoolId(): void
    {
        $request = (new ServerRequest('POST', '/api/classes'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => 'No School Class'])));
        $response = $this->app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(400, $body);
        $this->assertNull($body['data']);
    }

    /** PSR-15 POST /api/classes returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testCreateClassRequiresAuth(): void
    {
        $request = (new ServerRequest('POST', '/api/classes'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => 'Unauthorized', 'school_id' => 1])));
        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
    }

    // =========================================================================
    // PUT /api/classes/{id}
    // =========================================================================

    /** PSR-15 PUT /api/classes/{id} returns documented response format on success. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testUpdateClassResponseFormat(): void
    {
        $schoolId    = $this->createTestSchool('CompatUpdate_' . time());
        $classId     = $this->createTestClass('CompatUpdateClass_' . time(), $schoolId);
        $updatedName = 'CompatUpdated_' . time();

        $request = (new ServerRequest('PUT', "/api/classes/{$classId}"))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $updatedName])));
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertSame('Class updated successfully', $body['message']);
        $this->assertIsArray($body['data']);
        $this->assertSame($classId,     $body['data']['id']);
        $this->assertSame($updatedName, $body['data']['name']);
    }

    /** PSR-15 PUT /api/classes/{id} returns 404 for non-existent class. Req 1.7.2, 1.7.3 */
    public function testUpdateClassNotFound(): void
    {
        $request = (new ServerRequest('PUT', '/api/classes/999999'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => 'Ghost'])));
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(404, $body);
        $this->assertNull($body['data']);
    }

    /** PSR-15 PUT /api/classes/{id} returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testUpdateClassRequiresAuth(): void
    {
        $request = (new ServerRequest('PUT', '/api/classes/1'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => 'Unauthorized'])));
        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
    }

    // =========================================================================
    // DELETE /api/classes/{id}
    // =========================================================================

    /** PSR-15 DELETE /api/classes/{id} returns documented response format on success. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testDeleteClassResponseFormat(): void
    {
        $schoolId = $this->createTestSchool('CompatDelete_' . time());
        $classId  = $this->createTestClass('CompatDeleteClass_' . time(), $schoolId);

        $request = (new ServerRequest('DELETE', "/api/classes/{$classId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertSame('Class deleted successfully', $body['message']);

        $this->createdClassIds = array_values(array_filter($this->createdClassIds, fn($id) => $id !== $classId));

        // Verify deleted
        $verifyRequest = (new ServerRequest('GET', "/api/classes/{$classId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $this->assertSame(404, $this->app->handle($verifyRequest)->getStatusCode());
    }

    /** PSR-15 DELETE /api/classes/{id} returns 404 for non-existent class. Req 1.7.2, 1.7.3 */
    public function testDeleteClassNotFound(): void
    {
        $request = (new ServerRequest('DELETE', '/api/classes/999999'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(404, $body);
        $this->assertNull($body['data']);
    }

    /** PSR-15 DELETE /api/classes/{id} returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testDeleteClassRequiresAuth(): void
    {
        $response = $this->app->handle(new ServerRequest('DELETE', '/api/classes/1'));
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
    }

    // =========================================================================
    // Cross-endpoint consistency
    // =========================================================================

    /** All class endpoints return Content-Type: application/json. Req 1.7.1 */
    public function testAllEndpointsReturnJsonContentType(): void
    {
        $schoolId = $this->createTestSchool('CompatCT_' . time());
        $classId  = $this->createTestClass('CompatCTClass_' . time(), $schoolId);

        $requests = [
            (new ServerRequest('GET',    '/api/classes'))->withHeader('Authorization', 'Bearer ' . $this->authToken),
            (new ServerRequest('GET',    "/api/classes/{$classId}"))->withHeader('Authorization', 'Bearer ' . $this->authToken),
            (new ServerRequest('DELETE', '/api/classes/999999'))->withHeader('Authorization', 'Bearer ' . $this->authToken),
        ];
        foreach ($requests as $req) {
            $response = $this->app->handle($req);
            $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'),
                "{$req->getMethod()} {$req->getUri()->getPath()} must return application/json");
        }
    }

    /** Error responses always have null data field. Req 1.7.3 */
    public function testErrorResponsesHaveNullData(): void
    {
        $unauthRequests = [
            new ServerRequest('GET',    '/api/classes'),
            new ServerRequest('POST',   '/api/classes'),
            new ServerRequest('PUT',    '/api/classes/1'),
            new ServerRequest('DELETE', '/api/classes/1'),
        ];
        foreach ($unauthRequests as $req) {
            $body = json_decode((string) $this->app->handle($req)->getBody(), true);
            $this->assertApiContract($body);
            $this->assertNull($body['data'],
                "{$req->getMethod()} {$req->getUri()->getPath()} error response must have null data");
        }
    }

    /** Timestamp field is a recent Unix timestamp. Req 1.7.4 */
    public function testTimestampIsRecentUnixTime(): void
    {
        $request = (new ServerRequest('GET', '/api/classes'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $before   = time();
        $response = $this->app->handle($request);
        $after    = time();
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertGreaterThanOrEqual($before - 5, $body['timestamp']);
        $this->assertLessThanOrEqual($after + 5,     $body['timestamp']);
    }
}
