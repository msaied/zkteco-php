<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;
use ZkTeco\Values\BiometricTemplate;

/**
 * Push a biometric enrollment to the device — the socket client's
 * `templates()->upload()` counterpart, but PIN-keyed like the rest of ADMS.
 *
 * This is the one command whose wire form genuinely differs by generation:
 * legacy renders it to the `FINGERTMP` table, PUSH SDK to `BIODATA`. Both forms
 * are firmware-sensitive and provisional until pinned against a real capture
 * (see docs/adr/0005); M6 confirms them.
 */
final readonly class PushTemplate implements DeviceCommand
{
    public function __construct(
        public BiometricTemplate $template,
    ) {}
}
