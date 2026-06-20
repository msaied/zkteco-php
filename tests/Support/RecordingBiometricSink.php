<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\ADMS\BiometricSink;
use ZkTeco\Values\BiometricTemplate;

/**
 * A {@see BiometricSink} that collects what it is handed, so a test can assert
 * which biometric templates a handler emitted and for which serial number.
 */
final class RecordingBiometricSink implements BiometricSink
{
    /** @var list<array{template: BiometricTemplate, serial: string}> */
    public array $received = [];

    public function receive(BiometricTemplate $template, string $serialNumber): void
    {
        $this->received[] = ['template' => $template, 'serial' => $serialNumber];
    }
}
