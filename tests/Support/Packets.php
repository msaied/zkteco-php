<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\TCP\Protocol\Command;

/**
 * Builds raw response packets for {@see FakeTransport}. The checksum field is
 * left zero because Codec::parse does not validate it on the way in.
 */
final class Packets
{
    public static function response(Command $command, int $sessionId = 0, int $replyId = 0, string $payload = ''): string
    {
        return pack('vvvv', $command->value, 0, $sessionId, $replyId).$payload;
    }
}
