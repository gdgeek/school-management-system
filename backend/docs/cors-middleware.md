# CorsMiddleware Documentation

## Overview

The `CorsMiddleware` handles Cross-Origin Resource Sharing (CORS) for the school management system backend. It allows the frontend application (running on `http://localhost:5173`) to communicate with the backend API (running on `http://localhost:8084`).

## Features

- ✅ Handles OPTIONS preflight requests
- ✅ Adds CORS headers to all responses
- ✅ Supports multiple allowed origins
- ✅ Supports wildcard origin patterns
- ✅ Configurable via environment variables or config array
- ✅ Supports credentials (cookies, authorization headers)
- ✅ Configurable max-age for preflight caching

## Configuration

### Environment Variable Configuration

Set the `CORS_ALLOWED_ORIGINS` environment variable in your `.env` file:

```env
# Single origin
CORS_ALLOWED_ORIGINS=http://localhost:5173

# Multiple origins (comma-separated)
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:3000,https://app.example.com

# Allow all origins (not recommended for production)
CORS_ALLOWED_ORIGINS=*

# Wildcard patterns
CORS_ALLOWED_ORIGINS=http://*.example.com,https://*.example.com
```

### Programmatic Configuration

Configure the middleware in `config/middleware.php`:

```php
'cors' => [
    'origins' => ['http://localhost:5173', 'http://localhost:3000'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
    'headers' => ['Authorization', 'Content-Type', 'X-Requested-With', 'Accept'],
    'credentials' => true,
    'maxAge' => 86400, // 24 hours
],
```

### DI Container Registration

The middleware is registered in `config/di.php`:

```php
CorsMiddleware::class => static function (ContainerInterface $container) {
    $config = require __DIR__ . '/middleware.php';
    return new CorsMiddleware(
        $container->get(ResponseFactoryInterface::class),
        $config['cors'] ?? null
    );
},
```

## How It Works

### Preflight Requests (OPTIONS)

When the browser sends a preflight request:

1. Request: `OPTIONS /api/schools`
2. Headers: `Origin: http://localhost:5173`
3. Middleware returns `204 No Content` with CORS headers
4. Browser caches the response for `maxAge` seconds

### Actual Requests

When the browser sends an actual request:

1. Request: `POST /api/schools`
2. Headers: `Origin: http://localhost:5173`
3. Middleware passes request to next handler
4. Middleware adds CORS headers to the response
5. Browser receives response with CORS headers

## CORS Headers

The middleware adds the following headers to responses:

- **Access-Control-Allow-Origin**: The allowed origin (echoes the request origin if allowed)
- **Access-Control-Allow-Methods**: Allowed HTTP methods
- **Access-Control-Allow-Headers**: Allowed request headers
- **Access-Control-Allow-Credentials**: Whether credentials are allowed (true/false)
- **Access-Control-Max-Age**: How long the preflight response can be cached (in seconds)

## Usage in Middleware Stack

The CorsMiddleware should be the **first** middleware in the stack to ensure CORS headers are added to all responses, including error responses:

```php
// config/middleware.php
'global' => [
    CorsMiddleware::class,      // First: Handle CORS
    SecurityMiddleware::class,  // Second: Add security headers
    RouterMiddleware::class,    // Third: Route matching
],
```

## Security Considerations

### Production Configuration

For production, **never use `*` for allowed origins**. Always specify exact origins:

```env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
```

### Credentials

When `credentials: true`, the browser will send cookies and authorization headers with cross-origin requests. This requires:

1. `Access-Control-Allow-Credentials: true` header
2. Specific origin (cannot use `*`)
3. HTTPS in production (enforced by browsers)

### Wildcard Patterns

Wildcard patterns like `http://*.example.com` are useful for subdomains but should be used carefully:

- ✅ Good: `https://*.example.com` (your subdomains)
- ❌ Bad: `http://*` (allows any HTTP origin)

## Testing

### Unit Tests

Run unit tests for CorsMiddleware:

```bash
./vendor/bin/phpunit tests/Unit/Middleware/CorsMiddlewareTest.php
```

### Integration Tests

Run integration tests:

```bash
./vendor/bin/phpunit tests/Integration/Middleware/CorsMiddlewareIntegrationTest.php
```

### Manual Testing

Test CORS with curl:

```bash
# Preflight request
curl -X OPTIONS http://localhost:8084/api/schools \
  -H "Origin: http://localhost:5173" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Authorization, Content-Type" \
  -v

# Actual request
curl -X GET http://localhost:8084/api/schools \
  -H "Origin: http://localhost:5173" \
  -v
```

## Troubleshooting

### CORS Error: "No 'Access-Control-Allow-Origin' header"

**Cause**: The request origin is not in the allowed origins list.

**Solution**: Add the origin to `CORS_ALLOWED_ORIGINS` in `.env`:

```env
CORS_ALLOWED_ORIGINS=http://localhost:5173
```

### CORS Error: "Credentials flag is true, but Access-Control-Allow-Credentials is not"

**Cause**: The middleware is configured with `credentials: false` but the frontend is sending credentials.

**Solution**: Set `credentials: true` in the middleware configuration.

### Preflight Request Returns 404

**Cause**: The RouterMiddleware is handling OPTIONS requests before CorsMiddleware.

**Solution**: Ensure CorsMiddleware is **before** RouterMiddleware in the middleware stack.

### CORS Headers Not Present on Error Responses

**Cause**: An exception is thrown before the middleware can add headers.

**Solution**: Ensure CorsMiddleware is the **first** middleware in the stack so it wraps all other middleware.

## Examples

### Example 1: Frontend Login Request

```javascript
// Frontend (http://localhost:5173)
fetch('http://localhost:8084/api/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ username: 'guanfei', password: '123456' }),
  credentials: 'include', // Send cookies
})
```

The browser will:
1. Send preflight: `OPTIONS /api/auth/login`
2. Receive: `204` with CORS headers
3. Send actual: `POST /api/auth/login`
4. Receive: `200` with CORS headers and response data

### Example 2: Authenticated Request

```javascript
// Frontend (http://localhost:5173)
fetch('http://localhost:8084/api/schools', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer eyJ0eXAiOiJKV1QiLCJhbGc...',
  },
  credentials: 'include',
})
```

The middleware will:
1. Check origin: `http://localhost:5173` ✅
2. Add CORS headers to response
3. Allow credentials (cookies + auth header)

## References

- [MDN: CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [PSR-15: HTTP Server Request Handlers](https://www.php-fig.org/psr/psr-15/)
- [PSR-7: HTTP Message Interface](https://www.php-fig.org/psr/psr-7/)
