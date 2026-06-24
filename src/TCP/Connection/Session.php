<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Connection;

use Throwable;
use ZkTeco\Exceptions\ConnectionException;
use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Device;
use ZkTeco\TCP\Protocol\Bytes;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\CommKey;
use ZkTeco\TCP\Protocol\NameField;
use ZkTeco\TCP\Protocol\Packet;

/**
 * An authenticated ZK-protocol exchange over a {@see Transport}.
 *
 * Holds the negotiated session id and the incrementing reply id, and is the
 * single point through which sub-services issue commands. This is the internal
 * channel; callers reach it through {@see Device}.
 */
final class Session
{
    /**
     * Largest chunk requested per CMD_READ_BUFFER over TCP (pyzk's 0xFFc0).
     */
    private const MAX_CHUNK = 0xFFC0;

    /**
     * Largest chunk sent per CMD_DATA when uploading a buffer (pyzk's MAX_CHUNK
     * inside _send_with_buffer).
     */
    private const MAX_WRITE_CHUNK = 1024;

    private int $sessionId = 0;

    private int $replyId = 0;

    public function __construct(
        private readonly Transport $transport,
        private readonly Codec $codec,
        private readonly int $commKey = 0,
        /**
         * The device's name codepage. User names are re-encoded to this on write
         * and back to UTF-8 on read, so non-ASCII scripts (e.g. Windows-1256 for
         * Arabic) display correctly on the panel instead of as mojibake.
         */
        public readonly string $nameEncoding = NameField::DEFAULT_ENCODING,
    ) {}

    /**
     * Perform the CMD_CONNECT (and CMD_AUTH when the device demands it)
     * handshake.
     *
     * @throws ConnectionException when the device rejects the connection or the
     *                             comm key is wrong.
     */
    public function open(): void
    {
        $this->transport->connect();

        // pyzk seeds the counters this way before CMD_CONNECT; the device
        // assigns the real session id in its reply.
        $this->sessionId = 0;
        $this->replyId = Codec::USHRT_MAX - 1;

        $response = $this->exchange(Command::Connect);
        $this->sessionId = $response->sessionId;

        if ($response->command === Command::AckUnauthorized->value) {
            $token = (new CommKey($this->commKey, $this->sessionId))->token();
            $response = $this->exchange(Command::Auth, $token);
        }

        if (! $response->isOk()) {
            $this->transport->close();

            throw $response->command === Command::AckUnauthorized->value
                ? ConnectionException::authFailed()
                : ConnectionException::unexpectedResponse($response->command);
        }
    }

    /**
     * Send a command and return the device's response packet.
     *
     * @throws ConnectionException when not connected.
     */
    public function command(Command $command, string $data = ''): Packet
    {
        if (! $this->transport->isConnected()) {
            throw ConnectionException::sessionNotOpen();
        }

        return $this->exchange($command, $data);
    }

    /**
     * Send CMD_EXIT and close the transport. Safe to call when already closed.
     */
    public function close(): void
    {
        if (! $this->transport->isConnected()) {
            return;
        }

        try {
            $this->exchange(Command::Exit);
        } catch (Throwable) {
            // Best effort: we close the socket regardless of the device's reply.
        } finally {
            $this->transport->close();
        }
    }

    /**
     * Register (or, with 0, clear) the realtime event subscription
     * (CMD_REG_EVENT).
     *
     * @throws ResponseException when the device rejects the request.
     */
    public function registerEvents(int $flags): void
    {
        $response = $this->command(Command::RegEvent, pack('V', $flags));

        if (! $response->isOk()) {
            throw ResponseException::commandRejected(Command::RegEvent->value);
        }
    }

    /**
     * Change the read timeout (seconds) for subsequent reads.
     */
    public function setReadTimeout(float $seconds): void
    {
        $this->transport->setTimeout($seconds);
    }

    /**
     * The current read timeout in seconds.
     */
    public function readTimeout(): float
    {
        return $this->transport->getTimeout();
    }

    /**
     * Read the next packet the device pushes during live capture.
     *
     * Unlike a command exchange this does not advance the reply-id counter:
     * realtime events ride their own sequence, so consuming them must not
     * disturb the bookkeeping the next real command relies on (matching pyzk's
     * raw recv loop).
     */
    public function nextPacket(): Packet
    {
        return $this->codec->parse($this->transport->receive());
    }

    /**
     * Acknowledge a pushed event with CMD_ACK_OK. Send-only — the device does
     * not reply — and built with the seed reply id, like pyzk's __ack_ok.
     */
    public function acknowledge(): void
    {
        $this->transport->send(
            $this->codec->build(Command::AckOk, '', $this->sessionId, Codec::USHRT_MAX - 1),
        );
    }

    /**
     * Read the device's memory counters (CMD_GET_FREE_SIZES).
     *
     * @return array{users: int, fingers: int, records: int}
     *
     * @throws ResponseException when the device rejects the request.
     */
    public function readSizes(): array
    {
        $response = $this->command(Command::GetFreeSizes);

        if (! $response->isOk()) {
            throw ResponseException::commandRejected(Command::GetFreeSizes->value);
        }

        $sizes = ['users' => 0, 'fingers' => 0, 'records' => 0];

        if (strlen($response->payload) >= 80) {
            /** @var list<int> $fields */
            $fields = array_values(unpack('V20', substr($response->payload, 0, 80)));
            $sizes['users'] = $fields[4];
            $sizes['fingers'] = $fields[6];
            $sizes['records'] = $fields[8];
        }

        return $sizes;
    }

    /**
     * Read a full dataset using the buffered protocol (pyzk's read_with_buffer):
     * prepare the buffer, then pull it back in chunks. Small datasets come back
     * inline in the prepare response.
     *
     * @param  Command  $command  the read command (e.g. CMD_USERTEMP_RRQ, CMD_ATTLOG_RRQ)
     * @param  int  $fct  optional function code (FCT_*)
     *
     * @throws ResponseException when the device does not support buffered reads.
     */
    public function readBuffer(Command $command, int $fct = 0, int $ext = 0): string
    {
        $request = pack('cvVV', 1, $command->value, $fct, $ext);
        $response = $this->command(Command::PrepareBuffer, $request);

        if ($response->command === Command::Data->value) {
            // Inline download: the transport already delivered the full payload.
            return $response->payload;
        }

        if (! $response->isOk()) {
            throw ResponseException::bufferedReadUnsupported();
        }

        // The size lives in bytes 1..4 of the prepare response payload.
        $size = Bytes::uint32(substr($response->payload, 1, 4));
        $data = '';

        for ($start = 0; $start < $size; $start += self::MAX_CHUNK) {
            $data .= $this->readChunk($start, min(self::MAX_CHUNK, $size - $start));
        }

        $this->command(Command::FreeData);

        return $data;
    }

    /**
     * Upload a full dataset using the buffered protocol (pyzk's
     * _send_with_buffer): free any pending buffer, announce the size with
     * CMD_PREPARE_DATA, then stream the payload back in CMD_DATA chunks.
     *
     * @throws ResponseException when the device rejects any step of the upload.
     */
    public function writeBuffer(string $payload): void
    {
        $this->assertOk($this->command(Command::FreeData), Command::FreeData);
        $this->assertOk(
            $this->command(Command::PrepareData, pack('V', strlen($payload))),
            Command::PrepareData,
        );

        $size = strlen($payload);

        for ($offset = 0; $offset < $size; $offset += self::MAX_WRITE_CHUNK) {
            $this->assertOk(
                $this->command(Command::Data, substr($payload, $offset, self::MAX_WRITE_CHUNK)),
                Command::Data,
            );
        }
    }

    /**
     * Tell the device to reload its in-memory tables after a write
     * (pyzk's refresh_data).
     *
     * @throws ResponseException when the device rejects the refresh.
     */
    public function refreshData(): void
    {
        $this->assertOk($this->command(Command::RefreshData), Command::RefreshData);
    }

    /**
     * Read one buffer chunk (CMD_READ_BUFFER), retrying up to three times.
     *
     * @throws ResponseException when the chunk cannot be read.
     */
    private function readChunk(int $start, int $size): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->command(Command::ReadBuffer, pack('VV', $start, $size));
            $chunk = $this->receiveChunk($response);

            if ($chunk !== null) {
                return $chunk;
            }
        }

        throw ResponseException::chunkReadFailed($start, $size);
    }

    /**
     * Collect a chunk's bytes from a CMD_READ_BUFFER reply. The device either
     * returns the chunk inline as CMD_DATA, or announces it with
     * CMD_PREPARE_DATA and streams CMD_DATA packets until CMD_ACK_OK.
     */
    private function receiveChunk(Packet $response): ?string
    {
        if ($response->command === Command::Data->value) {
            return $response->payload;
        }

        if ($response->command === Command::PrepareData->value) {
            $buffer = '';

            while (true) {
                $packet = $this->receivePacket();

                if ($packet->command === Command::Data->value) {
                    $buffer .= $packet->payload;
                } elseif ($packet->isOk()) {
                    return $buffer;
                } else {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Write one command and read back the response, advancing the reply id to
     * the value the device echoes (matching pyzk's send_command bookkeeping).
     */
    private function exchange(Command $command, string $data = ''): Packet
    {
        $this->send($command, $data);

        return $this->receivePacket();
    }

    private function send(Command $command, string $data = ''): void
    {
        $this->transport->send(
            $this->codec->build($command, $data, $this->sessionId, $this->replyId),
        );
    }

    private function receivePacket(): Packet
    {
        $response = $this->codec->parse($this->transport->receive());
        $this->replyId = $response->replyId;

        return $response;
    }

    /**
     * @throws ResponseException when the device did not acknowledge the command.
     */
    private function assertOk(Packet $response, Command $command): void
    {
        if (! $response->isOk()) {
            throw ResponseException::commandRejected($command->value);
        }
    }
}
