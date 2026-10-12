<?php

declare(strict_types=1);

$file = __DIR__ . '/../../../vendor/autoload.php';

if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies to run the test suite.');
}

require $file;

date_default_timezone_set('UTC');

(new Symfony\Component\Filesystem\Filesystem())->remove(__DIR__ . '/../var/cache');

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';

if (!isset($_SERVER['DATABASE_URL']) && !isset($_ENV['DATABASE_URL']) && false === getenv('DATABASE_URL')) {
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///' . __DIR__ . '/../var/test.db';
}
