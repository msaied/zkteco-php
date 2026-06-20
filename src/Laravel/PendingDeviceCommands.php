<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use ZkTeco\ADMS\Commands\DeviceCommand;
use ZkTeco\ADMS\Commands\DeviceCommander;
use ZkTeco\ADMS\Commands\Intents\ClearData;
use ZkTeco\ADMS\Commands\Intents\ClearLog;
use ZkTeco\ADMS\Commands\Intents\ClearPhoto;
use ZkTeco\ADMS\Commands\Intents\DeleteUser;
use ZkTeco\ADMS\Commands\Intents\Disable;
use ZkTeco\ADMS\Commands\Intents\Enable;
use ZkTeco\ADMS\Commands\Intents\PowerOff;
use ZkTeco\ADMS\Commands\Intents\PushTemplate;
use ZkTeco\ADMS\Commands\Intents\QueryData;
use ZkTeco\ADMS\Commands\Intents\Reboot;
use ZkTeco\ADMS\Commands\Intents\Restart;
use ZkTeco\ADMS\Commands\Intents\SyncTime;
use ZkTeco\ADMS\Commands\Intents\UpsertUser;
use ZkTeco\ADMS\Commands\QueuedCommand;
use ZkTeco\Values\BiometricTemplate;
use ZkTeco\Values\User;

/**
 * The fluent write API for one Registered device, returned by
 * `ZkTeco::push($serial)`.
 *
 * Each method enqueues a typed command for the device to run on its next poll
 * and returns the {@see QueuedCommand} handle — the ADMS counterpart to the
 * socket client's `$device->control()->…` ergonomics. Every call is async: the
 * command is queued, not sent, and its outcome arrives later as a
 * `CommandAcknowledged` event (see docs/adr/0009, 0013).
 *
 * The data and `SET OPTIONS`/power commands ride on provisional wire layouts
 * until they are pinned against a real device (see docs/adr/0005).
 */
final readonly class PendingDeviceCommands
{
    public function __construct(
        private DeviceCommander $commander,
        private string $serialNumber,
    ) {}

    public function reboot(): QueuedCommand
    {
        return $this->dispatch(new Reboot);
    }

    public function restart(): QueuedCommand
    {
        return $this->dispatch(new Restart);
    }

    public function powerOff(): QueuedCommand
    {
        return $this->dispatch(new PowerOff);
    }

    public function enable(): QueuedCommand
    {
        return $this->dispatch(new Enable);
    }

    public function disable(): QueuedCommand
    {
        return $this->dispatch(new Disable);
    }

    public function clearData(): QueuedCommand
    {
        return $this->dispatch(new ClearData);
    }

    public function clearLog(): QueuedCommand
    {
        return $this->dispatch(new ClearLog);
    }

    public function clearPhoto(): QueuedCommand
    {
        return $this->dispatch(new ClearPhoto);
    }

    /**
     * Set the device clock, defaulting to now.
     */
    public function syncTime(?DateTimeImmutable $at = null): QueuedCommand
    {
        return $this->dispatch(new SyncTime($at ?? Date::now()->toDateTimeImmutable()));
    }

    public function queryData(string $table): QueuedCommand
    {
        return $this->dispatch(new QueryData($table));
    }

    public function deleteUser(string $pin): QueuedCommand
    {
        return $this->dispatch(new DeleteUser($pin));
    }

    public function upsertUser(User $user): QueuedCommand
    {
        return $this->dispatch(new UpsertUser($user));
    }

    public function pushTemplate(BiometricTemplate $template): QueuedCommand
    {
        return $this->dispatch(new PushTemplate($template));
    }

    private function dispatch(DeviceCommand $command): QueuedCommand
    {
        return $this->commander->dispatch($this->serialNumber, $command);
    }
}
