<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Protocol;

/**
 * A decoded ZK-protocol response: the reply command (typically an ACK code),
 * the session/reply ids it carried, and the raw payload that follows the header.
 */
final readonly class Packet
{
    public function __construct(
        public int $command,
        public int $sessionId,
        public int $replyId,
        public string $payload,
    ) {}

    public function isOk(): bool
    {
        return $this->command === Command::AckOk->value;
    }
}
