<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;

/**
 * Delete a user from the device by PIN — the socket client's
 * `users()->delete()` counterpart. The PIN is the human-facing employee number,
 * not a device-local record slot.
 */
final readonly class DeleteUser implements DeviceCommand
{
    public function __construct(
        public string $pin,
    ) {}
}
