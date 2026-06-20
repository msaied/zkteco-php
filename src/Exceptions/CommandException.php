<?php

declare(strict_types=1);

namespace ZkTeco\Exceptions;

use ZkTeco\ADMS\Commands\DeviceCommand;
use ZkTeco\ADMS\Commands\DeviceCommander;
use ZkTeco\ADMS\Generations\Generation;

/**
 * Thrown when an outbound ADMS command cannot be dispatched: the target device
 * is not on record, or the resolved {@see Generation} has no renderer for the
 * given {@see DeviceCommand}.
 *
 * Both are caller errors on the write path the {@see DeviceCommander} owns —
 * a device must register before it can be commanded, and every command intent
 * must have a rendering arm.
 */
class CommandException extends ZkException
{
    public static function unknownDevice(string $serialNumber): self
    {
        return new self(
            ErrorCode::UnknownDevice,
            "No registered device for serial [{$serialNumber}]; a device must register before it can be commanded.",
            ['serial' => $serialNumber],
        );
    }

    public static function unsupportedCommand(string $commandClass, string $generation): self
    {
        return new self(
            ErrorCode::UnsupportedCommand,
            "The {$generation} generation cannot render command [{$commandClass}].",
            ['command' => $commandClass, 'generation' => $generation],
        );
    }
}
