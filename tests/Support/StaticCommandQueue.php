<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\ADMS\Commands\CommandQueue;
use ZkTeco\ADMS\Commands\CommandResult;
use ZkTeco\ADMS\Commands\QueuedCommand;

/**
 * A {@see CommandQueue} that always returns the same preset commands, used to
 * exercise the `getrequest` rendering branch in isolation. The write path is
 * inert; for lifecycle behaviour use {@see InMemoryCommandQueue}.
 */
final class StaticCommandQueue implements CommandQueue
{
    /**
     * @param  list<QueuedCommand>  $commands
     */
    public function __construct(private array $commands = []) {}

    public function pending(string $serialNumber): array
    {
        return $this->commands;
    }

    public function enqueue(string $serialNumber, string $command): QueuedCommand
    {
        return new QueuedCommand('0', $serialNumber, $command);
    }

    public function markSent(string $id): void
    {
        // Preset queue: the rendering branch is all this double exercises.
    }

    public function acknowledge(CommandResult $result): ?QueuedCommand
    {
        return null;
    }
}
