<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use ZkTeco\Laravel\DeviceManager;
use ZkTeco\Laravel\PendingDeviceCommands;
use ZkTeco\TCP\Device;

/**
 * @method static Device connection(?string $name = null)
 * @method static PendingDeviceCommands push(string $serialNumber)
 *
 * @see DeviceManager
 */
final class ZkTeco extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'zkteco';
    }
}
