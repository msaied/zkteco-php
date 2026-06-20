<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands;

/**
 * The outcome a device reports for a {@see QueuedCommand} it ran, POSTed back to
 * `/iclock/devicecmd` (e.g. `ID=12&Return=0&CMD=DATA`).
 *
 * The `id` correlates to the {@see QueuedCommand} the server handed out; the
 * `returnCode` is the device's status for it, where `0` means success and any
 * other value is a device-defined failure.
 */
final readonly class CommandResult
{
    public function __construct(
        public string $id,
        public int $returnCode,
        public string $command = '',
    ) {}

    public function succeeded(): bool
    {
        return $this->returnCode === 0;
    }
}
