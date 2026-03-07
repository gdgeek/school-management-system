<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helper;

use App\Helper\JwtHelper;
use App\Exception\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for JwtHelper.
 *
 * These tests require the firebase/php-jwt package.
 * They verify token generation, verification, and extraction logic.
 */
class JwtHelperTest extends TestCase
{
    private JwtHelper $jwt;
    private string $secret = 'test-secret-key-for-unit-tests-only-32chars';

    protected function setUp(): void
    {
        $this->jwt = new JwtHelper($this->secret, 3600);
    }

    public function testGenerateReturnsNonEmptyString(): void
    {
        $token = $this->jwt->generate(['user_id' => 1, 'roles' => ['admin']]);
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testGenerateProducesThreePartJwt(): void
    {
        $token = $this->jwt->generate(['user_id' => 1]);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT should have 3 parts separated by dots');
    }

    public function testVerifyDecodesValidToken(): void
    {
        $payload = ['user_id' => 42, 'roles' => ['teacher']];
        $token = $this->jwt->generate($payload);
        $decoded = $this->jwt->verify($token);

        $this->assertSame(42, $decoded['user_id']);
        $this->assertSame(['teacher'], $decoded['roles']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testVerifyThrowsOnInvalidToken(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->jwt->verify('invalid.token.here');
    }

    public function testVerifyThrowsOnWrongSecret(): void
    {
        $token = $this->jwt->generate(['user_id' => 1]);
        $otherJwt = new JwtHelper('different-secret-key-32chars-min!', 3600);

        $this->expectException(UnauthorizedException::class);
        $otherJwt->verify($token);
    }

    public function testVerifyThrowsOnExpiredToken(): void
    {
        $shortLivedJwt = new JwtHelper($this->secret, -1);
        $token = $shortLivedJwt->generate(['user_id' => 1]);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('expired');
        $this->jwt->verify($token);
    }

    public function testGetUserIdExtractsId(): void
    {
        $token = $this->jwt->generate(['user_id' => 99]);
        $this->assertSame(99, $this->jwt->getUserId($token));
    }

    public function testGetUserIdThrowsWhenMissing(): void
    {
        $token = $this->jwt->generate(['name' => 'test']);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('user_id');
        $this->jwt->getUserId($token);
    }

    public function testGetUserRolesExtractsRoles(): void
    {
        $token = $this->jwt->generate(['user_id' => 1, 'roles' => ['admin', 'teacher']]);
        $roles = $this->jwt->getUserRoles($token);
        $this->assertSame(['admin', 'teacher'], $roles);
    }

    public function testGetUserRolesReturnsEmptyWhenNoRoles(): void
    {
        $token = $this->jwt->generate(['user_id' => 1]);
        $roles = $this->jwt->getUserRoles($token);
        $this->assertSame([], $roles);
    }

    public function testRefreshGeneratesNewToken(): void
    {
        $original = $this->jwt->generate(['user_id' => 1, 'roles' => ['admin']]);
        // Sleep 1 second so iat/exp differ, guaranteeing a different token string
        sleep(1);
        $refreshed = $this->jwt->refresh($original);

        $this->assertNotSame($original, $refreshed);

        $decoded = $this->jwt->verify($refreshed);
        $this->assertSame(1, $decoded['user_id']);
        $this->assertSame(['admin'], $decoded['roles']);
    }

    public function testIsExpiringSoonReturnsTrueForShortLivedToken(): void
    {
        $shortJwt = new JwtHelper($this->secret, 60);
        $token = $shortJwt->generate(['user_id' => 1]);
        $this->assertTrue($this->jwt->isExpiringSoon($token));
    }

    public function testIsExpiringSoonReturnsFalseForLongLivedToken(): void
    {
        $token = $this->jwt->generate(['user_id' => 1]);
        $this->assertFalse($this->jwt->isExpiringSoon($token));
    }

    public function testIsExpiringSoonReturnsTrueForInvalidToken(): void
    {
        $this->assertTrue($this->jwt->isExpiringSoon('invalid-token'));
    }

    // ── Secret key strength ───────────────────────────────────────────────────

    public function testConstructorRejectsShortSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('32 characters');
        new JwtHelper('short-key', 3600);
    }

    public function testConstructorAcceptsExactly32CharSecret(): void
    {
        $jwt = new JwtHelper(str_repeat('a', 32), 3600);
        $this->assertInstanceOf(JwtHelper::class, $jwt);
    }

    public function testConstructorAcceptsLongSecret(): void
    {
        $jwt = new JwtHelper(str_repeat('x', 64), 3600);
        $this->assertInstanceOf(JwtHelper::class, $jwt);
    }

    // ── Error message does not leak internals ─────────────────────────────────

    public function testVerifyDoesNotLeakInternalExceptionMessage(): void
    {
        try {
            $this->jwt->verify('bad.token.value');
            $this->fail('Expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            // Must not contain raw exception details
            $this->assertStringNotContainsString('Exception', $e->getMessage());
            $this->assertStringNotContainsString('firebase', strtolower($e->getMessage()));
            $this->assertSame('Invalid token', $e->getMessage());
        }
    }
}
