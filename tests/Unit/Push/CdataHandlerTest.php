<?php

declare(strict_types=1);

use ZkTeco\ADMS\Generations\GenerationSelector;
use ZkTeco\ADMS\Generations\LegacyGeneration;
use ZkTeco\ADMS\Generations\PushSdkGeneration;
use ZkTeco\ADMS\Handlers\CdataHandler;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Parsing\AttlogParser;
use ZkTeco\ADMS\Parsing\AttphotoParser;
use ZkTeco\ADMS\Parsing\BiodataParser;
use ZkTeco\ADMS\Parsing\OperlogParser;
use ZkTeco\ADMS\Parsing\RtlogParser;
use ZkTeco\ADMS\Registry\Negotiator;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\Enums\OperationType;
use ZkTeco\Tests\Support\InMemoryDeviceRegistry;
use ZkTeco\Tests\Support\RecordingAttendancePhotoSink;
use ZkTeco\Tests\Support\RecordingAttendanceSink;
use ZkTeco\Tests\Support\RecordingBiometricSink;
use ZkTeco\Tests\Support\RecordingOperationLogSink;
use ZkTeco\Tests\Support\RecordingUserSink;

function cdataHandler(
    InMemoryDeviceRegistry $registry,
    RecordingAttendanceSink $attendance = new RecordingAttendanceSink,
    RecordingOperationLogSink $operations = new RecordingOperationLogSink,
    RecordingUserSink $users = new RecordingUserSink,
    RecordingAttendancePhotoSink $photos = new RecordingAttendancePhotoSink,
    RecordingBiometricSink $biometrics = new RecordingBiometricSink,
): CdataHandler {
    $legacy = new LegacyGeneration(
        new AttlogParser,
        new OperlogParser,
        new AttphotoParser,
        $attendance,
        $operations,
        $users,
        $photos,
    );

    $pushSdk = new PushSdkGeneration($legacy, new RtlogParser, new BiodataParser, $attendance, $biometrics);

    $selector = new GenerationSelector([
        ProtocolGeneration::Legacy->value => $legacy,
        ProtocolGeneration::PushV2->value => $pushSdk,
        ProtocolGeneration::PushV3->value => $pushSdk,
    ], $legacy);

    return new CdataHandler($registry, new Negotiator, $selector);
}

it('registers the device on handshake and returns its config block', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $handler = cdataHandler($registry);

    $response = $handler->handshake(new PushRequest('GET', 'iclock/cdata', [
        'SN' => 'SN1',
        'options' => 'all',
        'pushver' => '2.4.1',
    ]));

    expect($response->status)->toBe(200)
        ->and($response->body)->toContain('GET OPTION FROM: SN1')
        ->and($response->body)->toContain('Stamp=0')
        ->and($response->body)->toContain('Realtime=1');

    expect($registry->find('SN1'))->not->toBeNull()
        ->and($registry->find('SN1')->generation)->toBe(ProtocolGeneration::PushV2);
});

it('ingests an ATTLOG upload to the sink and advances the stamp', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $sink = new RecordingAttendanceSink;
    $handler = cdataHandler($registry, $sink);

    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'SN1']));

    $body = "1001\t2026-06-19 08:00:00\t0\t1\n1001\t2026-06-19 17:00:00\t1\t1\n";
    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'ATTLOG',
        'Stamp' => '999',
    ], $body));

    expect($response->body)->toBe('OK: 2')
        ->and($sink->received)->toHaveCount(2)
        ->and($sink->received[0]['serial'])->toBe('SN1')
        ->and($sink->received[0]['record']->userId)->toBe('1001')
        ->and($registry->find('SN1')->stampFor('ATTLOG')?->value)->toBe('999');
});

it('ingests an OPERLOG upload as operation log and user records and advances the stamp', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $operations = new RecordingOperationLogSink;
    $users = new RecordingUserSink;
    $handler = cdataHandler($registry, operations: $operations, users: $users);

    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'SN1']));

    $body = "OPLOG\t6\t1\t1001\t2026-06-19 09:00:00\nUSER PIN=1001\tName=Alice\tPri=14";
    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'OPERLOG',
        'Stamp' => '42',
    ], $body));

    expect($response->body)->toBe('OK: 2')
        ->and($operations->received)->toHaveCount(1)
        ->and($operations->received[0]['serial'])->toBe('SN1')
        ->and($operations->received[0]['entry']->operation)->toBe(OperationType::FingerprintEnrolled)
        ->and($users->received)->toHaveCount(1)
        ->and($users->received[0]['user']->userId)->toBe('1001')
        ->and($registry->find('SN1')->stampFor('OPERLOG')?->value)->toBe('42');
});

it('routes a standalone USERINFO upload to the user sink', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $users = new RecordingUserSink;
    $handler = cdataHandler($registry, users: $users);

    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'SN1']));

    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'USERINFO',
        'Stamp' => '7',
    ], "USER PIN=2002\tName=Bob\tPri=0"));

    expect($response->body)->toBe('OK: 1')
        ->and($users->received)->toHaveCount(1)
        ->and($users->received[0]['user']->userId)->toBe('2002')
        ->and($registry->find('SN1')->stampFor('USERINFO')?->value)->toBe('7');
});

it('ingests an ATTPHOTO upload to the photo sink', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $photos = new RecordingAttendancePhotoSink;
    $handler = cdataHandler($registry, photos: $photos);

    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'SN1']));

    $jpeg = "\xFF\xD8\xFF\xE0".'fake-jpeg';
    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'ATTPHOTO',
        'PIN' => '1001',
        'time' => '2026-06-19 09:00:00',
    ], $jpeg));

    expect($response->body)->toBe('OK: 1')
        ->and($photos->received)->toHaveCount(1)
        ->and($photos->received[0]['serial'])->toBe('SN1')
        ->and($photos->received[0]['photo']->userId)->toBe('1001')
        ->and($photos->received[0]['photo']->image)->toBe($jpeg);
});

it('ingests an RTLOG upload through the push-sdk generation and emits a punch', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $sink = new RecordingAttendanceSink;
    $handler = cdataHandler($registry, $sink);

    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'SN1', 'pushver' => '2.4.1']));

    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'RTLOG',
        'Stamp' => '88',
    ], "1001\t2026-06-19 08:00:00\t0\t1\n"));

    expect($response->body)->toBe('OK: 1')
        ->and($sink->received)->toHaveCount(1)
        ->and($sink->received[0]['record']->userId)->toBe('1001')
        ->and($registry->find('SN1')->stampFor('RTLOG')?->value)->toBe('88');
});

it('ingests a BIODATA upload through the push-sdk generation to the biometric sink', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $biometrics = new RecordingBiometricSink;
    $handler = cdataHandler($registry, biometrics: $biometrics);

    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'SN1', 'pushver' => '3.0.1']));

    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'BIODATA',
        'Stamp' => '12',
    ], "BIODATA Pin=1001\tType=1\tIndex=0\tValid=1\tTmp=QUJD"));

    expect($response->body)->toBe('OK: 1')
        ->and($biometrics->received)->toHaveCount(1)
        ->and($biometrics->received[0]['template']->userId)->toBe('1001')
        ->and($biometrics->received[0]['serial'])->toBe('SN1')
        ->and($registry->find('SN1')->stampFor('BIODATA')?->value)->toBe('12');
});

it('treats a PUSH-SDK table as an unknown no-op on a legacy device', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $biometrics = new RecordingBiometricSink;
    $handler = cdataHandler($registry, biometrics: $biometrics);

    // No pushver: the device negotiates to the legacy generation, which does not
    // own BIODATA — the table is accepted as a no-op and the stamp is not advanced.
    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'SN1']));

    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'BIODATA',
        'Stamp' => '12',
    ], "BIODATA Pin=1001\tType=1\tTmp=QUJD"));

    expect($response->body)->toBe('OK')
        ->and($biometrics->received)->toBe([])
        ->and($registry->find('SN1')->stampFor('BIODATA'))->toBeNull();
});

it('accepts an unrecognised table as a no-op without emitting anything', function () {
    $registry = new InMemoryDeviceRegistry(['SN1']);
    $attendance = new RecordingAttendanceSink;
    $operations = new RecordingOperationLogSink;
    $handler = cdataHandler($registry, $attendance, $operations);

    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'WURFL',
    ], "some\tunknown\tpayload"));

    expect($response->body)->toBe('OK')
        ->and($attendance->received)->toBe([])
        ->and($operations->received)->toBe([])
        ->and($registry->seen)->toContain('SN1');
});

it('holds a pending device upload instead of ingesting it', function () {
    $registry = new InMemoryDeviceRegistry(autoRegister: true);
    $operations = new RecordingOperationLogSink;
    $users = new RecordingUserSink;
    $handler = cdataHandler($registry, operations: $operations, users: $users);

    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'NEW1']));

    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'NEW1',
        'table' => 'OPERLOG',
        'Stamp' => '5',
    ], "USER PIN=1\tName=Held"));

    expect($response->status)->toBe(503)
        ->and($operations->received)->toBe([])
        ->and($users->received)->toBe([])
        ->and($registry->find('NEW1')->stampFor('OPERLOG'))->toBeNull();
});

it('ingests a device upload once it is approved', function () {
    $registry = new InMemoryDeviceRegistry(autoRegister: true);
    $sink = new RecordingAttendanceSink;
    $handler = cdataHandler($registry, $sink);

    $handler->handshake(new PushRequest('GET', 'iclock/cdata', ['SN' => 'NEW1']));
    $registry->approve('NEW1');

    $response = $handler->receiveData(new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'NEW1',
        'table' => 'ATTLOG',
        'Stamp' => '5',
    ], "1\t2026-06-20 08:00:00\t0\t1"));

    expect($response->body)->toBe('OK: 1')
        ->and($sink->received)->toHaveCount(1)
        ->and($registry->find('NEW1')->stampFor('ATTLOG')?->value)->toBe('5');
});
