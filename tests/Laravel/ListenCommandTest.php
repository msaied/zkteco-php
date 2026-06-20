<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use ZkTeco\Laravel\DeviceManager;
use ZkTeco\Laravel\Events\PunchReceived;
use ZkTeco\TCP\Device;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\Tests\Support\FakeTransport;

/**
 * Bind a DeviceManager that hands the command a Device backed by the given
 * (fake) transport, so the whole zkteco:listen flow runs without a socket.
 */
function bindFakeDevice(FakeTransport $transport): void
{
    $session = openedSession($transport);
    $device = new Device('10.0.0.5', session: $session);

    $manager = new class(['default' => 'default', 'connections' => ['default' => ['host' => '10.0.0.5']]], $device) extends DeviceManager
    {
        public function __construct(array $config, private readonly Device $device)
        {
            parent::__construct($config);
        }

        protected function makeDevice(array $settings): Device
        {
            return $this->device;
        }
    };

    app()->instance('zkteco', $manager);
}

it('streams punches as PunchReceived events and unregisters on stop', function () {
    Event::fake([PunchReceived::class]);

    $userRecord = pack('v', 1)
        .pack('C', 0)
        .str_pad('', 8, "\0")
        .str_pad('Alice', 24, "\0")
        .pack('V', 0)
        ."\0"
        .str_pad('0', 7, "\0")
        ."\0"
        .str_pad('1001', 24, "\0");

    $event = fn (int $minute) => pack('v', 1001).pack('C', 1).pack('C', 0)
        .timehex(2024, 6, 19, 14, $minute, 15);

    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),                                    // connect
        responsePacket(Command::AckOk, sessionId: 1, payload: freeSizes(1, 0)),          // read sizes
        responsePacket(Command::Data, sessionId: 1, payload: pack('V', 72).$userRecord), // user buffer
        responsePacket(Command::AckOk, sessionId: 1),                                    // reg_event(EF_ATTLOG)
        responsePacket(Command::RegEvent, sessionId: 1, payload: $event(30)),            // punch 1
        responsePacket(Command::RegEvent, sessionId: 1, payload: $event(31)),            // punch 2
        // transport then exhausts -> closed socket -> the daemon exits.
    ]);

    bindFakeDevice($transport);

    $this->artisan('zkteco:listen')
        ->expectsOutputToContain('Listening to ZKTeco device [10.0.0.5] on connection [default]')
        ->assertFailed(); // a dropped connection exits non-zero so a supervisor restarts it

    Event::assertDispatchedTimes(PunchReceived::class, 2);
    Event::assertDispatched(PunchReceived::class, function (PunchReceived $event): bool {
        return $event->connection === 'default'
            && $event->record->userId === '1001'
            && $event->record->uid === 1;
    });

    // The subscription was cleared on the way out: a reg_event(0) was sent.
    $clearedEvents = collect($transport->sent)
        ->map(fn (string $raw) => (new Codec)->parse($raw))
        ->contains(fn ($packet) => $packet->command === Command::RegEvent->value && $packet->payload === pack('V', 0));

    expect($clearedEvents)->toBeTrue();
});

it('fails cleanly when the connection is not configured', function () {
    $this->artisan('zkteco:listen', ['connection' => 'missing'])
        ->expectsOutputToContain('ZKTeco connection [missing] is not configured.')
        ->assertFailed();
});
