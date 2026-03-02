<?php

declare(strict_types=1);

use Yiisoft\Config\Config;
use Yiisoft\Di\Container;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

// Define application root directory
define('ROOT_DIR', dirname(__DIR__));

// Load Composer autoloader
require_once ROOT_DIR . '/vendor/autoload.php';

// Load environment variables
if (file_exists(ROOT_DIR . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(ROOT_DIR);
    $dotenv->load();
}

// Create configuration
$config = new Config(ROOT_DIR . '/config');

// Create DI container
$container = new Container($config->get('di'));

// Run application
$runner = $container->get(HttpApplicationRunner::class);
$runner->run();
