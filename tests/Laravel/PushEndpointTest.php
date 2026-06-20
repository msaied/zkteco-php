<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use ZkTeco\ADMS\Commands\CommandQueue;
use ZkTeco\ADMS\Commands\CommandStatus;
use ZkTeco\Enums\OperationType;
use ZkTeco\Laravel\Events\AttendancePhotoReceived;
use ZkTeco\Laravel\Events\BiometricReceived;
use ZkTeco\Laravel\Events\CommandAcknowledged;
use ZkTeco\Laravel\Events\DeviceRegistered;
use ZkTeco\Laravel\Events\OperationLogged;
use ZkTeco\Laravel\Events\PunchReceived;
use ZkTeco\Laravel\Events\UserReceived;
use ZkTeco\Laravel\Models\Command;
use ZkTeco\Laravel\Models\Device;
use ZkTeco\Tests\TestCase;

const SN = TestCase::AllowedSerial;

it('rejects a request with no serial number', function () {
    $this->get('/iclock/cdata?options=all')->assertStatus(400);
});

it('rejects an upload from a serial that is not allowlisted', function () {
    $this->get('/iclock/cdata?SN=EVIL999&options=all')->assertStatus(401);

    $this->assertDatabaseMissing('zkteco_devices', ['serial_number' => 'EVIL999']);
});

it('registers an allowlisted device on handshake', function () {
    Event::fake([DeviceRegistered::class]);

    $response = $this->get('/iclock/cdata?SN='.SN.'&options=all&pushver=2.4.1');

    $response->assertOk();
    expect($response->getContent())->toContain('GET OPTION FROM: '.SN)
        ->and($response->getContent())->toContain('Realtime=1');

    $this->assertDatabaseHas('zkteco_devices', [
        'serial_number' => SN,
        'protocol_generation' => 'PushV2',
    ]);
    Event::assertDispatched(DeviceRegistered::class);
});

it('ingests an ATTLOG upload as PunchReceived events and advances the stamp', function () {
    $this->get('/iclock/cdata?SN='.SN.'&options=all');

    Event::fake([PunchReceived::class]);

    $body = "1001\t2026-06-19 08:00:00\t0\t1\n1001\t2026-06-19 17:00:00\t1\t1\n";
    $response = $this->call('POST', '/iclock/cdata?SN='.SN.'&table=ATTLOG&Stamp=999', content: $body);

    $response->assertOk();
    expect($response->getContent())->toBe('OK: 2');

    Event::assertDispatchedTimes(PunchReceived::class, 2);
    Event::assertDispatched(PunchReceived::class, fn (PunchReceived $event) => $event->connection === SN
        && $event->record->userId === '1001');

    expect(Device::query()->where('serial_number', SN)->first()->stamps['ATTLOG'])->toBe('999');
});

it('ingests an OPERLOG upload as OperationLogged and UserReceived events and advances the stamp', function () {
    $this->get('/iclock/cdata?SN='.SN.'&options=all');

    Event::fake([OperationLogged::class, UserReceived::class]);

    $body = "OPLOG\t6\t1\t1001\t2026-06-19 09:00:00\nUSER PIN=1001\tName=Alice\tPri=14";
    $response = $this->call('POST', '/iclock/cdata?SN='.SN.'&table=OPERLOG&Stamp=42', content: $body);

    $response->assertOk();
    expect($response->getContent())->toBe('OK: 2');

    Event::assertDispatched(OperationLogged::class, fn (OperationLogged $event): bool => $event->connection === SN
        && $event->entry->operation === OperationType::FingerprintEnrolled);
    Event::assertDispatched(UserReceived::class, fn (UserReceived $event): bool => $event->connection === SN
        && $event->user->userId === '1001');

    expect(Device::query()->where('serial_number', SN)->first()->stamps['OPERLOG'])->toBe('42');
});

it('ingests an ATTPHOTO upload as an AttendancePhotoReceived event', function () {
    $this->get('/iclock/cdata?SN='.SN.'&options=all');

    Event::fake([AttendancePhotoReceived::class]);

    $jpeg = "\xFF\xD8\xFF\xE0".'fake-jpeg-bytes';
    $response = $this->call('POST', '/iclock/cdata?SN='.SN.'&table=ATTPHOTO&PIN=1001&time='.urlencode('2026-06-19 09:00:00'), content: $jpeg);

    $response->assertOk();
    expect($response->getContent())->toBe('OK: 1');

    Event::assertDispatched(AttendancePhotoReceived::class, fn (AttendancePhotoReceived $event): bool => $event->connection === SN
        && $event->photo->userId === '1001'
        && $event->photo->image === $jpeg);
});

it('registers a PUSH-SDK device on the registry endpoint and echoes a registry code', function () {
    Event::fake([DeviceRegistered::class]);

    $response = $this->get('/iclock/registry?SN='.SN.'&pushver=2.4.1');

    $response->assertOk();
    expect($response->getContent())->toContain('RegistryCode='.SN);

    $this->assertDatabaseHas('zkteco_devices', ['serial_number' => SN, 'protocol_generation' => 'PushV2']);
    Event::assertDispatched(DeviceRegistered::class);
});

it('ingests an RTLOG upload from a PUSH-SDK device as PunchReceived events and advances the stamp', function () {
    $this->get('/iclock/cdata?SN='.SN.'&options=all&pushver=2.4.1');

    Event::fake([PunchReceived::class]);

    $body = "1001\t2026-06-19 08:00:00\t0\t1\n";
    $response = $this->call('POST', '/iclock/cdata?SN='.SN.'&table=RTLOG&Stamp=88', content: $body);

    $response->assertOk();
    expect($response->getContent())->toBe('OK: 1');

    Event::assertDispatched(PunchReceived::class, fn (PunchReceived $event): bool => $event->connection === SN
        && $event->record->userId === '1001');

    expect(Device::query()->where('serial_number', SN)->first()->stamps['RTLOG'])->toBe('88');
});

it('ingests a BIODATA upload from a PUSH-SDK device as a BiometricReceived event and advances the stamp', function () {
    $this->get('/iclock/cdata?SN='.SN.'&options=all&pushver=3.0.1');

    Event::fake([BiometricReceived::class]);

    $body = "BIODATA Pin=1001\tType=1\tIndex=0\tValid=1\tTmp=QUJD";
    $response = $this->call('POST', '/iclock/cdata?SN='.SN.'&table=BIODATA&Stamp=12', content: $body);

    $response->assertOk();
    expect($response->getContent())->toBe('OK: 1');

    Event::assertDispatched(BiometricReceived::class, fn (BiometricReceived $event): bool => $event->connection === SN
        && $event->template->userId === '1001'
        && $event->template->type === 1
        && $event->template->data === 'QUJD');

    expect(Device::query()->where('serial_number', SN)->first()->stamps['BIODATA'])->toBe('12');
});

it('answers a command poll with an OK keep-alive when nothing is queued', function () {
    $this->get('/iclock/cdata?SN='.SN.'&options=all');

    $this->get('/iclock/getrequest?SN='.SN.'&INFO=1')
        ->assertOk()
        ->assertContent('OK');
});

it('hands an enqueued command to the device on its next poll and marks it sent', function () {
    $this->get('/iclock/cdata?SN='.SN.'&options=all');

    $command = app(CommandQueue::class)->enqueue(SN, 'REBOOT');

    $this->get('/iclock/getrequest?SN='.SN.'&INFO=1')
        ->assertOk()
        ->assertContent("C:{$command->id}:REBOOT");

    $this->assertDatabaseHas('zkteco_commands', [
        'id' => $command->id,
        'status' => CommandStatus::Sent->value,
    ]);
});

it('records a device command result and fires CommandAcknowledged', function () {
    $this->get('/iclock/cdata?SN='.SN.'&options=all');
    $command = app(CommandQueue::class)->enqueue(SN, 'REBOOT');
    $this->get('/iclock/getrequest?SN='.SN.'&INFO=1');

    Event::fake([CommandAcknowledged::class]);

    $this->call('POST', '/iclock/devicecmd?SN='.SN, content: "ID={$command->id}&Return=0&CMD=DATA")
        ->assertOk();

    $row = Command::query()->find($command->id);
    expect($row->status)->toBe(CommandStatus::Acknowledged)
        ->and($row->return_code)->toBe(0)
        ->and($row->acknowledged_at)->not->toBeNull();

    Event::assertDispatched(CommandAcknowledged::class, fn (CommandAcknowledged $event): bool => $event->command->id === $command->id
        && $event->result->succeeded());
});
