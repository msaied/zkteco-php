<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ZkTeco\Values\User;

/**
 * Dispatched for each User a device syncs up in its USERINFO (carried inside the
 * legacy `OPERLOG` upload). Listen for this to mirror the device's enrolled
 * people into your application.
 *
 * The User is keyed by `userId` (the employee number); a push does not carry the
 * device-local `uid`, so it arrives as 0. `$connection` is the device serial
 * number, since that is how an ADMS device is addressed.
 */
final class UserReceived
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly string $connection,
    ) {}
}
