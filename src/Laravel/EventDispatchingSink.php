<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use ZkTeco\ADMS\AttendanceSink;
use ZkTeco\Laravel\Events\PunchReceived;
use ZkTeco\Values\AttendanceRecord;

/**
 * The bridge's {@see AttendanceSink}: dispatches the same {@see PunchReceived}
 * event the ZK live-listen path uses, so application listeners react to punches
 * identically whichever adapter delivered them (see docs/adr/0009).
 *
 * The device serial number is passed as the event's connection identifier,
 * since that is how an ADMS device is addressed.
 */
final class EventDispatchingSink implements AttendanceSink
{
    public function receive(AttendanceRecord $record, string $serialNumber): void
    {
        event(new PunchReceived($record, $serialNumber));
    }
}
