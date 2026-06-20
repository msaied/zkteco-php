<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use DateTimeImmutable;
use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Set the device clock to `$at` — the socket client's `info()->setTime()`
 * counterpart. The moment is taken as a naive wall-clock; the device keeps no
 * timezone.
 */
final readonly class SyncTime implements DeviceCommand
{
    public function __construct(
        public DateTimeImmutable $at,
    ) {}
}
