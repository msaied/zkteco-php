<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Services;

use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Device;
use ZkTeco\TCP\Protocol\Command;

/**
 * Device-wide control operations: locking input during bulk work, power, and
 * data resets.
 *
 * Note: {@see disable()} stops the device accepting punches. The managed
 * {@see Device::session()} scope guarantees a matching {@see enable()}
 * runs even on failure, so the terminal is never left locked.
 */
final class DeviceControlService
{
    public function __construct(private readonly Session $session) {}

    /**
     * Lock the device so it ignores user activity while a process runs
     * (CMD_DISABLEDEVICE).
     *
     * @throws ResponseException when the device rejects the request.
     */
    public function disable(): void
    {
        $this->send(Command::DisableDevice);
    }

    /**
     * Return the device to normal operation (CMD_ENABLEDEVICE).
     *
     * @throws ResponseException when the device rejects the request.
     */
    public function enable(): void
    {
        $this->send(Command::EnableDevice);
    }

    /**
     * Reboot the device (CMD_RESTART). The session is no longer usable
     * afterwards and should be reopened.
     *
     * @throws ResponseException when the device rejects the request.
     */
    public function restart(): void
    {
        $this->send(Command::Restart);
    }

    /**
     * Shut the device down (CMD_POWEROFF). The session is no longer usable
     * afterwards.
     *
     * @throws ResponseException when the device rejects the request.
     */
    public function powerOff(): void
    {
        $this->send(Command::PowerOff);
    }

    /**
     * Wipe all users, fingerprints and attendance from the device
     * (CMD_CLEAR_DATA).
     *
     * @throws ResponseException when the device rejects the request.
     */
    public function clearData(): void
    {
        $this->send(Command::ClearData);
    }

    /**
     * Send a payload-free control command and assert the device acknowledged it.
     *
     * @throws ResponseException when the device did not acknowledge the command.
     */
    private function send(Command $command): void
    {
        if (! $this->session->command($command)->isOk()) {
            throw ResponseException::commandRejected($command->value);
        }
    }
}
