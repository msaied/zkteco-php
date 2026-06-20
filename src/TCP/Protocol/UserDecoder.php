<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

use ZkTeco\Enums\Privilege;
use ZkTeco\Values\User;

/**
 * Decodes the raw user buffer returned by CMD_USERTEMP_RRQ into {@see User}
 * values.
 *
 * The buffer is a 4-byte little-endian total size followed by fixed-width
 * records. Firmware uses either a 28- or 72-byte layout; the width is the total
 * divided by the device's user count (pyzk's get_users). Field offsets and
 * types are ported character-for-character from pyzk's unpack formats
 * (`<HB5s8sIxBhI` and `<HB8s24sIx7sx24s`).
 */
final class UserDecoder
{
    /**
     * @return list<User>
     */
    public static function decode(string $buffer, int $userCount): array
    {
        if ($userCount <= 0 || strlen($buffer) <= 4) {
            return [];
        }

        $total = Bytes::uint32(substr($buffer, 0, 4));
        $body = substr($buffer, 4);
        $packetSize = intdiv($total, $userCount);

        $users = [];

        if ($packetSize === 28) {
            for ($offset = 0; $offset + 28 <= strlen($body); $offset += 28) {
                $users[] = self::decode28(substr($body, $offset, 28));
            }
        } else {
            for ($offset = 0; $offset + 72 <= strlen($body); $offset += 72) {
                $users[] = self::decode72(substr($body, $offset, 72));
            }
        }

        return $users;
    }

    private static function decode28(string $record): User
    {
        /** @var array{uid: int, privilege: int, password: string, name: string, card: int, group: int, userid: int} $f */
        $f = unpack('vuid/Cprivilege/a5password/a8name/Vcard/@21/Cgroup/@24/Vuserid', $record);

        $userId = (string) $f['userid'];

        return new User(
            uid: $f['uid'],
            userId: $userId,
            name: self::nameOr(trim(Bytes::cutNull($f['name'])), $userId),
            privilege: Privilege::tryFrom($f['privilege']) ?? Privilege::User,
            password: self::optional(Bytes::cutNull($f['password'])),
            cardNumber: $f['card'] !== 0 ? (string) $f['card'] : null,
            groupId: $f['group'],
        );
    }

    private static function decode72(string $record): User
    {
        /** @var array{uid: int, privilege: int, password: string, name: string, card: int, group: string, userid: string} $f */
        $f = unpack('vuid/Cprivilege/a8password/a24name/Vcard/@40/a7group/@48/a24userid', $record);

        $userId = Bytes::cutNull($f['userid']);

        return new User(
            uid: $f['uid'],
            userId: $userId,
            name: self::nameOr(trim(Bytes::cutNull($f['name'])), $userId),
            privilege: Privilege::tryFrom($f['privilege']) ?? Privilege::User,
            password: self::optional(Bytes::cutNull($f['password'])),
            cardNumber: $f['card'] !== 0 ? (string) $f['card'] : null,
            groupId: (int) trim(Bytes::cutNull($f['group'])),
        );
    }

    private static function nameOr(string $name, string $userId): string
    {
        return $name !== '' ? $name : "NN-{$userId}";
    }

    private static function optional(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }
}
