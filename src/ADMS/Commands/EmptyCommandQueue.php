<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands;

/**
 * The inert command queue: nothing is ever pending, and the write path is a
 * no-op.
 *
 * It keeps `getrequest` honest where no outbound path is configured — the
 * handler genuinely asks the queue what is pending and genuinely drains it. The
 * Laravel bridge binds a persisting queue (`EloquentCommandQueue`) to actually
 * drive a device; this is the default for core-only use with no write path.
 */
final class EmptyCommandQueue implements CommandQueue
{
    public function pending(string $serialNumber): array
    {
        return [];
    }

    /**
     * Inert: the null queue keeps no state, so the returned handle is a sentinel
     * (`id = '0'`) and the command is discarded. Bind a persisting queue to send
     * commands to a device.
     */
    public function enqueue(string $serialNumber, string $command): QueuedCommand
    {
        return new QueuedCommand('0', $serialNumber, $command);
    }

    public function markSent(string $id): void
    {
        // The null queue tracks nothing; there is no Pending command to advance.
    }

    public function acknowledge(CommandResult $result): ?QueuedCommand
    {
        return null;
    }
}
