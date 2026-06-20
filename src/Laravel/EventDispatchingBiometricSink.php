<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use ZkTeco\ADMS\BiometricSink;
use ZkTeco\Laravel\Events\BiometricReceived;
use ZkTeco\Values\BiometricTemplate;

/**
 * The bridge's {@see BiometricSink}: dispatches a {@see BiometricReceived} event
 * for each biometric template a PUSH-SDK device uploads, so an application can
 * mirror the device's enrollments without the core touching the framework.
 *
 * The device serial number is passed as the event's connection identifier, since
 * that is how an ADMS device is addressed.
 */
final class EventDispatchingBiometricSink implements BiometricSink
{
    public function receive(BiometricTemplate $template, string $serialNumber): void
    {
        event(new BiometricReceived($template, $serialNumber));
    }
}
