<?php

declare(strict_types=1);

namespace ZkTeco\Exceptions;

/**
 * Thrown when establishing, authenticating, or using a device session fails —
 * e.g. the socket cannot connect or the comm key is rejected.
 */
class ConnectionException extends ZkException
{
    public static function authFailed(): self
    {
        return new self(
            ErrorCode::AuthFailed,
            'Authentication failed: the comm key is missing or incorrect.',
        );
    }

    public static function unexpectedResponse(int $command): self
    {
        return new self(
            ErrorCode::UnexpectedResponse,
            "Unable to connect: unexpected device response [{$command}].",
            ['command' => $command],
        );
    }

    public static function sessionNotOpen(): self
    {
        return new self(
            ErrorCode::NotConnected,
            'Not connected. Open the session before issuing commands.',
        );
    }

    public static function deviceNotConnected(): self
    {
        return new self(
            ErrorCode::NotConnected,
            'Not connected. Call connect() or use session().',
        );
    }

    public static function udpUnsupported(): self
    {
        return new self(
            ErrorCode::UdpUnsupported,
            'UDP transport is not implemented yet; use TCP (useUdp: false).',
        );
    }
}
