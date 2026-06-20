<?php

declare(strict_types=1);

use ZkTeco\ADMS\Parsing\AttlogParser;
use ZkTeco\Enums\PunchState;
use ZkTeco\Enums\VerifyMode;

it('parses a tab-separated ATTLOG row into an attendance record', function () {
    $body = "1001\t2026-06-19 14:30:15\t0\t1\t0\t0\t0";

    $records = (new AttlogParser)->parse($body);

    expect($records)->toHaveCount(1);
    expect($records[0]->userId)->toBe('1001')
        ->and($records[0]->recordedAt->format('Y-m-d H:i:s'))->toBe('2026-06-19 14:30:15')
        ->and($records[0]->punchState)->toBe(PunchState::CheckIn)
        ->and($records[0]->verifyMode)->toBe(VerifyMode::Fingerprint)
        ->and($records[0]->uid)->toBeNull();
});

it('parses multiple rows and ignores blank lines', function () {
    $body = "1\t2026-06-19 08:00:00\t0\t1\n\n2\t2026-06-19 17:00:00\t1\t1\n";

    $records = (new AttlogParser)->parse($body);

    expect($records)->toHaveCount(2)
        ->and($records[0]->punchState)->toBe(PunchState::CheckIn)
        ->and($records[1]->punchState)->toBe(PunchState::CheckOut);
});

it('defaults punch state and verify mode when trailing fields are absent', function () {
    $records = (new AttlogParser)->parse("7\t2026-06-19 09:15:00");

    expect($records[0]->punchState)->toBe(PunchState::Undefined)
        ->and($records[0]->verifyMode)->toBe(VerifyMode::Other);
});

it('skips rows missing a user id or a parseable timestamp', function () {
    $body = "\t2026-06-19 09:00:00\t0\t1\n5\tnot-a-date\t0\t1\n5\t\t0";

    expect((new AttlogParser)->parse($body))->toBe([]);
});

it('returns nothing for an empty body', function () {
    expect((new AttlogParser)->parse(''))->toBe([]);
});
