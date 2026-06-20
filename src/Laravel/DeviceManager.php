<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use InvalidArgumentException;
use ZkTeco\ADMS\Commands\DeviceCommander;
use ZkTeco\Laravel\Facades\ZkTeco;
use ZkTeco\TCP\Device;

/**
 * Builds {@see Device} instances from the `config/zkteco.php` connection
 * definitions. Resolved from the container as the `zkteco` binding and fronted
 * by the {@see ZkTeco} facade.
 *
 * It is also the entry point to the ADMS write path: {@see push()} returns the
 * fluent command API for a Registered device, so the one facade serves both the
 * socket client (`ZkTeco::connection()`) and ADMS commands (`ZkTeco::push()`).
 *
 * Override {@see makeDevice()} to customise how Devices are constructed (for
 * decoration, logging, or test doubles).
 */
class DeviceManager
{
    /**
     * @param  array{default?: string, connections?: array<string, array<string, mixed>>}  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * Build the Device for a named connection (or the default when omitted).
     */
    public function connection(?string $name = null): Device
    {
        $name = $this->resolveName($name);
        $connections = $this->config['connections'] ?? [];

        if (! isset($connections[$name])) {
            throw new InvalidArgumentException("ZKTeco connection [{$name}] is not configured.");
        }

        return $this->makeDevice($connections[$name]);
    }

    /**
     * The connection name to use, falling back to the configured default.
     */
    public function resolveName(?string $name): string
    {
        return $name ?? $this->config['default'] ?? 'default';
    }

    /**
     * The fluent ADMS write API for a Registered device, keyed by its serial
     * number. Commands are queued for the device's next poll; dispatching to a
     * serial that has never registered throws.
     */
    public function push(string $serialNumber): PendingDeviceCommands
    {
        return new PendingDeviceCommands(app(DeviceCommander::class), $serialNumber);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function makeDevice(array $settings): Device
    {
        return new Device(
            host: (string) $settings['host'],
            port: (int) ($settings['port'] ?? 4370),
            commKey: (int) ($settings['comm_key'] ?? 0),
            timeout: (float) ($settings['timeout'] ?? 5.0),
            useUdp: (bool) ($settings['udp'] ?? false),
        );
    }
}
