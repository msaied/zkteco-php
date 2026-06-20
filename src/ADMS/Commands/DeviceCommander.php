<?php

declare(strict_types=1);

namespace ZkTeco\ADMS\Commands;

use ZkTeco\ADMS\Generations\GenerationSelector;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\Exceptions\CommandException;

/**
 * The typed entry point to the async write path: turn a {@see DeviceCommand}
 * intent into a queued ADMS instruction for a specific device.
 *
 * It resolves the device's {@see ProtocolGeneration}, asks
 * that generation to render the intent to its wire syntax, and enqueues the
 * result on the {@see CommandQueue} — the device drains it on its next poll and
 * reports the outcome out-of-band, exactly as a hand-written string would (see
 * docs/adr/0009, 0013). The intent stays generation-agnostic; only the rendering
 * is generation-specific.
 *
 * A device must be on record first: commanding an unknown serial throws rather
 * than queuing an instruction no registered device will ever drain.
 */
final class DeviceCommander
{
    public function __construct(
        private DeviceRegistry $registry,
        private GenerationSelector $generations,
        private CommandQueue $queue,
    ) {}

    public function dispatch(string $serialNumber, DeviceCommand $command): QueuedCommand
    {
        $device = $this->registry->find($serialNumber);

        if ($device === null) {
            throw CommandException::unknownDevice($serialNumber);
        }

        $wire = $this->generations->for($device)->renderCommand($command);

        return $this->queue->enqueue($serialNumber, $wire);
    }
}
