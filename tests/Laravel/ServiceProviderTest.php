<?php

declare(strict_types=1);

use ZkTeco\Laravel\DeviceManager;
use ZkTeco\TCP\Device;

it('registers the device manager and merges config', function () {
    expect(app('zkteco'))->toBeInstanceOf(DeviceManager::class)
        ->and(config('zkteco.default'))->not->toBeNull();
});

it('builds a device from the default connection', function () {
    config()->set('zkteco.connections.default.host', '10.0.0.5');

    $device = app(DeviceManager::class)->connection();

    expect($device)->toBeInstanceOf(Device::class)
        ->and($device->host)->toBe('10.0.0.5')
        ->and($device->port)->toBe(4370);
});

it('registers the listen command', function () {
    expect(array_keys(app('Illuminate\Contracts\Console\Kernel')->all()))
        ->toContain('zkteco:listen');
});
