<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Services;

use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\Packet;
use ZkTeco\TCP\Protocol\UserDecoder;
use ZkTeco\TCP\Protocol\UserEncoder;
use ZkTeco\Values\User;

/**
 * Read and manage the Users enrolled on a device.
 */
final class UserService
{
    /**
     * Function code for the user dataset (pyzk's FCT_USER).
     */
    private const FCT_USER = 5;

    public function __construct(private readonly Session $session) {}

    /**
     * @return list<User>
     */
    public function all(): array
    {
        $sizes = $this->session->readSizes();

        if ($sizes['users'] === 0) {
            return [];
        }

        $buffer = $this->session->readBuffer(Command::UserTempRead, self::FCT_USER);

        return UserDecoder::decode($buffer, $sizes['users'], $this->session->nameEncoding);
    }

    /**
     * Find a single enrolled user by their device-local slot (uid).
     *
     * The protocol has no single-record read, so this filters the full list
     * (matching pyzk's behaviour).
     */
    public function find(int $uid): ?User
    {
        foreach ($this->all() as $user) {
            if ($user->uid === $uid) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Create or overwrite a user (CMD_USER_WRQ), keyed by their uid.
     *
     * @throws ResponseException when the device rejects the write.
     */
    public function save(User $user): void
    {
        $this->assertOk(
            $this->session->command(Command::UserWrite, UserEncoder::encode($user, encoding: $this->session->nameEncoding)),
            Command::UserWrite,
        );

        $this->refresh();
    }

    /**
     * Delete a single user by their device-local slot (CMD_DELETE_USER).
     *
     * @throws ResponseException when the device rejects the delete.
     */
    public function delete(int $uid): void
    {
        $this->assertOk(
            $this->session->command(Command::DeleteUser, pack('v', $uid)),
            Command::DeleteUser,
        );

        $this->refresh();
    }

    /**
     * Wipe all users, fingerprints and attendance from the device
     * (CMD_CLEAR_DATA).
     *
     * @throws ResponseException when the device rejects the clear.
     */
    public function clear(): void
    {
        $this->assertOk(
            $this->session->command(Command::ClearData),
            Command::ClearData,
        );
    }

    /**
     * Tell the device to reload its in-memory tables after a write
     * (pyzk's refresh_data).
     *
     * @throws ResponseException when the device rejects the refresh.
     */
    private function refresh(): void
    {
        $this->assertOk(
            $this->session->command(Command::RefreshData),
            Command::RefreshData,
        );
    }

    /**
     * @throws ResponseException when the device did not acknowledge the command.
     */
    private function assertOk(Packet $response, Command $command): void
    {
        if (! $response->isOk()) {
            throw ResponseException::commandRejected($command->value);
        }
    }
}
