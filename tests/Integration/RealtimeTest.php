<?php

declare(strict_types=1);

use ZkTeco\Values\AttendanceRecord;

it('registers for live events and yields a valid first item', function () {
    // A short timeout keeps the idle heartbeat fast: with no punch, the stream
    // yields null on the read timeout. A real punch during the window yields an
    // AttendanceRecord instead. Either proves the realtime loop works on hardware.
    $device = integrationDevice(timeout: 2.0)->connect();

    try {
        $stream = $device->realtime()->live();
        $stream->rewind();

        $first = $stream->current();

        expect($first === null || $first instanceof AttendanceRecord)->toBeTrue();

        // Trigger the generator's finally so the event subscription is cleared
        // before we drop the connection.
        unset($stream);
    } finally {
        $device->disconnect();
    }
});
