<?php

declare(strict_types=1);

use ZkTeco\ADMS\Commands\CommandStatus;
use ZkTeco\Exceptions\CommandException;
use ZkTeco\Laravel\Facades\ZkTeco;
use ZkTeco\Tests\TestCase;
use ZkTeco\Values\BiometricTemplate;

/**
 * Register the allowlisted serial as a PUSH-SDK device via the handshake, the
 * same path a real device takes before it can be commanded.
 */
function registerPushDevice(): string
{
    test()->get('/iclock/cdata?SN='.TestCase::AllowedSerial.'&options=all&pushver=2.4.1');

    return TestCase::AllowedSerial;
}

it('queues a typed reboot for a registered device through the facade', function () {
    $serial = registerPushDevice();

    $handle = ZkTeco::push($serial)->reboot();

    expect($handle->command)->toBe('REBOOT');
    $this->assertDatabaseHas('zkteco_commands', [
        'id' => $handle->id,
        'serial_number' => $serial,
        'command' => 'REBOOT',
        'status' => CommandStatus::Pending->value,
    ]);
});

it('renders a template for a PUSH-SDK device as a BIODATA update end to end', function () {
    $serial = registerPushDevice();

    $handle = ZkTeco::push($serial)->pushTemplate(
        new BiometricTemplate(userId: '1001', type: 1, index: 0, valid: true, data: 'QUJD'),
    );

    expect($handle->command)->toContain('DATA UPDATE BIODATA')
        ->and($handle->command)->toContain('Pin=1001');
});

it('throws when commanding a device that never registered', function () {
    ZkTeco::push('GHOST-SN')->reboot();
})->throws(CommandException::class);
