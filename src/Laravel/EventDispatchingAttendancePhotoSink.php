<?php

declare(strict_types=1);

namespace ZkTeco\Laravel;

use ZkTeco\ADMS\AttendancePhotoSink;
use ZkTeco\Laravel\Events\AttendancePhotoReceived;
use ZkTeco\Values\AttendancePhoto;

/**
 * The bridge's {@see AttendancePhotoSink}: dispatches an
 * {@see AttendancePhotoReceived} event for each captured punch photo, leaving the
 * application to decide where the bytes are stored.
 *
 * The device serial number is passed as the event's connection identifier, since
 * that is how an ADMS device is addressed.
 */
final class EventDispatchingAttendancePhotoSink implements AttendancePhotoSink
{
    public function receive(AttendancePhoto $photo, string $serialNumber): void
    {
        event(new AttendancePhotoReceived($photo, $serialNumber));
    }
}
