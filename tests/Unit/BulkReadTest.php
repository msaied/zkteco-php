<?php

declare(strict_types=1);

use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Services\AttendanceService;
use ZkTeco\TCP\Services\UserService;
use ZkTeco\Tests\Support\FakeTransport;
use ZkTeco\Tests\Support\Packets;

/**
 * An 80-byte CMD_GET_FREE_SIZES payload with the user/finger/record counts in
 * place (fields 4, 6 and 8 respectively, matching pyzk's read_sizes).
 */
function freeSizes(int $users, int $records, int $fingers = 0): string
{
    $fields = array_fill(0, 20, 0);
    $fields[4] = $users;
    $fields[6] = $fingers;
    $fields[8] = $records;

    return pack('V20', ...$fields);
}

function openedSession(FakeTransport $transport): Session
{
    $session = new Session($transport, new Codec);
    $session->open();

    return $session;
}

it('reads small datasets inline from the prepare response', function () {
    $blob = pack('V', 4).'DATA';
    $transport = new FakeTransport([
        Packets::response(Command::AckOk, sessionId: 1),       // connect
        Packets::response(Command::Data, sessionId: 1, payload: $blob), // prepare -> inline data
    ]);

    expect(openedSession($transport)->readBuffer(Command::AttlogRead))->toBe($blob);
});

it('reads large datasets back in chunks then frees the buffer', function () {
    $chunk = str_repeat('X', 200);
    $transport = new FakeTransport([
        Packets::response(Command::AckOk, sessionId: 1),                       // connect
        Packets::response(Command::AckOk, sessionId: 1, payload: "\0".pack('V', strlen($chunk))), // prepare -> size
        Packets::response(Command::Data, sessionId: 1, payload: $chunk),       // read buffer chunk
        Packets::response(Command::AckOk, sessionId: 1),                       // free data
    ]);

    expect(openedSession($transport)->readBuffer(Command::UserTempRead, 5))->toBe($chunk);
});

it('reads the device memory counters', function () {
    $transport = new FakeTransport([
        Packets::response(Command::AckOk, sessionId: 1),
        Packets::response(Command::AckOk, sessionId: 1, payload: freeSizes(users: 12, records: 340)),
    ]);

    $sizes = openedSession($transport)->readSizes();

    expect($sizes['users'])->toBe(12)
        ->and($sizes['records'])->toBe(340);
});

it('pulls and decodes the user list end to end', function () {
    $record = pack('v', 1)         // uid
        .pack('C', 14)             // privilege (Admin)
        .str_pad('', 8, "\0")      // password
        .str_pad('Alice', 24, "\0")
        .pack('V', 0)              // card
        ."\0"
        .str_pad('1', 7, "\0")     // group
        ."\0"
        .str_pad('1001', 24, "\0"); // user id
    $blob = pack('V', 72).$record;

    $transport = new FakeTransport([
        Packets::response(Command::AckOk, sessionId: 1),                          // connect
        Packets::response(Command::AckOk, sessionId: 1, payload: freeSizes(1, 0)), // read sizes
        Packets::response(Command::Data, sessionId: 1, payload: $blob),           // user buffer (inline)
    ]);

    $users = (new UserService(openedSession($transport)))->all();

    expect($users)->toHaveCount(1)
        ->and($users[0]->userId)->toBe('1001')
        ->and($users[0]->name)->toBe('Alice');
});

it('pulls attendance and resolves the user id from the user list', function () {
    $userRecord = pack('v', 1)
        .pack('C', 0)
        .str_pad('', 8, "\0")
        .str_pad('Alice', 24, "\0")
        .pack('V', 0)
        ."\0"
        .str_pad('1', 7, "\0")
        ."\0"
        .str_pad('1001', 24, "\0");
    $userBlob = pack('V', 72).$userRecord;

    // 8-byte attendance record (uid only) -> user id comes from the user list.
    $attRecord = pack('v', 1).pack('C', 1).pack('V', 786378615).pack('C', 0);
    $attBlob = pack('V', 8).$attRecord;

    $transport = new FakeTransport([
        Packets::response(Command::AckOk, sessionId: 1),                           // connect
        Packets::response(Command::AckOk, sessionId: 1, payload: freeSizes(1, 1)), // attendance read sizes
        Packets::response(Command::AckOk, sessionId: 1, payload: freeSizes(1, 1)), // user read sizes
        Packets::response(Command::Data, sessionId: 1, payload: $userBlob),        // user buffer
        Packets::response(Command::Data, sessionId: 1, payload: $attBlob),         // attendance buffer
    ]);

    $records = (new AttendanceService(openedSession($transport)))->all();

    expect($records)->toHaveCount(1)
        ->and($records[0]->uid)->toBe(1)
        ->and($records[0]->userId)->toBe('1001')
        ->and($records[0]->recordedAt->format('Y-m-d H:i:s'))->toBe('2024-06-19 14:30:15');
});

it('returns no users when the device reports zero enrolled', function () {
    $transport = new FakeTransport([
        Packets::response(Command::AckOk, sessionId: 1),
        Packets::response(Command::AckOk, sessionId: 1, payload: freeSizes(0, 0)),
    ]);

    expect((new UserService(openedSession($transport)))->all())->toBe([]);
});
