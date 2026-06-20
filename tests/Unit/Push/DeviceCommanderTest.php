<?php

declare(strict_types=1);

use ZkTeco\ADMS\Commands\DeviceCommander;
use ZkTeco\ADMS\Commands\EmptyCommandQueue;
use ZkTeco\ADMS\Commands\Intents\PushTemplate;
use ZkTeco\ADMS\Commands\Intents\Reboot;
use ZkTeco\ADMS\Generations\GenerationSelector;
use ZkTeco\ADMS\Generations\LegacyGeneration;
use ZkTeco\ADMS\Generations\PushSdkGeneration;
use ZkTeco\ADMS\Parsing\AttlogParser;
use ZkTeco\ADMS\Parsing\AttphotoParser;
use ZkTeco\ADMS\Parsing\BiodataParser;
use ZkTeco\ADMS\Parsing\OperlogParser;
use ZkTeco\ADMS\Parsing\RtlogParser;
use ZkTeco\ADMS\Registry\Capabilities;
use ZkTeco\ADMS\Registry\DeviceRegistry;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\ADMS\Registry\Stamp;
use ZkTeco\Exceptions\CommandException;
use ZkTeco\Tests\Support\RecordingAttendancePhotoSink;
use ZkTeco\Tests\Support\RecordingAttendanceSink;
use ZkTeco\Tests\Support\RecordingBiometricSink;
use ZkTeco\Tests\Support\RecordingOperationLogSink;
use ZkTeco\Tests\Support\RecordingUserSink;
use ZkTeco\Values\BiometricTemplate;

/**
 * A real selector over both generations; rendering needs no live sinks, so the
 * recording doubles just satisfy the constructors.
 */
function commanderSelector(): GenerationSelector
{
    $legacy = new LegacyGeneration(
        new AttlogParser,
        new OperlogParser,
        new AttphotoParser,
        new RecordingAttendanceSink,
        new RecordingOperationLogSink,
        new RecordingUserSink,
        new RecordingAttendancePhotoSink,
    );

    $pushSdk = new PushSdkGeneration($legacy, new RtlogParser, new BiodataParser, new RecordingAttendanceSink, new RecordingBiometricSink);

    return new GenerationSelector(
        [
            ProtocolGeneration::Legacy->value => $legacy,
            ProtocolGeneration::PushV2->value => $pushSdk,
            ProtocolGeneration::PushV3->value => $pushSdk,
        ],
        $legacy,
    );
}

function commanderRegistry(?RegisteredDevice $device): DeviceRegistry
{
    return new class($device) implements DeviceRegistry
    {
        public function __construct(private ?RegisteredDevice $device) {}

        public function admits(string $serialNumber): bool
        {
            return true;
        }

        public function find(string $serialNumber): ?RegisteredDevice
        {
            return $this->device;
        }

        public function register(RegisteredDevice $device): RegisteredDevice
        {
            return $device;
        }

        public function markSeen(string $serialNumber): void {}

        public function updateStamp(string $serialNumber, Stamp $stamp): void {}

        public function approve(string $serialNumber): void {}

        public function block(string $serialNumber): void {}
    };
}

function commanderFor(ProtocolGeneration $generation): DeviceCommander
{
    $device = new RegisteredDevice('SN1', $generation, new Capabilities);

    return new DeviceCommander(commanderRegistry($device), commanderSelector(), new EmptyCommandQueue);
}

it('enqueues the rendered instruction and returns the handle', function () {
    $handle = commanderFor(ProtocolGeneration::Legacy)->dispatch('SN1', new Reboot);

    expect($handle->serialNumber)->toBe('SN1')
        ->and($handle->command)->toBe('REBOOT');
});

it('renders a template through the LEGACY device as FINGERTMP', function () {
    $template = new BiometricTemplate(userId: '1001', type: 1, index: 0, valid: true, data: 'QUJD');

    $handle = commanderFor(ProtocolGeneration::Legacy)->dispatch('SN1', new PushTemplate($template));

    expect($handle->command)->toContain('DATA UPDATE FINGERTMP')
        ->and($handle->command)->toContain('PIN=1001');
});

it('renders the same template through a PUSH-SDK device as BIODATA', function () {
    $template = new BiometricTemplate(userId: '1001', type: 1, index: 0, valid: true, data: 'QUJD');

    $handle = commanderFor(ProtocolGeneration::PushV2)->dispatch('SN1', new PushTemplate($template));

    expect($handle->command)->toContain('DATA UPDATE BIODATA')
        ->and($handle->command)->toContain('Pin=1001');
});

it('throws when commanding a serial that has never registered', function () {
    $commander = new DeviceCommander(commanderRegistry(null), commanderSelector(), new EmptyCommandQueue);

    $commander->dispatch('GHOST', new Reboot);
})->throws(CommandException::class);
