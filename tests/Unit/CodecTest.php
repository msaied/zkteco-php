<?php

declare(strict_types=1);

use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;

it('computes the ones-complement checksum over 16-bit words', function () {
    // Header for CMD_CONNECT with a zero checksum field: the single non-zero
    // word is 1000, so ~1000 folded into 16 bits is 64534.
    expect(Codec::checksum(pack('vvvv', 1000, 0, 0, 0)))->toBe(64534)
        ->and(Codec::checksum("\x01\x02"))->toBe(65021) // ~0x0201
        ->and(Codec::checksum("\x01"))->toBe(65533); // trailing odd byte: ~1
});

it('builds a request with the checksum over the pre-increment reply id', function () {
    // The packet ships reply id + 1 (here 1) but the checksum (64534) is taken
    // over the header carrying reply id 0 — pyzk's create_header quirk.
    expect((new Codec)->build(Command::Connect, '', 0, 0))
        ->toBe(pack('vvvv', 1000, 64534, 0, 1));
});

it('wraps the reply id at USHRT_MAX rather than 65536', function () {
    $packet = (new Codec)->build(Command::Connect, '', 0, Codec::USHRT_MAX - 1);

    // Reply id 65534 + 1 = 65535 wraps to 0 in the trailing two bytes.
    expect(substr($packet, 6, 2))->toBe("\x00\x00");
});

it('parses a response header and trailing payload', function () {
    $packet = (new Codec)->parse(pack('vvvv', Command::AckOk->value, 0, 5, 2).'payload');

    expect($packet->command)->toBe(Command::AckOk->value)
        ->and($packet->sessionId)->toBe(5)
        ->and($packet->replyId)->toBe(2)
        ->and($packet->payload)->toBe('payload')
        ->and($packet->isOk())->toBeTrue();
});

it('rejects a response shorter than the 8-byte header', function () {
    (new Codec)->parse("\x01\x02\x03");
})->throws(ResponseException::class);
