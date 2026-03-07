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
 * Pagination tests for GET /api/schools through PSR-15 middleware stack
 *
 * Covers:
 *   1. Default pagination (no params) — page=1, pageSize=20
 *   2. Custom page size (pageSize=5)
 *   3. Custom page number (page=2)
 *   4. Page size boundary (pageSize=1)
 *   5. Large page size (pageSize=100)
 *   6. Page beyond total — empty items, valid pagination metadata
 *   7. Pagination metadata accuracy (total, page, pageSize, totalPages)
 *   8. Items count matches pageSize (or less on last page)
 *   9. Consistent ordering across pages (no duplicates between pages)
 *  10. Invalid page params (page=0, pageSize=0, page=-1) — graceful handling
 */
class SchoolPaginationTest extends TestCase
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
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
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

        $config = ContainerConfig::create()
            ->withDefinitions(require __DIR__ . '/../../config/di.php');
        $this->container = new Container($config);
        $this->app       = $this->container->get(Application::class);
        $this->factory   = new Psr17Factory();

        $this->pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=3306;dbname=%s',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_NAME'] ?? 'bujiaban'
            ),
            $_ENV['DB_USER']     ?? 'bujiaban',
            $_ENV['DB_PASSWORD'] ?? 'testpassword',
            [
                \PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        $this->authToken = $this->authenticate();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdSchoolIds as $id) {
            try {
                $this->pdo->prepare('DELETE FROM edu_school WHERE id = ?')->execute([$id]);
            } catch (\Exception) {
                // ignore cleanup errors
            }
        }
        $this->createdSchoolIds = [];
        $this->authToken        = null;
        $this->pdo              = null;
    }

    // -------------------------------------------------------------------------
    // Helpers
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
            ->withBody($this->factory->createStream(json_encode([
                'name' => $name,
                'info' => ['description' => 'Pagination test school'],
            ])));

        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), "createTestSchool($name) must succeed");

        $body = json_decode((string) $response->getBody(), true);
        $id   = $body['data']['id'];
        $this->createdSchoolIds[] = $id;

        return $id;
    }

    /**
     * GET /api/schools with optional query string, returns decoded body.
     */
    private function listSchools(string $query = ''): array
    {
        $uri     = '/api/schools' . ($query !== '' ? '?' . $query : '');
        $request = (new ServerRequest('GET', $uri))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $response = $this->app->handle($request);
        $this->assertSame(200, $response->getStatusCode(), "GET $uri must return 200");

        return json_decode((string) $response->getBody(), true);
    }

    // -------------------------------------------------------------------------
    // Test 1: Default pagination — page=1, pageSize=20
    // -------------------------------------------------------------------------

    /**
     * Default pagination uses page=1 and pageSize=20 when no params are provided.
     */
    public function testDefaultPaginationUsesPage1AndPageSize20(): void
    {
        $body       = $this->listSchools();
        $pagination = $body['data']['pagination'];

        $this->assertSame(1,  $pagination['page'],     'Default page must be 1');
        $this->assertSame(20, $pagination['pageSize'], 'Default pageSize must be 20');
    }

    /**
     * Default pagination response contains all required pagination fields.
     */
    public function testDefaultPaginationResponseStructure(): void
    {
        $body       = $this->listSchools();
        $pagination = $body['data']['pagination'];

        $this->assertArrayHasKey('total',      $pagination);
        $this->assertArrayHasKey('page',       $pagination);
        $this->assertArrayHasKey('pageSize',   $pagination);
        $this->assertArrayHasKey('totalPages', $pagination);
        $this->assertIsInt($pagination['total']);
        $this->assertIsInt($pagination['page']);
        $this->assertIsInt($pagination['pageSize']);
        $this->assertIsInt($pagination['totalPages']);
    }

    // -------------------------------------------------------------------------
    // Test 2: Custom page size
    // -------------------------------------------------------------------------

    /**
     * pageSize=5 is reflected in the pagination metadata.
     */
    public function testCustomPageSizeIsReflectedInMetadata(): void
    {
        $body       = $this->listSchools('page=1&pageSize=5');
        $pagination = $body['data']['pagination'];

        $this->assertSame(1, $pagination['page']);
        $this->assertSame(5, $pagination['pageSize']);
    }

    /**
     * Items returned do not exceed the requested pageSize.
     */
    public function testItemsDoNotExceedCustomPageSize(): void
    {
        // Ensure at least 6 schools exist so the limit is exercised
        $prefix = 'PaginationCustomSize_' . uniqid();
        for ($i = 1; $i <= 6; $i++) {
            $this->createTestSchool("{$prefix}_{$i}");
        }

        $body  = $this->listSchools('page=1&pageSize=5');
        $items = $body['data']['items'];

        $this->assertLessThanOrEqual(5, count($items), 'Items must not exceed pageSize=5');
    }

    // -------------------------------------------------------------------------
    // Test 3: Custom page number
    // -------------------------------------------------------------------------

    /**
     * page=2 is reflected in the pagination metadata.
     */
    public function testCustomPageNumberIsReflectedInMetadata(): void
    {
        $body       = $this->listSchools('page=2&pageSize=5');
        $pagination = $body['data']['pagination'];

        $this->assertSame(2, $pagination['page']);
        $this->assertSame(5, $pagination['pageSize']);
    }

    // -------------------------------------------------------------------------
    // Test 4: Page size boundary — pageSize=1
    // -------------------------------------------------------------------------

    /**
     * pageSize=1 returns at most one item per page.
     */
    public function testPageSizeBoundaryOfOne(): void
    {
        // Ensure at least one school exists
        $this->createTestSchool('PaginationBoundary_' . uniqid());

        $body  = $this->listSchools('page=1&pageSize=1');
        $items = $body['data']['items'];

        $this->assertSame(1, $body['data']['pagination']['pageSize']);
        $this->assertLessThanOrEqual(1, count($items), 'pageSize=1 must return at most 1 item');
    }

    // -------------------------------------------------------------------------
    // Test 5: Large page size — pageSize=100
    // -------------------------------------------------------------------------

    /**
     * pageSize=100 is accepted and reflected in metadata (controller caps at 100).
     */
    public function testLargePageSizeIsAccepted(): void
    {
        $body       = $this->listSchools('page=1&pageSize=100');
        $pagination = $body['data']['pagination'];

        $this->assertSame(100, $pagination['pageSize']);
        $this->assertSame(1,   $pagination['page']);
    }

    // -------------------------------------------------------------------------
    // Test 6: Page beyond total — empty items, valid metadata
    // -------------------------------------------------------------------------

    /**
     * Requesting a page beyond the last page returns empty items array
     * but still provides valid pagination metadata.
     */
    public function testPageBeyondTotalReturnsEmptyItemsWithValidMetadata(): void
    {
        $body       = $this->listSchools('page=99999&pageSize=20');
        $pagination = $body['data']['pagination'];
        $items      = $body['data']['items'];

        $this->assertIsArray($items, 'items must be an array even when page is beyond total');
        $this->assertEmpty($items,   'items must be empty when page is beyond total');

        // Metadata must still be valid
        $this->assertSame(99999, $pagination['page']);
        $this->assertSame(20,    $pagination['pageSize']);
        $this->assertGreaterThanOrEqual(0, $pagination['total']);
        $this->assertGreaterThanOrEqual(0, $pagination['totalPages']);
    }

    // -------------------------------------------------------------------------
    // Test 7: Pagination metadata accuracy
    // -------------------------------------------------------------------------

    /**
     * totalPages is correctly computed as ceil(total / pageSize).
     */
    public function testTotalPagesIsComputedCorrectly(): void
    {
        $body       = $this->listSchools('page=1&pageSize=3');
        $pagination = $body['data']['pagination'];

        $expectedTotalPages = (int) ceil($pagination['total'] / $pagination['pageSize']);
        $this->assertSame(
            $expectedTotalPages,
            $pagination['totalPages'],
            'totalPages must equal ceil(total / pageSize)'
        );
    }

    /**
     * After creating known schools, total count increases accordingly.
     */
    public function testTotalCountReflectsCreatedSchools(): void
    {
        $before = $this->listSchools('page=1&pageSize=1')['data']['pagination']['total'];

        $prefix = 'PaginationTotal_' . uniqid();
        $this->createTestSchool("{$prefix}_A");
        $this->createTestSchool("{$prefix}_B");

        $after = $this->listSchools('page=1&pageSize=1')['data']['pagination']['total'];

        $this->assertSame($before + 2, $after, 'total must increase by 2 after creating 2 schools');
    }

    // -------------------------------------------------------------------------
    // Test 8: Items count matches pageSize (or less on last page)
    // -------------------------------------------------------------------------

    /**
     * Items on a full page equal pageSize exactly.
     */
    public function testItemsOnFullPageEqualPageSize(): void
    {
        // Create enough schools to guarantee a full page of 3
        $prefix = 'PaginationFull_' . uniqid();
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestSchool("{$prefix}_{$i}");
        }

        $body  = $this->listSchools('page=1&pageSize=3');
        $items = $body['data']['items'];

        // Only assert equality when total >= pageSize
        if ($body['data']['pagination']['total'] >= 3) {
            $this->assertCount(3, $items, 'A full page must contain exactly pageSize items');
        }
    }

    /**
     * Items on the last page are ≤ pageSize.
     */
    public function testItemsOnLastPageAreLessThanOrEqualToPageSize(): void
    {
        $body       = $this->listSchools('page=1&pageSize=7');
        $pagination = $body['data']['pagination'];
        $totalPages = $pagination['totalPages'];

        if ($totalPages < 1) {
            $this->markTestSkipped('No schools in database to test last-page behaviour');
        }

        $lastPageBody  = $this->listSchools("page={$totalPages}&pageSize=7");
        $lastPageItems = $lastPageBody['data']['items'];

        $this->assertLessThanOrEqual(7, count($lastPageItems), 'Last page items must be ≤ pageSize');
    }

    // -------------------------------------------------------------------------
    // Test 9: Consistent ordering across pages (no duplicates)
    // -------------------------------------------------------------------------

    /**
     * IDs returned on page 1 and page 2 (with pageSize=5) are disjoint.
     */
    public function testNoDuplicatesBetweenConsecutivePages(): void
    {
        // Ensure at least 10 schools exist
        $prefix = 'PaginationOrder_' . uniqid();
        for ($i = 1; $i <= 10; $i++) {
            $this->createTestSchool("{$prefix}_{$i}");
        }

        $page1Ids = array_column($this->listSchools('page=1&pageSize=5')['data']['items'], 'id');
        $page2Ids = array_column($this->listSchools('page=2&pageSize=5')['data']['items'], 'id');

        if (empty($page1Ids) || empty($page2Ids)) {
            $this->markTestSkipped('Not enough schools to test cross-page duplicates');
        }

        $intersection = array_intersect($page1Ids, $page2Ids);
        $this->assertEmpty($intersection, 'No school ID should appear on both page 1 and page 2');
    }

    /**
     * Requesting the same page twice returns the same IDs (stable ordering).
     */
    public function testSamePageReturnsSameOrderOnRepeatedRequests(): void
    {
        $ids1 = array_column($this->listSchools('page=1&pageSize=5')['data']['items'], 'id');
        $ids2 = array_column($this->listSchools('page=1&pageSize=5')['data']['items'], 'id');

        $this->assertSame($ids1, $ids2, 'Repeated requests for the same page must return the same order');
    }

    // -------------------------------------------------------------------------
    // Test 10: Invalid page params — graceful handling
    // -------------------------------------------------------------------------

    /**
     * page=0 is handled gracefully (does not crash; returns a valid response).
     *
     * The controller casts to int (0) and passes it to the service which computes
     * offset = (0-1)*pageSize = negative — the service/DB may clamp or return all rows.
     * We only assert the response is 200 with valid structure.
     */
    public function testPageZeroIsHandledGracefully(): void
    {
        $request = (new ServerRequest('GET', '/api/schools?page=0&pageSize=5'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $response = $this->app->handle($request);

        // Must not crash — accept 200 or any non-5xx status
        $this->assertLessThan(500, $response->getStatusCode(), 'page=0 must not cause a 500 error');

        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotNull($body, 'Response must be valid JSON');
        $this->assertArrayHasKey('code', $body);
    }

    /**
     * pageSize=0 is clamped to 1 by the controller (min(max(0,1),100) = 1).
     */
    public function testPageSizeZeroIsClamped(): void
    {
        $body       = $this->listSchools('page=1&pageSize=0');
        $pagination = $body['data']['pagination'];

        // Controller clamps: min(max(0, 1), 100) = 1
        $this->assertSame(1, $pagination['pageSize'], 'pageSize=0 must be clamped to 1');
    }

    /**
     * page=-1 is handled gracefully (does not crash).
     */
    public function testNegativePageIsHandledGracefully(): void
    {
        $request = (new ServerRequest('GET', '/api/schools?page=-1&pageSize=5'))
            ->withHeader('Authorization', 'Bearer ' . $this->authToken);

        $response = $this->app->handle($request);

        $this->assertLessThan(500, $response->getStatusCode(), 'page=-1 must not cause a 500 error');

        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotNull($body, 'Response must be valid JSON');
        $this->assertArrayHasKey('code', $body);
    }

    /**
     * pageSize=-1 is clamped to 1 by the controller (min(max(-1,1),100) = 1).
     */
    public function testNegativePageSizeIsClamped(): void
    {
        $body       = $this->listSchools('page=1&pageSize=-1');
        $pagination = $body['data']['pagination'];

        // Controller clamps: min(max(-1, 1), 100) = 1
        $this->assertSame(1, $pagination['pageSize'], 'pageSize=-1 must be clamped to 1');
    }
}
