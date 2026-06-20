<?php

declare(strict_types=1);

namespace ZkTeco\ADMS;

use ZkTeco\Values\AttendanceRecord;

/**
 * Where parsed punches go once an ADMS handler has decoded them.
 *
 * This is the seam that keeps the handlers framework-neutral: they decode an
 * upload into {@see AttendanceRecord} values and hand each one to a sink,
 * without knowing whether the consumer dispatches a Laravel event, writes a
 * row, or calls a webhook. The bridge's implementation dispatches the same
 * `PunchReceived` event the ZK live path uses, keeping the read path unified
 * across both adapters (see docs/adr/0009).
 */
interface AttendanceSink
{
    public function receive(AttendanceRecord $record, string $serialNumber): void;
}
