<?php

declare(strict_types=1);

namespace ZkTeco\Values;

use DateTimeImmutable;
use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;

/**
 * A single clock event: who punched, when, by what verify method, and what the
 * punch means.
 *
 * `userId` is the human-facing employee number (not the device-local uid).
 */
final readonly class AttendanceRecord
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $recordedAt,
        public VerifyMode $verifyMode,
        public PunchState $punchState,
        public ?int $uid = null,
    ) {}
}
