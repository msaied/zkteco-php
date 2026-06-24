<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

/**
 * Converts user names between the application's UTF-8 strings and the
 * fixed-width byte field the device stores them in.
 *
 * The device does not store names as UTF-8: it reads the raw name bytes using
 * its own configured codepage (Windows-1256 for Arabic firmware, GB2312 for
 * Chinese, and so on). Writing UTF-8 bytes into that field therefore renders as
 * mojibake on the panel. This codec re-encodes the name to the device's
 * codepage on the way out and back to UTF-8 on the way in.
 *
 * Truncation to the field width is done in the *encoded* form and on whole
 * source characters, so a multi-byte character is never split mid-sequence (the
 * old byte-wise `substr` could, leaving a stray garbage byte at the boundary).
 */
final class NameField
{
    /**
     * The default codepage: a no-op, matching firmware that already stores
     * names as UTF-8.
     */
    public const DEFAULT_ENCODING = 'UTF-8';

    /**
     * Encode a UTF-8 name into a fixed-width, NUL-padded device field.
     */
    public static function pack(string $name, int $width, string $encoding = self::DEFAULT_ENCODING): string
    {
        return str_pad(self::fit($name, $width, $encoding), $width, "\0");
    }

    /**
     * Decode a raw device name field back into a UTF-8 string, trimming the NUL
     * padding and surrounding whitespace.
     */
    public static function unpack(string $raw, string $encoding = self::DEFAULT_ENCODING): string
    {
        $value = Bytes::cutNull($raw);

        if (! self::isUtf8($encoding)) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);

            if ($converted !== false) {
                $value = $converted;
            }
        }

        return trim($value);
    }

    /**
     * Re-encode the name to the device codepage and shrink it — by whole source
     * characters — until the result fits $width bytes.
     */
    private static function fit(string $name, int $width, string $encoding): string
    {
        $encoded = self::toDevice($name, $encoding);

        if (strlen($encoded) <= $width) {
            return $encoded;
        }

        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);

        if ($chars === false) {
            // Not valid UTF-8; fall back to a plain byte cut.
            return substr($encoded, 0, $width);
        }

        for ($count = count($chars) - 1; $count > 0; $count--) {
            $candidate = self::toDevice(implode('', array_slice($chars, 0, $count)), $encoding);

            if (strlen($candidate) <= $width) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Convert a UTF-8 name to the device codepage, leaving it untouched when the
     * device already speaks UTF-8 or the conversion is unavailable.
     */
    private static function toDevice(string $name, string $encoding): string
    {
        if (self::isUtf8($encoding)) {
            return $name;
        }

        $converted = @iconv('UTF-8', $encoding.'//IGNORE', $name);

        return $converted === false ? $name : $converted;
    }

    private static function isUtf8(string $encoding): bool
    {
        return in_array(strtoupper($encoding), ['UTF-8', 'UTF8'], true);
    }
}
