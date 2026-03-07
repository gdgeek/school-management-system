# PSR-15 Middleware Stack — Security Audit Report

**Date**: 2025  
**Scope**: PSR-15 middleware stack implementation  
**Status**: All issues fixed, all tests passing (123/123)

---

## Summary

| # | Component | Issue | Severity | Status |
|---|-----------|-------|----------|--------|
| 1 | `JwtHelper` | Exception message leaks internal details | Medium | ✅ Fixed |
| 2 | `JwtHelper` | No minimum secret key length enforcement | High | ✅ Fixed |
| 3 | `AuthMiddleware` | Token accepted from URL query parameter | High | ✅ Fixed |
| 4 | `RateLimitMiddleware` | X-Forwarded-For not validated — IP spoofing | High | ✅ Fixed |
| 5 | `RateLimitMiddleware` | Redis key collision risk with IPv6 addresses | Medium | ✅ Fixed |
| 6 | `RequestValidationMiddleware` | JSON parse error message leaked in 400 response | Low | ✅ Fixed |
| 7 | `SecurityMiddleware` | CSP default policy allowed `'unsafe-inline'` scripts | Medium | ✅ Fixed |

---

## Detailed Findings

### Issue 1 — JwtHelper: Exception message leaks internal details

**File**: `src/Helper/JwtHelper.php`  
**Severity**: Medium  
**Requirement**: 2.2.4 — sanitize error messages in production to prevent information disclosure

**Before**:
```php
} catch (\Exception $e) {
    throw new UnauthorizedException('Invalid token: ' . $e->getMessage());
}
```

The catch-all block forwarded the raw `$e->getMessage()` from `firebase/php-jwt` to the caller. This could expose library internals, algorithm names, or structural details about the token format to an attacker.

**Fix**: Return a generic message with no internal detail.
```php
} catch (\Exception $e) {
    throw new UnauthorizedException('Invalid token');
}
```

**Test added**: `testVerifyDoesNotLeakInternalExceptionMessage()` in `JwtHelperTest`

---

### Issue 2 — JwtHelper: No minimum secret key length enforcement

**File**: `src/Helper/JwtHelper.php`  
**Severity**: High  
**Requirement**: 2.2.2 — validate JWT token signatures using a strong secret key (256-bit minimum)

**Before**: The constructor accepted any string, including single-character secrets, making HMAC-SHA256 trivially brute-forceable.

**Fix**: Enforce a minimum of 32 characters (256 bits) at construction time.
```php
public function __construct(string $secret, int $expireTime = 3600)
{
    if (strlen($secret) < 32) {
        throw new \InvalidArgumentException(
            'JWT secret key must be at least 32 characters (256 bits) long.'
        );
    }
    ...
}
```

**Tests added**: `testConstructorRejectsShortSecret()`, `testConstructorAcceptsExactly32CharSecret()`, `testConstructorAcceptsLongSecret()` in `JwtHelperTest`

**Side effect**: All existing tests using short secrets were updated to use 32+ character secrets.

---

### Issue 3 — AuthMiddleware: Token accepted from URL query parameter

**File**: `src/Middleware/AuthMiddleware.php`  
**Severity**: High  
**Requirement**: 2.2.4 — sanitize error messages / prevent information disclosure

Tokens in URLs are a well-known security anti-pattern:
- Logged verbatim by web servers, proxies, and load balancers
- Stored in browser history
- Leaked via the `Referer` header to third-party resources
- Visible in server-side access logs

**Before**:
```php
// 3. 从查询参数提取（用于跨系统跳转）
$queryParams = $request->getQueryParams();
if (isset($queryParams['token'])) {
    return $queryParams['token'];
}
```

**Fix**: Removed query-parameter extraction entirely. Only `Authorization: Bearer` header and `auth_token` cookie are accepted.

**Test added**: `testQueryParamTokenIsNotAccepted()` in `AuthMiddlewareTest` (new test file)

---

### Issue 4 — RateLimitMiddleware: X-Forwarded-For not validated (IP spoofing)

**File**: `src/Middleware/RateLimitMiddleware.php`  
**Severity**: High

`X-Forwarded-For` and `X-Real-IP` are client-controlled headers. Without validation, an attacker can send `X-Forwarded-For: 1.2.3.4` to impersonate any IP and bypass rate limiting entirely.

**Before**:
```php
$ip = trim($parts[0]);
if ($ip !== '') {
    return $ip;  // No validation — accepts any string
}
```

**Fix**: Each extracted IP is validated with `filter_var($ip, FILTER_VALIDATE_IP)` before use. Invalid values fall through to the next source, ultimately falling back to `REMOTE_ADDR`.
```php
if ($ip !== '' && $this->isValidIp($ip)) {
    return $ip;
}
```

**Tests added**: `testInvalidIpInXForwardedForFallsBackToRemoteAddr()`, `testInvalidIpInXRealIpFallsBackToRemoteAddr()`, `testIpv6AddressIsAccepted()` in `RateLimitMiddlewareTest`

---

### Issue 5 — RateLimitMiddleware: Redis key collision risk with IPv6

**File**: `src/Middleware/RateLimitMiddleware.php`  
**Severity**: Medium

The Redis key format was `rate_limit:{ip}:{window_start}`. IPv6 addresses contain colons (e.g. `2001:db8::1`), which makes the key ambiguous and could cause collision with the window-start separator.

**Before**:
```php
$cacheKey = self::KEY_PREFIX . $ip . ':' . $windowStart;
// e.g. rate_limit:2001:db8::1:1700000000  ← ambiguous
```

**Fix**: Hash the IP with `md5()` to produce a fixed-length, colon-free key component.
```php
$cacheKey = self::KEY_PREFIX . md5($ip) . ':' . $windowStart;
// e.g. rate_limit:a1b2c3d4...:1700000000  ← unambiguous
```

**Test added**: `testRedisKeyUsesHashedIpToAvoidCollisions()` in `RateLimitMiddlewareTest`; existing `testRedisKeyContainsIpAndWindowStart()` updated to use hashed IP.

---

### Issue 6 — RequestValidationMiddleware: JSON parse error details leaked

**File**: `src/Middleware/RequestValidationMiddleware.php`  
**Severity**: Low  
**Requirement**: 2.2.4 — sanitize error messages in production

**Before**:
```php
return $this->badRequest(
    'Request body contains invalid JSON: ' . json_last_error_msg() . '.'
);
```

`json_last_error_msg()` returns PHP-internal error strings (e.g. `"Syntax error"`, `"Unexpected control character found"`) that reveal parsing internals to clients.

**Fix**: Return a generic message.
```php
return $this->badRequest('Request body contains invalid JSON.');
```

No test change required — existing tests check for 400 status, not the specific message text.

---

### Issue 7 — SecurityMiddleware: CSP default policy allowed `'unsafe-inline'`

**File**: `src/Middleware/SecurityMiddleware.php`  
**Severity**: Medium  
**Requirement**: 2.2.1 — add security headers via SecurityMiddleware

**Before**:
```
default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;
```

`'unsafe-inline'` for `script-src` defeats the primary XSS protection that CSP provides. For a pure JSON API backend that serves no HTML, scripts, or stylesheets, this policy was unnecessarily permissive.

**Fix**: Use the strictest possible policy appropriate for a JSON API.
```
default-src 'none'; frame-ancestors 'none';
```

- `default-src 'none'` — blocks all resource loading (no scripts, styles, images, frames, etc.)
- `frame-ancestors 'none'` — redundant with `X-Frame-Options: SAMEORIGIN` but provides defence-in-depth via CSP

**Test updated**: `testCspHeaderPresentByDefault()` in `SecurityMiddlewareTest` now asserts `'unsafe-inline'` is absent and `frame-ancestors` is present.

---

## Items Reviewed with No Issues Found

### AuthMiddleware — JWT validation
- Signature validation: delegated to `firebase/php-jwt` with explicit `Key($secret, 'HS256')` — algorithm is pinned, `none` algorithm is rejected by the library
- Expiry enforcement: `ExpiredException` is caught and converted to 401
- Error responses: generic messages, no stack traces

### CorsMiddleware — Origin validation
- Wildcard `*` with `credentials=true` correctly falls back to echoing the specific origin (browsers require this)
- `Vary: Origin` header always added when origin-based matching is used
- Preflight OPTIONS requests are short-circuited before reaching controllers

### index.php — Error handling
- `APP_DEBUG` defaults to `false`; stack traces only exposed when explicitly enabled
- No environment variables echoed in responses
- `X-Powered-By` removed by `SecurityMiddleware`

---

## Tests Added / Modified

| File | Change |
|------|--------|
| `tests/Unit/Middleware/AuthMiddlewareTest.php` | **New** — 14 tests covering token extraction, user context injection, security properties |
| `tests/Unit/Helper/JwtHelperTest.php` | Added 3 secret-length tests + 1 error-leakage test; updated short secrets |
| `tests/Unit/Middleware/RateLimitMiddlewareTest.php` | Added 4 IP-validation/IPv6 tests; updated key-format test |
| `tests/Unit/Middleware/SecurityMiddlewareTest.php` | Updated CSP assertion to verify no `unsafe-inline` |
| `tests/Unit/Security/SecurityIntegrationTest.php` | Updated short secret strings to 32+ chars |

**Final test run**: 123 tests, 219 assertions, 0 failures, 0 errors.
