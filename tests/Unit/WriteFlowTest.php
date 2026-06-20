<?php

declare(strict_types=1);

use ZkTeco\Enums\Privilege;
use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\UserEncoder;
use ZkTeco\TCP\Services\AttendanceService;
use ZkTeco\TCP\Services\UserService;
use ZkTeco\Tests\Support\FakeTransport;
use ZkTeco\Values\User;

it('writes a user then refreshes the device tables', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // user write
        responsePacket(Command::AckOk, sessionId: 1),  // refresh data
    ]);

    $user = new User(uid: 1, userId: '1001', name: 'Alice', privilege: Privilege::Admin);

    (new UserService(openedSession($transport)))->save($user);

    expect($transport->sent)->toHaveCount(3);

    $write = (new Codec)->parse($transport->sent[1]);
    expect($write->command)->toBe(Command::UserWrite->value)
        ->and($write->payload)->toBe(UserEncoder::encode($user));

    expect((new Codec)->parse($transport->sent[2])->command)->toBe(Command::RefreshData->value);
});

it('deletes a user by uid then refreshes', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // delete
        responsePacket(Command::AckOk, sessionId: 1),  // refresh data
    ]);

    (new UserService(openedSession($transport)))->delete(5);

    $delete = (new Codec)->parse($transport->sent[1]);
    expect($delete->command)->toBe(Command::DeleteUser->value)
        ->and($delete->payload)->toBe(pack('v', 5));

    expect((new Codec)->parse($transport->sent[2])->command)->toBe(Command::RefreshData->value);
});

it('clears all user data without refreshing', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // clear data
    ]);

    (new UserService(openedSession($transport)))->clear();

    expect($transport->sent)->toHaveCount(2)
        ->and((new Codec)->parse($transport->sent[1])->command)->toBe(Command::ClearData->value);
});

it('clears the attendance log', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // clear attlog
    ]);

    (new AttendanceService(openedSession($transport)))->clear();

    expect((new Codec)->parse($transport->sent[1])->command)->toBe(Command::ClearAttlog->value);
});

it('throws when the device rejects a write', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),     // connect
        responsePacket(Command::AckError, sessionId: 1),  // user write rejected
    ]);

    $service = new UserService(openedSession($transport));

    expect(fn () => $service->save(new User(uid: 1, userId: '1', name: 'X')))
        ->toThrow(ResponseException::class);
});

it('finds a user by uid from the full list', function () {
    $record = pack('v', 4)
        .pack('C', 0)
        .str_pad('', 8, "\0")
        .str_pad('Dave', 24, "\0")
        .pack('V', 0)
        ."\0"
        .str_pad('0', 7, "\0")
        ."\0"
        .str_pad('4004', 24, "\0");

    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),                            // connect
        responsePacket(Command::AckOk, sessionId: 1, payload: freeSizes(1, 0)),  // read sizes
        responsePacket(Command::Data, sessionId: 1, payload: pack('V', 72).$record), // user buffer
    ]);

    $user = (new UserService(openedSession($transport)))->find(4);

    expect($user)->not->toBeNull()
        ->and($user->userId)->toBe('4004')
        ->and($user->name)->toBe('Dave');
});
