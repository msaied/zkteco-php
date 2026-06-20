<?php

declare(strict_types=1);

use ZkTeco\ADMS\Commands\CommandQueue;
use ZkTeco\ADMS\Generations\GenerationSelector;
use ZkTeco\ADMS\Generations\LegacyGeneration;
use ZkTeco\ADMS\Generations\PushSdkGeneration;
use ZkTeco\ADMS\Handlers\CdataHandler;
use ZkTeco\ADMS\Handlers\DevicecmdHandler;
use ZkTeco\ADMS\Handlers\GetrequestHandler;
use ZkTeco\ADMS\Handlers\RegistryHandler;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Http\PushRouter;
use ZkTeco\ADMS\Parsing\AttlogParser;
use ZkTeco\ADMS\Parsing\AttphotoParser;
use ZkTeco\ADMS\Parsing\BiodataParser;
use ZkTeco\ADMS\Parsing\CommandResultParser;
use ZkTeco\ADMS\Parsing\OperlogParser;
use ZkTeco\ADMS\Parsing\RtlogParser;
use ZkTeco\ADMS\Registry\DeviceStatus;
use ZkTeco\ADMS\Registry\Negotiator;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\Tests\Support\InMemoryCommandQueue;
use ZkTeco\Tests\Support\InMemoryDeviceRegistry;
use ZkTeco\Tests\Support\RecordingAttendancePhotoSink;
use ZkTeco\Tests\Support\RecordingAttendanceSink;
use ZkTeco\Tests\Support\RecordingBiometricSink;
use ZkTeco\Tests\Support\RecordingOperationLogSink;
use ZkTeco\Tests\Support\RecordingUserSink;

function pushRouter(InMemoryDeviceRegistry $registry, ?RecordingAttendanceSink $sink = null, ?CommandQueue $queue = null): PushRouter
{
    $sink ??= new RecordingAttendanceSink;
    $queue ??= new InMemoryCommandQueue;

    $legacy = new LegacyGeneration(
        new AttlogParser,
        new OperlogParser,
        new AttphotoParser,
        $sink,
        new RecordingOperationLogSink,
        new RecordingUserSink,
        new RecordingAttendancePhotoSink,
    );

    $pushSdk = new PushSdkGeneration($legacy, new RtlogParser, new BiodataParser, $sink, new RecordingBiometricSink);

    $selector = new GenerationSelector([
        ProtocolGeneration::Legacy->value => $legacy,
        ProtocolGeneration::PushV2->value => $pushSdk,
        ProtocolGeneration::PushV3->value => $pushSdk,
    ], $legacy);

    $cdata = new CdataHandler($registry, new Negotiator, $selector);

    return new PushRouter(
        $registry,
        $cdata,
        new GetrequestHandler($registry, $queue),
        new DevicecmdHandler($registry, $queue, new CommandResultParser),
        new RegistryHandler($registry, new Negotiator, $selector),
    );
}

it('rejects a request with no serial number', function () {
    $response = pushRouter(new InMemoryDeviceRegistry)
        ->dispatch(new PushRequest('GET', 'iclock/cdata', ['options' => 'all']));

    expect($response->status)->toBe(400);
});

it('rejects a serial that is not on the allowlist', function () {
    $response = pushRouter(new InMemoryDeviceRegistry(['GOOD']))
        ->dispatch(new PushRequest('GET', 'iclock/cdata', ['SN' => 'EVIL']));

    expect($response->status)->toBe(401);
});

it('admits an unknown serial in open-registration mode and records it pending', function () {
    $registry = new InMemoryDeviceRegistry(autoRegister: true);

    $response = pushRouter($registry)->dispatch(new PushRequest('GET', 'iclock/cdata', ['SN' => 'WHO']));

    expect($response->status)->toBe(200)
        ->and($registry->find('WHO')->status)->toBe(DeviceStatus::Pending);
});

it('rejects a device that has been blocked', function () {
    $registry = new InMemoryDeviceRegistry(autoRegister: true);
    $router = pushRouter($registry);

    $router->dispatch(new PushRequest('GET', 'iclock/cdata', ['SN' => 'BAD']));
    $registry->block('BAD');

    expect($router->dispatch(new PushRequest('GET', 'iclock/cdata', ['SN' => 'BAD']))->status)->toBe(401);
});

it('routes a GET cdata to the handshake and registers the device', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);

    $response = pushRouter($registry)
        ->dispatch(new PushRequest('GET', 'iclock/cdata', ['SN' => 'SN1']));

    expect($response->status)->toBe(200)
        ->and($response->body)->toContain('GET OPTION FROM: SN1')
        ->and($registry->find('SN1'))->not->toBeNull();
});

it('routes a POST cdata ATTLOG to ingestion', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $sink = new RecordingAttendanceSink;

    $response = pushRouter($registry, $sink)->dispatch(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'ATTLOG',
    ], "1001\t2026-06-19 08:00:00\t0\t1"));

    expect($response->status)->toBe(200)
        ->and($sink->received)->toHaveCount(1);
});

it('routes a GET getrequest to a keep-alive', function () {
    $response = pushRouter(new InMemoryDeviceRegistry(['SN1']))
        ->dispatch(new PushRequest('GET', 'iclock/getrequest', ['SN' => 'SN1']));

    expect($response->body)->toBe('OK');
});

it('routes a POST devicecmd to the acknowledgement handler', function () {
    $response = pushRouter(new InMemoryDeviceRegistry(['SN1']))
        ->dispatch(new PushRequest('POST', 'iclock/devicecmd', ['SN' => 'SN1'], 'ID=1&Return=0'));

    expect($response->status)->toBe(200);
});

it('routes a GET registry to the registry handler and registers the device', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);

    $response = pushRouter($registry)
        ->dispatch(new PushRequest('GET', 'iclock/registry', ['SN' => 'SN1', 'pushver' => '2.4.1']));

    expect($response->status)->toBe(200)
        ->and($response->body)->toContain('RegistryCode=SN1')
        ->and($registry->find('SN1'))->not->toBeNull();
});

it('gates the registry endpoint: rejects a missing or un-admitted serial', function () {
    expect(pushRouter(new InMemoryDeviceRegistry(['SN1']))->dispatch(new PushRequest('GET', 'iclock/registry'))->status)
        ->toBe(400)
        ->and(pushRouter(new InMemoryDeviceRegistry(['SN1']))->dispatch(new PushRequest('GET', 'iclock/registry', ['SN' => 'EVIL']))->status)
        ->toBe(401);
});

it('returns 404 for an allowed serial on an unknown endpoint or method', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);

    expect(pushRouter($registry)->dispatch(new PushRequest('GET', 'iclock/devicecmd', ['SN' => 'SN1']))->status)
        ->toBe(404)
        ->and(pushRouter($registry)->dispatch(new PushRequest('POST', 'iclock/registry', ['SN' => 'SN1']))->status)
        ->toBe(404)
        ->and(pushRouter($registry)->dispatch(new PushRequest('POST', 'iclock/unknown', ['SN' => 'SN1']))->status)
        ->toBe(404);
});
