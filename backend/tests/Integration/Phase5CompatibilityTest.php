<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Compatibility tests: verify Phase 5 PSR-15 endpoints return same structure
 * as the legacy switch-case routing in index.php.
 *
 * These tests call the live API at http://localhost:8084/api and compare
 * response shapes between legacy and PSR-15 paths.
 *
 * Run manually when Docker is available:
 *   vendor/bin/phpunit tests/Integration/Phase5CompatibilityTest.php
 */
class Phase5CompatibilityTest extends TestCase
{
    private string $baseUrl = 'http://localhost:8084/api';
    private ?string $token = null;

    protected function setUp(): void
    {
        // Try to get a real token from the live API
        $response = $this->httpRequest('POST', '/auth/login', [
            'username' => 'guanfei',
            'password' => '123456',
        ]);

        if ($response && isset($response['data']['token'])) {
            $this->token = $response['data']['token'];
        } else {
            $this->markTestSkipped('Cannot connect to live API or login failed');
        }
    }

    private function httpRequest(string $method, string $path, ?array $body = null, bool $auth = true): ?array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = ['Content-Type: application/json', 'Accept: application/json'];

        if ($auth && $this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error || $result === false) return null;
        return json_decode($result, true);
    }

    private function assertResponseShape(array $response): void
    {
        $this->assertArrayHasKey('code', $response, 'Response must have code field');
        $this->assertArrayHasKey('message', $response, 'Response must have message field');
        $this->assertArrayHasKey('data', $response, 'Response must have data field');
        $this->assertArrayHasKey('timestamp', $response, 'Response must have timestamp field');
    }

    private function assertListShape(array $data): void
    {
        $this->assertArrayHasKey('items', $data, 'List response must have items');
        $this->assertArrayHasKey('pagination', $data, 'List response must have pagination');
        $this->assertArrayHasKey('total', $data['pagination']);
        $this->assertArrayHasKey('page', $data['pagination']);
        $this->assertArrayHasKey('pageSize', $data['pagination']);
        $this->assertArrayHasKey('totalPages', $data['pagination']);
    }

    // ==================== Groups ====================

    public function testGroupsListResponseShape(): void
    {
        $response = $this->httpRequest('GET', '/groups');
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(200, $response['code']);
        $this->assertListShape($response['data']);
    }

    public function testGroupsListRequiresAuth(): void
    {
        $response = $this->httpRequest('GET', '/groups', null, false);
        $this->assertNotNull($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testGroupNotFoundShape(): void
    {
        $response = $this->httpRequest('GET', '/groups/999999');
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(404, $response['code']);
    }

    // ==================== Students ====================

    public function testStudentsListResponseShape(): void
    {
        $response = $this->httpRequest('GET', '/students');
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(200, $response['code']);
        $this->assertListShape($response['data']);
    }

    public function testStudentsListRequiresAuth(): void
    {
        $response = $this->httpRequest('GET', '/students', null, false);
        $this->assertNotNull($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testStudentNotFoundShape(): void
    {
        $response = $this->httpRequest('GET', '/students/999999');
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(404, $response['code']);
    }

    public function testStudentCreateValidationShape(): void
    {
        $response = $this->httpRequest('POST', '/students', ['class_id' => 1]);
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(400, $response['code']);
    }

    // ==================== Teachers ====================

    public function testTeachersListResponseShape(): void
    {
        $response = $this->httpRequest('GET', '/teachers');
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(200, $response['code']);
        $this->assertListShape($response['data']);
    }

    public function testTeachersListRequiresAuth(): void
    {
        $response = $this->httpRequest('GET', '/teachers', null, false);
        $this->assertNotNull($response);
        $this->assertEquals(401, $response['code']);
    }

    public function testTeacherNotFoundShape(): void
    {
        $response = $this->httpRequest('GET', '/teachers/999999');
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(404, $response['code']);
    }

    // ==================== User Search ====================

    public function testUserSearchResponseShape(): void
    {
        $response = $this->httpRequest('GET', '/users/search?keyword=a');
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(200, $response['code']);
        $this->assertListShape($response['data']);
    }

    public function testUserSearchRequiresKeyword(): void
    {
        $response = $this->httpRequest('GET', '/users/search');
        $this->assertNotNull($response);
        $this->assertResponseShape($response);
        $this->assertEquals(400, $response['code']);
    }

    public function testUserSearchRequiresAuth(): void
    {
        $response = $this->httpRequest('GET', '/users/search?keyword=test', null, false);
        $this->assertNotNull($response);
        $this->assertEquals(401, $response['code']);
    }
}
