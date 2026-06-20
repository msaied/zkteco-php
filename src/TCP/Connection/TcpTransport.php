<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Connection;

use ZkTeco\Exceptions\NetworkException;
use ZkTeco\Exceptions\ResponseException;

/**
 * TCP implementation of {@see Transport}, the default for the ZK protocol on
 * port 4370.
 *
 * Over TCP, each ZK packet is prefixed with an 8-byte framing tag: two magic
 * shorts followed by the little-endian length of the packet that follows. This
 * class adds that tag on {@see send()} and strips it on {@see receive()}, so the
 * protocol layer only ever sees bare ZK packets. Built on stream sockets so no
 * PHP extension beyond the default is required.
 */
final class TcpTransport implements Transport
{
    private const MACHINE_PREPARE_DATA_1 = 20560;

    private const MACHINE_PREPARE_DATA_2 = 32130;

    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 4370,
        private float $timeout = 5.0,
    ) {}

    public function connect(): void
    {
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );

        if ($socket === false) {
            throw NetworkException::connectionFailed($this->host, $this->port, "{$errstr} [{$errno}]");
        }

        $this->socket = $socket;
        $this->applyTimeout();
    }

    public function send(string $payload): void
    {
        $socket = $this->requireSocket();

        $framed = pack('vvV', self::MACHINE_PREPARE_DATA_1, self::MACHINE_PREPARE_DATA_2, strlen($payload)).$payload;
        $total = strlen($framed);
        $written = 0;

        while ($written < $total) {
            $result = @fwrite($socket, substr($framed, $written));

            if ($result === false || $result === 0) {
                throw NetworkException::writeFailed();
            }

            $written += $result;
        }
    }

    public function receive(): string
    {
        $socket = $this->requireSocket();

        /** @var array{magic1: int, magic2: int, length: int} $tag */
        $tag = unpack('vmagic1/vmagic2/Vlength', $this->readExactly($socket, 8));

        if ($tag['magic1'] !== self::MACHINE_PREPARE_DATA_1 || $tag['magic2'] !== self::MACHINE_PREPARE_DATA_2) {
            throw ResponseException::invalidFraming();
        }

        return $this->readExactly($socket, $tag['length']);
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    public function setTimeout(float $seconds): void
    {
        $this->timeout = $seconds;
        $this->applyTimeout();
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Push the current timeout onto the open socket, if any.
     */
    private function applyTimeout(): void
    {
        if ($this->socket === null) {
            return;
        }

        $seconds = (int) $this->timeout;
        $microseconds = (int) (($this->timeout - $seconds) * 1_000_000);
        stream_set_timeout($this->socket, $seconds, $microseconds);
    }

    /**
     * Read exactly $length bytes, looping until the buffer is full.
     *
     * @param  resource  $socket
     *
     * @throws NetworkException on timeout or an early close.
     */
    private function readExactly($socket, int $length): string
    {
        if ($length === 0) {
            return '';
        }

        $buffer = '';

        while (strlen($buffer) < $length) {
            $chunk = @fread($socket, $length - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($socket);

                if (! empty($meta['timed_out'])) {
                    throw NetworkException::timeout();
                }

                throw NetworkException::connectionClosed();
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    /**
     * @return resource
     *
     * @throws NetworkException when called before {@see connect()}.
     */
    private function requireSocket()
    {
        return $this->socket ?? throw NetworkException::notConnected();
    }
}
