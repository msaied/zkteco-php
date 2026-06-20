<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\ADMS\Commands\CommandQueue;
use ZkTeco\ADMS\Commands\CommandResult;
use ZkTeco\ADMS\Commands\CommandStatus;
use ZkTeco\ADMS\Commands\QueuedCommand;

/**
 * An in-memory {@see CommandQueue} for core tests: it tracks the full lifecycle
 * (enqueue → Pending, drain → Sent, acknowledge → Acknowledged) without a
 * database, and records acknowledgements so a test can assert the loop closed.
 * No Laravel, no events — that wiring belongs to the bridge.
 */
final class InMemoryCommandQueue implements CommandQueue
{
    /** @var array<string, array{command: QueuedCommand, status: CommandStatus}> */
    public array $entries = [];

    /** @var list<CommandResult> */
    public array $acknowledged = [];

    private int $sequence = 0;

    public function pending(string $serialNumber): array
    {
        $pending = [];

        foreach ($this->entries as $entry) {
            if ($entry['command']->serialNumber === $serialNumber && $entry['status'] === CommandStatus::Pending) {
                $pending[] = $entry['command'];
            }
        }

        return $pending;
    }

    public function enqueue(string $serialNumber, string $command): QueuedCommand
    {
        $queued = new QueuedCommand((string) (++$this->sequence), $serialNumber, $command);

        $this->entries[$queued->id] = ['command' => $queued, 'status' => CommandStatus::Pending];

        return $queued;
    }

    public function markSent(string $id): void
    {
        if (isset($this->entries[$id]) && $this->entries[$id]['status'] === CommandStatus::Pending) {
            $this->entries[$id]['status'] = CommandStatus::Sent;
        }
    }

    public function acknowledge(CommandResult $result): ?QueuedCommand
    {
        if (! isset($this->entries[$result->id])) {
            return null;
        }

        $this->entries[$result->id]['status'] = CommandStatus::Acknowledged;
        $this->acknowledged[] = $result;

        return $this->entries[$result->id]['command'];
    }

    public function statusOf(string $id): ?CommandStatus
    {
        return $this->entries[$id]['status'] ?? null;
    }
}
