<?php

declare(strict_types=1);

use ZkTeco\ADMS\Commands\DeviceCommand;
use ZkTeco\ADMS\Commands\Intents\ClearData;
use ZkTeco\ADMS\Commands\Intents\DeleteUser;
use ZkTeco\ADMS\Commands\Intents\PushTemplate;
use ZkTeco\ADMS\Commands\Intents\QueryData;
use ZkTeco\ADMS\Commands\Intents\Reboot;
use ZkTeco\ADMS\Commands\Intents\Restart;
use ZkTeco\ADMS\Commands\Intents\SyncTime;
use ZkTeco\ADMS\Commands\Intents\UpsertUser;
use ZkTeco\ADMS\Generations\LegacyGeneration;
use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Parsing\AttlogParser;
use ZkTeco\ADMS\Parsing\AttphotoParser;
use ZkTeco\ADMS\Parsing\OperlogParser;
use ZkTeco\ADMS\Registry\Capabilities;
use ZkTeco\ADMS\Registry\ProtocolGeneration;
use ZkTeco\ADMS\Registry\RegisteredDevice;
use ZkTeco\ADMS\Registry\Stamp;
use ZkTeco\Enums\OperationType;
use ZkTeco\Enums\Privilege;
use ZkTeco\Exceptions\CommandException;
use ZkTeco\Tests\Support\RecordingAttendancePhotoSink;
use ZkTeco\Tests\Support\RecordingAttendanceSink;
use ZkTeco\Tests\Support\RecordingOperationLogSink;
use ZkTeco\Tests\Support\RecordingUserSink;
use ZkTeco\Values\BiometricTemplate;
use ZkTeco\Values\User;

function legacyGeneration(
    RecordingAttendanceSink $attendance = new RecordingAttendanceSink,
    RecordingOperationLogSink $operations = new RecordingOperationLogSink,
    RecordingUserSink $users = new RecordingUserSink,
    RecordingAttendancePhotoSink $photos = new RecordingAttendancePhotoSink,
    string $nameEncoding = 'UTF-8',
): LegacyGeneration {
    return new LegacyGeneration(
        new AttlogParser,
        new OperlogParser,
        new AttphotoParser,
        $attendance,
        $operations,
        $users,
        $photos,
        $nameEncoding,
    );
}

function legacyPost(string $table, string $body): PushRequest
{
    return new PushRequest('POST', 'iclock/cdata', ['SN' => 'SN1', 'table' => $table], $body);
}

it('ingests ATTLOG to the attendance sink and reports the count', function () {
    $sink = new RecordingAttendanceSink;

    $outcome = legacyGeneration($sink)->ingest(
        'ATTLOG',
        legacyPost('ATTLOG', "1001\t2026-06-20 08:00:00\t0\t1\n1001\t2026-06-20 17:00:00\t1\t1\n"),
        'SN1',
    );

    expect($outcome->handled)->toBeTrue()
        ->and($outcome->count)->toBe(2)
        ->and($sink->received)->toHaveCount(2)
        ->and($sink->received[0]['serial'])->toBe('SN1');
});

it('ingests OPERLOG to the operation-log and user sinks', function () {
    $operations = new RecordingOperationLogSink;
    $users = new RecordingUserSink;

    $outcome = legacyGeneration(operations: $operations, users: $users)->ingest(
        'OPERLOG',
        legacyPost('OPERLOG', "OPLOG\t6\t1\t1001\t2026-06-20 09:00:00\nUSER PIN=1001\tName=Alice\tPri=14"),
        'SN1',
    );

    expect($outcome->count)->toBe(2)
        ->and($operations->received)->toHaveCount(1)
        ->and($operations->received[0]['entry']->operation)->toBe(OperationType::FingerprintEnrolled)
        ->and($users->received)->toHaveCount(1)
        ->and($users->received[0]['user']->userId)->toBe('1001');
});

it('routes a standalone USERINFO upload to the user sink', function () {
    $users = new RecordingUserSink;

    $outcome = legacyGeneration(users: $users)->ingest(
        'USERINFO',
        legacyPost('USERINFO', "USER PIN=2002\tName=Bob\tPri=0"),
        'SN1',
    );

    expect($outcome->count)->toBe(1)
        ->and($users->received[0]['user']->userId)->toBe('2002');
});

it('ingests ATTPHOTO to the photo sink', function () {
    $photos = new RecordingAttendancePhotoSink;
    $jpeg = "\xFF\xD8\xFF\xE0".'fake-jpeg';

    $request = new PushRequest('POST', 'iclock/cdata', [
        'SN' => 'SN1',
        'table' => 'ATTPHOTO',
        'PIN' => '1001',
        'time' => '2026-06-20 09:00:00',
    ], $jpeg);

    $outcome = legacyGeneration(photos: $photos)->ingest('ATTPHOTO', $request, 'SN1');

    expect($outcome->count)->toBe(1)
        ->and($photos->received[0]['photo']->userId)->toBe('1001');
});

it('ignores an unknown table without touching a sink', function () {
    $attendance = new RecordingAttendanceSink;
    $operations = new RecordingOperationLogSink;

    $outcome = legacyGeneration($attendance, $operations)->ingest(
        'RTLOG',
        legacyPost('RTLOG', "1001\t2026-06-20 08:00:00\t0\t1"),
        'SN1',
    );

    expect($outcome->handled)->toBeFalse()
        ->and($outcome->count)->toBe(0)
        ->and($attendance->received)->toBe([])
        ->and($operations->received)->toBe([]);
});

it('builds the GET OPTION FROM config block from the device stamps', function () {
    $device = new RegisteredDevice(
        serialNumber: 'SN1',
        generation: ProtocolGeneration::Legacy,
        capabilities: new Capabilities,
        stamps: ['ATTLOG' => new Stamp('ATTLOG', '123')],
    );

    $block = legacyGeneration()->configBlock($device);

    expect($block)->toContain('GET OPTION FROM: SN1')
        ->and($block)->toContain('Stamp=123')
        ->and($block)->toContain('Realtime=1');
});

it('renders reboot and restart to the single ADMS reboot verb', function () {
    expect(legacyGeneration()->renderCommand(new Reboot))->toBe('REBOOT')
        ->and(legacyGeneration()->renderCommand(new Restart))->toBe('REBOOT');
});

it('renders the clear and query control commands', function () {
    expect(legacyGeneration()->renderCommand(new ClearData))->toBe('CLEAR DATA')
        ->and(legacyGeneration()->renderCommand(new QueryData('USERINFO')))->toBe('DATA QUERY USERINFO');
});

it('renders a user delete keyed by PIN', function () {
    expect(legacyGeneration()->renderCommand(new DeleteUser('1001')))
        ->toBe('DATA DELETE USERINFO PIN=1001');
});

it('renders a user upsert as a USERINFO update', function () {
    $command = new UpsertUser(new User(uid: 7, userId: '1001', name: 'Alice', privilege: Privilege::Admin));

    $wire = legacyGeneration()->renderCommand($command);

    expect($wire)->toStartWith('DATA UPDATE USERINFO ')
        ->and($wire)->toContain('PIN=1001')
        ->and($wire)->toContain('Name=Alice')
        ->and($wire)->toContain('Pri=14')
        ->and($wire)->not->toContain('7'); // the device-local uid is not part of the push
});

it('encodes the user name to the device codepage on a USERINFO upsert', function () {
    $command = new UpsertUser(new User(uid: 0, userId: '1001', name: 'محمد', privilege: Privilege::User));

    $wire = legacyGeneration(nameEncoding: 'Windows-1256')->renderCommand($command);

    // The name rides the wire as single-byte CP1256, not 2-byte UTF-8, so the
    // panel reads it through its own codepage without mojibake.
    expect($wire)->toContain('Name='.hex2bin('e3cde3cf'))
        ->and($wire)->not->toContain('Name=محمد');
});

it('leaves the user name as UTF-8 when no device codepage is configured', function () {
    $command = new UpsertUser(new User(uid: 0, userId: '1001', name: 'محمد', privilege: Privilege::User));

    expect(legacyGeneration()->renderCommand($command))->toContain('Name=محمد');
});

it('renders a template push to the legacy FINGERTMP table', function () {
    $command = new PushTemplate(new BiometricTemplate(userId: '1001', type: 1, index: 0, valid: true, data: 'QUJD'));

    $wire = legacyGeneration()->renderCommand($command);

    expect($wire)->toStartWith('DATA UPDATE FINGERTMP ')
        ->and($wire)->toContain('PIN=1001')
        ->and($wire)->toContain('TMP=QUJD');
});

it('renders sync time as a SET OPTIONS instruction', function () {
    $wire = legacyGeneration()->renderCommand(new SyncTime(new DateTimeImmutable('2026-06-20 08:30:00')));

    expect($wire)->toStartWith('SET OPTIONS DateTime=');
});

it('throws for a command intent it has no renderer for', function () {
    $unknown = new class implements DeviceCommand {};

    legacyGeneration()->renderCommand($unknown);
})->throws(CommandException::class);
