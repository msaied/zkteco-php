<?php

declare(strict_types=1);

namespace ZkTeco\ADMS;

use ZkTeco\Values\BiometricTemplate;

/**
 * Where parsed biometric templates go once a PUSH-SDK handler has decoded a
 * `BIODATA` upload.
 *
 * Like {@see AttendanceSink} and {@see UserSink}, this is a framework-neutral
 * seam: a handler decodes the upload into {@see BiometricTemplate} values and
 * hands each to a sink, without knowing whether the consumer dispatches a Laravel
 * event, writes a row, or calls a webhook. The bridge's implementation dispatches
 * a `BiometricReceived` event (see docs/adr/0008, 0011).
 */
interface BiometricSink
{
    public function receive(BiometricTemplate $template, string $serialNumber): void;
}
