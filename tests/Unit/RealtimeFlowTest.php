<?php

declare(strict_types=1);

use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Services\RealtimeService;
use ZkTeco\Tests\Support\FakeTransport;

it('streams live punches, acks each, and unregisters on stop', function () {
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
        responsePacket(Command::AckOk, sessionId: 1),                                       // connect
        responsePacket(Command::AckOk, sessionId: 1, payload: freeSizes(1, 0)),             // read sizes
        responsePacket(Command::Data, sessionId: 1, payload: pack('V', 72).$userRecord),    // user buffer
        responsePacket(Command::AckOk, sessionId: 1),                                       // reg_event(EF_ATTLOG)
        responsePacket(Command::RegEvent, sessionId: 1, payload: $event(30)),               // event 1
        responsePacket(Command::RegEvent, sessionId: 1, payload: $event(31)),               // event 2
        responsePacket(Command::AckOk, sessionId: 1),                                       // reg_event(0) teardown
    ]);

    $service = new RealtimeService(openedSession($transport));

    $events = [];
    foreach ($service->live() as $event) {
        $events[] = $event;
        if (count($events) >= 2) {
            break;
        }
    }

    expect($events)->toHaveCount(2);
    expect($events[0]->userId)->toBe('1001')
        ->and($events[0]->uid)->toBe(1)   // resolved from the user list
        ->and($events[0]->recordedAt->format('H:i'))->toBe('14:30')
        ->and($events[1]->recordedAt->format('H:i'))->toBe('14:31');

    // Each event packet was acknowledged with CMD_ACK_OK.
    $sentCommands = array_map(fn (string $raw) => (new Codec)->parse($raw)->command, $transport->sent);
    expect(array_count_values($sentCommands)[Command::AckOk->value])->toBe(2);

    // The subscription was cleared on stop: the final packet is reg_event(0).
    $last = (new Codec)->parse($transport->sent[count($transport->sent) - 1]);
    expect($last->command)->toBe(Command::RegEvent->value)
        ->and($last->payload)->toBe(pack('V', 0));
});
