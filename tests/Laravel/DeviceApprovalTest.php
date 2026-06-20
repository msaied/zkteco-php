<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\DeviceStatus;
use ZkTeco\Laravel\Events\DeviceRegistered;
use ZkTeco\Laravel\Events\PunchReceived;
use ZkTeco\Laravel\Models\Device;

/**
 * Open-registration posture: accept every device but hold its data until an
 * operator approves it. The shared TestCase allowlists TEST-SN-1; here we clear
 * the allowlist and turn auto-register on so an unknown serial lands pending.
 */
beforeEach(function () {
    config()->set('zkteco.adms.auto_register', true);
    config()->set('zkteco.adms.allowed_serials', []);
});

it('records an unknown device as pending and fires DeviceRegistered', function () {
    Event::fake([DeviceRegistered::class]);

    $this->get('/iclock/cdata?SN=OPEN-9&options=all')->assertOk();

    $this->assertDatabaseHas('zkteco_devices', [
        'serial_number' => 'OPEN-9',
        'status' => 'pending',
    ]);
    Event::assertDispatched(DeviceRegistered::class);
});

it('holds a pending device upload with a retry response and no event', function () {
    $this->get('/iclock/cdata?SN=OPEN-9&options=all');

    Event::fake([PunchReceived::class]);

    $this->call('POST', '/iclock/cdata?SN=OPEN-9&table=ATTLOG&Stamp=7', content: "1001\t2026-06-20 08:00:00\t0\t1\n")
        ->assertStatus(503);

    Event::assertNotDispatched(PunchReceived::class);

    $stamps = Device::query()->where('serial_number', 'OPEN-9')->first()->stamps ?? [];
    expect($stamps['ATTLOG'] ?? null)->toBeNull();
});

it('ingests attendance once the device is approved', function () {
    $this->get('/iclock/cdata?SN=OPEN-9&options=all');
    app(DeviceRegistry::class)->approve('OPEN-9');

    Event::fake([PunchReceived::class]);

    $body = "1001\t2026-06-20 08:00:00\t0\t1\n1001\t2026-06-20 17:00:00\t1\t1\n";
    $this->call('POST', '/iclock/cdata?SN=OPEN-9&table=ATTLOG&Stamp=7', content: $body)
        ->assertOk()
        ->assertContent('OK: 2');

    Event::assertDispatchedTimes(PunchReceived::class, 2);
    expect(Device::query()->where('serial_number', 'OPEN-9')->first()->stamps['ATTLOG'])->toBe('7');
});

it('rejects a device that has been blocked', function () {
    $this->get('/iclock/cdata?SN=OPEN-9&options=all');

    app(DeviceRegistry::class)->block('OPEN-9');

    $this->get('/iclock/cdata?SN=OPEN-9&options=all')->assertStatus(401);
});

it('approves a pending device through the artisan command', function () {
    $this->get('/iclock/cdata?SN=OPEN-9&options=all');

    $this->artisan('zkteco:approve', ['serial' => 'OPEN-9'])->assertSuccessful();

    expect(Device::query()->where('serial_number', 'OPEN-9')->first()->status)
        ->toBe(DeviceStatus::Approved);
});

it('blocks a device through the artisan command', function () {
    $this->get('/iclock/cdata?SN=OPEN-9&options=all');

    $this->artisan('zkteco:approve', ['serial' => 'OPEN-9', '--block' => true])->assertSuccessful();

    expect(Device::query()->where('serial_number', 'OPEN-9')->first()->status)
        ->toBe(DeviceStatus::Blocked);
});

it('fails the approve command for an unknown serial', function () {
    $this->artisan('zkteco:approve', ['serial' => 'GHOST'])->assertFailed();
});

it('lists registered devices with their status', function () {
    $this->get('/iclock/cdata?SN=OPEN-9&options=all');

    $this->artisan('zkteco:devices')
        ->expectsOutputToContain('OPEN-9')
        ->assertSuccessful();
});
