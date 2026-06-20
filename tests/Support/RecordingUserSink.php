<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\ADMS\UserSink;
use ZkTeco\Values\User;

/**
 * A {@see UserSink} that collects what it is handed, so a test can assert which
 * User records a handler emitted and for which serial number.
 */
final class RecordingUserSink implements UserSink
{
    /** @var list<array{user: User, serial: string}> */
    public array $received = [];

    public function receive(User $user, string $serialNumber): void
    {
        $this->received[] = ['user' => $user, 'serial' => $serialNumber];
    }
}
