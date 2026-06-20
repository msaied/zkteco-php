<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Handlers;

use ZkTeco\ADMS\Commands\CommandQueue;
use ZkTeco\ADMS\Commands\CommandResult;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Http\PushResponse;
use ZkTeco\ADMS\Parsing\CommandResultParser;
use ZkTeco\ADMS\Registry\DeviceRegistry;

/**
 * Handles `/iclock/devicecmd`, where a device reports the result of a command it
 * was handed on a `getrequest` poll.
 *
 * The body is parsed into {@see CommandResult}s and each is
 * resolved against the queue, closing the async write loop a bridge surfaces as
 * `CommandAcknowledged`. A result for an unknown id is tolerated — the device is
 * still noted alive and the report is accepted so it keeps polling.
 */
final class DevicecmdHandler
{
    public function __construct(
        private DeviceRegistry $registry,
        private CommandQueue $queue,
        private CommandResultParser $parser,
    ) {}

    public function handle(PushRequest $request): PushResponse
    {
        $this->registry->markSeen((string) $request->serialNumber());

        foreach ($this->parser->parse($request->body) as $result) {
            $this->queue->acknowledge($result);
        }

        return PushResponse::ok();
    }
}
