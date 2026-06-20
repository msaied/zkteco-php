<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\Laravel\Models\Device;

/**
 * Dispatched the first time an ADMS device registers (its handshake creates a
 * new {@see Device} row). Listen for this to allowlist
 * follow-up, notify operators, or seed related records. Re-handshakes of an
 * already-registered device do not fire it.
 */
final class DeviceRegistered
{
    use Dispatchable;

    public function __construct(public readonly RegisteredDevice $device) {}
}
