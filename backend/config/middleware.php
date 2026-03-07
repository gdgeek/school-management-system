<?php

declare(strict_types=1);

/**
 * Middleware Configuration
 * 
 * Defines middleware execution order and route-specific middleware mappings.
 * 
 * Middleware Execution Order:
 * 1. Global middleware (applied to all routes)
 * 2. RouterMiddleware (route matching and dispatching)
 * 3. Route-specific middleware (from 'groups' or 'routes' configuration)
 * 4. Controller action
 * 
 * Configuration Structure:
 * - global: Middleware applied to all routes
 * - groups: Named middleware groups that can be referenced in routes
 * - routes: Route pattern to middleware group mapping
 * - cors: CORS configuration options
 * - security: Security middleware configuration options
 */

use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\DebugMiddleware;
use App\Middleware\ProfilingMiddleware;
use App\Middleware\RequestLoggingMiddleware;
use App\Middleware\SecurityMiddleware;
use App\Middleware\VerboseLoggingMiddleware;

return [
    // ========================================
    // Global Middleware (Applied to All Routes)
    // ========================================
    // These middleware execute for every request in the order specified.
    // They run before route matching and controller execution.
    // ProfilingMiddleware is prepended in development or when PROFILING_ENABLED=true.
    
    'global' => array_filter([
        \App\Middleware\ErrorHandlingMiddleware::class, // Catch unhandled exceptions → 500 + alert
        (($_ENV['APP_ENV'] ?? 'production') === 'development' || ($_ENV['PROFILING_ENABLED'] ?? 'false') === 'true')
            ? ProfilingMiddleware::class
            : null,
        CorsMiddleware::class,              // Handle CORS preflight and add CORS headers
        \App\Middleware\RequestValidationMiddleware::class, // Validate Content-Type, body size, JSON structure
        RequestLoggingMiddleware::class,    // Log request/response details
        // VerboseLoggingMiddleware: dev-only full request/response logger (no-op in production)
        (($_ENV['APP_ENV'] ?? 'production') === 'development' || ($_ENV['APP_DEBUG'] ?? 'false') === 'true')
            ? VerboseLoggingMiddleware::class
            : null,
        \App\Middleware\MetricsMiddleware::class, // Collect per-endpoint performance metrics
        SecurityMiddleware::class,          // Add security headers to all responses
        // DebugMiddleware is last so it can read attributes/headers set by all earlier middleware.
        // It is a no-op unless APP_DEBUG=true or APP_ENV=development.
        (($_ENV['APP_ENV'] ?? 'production') === 'development' || ($_ENV['APP_DEBUG'] ?? 'false') === 'true')
            ? DebugMiddleware::class
            : null,
    ]),
    
    // ========================================
    // Named Middleware Groups
    // ========================================
    // Define reusable middleware groups that can be applied to routes.
    // Groups can be referenced in route configuration or in the 'routes' mapping below.
    
    'groups' => [
        // Authentication group - requires valid JWT token
        'auth' => [
            AuthMiddleware::class,
        ],
        
        // API group - standard API middleware stack
        'api' => [
            CorsMiddleware::class,
            SecurityMiddleware::class,
        ],
    ],
    
    // ========================================
    // Route-Specific Middleware Mapping
    // ========================================
    // Map route patterns to middleware groups.
    // Supports wildcards: 'schools.*' matches all routes starting with 'schools.'
    // Middleware groups are applied in addition to global middleware.
    
    'routes' => [
        // Authentication routes
        'auth.user' => ['auth'],  // GET /api/auth/user requires authentication
        
        // School management routes (all require authentication)
        'schools.*' => ['auth'],
        
        // Class management routes (all require authentication)
        'classes.*' => ['auth'],
        
        // Group management routes (all require authentication)
        'groups.*' => ['auth'],
        
        // Student management routes (all require authentication)
        'students.*' => ['auth'],
        
        // Teacher management routes (all require authentication)
        'teachers.*' => ['auth'],
        
        // User search routes (require authentication)
        'users.*' => ['auth'],
    ],
    
    // ========================================
    // CORS Configuration
    // ========================================
    // Configuration options for CorsMiddleware.
    // These settings control cross-origin resource sharing behavior.
    //
    // SECURITY NOTES:
    // - In production, set CORS_ALLOWED_ORIGINS to the exact frontend origin(s).
    //   Never use '*' in production — it allows any website to read API responses.
    // - When credentials=true, the browser requires a specific origin (not '*').
    //   The middleware automatically echoes the request Origin when credentials
    //   are enabled, even if the allowlist contains '*'.
    // - The Vary: Origin header is always added so CDN/proxy caches do not serve
    //   the wrong CORS headers to different origins.
    
    'cors' => [
        // Allowed origins.
        // Reads from CORS_ALLOWED_ORIGINS env var (comma-separated list).
        // Default: localhost:5173 (Vue dev server) — safe for development.
        // Production example: CORS_ALLOWED_ORIGINS=https://app.example.com
        'origins' => array_values(array_filter(
            array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:5173'))
        )),

        // Allowed HTTP methods
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],

        // Allowed request headers (sent by the browser in Access-Control-Request-Headers)
        'headers' => [
            'Authorization',
            'Content-Type',
            'X-Requested-With',
            'Accept',
            'Origin',
        ],

        // Response headers the browser JS is allowed to read.
        // Add custom headers here if the frontend needs to access them.
        'expose' => [],

        // Allow credentials (cookies, Authorization header, TLS client certs).
        // Requires a specific origin — cannot be combined with wildcard '*'.
        'credentials' => true,

        // How long (seconds) the browser may cache a preflight response.
        // 86400 = 24 hours. Set to 0 to disable preflight caching.
        'maxAge' => 86400,
    ],
    
    // ========================================
    // Security Configuration
    // ========================================
    // Configuration options for SecurityMiddleware.
    // These settings control security headers and policies.
    
    'security' => [
        // Enable Content Security Policy
        'enableCsp' => true,
        
        // Content Security Policy directives
        // Tuned for a Vue.js SPA frontend consuming this API
        'cspPolicy' => implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",                // Vue.js requires inline scripts
            "style-src 'self' 'unsafe-inline'",                 // Allow inline styles
            "img-src 'self' data: https:",                      // Allow images from self, data URIs, and HTTPS
            "font-src 'self' data:",                            // Allow fonts from self and data URIs
            "connect-src 'self' http://localhost:8084 http://localhost:8082 ws://localhost:5173",  // Allow API + HMR
            "frame-ancestors 'none'",                           // Stricter than SAMEORIGIN — no framing at all for API
            "base-uri 'self'",                                  // Restrict base tag URLs
            "form-action 'self'",                               // Restrict form submissions
            "object-src 'none'",                                // Block plugins (Flash, etc.)
            "upgrade-insecure-requests",                        // Upgrade HTTP to HTTPS automatically
        ]),
        
        // Security headers applied by SecurityMiddleware:
        // - X-Frame-Options: SAMEORIGIN
        // - X-Content-Type-Options: nosniff
        // - X-XSS-Protection: 0  (deprecated; CSP is the modern replacement)
        // - Referrer-Policy: strict-origin-when-cross-origin
        // - Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()
        // - Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
        // - Cross-Origin-Opener-Policy: same-origin
        // - Cross-Origin-Resource-Policy: cross-origin  (API — allow cross-origin reads)
        // - X-Powered-By: removed
    ],
    
    // ========================================
    // Middleware Execution Notes
    // ========================================
    // 
    // Request Flow:
    // 1. CorsMiddleware checks origin and handles preflight requests
    // 2. SecurityMiddleware adds security headers
    // 3. RouterMiddleware matches route and extracts parameters
    // 4. Route-specific middleware (e.g., AuthMiddleware for protected routes)
    // 5. Controller action executes
    // 
    // Response Flow (reverse order):
    // 1. Controller returns PSR-7 Response
    // 2. Route-specific middleware can modify response
    // 3. RouterMiddleware passes response through
    // 4. SecurityMiddleware adds security headers
    // 5. CorsMiddleware adds CORS headers
    // 6. Response is emitted to client
    // 
    // Adding New Middleware:
    // 1. Create middleware class implementing PSR-15 MiddlewareInterface
    // 2. Add to 'global' array for all routes, or
    // 3. Add to 'groups' array and reference in 'routes' mapping
    // 4. Register middleware factory in config/di.php if it has dependencies
    // 
    // Middleware Best Practices:
    // - Keep middleware focused on a single responsibility
    // - Avoid heavy processing in middleware (use services instead)
    // - Always call $handler->handle($request) to continue the chain
    // - Return PSR-7 ResponseInterface from process() method
    // - Use request attributes to pass data between middleware and controllers
];

