<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

use ZkTeco\Values\User;

/**
 * Encodes a {@see User} into the fixed-width record CMD_USER_WRQ expects.
 *
 * The byte layout is the inverse of {@see UserDecoder}: a 28-byte (zk6) or
 * 72-byte (zk8) record. TCP devices default to 72, matching pyzk's set_user.
 * Field offsets and widths are ported from pyzk's pack formats
 * (`HB5s8sIxBHI` and `HB8s24s4sx7sx24s`).
 */
final class UserEncoder
{
    /**
     * The 72-byte (zk8) layout used by TCP devices; pyzk's default.
     */
    public const PACKET_SIZE_ZK8 = 72;

    /**
     * The 28-byte (zk6) layout used by older firmware.
     */
    public const PACKET_SIZE_ZK6 = 28;

    public static function encode(
        User $user,
        int $packetSize = self::PACKET_SIZE_ZK8,
        string $encoding = NameField::DEFAULT_ENCODING,
    ): string {
        $password = $user->password ?? '';
        $card = $user->cardNumber !== null ? (int) $user->cardNumber : 0;

        return $packetSize === self::PACKET_SIZE_ZK6
            ? self::encode28($user, $password, $card, $encoding)
            : self::encode72($user, $password, $card, $encoding);
    }

    private static function encode28(User $user, string $password, int $card, string $encoding): string
    {
        return pack('v', $user->uid)
            .pack('C', $user->privilege->value)
            .str_pad(substr($password, 0, 5), 5, "\0")
            .NameField::pack($user->name, 8, $encoding)
            .pack('V', $card)
            ."\0"                       // reserved pad
            .pack('C', $user->groupId)
            .pack('v', 0)               // timezone (unused)
            .pack('V', (int) $user->userId);
    }

    private static function encode72(User $user, string $password, int $card, string $encoding): string
    {
        return pack('v', $user->uid)
            .pack('C', $user->privilege->value)
            .str_pad(substr($password, 0, 8), 8, "\0")
            .NameField::pack($user->name, 24, $encoding)
            .pack('V', $card)
            ."\0"                       // reserved pad
            .str_pad(substr((string) $user->groupId, 0, 7), 7, "\0")
            ."\0"                       // reserved pad
            .str_pad(substr($user->userId, 0, 24), 24, "\0");
    }
}
