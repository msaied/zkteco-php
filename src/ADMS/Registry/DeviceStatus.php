<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Registry;

/**
 * The approval state of a Registered device. This is what makes "accept all,
 * choose which to add" safe: a device can dial in and be recorded without its
 * data being trusted (see docs/adr/0010).
 *
 * - **Pending**: recorded and visible, but its attendance is held, not ingested.
 * - **Approved**: an operator (or the allowlist) has admitted it; its attendance
 *   flows through as `PunchReceived`.
 * - **Blocked**: explicitly refused; rejected at the door on every request.
 */
enum DeviceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Blocked = 'blocked';
}
