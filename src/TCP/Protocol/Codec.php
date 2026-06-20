<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

use ZkTeco\Exceptions\ResponseException;

/**
 * Serialises commands to wire bytes and parses device responses back into
 * {@see Packet} values.
 *
 * The ZK header is four little-endian unsigned shorts — command, checksum,
 * session id, reply id — followed by the payload. The TCP framing tag is added
 * by {@see TcpTransport}, not here, so the codec stays transport-agnostic.
 *
 * Ported from pyzk's create_header / create_checksum. Two quirks are preserved
 * for byte-for-byte compatibility with real firmware:
 *   - the checksum is computed over the header carrying the *pre-increment*
 *     reply id, while the packet that ships carries reply id + 1;
 *   - the reply-id counter wraps at USHRT_MAX (65535), not 65536.
 */
final class Codec
{
    public const USHRT_MAX = 65535;

    /**
     * Build a request packet ready to write to the transport.
     */
    public function build(Command $command, string $data, int $sessionId, int $replyId): string
    {
        $head = pack('vvvv', $command->value, 0, $sessionId, $replyId).$data;
        $checksum = self::checksum($head);

        $replyId += 1;
        if ($replyId >= self::USHRT_MAX) {
            $replyId -= self::USHRT_MAX;
        }

        return pack('vvvv', $command->value, $checksum, $sessionId, $replyId).$data;
    }

    /**
     * Parse a raw response into a {@see Packet}.
     *
     * @throws ResponseException when the buffer is too short or malformed.
     */
    public function parse(string $buffer): Packet
    {
        if (strlen($buffer) < 8) {
            throw ResponseException::tooShort();
        }

        /** @var array{command: int, checksum: int, sessionId: int, replyId: int} $header */
        $header = unpack('vcommand/vchecksum/vsessionId/vreplyId', substr($buffer, 0, 8));

        return new Packet(
            command: $header['command'],
            sessionId: $header['sessionId'],
            replyId: $header['replyId'],
            payload: substr($buffer, 8),
        );
    }

    /**
     * Compute the ones-complement checksum over a packet buffer.
     *
     * Sums the buffer as little-endian 16-bit words (with a trailing odd byte
     * added on its own), folds carries back into 16 bits, then takes the ones
     * complement — matching pyzk's create_checksum.
     */
    public static function checksum(string $buffer): int
    {
        $length = strlen($buffer);
        $checksum = 0;
        $offset = 0;

        while ($length > 1) {
            $checksum += ord($buffer[$offset]) + (ord($buffer[$offset + 1]) << 8);

            if ($checksum > self::USHRT_MAX) {
                $checksum -= self::USHRT_MAX;
            }

            $offset += 2;
            $length -= 2;
        }

        if ($length === 1) {
            $checksum += ord($buffer[$offset]);
        }

        while ($checksum > self::USHRT_MAX) {
            $checksum -= self::USHRT_MAX;
        }

        $checksum = ~$checksum;

        while ($checksum < 0) {
            $checksum += self::USHRT_MAX;
        }

        return $checksum;
    }
}
