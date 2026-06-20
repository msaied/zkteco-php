<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Services;

use DateTimeImmutable;
use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Protocol\Bytes;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\Packet;
use ZkTeco\TCP\Protocol\TimeCodec;

/**
 * Read device metadata and the on-board clock.
 *
 * Metadata other than the firmware version is read through CMD_OPTIONS_RRQ,
 * which echoes a `name=value\0` string; the value is parsed out the way pyzk
 * does (split on the first `=`, cut at the NUL).
 */
final class DeviceInfoService
{
    public function __construct(private readonly Session $session) {}

    /**
     * @throws ResponseException when the device rejects the request.
     */
    public function firmwareVersion(): string
    {
        $response = $this->session->command(Command::Version);
        $this->assertOk($response, Command::Version);

        return Bytes::cutNull($response->payload);
    }

    /**
     * @throws ResponseException when the device rejects the request.
     */
    public function serialNumber(): string
    {
        return $this->readOption('~SerialNumber');
    }

    /**
     * The device's configured name, or an empty string when unset (pyzk returns
     * "" here rather than treating a missing name as an error).
     */
    public function name(): string
    {
        $response = $this->session->command(Command::OptionsRead, "~DeviceName\0");

        return $response->isOk() ? $this->parseOption($response->payload) : '';
    }

    /**
     * @throws ResponseException when the device rejects the request.
     */
    public function time(): DateTimeImmutable
    {
        $response = $this->session->command(Command::GetTime);
        $this->assertOk($response, Command::GetTime);

        return TimeCodec::decode(substr($response->payload, 0, 4));
    }

    /**
     * @throws ResponseException when the device rejects the request.
     */
    public function setTime(DateTimeImmutable $time): void
    {
        $this->assertOk(
            $this->session->command(Command::SetTime, TimeCodec::encode($time)),
            Command::SetTime,
        );
    }

    /**
     * Read a single CMD_OPTIONS_RRQ parameter by name.
     *
     * @throws ResponseException when the device rejects the request.
     */
    private function readOption(string $name): string
    {
        $response = $this->session->command(Command::OptionsRead, $name."\0");
        $this->assertOk($response, Command::OptionsRead);

        return $this->parseOption($response->payload);
    }

    /**
     * Pull the value out of a `name=value\0` options response.
     */
    private function parseOption(string $payload): string
    {
        $parts = explode('=', $payload, 2);
        $value = Bytes::cutNull(end($parts));

        return str_replace('=', '', $value);
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
