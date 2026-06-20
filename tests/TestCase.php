<?php

declare(strict_types=1);

namespace ZkTeco\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ZkTeco\Laravel\ZkTecoServiceProvider;

/**
 * Base case for the Laravel bridge tests. Boots a minimal Laravel app via
 * Testbench with the package's service provider registered, the package
 * migrations run, and the ADMS endpoints enabled for a fixed test serial so the
 * push tests have routes to hit. Tests that only use the socket client ignore
 * the ADMS setup.
 */
abstract class TestCase extends Orchestra
{
    /**
     * The serial number allowlisted for the ADMS endpoint tests.
     */
    public const string AllowedSerial = 'TEST-SN-1';

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ZkTecoServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('zkteco.adms.enabled', true);
        $app['config']->set('zkteco.adms.allowed_serials', [self::AllowedSerial]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
