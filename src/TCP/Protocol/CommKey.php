<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

/**
 * Derives the CMD_AUTH token from the device's numeric comm key and the
 * negotiated session id (pyzk's make_commkey, itself ported from commpro.c's
 * MakeKey).
 *
 * The algorithm reverses the 32 bits of the comm key, folds in the session id,
 * then runs two XOR passes — one against the literal bytes of "ZKSO" and one
 * against the low byte of a fixed tick value — with a 16-bit word swap between
 * them. The result is the four-byte token sent as the CMD_AUTH payload.
 */
final class CommKey
{
    /**
     * Fixed tick value used by pyzk's make_commkey (its `ticks` default).
     */
    private const TICKS = 50;

    public function __construct(
        private readonly int $commKey,
        private readonly int $sessionId,
    ) {}

    /**
     * The scrambled authentication token to send with CMD_AUTH.
     */
    public function token(): string
    {
        $k = 0;
        for ($i = 0; $i < 32; $i++) {
            if ($this->commKey & (1 << $i)) {
                $k = ($k << 1) | 1;
            } else {
                $k <<= 1;
            }
        }

        $k += $this->sessionId;

        // Treat $k as an unsigned 32-bit little-endian value.
        $bytes = array_values(unpack('C4', pack('V', $k & 0xFFFFFFFF)));

        $bytes = [
            $bytes[0] ^ ord('Z'),
            $bytes[1] ^ ord('K'),
            $bytes[2] ^ ord('S'),
            $bytes[3] ^ ord('O'),
        ];

        // Swap the two 16-bit words.
        $words = array_values(unpack('v2', pack('C4', ...$bytes)));
        $bytes = array_values(unpack('C4', pack('v2', $words[1], $words[0])));

        $b = self::TICKS & 0xFF;

        return pack(
            'C4',
            $bytes[0] ^ $b,
            $bytes[1] ^ $b,
            $b,
            $bytes[3] ^ $b,
        );
    }
}
