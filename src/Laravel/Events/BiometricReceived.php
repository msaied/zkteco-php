<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ZkTeco\Values\BiometricTemplate;

/**
 * Dispatched for each biometric template a PUSH-SDK device uploads in its
 * `BIODATA` table. Listen for this to mirror the device's enrolled fingerprints,
 * faces, and palms into your application.
 *
 * The template is keyed by `userId` (the employee PIN); a push does not carry the
 * device-local slot, so it is not part of the value. `$connection` is the device
 * serial number, since that is how an ADMS device is addressed.
 */
final class BiometricReceived
{
    use Dispatchable;

    public function __construct(
        public readonly BiometricTemplate $template,
        public readonly string $connection,
    ) {}
}
