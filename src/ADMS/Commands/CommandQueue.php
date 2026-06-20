<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands;

/**
 * The queue of {@see QueuedCommand}s a device drains on each `getrequest` poll,
 * plus the async write path that fills it (see docs/adr/0009).
 *
 * The lifecycle is explicitly asynchronous: {@see enqueue()} adds a command and
 * returns its handle immediately; the device picks it up on a later poll, where
 * it is {@see markSent()}; and the outcome arrives out-of-band via
 * {@see acknowledge()} when the device POSTs to `devicecmd`. Nothing here blocks
 * waiting on a device.
 */
interface CommandQueue
{
    /**
     * Commands waiting to be sent to this device, oldest first. Only Pending
     * commands are returned, so a command already handed out is never re-sent.
     *
     * @return list<QueuedCommand>
     */
    public function pending(string $serialNumber): array;

    /**
     * Enqueue a command for a device to run on its next poll and return the
     * handle the server correlates its acknowledgement by. The command text is a
     * plain ADMS instruction (e.g. `DATA QUERY USERINFO`, `REBOOT`); this package
     * never enqueues a `SHELL` command (see docs/adr/0010).
     */
    public function enqueue(string $serialNumber, string $command): QueuedCommand;

    /**
     * Mark a command as handed to the device — moved from Pending to Sent — so a
     * later poll does not re-send it. A no-op for an unknown or already-sent id.
     */
    public function markSent(string $id): void;

    /**
     * Record the outcome a device reported and return the command it resolved, or
     * `null` if the id is unknown. A bridge implementation also signals the
     * resolution to the application (the `CommandAcknowledged` event).
     */
    public function acknowledge(CommandResult $result): ?QueuedCommand;
}
