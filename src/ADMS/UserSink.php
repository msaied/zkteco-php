<?php

declare(strict_types=1);

namespace ZkTeco\ADMS;

use ZkTeco\Values\User;

/**
 * Where User records go once an ADMS handler has decoded them from a device's
 * USERINFO sync (which the legacy generation multiplexes into its `OPERLOG`
 * upload).
 *
 * The same framework-neutral seam as {@see AttendanceSink}: handlers emit
 * {@see User} values and a bridge decides what to do with them, so the core stays
 * free of any framework (see docs/adr/0008). A pushed User is keyed by its
 * `userId`, not the device-local `uid`, which a push does not carry.
 */
interface UserSink
{
    public function receive(User $user, string $serialNumber): void;
}
