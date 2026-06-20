<?php

declare(strict_types=1);

use ZkTeco\Enums\Privilege;
use ZkTeco\TCP\Protocol\TemplateEncoder;
use ZkTeco\Values\Template;
use ZkTeco\Values\User;

it('lays out the head, user, table and template blocks in order', function () {
    $user = new User(uid: 7, userId: '99', name: 'Bob', privilege: Privilege::User);
    $templates = [
        new Template(uid: 7, fingerIndex: 0, valid: true, data: 'AAAA'),
        new Template(uid: 7, fingerIndex: 3, valid: true, data: 'BBBBBB'),
    ];

    $buffer = TemplateEncoder::encode($user, $templates);

    /** @var array{ulen: int, tlen: int, flen: int} $head */
    $head = unpack('Vulen/Vtlen/Vflen', substr($buffer, 0, 12));

    expect($head['ulen'])->toBe(73)            // one repack73 record
        ->and($head['tlen'])->toBe(16)         // two 8-byte table entries
        ->and($head['flen'])->toBe(14);        // (2 + 4) + (2 + 6)

    $userBlock = substr($buffer, 12, $head['ulen']);
    expect(ord($userBlock[0]))->toBe(2);       // 0x02 tag
    expect(unpack('v', substr($userBlock, 1, 2))[1])->toBe(7);   // uid
    expect(ord($userBlock[3]))->toBe(Privilege::User->value);    // privilege
    expect(ord($userBlock[40]))->toBe(1);      // 0x01 flag after the 4-byte card
});

it('offsets each finger index by 0x10 and chains template offsets', function () {
    $user = new User(uid: 7, userId: '99', name: 'Bob');
    $templates = [
        new Template(uid: 7, fingerIndex: 0, valid: true, data: 'AAAA'),
        new Template(uid: 7, fingerIndex: 3, valid: true, data: 'BBBBBB'),
    ];

    $buffer = TemplateEncoder::encode($user, $templates);
    $table = substr($buffer, 12 + 73, 16);

    /** @var array{tag: int, uid: int, fid: int, offset: int} $first */
    $first = unpack('ctag/vuid/cfid/Voffset', substr($table, 0, 8));
    expect($first['tag'])->toBe(2)
        ->and($first['uid'])->toBe(7)
        ->and($first['fid'])->toBe(0x10)       // 0x10 + finger 0
        ->and($first['offset'])->toBe(0);

    /** @var array{tag: int, uid: int, fid: int, offset: int} $second */
    $second = unpack('ctag/vuid/cfid/Voffset', substr($table, 8, 8));
    expect($second['fid'])->toBe(0x13)         // 0x10 + finger 3
        ->and($second['offset'])->toBe(6);     // length of the first packed template

    $templateBlock = substr($buffer, 12 + 73 + 16);
    expect(unpack('v', substr($templateBlock, 0, 2))[1])->toBe(4) // first blob length
        ->and(substr($templateBlock, 2, 4))->toBe('AAAA')
        ->and(unpack('v', substr($templateBlock, 6, 2))[1])->toBe(6)
        ->and(substr($templateBlock, 8, 6))->toBe('BBBBBB');
});
