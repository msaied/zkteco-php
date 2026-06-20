<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands;

/**
 * An instruction the server has enqueued for a Registered device to run on its
 * next poll (see CONTEXT.md "Queued command").
 *
 * Distinct from a ZK-protocol command, which is a binary opcode answered
 * synchronously over the socket; this one is text, returned in a `getrequest`
 * poll and acknowledged later. The `id` is the server's handle for correlating
 * that acknowledgement.
 */
final readonly class QueuedCommand
{
    public function __construct(
        public string $id,
        public string $serialNumber,
        public string $command,
    ) {}
}
