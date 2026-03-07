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
 * Integration tests for School CRUD operations through PSR-15 middleware stack
 * 
 * Tests the complete request/response cycle including:
 * - Authentication flow (login → get token → use token)
 * - All CRUD operations (Create, Read, Update, Delete)
 * - Pagination and search functionality
 * - Error scenarios (404, 401, validation errors)
 * - Response format validation
 */
class SchoolCrudIntegrationTest extends TestCase
{
    private ContainerInterface $container;
    private Application $app;
    private Psr17Factory $factory;
    private ?string $authToken = null;
    private ?\PDO $pdo = null;
    private array $createdSchoolIds = [];

    protected function setUp(): void
    {
        // Load environment variables from .env file manually
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Skip comments
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
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
        
        // Create application instance
        $this->app = $this->container->get(Application::class);
        
        // Create PSR-17 factory
        $this->factory = new Psr17Factory();
        
        // Get database connection for cleanup
        // Use Docker database credentials
        $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $dbName = $_ENV['DB_NAME'] ?? 'bujiaban';
        $dbUser = $_ENV['DB_USER'] ?? 'bujiaban';
        $dbPassword = $_ENV['DB_PASSWORD'] ?? 'testpassword';
        
        $this->pdo = new \PDO(
            "mysql:host={$dbHost};port=3306;dbname={$dbName}",
            $dbUser,
            $dbPassword,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
        
        // Authenticate and get token
        $this->authToken = $this->authenticate();
    }

    protected function tearDown(): void
    {
        // Clean up created schools
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
        $this->authToken = null;
        $this->pdo = null;
    }

    /**
     * Authenticate and get JWT token
     */
    private function authenticate(): string
    {
        $request = new ServerRequest('POST', '/api/auth/login');
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'username' => 'guanfei',
                'password' => '123456'
            ])));

        $response = $this->app->handle($request);
        
        // Debug: print response if authentication fails
        if ($response->getStatusCode() !== 200) {
            $body = (string)$response->getBody();
            echo "\nAuthentication failed with status " . $response->getStatusCode() . "\n";
            echo "Response body: " . $body . "\n";
        }
        
        $this->assertEquals(200, $response->getStatusCode(), 'Authentication should succeed');
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('token', $body['data']);
        
        return $body['data']['token'];
    }

    /**
     * Test: Create school (POST /api/schools)
     */
    public function testCreateSchool(): void
    {
        $schoolData = [
            'name' => 'Integration Test School ' . time(),
            'info' => ['description' => 'Test school for integration testing'],
            'principal_id' => null
        ];

        $request = new ServerRequest('POST', '/api/schools');
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode($schoolData)));

        $response = $this->app->handle($request);

        // Verify response status
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify response format
        $body = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('code', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('timestamp', $body);
        
        // Verify response data
        $this->assertEquals(200, $body['code']);
        $this->assertEquals('School created successfully', $body['message']);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('id', $body['data']);
        $this->assertArrayHasKey('name', $body['data']);
        $this->assertEquals($schoolData['name'], $body['data']['name']);
        
        // Store ID for cleanup
        $this->createdSchoolIds[] = $body['data']['id'];
    }

    /**
     * Test: Create school without authentication (should fail with 401)
     */
    public function testCreateSchoolWithoutAuth(): void
    {
        $schoolData = [
            'name' => 'Unauthorized School',
            'info' => ['description' => 'This should fail']
        ];

        $request = new ServerRequest('POST', '/api/schools');
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode($schoolData)));

        $response = $this->app->handle($request);

        // Verify 401 Unauthorized
        $this->assertEquals(401, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(401, $body['code']);
        $this->assertStringContainsString('authentication', strtolower($body['message']));
    }

    /**
     * Test: Create school with invalid data (should fail with 400)
     */
    public function testCreateSchoolWithInvalidData(): void
    {
        $schoolData = [
            'name' => '', // Empty name should fail
        ];

        $request = new ServerRequest('POST', '/api/schools');
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode($schoolData)));

        $response = $this->app->handle($request);

        // Verify 400 Bad Request
        $this->assertEquals(400, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(400, $body['code']);
        $this->assertStringContainsString('required', strtolower($body['message']));
    }

    /**
     * Test: List schools with pagination (GET /api/schools)
     */
    public function testListSchoolsWithPagination(): void
    {
        // Create test schools
        $school1Id = $this->createTestSchool('Pagination Test School 1');
        $school2Id = $this->createTestSchool('Pagination Test School 2');

        $request = new ServerRequest('GET', '/api/schools?page=1&pageSize=10');
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $response = $this->app->handle($request);

        // Verify response
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(200, $body['code']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('items', $body['data']);
        $this->assertArrayHasKey('pagination', $body['data']);
        
        // Verify pagination structure
        $pagination = $body['data']['pagination'];
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('page', $pagination);
        $this->assertArrayHasKey('pageSize', $pagination);
        $this->assertArrayHasKey('totalPages', $pagination);
        $this->assertEquals(1, $pagination['page']);
        $this->assertEquals(10, $pagination['pageSize']);
        
        // Verify items structure
        $this->assertIsArray($body['data']['items']);
        if (!empty($body['data']['items'])) {
            $firstItem = $body['data']['items'][0];
            $this->assertArrayHasKey('id', $firstItem);
            $this->assertArrayHasKey('name', $firstItem);
        }
    }

    /**
     * Test: Search schools (GET /api/schools?search=keyword)
     */
    public function testSearchSchools(): void
    {
        // Create a school with unique name
        $uniqueName = 'SearchableSchool' . uniqid();
        $schoolId = $this->createTestSchool($uniqueName);

        $request = new ServerRequest('GET', '/api/schools?search=' . urlencode($uniqueName));
        $request = $request->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $response = $this->app->handle($request);

        // Verify response
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(200, $body['code']);
        $this->assertArrayHasKey('items', $body['data']);
        
        // Verify search results contain our school
        $found = false;
        foreach ($body['data']['items'] as $item) {
            if ($item['id'] === $schoolId) {
                $found = true;
                $this->assertEquals($uniqueName, $item['name']);
                break;
            }
        }
        $this->assertTrue($found, 'Created school should be found in search results');
    }

    /**
     * Test: Get single school (GET /api/schools/{id})
     */
    public function testGetSingleSchool(): void
    {
        // Create a test school
        $schoolName = 'Single School Test ' . time();
        $schoolId = $this->createTestSchool($schoolName);

        $request = new ServerRequest('GET', "/api/schools/{$schoolId}");
        $request = $request
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId);

        $response = $this->app->handle($request);

        // Verify response
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(200, $body['code']);
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
        $this->assertEquals($schoolId, $body['data']['id']);
        $this->assertEquals($schoolName, $body['data']['name']);
    }

    /**
     * Test: Get non-existent school (should return 404)
     */
    public function testGetNonExistentSchool(): void
    {
        $nonExistentId = 999999;

        $request = new ServerRequest('GET', "/api/schools/{$nonExistentId}");
        $request = $request
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $nonExistentId);

        $response = $this->app->handle($request);

        // Verify 404 Not Found
        $this->assertEquals(404, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(404, $body['code']);
        $this->assertStringContainsString('not found', strtolower($body['message']));
    }

    /**
     * Test: Update school (PUT /api/schools/{id})
     */
    public function testUpdateSchool(): void
    {
        // Create a test school
        $originalName = 'Original School Name ' . time();
        $schoolId = $this->createTestSchool($originalName);

        // Update the school
        $updatedName = 'Updated School Name ' . time();
        $updateData = [
            'name' => $updatedName,
            'info' => ['description' => 'Updated description']
        ];

        $request = new ServerRequest('PUT', "/api/schools/{$schoolId}");
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId)
            ->withBody($this->factory->createStream(json_encode($updateData)));

        $response = $this->app->handle($request);

        // Verify response
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(200, $body['code']);
        $this->assertEquals('School updated successfully', $body['message']);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals($schoolId, $body['data']['id']);
        $this->assertEquals($updatedName, $body['data']['name']);
    }

    /**
     * Test: Update non-existent school (should return 404)
     */
    public function testUpdateNonExistentSchool(): void
    {
        $nonExistentId = 999999;
        $updateData = ['name' => 'This should fail'];

        $request = new ServerRequest('PUT', "/api/schools/{$nonExistentId}");
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $nonExistentId)
            ->withBody($this->factory->createStream(json_encode($updateData)));

        $response = $this->app->handle($request);

        // Verify 404 Not Found
        $this->assertEquals(404, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(404, $body['code']);
    }

    /**
     * Test: Delete school (DELETE /api/schools/{id})
     */
    public function testDeleteSchool(): void
    {
        // Create a test school
        $schoolId = $this->createTestSchool('School to Delete ' . time());

        $request = new ServerRequest('DELETE', "/api/schools/{$schoolId}");
        $request = $request
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId);

        $response = $this->app->handle($request);

        // Verify response
        $this->assertEquals(200, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(200, $body['code']);
        $this->assertEquals('School deleted successfully', $body['message']);
        
        // Remove from cleanup list since it's already deleted
        $this->createdSchoolIds = array_filter(
            $this->createdSchoolIds,
            fn($id) => $id !== $schoolId
        );
        
        // Verify school is actually deleted
        $verifyRequest = new ServerRequest('GET', "/api/schools/{$schoolId}");
        $verifyRequest = $verifyRequest
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId);

        $verifyResponse = $this->app->handle($verifyRequest);
        $this->assertEquals(404, $verifyResponse->getStatusCode());
    }

    /**
     * Test: Delete non-existent school (should return 404)
     */
    public function testDeleteNonExistentSchool(): void
    {
        $nonExistentId = 999999;

        $request = new ServerRequest('DELETE', "/api/schools/{$nonExistentId}");
        $request = $request
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $nonExistentId);

        $response = $this->app->handle($request);

        // Verify 404 Not Found
        $this->assertEquals(404, $response->getStatusCode());
        
        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals(404, $body['code']);
    }

    /**
     * Test: Complete workflow - Create, Read, Update, Delete
     */
    public function testCompleteWorkflow(): void
    {
        // Step 1: Create
        $schoolName = 'Workflow Test School ' . time();
        $createData = [
            'name' => $schoolName,
            'info' => ['description' => 'Workflow test']
        ];

        $createRequest = new ServerRequest('POST', '/api/schools');
        $createRequest = $createRequest
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode($createData)));

        $createResponse = $this->app->handle($createRequest);
        $this->assertEquals(200, $createResponse->getStatusCode());
        
        $createBody = json_decode((string)$createResponse->getBody(), true);
        $schoolId = $createBody['data']['id'];
        $this->createdSchoolIds[] = $schoolId;

        // Step 2: Read
        $readRequest = new ServerRequest('GET', "/api/schools/{$schoolId}");
        $readRequest = $readRequest
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId);

        $readResponse = $this->app->handle($readRequest);
        $this->assertEquals(200, $readResponse->getStatusCode());
        
        $readBody = json_decode((string)$readResponse->getBody(), true);
        $this->assertEquals($schoolName, $readBody['data']['name']);

        // Step 3: Update
        $updatedName = 'Updated ' . $schoolName;
        $updateData = ['name' => $updatedName];

        $updateRequest = new ServerRequest('PUT', "/api/schools/{$schoolId}");
        $updateRequest = $updateRequest
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId)
            ->withBody($this->factory->createStream(json_encode($updateData)));

        $updateResponse = $this->app->handle($updateRequest);
        $this->assertEquals(200, $updateResponse->getStatusCode());
        
        $updateBody = json_decode((string)$updateResponse->getBody(), true);
        $this->assertEquals($updatedName, $updateBody['data']['name']);

        // Step 4: Delete
        $deleteRequest = new ServerRequest('DELETE', "/api/schools/{$schoolId}");
        $deleteRequest = $deleteRequest
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withAttribute('id', $schoolId);

        $deleteResponse = $this->app->handle($deleteRequest);
        $this->assertEquals(200, $deleteResponse->getStatusCode());
        
        // Remove from cleanup list
        $this->createdSchoolIds = array_filter(
            $this->createdSchoolIds,
            fn($id) => $id !== $schoolId
        );
    }

    /**
     * Helper: Create a test school and return its ID
     */
    private function createTestSchool(string $name): int
    {
        $schoolData = [
            'name' => $name,
            'info' => ['description' => 'Test school']
        ];

        $request = new ServerRequest('POST', '/api/schools');
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode($schoolData)));

        $response = $this->app->handle($request);
        $body = json_decode((string)$response->getBody(), true);
        
        $schoolId = $body['data']['id'];
        $this->createdSchoolIds[] = $schoolId;
        
        return $schoolId;
    }
}
