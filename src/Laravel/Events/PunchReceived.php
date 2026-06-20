<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ZkTeco\Values\AttendanceRecord;

/**
 * Dispatched by the zkteco:listen command for each live punch streamed from a
 * device. Listen for this to persist, notify, or react in real time.
 */
final class PunchReceived
{
    use Dispatchable;

    public function __construct(
        public readonly AttendanceRecord $record,
        public readonly string $connection,
    ) {}
}
