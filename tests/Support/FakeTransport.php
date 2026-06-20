<?php

declare(strict_types=1);

namespace ZkTeco\Tests\Support;

use ZkTeco\Exceptions\NetworkException;
use ZkTeco\TCP\Connection\Transport;

/**
 * An in-memory {@see Transport} for exercising the protocol layer without a
 * device. Scripted responses are returned from {@see receive()} in order, and
 * every sent packet is recorded for assertions.
 *
 * Once the script is exhausted, {@see receive()} behaves like a closed socket
 * and throws {@see NetworkException::connectionClosed()} — the way a real device
 * ending the stream would surface.
 */
final class FakeTransport implements Transport
{
    private bool $connected = false;

    /** @var list<string> */
    public array $sent = [];

    /**
     * @param  list<string>  $responses  raw packets {@see receive()} returns in order
     */
    public function __construct(private array $responses = []) {}

    public function connect(): void
    {
        $this->connected = true;
    }

    public function send(string $payload): void
    {
        $this->sent[] = $payload;
    }

    public function receive(): string
    {
        if ($this->responses === []) {
            throw NetworkException::connectionClosed();
        }

        return array_shift($this->responses);
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function setTimeout(float $seconds): void
    {
        // No-op: the in-memory transport has no socket timeout.
    }

    public function getTimeout(): float
    {
        return 0.0;
    }
}
