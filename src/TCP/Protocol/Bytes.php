<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

/**
 * The byte-level primitives shared by the protocol decoders. Both operations are
 * fixed by the wire format — not firmware-variant — so they live here once rather
 * than being re-derived in each decoder.
 */
final class Bytes
{
    /**
     * Read a little-endian unsigned 32-bit integer from the first 4 bytes.
     */
    public static function uint32(string $bytes): int
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('V', $bytes);

        return $unpacked[1];
    }

    /**
     * Truncate a C-style string at its first NUL, returning the value unchanged
     * when it carries no terminator.
     */
    public static function cutNull(string $value): string
    {
        $end = strpos($value, "\0");

        return $end === false ? $value : substr($value, 0, $end);
    }
}
