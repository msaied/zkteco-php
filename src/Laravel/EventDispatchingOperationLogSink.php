<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use ZkTeco\ADMS\OperationLogSink;
use ZkTeco\Laravel\Events\OperationLogged;
use ZkTeco\Values\OperationLog;

/**
 * The bridge's {@see OperationLogSink}: dispatches an {@see OperationLogged} event
 * for each operation log entry a device uploads, so application listeners can
 * build an audit trail without the core knowing anything about events.
 *
 * The device serial number is passed as the event's connection identifier, since
 * that is how an ADMS device is addressed.
 */
final class EventDispatchingOperationLogSink implements OperationLogSink
{
    public function receive(OperationLog $entry, string $serialNumber): void
    {
        event(new OperationLogged($entry, $serialNumber));
    }
}
