<?php

declare(strict_types=1);

use ZkTeco\Exceptions\ConnectionException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\CommKey;
use ZkTeco\Tests\Support\FakeTransport;

/**
 * Build a raw response packet. The checksum field is irrelevant here because
 * Codec::parse does not validate it on the way in.
 */
function responsePacket(Command $command, int $sessionId = 0, int $replyId = 0, string $payload = ''): string
{
    return pack('vvvv', $command->value, 0, $sessionId, $replyId).$payload;
}

it('connects without auth when the device acknowledges immediately', function () {
    $transport = new FakeTransport([responsePacket(Command::AckOk, sessionId: 42)]);
    $session = new Session($transport, new Codec);

    $session->open();

    expect($transport->isConnected())->toBeTrue()
        ->and($transport->sent)->toHaveCount(1);

    // The single packet sent is CMD_CONNECT.
    expect((new Codec)->parse($transport->sent[0])->command)->toBe(Command::Connect->value);
});

it('authenticates with the comm-key token when the device demands it', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckUnauthorized, sessionId: 42),
        responsePacket(Command::AckOk, sessionId: 42),
    ]);
    $session = new Session($transport, new Codec, commKey: 123456);

    $session->open();

    expect($transport->sent)->toHaveCount(2);

    // The second packet is CMD_AUTH carrying the token derived from the
    // session id the device assigned (42), not the seed value.
    $auth = (new Codec)->parse($transport->sent[1]);
    expect($auth->command)->toBe(Command::Auth->value)
        ->and($auth->payload)->toBe((new CommKey(123456, 42))->token());
});

it('throws when the comm key is rejected', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckUnauthorized, sessionId: 42),
        responsePacket(Command::AckUnauthorized, sessionId: 42),
    ]);
    $session = new Session($transport, new Codec, commKey: 999);

    expect(fn () => $session->open())->toThrow(ConnectionException::class);
    expect($transport->isConnected())->toBeFalse();
});

it('refuses commands before the session is open', function () {
    $session = new Session(new FakeTransport, new Codec);

    expect(fn () => $session->command(Command::Version))->toThrow(ConnectionException::class);
});
