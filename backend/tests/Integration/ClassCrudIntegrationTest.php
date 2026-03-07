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
 * Integration tests for Class CRUD operations through PSR-15 middleware stack.
 *
 * Covers:
 *   - Task 14.2: Full CRUD through PSR-15 Application
 *   - Task 14.3: Auto-group creation when creating classes
 *   - Task 14.4: deleteGroups parameter for DELETE endpoint
 *   - Task 14.5: school_id filter for GET /api/classes
 *
 * Requirements validated:
 *   4.4.1  ClassController handles all CRUD operations via PSR-7 requests
 *   4.4.2  Auto-group creation works when creating classes
 *   4.4.3  Current user ID is correctly extracted for group creation
 *   4.4.4  deleteGroups parameter works correctly for DELETE endpoint
 *   4.4.5  school_id filter works correctly for GET /api/classes
 */
class ClassCrudIntegrationTest extends TestCase
{
    private ContainerInterface $container;
    private Application $app;
    private Psr17Factory $factory;
    private ?string $authToken = null;
    private ?int $authUserId = null;
    private ?\PDO $pdo = null;

    /** IDs of classes created during tests — cleaned up in tearDown */
    private array $createdClassIds = [];
    /** IDs of schools created during tests — cleaned up in tearDown */
    private array $createdSchoolIds = [];

    protected function setUp(): void
    {
        // Load .env
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

        [$this->authToken, $this->authUserId] = $this->authenticate();
    }

    protected function tearDown(): void
    {
        // Delete created classes (also removes class-group associations via DB cascade or explicit delete)
        foreach ($this->createdClassIds as $id) {
            try {
                // Remove class-group associations
                $this->pdo->prepare('DELETE FROM edu_class_group WHERE class_id = ?')->execute([$id]);
                $this->pdo->prepare('DELETE FROM edu_class WHERE id = ?')->execute([$id]);
            } catch (\Exception) {}
        }

        // Delete created schools
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

    private function authenticate(): array
    {
        $request = (new ServerRequest('POST', '/api/auth/login'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['username' => 'guanfei', 'password' => '123456'])));
        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), 'Authentication must succeed');
        $body = json_decode((string) $response->getBody(), true);
        return [$body['data']['token'], (int) $body['data']['user']['id']];
    }

    /** Create a test school and return its ID */
    private function createTestSchool(string $name): int
    {
        $request = (new ServerRequest('POST', '/api/schools'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $name, 'info' => ['description' => 'test']])));
        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), "createTestSchool($name) must succeed");
        $body = json_decode((string) $response->getBody(), true);
        $id = $body['data']['id'];
        $this->createdSchoolIds[] = $id;
        return $id;
    }

    /** Create a test class and return its response body data */
    private function createTestClass(string $name, int $schoolId): array
    {
        $request = (new ServerRequest('POST', '/api/classes'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $name, 'school_id' => $schoolId])));
        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), "createTestClass($name) must succeed");
        $body = json_decode((string) $response->getBody(), true);
        $this->createdClassIds[] = $body['data']['id'];
        return $body['data'];
    }

    // =========================================================================
    // Task 14.2: Full CRUD
    // =========================================================================

    /** POST /api/classes creates a class successfully */
    public function testCreateClass(): void
    {
        $schoolId  = $this->createTestSchool('CRUD Test School ' . time());
        $className = 'CRUD Test Class ' . time();

        $request = (new ServerRequest('POST', '/api/classes'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $className, 'school_id' => $schoolId])));
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $body['code']);
        $this->assertSame('Class created successfully', $body['message']);
        $this->assertArrayHasKey('id', $body['data']);
        $this->assertSame($className, $body['data']['name']);
        $this->createdClassIds[] = $body['data']['id'];
    }

    /** GET /api/classes lists classes */
    public function testListClasses(): void
    {
        $schoolId = $this->createTestSchool('List Test School ' . time());
        $this->createTestClass('List Class A', $schoolId);

        $request = (new ServerRequest('GET', '/api/classes'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $body['code']);
        $this->assertArrayHasKey('items', $body['data']);
        $this->assertArrayHasKey('pagination', $body['data']);
    }

    /** GET /api/classes/{id} returns a single class */
    public function testShowClass(): void
    {
        $schoolId  = $this->createTestSchool('Show Test School ' . time());
        $classData = $this->createTestClass('Show Test Class', $schoolId);
        $classId   = $classData['id'];

        $request = (new ServerRequest('GET', "/api/classes/{$classId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $body['code']);
        $this->assertSame($classId, $body['data']['id']);
        $this->assertSame('Show Test Class', $body['data']['name']);
    }

    /** PUT /api/classes/{id} updates a class */
    public function testUpdateClass(): void
    {
        $schoolId  = $this->createTestSchool('Update Test School ' . time());
        $classData = $this->createTestClass('Original Class Name', $schoolId);
        $classId   = $classData['id'];

        $updatedName = 'Updated Class Name ' . time();
        $request = (new ServerRequest('PUT', "/api/classes/{$classId}"))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $updatedName])));
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $body['code']);
        $this->assertSame('Class updated successfully', $body['message']);
        $this->assertSame($updatedName, $body['data']['name']);
    }

    /** DELETE /api/classes/{id} deletes a class */
    public function testDeleteClass(): void
    {
        $schoolId  = $this->createTestSchool('Delete Test School ' . time());
        $classData = $this->createTestClass('Class to Delete', $schoolId);
        $classId   = $classData['id'];

        $request = (new ServerRequest('DELETE', "/api/classes/{$classId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $body['code']);
        $this->assertSame('Class deleted successfully', $body['message']);

        // Remove from cleanup list — already deleted
        $this->createdClassIds = array_values(array_filter($this->createdClassIds, fn($id) => $id !== $classId));

        // Verify it's gone
        $verifyRequest = (new ServerRequest('GET', "/api/classes/{$classId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $this->assertSame(404, $this->app->handle($verifyRequest)->getStatusCode());
    }

    /** Protected endpoints return 401 without auth token */
    public function testRequiresAuthentication(): void
    {
        $endpoints = [
            ['GET',    '/api/classes'],
            ['POST',   '/api/classes'],
        ];

        foreach ($endpoints as [$method, $path]) {
            $request  = new ServerRequest($method, $path);
            $response = $this->app->handle($request);
            $this->assertSame(401, $response->getStatusCode(),
                "$method $path should return 401 without auth");
        }
    }

    /** GET /api/classes/{id} returns 404 for non-existent class */
    public function testShowNonExistentClassReturns404(): void
    {
        $request = (new ServerRequest('GET', '/api/classes/999999'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(404, $body['code']);
    }

    // =========================================================================
    // Task 14.3: Auto-group creation
    // =========================================================================

    /**
     * Creating a class automatically creates an associated group.
     * Verifies:
     *   1. Response contains a positive integer group_id
     *   2. The group actually exists in the `group` table
     *   3. The group's user_id matches the authenticated user's ID
     *   4. The class-group association exists in edu_class_group
     *
     * Validates: Requirements 4.4.2, 4.4.3
     */
    public function testCreateClassAutoCreatesGroup(): void
    {
        $schoolId  = $this->createTestSchool('AutoGroup School ' . time());
        $className = 'AutoGroup Class ' . time();

        $request = (new ServerRequest('POST', '/api/classes'))
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->withBody($this->factory->createStream(json_encode(['name' => $className, 'school_id' => $schoolId])));
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        // 1. Response must contain group_id
        $this->assertArrayHasKey('group_id', $body['data'],
            'Response must contain group_id field when class is created');

        $groupId = $body['data']['group_id'];
        $classId = $body['data']['id'];
        $this->createdClassIds[] = $classId;

        // group_id must be a positive integer
        $this->assertIsInt($groupId, 'group_id must be an integer');
        $this->assertGreaterThan(0, $groupId, 'group_id must be a positive integer');

        // 2. The group must actually exist in the database
        $stmt = $this->pdo->prepare('SELECT * FROM `group` WHERE id = ?');
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        $this->assertNotFalse($group,
            "Group with id={$groupId} must exist in the `group` table");

        // 3. The group's user_id must match the authenticated user's ID
        $this->assertSame($this->authUserId, (int) $group['user_id'],
            "Group user_id must match the authenticated user's ID ({$this->authUserId}), got {$group['user_id']}");

        // 4. The class-group association must exist in edu_class_group
        $stmt = $this->pdo->prepare('SELECT * FROM edu_class_group WHERE class_id = ? AND group_id = ?');
        $stmt->execute([$classId, $groupId]);
        $association = $stmt->fetch();
        $this->assertNotFalse($association,
            "edu_class_group must contain a row linking class_id={$classId} to group_id={$groupId}");
    }

    // =========================================================================
    // Task 14.4: deleteGroups parameter
    // =========================================================================

    /**
     * DELETE /api/classes/{id}?deleteGroups=true deletes the class and its associated groups.
     *
     * Validates: Requirements 4.4.4
     */
    public function testDeleteClassWithDeleteGroupsParam(): void
    {
        $schoolId  = $this->createTestSchool('DeleteGroups School ' . time());
        $classData = $this->createTestClass('DeleteGroups Class ' . time(), $schoolId);
        $classId   = $classData['id'];

        // Verify group was auto-created
        $this->assertArrayHasKey('group_id', $classData, 'Class creation must return group_id');
        $groupId = $classData['group_id'];
        $this->assertGreaterThan(0, $groupId);

        // Delete class with deleteGroups=true
        $request = (new ServerRequest('DELETE', "/api/classes/{$classId}?deleteGroups=true"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $body['code']);
        $this->assertSame('Class deleted successfully', $body['message']);

        // Remove from cleanup list — already deleted
        $this->createdClassIds = array_values(array_filter($this->createdClassIds, fn($id) => $id !== $classId));

        // Verify the group was also deleted
        $stmt = $this->pdo->prepare('SELECT id FROM `group` WHERE id = ?');
        $stmt->execute([$groupId]);
        $this->assertFalse($stmt->fetch(), 'Associated group must be deleted when deleteGroups=true');
    }

    /**
     * DELETE /api/classes/{id} without deleteGroups param deletes the class but keeps the groups.
     * DELETE /api/classes/{id}?deleteGroups=false also keeps the groups.
     *
     * Validates: Requirements 4.4.4
     */
    public function testDeleteClassWithoutDeleteGroupsKeepsGroups(): void
    {
        $schoolId  = $this->createTestSchool('KeepGroups School ' . time());
        $classData = $this->createTestClass('KeepGroups Class ' . time(), $schoolId);
        $classId   = $classData['id'];

        // Verify group was auto-created
        $this->assertArrayHasKey('group_id', $classData, 'Class creation must return group_id');
        $groupId = $classData['group_id'];
        $this->assertGreaterThan(0, $groupId);

        // Verify group exists before deletion
        $stmt = $this->pdo->prepare('SELECT id FROM `group` WHERE id = ?');
        $stmt->execute([$groupId]);
        $this->assertNotFalse($stmt->fetch(), 'Group must exist before class deletion');

        // Delete class WITHOUT deleteGroups param (defaults to false)
        $request = (new ServerRequest('DELETE', "/api/classes/{$classId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $body['code']);
        $this->assertSame('Class deleted successfully', $body['message']);

        // Remove from cleanup list — class already deleted
        $this->createdClassIds = array_values(array_filter($this->createdClassIds, fn($id) => $id !== $classId));

        // Verify the class is gone
        $verifyRequest = (new ServerRequest('GET', "/api/classes/{$classId}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $this->assertSame(404, $this->app->handle($verifyRequest)->getStatusCode());

        // Verify the group still exists (was NOT deleted)
        $stmt = $this->pdo->prepare('SELECT id FROM `group` WHERE id = ?');
        $stmt->execute([$groupId]);
        $this->assertNotFalse($stmt->fetch(),
            'Associated group must be preserved when deleteGroups is not set');

        // Cleanup: delete the orphaned group
        $this->pdo->prepare('DELETE FROM `group` WHERE id = ?')->execute([$groupId]);

        // Also verify deleteGroups=false explicitly keeps the group
        $classData2 = $this->createTestClass('KeepGroups Class2 ' . time(), $schoolId);
        $classId2   = $classData2['id'];
        $groupId2   = $classData2['group_id'];

        $request2 = (new ServerRequest('DELETE', "/api/classes/{$classId2}?deleteGroups=false"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response2 = $this->app->handle($request2);

        $this->assertSame(200, $response2->getStatusCode());

        // Remove from cleanup list — class already deleted
        $this->createdClassIds = array_values(array_filter($this->createdClassIds, fn($id) => $id !== $classId2));

        // Verify the group still exists
        $stmt = $this->pdo->prepare('SELECT id FROM `group` WHERE id = ?');
        $stmt->execute([$groupId2]);
        $this->assertNotFalse($stmt->fetch(),
            'Associated group must be preserved when deleteGroups=false');

        // Cleanup: delete the orphaned group
        $this->pdo->prepare('DELETE FROM `group` WHERE id = ?')->execute([$groupId2]);
    }

    // =========================================================================
    // Task 14.5: school_id filter
    // =========================================================================

    /**
     * GET /api/classes?school_id=X returns only classes from that school.
     *
     * Validates: Requirements 4.4.5
     */
    public function testListClassesFilterBySchoolId(): void
    {
        $schoolA = $this->createTestSchool('Filter School A ' . time());
        $schoolB = $this->createTestSchool('Filter School B ' . time());

        $classA = $this->createTestClass('Class in School A', $schoolA);
        $classB = $this->createTestClass('Class in School B', $schoolB);

        // Filter by school A
        $request = (new ServerRequest('GET', "/api/classes?school_id={$schoolA}"))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(200, $body['code']);

        $items = $body['data']['items'];
        $this->assertIsArray($items);

        // All returned items must belong to school A
        foreach ($items as $item) {
            $this->assertSame($schoolA, $item['school_id'],
                "All classes returned for school_id={$schoolA} must have school_id={$schoolA}");
        }

        // Class from school A must be present
        $foundA = false;
        foreach ($items as $item) {
            if ($item['id'] === $classA['id']) {
                $foundA = true;
                break;
            }
        }
        $this->assertTrue($foundA, 'Class from school A must appear in filtered results');

        // Class from school B must NOT be present
        $foundB = false;
        foreach ($items as $item) {
            if ($item['id'] === $classB['id']) {
                $foundB = true;
                break;
            }
        }
        $this->assertFalse($foundB, 'Class from school B must NOT appear when filtering by school A');
    }
}
