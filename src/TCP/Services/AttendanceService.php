<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Services;

use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Protocol\AttendanceDecoder;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\Values\AttendanceRecord;

/**
 * Read and clear the attendance log stored on a device.
 */
final class AttendanceService
{
    public function __construct(private readonly Session $session) {}

    /**
     * @return list<AttendanceRecord>
     */
    public function all(): array
    {
        $sizes = $this->session->readSizes();

        if ($sizes['records'] === 0) {
            return [];
        }

        // The narrow record layouts carry only one identifier, so the user list
        // is needed to resolve uid <-> user id (matching pyzk's get_attendance).
        $users = (new UserService($this->session))->all();
        $buffer = $this->session->readBuffer(Command::AttlogRead);

        return AttendanceDecoder::decode($buffer, $sizes['records'], $users);
    }

    /**
     * Erase every stored attendance record (CMD_CLEAR_ATTLOG).
     *
     * @throws ResponseException when the device rejects the clear.
     */
    public function clear(): void
    {
        $response = $this->session->command(Command::ClearAttlog);

        if (! $response->isOk()) {
            throw ResponseException::commandRejected(Command::ClearAttlog->value);
        }
    }
}
