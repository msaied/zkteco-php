<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

use DateTimeImmutable;

/**
 * Converts between the device's packed 4-byte clock value and a
 * {@see DateTimeImmutable}.
 *
 * The device stores a naive wall-clock with no timezone, encoded with the
 * formula from zkemsdk.c (EncodeTime/DecodeTime), ported from pyzk. Encoding
 * uses the components of the given datetime as-is, so the round-trip preserves
 * the wall-clock the caller intends.
 */
final class TimeCodec
{
    public static function decode(string $bytes): DateTimeImmutable
    {
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('V', $bytes);
        $t = $unpacked[1];

        $second = $t % 60;
        $t = intdiv($t, 60);
        $minute = $t % 60;
        $t = intdiv($t, 60);
        $hour = $t % 24;
        $t = intdiv($t, 24);
        $day = $t % 31 + 1;
        $t = intdiv($t, 31);
        $month = $t % 12 + 1;
        $year = intdiv($t, 12) + 2000;

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
        );
    }

    /**
     * Decode the 6-byte "timehex" form pushed in realtime events: one byte each
     * for year-since-2000, month, day, hour, minute, second (pyzk's
     * __decode_timehex). This is distinct from the 4-byte packed clock value.
     */
    public static function decodeHex(string $bytes): DateTimeImmutable
    {
        /** @var array{1: int, 2: int, 3: int, 4: int, 5: int, 6: int} $f */
        $f = unpack('C6', $bytes);

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', 2000 + $f[1], $f[2], $f[3], $f[4], $f[5], $f[6]),
        );
    }

    /**
     * Pack a datetime into the device's 4-byte little-endian clock value.
     */
    public static function encode(DateTimeImmutable $time): string
    {
        $year = (int) $time->format('Y');
        $month = (int) $time->format('n');
        $day = (int) $time->format('j');
        $hour = (int) $time->format('G');
        $minute = (int) $time->format('i');
        $second = (int) $time->format('s');

        $value = (($year % 100) * 12 * 31 + ($month - 1) * 31 + $day - 1)
            * (24 * 60 * 60) + ($hour * 60 + $minute) * 60 + $second;

        return pack('V', $value);
    }
}
