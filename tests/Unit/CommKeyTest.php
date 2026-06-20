<?php

declare(strict_types=1);

use ZkTeco\TCP\Protocol\CommKey;

it('scrambles the zero comm key against the fixed tick', function () {
    // Hand-derived from pyzk's make_commkey(0, 0): "ZKSO" XOR then tick (50) XOR.
    expect((new CommKey(0, 0))->token())->toBe("\x61\x7d\x32\x79");
});

it('reverses the comm-key bits before scrambling', function () {
    // make_commkey(1, 0): bit 0 reverses to bit 31 (0x80000000) before the
    // XOR passes, so the token differs from the zero-key case.
    expect((new CommKey(1, 0))->token())->toBe("\x61\xfd\x32\x79");
});

it('produces a four-byte token', function () {
    expect((new CommKey(123456, 789))->token())->toHaveLength(4);
});
