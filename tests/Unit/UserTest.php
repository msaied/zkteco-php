<?php

declare(strict_types=1);

use ZkTeco\Enums\Privilege;
use ZkTeco\Values\User;

it('keeps the device uid and the human-facing user id distinct', function () {
    $user = new User(uid: 1, userId: '1001', name: 'Ada Lovelace');

    expect($user->uid)->toBe(1)
        ->and($user->userId)->toBe('1001')
        ->and($user->privilege)->toBe(Privilege::User);
});

it('defaults to the regular-user privilege', function () {
    $user = new User(uid: 2, userId: '1002', name: 'Alan Turing');

    expect($user->privilege)->toBe(Privilege::User);
});
