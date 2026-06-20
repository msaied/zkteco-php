<?php

declare(strict_types=1);

namespace ZkTeco\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ZkTeco\ADMS\Commands\CommandResult;
use ZkTeco\ADMS\Commands\QueuedCommand;

/**
 * Dispatched when a device reports the outcome of a command it ran, closing the
 * async ADMS write loop: the {@see QueuedCommand} that was enqueued, paired with
 * the {@see CommandResult} the device returned. Inspect
 * `$result->succeeded()` (or `$result->returnCode`) to branch on success.
 *
 * Fired once per command, on the first acknowledgement; a device re-reporting an
 * already-acknowledged command does not fire it again.
 */
final class CommandAcknowledged
{
    use Dispatchable;

    public function __construct(
        public readonly QueuedCommand $command,
        public readonly CommandResult $result,
    ) {}
}
