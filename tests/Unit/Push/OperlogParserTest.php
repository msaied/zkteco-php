<?php

declare(strict_types=1);

use ZkTeco\ADMS\Parsing\OperlogParser;
use ZkTeco\Enums\OperationType;
use ZkTeco\Enums\Privilege;

it('parses an OPLOG row into an operation log entry', function () {
    $batch = (new OperlogParser)->parse("OPLOG\t6\t1\t1001\t2026-06-19 09:00:00\t0\t0");

    expect($batch->operations)->toHaveCount(1)
        ->and($batch->users)->toBe([]);

    $entry = $batch->operations[0];
    expect($entry->code)->toBe(6)
        ->and($entry->operation)->toBe(OperationType::FingerprintEnrolled)
        ->and($entry->operatorId)->toBe('1')
        ->and($entry->target)->toBe('1001')
        ->and($entry->occurredAt->format('Y-m-d H:i:s'))->toBe('2026-06-19 09:00:00')
        ->and($entry->parameters)->toBe(['0', '0']);
});

it('anchors an OPLOG row on its timestamp when columns before it are absent', function () {
    $batch = (new OperlogParser)->parse("OPLOG\t0\t1\t2026-01-01 00:00:00");

    $entry = $batch->operations[0];
    expect($entry->operation)->toBe(OperationType::Startup)
        ->and($entry->operatorId)->toBe('1')
        ->and($entry->target)->toBe('')
        ->and($entry->parameters)->toBe([]);
});

it('parses a USER row into a User keyed by PIN with no device slot', function () {
    $batch = (new OperlogParser)->parse("USER PIN=1001\tName=Alice\tPri=14\tPasswd=secret\tCard=12345\tGrp=2");

    expect($batch->users)->toHaveCount(1)
        ->and($batch->operations)->toBe([]);

    $user = $batch->users[0];
    expect($user->uid)->toBe(0)
        ->and($user->userId)->toBe('1001')
        ->and($user->name)->toBe('Alice')
        ->and($user->privilege)->toBe(Privilege::Admin)
        ->and($user->password)->toBe('secret')
        ->and($user->cardNumber)->toBe('12345')
        ->and($user->groupId)->toBe(2);
});

it('treats an empty or zero card and empty password as absent', function () {
    $batch = (new OperlogParser)->parse("USER PIN=7\tName=Bob\tPri=0\tPasswd=\tCard=0");

    $user = $batch->users[0];
    expect($user->privilege)->toBe(Privilege::User)
        ->and($user->password)->toBeNull()
        ->and($user->cardNumber)->toBeNull();
});

it('demultiplexes a mixed OPERLOG body and skips record kinds it does not own', function () {
    $body = implode("\n", [
        "OPLOG\t9\t1\t2002\t2026-06-19 12:00:00",
        "USER PIN=2002\tName=Carol\tPri=0",
        "FP PIN=2002\tFID=0\tValid=1\tTMP=abc",
        'GARBAGE anything at all',
    ]);

    $batch = (new OperlogParser)->parse($body);

    expect($batch->operations)->toHaveCount(1)
        ->and($batch->operations[0]->operation)->toBe(OperationType::UserDeleted)
        ->and($batch->users)->toHaveCount(1)
        ->and($batch->users[0]->userId)->toBe('2002')
        ->and($batch->count())->toBe(2);
});

it('skips an OPLOG row with no parseable timestamp and a USER row with no PIN', function () {
    $body = "OPLOG\t4\t1\tnot-a-date\nUSER Name=Nopin\tPri=0";

    expect((new OperlogParser)->parse($body)->isEmpty())->toBeTrue();
});

it('returns an empty batch for an empty body', function () {
    $batch = (new OperlogParser)->parse('');

    expect($batch->isEmpty())->toBeTrue()
        ->and($batch->operations)->toBe([])
        ->and($batch->users)->toBe([]);
});
