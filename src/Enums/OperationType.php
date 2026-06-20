<?php

declare(strict_types=1);

namespace ZkTeco\Enums;

/**
 * What an Operation log entry records the device doing — a power cycle, an admin
 * action on a User, a settings change.
 *
 * This is a pure enum: a device reports the operation as a numeric code, and
 * {@see OperationType::fromCode()} maps it onto a case. Only the widely-reported
 * codes are mapped and everything else falls back to {@see OperationType::Other};
 * the raw code stays available on the Operation log entry. The numeric table
 * varies between firmware variants and is provisional until pinned against
 * hardware (see docs/adr/0005). Do not assume a backing integer here.
 */
enum OperationType
{
    case Startup;
    case Shutdown;
    case VerifyFailed;
    case Alarm;
    case MenuEntered;
    case SettingsChanged;
    case FingerprintEnrolled;
    case PasswordEnrolled;
    case CardEnrolled;
    case UserDeleted;
    case FingerprintDeleted;
    case DataCleared;
    case Other;

    /**
     * Map a firmware operation code onto a case.
     *
     * Provisional: only the commonly-reported standalone-SDK codes are mapped;
     * anything else falls back to {@see OperationType::Other}. The exact table is
     * firmware-sensitive and must be confirmed against hardware (see
     * docs/adr/0005); the raw code stays on the Operation log entry until then.
     */
    public static function fromCode(int $code): self
    {
        return match ($code) {
            0 => self::Startup,
            1 => self::Shutdown,
            2 => self::VerifyFailed,
            3 => self::Alarm,
            4 => self::MenuEntered,
            5 => self::SettingsChanged,
            6 => self::FingerprintEnrolled,
            7 => self::PasswordEnrolled,
            8 => self::CardEnrolled,
            9 => self::UserDeleted,
            10 => self::FingerprintDeleted,
            13 => self::DataCleared,
            default => self::Other,
        };
    }
}
