<?php

declare(strict_types=1);

use ZkTeco\TCP\Device;

it('completes the handshake and reads device metadata', function () {
    $info = integrationDevice()->session(fn (Device $d) => [
        'name' => $d->info()->name(),
        'firmware' => $d->info()->firmwareVersion(),
        'serial' => $d->info()->serialNumber(),
        'time' => $d->info()->time(),
    ]);

    expect($info['firmware'])->toBeString()->not->toBe('');
    expect($info['serial'])->toBeString()->not->toBe('');
    expect($info['time'])->toBeInstanceOf(DateTimeImmutable::class);
});

it('reads users and attendance through the buffered protocol', function () {
    [$users, $attendance] = integrationDevice()->session(fn (Device $d) => [
        $d->users()->all(),
        $d->attendance()->all(),
    ]);

    expect($users)->toBeArray();
    expect($attendance)->toBeArray();
});
