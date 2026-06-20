<?php

declare(strict_types=1);

namespace ZkTeco\Exceptions;

/**
 * Thrown when the device replies with an error acknowledgement or a payload
 * that cannot be parsed as the expected protocol response.
 */
class ResponseException extends ZkException
{
    public static function tooShort(): self
    {
        return new self(
            ErrorCode::InvalidResponse,
            'Response packet is shorter than the 8-byte ZK header.',
        );
    }

    public static function invalidFraming(): self
    {
        return new self(
            ErrorCode::InvalidResponse,
            'Unexpected TCP framing tag in the device response.',
        );
    }

    public static function bufferedReadUnsupported(): self
    {
        return new self(
            ErrorCode::InvalidResponse,
            'The device does not support the buffered read protocol.',
        );
    }

    public static function chunkReadFailed(int $start, int $size): self
    {
        return new self(
            ErrorCode::InvalidResponse,
            "Failed to read a data chunk at offset {$start} ({$size} bytes) after 3 attempts.",
            ['start' => $start, 'size' => $size],
        );
    }

    public static function commandRejected(int $command): self
    {
        return new self(
            ErrorCode::InvalidResponse,
            "The device rejected command [{$command}].",
            ['command' => $command],
        );
    }
}
