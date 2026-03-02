<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Helper\SecurityHelper;
use App\Helper\JwtHelper;
use App\Exception\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Security Integration Tests
 *
 * Tests SecurityHelper with malicious inputs and JWT edge cases.
 */
class SecurityIntegrationTest extends TestCase
{
    private JwtHelper $jwt;
    private string $secret = 'test-secret-key-for-security-tests';

    protected function setUp(): void
    {
        $this->jwt = new JwtHelper($this->secret, 3600);
    }

    // =========================================================================
    // SecurityHelper - XSS Prevention
    // =========================================================================

    public function testSanitizeInputWithScriptTag(): void
    {
        $malicious = '<script>document.cookie</script>';
        $result = SecurityHelper::sanitizeInput($malicious);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
    }

    public function testSanitizeInputWithEventHandler(): void
    {
        $malicious = '<img src=x onerror=alert(1)>';
        $result = SecurityHelper::sanitizeInput($malicious);
        // HTML tags are encoded, so <img> is not executable
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('&lt;img', $result);
    }

    public function testSanitizeInputWithSvgOnload(): void
    {
        $malicious = '<svg onload=alert(1)>';
        $result = SecurityHelper::sanitizeInput($malicious);
        $this->assertStringNotContainsString('<svg', $result);
    }

    public function testSanitizeInputWithJavascriptUri(): void
    {
        $malicious = '<a href="javascript:alert(1)">click</a>';
        $result = SecurityHelper::sanitizeInput($malicious);
        // HTML tags are encoded, so <a> is not executable
        $this->assertStringNotContainsString('<a ', $result);
        $this->assertStringContainsString('&lt;a', $result);
    }

    public function testSanitizeInputWithNestedTags(): void
    {
        $malicious = '<<script>script>alert(1)<</script>/script>';
        $result = SecurityHelper::sanitizeInput($malicious);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testSanitizeInputAllowHtmlBlocksScript(): void
    {
        $input = '<p>Hello</p><script>alert(1)</script><strong>World</strong>';
        $result = SecurityHelper::sanitizeInput($input, true);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testSanitizeInputWithEncodedEntities(): void
    {
        $input = 'Hello & "World" <test>';
        $result = SecurityHelper::sanitizeInput($input);
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&quot;', $result);
    }

    // =========================================================================
    // SecurityHelper - SQL Injection Prevention
    // =========================================================================

    public function testSanitizeSqlRemovesSemicolon(): void
    {
        $input = "1; DROP TABLE users";
        $result = SecurityHelper::sanitizeSql($input);
        $this->assertStringNotContainsString(';', $result);
    }

    public function testSanitizeSqlRemovesCommentDashes(): void
    {
        $input = "admin'--";
        $result = SecurityHelper::sanitizeSql($input);
        $this->assertStringNotContainsString('--', $result);
    }

    public function testSanitizeSqlRemovesBlockComments(): void
    {
        $input = "1 /* comment */ OR 1=1";
        $result = SecurityHelper::sanitizeSql($input);
        $this->assertStringNotContainsString('/*', $result);
        $this->assertStringNotContainsString('*/', $result);
    }

    public function testSanitizeSqlEscapesQuotes(): void
    {
        $input = "test' OR '1'='1";
        $result = SecurityHelper::sanitizeSql($input);
        $this->assertStringNotContainsString("' OR '", $result);
    }

    // =========================================================================
    // SecurityHelper - Array Sanitization
    // =========================================================================

    public function testSanitizeArrayWithMaliciousValues(): void
    {
        $data = [
            'name' => '<script>alert(1)</script>',
            'info' => '<img onerror=hack src=x>',
            'count' => 42,
            'nested' => [
                'field' => '<svg onload=alert(1)>',
            ],
        ];

        $result = SecurityHelper::sanitizeArray($data);

        $this->assertStringNotContainsString('<script>', $result['name']);
        // HTML tags are encoded — <img> becomes &lt;img, not executable
        $this->assertStringNotContainsString('<img', $result['info']);
        $this->assertStringContainsString('&lt;img', $result['info']);
        $this->assertSame(42, $result['count']);
        $this->assertStringNotContainsString('<svg', $result['nested']['field']);
    }

    public function testSanitizeArrayPreservesNonStringTypes(): void
    {
        $data = [
            'id' => 123,
            'active' => true,
            'score' => 3.14,
            'tags' => ['a', 'b'],
        ];

        $result = SecurityHelper::sanitizeArray($data);

        $this->assertSame(123, $result['id']);
        $this->assertTrue($result['active']);
        $this->assertSame(3.14, $result['score']);
    }

    // =========================================================================
    // SecurityHelper - URL Validation
    // =========================================================================

    public function testIsUrlSafeRejectsJavascriptProtocol(): void
    {
        $this->assertFalse(SecurityHelper::isUrlSafe('javascript:alert(1)'));
    }

    public function testIsUrlSafeRejectsDataProtocol(): void
    {
        $this->assertFalse(SecurityHelper::isUrlSafe('data:text/html,<script>alert(1)</script>'));
    }

    public function testIsUrlSafeRejectsFileProtocol(): void
    {
        $this->assertFalse(SecurityHelper::isUrlSafe('file:///etc/passwd'));
    }

    public function testIsUrlSafeWithSubdomainMatching(): void
    {
        $this->assertTrue(SecurityHelper::isUrlSafe('https://sub.example.com', ['example.com']));
        $this->assertFalse(SecurityHelper::isUrlSafe('https://notexample.com', ['example.com']));
    }

    public function testIsUrlSafeRejectsEmptyString(): void
    {
        $this->assertFalse(SecurityHelper::isUrlSafe(''));
    }

    // =========================================================================
    // SecurityHelper - Password Security
    // =========================================================================

    public function testHashPasswordUsesArgon2id(): void
    {
        $hash = SecurityHelper::hashPassword('TestP@ss123');
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function testHashPasswordProducesUniqueHashes(): void
    {
        $hash1 = SecurityHelper::hashPassword('SamePassword1!');
        $hash2 = SecurityHelper::hashPassword('SamePassword1!');
        $this->assertNotSame($hash1, $hash2); // Different salts
    }

    public function testVerifyPasswordRejectsWrongPassword(): void
    {
        $hash = SecurityHelper::hashPassword('Correct@Pass1');
        $this->assertFalse(SecurityHelper::verifyPassword('Wrong@Pass1', $hash));
    }

    public function testPasswordStrengthRejectsCommonWeakPasswords(): void
    {
        $weakPasswords = ['password', '12345678', 'abcdefgh', 'ABCDEFGH'];

        foreach ($weakPasswords as $pwd) {
            $result = SecurityHelper::checkPasswordStrength($pwd);
            $this->assertFalse($result['valid'], "Password '$pwd' should be rejected");
        }
    }

    // =========================================================================
    // SecurityHelper - CSRF Token
    // =========================================================================

    public function testCsrfTokenTimingSafeComparison(): void
    {
        $token = SecurityHelper::generateCsrfToken();
        // Slightly different token should fail
        $tampered = substr($token, 0, -1) . (($token[-1] === 'a') ? 'b' : 'a');
        $this->assertFalse(SecurityHelper::verifyCsrfToken($tampered, $token));
    }

    public function testCsrfTokenRejectsEmptyStrings(): void
    {
        $token = SecurityHelper::generateCsrfToken();
        $this->assertFalse(SecurityHelper::verifyCsrfToken('', $token));
        $this->assertFalse(SecurityHelper::verifyCsrfToken($token, ''));
    }

    // =========================================================================
    // JWT - Edge Cases
    // =========================================================================

    public function testJwtWithEmptyPayload(): void
    {
        $token = $this->jwt->generate([]);
        $decoded = $this->jwt->verify($token);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testJwtWithSpecialCharactersInPayload(): void
    {
        $payload = [
            'user_id' => 1,
            'username' => "test'user\"<script>",
            'roles' => ['admin'],
        ];
        $token = $this->jwt->generate($payload);
        $decoded = $this->jwt->verify($token);
        $this->assertSame("test'user\"<script>", $decoded['username']);
    }

    public function testJwtWithLargePayload(): void
    {
        $payload = [
            'user_id' => 1,
            'roles' => ['admin'],
            'data' => str_repeat('x', 1000),
        ];
        $token = $this->jwt->generate($payload);
        $decoded = $this->jwt->verify($token);
        $this->assertSame(1000, strlen($decoded['data']));
    }

    public function testJwtRejectsTokenSignedWithDifferentSecret(): void
    {
        $otherJwt = new JwtHelper('attacker-secret-key', 3600);
        $token = $otherJwt->generate(['user_id' => 1, 'roles' => ['admin']]);

        $this->expectException(UnauthorizedException::class);
        $this->jwt->verify($token);
    }

    public function testJwtRejectsCompletelyInvalidString(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->jwt->verify('not-a-jwt-at-all');
    }

    public function testJwtRejectsEmptyString(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->jwt->verify('');
    }

    public function testJwtExpiredTokenThrowsWithMessage(): void
    {
        $expiredJwt = new JwtHelper($this->secret, -1);
        $token = $expiredJwt->generate(['user_id' => 1]);

        try {
            $this->jwt->verify($token);
            $this->fail('Expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $this->assertStringContainsString('expired', strtolower($e->getMessage()));
        }
    }

    public function testJwtGetUserIdWithNonIntegerValue(): void
    {
        $token = $this->jwt->generate(['user_id' => '42', 'roles' => []]);
        $userId = $this->jwt->getUserId($token);
        $this->assertSame(42, $userId); // Should cast to int
    }

    public function testJwtGetUserRolesWithStringRole(): void
    {
        $token = $this->jwt->generate(['user_id' => 1, 'roles' => 'admin']);
        $roles = $this->jwt->getUserRoles($token);
        $this->assertSame(['admin'], $roles); // Should wrap in array
    }

    public function testJwtRefreshPreservesPayloadData(): void
    {
        $original = $this->jwt->generate([
            'user_id' => 5,
            'roles' => ['teacher'],
            'school_id' => 10,
        ]);

        $refreshed = $this->jwt->refresh($original);
        $decoded = $this->jwt->verify($refreshed);

        $this->assertSame(5, $decoded['user_id']);
        $this->assertSame(['teacher'], $decoded['roles']);
        $this->assertSame(10, $decoded['school_id']);
    }
}
