<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands;

/**
 * The lifecycle state of a {@see QueuedCommand} on the async write path.
 *
 * - **Pending**: enqueued by the server, not yet handed to the device.
 * - **Sent**: drained into a `getrequest` poll and now running on the device,
 *   awaiting its result.
 * - **Acknowledged**: the device reported the outcome via `devicecmd`; the
 *   command's return code carries success (0) or the failure reason.
 *
 * A command is sent at most once (see docs/adr/0009): there is no automatic
 * retry of a Sent command whose acknowledgement never arrives.
 */
enum CommandStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
}
