<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Connection;

use ZkTeco\Exceptions\NetworkException;
use ZkTeco\TCP\Protocol\Codec;

/**
 * A raw byte channel to a device. Implementations wrap TCP or UDP sockets; the
 * protocol layer is written against this interface so the transport can be
 * swapped without touching command logic (see docs/adr — "TCP first, UDP behind
 * the same interface").
 */
interface Transport
{
    /**
     * Open the underlying socket.
     *
     * @throws NetworkException when the socket cannot be opened.
     */
    public function connect(): void;

    /**
     * Write the full payload to the socket.
     *
     * @throws NetworkException on a short or failed write.
     */
    public function send(string $payload): void;

    /**
     * Read and return the next complete protocol packet — the bytes the
     * {@see Codec} parses. The implementation reassembles any
     * transport-level framing (e.g. the TCP length tag) so callers always see a
     * whole packet.
     *
     * @throws NetworkException on timeout or a failed read.
     */
    public function receive(): string;

    /**
     * Close the underlying socket. Safe to call when already closed.
     */
    public function close(): void;

    public function isConnected(): bool;

    /**
     * Change the read timeout (seconds) for subsequent {@see receive()} calls.
     * Used to widen the window while waiting for an interactive event, or to
     * shorten it while draining pending packets.
     */
    public function setTimeout(float $seconds): void;

    /**
     * The current read timeout in seconds.
     */
    public function getTimeout(): float;
}
