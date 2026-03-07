<?php

declare(strict_types=1);

/**
 * Router Configuration
 * 
 * Defines all application routes for the PSR-15 middleware stack.
 * 
 * Route Format:
 * [
 *     'name' => 'route.name',           // Unique route identifier
 *     'pattern' => '/api/path',         // URL pattern (supports FastRoute syntax)
 *     'methods' => ['GET', 'POST'],     // HTTP methods
 *     'handler' => Controller::class . '::method',  // Controller action
 *     'middleware' => [Middleware::class],          // Route-specific middleware
 * ]
 * 
 * Route Pattern Syntax:
 * - Static: /api/health
 * - Dynamic: /api/schools/{id}
 * - With constraints: /api/schools/{id:\d+}
 * - Optional segments: /api/users[/{id}]
 */

use App\Controller\HealthController;

return [
    // ========================================
    // Health Check Routes (Public)
    // ========================================
    
    [
        'name' => 'health.index',
        'pattern' => '/api/health',
        'methods' => ['GET'],
        'handler' => HealthController::class . '::index',
        // ResponseCacheMiddleware applied here as an example of optional per-route caching.
        // The health endpoint is public (no Authorization header) and idempotent, making it
        // a good candidate. TTL is controlled by the RESPONSE_CACHE_TTL env var (default 60s).
        'middleware' => [\App\Middleware\ResponseCacheMiddleware::class],
    ],
    
    [
        'name' => 'health.detailed',
        'pattern' => '/api/health/detailed',
        'methods' => ['GET'],
        'handler' => HealthController::class . '::detailed',
        'middleware' => [],
    ],
    
    [
        'name' => 'health.version',
        'pattern' => '/api/version',
        'methods' => ['GET'],
        'handler' => HealthController::class . '::version',
        'middleware' => [],
    ],
    
    // ========================================
    // Authentication Routes (Public)
    // ========================================
    
    [
        'name' => 'auth.login',
        'pattern' => '/api/auth/login',
        'methods' => ['POST'],
        'handler' => \App\Controller\AuthController::class . '::login',
        // Stricter rate limit for login: 10 req/min (brute-force protection)
        'middleware' => ['LoginRateLimitMiddleware'],
    ],
    
    [
        'name' => 'auth.user',
        'pattern' => '/api/auth/user',
        'methods' => ['GET'],
        'handler' => \App\Controller\AuthController::class . '::user',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    // ========================================
    // School Management Routes (Protected)
    // ========================================
    
    [
        'name' => 'schools.list',
        'pattern' => '/api/schools',
        'methods' => ['GET'],
        'handler' => \App\Controller\SchoolController::class . '::index',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    [
        'name' => 'schools.show',
        'pattern' => '/api/schools/{id:\d+}',
        'methods' => ['GET'],
        'handler' => \App\Controller\SchoolController::class . '::show',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    [
        'name' => 'schools.create',
        'pattern' => '/api/schools',
        'methods' => ['POST'],
        'handler' => \App\Controller\SchoolController::class . '::create',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    [
        'name' => 'schools.update',
        'pattern' => '/api/schools/{id:\d+}',
        'methods' => ['PUT'],
        'handler' => \App\Controller\SchoolController::class . '::update',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    [
        'name' => 'schools.delete',
        'pattern' => '/api/schools/{id:\d+}',
        'methods' => ['DELETE'],
        'handler' => \App\Controller\SchoolController::class . '::delete',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    // ========================================
    // Class Management Routes (Protected)
    // ========================================
    
    [
        'name' => 'classes.list',
        'pattern' => '/api/classes',
        'methods' => ['GET'],
        'handler' => \App\Controller\ClassController::class . '::index',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    [
        'name' => 'classes.show',
        'pattern' => '/api/classes/{id:\d+}',
        'methods' => ['GET'],
        'handler' => \App\Controller\ClassController::class . '::show',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    [
        'name' => 'classes.create',
        'pattern' => '/api/classes',
        'methods' => ['POST'],
        'handler' => \App\Controller\ClassController::class . '::create',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    [
        'name' => 'classes.update',
        'pattern' => '/api/classes/{id:\d+}',
        'methods' => ['PUT'],
        'handler' => \App\Controller\ClassController::class . '::update',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    [
        'name' => 'classes.delete',
        'pattern' => '/api/classes/{id:\d+}',
        'methods' => ['DELETE'],
        'handler' => \App\Controller\ClassController::class . '::delete',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],
    
    // ========================================
    // Group Management Routes (Protected)
    // ========================================

    [
        'name' => 'groups.list',
        'pattern' => '/api/groups',
        'methods' => ['GET'],
        'handler' => \App\Controller\GroupController::class . '::index',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'groups.show',
        'pattern' => '/api/groups/{id:\d+}',
        'methods' => ['GET'],
        'handler' => \App\Controller\GroupController::class . '::show',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'groups.create',
        'pattern' => '/api/groups',
        'methods' => ['POST'],
        'handler' => \App\Controller\GroupController::class . '::create',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'groups.update',
        'pattern' => '/api/groups/{id:\d+}',
        'methods' => ['PUT'],
        'handler' => \App\Controller\GroupController::class . '::update',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'groups.delete',
        'pattern' => '/api/groups/{id:\d+}',
        'methods' => ['DELETE'],
        'handler' => \App\Controller\GroupController::class . '::delete',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'groups.addMember',
        'pattern' => '/api/groups/{id:\d+}/members',
        'methods' => ['POST'],
        'handler' => \App\Controller\GroupController::class . '::addMember',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'groups.removeMember',
        'pattern' => '/api/groups/{id:\d+}/members/{userId:\d+}',
        'methods' => ['DELETE'],
        'handler' => \App\Controller\GroupController::class . '::removeMember',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'groups.addClass',
        'pattern' => '/api/groups/{id:\d+}/classes',
        'methods' => ['POST'],
        'handler' => \App\Controller\GroupController::class . '::addClass',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'groups.removeClass',
        'pattern' => '/api/groups/{id:\d+}/classes/{classId:\d+}',
        'methods' => ['DELETE'],
        'handler' => \App\Controller\GroupController::class . '::removeClass',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    // ========================================
    // Student Management Routes (Protected)
    // ========================================

    [
        'name' => 'students.list',
        'pattern' => '/api/students',
        'methods' => ['GET'],
        'handler' => \App\Controller\StudentController::class . '::index',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'students.show',
        'pattern' => '/api/students/{id:\d+}',
        'methods' => ['GET'],
        'handler' => \App\Controller\StudentController::class . '::show',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'students.create',
        'pattern' => '/api/students',
        'methods' => ['POST'],
        'handler' => \App\Controller\StudentController::class . '::create',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'students.delete',
        'pattern' => '/api/students/{id:\d+}',
        'methods' => ['DELETE'],
        'handler' => \App\Controller\StudentController::class . '::delete',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    // ========================================
    // Teacher Management Routes (Protected)
    // ========================================

    [
        'name' => 'teachers.list',
        'pattern' => '/api/teachers',
        'methods' => ['GET'],
        'handler' => \App\Controller\TeacherController::class . '::index',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'teachers.show',
        'pattern' => '/api/teachers/{id:\d+}',
        'methods' => ['GET'],
        'handler' => \App\Controller\TeacherController::class . '::show',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'teachers.create',
        'pattern' => '/api/teachers',
        'methods' => ['POST'],
        'handler' => \App\Controller\TeacherController::class . '::create',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    [
        'name' => 'teachers.delete',
        'pattern' => '/api/teachers/{id:\d+}',
        'methods' => ['DELETE'],
        'handler' => \App\Controller\TeacherController::class . '::delete',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    // ========================================
    // User Search Routes (Protected)
    // ========================================

    [
        'name' => 'users.search',
        'pattern' => '/api/users/search',
        'methods' => ['GET'],
        'handler' => \App\Controller\UserController::class . '::search',
        'middleware' => [\App\Middleware\AuthMiddleware::class],
    ],

    // ========================================
    // Metrics Routes (Token-protected)
    // ========================================

    [
        'name' => 'metrics.index',
        'pattern' => '/api/metrics',
        'methods' => ['GET'],
        'handler' => \App\Controller\MetricsController::class . '::index',
        'middleware' => [],  // Auth handled inside controller via METRICS_TOKEN
    ],

    [
        'name' => 'metrics.reset',
        'pattern' => '/api/metrics/reset',
        'methods' => ['POST'],
        'handler' => \App\Controller\MetricsController::class . '::reset',
        'middleware' => [],  // Auth handled inside controller via METRICS_TOKEN
    ],

    [
        'name' => 'metrics.dashboard',
        'pattern' => '/api/metrics/dashboard',
        'methods' => ['GET'],
        'handler' => \App\Controller\MetricsController::class . '::dashboard',
        'middleware' => [],  // Auth handled inside controller via METRICS_TOKEN
    ],

    // ========================================
    // Alerts Routes (Token-protected)
    // ========================================

    [
        'name' => 'alerts.status',
        'pattern' => '/api/alerts/status',
        'methods' => ['GET'],
        'handler' => \App\Controller\AlertController::class . '::status',
        'middleware' => [],  // Auth handled inside controller via METRICS_TOKEN
    ],
];
