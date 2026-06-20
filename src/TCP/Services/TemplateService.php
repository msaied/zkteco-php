<?php

declare(strict_types=1);

namespace ZkTeco\TCP\Services;

use Throwable;
use ZkTeco\Exceptions\ErrorCode;
use ZkTeco\Exceptions\NetworkException;
use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\TemplateDecoder;
use ZkTeco\TCP\Protocol\TemplateEncoder;
use ZkTeco\Values\Template;
use ZkTeco\Values\User;

/**
 * Read, write and capture the biometric Templates belonging to Users.
 *
 * This is the firmware-variant-sensitive corner of the protocol; see
 * docs/adr/0005 on why it is verified only against real hardware.
 */
final class TemplateService
{
    /**
     * Function code for the fingerprint dataset (pyzk's FCT_FINGERTMP).
     */
    private const FCT_FINGERTMP = 2;

    /**
     * Enrollment completion result code (the first two bytes of the completion
     * record): zero means the capture succeeded.
     */
    private const RES_DONE = 0;

    /**
     * Smallest payload that counts as an enrollment completion record. The
     * device emits one- or two-byte progress pings as the finger is pressed and
     * a longer record (result + template size + user id) once it finishes.
     */
    private const ENROLL_RESULT_MIN_BYTES = 8;

    /**
     * Upper bound on progress pings to read before giving up, so a device that
     * never reports completion cannot loop forever.
     */
    private const MAX_ENROLL_EVENTS = 64;

    /**
     * Short read timeout (seconds) used to drain the trailing/duplicate events
     * the device pushes after a completed enrollment.
     */
    private const DRAIN_TIMEOUT = 1.0;

    public function __construct(private readonly Session $session) {}

    /**
     * Every template enrolled on the device, across all users.
     *
     * @return list<Template>
     */
    public function all(): array
    {
        $sizes = $this->session->readSizes();

        if ($sizes['fingers'] === 0) {
            return [];
        }

        $buffer = $this->session->readBuffer(Command::DbRead, self::FCT_FINGERTMP);

        return TemplateDecoder::decode($buffer);
    }

    /**
     * The templates belonging to a single user (by device-local slot).
     *
     * The device's per-template read is a firmware-specific "secret" command, so
     * this filters the full list instead — reliable across firmwares at the cost
     * of one full read.
     *
     * @return list<Template>
     */
    public function forUser(int $uid): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Template $template): bool => $template->uid === $uid,
        ));
    }

    /**
     * Delete one finger's template for a user (CMD_DELETE_USERTEMP).
     *
     * @param  int  $fingerIndex  finger slot to clear (0-9; see {@see Template}
     *                            for the full finger map)
     *
     * @throws ResponseException when the device rejects the delete.
     */
    public function delete(int $uid, int $fingerIndex): void
    {
        $response = $this->session->command(
            Command::DeleteUserTemp,
            pack('v', $uid).pack('c', $fingerIndex),
        );

        if (! $response->isOk()) {
            throw ResponseException::commandRejected(Command::DeleteUserTemp->value);
        }
    }

    /**
     * Upload existing template blobs for a user (CMD_SAVE_USERTEMPS).
     *
     * This writes the user record and the given fingerprint templates together,
     * the way pyzk's save_user_template does. The template bytes are opaque and
     * device-specific: they must come from a compatible device (e.g. cloned via
     * {@see all()} / {@see forUser()}), not synthesised. To capture a brand-new
     * fingerprint from the sensor instead, use {@see enroll()}.
     *
     * @param  list<Template>  $templates  the fingerprints to store for the user
     *
     * @throws ResponseException when the device rejects the upload.
     */
    public function upload(User $user, array $templates): void
    {
        if ($templates === []) {
            return;
        }

        $this->session->writeBuffer(TemplateEncoder::encode($user, $templates));

        $response = $this->session->command(
            Command::SaveUserTemps,
            pack('V', 12).pack('v', 0).pack('v', 8),
        );

        if (! $response->isOk()) {
            throw ResponseException::commandRejected(Command::SaveUserTemps->value);
        }

        $this->session->refreshData();
    }

    /**
     * Capture a new fingerprint from the device sensor for an existing user
     * (CMD_STARTENROLL).
     *
     * This is interactive: it blocks while the person presses the same finger
     * (the device drives the press count itself, typically three times), so the
     * session must be opened with a generous read timeout. Returns true once the
     * device confirms the capture, false on a failed or duplicate finger, a
     * timeout, or if the person never completes the scans.
     *
     * The device reports progress as short one- or two-byte event pings and the
     * outcome as a longer completion record — `<result, size, user-id>` — where a
     * zero result and a non-zero template size mean success. (This is the
     * firmware-specific corner ADR-0005 covers; the byte layout was confirmed by
     * hand against the test device, not derived from pyzk, whose published
     * sequence does not match it.)
     *
     * @param  int  $fingerIndex  finger slot to store the capture in (0-9, e.g.
     *                            6 = right index; see {@see Template} for the
     *                            full finger map)
     *
     * @throws ResponseException when the device refuses to start enrollment.
     */
    public function enroll(User $user, int $fingerIndex = 0): bool
    {
        $this->cancelCapture();

        $start = $this->session->command(
            Command::StartEnroll,
            str_pad(substr($user->userId, 0, 24), 24, "\0").pack('c', $fingerIndex).pack('c', 1),
        );

        if (! $start->isOk()) {
            throw ResponseException::commandRejected(Command::StartEnroll->value);
        }

        try {
            return $this->awaitEnrollment();
        } finally {
            $this->teardownCapture();
        }
    }

    /**
     * Read enrollment events until the device pushes its completion record,
     * acknowledging each one. Short progress pings are skipped; the completion
     * record's result and template-size fields decide success. A read timeout
     * means the person never finished, so the capture failed.
     */
    private function awaitEnrollment(): bool
    {
        try {
            for ($event = 0; $event < self::MAX_ENROLL_EVENTS; $event++) {
                $packet = $this->session->nextPacket();
                $this->session->acknowledge();

                if (strlen($packet->payload) < self::ENROLL_RESULT_MIN_BYTES) {
                    continue;
                }

                /** @var array{result: int, size: int} $record */
                $record = unpack('vresult/vsize', substr($packet->payload, 0, 4));

                $done = $record['result'] === self::RES_DONE && $record['size'] > 0;

                // The device repeats the completion record; clear it (and any
                // trailing pings) so the next command reads its own reply.
                $this->drainEvents();

                return $done;
            }

            return false;
        } catch (NetworkException $exception) {
            if ($exception->errorCode === ErrorCode::Timeout) {
                return false;
            }

            throw $exception;
        }
    }

    /**
     * Consume any pending events left in the socket after a completed
     * enrollment, using a short timeout, then restore the original.
     */
    private function drainEvents(): void
    {
        $original = $this->session->readTimeout();
        $this->session->setReadTimeout(self::DRAIN_TIMEOUT);

        try {
            while (true) {
                $this->session->nextPacket();
                $this->session->acknowledge();
            }
        } catch (NetworkException) {
            // No more pending events (timeout or close): the socket is clean.
        } finally {
            $this->session->setReadTimeout($original);
        }
    }

    /**
     * Abort any capture in progress (CMD_CANCELCAPTURE). Best-effort: the device
     * reply is not asserted, matching pyzk's cancel_capture.
     */
    private function cancelCapture(): void
    {
        $this->session->command(Command::CancelCapture);
    }

    /**
     * Restore the device to its idle state after enrollment: clear the event
     * subscription, cancel capture and return to verify mode. Best-effort so a
     * teardown hiccup never masks the enrollment outcome.
     */
    private function teardownCapture(): void
    {
        try {
            $this->session->registerEvents(0);
            $this->session->command(Command::CancelCapture);
            $this->session->command(Command::StartVerify);
        } catch (Throwable) {
            // Best effort.
        }
    }
}
