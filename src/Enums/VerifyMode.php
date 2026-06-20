<?php

declare(strict_types=1);

namespace ZkTeco\Enums;

/**
 * How a punch's identity was confirmed on an Attendance record.
 *
 * Corresponds to pyzk's confusingly named `status` field. This is a pure enum:
 * the codec maps the firmware's wire byte onto these cases, because the numeric
 * mapping varies between firmware variants and must be pinned against hardware
 * (see docs/adr/0005). Do not assume a backing integer here.
 */
enum VerifyMode
{
    case Password;
    case Fingerprint;
    case Face;
    case Card;
    case Other;

    /**
     * Map a firmware verify byte onto a case.
     *
     * Provisional: only the widely-reported values are mapped and everything
     * else falls back to {@see VerifyMode::Other}. The exact table varies by
     * firmware and must be confirmed against hardware (see docs/adr/0005); the
     * raw byte stays available on the source record until then.
     */
    public static function fromWire(int $byte): self
    {
        return match ($byte) {
            1 => self::Fingerprint,
            3 => self::Password,
            4 => self::Card,
            15 => self::Face,
            default => self::Other,
        };
    }
}
