<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use ZkTeco\ADMS\Commands\CommandResult;
use ZkTeco\ADMS\Commands\CommandStatus;
use ZkTeco\Laravel\EloquentCommandQueue;
use ZkTeco\Laravel\Events\CommandAcknowledged;
use ZkTeco\Laravel\Models\Command;

beforeEach(function () {
    $this->queue = new EloquentCommandQueue;
});

it('enqueues a command as pending and returns its handle', function () {
    $command = $this->queue->enqueue('SN1', 'REBOOT');

    expect($command->serialNumber)->toBe('SN1')
        ->and($command->command)->toBe('REBOOT');

    $this->assertDatabaseHas('zkteco_commands', [
        'id' => $command->id,
        'serial_number' => 'SN1',
        'status' => CommandStatus::Pending->value,
    ]);
});

it('returns pending commands oldest first and only for the given device', function () {
    $first = $this->queue->enqueue('SN1', 'REBOOT');
    $second = $this->queue->enqueue('SN1', 'DATA QUERY USERINFO');
    $this->queue->enqueue('SN2', 'CLEAR DATA');

    $pending = $this->queue->pending('SN1');

    expect($pending)->toHaveCount(2)
        ->and($pending[0]->id)->toBe($first->id)
        ->and($pending[1]->id)->toBe($second->id);
});

it('does not return a command once it has been sent', function () {
    $command = $this->queue->enqueue('SN1', 'REBOOT');

    $this->queue->markSent($command->id);

    expect($this->queue->pending('SN1'))->toBeEmpty();
    $this->assertDatabaseHas('zkteco_commands', [
        'id' => $command->id,
        'status' => CommandStatus::Sent->value,
    ]);
});

it('acknowledges a command, stores the return code, and fires the event', function () {
    Event::fake([CommandAcknowledged::class]);
    $command = $this->queue->enqueue('SN1', 'REBOOT');
    $this->queue->markSent($command->id);

    $resolved = $this->queue->acknowledge(new CommandResult($command->id, 0, 'DATA'));

    expect($resolved?->id)->toBe($command->id);
    $row = Command::query()->find($command->id);
    expect($row->status)->toBe(CommandStatus::Acknowledged)
        ->and($row->return_code)->toBe(0);

    Event::assertDispatchedTimes(CommandAcknowledged::class, 1);
});

it('returns null and fires nothing for an unknown command id', function () {
    Event::fake([CommandAcknowledged::class]);

    expect($this->queue->acknowledge(new CommandResult('404', 0)))->toBeNull();

    Event::assertNotDispatched(CommandAcknowledged::class);
});

it('does not fire the event again when a device re-reports an acknowledged command', function () {
    Event::fake([CommandAcknowledged::class]);
    $command = $this->queue->enqueue('SN1', 'REBOOT');
    $this->queue->markSent($command->id);

    $this->queue->acknowledge(new CommandResult($command->id, 0));
    $this->queue->acknowledge(new CommandResult($command->id, 0));

    Event::assertDispatchedTimes(CommandAcknowledged::class, 1);
});
