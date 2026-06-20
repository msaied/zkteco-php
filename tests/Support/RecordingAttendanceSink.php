<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\ADMS\AttendanceSink;
use ZkTeco\Values\AttendanceRecord;

/**
 * An {@see AttendanceSink} that simply collects what it is handed, so a test can
 * assert which records a handler emitted and for which serial number.
 */
final class RecordingAttendanceSink implements AttendanceSink
{
    /** @var list<array{record: AttendanceRecord, serial: string}> */
    public array $received = [];

    public function receive(AttendanceRecord $record, string $serialNumber): void
    {
        $this->received[] = ['record' => $record, 'serial' => $serialNumber];
    }
}
