<?php

declare(strict_types=1);

namespace ZkTeco\TCP;

use Throwable;
use ZkTeco\Exceptions\ConnectionException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Connection\TcpTransport;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\NameField;
use ZkTeco\TCP\Services\AttendanceService;
use ZkTeco\TCP\Services\DeviceControlService;
use ZkTeco\TCP\Services\DeviceInfoService;
use ZkTeco\TCP\Services\RealtimeService;
use ZkTeco\TCP\Services\TemplateService;
use ZkTeco\TCP\Services\UserService;

/**
 * The entry point for talking to a single ZKTeco device.
 *
 * Operations are grouped into focused sub-services reached from here —
 * `$device->users()`, `$device->attendance()`, `$device->control()`, etc. —
 * rather than a single flat class (see docs/adr/0003).
 *
 * The preferred way to use it is the managed {@see Session()} scope, which
 * guarantees the device is re-enabled and disconnected even when the body
 * throws (see docs/adr/0004). Explicit {@see connect()}/{@see disconnect()}
 * remain available for long-lived use such as realtime listening.
 */
final class Device
{
    private ?Session $session = null;

    /**
     * @param  Session|null  $session  a pre-built session to use instead of
     *                                 opening one; mainly for advanced use and
     *                                 testing. When given, {@see connect()} is a
     *                                 no-op.
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port = 4370,
        public readonly int $commKey = 0,
        public readonly float $timeout = 5.0,
        public readonly bool $useUdp = false,
        public readonly string $nameEncoding = NameField::DEFAULT_ENCODING,
        ?Session $session = null,
    ) {
        $this->session = $session;
    }

    /**
     * Open and authenticate a session to the device.
     *
     * @throws ConnectionException
     */
    public function connect(): self
    {
        if ($this->session !== null) {
            return $this;
        }

        if ($this->useUdp) {
            throw ConnectionException::udpUnsupported();
        }

        $session = new Session(
            new TcpTransport($this->host, $this->port, $this->timeout),
            new Codec,
            $this->commKey,
            $this->nameEncoding,
        );
        $session->open();

        $this->session = $session;

        return $this;
    }

    /**
     * Close the session if one is open. Safe to call when not connected.
     */
    public function disconnect(): void
    {
        if ($this->session === null) {
            return;
        }

        $this->session->close();
        $this->session = null;
    }

    public function isConnected(): bool
    {
        return $this->session !== null;
    }

    /**
     * Run $callback inside a managed session.
     *
     * Connects, disables the device for the duration of the work, then
     * guarantees — via try/finally — that the device is re-enabled and the
     * session closed, even if $callback throws.
     *
     * @template T
     *
     * @param  callable(self): T  $callback
     * @return T
     */
    public function session(callable $callback): mixed
    {
        $this->connect();

        try {
            $this->control()->disable();

            return $callback($this);
        } finally {
            try {
                $this->control()->enable();
            } catch (Throwable) {
                // Re-enable is best-effort; disconnecting below still runs.
            }

            $this->disconnect();
        }
    }

    public function users(): UserService
    {
        return new UserService($this->requireSession());
    }

    public function attendance(): AttendanceService
    {
        return new AttendanceService($this->requireSession());
    }

    public function templates(): TemplateService
    {
        return new TemplateService($this->requireSession());
    }

    public function control(): DeviceControlService
    {
        return new DeviceControlService($this->requireSession());
    }

    public function info(): DeviceInfoService
    {
        return new DeviceInfoService($this->requireSession());
    }

    public function realtime(): RealtimeService
    {
        return new RealtimeService($this->requireSession());
    }

    /**
     * @throws ConnectionException when no session is open.
     */
    private function requireSession(): Session
    {
        return $this->session
            ?? throw ConnectionException::deviceNotConnected();
    }
}
