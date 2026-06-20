<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands\Intents;

use ZkTeco\ADMS\Commands\DeviceCommand;
use ZkTeco\Values\User;

/**
 * Create or update a user on the device — the socket client's `users()->save()`
 * counterpart. The `USERINFO` table is keyed by the user's PIN (`userId`), so
 * the device-local `uid` slot on the {@see User} is not part of the push and is
 * ignored when this is rendered.
 */
final readonly class UpsertUser implements DeviceCommand
{
    public function __construct(
        public User $user,
    ) {}
}
