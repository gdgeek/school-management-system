<?php

declare(strict_types=1);

use App\Application;
use App\Controller\HealthController;
use App\Helper\Logger;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\ProfilingMiddleware;
use App\Middleware\RouterMiddleware;
use App\Middleware\SecurityMiddleware;
use App\Helper\JwtHelper;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Mysql\Connection as MysqlConnection;
use Yiisoft\Db\Mysql\Driver as MysqlDriver;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Cache\ArrayCache;

return [
    // Database Connection (Singleton)
    ConnectionInterface::class => static function (ContainerInterface $container) {
        static $connection = null;
        
        if ($connection === null) {
            $driver = new MysqlDriver(
                'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . 
                ';port=' . ($_ENV['DB_PORT'] ?? '3306') . 
                ';dbname=' . ($_ENV['DB_NAME'] ?? 'bujiaban'),
                $_ENV['DB_USER'] ?? 'root',
                $_ENV['DB_PASSWORD'] ?? ''
            );
            
            // Create schema cache with array cache (no external cache dependency)
            $schemaCache = new SchemaCache(new ArrayCache());
            
            $connection = new MysqlConnection($driver, $schemaCache);
        }
        
        return $connection;
    },
    
    // PSR-17 HTTP Factories (using Nyholm PSR-7)
    // Psr17Factory implements all PSR-17 interfaces — share a single instance
    Psr17Factory::class => static function () {
        return new Psr17Factory();
    },

    ResponseFactoryInterface::class => static function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    
    ServerRequestFactoryInterface::class => static function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    
    StreamFactoryInterface::class => static function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    
    UriFactoryInterface::class => static function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    
    UploadedFileFactoryInterface::class => static function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },
    
    // Logger (Singleton)
    Logger::class => static function () {
        $logPath = dirname(__DIR__) . '/runtime/logs';
        return new Logger($logPath, 'debug');
    },

    // DatabaseHelper (Singleton — wraps the shared PDO connection)
    \App\Helper\DatabaseHelper::class => static function (ContainerInterface $container) {
        return new \App\Helper\DatabaseHelper($container->get(\PDO::class));
    },

    // Middleware Configuration
    CorsMiddleware::class => static function (ContainerInterface $container) {
        static $config = null;
        $config ??= require __DIR__ . '/middleware.php';
        return new CorsMiddleware(
            $container->get(ResponseFactoryInterface::class),
            $config['cors'] ?? null
        );
    },
    
    SecurityMiddleware::class => static function (ContainerInterface $container) {
        static $config = null;
        $config ??= require __DIR__ . '/middleware.php';
        return new SecurityMiddleware($config['security'] ?? null);
    },
    
    // JWT Helper (Singleton)
    JwtHelper::class => static function () {
        return new JwtHelper(
            $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-in-production',
            (int)($_ENV['JWT_EXPIRE_TIME'] ?? 3600)
        );
    },
    
    // PSR-15 Middleware
    AuthMiddleware::class => static function (ContainerInterface $container) {
        return new AuthMiddleware(
            $container->get(JwtHelper::class),
            $container->get(ResponseFactoryInterface::class)
        );
    },
    
    RouterMiddleware::class => static function (ContainerInterface $container) {
        return new RouterMiddleware(
            $container,
            $container->get(ResponseFactoryInterface::class)
        );
    },

    ProfilingMiddleware::class => static function (ContainerInterface $container) {
        return new ProfilingMiddleware($container->get(Logger::class));
    },

    \App\Middleware\RequestLoggingMiddleware::class => static function (ContainerInterface $container) {
        return new \App\Middleware\RequestLoggingMiddleware($container->get(Logger::class));
    },

    \App\Middleware\ResponseCacheMiddleware::class => static function (ContainerInterface $container) {
        return new \App\Middleware\ResponseCacheMiddleware(
            $container->get(\App\Contract\RedisInterface::class),
            $container->get(ResponseFactoryInterface::class),
            $container->get(StreamFactoryInterface::class),
            (int)($_ENV['RESPONSE_CACHE_TTL'] ?? 60)
        );
    },
    
    // Application
    Application::class => static function (ContainerInterface $container) {
        return new Application($container);
    },
    
    // Controllers
    HealthController::class => static function (ContainerInterface $container) {
        $responseHelper = new \App\Helper\ResponseHelper(
            $container->get(ResponseFactoryInterface::class)
        );

        // Redis is optional — HealthController accepts mixed $redis = null
        $redis = null;
        if (extension_loaded('redis')) {
            try {
                $r = new \Redis();
                $r->connect($_ENV['REDIS_HOST'] ?? 'localhost', (int)($_ENV['REDIS_PORT'] ?? 6379));
                $redis = $r;
            } catch (\Exception) {
                // Redis unavailable — detailed health check will report it
            }
        }

        return new HealthController(
            $responseHelper,
            $container->get(\App\Helper\DatabaseHelper::class),
            $redis
        );
    },
    
    // Redis (Singleton) — returns RedisInterface implementation
    \App\Contract\RedisInterface::class => static function () {
        static $instance = null;

        if ($instance === null) {
            // Stub used when the redis PHP extension is not loaded (e.g. local dev / CI)
            $stub = new class implements \App\Contract\RedisInterface {
                private array $store = [];
                public function get(string $key): mixed { return $this->store[$key] ?? false; }
                public function set(string $key, mixed $value, mixed $options = null): mixed { $this->store[$key] = $value; return true; }
                public function setex(string $key, int $ttl, mixed $value): mixed { $this->store[$key] = $value; return true; }
                public function del(string ...$keys): int { $n = 0; foreach ($keys as $k) { if (isset($this->store[$k])) { unset($this->store[$k]); $n++; } } return $n; }
                public function expire(string $key, int $ttl): bool { return true; }
                public function exists(string ...$keys): int { $n = 0; foreach ($keys as $k) { if (isset($this->store[$k])) $n++; } return $n; }
                public function incrBy(string $key, int $by = 1): int { $this->store[$key] = (int)($this->store[$key] ?? 0) + $by; return $this->store[$key]; }
                public function incrByFloat(string $key, float $by): float { $this->store[$key] = (float)($this->store[$key] ?? 0) + $by; return $this->store[$key]; }
                public function keys(string $pattern): array { $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/'; return array_values(array_filter(array_keys($this->store), fn($k) => preg_match($regex, $k))); }
            };

            if (!extension_loaded('redis')) {
                return $stub;
            }

            $real = new \Redis();
            try {
                $real->connect($_ENV['REDIS_HOST'] ?? 'localhost', (int)($_ENV['REDIS_PORT'] ?? 6379));
                // Wrap the real Redis in an adapter that satisfies RedisInterface
                $instance = new class($real) implements \App\Contract\RedisInterface {
                    public function __construct(private \Redis $r) {}
                    public function get(string $key): mixed { return $this->r->get($key); }
                    public function set(string $key, mixed $value, mixed $options = null): mixed { return $this->r->set($key, $value, $options); }
                    public function setex(string $key, int $ttl, mixed $value): mixed { return $this->r->setex($key, $ttl, $value); }
                    public function del(string ...$keys): int { return $this->r->del(...$keys); }
                    public function expire(string $key, int $ttl): bool { return $this->r->expire($key, $ttl); }
                    public function exists(string ...$keys): int { return $this->r->exists(...$keys); }
                    public function incrBy(string $key, int $by = 1): int { return (int)$this->r->incrBy($key, $by); }
                    public function incrByFloat(string $key, float $by): float { return (float)$this->r->incrByFloat($key, $by); }
                    public function keys(string $pattern): array { $result = $this->r->keys($pattern); return is_array($result) ? $result : []; }
                };
            } catch (\Exception $e) {
                return $stub;
            }
        }

        return $instance;
    },

    // Keep \Redis::class alias for any legacy references
    \Redis::class => static function (\Psr\Container\ContainerInterface $c) {
        return $c->get(\App\Contract\RedisInterface::class);
    },
    
    // PDO (Singleton)
    \PDO::class => static function () {
        static $pdo = null;
        
        if ($pdo === null) {
            $pdo = new \PDO(
                'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . 
                ';port=' . ($_ENV['DB_PORT'] ?? '3306') . 
                ';dbname=' . ($_ENV['DB_NAME'] ?? 'bujiaban'),
                $_ENV['DB_USER'] ?? 'root',
                $_ENV['DB_PASSWORD'] ?? '',
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
        
        return $pdo;
    },
    
    // Repositories
    \App\Repository\UserRepository::class => static function (ContainerInterface $container) {
        return new \App\Repository\UserRepository(
            $container->get(\App\Helper\DatabaseHelper::class)
        );
    },
    
    \App\Repository\SchoolRepository::class => static function (ContainerInterface $container) {
        return new \App\Repository\SchoolRepository($container->get(\PDO::class));
    },
    
    \App\Repository\ClassRepository::class => static function (ContainerInterface $container) {
        return new \App\Repository\ClassRepository($container->get(\PDO::class));
    },
    
    \App\Repository\TeacherRepository::class => static function (ContainerInterface $container) {
        return new \App\Repository\TeacherRepository($container->get(\PDO::class));
    },
    
    \App\Repository\StudentRepository::class => static function (ContainerInterface $container) {
        return new \App\Repository\StudentRepository($container->get(\PDO::class));
    },
    
    \App\Repository\GroupRepository::class => static function (ContainerInterface $container) {
        return new \App\Repository\GroupRepository($container->get(\PDO::class));
    },
    
    \App\Repository\ClassGroupRepository::class => static function (ContainerInterface $container) {
        return new \App\Repository\ClassGroupRepository($container->get(\PDO::class));
    },
    
    // Services
    \App\Service\AuthService::class => static function (ContainerInterface $container) {
        return new \App\Service\AuthService(
            $container->get(\App\Repository\UserRepository::class),
            $container->get(\App\Contract\RedisInterface::class)
        );
    },
    
    \App\Service\SchoolService::class => static function (ContainerInterface $container) {
        return new \App\Service\SchoolService(
            $container->get(\App\Repository\SchoolRepository::class),
            $container->get(\App\Repository\ClassRepository::class),
            $container->get(\App\Repository\TeacherRepository::class),
            $container->get(\App\Repository\StudentRepository::class),
            $container->get(\App\Helper\DatabaseHelper::class)
        );
    },
    
    // Auth Controller
    \App\Controller\AuthController::class => static function (ContainerInterface $container) {
        return new \App\Controller\AuthController(
            $container->get(\App\Service\AuthService::class),
            $container->get(JwtHelper::class),
            $container->get(ResponseFactoryInterface::class)
        );
    },
    
    // School Controller
    \App\Controller\SchoolController::class => static function (ContainerInterface $container) {
        return new \App\Controller\SchoolController(
            $container->get(ResponseFactoryInterface::class),
            $container->get(\App\Service\SchoolService::class)
        );
    },

    // Class Service
    \App\Service\ClassService::class => static function (ContainerInterface $container) {
        return new \App\Service\ClassService(
            $container->get(\App\Repository\ClassRepository::class),
            $container->get(\App\Repository\SchoolRepository::class),
            $container->get(\App\Repository\TeacherRepository::class),
            $container->get(\App\Repository\StudentRepository::class),
            $container->get(\App\Repository\GroupRepository::class),
            $container->get(\App\Repository\ClassGroupRepository::class),
            $container->get(\App\Helper\DatabaseHelper::class)
        );
    },

    // Class Controller
    \App\Controller\ClassController::class => static function (ContainerInterface $container) {
        return new \App\Controller\ClassController(
            $container->get(ResponseFactoryInterface::class),
            $container->get(\App\Service\ClassService::class)
        );
    },

    // Group User Repository
    \App\Repository\GroupUserRepository::class => static function (ContainerInterface $container) {
        return new \App\Repository\GroupUserRepository($container->get(\PDO::class));
    },

    // Group Service
    \App\Service\GroupService::class => static function (ContainerInterface $container) {
        return new \App\Service\GroupService(
            $container->get(\App\Repository\GroupRepository::class),
            $container->get(\App\Repository\GroupUserRepository::class),
            $container->get(\App\Repository\ClassGroupRepository::class),
            $container->get(\App\Repository\UserRepository::class),
            $container->get(\App\Repository\ClassRepository::class),
            $container->get(\App\Repository\TeacherRepository::class),
            $container->get(\App\Repository\StudentRepository::class),
            $container->get(\App\Helper\DatabaseHelper::class)
        );
    },

    // Group Controller
    \App\Controller\GroupController::class => static function (ContainerInterface $container) {
        return new \App\Controller\GroupController(
            $container->get(ResponseFactoryInterface::class),
            $container->get(\App\Service\GroupService::class)
        );
    },

    // Student Service
    \App\Service\StudentService::class => static function (ContainerInterface $container) {
        return new \App\Service\StudentService(
            $container->get(\App\Repository\StudentRepository::class),
            $container->get(\App\Repository\ClassRepository::class),
            $container->get(\App\Repository\UserRepository::class),
            $container->get(\App\Repository\SchoolRepository::class),
            $container->get(\App\Repository\ClassGroupRepository::class),
            $container->get(\App\Repository\GroupUserRepository::class),
            $container->get(\App\Repository\GroupRepository::class),
            $container->get(\App\Helper\DatabaseHelper::class)
        );
    },

    // Student Controller
    \App\Controller\StudentController::class => static function (ContainerInterface $container) {
        return new \App\Controller\StudentController(
            $container->get(ResponseFactoryInterface::class),
            $container->get(\App\Service\StudentService::class)
        );
    },

    // Teacher Service
    \App\Service\TeacherService::class => static function (ContainerInterface $container) {
        return new \App\Service\TeacherService(
            $container->get(\App\Repository\TeacherRepository::class),
            $container->get(\App\Repository\ClassRepository::class),
            $container->get(\App\Repository\UserRepository::class),
            $container->get(\App\Repository\SchoolRepository::class)
        );
    },

    // Teacher Controller
    \App\Controller\TeacherController::class => static function (ContainerInterface $container) {
        return new \App\Controller\TeacherController(
            $container->get(ResponseFactoryInterface::class),
            $container->get(\App\Service\TeacherService::class)
        );
    },

    // User Controller
    \App\Controller\UserController::class => static function (ContainerInterface $container) {
        return new \App\Controller\UserController(
            $container->get(ResponseFactoryInterface::class),
            $container->get(\App\Repository\UserRepository::class)
        );
    },

    // MetricsCollector (Singleton)
    \App\Service\MetricsCollector::class => static function (ContainerInterface $container) {
        return new \App\Service\MetricsCollector(
            $container->get(\App\Contract\RedisInterface::class)
        );
    },

    // MetricsMiddleware
    \App\Middleware\MetricsMiddleware::class => static function (ContainerInterface $container) {
        return new \App\Middleware\MetricsMiddleware(
            $container->get(\App\Service\MetricsCollector::class)
        );
    },

    // MetricsController
    \App\Controller\MetricsController::class => static function (ContainerInterface $container) {
        return new \App\Controller\MetricsController(
            $container->get(ResponseFactoryInterface::class),
            $container->get(\App\Service\MetricsCollector::class),
            $container->get(\App\Service\ErrorTracker::class)
        );
    },

    // ErrorTracker (Singleton)
    \App\Service\ErrorTracker::class => static function (ContainerInterface $container) {
        return new \App\Service\ErrorTracker(
            $container->get(\App\Contract\RedisInterface::class),
            $container->get(Logger::class)
        );
    },

    // AlertController
    \App\Controller\AlertController::class => static function (ContainerInterface $container) {
        return new \App\Controller\AlertController(
            $container->get(ResponseFactoryInterface::class),
            $container->get(\App\Service\ErrorTracker::class)
        );
    },

    // ErrorHandlingMiddleware
    \App\Middleware\ErrorHandlingMiddleware::class => static function (ContainerInterface $container) {
        return new \App\Middleware\ErrorHandlingMiddleware(
            $container->get(\App\Service\ErrorTracker::class),
            $container->get(ResponseFactoryInterface::class)
        );
    },

    // RequestValidationMiddleware
    \App\Middleware\RequestValidationMiddleware::class => static function (ContainerInterface $container) {
        return new \App\Middleware\RequestValidationMiddleware(
            $container->get(ResponseFactoryInterface::class)
        );
    },

    // DebugMiddleware — only active when APP_DEBUG=true or APP_ENV=development
    \App\Middleware\DebugMiddleware::class => static function () {
        return new \App\Middleware\DebugMiddleware();
    },

    // VerboseLoggingMiddleware — dev-only full request/response logger
    \App\Middleware\VerboseLoggingMiddleware::class => static function (ContainerInterface $container) {
        return new \App\Middleware\VerboseLoggingMiddleware($container->get(Logger::class));
    },

    // RateLimitMiddleware — general limit (100 req/min by default)
    \App\Middleware\RateLimitMiddleware::class => static function (ContainerInterface $container) {
        return new \App\Middleware\RateLimitMiddleware(
            $container->get(\App\Contract\RedisInterface::class),
            $container->get(ResponseFactoryInterface::class)
        );
    },

    // LoginRateLimitMiddleware — stricter limit for the login endpoint (10 req/min)
    'LoginRateLimitMiddleware' => static function (ContainerInterface $container) {
        return new \App\Middleware\RateLimitMiddleware(
            $container->get(\App\Contract\RedisInterface::class),
            $container->get(ResponseFactoryInterface::class),
            [
                'max_requests'   => (int)($_ENV['RATE_LIMIT_LOGIN_REQUESTS'] ?? 10),
                'window_seconds' => (int)($_ENV['RATE_LIMIT_WINDOW']         ?? 60),
            ]
        );
    },
];
