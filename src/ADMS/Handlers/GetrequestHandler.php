<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Handlers;

use ZkTeco\ADMS\Commands\CommandQueue;
use ZkTeco\ADMS\Commands\QueuedCommand;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Http\PushResponse;
use ZkTeco\ADMS\Registry\DeviceRegistry;

/**
 * Handles `/iclock/getrequest`, the device's command poll.
 *
 * The device asks "what should I do?" on a timer; we note it is alive and answer
 * with any pending {@see QueuedCommand}s as `C:<id>:<cmd>` lines. Draining a
 * command marks it Sent so the next poll does not hand it out again; the device
 * later reports each one's outcome to `devicecmd`. With nothing pending this is a
 * keep-alive that replies `OK`.
 */
final class GetrequestHandler
{
    public function __construct(
        private DeviceRegistry $registry,
        private CommandQueue $queue,
    ) {}

    public function handle(PushRequest $request): PushResponse
    {
        $serial = (string) $request->serialNumber();

        $this->registry->markSeen($serial);

        $pending = $this->queue->pending($serial);

        if ($pending === []) {
            return PushResponse::ok();
        }

        $lines = [];

        foreach ($pending as $command) {
            $this->queue->markSent($command->id);
            $lines[] = "C:{$command->id}:{$command->command}";
        }

        return PushResponse::ok(implode("\n", $lines));
    }
}
