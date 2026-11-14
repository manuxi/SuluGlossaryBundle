<?php

declare(strict_types=1);

$file = __DIR__ . '/../../../vendor/autoload.php';

if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies to run the test suite.');
}

require $file;

/*
 * manuxi/sulu-dashboard-bundle is an optional dependency ("suggest") and not part of composer.json. To run the tests
 * of the dashboard tile and PHPStan, point SULU_DASHBOARD_BUNDLE_PATH to a checkout of the bundle:
 *
 *     SULU_DASHBOARD_BUNDLE_PATH=/path/to/SuluDashboardBundle vendor/bin/phpunit
 */
$dashboardPath = getenv('SULU_DASHBOARD_BUNDLE_PATH');

if (!interface_exists('Manuxi\\SuluDashboardBundle\\Widget\\DashboardWidgetInterface') && is_string($dashboardPath) && '' !== $dashboardPath) {
    spl_autoload_register(static function (string $class) use ($dashboardPath): void {
        $prefix = 'Manuxi\\SuluDashboardBundle\\';

        if (str_starts_with($class, $prefix)) {
            $path = rtrim($dashboardPath, '/\\') . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

            if (is_file($path)) {
                require_once $path;
            }
        }
    });
}

date_default_timezone_set('UTC');

(new Symfony\Component\Filesystem\Filesystem())->remove(__DIR__ . '/../var/cache');

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';

if (!isset($_SERVER['DATABASE_URL']) && !isset($_ENV['DATABASE_URL']) && false === getenv('DATABASE_URL')) {
    $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///' . __DIR__ . '/../var/test.db';
}
