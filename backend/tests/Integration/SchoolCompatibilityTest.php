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
 * Compatibility tests for School endpoints via PSR-15 middleware stack
 *
 * Validates that the PSR-15 Application produces responses conforming to the
 * documented API contract: {code, message, data, timestamp}.
 *
 * Endpoints covered:
 *   GET    /api/schools
 *   GET    /api/schools/{id}
 *   POST   /api/schools
 *   PUT    /api/schools/{id}
 *   DELETE /api/schools/{id}
 *
 * Requirements validated:
 *   1.7.1  Identical request/response formats for migrated endpoints
 *   1.7.2  Same HTTP status codes as legacy implementation
 *   1.7.3  Same error message formats and structure
 *   1.7.4  JSON response structure: {code, message, data, timestamp}
 *   1.7.5  All existing query parameters, request body fields, and headers supported
 */
class SchoolCompatibilityTest extends TestCase
{
    private ContainerInterface $container;
    private Application $app;
    private Psr17Factory $factory;
    private ?string $authToken = null;
    private ?\PDO $pdo = null;
    private array $createdSchoolIds = [];

    protected function setUp(): void
    {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
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
        foreach ($this->createdSchoolIds as $id) {
            try { $this->pdo->prepare('DELETE FROM edu_school WHERE id = ?')->execute([$id]); }
            catch (\Exception) {}
        }
        $this->createdSchoolIds = [];
        $this->authToken = null;
        $this->pdo = null;
    }

    private function authenticate(): string
    {
        $request = (new ServerRequest('POST', '/api/auth/login'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['username' => 'guanfei', 'password' => '123456'])));
        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), 'Authentication must succeed');
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('token', $body['data']);
        return $body['data']['token'];
    }

    private function createTestSchool(string $name): int
    {
        $request = (new ServerRequest('POST', '/api/schools'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $name, 'info' => ['description' => 'compat test']])));
        $response = $this->app->handle($request);
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $response->getStatusCode(), 'createTestSchool must succeed');
        $id = $body['data']['id'];
        $this->createdSchoolIds[] = $id;
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
    // GET /api/schools
    // =========================================================================

    /** PSR-15 GET /api/schools returns documented response format. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testListSchoolsResponseFormat(): void
    {
        $request = (new ServerRequest('GET', '/api/schools'))
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

    /** PSR-15 GET /api/schools supports pagination params. Req 1.7.5 */
    public function testListSchoolsPaginationParams(): void
    {
        $request = (new ServerRequest('GET', '/api/schools?page=1&pageSize=5'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertApiContract($body);
        $this->assertSame(1, $body['data']['pagination']['page']);
        $this->assertSame(5, $body['data']['pagination']['pageSize']);
    }

    /** PSR-15 GET /api/schools supports search param. Req 1.7.5 */
    public function testListSchoolsSearchParam(): void
    {
        $uniqueName = 'CompatSearch_' . uniqid();
        $schoolId   = $this->createTestSchool($uniqueName);

        $request = (new ServerRequest('GET', '/api/schools?search=' . urlencode($uniqueName)))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertApiContract($body);
        $found = false;
        foreach ($body['data']['items'] as $item) {
            if ($item['id'] === $schoolId) { $found = true; $this->assertSame($uniqueName, $item['name']); break; }
        }
        $this->assertTrue($found, 'Searched school must appear in results');
    }

    /** PSR-15 GET /api/schools returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testListSchoolsRequiresAuth(): void
    {
        $response = $this->app->handle(new ServerRequest('GET', '/api/schools'));
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
        $this->assertNotEmpty($body['message']);
    }

    // =========================================================================
    // GET /api/schools/{id}
    // =========================================================================

    /** PSR-15 GET /api/schools/{id} returns documented response format. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testShowSchoolResponseFormat(): void
    {
        $name     = 'CompatShow_' . time();
        $schoolId = $this->createTestSchool($name);

        $request = (new ServerRequest('GET', "/api/schools/{$schoolId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('id',   $body['data']);
        $this->assertArrayHasKey('name', $body['data']);
        $this->assertSame($schoolId, $body['data']['id']);
        $this->assertSame($name,     $body['data']['name']);
    }

    /** PSR-15 GET /api/schools/{id} returns 404 for non-existent school. Req 1.7.2, 1.7.3 */
    public function testShowSchoolNotFound(): void
    {
        $request = (new ServerRequest('GET', '/api/schools/999999'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(404, $body);
        $this->assertNull($body['data']);
        $this->assertStringContainsString('not found', strtolower($body['message']));
    }

    /** PSR-15 GET /api/schools/{id} returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testShowSchoolRequiresAuth(): void
    {
        $response = $this->app->handle(new ServerRequest('GET', '/api/schools/1'));
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
    }

    // =========================================================================
    // POST /api/schools
    // =========================================================================

    /** PSR-15 POST /api/schools returns documented response format on success. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testCreateSchoolResponseFormat(): void
    {
        $name = 'CompatCreate_' . time();
        $request = (new ServerRequest('POST', '/api/schools'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $name, 'info' => ['description' => 'compat']])));
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertSame('School created successfully', $body['message']);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('id',   $body['data']);
        $this->assertArrayHasKey('name', $body['data']);
        $this->assertSame($name, $body['data']['name']);
        $this->createdSchoolIds[] = $body['data']['id'];
    }

    /** PSR-15 POST /api/schools returns 400 when name is missing. Req 1.7.2, 1.7.3 */
    public function testCreateSchoolMissingName(): void
    {
        $request = (new ServerRequest('POST', '/api/schools'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['info' => 'no name'])));
        $response = $this->app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(400, $body);
        $this->assertNull($body['data']);
        $this->assertStringContainsString('required', strtolower($body['message']));
    }

    /** PSR-15 POST /api/schools returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testCreateSchoolRequiresAuth(): void
    {
        $request = (new ServerRequest('POST', '/api/schools'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => 'Unauthorized'])));
        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
    }

    // =========================================================================
    // PUT /api/schools/{id}
    // =========================================================================

    /** PSR-15 PUT /api/schools/{id} returns documented response format on success. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testUpdateSchoolResponseFormat(): void
    {
        $schoolId    = $this->createTestSchool('CompatUpdate_' . time());
        $updatedName = 'CompatUpdated_' . time();

        $request = (new ServerRequest('PUT', "/api/schools/{$schoolId}"))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId)
            ->withBody($this->factory->createStream(json_encode(['name' => $updatedName])));
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertSame('School updated successfully', $body['message']);
        $this->assertIsArray($body['data']);
        $this->assertSame($schoolId,    $body['data']['id']);
        $this->assertSame($updatedName, $body['data']['name']);
    }

    /** PSR-15 PUT /api/schools/{id} returns 404 for non-existent school. Req 1.7.2, 1.7.3 */
    public function testUpdateSchoolNotFound(): void
    {
        $request = (new ServerRequest('PUT', '/api/schools/999999'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', 999999)
            ->withBody($this->factory->createStream(json_encode(['name' => 'Ghost'])));
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(404, $body);
        $this->assertNull($body['data']);
    }

    /** PSR-15 PUT /api/schools/{id} returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testUpdateSchoolRequiresAuth(): void
    {
        $request = (new ServerRequest('PUT', '/api/schools/1'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => 'Unauthorized'])));
        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
    }

    // =========================================================================
    // DELETE /api/schools/{id}
    // =========================================================================

    /** PSR-15 DELETE /api/schools/{id} returns documented response format on success. Req 1.7.1, 1.7.2, 1.7.4 */
    public function testDeleteSchoolResponseFormat(): void
    {
        $schoolId = $this->createTestSchool('CompatDelete_' . time());

        $request = (new ServerRequest('DELETE', "/api/schools/{$schoolId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(200, $body);
        $this->assertSame('School deleted successfully', $body['message']);

        $this->createdSchoolIds = array_values(array_filter($this->createdSchoolIds, fn($id) => $id !== $schoolId));

        $verifyRequest = (new ServerRequest('GET', "/api/schools/{$schoolId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $this->assertSame(404, $this->app->handle($verifyRequest)->getStatusCode());
    }

    /** PSR-15 DELETE /api/schools/{id} returns 404 for non-existent school. Req 1.7.2, 1.7.3 */
    public function testDeleteSchoolNotFound(): void
    {
        $request = (new ServerRequest('DELETE', '/api/schools/999999'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', 999999);
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(404, $body);
        $this->assertNull($body['data']);
    }

    /** PSR-15 DELETE /api/schools/{id} returns 401 without token. Req 1.7.2, 1.7.3 */
    public function testDeleteSchoolRequiresAuth(): void
    {
        $response = $this->app->handle(new ServerRequest('DELETE', '/api/schools/1'));
        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertApiContract($body);
        $this->assertStatusCodeConsistency(401, $body);
    }

    // =========================================================================
    // Cross-endpoint consistency
    // =========================================================================

    /** All school endpoints return Content-Type: application/json. Req 1.7.1 */
    public function testAllEndpointsReturnJsonContentType(): void
    {
        $schoolId = $this->createTestSchool('CompatCT_' . time());
        $requests = [
            (new ServerRequest('GET',    '/api/schools'))->withHeader('Authorization', 'Bearer ' . $this->authToken),
            (new ServerRequest('GET',    "/api/schools/{$schoolId}"))->withHeader('Authorization', 'Bearer ' . $this->authToken),
            (new ServerRequest('DELETE', '/api/schools/999999'))->withHeader('Authorization', 'Bearer ' . $this->authToken),
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
            new ServerRequest('GET',    '/api/schools'),
            new ServerRequest('POST',   '/api/schools'),
            new ServerRequest('PUT',    '/api/schools/1'),
            new ServerRequest('DELETE', '/api/schools/1'),
        ];
        foreach ($unauthRequests as $req) {
            $body = json_decode((string) $this->app->handle($req)->getBody(), true);
            $this->assertApiContract($body);
            $this->assertNull($body['data'],
                "{$req->getMethod()} {$req->getUri()->getPath()} error response must have null data");
        }
    }

    /** Successful list and show responses have non-null data. Req 1.7.1 */
    public function testSuccessResponsesHaveNonNullData(): void
    {
        $schoolId = $this->createTestSchool('CompatNonNull_' . time());
        $requests = [
            (new ServerRequest('GET', '/api/schools'))->withHeader('Authorization', 'Bearer ' . $this->authToken),
            (new ServerRequest('GET', "/api/schools/{$schoolId}"))->withHeader('Authorization', 'Bearer ' . $this->authToken),
        ];
        foreach ($requests as $req) {
            $response = $this->app->handle($req);
            $body = json_decode((string) $response->getBody(), true);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertApiContract($body);
            $this->assertNotNull($body['data'],
                "{$req->getMethod()} {$req->getUri()->getPath()} success response must have non-null data");
        }
    }

    /** Timestamp field is a recent Unix timestamp. Req 1.7.4 */
    public function testTimestampIsRecentUnixTime(): void
    {
        $request = (new ServerRequest('GET', '/api/schools'))
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
