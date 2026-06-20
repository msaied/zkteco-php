<?php

declare(strict_types=1);

use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\TimeCodec;
use ZkTeco\TCP\Services\DeviceControlService;
use ZkTeco\TCP\Services\DeviceInfoService;
use ZkTeco\Tests\Support\FakeTransport;

it('reads the firmware version up to the first NUL', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckOk, sessionId: 1, payload: "Ver 6.60 Jun 1 2017\0\0\0"),
    ]);

    expect((new DeviceInfoService(openedSession($transport)))->firmwareVersion())
        ->toBe('Ver 6.60 Jun 1 2017');
});

it('parses the serial number out of the options response', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckOk, sessionId: 1, payload: "~SerialNumber=ABC123456\0"),
    ]);

    $service = new DeviceInfoService(openedSession($transport));

    expect($service->serialNumber())->toBe('ABC123456');

    // The request carries the NUL-terminated parameter name.
    expect((new Codec)->parse($transport->sent[1])->payload)->toBe("~SerialNumber\0");
});

it('returns an empty device name when the device rejects the read', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckError, sessionId: 1),
    ]);

    expect((new DeviceInfoService(openedSession($transport)))->name())->toBe('');
});

it('reads the device clock from the first four payload bytes', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckOk, sessionId: 1, payload: pack('V', 786378615).'trailing'),
    ]);

    expect((new DeviceInfoService(openedSession($transport)))->time()->format('Y-m-d H:i:s'))
        ->toBe('2024-06-19 14:30:15');
});

it('sets the device clock with the encoded timestamp', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckOk, sessionId: 1),
    ]);

    $time = new DateTimeImmutable('2024-06-19 14:30:15');
    (new DeviceInfoService(openedSession($transport)))->setTime($time);

    $sent = (new Codec)->parse($transport->sent[1]);
    expect($sent->command)->toBe(Command::SetTime->value)
        ->and($sent->payload)->toBe(TimeCodec::encode($time));
});

it('throws when the device rejects a metadata read', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckError, sessionId: 1),
    ]);

    expect(fn () => (new DeviceInfoService(openedSession($transport)))->firmwareVersion())
        ->toThrow(ResponseException::class);
});

it('sends payload-free control commands', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // disable
        responsePacket(Command::AckOk, sessionId: 1),  // enable
    ]);

    $service = new DeviceControlService(openedSession($transport));
    $service->disable();
    $service->enable();

    expect((new Codec)->parse($transport->sent[1])->command)->toBe(Command::DisableDevice->value)
        ->and((new Codec)->parse($transport->sent[1])->payload)->toBe('')
        ->and((new Codec)->parse($transport->sent[2])->command)->toBe(Command::EnableDevice->value);
});

it('throws when a control command is rejected', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckError, sessionId: 1),
    ]);

    expect(fn () => (new DeviceControlService(openedSession($transport)))->restart())
        ->toThrow(ResponseException::class);
});
