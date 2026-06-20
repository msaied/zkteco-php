<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use ZkTeco\ADMS\UserSink;
use ZkTeco\Laravel\Events\UserReceived;
use ZkTeco\Values\User;

/**
 * The bridge's {@see UserSink}: dispatches a {@see UserReceived} event for each
 * User a device syncs up, so an application can mirror the device's enrolled
 * people without the core touching the framework.
 *
 * The device serial number is passed as the event's connection identifier, since
 * that is how an ADMS device is addressed.
 */
final class EventDispatchingUserSink implements UserSink
{
    public function receive(User $user, string $serialNumber): void
    {
        event(new UserReceived($user, $serialNumber));
    }
}
