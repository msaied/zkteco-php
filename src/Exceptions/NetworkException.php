<?php

declare(strict_types=1);

namespace ZkTeco\Exceptions;

/**
 * Thrown on transport-level failures: timeouts, resets, or short reads/writes
 * on the underlying socket.
 */
class NetworkException extends ZkException
{
    public static function connectionFailed(string $host, int $port, string $reason): self
    {
        return new self(
            ErrorCode::ConnectionFailed,
            "Unable to connect to {$host}:{$port} ({$reason}).",
            ['host' => $host, 'port' => $port, 'reason' => $reason],
        );
    }

    public static function timeout(): self
    {
        return new self(
            ErrorCode::Timeout,
            'Timed out waiting for a response from the device.',
        );
    }

    public static function writeFailed(): self
    {
        return new self(
            ErrorCode::WriteFailed,
            'Failed to write the request to the device.',
        );
    }

    public static function connectionClosed(): self
    {
        return new self(
            ErrorCode::ConnectionClosed,
            'The device closed the connection before sending a full response.',
        );
    }

    public static function notConnected(): self
    {
        return new self(
            ErrorCode::NotConnected,
            'The transport is not connected.',
        );
    }
}
