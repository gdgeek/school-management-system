<?php

declare(strict_types=1);

namespace App\Tests\Unit\Helper;

use App\Helper\SecurityHelper;
use PHPUnit\Framework\TestCase;

class SecurityHelperTest extends TestCase
{
    public function testSanitizeInputStripsHtmlTags(): void
    {
        $input = '<script>alert("xss")</script>Hello';
        $result = SecurityHelper::sanitizeInput($input);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testSanitizeInputAllowsSafeHtml(): void
    {
        $input = '<p>Hello</p><script>alert("xss")</script>';
        $result = SecurityHelper::sanitizeInput($input, true);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testSanitizeInputEncodesSpecialChars(): void
    {
        $input = 'Hello & "World" <test>';
        $result = SecurityHelper::sanitizeInput($input);
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringNotContainsString('<test>', $result);
    }

    public function testSanitizeArrayCleansNestedStrings(): void
    {
        $data = [
            'name' => '<b>Test</b>',
            'nested' => [
                'value' => '<script>xss</script>',
            ],
            'number' => 42,
        ];

        $result = SecurityHelper::sanitizeArray($data);
        $this->assertStringNotContainsString('<b>', $result['name']);
        $this->assertStringNotContainsString('<script>', $result['nested']['value']);
        $this->assertSame(42, $result['number']);
    }

    public function testGenerateCsrfTokenReturnsHexString(): void
    {
        $token = SecurityHelper::generateCsrfToken();
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function testGenerateCsrfTokenIsUnique(): void
    {
        $token1 = SecurityHelper::generateCsrfToken();
        $token2 = SecurityHelper::generateCsrfToken();
        $this->assertNotSame($token1, $token2);
    }

    public function testVerifyCsrfTokenWithValidToken(): void
    {
        $token = SecurityHelper::generateCsrfToken();
        $this->assertTrue(SecurityHelper::verifyCsrfToken($token, $token));
    }

    public function testVerifyCsrfTokenWithInvalidToken(): void
    {
        $this->assertFalse(SecurityHelper::verifyCsrfToken('invalid', 'expected'));
    }

    public function testSanitizeSqlRemovesDangerousPatterns(): void
    {
        $input = "SELECT * FROM users; DROP TABLE users--";
        $result = SecurityHelper::sanitizeSql($input);
        $this->assertStringNotContainsString(';', $result);
        $this->assertStringNotContainsString('--', $result);
    }

    public function testIsUrlSafeWithValidHttpsUrl(): void
    {
        $this->assertTrue(SecurityHelper::isUrlSafe('https://example.com'));
    }

    public function testIsUrlSafeWithValidHttpUrl(): void
    {
        $this->assertTrue(SecurityHelper::isUrlSafe('http://example.com'));
    }

    public function testIsUrlSafeRejectsInvalidUrl(): void
    {
        $this->assertFalse(SecurityHelper::isUrlSafe('not-a-url'));
    }

    public function testIsUrlSafeRejectsFtpProtocol(): void
    {
        $this->assertFalse(SecurityHelper::isUrlSafe('ftp://example.com'));
    }

    public function testIsUrlSafeWithAllowedDomains(): void
    {
        $this->assertTrue(SecurityHelper::isUrlSafe('https://app.example.com', ['example.com']));
        $this->assertFalse(SecurityHelper::isUrlSafe('https://evil.com', ['example.com']));
    }

    public function testGenerateRandomStringLength(): void
    {
        $str = SecurityHelper::generateRandomString(16);
        $this->assertSame(16, strlen($str));
    }

    public function testCheckPasswordStrengthWithStrongPassword(): void
    {
        $result = SecurityHelper::checkPasswordStrength('MyP@ssw0rd!');
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testCheckPasswordStrengthWithWeakPassword(): void
    {
        $result = SecurityHelper::checkPasswordStrength('abc');
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testCheckPasswordStrengthRequiresMinLength(): void
    {
        $result = SecurityHelper::checkPasswordStrength('Ab1!');
        $this->assertFalse($result['valid']);
        $this->assertContains(
            'Password must be at least 8 characters long',
            $result['errors']
        );
    }

    public function testHashAndVerifyPassword(): void
    {
        $password = 'SecureP@ss123';
        $hash = SecurityHelper::hashPassword($password);

        $this->assertNotSame($password, $hash);
        $this->assertTrue(SecurityHelper::verifyPassword($password, $hash));
        $this->assertFalse(SecurityHelper::verifyPassword('wrong', $hash));
    }
}
