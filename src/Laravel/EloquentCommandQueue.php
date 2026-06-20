<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use Illuminate\Support\Facades\Date;
use ZkTeco\ADMS\Commands\CommandQueue;
use ZkTeco\ADMS\Commands\CommandResult;
use ZkTeco\ADMS\Commands\CommandStatus;
use ZkTeco\ADMS\Commands\QueuedCommand;
use ZkTeco\Laravel\Events\CommandAcknowledged;
use ZkTeco\Laravel\Models\Command;

/**
 * Eloquent-backed {@see CommandQueue}: outbound commands live in
 * `zkteco_commands`, one row per instruction (see docs/adr/0008).
 *
 * This is the write path ADR 0009 keeps explicitly async — {@see enqueue()}
 * records a Pending row and returns immediately, {@see markSent()} flips it as
 * the device drains it on a poll, and {@see acknowledge()} resolves it when the
 * device reports back, firing {@see CommandAcknowledged} the same way the device
 * registry fires `DeviceRegistered` from its own write (see
 * {@see EloquentDeviceRegistry}).
 */
final class EloquentCommandQueue implements CommandQueue
{
    public function pending(string $serialNumber): array
    {
        return Command::query()
            ->where('serial_number', $serialNumber)
            ->where('status', CommandStatus::Pending)
            ->orderBy('id')
            ->get()
            ->map(fn (Command $row): QueuedCommand => $this->toValue($row))
            ->all();
    }

    public function enqueue(string $serialNumber, string $command): QueuedCommand
    {
        $row = Command::query()->create([
            'serial_number' => $serialNumber,
            'command' => $command,
            'status' => CommandStatus::Pending,
        ]);

        return $this->toValue($row);
    }

    public function markSent(string $id): void
    {
        Command::query()
            ->whereKey($id)
            ->where('status', CommandStatus::Pending)
            ->update([
                'status' => CommandStatus::Sent,
                'sent_at' => Date::now(),
            ]);
    }

    public function acknowledge(CommandResult $result): ?QueuedCommand
    {
        $row = Command::query()->whereKey($result->id)->first();

        if ($row === null) {
            return null;
        }

        $firstAcknowledgement = $row->status !== CommandStatus::Acknowledged;

        $row->status = CommandStatus::Acknowledged;
        $row->return_code = $result->returnCode;
        $row->acknowledged_at = Date::now();
        $row->save();

        $command = $this->toValue($row);

        if ($firstAcknowledgement) {
            event(new CommandAcknowledged($command, $result));
        }

        return $command;
    }

    private function toValue(Command $row): QueuedCommand
    {
        return new QueuedCommand((string) $row->id, $row->serial_number, $row->command);
    }
}
