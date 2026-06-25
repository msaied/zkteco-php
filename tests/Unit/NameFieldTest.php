<?php

declare(strict_types=1);

use ZkTeco\TCP\Protocol\NameField;

it('packs an ASCII name unchanged under the default UTF-8 encoding', function () {
    expect(NameField::pack('Alice', 24))->toBe(str_pad('Alice', 24, "\0"))
        ->and(strlen(NameField::pack('Alice', 24)))->toBe(24);
});

it('re-encodes an Arabic name to the device codepage on the way out', function () {
    $packed = NameField::pack('محمد', 24, 'Windows-1256');

    // CP1256 stores each Arabic letter in a single byte, not the 2-byte UTF-8
    // sequence — this is what stops the panel showing mojibake.
    expect(bin2hex(rtrim($packed, "\0")))->toBe('e3cde3cf')
        ->and(strlen($packed))->toBe(24);
});

it('re-encodes a name to the codepage with no padding or truncation', function () {
    // The variable-length ADMS text field uses this rather than pack(): same
    // CP1256 bytes, but no fixed-width NUL padding.
    expect(bin2hex(NameField::toCodepage('محمد', 'Windows-1256')))->toBe('e3cde3cf')
        ->and(NameField::toCodepage('Alice', 'Windows-1256'))->toBe('Alice');
});

it('leaves a name untouched when the codepage is UTF-8', function () {
    expect(NameField::toCodepage('محمد', 'UTF-8'))->toBe('محمد');
});

it('round-trips a non-ASCII name through pack then unpack', function () {
    $name = 'محمد علي';

    $decoded = NameField::unpack(NameField::pack($name, 24, 'Windows-1256'), 'Windows-1256');

    expect($decoded)->toBe($name);
});

it('reads UTF-8 bytes raw as garbage when the device codepage is wrong', function () {
    // Reproduces the bug: UTF-8 bytes interpreted as CP1256 do not come back as
    // the original Arabic.
    $utf8Bytes = str_pad('محمد', 24, "\0");

    expect(NameField::unpack($utf8Bytes, 'Windows-1256'))->not->toBe('محمد');
});

it('truncates a CP1256 name to the field width on a character boundary', function () {
    // Ten Arabic letters = 10 bytes in CP1256; a 5-byte field keeps five whole
    // letters and drops the rest.
    $name = 'ابتثجحخدذر';

    $packed = NameField::pack($name, 5, 'Windows-1256');
    $decoded = NameField::unpack($packed, 'Windows-1256');

    expect(strlen($packed))->toBe(5)
        ->and(mb_strlen($decoded))->toBe(5)
        ->and($decoded)->toBe(mb_substr($name, 0, 5));
});

it('truncates UTF-8 names on a character boundary too', function () {
    // Six 2-byte characters into a 9-byte field: the old byte-wise cut would
    // leave 8 bytes ending mid-character; we keep 4 whole characters (8 bytes).
    $name = 'مممممم';

    $decoded = NameField::unpack(NameField::pack($name, 9));

    expect($decoded)->toBe('مممم');
});
