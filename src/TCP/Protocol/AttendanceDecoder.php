<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

use DateTimeImmutable;
use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;
use ZkTeco\Values\AttendanceRecord;
use ZkTeco\Values\User;

/**
 * Decodes the raw attendance buffer returned by CMD_ATTLOG_RRQ into
 * {@see AttendanceRecord} values.
 *
 * Like the user buffer, this is a 4-byte little-endian total size followed by
 * fixed-width records. The width (8, 16, or 40 bytes) is the total divided by
 * the device's record count. The narrow layouts omit one of the identifiers, so
 * the decoded {@see User} list is used to resolve uid <-> user id. Field offsets
 * and the timestamp formula are ported from pyzk's get_attendance.
 */
final class AttendanceDecoder
{
    /**
     * Marker some firmware prefixes to a 40-byte record block (pyzk's code_init).
     */
    private const CODE_INIT = "\xff255\x00\x00\x00\x00\x00";

    /**
     * @param  list<User>  $users  resolves the identifier the record omits
     * @return list<AttendanceRecord>
     */
    public static function decode(string $buffer, int $recordCount, array $users = []): array
    {
        if ($recordCount <= 0 || strlen($buffer) < 4) {
            return [];
        }

        $total = Bytes::uint32(substr($buffer, 0, 4));
        $body = substr($buffer, 4);
        $recordSize = intdiv($total, $recordCount);

        [$userIdByUid, $uidByUserId] = self::indexUsers($users);

        return match (true) {
            $recordSize === 8 => self::decode8($body, $userIdByUid),
            $recordSize === 16 => self::decode16($body, $uidByUserId),
            default => self::decode40($body),
        };
    }

    /**
     * @param  array<int, string>  $userIdByUid
     * @return list<AttendanceRecord>
     */
    private static function decode8(string $body, array $userIdByUid): array
    {
        $records = [];

        for ($offset = 0; $offset + 8 <= strlen($body); $offset += 8) {
            /** @var array{uid: int, status: int, time: string, punch: int} $f */
            $f = unpack('vuid/Cstatus/a4time/Cpunch', substr($body, $offset, 8));

            $records[] = new AttendanceRecord(
                userId: $userIdByUid[$f['uid']] ?? (string) $f['uid'],
                recordedAt: self::decodeTime($f['time']),
                verifyMode: VerifyMode::fromWire($f['status']),
                punchState: PunchState::tryFrom($f['punch']) ?? PunchState::Undefined,
                uid: $f['uid'],
            );
        }

        return $records;
    }

    /**
     * @param  array<string, int>  $uidByUserId
     * @return list<AttendanceRecord>
     */
    private static function decode16(string $body, array $uidByUserId): array
    {
        $records = [];

        for ($offset = 0; $offset + 16 <= strlen($body); $offset += 16) {
            /** @var array{userid: int, time: string, status: int, punch: int} $f */
            $f = unpack('Vuserid/a4time/Cstatus/Cpunch', substr($body, $offset, 16));

            $userId = (string) $f['userid'];

            $records[] = new AttendanceRecord(
                userId: $userId,
                recordedAt: self::decodeTime($f['time']),
                verifyMode: VerifyMode::fromWire($f['status']),
                punchState: PunchState::tryFrom($f['punch']) ?? PunchState::Undefined,
                uid: $uidByUserId[$userId] ?? null,
            );
        }

        return $records;
    }

    /**
     * @return list<AttendanceRecord>
     */
    private static function decode40(string $body): array
    {
        $records = [];

        while (strlen($body) >= 40) {
            if (str_starts_with($body, self::CODE_INIT)) {
                $body = substr($body, strlen(self::CODE_INIT));

                if (strlen($body) < 40) {
                    break;
                }
            }

            /** @var array{uid: int, userid: string, status: int, time: string, punch: int} $f */
            $f = unpack('vuid/a24userid/Cstatus/a4time/Cpunch', substr($body, 0, 40));

            $records[] = new AttendanceRecord(
                userId: Bytes::cutNull($f['userid']),
                recordedAt: self::decodeTime($f['time']),
                verifyMode: VerifyMode::fromWire($f['status']),
                punchState: PunchState::tryFrom($f['punch']) ?? PunchState::Undefined,
                uid: $f['uid'],
            );

            $body = substr($body, 40);
        }

        return $records;
    }

    /**
     * Decode the 4-byte little-endian device timestamp (pyzk's __decode_time).
     */
    private static function decodeTime(string $bytes): DateTimeImmutable
    {
        return TimeCodec::decode($bytes);
    }

    /**
     * @param  list<User>  $users
     * @return array{0: array<int, string>, 1: array<string, int>}
     */
    private static function indexUsers(array $users): array
    {
        $userIdByUid = [];
        $uidByUserId = [];

        foreach ($users as $user) {
            $userIdByUid[$user->uid] = $user->userId;
            $uidByUserId[$user->userId] = $user->uid;
        }

        return [$userIdByUid, $uidByUserId];
    }
}
