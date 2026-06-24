<?php

declare(strict_types=1);

use ZkTeco\Enums\Privilege;
use ZkTeco\TCP\Protocol\UserDecoder;
use ZkTeco\TCP\Protocol\UserEncoder;
use ZkTeco\Values\User;

it('encodes the 72-byte layout byte-for-byte', function () {
    $user = new User(
        uid: 1,
        userId: '1001',
        name: 'Alice',
        privilege: Privilege::Admin,
        password: '1234',
        cardNumber: '5566',
        groupId: 2,
    );

    $expected = pack('v', 1)
        .pack('C', 14)
        .str_pad('1234', 8, "\0")
        .str_pad('Alice', 24, "\0")
        .pack('V', 5566)
        ."\0"
        .str_pad('2', 7, "\0")
        ."\0"
        .str_pad('1001', 24, "\0");

    expect(UserEncoder::encode($user))->toBe($expected)
        ->and(strlen(UserEncoder::encode($user)))->toBe(72);
});

it('encodes the 28-byte layout byte-for-byte', function () {
    $user = new User(
        uid: 2,
        userId: '2002',
        name: 'Bob',
        privilege: Privilege::User,
        password: null,
        cardNumber: null,
        groupId: 3,
    );

    $expected = pack('v', 2)
        .pack('C', 0)
        .str_pad('', 5, "\0")
        .str_pad('Bob', 8, "\0")
        .pack('V', 0)
        ."\0"
        .pack('C', 3)
        .pack('v', 0)
        .pack('V', 2002);

    expect(UserEncoder::encode($user, UserEncoder::PACKET_SIZE_ZK6))->toBe($expected)
        ->and(strlen(UserEncoder::encode($user, UserEncoder::PACKET_SIZE_ZK6)))->toBe(28);
});

it('round-trips a user through encode then decode (72-byte)', function () {
    $user = new User(
        uid: 7,
        userId: '7007',
        name: 'Grace',
        privilege: Privilege::Admin,
        password: 'secret',
        cardNumber: '424242',
        groupId: 5,
    );

    $buffer = pack('V', 72).UserEncoder::encode($user);
    $decoded = UserDecoder::decode($buffer, 1)[0];

    expect($decoded->uid)->toBe(7)
        ->and($decoded->userId)->toBe('7007')
        ->and($decoded->name)->toBe('Grace')
        ->and($decoded->privilege)->toBe(Privilege::Admin)
        ->and($decoded->password)->toBe('secret')
        ->and($decoded->cardNumber)->toBe('424242')
        ->and($decoded->groupId)->toBe(5);
});

it('round-trips an Arabic name through the device codepage', function () {
    $user = new User(
        uid: 3,
        userId: '3003',
        name: 'محمد علي',
        privilege: Privilege::User,
    );

    $record = UserEncoder::encode($user, encoding: 'Windows-1256');

    // The name field holds single-byte CP1256, not the longer UTF-8 form the
    // device would otherwise render as garbage.
    $nameField = substr($record, 11, 24);
    expect(rtrim($nameField, "\0"))->toBe(iconv('UTF-8', 'Windows-1256', 'محمد علي'));

    $decoded = UserDecoder::decode(pack('V', 72).$record, 1, 'Windows-1256')[0];
    expect($decoded->name)->toBe('محمد علي');
});

it('truncates over-long fields to the layout width', function () {
    $user = new User(
        uid: 1,
        userId: str_repeat('9', 40),
        name: str_repeat('N', 40),
        password: str_repeat('p', 12),
    );

    $record = UserEncoder::encode($user);

    expect(strlen($record))->toBe(72);

    $decoded = UserDecoder::decode(pack('V', 72).$record, 1)[0];
    expect(strlen($decoded->name))->toBe(24)
        ->and(strlen($decoded->userId))->toBe(24)
        ->and(strlen($decoded->password))->toBe(8);
});
