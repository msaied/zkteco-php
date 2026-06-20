<?php

declare(strict_types=1);

use ZkTeco\Exceptions\ResponseException;
use ZkTeco\TCP\Protocol\Codec;
use ZkTeco\TCP\Protocol\Command;
use ZkTeco\TCP\Protocol\TemplateEncoder;
use ZkTeco\TCP\Services\TemplateService;
use ZkTeco\Tests\Support\FakeTransport;
use ZkTeco\Values\Template;
use ZkTeco\Values\User;

it('reads all templates end to end', function () {
    $buffer = templateBuffer(
        templateRecord(uid: 1, fid: 0, valid: 1, blob: str_repeat("\x41", 40)),
        templateRecord(uid: 2, fid: 3, valid: 1, blob: str_repeat("\x42", 40)),
    );

    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),                                       // connect
        responsePacket(Command::AckOk, sessionId: 1, payload: freeSizes(0, 0, fingers: 2)), // read sizes
        responsePacket(Command::Data, sessionId: 1, payload: $buffer),                      // template buffer (inline)
    ]);

    $templates = (new TemplateService(openedSession($transport)))->all();

    expect($templates)->toHaveCount(2)
        ->and($templates[0]->uid)->toBe(1)
        ->and($templates[1]->fingerIndex)->toBe(3);
});

it('returns no templates when the device reports zero fingers', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckOk, sessionId: 1, payload: freeSizes(0, 0)),
    ]);

    expect((new TemplateService(openedSession($transport)))->all())->toBe([]);
});

it('filters templates for a single user', function () {
    $buffer = templateBuffer(
        templateRecord(uid: 1, fid: 0, valid: 1, blob: 'aaaa'),
        templateRecord(uid: 2, fid: 0, valid: 1, blob: 'bbbb'),
        templateRecord(uid: 2, fid: 1, valid: 1, blob: 'cccc'),
    );

    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckOk, sessionId: 1, payload: freeSizes(0, 0, fingers: 3)),
        responsePacket(Command::Data, sessionId: 1, payload: $buffer),
    ]);

    $templates = (new TemplateService(openedSession($transport)))->forUser(2);

    expect($templates)->toHaveCount(2)
        ->and($templates[0]->uid)->toBe(2)
        ->and($templates[1]->uid)->toBe(2);
});

it('deletes a single finger template', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),  // connect
        responsePacket(Command::AckOk, sessionId: 1),  // delete
    ]);

    (new TemplateService(openedSession($transport)))->delete(uid: 7, fingerIndex: 2);

    $sent = (new Codec)->parse($transport->sent[1]);
    expect($sent->command)->toBe(Command::DeleteUserTemp->value)
        ->and($sent->payload)->toBe(pack('v', 7).pack('c', 2));
});

it('throws when a template delete is rejected', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),
        responsePacket(Command::AckError, sessionId: 1),
    ]);

    expect(fn () => (new TemplateService(openedSession($transport)))->delete(1, 0))
        ->toThrow(ResponseException::class);
});

it('uploads templates through the buffer then refreshes the device tables', function () {
    $user = new User(uid: 7, userId: '99', name: 'Bob');
    $templates = [new Template(uid: 7, fingerIndex: 0, valid: true, data: 'AAAA')];
    $payload = TemplateEncoder::encode($user, $templates);

    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1), // connect
        responsePacket(Command::AckOk, sessionId: 1), // free data
        responsePacket(Command::AckOk, sessionId: 1), // prepare data
        responsePacket(Command::AckOk, sessionId: 1), // data chunk
        responsePacket(Command::AckOk, sessionId: 1), // save usertemps
        responsePacket(Command::AckOk, sessionId: 1), // refresh data
    ]);

    (new TemplateService(openedSession($transport)))->upload($user, $templates);

    $codec = new Codec;
    expect($transport->sent)->toHaveCount(6);
    expect($codec->parse($transport->sent[1])->command)->toBe(Command::FreeData->value);

    $prepare = $codec->parse($transport->sent[2]);
    expect($prepare->command)->toBe(Command::PrepareData->value)
        ->and($prepare->payload)->toBe(pack('V', strlen($payload)));

    $data = $codec->parse($transport->sent[3]);
    expect($data->command)->toBe(Command::Data->value)
        ->and($data->payload)->toBe($payload);

    $save = $codec->parse($transport->sent[4]);
    expect($save->command)->toBe(Command::SaveUserTemps->value)
        ->and($save->payload)->toBe(pack('V', 12).pack('v', 0).pack('v', 8));

    expect($codec->parse($transport->sent[5])->command)->toBe(Command::RefreshData->value);
});

it('splits a large upload into 1024-byte data chunks', function () {
    $user = new User(uid: 1, userId: '1', name: 'X');
    $templates = [new Template(uid: 1, fingerIndex: 0, valid: true, data: str_repeat("\x41", 3000))];
    $payload = TemplateEncoder::encode($user, $templates);
    $chunks = (int) ceil(strlen($payload) / 1024);

    $responses = array_fill(0, 2 + $chunks + 2 + 1, responsePacket(Command::AckOk, sessionId: 1));
    $transport = new FakeTransport($responses);

    (new TemplateService(openedSession($transport)))->upload($user, $templates);

    $codec = new Codec;
    $dataChunks = array_values(array_filter(
        array_map(fn (string $raw) => $codec->parse($raw), $transport->sent),
        fn ($packet) => $packet->command === Command::Data->value,
    ));

    expect($dataChunks)->toHaveCount($chunks);
    expect(implode('', array_map(fn ($packet) => $packet->payload, $dataChunks)))->toBe($payload);
});

it('does nothing when uploading an empty template list', function () {
    $transport = new FakeTransport([responsePacket(Command::AckOk, sessionId: 1)]); // connect only

    (new TemplateService(openedSession($transport)))->upload(new User(uid: 1, userId: '1', name: 'X'), []);

    expect($transport->sent)->toHaveCount(1);
});

it('throws when a template upload is rejected', function () {
    $transport = new FakeTransport([
        responsePacket(Command::AckOk, sessionId: 1),    // connect
        responsePacket(Command::AckOk, sessionId: 1),    // free data
        responsePacket(Command::AckOk, sessionId: 1),    // prepare data
        responsePacket(Command::AckOk, sessionId: 1),    // data chunk
        responsePacket(Command::AckError, sessionId: 1), // save rejected
    ]);

    $service = new TemplateService(openedSession($transport));

    expect(fn () => $service->upload(
        new User(uid: 1, userId: '1', name: 'X'),
        [new Template(uid: 1, fingerIndex: 0, valid: true, data: 'AAAA')],
    ))->toThrow(ResponseException::class);
});
