<?php

declare(strict_types=1);

namespace ZkTeco\Enums;

/**
 * A User's access level on a device.
 *
 * Backing values are the ZK protocol's privilege byte as used by pyzk.
 */
enum Privilege: int
{
    case User = 0;
    case Enroller = 2;
    case Manager = 6;
    case Admin = 14;
}
