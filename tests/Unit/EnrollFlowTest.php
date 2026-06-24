<?php

declare(strict_types=1);

use ZkTeco\Exceptions\NetworkException;
use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Connection\Session;
use ZkTeco\TCP\Connection\Transport;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Services\TemplateService;
use ZkTeco\Values\User;

/**
 * A scripted transport for enrollment: each script entry is either a raw
 * response packet to return, or null to make the next read throw a timeout
 * (used to end the post-enrollment drain). Sent packets are recorded.
 */
function enrollTransport(array $script): Transport
{
    return new class($script) implements Transport
    {
        /** @var list<string> */
        public array $sent = [];

        /**
         * @param  list<string|null>  $script
         */
        public function __construct(private array $script) {}

        public function connect(): void {}

        public function send(string $payload): void
        {
            $this->sent[] = $payload;
        }

        public function receive(): string
        {
            if ($this->script === []) {
                throw NetworkException::connectionClosed();
            }

            $item = array_shift($this->script);

            if ($item === null) {
                throw NetworkException::timeout();
            }

            return $item;
        }

        public function close(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function setTimeout(float $seconds): void {}

        public function getTimeout(): float
        {
            return 0.0;
        }
    };
}

/**
 * One pushed enrollment event packet carrying the given raw payload.
 */
function enrollEvent(string $payload): string
{
    return responsePacket(Command::RegEvent, sessionId: 1, payload: $payload);
}

/**
 * The device's completion record: <result, template size, user id>.
 */
function enrollCompletion(int $result, int $size): string
{
    return enrollEvent(pack('v', $result).pack('v', $size).str_pad('99399', 11, "\0"));
}

function enrollSession(Transport $transport): Session
{
    $session = new Session($transport, new Codec);
    $session->open();

    return $session;
}

it('enrolls a fingerprint when the device reports a completed capture', function () {
    $transport = enrollTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // start enroll
        enrollEvent("\x44"),                           // scan progress ping
        enrollCompletion(result: 0, size: 926),        // capture complete
        null,                                          // drain: no more events
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: clear events
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: verify mode
    ]);

    $done = (new TemplateService(enrollSession($transport)))->enroll(new User(uid: 7, userId: '99399', name: 'Bob'), fingerIndex: 1);

    expect($done)->toBeTrue();

    $codec = new Codec;
    $start = $codec->parse($transport->sent[2]);
    expect($start->command)->toBe(Command::StartEnroll->value)
        ->and($start->payload)->toBe(str_pad('99399', 24, "\0").pack('c', 1).pack('c', 1));

    // Teardown returns the device to an idle, verifying state.
    expect($codec->parse($transport->sent[5])->command)->toBe(Command::RegEvent->value)
        ->and($codec->parse($transport->sent[6])->command)->toBe(Command::CancelCapture->value)
        ->and($codec->parse($transport->sent[7])->command)->toBe(Command::StartVerify->value);
});

it('reports failure when the completion record carries a non-zero result', function () {
    $transport = enrollTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // start enroll
        enrollCompletion(result: 1, size: 926),        // device rejected the capture
        null,                                          // drain
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: clear events
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: verify mode
    ]);

    expect((new TemplateService(enrollSession($transport)))->enroll(new User(uid: 7, userId: '99399', name: 'Bob')))
        ->toBeFalse();
});

it('reports failure when no template is captured', function () {
    $transport = enrollTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // start enroll
        enrollCompletion(result: 0, size: 0),          // completed but empty template
        null,                                          // drain
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: clear events
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: verify mode
    ]);

    expect((new TemplateService(enrollSession($transport)))->enroll(new User(uid: 7, userId: '99399', name: 'Bob')))
        ->toBeFalse();
});

it('returns false when the person never completes the scans', function () {
    $transport = enrollTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // start enroll
        enrollEvent("\x44"),                           // one progress ping
        null,                                          // then a read timeout
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: clear events
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: verify mode
    ]);

    expect((new TemplateService(enrollSession($transport)))->enroll(new User(uid: 7, userId: '99399', name: 'Bob')))
        ->toBeFalse();
});

it('reports each step to the diagnostic trace hook', function () {
    $transport = enrollTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // start enroll
        enrollEvent("\x44"),                           // scan progress ping
        enrollCompletion(result: 0, size: 926),        // capture complete
        enrollCompletion(result: 0, size: 926),        // repeated completion, drained
        null,                                          // drain: no more events
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: clear events
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: verify mode
    ]);

    $events = [];
    $trace = function (string $event, array $context) use (&$events): void {
        $events[] = [$event, $context];
    };

    $done = (new TemplateService(enrollSession($transport)))
        ->enroll(new User(uid: 7, userId: '99399', name: 'Bob'), fingerIndex: 6, trace: $trace);

    expect($done)->toBeTrue();

    $names = array_column($events, 0);
    expect($names)->toBe([
        'start_enroll.send',
        'start_enroll.reply',
        'enroll.event',   // progress ping
        'enroll.event',   // completion record
        'enroll.completion',
        'enroll.drain',   // repeated completion record
    ]);

    $context = array_column($events, 1, 0);
    expect($context['start_enroll.send']['finger_index'])->toBe(6)
        ->and($context['start_enroll.send']['hex'])->toBe(bin2hex(str_pad('99399', 24, "\0").pack('c', 6).pack('c', 1)))
        ->and($context['start_enroll.reply']['ok'])->toBeTrue()
        ->and($context['enroll.completion'])->toMatchArray(['result' => 0, 'size' => 926, 'done' => true])
        ->and($context['enroll.drain']['bytes'])->toBeGreaterThanOrEqual(4);
});

it('reports a timeout to the trace hook when the person never finishes', function () {
    $transport = enrollTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // start enroll
        null,                                          // read timeout before any event
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: clear events
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: cancel capture
        responsePacket(Command::AckOk, sessionId: 1),  // teardown: verify mode
    ]);

    $names = [];
    $trace = function (string $event) use (&$names): void {
        $names[] = $event;
    };

    (new TemplateService(enrollSession($transport)))
        ->enroll(new User(uid: 7, userId: '99399', name: 'Bob'), trace: $trace);

    expect($names)->toBe(['start_enroll.send', 'start_enroll.reply', 'enroll.timeout']);
});

it('throws when the device refuses to start enrollment', function () {
    $transport = enrollTransport([
        responsePacket(Command::AckOk, sessionId: 1),    // connect
        responsePacket(Command::AckOk, sessionId: 1),    // cancel capture
        responsePacket(Command::AckError, sessionId: 1), // start enroll rejected
        responsePacket(Command::AckOk, sessionId: 1),    // teardown: clear events
        responsePacket(Command::AckOk, sessionId: 1),    // teardown: cancel capture
        responsePacket(Command::AckOk, sessionId: 1),    // teardown: verify mode
    ]);

    expect(fn () => (new TemplateService(enrollSession($transport)))->enroll(new User(uid: 1, userId: '1', name: 'X')))
        ->toThrow(ResponseException::class);
});
