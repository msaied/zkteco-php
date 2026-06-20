<?php

declare(strict_types=1);

use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;
use ZkTeco\TCP\Protocol\LiveEventDecoder;
use ZkTeco\Values\User;

/**
 * The 6-byte realtime timestamp: year-2000, month, day, hour, minute, second.
 */
function timehex(int $year, int $month, int $day, int $hour, int $minute, int $second): string
{
    return pack('C6', $year - 2000, $month, $day, $hour, $minute, $second);
}

it('decodes the 10-byte event with a uint16 user id', function () {
    $payload = pack('v', 1001).pack('C', 1).pack('C', 0).timehex(2024, 6, 19, 14, 30, 15);

    $events = LiveEventDecoder::decode($payload);

    expect($events)->toHaveCount(1);
    expect($events[0]->userId)->toBe('1001')
        ->and($events[0]->recordedAt->format('Y-m-d H:i:s'))->toBe('2024-06-19 14:30:15')
        ->and($events[0]->verifyMode)->toBe(VerifyMode::Fingerprint)
        ->and($events[0]->punchState)->toBe(PunchState::CheckIn);
});

it('decodes the 12-byte event with a uint32 user id', function () {
    $payload = pack('V', 70000).pack('C', 4).pack('C', 1).timehex(2024, 6, 19, 14, 30, 15);

    $event = LiveEventDecoder::decode($payload)[0];

    expect($event->userId)->toBe('70000')
        ->and($event->verifyMode)->toBe(VerifyMode::Card)
        ->and($event->punchState)->toBe(PunchState::CheckOut);
});

it('decodes the 32-byte event with a string user id', function () {
    $payload = str_pad('emp-7', 24, "\0").pack('C', 15).pack('C', 0).timehex(2024, 6, 19, 14, 30, 15);

    $event = LiveEventDecoder::decode($payload)[0];

    expect($event->userId)->toBe('emp-7')
        ->and($event->verifyMode)->toBe(VerifyMode::Face)
        ->and($event->uid)->toBeNull();
});

it('decodes back-to-back 52-byte records', function () {
    $record = fn (string $id) => str_pad($id, 24, "\0")
        .pack('C', 1).pack('C', 0).timehex(2024, 6, 19, 14, 30, 15).str_repeat("\0", 20);

    $events = LiveEventDecoder::decode($record('100').$record('200'));

    expect($events)->toHaveCount(2)
        ->and($events[0]->userId)->toBe('100')
        ->and($events[1]->userId)->toBe('200');
});

it('resolves the device uid from the user list', function () {
    $payload = pack('v', 1001).pack('C', 1).pack('C', 0).timehex(2024, 6, 19, 14, 30, 15);
    $users = [new User(uid: 5, userId: '1001', name: 'Alice')];

    expect(LiveEventDecoder::decode($payload, $users)[0]->uid)->toBe(5);
});

it('falls back to a numeric user id as the uid when unknown', function () {
    $payload = pack('v', 1001).pack('C', 1).pack('C', 0).timehex(2024, 6, 19, 14, 30, 15);

    expect(LiveEventDecoder::decode($payload)[0]->uid)->toBe(1001);
});

it('stops on an unrecognised payload length', function () {
    expect(LiveEventDecoder::decode(str_repeat("\0", 11)))->toBe([])
        ->and(LiveEventDecoder::decode(''))->toBe([]);
});
