<?php

declare(strict_types=1);

use ZkTeco\Enums\Privilege;
use ZkTeco\TCP\Protocol\UserDecoder;

/**
 * Build a 72-byte user record: <HB8s24sIx7sx24s>.
 */
function user72(int $uid, int $privilege, string $password, string $name, int $card, string $group, string $userId): string
{
    return pack('v', $uid)
        .pack('C', $privilege)
        .str_pad(substr($password, 0, 8), 8, "\0")
        .str_pad(substr($name, 0, 24), 24, "\0")
        .pack('V', $card)
        ."\0"
        .str_pad(substr($group, 0, 7), 7, "\0")
        ."\0"
        .str_pad(substr($userId, 0, 24), 24, "\0");
}

/**
 * Build a 28-byte user record: <HB5s8sIxBhI>.
 */
function user28(int $uid, int $privilege, string $password, string $name, int $card, int $group, int $userId): string
{
    return pack('v', $uid)
        .pack('C', $privilege)
        .str_pad(substr($password, 0, 5), 5, "\0")
        .str_pad(substr($name, 0, 8), 8, "\0")
        .pack('V', $card)
        ."\0"
        .pack('C', $group)
        .pack('v', 0) // timezone (ignored)
        .pack('V', $userId);
}

it('decodes the 72-byte user layout', function () {
    $record = user72(uid: 1, privilege: 14, password: '1234', name: 'Alice', card: 5566, group: '2', userId: '1001');
    $buffer = pack('V', 72).$record;

    $users = UserDecoder::decode($buffer, userCount: 1);

    expect($users)->toHaveCount(1);
    expect($users[0]->uid)->toBe(1)
        ->and($users[0]->userId)->toBe('1001')
        ->and($users[0]->name)->toBe('Alice')
        ->and($users[0]->privilege)->toBe(Privilege::Admin)
        ->and($users[0]->password)->toBe('1234')
        ->and($users[0]->cardNumber)->toBe('5566')
        ->and($users[0]->groupId)->toBe(2);
});

it('decodes the 28-byte user layout', function () {
    $record = user28(uid: 2, privilege: 0, password: '', name: 'Bob', card: 0, group: 3, userId: 2002);
    $buffer = pack('V', 28).$record;

    $users = UserDecoder::decode($buffer, userCount: 1);

    expect($users)->toHaveCount(1);
    expect($users[0]->uid)->toBe(2)
        ->and($users[0]->userId)->toBe('2002')
        ->and($users[0]->name)->toBe('Bob')
        ->and($users[0]->privilege)->toBe(Privilege::User)
        ->and($users[0]->password)->toBeNull()
        ->and($users[0]->cardNumber)->toBeNull()
        ->and($users[0]->groupId)->toBe(3);
});

it('decodes multiple records and picks the width from the user count', function () {
    $buffer = pack('V', 144)
        .user72(1, 0, '', 'A', 0, '1', '100')
        .user72(2, 0, '', 'B', 0, '1', '200');

    $users = UserDecoder::decode($buffer, userCount: 2);

    expect($users)->toHaveCount(2)
        ->and($users[0]->userId)->toBe('100')
        ->and($users[1]->userId)->toBe('200');
});

it('falls back to a placeholder name when the record has none', function () {
    $buffer = pack('V', 72).user72(9, 0, '', '', 0, '0', '777');

    expect(UserDecoder::decode($buffer, 1)[0]->name)->toBe('NN-777');
});

it('returns an empty list when there are no users', function () {
    expect(UserDecoder::decode(pack('V', 0), 0))->toBe([]);
});
