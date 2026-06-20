<?php

declare(strict_types=1);

use ZkTeco\TCP\Protocol\TimeCodec;

// 786378615 encodes 2024-06-19 14:30:15 via the ZK time formula.
const TIMECODEC_TS = 786378615;

it('decodes the packed device clock value', function () {
    $decoded = TimeCodec::decode(pack('V', TIMECODEC_TS));

    expect($decoded->format('Y-m-d H:i:s'))->toBe('2024-06-19 14:30:15');
});

it('encodes a datetime back into the packed value', function () {
    $time = new DateTimeImmutable('2024-06-19 14:30:15');

    expect(TimeCodec::encode($time))->toBe(pack('V', TIMECODEC_TS));
});

it('round-trips an arbitrary wall-clock', function () {
    $time = new DateTimeImmutable('2031-12-25 23:59:07');

    expect(TimeCodec::decode(TimeCodec::encode($time))->format('Y-m-d H:i:s'))
        ->toBe('2031-12-25 23:59:07');
});
