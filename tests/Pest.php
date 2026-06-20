<?php

declare(strict_types=1);

use ZkTeco\TCP\Device;
use ZkTeco\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Cases
|--------------------------------------------------------------------------
|
| Core tests under tests/Unit run as plain Pest tests with no framework. Only
| the bridge tests under tests/Laravel need a booted Laravel app, provided by
| Orchestra Testbench through the shared TestCase.
|
*/

pest()->extend(TestCase::class)->in('Laravel');

/*
|--------------------------------------------------------------------------
| Integration Helpers
|--------------------------------------------------------------------------
|
| Tests under tests/Integration talk to a real ZKTeco device and are skipped
| unless ZKTECO_DEVICE_HOST is set. Run them explicitly, for example:
|
|   ZKTECO_DEVICE_HOST=192.168.1.195 vendor/bin/pest --testsuite=Integration
|
| Optional overrides: ZKTECO_DEVICE_COMM_KEY (default 0) and
| ZKTECO_DEVICE_TIMEOUT (seconds, default 5).
|
*/

pest()->beforeEach(function () {
    if (! integrationEnabled()) {
        $this->markTestSkipped('Set ZKTECO_DEVICE_HOST to run integration tests against a real device.');
    }
})->in('Integration');

function integrationEnabled(): bool
{
    return (getenv('ZKTECO_DEVICE_HOST') ?: '') !== '';
}

function integrationDevice(?float $timeout = null): Device
{
    return new Device(
        host: (string) getenv('ZKTECO_DEVICE_HOST'),
        commKey: (int) (getenv('ZKTECO_DEVICE_COMM_KEY') ?: 0),
        timeout: $timeout ?? (float) (getenv('ZKTECO_DEVICE_TIMEOUT') ?: 5.0),
    );
}
