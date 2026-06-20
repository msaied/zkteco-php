<?php

declare(strict_types=1);

use ZkTeco\ADMS\Parsing\RtlogParser;
use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;

it('parses an RTLOG row into an attendance record', function () {
    $records = (new RtlogParser)->parse("1001\t2026-06-20 08:00:00\t0\t1\n");

    expect($records)->toHaveCount(1)
        ->and($records[0]->userId)->toBe('1001')
        ->and($records[0]->recordedAt->format('Y-m-d H:i:s'))->toBe('2026-06-20 08:00:00')
        ->and($records[0]->punchState)->toBe(PunchState::CheckIn)
        ->and($records[0]->verifyMode)->toBe(VerifyMode::Fingerprint)
        ->and($records[0]->uid)->toBeNull();
});

it('parses multiple RTLOG rows and ignores blank lines', function () {
    $body = "1001\t2026-06-20 08:00:00\t0\t1\n\n1002\t2026-06-20 17:00:00\t1\t15\n";

    $records = (new RtlogParser)->parse($body);

    expect($records)->toHaveCount(2)
        ->and($records[1]->userId)->toBe('1002')
        ->and($records[1]->punchState)->toBe(PunchState::CheckOut)
        ->and($records[1]->verifyMode)->toBe(VerifyMode::Face);
});

it('defaults the punch state and verify mode when the trailing fields are absent', function () {
    $records = (new RtlogParser)->parse("1001\t2026-06-20 08:00:00");

    expect($records)->toHaveCount(1)
        ->and($records[0]->punchState)->toBe(PunchState::Undefined)
        ->and($records[0]->verifyMode)->toBe(VerifyMode::Other);
});

it('anchors the punch columns on the timestamp despite a leading event column', function () {
    // Some firmwares prepend an event-type column, shifting the punch fields; the
    // parser locates them relative to the timestamp rather than a fixed offset.
    $records = (new RtlogParser)->parse("1001\t2026-06-20 08:00:00\t1\t3");

    expect($records[0]->punchState)->toBe(PunchState::CheckOut)
        ->and($records[0]->verifyMode)->toBe(VerifyMode::Password);
});

it('skips a row missing a PIN or a parseable timestamp', function () {
    $body = "\t2026-06-20 08:00:00\t0\t1\n1002\tnot-a-time\t0\t1\n";

    expect((new RtlogParser)->parse($body))->toBe([]);
});

it('returns nothing for an empty body', function () {
    expect((new RtlogParser)->parse(''))->toBe([]);
});
