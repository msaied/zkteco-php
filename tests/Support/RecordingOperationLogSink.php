<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\ADMS\OperationLogSink;
use ZkTeco\Values\OperationLog;

/**
 * An {@see OperationLogSink} that collects what it is handed, so a test can
 * assert which operation log entries a handler emitted and for which serial
 * number.
 */
final class RecordingOperationLogSink implements OperationLogSink
{
    /** @var list<array{entry: OperationLog, serial: string}> */
    public array $received = [];

    public function receive(OperationLog $entry, string $serialNumber): void
    {
        $this->received[] = ['entry' => $entry, 'serial' => $serialNumber];
    }
}
