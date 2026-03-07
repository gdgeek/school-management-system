<?php

declare(strict_types=1);

/**
 * Dependency Injection Container Builder
 * 
 * This file builds and returns a PSR-11 compatible DI container
 * configured with all application services, controllers, middleware,
 * and dependencies.
 * 
 * The container is built using Yii3 DI container with service definitions
 * loaded from config/di.php.
 */

use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

// Load service definitions
$definitions = require __DIR__ . '/di.php';

// Create container configuration
$config = ContainerConfig::create()
    ->withDefinitions($definitions);

// Build and return container
return new Container($config);
