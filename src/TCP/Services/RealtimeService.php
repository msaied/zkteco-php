<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Services;

use Generator;
use Throwable;
use ZkTeco\Exceptions\ErrorCode;
use ZkTeco\Exceptions\NetworkException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\LiveEventDecoder;
use ZkTeco\Values\AttendanceRecord;

/**
 * Stream live punch events from a device as they happen.
 *
 * Registers for realtime events and yields each punch as it arrives. The
 * generator holds the connection open for as long as it is iterated; the caller
 * (or the Laravel zkteco:listen command) owns that lifecycle — it is not bound
 * to the short-lived managed session scope.
 *
 * On an idle read timeout the generator yields `null` as a heartbeat, letting a
 * long-running listener break out or do periodic work between punches. When the
 * caller stops iterating, the event subscription is cleared automatically.
 */
final class RealtimeService
{
    /**
     * Event flag for attendance verifications (pyzk's EF_ATTLOG).
     */
    private const EF_ATTLOG = 1;

    public function __construct(private readonly Session $session) {}

    /**
     * @return Generator<int, AttendanceRecord|null>
     */
    public function live(): Generator
    {
        // The user list resolves an event's user id to its device-local uid.
        $users = (new UserService($this->session))->all();

        $this->session->registerEvents(self::EF_ATTLOG);

        try {
            while (true) {
                try {
                    $packet = $this->session->nextPacket();
                } catch (NetworkException $exception) {
                    if ($exception->errorCode === ErrorCode::Timeout) {
                        yield null;

                        continue;
                    }

                    throw $exception;
                }

                $this->session->acknowledge();

                if ($packet->command !== Command::RegEvent->value) {
                    continue;
                }

                foreach (LiveEventDecoder::decode($packet->payload, $users) as $event) {
                    yield $event;
                }
            }
        } finally {
            try {
                $this->session->registerEvents(0);
            } catch (Throwable) {
                // Best effort: unregister cleanly, but never let teardown mask
                // the reason the stream actually stopped.
            }
        }
    }
}
