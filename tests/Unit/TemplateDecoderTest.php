<?php

declare(strict_types=1);

use ZkTeco\TCP\Protocol\TemplateDecoder;

/**
 * Build one variable-length template record: <HHbb> header + opaque blob.
 * The leading size covers the 6-byte header plus the blob.
 */
function templateRecord(int $uid, int $fid, int $valid, string $blob): string
{
    $size = 6 + strlen($blob);

    return pack('v', $size).pack('v', $uid).pack('c', $fid).pack('c', $valid).$blob;
}

/**
 * Wrap records in the 4-byte total-size prefix the buffer carries.
 */
function templateBuffer(string ...$records): string
{
    $body = implode('', $records);

    return pack('V', strlen($body)).$body;
}

it('decodes consecutive variable-length template records', function () {
    $buffer = templateBuffer(
        templateRecord(uid: 1, fid: 0, valid: 1, blob: str_repeat("\x41", 50)),
        templateRecord(uid: 1, fid: 1, valid: 1, blob: str_repeat("\x42", 80)),
        templateRecord(uid: 2, fid: 0, valid: 0, blob: str_repeat("\x43", 30)),
    );

    $templates = TemplateDecoder::decode($buffer);

    expect($templates)->toHaveCount(3);

    expect($templates[0]->uid)->toBe(1)
        ->and($templates[0]->fingerIndex)->toBe(0)
        ->and($templates[0]->valid)->toBeTrue()
        ->and($templates[0]->data)->toBe(str_repeat("\x41", 50));

    expect($templates[1]->fingerIndex)->toBe(1)
        ->and($templates[1]->data)->toHaveLength(80);

    expect($templates[2]->uid)->toBe(2)
        ->and($templates[2]->valid)->toBeFalse()
        ->and($templates[2]->data)->toBe(str_repeat("\x43", 30));
});

it('returns an empty list when the buffer carries no data', function () {
    expect(TemplateDecoder::decode(pack('V', 0)))->toBe([])
        ->and(TemplateDecoder::decode(''))->toBe([]);
});

it('stops on a malformed record length instead of looping', function () {
    // A record claiming a size smaller than its own header.
    $body = pack('v', 3).pack('v', 1).pack('c', 0).pack('c', 1);
    $buffer = pack('V', strlen($body)).$body;

    expect(TemplateDecoder::decode($buffer))->toBe([]);
});
