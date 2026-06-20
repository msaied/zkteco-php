<?php

declare(strict_types=1);

use ZkTeco\ADMS\Commands\Intents\PushTemplate;
use ZkTeco\ADMS\Commands\Intents\Reboot;
use ZkTeco\ADMS\Commands\Intents\UpsertUser;
use ZkTeco\ADMS\Generations\LegacyGeneration;
use ZkTeco\ADMS\Generations\PushSdkGeneration;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Parsing\AttlogParser;
use ZkTeco\ADMS\Parsing\AttphotoParser;
use ZkTeco\ADMS\Parsing\BiodataParser;
use ZkTeco\ADMS\Parsing\OperlogParser;
use ZkTeco\ADMS\Parsing\RtlogParser;
use ZkTeco\ADMS\Registry\Capabilities;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\Tests\Support\RecordingAttendancePhotoSink;
use ZkTeco\Tests\Support\RecordingAttendanceSink;
use ZkTeco\Tests\Support\RecordingBiometricSink;
use ZkTeco\Tests\Support\RecordingOperationLogSink;
use ZkTeco\Tests\Support\RecordingUserSink;
use ZkTeco\Values\BiometricTemplate;
use ZkTeco\Values\User;

/**
 * @param  array{attendance?: RecordingAttendanceSink, biometric?: RecordingBiometricSink, operations?: RecordingOperationLogSink, users?: RecordingUserSink}  $sinks
 */
function pushSdkGeneration(array $sinks = []): PushSdkGeneration
{
    $attendance = $sinks['attendance'] ?? new RecordingAttendanceSink;
    $biometric = $sinks['biometric'] ?? new RecordingBiometricSink;

    $legacy = new LegacyGeneration(
        new AttlogParser,
        new OperlogParser,
        new AttphotoParser,
        $attendance,
        $sinks['operations'] ?? new RecordingOperationLogSink,
        $sinks['users'] ?? new RecordingUserSink,
        new RecordingAttendancePhotoSink,
    );

    return new PushSdkGeneration($legacy, new RtlogParser, new BiodataParser, $attendance, $biometric);
}

function pushPost(string $table, string $body): PushRequest
{
    return new PushRequest('POST', 'iclock/cdata', ['SN' => 'SN1', 'table' => $table], $body);
}

function pushDevice(): RegisteredDevice
{
    return new RegisteredDevice('SN1', ProtocolGeneration::PushV2, new Capabilities);
}

it('ingests RTLOG to the attendance sink so it joins the unified read path', function () {
    $attendance = new RecordingAttendanceSink;

    $outcome = pushSdkGeneration(['attendance' => $attendance])->ingest(
        'RTLOG',
        pushPost('RTLOG', "1001\t2026-06-20 08:00:00\t0\t1\n"),
        'SN1',
    );

    expect($outcome->handled)->toBeTrue()
        ->and($outcome->count)->toBe(1)
        ->and($attendance->received)->toHaveCount(1)
        ->and($attendance->received[0]['record']->userId)->toBe('1001');
});

it('ingests BIODATA to the biometric sink', function () {
    $biometric = new RecordingBiometricSink;

    $outcome = pushSdkGeneration(['biometric' => $biometric])->ingest(
        'BIODATA',
        pushPost('BIODATA', "BIODATA Pin=1001\tType=1\tIndex=0\tValid=1\tTmp=QUJD"),
        'SN1',
    );

    expect($outcome->count)->toBe(1)
        ->and($biometric->received)->toHaveCount(1)
        ->and($biometric->received[0]['template']->userId)->toBe('1001')
        ->and($biometric->received[0]['serial'])->toBe('SN1');
});

it('delegates the legacy tables to the composed legacy generation', function () {
    $attendance = new RecordingAttendanceSink;
    $users = new RecordingUserSink;

    $generation = pushSdkGeneration(['attendance' => $attendance, 'users' => $users]);

    $attlog = $generation->ingest('ATTLOG', pushPost('ATTLOG', "1001\t2026-06-20 08:00:00\t0\t1"), 'SN1');
    $userinfo = $generation->ingest('USERINFO', pushPost('USERINFO', "USER PIN=2002\tName=Bob"), 'SN1');

    expect($attlog->count)->toBe(1)
        ->and($attendance->received)->toHaveCount(1)
        ->and($userinfo->count)->toBe(1)
        ->and($users->received[0]['user']->userId)->toBe('2002');
});

it('ignores a table neither legacy nor push-sdk owns', function () {
    $outcome = pushSdkGeneration()->ingest('WURFL', pushPost('WURFL', 'whatever'), 'SN1');

    expect($outcome->handled)->toBeFalse()
        ->and($outcome->count)->toBe(0);
});

it('extends the legacy config block with the push-sdk channels', function () {
    $block = pushSdkGeneration()->configBlock(pushDevice());

    expect($block)->toContain('GET OPTION FROM: SN1')
        ->and($block)->toContain('Realtime=1')
        ->and($block)->toContain('BioDataFun=1')
        ->and($block)->toContain('RtDataFun=1');
});

it('returns a registry block echoing the serial as the registry code', function () {
    expect(pushSdkGeneration()->registryBlock(pushDevice()))->toContain('RegistryCode=SN1');
});

it('delegates control commands to the composed legacy generation', function () {
    expect(pushSdkGeneration()->renderCommand(new Reboot))->toBe('REBOOT');
});

it('delegates the USERINFO upsert to legacy, since the form is shared', function () {
    $command = new UpsertUser(new User(uid: 0, userId: '1001', name: 'Alice'));

    expect(pushSdkGeneration()->renderCommand($command))->toStartWith('DATA UPDATE USERINFO ');
});

it('overrides the template push to the PUSH-SDK BIODATA table', function () {
    $command = new PushTemplate(new BiometricTemplate(userId: '1001', type: 2, index: 0, valid: true, data: 'QUJD'));

    $wire = pushSdkGeneration()->renderCommand($command);

    expect($wire)->toStartWith('DATA UPDATE BIODATA ')
        ->and($wire)->toContain('Pin=1001')
        ->and($wire)->toContain('Type=2')
        ->and($wire)->toContain('Tmp=QUJD')
        ->and($wire)->not->toContain('FINGERTMP');
});
