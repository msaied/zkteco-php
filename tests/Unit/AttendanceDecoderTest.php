<?php

declare(strict_types=1);

use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;
use ZkTeco\TCP\Protocol\AttendanceDecoder;
use ZkTeco\Values\User;

// 786378615 encodes 2024-06-19 14:30:15 via the ZK time formula.
const SAMPLE_TS = 786378615;

it('decodes the 16-byte attendance layout and the device timestamp', function () {
    $record = pack('V', 1001).pack('V', SAMPLE_TS).pack('C', 1).pack('C', 0)."\0\0".pack('V', 0);
    $buffer = pack('V', 16).$record;

    $records = AttendanceDecoder::decode($buffer, recordCount: 1);

    expect($records)->toHaveCount(1);
    expect($records[0]->userId)->toBe('1001')
        ->and($records[0]->recordedAt->format('Y-m-d H:i:s'))->toBe('2024-06-19 14:30:15')
        ->and($records[0]->verifyMode)->toBe(VerifyMode::Fingerprint)
        ->and($records[0]->punchState)->toBe(PunchState::CheckIn);
});

it('decodes the 40-byte attendance layout', function () {
    $record = pack('v', 5)
        .str_pad('1001', 24, "\0")
        .pack('C', 4)          // status -> Card
        .pack('V', SAMPLE_TS)
        .pack('C', 1)          // punch -> CheckOut
        .str_repeat("\0", 8);
    $buffer = pack('V', 40).$record;

    $records = AttendanceDecoder::decode($buffer, recordCount: 1);

    expect($records[0]->uid)->toBe(5)
        ->and($records[0]->userId)->toBe('1001')
        ->and($records[0]->verifyMode)->toBe(VerifyMode::Card)
        ->and($records[0]->punchState)->toBe(PunchState::CheckOut);
});

it('resolves the user id from the uid for the 8-byte layout', function () {
    $record = pack('v', 3).pack('C', 1).pack('V', SAMPLE_TS).pack('C', 0);
    $buffer = pack('V', 8).$record;
    $users = [new User(uid: 3, userId: '3003', name: 'Carol')];

    $records = AttendanceDecoder::decode($buffer, recordCount: 1, users: $users);

    expect($records[0]->uid)->toBe(3)
        ->and($records[0]->userId)->toBe('3003');
});

it('falls back to the uid when no matching user is known', function () {
    $record = pack('v', 7).pack('C', 0).pack('V', SAMPLE_TS).pack('C', 0);
    $buffer = pack('V', 8).$record;

    expect(AttendanceDecoder::decode($buffer, 1)[0]->userId)->toBe('7');
});

it('maps an unknown verify byte to Other', function () {
    $record = pack('V', 1001).pack('V', SAMPLE_TS).pack('C', 200).pack('C', 0)."\0\0".pack('V', 0);

    expect(AttendanceDecoder::decode(pack('V', 16).$record, 1)[0]->verifyMode)->toBe(VerifyMode::Other);
});

it('returns an empty list when there are no records', function () {
    expect(AttendanceDecoder::decode(pack('V', 0), 0))->toBe([]);
});
