<?php

declare(strict_types=1);

use ZkTeco\ADMS\Parsing\BiodataParser;

it('parses a BIODATA row into a PIN-keyed biometric template', function () {
    $templates = (new BiodataParser)->parse("BIODATA Pin=1001\tNo=0\tIndex=2\tValid=1\tType=1\tTmp=QUJD");

    expect($templates)->toHaveCount(1)
        ->and($templates[0]->userId)->toBe('1001')
        ->and($templates[0]->type)->toBe(1)
        ->and($templates[0]->index)->toBe(2)
        ->and($templates[0]->valid)->toBeTrue()
        ->and($templates[0]->data)->toBe('QUJD');
});

it('treats Valid=0 as an invalid template', function () {
    $templates = (new BiodataParser)->parse("Pin=1001\tValid=0\tType=1\tTmp=QUJD");

    expect($templates[0]->valid)->toBeFalse();
});

it('defaults type and index to zero when absent', function () {
    $templates = (new BiodataParser)->parse("Pin=1001\tTmp=QUJD");

    expect($templates[0]->type)->toBe(0)
        ->and($templates[0]->index)->toBe(0)
        ->and($templates[0]->valid)->toBeTrue();
});

it('falls back to the legacy No column for the index', function () {
    $templates = (new BiodataParser)->parse("Pin=1001\tNo=5\tTmp=QUJD");

    expect($templates[0]->index)->toBe(5);
});

it('parses fingerprint, face, and palm type codes', function (int $code) {
    $templates = (new BiodataParser)->parse("Pin=1001\tType={$code}\tTmp=QUJD");

    expect($templates[0]->type)->toBe($code);
})->with([1, 2, 9]);

it('reads the template blob from either Tmp or TmpData', function () {
    $templates = (new BiodataParser)->parse("Pin=1001\tTmpData=WFla");

    expect($templates[0]->data)->toBe('WFla');
});

it('drops a row with no PIN or no template data', function () {
    $body = "Type=1\tTmp=QUJD\nPin=1001\tType=1\n";

    expect((new BiodataParser)->parse($body))->toBe([]);
});

it('keeps the PIN a string and never derives a uid from it', function () {
    $templates = (new BiodataParser)->parse("Pin=007\tTmp=QUJD");

    expect($templates[0]->userId)->toBe('007')
        ->and($templates[0])->not->toHaveProperty('uid');
});

it('returns an empty list for an empty body', function () {
    expect((new BiodataParser)->parse(''))->toBe([]);
});
