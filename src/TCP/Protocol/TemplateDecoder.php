<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

use ZkTeco\Values\Template;

/**
 * Decodes the raw fingerprint buffer returned by CMD_DB_RRQ (FCT_FINGERTMP)
 * into {@see Template} values.
 *
 * Unlike the fixed-width user and attendance buffers, this is a 4-byte
 * little-endian total size followed by variable-length records. Each record
 * opens with a 6-byte header — `size` (the whole record length, header
 * included), `uid`, `fid`, `valid` — and is followed by `size - 6` opaque
 * template bytes. Field layout is ported from pyzk's get_templates (`HHbb`).
 */
final class TemplateDecoder
{
    /**
     * @return list<Template>
     */
    public static function decode(string $buffer): array
    {
        if (strlen($buffer) < 4) {
            return [];
        }

        $remaining = Bytes::uint32(substr($buffer, 0, 4));
        $body = substr($buffer, 4);

        $templates = [];

        while ($remaining > 0 && strlen($body) >= 6) {
            /** @var array{size: int, uid: int, fid: int, valid: int} $header */
            $header = unpack('vsize/vuid/cfid/cvalid', substr($body, 0, 6));
            $size = $header['size'];

            // Guard against a malformed length that would loop forever or read
            // past the buffer (pyzk trusts the device here).
            if ($size < 6 || $size > strlen($body)) {
                break;
            }

            $templates[] = new Template(
                uid: $header['uid'],
                fingerIndex: $header['fid'],
                valid: $header['valid'] !== 0,
                data: substr($body, 6, $size - 6),
            );

            $body = substr($body, $size);
            $remaining -= $size;
        }

        return $templates;
    }
}
