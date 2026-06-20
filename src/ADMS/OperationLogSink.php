<?php

declare(strict_types=1);

namespace ZkTeco\ADMS;

use ZkTeco\Values\OperationLog;

/**
 * Where parsed operation log entries go once an ADMS handler has decoded an
 * `OPERLOG` upload — the audit-trail counterpart to {@see AttendanceSink}.
 *
 * Keeping it a seam is what lets the handlers stay framework-neutral (see
 * docs/adr/0008): they decode the upload into {@see OperationLog} values and hand
 * each one off without knowing whether the consumer dispatches an event, writes a
 * row, or forwards a webhook.
 */
interface OperationLogSink
{
    public function receive(OperationLog $entry, string $serialNumber): void;
}
