<?php

declare(strict_types=1);

use ZkTeco\ADMS\Http\PushRequest;
use ZkTeco\ADMS\Parsing\AttphotoParser;

function attphotoRequest(array $query, string $body): PushRequest
{
    return new PushRequest('POST', 'iclock/cdata', $query, $body);
}

$jpeg = "\xFF\xD8\xFF\xE0".'JFIF-fake-image-bytes';

it('reads PIN and time from the query and takes a bare JPEG body as the image', function () use ($jpeg) {
    $photo = (new AttphotoParser)->parse(attphotoRequest([
        'SN' => 'SN1',
        'table' => 'ATTPHOTO',
        'PIN' => '1001',
        'time' => '2026-06-19 09:00:00',
    ], $jpeg));

    expect($photo)->not->toBeNull()
        ->and($photo->userId)->toBe('1001')
        ->and($photo->capturedAt->format('Y-m-d H:i:s'))->toBe('2026-06-19 09:00:00')
        ->and($photo->image)->toBe($jpeg)
        ->and($photo->contentType)->toBe('image/jpeg');
});

it('falls back to a body header line when the query has no metadata', function () {
    $body = "PIN=2002\ttime=2026-06-19 10:00:00\tsize=4\nDATA";

    $photo = (new AttphotoParser)->parse(attphotoRequest(['SN' => 'SN1', 'table' => 'ATTPHOTO'], $body));

    expect($photo->userId)->toBe('2002')
        ->and($photo->capturedAt->format('Y-m-d H:i:s'))->toBe('2026-06-19 10:00:00')
        ->and($photo->image)->toBe('DATA');
});

it('prefers the query PIN over a body header PIN', function () use ($jpeg) {
    $photo = (new AttphotoParser)->parse(attphotoRequest([
        'PIN' => 'fromquery',
    ], "PIN=fromheader\n".$jpeg));

    expect($photo->userId)->toBe('fromquery');
});

it('returns null when there is no PIN', function () use ($jpeg) {
    expect((new AttphotoParser)->parse(attphotoRequest(['table' => 'ATTPHOTO'], $jpeg)))->toBeNull();
});

it('returns null when there are no image bytes', function () {
    expect((new AttphotoParser)->parse(attphotoRequest(['PIN' => '1001'], '')))->toBeNull();
});

it('leaves capturedAt null when no usable timestamp is sent', function () use ($jpeg) {
    $photo = (new AttphotoParser)->parse(attphotoRequest(['PIN' => '1001'], $jpeg));

    expect($photo->userId)->toBe('1001')
        ->and($photo->capturedAt)->toBeNull();
});
