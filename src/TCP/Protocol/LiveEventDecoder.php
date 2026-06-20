<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;
use ZkTeco\Values\AttendanceRecord;
use ZkTeco\Values\User;

/**
 * Decodes the event payload pushed during live capture (CMD_REG_EVENT) into
 * {@see AttendanceRecord} values.
 *
 * Each record is `user_id`, `status`, `punch`, then a 6-byte timehex; only the
 * width of `user_id` varies (uint16, uint32, or a 24-byte string). Firmware
 * signals which by the payload length, so the layout is picked from it exactly
 * as pyzk's live_capture does. This is firmware-variant-sensitive — see
 * docs/adr/0005.
 */
final class LiveEventDecoder
{
    /**
     * @param  list<User>  $users  resolves user id -> device-local uid
     * @return list<AttendanceRecord>
     */
    public static function decode(string $data, array $users = []): array
    {
        $uidByUserId = [];
        foreach ($users as $user) {
            $uidByUserId[$user->userId] = $user->uid;
        }

        $records = [];

        while (strlen($data) >= 10) {
            $layout = self::layoutFor(strlen($data));

            // An unrecognised length: stop rather than spin or read garbage.
            if ($layout === null) {
                break;
            }

            [$idCode, $consumed] = $layout;
            $fields = self::unpackRecord($data, $idCode);
            $data = substr($data, $consumed);

            $userId = $fields['userId'];

            $records[] = new AttendanceRecord(
                userId: $userId,
                recordedAt: TimeCodec::decodeHex($fields['time']),
                verifyMode: VerifyMode::fromWire($fields['status']),
                punchState: PunchState::tryFrom($fields['punch']) ?? PunchState::Undefined,
                uid: $uidByUserId[$userId] ?? (ctype_digit($userId) ? (int) $userId : null),
            );
        }

        return $records;
    }

    /**
     * Map a payload length to its [user-id format code, bytes consumed].
     *
     * @return array{0: string, 1: int}|null
     */
    private static function layoutFor(int $length): ?array
    {
        return match (true) {
            $length === 10 => ['v', 10],
            $length === 12 => ['V', 12],
            $length === 14 => ['v', 14],
            $length === 32 => ['a24', 32],
            $length === 36 => ['a24', 36],
            $length === 37 => ['a24', 37],
            $length >= 52 => ['a24', 52],
            default => null,
        };
    }

    /**
     * @return array{userId: string, status: int, punch: int, time: string}
     */
    private static function unpackRecord(string $data, string $idCode): array
    {
        if ($idCode === 'a24') {
            /** @var array{user: string, status: int, punch: int, time: string} $f */
            $f = unpack('a24user/Cstatus/Cpunch/a6time', substr($data, 0, 32));
            $userId = Bytes::cutNull($f['user']);
        } else {
            $idWidth = $idCode === 'V' ? 4 : 2;
            /** @var array{user: int, status: int, punch: int, time: string} $f */
            $f = unpack("{$idCode}user/Cstatus/Cpunch/a6time", substr($data, 0, $idWidth + 8));
            $userId = (string) $f['user'];
        }

        return ['userId' => $userId, 'status' => $f['status'], 'punch' => $f['punch'], 'time' => $f['time']];
    }
}
