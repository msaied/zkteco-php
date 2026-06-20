<?php

declare(strict_types=1);

namespace ZkTeco\Values;

use ZkTeco\Enums\Privilege;

/**
 * A person enrolled on a device.
 *
 * The `uid` is the device-local record slot (1..N); the `userId` is the
 * human-facing employee number string. These two are distinct and must never
 * be conflated.
 */
final readonly class User
{
    public function __construct(
        public int $uid,
        public string $userId,
        public string $name,
        public Privilege $privilege = Privilege::User,
        public ?string $password = null,
        public ?string $cardNumber = null,
        public int $groupId = 0,
    ) {}
}
